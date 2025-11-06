<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>العملاء</h1>

<div class="card">
  <a class="btn" href="<?= $cfg['APP_URL'] ?>/customers/create">إضافة عميل</a>
  <form method="post" action="<?= $cfg['APP_URL'] ?>/customers/import" enctype="multipart/form-data" style="margin-top:10px">
    <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
    <input class="input" type="file" name="file" accept=".csv" required>
    <button class="btn outline" type="submit">استيراد CSV</button>
    <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/customers/export">تصدير CSV</a>
  </form>
</div>

<table class="table" style="margin-top:10px">
  <thead>
    <tr><th>#</th><th>الاسم</th><th>الهاتف</th><th>المنطقة</th><th>إدارة</th></tr>
  </thead>
  <tbody>
  <?php $i=1; foreach($customers as $c): ?>
    <tr>
      <td><?= $i++ ?></td>
      <td><?= Utils::h($c['name']) ?></td>
      <td><?= Utils::h($c['phone']) ?></td>
      <td><?= Utils::h($c['area'] ?? '-') ?></td>
      <td>
        <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/customers/edit?id=<?= $c['id'] ?>">تعديل</a>
        <form style="display:inline" method="post" action="<?= $cfg['APP_URL'] ?>/customers/delete" onsubmit="return confirm('تأكيد الحذف؟')">
          <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
          <input type="hidden" name="id" value="<?= $c['id'] ?>">
          <button class="btn danger" type="submit">حذف</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>
