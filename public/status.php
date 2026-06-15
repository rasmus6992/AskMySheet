<?php

declare(strict_types=1);

use TalkToExcel\ContextStore;
use TalkToExcel\Database;
use TalkToExcel\Env;
use TalkToExcel\JsonResponse;
use TalkToExcel\RateLimiter;
use TalkToExcel\Security;
use TalkToExcel\UploadRepository;

require dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    JsonResponse::error('Method not allowed.', 405, 'method_not_allowed');
}

$pdo = Database::connection();
$limiter = new RateLimiter($pdo);
$ipHash = Security::ipHash(Security::clientIp());
$usage = $limiter->usage($ipHash);
$upload = null;

$uploadId = $_SESSION['upload_id'] ?? null;
$contextToken = $_SESSION['context_token'] ?? null;

// When the one-hour allowance has reset, invalidate the previous workbook.
// Without this, the browser keeps state.ready=true and disables uploading.
if ($usage['upload_count'] === 0
    && (is_int($uploadId) || ctype_digit((string) $uploadId))
    && is_string($contextToken)
    && preg_match('/^[a-f0-9]{64}$/', $contextToken)) {
    (new ContextStore(dirname(__DIR__) . '/storage/contexts'))->delete($contextToken);
    unset($_SESSION['upload_id'], $_SESSION['context_token'], $_SESSION['chat_history']);
    $uploadId = null;
    $contextToken = null;
}

if ((is_int($uploadId) || ctype_digit((string) $uploadId))
    && is_string($contextToken)
    && preg_match('/^[a-f0-9]{64}$/', $contextToken)) {
    $upload = (new UploadRepository($pdo))->findAuthorized(
        (int) $uploadId,
        $ipHash,
        Security::tokenHash($contextToken)
    );
}

JsonResponse::send([
    'ok' => true,
    'ready' => is_array($upload) && ($upload['status'] ?? null) === 'ready',
    'upload' => is_array($upload) ? [
        'name' => (string) $upload['original_name'],
        'rows' => (int) $upload['row_count'],
        'sheets' => (int) $upload['sheet_count'],
        'context_bytes' => (int) $upload['context_bytes'],
        'truncated' => (bool) $upload['is_truncated'],
    ] : null,
    'usage' => [
        'uploads_remaining' => max(0, Env::int('MAX_UPLOADS_PER_IP', 1) - $usage['upload_count']),
        'questions_remaining' => max(0, Env::int('MAX_QUESTIONS_PER_IP', 10) - $usage['question_count']),
    ],
]);
