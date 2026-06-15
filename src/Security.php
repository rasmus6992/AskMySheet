<?php

declare(strict_types=1);

namespace TalkToExcel;

final class Security
{
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    }

    public static function csrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(): void
    {
        $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';

        if (!is_string($provided) || !is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
            JsonResponse::error('Your session token is invalid or expired. Refresh the page and try again.', 419, 'csrf_failed');
        }
    }

    public static function clientIp(): string
    {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trusted = array_filter(array_map('trim', explode(',', Env::get('TRUSTED_PROXIES', '') ?? '')));

        if (in_array($remote, $trusted, true)) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            foreach (array_map('trim', explode(',', $forwarded)) as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public static function ipHash(string $ip): string
    {
        return hash_hmac('sha256', $ip, Env::require('APP_KEY'), true);
    }

    public static function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, Env::require('APP_KEY'), true);
    }

    public static function questionHash(string $question): string
    {
        return hash_hmac('sha256', $question, Env::require('APP_KEY'), true);
    }

    public static function cleanFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._ -]/u', '_', $filename) ?? 'spreadsheet';
        return mb_substr(trim($filename), 0, 255);
    }
}
