<?php
require __DIR__ . '/../app/bootstrap.php';

$user = require_login(ROLE_TEACHER);

$classrooms = all('SELECT id, name FROM classrooms WHERE teacher_id = ? ORDER BY name COLLATE NOCASE', [(int) $user['id']]);
$ownedIds = array_map(static fn(array $c): int => (int) $c['id'], $classrooms);

$filterId = (int) ($_GET['classroom'] ?? 0);
if (!in_array($filterId, $ownedIds, true)) {
    $filterId = 0;
}

$form = ['classroom_id' => $filterId ?: ($ownedIds[0] ?? 0), 'title' => '', 'description' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post_too_large()) {
        flash('error', 'That upload is larger than the server allows. Choose a file under ' . MAX_UPLOAD_MB . ' MB.');
        redirect('upload.php');
    }
    verify_csrf();

    if ($classrooms) {
        $form = [
            'classroom_id' => (int) ($_POST['classroom_id'] ?? 0),
            'title' => post_text('title'),
            'description' => post_text('description'),
        ];
        $errors = validate_material_fields($form);
        if (!in_array($form['classroom_id'], $ownedIds, true)) {
            $errors['classroom_id'] = 'Choose one of your classrooms.';
        }

        if (!$errors) {
            try {
                $stored = store_upload($_FILES['file'] ?? null);
                insert(
                    'INSERT INTO learning_materials (classroom_id, title, description, original_name, stored_name, mime_type, file_size)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        $form['classroom_id'], $form['title'], $form['description'],
                        $stored['original_name'], $stored['stored_name'], $stored['mime_type'], $stored['file_size'],
                    ]
                );
                flash('success', '"' . $form['title'] . '" was uploaded. Students in this classroom can download it now.');
                redirect('upload.php?classroom=' . $form['classroom_id']);
            } catch (UploadException $exception) {
                $errors['file'] = $exception->getMessage();
            }
        }
        flash('error', 'Please fix the highlighted fields and try again.');
    }
}

$materials = materials_for_teacher((int) $user['id'], $filterId);

page_start('Upload materials', 'Upload materials');
?>
<div class="page-head">
  <div>
    <h1>Upload materials</h1>
    <p class="muted">Share PDFs, documents, slides, spreadsheets, and images with your students.</p>
  </div>
</div>

<?php if (upload_limit_too_low()): ?>
  <div class="alert alert-error" role="alert">
    <?= icon('alert') ?>
    <span class="alert-text">This server only accepts uploads up to <?= server_upload_limit_mb() ?> MB. Ask the administrator to set <code>upload_max_filesize = 25M</code> and <code>post_max_size = 26M</code> in php.ini, then restart the server.</span>
  </div>
<?php endif; ?>

<?php if (!$classrooms): ?>
  <div class="card empty">
    <?= icon('book') ?>
    <h2>Create a classroom first</h2>
    <p>Materials are shared with students through a classroom.</p>
    <a class="btn btn-primary" href="classroom-form.php"><?= icon('plus') ?> Create a classroom</a>
  </div>
<?php else: ?>
<div class="split upload-split">
  <section class="card form-card">
    <h2>New material</h2>
    <form method="post" enctype="multipart/form-data" class="form" data-busy>
      <?= csrf_field() ?>

      <div class="field">
        <label for="classroom_id">Classroom</label>
        <select id="classroom_id" name="classroom_id" required class="<?= !empty($errors['classroom_id']) ? 'is-invalid' : '' ?>">
          <?php foreach ($classrooms as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $form['classroom_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['classroom_id'])): ?><p class="field-error"><?= e($errors['classroom_id']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="title">Title</label>
        <input id="title" name="title" value="<?= e($form['title']) ?>" minlength="2" maxlength="120" required placeholder="e.g. Week 3 lecture slides" class="<?= !empty($errors['title']) ? 'is-invalid' : '' ?>">
        <?php if (!empty($errors['title'])): ?><p class="field-error"><?= e($errors['title']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="description">Description <span class="muted">(optional)</span></label>
        <textarea id="description" name="description" rows="3" maxlength="1000" placeholder="What is this file about?" class="<?= !empty($errors['description']) ? 'is-invalid' : '' ?>"><?= e($form['description']) ?></textarea>
        <?php if (!empty($errors['description'])): ?><p class="field-error"><?= e($errors['description']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <span class="label">File</span>
        <label class="dropzone" for="file">
          <?= icon('upload') ?>
          <span data-file-label>Choose a file or drop it here</span>
          <small>PDF, Word, PowerPoint, Excel, text, CSV, or image · up to 25 MB</small>
        </label>
        <input id="file" class="sr-only" name="file" type="file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.md,.csv,.png,.jpg,.jpeg,.gif,.webp" data-accept-exts="pdf,doc,docx,ppt,pptx,xls,xlsx,txt,md,csv,png,jpg,jpeg,gif,webp" data-max-mb="25">
        <p class="hint" data-file-info></p>
        <?php if (!empty($errors['file'])): ?><p class="field-error"><?= e($errors['file']) ?></p><?php endif; ?>
      </div>

      <button class="btn btn-primary btn-block btn-lg" type="submit"><?= icon('upload') ?> Upload material</button>
    </form>
  </section>

  <section>
    <div class="section-head">
      <h2>Your materials</h2>
      <form method="get" class="filter-form">
        <label class="sr-only" for="filter-classroom">Filter by classroom</label>
        <select id="filter-classroom" name="classroom" data-auto-submit>
          <option value="">All classrooms</option>
          <?php foreach ($classrooms as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === $filterId ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <noscript><button class="btn btn-secondary btn-sm" type="submit">Filter</button></noscript>
      </form>
    </div>

    <?php if ($materials): ?>
      <div class="card-grid">
        <?php foreach ($materials as $m) { material_card($m, true, true, 'upload'); } ?>
      </div>
    <?php else: ?>
      <div class="card empty">
        <?= icon('file') ?>
        <h2>No materials yet</h2>
        <p>Use the form to upload your first file.</p>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php endif; ?>
<?php
page_end();
