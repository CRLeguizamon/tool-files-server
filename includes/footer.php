<?php
/**
 * Pie de Página Común y Modales
 */

declare(strict_types=1);
?>
</main>

<!-- Modal Visualizador Lightbox -->
<div id="lightbox-modal" class="modal-overlay" role="dialog" aria-modal="true">
  <div class="lightbox-img-wrap">
    <button type="button" class="lightbox-close" onclick="closeLightbox()" title="Cerrar">
      <i class="ri-close-line"></i>
    </button>
    <img id="lightbox-img" class="lightbox-img" src="" alt="Vista previa">
  </div>
</div>

<!-- Contenedor Flotante de Notificaciones Toast -->
<div id="toast-container" class="toast-container"></div>

<footer class="site-footer">
  <p>Sistema minimalista de optimización y gestión de imágenes - Versión <?= APP_VERSION ?></p>
</footer>

<script src="assets/js/app.js?v=<?= APP_VERSION ?>"></script>
<?php if (isset($extraScripts) && is_array($extraScripts)): ?>
  <?php foreach ($extraScripts as $script): ?>
    <script src="<?= htmlspecialchars($script) ?>?v=<?= APP_VERSION ?>"></script>
  <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
