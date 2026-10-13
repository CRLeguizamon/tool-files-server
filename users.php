<?php
/**
 * Gestión de Administradores
 * Permite listar y crear nuevos usuarios con rol 'admin'
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
    <h1 class="page-title">Gestión de Usuarios</h1>
    <p class="page-desc">Usuarios autorizados para acceder y optimizar imágenes en la plataforma.</p>
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
        </tr>
      </thead>
      <tbody id="users-table-body">
        <?php foreach ($users as $u): ?>
          <?php $isAdmin = ($u['role'] === 'admin'); ?>
          <tr id="user-row-<?= $u['id'] ?>">
            <td><strong>#<?= $u['id'] ?></strong></td>
            <td style="font-weight: 600;"><?= htmlspecialchars($u['name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <?php if ($isAdmin): ?>
                <span class="status-badge status-done" style="text-transform: uppercase; font-size: 0.7rem;">ADMIN</span>
              <?php else: ?>
                <span class="status-badge" style="background: var(--primary-light); color: var(--primary); text-transform: uppercase; font-size: 0.7rem; font-weight: 700;">CUSTOMER</span>
              <?php endif; ?>
            </td>
            <td style="color: var(--text-muted); font-size: 0.85rem;">
              <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal para Crear Usuario -->
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
          <option value="customer" selected>Cliente (Customer — Puede subir y ver medios, sin acceso a usuarios)</option>
          <option value="admin">Administrador (Admin — Acceso total al sistema y gestión de usuarios)</option>
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

<script>
console.log('[Users] Vista de usuarios cargada.');

function openUserModal() {
  console.log('[Modal] Abriendo formulario de nuevo usuario');
  document.getElementById('user-modal').classList.add('active');
  document.getElementById('user-name').focus();
}

function closeUserModal() {
  document.getElementById('user-modal').classList.remove('active');
  document.getElementById('create-user-form').reset();
}

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

      // Añadir fila a la tabla dinámicamente con badge según rol
      const tbody = document.getElementById('users-table-body');
      const tr = document.createElement('tr');
      tr.id = `user-row-${data.data.id}`;
      
      const roleBadge = data.data.role === 'admin'
        ? '<span class="status-badge status-done" style="text-transform: uppercase; font-size: 0.7rem;">ADMIN</span>'
        : '<span class="status-badge" style="background: var(--primary-light); color: var(--primary); text-transform: uppercase; font-size: 0.7rem; font-weight: 700;">CUSTOMER</span>';

      tr.innerHTML = `
        <td><strong>#${data.data.id}</strong></td>
        <td style="font-weight: 600;">${escapeHtml(data.data.name)}</td>
        <td>${escapeHtml(data.data.email)}</td>
        <td>${roleBadge}</td>
        <td style="color: var(--text-muted); font-size: 0.85rem;">Justo ahora</td>
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

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
