// Simple toast notifications
export function toast(text, type='info'){
  const wrap = document.querySelector('.toast') || (()=>{ const d=document.createElement('div'); d.className='toast'; document.body.appendChild(d); return d; })();
  const m = document.createElement('div'); m.className='msg'; m.textContent = text;
  wrap.appendChild(m); setTimeout(()=> m.remove(), 4000);
}

// Offline report draft
export function saveDraft(form){
  const idEl = form.querySelector('input[name="ticket_id"]');
  const key = 'reportDraft:' + (idEl ? idEl.value : 'unknown');
  const data = new FormData(form);
  const obj = {}; for(const [k,v] of data.entries()) obj[k]=v;
  localStorage.setItem(key, JSON.stringify(obj));
  toast('تم حفظ التقرير محليًا');
}
export function loadDraft(ticketId, form){
  const key = 'reportDraft:' + ticketId; const raw = localStorage.getItem(key);
  if(!raw) return; const obj = JSON.parse(raw);
  ['diagnosis','action_taken'].forEach(k=>{ const el=form.querySelector(`[name="${k}"]`); if(el) el.value = obj[k] || ''; });
  toast('تم تحميل مسودة محلية للتقرير');
}
export async function trySync(url, form){
  try {
    const r = await fetch(url, { method:'POST', body: new FormData(form) });
    if(r.ok){ toast('تمت مزامنة التقرير بنجاح','success'); const id=form.querySelector('input[name="ticket_id"]').value; localStorage.removeItem('reportDraft:' + id); return true; }
  } catch(e){ toast('فشل الاتصال، تم الحفاظ على المسودة محليًا','warning'); }
  return false;
}