<?php
declare(strict_types=1);

/* Small utilities: escaping, redirects, flash messages, validation and formatting. */

/** Escape text for safe HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

/** Read a trimmed text field from the POST body. */
function post_text(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

/** Character count that works with or without the mbstring extension. */
function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function first_letter(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '?';
    }
    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8');
    }
    return strtoupper(substr($value, 0, 1));
}

function first_name(string $fullName): string
{
    $parts = preg_split('/\s+/', trim($fullName)) ?: [''];
    return $parts[0];
}

/** Six-character class code without look-alike characters (no 0, O, 1, I or L). */
function new_class_code(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return $code;
}

function is_valid_email(string $email): bool
{
    return $email !== ''
        && text_length($email) <= 120
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function password_problem(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Use at least 8 characters.';
    }
    if (strlen($password) > 128) {
        return 'Use 128 characters or fewer.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Include at least one letter and one number.';
    }
    return null;
}

/** Stored timestamps are UTC ("YYYY-MM-DD HH:MM:SS"). */
function short_date(?string $stamp): string
{
    if (!$stamp) {
        return '';
    }
    $time = strtotime($stamp . ' UTC');
    return $time ? date('M j, Y', $time) : $stamp;
}

function format_size(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }
    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }
    return number_format($bytes / (1024 * 1024), 1) . ' MB';
}

function file_type_label(string $filename): string
{
    $ext = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));
    return $ext !== '' ? $ext : 'FILE';
}

function plural(int $count, string $singular, string $plural): string
{
    return $count . ' ' . ($count === 1 ? $singular : $plural);
}

function validate_classroom(array $form): array
{
    $errors = [];
    $nameLength = text_length($form['name']);
    if ($nameLength < 2 || $nameLength > 80) {
        $errors['name'] = 'Class name must be 2 to 80 characters.';
    }
    if (text_length($form['subject']) > 60) {
        $errors['subject'] = 'Subject must be 60 characters or fewer.';
    }
    if (text_length($form['description']) > 500) {
        $errors['description'] = 'Description must be 500 characters or fewer.';
    }
    return $errors;
}

function validate_material_fields(array $form): array
{
    $errors = [];
    $titleLength = text_length($form['title']);
    if ($titleLength < 2 || $titleLength > 120) {
        $errors['title'] = 'Title must be 2 to 120 characters.';
    }
    if (text_length($form['description']) > 1000) {
        $errors['description'] = 'Description must be 1000 characters or fewer.';
    }
    return $errors;
}
