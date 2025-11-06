<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تغيير كلمة المرور</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/users/password">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>كلمة المرور الحالية</label><input class="input" type="password" name="old" required>
  <label>كلمة المرور الجديدة</label><input class="input" type="password" name="new" required>
  <button class="btn" type="submit">تحديث</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>