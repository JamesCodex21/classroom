<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

$form = ['full_name' => '', 'email' => '', 'role' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = [
        'full_name' => post_text('full_name'),
        'email' => strtolower(post_text('email')),
        'role' => (string) ($_POST['role'] ?? ''),
    ];
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    $nameLength = text_length($form['full_name']);
    if ($nameLength < 2 || $nameLength > 80) {
        $errors['full_name'] = 'Enter your name (2 to 80 characters).';
    }
    if (!is_valid_email($form['email'])) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!in_array($form['role'], [ROLE_TEACHER, ROLE_STUDENT], true)) {
        $errors['role'] = 'Choose Teacher or Student.';
    }
    $problem = password_problem($password);
    if ($problem !== null) {
        $errors['password'] = $problem;
    } elseif (!hash_equals($password, $confirm)) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }
    if (!$errors && one('SELECT 1 AS found FROM users WHERE email = ?', [$form['email']]) !== null) {
        $errors['email'] = 'An account with this email already exists. Try logging in instead.';
    }

    if (!$errors) {
        try {
            $userId = insert(
                'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)',
                [$form['full_name'], $form['email'], password_hash($password, PASSWORD_DEFAULT), $form['role']]
            );
            start_user_session($userId);
            flash('success', 'Welcome to ClassConnect, ' . first_name($form['full_name']) . '! Your account is ready.');
            redirect('dashboard.php');
        } catch (PDOException $exception) {
            $errors['email'] = 'An account with this email already exists. Try logging in instead.';
        }
    }
    flash('error', 'Please fix the highlighted fields and try again.');
}

page_start('Create your account');
?>
<div class="container auth-wrap">
  <div class="card auth-card auth-card-wide">
    <div class="auth-head">
      <span class="brand-mark"><?= icon('school') ?></span>
      <h1>Create your account</h1>
      <p class="muted">Join ClassConnect as a teacher or a student.</p>
    </div>
    <form method="post" class="form" data-busy>
      <?= csrf_field() ?>

      <fieldset class="fieldset">
        <legend>I am a…</legend>
        <div class="role-options">
          <label class="role-option">
            <input type="radio" name="role" value="teacher" required <?= $form['role'] === 'teacher' ? 'checked' : '' ?>>
            <?= icon('book') ?>
            <strong>Teacher</strong>
            <small>Create classrooms and share materials</small>
          </label>
          <label class="role-option">
            <input type="radio" name="role" value="student" <?= $form['role'] === 'student' ? 'checked' : '' ?>>
            <?= icon('users') ?>
            <strong>Student</strong>
            <small>Join classes and access materials</small>
          </label>
        </div>
        <?php if (!empty($errors['role'])): ?><p class="field-error"><?= e($errors['role']) ?></p><?php endif; ?>
      </fieldset>

      <div class="field">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" value="<?= e($form['full_name']) ?>" autocomplete="name" minlength="2" maxlength="80" required class="<?= !empty($errors['full_name']) ? 'is-invalid' : '' ?>">
        <?php if (!empty($errors['full_name'])): ?><p class="field-error"><?= e($errors['full_name']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="email">Email address</label>
        <input id="email" name="email" type="email" value="<?= e($form['email']) ?>" autocomplete="email" maxlength="120" required class="<?= !empty($errors['email']) ? 'is-invalid' : '' ?>">
        <?php if (!empty($errors['email'])): ?><p class="field-error"><?= e($errors['email']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required class="<?= !empty($errors['password']) ? 'is-invalid' : '' ?>" aria-describedby="password-hint">
        <p class="hint" id="password-hint">At least 8 characters, including a letter and a number.</p>
        <?php if (!empty($errors['password'])): ?><p class="field-error"><?= e($errors['password']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required class="<?= !empty($errors['confirm_password']) ? 'is-invalid' : '' ?>">
        <?php if (!empty($errors['confirm_password'])): ?><p class="field-error"><?= e($errors['confirm_password']) ?></p><?php endif; ?>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon('user-plus') ?> Create account</button>
    </form>
    <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
  </div>
</div>
<?php
page_end();
