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
      <h3 style="margin:0">${workspace ? 'แก้ไข Workspace' : 'เพิ่ม Workspace'}</h3>
      <button type="button" class="btn tiny" id="workspaceModalClose">&times;</button>
    </div>
    <form id="workspaceForm">
      <label>Code *</label>
      <input type="text" name="code" required placeholder="WS-00100" ${workspace ? 'disabled' : ''}>
      <label>Name *</label>
      <input type="text" name="name" required placeholder="Workspace Name">
      <label>Description</label>
      <textarea name="description" rows="2"></textarea>
      <label>Status</label>
      <select name="status">
        <option value="active">active</option>
        <option value="inactive">inactive</option>
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
        await showSwal('สำเร็จ', 'บันทึก Workspace สำเร็จ', 'success');
      } else {
        const result = await api('/api/v1/workspaces', { method: 'POST', body: JSON.stringify(body) });
        closeModal();
        await showSwal('สำเร็จ', `สร้าง Workspace #${result.id} สำเร็จ`, 'success');
      }
      loadWorkspaces();
      loadProjects();
    } catch (err) {
      submitBtn.disabled = false;
      showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
    }
  });
}

/* ===== Add/Edit Project Modal ===== */
let _projectModalRequestSeq = 0;
async function openAddProjectModal(project = null) {
  // เปิดสองครั้งติดกัน (เช่น ดับเบิลคลิก Edit คนละแถว) แล้ว fetch workspaces เสร็จไม่ตามลำดับ —
  // ต้องเช็คว่ายังเป็น request ล่าสุดก่อน render ทับ modal ที่เปิดใหม่กว่าไปแล้ว
  const requestSeq = ++_projectModalRequestSeq;
  let workspacesHTML = '<option value="">— เลือก Workspace —</option>';
  try {
    const workspaces = await api('/api/v1/workspaces');
    workspacesHTML += workspaces.map(w => `<option value="${w.id}">${esc(w.code)} - ${esc(w.name)}</option>`).join('');
  } catch (e) {
    workspacesHTML = '<option value="">ไม่สามารถโหลด Workspace ได้</option>';
  }

  if (requestSeq !== _projectModalRequestSeq) return; // ถูกแทนที่ด้วยการเปิด modal ครั้งใหม่กว่าแล้ว

  const overlay = openModal(`
    <div style="border-bottom:1px solid #e0e0e0;padding-bottom:12px;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0">${project ? 'แก้ไข Project' : 'เพิ่ม Project'}</h3>
      <button type="button" class="btn tiny" id="projectModalClose">&times;</button>
    </div>
    <form id="projectForm">
      <label>Name *</label>
      <input type="text" name="name" required placeholder="Project Name" ${project ? 'disabled' : ''}>
      <label>Code *</label>
      <input type="text" name="code" required placeholder="PRJ-00100" ${project ? 'disabled' : ''}>
      <label>Workspace</label>
      <select name="workspaceId">${workspacesHTML}</select>
      <label>Development Mode</label>
      <select name="development_mode" ${project ? 'disabled' : ''}>
        <option value="">default</option>
        <option value="manual">Manual</option>
        <option value="ai_assisted">AI Assisted</option>
        <option value="ai_dev_auto">AI Dev Auto</option>
      </select>
      <label>Progress (%)</label>
      <input type="number" name="progress" min="0" max="100">
      <label>Health</label>
      <select name="health">
        <option value="green">green</option>
        <option value="yellow">yellow</option>
        <option value="red">red</option>
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
        await showSwal('สำเร็จ', 'บันทึก Project สำเร็จ', 'success');
      } else {
        const result = await api('/api/v1/projects', { method: 'POST', body: JSON.stringify(body) });
        closeModal();
        await showSwal('สำเร็จ', `สร้าง Project #${result.id} สำเร็จ`, 'success');
      }
      loadWorkspaces();
      loadProjects();
    } catch (err) {
      submitBtn.disabled = false;
      showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
    }
  });
}

/* ===== Data Tables ===== */
let _workspacesCache = [];
let _projectsCache = [];

async function loadWorkspaces() {
  try {
    const workspaces = await api('/api/v1/workspaces');
    _workspacesCache = workspaces;
    const listEl = document.getElementById('workspaceList');
    if (!workspaces || workspaces.length === 0) {
      listEl.innerHTML = '<span class="muted">ยังไม่มี Workspace</span>';
      return;
    }

    let projectCounts = {};
    try {
      const allProjects = await api('/api/v1/projects');
      for (const p of allProjects) projectCounts[p.workspaceId] = (projectCounts[p.workspaceId] || 0) + 1;
    } catch (e) { /* ignore */ }

    let html = '<table><tr><th>Code</th><th>Name</th><th>Status</th><th>จำนวน Projects</th><th>Actions</th></tr>';
    for (const ws of workspaces) {
      html += `<tr>
        <td>${esc(ws.code)}</td>
        <td>${esc(ws.name)}</td>
        <td><span class="badge ${ws.status === 'active' ? 'green' : 'gray'}">${esc(ws.status)}</span></td>
        <td>${projectCounts[ws.id] || 0}</td>
        <td>
          <button class="btn tiny" onclick="editWorkspace(${ws.id})">Edit</button>
          <button class="btn tiny" onclick="activateDeactivateWorkspace(${ws.id}, '${ws.status}')">Activate/Deactivate</button>
        </td>
      </tr>`;
    }
    html += '</table>';
    listEl.innerHTML = html;
  } catch (err) {
    console.error('[PMOIS] loadWorkspaces failed', err);
    document.getElementById('workspaceList').innerHTML = `<span class="error">Error: ${esc(err.code || 'ERROR')} — ${esc(err.message || '')}</span>`;
  }
}

function editWorkspace(id) {
  const ws = _workspacesCache.find(w => w.id === id);
  if (ws) openAddWorkspaceModal(ws);
}

async function loadProjects() {
  try {
    const projects = await api('/api/v1/projects');
    _projectsCache = projects;
    const listEl = document.getElementById('projectList');
    if (!projects || projects.length === 0) {
      listEl.innerHTML = '<span class="muted">ยังไม่มี Project</span>';
      return;
    }

    let html = '<table><tr><th>Code</th><th>Name</th><th>Workspace</th><th>Status</th><th>Dev Mode</th><th>Progress</th><th>Health</th><th>Current Milestone</th><th>Actions</th></tr>';
    for (const p of projects) {
      html += `<tr>
        <td>${esc(p.code)}</td>
        <td>${esc(p.name)}</td>
        <td>${esc(p.workspaceCode || '—')}</td>
        <td>${statusBadge(p.status)}</td>
        <td>${esc(p.development_mode || '—')}</td>
        <td>${p.progress !== undefined && p.progress !== null ? p.progress + '%' : '—'}</td>
        <td>${healthBadge(p.health)}</td>
        <td>${p.current_milestone ?? '—'}</td>
        <td>
          <button class="btn tiny" onclick="editProject(${p.id})">Edit</button>
          <button class="btn tiny" onclick="moveProject(${p.id})">Change Parent</button>
        </td>
      </tr>`;
    }
    html += '</table>';
    listEl.innerHTML = html;
  } catch (err) {
    console.error('[PMOIS] loadProjects failed', err);
    document.getElementById('projectList').innerHTML = `<span class="error">Error: ${esc(err.code || 'ERROR')} — ${esc(err.message || '')}</span>`;
  }
}

function editProject(id) {
  const p = _projectsCache.find(x => x.id === id);
  if (p) openAddProjectModal(p);
}

/* ===== Actions ===== */
async function activateDeactivateWorkspace(workspaceId, currentStatus) {
  const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
  const result = await showSwal('ยืนยัน', `เปลี่ยนสถานะ Workspace เป็น "${newStatus}" ใช่หรือไม่?`, 'question', true, 'ยืนยัน');
  if (result.isConfirmed) {
    try {
      await api(`/api/v1/workspaces/${workspaceId}`, { method: 'PUT', body: JSON.stringify({ status: newStatus }) });
      showSwal('สำเร็จ', `เปลี่ยนสถานะ Workspace เป็น "${newStatus}" สำเร็จ`, 'success');
      loadWorkspaces();
      loadProjects();
    } catch (err) {
      showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
    }
  }
}

async function moveProject(projectId) {
  await showSwal('ข้อมูล', 'ฟีเจอร์ Change Parent ยังไม่ได้ Implement ในรอบนี้ — จะมาในรอบถัดไป', 'info', true, 'ตกลง');
}
