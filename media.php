<?php
/**
 * Sección de Medios: Tabla de Imágenes Optimizadas, Copiado de URL y Exportación a CSV
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = get_db();
$stmt = $pdo->query("SELECT * FROM `media` ORDER BY `id` DESC");
$mediaItems = $stmt->fetchAll();

$totalItems = count($mediaItems);
$totalOriginalBytes = array_sum(array_column($mediaItems, 'original_size'));
$totalFinalBytes = array_sum(array_column($mediaItems, 'file_size'));
$totalSavedBytes = max(0, $totalOriginalBytes - $totalFinalBytes);
$globalSavedPct = $totalOriginalBytes > 0 ? round(($totalSavedBytes / $totalOriginalBytes) * 100, 1) : 0;

function formatBytes(int $bytes): string {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

$pageTitle = 'Medios';
$extraScripts = ['assets/js/media.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Biblioteca de Medios</h1>
    <p class="page-desc">
      Total: <strong><?= $totalItems ?></strong> imágenes 
      <?php if ($totalItems > 0): ?>
        &bull; Espacio optimizado: <strong><?= formatBytes((int) $totalFinalBytes) ?></strong>
        <?php if ($globalSavedPct > 0): ?>
          <span class="savings-tag">Ahorro global: -<?= $globalSavedPct ?>%</span>
        <?php endif; ?>
      <?php endif; ?>
    </p>
  </div>

  <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
    <button type="button" id="btn-export-csv" class="btn btn-outline" <?= $totalItems === 0 ? 'disabled' : '' ?>>
      <i class="ri-file-excel-2-line"></i> Exportar en CSV
    </button>
    <a href="index.php" class="btn btn-primary">
      <i class="ri-upload-cloud-line"></i> Subir más
    </a>
  </div>
</div>

<?php if ($totalItems === 0): ?>
  <div class="card" style="text-align: center; padding: 4rem 2rem;">
    <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--text-muted);">
      <i class="ri-image-add-line"></i>
    </div>
    <h2 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem;">Aún no hay imágenes procesadas</h2>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Comienza subiendo tus archivos JPG, PNG, WEBP o AVIF para optimizarlos.</p>
    <a href="index.php" class="btn btn-primary">
      <i class="ri-upload-cloud-line"></i> Subir mi primer lote
    </a>
  </div>
<?php else: ?>

  <!-- Barra de Filtros y Búsqueda -->
  <div class="card" style="padding: 1rem 1.25rem; margin-bottom: 1.25rem;">
    <div style="display: flex; gap: 1rem; align-items: center; justify-content: space-between; flex-wrap: wrap;">
      <div style="flex: 1; max-width: 420px; position: relative;">
        <input 
          type="text" 
          id="media-search" 
          class="form-input" 
          placeholder="Buscar por nombre de archivo..."
          style="padding-left: 0.85rem;"
        >
      </div>
      <div style="color: var(--text-muted); font-size: 0.875rem;">
        Mostrando <?= $totalItems ?> imágenes
      </div>
    </div>
  </div>

  <!-- Tabla de Medios -->
  <div class="table-responsive">
    <table class="media-table">
      <thead>
        <tr>
          <th style="width: 70px;">Miniatura</th>
          <th>Nombre del Archivo</th>
          <th>Dimensiones</th>
          <th>Peso / Ahorro</th>
          <th>Fecha</th>
          <th style="min-width: 300px;">URL Directa</th>
          <th style="text-align: right; width: 140px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($mediaItems as $item): ?>
          <?php 
            $fullUrl = base_url($item['file_path']);
            $thumbUrl = base_url($item['thumb_path']);
            $savedPercent = $item['original_size'] > 0
                ? round((($item['original_size'] - $item['file_size']) / $item['original_size']) * 100, 1)
                : 0;
            $format = strtoupper(pathinfo($item['filename'], PATHINFO_EXTENSION));
          ?>
          <tr 
            id="media-row-<?= $item['id'] ?>" 
            class="media-row" 
            data-search="<?= htmlspecialchars($item['filename'] . ' ' . $item['original_name']) ?>"
          >
            <td>
              <img 
                src="<?= htmlspecialchars($thumbUrl) ?>" 
                alt="Thumb" 
                class="table-thumb view-trigger" 
                data-full="<?= htmlspecialchars($fullUrl) ?>" 
                data-title="<?= htmlspecialchars($item['original_name']) ?>"
                loading="lazy"
                title="Clic para ampliar"
              >
            </td>
            <td>
              <div style="font-weight: 600; color: var(--text-main); font-size: 0.9rem;" title="<?= htmlspecialchars($item['original_name']) ?>">
                <?= htmlspecialchars($item['original_name']) ?>
              </div>
              <div style="font-size: 0.775rem; color: var(--text-muted); font-family: monospace;">
                <?= htmlspecialchars($item['filename']) ?>
              </div>
            </td>
            <td>
              <span style="font-weight: 500;">
                <?= $item['width'] ?> × <?= $item['height'] ?> px
              </span>
              <span class="format-pill" style="margin-left: 0.25rem; font-size: 0.7rem;">
                <?= $format ?>
              </span>
            </td>
            <td>
              <div>
                <strong><?= formatBytes((int) $item['file_size']) ?></strong>
                <?php if ($savedPercent > 0): ?>
                  <span class="savings-tag">-<?= $savedPercent ?>%</span>
                <?php endif; ?>
              </div>
              <div style="font-size: 0.75rem; color: var(--text-muted);">
                Orig: <?= formatBytes((int) $item['original_size']) ?>
              </div>
            </td>
            <td style="color: var(--text-muted); font-size: 0.825rem; white-space: nowrap;">
              <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?>
            </td>
            <td>
              <div class="url-box">
                <input 
                  type="text" 
                  class="url-input" 
                  value="<?= htmlspecialchars($fullUrl) ?>" 
                  readonly 
                  onfocus="this.select()"
                >
                <button 
                  type="button" 
                  class="btn-copy" 
                  data-url="<?= htmlspecialchars($fullUrl) ?>" 
                  title="Copiar URL al portapapeles"
                >
                  Copiar
                </button>
              </div>
            </td>
            <td style="text-align: right; white-space: nowrap;">
              <button 
                type="button" 
                class="btn btn-outline btn-sm view-trigger" 
                data-full="<?= htmlspecialchars($fullUrl) ?>" 
                data-title="<?= htmlspecialchars($item['original_name']) ?>"
                title="Ver imagen completa"
              >
                <i class="ri-eye-line"></i> Ver
              </button>
              <button 
                type="button" 
                class="btn-danger-sm btn-delete-media" 
                data-id="<?= $item['id'] ?>" 
                data-name="<?= htmlspecialchars($item['original_name']) ?>"
                title="Eliminar imagen"
              >
                <i class="ri-delete-bin-line"></i>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
