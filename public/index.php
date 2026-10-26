<?php
declare(strict_types=1);

use SMTPValidateEmail\Validator;

require dirname(__DIR__) . '/vendor/autoload.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $body): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($path === '/health' && $method === 'GET') {
    respond(200, ['status' => 'ok']);
}

if ($path !== '/validate') {
    respond(404, ['error' => 'not_found', 'message' => 'Use GET /health or POST /validate.']);
}

if ($method !== 'POST') {
    header('Allow: POST');
    respond(405, ['error' => 'method_not_allowed']);
}

$expectedKey = (string) getenv('API_KEY');
if ($expectedKey === '') {
    respond(503, ['error' => 'service_not_configured', 'message' => 'API_KEY is not configured.']);
}

$auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches) || !hash_equals($expectedKey, trim($matches[1]))) {
    respond(401, ['error' => 'unauthorized', 'message' => 'Supply a valid Authorization: Bearer token.']);
}

$contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
if ($contentType !== 'application/json') {
    respond(415, ['error' => 'unsupported_media_type', 'message' => 'Send Content-Type: application/json.']);
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 16384) {
    respond(413, ['error' => 'request_too_large', 'message' => 'Request body must be 16 KB or smaller.']);
}

$payload = json_decode($raw, true);
if (!is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
    respond(400, ['error' => 'invalid_json']);
}

$email = $payload['email'] ?? null;
if (!is_string($email) || trim($email) === '' || strlen($email) > 254 || filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
    respond(400, ['error' => 'invalid_email', 'message' => 'Provide one syntactically valid email in the email field.']);
}
$email = trim($email);

$sender = trim((string) getenv('SENDER_EMAIL'));
if ($sender === '' || filter_var($sender, FILTER_VALIDATE_EMAIL) === false) {
    respond(503, ['error' => 'service_not_configured', 'message' => 'Set SENDER_EMAIL to a valid sender address.']);
}

try {
    $validator = new Validator($email, $sender);
    $result = $validator->validate();

    respond(200, [
        'email' => $email,
        'result' => $result,
        'note' => 'SMTP verification is heuristic. Mail providers may block or obscure recipient checks; a result is not a delivery guarantee.'
    ]);
} catch (Throwable $e) {
    error_log('Email validation failed: ' . get_class($e));
    respond(502, ['error' => 'validation_failed', 'message' => 'The remote mail server could not be checked reliably.']);
}
