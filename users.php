<?php
/**
 * Gestión de Usuarios
 * Permite listar, crear y editar usuarios del sistema con roles 'admin' y 'customer'
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_admin();

$pdo = get_db();
$stmt = $pdo->query("SELECT `id`, `name`, `email`, `role`, `created_at` FROM `users` ORDER BY `id` ASC");
$users = $stmt->fetchAll();

$pageTitle = 'Usuarios';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="ri-group-line"></i> Gestión de Usuarios</h1>
    <p class="page-desc">Usuarios autorizados para acceder, optimizar imágenes y administrar la plataforma.</p>
  </div>
  <button type="button" class="btn btn-primary" onclick="openUserModal()">
    <i class="ri-user-add-line"></i> Nuevo Usuario
  </button>
</div>

<!-- Tabla de Usuarios -->
<div class="card" style="padding: 0; overflow: hidden;">
  <div class="table-responsive" style="border: none;">
    <table class="media-table">
      <thead>
        <tr>
          <th style="width: 60px;">ID</th>
          <th>Nombre</th>
          <th>Correo Electrónico</th>
          <th>Rol</th>
          <th>Fecha de Registro</th>
          <th style="text-align: right; width: 120px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="users-table-body">
        <?php foreach ($users as $u): ?>
          <?php $isAdmin = ($u['role'] === 'admin'); ?>
          <tr id="user-row-<?= $u['id'] ?>">
            <td><strong>#<?= $u['id'] ?></strong></td>
            <td class="col-user-name" style="font-weight: 600;"><?= htmlspecialchars($u['name']) ?></td>
            <td class="col-user-email"><?= htmlspecialchars($u['email']) ?></td>
            <td class="col-user-role">
              <?php if ($isAdmin): ?>
                <span class="status-badge status-done" style="text-transform: uppercase; font-size: 0.7rem;">ADMIN</span>
              <?php else: ?>
                <span class="status-badge" style="background: var(--primary-light); color: var(--primary); text-transform: uppercase; font-size: 0.7rem; font-weight: 700;">CUSTOMER</span>
              <?php endif; ?>
            </td>
            <td style="color: var(--text-muted); font-size: 0.85rem;">
              <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
            </td>
            <td style="text-align: right; white-space: nowrap;">
              <button 
                type="button" 
                class="btn btn-outline btn-sm btn-edit-user" 
                onclick="openEditUserModal(<?= htmlspecialchars(json_encode([
                    'id'    => (int) $u['id'],
                    'name'  => $u['name'],
                    'email' => $u['email'],
                    'role'  => $u['role'],
                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)"
                title="Editar este usuario"
              >
                <i class="ri-edit-line"></i> Editar
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal 1: Crear Usuario -->
<div id="user-modal" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-size: 1.25rem; font-weight: 700;">Crear Nuevo Usuario</h3>
      <button type="button" class="lightbox-close" style="color: var(--text-muted); font-size: 1.4rem; position: static;" onclick="closeUserModal()">
        <i class="ri-close-line"></i>
      </button>
    </div>

    <form id="create-user-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <div class="form-group">
        <label class="form-label" for="user-name">Nombre Completo</label>
        <input 
          type="text" 
          id="user-name" 
          name="name" 
          class="form-input" 
          required 
          placeholder="Ej. Cristian Dev"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="user-email">Correo Electrónico</label>
        <input 
          type="email" 
          id="user-email" 
          name="email" 
          class="form-input" 
          required 
          placeholder="usuario@ejemplo.com"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="user-role">Tipo de Usuario (Rol)</label>
        <select id="user-role" name="role" class="form-input" style="font-weight: 500;">
          <option value="customer" selected>Cliente (Customer — Puede subir y ver medios)</option>
          <option value="admin">Administrador (Admin — Acceso total al sistema y configuración)</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="user-password">Contraseña</label>
        <input 
          type="password" 
          id="user-password" 
          name="password" 
          class="form-input" 
          required 
          minlength="4"
          placeholder="Mínimo 4 caracteres"
        >
      </div>

      <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.75rem;">
        <button type="button" class="btn btn-outline" onclick="closeUserModal()">Cancelar</button>
        <button type="submit" id="btn-save-user" class="btn btn-primary">Guardar Usuario</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal 2: Editar Usuario -->
<div id="edit-user-modal" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="modal-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
      <h3 style="font-size: 1.25rem; font-weight: 700;">
        <i class="ri-user-settings-line" style="color: var(--primary);"></i> Editar Usuario <span id="edit-modal-user-id" style="color: var(--text-muted); font-size: 1rem; font-weight: 500;"></span>
      </h3>
      <button type="button" class="lightbox-close" style="color: var(--text-muted); font-size: 1.4rem; position: static;" onclick="closeEditUserModal()">
        <i class="ri-close-line"></i>
      </button>
    </div>

    <form id="edit-user-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" id="edit-user-id" name="id" value="">

      <div class="form-group">
        <label class="form-label" for="edit-user-name">Nombre Completo</label>
        <input 
          type="text" 
          id="edit-user-name" 
          name="name" 
          class="form-input" 
          required 
          placeholder="Nombre del usuario"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="edit-user-email">Correo Electrónico</label>
        <input 
          type="email" 
          id="edit-user-email" 
          name="email" 
          class="form-input" 
          required 
          placeholder="correo@ejemplo.com"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="edit-user-role">Tipo de Usuario (Rol)</label>
        <select id="edit-user-role" name="role" class="form-input" style="font-weight: 500;">
          <option value="customer">Cliente (Customer — Puede subir y ver medios)</option>
          <option value="admin">Administrador (Admin — Acceso total al sistema y configuración)</option>
        </select>
        <span id="edit-role-warning" style="display: none; font-size: 0.75rem; color: var(--warning, #f59e0b); margin-top: 0.35rem;">
          <i class="ri-information-line"></i> El usuario principal debe mantener siempre el rol de Administrador.
        </span>
      </div>

      <div class="form-group">
        <label class="form-label" for="edit-user-password">Nueva Contraseña <span style="font-weight: normal; color: var(--text-muted); font-size: 0.8rem;">(Opcional)</span></label>
        <div style="position: relative;">
          <input 
            type="password" 
            id="edit-user-password" 
            name="password" 
            class="form-input" 
            placeholder="•••••••• (Dejar en blanco para conservar la actual)"
            autocomplete="new-password"
            minlength="4"
            style="padding-right: 2.75rem;"
          >
          <button 
            type="button" 
            id="toggle-edit-user-pass" 
            class="btn-icon-addon" 
            title="Mostrar / Ocultar contraseña"
          >
            <i class="ri-eye-line"></i>
          </button>
        </div>
        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
          Ingresa una nueva contraseña solo si deseas cambiarla (mínimo 4 caracteres).
        </span>
      </div>

      <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.75rem;">
        <button type="button" class="btn btn-outline" onclick="closeEditUserModal()">Cancelar</button>
        <button type="submit" id="btn-update-user" class="btn btn-primary">
          <i class="ri-save-line"></i> Guardar Cambios
        </button>
      </div>
    </form>
  </div>
</div>

<script>
console.log('[Users] Vista de usuarios cargada.');

// --- MODAL CREAR USUARIO ---
function openUserModal() {
  console.log('[Modal] Abriendo formulario de nuevo usuario');
  document.getElementById('user-modal').classList.add('active');
  document.getElementById('user-name').focus();
}

function closeUserModal() {
  document.getElementById('user-modal').classList.remove('active');
  document.getElementById('create-user-form').reset();
}

// --- MODAL EDITAR USUARIO ---
function openEditUserModal(user) {
  console.log('[Modal] Abriendo edición para usuario:', user);
  if (!user || !user.id) return;

  document.getElementById('edit-user-id').value = user.id;
  document.getElementById('edit-modal-user-id').textContent = `#${user.id}`;
  document.getElementById('edit-user-name').value = user.name || '';
  document.getElementById('edit-user-email').value = user.email || '';
  document.getElementById('edit-user-password').value = '';

  const roleSelect = document.getElementById('edit-user-role');
  roleSelect.value = user.role || 'customer';

  const roleWarning = document.getElementById('edit-role-warning');
  if (parseInt(user.id, 10) === 1) {
    roleSelect.disabled = true;
    roleWarning.style.display = 'block';
  } else {
    roleSelect.disabled = false;
    roleWarning.style.display = 'none';
  }

  // Reset del toggle de contraseña a tipo password
  const passInput = document.getElementById('edit-user-password');
  passInput.type = 'password';
  const toggleBtn = document.getElementById('toggle-edit-user-pass');
  if (toggleBtn) {
    toggleBtn.innerHTML = '<i class="ri-eye-line"></i>';
  }

  document.getElementById('edit-user-modal').classList.add('active');
  document.getElementById('edit-user-name').focus();
}

function closeEditUserModal() {
  document.getElementById('edit-user-modal').classList.remove('active');
  document.getElementById('edit-user-form').reset();
  document.getElementById('edit-user-role').disabled = false;
}

// Toggle ver/ocultar contraseña en modal de edición
const toggleEditPassBtn = document.getElementById('toggle-edit-user-pass');
if (toggleEditPassBtn) {
  toggleEditPassBtn.addEventListener('click', () => {
    const passInput = document.getElementById('edit-user-password');
    const isPass = passInput.type === 'password';
    passInput.type = isPass ? 'text' : 'password';
    toggleEditPassBtn.innerHTML = isPass ? '<i class="ri-eye-off-line"></i>' : '<i class="ri-eye-line"></i>';
  });
}

// Cerrar modales con clic fuera y tecla Escape
document.addEventListener('DOMContentLoaded', () => {
  const userModal = document.getElementById('user-modal');
  const editModal = document.getElementById('edit-user-modal');

  [userModal, editModal].forEach(modal => {
    if (modal) {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) {
          if (modal === userModal) closeUserModal();
          if (modal === editModal) closeEditUserModal();
        }
      });
    }
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (userModal && userModal.classList.contains('active')) closeUserModal();
      if (editModal && editModal.classList.contains('active')) closeEditUserModal();
    }
  });
});

// SUBMIT: Crear Usuario
document.getElementById('create-user-form').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btn-save-user');
  btn.disabled = true;
  btn.textContent = 'Guardando...';

  const formData = new FormData(form);
  console.log('[Users] Enviando creación de usuario:', formData.get('email'), 'Rol:', formData.get('role'));

  fetch('api/create_user.php', {
    method: 'POST',
    body: formData,
  })
  .then(async res => {
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('[Users Error Raw Response]', text);
      throw new Error(`El servidor respondió con código ${res.status} (${res.statusText || 'Error'}).`);
    }
    return { ok: res.ok, status: res.status, data };
  })
  .then(({ ok, status, data }) => {
    btn.disabled = false;
    btn.textContent = 'Guardar Usuario';

    if (ok && data.success) {
      console.log('[Users] Usuario creado exitosamente:', data.data);
      showToast(data.message || 'Usuario creado correctamente.', 'success');
      closeUserModal();

      // Añadir fila a la tabla dinámicamente con badge según rol y botón editar
      const tbody = document.getElementById('users-table-body');
      const tr = document.createElement('tr');
      tr.id = `user-row-${data.data.id}`;
      
      const roleBadge = data.data.role === 'admin'
        ? '<span class="status-badge status-done" style="text-transform: uppercase; font-size: 0.7rem;">ADMIN</span>'
        : '<span class="status-badge" style="background: var(--primary-light); color: var(--primary); text-transform: uppercase; font-size: 0.7rem; font-weight: 700;">CUSTOMER</span>';

      const userJson = JSON.stringify({
        id: data.data.id,
        name: data.data.name,
        email: data.data.email,
        role: data.data.role
      });

      tr.innerHTML = `
        <td><strong>#${data.data.id}</strong></td>
        <td class="col-user-name" style="font-weight: 600;">${escapeHtml(data.data.name)}</td>
        <td class="col-user-email">${escapeHtml(data.data.email)}</td>
        <td class="col-user-role">${roleBadge}</td>
        <td style="color: var(--text-muted); font-size: 0.85rem;">Justo ahora</td>
        <td style="text-align: right; white-space: nowrap;">
          <button 
            type="button" 
            class="btn btn-outline btn-sm btn-edit-user" 
            onclick='openEditUserModal(${escapeAttr(userJson)})'
            title="Editar este usuario"
          >
            <i class="ri-edit-line"></i> Editar
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    } else {
      console.error('[Users Error]', data);
      showToast(data.message || `Error al crear usuario (Código ${status}).`, 'error');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.textContent = 'Guardar Usuario';
    console.error('[Users Network Error]', err);
    showToast(err.message || 'Error de conexión al crear usuario.', 'error');
  });
});

// SUBMIT: Editar Usuario
document.getElementById('edit-user-form').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('btn-update-user');
  btn.disabled = true;
  btn.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Actualizando...';

  // Habilitar temporalmente select si estaba deshabilitado para que FormData lo capture
  const roleSelect = document.getElementById('edit-user-role');
  const wasDisabled = roleSelect.disabled;
  if (wasDisabled) roleSelect.disabled = false;

  const formData = new FormData(form);
  if (wasDisabled) roleSelect.disabled = true;

  console.log('[Users] Enviando actualización de usuario ID:', formData.get('id'));

  fetch('api/edit_user.php', {
    method: 'POST',
    body: formData,
  })
  .then(async res => {
    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error('[Users Edit Error Raw]', text);
      throw new Error(`El servidor respondió con código ${res.status}.`);
    }
    return { ok: res.ok, status: res.status, data };
  })
  .then(({ ok, status, data }) => {
    btn.disabled = false;
    btn.innerHTML = '<i class="ri-save-line"></i> Guardar Cambios';

    if (ok && data.success) {
      console.log('[Users] Usuario actualizado:', data.data);
      showToast(data.message || 'Usuario actualizado correctamente.', 'success');
      closeEditUserModal();

      // Actualizar fila en la tabla en tiempo real
      const row = document.getElementById(`user-row-${data.data.id}`);
      if (row) {
        const nameCell = row.querySelector('.col-user-name');
        if (nameCell) nameCell.textContent = data.data.name;

        const emailCell = row.querySelector('.col-user-email');
        if (emailCell) emailCell.textContent = data.data.email;

        const roleCell = row.querySelector('.col-user-role');
        if (roleCell) {
          roleCell.innerHTML = data.data.role === 'admin'
            ? '<span class="status-badge status-done" style="text-transform: uppercase; font-size: 0.7rem;">ADMIN</span>'
            : '<span class="status-badge" style="background: var(--primary-light); color: var(--primary); text-transform: uppercase; font-size: 0.7rem; font-weight: 700;">CUSTOMER</span>';
        }

        const editBtn = row.querySelector('.btn-edit-user');
        if (editBtn) {
          const updatedJson = JSON.stringify({
            id: data.data.id,
            name: data.data.name,
            email: data.data.email,
            role: data.data.role
          });
          editBtn.setAttribute('onclick', `openEditUserModal(${escapeAttr(updatedJson)})`);
        }
      }

      // Si el usuario editado es el actual, refrescar nombre en el header
      const currentNameEls = document.querySelectorAll('.user-name-text, .dropdown-user-name');
      currentNameEls.forEach(el => {
        // Si el ID editado coincide con la inicial o el texto
        const avatarEl = document.querySelector('.user-avatar-circle');
        if (avatarEl && data.data.name) {
          avatarEl.textContent = data.data.name.charAt(0).toUpperCase();
        }
      });
    } else {
      console.error('[Users Edit Error]', data);
      showToast(data.message || `Error al actualizar usuario (Código ${status}).`, 'error');
    }
  })
  .catch(err => {
    btn.disabled = false;
    btn.innerHTML = '<i class="ri-save-line"></i> Guardar Cambios';
    console.error('[Users Edit Network Error]', err);
    showToast(err.message || 'Error de conexión al actualizar usuario.', 'error');
  });
});

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function escapeAttr(text) {
  return text.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
