<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('index.php#registro');
}
if (!verificarCsrf()) {
    mensajeFlash('registro', 'El formulario expiró. Actualiza la página e inténtalo de nuevo.');
    redirigir('index.php#registro');
}

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$correo = trim((string) ($_POST['correo'] ?? ''));
$contrasena = (string) ($_POST['contrasena'] ?? '');
$_SESSION['valor_registro'] = ['nombre' => $nombre, 'correo' => $correo];

// Permite nombres como "Juanito 4" y letras con tildes.
if (!preg_match('/\A[\p{L}\p{N}._ -]{3,30}\z/u', $nombre)) {
    mensajeFlash('registro', 'El nombre debe tener de 3 a 30 caracteres (letras, números, espacios, punto, guion o _).');
    redirigir('index.php#registro');
}
if (strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    mensajeFlash('registro', 'Escribe un correo electrónico válido.');
    redirigir('index.php#registro');
}
if (strlen($contrasena) < 8 || strlen($contrasena) > 72) {
    mensajeFlash('registro', 'La contraseña debe tener entre 8 y 72 bytes.');
    redirigir('index.php#registro');
}

try {
    $db = conexion();
    $consulta = $db->prepare(
        'SELECT id_usuario FROM usuarios WHERE nombre_usuario = :usuario OR correo = :correo LIMIT 1'
    );
    $consulta->execute(['usuario' => $nombre, 'correo' => $correo]);
    if ($consulta->fetch()) {
        mensajeFlash('registro', 'Ese nombre de usuario o correo ya está registrado.');
        redirigir('index.php#registro');
    }

    $hash = password_hash($contrasena, PASSWORD_DEFAULT);
    $insertar = $db->prepare(
        'INSERT INTO usuarios (nombre_usuario, correo, contrasena_hash, cultura)
         VALUES (:nombre, :correo, :hash, 0)'
    );
    $insertar->execute(['nombre' => $nombre, 'correo' => $correo, 'hash' => $hash]);

    iniciarSesionUsuario((int) $db->lastInsertId(), $nombre);
    unset($_SESSION['valor_registro']);
    redirigir('inicio.php');
} catch (PDOException $e) {
    error_log('[TRILLAL] Error de registro: ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        mensajeFlash('registro', 'El nombre de usuario o correo ya existe. Elige otro.');
    } else {
        mensajeFlash('registro', 'No se pudo crear la cuenta. Revisa la conexión o las columnas de usuarios.');
    }
} catch (Throwable $e) {
    error_log('[TRILLAL] Error de registro: ' . $e->getMessage());
    mensajeFlash('registro', 'No se pudo registrar la cuenta. Inténtalo nuevamente.');
}
redirigir('index.php#registro');
