/* Mini HR Vanilla - app.js | Phase 0 */
const getCsrfToken=()=>document.querySelector('meta[name="csrf-token"]')?.content??'';
async function apiFetch(url,method='GET',body=null){
  const o={method,headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':getCsrfToken()}};
  if(body!==null){if(body instanceof FormData)o.body=body;else{o.headers['Content-Type']='application/json';o.body=JSON.stringify(body);}}
  try{const r=await fetch(url,o);const t=await r.text();let d;try{d=JSON.parse(t);}catch{d={raw:t};}if(!r.ok)throw{status:r.status,data:d};return d;}
  catch(e){if(e.status)throw e;throw{status:0,data:{error:'Errore di connessione'}};}
}
function setLoading(b,l){if(!b)return;if(l){b._orig=b.innerHTML;b.innerHTML='<span class="spinner"></span>';b.disabled=true;}else{b.innerHTML=b._orig??b.innerHTML;b.disabled=false;}}
let _tc;function _gtc(){if(!_tc){_tc=document.createElement('div');_tc.id='toast-container';document.body.appendChild(_tc);}return _tc;}
function showToast(m,t='info',d=3500){const ic={success:'✓',error:'✕',warning:'⚠',info:'ℹ'};const el=document.createElement('div');el.className=`toast toast-${t}`;el.innerHTML=`<span>${ic[t]??'ℹ'}</span><span>${escapeHtml(m)}</span>`;_gtc().appendChild(el);setTimeout(()=>{el.style.cssText='opacity:0;transition:opacity .3s';setTimeout(()=>el.remove(),300);},d);}
let _mo;function _gmo(){if(!_mo){_mo=document.createElement('div');_mo.className='modal-overlay hidden';_mo.addEventListener('click',e=>{if(e.target===_mo)closeModal();});document.body.appendChild(_mo);}return _mo;}
function showModal(h,s=''){const o=_gmo();o.innerHTML=`<div class="modal ${s}">${h}</div>`;o.classList.remove('hidden');document.body.style.overflow='hidden';o.querySelectorAll('.modal-close,[data-dismiss="modal"]').forEach(b=>b.addEventListener('click',closeModal));}
function closeModal(){const o=_gmo();o.classList.add('hidden');o.innerHTML='';document.body.style.overflow='';}
function confirmDialog(m,ok,d=false){showModal(`<div class="modal-header"><span class="modal-title">Conferma</span><button class="modal-close">&#x2715;</button></div><div class="modal-body"><p>${escapeHtml(m)}</p></div><div class="modal-footer"><button class="btn btn-secondary" data-dismiss="modal">Annulla</button><button class="btn ${d?'btn-danger':'btn-primary'}" id="cok">Conferma</button></div>`);document.getElementById('cok').addEventListener('click',()=>{closeModal();ok();});}
function initTabs(s='.tabs'){document.querySelectorAll(s+' .tab-btn').forEach(b=>{b.addEventListener('click',()=>{b.closest('.tabs').querySelectorAll('.tab-btn').forEach(x=>x.classList.remove('active'));document.querySelectorAll('.tab-panel').forEach(p=>p.classList.remove('active'));b.classList.add('active');document.getElementById(b.dataset.tab)?.classList.add('active');});});if(!document.querySelector(s+' .tab-btn.active'))document.querySelector(s+' .tab-btn')?.click();}
const formatDate=i=>i?new Date(i).toLocaleDateString('it-IT',{day:'2-digit',month:'2-digit',year:'numeric'}):'—';
const formatDateTime=i=>i?new Date(i).toLocaleString('it-IT',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'}):'—';
const formatTime=t=>t?t.slice(0,5):'—';
const todayISO=()=>new Date().toISOString().slice(0,10);
const currentMonthISO=()=>{const d=new Date();return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;};
function countBusinessDays(f,t,h=[]){const s=new Date(f),e=new Date(t);if(e<s)return 0;let n=0;const c=new Date(s);while(c<=e){const dow=c.getDay(),d=c.toISOString().slice(0,10);if(dow&&dow!==6&&!h.includes(d))n++;c.setDate(c.getDate()+1);}return n;}
function isoWeek(d){const dt=new Date(d);dt.setHours(0,0,0,0);dt.setDate(dt.getDate()+4-(dt.getDay()||7));const ys=new Date(dt.getFullYear(),0,1);return `${dt.getFullYear()}-W${String(Math.ceil(((dt-ys)/86400000+1)/7)).padStart(2,'0')}`;}
function escapeHtml(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function presenceBadge(t){const l={presenza:'Presenza',smartworking:'Smartworking',ferie:'Ferie',permesso:'Permesso',malattia:'Malattia',assente_non_giustificato:'Assente'};return `<span class="badge badge-${t??'gray'}">${l[t]??t}</span>`;}
function statusBadge(s){const l={pending:'In attesa',approved:'Approvata',rejected:'Rifiutata',attivo:'Attivo',cessato:'Cessato',active:'Attivo',closed:'Chiuso',cancelled:'Annullato'};const cls={active:'approved',closed:'gray',cancelled:'rejected'};return `<span class="badge badge-${cls[s]??s}">${l[s]??s}</span>`;}
function docStatusBadge(s){const l={missing:'Mancante',uploaded:'Caricato',received:'Ricevuto'};const cls={missing:'pending',uploaded:'approved',received:'success'};return `<span class="badge badge-${cls[s]??'gray'}">${l[s]??s??'—'}</span>`;}
function initDropZone(zid,iid){const z=document.getElementById(zid),inp=document.getElementById(iid);if(!z||!inp)return;z.addEventListener('click',()=>inp.click());z.addEventListener('dragover',e=>{e.preventDefault();z.classList.add('dragover');});z.addEventListener('dragleave',()=>z.classList.remove('dragover'));z.addEventListener('drop',e=>{e.preventDefault();z.classList.remove('dragover');const f=e.dataTransfer.files[0];if(f){const dt=new DataTransfer();dt.items.add(f);inp.files=dt.files;inp.dispatchEvent(new Event('change'));}});inp.addEventListener('change',()=>{const f=inp.files[0];if(f)z.querySelector('.drop-label').textContent=`\uD83D\uDCCE ${f.name}`;});}
function initTableSearch(iid,bid){const inp=document.getElementById(iid),tb=document.getElementById(bid);if(!inp||!tb)return;inp.addEventListener('input',()=>{const q=inp.value.toLowerCase();tb.querySelectorAll('tr').forEach(r=>{r.style.display=r.textContent.toLowerCase().includes(q)?'':'none';});});}
document.addEventListener('DOMContentLoaded',()=>{initTabs();});

/* ── Phase 1 – Profile & Password Change ─────────────────────────────────── */
async function openProfileModal(){
  showModal(`
    <div class="modal-header">
      <span class="modal-title">&#128100; Profilo</span>
      <button class="modal-close">&#x2715;</button>
    </div>
    <div class="modal-body">
      <div id="_prof-info" style="margin-bottom:.5rem"></div>
      <hr style="margin:1rem 0;border:none;border-top:1px solid var(--border)">
      <p style="font-weight:600;margin:0 0 .75rem;font-size:.9rem">Cambia password</p>
      <div class="form-group">
        <label class="form-label">Password attuale</label>
        <input type="password" class="form-control" id="_old-pwd" autocomplete="current-password">
      </div>
      <div class="form-group">
        <label class="form-label">Nuova password <small style="font-weight:400;color:var(--text-light)">(min. 8 caratteri)</small></label>
        <input type="password" class="form-control" id="_new-pwd" autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label">Conferma nuova password</label>
        <input type="password" class="form-control" id="_new-pwd2" autocomplete="new-password">
      </div>
      <div id="_pwd-err" style="color:var(--danger,#dc3545);font-size:.85rem;margin-top:.25rem;display:none"></div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" data-dismiss="modal">Annulla</button>
      <button class="btn btn-primary" id="_pwd-btn">Salva password</button>
    </div>
  `);

  // Load current user info
  try {
    const u = await apiFetch('../api/users.php');
    document.getElementById('_prof-info').innerHTML =
      `<div class="form-group" style="margin-bottom:.5rem"><label class="form-label">Username</label>`+
      `<input class="form-control" value="${escapeHtml(u.username)}" disabled></div>`+
      `<div class="form-group" style="margin-bottom:0"><label class="form-label">Ruolo</label>`+
      `<input class="form-control" value="${escapeHtml(u.role==='hr'?'HR Consultant':'Dipendente')}" disabled></div>`;
  } catch(_){}

  document.getElementById('_pwd-btn').addEventListener('click', async () => {
    const old = document.getElementById('_old-pwd').value;
    const n1  = document.getElementById('_new-pwd').value;
    const n2  = document.getElementById('_new-pwd2').value;
    const err = document.getElementById('_pwd-err');
    const btn = document.getElementById('_pwd-btn');
    err.style.display = 'none';
    if (!old||!n1||!n2){ err.textContent='Compila tutti i campi'; err.style.display=''; return; }
    if (n1!==n2)        { err.textContent='Le password non coincidono'; err.style.display=''; return; }
    if (n1.length<8)    { err.textContent='Minimo 8 caratteri'; err.style.display=''; return; }
    setLoading(btn,true);
    try {
      await apiFetch('../api/users.php','POST',{action:'change_password',old_password:old,new_password:n1});
      closeModal();
      showToast('Password aggiornata con successo','success');
    } catch(e){
      err.textContent = e.data?.error ?? 'Errore imprevisto';
      err.style.display = '';
    } finally { setLoading(btn,false); }
  });
}
