<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login(ROLE_STUDENT);
$code = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = strtoupper((string) preg_replace('/[\s-]+/', '', post_text('class_code')));

    if (!preg_match('/^[A-Z0-9]{6}$/', $code)) {
        flash('error', 'Class codes are 6 letters or numbers. Check the code and try again.');
    } else {
        $classroom = one('SELECT id, name FROM classrooms WHERE class_code = ?', [$code]);
        if ($classroom === null) {
            flash('error', 'No classroom matches that code. Check it with your teacher and try again.');
        } else {
            $classroomId = (int) $classroom['id'];
            $existing = one(
                'SELECT 1 AS found FROM classroom_members WHERE classroom_id = ? AND student_id = ?',
                [$classroomId, (int) $user['id']]
            );
            if ($existing !== null) {
                flash('info', 'You have already joined "' . $classroom['name'] . '".');
            } else {
                run(
                    'INSERT INTO classroom_members (classroom_id, student_id) VALUES (?, ?)',
                    [$classroomId, (int) $user['id']]
                );
                flash('success', 'You joined "' . $classroom['name'] . '". Welcome!');
            }
            redirect('classroom.php?id=' . $classroomId);
        }
    }
}

page_start('Join a classroom', 'Join a classroom');
?>
<div class="narrow-card">
  <div class="page-head page-head-center">
    <div>
      <h1>Join a classroom</h1>
      <p class="muted">Enter the 6-character class code your teacher shared with you.</p>
    </div>
  </div>

  <section class="card form-card">
    <form method="post" class="form" data-busy>
      <?= csrf_field() ?>
      <div class="field">
        <label for="class_code">Class code</label>
        <input id="class_code" name="class_code" value="<?= e($code) ?>" class="code-input" maxlength="9" autocomplete="off" autocapitalize="characters" spellcheck="false" placeholder="ABC123" required aria-describedby="code-hint">
        <p class="hint" id="code-hint">Codes are 6 letters or numbers and are not case-sensitive.</p>
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon('user-plus') ?> Join classroom</button>
    </form>
  </section>

  <p class="muted small center-text">Don't have a code? Ask your teacher to open their classroom page and share the code shown there.</p>
</div>
<?php
page_end();
