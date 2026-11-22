<?php
declare(strict_types=1);

/* SQLite access through PDO. Tables are created automatically on first run. */

const SCHEMA = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name     TEXT    NOT NULL,
    email         TEXT    NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT    NOT NULL,
    role          TEXT    NOT NULL CHECK (role IN ('teacher', 'student')),
    created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS classrooms (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    teacher_id  INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    name        TEXT    NOT NULL,
    subject     TEXT    NOT NULL DEFAULT '',
    description TEXT    NOT NULL DEFAULT '',
    class_code  TEXT    NOT NULL UNIQUE,
    created_at  TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS classroom_members (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    classroom_id INTEGER NOT NULL REFERENCES classrooms (id) ON DELETE CASCADE,
    student_id   INTEGER NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    joined_at    TEXT    NOT NULL DEFAULT (datetime('now')),
    UNIQUE (classroom_id, student_id)
);
CREATE TABLE IF NOT EXISTS learning_materials (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    classroom_id  INTEGER NOT NULL REFERENCES classrooms (id) ON DELETE CASCADE,
    title         TEXT    NOT NULL,
    description   TEXT    NOT NULL DEFAULT '',
    original_name TEXT    NOT NULL,
    stored_name   TEXT    NOT NULL UNIQUE,
    mime_type     TEXT    NOT NULL,
    file_size     INTEGER NOT NULL,
    uploaded_at   TEXT    NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    throttle_key TEXT    NOT NULL,
    attempted_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_members_student ON classroom_members (student_id);
CREATE INDEX IF NOT EXISTS idx_materials_classroom ON learning_materials (classroom_id);
CREATE INDEX IF NOT EXISTS idx_attempts_key ON login_attempts (throttle_key);
SQL;

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec(SCHEMA);
    }
    return $pdo;
}

/** Run a prepared statement and return it. */
function q(string $sql, array $params = []): PDOStatement
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    return $statement;
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** Run a write statement. Returns the number of affected rows. */
function run(string $sql, array $params = []): int
{
    return q($sql, $params)->rowCount();
}

/** Run an INSERT and return the new row id. */
function insert(string $sql, array $params = []): int
{
    q($sql, $params);
    return (int) db()->lastInsertId();
}
