<?php
declare(strict_types=1);

// Configuración segura de sesiones (necesita HTTPS en el sitio publicado).
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function conexion(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . '/db.php';
    $dsn = 'mysql:host=' . $config['host'] . ';dbname=' . $config['nombre'] . ';charset=utf8mb4';

    try {
        $pdo = new PDO($dsn, $config['usuario'], $config['contrasena'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        error_log('[TRILLAL] Error MySQL: ' . $e->getMessage());
        throw new RuntimeException('No se pudo conectar a MySQL. Revisa config/db.php y la base de datos de ByetHost.');
    }

    return $pdo;
}

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificarCsrf(): bool
{
    return isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
        && hash_equals(tokenCsrf(), $_POST['csrf_token']);
}

function redirigir(string $destino): void
{
    header('Location: ' . $destino, true, 303);
    exit;
}

function mensajeFlash(string $seccion, string $mensaje): void
{
    $_SESSION['flash'][$seccion] = $mensaje;
}

function iniciarSesionUsuario(int $idUsuario, string $nombreUsuario): void
{
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = $idUsuario;
    $_SESSION['nombre_usuario'] = $nombreUsuario;
    unset($_SESSION['fallos_login'], $_SESSION['bloqueado_hasta']);
}

function usuarioActual(): ?array
{
    if (empty($_SESSION['id_usuario'])) {
        return null;
    }

    $consulta = conexion()->prepare(
        'SELECT id_usuario, nombre_usuario, correo, avatar_url, cultura, estado
         FROM usuarios WHERE id_usuario = ? LIMIT 1'
    );
    $consulta->execute([(int) $_SESSION['id_usuario']]);
    $usuario = $consulta->fetch();

    if (!$usuario || $usuario['estado'] !== 'activo') {
        unset($_SESSION['id_usuario'], $_SESSION['nombre_usuario']);
        return null;
    }
    return $usuario;
}

function exigirSesion(): array
{
    if (empty($_SESSION['id_usuario'])) {
        redirigir('index.php');
    }
    $usuario = usuarioActual();
    if ($usuario === null) {
        redirigir('index.php');
    }
    return $usuario;
}
