<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تعديل مستخدم</h1>
<form method="post" action="<?= $cfg['APP_URL'] ?>/users/edit?id=<?= $user['id'] ?>">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>اسم المستخدم</label><input class="input" name="username" value="<?= Utils::h($user['username']) ?>" required>
  <label>الدور</label>
  <select class="input" name="role" required>
    <option value="support_engineer" <?= $user['role']==='support_engineer'?'selected':'' ?>>مهندس دعم</option>
    <option value="field_engineer" <?= $user['role']==='field_engineer'?'selected':'' ?>>مهندس ميداني</option>
    <option value="manager" <?= $user['role']==='manager'?'selected':'' ?>>مدير</option>
  </select>
  <label>كلمة المرور (اتركها فارغة إن لم ترد تغييرها)</label><input class="input" type="password" name="password">
  <div style="display:flex; gap:8px">
    <button class="btn" type="submit">تحديث</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/users">رجوع</a>
  </div>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>