<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login(ROLE_TEACHER);
$classroomId = (int) ($_GET['id'] ?? 0);
$classroom = $classroomId > 0 ? owned_classroom($classroomId, $user) : null;

$form = $classroom !== null
    ? ['name' => $classroom['name'], 'subject' => $classroom['subject'], 'description' => $classroom['description']]
    : ['name' => '', 'subject' => '', 'description' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $form = [
        'name' => post_text('name'),
        'subject' => post_text('subject'),
        'description' => post_text('description'),
    ];
    $errors = validate_classroom($form);

    if (!$errors) {
        if ($classroom !== null) {
            run(
                'UPDATE classrooms SET name = ?, subject = ?, description = ? WHERE id = ?',
                [$form['name'], $form['subject'], $form['description'], (int) $classroom['id']]
            );
            flash('success', 'Classroom details saved.');
            redirect('classroom.php?id=' . (int) $classroom['id']);
        }
        $newId = create_classroom((int) $user['id'], $form);
        flash('success', '"' . $form['name'] . '" was created. Share its class code with your students.');
        redirect('classroom.php?id=' . $newId);
    }
    flash('error', 'Please fix the highlighted fields and try again.');
}

$isEdit = $classroom !== null;
page_start($isEdit ? 'Edit classroom' : 'New classroom', $isEdit ? 'Edit classroom' : 'New classroom');
?>
<div class="page-head">
  <div>
    <h1><?= $isEdit ? 'Edit classroom' : 'Create a classroom' ?></h1>
    <p class="muted"><?= $isEdit ? 'Update the details your students see.' : 'Give your class a name. You will get a class code to share with students.' ?></p>
  </div>
  <?php if ($isEdit): ?>
    <a class="btn btn-secondary" href="classroom.php?id=<?= (int) $classroom['id'] ?>"><?= icon('arrow-left') ?> Back to classroom</a>
  <?php endif; ?>
</div>

<div class="split">
  <section class="card form-card">
    <form method="post" class="form" data-busy>
      <?= csrf_field() ?>
      <div class="field">
        <label for="name">Class name</label>
        <input id="name" name="name" value="<?= e($form['name']) ?>" minlength="2" maxlength="80" required placeholder="e.g. Grade 10 Biology" class="<?= !empty($errors['name']) ? 'is-invalid' : '' ?>">
        <?php if (!empty($errors['name'])): ?><p class="field-error"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>
      <div class="field">
        <label for="subject">Subject <span class="muted">(optional)</span></label>
        <input id="subject" name="subject" value="<?= e($form['subject']) ?>" maxlength="60" placeholder="e.g. Science" class="<?= !empty($errors['subject']) ? 'is-invalid' : '' ?>">
        <?php if (!empty($errors['subject'])): ?><p class="field-error"><?= e($errors['subject']) ?></p><?php endif; ?>
      </div>
      <div class="field">
        <label for="description">Description <span class="muted">(optional)</span></label>
        <textarea id="description" name="description" rows="4" maxlength="500" placeholder="What will students learn in this class?" class="<?= !empty($errors['description']) ? 'is-invalid' : '' ?>"><?= e($form['description']) ?></textarea>
        <?php if (!empty($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>
      </div>
      <div class="form-actions">
        <a class="btn btn-ghost" href="<?= $isEdit ? 'classroom.php?id=' . (int) $classroom['id'] : 'dashboard.php' ?>">Cancel</a>
        <button class="btn btn-primary" type="submit"><?= icon('check') ?> <?= $isEdit ? 'Save changes' : 'Create classroom' ?></button>
      </div>
    </form>
  </section>

  <?php if ($isEdit): ?>
  <aside class="stack">
    <div class="card side-card">
      <h2>Class code</h2>
      <p class="muted small">Anyone with this code can join. Generating a new code stops the old one from working.</p>
      <div class="code-row"><code><?= e($classroom['class_code']) ?></code></div>
      <form method="post" action="action.php" class="mt" data-confirm="Generate a new class code? The current code will stop working.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="regenerate_code">
        <input type="hidden" name="classroom_id" value="<?= (int) $classroom['id'] ?>">
        <button class="btn btn-secondary btn-sm" type="submit"><?= icon('refresh') ?> Regenerate code</button>
      </form>
    </div>
    <div class="card side-card danger-zone">
      <h2>Delete classroom</h2>
      <p class="muted small">This removes the classroom, removes all students from it, and deletes its materials. This cannot be undone.</p>
      <form method="post" action="action.php" data-confirm="Delete this classroom and all of its materials? This cannot be undone.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_classroom">
        <input type="hidden" name="classroom_id" value="<?= (int) $classroom['id'] ?>">
        <button class="btn btn-danger-outline btn-sm" type="submit"><?= icon('trash') ?> Delete classroom</button>
      </form>
    </div>
  </aside>
  <?php endif; ?>
</div>
<?php
page_end();
