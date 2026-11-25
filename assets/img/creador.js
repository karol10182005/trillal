'use strict';
(function () {
  var opcionesModo = Array.prototype.slice.call(document.querySelectorAll('input[name="modalidad"]'));
  var campoClave = document.getElementById('campo-clave-privada');
  var clave = document.getElementById('clave-privada');
  var tiempo = document.getElementById('tiempo-minutos');
  function cambiarModo() {
    var elegido = opcionesModo.find(function (radio) { return radio.checked; });
    if (!elegido) return;
    var privado = elegido.value === 'privada';
    if (campoClave) campoClave.classList.toggle('oculto', !privado);
    if (clave) {
      clave.disabled = !privado || clave.closest('form').querySelector('input[name="titulo"]').disabled;
      // En edición de una trivia privada, la clave vacía significa conservar la existente.
      clave.required = privado && !clave.disabled && !campoClave.querySelector('small').textContent.includes('conservar');
    }
    if (tiempo && elegido.value === 'concurso') {
      var sinLimite = tiempo.querySelector('option[value="0"]');
      if (sinLimite) sinLimite.disabled = true;
      if (tiempo.value === '0') tiempo.value = '2';
    } else if (tiempo) {
      var libre = tiempo.querySelector('option[value="0"]');
      if (libre) libre.disabled = false;
    }
  }
  opcionesModo.forEach(function (radio) { radio.addEventListener('change', cambiarModo); });
  cambiarModo();

  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (campo) {
    var urlAnterior = null;
    campo.addEventListener('change', function () {
      var destino = document.getElementById(campo.dataset.preview);
      if (!destino) return;
      if (urlAnterior) URL.revokeObjectURL(urlAnterior);
      var archivo = campo.files && campo.files[0];
      if (!archivo) return;
      if (!/^image\/(jpeg|png|webp)$/.test(archivo.type) || archivo.size > 1572864) {
        window.alert('Selecciona una imagen JPG, PNG o WEBP de hasta 1.5 MB.');
        campo.value = '';
        return;
      }
      urlAnterior = URL.createObjectURL(archivo);
      destino.replaceChildren();
      var img = document.createElement('img');
      img.src = urlAnterior;
      img.alt = 'Vista previa de la imagen';
      destino.appendChild(img);
    });
  });

  document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
    form.addEventListener('submit', function (evento) {
      if (!window.confirm(form.dataset.confirmar)) evento.preventDefault();
    });
  });

  var editor = document.getElementById('editor-pregunta');
  if (editor) {
    editor.addEventListener('submit', function (evento) {
      var opciones = [1,2,3,4];
      var incompleta = opciones.some(function (i) {
        var texto = editor.querySelector('[name="opcion_texto_' + i + '"]');
        var archivo = editor.querySelector('[name="opcion_imagen_' + i + '"]');
        var existente = document.getElementById('imagen-opcion-' + i);
        var quitar = editor.querySelector('[name="quitar_imagen_opcion_' + i + '"]');
        var tieneImagenAnterior = existente && !!existente.querySelector('img') && !(quitar && quitar.checked);
        return !(texto && texto.value.trim()) && !(archivo && archivo.files.length) && !tieneImagenAnterior;
      });
      if (incompleta) {
        evento.preventDefault();
        window.alert('Las cuatro opciones necesitan texto, una imagen o ambos.');
      }
    });
  }
})();
