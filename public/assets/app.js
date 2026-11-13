'use strict';
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
