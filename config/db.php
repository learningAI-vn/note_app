<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Returns a singleton PDO connection.
 * Supports MySQL (Docker / XAMPP) and SQLite (for automated CLI test suite).
 */
function getPDO(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn  = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=note_app;charset=utf8mb4';
    $user = getenv('DB_USER') !== false ? getenv('DB_USER') : 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (str_starts_with($dsn, 'sqlite:')) {
        $pdo = new PDO($dsn, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
        initSqliteSchemaIfNeeded($pdo);
    } else {
        $pdo = new PDO($dsn, $user, $pass, $options);
    }

    return $pdo;
}

/**
 * Ensures SQLite test databases have the exact same 6 tables as MySQL schema.sql.
 */
function initSqliteSchemaIfNeeded(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            display_name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            avatar TEXT DEFAULT NULL,
            is_activated INTEGER NOT NULL DEFAULT 0,
            activation_token TEXT DEFAULT NULL,
            reset_token TEXT DEFAULT NULL,
            reset_expires_at TEXT DEFAULT NULL,
            pref_font_size TEXT NOT NULL DEFAULT 'medium',
            pref_note_color TEXT NOT NULL DEFAULT '#ffffff',
            pref_theme TEXT NOT NULL DEFAULT 'light',
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );
        CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL DEFAULT '',
            content TEXT NOT NULL DEFAULT '',
            color TEXT NOT NULL DEFAULT '#ffffff',
            is_pinned INTEGER NOT NULL DEFAULT 0,
            pinned_at TEXT DEFAULT NULL,
            password_hash TEXT DEFAULT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            updated_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS labels (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(user_id, name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS note_labels (
            note_id INTEGER NOT NULL,
            label_id INTEGER NOT NULL,
            PRIMARY KEY (note_id, label_id),
            FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
            FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS note_attachments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            note_id INTEGER NOT NULL,
            file_name TEXT NOT NULL,
            file_path TEXT NOT NULL,
            file_type TEXT NOT NULL DEFAULT 'file',
            mime_type TEXT NOT NULL,
            file_size INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT (datetime('now')),
            FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
        );
        CREATE TABLE IF NOT EXISTS note_shares (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            note_id INTEGER NOT NULL,
            owner_id INTEGER NOT NULL,
            recipient_id INTEGER NOT NULL,
            permission TEXT NOT NULL DEFAULT 'read',
            is_notified INTEGER NOT NULL DEFAULT 0,
            shared_at TEXT NOT NULL DEFAULT (datetime('now')),
            UNIQUE(note_id, recipient_id),
            FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE
        );
    ");
}

/**
 * Sends a JSON HTTP response and terminates script execution.
 */
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Reads JSON body from php://input (or APP_TEST_BODY in CLI test runner).
 */
function readJsonInput(): array {
    $testBody = getenv('APP_TEST_BODY');
    if ($testBody !== false && $testBody !== '') {
        $decoded = json_decode($testBody, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return $_POST ?: [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ($_POST ?: []);
}

/**
 * Enforces login on HTML pages. Immediately redirects to login.php if unauthenticated.
 */
function requireAuthPage(): array {
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) {
        unset($_SESSION['user_id']);
        header('Location: login.php');
        exit;
    }
    return $user;
}

/**
 * Enforces login on JSON API endpoints. Returns current user ID or exits with 401.
 */
function requireAuthApi(): int {
    if (empty($_SESSION['user_id'])) {
        jsonResponse(['ok' => false, 'error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
    }
    return (int)$_SESSION['user_id'];
}
