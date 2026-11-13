'use strict';
const sidebarToggle = document.querySelector('.sidebar-toggle');
if (sidebarToggle) {
  const sidebarContent = document.getElementById('sidebar-content');
  const narrowScreen = window.matchMedia('(max-width: 780px)');
  function setSidebarOpen(open) {
    sidebarContent.hidden = !open;
    sidebarToggle.setAttribute('aria-expanded', String(open));
  }
  function syncSidebar() {
    sidebarToggle.hidden = !narrowScreen.matches;
    setSidebarOpen(!narrowScreen.matches);
  }
  sidebarToggle.addEventListener('click', () => setSidebarOpen(sidebarContent.hidden));
  narrowScreen.addEventListener('change', syncSidebar);
  syncSidebar();
}
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
  if (typeof drawer.showModal === 'function') {
    drawer.removeAttribute('open');
    drawer.showModal();
  }
  const tabs = Array.from(drawer.querySelectorAll('.drawer-tabs a'));
  function showSection() {
    const hash = location.hash === '#documents' ? '#request-information' : location.hash;
    const active = tabs.find(tab => tab.hash === hash) || tabs[0];
    for (const tab of tabs) {
      if (tab === active) tab.setAttribute('aria-current', 'location');
      else tab.removeAttribute('aria-current');
      document.getElementById(tab.hash.slice(1)).hidden = tab !== active;
    }
  }
  showSection();
  window.addEventListener('hashchange', showSection);
  close.focus({ preventScroll: true });
  drawer.addEventListener('cancel', event => {
    event.preventDefault();
    window.location.assign(close.href);
  });
  drawer.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    event.preventDefault();
    window.location.assign(close.href);
  });
}
const returnedRequest = document.getElementById(location.hash.slice(1));
if (!drawer && returnedRequest?.matches('[data-request-row]')) {
  window.addEventListener('pageshow', () => {
    requestAnimationFrame(() => returnedRequest.querySelector('a').focus());
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
