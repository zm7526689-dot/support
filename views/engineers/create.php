<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>إضافة مهندس</h1>

<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>

<form method="post" action="<?= $cfg['APP_URL'] ?>/engineers/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <label>الاسم</label><input class="input" name="name" required>
  <label>الهاتف</label><input class="input" name="phone" required>
  <label>التخصص</label><input class="input" name="specialty">
  <button class="btn" type="submit">حفظ</button>
</form>

<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
