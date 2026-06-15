<?php

declare(strict_types=1);

namespace TalkToExcel;

final class ContextStore
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the context storage directory.');
        }
    }

    /** @param array<string, mixed> $metadata */
    public function write(string $token, string $context, array $metadata): void
    {
        $payload = json_encode([
            'context' => $context,
            'metadata' => $metadata,
            'created_at' => gmdate(DATE_ATOM),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        $compressed = gzencode($payload, 6);
        if ($compressed === false) {
            throw new \RuntimeException('Unable to compress spreadsheet context.');
        }

        $target = $this->path($token);
        $temporary = $target . '.' . bin2hex(random_bytes(6)) . '.tmp';

        if (file_put_contents($temporary, $compressed, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to store spreadsheet context.');
        }

        chmod($temporary, 0600);
        if (!rename($temporary, $target)) {
            @unlink($temporary);
            throw new \RuntimeException('Unable to finalize spreadsheet context storage.');
        }
    }

    /** @return array{context:string,metadata:array<string,mixed>} */
    public function read(string $token): array
    {
        $path = $this->path($token);
        if (!is_file($path)) {
            throw new \RuntimeException('The spreadsheet context has expired or is unavailable.');
        }

        $compressed = file_get_contents($path);
        if ($compressed === false) {
            throw new \RuntimeException('Unable to read spreadsheet context.');
        }

        $json = gzdecode($compressed);
        if ($json === false) {
            throw new \RuntimeException('Spreadsheet context is corrupted.');
        }

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || !isset($payload['context']) || !is_string($payload['context'])) {
            throw new \RuntimeException('Spreadsheet context is invalid.');
        }

        return [
            'context' => $payload['context'],
            'metadata' => is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [],
        ];
    }

    public function delete(string $token): void
    {
        $path = $this->path($token);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public function cleanupExpired(int $retentionHours): void
    {
        $cutoff = time() - max(1, $retentionHours) * 3600;
        $files = glob($this->directory . '/*.json.gz') ?: [];
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && $mtime < $cutoff) {
                @unlink($file);
            }
        }
    }

    private function path(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new \InvalidArgumentException('Invalid context token.');
        }

        return rtrim($this->directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $token . '.json.gz';
    }
}
