<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>إضافة مستخدم</h1>
<form method="post" action="<?= $cfg['APP_URL'] ?>/users/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>اسم المستخدم</label><input class="input" name="username" required>
  <label>كلمة المرور</label><input class="input" type="password" name="password" required>
  <label>الدور</label>
  <select class="input" name="role" required>
    <option value="support_engineer">مهندس دعم</option>
    <option value="field_engineer">مهندس ميداني</option>
    <option value="manager">مدير</option>
  </select>
  <button class="btn" type="submit">حفظ</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>