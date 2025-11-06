<?php ob_start(); ?>
<h1>لوحة التحكم</h1>

<div class="grid kpi">
  <div class="card kpi"><div class="label">جديدة</div><div class="value"><?= $stats['new'] ?? 0 ?></div></div>
  <div class="card kpi"><div class="label">مسندة</div><div class="value"><?= $stats['assigned'] ?? 0 ?></div></div>
  <div class="card kpi"><div class="label">هاتفياً</div><div class="value"><?= $stats['solved_phone'] ?? 0 ?></div></div>
  <div class="card kpi"><div class="label">ميدانياً</div><div class="value"><?= $stats['solved_field'] ?? 0 ?></div></div>
</div>

<div class="grid">
  <div class="card">
    <h3>توزيع الحالات</h3>
    <canvas id="statusChart" height="120"></canvas>
  </div>
  <div class="card">
    <h3>الأعطال الأكثر تكرارًا</h3>
    <canvas id="faultsChart" height="120"></canvas>
  </div>
  <div class="card">
    <h3>أداء المهندسين</h3>
    <canvas id="engineersChart" height="120"></canvas>
  </div>
</div>

<div class="card" style="margin-top:12px">
  <h3>تذاكر متأخرة (SLA)</h3>
  <?php if(empty($sla)): ?>
    <p class="muted">لا توجد تذاكر متأخرة</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>#</th><th>العنوان</th><th>الحالة</th><th>أُحدثت</th></tr></thead>
    <tbody>
      <?php foreach($sla as $t): ?>
      <tr>
        <td><?= $t['id'] ?></td>
        <td><?= Utils::h($t['title']) ?></td>
        <td><span class="badge <?= $t['status'] ?>"><?= $t['status'] ?></span></td>
        <td><?= $t['updated_at'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
new Chart(document.getElementById('statusChart'), {
  type:'pie',
  data:{ labels:['جديدة','مسندة','هاتفياً','ميدانياً'],
    datasets:[{ data:[<?= $stats['new'] ?? 0 ?>,<?= $stats['assigned'] ?? 0 ?>,<?= $stats['solved_phone'] ?? 0 ?>,<?= $stats['solved_field'] ?? 0 ?>],
      backgroundColor:['#f44336','#3b82f6','#a3e635','#22c55e'] }] }
});
new Chart(document.getElementById('faultsChart'), {
  type:'bar',
  data:{ labels:<?= json_encode(array_column($freq,'keypart')) ?>,
    datasets:[{ label:'التكرار', data:<?= json_encode(array_column($freq,'cnt')) ?>, backgroundColor:'#3b82f6' }] },
  options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false }
});
new Chart(document.getElementById('engineersChart'), {
  type:'bar',
  data:{ labels:<?= json_encode(array_column($engineers,'username')) ?>,
    datasets:[{ label:'مغلقة', data:<?= json_encode(array_column($engineers,'closed')) ?>, backgroundColor:'#22c55e' }] },
  options:{ responsive:true, maintainAspectRatio:false }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/layout.php'; ?>