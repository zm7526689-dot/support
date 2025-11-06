<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>تفاصيل التذكرة #<?= $ticket['id'] ?></h1>
<div class="card">
<p><strong>العنوان:</strong> <?= Utils::h($ticket['title']) ?></p>
<p><strong>الوصف:</strong> <?= Utils::h($ticket['problem_description']) ?></p>
<p><strong>الحالة:</strong> <span class="badge <?= $ticket['status'] ?>"><?= $ticket['status'] ?></span></p>
</div>

<div class="card" style="margin-top:10px">
<h3>العميل</h3>
<p><strong>الاسم:</strong> <?= Utils::h($customer['name']) ?> — <strong>الهاتف:</strong> <?= Utils::h($customer['phone']) ?> — <strong>المنطقة:</strong> <?= Utils::h($customer['area'] ?? '-') ?></p>
</div>

<div class="card" style="margin-top:10px">
<h3>التقارير</h3>
<ul>
<?php foreach($reports as $r): ?>
  <li>
    <strong>التاريخ:</strong> <?= $r['created_at'] ?> —
    <strong>التشخيص:</strong> <?= Utils::h($r['diagnosis']) ?> —
    <strong>الإجراء:</strong> <?= Utils::h($r['action_taken']) ?>
  </li>
<?php endforeach; ?>
</ul>
</div>

<?php if(in_array(Auth::user()['role'], array('field_engineer','support_engineer'))): ?>
<div class="card" style="margin-top:10px">
<h3>إضافة تقرير</h3>
<form id="reportForm" method="post" action="<?= $cfg['APP_URL'] ?>/reports/create" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
  <label>التشخيص</label><textarea class="input" name="diagnosis" required></textarea>
  <label>الإجراء المتخذ</label><textarea class="input" name="action_taken" required></textarea>
  <label>مرفقات</label><input class="input" type="file" name="attachments[]" multiple>
  <div class="grid" style="grid-template-columns:repeat(3,1fr)">
    <button class="btn" type="submit">حفظ التقرير</button>
    <button class="btn outline" type="button" onclick="saveDraft(document.getElementById('reportForm'))">حفظ محليًا</button>
    <button class="btn outline" type="button" onclick="trySync('<?= $cfg['APP_URL'] ?>/reports/create', document.getElementById('reportForm'))">مزامنة الآن</button>
  </div>
</form>
</div>
<?php endif; ?>

<?php if(Auth::user()['role']==='support_engineer' && !empty($reports)): ?>
<div class="card" style="margin-top:10px">
<form method="post" action="<?= $cfg['APP_URL'] ?>/knowledge/promote">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
  <input type="hidden" name="report_id" value="<?= (int)$reports[0]['id'] ?>">
  <button class="btn outline" type="submit">تحويل لأرشيف المعرفة</button>
</form>
</div>
<?php endif; ?>

<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>