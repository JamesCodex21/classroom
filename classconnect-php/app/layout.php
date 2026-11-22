<?php
declare(strict_types=1);

/* Page frame (header, sidebar, footer) and reusable card components. */

$GLOBALS['app_shell'] = false;

function page_start(string $title, string $heading = ''): void
{
    $user = current_user();
    $GLOBALS['app_shell'] = $user !== null;
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="description" content="ClassConnect is an online classroom where teachers share learning materials and students access them anytime.">
  <title><?= e($title) ?> | <?= APP_NAME ?></title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Crect width='24' height='24' rx='6' fill='%231d4ed8'/%3E%3Cpath fill='white' d='M12 5 4 9.2l8 4.3 6.5-3.5V15h1.5V9.2z'/%3E%3C/svg%3E">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php if ($user !== null): ?>
<div class="app-shell">
  <aside class="sidebar" id="sidebar" aria-label="Main navigation">
    <a class="brand" href="dashboard.php">
      <span class="brand-mark"><?= icon('school') ?></span>
      <span class="brand-name">ClassConnect</span>
    </a>
    <nav class="nav">
      <?= nav_link('dashboard.php', 'home', 'Dashboard') ?>
      <?php if ($user['role'] === ROLE_TEACHER): ?>
        <?= nav_link('classroom-form.php', 'plus', 'New classroom') ?>
        <?= nav_link('upload.php', 'upload', 'Upload materials') ?>
      <?php else: ?>
        <?= nav_link('join.php', 'user-plus', 'Join classroom') ?>
      <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
      <div class="user-chip">
        <span class="avatar"><?= e(first_letter($user['full_name'])) ?></span>
        <div class="user-meta">
          <strong><?= e($user['full_name']) ?></strong>
          <small><?= $user['role'] === ROLE_TEACHER ? 'Teacher' : 'Student' ?></small>
        </div>
      </div>
      <form method="post" action="logout.php">
        <?= csrf_field() ?>
        <button class="nav-link nav-button" type="submit"><?= icon('logout') ?> Log out</button>
      </form>
    </div>
  </aside>
  <div class="sidebar-backdrop" data-close-sidebar></div>

  <div class="main-column">
    <header class="topbar">
      <button class="icon-btn" type="button" id="menu-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Open menu"><?= icon('menu') ?></button>
      <span class="topbar-title"><?= e($heading !== '' ? $heading : APP_NAME) ?></span>
    </header>
    <main class="content">
      <?php render_flashes(); ?>
<?php else: ?>
<header class="public-header">
  <div class="container public-header-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark"><?= icon('school') ?></span>
      <span class="brand-name">ClassConnect</span>
    </a>
    <nav class="public-actions">
      <a class="btn btn-ghost" href="login.php">Log in</a>
      <a class="btn btn-primary" href="register.php">Register</a>
    </nav>
  </div>
</header>
<main class="public-main">
  <div class="container"><?php render_flashes(); ?></div>
<?php endif; ?>
<?php
}

function page_end(): void
{
    echo $GLOBALS['app_shell'] ? "</main>\n  </div>\n</div>\n" : "</main>\n";
    echo "<script src=\"assets/js/app.js\"></script>\n</body>\n</html>\n";
}

function nav_link(string $href, string $iconName, string $label): string
{
    $current = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $active = $current === basename($href) ? ' active' : '';
    return '<a class="nav-link' . $active . '" href="' . e($href) . '">' . icon($iconName) . ' ' . e($label) . '</a>';
}

function render_flashes(): void
{
    $items = take_flashes();
    if (!$items) {
        return;
    }
    echo '<div class="flash-stack" role="status" aria-live="polite">';
    foreach ($items as $item) {
        $kind = in_array($item['type'], ['success', 'error', 'info'], true) ? $item['type'] : 'info';
        $iconName = $kind === 'success' ? 'check' : ($kind === 'error' ? 'alert' : 'info');
        echo '<div class="alert alert-' . $kind . '" data-alert>'
            . icon($iconName)
            . '<span class="alert-text">' . e($item['message']) . '</span>'
            . '<button type="button" class="alert-close" data-dismiss aria-label="Dismiss message">' . icon('x') . '</button>'
            . '</div>';
    }
    echo '</div>';
}

function render_error(int $code, string $message): void
{
    http_response_code($code);
    $titles = [400 => 'Bad request', 403 => 'Forbidden', 404 => 'Not found', 405 => 'Method not allowed'];
    $title = $titles[$code] ?? 'Something went wrong';
    $loggedIn = current_user() !== null;
    page_start($title, $title);
    ?>
<div class="card error-card">
  <span class="error-code"><?= $code ?></span>
  <h1><?= e($title) ?></h1>
  <p class="muted"><?= e($message) ?></p>
  <a class="btn btn-primary" href="<?= $loggedIn ? 'dashboard.php' : 'index.php' ?>"><?= $loggedIn ? 'Back to dashboard' : 'Back to home' ?></a>
</div>
<?php
    page_end();
    exit;
}

function not_found(): void
{
    render_error(404, "We couldn't find that page, or you don't have access to it.");
}

/** Classroom summary card. Teachers see counts and the class code; students see the teacher. */
function class_card(array $c, string $role): void
{
    $href = 'classroom.php?id=' . (int) $c['id'];
    ?>
<a class="card class-card" href="<?= e($href) ?>">
  <div class="class-band">
    <span class="class-initial"><?= e(first_letter($c['name'])) ?></span>
    <?php if (!empty($c['subject'])): ?><span class="class-subject"><?= e($c['subject']) ?></span><?php endif; ?>
  </div>
  <div class="class-body">
    <h3 class="class-name"><?= e($c['name']) ?></h3>
    <?php if ($role === ROLE_TEACHER): ?>
      <p class="muted small clamp-2"><?= e($c['description'] ?: 'No description yet.') ?></p>
    <?php else: ?>
      <p class="muted small">Teacher: <?= e($c['teacher_name']) ?></p>
    <?php endif; ?>
    <div class="class-stats">
      <?php if ($role === ROLE_TEACHER): ?>
        <span><?= icon('users') ?> <?= e(plural((int) $c['student_count'], 'student', 'students')) ?></span>
      <?php endif; ?>
      <span><?= icon('file') ?> <?= e(plural((int) $c['material_count'], 'material', 'materials')) ?></span>
    </div>
    <?php if ($role === ROLE_TEACHER): ?><span class="code-chip">Class code: <?= e($c['class_code']) ?></span><?php endif; ?>
  </div>
</a>
<?php
}

/** Learning material card with download, preview, and (for the owner) edit and delete. */
function material_card(array $m, bool $owner = false, bool $showClassroom = false, string $returnTo = 'upload'): void
{
    $id = (int) $m['id'];
    ?>
<article class="card material-card">
  <div class="material-head">
    <span class="file-badge" title="<?= e($m['original_name']) ?>"><?= e(file_type_label($m['original_name'])) ?></span>
    <div class="material-heading-text">
      <h3 class="material-title"><?= e($m['title']) ?></h3>
      <?php if ($showClassroom && !empty($m['classroom_name'])): ?><span class="small muted"><?= e($m['classroom_name']) ?></span><?php endif; ?>
    </div>
  </div>
  <p class="material-desc<?= empty($m['description']) ? ' muted' : '' ?>"><?= e($m['description'] ?: 'No description provided.') ?></p>
  <ul class="meta-list">
    <li><?= icon('clock') ?> Uploaded <?= e(short_date($m['uploaded_at'])) ?></li>
    <li><?= icon('file') ?> <?= e(format_size((int) $m['file_size'])) ?></li>
  </ul>
  <div class="card-actions">
    <a class="btn btn-primary btn-sm" href="download.php?id=<?= $id ?>"><?= icon('download') ?> Download</a>
    <?php if (is_previewable($m['mime_type'])): ?>
      <a class="btn btn-secondary btn-sm" href="download.php?id=<?= $id ?>&amp;inline=1" target="_blank" rel="noopener"><?= icon('eye') ?> View</a>
    <?php endif; ?>
    <?php if ($owner): ?>
      <a class="btn btn-secondary btn-sm" href="material-edit.php?id=<?= $id ?>"><?= icon('edit') ?> Edit</a>
      <form method="post" action="action.php" class="inline-form" data-confirm="Delete “<?= e($m['title']) ?>”? Students will lose access to it.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_material">
        <input type="hidden" name="material_id" value="<?= $id ?>">
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <button class="btn btn-danger-outline btn-sm" type="submit"><?= icon('trash') ?> Delete</button>
      </form>
    <?php endif; ?>
  </div>
</article>
<?php
}
