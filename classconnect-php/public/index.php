<?php
require __DIR__ . '/../app/bootstrap.php';

if (current_user() !== null) {
    redirect('dashboard.php');
}

page_start('Online classroom for teachers and students');
?>
<section class="hero">
  <div class="container hero-grid">
    <div class="hero-copy">
      <span class="eyebrow">Online classroom for teachers and students</span>
      <h1>Share learning materials. Learn anytime, anywhere.</h1>
      <p class="lead">ClassConnect brings every class into one organized space. Teachers create classrooms and upload lesson files, and students join with a class code to download what they need from any device.</p>
      <div class="cta-row">
        <a class="btn btn-primary btn-lg" href="register.php"><?= icon('user-plus') ?> Get started free</a>
        <a class="btn btn-secondary btn-lg" href="login.php"><?= icon('login') ?> Log in</a>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true">
      <div class="mock mock-main">
        <div class="mock-band"><strong>Biology 101</strong><small>Ms. Santos</small></div>
        <div class="mock-row"><span class="file-badge">PDF</span><div><strong>Cell structure notes</strong><small>Uploaded today</small></div></div>
        <div class="mock-row"><span class="file-badge">PPTX</span><div><strong>Week 4 lecture slides</strong><small>Uploaded yesterday</small></div></div>
        <div class="mock-row"><span class="file-badge">DOCX</span><div><strong>Lab report template</strong><small>Uploaded Monday</small></div></div>
      </div>
      <div class="mock mock-chip">
        <span class="avatar sm mock-check"><?= icon('check') ?></span>
        <div><strong>24 students joined</strong><small>With a class code</small></div>
      </div>
    </div>
  </div>
</section>

<section class="container features">
  <article class="card feature">
    <span class="stat-icon"><?= icon('school') ?></span>
    <h3>Organized classrooms</h3>
    <p class="muted">Create a classroom for each course, add a subject and description, and manage everything from one dashboard.</p>
  </article>
  <article class="card feature">
    <span class="stat-icon"><?= icon('upload') ?></span>
    <h3>Simple material sharing</h3>
    <p class="muted">Upload PDFs, Word and PowerPoint files, spreadsheets, and images with titles and descriptions for every item.</p>
  </article>
  <article class="card feature">
    <span class="stat-icon"><?= icon('users') ?></span>
    <h3>Join with one code</h3>
    <p class="muted">Students enter a class code to join, see every class they belong to, and download materials whenever they need them.</p>
  </article>
</section>

<section class="container how-it-works">
  <h2 class="center-title">How it works</h2>
  <ol class="steps">
    <li><span class="step-num">1</span><div><h3>Create or join</h3><p class="muted">Teachers create a classroom and share its code. Students enter the code to join.</p></div></li>
    <li><span class="step-num">2</span><div><h3>Share materials</h3><p class="muted">Teachers upload files to a classroom. Students see them on their dashboard right away.</p></div></li>
    <li><span class="step-num">3</span><div><h3>Learn anywhere</h3><p class="muted">Students open or download materials from any device, whenever they need them.</p></div></li>
  </ol>
</section>

<footer class="public-footer">
  <div class="container footer-inner">
    <span>ClassConnect · Online classroom</span>
    <span>Built for teachers and students</span>
  </div>
</footer>
<?php
page_end();
