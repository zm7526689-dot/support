<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تعديل عميل</h1>
<form method="post" action="<?= $cfg['APP_URL'] ?>/customers/edit?id=<?= $customer['id'] ?>">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>الاسم</label><input class="input" name="name" value="<?= Utils::h($customer['name']) ?>" required>
  <label>الهاتف</label><input class="input" name="phone" value="<?= Utils::h($customer['phone']) ?>" required>
  <label>المنطقة</label><input class="input" name="area" value="<?= Utils::h($customer['area']) ?>">
  <div style="display:flex; gap:8px">
    <button class="btn" type="submit">تحديث</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/customers">رجوع</a>
  </div>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>