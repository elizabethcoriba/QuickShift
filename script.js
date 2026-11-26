'use strict';
const $=s=>document.querySelector(s),E=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let tok=localStorage.getItem('qs2_tok')||'',users=[],jobs=[],apps=[],msgs=[],me=null,adminData=null,view='home',th=null,up=false,fl='',mine=true,sig='';const lockUntil={};
const DN=['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],CATS=['Retail','Food','Service','Delivery','Events','Office'],ST=['Pending','Reviewed','Hired','Rejected'];
const LOCS=['Davao','Carmen','Cabadbaran','La union','Calibunan','Butuan','Magallanes','Kauswagan','talacogon','Luna','Cagayan'];
const T=t=>{const[h,m]=t.split(':');return((h%12)||12)+(m==='00'?'':':'+m)+(h<12?'am':'pm')};
const user=e=>users.find(u=>u.email===e),jobOf=id=>jobs.find(j=>j.id===id),own=()=>me.role==='owner',adm=()=>me.role==='admin';
const myApps=()=>own()?apps.filter(a=>jobOf(a.jobId)?.owner===me.email):apps.filter(a=>a.applicant===me.email);
const unread=()=>msgs.filter(m=>m.to===me.email&&!m.read).length;
let tt;const toast=m=>{const t=$('#toast');t.textContent=m;t.classList.add('on');clearTimeout(tt);tt=setTimeout(()=>t.classList.remove('on'),2600)};

const API='api.php?action=';
const H=()=>({'Content-Type':'application/json',Authorization:'Bearer '+tok});
async function pull(force){if(!tok)return;try{const r=await fetch(API+'data',{headers:{...H(),'X-Poll':'1'}});if(r.status===401){if(me){const e=await r.json().catch(()=>({}));A.logout(0,e.error)}return}const d=await r.json(),g=JSON.stringify(d);if(!force&&g===sig)return;sig=g;({me,users,jobs,apps,msgs,admin:adminData}=d);if(!$('#app').hidden)refresh()}catch{}}
async function op(name,data){try{const r=await fetch(API+'op',{method:'POST',headers:H(),body:JSON.stringify({op:name,...data})}),j=await r.json().catch(()=>({}));if(!r.ok){toast(j.error||'Something went wrong.');if(r.status===401)A.logout(0,j.error);return null}await pull();return j}catch{toast('Can’t reach the server.');return null}}
function refresh(){if(view==='msgs'){markRead();drawNav();renderTL();renderML()}else render()}
function markRead(){if(!th)return;const u=msgs.filter(m=>m.j===th.j&&m.ap===th.ap&&m.to===me.email&&!m.read);if(!u.length)return;u.forEach(m=>m.read=true);fetch(API+'op',{method:'POST',headers:H(),body:JSON.stringify({op:'read',j:th.j,ap:th.ap})})}

const lockLeft=k=>Math.max(0,Math.ceil(((lockUntil[k]||0)-Date.now())/1000));
const mm=s=>Math.floor(s/60)+':'+String(s%60).padStart(2,'0');
const err=m=>{const x=$('#aerr');x.textContent=m;x.dataset.lock=m.startsWith('Too many')?1:''};
function lockUI(){const e=$('#email').value.trim().toLowerCase(),s=lockLeft(e),b=$('#authBtn');b.disabled=s>0;b.textContent=s>0?'Try again in '+mm(s):up?'Create account':'Log in';if(s>0)err('Too many failed attempts. Please wait before trying again.');else if($('#aerr').dataset.lock)err('')}
setInterval(()=>{if(!$('#auth').hidden)lockUI()},1000);$('#email').addEventListener('input',lockUI);

async function api(p,b){try{const r=await fetch(API+encodeURIComponent(p),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)});return[r,await r.json().catch(()=>({}))]}catch{return[{ok:false},{error:'Can’t reach the server. Start Apache and MySQL in XAMPP first.'}]}}
$('#authForm').onsubmit=async e=>{e.preventDefault();err('');const email=$('#email').value.trim().toLowerCase(),pass=$('#pass').value;
 if(lockLeft(email))return lockUI();if(!email||!pass)return err('Enter your username and password.');
 const body={email,pass};
 if(up){const qa=[...document.querySelectorAll('.qa')];Object.assign(body,{first:$('#fn').value,last:$('#ln').value,gmail:$('#gmail').value.trim().toLowerCase(),role:$('#rl').value,phone:$('#phone').value,qa:[...document.querySelectorAll('.qs')].map((s,k)=>({i:+s.value,a:qa[k].value}))})}
 const[r,j]=await api(up?'signup':'login',body);
 if(j.needVerify)return openVerifyModal(j.username||email,j.error);
 if(j.forgot)$('#fgt').hidden=false;
 if(!r.ok){if(j.wait){lockUntil[email]=Date.now()+j.wait*1000;return lockUI()}return err(j.error+(j.left!=null?` ${j.left} ${j.left===1?'try':'tries'} left before lockout.`:''))}
 tok=j.token;localStorage.setItem('qs2_tok',tok);await pull(true);if(me)enter()};

function enter(){$('#fgt').hidden=true;$('#auth').hidden=true;$('#app').hidden=false;$('#authForm').reset();go(adm()?'admin':'home')}
function go(v){view=v;document.querySelectorAll('.view').forEach(s=>s.hidden=s.id!=='v-'+v);$('#q').hidden=v!=='jobs';render()}
function drawNav(){const n=unread(),nav=adm()?[['admin','Admin Security'],['jobs','All Jobs'],['msgs','Messages']]:[['home','Home'],['jobs','Jobs'],...(own()?[['post','Post a job']]:[]),['people',own()?'Applicants':'My applications'],['msgs','Messages']];
 $('#nav').innerHTML=nav.map(([id,l])=>`<button class="${id===view?'on':''}" data-go="${id}"><span class="ic">${{admin:'🛡',home:'⌂',jobs:'▤',post:'＋',people:'♙',msgs:'✉'}[id]}</span><span class="lb">${l}</span>${id==='msgs'&&n?`<i>${n}</i>`:''}</button>`).join('');
 $('#me').innerHTML=`<span class="av">${E(me.name[0].toUpperCase())}</span><b>${E(me.name)}</b><small>${adm()?'Administrator':own()?'Owner':'Part-timer'}</small>`;$('#vt').textContent=nav.find(n=>n[0]===view)[1]}
function render(){if(me){drawNav();R[view]()}}

const wk=j=>`<div class="wk">${'MTWTFSS'.split('').map((c,i)=>`<i class="${j.days.includes(i)?'on':''}" title="${DN[i]}">${c}</i>`).join('')}<span>${T(j.t1)}–${T(j.t2)}</span></div>`;
function ticket(j){const n=apps.filter(a=>a.jobId===j.id),left=j.slots-(j.hired||0),my=n.find(a=>a.applicant===me.email);let act='';
 if(j.owner===me.email)act=`<button class="ghost" data-act="toggle" data-id="${j.id}">${j.open?'Close hiring':'Reopen'}</button><button class="ghost" data-go="people">Applicants (${n.length})</button><button class="ghost" data-act="del" data-id="${j.id}">Delete</button>`;
 else if(!own()&&!adm())act=(my?`<span class="tag">Applied · ${my.status}</span>`:j.open&&left>0?`<button class="btn" data-act="apply" data-id="${j.id}">Apply</button>`:'<span class="tag">Filled</span>')+`<button class="ghost" data-act="open" data-j="${j.id}" data-ap="${E(me.email)}">Message owner</button>`;
 return`<article class="tk ${j.open&&left>0?'':'off'}"><div class="bd"><span class="cat">${E(j.cat)}</span><h3>${E(j.title)}</h3><p class="sub">${E(j.ownerName)} · ${E(j.loc)}</p>${wk(j)}<p class="ds">${E(j.desc||'No description.')}</p><div class="acts">${act}</div></div><div class="stub"><b>₱${(+j.pay).toLocaleString()}</b><span>per ${E(j.unit)}</span><em>${j.open?Math.max(left,0)+' of '+j.slots+' open':'Closed'}</em></div></article>`}
function row(a){const o=own();return`<div class="row"><div><b>${E(o?a.name:a.title)}</b><small>${o?'for '+E(a.title)+' · '+E(a.phone||a.applicant):E(jobOf(a.jobId)?.ownerName||'')}</small>${a.note?`<q>${E(a.note)}</q>`:''}</div><div class="rt"><span class="pill st-${a.status}">${a.status}</span>${o?['Reviewed','Hired','Rejected'].map(s=>`<button class="ghost seg ${a.status===s?'on':''}" data-act="st" data-id="${a.id}" data-s="${s}">${s}</button>`).join(''):a.status==='Pending'||a.status==='Reviewed'?`<button class="ghost" data-act="wd" data-id="${a.id}">Withdraw</button>`:''}<button class="ghost" data-act="open" data-j="${a.jobId}" data-ap="${E(a.applicant)}">Message</button></div></div>`}

const R={};
R.admin=()=>{
 if(!adminData)return;
 const uRows=adminData.allUsers.map(u=>`<div class="row"><div><b>${E(u.name)} (@${E(u.email)})</b><small>${E(u.role.toUpperCase())} · ${E(u.gmail||'')} · ${u.is_verified==1?'Verified':'Unverified'}</small></div></div>`).join('');
 const lockRows=adminData.locks.map(l=>`<div class="row"><div><b>User Lock: ${E(l.username)}</b><small>Total fails: ${l.t} · Stage: ${l.s}</small></div><button class="ghost" data-act="unlockUser" data-target="${E(l.username)}">Clear Lock</button></div>`).join('')||'<p class="mut">No locked accounts.</p>';
 const ipRows=adminData.ipBlocks.map(b=>`<div class="row"><div><b>Blocked IP: ${E(b.ip)}</b></div><button class="ghost" data-act="unblockIp" data-target="${E(b.ip)}">Unblock IP</button></div>`).join('')||'<p class="mut">No blocked IPs.</p>';
 const logRows=adminData.logs.map(g=>`<div class="row"><div><b>${E(g.action)}</b><small>${E(g.username)} · ${E(g.details)} (${E(g.ip)})</small></div><small>${new Date(+g.ts).toLocaleString()}</small></div>`).join('');
 $('#v-admin').innerHTML=`
   <div class="timecard">
     <div><b>${adminData.allUsers.length}</b><span>Total Users</span></div>
     <div><b>${adminData.locks.length}</b><span>Security Flags</span></div>
     <div><b>${adminData.ipBlocks.length}</b><span>Blocked IPs</span></div>
     <div><b>${adminData.logs.length}</b><span>Activity Logs</span></div>
   </div>
   <div class="panel" style="margin-bottom:18px"><h4>Security Flags (Account Locks & IP Blocks)</h4>${lockRows}${ipRows}</div>
   <div class="panel" style="margin-bottom:18px"><h4>Registered Accounts (${adminData.allUsers.length})</h4>${uRows}</div>
   <div class="panel"><h4>Recent Activity Logs</h4>${logRows}</div>`;
};
R.home=()=>{const a=myApps(),o=own(),cnt=s=>a.filter(x=>x.status===s).length,h=new Date().getHours(),g=h<11?'Good Morning':h<13?'Good Afternoon':h<18?'Good Afternoon':'Good Evening';
 const open=jobs.filter(j=>j.open&&(!o||j.owner===me.email)),lc={};open.forEach(j=>lc[j.loc]=(lc[j.loc]||0)+1);const le=Object.entries(lc).sort((x,y)=>y[1]-x[1]).slice(0,5),mx=le[0]?.[1]||1;
 const st=[[o?'Active listings':'Open jobs',open.length],[o?'Applicants':'My applications',a.length],['Unread messages',unread()],[o?'Hired':'Matches',cnt('Hired')]];
 const list=o?a.filter(x=>x.status==='Pending').slice(0,4).map(row).join('')||'<p class="empty">No one is waiting on you. New applicants show up here.</p>'
  :jobs.filter(j=>j.open&&!apps.some(x=>x.jobId===j.id&&x.applicant===me.email)).slice(0,2).map(ticket).join('')||'<p class="empty">You’ve applied to everything open. Check back soon.</p>';
 $('#v-home').innerHTML=`<div class="hello"><div><h3>${g}, ${E(me.name.split(' ')[0])}.</h3><small>${new Date().toLocaleDateString(undefined,{weekday:'long',month:'long',day:'numeric'})}</small></div>${o?'<button class="btn" data-go="post">+ Post a job</button>':'<button class="btn" data-go="jobs">Browse jobs</button>'}</div>
 <div class="timecard">${st.map(([l,n])=>`<div><b>${n}</b><span>${l}</span></div>`).join('')}</div>
 <div class="cols"><div class="panel"><h4>Application pipeline</h4><div class="pipe">${ST.map(s=>`<i class="st-${s}" style="flex:${cnt(s)}" title="${s}"></i>`).join('')}</div><ul class="legend">${ST.map(s=>`<li><span class="dot st-${s}"></span>${s} <b>${cnt(s)}</b></li>`).join('')}</ul></div>
 <div class="panel"><h4>Open jobs by area</h4>${le.map(([p,n])=>`<div class="loc"><span>${E(p)}</span><i style="width:${n/mx*100}%"></i><b>${n}</b></div>`).join('')||'<p class="mut">No open jobs yet.</p>'}</div></div>
 <h4 style="margin:0 0 12px;font-size:19px">${o?'Waiting for your reply':'Fresh jobs for you'}</h4>${list}`};
R.jobs=()=>{const q=$('#q').value.toLowerCase();let l=jobs.filter(j=>(!own()||!mine||j.owner===me.email)&&(!fl||j.loc===fl)&&(j.title+j.loc+j.desc+j.cat+j.ownerName).toLowerCase().includes(q));
 if(!own()&&!adm())l=l.filter(j=>j.open||apps.some(a=>a.jobId===j.id&&a.applicant===me.email));
 $('#v-jobs').innerHTML=`<div class="bar"><select id="fl" aria-label="Area"><option value="">All areas</option>${[...new Set(jobs.map(j=>j.loc))].sort().map(x=>`<option ${x===fl?'selected':''}>${E(x)}</option>`).join('')}</select>${own()?`<label class="chk"><input type="checkbox" id="mine" ${mine?'checked':''}> My listings only</label>`:''}<span class="cnt">${l.length} job${l.length==1?'':'s'}</span></div>`+(l.map(ticket).join('')||`<p class="empty">${own()?'Nothing here yet. Post a job and applicants will appear under Applicants.':'No jobs match. Try another area or search.'}</p>`)};
R.post=()=>{};
R.people=()=>{$('#v-people').innerHTML=myApps().sort((a,b)=>b.ts-a.ts).map(row).join('')||`<p class="empty">${own()?'No applicants yet. They’ll show up here as soon as someone applies.':'You haven’t applied yet. Open Jobs and tap Apply.'}</p>`};
R.msgs=()=>{renderTL();renderTV()};
const other=(j,ap)=>own()?ap:jobOf(j)?.owner;
function renderTL(){const m=new Map();msgs.filter(x=>x.from===me.email||x.to===me.email).sort((a,b)=>a.ts-b.ts).forEach(x=>m.set(x.j+'|'+x.ap,x));
 $('#tl').innerHTML=[...m.values()].reverse().map(x=>{const o=x.from===me.email?x.to:x.from,n=msgs.filter(y=>y.j===x.j&&y.ap===x.ap&&y.to===me.email&&!y.read).length;
 return`<button class="${th&&th.j===x.j&&th.ap===x.ap?'on':''}" data-act="open" data-j="${x.j}" data-ap="${E(x.ap)}"><b>${E(user(o)?.name||o)}</b><small>${E(jobOf(x.j)?.title||'Deleted job')}</small><span>${E(x.text)}</span>${n?`<i>${n}</i>`:''}</button>`}).join('')||'<p class="empty">No conversations yet.</p>'}
function renderTV(){const v=$('#tv');if(!th){v.innerHTML='<p class="empty" style="margin:16px">Pick a conversation, or tap Message on a job or applicant.</p>';return}
 const o=other(th.j,th.ap);v.innerHTML=`<header><b>${E(user(o)?.name||o||'Unknown')}</b><small>${E(jobOf(th.j)?.title||'')}</small></header><div id="ml"></div><form id="sf"><input id="mi" placeholder="Write a message…" autocomplete="off" aria-label="Message"><button class="btn">Send</button></form>`;renderML()}
function renderML(){const l=$('#ml');if(!l||!th)return;const x=msgs.filter(m=>m.j===th.j&&m.ap===th.ap);
 l.innerHTML=x.map(m=>m.sys?`<p class="sys">${E(m.text)}</p>`:`<div class="bub ${m.from===me.email?'me':'ot'}">${E(m.text)}<small>${new Date(m.ts).toLocaleTimeString([],{hour:'numeric',minute:'2-digit'})}</small></div>`).join('')||'<p class="sys">Say hello 👋</p>';l.scrollTop=l.scrollHeight}

let verifyUser='';
function openVerifyModal(u,msg){verifyUser=u;$('#ve').textContent=msg||'';$('#vo').value='';$('#vfy').showModal()}

const A={
 mode(){up=!up;document.querySelectorAll('.su').forEach(x=>x.hidden=!up);$('#at').textContent=up?'Create your account':'Welcome back';$('#swap').textContent=up?'Already have an account? Log in':'No account yet? Sign up';$('#pass').autocomplete=up?'new-password':'current-password';err('');lockUI()},
 logout(d,msg){fetch(API+'logout',{method:'POST',headers:H()}).catch(()=>{});localStorage.removeItem('qs2_tok');tok='';sig='';me=null;adminData=null;th=null;$('#app').hidden=true;$('#auth').hidden=false;lockUI();if(msg)toast(msg)},
 forgot(){FP={step:0,u:'',rt:'',qs:[]};fpDraw();$('#fp').showModal()},
 fcx(){$('#fp').close()},
 vcx(){$('#vfy').close()},
 apply(d){$('#dlg').dataset.id=d.id;$('#dn').value='';$('#dlg').showModal()},
 cx(){$('#dlg').close()},
 peek(){const p=$('#pass'),s=p.type==='password';p.type=s?'text':'password';$('#peek').textContent=s?'Hide':'Show'},
 async toggle(d){if(await op('toggle',{id:d.id}))toast('Updated.')},
 async del(d){if(confirm('Delete this job and its applications?')&&await op('del',{id:d.id}))toast('Job deleted.')},
 async st(d){if(await op('st',{id:d.id,s:d.s}))toast('Marked as '+d.s+'.')},
 async wd(d){if(confirm('Withdraw this application?'))await op('wd',{id:d.id})},
 async unlockUser(d){if(await op('unlock_user',{target:d.target}))toast('User lock cleared.')},
 async unblockIp(d){if(await op('unblock_ip',{target:d.target}))toast('IP unblocked.')},
 open(d){th={j:d.j,ap:d.ap};markRead();go('msgs')}
};
document.addEventListener('click',e=>{const t=e.target.closest('[data-act],[data-go]');if(!t)return;t.dataset.go?go(t.dataset.go):A[t.dataset.act]?.(t.dataset)});
document.addEventListener('change',e=>{if(e.target.id==='fl'){fl=e.target.value;render()}if(e.target.id==='mine'){mine=e.target.checked;render()}});
$('#q').oninput=render;
$('#df').onsubmit=async e=>{e.preventDefault();const r=await op('apply',{id:$('#dlg').dataset.id,note:$('#dn').value.trim()});$('#dlg').close();if(r)toast('Application sent. You can message the owner anytime.')};
document.addEventListener('submit',async e=>{if(e.target.id!=='sf')return;e.preventDefault();const i=$('#mi'),text=i.value.trim();if(!text||!th)return;i.value='';await op('send',{j:th.j,ap:th.ap,text});$('#mi')?.focus()});
$('#pf').onsubmit=async e=>{e.preventDefault();const days=[...document.querySelectorAll('[name=d]:checked')].map(x=>+x.value);if(!days.length)return toast('Pick at least one working day.');
 const r=await op('post',{title:$('#jt').value,cat:$('#jc').value,loc:$('#jl').value,pay:$('#jp').value,unit:$('#ju').value,days,t1:$('#t1').value,t2:$('#t2').value,slots:$('#js').value,desc:$('#jd').value});
 if(r){e.target.reset();mine=true;fl='';toast('Job posted. Everyone can see it now.');go('jobs')}};

$('#vf').onsubmit=async e=>{e.preventDefault();$('#ve').textContent='';
 const[r,j]=await api('verify_signup',{email:verifyUser,code:$('#vo').value.trim()});
 if(!r.ok)return $('#ve').textContent=j.error;
 $('#vfy').close();tok=j.token;localStorage.setItem('qs2_tok',tok);await pull(true);if(me)enter();toast('Gmail verified! Welcome to QuickShift.')};

let QS=[],FP={};
function sqUI(){$('#sq').innerHTML='<p class="mut" style="margin-bottom:10px">Pick 3 different security questions. You’ll need them if you forget your password.</p>'+[0,1,2].map(k=>`<label>Question ${k+1}<select class="qs">${QS.map((q,i)=>`<option value="${i}" ${i===k?'selected':''}>${E(q)}</option>`).join('')}</select><input class="qa" maxlength="100" placeholder="Your answer" aria-label="Answer ${k+1}"></label>`).join('');
 const sel=[...document.querySelectorAll('.qs')],fix=()=>sel.forEach(s=>[...s.options].forEach(o=>o.disabled=o.value!==s.value&&sel.some(x=>x!==s&&x.value===o.value)));sel.forEach(s=>s.onchange=fix);fix()}
function fpDraw(){const s=FP.step;$('#fe').textContent='';$('#fgo').textContent=['Send OTP','Verify OTP','Reset password'][s];
 $('#fb').innerHTML=s===0?`<label>Gmail or Username<input id="fu" maxlength="120" value="${E($('#email').value)}" autocomplete="username"></label>`
 :s===1?'<p class="mut" style="margin-bottom:12px">Check your Gmail inbox for the 6-digit OTP (valid for 5 minutes).</p><label>6-digit OTP<input id="fo" inputmode="numeric" maxlength="6" autocomplete="one-time-code"></label>'
 :FP.qs.map(q=>`<label>${E(q)}<input class="fa" maxlength="100"></label>`).join('')+'<label>New password (at least 8 characters)<input id="fpw" type="password" minlength="8" maxlength="100" autocomplete="new-password"></label>'}
$('#ff').onsubmit=async e=>{e.preventDefault();const s=FP.step,fe=m=>$('#fe').textContent=m;
 if(s===0){const u=$('#fu').value.trim().toLowerCase();if(!u)return fe('Enter your Gmail or username.');const[r,j]=await api('forgot',{email:u});if(!r.ok)return fe(j.error);Object.assign(FP,{u:j.resolvedUser||u,step:1});fpDraw()}
 else if(s===1){const[r,j]=await api('otp',{email:FP.u,code:$('#fo').value});if(!r.ok)return fe(j.error);Object.assign(FP,{rt:j.rt,qs:j.questions,step:2});fpDraw()}
 else{const[r,j]=await api('reset',{email:FP.u,rt:FP.rt,answers:[...document.querySelectorAll('.fa')].map(x=>x.value),pass:$('#fpw').value});if(!r.ok)return fe(j.error);$('#fp').close();$('#email').value=FP.u;$('#pass').value='';toast('Password changed. You can log in now.');lockUI()}};

let act=Date.now(),pinged=0;['click','keydown','touchstart'].forEach(ev=>addEventListener(ev,()=>act=Date.now(),{passive:true}));
setInterval(()=>{if(!me)return;if(Date.now()-act>15*60000)return A.logout(0,'You were logged out after 15 minutes of inactivity.');if(act>pinged&&Date.now()-pinged>60000){pinged=Date.now();fetch(API+'ping',{method:'POST',headers:H()}).catch(()=>{})}},15000);

(async()=>{
 $('#days').innerHTML=DN.map((d,i)=>`<label><input type="checkbox" name="d" value="${i}"><span>${d}</span></label>`).join('');
 $('#jc').innerHTML=CATS.map(c=>`<option>${c}</option>`).join('');$('#locs').innerHTML=LOCS.map(l=>`<option value="${l}">`).join('');
 QS=await fetch(API+'questions').then(r=>r.json()).catch(()=>[]);sqUI();await pull(true);me?enter():lockUI();setInterval(()=>{if(me)pull()},3000)})();