<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/security.php';
require __DIR__ . '/src/notes.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
// No Access-Control-Allow-* headers: browsers block cross-origin reads by default.

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

try {
    // 1. Reject requests coming from another website (untrusted Origin).
    $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
    $self = 'http://' . ($_SERVER['HTTP_HOST'] ?? '');
    if ($origin !== null && $origin !== $self) {
        respond(403, ['error' => 'Origin not allowed']);
    }

    // 2. Require a logged-in session.
    start_secure_session();
    $userId = current_user_id();
    if ($userId === null) {
        respond(401, ['error' => 'Authentication required']);
    }
    $db = db();

    // 3. GET: only the caller's own notes. A different user_id is refused.
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['user_id']) && (string) $_GET['user_id'] !== (string) $userId) {
            respond(403, ['error' => 'Forbidden']);
        }
        respond(200, notes_for_user($db, $userId));
    }

    // 4. POST: CSRF header + JSON body validation + owner check.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_is_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
            respond(403, ['error' => 'Invalid CSRF token']);
        }
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
            respond(415, ['error' => 'Content-Type must be application/json']);
        }
        $raw = file_get_contents('php://input');
        if ($raw === false || strlen($raw) > 20000) {
            respond(413, ['error' => 'Request body too large']);
        }
        try {
            $data = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            respond(400, ['error' => 'Malformed JSON']);
        }
        if (!is_array($data)) {
            respond(400, ['error' => 'JSON object expected']);
        }
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $error = $id === false ? 'id must be a positive integer'
                               : validate_note($data['title'] ?? null, $data['body'] ?? null);
        if ($error !== null) {
            respond(422, ['error' => $error]);
        }
        if (!update_note($db, $id, $userId, trim($data['title']), $data['body'])) {
            respond(404, ['error' => 'Note not found']);   // same answer for "not yours" and "missing"
        }
        respond(200, ['updated' => 1]);
    }

    header('Allow: GET, POST');
    respond(405, ['error' => 'Method not supported']);
} catch (Throwable $ex) {
    error_log('Campus Notes API error: ' . $ex->getMessage());
    respond(500, ['error' => 'Server error']);
}