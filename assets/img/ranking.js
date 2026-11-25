/* Trillal Ranking — interacción visual accesible, los puntos son del servidor. */
(() => {
  'use strict';
  const elegido = document.querySelector('.ranking-jugador:target');
  if (elegido && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)) {
    elegido.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
  const search = document.querySelector('#buscar-jugador');
  if (search) search.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') { search.value = ''; search.blur(); }
  });
})();
