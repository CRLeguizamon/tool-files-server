<?php
/**
 * Sección de Medios: Tabla de Imágenes Optimizadas, Acciones en Lote, Copiado de URL y Exportación
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
      Total: <strong id="total-media-count"><?= $totalItems ?></strong> imágenes 
      <?php if ($totalItems > 0): ?>
        &bull; Espacio optimizado: <strong id="total-final-bytes"><?= formatBytes((int) $totalFinalBytes) ?></strong>
        <?php if ($globalSavedPct > 0): ?>
          <span id="savings-tag-header" class="savings-tag">Ahorro global: -<?= $globalSavedPct ?>%</span>
        <?php endif; ?>
      <?php endif; ?>
    </p>
  </div>

  <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
    <a 
      href="api/download_zip.php?all=1" 
      id="btn-export-zip" 
      class="btn btn-primary <?= $totalItems === 0 ? 'disabled' : '' ?>" 
      <?= $totalItems === 0 ? 'aria-disabled="true" style="pointer-events: none; opacity: 0.5;"' : 'download' ?> 
      title="Descargar todas las imágenes optimizadas en un archivo ZIP"
    >
      <i class="ri-file-zip-line"></i> Descargar Todo en ZIP
    </a>
    <a 
      href="api/export_csv.php" 
      id="btn-export-csv" 
      class="btn btn-outline <?= $totalItems === 0 ? 'disabled' : '' ?>" 
      <?= $totalItems === 0 ? 'aria-disabled="true" style="pointer-events: none; opacity: 0.5;"' : 'download' ?> 
      title="Exportar reporte de medios a formato CSV"
    >
      <i class="ri-file-excel-2-line"></i> Exportar en CSV
    </a>
    <a href="index.php" class="btn btn-outline">
      <i class="ri-upload-cloud-line"></i> Subir más
    </a>
  </div>
</div>

<!-- Tarjeta de Estado Vacío (Visible si no hay imágenes o tras borrar todas) -->
<div id="media-empty-card" class="card" style="text-align: center; padding: 4rem 2rem; <?= $totalItems > 0 ? 'display: none;' : '' ?>">
  <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--text-muted);">
    <i class="ri-image-add-line"></i>
  </div>
  <h2 style="font-size: 1.3rem; font-weight: 700; margin-bottom: 0.5rem;">Aún no hay imágenes procesadas</h2>
  <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Comienza subiendo tus archivos JPG, PNG, WEBP o AVIF para optimizarlos.</p>
  <a href="index.php" class="btn btn-primary">
    <i class="ri-upload-cloud-line"></i> Subir mi primer lote
  </a>
</div>

<!-- Contenedor Principal de Medios (Oculto si totalItems es 0) -->
<div id="media-content-wrap" style="<?= $totalItems === 0 ? 'display: none;' : '' ?>">

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
      <div id="media-showing-count" style="color: var(--text-muted); font-size: 0.875rem;">
        Mostrando <?= $totalItems ?> imágenes
      </div>
    </div>
  </div>

  <!-- Barra de Acciones en Lote (Bulk Actions) -->
  <div id="bulk-actions-bar" class="bulk-actions-bar" style="display: none;">
    <div class="bulk-info">
      <span class="bulk-badge">
        <i class="ri-checkbox-circle-line"></i> 
        <span id="bulk-count">0</span> seleccionadas
      </span>
      <span id="bulk-size-text" style="font-size: 0.825rem; color: var(--text-muted);"></span>
    </div>
    <div class="bulk-buttons">
      <a href="#" id="btn-bulk-zip" class="btn btn-primary btn-sm" download title="Descargar imágenes seleccionadas en un archivo ZIP">
        <i class="ri-file-zip-line"></i> Descargar ZIP (<span id="bulk-zip-count">0</span>)
      </a>
      <button type="button" id="btn-bulk-delete" class="btn btn-danger-sm" title="Eliminar las imágenes seleccionadas de forma permanente">
        <i class="ri-delete-bin-line"></i> Borrar seleccionadas (<span id="bulk-delete-count">0</span>)
      </button>
      <button type="button" id="btn-bulk-cancel" class="btn btn-outline btn-sm" title="Cancelar selección de imágenes">
        <i class="ri-close-line"></i> Deseleccionar
      </button>
    </div>
  </div>

  <!-- Tabla de Medios -->
  <div class="table-responsive">
    <table class="media-table">
      <thead>
        <tr>
          <th class="col-checkbox">
            <input 
              type="checkbox" 
              id="select-all-media" 
              class="custom-checkbox" 
              title="Seleccionar o deseleccionar todas las imágenes visibles"
            >
          </th>
          <th style="width: 70px;">Miniatura</th>
          <th>Nombre del Archivo</th>
          <th>Dimensiones</th>
          <th>Peso / Ahorro</th>
          <th>Fecha</th>
          <th style="min-width: 300px;">URL Directa</th>
          <th style="text-align: right; width: 140px;">Acciones</th>
        </tr>
      </thead>
      <tbody id="media-tbody">
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
            data-id="<?= $item['id'] ?>"
            data-name="<?= htmlspecialchars($item['original_name']) ?>"
            data-size="<?= (int) $item['file_size'] ?>"
            data-search="<?= htmlspecialchars($item['filename'] . ' ' . $item['original_name']) ?>"
          >
            <td class="col-checkbox">
              <input 
                type="checkbox" 
                class="custom-checkbox row-select-check" 
                data-id="<?= $item['id'] ?>" 
                aria-label="Seleccionar imagen"
              >
            </td>
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

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
