<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/security.php';
require __DIR__ . '/src/notes.php';

start_secure_session();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'none'");

function redirect_home(): never
{
    header('Location: /');
    exit;
}

try {
    $db = db();
    $userId = current_user_id();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            exit('Invalid or missing CSRF token. Go back, reload the page, and try again.');
        }
        $action = $_POST['action'] ?? '';

        if ($action === 'register') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
                flash('Enter a valid email and a password of at least 12 characters.');
                redirect_home();
            }
            try {
                $stmt = $db->prepare('INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)');
                $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT), 'student']);
                flash('Account created. You can sign in now.');
            } catch (PDOException $ex) {
                error_log('Register failed: ' . $ex->getCode());
                flash('Registration failed. The email may already be in use.');
            }
            redirect_home();
        }

        if ($action === 'login') {
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $stmt = $db->prepare('SELECT id, password_hash, role FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);           // stop session fixation
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['role'] = $user['role'];
                unset($_SESSION['csrf_token']);         // fresh token after login
                redirect_home();
            }
            flash('Invalid email or password.');       // generic, does not echo input
            redirect_home();
        }

        if ($action === 'logout') {
            logout_user();
            start_secure_session();
            flash('You have been logged out.');
            redirect_home();
        }

        // Everything below requires a logged-in user.
        if ($userId === null) {
            http_response_code(401);
            exit('Please sign in.');
        }

        if ($action === 'save') {
            $title = $_POST['title'] ?? '';
            $body = $_POST['body'] ?? '';
            $error = validate_note($title, $body);
            if ($error === null) {
                // Owner comes from the SESSION, not from the form.
                create_note($db, $userId, trim($title), $body);
            }
            flash($error ?? 'Note saved.');
            redirect_home();
        }

        if ($action === 'update') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $title = $_POST['title'] ?? '';
            $body = $_POST['body'] ?? '';
            $error = $id === false ? 'Invalid note.' : validate_note($title, $body);
            if ($error === null && !update_note($db, $id, $userId, trim($title), $body)) {
                $error = 'Note not found.';
            }
            flash($error ?? 'Note updated.');
            redirect_home();
        }

        if ($action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $ok = $id !== false && delete_note($db, $id, $userId);
            flash($ok ? 'Note deleted.' : 'Note not found.');
            redirect_home();
        }

        http_response_code(400);
        exit('Unknown action.');
    }

    $message = take_flash();
    $notes = [];
    $editing = false;
    if ($userId !== null) {
        $notes = notes_for_user($db, $userId);
        $editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($editId !== false && $editId !== null) {
            $editing = find_note_for_user($db, $editId, $userId);
        }
    }
} catch (Throwable $ex) {
    error_log('Campus Notes error: ' . $ex->getMessage());
    http_response_code(500);
    exit('Something went wrong. Please try again later.');
}

require __DIR__ . '/src/views/page.php';