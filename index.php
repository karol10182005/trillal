<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
if (!empty($_SESSION['id_usuario'])) {
    redirigir('inicio.php');
}

$flashLogin = $_SESSION['flash']['login'] ?? '';
$flashRegistro = $_SESSION['flash']['registro'] ?? '';
$valorLogin = $_SESSION['valor_login'] ?? '';
$valorRegistro = $_SESSION['valor_registro'] ?? ['nombre' => '', 'correo' => ''];
unset($_SESSION['flash'], $_SESSION['valor_login'], $_SESSION['valor_registro']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#141414">
    <meta name="description" content="Trillal: crea trivias, responde preguntas y gana Cultura.">
    <title>Trillal | Inicia sesión o crea tu cuenta</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body class="pagina-acceso">
    <div class="fondo-cuadricula" aria-hidden="true"></div>
    <main class="contenedor-acceso">
        <header class="encabezado-acceso">
            <img class="logo-acceso" src="assets/img/logo-trillal.png" alt="Logo de Trillal" width="112" height="106">
            <p class="etiqueta">LA COMUNIDAD DE LAS TRIVIAS</p>
            <h1>Demuestra lo que sabes<span class="punto-acento">.</span></h1>
            <p class="subtitulo">Juega, crea preguntas y acumula puntos de <strong>Cultura</strong>.</p>
        </header>

        <div class="paneles-acceso">
            <section class="panel-formulario" id="ingresar" aria-labelledby="titulo-login">
                <div class="panel-cabecera">
                    <span class="numero-panel">01 / ACCESO</span>
                    <h2 id="titulo-login">Bienvenido de nuevo</h2>
                    <p>Ingresa a la comunidad de Trillal.</p>
                </div>
                <?php if ($flashLogin !== ''): ?>
                    <div class="alerta-error" role="alert"><?= escapar($flashLogin) ?></div>
                <?php endif; ?>
                <form action="login.php" method="post" autocomplete="on" class="formulario">
                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                    <div class="grupo-campo">
                        <label for="usuario-login">Usuario o correo</label>
                        <input id="usuario-login" name="usuario" type="text" value="<?= escapar($valorLogin) ?>" placeholder="Ej. Juanito 4" autocomplete="username" required maxlength="150">
                    </div>
                    <div class="grupo-campo">
                        <label for="contrasena-login">Contraseña</label>
                        <div class="campo-contrasena">
                            <input id="contrasena-login" name="contrasena" type="password" placeholder="Tu contraseña" autocomplete="current-password" required>
                            <button type="button" class="alternar-contrasena" data-mostrar-contrasena="contrasena-login" aria-label="Mostrar contraseña">Ver</button>
                        </div>
                    </div>
                    <button class="boton-principal" type="submit">Iniciar sesión <span aria-hidden="true">↗</span></button>
                </form>
                <p class="pie-panel">¿Aún no tienes cuenta? <a href="#registro">Crear usuario</a></p>
            </section>

            <section class="panel-formulario panel-registro" id="registro" aria-labelledby="titulo-registro">
                <div class="panel-cabecera">
                    <span class="numero-panel">02 / NUEVA CUENTA</span>
                    <h2 id="titulo-registro">Únete a Trillal</h2>
                    <p>Tu camino hacia la Cultura comienza aquí.</p>
                </div>
                <?php if ($flashRegistro !== ''): ?>
                    <div class="alerta-error" role="alert"><?= escapar($flashRegistro) ?></div>
                <?php endif; ?>
                <form action="registro.php" method="post" autocomplete="on" class="formulario">
                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                    <div class="grupo-campo">
                        <label for="nombre-registro">Nombre de usuario</label>
                        <input id="nombre-registro" name="nombre" type="text" value="<?= escapar($valorRegistro['nombre'] ?? '') ?>" placeholder="Como quieres que te llamen" autocomplete="username" minlength="3" maxlength="30" required>
                    </div>
                    <div class="grupo-campo">
                        <label for="correo-registro">Correo electrónico</label>
                        <input id="correo-registro" name="correo" type="email" value="<?= escapar($valorRegistro['correo'] ?? '') ?>" placeholder="tu@correo.com" autocomplete="email" maxlength="150" required>
                    </div>
                    <div class="grupo-campo">
                        <label for="contrasena-registro">Contraseña</label>
                        <div class="campo-contrasena">
                            <input id="contrasena-registro" name="contrasena" type="password" placeholder="Mínimo 8 caracteres" autocomplete="new-password" minlength="8" maxlength="72" required>
                            <button type="button" class="alternar-contrasena" data-mostrar-contrasena="contrasena-registro" aria-label="Mostrar contraseña">Ver</button>
                        </div>
                        <small class="ayuda-campo">Utiliza una contraseña que no uses en otros sitios.</small>
                    </div>
                    <button class="boton-principal" type="submit">Crear usuario <span aria-hidden="true">↗</span></button>
                </form>
                <p class="pie-panel">¿Ya tienes una cuenta? <a href="#ingresar">Volver a iniciar sesión</a></p>
            </section>
        </div>
        <footer class="pie-acceso"><span>TRILLAL © <?= date('Y') ?></span><span>Aprender también es competir.</span></footer>
    </main>
</body>
</html>
