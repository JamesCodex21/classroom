<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
[$classroom, $isOwner] = classroom_for_viewer((int) ($_GET['id'] ?? 0), $user);
$classroomId = (int) $classroom['id'];

$materials = all(
    'SELECT * FROM learning_materials WHERE classroom_id = ? ORDER BY uploaded_at DESC, id DESC',
    [$classroomId]
);
$memberCount = (int) one('SELECT COUNT(*) AS n FROM classroom_members WHERE classroom_id = ?', [$classroomId])['n'];
$students = $isOwner ? all(
    'SELECT u.id, u.full_name, u.email, cm.joined_at
     FROM classroom_members cm JOIN users u ON u.id = cm.student_id
     WHERE cm.classroom_id = ?
     ORDER BY u.full_name COLLATE NOCASE',
    [$classroomId]
) : [];

page_start($classroom['name'], $classroom['name']);
?>
<section class="card class-hero">
  <div class="class-hero-main">
    <span class="eyebrow"><?= e($classroom['subject'] ?: 'Classroom') ?></span>
    <h1><?= e($classroom['name']) ?></h1>
    <p class="muted">Taught by <?= e($classroom['teacher_name']) ?> · <?= e(plural($memberCount, 'student', 'students')) ?></p>
    <?php if ($classroom['description']): ?><p class="class-desc"><?= e($classroom['description']) ?></p><?php endif; ?>
  </div>
  <div class="class-code-box">
    <span class="small muted">Class code</span>
    <div class="code-row">
      <code><?= e($classroom['class_code']) ?></code>
      <button type="button" class="icon-btn" data-copy="<?= e($classroom['class_code']) ?>" aria-label="Copy class code" title="Copy class code"><?= icon('copy') ?></button>
    </div>
    <span class="small muted">Students enter this code to join.</span>
  </div>
</section>

<div class="toolbar">
  <?php if ($isOwner): ?>
    <a class="btn btn-primary" href="upload.php?classroom=<?= $classroomId ?>"><?= icon('upload') ?> Upload material</a>
    <a class="btn btn-secondary" href="classroom-form.php?id=<?= $classroomId ?>"><?= icon('edit') ?> Edit classroom</a>
  <?php else: ?>
    <form method="post" action="action.php" class="inline-form" data-confirm="Leave “<?= e($classroom['name']) ?>”? You can rejoin later with the class code.">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="leave">
      <input type="hidden" name="classroom_id" value="<?= $classroomId ?>">
      <button class="btn btn-danger-outline" type="submit"><?= icon('logout') ?> Leave classroom</button>
    </form>
  <?php endif; ?>
</div>

<div class="split">
  <section>
    <div class="section-head">
      <h2>Learning materials</h2>
      <span class="badge"><?= count($materials) ?></span>
    </div>
    <?php if ($materials): ?>
      <div class="card-grid">
        <?php foreach ($materials as $m) { material_card($m, $isOwner, false, 'classroom'); } ?>
      </div>
    <?php else: ?>
      <div class="card empty">
        <?= icon('file') ?>
        <h2>No materials yet</h2>
        <?php if ($isOwner): ?>
          <p>Upload lessons, handouts, or slides and students will see them here.</p>
          <a class="btn btn-primary" href="upload.php?classroom=<?= $classroomId ?>"><?= icon('upload') ?> Upload material</a>
        <?php else: ?>
          <p>Your teacher hasn't shared anything yet. Check back soon.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <aside class="stack">
    <?php if ($isOwner): ?>
      <div class="card side-card">
        <div class="section-head">
          <h2>Students</h2>
          <span class="badge"><?= count($students) ?></span>
        </div>
        <?php if ($students): ?>
          <ul class="student-list">
            <?php foreach ($students as $s): ?>
              <li>
                <span class="avatar sm"><?= e(first_letter($s['full_name'])) ?></span>
                <div class="student-meta">
                  <strong><?= e($s['full_name']) ?></strong>
                  <small><?= e($s['email']) ?> · joined <?= e(short_date($s['joined_at'])) ?></small>
                </div>
                <form method="post" action="action.php" class="inline-form" data-confirm="Remove <?= e($s['full_name']) ?> from this classroom?">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="remove_student">
                  <input type="hidden" name="classroom_id" value="<?= $classroomId ?>">
                  <input type="hidden" name="student_id" value="<?= (int) $s['id'] ?>">
                  <button class="icon-btn danger" type="submit" aria-label="Remove <?= e($s['full_name']) ?>" title="Remove student"><?= icon('user-minus') ?></button>
                </form>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p class="muted small">No students have joined yet. Share the class code above to get started.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="card side-card">
        <h2>About this class</h2>
        <p class="muted small">Download any material shared here. Files you can view in the browser, such as PDFs and images, also open in a new tab.</p>
      </div>
    <?php endif; ?>
  </aside>
</div>
<?php
page_end();
