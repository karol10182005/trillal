<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/juego.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

function jJson(array $datos, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
try {
    $usuario = usuarioActual();
    if (!$usuario) jJson(['ok'=>false,'error'=>'Tu sesión caducó. Inicia sesión nuevamente.','redirigir'=>'index.php'],401);
    $db = conexion(); $uid = (int)$usuario['id_usuario'];
    $accion = (string)($_REQUEST['accion'] ?? '');
    $paso = 'validación';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verificarCsrf()) jJson(['ok'=>false,'error'=>'Formulario caducado. Actualiza la página.'],403);

    if ($accion === 'desbloquear' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = jEntero($_POST['id_trivia'] ?? null,1,2147483647,'Trivia');
        $t = jTrivia($db,$id);
        if (!$t || $t['estado'] !== 'publicada' || $t['modalidad'] !== 'privada') jError('Esta trivia privada no está disponible.');
        $clave=(string)($_POST['clave'] ?? '');
        // Máximo cinco fallos seguidos por sesión y trivia durante un minuto.
        $intentosClave = $_SESSION['fallos_clave_trivia'][$id] ?? ['cantidad'=>0,'desde'=>time()];
        if (time()-(int)$intentosClave['desde']>60) $intentosClave=['cantidad'=>0,'desde'=>time()];
        if ((int)$intentosClave['cantidad']>=5) jError('Demasiados intentos de contraseña. Vuelve a intentarlo en un minuto.');
        if (strlen($clave) > 120 || !password_verify($clave,(string)$t['contrasena_hash'])) {
            $intentosClave['cantidad']++;
            $_SESSION['fallos_clave_trivia'][$id]=$intentosClave;
            jError('Contraseña incorrecta.');
        }
        unset($_SESSION['fallos_clave_trivia'][$id]);
        $db->prepare('INSERT INTO accesos_privados (id_trivia,id_usuario) VALUES (?,?) ON DUPLICATE KEY UPDATE autorizado_en=CURRENT_TIMESTAMP')
          ->execute([$id,$uid]);
        jJson(['ok'=>true,'mensaje'=>'¡Acceso concedido!','redirigir'=>'trivia.php?id='.$id]);
    }
    if ($accion === 'crear_sala' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = jEntero($_POST['id_trivia'] ?? null,1,2147483647,'Trivia');
        $cupo = jEntero($_POST['cupo'] ?? null,1,40,'Cupo (1–40)');
        $antes = jEntero($_POST['cerrar_antes'] ?? null,0,60,'Cierre de inscripciones');
        if (!in_array($antes,[0,15,30,60],true)) jError('Tiempo de cierre no permitido.');
        $fechaStr=(string)($_POST['inicio_local'] ?? '');
        $fecha=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$fechaStr,jZona());
        if (!$fecha || $fecha->format('Y-m-d\TH:i') !== $fechaStr) jError('Elige una fecha y hora válidas.');
        $inicio=$fecha->getTimestamp(); $ahora=time(); $cierre=$inicio-$antes;
        if ($inicio < $ahora+35 || $inicio > $ahora+7*86400) jError('Programa la sala entre 35 segundos y 7 días a partir de ahora.');
        if ($cierre <= $ahora+5) jError('La inscripción debe mantenerse abierta al menos unos segundos.');
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM trivias WHERE id_trivia=? FOR UPDATE'); $q->execute([$id]); $t=$q->fetch();
            if (!$t || $t['estado']!=='publicada' || (int)$t['id_creador']!==$uid) jError('Solamente el creador puede programar su trivia publicada.');
            $total=count(jPreguntas($db,$id)); if (!$total) jError('Primero publica una trivia con preguntas.');
            $q=$db->prepare("SELECT COUNT(*) FROM salas WHERE id_trivia=? AND estado IN ('programada','en_curso')");$q->execute([$id]);
            if ((int)$q->fetchColumn()>=12) jError('Ya hay 12 salas pendientes o activas. Finaliza algunas antes de crear más.');
            $dia=jDiaLocal($inicio);
            $q=$db->prepare('SELECT 1 FROM cierres_cultura WHERE id_trivia=? AND fecha_competencia=?');$q->execute([$id,$dia]);
            if ($q->fetchColumn()) jError('Ya se cerró la premiación de ese día. Elige otro día.');
            $d=jDuracion($t,$total);
            $db->prepare("INSERT INTO salas (id_trivia,inscripciones_abren_en,inscripciones_cierran_en,inicio_programado_en,fecha_competencia,duracion_segundos,cupo_maximo,estado)
                VALUES (?,?,?,?,?,?,?,'programada')")
              ->execute([$id,jSqlUTC($ahora),jSqlUTC($cierre),jSqlUTC($inicio),$dia,$d,$cupo]);
            $idSala=(int)$db->lastInsertId();
            $db->commit();
        } catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); throw $e; }
        jJson(['ok'=>true,'mensaje'=>'¡Sala creada! Comparte el enlace para reunir jugadores.','redirigir'=>'trivia.php?id='.$id.'#salas']);
    }
    if ($accion === 'inscribir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $idSala=jEntero($_POST['id_sala'] ?? null,1,PHP_INT_MAX,'Sala');
        $s=jSincronizarSala($db,$idSala);
        if (!$s || $s['estado_trivia'] !== 'publicada') jError('La sala no está disponible.');
        $t=jTrivia($db,(int)$s['id_trivia']);
        if (!jAcceso($db,$t,$uid)) jError('Primero introduce la contraseña de la trivia privada.');
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM salas WHERE id_sala=? FOR UPDATE');$q->execute([$idSala]);$slock=$q->fetch();
            if (!$slock || $slock['estado']!=='programada' || time()<jFecha($slock['inscripciones_abren_en']) || time()>=jFecha($slock['inscripciones_cierran_en'])) jError('Las inscripciones ya cerraron o aún no están abiertas.');
            $q=$db->prepare('SELECT estado FROM inscripciones_sala WHERE id_sala=? AND id_usuario=?');$q->execute([$idSala,$uid]);$anterior=$q->fetchColumn();
            if ($anterior==='inscrito') {
                $db->commit(); jJson(['ok'=>true,'redirigir'=>'sala.php?id='.$idSala,'mensaje'=>'Ya estás inscrito.']);
            }
            if (jCuentaInscritos($db,$idSala)>=(int)$slock['cupo_maximo']) jError('La sala ya alcanzó su cupo máximo.');
            $db->prepare("INSERT INTO inscripciones_sala (id_sala,id_usuario,estado) VALUES (?,?,'inscrito') ON DUPLICATE KEY UPDATE estado='inscrito',inscrito_en=CURRENT_TIMESTAMP")
              ->execute([$idSala,$uid]);
            $db->commit();
        } catch(Throwable $e) {if($db->inTransaction()) $db->rollBack(); throw $e;}
        jJson(['ok'=>true,'mensaje'=>'¡Tu lugar está asegurado!','redirigir'=>'sala.php?id='.$idSala]);
    }
    // Partidas instantáneas sin inscripción previa: normal y rápida.
    if ($accion === 'solo' && $_SERVER['REQUEST_METHOD']==='POST') {
        $id = jEntero($_POST['id_trivia'] ?? null, 1, 2147483647, 'Trivia');
        $tipo = (string)($_POST['tipo_partida'] ?? 'normal');
        if (!in_array($tipo, ['normal','rapida'], true)) jError('Elige una partida normal o rápida.');
        $paso = 'verificación de base de datos';
        // Evita mostrar un error SQL genérico cuando falta la pequeña actualización.
        $col = $db->query("SHOW COLUMNS FROM salas LIKE 'tipo_partida'")->fetch();
        if (!$col) jError('Falta instalar la actualización SQL de las dos modalidades. Consulta el archivo LEEME.');
        $paso = 'bloqueo de trivia';
        $db->beginTransaction();
        try {
            $q = $db->prepare('SELECT * FROM trivias WHERE id_trivia=? FOR UPDATE');
            $q->execute([$id]); $t = $q->fetch();
            if (!$t || $t['estado']!=='publicada' || $t['modalidad']!=='libre') {
                jError('Esta trivia no admite partidas individuales.');
            }
            $preguntas = jPreguntas($db, $id);
            if (!$preguntas) jError('Esta trivia no tiene preguntas.');
            $ahora = time();
            $duracion = jDuracion($t, count($preguntas), $tipo);
            // TRILLAL: cada jugador tiene UNA oportunidad NORMAL y UNA RÁPIDA
            // por trivia (no por día). La fila de trivias bloqueada mediante
            // SELECT ... FOR UPDATE serializa solicitudes simultáneas.
            $paso = 'comprobación de intentos únicos';
            $q = $db->prepare("SELECT s.id_sala, s.tipo_partida, s.estado,
                      s.inicio_programado_en, s.duracion_segundos
                 FROM intentos i INNER JOIN salas s ON s.id_sala=i.id_sala
                 WHERE i.id_trivia=? AND i.id_usuario=?
                   AND s.tipo_partida IN ('normal','rapida')
                 ORDER BY i.id_intento DESC");
            $q->execute([$id,$uid]);
            $existentes = $q->fetchAll();
            $modoUsado = null;
            $otroEnCurso = null;
            foreach ($existentes as $partida) {
                if ($partida['tipo_partida'] === $tipo) {
                    $modoUsado = $partida;
                    break;
                }
                if ($partida['estado'] === 'en_curso' &&
                    $ahora < jFecha($partida['inicio_programado_en'])+(int)$partida['duracion_segundos']) {
                    $otroEnCurso = $partida;
                }
            }
            if ($modoUsado) {
                $sigue = $modoUsado['estado'] === 'en_curso' &&
                    $ahora < jFecha($modoUsado['inicio_programado_en'])+(int)$modoUsado['duracion_segundos'];
                $db->commit();
                jJson(['ok'=>true,
                    'mensaje'=>$sigue ? 'Ya iniciaste este modo. Continúa tu partida.'
                                      : 'Ya usaste tu intento de este modo. Puedes consultar tu resultado, pero no volver a jugarlo.',
                    'redirigir'=>'sala.php?id='.(int)$modoUsado['id_sala']]);
            }
            if ($otroEnCurso) {
                $db->commit();
                jJson(['ok'=>true,'mensaje'=>'Termina primero tu partida activa antes de iniciar el otro modo.',
                    'redirigir'=>'sala.php?id='.(int)$otroEnCurso['id_sala']]);
            }
            $dia = jDiaLocal($ahora);
            $paso = 'creación de sala individual';
            // La versión original del esquema exige: apertura < cierre <= inicio.
            // Las partidas individuales se inscriben automáticamente después del INSERT;
            // estos horarios históricos no requieren ventana pública de inscripción.
            $aperturaSql = jSqlUTC($ahora - 2);
            $cierreSql = jSqlUTC($ahora - 1);
            $inicioSql = jSqlUTC($ahora);
            $db->prepare("INSERT INTO salas
                (id_trivia,tipo_partida,inscripciones_abren_en,inscripciones_cierran_en,
                 inicio_programado_en,inicio_real_en,fecha_competencia,duracion_segundos,cupo_maximo,estado)
                 VALUES (?,?,?,?,?,?,?,?,1,'en_curso')")
              ->execute([$id,$tipo,$aperturaSql,$cierreSql,$inicioSql,$inicioSql,$dia,$duracion]);
            $idSala = (int)$db->lastInsertId();
            $paso = 'inscripción automática';
            $db->prepare("INSERT INTO inscripciones_sala (id_sala,id_usuario,estado)
                          VALUES (?,?,'inscrito')")->execute([$idSala,$uid]);
            $paso = 'creación de intento';
            // Soporta incluso una instalación que conserve el índice antiguo
            // (id_trivia,id_usuario,numero_intento) de la versión inicial.
            $qNumero = $db->prepare('SELECT COALESCE(MAX(numero_intento),0)+1 FROM intentos WHERE id_trivia=? AND id_usuario=?');
            $qNumero->execute([$id,$uid]);
            $numeroIntento = (int)$qNumero->fetchColumn();
            if ($numeroIntento > 65535) jError('Has alcanzado el máximo técnico de intentos.');
            $db->prepare("INSERT INTO intentos
                (id_trivia,id_sala,id_usuario,numero_intento,estado,puntuacion_total,
                 respuestas_correctas,tiempo_total_segundos,iniciado_en)
                 VALUES (?,?,?,?,'en_curso',0,0,0,?)")
              ->execute([$id,$idSala,$uid,$numeroIntento,jSqlUTC($ahora)]);
            $db->commit();
        } catch(Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        jJson(['ok'=>true,'mensaje'=>$tipo==='rapida'?'¡Reto relámpago! +10 al terminar.':'¡Comienza el reto!',
              'redirigir'=>'sala.php?id='.$idSala]);
    }
    if ($accion === 'responder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $idSala=jEntero($_POST['id_sala'] ?? null,1,PHP_INT_MAX,'Sala');
        $idPregunta=jEntero($_POST['id_pregunta'] ?? null,1,2147483647,'Pregunta');
        $idOpcion=jEntero($_POST['id_opcion'] ?? null,1,2147483647,'Opción');
        jSincronizarSala($db,$idSala);
        $db->beginTransaction();
        try {
            $q=$db->prepare('SELECT * FROM salas WHERE id_sala=? FOR UPDATE');$q->execute([$idSala]);$s=$q->fetch();
            if(!$s || $s['estado']!=='en_curso') jError('La partida ya terminó o no ha comenzado.');
            if(!jInscrito($db,$idSala,$uid)) jError('No estás inscrito en esta sala.');
            $preguntas=jPreguntas($db,(int)$s['id_trivia']);$total=count($preguntas);
            if($total<1) jError('No hay preguntas disponibles.');
            $elapsed=time()-jFecha($s['inicio_programado_en']);$dur=(int)$s['duracion_segundos'];
            if($elapsed<0 || $elapsed>=$dur) jError('El reloj terminó. No se aceptan respuestas tardías.');
            $pos=jPosicion($elapsed,$dur,$total);
            if((int)$preguntas[$pos]['id_pregunta']!==$idPregunta) jError('Esta pregunta ya no está activa.');
            $q=$db->prepare('SELECT id_opcion,es_correcta FROM opciones WHERE id_opcion=? AND id_pregunta=?');$q->execute([$idOpcion,$idPregunta]);$op=$q->fetch();
            if(!$op) jError('Opción de respuesta inválida.');
            $q=$db->prepare("SELECT id_intento,estado FROM intentos WHERE id_sala=? AND id_usuario=? FOR UPDATE");$q->execute([$idSala,$uid]);$i=$q->fetch();
            if(!$i || $i['estado']!=='en_curso') jError('No hay un intento activo para este jugador.');
            $q=$db->prepare('SELECT id_opcion,es_correcta,puntos_obtenidos FROM respuestas WHERE id_intento=? AND id_pregunta=?');$q->execute([(int)$i['id_intento'],$idPregunta]);$exist=$q->fetch();
            if($exist) { $db->commit(); jJson(['ok'=>true,'respondida'=>true,'correcta'=>(bool)$exist['es_correcta'],'puntos'=>(int)$exist['puntos_obtenidos'],'seleccion'=>(int)$exist['id_opcion']]); }
            $correcta=(int)$op['es_correcta']===1;
            $puntos=$correcta ? (int)$preguntas[$pos]['puntos'] : 0;
            $inicioTurno=(int)ceil(($pos*$dur)/$total);
            $tiempo=(int)max(0,$elapsed-$inicioTurno);
            $db->prepare('INSERT INTO respuestas (id_intento,id_pregunta,id_opcion,es_correcta,puntos_obtenidos,tiempo_respuesta_segundos,respondida_en) VALUES (?,?,?,?,?,?,?)')
               ->execute([(int)$i['id_intento'],$idPregunta,$idOpcion,$correcta?1:0,$puntos,$tiempo,jSqlUTC(time())]);
            $db->prepare('UPDATE intentos SET puntuacion_total=puntuacion_total+?,respuestas_correctas=respuestas_correctas+?,tiempo_total_segundos=COALESCE(tiempo_total_segundos,0)+? WHERE id_intento=?')
               ->execute([$puntos,$correcta?1:0,$tiempo,(int)$i['id_intento']]);
            $db->commit();
        }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        jJson(['ok'=>true,'respondida'=>true,'correcta'=>$correcta,'puntos'=>$puntos,'seleccion'=>$idOpcion]);
    }
    if ($accion === 'estado' && $_SERVER['REQUEST_METHOD']==='GET') {
        $idSala=jEntero($_GET['sala'] ?? null,1,PHP_INT_MAX,'Sala');
        $s=jSincronizarSala($db,$idSala);
        if(!$s)jError('Esta sala ya no existe.');
        $t=jTrivia($db,(int)$s['id_trivia']);
        if(!$t || !jAcceso($db,$t,$uid) || !jInscrito($db,$idSala,$uid)) jJson(['ok'=>false,'error'=>'No tienes acceso a esta partida.'],403);
        $now=time();$inicio=jFecha($s['inicio_programado_en']);$dur=(int)$s['duracion_segundos'];
        $total=count(jPreguntas($db,(int)$s['id_trivia']));
        $out=['ok'=>true,'estado'=>$s['estado'],'servidor'=>$now,'servidor_ms'=>(int)round(microtime(true)*1000),'inicio'=>$inicio,'fin'=>$inicio+$dur,
           'cierre_inscripciones'=>jFecha($s['inscripciones_cierran_en']),'duracion'=>$dur,
           'titulo'=>$s['titulo'],'modalidad'=>$s['modalidad'],'tipo_partida'=>$s['tipo_partida'] ?? 'sala',
           'bono_rapida'=>($s['tipo_partida'] ?? 'sala') === 'rapida' ? 10 : 0,'cupo'=>(int)$s['cupo_maximo'],
           'inscritos'=>jCuentaInscritos($db,$idSala),'total_preguntas'=>$total,'usuario'=>$uid,'cultura'=>(int)$usuario['cultura']];
        if($s['estado']==='en_curso' || $s['estado']==='finalizada'){
            $ranking=jRanking($db,$idSala);$out['ranking']=$ranking;
            $out['posicion']=null;$out['puntos']=0;
            foreach($ranking as $r){if($r['id_usuario']===$uid){$out['posicion']=$r['puesto'];$out['puntos']=$r['puntos'];break;}}
            if($s['estado']==='en_curso' && $total>0){
                $pos=jPosicion($now-$inicio,$dur,$total);$p=jPreguntas($db,(int)$s['id_trivia'])[$pos];
                $q=$db->prepare('SELECT id_opcion,texto,imagen_url,orden FROM opciones WHERE id_pregunta=? ORDER BY orden');$q->execute([(int)$p['id_pregunta']]);
                $out['pregunta']=['id'=>(int)$p['id_pregunta'],'indice'=>$pos+1,'texto'=>$p['enunciado'],'imagen'=>$p['imagen_url'],
                     'opciones'=>$q->fetchAll(),'fin_pregunta'=>jFinPregunta($pos,$dur,$total,$inicio)];
                $q=$db->prepare('SELECT r.id_opcion,r.es_correcta,r.puntos_obtenidos FROM respuestas r INNER JOIN intentos i ON i.id_intento=r.id_intento WHERE i.id_sala=? AND i.id_usuario=? AND r.id_pregunta=?');
                $q->execute([$idSala,$uid,(int)$p['id_pregunta']]);$r=$q->fetch();
                $out['respuesta']=$r ? ['opcion'=>(int)$r['id_opcion'],'correcta'=>(bool)$r['es_correcta'],'puntos'=>(int)$r['puntos_obtenidos']] : null;
            }
            if($s['modalidad']!=='privada') {
                $out['ranking_dia']=jRankingDia($db,(int)$s['id_trivia'],$s['fecha_competencia']);
                $out['posicion_dia']=null;
                foreach($out['ranking_dia'] as $filaDia){if($filaDia['id_usuario']===$uid){$out['posicion_dia']=$filaDia['puesto'];break;}}
            }
            if($s['estado']==='finalizada') {
                jPremiarPublicaSiLista($db,$s);
                $out['premio']=jPremioUsuario($db,$s,$uid);
                $out['pendiente_cierre']=$s['modalidad']!=='privada' && $s['fecha_competencia']>=jDiaLocal($now);
            }
        }
        jJson($out);
    }
    jJson(['ok'=>false,'error'=>'Acción o método desconocido.'],400);
} catch(InvalidArgumentException $e) {jJson(['ok'=>false,'error'=>$e->getMessage()],422);}
catch(Throwable $e) {
    // Registrar el error real en los logs del hosting sin exponer SQL ni contraseñas al visitante.
    $referencia = substr(bin2hex(random_bytes(5)), 0, 10);
    error_log('[TRILLAL JUGAR '.$referencia.'] accion='.($accion ?? '?').' paso='.($paso ?? '?').
        ' '.get_class($e).' SQL/ERROR: '.$e->getMessage());
    $mensaje = 'No se pudo iniciar/completar la partida en el paso «'.($paso ?? 'desconocido').'». Referencia: '.$referencia.'.';
    if ($e instanceof PDOException) {
        $driver = (int)($e->errorInfo[1] ?? 0);
        if ($driver === 1062) $mensaje = 'Se detectó una restricción de intentos duplicados en MySQL. Revisa el diagnóstico de Jugar. Ref: '.$referencia;
        elseif ($driver === 1452 || $driver === 1451) $mensaje = 'La base de datos rechazó la relación entre sala, inscripción e intento. Ref: '.$referencia;
        elseif ($driver === 1054) $mensaje = 'Falta una columna en la base de datos. Importa la actualización SQL de Jugar. Ref: '.$referencia;
        elseif ($driver === 1146) $mensaje = 'Falta una tabla de Trillal en MySQL. Revisa phpMyAdmin. Ref: '.$referencia;
        elseif (in_array($driver, [3819, 4025], true)) $mensaje = 'MySQL rechazó una restricción de la sala (CHECK). Comprueba la versión de jugar_api.php. Ref: '.$referencia;
    }
    jJson(['ok'=>false,'error'=>$mensaje],500);
}
