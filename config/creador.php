<?php
declare(strict_types=1);

// Funciones compartidas del editor de trivias. Este archivo debe permanecer en config/.
const TRILLAL_LIMITE_TRIVIAS = 5;

/** Una trivia específica, solo si el usuario es su creador. */
function triviaPropia(PDO $db, int $idUsuario, int $idTrivia, bool $bloquear = false): ?array
{
    if ($idTrivia <= 0) return null;
    $sql = 'SELECT t.*, c.nombre AS categoria_nombre FROM trivias t '
        . 'INNER JOIN categorias c ON c.id_categoria = t.id_categoria '
        . 'WHERE t.id_creador = ? AND t.id_trivia = ? LIMIT 1' . ($bloquear ? ' FOR UPDATE' : '');
    $stmt = $db->prepare($sql);
    $stmt->execute([$idUsuario, $idTrivia]);
    return $stmt->fetch() ?: null;
}

/** Todas las trivias del jugador, nunca una trivia arbitraria. */
function misTrivias(PDO $db, int $idUsuario): array
{
    $stmt = $db->prepare('SELECT t.id_trivia, t.titulo, t.descripcion, t.modalidad, t.estado,
            c.nombre AS categoria_nombre,
            (SELECT COUNT(*) FROM preguntas p WHERE p.id_trivia=t.id_trivia) AS preguntas,
            (SELECT COUNT(*) FROM salas s WHERE s.id_trivia=t.id_trivia) AS salas
        FROM trivias t INNER JOIN categorias c ON c.id_categoria=t.id_categoria
        WHERE t.id_creador=? ORDER BY t.creada_en DESC, t.id_trivia DESC');
    $stmt->execute([$idUsuario]);
    return $stmt->fetchAll();
}

/** Bloqueo serializado por usuario para que dos pestañas no creen una sexta trivia. */
function bloquearCuentaCreadora(PDO $db, int $idUsuario): void
{
    $stmt = $db->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario=? FOR UPDATE');
    $stmt->execute([$idUsuario]);
    if (!$stmt->fetchColumn()) throw new InvalidArgumentException('Esta cuenta ya no está disponible.');
}

function cantidadTriviasCreadas(PDO $db, int $idUsuario): int
{
    $stmt = $db->prepare('SELECT COUNT(*) FROM trivias WHERE id_creador=?');
    $stmt->execute([$idUsuario]);
    return (int)$stmt->fetchColumn();
}

function preguntasDeTrivia(PDO $db, int $idTrivia): array
{
    $stmt = $db->prepare('SELECT id_pregunta, enunciado, imagen_url, orden, puntos FROM preguntas WHERE id_trivia = ? ORDER BY orden, id_pregunta');
    $stmt->execute([$idTrivia]);
    $preguntas = $stmt->fetchAll();
    if (!$preguntas) {
        return [];
    }

    $opciones = $db->prepare('SELECT id_opcion, texto, imagen_url, es_correcta, orden FROM opciones WHERE id_pregunta = ? ORDER BY orden');
    foreach ($preguntas as &$pregunta) {
        $opciones->execute([(int) $pregunta['id_pregunta']]);
        $pregunta['opciones'] = $opciones->fetchAll();
    }
    unset($pregunta);
    return $preguntas;
}

function solicitarBorrador(array $trivia): void
{
    if ($trivia['estado'] !== 'borrador') {
        throw new InvalidArgumentException('La trivia debe estar en borrador para hacer cambios. Puedes volver a borrador si aún nadie ha jugado.');
    }
}

/**
 * Guarda únicamente PNG, JPEG o WEBP válidos, con límite de 1.5 MB.
 * Los archivos se nombran de forma aleatoria: nunca se usa el nombre enviado.
 * Devuelve una ruta relativa o NULL si no se eligió archivo.
 */
function subirImagenTrivia(string $campo, array &$nuevos): ?string
{
    if (!isset($_FILES[$campo]) || !is_array($_FILES[$campo])) {
        return null;
    }
    $archivo = $_FILES[$campo];
    $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK || is_array($archivo['tmp_name'] ?? null)) {
        throw new InvalidArgumentException('No se pudo subir una imagen. Comprueba el tamaño y vuelve a intentarlo.');
    }
    if ((int) $archivo['size'] > 1572864 || (int) $archivo['size'] <= 0) {
        throw new InvalidArgumentException('Cada imagen debe pesar como máximo 1.5 MB.');
    }
    $temporal = (string) $archivo['tmp_name'];
    if (!is_uploaded_file($temporal)) {
        throw new InvalidArgumentException('El archivo de imagen no es válido.');
    }
    $info = @getimagesize($temporal);
    $extensiones = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($extensiones[$info[2]]) || $info[0] > 5000 || $info[1] > 5000) {
        throw new InvalidArgumentException('Utiliza imágenes JPG, PNG o WEBP de hasta 5000 píxeles por lado.');
    }
    // Confirmación adicional del tipo de archivo, si está disponible.
    if (class_exists('finfo')) {
        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($temporal);
        $permitidos = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png', IMAGETYPE_WEBP => 'image/webp'];
        if ($tipo !== $permitidos[$info[2]]) {
            throw new InvalidArgumentException('El contenido de la imagen no coincide con su formato.');
        }
    }

    $directorio = dirname(__DIR__) . '/uploads/trivias';
    if (!is_dir($directorio) && !@mkdir($directorio, 0755, true) && !is_dir($directorio)) {
        throw new RuntimeException('No se pudo crear uploads/trivias. Revisa sus permisos.');
    }
    $nombre = bin2hex(random_bytes(16)) . '.' . $extensiones[$info[2]];
    $destino = $directorio . '/' . $nombre;
    if (!move_uploaded_file($temporal, $destino)) {
        throw new RuntimeException('No se pudo guardar la imagen. Revisa los permisos de uploads/trivias.');
    }
    @chmod($destino, 0644);
    $ruta = 'uploads/trivias/' . $nombre;
    $nuevos[] = $ruta;
    return $ruta;
}

function borrarImagenTrivia(?string $ruta): void
{
    if ($ruta === null || !preg_match('~^uploads/trivias/[a-f0-9]{32}\.(jpg|png|webp)$~D', $ruta)) {
        return;
    }
    $archivo = dirname(__DIR__) . '/' . $ruta;
    if (is_file($archivo)) {
        @unlink($archivo);
    }
}

function recortarTexto(string $texto, int $limite, string $campo): string
{
    $texto = trim($texto);
    if ($texto === '' || strlen($texto) > $limite) {
        throw new InvalidArgumentException($campo . ' es obligatorio y debe tener un máximo de ' . $limite . ' caracteres.');
    }
    return $texto;
}
