<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>المستخدمون</h1>
<a class="btn" href="<?= $cfg['APP_URL'] ?>/users/create">إضافة مستخدم</a>
<table class="table" style="margin-top:10px">
  <thead><tr><th>#</th><th>اسم المستخدم</th><th>الدور</th><th>إدارة</th></tr></thead>
  <tbody>
    <?php foreach($users as $u): ?>
      <tr>
        <td><?= $u['id'] ?></td>
        <td><?= Utils::h($u['username']) ?></td>
        <td><?= Utils::h($u['role']) ?></td>
        <td>
          <a class="btn outline" href="<?= $cfg['APP_URL'] ?>/users/edit?id=<?= $u['id'] ?>">تعديل</a>
          <form style="display:inline" method="post" action="<?= $cfg['APP_URL'] ?>/users/delete" onsubmit="return confirm('تأكيد حذف المستخدم؟')">
            <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button class="btn danger" type="submit">حذف</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>