<?php

declare(strict_types=1);

use TalkToExcel\ContextStore;
use TalkToExcel\Database;
use TalkToExcel\Env;
use TalkToExcel\ExcelParser;
use TalkToExcel\JsonResponse;
use TalkToExcel\RateLimiter;
use TalkToExcel\Security;
use TalkToExcel\UploadRepository;

require dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::error('Method not allowed.', 405, 'method_not_allowed');
}

Security::validateCsrf();

if (!isset($_FILES['excel_file']) || !is_array($_FILES['excel_file'])) {
    JsonResponse::error('Choose an Excel file to upload.', 422, 'file_required');
}

$pdo = Database::connection();
$limiter = new RateLimiter($pdo);
$uploads = new UploadRepository($pdo);
$ipHash = Security::ipHash(Security::clientIp());

if (!$limiter->reserveUpload($ipHash)) {
    JsonResponse::error('This IP address has already used its upload allowance.', 429, 'upload_limit_reached');
}

$uploadId = null;
$contextToken = bin2hex(random_bytes(32));
$store = new ContextStore(dirname(__DIR__) . '/storage/contexts');

try {
    if (random_int(1, 20) === 1) {
        $store->cleanupExpired(Env::int('CONTEXT_RETENTION_HOURS', 24));
    }

    $file = $_FILES['excel_file'];
    $originalName = Security::cleanFilename((string) $file['name']);
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    $uploadId = $uploads->create(
        $ipHash,
        Security::tokenHash($contextToken),
        $originalName,
        $extension,
        (int) $file['size']
    );

    $result = (new ExcelParser())->parseUploadedFile($file);
    $store->write($contextToken, $result['context'], $result['metadata']);
    $uploads->markReady($uploadId, $result['metadata']);

    session_regenerate_id(true);
    $_SESSION['upload_id'] = $uploadId;
    $_SESSION['context_token'] = $contextToken;
    $_SESSION['chat_history'] = [];

    $usage = $limiter->usage($ipHash);

    JsonResponse::send([
        'ok' => true,
        'message' => 'Workbook uploaded and ready for questions.',
        'upload' => [
            'name' => $originalName,
            'rows' => $result['metadata']['row_count'],
            'sheets' => $result['metadata']['sheet_count'],
            'context_bytes' => $result['metadata']['context_bytes'],
            'truncated' => $result['metadata']['is_truncated'],
        ],
        'usage' => [
            'uploads_remaining' => max(0, Env::int('MAX_UPLOADS_PER_IP', 1) - $usage['upload_count']),
            'questions_remaining' => max(0, Env::int('MAX_QUESTIONS_PER_IP', 10) - $usage['question_count']),
        ],
    ], 201);
} catch (\DomainException $exception) {
    if ($uploadId !== null) {
        $uploads->markFailed($uploadId, 'invalid_spreadsheet');
    }
    $store->delete($contextToken);
    $limiter->releaseUpload($ipHash);
    JsonResponse::error($exception->getMessage(), 422, 'invalid_spreadsheet');
} catch (\Throwable $exception) {
    if ($uploadId !== null) {
        $uploads->markFailed($uploadId, 'upload_processing_failed');
    }
    $store->delete($contextToken);
    $limiter->releaseUpload($ipHash);
    error_log('TalkToExcel upload error: ' . $exception->getMessage());
    JsonResponse::error('The workbook could not be processed. Please verify the file and try again.', 500, 'upload_processing_failed');
}
