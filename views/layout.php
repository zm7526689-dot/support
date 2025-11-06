<?php $cfg = require __DIR__ . '/../config/config.php'; ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= Utils::h($cfg['APP_NAME']) ?></title>
  <link rel="stylesheet" href="<?= $cfg['APP_URL'] ?>/assets/css/app.css">
  <script type="module" src="<?= $cfg['APP_URL'] ?>/assets/js/app.js"></script>
</head>
<body>
<header class="topbar">
  <nav>
    <span class="brand">SupportOps</span>
    <a href="<?= $cfg['APP_URL'] ?>/dashboard">لوحة التحكم</a>
    <a href="<?= $cfg['APP_URL'] ?>/customers">العملاء</a>
    <a href="<?= $cfg['APP_URL'] ?>/tickets">التذاكر</a>
    <form class="actions" action="<?= $cfg['APP_URL'] ?>/search" method="get">
      <input class="input" name="q" placeholder="ابحث: كود 33، Pylontech 5001...">
      <button class="btn outline" type="submit">بحث</button>
      <?php if(Auth::check()): ?><a class="btn danger" href="<?= $cfg['APP_URL'] ?>/logout">خروج</a><?php endif; ?>
    </form>
  </nav>
</header>
<div class="toast"></div>
<main class="container">
  <?php if(isset($content)) echo $content; ?>
</main>
</body>
</html>