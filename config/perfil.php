<?php
declare(strict_types=1);
// Trillal · Perfil. Solo consultas de lectura; no se requieren nuevas tablas.

function perfilAvatarSeguro(?string $ruta): ?string
{
    if (!is_string($ruta)) return null;
    // Únicamente archivos creados en nuestro directorio de avatares.
    return preg_match('~\Auploads/avatars/[a-f0-9]{32}\.(?:jpg|png|webp)\z~D', $ruta) ? $ruta : null;
}

function perfilInicial(string $nombre): string
{
    if (preg_match('/^./us', $nombre, $m)) {
        return function_exists('mb_strtoupper') ? mb_strtoupper($m[0], 'UTF-8') : strtoupper($m[0]);
    }
    return '?';
}

function perfilFecha(?string $fecha, bool $utc = false): string
{
    if (!$fecha) return '—';
    try {
        $tz = $utc ? new DateTimeZone('UTC') : null;
        $d = new DateTimeImmutable($fecha, $tz);
        if ($utc) $d = $d->setTimezone(new DateTimeZone('America/Mexico_City'));
        $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
        return $d->format('d') . ' ' . $meses[(int)$d->format('n') - 1] . ' ' . $d->format('Y');
    } catch (Throwable $e) {
        return '—';
    }
}

function perfilSegundos($seg): string
{
    if ($seg === null || $seg === '') return '—';
    $s = max(0, (int)$seg);
    return sprintf('%d:%02d', intdiv($s, 60), $s % 60);
}

function perfilEstadisticas(PDO $db, array $usuario): array
{
    $uid = (int)$usuario['id_usuario'];
    // Sólo partidas realmente terminadas cuentan como jugadas y para precisión.
    $consulta = $db->prepare("SELECT COUNT(*) AS partidas,
          COALESCE(SUM(respuestas_correctas), 0) AS aciertos,
          COALESCE(MAX(puntuacion_total), 0) AS mejor_puntuacion,
          COALESCE(SUM(puntuacion_total), 0) AS puntos_juego
          FROM intentos WHERE id_usuario=? AND estado='finalizado'");
    $consulta->execute([$uid]);
    $base = $consulta->fetch() ?: [];

    $consulta = $db->prepare("SELECT COUNT(*) FROM respuestas r
       INNER JOIN intentos i ON i.id_intento=r.id_intento
       WHERE i.id_usuario=? AND i.estado='finalizado'");
    $consulta->execute([$uid]);
    $totalRespuestas = (int)$consulta->fetchColumn();
    // Los aciertos se cuentan desde respuestas para igualar denominador y evitar desajustes.
    $consulta = $db->prepare("SELECT COUNT(*) FROM respuestas r
       INNER JOIN intentos i ON i.id_intento=r.id_intento
       WHERE i.id_usuario=? AND i.estado='finalizado' AND r.es_correcta=1");
    $consulta->execute([$uid]);
    $aciertos = (int)$consulta->fetchColumn();

    $consulta = $db->prepare("SELECT COUNT(*) AS premios,
       COALESCE(SUM(CASE WHEN puesto=1 THEN 1 ELSE 0 END),0) AS oro,
       COALESCE(SUM(CASE WHEN puesto=2 THEN 1 ELSE 0 END),0) AS plata,
       COALESCE(SUM(CASE WHEN puesto=3 THEN 1 ELSE 0 END),0) AS bronce
       FROM movimientos_cultura WHERE id_usuario=?");
    $consulta->execute([$uid]);
    $premios = $consulta->fetch() ?: [];

    // Puntuación mayor primero; a igualdad desempata el identificador más antiguo.
    $consulta = $db->prepare("SELECT COUNT(*)+1 FROM usuarios
       WHERE estado='activo' AND (cultura>? OR (cultura=? AND id_usuario<?))");
    $consulta->execute([(int)$usuario['cultura'], (int)$usuario['cultura'], $uid]);
    $posicion = (int)$consulta->fetchColumn();

    return [
        'partidas' => (int)($base['partidas'] ?? 0),
        'aciertos' => $aciertos,
        'respondidas' => $totalRespuestas,
        'precision' => $totalRespuestas > 0 ? (int)round($aciertos*100/$totalRespuestas) : 0,
        'mejor_puntuacion' => (int)($base['mejor_puntuacion'] ?? 0),
        'premios' => (int)($premios['premios'] ?? 0),
        'oro' => (int)($premios['oro'] ?? 0),
        'plata' => (int)($premios['plata'] ?? 0),
        'bronce' => (int)($premios['bronce'] ?? 0),
        'posicion' => $posicion,
    ];
}

function perfilMisTrivias(PDO $db, int $uid): array
{
    $sql = "SELECT t.id_trivia, t.titulo, t.modalidad, t.estado,
        (SELECT COUNT(*) FROM preguntas p WHERE p.id_trivia=t.id_trivia) AS preguntas,
        (SELECT COUNT(*) FROM salas s WHERE s.id_trivia=t.id_trivia) AS salas
        FROM trivias t WHERE t.id_creador=? ORDER BY t.creada_en DESC, t.id_trivia DESC LIMIT 5";
    $q = $db->prepare($sql); $q->execute([$uid]);
    return $q->fetchAll();
}

function perfilHistorial(PDO $db, int $uid): array
{
    $q = $db->prepare("SELECT i.id_intento, i.id_sala, i.id_trivia, i.estado, i.puntuacion_total,
          i.respuestas_correctas, i.tiempo_total_segundos, i.iniciado_en, i.finalizado_en,
          t.titulo, t.modalidad, c.nombre AS categoria
          FROM intentos i
          INNER JOIN trivias t ON t.id_trivia=i.id_trivia
          INNER JOIN categorias c ON c.id_categoria=t.id_categoria
          WHERE i.id_usuario=? ORDER BY i.iniciado_en DESC, i.id_intento DESC LIMIT 12");
    $q->execute([$uid]);
    return $q->fetchAll();
}

function perfilPremios(PDO $db, int $uid): array
{
    $q = $db->prepare("SELECT m.puesto, m.cantidad, m.otorgado_en, c.tipo, t.titulo
        FROM movimientos_cultura m
        INNER JOIN cierres_cultura c ON c.id_cierre=m.id_cierre
        INNER JOIN trivias t ON t.id_trivia=c.id_trivia
        WHERE m.id_usuario=? ORDER BY m.otorgado_en DESC, m.id_movimiento DESC LIMIT 6");
    $q->execute([$uid]);
    return $q->fetchAll();
}

function perfilFlash(string $clave, string $texto): void
{
    mensajeFlash($clave, $texto);
}
