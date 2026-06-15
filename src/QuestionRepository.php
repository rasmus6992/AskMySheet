<?php

declare(strict_types=1);

namespace TalkToExcel;

use PDO;

final class QuestionRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(int $uploadId, string $ipHash, string $questionHash): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO questions (upload_id, ip_hash, question_hash, status) VALUES (?, ?, ?, \'pending\')'
        );
        $statement->execute([$uploadId, $ipHash, $questionHash]);
        return (int) $this->pdo->lastInsertId();
    }

    public function complete(int $questionId, ?int $inputTokens, ?int $outputTokens): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE questions SET status = \'completed\', input_tokens = ?, output_tokens = ?, completed_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $statement->execute([$inputTokens, $outputTokens, $questionId]);
    }

    public function fail(int $questionId, string $errorCode): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE questions SET status = \'failed\', error_code = ?, completed_at = UTC_TIMESTAMP() WHERE id = ?'
        );
        $statement->execute([mb_substr($errorCode, 0, 80), $questionId]);
    }
}
