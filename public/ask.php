<?php

declare(strict_types=1);

use TalkToExcel\ContextStore;
use TalkToExcel\Database;
use TalkToExcel\Env;
use TalkToExcel\JsonResponse;
use TalkToExcel\OpenAIClient;
use TalkToExcel\OpenAIException;
use TalkToExcel\QuestionRepository;
use TalkToExcel\RateLimiter;
use TalkToExcel\Security;
use TalkToExcel\UploadRepository;

require dirname(__DIR__) . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::error('Method not allowed.', 405, 'method_not_allowed');
}

Security::validateCsrf();

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
$question = is_array($payload) ? trim((string) ($payload['question'] ?? '')) : '';

if ($question === '') {
    JsonResponse::error('Enter a question about the workbook.', 422, 'question_required');
}
if (mb_strlen($question) > 1000) {
    JsonResponse::error('Questions are limited to 1,000 characters.', 422, 'question_too_long');
}

$uploadId = $_SESSION['upload_id'] ?? null;
$contextToken = $_SESSION['context_token'] ?? null;
if (!is_int($uploadId) && !ctype_digit((string) $uploadId)) {
    JsonResponse::error('Upload a workbook before asking questions.', 409, 'workbook_required');
}
if (!is_string($contextToken) || !preg_match('/^[a-f0-9]{64}$/', $contextToken)) {
    JsonResponse::error('Your workbook session has expired. Refresh the page.', 409, 'workbook_session_expired');
}
$uploadId = (int) $uploadId;

$pdo = Database::connection();
$limiter = new RateLimiter($pdo);
$uploads = new UploadRepository($pdo);
$questions = new QuestionRepository($pdo);
$ipHash = Security::ipHash(Security::clientIp());

$upload = $uploads->findAuthorized($uploadId, $ipHash, Security::tokenHash($contextToken));
if ($upload === null || ($upload['status'] ?? null) !== 'ready') {
    JsonResponse::error('The workbook is unavailable or has expired.', 409, 'workbook_unavailable');
}

if (!$limiter->reserveQuestion($ipHash)) {
    JsonResponse::error('This IP address has reached the 10-question allowance.', 429, 'question_limit_reached');
}

$questionId = null;
try {
    $questionId = $questions->create($uploadId, $ipHash, Security::questionHash($question));
    $stored = (new ContextStore(dirname(__DIR__) . '/storage/contexts'))->read($contextToken);

    $history = $_SESSION['chat_history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }

    $result = (new OpenAIClient())->ask($stored['context'], $question, $history);
    $questions->complete($questionId, $result['input_tokens'], $result['output_tokens']);

    $history[] = [
        'question' => mb_substr($question, 0, 1000),
        'answer' => mb_substr($result['answer'], 0, 5000),
    ];
    $_SESSION['chat_history'] = array_slice($history, -5);

    $usage = $limiter->usage($ipHash);
    JsonResponse::send([
        'ok' => true,
        'answer' => $result['answer'],
        'usage' => [
            'uploads_remaining' => max(0, Env::int('MAX_UPLOADS_PER_IP', 1) - $usage['upload_count']),
            'questions_remaining' => max(0, Env::int('MAX_QUESTIONS_PER_IP', 10) - $usage['question_count']),
        ],
    ]);
} catch (OpenAIException $exception) {
    if ($questionId !== null) {
        $questions->fail($questionId, $exception->errorCode);
    }
    $limiter->releaseQuestion($ipHash);
    error_log('TalkToExcel OpenAI error: ' . $exception->getMessage());
    $status = in_array($exception->httpStatus, [400, 401, 403, 429], true) ? 502 : 503;
    JsonResponse::error('The AI service could not answer this question. Your question allowance was not consumed.', $status, 'ai_service_error');
} catch (\Throwable $exception) {
    if ($questionId !== null) {
        $questions->fail($questionId, 'question_processing_failed');
    }
    $limiter->releaseQuestion($ipHash);
    error_log('TalkToExcel question error: ' . $exception->getMessage());
    JsonResponse::error('The question could not be processed. Your question allowance was not consumed.', 500, 'question_processing_failed');
}
