'use strict';
const fileInput = document.querySelector('#document');
if (fileInput) {
  const summary = document.querySelector('#upload-summary');
  fileInput.addEventListener('change', () => {
    const files = Array.from(fileInput.files);
    const oversized = files.some(file => file.size > Number(fileInput.dataset.maxBytes));
    const error = files.length > 10 ? 'Selecciona hasta 10 archivos.'
      : oversized ? 'Un archivo supera el tamaño máximo.' : '';
    fileInput.setCustomValidity(error);
    summary.textContent = error || (files.length === 1 ? '1 archivo seleccionado'
      : files.length > 1 ? files.length + ' archivos seleccionados' : '');
  });
}
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('submit', event => {
    if (!window.confirm(el.dataset.confirm || '¿Confirmar acción?')) event.preventDefault();
  });
});
const preview = document.querySelector('.request-preview');
if (preview && typeof preview.showModal === 'function') {
  document.querySelectorAll('[data-preview]').forEach(button => {
    button.hidden = false;
    button.addEventListener('click', () => {
      const template = document.getElementById(button.dataset.preview);
      preview.querySelector('.preview-content').replaceChildren(template.content.cloneNode(true));
      preview.showModal();
    });
  });
}
