/* PMOIS v2 — Web UI shared helpers (M3) */
async function api(path, opts = {}) {
  const res = await fetch(path, {
    headers: { 'Accept': 'application/json', ...(opts.body ? { 'Content-Type': 'application/json' } : {}) },
    ...opts,
  });
  const body = await res.json().catch(() => null);
  if (!res.ok || !body || body.success === false) {
    if (res.status === 401) { location.href = '/app/index.html'; throw new Error('unauthorized'); }
    // ห้ามยุบ error ที่มีโครงสร้างให้เหลือแค่ "UNKNOWN" — ถ้า body ไม่มี error object
    // (เช่น response ที่ไม่ใช่ envelope มาตรฐาน) ให้ใช้ HTTP status/statusText แทน
    // เพื่อให้ยังเห็นเบาะแสจริงว่าเกิดอะไรขึ้น แทนที่จะจบที่ "Error: UNKNOWN" เฉยๆ
    const err = new Error(body && body.error ? body.error.message : `HTTP ${res.status} ${res.statusText || ''}`.trim());
    err.code = body && body.error ? body.error.code : `HTTP_${res.status}`;
    err.status = res.status;
    console.error(`[PMOIS API] ${opts.method || 'GET'} ${path} failed`, { status: res.status, body });
    throw err;
  }
  return body.data;
}

async function requireSession() {
  try { await api('/api/v1/dashboards/workspace'); } catch (e) { location.href = '/app/index.html'; throw e; }
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

function esc(v) {
  return String(v ?? '').replace(/[&<>"']/g, c => {
    switch (c) {
      case '&': return '&';
      case '<': return '<';
      case '>': return '>';
      case '"': return '"';
      case "'": return '&#39;';
    }
  });
}

/* หมายเหตุ: badge()/healthBadge()/statusBadge() ใช้ร่วมกันหลายหน้า (Dashboard, Governance,
 * Knowledge, Automation, Reviews, Project Detail) กับ status vocabulary ที่ต่างกันไปในแต่ละหน้า
 * (job status, revision status, ADR status, ฯลฯ) — ห้ามเปลี่ยน mapping ของฟังก์ชันนี้ให้เป็นคำ
 * เฉพาะของ Projects page เพราะจะไปกระทบหน้าอื่นที่ไม่เกี่ยวข้อง ใช้ projectStatusBadge()/
 * projectHealthBadge() ด้านล่าง (เฉพาะหน้า Projects) แทน */
function badge(text, kind) { return `<span class="badge ${kind}">${esc(text)}</span>`; }
function healthBadge(h) { return badge(h, h === 'green' ? 'green' : h === 'yellow' ? 'yellow' : 'red'); }
function statusBadge(s) {
  const map = { active: 'green', planning: 'blue', on_hold: 'yellow', closed: 'gray', submitted: 'blue', cto_approved: 'green', cto_rejected: 'red', committed: 'green' };
  return badge(String(s).replaceAll('_', ' '), map[s] || 'gray');
}

/* ===== SweetAlert2 CDN =====
 * หมายเหตุ: เดิม script tag นี้มี integrity (SRI) hash ที่ไม่ถูกต้อง ทำให้เบราว์เซอร์
 * บล็อกการโหลดสคริปต์เงียบๆ (window.Swal ไม่เคยถูกสร้างขึ้นจริง) — เอาออกเพื่อให้โหลดได้
 */
let _swalReadyResolve;
const _swalReady = new Promise((resolve) => { _swalReadyResolve = resolve; });

(function() {
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
  script.onload = () => _swalReadyResolve();
  document.head.appendChild(script);
})();

/* ===== SweetAlert2 Helper — ใช้สำหรับ Success/Error/Warning/Confirmation เท่านั้น ===== */
async function showSwal(title, text, icon, showCancel = false, confirmButtonText = 'ตกลง') {
  await _swalReady;
  return window.Swal.fire({
    title,
    text,
    icon,
    showCancelButton: showCancel,
    confirmButtonText,
    cancelButtonText: 'ยกเลิก',
    allowOutsideClick: false,
  });
}

/* ===== Generic Modal Helper (ฟอร์ม Add/Edit — แยกจาก SweetAlert2) ===== */
let _activeModalEl = null;
function closeModal() {
  if (_activeModalEl) {
    _activeModalEl.remove();
    _activeModalEl = null;
  }
}
function openModal(innerHTML) {
  closeModal();
  const overlay = document.createElement('div');
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `<div class="modal-box">${innerHTML}</div>`;
  overlay.addEventListener('click', (e) => { if (e.target === overlay) closeModal(); });
  document.body.appendChild(overlay);
  _activeModalEl = overlay;
  return overlay;
}

/* ===== Add/Edit Workspace Modal ===== */
function openAddWorkspaceModal(workspace = null) {
  const overlay = openModal(`
    <div style="border-bottom:1px solid #e0e0e0;padding-bottom:12px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0">${workspace ? 'แก้ไขพื้นที่ทำงาน' : 'เพิ่มพื้นที่ทำงาน'}</h3>
      <button type="button" class="btn tiny" id="workspaceModalClose">&times;</button>
    </div>
    <form id="workspaceForm">
      <label>รหัส *</label>
      <input type="text" name="code" required placeholder="WS-00100" ${workspace ? 'disabled' : ''}>
      <label>ชื่อพื้นที่ทำงาน *</label>
      <input type="text" name="name" required placeholder="ชื่อพื้นที่ทำงาน">
      <label>รายละเอียด</label>
      <textarea name="description" rows="2"></textarea>
      <label>สถานะ</label>
      <select name="status">
        <option value="active">เปิดใช้งาน</option>
        <option value="inactive">ปิดใช้งาน</option>
      </select>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
        <button type="button" class="btn" id="workspaceModalCancel">ยกเลิก</button>
        <button type="submit" class="btn primary">บันทึก</button>
      </div>
    </form>
  `);

  const form = overlay.querySelector('#workspaceForm');
  if (workspace) {
    form.code.value = workspace.code || '';
    form.name.value = workspace.name || '';
    form.description.value = workspace.description || '';
    form.status.value = workspace.status || 'active';
  }

  overlay.querySelector('#workspaceModalClose').addEventListener('click', closeModal);
  overlay.querySelector('#workspaceModalCancel').addEventListener('click', closeModal);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn.disabled) return; // ป้องกันกดซ้ำระหว่างรอ response
    submitBtn.disabled = true;
    const formData = new FormData(form);
    const body = {};
    for (const [k, v] of formData.entries()) body[k] = v.trim();

    try {
      if (workspace) {
        await api(`/api/v1/workspaces/${workspace.id}`, { method: 'PUT', body: JSON.stringify(body) });
        closeModal();
        await showSwal('สำเร็จ', 'บันทึกพื้นที่ทำงานสำเร็จ', 'success');
        await loadAll();
      } else {
        const result = await api('/api/v1/workspaces', { method: 'POST', body: JSON.stringify(body) });
        closeModal();
        await showSwal('สำเร็จ', 'สร้างพื้นที่ทำงานสำเร็จ', 'success');
        await loadAll();
        selectWorkspace(result.id); // Success → เลือก Workspace ที่เพิ่งสร้างให้อัตโนมัติ
      }
    } catch (err) {
      submitBtn.disabled = false;
      showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
    }
  });
}

/* ===== Add/Edit Project Modal ===== */
let _projectModalRequestSeq = 0;
async function openAddProjectModal(project = null, defaultWorkspaceId = null) {
  // เปิดสองครั้งติดกัน (เช่น ดับเบิลคลิก Edit คนละแถว) แล้ว fetch workspaces เสร็จไม่ตามลำดับ —
  // ต้องเช็คว่ายังเป็น request ล่าสุดก่อน render ทับ modal ที่เปิดใหม่กว่าไปแล้ว
  const requestSeq = ++_projectModalRequestSeq;
  let workspacesHTML = '<option value="">— เลือกพื้นที่ทำงาน —</option>';
  try {
    const workspaces = await api('/api/v1/workspaces');
    workspacesHTML += workspaces.map(w => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
  } catch (e) {
    workspacesHTML = '<option value="">ไม่สามารถโหลดพื้นที่ทำงานได้</option>';
  }

  if (requestSeq !== _projectModalRequestSeq) return; // ถูกแทนที่ด้วยการเปิด modal ครั้งใหม่กว่าแล้ว

  const overlay = openModal(`
    <div style="border-bottom:1px solid #e0e0e0;padding-bottom:12px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0">${project ? 'แก้ไขโครงการ' : 'เพิ่มโครงการ'}</h3>
      <button type="button" class="btn tiny" id="projectModalClose">&times;</button>
    </div>
    <form id="projectForm">
      <label>ชื่อโครงการ *</label>
      <input type="text" name="name" required placeholder="ชื่อโครงการ" ${project ? 'disabled' : ''}>
      <label>รหัส *</label>
      <input type="text" name="code" required placeholder="PRJ-00100" ${project ? 'disabled' : ''}>
      <label>พื้นที่ทำงาน</label>
      <select name="workspaceId" ${project ? 'disabled' : ''}>${workspacesHTML}</select>
      <label>รูปแบบการพัฒนา</label>
      <select name="development_mode" ${project ? 'disabled' : ''}>
        <option value="">ค่าเริ่มต้น</option>
        <option value="manual">ทีมพัฒนา</option>
        <option value="ai_assisted">มี AI ช่วย</option>
        <option value="ai_dev_auto">AI พัฒนาอัตโนมัติ</option>
      </select>
      <label>ความคืบหน้า (%)</label>
      <input type="number" name="progress" min="0" max="100">
      <label>สุขภาพโครงการ</label>
      <select name="health">
        <option value="green">ปกติ</option>
        <option value="yellow">เฝ้าระวัง</option>
        <option value="red">เสี่ยง</option>
      </select>
      <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
        <button type="button" class="btn" id="projectModalCancel">ยกเลิก</button>
        <button type="submit" class="btn primary">บันทึก</button>
      </div>
    </form>
  `);

  const form = overlay.querySelector('#projectForm');
  if (project) {
    form.name.value = project.name || '';
    form.code.value = project.code || '';
    if (project.workspaceId) form.workspaceId.value = project.workspaceId;
    form.development_mode.value = project.development_mode || '';
    form.progress.value = project.progress ?? '';
    form.health.value = project.health || 'green';
  } else if (defaultWorkspaceId) {
    // เพิ่มโครงการใหม่ — เลือก Workspace ปัจจุบันให้อัตโนมัติ ไม่บังคับผู้ใช้เลือกซ้ำ
    form.workspaceId.value = defaultWorkspaceId;
  }

  overlay.querySelector('#projectModalClose').addEventListener('click', closeModal);
  overlay.querySelector('#projectModalCancel').addEventListener('click', closeModal);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn.disabled) return; // ป้องกันกดซ้ำระหว่างรอ response
    submitBtn.disabled = true;
    const formData = new FormData(form);
    const body = {};
    for (const [k, v] of formData.entries()) { if (v !== '') body[k] = v; }

    try {
      if (project) {
        await api(`/api/v1/projects/${project.id}`, { method: 'PUT', body: JSON.stringify(body) });
        closeModal();
        await showSwal('สำเร็จ', 'บันทึกโครงการสำเร็จ', 'success');
      } else {
        const result = await api('/api/v1/projects', { method: 'POST', body: JSON.stringify(body) });
        closeModal();
        // ข้อจำกัดสถาปัตยกรรมปัจจุบัน: โครงการใหม่ถูกสร้างเข้า workspace หลักของบัญชีผู้ใช้เสมอ
        // (ไม่ใช่ workspace ที่ระบุใน body) — ถ้าไม่ตรงกับ Tab ที่กำลังเปิดอยู่ ต้องแจ้งให้ทราบ
        // ตรงๆ แทนที่จะแสดง "สำเร็จ" เฉยๆ แล้วผู้ใช้งงว่าทำไมโครงการไม่ขึ้นใน Tab ปัจจุบัน
        if (result.workspaceId && result.workspaceId !== _activeWorkspaceId) {
          await showSwal('สร้างสำเร็จ', 'ระบบสร้างโครงการเข้าพื้นที่ทำงานหลักของบัญชีคุณแทน (ข้อจำกัดปัจจุบัน ไม่สามารถสร้างข้ามพื้นที่ทำงานได้)', 'info');
          await loadAll();
          selectWorkspace(result.workspaceId);
          return;
        }
        await showSwal('สำเร็จ', 'สร้างโครงการสำเร็จ', 'success');
      }
      await loadAll();
    } catch (err) {
      submitBtn.disabled = false;
      showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
    }
  });
}

/* =====================================================================
 * Projects Page — Workspace Tabs (navigation context) + Project List
 * (working dataset). Workspace เป็น Tab ไม่ใช่ Security Boundary —
 * Permission/Workspace Scope จริงยังบังคับฝั่ง Server เสมอ (PermissionResolver
 * + BaseRepository::applyWorkspaceScope) ไม่ว่า Tab ฝั่ง UI จะเลือกอะไรก็ตาม
 * ===================================================================== */
let _workspaces = [];
let _allProjects = [];        // flat list across all accessible workspaces — ใช้สำหรับ find-by-id เท่านั้น
let _projectsByWorkspace = {}; // { [workspaceId]: Project[] | null }  null = ไม่มีสิทธิ์เข้าถึง workspace นี้
let _activeWorkspaceId = null;
let _search = '';
let _statusFilter = '';
let _healthFilter = '';
let _page = 1;
let _pageSize = 10;

/* หมายเหตุ: label ชุดนี้เฉพาะหน้า Projects เท่านั้น ไม่แตะ statusBadge()/healthBadge()
 * ที่ใช้ร่วมกับหน้าอื่น — ค่า enum ที่ส่งไป/มาจาก API ไม่เปลี่ยน เปลี่ยนแค่ข้อความที่แสดงผล */
const PROJECT_STATUS_LABELS = { planning: 'วางแผน', active: 'กำลังดำเนินการ', on_hold: 'ระงับชั่วคราว', closed: 'ปิดโครงการ' };
const PROJECT_STATUS_COLORS = { planning: 'blue', active: 'green', on_hold: 'yellow', closed: 'gray' };
const PROJECT_HEALTH_LABELS = { green: 'ปกติ', yellow: 'เฝ้าระวัง', red: 'เสี่ยง' };
const DEV_MODE_LABELS = { manual: 'ทีมพัฒนา', ai_assisted: 'มี AI ช่วย', ai_dev_auto: 'AI พัฒนาอัตโนมัติ' };

function projectStatusBadge(s) { return badge(PROJECT_STATUS_LABELS[s] || s, PROJECT_STATUS_COLORS[s] || 'gray'); }
function projectHealthBadge(h) { return badge(PROJECT_HEALTH_LABELS[h] || h, h === 'green' ? 'green' : h === 'yellow' ? 'yellow' : 'red'); }

async function loadAll() {
  const wsTabsEl = document.getElementById('wsTabs');
  const wsHeaderEl = document.getElementById('wsHeader');
  const listEl = document.getElementById('projectList');
  const pagEl = document.getElementById('projectPagination');

  try {
    _workspaces = await api('/api/v1/workspaces') || [];
  } catch (err) {
    console.error('[PMOIS] loadAll (workspaces) failed', err);
    wsTabsEl.innerHTML = '';
    wsHeaderEl.innerHTML = `<span class="error">Error: ${esc(err.code || 'ERROR')} — ${esc(err.message || '')}</span>`;
    listEl.innerHTML = '';
    pagEl.innerHTML = '';
    return;
  }

  if (_workspaces.length === 0) {
    wsTabsEl.innerHTML = '<span class="muted">ยังไม่มีพื้นที่ทำงาน</span>';
    wsHeaderEl.innerHTML = '';
    listEl.innerHTML = '<span class="muted">เพิ่มพื้นที่ทำงานก่อนเริ่มสร้างโครงการ</span>';
    pagEl.innerHTML = '';
    return;
  }

  // ดึงโครงการของแต่ละ Workspace แยกกันผ่าน ?workspace_id= (ไม่ใช่ filter รายการเดียวฝั่ง
  // client) เพื่อให้ Permission/Workspace Scope ถูกบังคับจริงฝั่ง Server ต่อ workspace เสมอ —
  // Workspace Tab เป็นแค่ navigation context ไม่ใช่ security boundary (null = ไม่มีสิทธิ์)
  _projectsByWorkspace = {};
  await Promise.all(_workspaces.map(async (w) => {
    try {
      _projectsByWorkspace[w.id] = await api(`/api/v1/projects?workspace_id=${w.id}`);
    } catch (err) {
      _projectsByWorkspace[w.id] = null;
    }
  }));
  _allProjects = Object.values(_projectsByWorkspace).filter(Boolean).flat();

  // เลือก Workspace ตาม query string (?ws=) ถ้ายังมีอยู่จริง ไม่งั้นใช้ตัวแรก — ทำให้
  // Refresh หน้าแล้วยังอยู่ Workspace เดิม (state ที่เหมาะสม ไม่ reset โดยไม่จำเป็น)
  const urlWs = Number(new URLSearchParams(location.search).get('ws'));
  const stillExists = _workspaces.some(w => w.id === urlWs);
  if (!stillExists || _activeWorkspaceId === null) {
    _activeWorkspaceId = stillExists ? urlWs : _workspaces[0].id;
  }

  renderWorkspaceTabs();
  renderWorkspaceHeader();
  renderProjectSection();
}

function projectCountFor(workspaceId) {
  return (_projectsByWorkspace[workspaceId] || []).length;
}

function renderWorkspaceTabs() {
  const el = document.getElementById('wsTabs');
  el.innerHTML = _workspaces.map(w => `
    <button type="button" class="ws-tab ${w.id === _activeWorkspaceId ? 'active' : ''}" data-ws-id="${w.id}">${esc(w.name)} (${projectCountFor(w.id)})</button>
  `).join('');
  el.querySelectorAll('.ws-tab').forEach(btn => {
    btn.addEventListener('click', () => selectWorkspace(Number(btn.dataset.wsId)));
  });
}

function selectWorkspace(id) {
  _activeWorkspaceId = id;
  _page = 1;
  const params = new URLSearchParams(location.search);
  params.set('ws', String(id));
  history.replaceState(null, '', `${location.pathname}?${params.toString()}`);
  renderWorkspaceTabs();
  renderWorkspaceHeader();
  renderProjectSection();
}

function renderWorkspaceHeader() {
  const ws = _workspaces.find(w => w.id === _activeWorkspaceId);
  const el = document.getElementById('wsHeader');
  if (!ws) { el.innerHTML = ''; return; }
  const toggleLabel = ws.status === 'active' ? 'ปิดใช้งาน' : 'เปิดใช้งาน';
  el.innerHTML = `
    <h2>${esc(ws.name)}</h2>
    <div class="actions">
      <button class="btn tiny" onclick="editWorkspace(${ws.id})">แก้ไข</button>
      <button class="btn tiny" onclick="activateDeactivateWorkspace(${ws.id}, '${ws.status}')">${toggleLabel}</button>
    </div>
  `;
}

function editWorkspace(id) {
  const ws = _workspaces.find(w => w.id === id);
  if (ws) openAddWorkspaceModal(ws);
}

function editProject(id) {
  const p = _allProjects.find(x => x.id === id);
  if (p) openAddProjectModal(p, _activeWorkspaceId);
}

function renderProjectSection() {
  const listEl = document.getElementById('projectList');
  const pagEl = document.getElementById('projectPagination');

  const wsProjects = _projectsByWorkspace[_activeWorkspaceId];
  if (wsProjects === null) {
    listEl.innerHTML = '<span class="error">ไม่มีสิทธิ์เข้าถึงพื้นที่ทำงานนี้</span>';
    pagEl.innerHTML = '';
    return;
  }

  let projects = wsProjects || [];
  const hasAnyInWorkspace = projects.length > 0;

  if (_search.trim() !== '') {
    const q = _search.trim().toLowerCase();
    projects = projects.filter(p => p.code.toLowerCase().includes(q) || p.name.toLowerCase().includes(q));
  }
  if (_statusFilter) projects = projects.filter(p => p.status === _statusFilter);
  if (_healthFilter) projects = projects.filter(p => p.health === _healthFilter);

  if (projects.length === 0) {
    listEl.innerHTML = `<span class="muted">${hasAnyInWorkspace ? 'ไม่พบโครงการที่ตรงกับเงื่อนไข' : 'ยังไม่มีโครงการในพื้นที่ทำงานนี้'}</span>`;
    pagEl.innerHTML = '';
    return;
  }

  const totalPages = Math.max(1, Math.ceil(projects.length / _pageSize));
  if (_page > totalPages) _page = totalPages;
  if (_page < 1) _page = 1;
  const pageItems = projects.slice((_page - 1) * _pageSize, _page * _pageSize);

  let html = '<table><tr><th>รหัส</th><th>ชื่อโครงการ</th><th>สถานะ</th><th>รูปแบบการพัฒนา</th><th>ความคืบหน้า</th><th>สุขภาพ</th><th>Milestone ปัจจุบัน</th><th>จัดการ</th></tr>';
  for (const p of pageItems) {
    html += `<tr>
      <td>${esc(p.code)}</td>
      <td>${esc(p.name)}</td>
      <td>${projectStatusBadge(p.status)}</td>
      <td>${esc(DEV_MODE_LABELS[p.development_mode] || p.development_mode || '—')}</td>
      <td>${p.progress !== undefined && p.progress !== null ? p.progress + '%' : '—'}</td>
      <td>${projectHealthBadge(p.health)}</td>
      <td>${p.current_milestone ?? '—'}</td>
      <td>
        <button class="btn tiny" onclick="editProject(${p.id})" title="แก้ไขโครงการนี้">แก้ไข</button>
        <button class="btn tiny" onclick="moveProject(${p.id})" title="ย้ายโครงการนี้ไปพื้นที่ทำงานอื่น">ย้ายพื้นที่ทำงาน</button>
      </td>
    </tr>`;
  }
  html += '</table>';
  listEl.innerHTML = html;

  renderPagination(pagEl, totalPages);
}

function renderPagination(pagEl, totalPages) {
  const windowSize = 5;
  let start = Math.max(1, _page - Math.floor(windowSize / 2));
  let end = Math.min(totalPages, start + windowSize - 1);
  start = Math.max(1, end - windowSize + 1);

  let html = `<button ${_page <= 1 ? 'disabled' : ''} onclick="changePage(${_page - 1})">‹</button>`;
  if (start > 1) {
    html += `<button onclick="changePage(1)">1</button>`;
    if (start > 2) html += `<span class="muted" style="padding:0 4px">…</span>`;
  }
  for (let i = start; i <= end; i++) {
    html += `<button class="${i === _page ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
  }
  if (end < totalPages) {
    if (end < totalPages - 1) html += `<span class="muted" style="padding:0 4px">…</span>`;
    html += `<button onclick="changePage(${totalPages})">${totalPages}</button>`;
  }
  html += `<button ${_page >= totalPages ? 'disabled' : ''} onclick="changePage(${_page + 1})">›</button>`;
  html += `<select class="page-size" onchange="changePageSize(this.value)">
    <option value="10" ${_pageSize === 10 ? 'selected' : ''}>10 / หน้า</option>
    <option value="20" ${_pageSize === 20 ? 'selected' : ''}>20 / หน้า</option>
    <option value="50" ${_pageSize === 50 ? 'selected' : ''}>50 / หน้า</option>
  </select>`;
  pagEl.innerHTML = html;
}

function changePage(n) {
  _page = n;
  renderProjectSection();
}
function changePageSize(n) {
  _pageSize = Number(n);
  _page = 1;
  renderProjectSection();
}

/* ===== Actions ===== */
async function activateDeactivateWorkspace(workspaceId, currentStatus) {
  const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
  const actionLabel = newStatus === 'active' ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
  const result = await showSwal('ยืนยัน', `ต้องการ${actionLabel}พื้นที่ทำงานนี้ใช่หรือไม่?`, 'question', true, 'ยืนยัน');
  if (result.isConfirmed) {
    try {
      await api(`/api/v1/workspaces/${workspaceId}`, { method: 'PUT', body: JSON.stringify({ status: newStatus }) });
      await showSwal('สำเร็จ', `${actionLabel}พื้นที่ทำงานสำเร็จ`, 'success');
      await loadAll();
    } catch (err) {
      showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
    }
  }
}

async function moveProject(projectId) {
  const p = _allProjects.find(x => x.id === projectId);
  if (!p) return;

  const targets = _workspaces.filter(w => w.id !== p.workspaceId);
  if (targets.length === 0) {
    await showSwal('ข้อมูล', 'ไม่มีพื้นที่ทำงานอื่นให้ย้ายไป', 'info');
    return;
  }

  await _swalReady;
  const optionsHtml = targets.map(w => `<option value="${w.id}">${esc(w.name)}</option>`).join('');
  const result = await window.Swal.fire({
    title: 'ย้ายพื้นที่ทำงาน',
    html: `<div style="text-align:left"><p style="margin:0 0 8px">ย้าย "${esc(p.name)}" ไปยัง</p>
      <select id="moveTargetWs" class="swal2-select" style="display:block;width:100%">${optionsHtml}</select></div>`,
    showCancelButton: true,
    confirmButtonText: 'ย้าย',
    cancelButtonText: 'ยกเลิก',
    focusConfirm: false,
    allowOutsideClick: false,
    preConfirm: () => document.getElementById('moveTargetWs').value,
  });

  if (!result.isConfirmed) return;

  try {
    // ใช้ canonical structure endpoint (มีอยู่แล้ว รองรับ move_workspace/change_parent/promote
    // พร้อม audit ของตัวเองใน project_structure_history) แทนการเรียก PUT ตรงๆ
    await api(`/api/v1/projects/${projectId}/structure`, {
      method: 'PATCH',
      body: JSON.stringify({ action: 'move_workspace', new_workspace_id: result.value }),
    });
    await showSwal('สำเร็จ', 'ย้ายพื้นที่ทำงานสำเร็จ', 'success');
    await loadAll();
  } catch (err) {
    showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
  }
}
