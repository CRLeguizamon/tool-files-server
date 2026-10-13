/**
 * Image Optimizer - Script Principal y Utilidades Globales
 * Cumple la regla de registrar cada interacción mediante console.log
 */

console.log('[App] Inicializando Image Optimizer UI...');

/**
 * Muestra una notificación flotante (Toast)
 * @param {string} message 
 * @param {'success'|'error'|'info'} type 
 */
function showToast(message, type = 'info') {
  console.log(`[Toast] (${type}) ${message}`);
  const container = document.getElementById('toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.opacity = '0';
    toast.style.transform = 'translateY(10px)';
    toast.style.transition = 'all 0.3s ease';
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

/**
 * Copia texto al portapapeles y cambia temporalmente el botón
 * @param {string} text 
 * @param {HTMLElement} btn 
 */
function copyToClipboard(text, btn) {
  console.log('[Clipboard] Copiando al portapapeles:', text);
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(() => {
      triggerCopySuccess(btn);
    }).catch(err => {
      console.error('[Clipboard Error]', err);
      fallbackCopy(text, btn);
    });
  } else {
    fallbackCopy(text, btn);
  }
}

function fallbackCopy(text, btn) {
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.opacity = '0';
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand('copy');
    triggerCopySuccess(btn);
  } catch (err) {
    console.error('[Fallback Copy Error]', err);
    showToast('No se pudo copiar el enlace.', 'error');
  }
  document.body.removeChild(textArea);
}

function triggerCopySuccess(btn) {
  if (!btn) return;
  const originalText = btn.innerHTML;
  btn.classList.add('copied');
  btn.innerHTML = '<i class="ri-check-line"></i> ¡Copiado!';
  showToast('Enlace copiado al portapapeles.', 'success');
  setTimeout(() => {
    btn.classList.remove('copied');
    btn.innerHTML = originalText;
  }, 2000);
}

/**
 * Abre el visualizador Lightbox con la imagen a resolución completa
 * @param {string} src 
 * @param {string} title 
 */
function openLightbox(src, title = '') {
  console.log('[Lightbox] Abriendo imagen:', src);
  const modal = document.getElementById('lightbox-modal');
  const img = document.getElementById('lightbox-img');
  if (!modal || !img) return;

  img.src = src;
  img.alt = title;
  modal.classList.add('active');
}

function closeLightbox() {
  const modal = document.getElementById('lightbox-modal');
  if (modal) {
    modal.classList.remove('active');
  }
}

// Cierre del lightbox al hacer clic fuera de la imagen
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('lightbox-modal');
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal || e.target.classList.contains('lightbox-close')) {
        closeLightbox();
      }
    });
  }
});
