<?php
/**
 * Panel Principal: Subida y Optimización de Imágenes por Lotes
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Subir y Optimizar';
$extraScripts = ['assets/js/uploader.js'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="page-header">
  <div>
    <h1 class="page-title">Subir y Optimizar Imágenes</h1>
    <p class="page-desc">Redimensiona y comprime imágenes por lotes con procesamiento AJAX estable.</p>
  </div>
  <a href="media.php" class="btn btn-outline">
    <i class="ri-image-line"></i> Ir a Medios
  </a>
</div>

<!-- Tarjeta de Subida y Configuración -->
<div class="card">
  <!-- Zona Dropzone -->
  <div id="dropzone-box" class="dropzone-box">
    <div class="dropzone-icon">
      <i class="ri-upload-cloud-2-line" style="font-size: 1.85rem;"></i>
    </div>
    <div class="dropzone-title">Arrastra y suelta tus imágenes aquí</div>
    <div class="dropzone-hint">o haz clic en cualquier parte de este recuadro para seleccionarlas</div>
    
    <div class="format-pills">
      <span class="format-pill">JPG</span>
      <span class="format-pill">PNG</span>
      <span class="format-pill">WEBP</span>
      <span class="format-pill">AVIF</span>
    </div>

    <input 
      type="file" 
      id="file-input" 
      multiple 
      accept=".jpg,.jpeg,.png,.webp,.avif,image/jpeg,image/png,image/webp,image/avif" 
      style="display: none;"
    >
  </div>

  <!-- Barra de Configuración y Parámetros -->
  <div class="controls-grid">
    <!-- Ancho Máximo (Default 1920, máx 2048) -->
    <div class="control-item">
      <div class="control-label">
        <label for="max-width-input">Ancho máximo permitido:</label>
        <div style="display: flex; align-items: center; gap: 0.35rem;">
          <input 
            type="number" 
            id="max-width-input" 
            class="number-input-field" 
            min="300" 
            max="2048" 
            value="1920" 
            style="width: 86px; padding: 0.25rem 0.5rem; text-align: center; font-weight: 700; border: 1px solid var(--border); border-radius: var(--radius-sm); color: var(--primary); font-size: 0.925rem;"
          >
          <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted);">px</span>
        </div>
      </div>
      <div class="slider-container">
        <input 
          type="range" 
          id="max-width-slider" 
          class="range-slider" 
          min="300" 
          max="2048" 
          value="1920" 
          step="10"
        >
      </div>
    </div>

    <!-- Checkbox Comprimir -->
    <div class="control-item" style="justify-content: center;">
      <label class="checkbox-label" for="compress-check">
        <input 
          type="checkbox" 
          id="compress-check" 
          class="custom-checkbox" 
          checked
        >
        <span>Comprimir imágenes</span>
      </label>
    </div>

    <!-- Checkbox Convertir a JPG -->
    <div class="control-item" style="justify-content: center;">
      <label class="checkbox-label" for="convert-jpg-check" title="Convierte imágenes PNG a JPG con fondo blanco para lograr un peso menor a 1 MB. Si ya es JPG se mantiene igual.">
        <input 
          type="checkbox" 
          id="convert-jpg-check" 
          class="custom-checkbox" 
          checked
        >
        <span>Convertir a JPG (PNG &rarr; JPG)</span>
      </label>
    </div>

    <!-- Checkbox Descargar como ZIP -->
    <div class="control-item" style="justify-content: center;">
      <label class="checkbox-label" for="zip-only-check" title="Descarga automáticamente las imágenes optimizadas en un archivo ZIP al completar el lote.">
        <input 
          type="checkbox" 
          id="zip-only-check" 
          class="custom-checkbox"
        >
        <span><i class="ri-file-zip-line" style="color: var(--primary);"></i> Descargar lote en ZIP al finalizar</span>
      </label>
    </div>

    <!-- Botón Procesar -->
    <div class="control-item" style="justify-content: center;">
      <button type="button" id="btn-process" class="btn btn-primary" disabled>
        <i class="ri-sparkling-2-line"></i> Procesar Imágenes
      </button>
    </div>
  </div>
</div>

<!-- Sección de Cola y Progreso AJAX -->
<div id="queue-section" class="card" style="display: none;">
  <div class="queue-header">
    <h2 style="font-size: 1.15rem; font-weight: 700;">Cola de Procesamiento</h2>
    <div style="display: flex; gap: 0.5rem; align-items: center;">
      <span id="progress-text" style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);">0%</span>
      <button type="button" id="btn-clear-queue" class="btn btn-outline btn-sm">
        <i class="ri-delete-bin-line"></i> Limpiar cola
      </button>
    </div>
  </div>

  <div class="progress-bar-wrap">
    <div id="progress-bar-fill" class="progress-bar-fill"></div>
  </div>

  <div id="queue-list" class="queue-list"></div>

  <div id="queue-completed-actions" style="display: none; margin-top: 1.5rem; text-align: center;">
    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
      <a href="#" id="btn-download-batch-zip" class="btn btn-primary" style="display: none;" download>
        <i class="ri-file-zip-line"></i> Descargar este Lote en ZIP
      </a>
      <a href="media.php" id="btn-go-media" class="btn btn-outline">
        <i class="ri-image-line"></i> Ver todas las imágenes en Medios
      </a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
