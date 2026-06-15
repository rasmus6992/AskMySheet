<?php

declare(strict_types=1);

namespace TalkToExcel;

use PDO;

final class RateLimiter
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function reserveUpload(string $ipHash): bool
    {
        return $this->reserve(
            $ipHash,
            'upload_count',
            'last_upload_at',
            Env::int('MAX_UPLOADS_PER_IP', 1)
        );
    }

    public function releaseUpload(string $ipHash): void
    {
        $this->release($ipHash, 'upload_count');

        // If the first upload failed and no questions were used, remove the
        // empty window so the next valid upload receives a full hour.
        $statement = $this->pdo->prepare(
            'UPDATE ip_usage
             SET window_started_at = NULL,
                 last_upload_at = NULL,
                 last_seen_at = UTC_TIMESTAMP()
             WHERE ip_hash = ?
               AND upload_count = 0
               AND question_count = 0'
        );
        $statement->execute([$ipHash]);
    }

    public function reserveQuestion(string $ipHash): bool
    {
        return $this->reserve(
            $ipHash,
            'question_count',
            'last_question_at',
            Env::int('MAX_QUESTIONS_PER_IP', 10)
        );
    }

    public function releaseQuestion(string $ipHash): void
    {
        $this->release($ipHash, 'question_count');
    }

    /**
     * @return array{
     *     upload_count:int,
     *     question_count:int,
     *     window_started_at:?string,
     *     reset_at:?string
     * }
     */
    public function usage(string $ipHash): array
    {
        $windowMinutes = max(1, Env::int('RATE_LIMIT_WINDOW_MINUTES', 60));

        $this->pdo->beginTransaction();
        try {
            $this->ensureRow($ipHash);
            $row = $this->lockedUsageRow($ipHash);
            $row = $this->resetExpiredWindow($ipHash, $row, $windowMinutes);
            $this->pdo->commit();

            $windowStartedAt = isset($row['window_started_at'])
                ? (string) $row['window_started_at']
                : null;

            return [
                'upload_count' => (int) ($row['upload_count'] ?? 0),
                'question_count' => (int) ($row['question_count'] ?? 0),
                'window_started_at' => $windowStartedAt,
                'reset_at' => $windowStartedAt === null
                    ? null
                    : gmdate('Y-m-d H:i:s', strtotime($windowStartedAt . ' UTC') + ($windowMinutes * 60)),
            ];
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function reserve(string $ipHash, string $counterColumn, string $timestampColumn, int $limit): bool
    {
        if (!in_array($counterColumn, ['upload_count', 'question_count'], true)
            || !in_array($timestampColumn, ['last_upload_at', 'last_question_at'], true)) {
            throw new \InvalidArgumentException('Invalid rate-limit column.');
        }

        $limit = max(1, $limit);
        $windowMinutes = max(1, Env::int('RATE_LIMIT_WINDOW_MINUTES', 60));

        $this->pdo->beginTransaction();
        try {
            $this->ensureRow($ipHash);
            $row = $this->lockedUsageRow($ipHash);
            $row = $this->resetExpiredWindow($ipHash, $row, $windowMinutes);

            if ((int) ($row[$counterColumn] ?? 0) >= $limit) {
                $this->pdo->commit();
                return false;
            }

            // The first successful reservation starts the one-hour window.
            $startWindowSql = empty($row['window_started_at'])
                ? ', window_started_at = UTC_TIMESTAMP()'
                : '';

            $sql = "UPDATE ip_usage
                    SET {$counterColumn} = {$counterColumn} + 1,
                        {$timestampColumn} = UTC_TIMESTAMP(),
                        last_seen_at = UTC_TIMESTAMP()
                        {$startWindowSql}
                    WHERE ip_hash = ?";

            $statement = $this->pdo->prepare($sql);
            $statement->execute([$ipHash]);
            $this->pdo->commit();
            return true;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function release(string $ipHash, string $counterColumn): void
    {
        if (!in_array($counterColumn, ['upload_count', 'question_count'], true)) {
            throw new \InvalidArgumentException('Invalid rate-limit column.');
        }

        $statement = $this->pdo->prepare(
            "UPDATE ip_usage
             SET {$counterColumn} = GREATEST({$counterColumn} - 1, 0),
                 last_seen_at = UTC_TIMESTAMP()
             WHERE ip_hash = ?"
        );
        $statement->execute([$ipHash]);
    }

    /** @return array<string, mixed> */
    private function lockedUsageRow(string $ipHash): array
    {
        $statement = $this->pdo->prepare(
            'SELECT upload_count, question_count, window_started_at
             FROM ip_usage
             WHERE ip_hash = ?
             FOR UPDATE'
        );
        $statement->execute([$ipHash]);
        $row = $statement->fetch();

        return is_array($row) ? $row : [];
    }

    /**
     * Reset both allowances once the configured window has elapsed.
     * The reset is lazy: it occurs on the next status, upload, or question request.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function resetExpiredWindow(string $ipHash, array $row, int $windowMinutes): array
    {
        $windowStartedAt = $row['window_started_at'] ?? null;
        if (!is_string($windowStartedAt) || $windowStartedAt === '') {
            return $row;
        }

        $startedTimestamp = strtotime($windowStartedAt . ' UTC');
        if ($startedTimestamp === false || $startedTimestamp + ($windowMinutes * 60) > time()) {
            return $row;
        }

        $statement = $this->pdo->prepare(
            'UPDATE ip_usage
             SET upload_count = 0,
                 question_count = 0,
                 window_started_at = NULL,
                 last_upload_at = NULL,
                 last_question_at = NULL,
                 last_seen_at = UTC_TIMESTAMP()
             WHERE ip_hash = ?'
        );
        $statement->execute([$ipHash]);

        // Keep the window empty until the next upload. That upload becomes
        // the first action and starts a fresh one-hour allowance window.
        $row['upload_count'] = 0;
        $row['question_count'] = 0;
        $row['window_started_at'] = null;

        return $row;
    }

    private function ensureRow(string $ipHash): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO ip_usage (ip_hash, first_seen_at, last_seen_at)
             VALUES (?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE last_seen_at = UTC_TIMESTAMP()'
        );
        $statement->execute([$ipHash]);
    }
}
