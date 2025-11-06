<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>إسناد التذكرة #<?= $ticket['id'] ?></h1>
<form method="post" action="<?= $cfg['APP_URL'] ?>/tickets/assign">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
  <label>اختر مهندس ميداني</label>
  <select class="input" name="user_id" required>
    <?php foreach($engineers as $e): ?>
      <option value="<?= $e['id'] ?>"><?= Utils::h($e['username']) ?></option>
    <?php endforeach; ?>
  </select>
  <div style="display:flex; gap:8px; margin-top:10px">
    <button class="btn" type="submit">إسناد</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/tickets/show?id=<?= $ticket['id'] ?>">رجوع</a>
  </div>
</form>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>