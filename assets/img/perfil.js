'use strict';
// Trillal · Perfil: funciones visuales. PHP conserva la autoridad sobre las cuentas.
document.addEventListener('DOMContentLoaded', () => {
  const input = document.querySelector('#avatar-input');
  const preview = document.querySelector('#avatar-preview');
  const nombreArchivo = document.querySelector('#nombre-archivo');
  if (input && preview) {
    let objectURL = null;
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (objectURL) URL.revokeObjectURL(objectURL);
      if (!file) return;
      if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        input.value = '';
        if (nombreArchivo) nombreArchivo.textContent = 'Usa una imagen JPG, PNG o WEBP.';
        return;
      }
      if (file.size > 2 * 1024 * 1024) {
        input.value = '';
        if (nombreArchivo) nombreArchivo.textContent = 'La imagen pesa más de 2 MB.';
        return;
      }
      objectURL = URL.createObjectURL(file);
      preview.replaceChildren();
      const imagen = document.createElement('img');
      imagen.src = objectURL;
      imagen.alt = 'Vista previa del nuevo avatar';
      preview.appendChild(imagen);
      if (nombreArchivo) nombreArchivo.textContent = file.name;
    });
    window.addEventListener('pagehide', () => { if (objectURL) URL.revokeObjectURL(objectURL); });
  }
  document.querySelectorAll('form[data-confirmar]').forEach(form => {
    form.addEventListener('submit', e => {
      if (!window.confirm(form.dataset.confirmar || '¿Confirmar?')) e.preventDefault();
    });
  });
  // Animación de contadores (solo presentación; no cambia la Cultura almacenada).
  const noAnimar = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!noAnimar) {
    document.querySelectorAll('[data-contador]').forEach(el => {
      const objetivo = Number(el.dataset.contador);
      if (!Number.isSafeInteger(objetivo) || objetivo < 0 || objetivo > 5000000) return;
      const duracion = 700;
      let inicio = null;
      const formato = new Intl.NumberFormat('es-MX');
      function tick(marca) {
        if (inicio === null) inicio = marca;
        const t = Math.min((marca - inicio) / duracion, 1);
        const easing = 1 - Math.pow(1 - t, 3);
        el.textContent = formato.format(Math.floor(objetivo * easing));
        if (t < 1) window.requestAnimationFrame(tick);
      }
      window.requestAnimationFrame(tick);
    });
  }
});
