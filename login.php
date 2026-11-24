<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php');
}
if (!verificarCsrf()) {
    mensajeFlash('login', 'La sesión del formulario expiró. Intenta de nuevo.');
    redirigir('index.php#ingresar');
}

$identificador = trim((string) ($_POST['usuario'] ?? ''));
$contrasena = (string) ($_POST['contrasena'] ?? '');
$_SESSION['valor_login'] = $identificador;

if ($identificador === '' || $contrasena === '') {
    mensajeFlash('login', 'Escribe tu usuario y contraseña.');
    redirigir('index.php#ingresar');
}

// Límite básico por sesión. Para producción puede complementarse con límites por IP.
if (time() < (int) ($_SESSION['bloqueado_hasta'] ?? 0)) {
    mensajeFlash('login', 'Demasiados intentos. Vuelve a probar en unos minutos.');
    redirigir('index.php#ingresar');
}

try {
    $stmt = conexion()->prepare(
        'SELECT id_usuario, nombre_usuario, contrasena_hash, estado
         FROM usuarios
         WHERE nombre_usuario = :usuario OR correo = :correo
         LIMIT 1'
    );
    $stmt->execute(['usuario' => $identificador, 'correo' => $identificador]);
    $usuario = $stmt->fetch();

    if ($usuario && $usuario['estado'] === 'activo'
        && password_verify($contrasena, $usuario['contrasena_hash'])) {
        if (password_needs_rehash($usuario['contrasena_hash'], PASSWORD_DEFAULT)) {
            $hashNuevo = password_hash($contrasena, PASSWORD_DEFAULT);
            $update = conexion()->prepare('UPDATE usuarios SET contrasena_hash = ? WHERE id_usuario = ?');
            $update->execute([$hashNuevo, (int) $usuario['id_usuario']]);
        }
        iniciarSesionUsuario((int) $usuario['id_usuario'], $usuario['nombre_usuario']);
        unset($_SESSION['valor_login']);
        redirigir('inicio.php');
    }

    $_SESSION['fallos_login'] = (int) ($_SESSION['fallos_login'] ?? 0) + 1;
    if ($_SESSION['fallos_login'] >= 5) {
        $_SESSION['bloqueado_hasta'] = time() + 600;
        $_SESSION['fallos_login'] = 0;
        mensajeFlash('login', 'Demasiados intentos fallidos. Prueba de nuevo en 10 minutos.');
    } else {
        mensajeFlash('login', 'Usuario, correo o contraseña incorrectos.');
    }
} catch (Throwable $e) {
    error_log('[TRILLAL] Error en login: ' . $e->getMessage());
    mensajeFlash('login', 'No se pudo iniciar sesión. Revisa la conexión a MySQL.');
}
redirigir('index.php#ingresar');
