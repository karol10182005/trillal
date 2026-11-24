<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/perfil.php';
require __DIR__ . '/config/juego.php';
require __DIR__ . '/config/juego_ui.php';
require __DIR__ . '/config/ranking.php';
$usuario=exigirSesion();
$db=conexion();
// En ByetHost sin cron, procesa los premios de jornadas YA terminadas.
// Si falla mantenimiento, la tabla de clasificación debe seguir visible.
try { jMantenimiento($db); }
catch (Throwable $e) { error_log('[Trillal Ranking / mantenimiento] '.$e->getMessage()); }
$usuario=usuarioActual() ?: $usuario;
$q=isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$q=preg_replace('/\s+/u',' ',$q) ?? '';
$q=function_exists('mb_substr') ? mb_substr($q,0,40,'UTF-8') : substr($q,0,40);
$pagina=filter_var($_GET['pagina']??1,FILTER_VALIDATE_INT);
$pagina=($pagina!==false && $pagina>0) ? min($pagina,100000) : 1;
$error=null;
try { $r=rankingDatos($db,$q,$pagina,$usuario); }
catch (Throwable $e) {
    error_log('[Trillal Ranking] '.$e->getMessage());
    $r=null;$error='No se pudo cargar la clasificación. Intenta recargar la página.';
}
$miId=(int)$usuario['id_usuario'];
$miCultura=(int)$usuario['cultura'];
$miPuesto=$r ? (int)$r['mi_puesto'] : 0;
$miPagina=$miPuesto>0 ? (int)floor(($miPuesto-1)/RANKING_POR_PAGINA)+1 : 1;
$podio=$r ? $r['top'] : [];
$podioActivo=$podio && (int)$podio[0]['cultura']>0;
$podioOrden=[1,0,2];
function rankingNumero(int $numero): string { return number_format($numero,0,',','.'); }
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="theme-color" content="#151413"><meta name="description" content="Ranking general de Cultura de Trillal. Descubre tu puesto y conoce a los mejores jugadores.">
<title>Ranking de Cultura | Trillal</title>
<link rel="stylesheet" href="assets/css/estilos.css">
<link rel="stylesheet" href="assets/css/juego.css">
<link rel="stylesheet" href="assets/css/ranking.css?v=1">
<script src="assets/js/ranking.js?v=1" defer></script>
</head><body class="pagina-inicio pagina-juego pagina-ranking"><div class="app-layout">
<?php jMenu($usuario,'ranking'); ?>
<main class="ranking-main" id="contenido">
 <div class="ranking-encima"><span class="etiqueta">TRILLAL / CLASIFICACIÓN GLOBAL</span><a href="inicio.php">← Volver al inicio</a></div>
 <header class="ranking-hero">
  <div class="ranking-hero-fondo" aria-hidden="true">♛</div>
  <div class="ranking-hero-texto"><span class="ranking-ceja">♛ EL SALÓN DE LA CULTURA</span><h1>El conocimiento <em>deja huella.</em></h1><p>Cada pregunta cuenta. Descubre quiénes lideran Trillal y hasta dónde has llegado tú.</p>
   <a href="jugar.php" class="ranking-hero-enlace">Explorar trivias <span aria-hidden="true">↗</span></a>
  </div>
  <div class="ranking-hero-stats" aria-label="Resumen de la comunidad">
    <div><small>JUGADORES</small><strong><?= $r ? rankingNumero((int)$r['resumen']['usuarios']) : '—' ?></strong></div>
    <div><small>CON CULTURA</small><strong><?= $r ? rankingNumero((int)$r['resumen']['jugadores_premiados']) : '—' ?></strong></div>
  </div>
 </header>
 <?php if($error): ?><div class="ranking-error" role="alert"><?= escapar($error) ?></div><?php endif; ?>
 <section class="ranking-mi-progreso" aria-label="Tu posición">
    <div class="ranking-mio-icono" aria-hidden="true">★</div>
    <div class="ranking-mi-cuerpo"><small>TU CAMINO EN TRILLAL</small><strong><?= escapar($usuario['nombre_usuario']) ?></strong><span>Tu Cultura se actualiza cuando se otorgan los premios.</span></div>
    <div class="ranking-mi-metrica"><small>POSICIÓN GLOBAL</small><b><?= $miPuesto ? '#'.rankingNumero($miPuesto) : '—' ?></b></div>
    <div class="ranking-mi-metrica"><small>TU CULTURA</small><b><?= rankingNumero($miCultura) ?></b></div>
    <a class="ranking-verme" href="<?= escapar(rankingUrl('', $miPagina, '#jugador-'.$miId)) ?>">Ver mi puesto <span aria-hidden="true">↗</span></a>
 </section>
 <section class="ranking-bloque" aria-labelledby="ranking-podio-titulo">
   <div class="ranking-seccion-top"><div><span class="ranking-etiqueta">01 / LOS DESTACADOS</span><h2 id="ranking-podio-titulo">El podio de Cultura.</h2></div><span class="ranking-ayuda">Clasificación acumulada</span></div>
   <?php if(!$podioActivo): ?>
     <div class="ranking-sin-podio"><span aria-hidden="true">♛</span><h3>El podio está esperando a sus primeros ganadores.</h3><p>Los premios públicos se suman al cerrar la jornada. En concursos privados, al finalizar la sala.</p></div>
   <?php else: ?>
   <div class="ranking-podio" aria-label="Tres primeros jugadores">
     <?php foreach($podioOrden as $i): if(!isset($podio[$i])) continue; $p=$podio[$i]; $puesto=$i+1; $avat=perfilAvatarSeguro($p['avatar_url'] ?? null); $esMio=(int)$p['id_usuario']===$miId; ?>
       <article class="ranking-podio-card puesto-<?= $puesto ?><?= $esMio?' mi-tarjeta':'' ?>" aria-label="Puesto <?= $puesto ?>: <?= escapar($p['nombre_usuario']) ?>">
         <span class="ranking-podio-numero"><?= $puesto===1?'♛':($puesto===2?'Ⅱ':'Ⅲ') ?></span>
         <div class="ranking-podio-avatar">
           <?php if($avat): ?><img src="<?= escapar($avat) ?>" alt="" loading="lazy" width="76" height="76">
           <?php else: ?><span aria-hidden="true"><?= escapar(perfilInicial($p['nombre_usuario'])) ?></span><?php endif; ?>
         </div>
         <small class="ranking-podio-insignia"><?= ['CAMPEÓN','SEGUNDO LUGAR','TERCER LUGAR'][$i] ?></small>
         <h3><?= escapar($p['nombre_usuario']) ?><?= $esMio?'<span class="ranking-tu">TÚ</span>':'' ?></h3>
         <p><strong><?= rankingNumero((int)$p['cultura']) ?></strong> Cultura</p>
         <div class="ranking-podio-pie"><span>✦ <?= (int)($r['medallas'][(int)$p['id_usuario']]['premios'] ?? 0) ?> premios</span><span>♛ <?= (int)($r['medallas'][(int)$p['id_usuario']]['oros'] ?? 0) ?> oros</span></div>
       </article>
     <?php endforeach; ?>
   </div>
   <?php endif; ?>
 </section>
 <section class="ranking-bloque ranking-clasificacion" aria-labelledby="ranking-lista-titulo">
   <div class="ranking-seccion-top ranking-busqueda-titulo"><div><span class="ranking-etiqueta">02 / TODOS LOS JUGADORES</span><h2 id="ranking-lista-titulo">Tabla de posiciones.</h2></div><span class="ranking-ayuda">Cultura · Mayor a menor</span></div>
   <form class="ranking-buscador" method="get" action="ranking.php" role="search">
     <label for="buscar-jugador" class="ranking-lupa">⌕ <span class="sr-ranking">Buscar un jugador</span></label>
     <input id="buscar-jugador" name="q" type="search" value="<?= escapar($q) ?>" placeholder="Busca un jugador por nombre..." maxlength="40" autocomplete="off">
     <button type="submit">Buscar <span aria-hidden="true">↗</span></button>
     <?php if($q!==''): ?><a class="ranking-limpiar" href="ranking.php">Limpiar</a><?php endif; ?>
   </form>
   <div class="ranking-lista" aria-live="polite">
     <div class="ranking-fila ranking-fila-titulos"><span>PUESTO</span><span>JUGADOR</span><span>CULTURA</span></div>
     <?php if(!$r || !$r['filas']): ?>
       <div class="ranking-no-resultados"><span aria-hidden="true">⌕</span><strong>Sin jugadores que mostrar</strong><p><?= $q!==''?'Prueba con otro nombre.':'La clasificación se llenará cuando haya cuentas activas.' ?></p></div>
     <?php else: foreach($r['filas'] as $jug): $esMio=(int)$jug['id_usuario']===$miId; $avat=perfilAvatarSeguro($jug['avatar_url'] ?? null); $puesto=(int)$jug['puesto']; ?>
     <div class="ranking-fila ranking-jugador<?= $esMio?' es-mio':'' ?>" <?= $esMio?'id="jugador-'.$miId.'"':'' ?>>
        <span class="ranking-fila-puesto <?= $puesto<=3?'destacado':'' ?>"><?= $puesto===1?'♛':('#'.rankingNumero($puesto)) ?></span>
        <div class="ranking-fila-identidad"><div class="ranking-mini-avatar"><?php if($avat): ?><img src="<?= escapar($avat) ?>" width="42" height="42" loading="lazy" alt=""><?php else: ?><?= escapar(perfilInicial($jug['nombre_usuario'])) ?><?php endif; ?></div><div class="ranking-fila-nombre"><strong><?= escapar($jug['nombre_usuario']) ?></strong><?php if($esMio): ?><span>ESTÁS AQUÍ</span><?php endif; ?></div></div>
        <div class="ranking-fila-cultura"><strong><?= rankingNumero((int)$jug['cultura']) ?></strong><small>Cultura</small></div>
     </div>
     <?php endforeach; endif; ?>
   </div>
   <div class="ranking-paginacion">
     <span><?= $r ? rankingNumero($r['cuenta']) : 0 ?> <?= $q!==''?'coincidencias':'jugadores' ?> · Página <?= $r ? $r['pagina'] : 1 ?> de <?= $r ? $r['paginas'] : 1 ?></span>
     <?php if($r && $r['paginas']>1): ?><nav aria-label="Páginas de clasificación"><a class="ranking-pagina<?= $r['pagina']<=1?' inactiva':'' ?>" <?= $r['pagina']<=1?'aria-disabled="true" tabindex="-1"':'' ?> href="<?= escapar(rankingUrl($q,max(1,$r['pagina']-1))) ?>">← Anterior</a><a class="ranking-pagina<?= $r['pagina']>=$r['paginas']?' inactiva':'' ?>" <?= $r['pagina']>=$r['paginas']?'aria-disabled="true" tabindex="-1"':'' ?> href="<?= escapar(rankingUrl($q,min($r['paginas'],$r['pagina']+1))) ?>">Siguiente →</a></nav><?php endif; ?>
   </div>
 </section>
 <aside class="ranking-nota"><span aria-hidden="true">✦</span><p><strong>¿Cómo se gana Cultura?</strong> En cada trivia pública, los primeros tres del día reciben 25, 20 y 20 puntos respectivamente una vez que termina la jornada (hora de Ciudad de México). En las privadas, los premios se entregan por sala terminada. Si hay un empate en Cultura global, aparece antes la cuenta con ID menor.</p><a href="cultura_estado.php">Ver mis premios ↗</a></aside>
 <footer class="ranking-pie">TRILLAL · EL CONOCIMIENTO TAMBIÉN TIENE SU LUGAR EN EL PODIO</footer>
</main></div></body></html>
