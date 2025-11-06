<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>إضافة عميل</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/customers/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>الاسم</label><input class="input" name="name" required>
  <label>الهاتف</label><input class="input" name="phone" required>
  <label>المنطقة</label><input class="input" name="area">
  <button class="btn" type="submit">حفظ</button>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>