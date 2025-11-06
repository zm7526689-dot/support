<?php ob_start(); $cfg = require __DIR__ . '/../config/config.php'; ?>
<h1>بحث في قاعدة المعرفة</h1>
<?php if(!empty($res)): ?>
<table class="table">
  <thead><tr><th>التذكرة</th><th>العنوان</th><th>ملخص</th></tr></thead>
  <tbody>
  <?php foreach($res as $row): ?>
    <tr>
      <td>#<?= $row['ticket_id'] ?></td>
      <td><?= Utils::h($row['title']) ?></td>
      <td><?= Utils::h(mb_strimwidth(($row['diagnosis'] ?? '').' / '.($row['action_taken'] ?? ''), 0, 80, '...')) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php else: ?>
<p class="muted">اكتب كلمات البحث في الشريط أعلى الصفحة.</p>
<?php endif; ?>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>