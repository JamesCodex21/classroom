<?php
declare(strict_types=1);

/* Upload validation and storage. Files are checked by extension AND by their first bytes,
   stored under random names outside the public folder, and only served through access checks. */

final class UploadException extends RuntimeException
{
}

/** Converts php.ini sizes such as "25M" or "2G" into bytes. */
function ini_bytes(string $value): int
{
    $value = trim($value);
    $number = (int) $value;
    return match (strtolower(substr($value, -1))) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => $number,
    };
}

/** The largest upload this server will accept, in MB (the smaller of the two php.ini limits). */
function server_upload_limit_mb(): int
{
    $bytes = min(ini_bytes((string) ini_get('upload_max_filesize')), ini_bytes((string) ini_get('post_max_size')));
    return (int) floor($bytes / (1024 * 1024));
}

/** True when php.ini does not allow the 25 MB that ClassConnect promises. */
function upload_limit_too_low(): bool
{
    return server_upload_limit_mb() < MAX_UPLOAD_MB;
}

/** extension => [MIME type, signature prefixes (null = plain text)] */
function allowed_file_types(): array
{
    $ole = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
    $zip = "PK\x03\x04";
    return [
        'pdf'  => ['application/pdf', ['%PDF']],
        'doc'  => ['application/msword', [$ole]],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', [$zip]],
        'ppt'  => ['application/vnd.ms-powerpoint', [$ole]],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', [$zip]],
        'xls'  => ['application/vnd.ms-excel', [$ole]],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', [$zip]],
        'txt'  => ['text/plain', null],
        'md'   => ['text/markdown', null],
        'csv'  => ['text/csv', null],
        'png'  => ['image/png', ["\x89PNG\r\n\x1a\n"]],
        'jpg'  => ['image/jpeg', ["\xFF\xD8\xFF"]],
        'jpeg' => ['image/jpeg', ["\xFF\xD8\xFF"]],
        'gif'  => ['image/gif', ['GIF87a', 'GIF89a']],
        'webp' => ['image/webp', ['RIFF']],
    ];
}

function head_matches(string $ext, string $head): bool
{
    if ($ext === 'webp') {
        return substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP';
    }
    $rules = allowed_file_types()[$ext][1];
    if ($rules === null) {
        return !str_contains($head, "\0"); // plain text must not contain binary NUL bytes
    }
    foreach ($rules as $prefix) {
        if (str_starts_with($head, $prefix)) {
            return true;
        }
    }
    return false;
}

function is_valid_ooxml(string $path): bool
{
    if (!class_exists('ZipArchive')) {
        return true; // zip extension not enabled: rely on the signature check
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return false;
    }
    $ok = $zip->locateName('[Content_Types].xml') !== false;
    $zip->close();
    return $ok;
}

/** Validate an entry from $_FILES and move it into storage. Returns metadata for the database. */
function store_upload(?array $file): array
{
    if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new UploadException('Please choose a file to upload.');
    }
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new UploadException('That file is larger than the ' . server_upload_limit_mb()
            . ' MB upload limit on this server. Choose a smaller file.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new UploadException('The upload did not finish. Please try again.');
    }

    $original = (string) $file['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $types = allowed_file_types();
    if (!isset($types[$ext])) {
        $shown = $ext !== '' ? '.' . $ext : 'This file';
        throw new UploadException($shown . ' files are not supported. Allowed types: '
            . strtoupper(implode(', ', array_keys($types))) . '.');
    }

    $size = (int) $file['size'];
    if ($size === 0) {
        throw new UploadException('That file is empty.');
    }
    if ($size > MAX_UPLOAD_MB * 1024 * 1024) {
        throw new UploadException('That file is larger than ' . MAX_UPLOAD_MB . ' MB.');
    }

    $head = (string) file_get_contents($file['tmp_name'], false, null, 0, 4096);
    $mismatch = "The contents of this file don't match a .$ext file. Please check it and try again.";
    if (!head_matches($ext, $head)) {
        throw new UploadException($mismatch);
    }
    if (in_array($ext, ['docx', 'pptx', 'xlsx'], true) && !is_valid_ooxml($file['tmp_name'])) {
        throw new UploadException($mismatch);
    }

    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/' . $stored)) {
        throw new UploadException('We could not save the file. Please try again.');
    }

    return [
        'stored_name' => $stored,
        'original_name' => safe_display_name($original, $ext),
        'mime_type' => $types[$ext][0],
        'file_size' => $size,
    ];
}

/** A download-friendly file name: letters, numbers, dots, dashes and spaces only. */
function safe_display_name(string $original, string $ext): string
{
    $name = preg_replace('/[^A-Za-z0-9. _-]+/', '_', basename($original)) ?? '';
    $name = trim($name, ' ._');
    if ($name === '') {
        $name = 'file';
    }
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== $ext) {
        $name .= '.' . $ext;
    }
    return $name;
}

function delete_stored_file(string $storedName): void
{
    $path = UPLOAD_DIR . '/' . basename($storedName);
    if (is_file($path)) {
        unlink($path);
    }
}

function is_previewable(string $mime): bool
{
    return $mime === 'application/pdf' || str_starts_with($mime, 'image/');
}

/** PHP silently empties $_POST and $_FILES when a request exceeds post_max_size. */
function post_too_large(): bool
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && $length > 0 && empty($_POST) && empty($_FILES);
}
