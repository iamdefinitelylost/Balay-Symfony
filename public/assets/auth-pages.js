// Forgot-password and reset-password pages: send the form as JSON to the API in data-api.
const $ = id => document.getElementById(id);
const form = $('f');
form.addEventListener('submit', async e => {
  e.preventDefault();
  const err = $('err');
  err.textContent = '';
  const body = Object.fromEntries(new FormData(form).entries());
  if ('confirm' in body && body.confirm !== body.password) { err.textContent = 'The two passwords do not match.'; return; }
  try {
    const r = await fetch(form.dataset.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => ({}));
    if (!r.ok) { err.textContent = j.error || 'Something went wrong. Please try again.'; return; }
    form.hidden = true;
    $('done').hidden = false;
  } catch (x) {
    err.textContent = 'Could not reach the server.';
  }
});
