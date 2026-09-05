/* PMOIS v2 — Web UI shared helpers (M3) */
async function api(path, opts = {}) {
  const res = await fetch(path, {
    headers: { 'Accept': 'application/json', ...(opts.body ? { 'Content-Type': 'application/json' } : {}) },
    ...opts,
  });
  const body = await res.json().catch(() => ({ success: false, error: { code: 'BAD_JSON', message: 'invalid response' } }));
  if (!res.ok || body.success === false) {
    if (res.status === 401) { location.href = '/app/index.html'; throw new Error('unauthorized'); }
    const err = new Error(body.error ? body.error.message : 'request failed');
    err.code = body.error ? body.error.code : 'UNKNOWN';
    err.status = res.status;
    throw err;
  }
  return body.data;
}

async function requireSession() {
  try { await api('/api/v1/dashboards/workspace'); } catch (e) { location.href = '/app/index.html'; throw e; }
}

function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function badge(text, kind) { return `<span class="badge ${kind}">${esc(text)}</span>`; }
function healthBadge(h) { return badge(h, h === 'green' ? 'green' : h === 'yellow' ? 'yellow' : 'red'); }
function statusBadge(s) {
  const map = { active: 'green', planning: 'blue', on_hold: 'yellow', closed: 'gray', submitted: 'blue', cto_approved: 'green', cto_rejected: 'red', committed: 'green' };
  return badge(s.replaceAll('_', ' '), map[s] || 'gray');
}

async function logout() {
  try { await api('/auth/logout', { method: 'POST' }); } catch (e) { /* ignore */ }
  location.href = '/app/index.html';
}

function topbar(active) {
  const el = document.createElement('div');
  el.className = 'topbar';
  el.innerHTML = `
    <strong>PMOIS v2</strong>
    <a href="/app/dashboard.html" class="${active === 'dashboard' ? 'active' : ''}">Dashboard</a>
    <a href="/app/analytics.html" class="${active === 'analytics' ? 'active' : ''}">Analytics</a>
    <a href="/app/projects.html" class="${active === 'projects' ? 'active' : ''}">Projects</a>
    <a href="/app/reviews.html" class="${active === 'reviews' ? 'active' : ''}">Reviews</a>
    <a href="/app/governance.html" class="${active === 'governance' ? 'active' : ''}">Governance</a>
    <a href="/app/knowledge.html" class="${active === 'knowledge' ? 'active' : ''}">Knowledge</a>
    <a href="/app/automation.html" class="${active === 'automation' ? 'active' : ''}">Automation</a>
    <span class="spacer"></span>
    <button class="btn" onclick="logout()">Logout</button>`;
  document.body.prepend(el);
}
