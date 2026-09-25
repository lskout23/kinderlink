<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= APP_NAME_GR ?></title>
  <!-- PWA -->
  <link rel="manifest" href="<?= BASE_URL ?>/manifest.json">
  <meta name="theme-color" content="#087f83">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="KinderLink">
  <meta name="application-name" content="KinderLink">
  <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/public/kinderlink-mark.svg">
  <link rel="apple-touch-icon" href="<?= APP_URL ?>/public/icons/icon-192.png">
  <!-- /PWA -->
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/style.css?v=20260924-1">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/ui.css?v=20260922-4">
  <link rel="stylesheet" href="<?= APP_URL ?>/public/css/kinderlink.css?v=20260924-3">
</head>
<body class="public-page modern-ui">

<!-- HEADER -->
<div id="app-header">
  <div class="logo">
    <?php
      $publicLogoHref = BASE_URL . '/';
      if (Auth::check()) {
        $publicLogoHref = Auth::isParent() ? BASE_URL . '/parent/dashboard' : BASE_URL . '/dashboard';
      }
    ?>
    <a href="<?= $publicLogoHref ?>" style="display:flex;align-items:center;gap:10px;color:inherit;text-decoration:none;">
      <img src="<?= APP_URL ?>/public/kinderlink-mark.svg" alt="KinderLink" class="brand-mascot">
      <div class="app-title"><?= APP_NAME_GR ?></div>
    </a>
  </div>
</div>

<div class="login-wrapper">
  <?php echo $content; ?>
</div>

<div id="app-footer">
  © <?= date('Y') ?> <?= APP_NAME_GR ?>
</div>

</body>
</html>
