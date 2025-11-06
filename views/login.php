<?php ob_start(); $cfg = require __DIR__ . '/../config/config.php'; ?>
<h1>تسجيل الدخول</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/login">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>اسم المستخدم</label>
  <input class="input" name="username" required>
  <label>كلمة المرور</label>
  <input class="input" type="password" name="password" required>
  <button class="btn" type="submit">دخول</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>