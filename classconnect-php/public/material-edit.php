<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login(ROLE_TEACHER);
[$material, $isOwner] = material_for_viewer((int) ($_GET['id'] ?? 0), $user);
if (!$isOwner) {
    not_found();
}

$form = ['title' => $material['title'], 'description' => $material['description']];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_too_large()) {
        flash('error', 'That file is larger than the server allows. Choose a file under ' . MAX_UPLOAD_MB . ' MB.');
        redirect('material-edit.php?id=' . (int) $material['id']);
    }
    verify_csrf();

    $form = ['title' => post_text('title'), 'description' => post_text('description')];
    $errors = validate_material_fields($form);

    $hasNewFile = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;
    $newFile = null;
    if (!$errors && $hasNewFile) {
        try {
            $newFile = store_upload($_FILES['file']);
        } catch (UploadException $exception) {
            $errors['file'] = $exception->getMessage();
        }
    }

    if (!$errors) {
        if ($newFile !== null) {
            run(
                "UPDATE learning_materials
                 SET title = ?, description = ?, original_name = ?, stored_name = ?, mime_type = ?, file_size = ?, updated_at = datetime('now')
                 WHERE id = ?",
                [
                    $form['title'], $form['description'], $newFile['original_name'], $newFile['stored_name'],
                    $newFile['mime_type'], $newFile['file_size'], (int) $material['id'],
                ]
            );
            delete_stored_file($material['stored_name']);
        } else {
            run(
                "UPDATE learning_materials SET title = ?, description = ?, updated_at = datetime('now') WHERE id = ?",
                [$form['title'], $form['description'], (int) $material['id']]
            );
        }
        flash('success', 'Material updated.');
        redirect('upload.php?classroom=' . (int) $material['classroom_id']);
    }
    flash('error', 'Please fix the highlighted fields and try again.');
}

page_start('Edit material', 'Edit material');
?>
<div class="page-head">
  <div>
    <h1>Edit material</h1>
    <p class="muted">In <?= e($material['classroom_name']) ?></p>
  </div>
  <a class="btn btn-secondary" href="upload.php?classroom=<?= (int) $material['classroom_id'] ?>"><?= icon('arrow-left') ?> Back to materials</a>
</div>

<section class="card form-card narrow-card">
  <form method="post" enctype="multipart/form-data" class="form" data-busy>
    <?= csrf_field() ?>

    <div class="field">
      <label for="title">Title</label>
      <input id="title" name="title" value="<?= e($form['title']) ?>" minlength="2" maxlength="120" required class="<?= !empty($errors['title']) ? 'is-invalid' : '' ?>">
      <?php if (!empty($errors['title'])): ?><p class="field-error"><?= e($errors['title']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="description">Description <span class="muted">(optional)</span></label>
      <textarea id="description" name="description" rows="3" maxlength="1000" class="<?= !empty($errors['description']) ? 'is-invalid' : '' ?>"><?= e($form['description']) ?></textarea>
      <?php if (!empty($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>
    </div>

    <div class="current-file">
      <span class="file-badge"><?= e(file_type_label($material['original_name'])) ?></span>
      <div>
        <strong><?= e($material['original_name']) ?></strong>
        <small class="muted"><?= e(format_size((int) $material['file_size'])) ?> · uploaded <?= e(short_date($material['uploaded_at'])) ?></small>
      </div>
    </div>

    <div class="field">
      <label for="file">Replace file <span class="muted">(optional)</span></label>
      <input id="file" name="file" type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.md,.csv,.png,.jpg,.jpeg,.gif,.webp" data-accept-exts="pdf,doc,docx,ppt,pptx,xls,xlsx,txt,md,csv,png,jpg,jpeg,gif,webp" data-max-mb="25">
      <p class="hint" data-file-info>Leave this empty to keep the current file.</p>
      <?php if (!empty($errors['file'])): ?><p class="field-error"><?= e($errors['file']) ?></p><?php endif; ?>
    </div>

    <div class="form-actions">
      <a class="btn btn-ghost" href="upload.php?classroom=<?= (int) $material['classroom_id'] ?>">Cancel</a>
      <button class="btn btn-primary" type="submit"><?= icon('check') ?> Save changes</button>
    </div>
  </form>
</section>
<?php
page_end();
