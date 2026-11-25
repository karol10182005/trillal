'use strict';

document.querySelectorAll('[data-mostrar-contrasena]').forEach(function (boton) {
  boton.addEventListener('click', function () {
    var input = document.getElementById(boton.getAttribute('data-mostrar-contrasena'));
    if (!input) return;
    var visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    boton.textContent = visible ? 'Ver' : 'Ocultar';
    boton.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
  });
});

// Al entrar desde el enlace "Crear usuario" se destaca el formulario correcto.
function resaltarFormulario() {
  document.querySelectorAll('.panel-formulario').forEach(function (panel) {
    panel.classList.toggle('panel-destacado', '#' + panel.id === window.location.hash);
  });
}
window.addEventListener('hashchange', resaltarFormulario);
resaltarFormulario();
