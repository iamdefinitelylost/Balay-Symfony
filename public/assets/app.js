const $ = id => document.getElementById(id);
const money = n => '₱' + Number(n).toLocaleString('en-PH');
const esc = s => String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let ME = window.ME;
const today = new Date().toISOString().slice(0, 10);
let type = 'All', stays = [];
let favs = {};
try { favs = JSON.parse(localStorage.getItem('balay-favs') || '{}'); } catch (e) {}

function pic(l, withFav) {
  if (l.images && l.images.length) {
    const tag = +l.is_new ? '<span class="tag">New</span>' : '';
    const fav = withFav ? `<button class="fav" data-fav="${l.id}" aria-pressed="${!!favs[l.id]}" aria-label="Save to wishlist">${favs[l.id] ? '♥' : '♡'}</button>` : '';
    return `<div class="pic"><img src="/uploads/${esc(l.images[0])}" alt="${esc(l.title)}" loading="lazy">${tag}${fav}</div>`;
  }
  const o = l.id * 7 % 40;
  return `<div class="pic"><svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><defs><linearGradient id="g${l.id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${l.color2}"/><stop offset="1" stop-color="${l.color1}"/></linearGradient></defs><rect width="400" height="300" fill="url(#g${l.id})"/><circle cx="${300 - o}" cy="90" r="34" fill="#fff" opacity=".55"/><path d="M0 210 Q100 ${150 + o} 200 200 T400 190 V300 H0Z" fill="${l.color1}" opacity=".75"/><path d="M0 250 Q120 210 240 245 T400 235 V300 H0Z" fill="#12263A" opacity=".35"/><rect x="${150 + o}" y="165" width="70" height="46" fill="#fff"/><path d="M${142 + o} 167 L${185 + o} 135 L${228 + o} 167Z" fill="#12263A"/></svg>${+l.is_new ? '<span class="tag">New</span>' : ''}${withFav ? `<button class="fav" data-fav="${l.id}" aria-pressed="${!!favs[l.id]}" aria-label="Save to wishlist">${favs[l.id] ? '♥' : '♡'}</button>` : ''}</div>`;
}

async function load() {
  const q = new URLSearchParams({ type, city: $('q-city').value, guests: $('q-guests').value, check_in: $('q-in').value, check_out: $('q-out').value });
  $('grid').innerHTML = '<div class="empty">Loading stays…</div>';
  try {
    const r = await fetch('/api/listings?' + q);
    if (!r.ok) throw new Error();
    stays = await r.json();
  } catch (e) {
    $('grid').innerHTML = '<div class="empty">Could not load stays. Check that MySQL is running and the database is imported.</div>';
    return;
  }
  render();
}

function render() {
  $('count').textContent = stays.length + (stays.length === 1 ? ' stay' : ' stays');
  $('grid').innerHTML = stays.length ? stays.map(l => `<article class="card" data-open="${l.id}" tabindex="0" role="button" aria-label="View ${esc(l.title)}">${pic(l, true)}<h3>${esc(l.title)}</h3><div class="meta">${esc(l.city)} · up to ${l.max_guests} guests · ★ ${l.rating} (${l.reviews})</div><div><b>${money(l.price)}</b> night</div></article>`).join('') : '<div class="empty">No stays fit those filters. Try other dates, another place, or fewer guests.</div>';
}

function gallery(l) {
  if (!(l.images && l.images.length)) return pic(l, false);
  const thumbs = l.images.length > 1 ? `<div class="thumbs">${l.images.map((f, i) => `<button type="button" data-th="${esc(f)}" aria-label="Show photo ${i + 1}"><img src="/uploads/${esc(f)}" alt=""></button>`).join('')}</div>` : '';
  return `<div class="gal"><div class="pic"><img id="g-main" src="/uploads/${esc(l.images[0])}" alt="${esc(l.title)}"></div>${thumbs}</div>`;
}

function nights(a, b) { return a && b ? Math.round((new Date(b) - new Date(a)) / 864e5) : 0; }

function openStay(id) {
  const l = stays.find(x => x.id == id), d = $('dlg');
  let opts = '';
  for (let i = 1; i <= l.max_guests; i++) opts += `<option value="${i}"${i === Math.min(2, l.max_guests) ? ' selected' : ''}>${i} guest${i > 1 ? 's' : ''}</option>`;
  d.innerHTML = `<button class="x" data-close aria-label="Close">✕</button><div class="dl">${gallery(l)}<div class="info"><h2>${esc(l.title)}</h2><div class="meta">${esc(l.city)} · hosted by ${esc(l.host)} · ★ ${l.rating} (${l.reviews} reviews)</div><p style="margin:0">${esc(l.description)}</p><ul class="am">${l.amenities.split(',').map(a => `<li>${esc(a)}</li>`).join('')}</ul><div class="dates"><label>Check in<input type="date" id="b-in" min="${today}"></label><label>Check out<input type="date" id="b-out" min="${today}"></label></div><select id="b-g" aria-label="Guests">${opts}</select>${ME ? `<div class="meta">Booking as <b>${esc(ME.name)}</b></div>` : '<p class="meta">Log in or create an account to book.</p>'}<div id="sum" class="meta">Pick your dates to see the total.</div><p class="err" id="err" role="alert"></p><button class="btn" id="book">${ME ? 'Book this stay' : 'Log in to book'}</button></div></div>`;
  $('b-in').value = $('q-in').value; $('b-out').value = $('q-out').value;
  const sum = () => {
    const n = nights($('b-in').value, $('b-out').value);
    if (n < 1) { $('sum').textContent = 'Pick your dates to see the total.'; return; }
    const s = l.price * n, fee = Math.round(s * .1);
    $('sum').innerHTML = `<div class="row"><span>${money(l.price)} × ${n} night${n > 1 ? 's' : ''}</span><span>${money(s)}</span></div><div class="row"><span>Balay service fee</span><span>${money(fee)}</span></div><div class="row tot"><span>Total</span><span>${money(s + fee)}</span></div>`;
  };
  sum(); $('b-in').onchange = $('b-out').onchange = sum;
  $('book').onclick = async () => {
    if (!ME) { authDialog('login'); return; }
    const btn = $('book'); $('err').textContent = ''; btn.disabled = true;
    try {
      const r = await fetch('/api/book', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ listing_id: l.id, guests: +$('b-g').value, check_in: $('b-in').value, check_out: $('b-out').value }) });
      const j = await r.json();
      if (r.status === 401) { ME = null; renderNav(); authDialog('login'); return; }
      if (!r.ok) { $('err').textContent = j.error || 'Booking failed. Try again.'; btn.disabled = false; return; }
      d.innerHTML = `<div class="done"><h2>You’re booked!</h2><p>${esc(j.title)}<br>${esc($('b-in').value)} to ${esc($('b-out').value)}</p><p><b>Reference ${esc(j.ref)} · Total ${money(j.total)}</b></p><p class="meta">Payment is not connected yet, so nothing was charged.</p><button class="btn" data-close>Done</button></div>`;
      load();
    } catch (e) { $('err').textContent = 'Could not reach the server.'; btn.disabled = false; }
  };
  d.showModal();
}

document.addEventListener('click', e => {
  const t = e.target;
  if (t.closest('[data-close]')) { $('dlg').close(); return; }
  const fv = t.closest('[data-fav]');
  if (fv) { e.stopPropagation(); const i = fv.dataset.fav; favs[i] = !favs[i]; try { localStorage.setItem('balay-favs', JSON.stringify(favs)); } catch (x) {} render(); return; }
  const c = t.closest('[data-chip]');
  if (c) { type = c.dataset.chip; document.querySelectorAll('.chip').forEach(x => x.setAttribute('aria-pressed', x === c)); load(); return; }
  const o = t.closest('[data-open]');
  if (o) openStay(o.dataset.open);
});
document.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.dataset && e.target.dataset.open) openStay(e.target.dataset.open); });

$('chips').innerHTML = ['All', 'Beach', 'Mountain', 'City'].map(t => `<button class="chip" data-chip="${t}" aria-pressed="${t === 'All'}">${t}</button>`).join('');
$('q-in').min = $('q-out').min = today;
$('go').onclick = () => { load(); $('grid').scrollIntoView({ behavior: 'smooth' }); };
$('q-city').onchange = $('q-guests').onchange = load;
function renderNav() {
  $('nav').innerHTML = ME
    ? `<span class="meta">Hi, ${esc(ME.name.split(' ')[0])}</span>${ME.role === 'admin' ? '<a href="/admin">Admin</a>' : ''}${ME.role === 'staff' ? '<a href="/records">Records</a>' : ''}<a href="/account">My bookings</a><a href="/profile">Profile</a><button data-logout>Log out</button>`
    : '<button data-auth="login">Log in</button><button class="btn sm" data-auth="register">Sign up</button>';
}
function authDialog(mode) {
  const d = $('dlg'), reg = mode === 'register';
  d.innerHTML = `<button class="x" data-close aria-label="Close">✕</button><form class="info" id="af" style="max-width:420px;margin:auto"><h2>${reg ? 'Create your account' : 'Welcome back'}</h2>${reg ? '<input id="a-name" placeholder="Full name" autocomplete="name" required>' : ''}<input id="a-email" type="email" placeholder="Email" autocomplete="email" required><input id="a-pass" type="password" placeholder="Password${reg ? ' (8+ characters)' : ''}" autocomplete="${reg ? 'new-password' : 'current-password'}" required><p class="err" id="err" role="alert"></p><button class="btn" type="submit">${reg ? 'Sign up' : 'Log in'}</button><button type="button" class="link" data-auth="${reg ? 'login' : 'register'}">${reg ? 'Have an account? Log in' : 'New here? Create an account'}</button></form>`;
  if (!d.open) d.showModal();
  $('af').onsubmit = async e => {
    e.preventDefault();
    const post = (url, data) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
    const creds = { email: $('a-email').value, password: $('a-pass').value };
    if (reg) {
      const r0 = await post('/api/auth/register', { name: $('a-name').value, ...creds });
      const j0 = await r0.json();
      if (!r0.ok) { $('err').textContent = j0.error; return; }
    }
    const r = await post('/api/auth/login', creds);
    const j = await r.json();
    if (!r.ok) { $('err').textContent = j.error; return; }
    ME = j.user; renderNav(); d.close();
    if (ME.role === 'admin') location = '/admin';
    else if (ME.role === 'staff') location = '/records';
  };
}
document.addEventListener('click', async e => {
  const th = e.target.closest('[data-th]');
  if (th) { $('g-main').src = '/uploads/' + th.dataset.th; return; }
  const au = e.target.closest('[data-auth]');
  if (au) authDialog(au.dataset.auth);
  if (e.target.closest('[data-logout]')) { await fetch('/logout', { method: 'POST' }); ME = null; renderNav(); }
});
renderNav();
load();
