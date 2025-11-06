<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>التذاكر</h1>
<?php if(Auth::user()['role']==='support_engineer'): ?>
  <a class="btn" href="<?= $cfg['APP_URL'] ?>/tickets/create">فتح تذكرة جديدة</a>
<?php endif; ?>
<table class="table" style="margin-top:10px">
  <thead><tr><th>#</th><th>العنوان</th><th>الحالة</th><th>مكلف إلى</th><th>فتح</th></tr></thead>
  <tbody>
  <?php foreach($tickets as $t): ?>
    <tr>
      <td><?= $t['id'] ?></td>
      <td><?= Utils::h($t['title']) ?></td>
      <td><span class="badge <?= $t['status'] ?>"><?= $t['status'] ?></span></td>
      <td><?= $t['assigned_to_user_id'] ?: '-' ?></td>
      <td><a href="<?= $cfg['APP_URL'] ?>/tickets/show?id=<?= $t['id'] ?>">عرض</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>