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
  return String(v ?? '').replace(/[&<>"']/g, c => {
    switch (c) {
      case '&': return '&';
      case '<': return '<';
      case '>': return '>';
      case '"': return '"';
      case "'": return "''";
    }
  });
}

function badge(text, kind) { return `<span class="badge ${kind}">${esc(text)}</span>`; }
function healthBadge(h) { return badge(h, h === 'green' ? 'green' : h === 'yellow' ? 'yellow' : 'red'); }
function statusBadge(s) {
  const map = { active: 'green', planning: 'blue', on_hold: 'yellow', closed: 'gray', submitted: 'blue', cto_approved: 'green', cto_rejected: 'red', committed: 'green' };
  return badge(s.replaceAll('_', ' '), map[s] || 'gray');
}

/* ===== SweetAlert2 CDN ===== */
(function() {
  const script = document.createElement('script');
  script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
  script.integrity = 'sha384-pQQkDk69Z6G+5jZRR+k6xjz0Q+5c6cLHUtOw5LZI8Oc4tlD24f0lB0/0vfgXS36Q';
  script.crossOrigin = 'anonymous';
  script.onload = () => { /* SweetAlert2 loaded */ };
  document.head.appendChild(script);
})();

/* ===== Modal Helpers ===== */

function openAddWorkspaceModal(workspace = null) {
  const modal = document.getElementById('workspaceModal');
  if (modal) {
    modalSwal.close();
    document.body.removeChild(modal);
  }

  const modalHTML = `
    <div id="workspaceModal" class="swal2-container swal2-modal" style="padding: 24px; max-width: 480px;">
      <div style="border-bottom: 1px solid #e0e0e0; padding-bottom: 12px; margin-bottom: 12px;">
        <h3 id="workspaceModalTitle">${workspace ? 'แก้ไข Workspace' : 'เพิ่ม Workspace'}</h3>
        <button class="swal2-close" aria-label="ปิด">&times;</button>
      </div>
      <form id="workspaceForm">
        <div style="margin: 16px 0;">
          <label>Code *</label>
          <input type="text" name="code" required placeholder="WS-00100" style="width: 100%; padding: 8px;">
        </div>
        <div style="margin: 16px 0;">
          <label>Name *</label>
          <input type="text" name="name" required placeholder="Workspace Name" style="width: 100%; padding: 8px;">
        </div>
        <div style="margin: 16px 0;">
          <label>Description</label>
          <textarea name="description" rows="2" style="width: 100%; padding: 8px;"></textarea>
        </div>
        <div style="margin: 16px 0;">
          <label>Status</label>
          <select name="status" style="width: 100%; padding: 8px;">
            <option value="active">active</option>
            <option value="planning">planning</option>
            <option value="on_hold">on_hold</option>
          </select>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
          <button type="submit" class="swal2-confirm btn primary" style="padding: 8px 16px;">บันทึก</button>
          <button type="button" class="swal2-cancel" style="padding: 8px 16px;">ยกเลิก</button>
        </div>
      </form>
    </div>
  `;
  modalSwal = showSwal('เพิ่ม Workspace', '', 'info', false);
  document.body.insertAdjacentHTML('beforeend', modalHTML);

  const form = document.getElementById('workspaceForm');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    const body = {};
    for (const [k, v] of formData.entries()) body[k] = v.trim() || undefined;

    try {
      const result = await api('/api/v1/workspaces', { method: 'POST', body: JSON.stringify(body) });
      showSwal('สำเร็จ', `สร้าง Workspace #${result.id} สำเร็จ`, 'success');
      modalSwal.close();
      loadWorkspaces();
      loadProjects();
    } catch (err) {
      showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
    }
  });

  const closeBtn = modal.querySelector('.swal2-close');
  if (closeBtn) closeBtn.click = () => modalSwal.close();
}

/* Add Project Modal */
function openAddProjectModal(project = null) {
  const modal = document.getElementById('projectModal');
  if (modal) {
    modalSwal.close();
    document.body.removeChild(modal);
  }

  // Load workspaces for the select
  let workspacesHTML = '';
  try {
    const workspaces = await api('/api/v1/workspaces');
    workspacesHTML = workspaces.map(w => `<option value="${w.id}">${w.code} - ${w.name}</option>`).join('');
  } catch (e) {
    workspacesHTML = '<option value="">ไม่สามารถโหลด Workspace ได้</option>';
  }

  const modalHTML = `
    <div id="projectModal" class="swal2-container swal2-modal" style="padding: 24px; max-width: 480px;">
      <div style="border-bottom: 1px solid #e0e0e0; padding-bottom: 12px; margin-bottom: 12px;">
        <h3 id="projectModalTitle">${project ? 'แก้ไข Project' : 'เพิ่ม Project'}</h3>
        <button class="swal2-close" aria-label="ปิด">&times;</button>
      </div>
      <form id="projectForm">
        <div style="margin: 16px 0;">
          <label>Name *</label>
          <input type="text" name="name" required placeholder="Project Name" style="width: 100%; padding: 8px;">
        </div>
        <div style="margin: 16px 0;">
          <label>Code *</label>
          <input type="text" name="code" required placeholder="PRJ-00100" style="width: 100%; padding: 8px;">
        </div>
        <div style="margin: 16px 0;">
          <label>Workspace</label>
          <select name="workspaceId" style="width: 100%; padding: 8px;">
            ${workspacesHTML}
          </select>
        </div>
        <div style="margin: 16px 0;">
          <label>Development Mode</label>
          <select name="development_mode" style="width: 100%; padding: 8px;">
            <option value="">default</option>
            <option value="manual">Manual</option>
            <option value="ai_assisted">AI Assisted</option>
            <option value="ai_dev_auto">AI Dev Auto</option>
          </select>
        </div>
        <div style="margin: 16px 0;">
          <label>Progress (%)</label>
          <input type="number" name="progress" min="0" max="100" style="width: 100%; padding: 8px;">
        </div>
        <div style="margin: 16px 0;">
          <label>Health</label>
          <select name="health" style="width: 100%; padding: 8px;">
            <option value="green">green</option>
            <option value="yellow">yellow</option>
            <option value="red">red</option>
          </select>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px;">
          <button type="submit" class="swal2-confirm btn primary" style="padding: 8px 16px;">บันทึก</button>
          <button type="button" class="swal2-cancel" style="padding: 8px 16px;">ยกเลิก</button>
        </div>
      </form>
    </div>
  `;
  modalSwal = showSwal('เพิ่ม Project', '', 'info', false);
  document.body.insertAdjacentHTML('beforeend', modalHTML);

  const form = document.getElementById('projectForm');
  if (project) {
    // Fill form with existing data
    if (project.name) form.querySelector('input[name="name"]').value = project.name;
    if (project.code) form.querySelector('input[name="code"]').value = project.code;
    if (project.workspaceId) {
      const wsSelect = form.querySelector('select[name="workspaceId"]');
      if (wsSelect) wsSelect.value = project.workspaceId;
    }
    if (project.development_mode) form.querySelector('select[name="development_mode"]').value = project.development_mode;
    if (project.progress !== undefined) form.querySelector('input[name="progress"]').value = project.progress;
    if (project.health) form.querySelector('select[name="health"]').value = project.health;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(form);
    const body = {};
    for (const [k, v] of formData.entries()) body[k] = v.trim() || undefined;

    try {
      const result = await api('/api/v1/projects', { method: 'POST', body: JSON.stringify(body) });
      showSwal('สำเร็จ', `สร้าง Project #${result.id} สำเร็จ`, 'success');
      modalSwal.close();
      loadWorkspaces();
      loadProjects();
    } catch (err) {
      showSwal('ผิดพลาด', `${err.code}: ${err.message}`, 'error');
    }
  });

  const closeBtn = modal.querySelector('.swal2-close');
  if (closeBtn) closeBtn.click = () => modalSwal.close();
}

/* ===== Data Tables ===== */
async function loadWorkspaces() {
  try {
    const workspaces = await api('/api/v1/workspaces');
    const listEl = document.getElementById('workspaceList');
    if (!workspaces || workspaces.length === 0) {
      listEl.innerHTML = '<span class="muted">ยังไม่มี Workspace</span>';
      return;
    }

    let html = '<table><tr><th>Code</th><th>Name</th><th>Status</th><th>จำนวน Projects</th><th>Actions</th></tr>';
    for (const ws of workspaces) {
      let projectCount = 0;
      try {
        const allProjects = await api('/api/v1/projects');
        projectCount = allProjects.filter(p => p.workspaceId === ws.id).length;
      } catch (e) { /* ignore */ }

      html += `<tr>
        <td>${ws.code}</td>
        <td>${esc(ws.name)}</td>
        <td><span class="badge ${ws.status === 'active' ? 'green' : ws.status === 'planning' ? 'blue' : 'yellow'}">${ws.status}</span></td>
        <td>${projectCount}</td>
        <td>
          <button class="btn tiny" onclick="openAddWorkspaceModal(${JSON.stringify({ id: ws.id, code: ws.code, name: ws.name })}">Edit</button>
          <button class="btn tiny" onclick="activateDeactivateWorkspace(${ws.id}, '${ws.status}')">Activate/Deactivate</button>
        </td>
      </tr>`;
    }
    html += '</table>';
    listEl.innerHTML = html;
  } catch (err) {
    document.getElementById('workspaceList').innerHTML = `<span class="error">Error: ${err.code}</span>`;
  }
}

async function loadProjects() {
  try {
    const projects = await api('/api/v1/projects');
    const listEl = document.getElementById('projectList');
    if (!projects || projects.length === 0) {
      listEl.innerHTML = '<span class="muted">ยังไม่มี Project</span>';
      return;
    }

    let html = '<table><tr><th>Code</th><th>Name</th><th>Workspace</th><th>Status</th><th>Dev Mode</th><th>Progress</th><th>Health</th><th>Current Milestone</th><th>Actions</th></tr>';
    for (const p of projects) {
      html += `<tr>
        <td>${p.code}</td>
        <td>${esc(p.name)}</td>
        <td>${p.workspaceCode || '—'}</td>
        <td>${statusBadge(p.status)}</td>
        <td>${p.development_mode || '—'}</td>
        <td>${p.progress !== undefined ? p.progress + '%' : '—'}</td>
        <td>${healthBadge(p.health)}</td>
        <td>${p.current_milestone || '—'}</td>
        <td>
          <button class="btn tiny" onclick="openAddProjectModal(${JSON.stringify(p)})">Edit</button>
          <button class="btn tiny" onclick="moveProject(${p.id})">Change Parent</button>
        </td>
      </tr>`;
    }
    html += '</table>';
    listEl.innerHTML = html;
  } catch (err) {
    document.getElementById('projectList').innerHTML = `<span class="error">Error: ${err.code}</span>`;
  }
}

/* ===== Actions ===== */
async function activateDeactivateWorkspace(workspaceId, currentStatus) {
  const newStatus = currentStatus === 'active' ? 'on_hold' : 'active';
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
  // TODO: Implement Change Parent when available
  const result = await showSwal('ข้อมูล', 'ฟีเจอร์ Change Parent ยังไม่ได้ Implement ในรอบนี้', 'info', true, 'ตกลง');
  if (result.isConfirmed) {
    // Placeholder
  }
  showSwal('ข้อมูล', 'ฟีเจอร์นี้จะมาในรอบถัดไป', 'info');
}

/* ===== Init on Load ===== */
(async () => {
  await requireSession();
  await loadWorkspaces();
  await loadProjects();
})();
</script>
</body>
</html>