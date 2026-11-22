<?php
declare(strict_types=1);

/* Login sessions, role checks, CSRF protection and login throttling. */

const ROLE_TEACHER = 'teacher';
const ROLE_STUDENT = 'student';
const LOGIN_WINDOW_SECONDS = 900;
const LOGIN_MAX_FAILURES = 8;

/** The signed-in user (id, full_name, email, role) or null. */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if (!$loaded) {
        $loaded = true;
        $id = $_SESSION['user_id'] ?? null;
        if ($id !== null) {
            $user = one('SELECT id, full_name, email, role FROM users WHERE id = ?', [(int) $id]);
            if ($user === null) {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

/** Send visitors to log in; send the wrong role to a 403 page. */
function require_login(?string $role = null): array
{
    $user = current_user();
    if ($user === null) {
        flash('info', 'Please log in to continue.');
        redirect('login.php');
    }
    if ($role !== null && $user['role'] !== $role) {
        render_error(403, 'You do not have permission to view this page.');
    }
    return $user;
}

function start_user_session(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function end_user_session(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** Hidden form field that proves the form came from this site. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = (string) ($_POST['csrf_token'] ?? '');
    $expected = (string) ($_SESSION['csrf'] ?? '');
    if ($expected === '' || $sent === '' || !hash_equals($expected, $sent)) {
        render_error(400, 'Your session has expired or the form could not be verified. Please refresh the page and try again.');
    }
}

function login_throttled(string $key): bool
{
    $row = one(
        'SELECT COUNT(*) AS n FROM login_attempts WHERE throttle_key = ? AND attempted_at > ?',
        [$key, time() - LOGIN_WINDOW_SECONDS]
    );
    return (int) $row['n'] >= LOGIN_MAX_FAILURES;
}

function record_failed_login(string $key): void
{
    run('DELETE FROM login_attempts WHERE attempted_at < ?', [time() - LOGIN_WINDOW_SECONDS]);
    run('INSERT INTO login_attempts (throttle_key, attempted_at) VALUES (?, ?)', [$key, time()]);
}

function clear_failed_logins(string $key): void
{
    run('DELETE FROM login_attempts WHERE throttle_key = ?', [$key]);
}
