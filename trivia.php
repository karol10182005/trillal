<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';require __DIR__.'/config/juego.php';require __DIR__.'/config/juego_ui.php';
$usuario=exigirSesion();$db=conexion();
$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);if(!$id||!($t=jTrivia($db,$id))||$t['estado']!=='publicada'){http_response_code(404);exit('La trivia solicitada no está disponible. <a href="jugar.php">Volver</a>');}
$uid=(int)$usuario['id_usuario'];$esCreador=(int)$t['id_creador']===$uid;
$autorizado=jAcceso($db,$t,$uid);
$preguntas=jPreguntas($db,(int)$id);$duracion=jDuracion($t,count($preguntas));
$salas=[];
if($autorizado){
 $q=$db->prepare("SELECT s.*,(SELECT COUNT(*) FROM inscripciones_sala i WHERE i.id_sala=s.id_sala AND i.estado='inscrito') AS inscritos,
      (SELECT COUNT(*) FROM inscripciones_sala i WHERE i.id_sala=s.id_sala AND i.id_usuario=? AND i.estado='inscrito') AS ya_inscrito
      FROM salas s WHERE s.id_trivia=? ORDER BY s.inicio_programado_en DESC LIMIT 35");
 $q->execute([$uid,$id]);$salas=$q->fetchAll();
}
// Actualiza salas terminadas y Cultura de días anteriores al entrar a la trivia.
// En MySQL público los premios se entregan DESPUÉS de cerrar el día local.
jMantenimiento($db);
$usuario=usuarioActual() ?: $usuario; // cultura refrescada tras procesar cierres
$misModos=[];
if ($autorizado && $t['modalidad']==='libre') {
    $q=$db->prepare("SELECT s.id_sala,s.tipo_partida,s.estado,s.inicio_programado_en,s.duracion_segundos
        FROM intentos i INNER JOIN salas s ON s.id_sala=i.id_sala
        WHERE i.id_trivia=? AND i.id_usuario=? AND s.tipo_partida IN ('normal','rapida')
        ORDER BY i.id_intento DESC");
    $q->execute([$id,$uid]);
    foreach ($q->fetchAll() as $fila) {
        if (!isset($misModos[$fila['tipo_partida']])) $misModos[$fila['tipo_partida']]=$fila;
    }
}
$prox=time()+120;
?>
<!doctype html><html lang="es"><head><?php jHeader($t['titulo']);?><style>
/* TRILLAL · Selector de modos v2. Estilos críticos integrados para evitar caché en ByetHost. */
.selector-trillal{margin:28px 0 34px!important;display:block!important;max-width:100%!important;color:#f4eee6;font-family:inherit}
.selector-trillal *{box-sizing:border-box}
.selector-trillal__encabezado{display:flex;justify-content:space-between;align-items:end;gap:18px;margin-bottom:19px}
.selector-trillal__encabezado h2{font-size:clamp(25px,3vw,36px);font-weight:850;letter-spacing:-.035em;line-height:1.15;margin:7px 0 7px;color:#fff4e8}
.selector-trillal__encabezado p{color:#bdb5ac;font-size:13px;line-height:1.55;margin:0}
.selector-trillal__sello{white-space:nowrap;border:1px solid #796448;background:#322c25;color:#efcf9b;font-size:10px;font-weight:850;padding:9px 13px;border-radius:999px;letter-spacing:.08em}
.selector-trillal__grid{display:grid!important;grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:16px!important;align-items:stretch!important;width:100%!important}
.selector-trillal__card{min-width:0;display:flex!important;flex-direction:column!important;gap:0!important;position:relative;overflow:hidden;padding:22px 22px 19px!important;border-radius:17px!important;background:linear-gradient(145deg,#34312f,#242220)!important;border:1px solid #62564c!important;box-shadow:0 8px 28px #0003;transition:transform .23s ease,border-color .23s ease,box-shadow .23s ease}
.selector-trillal__card:hover{transform:translateY(-4px);border-color:#d5b27c!important;box-shadow:0 16px 33px #0007}
.selector-trillal__card--rapida{background:radial-gradient(circle at 96% 0%,#bc873a30,transparent 47%),linear-gradient(145deg,#3d322a,#24201c)!important;border-color:#92704c!important}
.selector-trillal__fila{display:flex!important;align-items:center!important;gap:16px!important;min-height:95px}
.selector-trillal__icono{flex:0 0 62px;width:62px;height:62px;display:grid;place-items:center;border-radius:15px;border:1px solid #836a4e;background:#574735;color:#f7d9a0;font-size:38px;line-height:1;box-shadow:inset 0 1px #ffffff16}
.selector-trillal__card--rapida .selector-trillal__icono{background:#674629;color:#ffdda0;border-color:#9b7046;font-size:45px}
.selector-trillal__texto{min-width:0}
.selector-trillal__sup{font-size:10px;letter-spacing:.125em;color:#d8b787;font-weight:900;margin-bottom:6px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.selector-trillal__bonus{padding:3px 7px;border-radius:5px;background:#bd83322f;color:#ffddaa;border:1px solid #9b7249;letter-spacing:0;font-size:9px}
.selector-trillal__texto h3{font-size:clamp(20px,2.15vw,26px);font-weight:850;line-height:1.12;letter-spacing:-.04em;margin:0 0 6px;color:#fff6eb}
.selector-trillal__texto p{color:#bdb4a9;font-size:12px;line-height:1.45;margin:0;max-width:360px}
.selector-trillal__info{display:flex;align-items:center;justify-content:space-between;gap:10px;border-top:1px solid #6c5a4778;margin:17px 0 15px;padding-top:14px;min-height:44px}
.selector-trillal__info>span:first-child{display:flex;align-items:baseline;gap:9px;white-space:nowrap}
.selector-trillal__mini{font-size:10px;color:#afa295;letter-spacing:.12em;font-weight:900}
.selector-trillal__info strong{font-size:20px;color:#f3d4a5;font-variant-numeric:tabular-nums;letter-spacing:-.02em}
.selector-trillal__limite{font-size:10px;color:#d1c3af;border-radius:999px;border:1px solid #5c5043;padding:7px 10px;white-space:nowrap;background:#1c1b1a6b}
.selector-trillal__estado{border-radius:8px;color:#e9d0a4;background:#56432b70;border:1px solid #776042;padding:8px 11px;font-size:11px;font-weight:800;margin:-3px 0 11px}
.selector-trillal__card form{display:block!important;width:100%!important;margin-top:auto!important}
.selector-trillal__boton{width:100%!important;min-height:50px!important;display:flex!important;align-items:center!important;justify-content:space-between!important;gap:12px!important;padding:12px 17px!important;text-decoration:none!important;border-radius:10px!important;border:1px solid #ae8e69!important;cursor:pointer;font-family:inherit;font-weight:900!important;font-size:13px!important;letter-spacing:.045em!important;text-align:left!important;transition:filter .2s,transform .2s,box-shadow .2s!important;margin-top:auto!important}
.selector-trillal__boton span{font-size:18px;font-weight:900}
.selector-trillal__boton:hover{filter:brightness(1.09);transform:translateY(-2px);box-shadow:0 8px 18px #0006}
.selector-trillal__boton--normal{background:#55473b!important;color:#f9e8cf!important}
.selector-trillal__boton--rapida{background:linear-gradient(110deg,#f9dcaa,#dfb673)!important;color:#241b14!important;border-color:#eed19d!important}
.selector-trillal__boton--hecho{background:#393530!important;color:#e3d6c8!important;border-color:#74675b!important}
.selector-trillal__boton:focus-visible{outline:3px solid #f5daae!important;outline-offset:3px}
.selector-trillal__boton:disabled{opacity:.55;cursor:progress}
.selector-trillal__nota{border:1px solid #4d4135;background:#211f1c;padding:14px 16px;border-radius:10px;margin-top:15px;display:flex;justify-content:space-between;gap:18px;align-items:center;color:#bba994;font-size:11px;line-height:1.55}
.selector-trillal__nota span{flex:1}
.selector-trillal__nota a{white-space:nowrap;color:#f1d6a9;text-decoration:none;font-weight:850}
.selector-trillal__nota a:hover{text-decoration:underline}
@media(max-width:850px){.selector-trillal__grid{grid-template-columns:1fr!important}.selector-trillal__encabezado{align-items:start;flex-direction:column}.selector-trillal__nota{flex-direction:column;align-items:start;gap:8px}}
@media(max-width:430px){.selector-trillal__card{padding:17px!important}.selector-trillal__fila{gap:12px!important}.selector-trillal__icono{width:52px;height:52px;flex-basis:52px;font-size:31px}.selector-trillal__limite{font-size:9px}.selector-trillal__info strong{font-size:18px}}
@media(prefers-reduced-motion:reduce){.selector-trillal__card,.selector-trillal__boton{transition:none!important}}
</style></head>
<body class="pagina-inicio pagina-juego"><div class="app-layout"><?php jMenu($usuario,'jugar'); ?>
<main class="juego-main" data-juego-csrf="<?= escapar(tokenCsrf()) ?>">
 <div class="juego-topline"><span class="etiqueta">02 / ELIGE TU PARTIDA</span><a class="juego-link" href="jugar.php">← Todas las trivias</a></div>
 <section class="detalle-hero"><span class="tipo-chip <?= escapar($t['modalidad']) ?>"><?= $t['modalidad']==='privada'?'◇ PRIVADA':($t['modalidad']==='concurso'?'♛ CONCURSO':'♟ LIBRE') ?></span><div class="detalle-grid"><div><span class="juego-categoria"><?= escapar($t['categoria']) ?> · por <?= escapar($t['autor']) ?></span><h1><?= escapar($t['titulo']) ?></h1><p><?= escapar($t['descripcion']?:'Demuestra cuánto sabes y entra en la clasificación.') ?></p></div><div class="detalle-estadisticas"><div><b><?= count($preguntas) ?></b><small>Preguntas</small></div><div><b><?= (int)ceil($duracion/60) ?> min</b><small>Reloj</small></div><div><b>+25</b><small>1.er lugar</small></div></div></div></section>
 <?php if(!$autorizado): ?>
 <section class="panel-juego acceso-privado"><span class="juego-eyebrow">◇ ACCESO PRIVADO</span><h2>Necesitas la contraseña</h2><p>El creador ha protegido esta trivia. Escribe la clave para conocer sus salas y participar.</p><form class="juego-form" data-juego-ajax><input type="hidden" name="accion" value="desbloquear"><input type="hidden" name="id_trivia" value="<?= (int)$id ?>"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><label>Contraseña de la trivia<input type="password" name="clave" required maxlength="120" autocomplete="off" placeholder="Introduce la contraseña"></label><button class="juego-btn primaria" type="submit">Desbloquear trivia →</button></form></section>
 <?php else: ?>
  <?php if($t['modalidad']==='libre'): ?>
   <section class="selector-trillal" aria-label="Selecciona la modalidad de partida">
     <div class="selector-trillal__encabezado">
       <div><span class="juego-eyebrow">♛ ESCOGE TU PARTIDA</span><h2>¿Cómo quieres jugar?</h2><p>Dos formas de demostrar lo que sabes. Tienes una oportunidad por modalidad.</p></div>
       <span class="selector-trillal__sello">2 MODOS · 2 OPORTUNIDADES</span>
     </div>
     <div class="selector-trillal__grid">
       <article class="selector-trillal__card selector-trillal__card--normal">
         <div class="selector-trillal__fila">
           <div class="selector-trillal__icono" aria-hidden="true">◷</div>
           <div class="selector-trillal__texto">
             <div class="selector-trillal__sup">MODO CLÁSICO</div>
             <h3>Partida normal</h3>
             <p>Juega con el tiempo completo de la trivia.</p>
           </div>
         </div>
         <div class="selector-trillal__info">
           <span><span class="selector-trillal__mini">TIEMPO</span><strong><?= escapar(sprintf('%d:%02d', intdiv($duracion,60), $duracion%60)) ?></strong></span>
           <span class="selector-trillal__limite">1 intento por trivia</span>
         </div>
         <?php if(isset($misModos['normal'])): $mn=$misModos['normal'];
                 $enCursoN=$mn['estado']==='en_curso' && time()<jFecha($mn['inicio_programado_en'])+(int)$mn['duracion_segundos']; ?>
           <div class="selector-trillal__estado">✓ Intento normal utilizado</div>
           <a class="selector-trillal__boton selector-trillal__boton--hecho" href="sala.php?id=<?= (int)$mn['id_sala'] ?>"><?= $enCursoN?'CONTINUAR PARTIDA':'VER RESULTADO' ?> <span aria-hidden="true">↗</span></a>
         <?php else: ?>
           <form data-juego-ajax><input type="hidden" name="accion" value="solo"><input type="hidden" name="tipo_partida" value="normal"><input type="hidden" name="id_trivia" value="<?= (int)$id ?>"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><button type="submit" class="selector-trillal__boton selector-trillal__boton--normal">JUGAR NORMAL <span aria-hidden="true">↗</span></button></form>
         <?php endif; ?>
       </article>
       <article class="selector-trillal__card selector-trillal__card--rapida">
         <div class="selector-trillal__fila">
           <div class="selector-trillal__icono" aria-hidden="true">ϟ</div>
           <div class="selector-trillal__texto">
             <div class="selector-trillal__sup">MODO CONTRARRELOJ <span class="selector-trillal__bonus">+10 PTS</span></div>
             <h3>Partida rápida</h3>
             <p>La mitad del tiempo y 10 puntos extra de puntuación al completar.</p>
           </div>
         </div>
         <div class="selector-trillal__info">
           <span><span class="selector-trillal__mini">TIEMPO</span><strong><?= escapar(sprintf('%d:%02d', intdiv(jDuracion($t,count($preguntas),'rapida'),60), jDuracion($t,count($preguntas),'rapida')%60)) ?></strong></span>
           <span class="selector-trillal__limite">1 intento por trivia</span>
         </div>
         <?php if(isset($misModos['rapida'])): $mr=$misModos['rapida'];
                 $enCursoR=$mr['estado']==='en_curso' && time()<jFecha($mr['inicio_programado_en'])+(int)$mr['duracion_segundos']; ?>
           <div class="selector-trillal__estado">✓ Intento rápido utilizado</div>
           <a class="selector-trillal__boton selector-trillal__boton--hecho" href="sala.php?id=<?= (int)$mr['id_sala'] ?>"><?= $enCursoR?'CONTINUAR PARTIDA':'VER RESULTADO' ?> <span aria-hidden="true">↗</span></a>
         <?php else: ?>
           <form data-juego-ajax><input type="hidden" name="accion" value="solo"><input type="hidden" name="tipo_partida" value="rapida"><input type="hidden" name="id_trivia" value="<?= (int)$id ?>"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><button type="submit" class="selector-trillal__boton selector-trillal__boton--rapida">JUGAR RÁPIDA <span aria-hidden="true">⚡</span></button></form>
         <?php endif; ?>
       </article>
     </div>
     <div class="selector-trillal__nota"><span>♛ La mejor puntuación del día entra al ranking. Rápida suma +10 de puntuación, no de Cultura; los premios públicos se acreditan al cerrar la jornada.</span><a href="cultura_estado.php">Ver mi Cultura ↗</a></div>
   </section>
  <?php endif; ?>
  <section class="seccion-salas" id="salas"><div class="juego-heading"><div><span class="etiqueta">SALAS PROGRAMADAS</span><h2>Elige dónde competir</h2></div><span class="juego-pill">Zona horaria: CDMX</span></div>
  <div class="salas-lista">
  <?php if(!$salas): ?><div class="juego-vacio compacta"><span>◷</span><h3>Aún no hay salas</h3><p>El creador puede programar una. En modo libre también puedes jugar inmediatamente.</p></div><?php endif; ?>
  <?php foreach($salas as $s): $inscritos=(int)$s['inscritos'];$inscrito=(int)$s['ya_inscrito']>0;$abierta=$s['estado']==='programada' && time()>=jFecha($s['inscripciones_abren_en']) && time()<jFecha($s['inscripciones_cierran_en']) && $inscritos<(int)$s['cupo_maximo']; ?>
   <article class="sala-fila"><div class="sala-numero">#<?= (int)$s['id_sala'] ?></div><div class="sala-informacion"><strong><?= escapar(jMetaFecha($s['inicio_programado_en'])) ?></strong><span><?= $inscritos ?> / <?= (int)$s['cupo_maximo'] ?> participantes · Cierre <?= escapar(jMetaFecha($s['inscripciones_cierran_en'])) ?></span></div><span class="estado-sala <?= escapar($s['estado']) ?>"><?= ['programada'=>'◷ Próxima','en_curso'=>'● En vivo','finalizada'=>'✓ Terminada','cancelada'=>'× Cancelada'][$s['estado']] ?? escapar($s['estado']) ?></span>
   <?php if($inscrito): ?><a class="juego-btn secundaria" href="sala.php?id=<?= (int)$s['id_sala'] ?>"><?= $s['estado']==='finalizada'?'Ver podio':'Entrar a sala' ?> →</a>
   <?php elseif($abierta): ?><form data-juego-ajax><input type="hidden" name="accion" value="inscribir"><input type="hidden" name="id_sala" value="<?= (int)$s['id_sala'] ?>"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><button class="juego-btn primaria" type="submit">Inscribirme ↗</button></form>
   <?php else: ?><span class="sala-cerrada"><?= $inscritos>=(int)$s['cupo_maximo']?'Cupo lleno':'Inscripción cerrada' ?></span><?php endif; ?>
   </article>
  <?php endforeach; ?></div></section>
  <?php if($esCreador): ?>
   <section class="panel-juego programar-sala"><div><span class="juego-eyebrow">♛ PANEL DEL CREADOR</span><h2>Programar una sala</h2><p>Elige la hora de inicio, el cupo y cuándo dejarás de aceptar jugadores. Todos empiezan al mismo tiempo.</p></div>
   <form class="juego-form programacion-form" data-juego-ajax><input type="hidden" name="accion" value="crear_sala"><input type="hidden" name="id_trivia" value="<?= (int)$id ?>"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
    <label>Fecha y hora de inicio <small>Hora de Ciudad de México</small><input type="datetime-local" name="inicio_local" value="<?= escapar(jLocalForm($prox)) ?>" min="<?= escapar(jLocalForm(time()+1)) ?>" required></label>
    <label>Cupo máximo<input type="number" name="cupo" min="1" max="40" value="10" required></label>
    <label>Inscripción cierra<select name="cerrar_antes"><option value="0">Justo al iniciar</option><option value="15" selected>15 segundos antes</option><option value="30">30 segundos antes</option><option value="60">1 minuto antes</option></select></label>
    <button class="juego-btn primaria" type="submit">Crear sala ♛</button>
   </form><p class="programar-nota">El sistema permite hasta 12 salas pendientes o en curso por trivia. La partida se sincroniza desde el servidor al entrar.</p></section>
  <?php endif; ?>
 <?php endif; ?>
 <div id="juego-toast" class="juego-toast" role="status" aria-live="polite" hidden></div>
 <p class="juego-footer">TRILLAL — ¡Que comience el reto!</p>
</main></div></body></html>
