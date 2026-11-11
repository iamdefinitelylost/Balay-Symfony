const $=id=>document.getElementById(id);
const money=n=>'₱'+Number(n).toLocaleString('en-PH');
const esc=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const today=new Date(Date.now()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,10);
const stars=n=>'★'.repeat(n)+'☆'.repeat(5-n);
async function load(){
  const r=await fetch('/api/my-bookings');
  if(r.status===401){location='/';return}
  const rows=await r.json();
  const rated=rows.filter(b=>b.host_rating);
  $('summary').textContent=rated.length?`Hosts rate you ★ ${(rated.reduce((a,b)=>a+ +b.host_rating,0)/rated.length).toFixed(1)} from ${rated.length} review${rated.length===1?'':'s'}.`:'';
  $('list').innerHTML=rows.length?`<table><thead><tr><th>Ref</th><th>Stay</th><th>Dates</th><th>Guests</th><th>Total</th><th>Status</th><th>Host feedback</th><th></th></tr></thead><tbody>${rows.map(b=>`<tr><td>BAL-${String(b.id).padStart(5,'0')}</td><td>${esc(b.title)}<br><span class="meta">${esc(b.city)}</span></td><td>${b.check_in} → ${b.check_out}</td><td>${b.guests}</td><td>${money(b.total)}</td><td><span class="pill ${b.status}">${b.status}</span></td><td>${b.host_rating?`<span class="stars" aria-label="${b.host_rating} out of 5">${stars(+b.host_rating)}</span>${b.host_comment?`<br><span class="meta">${esc(b.host_comment)}</span>`:''}`:'<span class="meta">—</span>'}</td><td>${b.status==='confirmed'&&b.check_out<=today?`<a class="sm btn ghost" href="/#stay-${b.listing_id}">Review stay</a>`:''}${b.status==='confirmed'&&b.check_in>=today?`<button class="sm" data-c="${b.id}">Cancel</button>`:''}</td></tr>`).join('')}</tbody></table>`:'<div class="empty">No bookings yet. <a href="/">Find a stay</a>.</div>';
}
document.addEventListener('click',async e=>{
  const c=e.target.closest('[data-c]');
  if(c&&confirm('Cancel this booking?')){const r=await fetch('/api/my-bookings',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({cancel:+c.dataset.c})});if(!r.ok)alert((await r.json()).error);load()}
});
$('out').onclick=async()=>{await fetch('/logout',{method:'POST'});location='/'};
load();
