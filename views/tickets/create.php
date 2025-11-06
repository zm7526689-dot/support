<?php ob_start(); $cfg = require __DIR__ . '/../../config/config.php'; ?>
<h1>فتح تذكرة جديدة</h1>
<?php if(!empty($error)): ?><p class="error"><?= Utils::h($error) ?></p><?php endif; ?>
<form method="post" action="<?= $cfg['APP_URL'] ?>/tickets/create">
  <input type="hidden" name="csrf" value="<?= Utils::csrfToken() ?>">
  <fieldset class="card">
    <legend>العميل</legend>
    <label>الهاتف</label><input class="input" name="phone" required>
    <label>الاسم (إن لم يوجد)</label><input class="input" name="name">
    <label>المنطقة</label><input class="input" name="area">
  </fieldset>
  <fieldset class="card" style="margin-top:10px">
    <legend>التذكرة</legend>
    <label>العنوان</label><input class="input" name="title" required oninput="suggest(this.value)">
    <div id="sugg" class="suggestions"></div>
    <label>وصف المشكلة</label><textarea class="input" name="problem_description" required></textarea>
  </fieldset>
  <button class="btn" type="submit">حفظ</button>
</form>
<script>
async function suggest(q){
  const box = document.getElementById('sugg'); if(!q || q.length<2){ box.innerHTML=''; return; }
  try{
    const r = await fetch('<?= $cfg['APP_URL'] ?>/search?q=' + encodeURIComponent(q));
    const text = await r.text();
    box.innerHTML = text.includes('<table') ? '<p>اقتراحات: راجع صفحة البحث؛ تم العثور على تطابقات.</p>' : '';
  }catch(e){}
}
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../layout.php'; ?>