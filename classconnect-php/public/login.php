<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$form = ['email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(post_text('email'));
    $password = (string) ($_POST['password'] ?? '');
    $form['email'] = $email;
    $throttleKey = $email . '|' . ($_SERVER['REMOTE_ADDR'] ?? '');

    if (login_throttled($throttleKey)) {
        flash('error', 'Too many failed attempts. Please wait 15 minutes and try again.');
    } else {
        $user = one('SELECT id, password_hash FROM users WHERE email = ?', [$email]);
        if ($user !== null && password_verify($password, $user['password_hash'])) {
            clear_failed_logins($throttleKey);
            start_user_session((int) $user['id']);
            flash('success', 'Welcome back!');
            redirect('dashboard.php');
        }
        record_failed_login($throttleKey);
        flash('error', 'Incorrect email or password.');
    }
}

page_start('Log in');
?>
<div class="container auth-wrap">
  <div class="card auth-card">
    <div class="auth-head">
      <span class="brand-mark"><?= icon('school') ?></span>
      <h1>Welcome back</h1>
      <p class="muted">Log in to continue to ClassConnect.</p>
    </div>
    <form method="post" class="form" data-busy>
      <?= csrf_field() ?>
      <div class="field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" autocomplete="email" maxlength="120" required autofocus>
      </div>
      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="current-password" maxlength="128" required>
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon('login') ?> Log in</button>
    </form>
    <p class="auth-switch">New to ClassConnect? <a href="register.php">Create an account</a></p>
  </div>
</div>
<?php
page_end();
