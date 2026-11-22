<?php
/* Quick check that the project files are in place and PHP can load them.
   Run from the project folder:  php tests\run-smoke.php */

$root = dirname(__DIR__);
$required = [
    'public/index.php', 'public/dashboard.php', 'public/upload.php', 'public/download.php',
    'public/action.php', 'public/assets/css/style.css', 'public/assets/js/app.js',
    'app/bootstrap.php', 'app/db.php', 'app/auth.php', 'app/files.php', 'app/layout.php',
];
$failures = 0;
foreach ($required as $file) {
    if (!is_file("$root/$file")) {
        echo "MISSING  $file\n";
        $failures++;
    }
}
foreach (['pdo_sqlite', 'sqlite3', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        echo "MISSING EXTENSION  $extension (enable it in php.ini)\n";
        $failures++;
    }
}
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    echo "PHP 8.0 or newer is required (found " . PHP_VERSION . ")\n";
    $failures++;
}
echo $failures === 0 ? "All checks passed. Start the app with: php -S localhost:8000 -t public\n"
                     : "$failures problem(s) found. See above.\n";
exit($failures === 0 ? 0 : 1);
