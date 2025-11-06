<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تعديل مهندس</h1>

<form method="post" action="<?= $cfg['APP_URL'] ?>/engineers/edit?id=<?= $engineer['id'] ?>">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <input type="hidden" name="id" value="<?= $engineer['id'] ?>">
  <label>الاسم</label><input class="input" name="name" value="<?= Utils::h($engineer['name']) ?>" required>
  <label>الهاتف</label><input class="input" name="phone" value="<?= Utils::h($engineer['phone']) ?>" required>
  <label>التخصص</label><input class="input" name="specialty" value="<?= Utils::h($engineer['specialty']) ?>">
  <div style="display:flex; gap:8px">
    <button class="btn" type="submit">تحديث</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/engineers">رجوع</a>
  </div>
</form>

<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
