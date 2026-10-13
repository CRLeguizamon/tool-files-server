/**
 * Image Optimizer - Módulo de Medios y Biblioteca de Imágenes
 * Control de filtrado en tabla, copiado de URLs, eliminación y lightbox
 */

console.log('[Media] Módulo de medios cargado.');

document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('media-search');
  const mediaRows = document.querySelectorAll('.media-row');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // 1. Filtrado dinámico en la tabla
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase().trim();
      console.log('[Media Search] Filtrando por:', query);

      let visibleCount = 0;
      mediaRows.forEach(row => {
        const text = row.getAttribute('data-search')?.toLowerCase() || '';
        if (text.includes(query)) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      console.log(`[Media Search] ${visibleCount} de ${mediaRows.length} imágenes visibles`);
    });
  }

  // 2. Delegación de eventos para copiar URL, ver y eliminar
  document.addEventListener('click', (e) => {
    // Botón Copiar URL
    const btnCopy = e.target.closest('.btn-copy');
    if (btnCopy) {
      const url = btnCopy.getAttribute('data-url');
      if (url) {
        copyToClipboard(url, btnCopy);
      }
      return;
    }

    // Botón Ver / Clic en Miniatura para Lightbox
    const viewTrigger = e.target.closest('.view-trigger');
    if (viewTrigger) {
      const fullUrl = viewTrigger.getAttribute('data-full');
      const title = viewTrigger.getAttribute('data-title') || 'Vista Previa';
      if (fullUrl) {
        openLightbox(fullUrl, title);
      }
      return;
    }

    // Botón Eliminar Imagen
    const btnDelete = e.target.closest('.btn-delete-media');
    if (btnDelete) {
      const id = btnDelete.getAttribute('data-id');
      const name = btnDelete.getAttribute('data-name');

      if (!confirm(`¿Estás seguro de que deseas eliminar permanentemente la imagen "${name}"?`)) {
        return;
      }

      console.log('[Media Delete] Solicitando eliminación de imagen ID:', id);

      const formData = new FormData();
      formData.append('id', id);
      formData.append('csrf_token', csrfToken);

      fetch('api/delete_media.php', {
        method: 'POST',
        body: formData,
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          console.log('[Media Delete] Imagen eliminada con éxito:', id);
          showToast('Imagen eliminada correctamente.', 'success');
          const row = document.getElementById(`media-row-${id}`);
          if (row) {
            row.style.opacity = '0';
            row.style.transform = 'scale(0.95)';
            row.style.transition = 'all 0.3s ease';
            setTimeout(() => row.remove(), 300);
          }
        } else {
          console.error('[Media Delete Error]', data.message);
          showToast(data.message || 'Error al eliminar la imagen.', 'error');
        }
      })
      .catch(err => {
        console.error('[Media Delete Network Error]', err);
        showToast('Error de conexión al eliminar la imagen.', 'error');
      });
    }
  });

  // 3. Botón de Exportar a CSV
  const btnExportCsv = document.getElementById('btn-export-csv');
  if (btnExportCsv) {
    btnExportCsv.addEventListener('click', () => {
      console.log('[CSV Export] Iniciando descarga de archivo CSV...');
      showToast('Descargando archivo CSV...', 'info');
      window.location.href = 'api/export_csv.php';
    });
  }
});
