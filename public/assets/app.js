'use strict';
const projectSearch = document.querySelector('#project-search');
if (projectSearch) {
  const cards = Array.from(document.querySelectorAll('[data-project-card]'));
  projectSearch.addEventListener('input', () => {
    const query = projectSearch.value.trim().toLocaleLowerCase();
    for (const card of cards) card.hidden = !card.querySelector('h3').textContent.toLocaleLowerCase().includes(query);
    document.querySelector('#project-search-empty').hidden = !cards.length || cards.some(card => !card.hidden);
  });
}
const drawer = document.querySelector('.request-drawer');
if (drawer) {
  const close = drawer.querySelector('.drawer-header a');
  const tabs = Array.from(drawer.querySelectorAll('.drawer-tabs a'));
  function showSection() {
    const hash = location.hash === '#documents' ? '#request-information' : location.hash;
    const active = tabs.find(tab => tab.hash === hash) || tabs[0];
    for (const tab of tabs) {
      tab.toggleAttribute('aria-current', tab === active);
      document.getElementById(tab.hash.slice(1)).hidden = tab !== active;
    }
  }
  showSection();
  window.addEventListener('hashchange', showSection);
  close.focus({ preventScroll: true });
  drawer.addEventListener('keydown', event => {
    if (event.key === 'Escape') window.location.assign(close.href);
  });
}
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
