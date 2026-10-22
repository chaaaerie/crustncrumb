<?php
/**
 * config.php — database connection, session setup, and shared helpers.
 */

// ---------- Database settings ----------
const DB_HOST    = '127.0.0.1';
const DB_PORT    = 3307;
const DB_NAME    = 'bakeshop_db';
const DB_USER    = 'root';
const DB_PASS    = '';
const DB_CHARSET = 'utf8mb4';

// Set to true while developing to see real error messages in API responses.
const DEBUG = true;

ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(E_ALL);

// ---------- Session ----------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ---------- PDO connection ----------
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ---------- Auth helpers ----------
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

// ---------- JSON helpers ----------
function respond(array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_UNICODE);
    exit;
}

function fail(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        fail('Please sign in first.', 401);
    }
    return $user;
}

function require_role(array $roles): array
{
    $user = require_login();
    if (!in_array($user['role'], $roles, true)) {
        fail('You do not have permission to do that.', 403);
    }
    return $user;
}