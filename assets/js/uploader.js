/**
 * Image Optimizer - Módulo de Subida y Procesamiento por Lotes
 * Envía imágenes secuencialmente vía AJAX para garantizar estabilidad en el servidor
 */

console.log('[Uploader] Módulo de subida cargado.');

document.addEventListener('DOMContentLoaded', () => {
  const dropzone = document.getElementById('dropzone-box');
  const fileInput = document.getElementById('file-input');
  const btnProcess = document.getElementById('btn-process');
  const btnClear = document.getElementById('btn-clear-queue');
  const widthSlider = document.getElementById('max-width-slider');
  const widthInput = document.getElementById('max-width-input');
  const compressCheck = document.getElementById('compress-check');
  const convertJpgCheck = document.getElementById('convert-jpg-check');
  const queueSection = document.getElementById('queue-section');
  const queueList = document.getElementById('queue-list');
  const progressBar = document.getElementById('progress-bar-fill');
  const progressText = document.getElementById('progress-text');
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  if (!dropzone || !fileInput) return;

  let queue = [];
  let isProcessing = false;

  // 1. Sincronización Bidireccional de Ancho Máximo (hasta 2048px, default 1920px)
  if (widthSlider && widthInput) {
    widthSlider.addEventListener('input', (e) => {
      const val = e.target.value;
      widthInput.value = val;
      console.log(`[Config] Slider: Ancho máximo ajustado a: ${val}px`);
    });

    widthInput.addEventListener('input', (e) => {
      const val = parseInt(e.target.value, 10);
      if (!isNaN(val) && val >= 300 && val <= 2048) {
        widthSlider.value = val;
        console.log(`[Config] Input: Ancho máximo sincronizado a: ${val}px`);
      }
    });

    widthInput.addEventListener('change', (e) => {
      let val = parseInt(e.target.value, 10);
      if (isNaN(val) || val < 300) val = 300;
      if (val > 2048) val = 2048;
      widthInput.value = val;
      widthSlider.value = val;
      console.log(`[Config] Input fijado y validado a: ${val}px`);
    });
  }

  // 2. Eventos de Selección y Arrastre (Drag and Drop)
  ['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.add('dragover');
    }, false);
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.remove('dragover');
    }, false);
  });

  dropzone.addEventListener('drop', (e) => {
    const dt = e.dataTransfer;
    const files = dt.files;
    console.log('[Drop] Archivos arrastrados al dropzone:', files.length);
    handleFiles(files);
  });

  dropzone.addEventListener('click', () => {
    fileInput.click();
  });

  fileInput.addEventListener('change', (e) => {
    console.log('[FileInput] Archivos seleccionados desde el explorador:', e.target.files.length);
    handleFiles(e.target.files);
    fileInput.value = ''; // Permite volver a seleccionar el mismo archivo si se desea
  });

  // 3. Validación y adición a la cola
  const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
  const ALLOWED_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

  function handleFiles(files) {
    if (!files || files.length === 0) return;

    let addedCount = 0;
    Array.from(files).forEach((file) => {
      const ext = file.name.split('.').pop().toLowerCase();
      const isValidMime = ALLOWED_TYPES.includes(file.type);
      const isValidExt = ALLOWED_EXTS.includes(ext);

      if (!isValidMime && !isValidExt) {
        console.warn(`[Validación] Archivo rechazado (formato no soportado): ${file.name}`);
        showToast(`El archivo "${file.name}" no es JPG, PNG, WEBP o AVIF.`, 'error');
        return;
      }

      const item = {
        id: 'img_' + Math.random().toString(36).substr(2, 9),
        file: file,
        status: 'pending', // pending | processing | done | error
        result: null,
      };

      queue.push(item);
      renderQueueItem(item);
      addedCount++;
    });

    console.log(`[Cola] Total en cola actual: ${queue.length} (${addedCount} nuevos añadidos)`);

    if (queue.length > 0) {
      queueSection.style.display = 'block';
      updateProgress();
      if (!isProcessing) {
        btnProcess.disabled = false;
      }
    }
  }

  // 4. Renderizado visual de un elemento de la cola
  function renderQueueItem(item) {
    const el = document.createElement('div');
    el.id = item.id;
    el.className = 'queue-item';

    const objectUrl = URL.createObjectURL(item.file);
    const sizeKb = (item.file.size / 1024).toFixed(1);

    el.innerHTML = `
      <div class="queue-file-info">
        <img class="queue-thumb" src="${objectUrl}" alt="Preview" />
        <div class="queue-texts">
          <div class="queue-name" title="${item.file.name}">${item.file.name}</div>
          <div class="queue-meta">${sizeKb} KB • Formato: ${item.file.name.split('.').pop().toUpperCase()}</div>
        </div>
      </div>
      <div class="queue-status">
        <span class="status-badge status-pending"><i class="ri-time-line"></i> En espera</span>
      </div>
    `;

    queueList.appendChild(el);
    item.element = el;
  }

  // 5. Botón de limpiar cola
  if (btnClear) {
    btnClear.addEventListener('click', () => {
      if (isProcessing) return;
      console.log('[Cola] Vaciando cola de archivos.');
      queue = [];
      queueList.innerHTML = '';
      queueSection.style.display = 'none';
      btnProcess.disabled = true;
      updateProgress();
    });
  }

  // 6. Procesamiento por Lotes vía AJAX Secuencial
  btnProcess.addEventListener('click', async () => {
    if (isProcessing || queue.length === 0) return;

    const pendingItems = queue.filter(q => q.status === 'pending');
    if (pendingItems.length === 0) {
      showToast('No hay imágenes pendientes de procesar.', 'info');
      return;
    }

    isProcessing = true;
    btnProcess.disabled = true;
    if (btnClear) btnClear.disabled = true;
    widthSlider.disabled = true;
    if (widthInput) widthInput.disabled = true;
    compressCheck.disabled = true;
    if (convertJpgCheck) convertJpgCheck.disabled = true;

    const maxWidth = parseInt(widthInput?.value || widthSlider?.value || 1920, 10);
    const compress = compressCheck.checked ? 1 : 0;
    const convertJpg = (convertJpgCheck && convertJpgCheck.checked) ? 1 : 0;

    console.log(`[Lote] Iniciando procesamiento de ${pendingItems.length} imágenes. Ancho: ${maxWidth}px, Comprimir: ${compress}, Convertir JPG: ${convertJpg}`);

    let completedCount = 0;
    const totalToProcess = pendingItems.length;

    for (const item of pendingItems) {
      item.status = 'processing';
      updateItemStatus(item, 'processing', '<i class="ri-loader-4-line ri-spin"></i> Procesando...');

      console.log(`[Lote AJAX] Enviando imagen: ${item.file.name} (${(item.file.size / 1024).toFixed(1)} KB) | Convertir JPG: ${convertJpg}`);

      const formData = new FormData();
      formData.append('image', item.file);
      formData.append('max_width', maxWidth);
      formData.append('compress', compress);
      formData.append('convert_jpg', convertJpg);
      formData.append('csrf_token', csrfToken);

      try {
        const response = await fetch('api/process_image.php', {
          method: 'POST',
          body: formData,
        });

        const data = await response.json();

        if (response.ok && data.success) {
          item.status = 'done';
          item.result = data.data;
          const savings = data.data.saved_percent > 0 ? `-${data.data.saved_percent}%` : '0%';
          const newSizeMb = (data.data.file_size / (1024 * 1024)).toFixed(2);
          const newSizeDisplay = data.data.file_size >= 1048576 
            ? `${newSizeMb} MB` 
            : `${(data.data.file_size / 1024).toFixed(1)} KB`;
          const isConverted = data.data.is_converted_jpg ? ' • JPG' : '';

          updateItemStatus(
            item, 
            'done', 
            `<i class="ri-checkbox-circle-line"></i> ${data.data.filename} (${newSizeDisplay} • Ahorro: ${savings}${isConverted})`
          );

          console.log(`[Lote AJAX] Éxito procesando ${item.file.name} -> ${data.data.filename}:`, data.data);
        } else {
          item.status = 'error';
          updateItemStatus(item, 'error', `<i class="ri-close-circle-line"></i> ${data.message || 'Error desconocido'}`);
          console.error(`[Lote AJAX Error] ${item.file.name}:`, data);
        }
      } catch (err) {
        item.status = 'error';
        updateItemStatus(item, 'error', '<i class="ri-close-circle-line"></i> Error de red / conexión');
        console.error(`[Lote AJAX Exception] ${item.file.name}:`, err);
      }

      completedCount++;
      updateProgress();
    }

    isProcessing = false;
    btnProcess.disabled = false;
    if (btnClear) btnClear.disabled = false;
    widthSlider.disabled = false;
    if (widthInput) widthInput.disabled = false;
    compressCheck.disabled = false;
    if (convertJpgCheck) convertJpgCheck.disabled = false;

    console.log(`[Lote Finalizado] Se han procesado ${completedCount} de ${totalToProcess} imágenes.`);
    showToast(`Lote completado: ${completedCount} imágenes procesadas.`, 'success');

    // Botón de acceso directo a medios
    const actionWrap = document.getElementById('queue-completed-actions');
    if (actionWrap) {
      actionWrap.style.display = 'block';
    }
  });

  function updateItemStatus(item, statusClass, text) {
    if (!item.element) return;
    const badge = item.element.querySelector('.queue-status');
    if (badge) {
      badge.innerHTML = `<span class="status-badge status-${statusClass}">${text}</span>`;
    }
  }

  function updateProgress() {
    const total = queue.length;
    if (total === 0) {
      progressBar.style.width = '0%';
      progressText.textContent = '0%';
      return;
    }

    const finished = queue.filter(q => q.status === 'done' || q.status === 'error').length;
    const percent = Math.round((finished / total) * 100);

    progressBar.style.width = `${percent}%`;
    progressText.textContent = `${finished} de ${total} procesadas (${percent}%)`;
  }
});
