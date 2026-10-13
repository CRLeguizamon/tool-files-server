<?php
/**
 * Configuración del Servidor SMTP y Diagnóstico de Envío
 * Módulo administrativo exclusivo para administradores
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/Mailer.php';

require_login();
require_admin();

$pageTitle = 'Configuración SMTP';
$smtpConfig = get_smtp_config();
$adminUser = current_user();

require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title"><i class="ri-mail-settings-line"></i> Servidor SMTP y Correos</h1>
    <p class="page-desc">Configura el servidor de correo saliente para el envío de credenciales, avisos y restablecimiento de contraseña.</p>
  </div>
  <div>
    <span class="user-badge" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; background: var(--surface); border: 1px solid var(--border);">
      <i class="ri-shield-keyhole-line" style="color: var(--primary);"></i> Administrador
    </span>
  </div>
</div>

<div class="smtp-grid">

  <!-- Columna Izquierda: Formulario de Parámetros SMTP -->
  <div class="card">
    <div class="card-header-flex">
      <div class="brand-icon" style="background: var(--primary-light); color: var(--primary);">
        <i class="ri-server-line"></i>
      </div>
      <div>
        <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0;">Parámetros del Servidor</h2>
        <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Credenciales de conexión directa por socket (Hostinger / cPanel / Gmail).</p>
      </div>
    </div>

    <form id="smtp-config-form" style="margin-top: 1.25rem;">
      <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label" for="smtp-host">Servidor SMTP (Host)</label>
          <input 
            type="text" 
            id="smtp-host" 
            name="host" 
            class="form-input" 
            value="<?= htmlspecialchars($smtpConfig['host'] ?? '') ?>" 
            placeholder="ej. smtp.hostinger.com" 
            required
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="smtp-port">Puerto</label>
          <input 
            type="number" 
            id="smtp-port" 
            name="port" 
            class="form-input" 
            value="<?= htmlspecialchars((string) ($smtpConfig['port'] ?? 465)) ?>" 
            placeholder="465" 
            required 
            min="1" 
            max="65535"
          >
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label" for="smtp-encryption">Cifrado de Conexión</label>
          <select id="smtp-encryption" name="encryption" class="form-input">
            <option value="ssl" <?= ($smtpConfig['encryption'] ?? 'ssl') === 'ssl' ? 'selected' : '' ?>>SSL (Recomendado - Puerto 465)</option>
            <option value="tls" <?= ($smtpConfig['encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>STARTTLS (Puerto 587)</option>
            <option value="none" <?= ($smtpConfig['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>Sin Cifrado (Puerto 25)</option>
          </select>
        </div>

        <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end; padding-bottom: 0.5rem;">
          <label class="checkbox-label" style="cursor: pointer; user-select: none;">
            <input 
              type="checkbox" 
              id="smtp-auth" 
              name="auth" 
              value="1" 
              <?= !empty($smtpConfig['auth']) ? 'checked' : '' ?>
            >
            <span>Requiere Autenticación (AUTH LOGIN)</span>
          </label>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="smtp-username">Usuario SMTP (Email de autenticación)</label>
        <input 
          type="text" 
          id="smtp-username" 
          name="username" 
          class="form-input" 
          value="<?= htmlspecialchars($smtpConfig['username'] ?? '') ?>" 
          placeholder="notificaciones@tudominio.com"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="smtp-password">Contraseña SMTP</label>
        <div style="position: relative;">
          <input 
            type="password" 
            id="smtp-password" 
            name="password" 
            class="form-input" 
            placeholder="<?= !empty($smtpConfig['password']) ? '•••••••• (Guardada - Deja en blanco si no deseas cambiar)' : 'Contraseña de la cuenta de correo' ?>"
            autocomplete="new-password"
            style="padding-right: 2.75rem;"
          >
          <button 
            type="button" 
            id="toggle-smtp-pass" 
            class="btn-icon-addon" 
            title="Mostrar / Ocultar contraseña"
          >
            <i class="ri-eye-line"></i>
          </button>
        </div>
      </div>

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
        <div class="form-group">
          <label class="form-label" for="smtp-from-email">Email del Remitente (From)</label>
          <input 
            type="email" 
            id="smtp-from-email" 
            name="from_email" 
            class="form-input" 
            value="<?= htmlspecialchars($smtpConfig['from_email'] ?? '') ?>" 
            placeholder="soporte@tudominio.com"
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="smtp-from-name">Nombre del Remitente</label>
          <input 
            type="text" 
            id="smtp-from-name" 
            name="from_name" 
            class="form-input" 
            value="<?= htmlspecialchars($smtpConfig['from_name'] ?? APP_NAME) ?>" 
            placeholder="<?= APP_NAME ?>"
          >
        </div>
      </div>

      <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
        <button type="submit" id="btn-save-smtp" class="btn btn-primary" style="flex: 1;">
          <i class="ri-save-3-line"></i> Guardar Configuración
        </button>
      </div>
    </form>
  </div>

  <!-- Columna Derecha: Diagnóstico y Prueba en Vivo -->
  <div style="display: flex; flex-direction: column; gap: 1.5rem;">

    <!-- Tarjeta de Prueba de Envío -->
    <div class="card">
      <div class="card-header-flex">
        <div class="brand-icon" style="background: var(--success-light); color: var(--success);">
          <i class="ri-send-plane-2-line"></i>
        </div>
        <div>
          <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0;">Probar Envío en Tiempo Real</h2>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Dispara una prueba de conexión por sockets hacia el destinatario que elijas.</p>
        </div>
      </div>

      <div style="margin-top: 1.25rem;">
        <div class="form-group">
          <label class="form-label" for="test-recipient-email">Correo de Destino de la Prueba</label>
          <div style="display: flex; gap: 0.5rem;">
            <input 
              type="email" 
              id="test-recipient-email" 
              class="form-input" 
              value="<?= htmlspecialchars($adminUser['email'] ?? '') ?>" 
              placeholder="tu-correo@ejemplo.com" 
              required
            >
            <button type="button" id="btn-run-smtp-test" class="btn btn-success" style="white-space: nowrap;">
              <i class="ri-play-circle-line"></i> Probar Envío
            </button>
          </div>
          <span style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.35rem; display: block;">
            Puedes probar sin necesidad de guardar primero: se usarán los valores actualmente escritos en el formulario.
          </span>
        </div>

        <!-- Consola de Resultados y Transcripción de Comandos -->
        <div id="smtp-test-result-box" style="display: none; margin-top: 1rem;">
          <div id="smtp-status-badge" style="margin-bottom: 0.75rem; padding: 0.6rem 0.9rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600;"></div>
          
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
            <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">
              <i class="ri-terminal-line"></i> Transcripción del Protocolo SMTP
            </span>
            <button type="button" class="btn-text-sm" onclick="clearSmtpTerminal()">Limpiar</button>
          </div>

          <div id="smtp-terminal" class="smtp-terminal-window"></div>
        </div>
      </div>
    </div>

    <!-- Tarjeta de Plantillas de Correo Integradas -->
    <div class="card">
      <div class="card-header-flex">
        <div class="brand-icon" style="background: var(--surface); color: var(--primary); border: 1px solid var(--border);">
          <i class="ri-layout-masonry-line"></i>
        </div>
        <div>
          <h2 style="font-size: 1.15rem; font-weight: 700; margin: 0;">Plantillas de Correo</h2>
          <p style="font-size: 0.8rem; color: var(--text-muted); margin: 0;">Plantillas HTML responsive creadas con diseño minimalista.</p>
        </div>
      </div>

      <div style="margin-top: 1rem;">
        <div class="template-tabs">
          <button type="button" class="template-tab-btn active" data-target="tpl-created">
            <i class="ri-user-add-line"></i> Nueva Cuenta
          </button>
          <button type="button" class="template-tab-btn" data-target="tpl-reset">
            <i class="ri-key-2-line"></i> Reset Clave
          </button>
          <button type="button" class="template-tab-btn" data-target="tpl-test">
            <i class="ri-mail-check-line"></i> Prueba SMTP
          </button>
        </div>

        <div class="template-preview-container">
          <div id="tpl-created" class="template-panel active">
            <div class="template-card-preview">
              <div class="tpl-badge">Asunto: Bienvenido a <?= APP_NAME ?> - Tus Credenciales de Acceso</div>
              <p>Hola <strong>Juan Pérez</strong>,</p>
              <p>Se ha creado una cuenta para ti en la plataforma <strong><?= $_SERVER['HTTP_HOST'] ?? 'devcristian.com' ?></strong>. A continuación encontrarás tus credenciales de acceso:</p>
              <div class="tpl-code-box">
                <div><strong>Usuario:</strong> <code>juan@ejemplo.com</code></div>
                <div><strong>Contraseña temporal:</strong> <code>Temp#2026Pass</code></div>
              </div>
              <div style="text-align: center; margin: 1.25rem 0;">
                <span class="btn btn-primary" style="pointer-events: none; padding: 0.5rem 1.25rem; font-size: 0.85rem;">Iniciar Sesión &rarr;</span>
              </div>
            </div>
          </div>

          <div id="tpl-reset" class="template-panel">
            <div class="template-card-preview">
              <div class="tpl-badge">Asunto: Restablecimiento de Contraseña - <?= APP_NAME ?></div>
              <p>Hola <strong>Juan Pérez</strong>,</p>
              <p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong><?= $_SERVER['HTTP_HOST'] ?? 'devcristian.com' ?></strong>.</p>
              <p>Haz clic en el botón siguiente para definir tu nueva contraseña (enlace seguro de un solo uso):</p>
              <div style="text-align: center; margin: 1.25rem 0;">
                <span class="btn btn-primary" style="pointer-events: none; padding: 0.5rem 1.25rem; font-size: 0.85rem;">Crear Nueva Contraseña &rarr;</span>
              </div>
              <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 1rem;">Si no solicitaste este cambio, puedes ignorar el correo.</p>
            </div>
          </div>

          <div id="tpl-test" class="template-panel">
            <div class="template-card-preview">
              <div class="tpl-badge">Asunto: Prueba de Conexión SMTP - <?= APP_NAME ?></div>
              <p>Hola,</p>
              <p>Este es un correo de prueba generado automáticamente para verificar que el servidor <strong>SMTP</strong> está funcionando correctamente.</p>
              <div class="tpl-code-box">
                <div><strong>Estado:</strong> Conexión y autenticación exitosas</div>
                <div><strong>Servidor:</strong> <?= $_SERVER['HTTP_HOST'] ?? 'devcristian.com' ?></div>
              </div>
              <div style="text-align: center; margin: 1.25rem 0;">
                <span class="btn btn-primary" style="pointer-events: none; padding: 0.5rem 1.25rem; font-size: 0.85rem;">Ir a la Plataforma &rarr;</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // 1. Mostrar/Ocultar Contraseña SMTP
  const togglePassBtn = document.getElementById('toggle-smtp-pass');
  const passInput = document.getElementById('smtp-password');
  if (togglePassBtn && passInput) {
    togglePassBtn.addEventListener('click', () => {
      const isPassword = passInput.type === 'password';
      passInput.type = isPassword ? 'text' : 'password';
      togglePassBtn.innerHTML = isPassword ? '<i class="ri-eye-off-line"></i>' : '<i class="ri-eye-line"></i>';
    });
  }

  // 2. Guardar Configuración SMTP
  const configForm = document.getElementById('smtp-config-form');
  const btnSave = document.getElementById('btn-save-smtp');

  if (configForm) {
    configForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      console.log('[SMTP] Guardando configuración...');

      btnSave.disabled = true;
      btnSave.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Guardando...';

      const formData = new FormData(configForm);
      formData.append('csrf_token', csrfToken);

      try {
        const res = await fetch('api/save_smtp.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (res.ok && data.success) {
          showToast(data.message || 'Configuración guardada correctamente.', 'success');
        } else {
          showToast(data.message || 'Error al guardar configuración.', 'error');
        }
      } catch (err) {
        console.error('[SMTP Save Error]', err);
        showToast('Error de red al conectar con el servidor.', 'error');
      } finally {
        btnSave.disabled = false;
        btnSave.innerHTML = '<i class="ri-save-3-line"></i> Guardar Configuración';
      }
    });
  }

  // 3. Probar Envío SMTP en Vivo
  const btnTest = document.getElementById('btn-run-smtp-test');
  const testEmailInput = document.getElementById('test-recipient-email');
  const resultBox = document.getElementById('smtp-test-result-box');
  const statusBadge = document.getElementById('smtp-status-badge');
  const terminal = document.getElementById('smtp-terminal');

  if (btnTest) {
    btnTest.addEventListener('click', async () => {
      const recipient = testEmailInput.value.trim();
      if (!recipient) {
        showToast('Por favor escribe un correo para recibir la prueba.', 'error');
        testEmailInput.focus();
        return;
      }

      console.log('[SMTP Test] Iniciando prueba hacia:', recipient);

      btnTest.disabled = true;
      btnTest.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Probando...';
      resultBox.style.display = 'block';
      statusBadge.style.display = 'block';
      statusBadge.style.backgroundColor = '#f1f5f9';
      statusBadge.style.color = '#475569';
      statusBadge.innerHTML = '<i class="ri-loader-line ri-spin"></i> Estableciendo conexión socket y negociando protocolo SMTP...';

      terminal.innerHTML = '<div class="terminal-line info"><i class="ri-arrow-right-s-line"></i> Conectando con el servidor SMTP...</div>';

      const formData = new FormData(configForm);
      formData.append('test_email', recipient);
      formData.append('csrf_token', csrfToken);

      try {
        const res = await fetch('api/test_smtp.php', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
          statusBadge.style.backgroundColor = 'var(--success-light, #dcfce7)';
          statusBadge.style.color = 'var(--success, #15803d)';
          statusBadge.innerHTML = `<i class="ri-checkbox-circle-fill"></i> ${data.message}`;
          showToast('Prueba de correo exitosa. Revisa tu bandeja de entrada.', 'success');
        } else {
          statusBadge.style.backgroundColor = 'var(--danger-light, #fee2e2)';
          statusBadge.style.color = 'var(--danger, #b91c1c)';
          statusBadge.innerHTML = `<i class="ri-error-warning-fill"></i> Fallo en el envío: ${data.message}`;
          showToast('Fallo en la prueba SMTP: ' + data.message, 'error');
        }

        // Renderizar transcripción detallada
        renderTranscript(data.transcript || []);

      } catch (err) {
        console.error('[SMTP Test Error]', err);
        statusBadge.style.backgroundColor = 'var(--danger-light, #fee2e2)';
        statusBadge.style.color = 'var(--danger, #b91c1c)';
        statusBadge.innerHTML = '<i class="ri-error-warning-fill"></i> Error de conexión con el script de prueba.';
        showToast('Error de red al ejecutar prueba SMTP.', 'error');
      } finally {
        btnTest.disabled = false;
        btnTest.innerHTML = '<i class="ri-play-circle-line"></i> Probar Envío';
      }
    });
  }

  function renderTranscript(steps) {
    if (!steps || steps.length === 0) {
      terminal.innerHTML += '<div class="terminal-line muted">No se registraron pasos de comunicación.</div>';
      return;
    }

    let html = '';
    steps.forEach(step => {
      const dir = step.direction;
      let badgeClass = 'sys';
      let icon = 'ri-information-line';

      if (dir === 'CLIENT') {
        badgeClass = 'client';
        icon = 'ri-arrow-right-line';
      } else if (dir === 'SERVER') {
        badgeClass = 'server';
        icon = 'ri-arrow-left-line';
      } else if (dir === 'ERR') {
        badgeClass = 'err';
        icon = 'ri-close-circle-line';
      }

      html += `
        <div class="terminal-line ${badgeClass}">
          <span class="term-badge"><i class="${icon}"></i> ${dir}</span>
          <span class="term-msg">${escapeHtml(step.message)}</span>
        </div>
      `;
    });

    terminal.innerHTML = html;
    terminal.scrollTop = terminal.scrollHeight;
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  window.clearSmtpTerminal = function() {
    terminal.innerHTML = '<div class="terminal-line muted">Consola vacía.</div>';
  };

  // 4. Pestañas de Vista Previa de Plantillas
  const tabBtns = document.querySelectorAll('.template-tab-btn');
  const panels = document.querySelectorAll('.template-panel');

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const targetId = btn.getAttribute('data-target');
      tabBtns.forEach(b => b.classList.remove('active'));
      panels.forEach(p => p.classList.remove('active'));

      btn.classList.add('active');
      const targetPanel = document.getElementById(targetId);
      if (targetPanel) {
        targetPanel.classList.add('active');
      }
    });
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
