<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,   
        'httponly' => true,       // JavaScript cannot read the cookie
        'samesite' => 'Lax',      // cookie not sent on cross-site POSTs
    ]);
    session_start();
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $user = getenv('LAB_DB_USER');
    $pass = getenv('LAB_DB_PASSWORD');
    if ($user === false || $user === '' || $pass === false) {
        error_log('Campus Notes: LAB_DB_USER / LAB_DB_PASSWORD not set');
        throw new RuntimeException('Database is not configured.');
    }
    $pdo = new PDO(
        'mysql:host=localhost;dbname=notes_lab;charset=utf8mb4',
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true, // rowCount() = matched rows
        ]
    );
    return $pdo;
}