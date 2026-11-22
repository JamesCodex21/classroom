<?php
require __DIR__ . '/../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();
end_user_session();
flash('info', 'You have been logged out.');
redirect('index.php');
