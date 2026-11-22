<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login();
$isTeacher = $user['role'] === ROLE_TEACHER;

if ($isTeacher) {
    $classrooms = all(
        'SELECT c.id, c.name, c.subject, c.description, c.class_code,
                (SELECT COUNT(*) FROM classroom_members cm WHERE cm.classroom_id = c.id) AS student_count,
                (SELECT COUNT(*) FROM learning_materials lm WHERE lm.classroom_id = c.id) AS material_count
         FROM classrooms c
         WHERE c.teacher_id = ?
         ORDER BY c.created_at DESC, c.id DESC',
        [(int) $user['id']]
    );
    $totals = one(
        'SELECT
            (SELECT COUNT(DISTINCT cm.student_id) FROM classroom_members cm
               JOIN classrooms c ON c.id = cm.classroom_id WHERE c.teacher_id = ?) AS students,
            (SELECT COUNT(*) FROM learning_materials lm
               JOIN classrooms c ON c.id = lm.classroom_id WHERE c.teacher_id = ?) AS materials',
        [(int) $user['id'], (int) $user['id']]
    );
    $recent = all(
        'SELECT m.*, c.name AS classroom_name
         FROM learning_materials m JOIN classrooms c ON c.id = m.classroom_id
         WHERE c.teacher_id = ?
         ORDER BY m.uploaded_at DESC, m.id DESC
         LIMIT 6',
        [(int) $user['id']]
    );
} else {
    $classrooms = all(
        'SELECT c.id, c.name, c.subject, c.description, u.full_name AS teacher_name,
                (SELECT COUNT(*) FROM learning_materials lm WHERE lm.classroom_id = c.id) AS material_count
         FROM classroom_members cm
         JOIN classrooms c ON c.id = cm.classroom_id
         JOIN users u ON u.id = c.teacher_id
         WHERE cm.student_id = ?
         ORDER BY cm.joined_at DESC, c.id DESC',
        [(int) $user['id']]
    );
    $recent = all(
        'SELECT m.*, c.name AS classroom_name
         FROM learning_materials m
         JOIN classrooms c ON c.id = m.classroom_id
         JOIN classroom_members cm ON cm.classroom_id = c.id AND cm.student_id = ?
         ORDER BY m.uploaded_at DESC, m.id DESC
         LIMIT 8',
        [(int) $user['id']]
    );
    $totalMaterials = array_sum(array_map(static fn(array $c): int => (int) $c['material_count'], $classrooms));
}

$role = $isTeacher ? ROLE_TEACHER : ROLE_STUDENT;
page_start($isTeacher ? 'Teacher dashboard' : 'Student dashboard', 'Dashboard');
?>
<div class="page-head">
  <div>
    <span class="eyebrow"><?= $isTeacher ? 'Teacher' : 'Student' ?></span>
    <h1><?= $isTeacher ? 'Welcome back' : 'Hi' ?>, <?= e(first_name($user['full_name'])) ?></h1>
    <p class="muted"><?= $isTeacher
        ? 'Manage your classrooms, see who has joined, and keep materials up to date.'
        : 'Your classes and the latest materials, all in one place.' ?></p>
  </div>
  <div class="page-actions">
    <?php if ($isTeacher): ?>
      <a class="btn btn-primary" href="classroom-form.php"><?= icon('plus') ?> New classroom</a>
      <a class="btn btn-secondary" href="upload.php"><?= icon('upload') ?> Upload materials</a>
    <?php else: ?>
      <a class="btn btn-primary" href="join.php"><?= icon('user-plus') ?> Join classroom</a>
    <?php endif; ?>
  </div>
</div>

<div class="stat-grid">
  <div class="card stat"><span class="stat-icon"><?= icon('book') ?></span><div><strong><?= count($classrooms) ?></strong><span><?= $isTeacher ? 'Classrooms' : 'Joined classrooms' ?></span></div></div>
  <?php if ($isTeacher): ?>
    <div class="card stat"><span class="stat-icon"><?= icon('users') ?></span><div><strong><?= (int) $totals['students'] ?></strong><span>Students joined</span></div></div>
    <div class="card stat"><span class="stat-icon"><?= icon('file') ?></span><div><strong><?= (int) $totals['materials'] ?></strong><span>Materials shared</span></div></div>
  <?php else: ?>
    <div class="card stat"><span class="stat-icon"><?= icon('file') ?></span><div><strong><?= (int) $totalMaterials ?></strong><span>Materials available</span></div></div>
  <?php endif; ?>
</div>

<section class="section">
  <div class="section-head"><h2><?= $isTeacher ? 'Your classrooms' : 'My classrooms' ?></h2></div>
  <?php if ($classrooms): ?>
    <div class="card-grid">
      <?php foreach ($classrooms as $c) { class_card($c, $role); } ?>
    </div>
  <?php elseif ($isTeacher): ?>
    <div class="card empty">
      <?= icon('school') ?>
      <h2>No classrooms yet</h2>
      <p>Create your first classroom to get a class code for your students.</p>
      <a class="btn btn-primary" href="classroom-form.php"><?= icon('plus') ?> Create classroom</a>
    </div>
  <?php else: ?>
    <div class="card empty">
      <?= icon('users') ?>
      <h2>You haven't joined a classroom yet</h2>
      <p>Ask your teacher for the 6-character class code, then join below.</p>
      <a class="btn btn-primary" href="join.php"><?= icon('user-plus') ?> Join with a code</a>
    </div>
  <?php endif; ?>
</section>

<section class="section">
  <div class="section-head">
    <h2>Recently uploaded</h2>
    <?php if ($isTeacher && $recent): ?><a href="upload.php">View all materials</a><?php endif; ?>
  </div>
  <?php if ($recent): ?>
    <div class="card-grid">
      <?php foreach ($recent as $m) { material_card($m, $isTeacher, true, 'upload'); } ?>
    </div>
  <?php else: ?>
    <div class="card empty">
      <?= icon('file') ?>
      <h2>No materials yet</h2>
      <p><?= $isTeacher ? 'Files you upload will appear here.' : 'When your teachers upload materials, they will show up here.' ?></p>
    </div>
  <?php endif; ?>
</section>
<?php
page_end();
