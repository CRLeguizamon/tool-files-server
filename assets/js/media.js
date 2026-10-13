/**
 * Image Optimizer - Módulo de Medios y Biblioteca de Imágenes
 * Control de filtrado en tabla, selección en lote, eliminación masiva, copiado de URLs y lightbox
 */

console.log('[Media] Módulo de medios cargado (v1.0.3).');

document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('media-search');
  const mediaTbody = document.getElementById('media-tbody');
  const selectAllCheckbox = document.getElementById('select-all-media');
  const bulkActionsBar = document.getElementById('bulk-actions-bar');
  const bulkCountSpan = document.getElementById('bulk-count');
  const bulkSizeText = document.getElementById('bulk-size-text');
  const bulkZipCountSpan = document.getElementById('bulk-zip-count');
  const bulkDeleteCountSpan = document.getElementById('bulk-delete-count');
  const btnBulkZip = document.getElementById('btn-bulk-zip');
  const btnBulkDelete = document.getElementById('btn-bulk-delete');
  const btnBulkCancel = document.getElementById('btn-bulk-cancel');

  const totalCountEl = document.getElementById('total-media-count');
  const showingCountEl = document.getElementById('media-showing-count');
  const contentWrap = document.getElementById('media-content-wrap');
  const emptyCard = document.getElementById('media-empty-card');
  const btnExportZip = document.getElementById('btn-export-zip');
  const btnExportCsv = document.getElementById('btn-export-csv');

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  // Conjunto de IDs seleccionados
  const selectedIds = new Set();

  function formatBytes(bytes) {
    if (bytes >= 1048576) {
      return (bytes / 1048576).toFixed(2) + ' MB';
    } else if (bytes >= 1024) {
      return (bytes / 1024).toFixed(1) + ' KB';
    }
    return bytes + ' B';
  }

  function getVisibleRows() {
    return Array.from(document.querySelectorAll('.media-row')).filter(row => row.style.display !== 'none');
  }

  function getAllRows() {
    return Array.from(document.querySelectorAll('.media-row'));
  }

  // 1. Actualizar barra de acciones en lote y estado de selección
  function updateBulkBar() {
    const count = selectedIds.size;
    console.log(`[Bulk Selection] Actualizando barra. Seleccionados: ${count}`);

    if (count > 0) {
      // Calcular tamaño total de los archivos seleccionados
      let totalBytes = 0;
      selectedIds.forEach(id => {
        const row = document.getElementById(`media-row-${id}`);
        if (row) {
          totalBytes += parseInt(row.getAttribute('data-size') || '0', 10);
        }
      });

      if (bulkCountSpan) bulkCountSpan.textContent = count;
      if (bulkZipCountSpan) bulkZipCountSpan.textContent = count;
      if (bulkDeleteCountSpan) bulkDeleteCountSpan.textContent = count;
      if (bulkSizeText) {
        bulkSizeText.textContent = totalBytes > 0 ? `(${formatBytes(totalBytes)} en total)` : '';
      }

      if (btnBulkZip) {
        const idListStr = Array.from(selectedIds).join(',');
        btnBulkZip.href = `api/download_zip.php?ids=${encodeURIComponent(idListStr)}`;
        btnBulkZip.setAttribute('download', `seleccion_medios_${Date.now()}.zip`);
      }

      if (bulkActionsBar) {
        bulkActionsBar.style.display = 'flex';
      }
    } else {
      if (bulkActionsBar) {
        bulkActionsBar.style.display = 'none';
      }
    }

    // Actualizar estado del checkbox maestro (select-all)
    if (selectAllCheckbox) {
      const visibleRows = getVisibleRows();
      if (visibleRows.length === 0) {
        selectAllCheckbox.checked = false;
        selectAllCheckbox.indeterminate = false;
      } else {
        const visibleSelectedCount = visibleRows.filter(r => {
          const id = r.getAttribute('data-id');
          return selectedIds.has(id);
        }).length;

        if (visibleSelectedCount === 0) {
          selectAllCheckbox.checked = false;
          selectAllCheckbox.indeterminate = false;
        } else if (visibleSelectedCount === visibleRows.length) {
          selectAllCheckbox.checked = true;
          selectAllCheckbox.indeterminate = false;
        } else {
          selectAllCheckbox.checked = false;
          selectAllCheckbox.indeterminate = true;
        }
      }
    }
  }

  // 2. Filtrado dinámico por búsqueda
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase().trim();
      console.log('[Media Search] Filtrando por:', query);

      const allRows = getAllRows();
      let visibleCount = 0;

      allRows.forEach(row => {
        const text = row.getAttribute('data-search')?.toLowerCase() || '';
        if (text.includes(query)) {
          row.style.display = '';
          visibleCount++;
        } else {
          row.style.display = 'none';
        }
      });

      if (showingCountEl) {
        showingCountEl.textContent = `Mostrando ${visibleCount} de ${allRows.length} imágenes`;
      }

      updateBulkBar();
    });
  }

  // 3. Evento del checkbox maestro (#select-all-media)
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', (e) => {
      const isChecked = e.target.checked;
      const visibleRows = getVisibleRows();
      console.log(`[Bulk Select All] ${isChecked ? 'Seleccionando' : 'Deseleccionando'} ${visibleRows.length} filas visibles`);

      visibleRows.forEach(row => {
        const id = row.getAttribute('data-id');
        const check = row.querySelector('.row-select-check');
        if (isChecked) {
          selectedIds.add(id);
          row.classList.add('media-row-selected');
          if (check) check.checked = true;
        } else {
          selectedIds.delete(id);
          row.classList.remove('media-row-selected');
          if (check) check.checked = false;
        }
      });

      updateBulkBar();
    });
  }

  // 4. Delegación de clics y selección en filas de la tabla
  if (mediaTbody) {
    // Cambio en checkbox de fila
    mediaTbody.addEventListener('change', (e) => {
      const check = e.target.closest('.row-select-check');
      if (check) {
        const row = check.closest('.media-row');
        const id = check.getAttribute('data-id');

        if (check.checked) {
          selectedIds.add(id);
          if (row) row.classList.add('media-row-selected');
        } else {
          selectedIds.delete(id);
          if (row) row.classList.remove('media-row-selected');
        }

        updateBulkBar();
      }
    });

    // Clic en fila para seleccionar (salvo que sea un botón, enlace, input o imagen ampliable)
    mediaTbody.addEventListener('click', (e) => {
      if (
        e.target.closest('button') ||
        e.target.closest('a') ||
        e.target.closest('input') ||
        e.target.closest('.view-trigger')
      ) {
        return;
      }

      const row = e.target.closest('.media-row');
      if (row) {
        const check = row.querySelector('.row-select-check');
        if (check) {
          check.checked = !check.checked;
          const id = row.getAttribute('data-id');
          if (check.checked) {
            selectedIds.add(id);
            row.classList.add('media-row-selected');
          } else {
            selectedIds.delete(id);
            row.classList.remove('media-row-selected');
          }
          updateBulkBar();
        }
      }
    });
  }

  // 5. Botón Deseleccionar (#btn-bulk-cancel)
  if (btnBulkCancel) {
    btnBulkCancel.addEventListener('click', () => {
      console.log('[Bulk Cancel] Limpiando selección en lote.');
      selectedIds.clear();
      getAllRows().forEach(row => {
        row.classList.remove('media-row-selected');
        const check = row.querySelector('.row-select-check');
        if (check) check.checked = false;
      });
      updateBulkBar();
    });
  }

  // 6. Botón Descargar ZIP en lote (#btn-bulk-zip)
  if (btnBulkZip) {
    btnBulkZip.addEventListener('click', () => {
      const count = selectedIds.size;
      console.log(`[Bulk ZIP] Iniciando descarga de ${count} imágenes seleccionadas.`);
      showToast(`Empaquetando ${count} imágenes seleccionadas en ZIP...`, 'info');
    });
  }

  // 7. Botón Eliminar en Lote (#btn-bulk-delete)
  if (btnBulkDelete) {
    btnBulkDelete.addEventListener('click', async () => {
      const count = selectedIds.size;
      if (count === 0) return;

      const confirmMsg = count === 1
        ? '¿Estás seguro de que deseas eliminar permanentemente la imagen seleccionada?'
        : `¿Estás seguro de que deseas eliminar permanentemente las ${count} imágenes seleccionadas?\n\nEsta acción eliminará los archivos físicos del servidor y sus registros de la base de datos de forma irreversible.`;

      if (!confirm(confirmMsg)) {
        return;
      }

      console.log(`[Bulk Delete] Confirmado. Enviando solicitud para eliminar ${count} imágenes:`, Array.from(selectedIds));

      const originalHtml = btnBulkDelete.innerHTML;
      btnBulkDelete.disabled = true;
      btnBulkDelete.innerHTML = '<i class="ri-loader-4-line ri-spin"></i> Eliminando...';

      const formData = new FormData();
      formData.append('ids', Array.from(selectedIds).join(','));
      formData.append('csrf_token', csrfToken);

      try {
        const response = await fetch('api/delete_media.php', {
          method: 'POST',
          body: formData,
        });

        const data = await response.json();

        if (response.ok && data.success) {
          console.log('[Bulk Delete Success]', data);
          showToast(data.message || `${count} imágenes eliminadas con éxito.`, 'success');

          const deletedList = data.deleted_ids || Array.from(selectedIds);
          deletedList.forEach(id => {
            const row = document.getElementById(`media-row-${id}`);
            if (row) {
              row.style.opacity = '0';
              row.style.transform = 'scale(0.95)';
              row.style.transition = 'all 0.3s ease';
              setTimeout(() => row.remove(), 300);
            }
          });

          selectedIds.clear();

          // Esperar animación de salida y actualizar contadores
          setTimeout(() => {
            checkAndUpdateEmptyState();
            updateBulkBar();
          }, 350);

        } else {
          console.error('[Bulk Delete Error]', data);
          showToast(data.message || 'Error al eliminar las imágenes seleccionadas.', 'error');
        }
      } catch (err) {
        console.error('[Bulk Delete Exception]', err);
        showToast('Error de red al procesar la eliminación en lote.', 'error');
      } finally {
        btnBulkDelete.disabled = false;
        btnBulkDelete.innerHTML = originalHtml;
      }
    });
  }

  // 8. Delegación global para botones individuales (Copiar URL, Ver, Eliminar individual)
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

    // Botón Eliminar Imagen Individual
    const btnDelete = e.target.closest('.btn-delete-media');
    if (btnDelete) {
      const id = btnDelete.getAttribute('data-id');
      const name = btnDelete.getAttribute('data-name');

      if (!confirm(`¿Estás seguro de que deseas eliminar permanentemente la imagen "${name}"?`)) {
        return;
      }

      console.log('[Media Delete] Solicitando eliminación individual de imagen ID:', id);

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
          
          if (selectedIds.has(id)) {
            selectedIds.delete(id);
            updateBulkBar();
          }

          const row = document.getElementById(`media-row-${id}`);
          if (row) {
            row.style.opacity = '0';
            row.style.transform = 'scale(0.95)';
            row.style.transition = 'all 0.3s ease';
            setTimeout(() => {
              row.remove();
              checkAndUpdateEmptyState();
            }, 300);
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

  // 9. Enlace superior de Exportar a CSV
  if (btnExportCsv) {
    btnExportCsv.addEventListener('click', () => {
      console.log('[CSV Export] Clic en Exportar CSV. Iniciando descarga...');
      showToast('Descargando archivo CSV...', 'info');
    });
  }

  // 10. Enlace superior de Descargar Todo en ZIP
  if (btnExportZip) {
    btnExportZip.addEventListener('click', () => {
      console.log('[ZIP Export] Clic en Descargar Todo en ZIP. Iniciando empaquetado...');
      showToast('Generando archivo ZIP con todas las imágenes...', 'info');
    });
  }

  // 11. Función auxiliar para verificar si la tabla se quedó sin filas
  function checkAndUpdateEmptyState() {
    const remainingRows = getAllRows();
    const count = remainingRows.length;

    if (totalCountEl) totalCountEl.textContent = count;
    if (showingCountEl) showingCountEl.textContent = `Mostrando ${count} imágenes`;

    if (count === 0) {
      if (contentWrap) contentWrap.style.display = 'none';
      if (emptyCard) emptyCard.style.display = 'block';

      if (btnExportZip) {
        btnExportZip.classList.add('disabled');
        btnExportZip.style.pointerEvents = 'none';
        btnExportZip.style.opacity = '0.5';
      }
      if (btnExportCsv) {
        btnExportCsv.classList.add('disabled');
        btnExportCsv.style.pointerEvents = 'none';
        btnExportCsv.style.opacity = '0.5';
      }
    }
  }
});
