<?php
/**
 * Trillal: premiacion sin VISTAS ni TRIGGERS.
 * Compatible con PHP 7.4+ / 8.x y MySQL/MariaDB con InnoDB.
 *
 * Uso (desde un proceso PHP INTERNO, nunca desde peticiones publicas sin control):
 *   require 'trillal_premiar_cultura.php';
 *   $ganadores = premiarCulturaPrivada($pdo, 15); // id_sala
 *   $ganadores = premiarCulturaPublica($pdo, 8, '2026-11-22'); // trivia, fecha local YA FINALIZADA
 *
 * Retorna [ ['id_usuario'=>..., 'puesto'=>..., 'cultura'=>...], ... ]
 *
 * La aplicacion debe finalizar las salas y congelar los puntajes ANTES
 * de premiar. Para altas de salas, bloquear la fila de la trivia e impedir
 * nuevas salas de dias que ya tengan un cierre de Cultura.
 */
declare(strict_types=1);

/** @return array<int,array<string,int>> */
function premiarCulturaPrivada(PDO $pdo, int $idSala): array
{
    if ($idSala < 1) {
        throw new InvalidArgumentException('ID de sala no valido.');
    }
    return premiarCulturaTransaccion($pdo, 'privada_sala', null, $idSala, null);
}

/** @return array<int,array<string,int>> */
function premiarCulturaPublica(PDO $pdo, int $idTrivia, string $fechaCompetencia): array
{
    if ($idTrivia < 1) {
        throw new InvalidArgumentException('ID de trivia no valido.');
    }
    $zona = new DateTimeZone('America/Mexico_City');
    $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaCompetencia, $zona);
    if (!$dia || $dia->format('Y-m-d') !== $fechaCompetencia) {
        throw new InvalidArgumentException('Fecha esperada: AAAA-MM-DD.');
    }
    if ($dia >= new DateTimeImmutable('today', $zona)) {
        throw new LogicException('La premiacion publica se hace DESPUES de que termine el dia local.');
    }
    return premiarCulturaTransaccion($pdo, 'publica_diaria', $idTrivia, null, $fechaCompetencia);
}

/** @return array<int,array<string,int>> */
function premiarCulturaTransaccion(PDO $pdo, string $tipo, ?int $idTrivia, ?int $idSala, ?string $fecha): array
{
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();
    try {
        // Las dos modalidades bloquean la trivia para serializar sus cierres.
        if ($tipo === 'privada_sala') {
            $lookup = $pdo->prepare('SELECT id_trivia FROM salas WHERE id_sala = ?');
            $lookup->execute([$idSala]);
            $idTrivia = (int) $lookup->fetchColumn();
            if ($idTrivia < 1) {
                throw new LogicException('La sala no existe.');
            }
        }

        $lock = $pdo->prepare('SELECT modalidad FROM trivias WHERE id_trivia = ? FOR UPDATE');
        $lock->execute([$idTrivia]);
        $modalidad = $lock->fetchColumn();
        if ($modalidad === false) {
            throw new LogicException('La trivia no existe.');
        }

        if ($tipo === 'privada_sala') {
            if ($modalidad !== 'privada') {
                throw new LogicException('Esta sala no pertenece a una trivia privada.');
            }
            $sala = $pdo->prepare('SELECT estado FROM salas WHERE id_sala = ? AND id_trivia = ? FOR UPDATE');
            $sala->execute([$idSala, $idTrivia]);
            if ($sala->fetchColumn() !== 'finalizada') {
                throw new LogicException('Solo se premian salas privadas finalizadas.');
            }
            $cerrar = $pdo->prepare(
                'INSERT INTO cierres_cultura (tipo, id_trivia, id_sala) VALUES (\'privada_sala\', ?, ?)'
            );
            $cerrar->execute([$idTrivia, $idSala]);

            $ranking = $pdo->prepare(
                'SELECT i.id_usuario, i.puntuacion_total, i.tiempo_total_segundos, i.finalizado_en, i.id_intento
                 FROM intentos i
                 WHERE i.id_sala = ? AND i.estado = \'finalizado\'
                 ORDER BY i.puntuacion_total DESC,
                          (i.tiempo_total_segundos IS NULL) ASC,
                          i.tiempo_total_segundos ASC,
                          i.finalizado_en ASC, i.id_intento ASC'
            );
            $ranking->execute([$idSala]);
        } else {
            if ($modalidad === 'privada') {
                throw new LogicException('Esta trivia es privada.');
            }
            // No premiar un dia con salas pendientes.
            $pendientes = $pdo->prepare(
                'SELECT COUNT(*) FROM salas
                 WHERE id_trivia = ? AND fecha_competencia = ?
                   AND estado IN (\'programada\', \'en_curso\')'
            );
            $pendientes->execute([$idTrivia, $fecha]);
            if ((int) $pendientes->fetchColumn() > 0) {
                throw new LogicException('Aun existen salas de esa fecha sin terminar.');
            }
            $cerrar = $pdo->prepare(
                'INSERT INTO cierres_cultura (tipo, id_trivia, fecha_competencia)
                 VALUES (\'publica_diaria\', ?, ?)'
            );
            $cerrar->execute([$idTrivia, $fecha]);
            // Varias salas el mismo dia: el ORDER BY deja primero el MEJOR
            // intento de cada usuario, y el bucle lo toma solo una vez.
            $ranking = $pdo->prepare(
                'SELECT i.id_usuario, i.puntuacion_total, i.tiempo_total_segundos, i.finalizado_en, i.id_intento
                 FROM intentos i
                 INNER JOIN salas s ON s.id_sala = i.id_sala
                 WHERE s.id_trivia = ? AND s.fecha_competencia = ?
                   AND s.estado = \'finalizada\' AND i.estado = \'finalizado\'
                 ORDER BY i.puntuacion_total DESC,
                          (i.tiempo_total_segundos IS NULL) ASC,
                          i.tiempo_total_segundos ASC,
                          i.finalizado_en ASC, i.id_intento ASC'
            );
            $ranking->execute([$idTrivia, $fecha]);
        }

        $idCierre = (int) $pdo->lastInsertId();
        $guardar = $pdo->prepare(
            'INSERT INTO movimientos_cultura (id_cierre, id_usuario, puesto, cantidad)
             VALUES (?, ?, ?, ?)'
        );
        $sumar = $pdo->prepare(
            'UPDATE usuarios SET cultura = cultura + ? WHERE id_usuario = ?'
        );

        // Libera el cursor antes de hacer INSERT/UPDATE en la misma conexion.
        $resultados = $ranking->fetchAll(PDO::FETCH_ASSOC);
        $ranking->closeCursor();
        $ganadores = [];
        $usuariosVistos = [];
        // Se queda con hasta tres jugadores UNICOS por clasificacion.
        foreach ($resultados as $fila) {
            $usuario = (int) $fila['id_usuario'];
            if (isset($usuariosVistos[$usuario])) {
                continue;
            }
            $usuariosVistos[$usuario] = true;
            $puesto = count($ganadores) + 1;
            $puntos = ($puesto === 1) ? 25 : 20;

            $guardar->execute([$idCierre, $usuario, $puesto, $puntos]);
            $sumar->execute([$puntos, $usuario]);
            if ($sumar->rowCount() !== 1) {
                throw new RuntimeException('No se pudo actualizar la Cultura del usuario.');
            }
            $ganadores[] = ['id_usuario' => $usuario, 'puesto' => $puesto, 'cultura' => $puntos];
            if (count($ganadores) === 3) {
                break;
            }
        }
        $pdo->commit();
        return $ganadores;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Si ya existe un cierre, las restricciones UNIQUE lo bloquean;
        // no se duplican premios. Gestionar la excepcion desde el caller.
        throw $error;
    }
}
