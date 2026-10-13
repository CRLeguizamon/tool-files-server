<?php
/**
 * Panel de Logs de Conversión y Depuración del Sistema
 * Permite supervisar, copiar y descargar el registro detallado para análisis
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_admin();

$pdo = get_db();

// 1. Obtener últimas conversiones registradas en la base de datos
$stmt = $pdo->query("SELECT * FROM `media` ORDER BY `id` DESC LIMIT 50");
$conversions = $stmt->fetchAll();
$totalConversions = count($conversions);

// 2. Leer archivo debug.log
$logFile = LOGS_DIR . '/debug.log';
$rawLog = '';
$logSizeFormatted = '0 KB';
$totalLogLines = 0;

if (file_exists($logFile)) {
    $rawLog = (string) @file_get_contents($logFile);
    $bytes = filesize($logFile);
    if ($bytes >= 1048576) {
        $logSizeFormatted = round($bytes / 1048576, 2) . ' MB';
    } else {
        $logSizeFormatted = round($bytes / 1024, 1) . ' KB';
    }
    $totalLogLines = substr_count($rawLog, "\n");
}

function formatLogBytes(int $bytes): string {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

$pageTitle = 'Logs de Conversión';
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Logs de Conversión y Depuración</h1>
    <p class="page-desc">Supervisa la compresión, cambios de formato y copia los registros para revisarlos con tu asistente.</p>
  </div>

  <div style="display: flex; gap: 0.65rem; align-items: center; flex-wrap: wrap;">
    <button type="button" id="btn-copy-all-logs" class="btn btn-primary">
      <i class="ri-file-copy-2-line"></i> Copiar Logs para el Asistente
    </button>
    <a href="api/download_log.php" class="btn btn-outline" title="Descargar archivo debug.log">
      <i class="ri-download-2-line"></i> Descargar .log
    </a>
    <button type="button" id="btn-clear-log" class="btn btn-outline" style="color: var(--danger); border-color: #fecaca;" title="Vaciar archivo de log">
      <i class="ri-delete-bin-line"></i> Vaciar Log
    </button>
  </div>
</div>

<!-- Tarjetas de Estadísticas / KPIs -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon"><i class="ri-file-list-3-line"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalLogLines) ?></span>
      <span class="stat-label">Líneas en debug.log</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon"><i class="ri-hard-drive-2-line"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= $logSizeFormatted ?></span>
      <span class="stat-label">Tamaño del archivo de log</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon"><i class="ri-image-line"></i></div>
    <div class="stat-info">
      <span class="stat-value"><?= number_format($totalConversions) ?></span>
      <span class="stat-label">Conversiones registradas</span>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-icon"><i class="ri-cpu-line"></i></div>
    <div class="stat-info">
      <span class="stat-value" style="font-size: 1rem; color: #10b981;">
        <?= extension_loaded('imagick') ? 'Imagick Activo' : 'GD Nativo' ?>
      </span>
      <span class="stat-label">Motor de compresión</span>
    </div>
  </div>
</div>

<!-- Pestañas de Navegación de Logs -->
<div class="log-tabs">
  <button type="button" class="log-tab-btn active" data-tab="tab-conversions">
    <i class="ri-table-line"></i> Historial de Conversiones (<?= $totalConversions ?>)
  </button>
  <button type="button" class="log-tab-btn" data-tab="tab-raw">
    <i class="ri-terminal-box-line"></i> Consola debug.log (Raw)
  </button>
</div>

<!-- Contenido Pestaña 1: Tabla de Conversiones Recientes -->
<div id="tab-conversions" class="log-tab-content">
  <?php if (empty($conversions)): ?>
    <div class="card" style="text-align: center; padding: 3rem 1.5rem;">
      <i class="ri-inbox-line" style="font-size: 2.5rem; color: var(--text-muted); margin-bottom: 0.5rem; display: block;"></i>
      <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.35rem;">Aún no hay conversiones registradas</h3>
      <p style="color: var(--text-muted); margin-bottom: 1.25rem;">Sube imágenes en el panel principal para ver el registro detallado aquí.</p>
      <a href="index.php" class="btn btn-primary"><i class="ri-upload-cloud-line"></i> Ir a Subir Imágenes</a>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="media-table">
        <thead>
          <tr>
            <th>Archivo Original</th>
            <th>Nombre Generado</th>
            <th>Formato</th>
            <th>Peso Inicial</th>
            <th>Peso Optimizado</th>
            <th>Ahorro</th>
            <th>Dimensiones</th>
            <th>Fecha y Hora</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($conversions as $c): 
            $origBytes = (int) $c['original_size'];
            $finalBytes = (int) $c['file_size'];
            $savedBytes = max(0, $origBytes - $finalBytes);
            $pct = $origBytes > 0 ? round(($savedBytes / $origBytes) * 100, 1) : 0;
            $isJpgConverted = str_ends_with(strtolower($c['filename']), '.jpg') && !str_ends_with(strtolower($c['original_name']), '.jpg') && !str_ends_with(strtolower($c['original_name']), '.jpeg');
          ?>
            <tr>
              <td>
                <div style="font-weight: 600; color: var(--text-main);" title="<?= htmlspecialchars($c['original_name']) ?>">
                  <?= htmlspecialchars($c['original_name']) ?>
                </div>
              </td>
              <td>
                <code style="background: var(--bg-subtle); padding: 0.2rem 0.4rem; border-radius: var(--radius-sm); font-size: 0.85rem; color: var(--primary);">
                  <?= htmlspecialchars($c['filename']) ?>
                </code>
              </td>
              <td>
                <?php if ($isJpgConverted): ?>
                  <span class="status-badge" style="background: #e0e7ff; color: #3730a3; font-weight: 700;">
                    <i class="ri-arrow-right-line"></i> JPG
                  </span>
                <?php else: ?>
                  <span class="format-pill"><?= htmlspecialchars(strtoupper(pathinfo($c['filename'], PATHINFO_EXTENSION))) ?></span>
                <?php endif; ?>
              </td>
              <td style="color: var(--text-muted); font-size: 0.875rem;">
                <?= formatLogBytes($origBytes) ?>
              </td>
              <td>
                <strong style="color: <?= $finalBytes < 1048576 ? '#059669' : '#d97706' ?>;">
                  <?= formatLogBytes($finalBytes) ?>
                </strong>
              </td>
              <td>
                <?php if ($pct > 0): ?>
                  <span class="savings-tag">-<?= $pct ?>%</span>
                <?php else: ?>
                  <span style="color: var(--text-muted); font-size: 0.85rem;">0%</span>
                <?php endif; ?>
              </td>
              <td style="color: var(--text-muted); font-size: 0.85rem;">
                <?= $c['width'] ?> &times; <?= $c['height'] ?> px
              </td>
              <td style="color: var(--text-muted); font-size: 0.825rem; white-space: nowrap;">
                <?= date('d/m/Y H:i:s', strtotime($c['created_at'])) ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Contenido Pestaña 2: Consola Terminal Raw -->
<div id="tab-raw" class="log-tab-content" style="display: none;">
  <div class="log-terminal">
    <div class="terminal-header">
      <div class="terminal-dots">
        <span class="dot dot-red"></span>
        <span class="dot dot-yellow"></span>
        <span class="dot dot-green"></span>
      </div>
      <span class="terminal-title">logs/debug.log (<?= $totalLogLines ?> líneas &bull; <?= $logSizeFormatted ?>)</span>
      <button type="button" class="btn btn-sm btn-outline" id="btn-copy-terminal" style="color: #94a3b8; border-color: #475569;">
        <i class="ri-file-copy-line"></i> Copiar Contenido
      </button>
    </div>
    <pre id="raw-log-content" class="terminal-body"><?= htmlspecialchars($rawLog !== '' ? $rawLog : "No hay registros disponibles en logs/debug.log todavía.\nRealiza alguna subida de imágenes para ver las trazas aquí.") ?></pre>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // 1. Manejo de Pestañas
  const tabButtons = document.querySelectorAll('.log-tab-btn');
  const tabContents = document.querySelectorAll('.log-tab-content');

  tabButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      tabButtons.forEach(b => b.classList.remove('active'));
      tabContents.forEach(c => c.style.display = 'none');

      btn.classList.add('active');
      const targetId = btn.getAttribute('data-tab');
      const targetContent = document.getElementById(targetId);
      if (targetContent) {
        targetContent.style.display = 'block';
      }
    });
  });

  // 2. Función para Copiar Texto al Portapapeles
  function copyTextToClipboard(text, successMsg) {
    if (!text || text.trim() === '') {
      showToast('No hay registros para copiar.', 'info');
      return;
    }

    navigator.clipboard.writeText(text).then(() => {
      showToast(successMsg, 'success');
      console.log('[Logs] Registros copiados al portapapeles exitosamente.');
    }).catch(err => {
      // Fallback
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        showToast(successMsg, 'success');
      } catch (e) {
        showToast('No se pudo copiar automáticamente. Por favor selecciónalo manualmente.', 'error');
      }
      document.body.removeChild(ta);
    });
  }

  // 3. Botón Principal: Copiar Logs para el Asistente
  const btnCopyAll = document.getElementById('btn-copy-all-logs');
  const rawLogEl = document.getElementById('raw-log-content');

  if (btnCopyAll && rawLogEl) {
    btnCopyAll.addEventListener('click', () => {
      const logText = rawLogEl.textContent;
      copyTextToClipboard(logText, '¡Logs copiados! Ya puedes pegarlos en el chat con tu asistente.');
    });
  }

  // 4. Botón Secundario de la Consola Terminal
  const btnCopyTerminal = document.getElementById('btn-copy-terminal');
  if (btnCopyTerminal && rawLogEl) {
    btnCopyTerminal.addEventListener('click', () => {
      const logText = rawLogEl.textContent;
      copyTextToClipboard(logText, 'Contenido de la consola copiado.');
    });
  }

  // 5. Botón Vaciar Log
  const btnClearLog = document.getElementById('btn-clear-log');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  if (btnClearLog) {
    btnClearLog.addEventListener('click', async () => {
      if (!confirm('¿Estás seguro de que deseas vaciar el archivo de logs debug.log? Esta acción no se puede deshacer.')) {
        return;
      }

      try {
        const formData = new FormData();
        formData.append('csrf_token', csrfToken);

        const res = await fetch('api/clear_logs.php', {
          method: 'POST',
          body: formData,
        });
        const data = await res.json();

        if (res.ok && data.success) {
          showToast(data.message, 'success');
          setTimeout(() => {
            window.location.reload();
          }, 800);
        } else {
          showToast(data.message || 'Error al vaciar logs.', 'error');
        }
      } catch (err) {
        console.error('[Logs Error]', err);
        showToast('Error de conexión al vaciar los logs.', 'error');
      }
    });
  }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
