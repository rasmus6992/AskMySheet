<?php

declare(strict_types=1);

namespace TalkToExcel;

use PDO;

final class UploadRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(
        string $ipHash,
        string $sessionTokenHash,
        string $originalName,
        string $extension,
        int $fileSize
    ): int {
        $retentionHours = max(1, Env::int('CONTEXT_RETENTION_HOURS', 24));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($retentionHours * 3600));
        $statement = $this->pdo->prepare(
            'INSERT INTO uploads
                (ip_hash, session_token_hash, original_name, file_extension, file_size, status, expires_at)
             VALUES (?, ?, ?, ?, ?, \'processing\', ?)'
        );
        $statement->execute([$ipHash, $sessionTokenHash, $originalName, $extension, $fileSize, $expiresAt]);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array{row_count:int,sheet_count:int,context_bytes:int,is_truncated:bool} $metadata */
    public function markReady(int $uploadId, array $metadata): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE uploads
             SET row_count = ?, sheet_count = ?, context_bytes = ?, is_truncated = ?, status = \'ready\', error_code = NULL
             WHERE id = ?'
        );
        $statement->execute([
            $metadata['row_count'],
            $metadata['sheet_count'],
            $metadata['context_bytes'],
            $metadata['is_truncated'] ? 1 : 0,
            $uploadId,
        ]);
    }

    public function markFailed(int $uploadId, string $errorCode): void
    {
        $statement = $this->pdo->prepare('UPDATE uploads SET status = \'failed\', error_code = ? WHERE id = ?');
        $statement->execute([mb_substr($errorCode, 0, 80), $uploadId]);
    }

    /** @return array<string, mixed>|null */
    public function findAuthorized(int $uploadId, string $ipHash, string $sessionTokenHash): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, original_name, row_count, sheet_count, context_bytes, is_truncated, status, expires_at
             FROM uploads
             WHERE id = ? AND ip_hash = ? AND session_token_hash = ? AND expires_at > UTC_TIMESTAMP()
             LIMIT 1'
        );
        $statement->execute([$uploadId, $ipHash, $sessionTokenHash]);
        $upload = $statement->fetch();
        return is_array($upload) ? $upload : null;
    }
}
