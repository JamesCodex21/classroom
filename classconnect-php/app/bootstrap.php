<?php
declare(strict_types=1);

/* Shared setup loaded by every page: paths, session, database and helpers. */

define('APP_NAME', 'ClassConnect');
define('ROOT_DIR', dirname(__DIR__));
define('STORAGE_DIR', ROOT_DIR . '/storage');
define('UPLOAD_DIR', STORAGE_DIR . '/uploads');
define('DB_PATH', STORAGE_DIR . '/classconnect.sqlite');
define('MAX_UPLOAD_MB', 25);

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/classrooms.php';
require_once __DIR__ . '/materials.php';
require_once __DIR__ . '/files.php';
require_once __DIR__ . '/layout.php';

/* Keep sessions inside the project so they work the same on every machine,
   regardless of the server's default session folder. */
$sessionDir = STORAGE_DIR . '/sessions';
if (!is_dir($sessionDir)) {
    mkdir($sessionDir, 0775, true);
}
session_save_path($sessionDir);
session_name('classconnect_session');
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 7,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);
session_start();

db(); // Creates the database file and tables on first run.
