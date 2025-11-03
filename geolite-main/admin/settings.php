<?php
// TODO: Protect this page with your admin guard.
require_once '../incl/Auth.php';
if (!isLoggedIn() || !isAdmin()) { header('Location: /login.php'); exit; }

require_once '../incl/const.php';
require_once '../incl/db.php';
require_once '../incl/Settings.php';

session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

$settings = new Settings($pdo, '../assets/brand', '/assets/brand');
$current  = $settings->load();
$errors   = [];
$notice   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        [$saved, $errs] = $settings->save($_POST, $_FILES);
        $current = $saved;
        $errors  = $errs;
        if (!$errors) $notice = 'Settings updated successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Settings · Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root { --brand-primary: <?= htmlspecialchars($current['primary_color']) ?>; }
    .brand-swatch { width: 28px; height: 28px; border-radius: 8px; background: var(--brand-primary); border: 1px solid rgba(0,0,0,0.1); }
    .preview-img { max-height: 56px; }
    .navbar { background: white; border-bottom: 2px solid var(--brand-primary); }
  </style>
</head>
<body class="bg-light">
  <!-- Set header variables for the include -->
  <?php 
  $headerTitle = 'Settings';
  $headerSubtitle = 'Admin Settings';
  $headerIcon = 'gear';
  include '../incl/header.php'; 
  ?>

  <div class="container py-4">
    <div class="d-flex align-items-center mb-4">
      <h1 class="h4 mb-0">Site Settings</h1>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger"><ul class="mb-0">
        <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul></div>
    <?php endif; ?>

    <?php if ($notice): ?>
      <div class="alert alert-success"><?= htmlspecialchars($notice) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card p-3 shadow-sm bg-white">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

      <div class="mb-3">
        <label class="form-label">Site name</label>
        <input type="text" name="site_name" class="form-control" required
               value="<?= htmlspecialchars($current['site_name']) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label d-flex justify-content-between">Primary color
          <small class="text-muted">Hex (e.g., #10b981)</small>
        </label>
        <div class="input-group">
          <span class="input-group-text">#</span>
          <input type="text" name="primary_color" class="form-control"
                 value="<?= ltrim(htmlspecialchars($current['primary_color']), '#') ?>">
          <input type="color" class="form-control form-control-color" style="max-width: 60px"
                 value="<?= htmlspecialchars($current['primary_color']) ?>"
                 oninput="syncHex(this)">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Footer text</label>
        <input type="text" name="footer_text" class="form-control"
               value="<?= htmlspecialchars($current['footer_text']) ?>">
      </div>

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label d-flex justify-content-between">Logo (PNG/JPG/WebP/SVG)
            <?php if ($current['logo_url']): ?>
              <a href="<?= htmlspecialchars($current['logo_url']) ?>" target="_blank">View</a>
            <?php endif; ?>
          </label>
          <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg">
          <?php if ($current['logo_url']): ?>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="delete_logo" id="delete_logo" value="1">
              <label class="form-check-label" for="delete_logo">Remove current logo</label>
            </div>
            <div class="mt-2">
              <img src="<?= htmlspecialchars($current['logo_url']) ?>" class="preview-img" alt="Logo preview">
            </div>
          <?php endif; ?>
        </div>

        <div class="col-md-6">
          <label class="form-label d-flex justify-content-between">Hero image (PNG/JPG/WebP/SVG)
            <?php if ($current['hero_image']): ?>
              <a href="<?= htmlspecialchars($current['hero_image']) ?>" target="_blank">View</a>
            <?php endif; ?>
          </label>
          <input type="file" name="hero_image" class="form-control" accept=".png,.jpg,.jpeg,.webp,.svg">
          <?php if ($current['hero_image']): ?>
            <div class="form-check mt-2">
              <input class="form-check-input" type="checkbox" name="delete_hero" id="delete_hero" value="1">
              <label class="form-check-label" for="delete_hero">Remove current hero image</label>
            </div>
            <div class="mt-2">
              <img src="<?= htmlspecialchars($current['hero_image']) ?>" class="preview-img" alt="Hero preview">
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-check-circle"></i> Save settings
        </button>
        <a class="btn btn-outline-secondary" href="/login.php" target="_blank">
          <i class="bi bi-box-arrow-up-right"></i> Preview login
        </a>
        <a class="btn btn-outline-primary" href="../index.php">
          <i class="bi bi-house"></i> Back to Home
        </a>
      </div>
    </form>

    <p class="text-muted small mt-3">This updates the database. If <code>settings.php</code> exists, it is merged as fallback.</p>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function syncHex(colorInput) {
      const hexInput = document.querySelector('input[name="primary_color"]');
      hexInput.value = colorInput.value.replace(/^#/, '');
    }
  </script>
</body>
</html>
