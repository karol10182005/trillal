<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/creador.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('crear.php');
}
$usuario = exigirSesion();
if (!verificarCsrf()) {
    mensajeFlash('crear_error', 'La sesión del formulario caducó. Actualiza la página y vuelve a intentarlo.');
    redirigir('crear.php');
}

$db = conexion();
$idUsuario = (int) $usuario['id_usuario'];
$accion = (string) ($_POST['accion'] ?? '');
$idTriviaSolicitada = filter_var($_POST['id_trivia'] ?? 0, FILTER_VALIDATE_INT);
$idTriviaSolicitada = $idTriviaSolicitada === false ? -1 : (int)$idTriviaSolicitada;
$idTriviaDestino = $idTriviaSolicitada;

$nuevosArchivos = [];
$archivosAntiguos = [];

try {
    if (!in_array($accion, ['guardar_trivia', 'guardar_pregunta', 'eliminar_pregunta', 'publicar', 'volver_borrador'], true)) {
        throw new InvalidArgumentException('Acción desconocida.');
    }
    if ($idTriviaSolicitada < 0) throw new InvalidArgumentException('Selecciona una trivia válida.');
    $db->beginTransaction();
    // El bloqueo del usuario hace atómico el control de cinco trivias por cuenta.
    bloquearCuentaCreadora($db, $idUsuario);
    $trivia = triviaPropia($db, $idUsuario, $idTriviaSolicitada, true);
    if ($idTriviaSolicitada > 0 && !$trivia) {
        throw new InvalidArgumentException('La trivia solicitada no existe o no pertenece a tu cuenta.');
    }
    if ($accion !== 'guardar_trivia' && !$trivia) {
        throw new InvalidArgumentException('Primero selecciona una trivia de tu lista.');
    }


    if ($accion === 'guardar_trivia') {
        if ($trivia) solicitarBorrador($trivia);
        $titulo = recortarTexto((string) ($_POST['titulo'] ?? ''), 150, 'El título');
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        if (strlen($descripcion) > 1500) {
            throw new InvalidArgumentException('La descripción puede tener hasta 1500 caracteres.');
        }
        $idCategoria = filter_var($_POST['id_categoria'] ?? null, FILTER_VALIDATE_INT);
        if (!$idCategoria || $idCategoria < 1) {
            throw new InvalidArgumentException('Selecciona una categoría.');
        }
        $verificar = $db->prepare('SELECT 1 FROM categorias WHERE id_categoria = ?');
        $verificar->execute([$idCategoria]);
        if (!$verificar->fetchColumn()) {
            throw new InvalidArgumentException('La categoría elegida ya no existe.');
        }
        $modalidad = (string) ($_POST['modalidad'] ?? '');
        if (!in_array($modalidad, ['libre', 'privada', 'concurso'], true)) {
            throw new InvalidArgumentException('Selecciona una modalidad válida.');
        }
        $minutos = filter_var($_POST['tiempo_minutos'] ?? null, FILTER_VALIDATE_INT);
        if ($minutos === false || $minutos < 0 || $minutos > 30) {
            throw new InvalidArgumentException('El temporizador debe estar entre 0 y 30 minutos.');
        }
        if ($modalidad === 'concurso' && $minutos === 0) {
            throw new InvalidArgumentException('Los concursos necesitan un límite de tiempo.');
        }
        $segundos = $minutos === 0 ? null : $minutos * 60;
        $clave = (string) ($_POST['contrasena_trivia'] ?? '');
        $hash = null;
        $cambioClave = false;
        if ($modalidad === 'privada') {
            if ($clave !== '') {
                if (strlen($clave) < 6 || strlen($clave) > 72) {
                    throw new InvalidArgumentException('La contraseña privada debe tener entre 6 y 72 caracteres.');
                }
                $hash = password_hash($clave, PASSWORD_DEFAULT);
                $cambioClave = true;
            } elseif ($trivia && $trivia['modalidad'] === 'privada' && $trivia['contrasena_hash']) {
                $hash = $trivia['contrasena_hash'];
            } else {
                throw new InvalidArgumentException('Escribe una contraseña para la trivia privada.');
            }
        }
        // El cupo y la fecha pertenecen a las salas, que se programarán en el siguiente módulo.
        $maxIntentos = $modalidad === 'libre' ? null : 1;
        if (!$trivia) {
            if (cantidadTriviasCreadas($db, $idUsuario) >= TRILLAL_LIMITE_TRIVIAS) {
                throw new InvalidArgumentException('Ya tienes cinco trivias. Puedes editar las existentes, pero no crear una sexta.');
            }
            $stmt = $db->prepare('INSERT INTO trivias (id_creador, id_categoria, titulo, descripcion, modalidad, contrasena_hash, tiempo_limite_segundos, max_intentos, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ? )');
            $stmt->execute([$idUsuario, $idCategoria, $titulo, $descripcion ?: null, $modalidad, $hash, $segundos, $maxIntentos, 'borrador']);
            $idTriviaDestino = (int)$db->lastInsertId();
        } else {
            $stmt = $db->prepare('UPDATE trivias SET id_categoria=?, titulo=?, descripcion=?, modalidad=?, contrasena_hash=?, tiempo_limite_segundos=?, max_intentos=? WHERE id_trivia=? AND id_creador=?');
            $stmt->execute([$idCategoria, $titulo, $descripcion ?: null, $modalidad, $hash, $segundos, $maxIntentos, $trivia['id_trivia'], $idUsuario]);
            if ($cambioClave || ($trivia['modalidad'] === 'privada' && $modalidad !== 'privada')) {
                $stmt = $db->prepare('DELETE FROM accesos_privados WHERE id_trivia = ?');
                $stmt->execute([(int) $trivia['id_trivia']]);
            }
        }
        $mensaje = $trivia ? 'Configuración de tu trivia actualizada.' : '¡Tu trivia fue creada! Ahora agrega las preguntas.';
    }

    if ($accion === 'guardar_pregunta') {
        if (!$trivia) throw new InvalidArgumentException('Primero configura tu trivia.');
        solicitarBorrador($trivia);
        $idTrivia = (int) $trivia['id_trivia'];
        $enunciado = recortarTexto((string) ($_POST['enunciado'] ?? ''), 1600, 'La pregunta');
        $idPregunta = filter_var($_POST['id_pregunta'] ?? 0, FILTER_VALIDATE_INT);
        if ($idPregunta === false || $idPregunta < 0) {
            throw new InvalidArgumentException('La pregunta seleccionada no es válida.');
        }
        $anterior = null;
        $opcionesPrevias = [];
        if ($idPregunta > 0) {
            $stmt = $db->prepare('SELECT id_pregunta, imagen_url, orden FROM preguntas WHERE id_pregunta = ? AND id_trivia = ? FOR UPDATE');
            $stmt->execute([$idPregunta, $idTrivia]);
            $anterior = $stmt->fetch();
            if (!$anterior) throw new InvalidArgumentException('La pregunta no pertenece a tu trivia.');
            $stmt = $db->prepare('SELECT * FROM opciones WHERE id_pregunta = ? ORDER BY orden FOR UPDATE');
            $stmt->execute([$idPregunta]);
            $opcionesPrevias = $stmt->fetchAll();
            if (count($opcionesPrevias) !== 4) {
                throw new RuntimeException('La pregunta tiene opciones incompletas. Contacta al administrador.');
            }
        } else {
            $stmt = $db->prepare('SELECT COUNT(*) FROM preguntas WHERE id_trivia = ?');
            $stmt->execute([$idTrivia]);
            if ((int) $stmt->fetchColumn() >= 40) {
                throw new InvalidArgumentException('Una trivia puede contener hasta 40 preguntas.');
            }
        }
        $correcta = filter_var($_POST['respuesta_correcta'] ?? null, FILTER_VALIDATE_INT);
        if ($correcta === false || $correcta < 1 || $correcta > 4) {
            throw new InvalidArgumentException('Selecciona una única respuesta correcta (A, B, C o D).');
        }

        $rutaPregunta = $anterior['imagen_url'] ?? null;
        if (!empty($_POST['quitar_imagen_pregunta'])) {
            if ($rutaPregunta) $archivosAntiguos[] = $rutaPregunta;
            $rutaPregunta = null;
        }
        $subidaPregunta = subirImagenTrivia('imagen_pregunta', $nuevosArchivos);
        if ($subidaPregunta !== null) {
            if ($rutaPregunta) $archivosAntiguos[] = $rutaPregunta;
            $rutaPregunta = $subidaPregunta;
        }

        $valores = [];
        for ($i = 1; $i <= 4; $i++) {
            $texto = trim((string) ($_POST['opcion_texto_' . $i] ?? ''));
            if (strlen($texto) > 500) {
                throw new InvalidArgumentException('El texto de la opción ' . $i . ' es demasiado largo (máximo 500 caracteres).');
            }
            $vieja = $opcionesPrevias[$i - 1] ?? null;
            $rutaOpcion = $vieja['imagen_url'] ?? null;
            if (!empty($_POST['quitar_imagen_opcion_' . $i])) {
                if ($rutaOpcion) $archivosAntiguos[] = $rutaOpcion;
                $rutaOpcion = null;
            }
            $nuevaOpcion = subirImagenTrivia('opcion_imagen_' . $i, $nuevosArchivos);
            if ($nuevaOpcion !== null) {
                if ($rutaOpcion) $archivosAntiguos[] = $rutaOpcion;
                $rutaOpcion = $nuevaOpcion;
            }
            if ($texto === '' && !$rutaOpcion) {
                throw new InvalidArgumentException('Completa la opción ' . chr(64 + $i) . ': agrega texto, imagen o ambos.');
            }
            $valores[] = [$texto === '' ? null : $texto, $rutaOpcion, $i === $correcta ? 1 : 0, $i];
        }
        if ($anterior) {
            $stmt = $db->prepare('UPDATE preguntas SET enunciado=?, imagen_url=? WHERE id_pregunta=? AND id_trivia=?');
            $stmt->execute([$enunciado, $rutaPregunta, $idPregunta, $idTrivia]);
            $stmt = $db->prepare('UPDATE opciones SET texto=?, imagen_url=?, es_correcta=?, orden=? WHERE id_opcion=? AND id_pregunta=?');
            foreach ($valores as $i => $datos) {
                $stmt->execute([$datos[0], $datos[1], $datos[2], $datos[3], (int) $opcionesPrevias[$i]['id_opcion'], $idPregunta]);
            }
            $mensaje = 'Pregunta actualizada correctamente.';
        } else {
            $stmt = $db->prepare('SELECT COALESCE(MAX(orden),0) + 1 FROM preguntas WHERE id_trivia = ?');
            $stmt->execute([$idTrivia]);
            $orden = (int) $stmt->fetchColumn();
            $stmt = $db->prepare('INSERT INTO preguntas (id_trivia, enunciado, imagen_url, orden, puntos) VALUES (?, ?, ?, ?, 100)');
            $stmt->execute([$idTrivia, $enunciado, $rutaPregunta, $orden]);
            $idNueva = (int) $db->lastInsertId();
            $stmt = $db->prepare('INSERT INTO opciones (id_pregunta, texto, imagen_url, es_correcta, orden) VALUES (?, ?, ?, ?, ?)');
            foreach ($valores as $datos) {
                $stmt->execute([$idNueva, $datos[0], $datos[1], $datos[2], $datos[3]]);
            }
            $mensaje = 'Pregunta guardada. Puedes crear la siguiente.';
        }
    }

    if ($accion === 'eliminar_pregunta') {
        if (!$trivia) throw new InvalidArgumentException('No se encontró tu trivia.');
        solicitarBorrador($trivia);
        $idPregunta = filter_var($_POST['id_pregunta'] ?? null, FILTER_VALIDATE_INT);
        if (!$idPregunta || $idPregunta < 1) throw new InvalidArgumentException('Pregunta no válida.');
        $stmt = $db->prepare('SELECT imagen_url FROM preguntas WHERE id_pregunta=? AND id_trivia=? FOR UPDATE');
        $stmt->execute([$idPregunta, $trivia['id_trivia']]);
        $pregunta = $stmt->fetch();
        if (!$pregunta) throw new InvalidArgumentException('No puedes borrar esa pregunta.');
        if ($pregunta['imagen_url']) $archivosAntiguos[] = $pregunta['imagen_url'];
        $stmt = $db->prepare('SELECT imagen_url FROM opciones WHERE id_pregunta = ?');
        $stmt->execute([$idPregunta]);
        foreach ($stmt->fetchAll() as $opcion) {
            if ($opcion['imagen_url']) $archivosAntiguos[] = $opcion['imagen_url'];
        }
        $stmt = $db->prepare('DELETE FROM preguntas WHERE id_pregunta = ? AND id_trivia = ?');
        $stmt->execute([$idPregunta, $trivia['id_trivia']]);
        $mensaje = 'La pregunta se eliminó.';
    }

    if ($accion === 'publicar') {
        if (!$trivia) throw new InvalidArgumentException('Primero crea tu trivia.');
        solicitarBorrador($trivia);
        $stmt = $db->prepare('SELECT p.id_pregunta, COUNT(o.id_opcion) AS cantidad, COALESCE(SUM(o.es_correcta = 1), 0) AS correctas, COALESCE(SUM(CASE WHEN (o.texto IS NULL OR TRIM(o.texto) = \'\') AND o.imagen_url IS NULL THEN 1 ELSE 0 END), 0) AS vacias FROM preguntas p LEFT JOIN opciones o ON o.id_pregunta = p.id_pregunta WHERE p.id_trivia = ? GROUP BY p.id_pregunta');
        $stmt->execute([(int) $trivia['id_trivia']]);
        $filas = $stmt->fetchAll();
        if (!$filas) throw new InvalidArgumentException('Agrega al menos una pregunta antes de publicar.');
        foreach ($filas as $fila) {
            if ((int) $fila['cantidad'] !== 4 || (int) $fila['correctas'] !== 1 || (int) $fila['vacias'] > 0) {
                throw new InvalidArgumentException('Revisa las preguntas: cada una necesita cuatro opciones y solo una correcta.');
            }
        }
        $stmt = $db->prepare("UPDATE trivias SET estado='publicada', publicada_en=UTC_TIMESTAMP() WHERE id_trivia=? AND id_creador=?");
        $stmt->execute([$trivia['id_trivia'], $idUsuario]);
        $mensaje = '¡Tu trivia está publicada! Ya está preparada para crear salas y partidas.';
    }

    if ($accion === 'volver_borrador') {
        if (!$trivia || $trivia['estado'] !== 'publicada') throw new InvalidArgumentException('Esta trivia no está publicada.');
        $stmt = $db->prepare('SELECT COUNT(*) FROM salas WHERE id_trivia = ?');
        $stmt->execute([$trivia['id_trivia']]);
        $totalSalas = (int) $stmt->fetchColumn();
        $stmt = $db->prepare('SELECT COUNT(*) FROM intentos WHERE id_trivia = ?');
        $stmt->execute([$trivia['id_trivia']]);
        if ($totalSalas > 0 || (int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('No se puede editar una trivia con salas o partidas registradas: debemos preservar sus resultados.');
        }
        $stmt = $db->prepare("UPDATE trivias SET estado='borrador', publicada_en=NULL WHERE id_trivia=? AND id_creador=?");
        $stmt->execute([$trivia['id_trivia'], $idUsuario]);
        $mensaje = 'La trivia volvió a borrador y puedes editarla.';
    }

    $db->commit();
    foreach (array_unique($archivosAntiguos) as $ruta) borrarImagenTrivia($ruta);
    mensajeFlash('crear_ok', $mensaje);
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    foreach ($nuevosArchivos as $ruta) borrarImagenTrivia($ruta);
    if ($e instanceof InvalidArgumentException) {
        mensajeFlash('crear_error', $e->getMessage());
    } else {
        error_log('[TRILLAL creador] ' . $e->getMessage());
        mensajeFlash('crear_error', 'No se guardaron los cambios. Revisa la conexión, permisos de uploads/trivias e inténtalo de nuevo.');
    }
}
$url = $idTriviaDestino > 0 ? 'crear.php?id=' . $idTriviaDestino : 'crear.php?nueva=1';
if ($accion === 'guardar_pregunta' || $accion === 'eliminar_pregunta') $url .= '#preguntas';
redirigir($url);
