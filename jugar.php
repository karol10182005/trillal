<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php'; require __DIR__.'/config/juego.php';require __DIR__.'/config/juego_ui.php';
$usuario=exigirSesion();$db=conexion();jMantenimiento($db);
$usuario=usuarioActual() ?: $usuario; // reflejar Cultura acreditada en esta visita
$idCategoria=filter_var($_GET['categoria']??0,FILTER_VALIDATE_INT);$idCategoria=$idCategoria?:0;
$categorias=$db->query('SELECT id_categoria,nombre FROM categorias ORDER BY nombre')->fetchAll();
$sql="SELECT t.id_trivia,t.titulo,t.descripcion,t.modalidad,t.tiempo_limite_segundos,c.nombre AS categoria,u.nombre_usuario AS autor,
 (SELECT COUNT(*) FROM preguntas p WHERE p.id_trivia=t.id_trivia) AS preguntas,
 (SELECT COUNT(*) FROM salas s WHERE s.id_trivia=t.id_trivia AND s.estado='programada') AS salas_programadas
 FROM trivias t JOIN categorias c ON c.id_categoria=t.id_categoria JOIN usuarios u ON u.id_usuario=t.id_creador
 WHERE t.estado='publicada'";
$params=[];if($idCategoria){$sql.=' AND t.id_categoria=?';$params[]=$idCategoria;}
$sql.=' ORDER BY t.publicada_en DESC,t.id_trivia DESC LIMIT 80';
$q=$db->prepare($sql);$q->execute($params);$trivias=$q->fetchAll();
?>
<!doctype html><html lang="es"><head><?php jHeader('Jugar · Explorar');?></head>
<body class="pagina-inicio pagina-juego"><div class="app-layout"><?php jMenu($usuario,'jugar'); ?>
<main class="juego-main">
 <div class="juego-topline"><span class="etiqueta">TRILLAL / MODO JUGADOR</span><a class="juego-link" href="inicio.php">← Mi inicio</a></div>
 <section class="juego-hero"><div><span class="juego-eyebrow">♛ QUE GANE LA CULTURA</span><h1>El conocimiento<br><em>se pone a prueba.</em></h1><p>Elige una trivia. Entra a una sala, responde contra reloj y escala hasta el podio.</p><div class="juego-features"><span>◉ Salas en vivo</span><span>◷ Reloj sincronizado</span><span>♛ Cultura</span></div></div><div class="juego-hero-emblema" aria-hidden="true"><span>?!</span><small>¿ESTÁS LISTO?</small></div></section>
 <section class="juego-explorar"><div class="juego-heading"><div><span class="etiqueta">01 / DESCUBRE</span><h2>Trivias de la comunidad</h2></div><span class="juego-pill"><?= count($trivias) ?> disponibles</span></div>
 <div class="filtros-juego"><a class="filtro-juego <?= !$idCategoria?'seleccionado':'' ?>" href="jugar.php">Todas</a><?php foreach($categorias as $c): ?><a class="filtro-juego <?= $idCategoria===(int)$c['id_categoria']?'seleccionado':'' ?>" href="jugar.php?categoria=<?= (int)$c['id_categoria'] ?>"><?= escapar($c['nombre']) ?></a><?php endforeach; ?></div>
 <?php if(!$trivias): ?><div class="juego-vacio"><span>♟</span><h3>El tablero está vacío</h3><p>No hay trivias publicadas en esta categoría. ¡Puedes ser quien cree la primera!</p><a href="crear.php" class="juego-btn primaria">Crear mi trivia ↗</a></div><?php else: ?>
 <div class="juego-tarjetas"><?php foreach($trivias as $t): ?>
  <a class="juego-tarjeta" href="trivia.php?id=<?= (int)$t['id_trivia'] ?>"><div class="tarjeta-superior"><span class="tipo-chip <?= escapar($t['modalidad']) ?>"><?= $t['modalidad']==='privada'?'◇ PRIVADA':($t['modalidad']==='concurso'?'♛ CONCURSO':'♟ LIBRE') ?></span><span class="tarjeta-icono">♛</span></div><span class="juego-categoria"><?= escapar($t['categoria']) ?></span><h3><?= escapar($t['titulo']) ?></h3><p><?= escapar($t['descripcion'] ?: 'Pon a prueba tu conocimiento y consigue la mejor puntuación.') ?></p><div class="tarjeta-footer"><span><?= (int)$t['preguntas'] ?> preguntas · <?= (int)$t['salas_programadas'] ?> salas</span><strong>Jugar ↗</strong></div></a>
 <?php endforeach; ?></div><?php endif; ?>
 </section>
 <p class="juego-footer">TRILLAL © 2026 — La Cultura se gana jugando.</p>
</main></div></body></html>
