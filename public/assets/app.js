const $ = id => document.getElementById(id);
const money = n => '₱' + Number(n).toLocaleString('en-PH');
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
let ME = window.ME;
// today in the visitor's own time zone (YYYY-MM-DD)
const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
let type = 'All', stays = [], view = 'grid', map = null, markers = null, dmap = null;
const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
const stars = n => '★'.repeat(n) + '☆'.repeat(5 - n);
const postJson = (url, data) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });

/* ---------- cards ---------- */
function favBtn(l) {
  return `<button class="fav" data-fav="${l.id}" aria-pressed="${!!l.is_favorite}" aria-label="${l.is_favorite ? 'Remove from' : 'Save to'} wishlist">${l.is_favorite ? '♥' : '♡'}</button>`;
}

function pic(l, withFav) {
  const tag = +l.is_new ? '<span class="tag">New</span>' : '';
  const fav = withFav ? favBtn(l) : '';
  if (l.images && l.images.length) {
    return `<div class="pic"><img src="/uploads/${esc(l.images[0])}" alt="${esc(l.title)}" loading="lazy">${tag}${fav}</div>`;
  }
  const o = l.id * 7 % 40;
  return `<div class="pic"><svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice" aria-hidden="true"><defs><linearGradient id="g${l.id}" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="${l.color2}"/><stop offset="1" stop-color="${l.color1}"/></linearGradient></defs><rect width="400" height="300" fill="url(#g${l.id})"/><circle cx="${300 - o}" cy="90" r="34" fill="#fff" opacity=".55"/><path d="M0 210 Q100 ${150 + o} 200 200 T400 190 V300 H0Z" fill="${l.color1}" opacity=".75"/><path d="M0 250 Q120 210 240 245 T400 235 V300 H0Z" fill="#12263A" opacity=".35"/><rect x="${150 + o}" y="165" width="70" height="46" fill="#fff"/><path d="M${142 + o} 167 L${185 + o} 135 L${228 + o} 167Z" fill="#12263A"/></svg>${tag}${fav}</div>`;
}

async function load() {
  const p = { type: type === 'Saved' ? 'All' : type, city: $('q-city').value, guests: $('q-guests').value, check_in: $('q-in').value, check_out: $('q-out').value };
  if (type === 'Saved') p.favorites = '1';
  $('grid').innerHTML = '<div class="empty">Loading stays…</div>';
  try {
    const r = await fetch('/api/listings?' + new URLSearchParams(p));
    if (r.status === 401 && type === 'Saved') { ME = null; type = 'All'; renderNav(); renderChips(); return load(); }
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
  $('grid').innerHTML = stays.length
    ? stays.map(l => `<article class="card" data-open="${l.id}" tabindex="0" role="button" aria-label="View ${esc(l.title)}">${pic(l, true)}<h3>${esc(l.title)}</h3><div class="meta">${esc(l.city)} · up to ${l.max_guests} guests · ★ ${l.rating} (${l.reviews})</div><div><b>${money(l.price)}</b> night</div></article>`).join('')
    : `<div class="empty">${type === 'Saved' ? 'You have not saved any stays yet. Tap the heart on a stay to save it.' : 'No stays fit those filters. Try other dates, another place, or fewer guests.'}</div>`;
  $('grid').hidden = view === 'map';
  $('map').hidden = view !== 'map';
  if (view === 'map') renderMap();
}

/* ---------- map of all stays ---------- */
function renderMap() {
  const el = $('map');
  if (!window.L) { el.innerHTML = '<div class="empty">The map could not load. Check your internet connection.</div>'; return; }
  if (!map) {
    map = L.map('map').setView([9.25, 123.3], 10);
    L.tileLayer(TILES, { maxZoom: 19, attribution: ATTR }).addTo(map);
    markers = L.layerGroup().addTo(map);
  }
  markers.clearLayers();
  const pts = [];
  stays.filter(l => l.latitude != null && l.longitude != null).forEach(l => {
    const box = document.createElement('div');
    box.innerHTML = `<b>${esc(l.title)}</b><br>${esc(l.city)} · ${money(l.price)} night<br>`;
    const btn = document.createElement('button');
    btn.className = 'btn sm'; btn.textContent = 'View stay';
    btn.onclick = () => openStay(l.id);
    box.appendChild(btn);
    L.marker([l.latitude, l.longitude]).addTo(markers).bindPopup(box);
    pts.push([l.latitude, l.longitude]);
  });
  setTimeout(() => {
    map.invalidateSize();
    if (pts.length) map.fitBounds(pts, { padding: [30, 30], maxZoom: 13 });
  }, 0);
}

/* ---------- favorites ---------- */
async function toggleFav(id) {
  if (!ME) { authDialog('login'); return; }
  const r = await postJson('/api/favorites/toggle', { listing_id: +id });
  if (r.status === 401) { ME = null; renderNav(); renderChips(); authDialog('login'); return; }
  if (!r.ok) return;
  const j = await r.json(), l = stays.find(x => x.id == id);
  if (l) l.is_favorite = j.favorite;
  if (type === 'Saved' && !j.favorite) load(); else render();
}

/* ---------- stay page ---------- */
function gallery(l) {
  if (!(l.images && l.images.length)) return pic(l, false);
  const thumbs = l.images.length > 1 ? `<div class="thumbs">${l.images.map((f, i) => `<button type="button" data-th="${esc(f)}" aria-label="Show photo ${i + 1}"><img src="/uploads/${esc(f)}" alt=""></button>`).join('')}</div>` : '';
  return `<div class="gal"><div class="pic"><img id="g-main" src="/uploads/${esc(l.images[0])}" alt="${esc(l.title)}"></div>${thumbs}</div>`;
}

function nights(a, b) { return a && b ? Math.round((new Date(b) - new Date(a)) / 864e5) : 0; }
// calls fn('YYYY-MM-DD') for every night from a (included) to b (not included)
function eachNight(a, b, fn) {
  for (let t = Date.parse(a + 'T00:00:00Z'), e = Date.parse(b + 'T00:00:00Z'); t < e; t += 864e5) fn(new Date(t).toISOString().slice(0, 10));
}
const metaLine = l => `${esc(l.city)} · hosted by ${esc(l.host)} · ★ ${l.rating} (${l.reviews} review${+l.reviews === 1 ? '' : 's'})`;

function openStay(id) {
  const l = stays.find(x => x.id == id), d = $('dlg');
  if (!l) return;
  if (dmap) { try { dmap.remove(); } catch (e) {} dmap = null; }
  let opts = '';
  for (let i = 1; i <= l.max_guests; i++) opts += `<option value="${i}"${i === Math.min(2, l.max_guests) ? ' selected' : ''}>${i} guest${i > 1 ? 's' : ''}</option>`;
  const hasPin = l.latitude != null && l.longitude != null;
  d.innerHTML = `<button class="x" data-close aria-label="Close">✕</button>
<div class="dl">${gallery(l)}<div class="info"><h2>${esc(l.title)}</h2><div class="meta" id="st-meta">${metaLine(l)}</div><p style="margin:0">${esc(l.description)}</p><ul class="am">${l.amenities.split(',').map(a => `<li>${esc(a)}</li>`).join('')}</ul>
<div class="cal" id="cal" data-id="${l.id}" aria-label="Availability calendar"><div class="meta">Loading availability…</div></div>
<div class="dates"><label>Check in<input type="date" id="b-in" min="${today}"></label><label>Check out<input type="date" id="b-out" min="${today}"></label></div><select id="b-g" aria-label="Guests">${opts}</select>${ME ? `<div class="meta">Booking as <b>${esc(ME.name)}</b></div>` : '<p class="meta">Log in or create an account to book.</p>'}<div id="sum" class="meta">Pick your dates to see the total.</div><p class="err" id="err" role="alert"></p><button class="btn" id="book">${ME ? 'Book this stay' : 'Log in to book'}</button></div></div>
<div class="more">${hasPin ? '<h3>Location</h3><div id="smap" class="smap"></div>' : ''}<h3>Reviews</h3><div id="rv"><div class="meta">Loading reviews…</div></div></div>`;
  $('b-in').value = $('q-in').value; $('b-out').value = $('q-out').value;

  const sum = () => {
    const n = nights($('b-in').value, $('b-out').value);
    if (n < 1) { $('sum').textContent = 'Pick your dates to see the total.'; return; }
    const s = l.price * n, fee = Math.round(s * .1);
    $('sum').innerHTML = `<div class="row"><span>${money(l.price)} × ${n} night${n > 1 ? 's' : ''}</span><span>${money(s)}</span></div><div class="row"><span>Balay service fee</span><span>${money(fee)}</span></div><div class="row tot"><span>Total</span><span>${money(s + fee)}</span></div>`;
  };

  /* availability calendar */
  let booked = null;
  const start = $('b-in').value || today;
  let cy = +start.slice(0, 4), cm = +start.slice(5, 7) - 1;
  function drawCal() {
    const el = $('cal');
    if (!el || el.dataset.id != l.id || !booked) return;
    const a = $('b-in').value, b = $('b-out').value;
    const first = new Date(Date.UTC(cy, cm, 1)), dim = new Date(Date.UTC(cy, cm + 1, 0)).getUTCDate();
    const title = first.toLocaleString('en-US', { month: 'long', year: 'numeric', timeZone: 'UTC' });
    let cells = '';
    for (let i = 0; i < first.getUTCDay(); i++) cells += '<span></span>';
    for (let n = 1; n <= dim; n++) {
      const ds = `${cy}-${String(cm + 1).padStart(2, '0')}-${String(n).padStart(2, '0')}`;
      const past = ds < today, bk = booked.has(ds);
      const cls = ['day', past ? 'past' : '', bk ? 'bk' : '', ds === a || ds === b ? 'sel' : '', a && b && ds > a && ds < b ? 'rng' : ''].join(' ');
      cells += `<button type="button" class="${cls}" data-day="${ds}"${past ? ' disabled' : ''} aria-label="${ds}${bk ? ', booked' : ''}">${n}</button>`;
    }
    el.innerHTML = `<div class="cal-h"><button type="button" class="sm" data-cal="-1" aria-label="Previous month">‹</button><b>${title}</b><button type="button" class="sm" data-cal="1" aria-label="Next month">›</button></div><div class="cal-g">${'SMTWTFS'.split('').map(x => `<i>${x}</i>`).join('')}${cells}</div><div class="meta cal-n">Tap a check-in day, then a check-out day. Crossed-out nights are taken.</div>`;
  }
  function pickDay(ds) {
    const a = $('b-in').value, b = $('b-out').value, msg = $('err');
    msg.textContent = '';
    if (a && !b && ds > a) {
      let free = true;
      eachNight(a, ds, n => { if (booked.has(n)) free = false; });
      if (!free) { msg.textContent = 'Those dates include nights that are already booked.'; return; }
      $('b-out').value = ds;
    } else {
      if (booked.has(ds)) { msg.textContent = 'That night is already booked.'; return; }
      $('b-in').value = ds; $('b-out').value = '';
    }
    sync();
  }
  const sync = () => { sum(); drawCal(); };
  $('cal').onclick = e => {
    const nav = e.target.closest('[data-cal]');
    if (nav) { cm += +nav.dataset.cal; if (cm < 0) { cm = 11; cy--; } if (cm > 11) { cm = 0; cy++; } drawCal(); return; }
    const day = e.target.closest('[data-day]');
    if (day && booked) pickDay(day.dataset.day);
  };
  fetch(`/api/listings/${l.id}/availability`).then(r => r.ok ? r.json() : Promise.reject()).then(j => {
    booked = new Set();
    j.booked.forEach(x => eachNight(x.check_in, x.check_out, n => booked.add(n)));
    drawCal();
  }).catch(() => { const c = $('cal'); if (c && c.dataset.id == l.id) c.innerHTML = ''; });

  sum(); $('b-in').onchange = $('b-out').onchange = sync;
  $('book').onclick = async () => {
    if (!ME) { authDialog('login'); return; }
    const btn = $('book'); $('err').textContent = ''; btn.disabled = true;
    try {
      const r = await postJson('/api/book', { listing_id: l.id, guests: +$('b-g').value, check_in: $('b-in').value, check_out: $('b-out').value });
      const j = await r.json();
      if (r.status === 401) { ME = null; renderNav(); renderChips(); authDialog('login'); return; }
      if (!r.ok) { $('err').textContent = j.error || 'Booking failed. Try again.'; btn.disabled = false; return; }
      d.innerHTML = `<div class="done"><h2>You’re booked!</h2><p>${esc(j.title)}<br>${esc($('b-in').value)} to ${esc($('b-out').value)}</p><p><b>Reference ${esc(j.ref)} · Total ${money(j.total)}</b></p><p class="meta">Payment is not connected yet, so nothing was charged.</p><button class="btn" data-close>Done</button></div>`;
      load();
    } catch (e) { $('err').textContent = 'Could not reach the server.'; btn.disabled = false; }
  };
  d.showModal();
  initStayMap(l);
  loadReviews(l);
}

function initStayMap(l) {
  const el = $('smap');
  if (!el) return;
  if (!window.L) { el.innerHTML = '<div class="empty">The map could not load.</div>'; return; }
  dmap = L.map(el, { scrollWheelZoom: false }).setView([l.latitude, l.longitude], 13);
  L.tileLayer(TILES, { maxZoom: 19, attribution: ATTR }).addTo(dmap);
  L.marker([l.latitude, l.longitude]).addTo(dmap).bindPopup(esc(l.title));
  setTimeout(() => dmap && dmap.invalidateSize(), 60);
}

/* ---------- reviews of a stay (by guests who finished a trip) ---------- */
async function loadReviews(l) {
  const box = $('rv');
  if (!box) return;
  let j;
  try {
    const r = await fetch(`/api/listings/${l.id}/reviews`);
    if (!r.ok) throw 0;
    j = await r.json();
  } catch (e) { box.innerHTML = '<div class="meta">Could not load reviews.</div>'; return; }
  if ($('rv') !== box) return; // another stay was opened meanwhile
  const mine = j.my_review;
  const form = j.can_review
    ? `<form id="rv-form" class="rv-form"><b>${mine ? 'Update your review' : 'Rate your stay'}</b><select id="rv-rating" aria-label="Rating">${[5, 4, 3, 2, 1].map(n => `<option value="${n}"${mine && mine.rating == n ? ' selected' : ''}>${stars(n)} (${n})</option>`).join('')}</select><textarea id="rv-text" maxlength="1000" placeholder="Tell future guests about your stay (optional)">${esc(mine ? mine.comment : '')}</textarea><p class="err" id="rv-err" role="alert"></p><button class="btn sm" type="submit">${mine ? 'Save review' : 'Post review'}</button></form>`
    : `<p class="meta">${ME ? 'Guests can review a stay after their trip has ended.' : 'Log in to review a stay after your trip.'}</p>`;
  const list = j.reviews.length
    ? j.reviews.map(v => `<div class="rv"><div class="rv-h"><b>${esc(v.name)}</b><span class="stars" aria-label="${v.rating} out of 5">${stars(+v.rating)}</span><span class="meta">${esc(String(v.created_at).slice(0, 10))}</span></div>${v.comment ? `<p>${esc(v.comment)}</p>` : ''}</div>`).join('')
    : '<p class="meta">No reviews yet.</p>';
  box.innerHTML = form + list;
  const f = $('rv-form');
  if (f) f.onsubmit = async e => {
    e.preventDefault();
    $('rv-err').textContent = '';
    const r = await postJson(`/api/listings/${l.id}/reviews`, { rating: +$('rv-rating').value, comment: $('rv-text').value });
    const j2 = await r.json();
    if (!r.ok) { $('rv-err').textContent = j2.error || 'Could not save your review.'; return; }
    l.rating = j2.rating; l.reviews = j2.reviews;
    if ($('st-meta')) $('st-meta').innerHTML = metaLine(l);
    render();
    loadReviews(l);
  };
}

/* ---------- clicks ---------- */
document.addEventListener('click', e => {
  const t = e.target;
  if (t.closest('[data-close]')) { $('dlg').close(); return; }
  const fv = t.closest('[data-fav]');
  if (fv) { e.stopPropagation(); toggleFav(fv.dataset.fav); return; }
  const c = t.closest('#chips [data-chip]');
  if (c) { type = c.dataset.chip; renderChips(); load(); return; }
  const v = t.closest('[data-view]');
  if (v) {
    view = v.dataset.view;
    document.querySelectorAll('.view-toggle .chip').forEach(x => x.setAttribute('aria-pressed', x === v));
    render();
    return;
  }
  const o = t.closest('[data-open]');
  if (o) openStay(o.dataset.open);
});
document.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.dataset && e.target.dataset.open) openStay(e.target.dataset.open); });

function renderChips() {
  $('chips').innerHTML = ['All', 'Beach', 'Mountain', 'City', ...(ME ? ['Saved'] : [])]
    .map(t => `<button class="chip" data-chip="${t}" aria-pressed="${t === type}">${t === 'Saved' ? '♥ Saved' : t}</button>`).join('');
}
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
  d.innerHTML = `<button class="x" data-close aria-label="Close">✕</button><form class="info" id="af" style="max-width:420px;margin:auto"><h2>${reg ? 'Create your account' : 'Welcome back'}</h2>${reg ? '<input id="a-name" placeholder="Full name" autocomplete="name" required>' : ''}<input id="a-email" type="email" placeholder="Email" autocomplete="email" required><input id="a-pass" type="password" placeholder="Password${reg ? ' (8+ characters)' : ''}" autocomplete="${reg ? 'new-password' : 'current-password'}" required><p class="err" id="err" role="alert"></p><button class="btn" type="submit">${reg ? 'Sign up' : 'Log in'}</button>${reg ? '' : '<a class="meta" href="/forgot-password">Forgot your password?</a>'}<button type="button" class="link" data-auth="${reg ? 'login' : 'register'}">${reg ? 'Have an account? Log in' : 'New here? Create an account'}</button></form>`;
  if (!d.open) d.showModal();
  $('af').onsubmit = async e => {
    e.preventDefault();
    const creds = { email: $('a-email').value, password: $('a-pass').value };
    if (reg) {
      const r0 = await postJson('/api/auth/register', { name: $('a-name').value, ...creds });
      const j0 = await r0.json();
      if (!r0.ok) { $('err').textContent = j0.error; return; }
    }
    const r = await postJson('/api/auth/login', creds);
    const j = await r.json();
    if (!r.ok) { $('err').textContent = j.error; return; }
    ME = j.user; renderNav(); renderChips(); d.close();
    if (ME.role === 'admin') location = '/admin';
    else if (ME.role === 'staff') location = '/records';
    else load(); // refresh the hearts for this account
  };
}

document.addEventListener('click', async e => {
  const th = e.target.closest('[data-th]');
  if (th) { $('g-main').src = '/uploads/' + th.dataset.th; return; }
  const au = e.target.closest('[data-auth]');
  if (au) authDialog(au.dataset.auth);
  if (e.target.closest('[data-logout]')) {
    await fetch('/logout', { method: 'POST' });
    ME = null; if (type === 'Saved') type = 'All';
    renderNav(); renderChips(); load();
  }
});

renderNav();
renderChips();
load().then(() => {
  // links like /#stay-3 (from "My bookings") open that stay
  const m = location.hash.match(/^#stay-(\d+)$/);
  if (m && stays.some(x => x.id == m[1])) openStay(m[1]);
});
