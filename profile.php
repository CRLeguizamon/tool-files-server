<?php
/**
 * Pantalla de Mi Perfil y Cambio de Contraseña
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$currentUser = current_user();
$userId = (int) ($currentUser['id'] ?? 0);

$pdo = get_db();
$stmt = $pdo->prepare("SELECT `id`, `name`, `email`, `role`, `created_at` FROM `users` WHERE `id` = ? LIMIT 1");
$stmt->execute([$userId]);
$userData = $stmt->fetch() ?: $currentUser;

$pageTitle = 'Mi Perfil';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Mi Perfil</h1>
    <p class="page-desc">Administra tu información personal y credenciales de acceso al sistema.</p>
  </div>
  <div>
    <span class="user-badge" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
      <i class="ri-shield-user-line"></i> Rol: <strong><?= is_admin() ? 'Administrador' : 'Usuario' ?></strong>
    </span>
  </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; max-width: 1000px; margin: 0 auto;">

  <!-- Tarjeta 1: Información Personal -->
  <div class="card">
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border);">
      <div class="brand-icon" style="background: var(--primary-light); color: var(--primary);">
        <i class="ri-user-smile-line"></i>
      </div>
      <div>
        <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Datos Personales</h2>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Actualiza tu nombre y correo de inicio de sesión.</p>
      </div>
    </div>

    <form id="profile-info-form">
      <div class="form-group">
        <label class="form-label" for="profile-name">Nombre Completo</label>
        <input 
          type="text" 
          id="profile-name" 
          name="name" 
          class="form-input" 
          value="<?= htmlspecialchars($userData['name'] ?? '') ?>" 
          required 
          placeholder="Tu nombre completo"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="profile-email">Correo Electrónico</label>
        <input 
          type="email" 
          id="profile-email" 
          name="email" 
          class="form-input" 
          value="<?= htmlspecialchars($userData['email'] ?? '') ?>" 
          required 
          placeholder="nombre@ejemplo.com"
        >
        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; display: block;">
          Este correo se utiliza para iniciar sesión en la plataforma.
        </span>
      </div>

      <div class="form-group" style="margin-bottom: 1.5rem;">
        <label class="form-label" style="color: var(--text-muted);">Fecha de Registro</label>
        <div style="font-size: 0.875rem; color: var(--text-main); font-weight: 500;">
          <?= !empty($userData['created_at']) ? date('d/m/Y H:i', strtotime($userData['created_at'])) : 'No registrada' ?>
        </div>
      </div>

      <button type="submit" id="btn-save-profile" class="btn btn-primary" style="width: 100%;">
        <i class="ri-save-line"></i> Guardar Información
      </button>
    </form>
  </div>

  <!-- Tarjeta 2: Cambio de Contraseña -->
  <div class="card">
    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; padding-bottom: 0.85rem; border-bottom: 1px solid var(--border);">
      <div class="brand-icon" style="background: var(--danger-light); color: var(--danger);">
        <i class="ri-lock-password-line"></i>
      </div>
      <div>
        <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Seguridad y Contraseña</h2>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Modifica tu clave de acceso periódicamente.</p>
      </div>
    </div>

    <form id="profile-password-form">
      <div class="form-group">
        <label class="form-label" for="current-password">Contraseña Actual</label>
        <div style="position: relative;">
          <input 
            type="password" 
            id="current-password" 
            name="current_password" 
            class="form-input" 
            required 
            placeholder="••••••••"
            autocomplete="current-password"
            style="padding-right: 2.75rem;"
          >
          <button 
            type="button" 
            id="toggle-current-pass" 
            class="btn-icon-addon" 
            title="Mostrar / Ocultar contraseña actual"
          >
            <i class="ri-eye-line"></i>
          </button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="new-password">Nueva Contraseña</label>
        <div style="position: relative;">
          <input 
            type="password" 
            id="new-password" 
            name="new_password" 
            class="form-input" 
            required 
            minlength="4"
            placeholder="Mínimo 4 caracteres"
            autocomplete="new-password"
            style="padding-right: 2.75rem;"
          >
          <button 
            type="button" 
            id="toggle-new-pass" 
            class="btn-icon-addon" 
            title="Mostrar / Ocultar nueva contraseña"
          >
            <i class="ri-eye-line"></i>
          </button>
        </div>
        <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
          Haz clic en el ícono del ojo para verificar los caracteres antes de guardar.
        </span>
      </div>

      <button type="submit" id="btn-save-password" class="btn btn-outline" style="width: 100%; border-color: var(--primary); color: var(--primary); margin-top: 0.5rem;">
        <i class="ri-key-line"></i> Actualizar Contraseña
      </button>
    </form>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // 1. Guardar Información Personal
  const infoForm = document.getElementById('profile-info-form');
  const btnSaveProfile = document.getElementById('btn-save-profile');

  if (infoForm) {
    infoForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      console.log('[Perfil] Guardando datos personales...');

      const name = document.getElementById('profile-name').value.trim();
      const email = document.getElementById('profile-email').value.trim();

      if (!name || !email) {
        showToast('Por favor completa todos los campos requeridos.', 'error');
        return;
      }

      btnSaveProfile.disabled = true;
      btnSaveProfile.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Guardando...';

      const formData = new FormData();
      formData.append('name', name);
      formData.append('email', email);
      formData.append('csrf_token', csrfToken);

      try {
        const res = await fetch('api/update_profile.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (res.ok && data.success) {
          showToast(data.message || 'Información actualizada exitosamente.', 'success');
          // Actualizar nombre visible en el menú desplegable superior
          const nameEls = document.querySelectorAll('.user-name-text, .dropdown-user-name');
          nameEls.forEach(el => el.textContent = name);
          const emailEls = document.querySelectorAll('.dropdown-user-email');
          emailEls.forEach(el => el.textContent = email);
          const avatarEls = document.querySelectorAll('.user-avatar-circle');
          avatarEls.forEach(el => el.textContent = name.charAt(0).toUpperCase());
        } else {
          showToast(data.message || 'Error al guardar los datos.', 'error');
        }
      } catch (err) {
        console.error('[Perfil Error]', err);
        showToast('Error de conexión con el servidor.', 'error');
      } finally {
        btnSaveProfile.disabled = false;
        btnSaveProfile.innerHTML = '<i class="ri-save-line"></i> Guardar Información';
      }
    });
  }

  // Helper para alternar visibilidad de contraseña (ojo)
  function setupPassToggle(toggleBtnId, inputId) {
    const btn = document.getElementById(toggleBtnId);
    const input = document.getElementById(inputId);
    if (btn && input) {
      btn.addEventListener('click', () => {
        const isPass = input.type === 'password';
        input.type = isPass ? 'text' : 'password';
        btn.innerHTML = isPass ? '<i class="ri-eye-off-line"></i>' : '<i class="ri-eye-line"></i>';
      });
    }
  }

  setupPassToggle('toggle-current-pass', 'current-password');
  setupPassToggle('toggle-new-pass', 'new-password');

  // 2. Cambiar Contraseña
  const passForm = document.getElementById('profile-password-form');
  const btnSavePassword = document.getElementById('btn-save-password');

  if (passForm) {
    passForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      console.log('[Perfil] Actualizando contraseña...');

      const currentPass = document.getElementById('current-password').value;
      const newPass = document.getElementById('new-password').value;

      if (!currentPass || !newPass) {
        showToast('Por favor completa la contraseña actual y la nueva contraseña.', 'error');
        return;
      }

      if (newPass.length < 4) {
        showToast('La nueva contraseña debe tener al menos 4 caracteres.', 'error');
        return;
      }

      const name = document.getElementById('profile-name').value.trim();
      const email = document.getElementById('profile-email').value.trim();

      btnSavePassword.disabled = true;
      btnSavePassword.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Actualizando...';

      const formData = new FormData();
      formData.append('name', name);
      formData.append('email', email);
      formData.append('current_password', currentPass);
      formData.append('new_password', newPass);
      formData.append('csrf_token', csrfToken);

      try {
        const res = await fetch('api/update_profile.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (res.ok && data.success) {
          showToast(data.message || 'Contraseña actualizada con éxito.', 'success');
          passForm.reset();

          // Restaurar tipo password en los inputs
          const curInput = document.getElementById('current-password');
          const newInput = document.getElementById('new-password');
          if (curInput) curInput.type = 'password';
          if (newInput) newInput.type = 'password';
          const curBtn = document.getElementById('toggle-current-pass');
          const newBtn = document.getElementById('toggle-new-pass');
          if (curBtn) curBtn.innerHTML = '<i class="ri-eye-line"></i>';
          if (newBtn) newBtn.innerHTML = '<i class="ri-eye-line"></i>';
        } else {
          showToast(data.message || 'Error al actualizar la contraseña.', 'error');
        }
      } catch (err) {
        console.error('[Password Update Error]', err);
        showToast('Error de red al actualizar contraseña.', 'error');
      } finally {
        btnSavePassword.disabled = false;
        btnSavePassword.innerHTML = '<i class="ri-key-line"></i> Actualizar Contraseña';
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
