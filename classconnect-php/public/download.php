<?php
require __DIR__ . '/../app/bootstrap.php';

/* Serves a stored file only to the classroom's teacher and joined students. */

$user = require_login();
[$material] = material_for_viewer((int) ($_GET['id'] ?? 0), $user);

$path = UPLOAD_DIR . '/' . basename($material['stored_name']);
if (!is_file($path)) {
    render_error(404, 'This file is missing from the server. Ask your teacher to upload it again.');
}

$inline = ($_GET['inline'] ?? '') === '1' && is_previewable($material['mime_type']);
$filename = str_replace('"', '', $material['original_name']);

header('Content-Type: ' . $material['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-cache');
readfile($path);
exit;
