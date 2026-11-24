<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require __DIR__.'/config/perfil.php';

$usuario = exigirSesion();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirigir('perfil.php');
if (!verificarCsrf()) {
    perfilFlash('perfil_error', 'La sesión del formulario caducó. Recarga e inténtalo otra vez.');
    redirigir('perfil.php#ajustes');
}
$id = (int)$usuario['id_usuario'];
$accion = (string)($_POST['accion'] ?? '');
$db = conexion();
try {
    if ($accion === 'datos') {
        $nombre = trim((string)($_POST['nombre_usuario'] ?? ''));
        $correo = trim((string)($_POST['correo'] ?? ''));
        $clave = (string)($_POST['contrasena_actual'] ?? '');
        if (!preg_match('/\A[\p{L}\p{N}._ -]{3,30}\z/u', $nombre)) {
            throw new DomainException('El nombre necesita de 3 a 30 caracteres (letras, números, espacios, punto, guion o _).');
        }
        if (strlen($correo) > 150 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Introduce un correo electrónico válido.');
        }
        $q = $db->prepare('SELECT contrasena_hash FROM usuarios WHERE id_usuario=?');
        $q->execute([$id]);
        $hash = $q->fetchColumn();
        if (!$hash || !password_verify($clave, (string)$hash)) {
            throw new DomainException('Escribe tu contraseña actual para guardar los cambios.');
        }
        $q = $db->prepare('SELECT id_usuario FROM usuarios WHERE (nombre_usuario=? OR correo=?) AND id_usuario<>? LIMIT 1');
        $q->execute([$nombre, $correo, $id]);
        if ($q->fetch()) throw new DomainException('Ese nombre o correo ya pertenece a otra cuenta.');

        $q = $db->prepare('UPDATE usuarios SET nombre_usuario=?, correo=? WHERE id_usuario=? AND estado=\'activo\'');
        $q->execute([$nombre, $correo, $id]);
        $_SESSION['nombre_usuario'] = $nombre;
        perfilFlash('perfil_ok', 'Tus datos de cuenta se guardaron correctamente.');
        redirigir('perfil.php#ajustes');
    }

    if ($accion === 'clave') {
        $actual = (string)($_POST['contrasena_actual'] ?? '');
        $nueva = (string)($_POST['contrasena_nueva'] ?? '');
        $repetida = (string)($_POST['contrasena_confirmar'] ?? '');
        if (strlen($nueva) < 8 || strlen($nueva) > 72) throw new DomainException('La nueva contraseña debe contener entre 8 y 72 bytes.');
        if ($nueva !== $repetida) throw new DomainException('Las nuevas contraseñas no coinciden.');
        $q = $db->prepare('SELECT contrasena_hash FROM usuarios WHERE id_usuario=?');
        $q->execute([$id]);
        $hash = $q->fetchColumn();
        if (!$hash || !password_verify($actual, (string)$hash)) throw new DomainException('La contraseña actual es incorrecta.');
        if (password_verify($nueva, (string)$hash)) throw new DomainException('Elige una contraseña distinta a la anterior.');
        $q = $db->prepare('UPDATE usuarios SET contrasena_hash=? WHERE id_usuario=?');
        $q->execute([password_hash($nueva, PASSWORD_DEFAULT), $id]);
        session_regenerate_id(true);
        perfilFlash('perfil_ok', 'Contraseña actualizada. Tu sesión actual continúa abierta.');
        redirigir('perfil.php#seguridad');
    }

    if ($accion === 'avatar') {
        if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) throw new DomainException('Selecciona una imagen primero.');
        $f = $_FILES['avatar'];
        if ((int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new DomainException('No se pudo recibir la imagen. Inténtalo con un archivo más pequeño.');
        if (!is_uploaded_file((string)$f['tmp_name'])) throw new DomainException('El archivo no es una subida válida.');
        if ((int)$f['size'] <= 0 || (int)$f['size'] > 2 * 1024 * 1024) throw new DomainException('Tu avatar debe pesar como máximo 2 MB.');
        $tmp = (string)$f['tmp_name'];
        $info = @getimagesize($tmp);
        if (!is_array($info) || (int)$info[0] < 32 || (int)$info[1] < 32 || (int)$info[0] > 4096 || (int)$info[1] > 4096 || (int)$info[0] * (int)$info[1] > 12000000) {
            throw new DomainException('La imagen no es válida o sus dimensiones son demasiado grandes.');
        }
        $tipos = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
        $mime = function_exists('finfo_open') ? (new finfo(FILEINFO_MIME_TYPE))->file($tmp) : ($info['mime'] ?? '');
        if (!isset($tipos[$mime]) || ($info['mime'] ?? '') !== $mime) throw new DomainException('Solo se aceptan imágenes JPG, PNG o WEBP.');
        $carpeta = __DIR__.'/uploads/avatars';
        if (!is_dir($carpeta) || !is_writable($carpeta)) throw new DomainException('La carpeta uploads/avatars no tiene permiso de escritura en el servidor.');
        $nombreArchivo = bin2hex(random_bytes(16)).'.'.$tipos[$mime];
        $ruta = 'uploads/avatars/'.$nombreArchivo;
        if (!move_uploaded_file($tmp, __DIR__.'/'.$ruta)) throw new RuntimeException('No fue posible guardar el avatar en el servidor.');
        try {
            $q = $db->prepare('UPDATE usuarios SET avatar_url=? WHERE id_usuario=?');
            $q->execute([$ruta, $id]);
        } catch (Throwable $e) {
            @unlink(__DIR__.'/'.$ruta);
            throw $e;
        }
        $anterior = perfilAvatarSeguro($usuario['avatar_url'] ?? null);
        if ($anterior && $anterior !== $ruta && is_file(__DIR__.'/'.$anterior)) @unlink(__DIR__.'/'.$anterior);
        perfilFlash('perfil_ok', 'Tu foto de perfil ya está actualizada.');
        redirigir('perfil.php#avatar');
    }

    if ($accion === 'quitar_avatar') {
        $anterior = perfilAvatarSeguro($usuario['avatar_url'] ?? null);
        $q = $db->prepare('UPDATE usuarios SET avatar_url=NULL WHERE id_usuario=?');
        $q->execute([$id]);
        if ($anterior && is_file(__DIR__.'/'.$anterior)) @unlink(__DIR__.'/'.$anterior);
        perfilFlash('perfil_ok', 'Eliminaste tu foto. Ahora aparece tu inicial.');
        redirigir('perfil.php#avatar');
    }

    throw new DomainException('La acción solicitada no existe.');
} catch (DomainException $e) {
    perfilFlash('perfil_error', $e->getMessage());
} catch (PDOException $e) {
    error_log('[TRILLAL / PERFIL] MySQL: '.$e->getMessage());
    perfilFlash('perfil_error', $e->getCode()==='23000' ? 'El usuario o correo ya existe.' : 'No se pudo guardar. Revisa la conexión e inténtalo otra vez.');
} catch (Throwable $e) {
    error_log('[TRILLAL / PERFIL] Error: '.$e->getMessage());
    perfilFlash('perfil_error', 'Ocurrió un error al procesar el cambio. Revisa los permisos del servidor.');
}
redirigir('perfil.php#ajustes');
