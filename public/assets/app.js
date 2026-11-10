'use strict';
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('submit', event => {
    if (!window.confirm(el.dataset.confirm || '¿Confirmar acción?')) event.preventDefault();
  });
});
const search = document.querySelector('#filter-text');
if (search) {
  search.addEventListener('input', () => {
    const q = search.value.trim().toLocaleLowerCase();
    document.querySelectorAll('[data-request-row]').forEach(row => {
      row.hidden = !row.textContent.toLocaleLowerCase().includes(q);
    });
  });
}
