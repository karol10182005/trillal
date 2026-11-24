<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';require __DIR__.'/config/juego.php';require __DIR__.'/config/juego_ui.php';
$usuario=exigirSesion();$db=conexion();$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id || !($s=jSala($db,$id))){http_response_code(404);exit('La sala solicitada no existe. <a href="jugar.php">Volver</a>');}
$t=jTrivia($db,(int)$s['id_trivia']);
if(!jAcceso($db,$t,(int)$usuario['id_usuario']) || !jInscrito($db,(int)$id,(int)$usuario['id_usuario'])){
    redirigir('trivia.php?id='.(int)$s['id_trivia']);
}
?>
<!doctype html><html lang="es"><head><?php jHeader('Sala #'.$id);?></head>
<body class="pagina-inicio pagina-juego"><div class="app-layout"><?php jMenu($usuario,'jugar'); ?>
<main class="juego-main partida-main" data-sala-id="<?= (int)$id ?>" data-trivia-id="<?= (int)$s['id_trivia'] ?>" data-juego-csrf="<?= escapar(tokenCsrf()) ?>" id="app-partida">
 <div class="juego-topline"><span class="etiqueta">TRILLAL / SALA #<?= (int)$id ?></span><a class="juego-link" href="trivia.php?id=<?= (int)$s['id_trivia'] ?>">← Volver a la trivia</a></div>
 <header class="partida-cabecera"><div><span class="juego-eyebrow"><?= ($s['tipo_partida'] ?? "sala") === "rapida" ? "⚡ PARTIDA RÁPIDA · +10 PUNTOS" : ((($s['tipo_partida'] ?? "sala") === "normal") ? "◷ PARTIDA NORMAL" : "♛ COMPITE · RESPONDE · SUBE") ?></span><h1><?= escapar($s['titulo']) ?></h1><span id="sala-etiqueta" class="juego-pill">Preparando partida...</span></div><div class="reloj-partida" id="reloj-partida" role="timer" aria-label="Tiempo restante"><div class="reloj-icono">◷</div><div><small>TIEMPO RESTANTE</small><strong id="reloj-valor">--:--</strong></div></div></header>
 <div class="partida-layout"><section class="partida-panel" id="zona-juego" aria-live="polite"><div class="partida-cargando"><span class="partida-cargando-icono">?!</span><h2>Preparando el tablero...</h2><p>Sincronizando con el servidor de Trillal</p></div></section>
 <aside class="ranking-panel"><div class="ranking-encabezado"><div><span class="etiqueta">CLASIFICACIÓN</span><h2>En directo <span class="senal-live">●</span></h2></div><span id="ranking-numero" class="juego-pill">0</span></div><div class="mi-posicion"><small>TU POSICIÓN ACTUAL</small><div><strong id="mi-puesto">—</strong><span id="mis-puntos">0 pts</span></div></div><div id="ranking-diario" class="ranking-diario" hidden></div><div class="ranking-listado" id="ranking-listado"><p class="ranking-sin-datos">Los jugadores aparecerán al empezar.</p></div><div class="ranking-explicacion">♛ 1.º +25 · 2.º +20 · 3.º +20 de Cultura. En públicas el premio se entrega al cierre del día.</div></aside></div>
 <div id="juego-toast" class="juego-toast" role="status" aria-live="polite" hidden></div>
 <canvas class="capa-confeti" id="capa-confeti" aria-hidden="true"></canvas>
 <p class="juego-footer">TRILLAL — La gloria se decide pregunta a pregunta.</p>
</main></div></body></html>
