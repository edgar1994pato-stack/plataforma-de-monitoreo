<?php
if (!isset($PAGE_TITLE))        $PAGE_TITLE = "📄 Página";
if (!isset($PAGE_SUBTITLE))     $PAGE_SUBTITLE = "";
if (!isset($PAGE_ACTION_HTML))  $PAGE_ACTION_HTML = "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($PAGE_TITLE) ?> - Alfanet</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="<?= htmlspecialchars($BASE_URL) ?>/assets_recursos/theme.css">
</head>
<body>

<div class="app-topbar">
  <div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center py-3">
      <div>
        <div class="app-title"><?= htmlspecialchars($PAGE_TITLE) ?></div>
        <?php if (!empty($PAGE_SUBTITLE)): ?>
          <div class="app-subtitle"><?= htmlspecialchars($PAGE_SUBTITLE) ?></div>
        <?php endif; ?>
      </div>

      <?php if (!empty($PAGE_ACTION_HTML)): ?>
        <div class="d-flex gap-2 align-items-center">
          <?= $PAGE_ACTION_HTML ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="container-fluid mt-4 px-4">




