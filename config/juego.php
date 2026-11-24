<?php
declare(strict_types=1);
/** Trillal / motor de salas y rondas. No crea tablas, vistas ni triggers. */
require_once __DIR__ . '/cultura.php';

function jZona(): DateTimeZone { return new DateTimeZone('America/Mexico_City'); }
function jUTC(): DateTimeZone { return new DateTimeZone('UTC'); }
function jFecha(string $utc): int { return (new DateTimeImmutable($utc, jUTC()))->getTimestamp(); }
function jSqlUTC(int $ts): string { return gmdate('Y-m-d H:i:s', $ts); }
function jDiaLocal(int $ts): string { return (new DateTimeImmutable('@' . $ts))->setTimezone(jZona())->format('Y-m-d'); }
function jLocalForm(int $ts): string { return (new DateTimeImmutable('@' . $ts))->setTimezone(jZona())->format('Y-m-d\TH:i'); }
function jError(string $mensaje): void { throw new InvalidArgumentException($mensaje); }
function jEntero($valor, int $min, int $max, string $etiqueta): int {
    $numero = filter_var($valor, FILTER_VALIDATE_INT);
    if ($numero === false || $numero === null || $numero < $min || $numero > $max) jError($etiqueta . ' inválido.');
    return (int)$numero;
}
function jTrivia(PDO $db, int $id): ?array {
    $q = $db->prepare('SELECT t.*, c.nombre AS categoria, u.nombre_usuario AS autor FROM trivias t INNER JOIN categorias c ON c.id_categoria=t.id_categoria INNER JOIN usuarios u ON u.id_usuario=t.id_creador WHERE t.id_trivia=? LIMIT 1');
    $q->execute([$id]); return $q->fetch() ?: null;
}
function jAcceso(PDO $db, array $t, int $uid): bool {
    if ($t['modalidad'] !== 'privada' || (int)$t['id_creador'] === $uid) return true;
    $q = $db->prepare('SELECT 1 FROM accesos_privados WHERE id_trivia=? AND id_usuario=?');
    $q->execute([(int)$t['id_trivia'], $uid]); return (bool)$q->fetchColumn();
}
function jPreguntas(PDO $db, int $idTrivia): array {
    $q = $db->prepare('SELECT id_pregunta, enunciado, imagen_url, orden, puntos FROM preguntas WHERE id_trivia=? ORDER BY orden, id_pregunta');
    $q->execute([$idTrivia]); return $q->fetchAll();
}
/** Reloj de partida: normal usa el configurado; rápida exactamente la mitad (redondeando hacia arriba). */
function jDuracion(array $t, int $total, string $tipo = 'normal'): int {
    $config = (int)($t['tiempo_limite_segundos'] ?? 0);
    $normal = $config > 0 ? $config : 120;
    // Un límite mínimo evita divisiones por cero y rondas imposibles.
    $normal = max(20, $normal);
    return $tipo === 'rapida' ? max(10, (int)ceil($normal / 2)) : $normal;
}
function jSala(PDO $db, int $idSala): ?array {
    $q = $db->prepare('SELECT s.*, t.titulo, t.modalidad, t.id_creador, t.estado AS estado_trivia, t.id_categoria FROM salas s INNER JOIN trivias t ON t.id_trivia=s.id_trivia WHERE s.id_sala=?');
    $q->execute([$idSala]); return $q->fetch() ?: null;
}
function jInscrito(PDO $db, int $idSala, int $uid): bool {
    $q = $db->prepare("SELECT 1 FROM inscripciones_sala WHERE id_sala=? AND id_usuario=? AND estado='inscrito'");
    $q->execute([$idSala,$uid]); return (bool)$q->fetchColumn();
}
function jCuentaInscritos(PDO $db, int $idSala): int {
    $q = $db->prepare("SELECT COUNT(*) FROM inscripciones_sala WHERE id_sala=? AND estado='inscrito'");
    $q->execute([$idSala]); return (int)$q->fetchColumn();
}
function jPremioPrivado(PDO $db, int $idSala): void {
    $q=$db->prepare("SELECT 1 FROM cierres_cultura WHERE tipo='privada_sala' AND id_sala=? LIMIT 1");
    $q->execute([$idSala]);
    if ($q->fetchColumn()) return;
    try { premiarCulturaPrivada($db, $idSala); }
    catch (PDOException $e) {
        // Premios ya procesados: una clave UNIQUE evita una segunda entrega.
        if ($e->getCode() !== '23000') error_log('[Trillal Cultura privada] ' . $e->getMessage());
    }
    catch (Throwable $e) { error_log('[Trillal Cultura privada] ' . $e->getMessage()); }
}
/**
 * Inicio/finalización determinados por la hora del SERVIDOR, no la del navegador.
 * El inicio en sala se fija a inicio_programado_en, incluso si no había jugadores conectados.
 * El primer GET posterior al inicio/fin dispara la transición sin necesitar cron.
 */
function jSincronizarSala(PDO $db, int $idSala): ?array {
    // Ruta rápida durante la espera/partida: evita bloquear la sala en cada sondeo.
    $previa = jSala($db,$idSala);
    if (!$previa) return null;
    $ahoraPrevia=time();
    $inicioPrevia=jFecha($previa['inicio_programado_en']);
    if (($previa['estado']==='programada' && $ahoraPrevia<$inicioPrevia)
      || ($previa['estado']==='en_curso' && $ahoraPrevia<$inicioPrevia+(int)$previa['duracion_segundos'])) {
        return $previa;
    }
    if ($previa['estado']==='finalizada' || $previa['estado']==='cancelada') {
        if ($previa['estado']==='finalizada' && $previa['modalidad']==='privada') jPremioPrivado($db,$idSala);
        return $previa;
    }
    $db->beginTransaction();
    try {
        $q = $db->prepare('SELECT * FROM salas WHERE id_sala=? FOR UPDATE');
        $q->execute([$idSala]); $s = $q->fetch();
        if (!$s) { $db->commit(); return null; }
        $ahora = time(); $inicio = jFecha($s['inicio_programado_en']);
        if ($s['estado'] === 'programada' && $ahora >= $inicio) {
            $n = jCuentaInscritos($db, $idSala);
            if ($n === 0) {
                $db->prepare("UPDATE salas SET estado='cancelada',finalizada_en=? WHERE id_sala=?")->execute([jSqlUTC($ahora), $idSala]);
                $s['estado'] = 'cancelada';
            } else {
                $db->prepare("UPDATE salas SET estado='en_curso',inicio_real_en=? WHERE id_sala=?")->execute([jSqlUTC($inicio),$idSala]);
                // Crear un intento por inscrito. El número es consecutivo para
                // compatibilidad con un índice histórico que quizá aún exista.
                $qIn = $db->prepare("SELECT id_usuario FROM inscripciones_sala WHERE id_sala=? AND estado='inscrito'");
                $qIn->execute([$idSala]);
                $inscritos = $qIn->fetchAll(PDO::FETCH_COLUMN);
                $qN = $db->prepare('SELECT COALESCE(MAX(numero_intento),0)+1 FROM intentos WHERE id_trivia=? AND id_usuario=?');
                $qI = $db->prepare("INSERT INTO intentos
                    (id_trivia,id_sala,id_usuario,numero_intento,estado,puntuacion_total,respuestas_correctas,tiempo_total_segundos,iniciado_en)
                    VALUES (?,?,?,?,'en_curso',0,0,0,?)");
                foreach ($inscritos as $inscritoUid) {
                    $qN->execute([(int)$s['id_trivia'],(int)$inscritoUid]);
                    $num = (int)$qN->fetchColumn();
                    if ($num > 65535) jError('Se alcanzó el máximo de intentos.');
                    $qI->execute([(int)$s['id_trivia'],$idSala,(int)$inscritoUid,$num,jSqlUTC($inicio)]);
                }
                $s['estado'] = 'en_curso';
            }
        }
        if ($s['estado'] === 'en_curso' && $ahora >= $inicio + (int)$s['duracion_segundos']) {
            $totalPreguntas = count(jPreguntas($db,(int)$s['id_trivia']));
            // Desempate: segundo dedicado a responder + penalización por preguntas sin contestar.
            $durPregunta = $totalPreguntas > 0 ? (int)ceil((int)$s['duracion_segundos'] / $totalPreguntas) : 0;
            $db->prepare("UPDATE intentos i INNER JOIN salas sala ON sala.id_sala=i.id_sala
                    SET i.tiempo_total_segundos = COALESCE(i.tiempo_total_segundos,0) +
                    (? - (SELECT COUNT(*) FROM respuestas r WHERE r.id_intento=i.id_intento)) * ?,
                    i.puntuacion_total = i.puntuacion_total + CASE WHEN sala.tipo_partida='rapida' THEN 10 ELSE 0 END,
                    i.estado='finalizado', i.finalizado_en=?
                    WHERE i.id_sala=? AND i.estado='en_curso'")
                ->execute([$totalPreguntas,$durPregunta,jSqlUTC($inicio + (int)$s['duracion_segundos']),$idSala]);
            $db->prepare("UPDATE salas SET estado='finalizada',finalizada_en=? WHERE id_sala=?")->execute([jSqlUTC($inicio + (int)$s['duracion_segundos']),$idSala]);
            $s['estado'] = 'finalizada';
        }
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    // Si la sala ya se finalizó previamente pero no se pudo premiar, reintentar de forma idempotente.
    $sal = jSala($db,$idSala);
    if ($sal && $sal['estado'] === 'finalizada' && $sal['modalidad'] === 'privada') jPremioPrivado($db,$idSala);
    return $sal;
}
/** Limpieza eventual, útil en hostings sin cron: se ejecuta al visitar Jugar. */
function jMantenimiento(PDO $db): void {
    try {
        $q = $db->prepare("SELECT id_sala FROM salas WHERE estado IN ('programada','en_curso') AND inicio_programado_en < ? ORDER BY inicio_programado_en ASC LIMIT 20");
        $q->execute([jSqlUTC(time() - 60)]);
        foreach ($q->fetchAll() as $s) jSincronizarSala($db,(int)$s['id_sala']);
        $hoy = jDiaLocal(time());
        $q = $db->prepare("SELECT DISTINCT s.id_trivia, s.fecha_competencia FROM salas s INNER JOIN trivias t ON t.id_trivia=s.id_trivia
            WHERE t.modalidad <> 'privada' AND s.fecha_competencia < ?
             AND NOT EXISTS(SELECT 1 FROM cierres_cultura c WHERE c.id_trivia=s.id_trivia AND c.fecha_competencia=s.fecha_competencia)
            ORDER BY s.fecha_competencia DESC LIMIT 12");
        $q->execute([$hoy]);
        foreach ($q->fetchAll() as $r) {
            try { premiarCulturaPublica($db,(int)$r['id_trivia'],$r['fecha_competencia']); }
            catch (PDOException $e) { if ($e->getCode() !== '23000') error_log('[Trillal Cultura pública] ' . $e->getMessage()); }
            catch (Throwable $e) { error_log('[Trillal cierre público] ' . $e->getMessage()); }
        }
    } catch (Throwable $e) { error_log('[Trillal mantenimiento] ' . $e->getMessage()); }
}
function jPosicion(int $elapsed, int $duracion, int $total): int {
    if ($total < 1) return 0;
    return min($total-1, (int)floor(max(0,$elapsed)*$total/$duracion));
}
function jFinPregunta(int $i, int $duracion, int $total, int $inicio): int {
    return $inicio + (int)ceil((($i + 1)*$duracion)/$total);
}
/** Clasificación de sala en vivo y final, nunca toma datos del navegador. */
function jRanking(PDO $db, int $idSala): array {
    // El orden coincide con la regla usada en config/cultura.php para entregar premios.
    $q = $db->prepare("SELECT i.id_usuario,u.nombre_usuario,i.puntuacion_total,i.respuestas_correctas,i.tiempo_total_segundos,
         i.estado FROM intentos i INNER JOIN usuarios u ON u.id_usuario=i.id_usuario
         WHERE i.id_sala=? ORDER BY i.puntuacion_total DESC,
         (i.tiempo_total_segundos IS NULL) ASC,i.tiempo_total_segundos ASC,i.finalizado_en ASC,i.id_intento ASC LIMIT 80");
    $q->execute([$idSala]);
    $out=[]; foreach($q->fetchAll() as $n=>$r) $out[] = ['puesto'=>$n+1,'id_usuario'=>(int)$r['id_usuario'],
       'nombre'=>$r['nombre_usuario'],'puntos'=>(int)$r['puntuacion_total'],'correctas'=>(int)$r['respuestas_correctas'],'estado'=>$r['estado']];
    return $out;
}
/** Puntos de cultura reales entregados en el cierre que corresponda. */
function jPremioUsuario(PDO $db, array $s, int $uid): int {
    if ($s['modalidad'] === 'privada') {
        $q=$db->prepare("SELECT m.cantidad FROM movimientos_cultura m INNER JOIN cierres_cultura c ON c.id_cierre=m.id_cierre
            WHERE c.tipo='privada_sala' AND c.id_sala=? AND m.id_usuario=? LIMIT 1");
        $q->execute([(int)$s['id_sala'],$uid]);
    } else {
        $q=$db->prepare("SELECT m.cantidad FROM movimientos_cultura m INNER JOIN cierres_cultura c ON c.id_cierre=m.id_cierre
            WHERE c.tipo='publica_diaria' AND c.id_trivia=? AND c.fecha_competencia=? AND m.id_usuario=? LIMIT 1");
        $q->execute([(int)$s['id_trivia'],$s['fecha_competencia'],$uid]);
    }
    return (int)($q->fetchColumn() ?: 0);
}

/** Recompensa del día anterior, si ya terminaron todas las salas de esa fecha. */
function jPremiarPublicaSiLista(PDO $db, array $s): void {
    if ($s['modalidad']==='privada' || $s['fecha_competencia']>=jDiaLocal(time())) return;
    $q=$db->prepare('SELECT 1 FROM cierres_cultura WHERE id_trivia=? AND fecha_competencia=?');
    $q->execute([(int)$s['id_trivia'],$s['fecha_competencia']]);
    if($q->fetchColumn()) return;
    $q=$db->prepare("SELECT 1 FROM salas WHERE id_trivia=? AND fecha_competencia=? AND estado IN ('programada','en_curso') LIMIT 1");
    $q->execute([(int)$s['id_trivia'],$s['fecha_competencia']]);
    if($q->fetchColumn()) return;
    try { premiarCulturaPublica($db,(int)$s['id_trivia'],$s['fecha_competencia']); }
    catch(PDOException $e){if($e->getCode()!=='23000')error_log('[Trillal cultura pública] '.$e->getMessage());}
    catch(Throwable $e){error_log('[Trillal cultura pública] '.$e->getMessage());}
}
/** Clasificación pública diaria basada en las mejores partidas ya terminadas. */
function jRankingDia(PDO $db, int $idTrivia, string $dia): array {
    $q=$db->prepare("SELECT i.id_usuario,u.nombre_usuario,i.puntuacion_total,i.tiempo_total_segundos,i.id_intento
        FROM intentos i INNER JOIN usuarios u ON u.id_usuario=i.id_usuario
        INNER JOIN salas s ON s.id_sala=i.id_sala
        WHERE s.id_trivia=? AND s.fecha_competencia=? AND s.estado='finalizada' AND i.estado='finalizado'
        ORDER BY i.puntuacion_total DESC,(i.tiempo_total_segundos IS NULL) ASC,
        i.tiempo_total_segundos ASC,i.finalizado_en ASC,i.id_intento ASC LIMIT 1000");
    $q->execute([$idTrivia,$dia]);
    $salida=[];$vistos=[];
    foreach($q->fetchAll() as $r){
        if(isset($vistos[$r['id_usuario']]))continue;
        $vistos[$r['id_usuario']]=true;
        $salida[]=['puesto'=>count($salida)+1,'id_usuario'=>(int)$r['id_usuario'],
          'nombre'=>$r['nombre_usuario'],'puntos'=>(int)$r['puntuacion_total']];
        if(count($salida)>=20)break;
    }
    return $salida;
}
