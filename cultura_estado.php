<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require __DIR__.'/config/juego.php';
require __DIR__.'/config/juego_ui.php';
$usuario=exigirSesion();
$db=conexion();
// En hosting sin cron: procesa jornadas vencidas cuando alguien entra aquí.
jMantenimiento($db);
$usuario=usuarioActual() ?: $usuario; // saldo fresco tras premiar
$uid=(int)$usuario['id_usuario'];
$q=$db->prepare("SELECT m.puesto,m.cantidad,m.otorgado_en,t.titulo,c.tipo,c.fecha_competencia
 FROM movimientos_cultura m
 INNER JOIN cierres_cultura c ON c.id_cierre=m.id_cierre
 INNER JOIN trivias t ON t.id_trivia=c.id_trivia
 WHERE m.id_usuario=? ORDER BY m.id_movimiento DESC LIMIT 20");
$q->execute([$uid]);$recompensas=$q->fetchAll();
$q=$db->prepare("SELECT DISTINCT s.id_trivia,s.fecha_competencia,t.titulo
 FROM intentos i
 INNER JOIN salas s ON s.id_sala=i.id_sala
 INNER JOIN trivias t ON t.id_trivia=s.id_trivia
 WHERE i.id_usuario=? AND t.modalidad<>'privada'
 AND NOT EXISTS (SELECT 1 FROM cierres_cultura c
                 WHERE c.id_trivia=s.id_trivia AND c.fecha_competencia=s.fecha_competencia
                   AND c.tipo='publica_diaria')
 ORDER BY s.fecha_competencia DESC LIMIT 20");
$q->execute([$uid]);$pendientes=$q->fetchAll();
foreach ($pendientes as &$pendiente) {
    $pendiente['puesto']=null;
    $ranking=jRankingDia($db,(int)$pendiente['id_trivia'], $pendiente['fecha_competencia']);
    foreach ($ranking as $r) {
        if ($r['id_usuario']===$uid) { $pendiente['puesto']=$r['puesto']; break; }
    }
}
unset($pendiente);
?>
<!doctype html><html lang="es"><head><?php jHeader('Mi Cultura'); ?>
<style>
.cultura-contenido{max-width:1060px;margin:36px auto;padding:0 24px 50px}
.cultura-caja{border:1px solid #574534;background:#211e1c;border-radius:18px;padding:26px;margin:18px 0}
.cultura-encabezado{font-size:14px;color:#d7b986;letter-spacing:.1em;text-transform:uppercase}
.cultura-grande{font-size:clamp(58px,10vw,105px);line-height:1.1;font-weight:900;color:#f1d4a0}
.cultura-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px}
.cultura-nota{color:#cfc5bd;line-height:1.7}
.cultura-fila{padding:14px 0;border-bottom:1px solid #51453b;line-height:1.55}
.cultura-fila:last-child{border:0}
.cultura-fila b{color:#f0d4a6}
.cultura-vacio{color:#b8a89a;font-size:14px}
</style></head><body class="pagina-inicio pagina-juego"><div class="app-layout"><?php jMenu($usuario,'perfil'); ?>
<main class="juego-main cultura-contenido"><div class="juego-topline"><span class="etiqueta">TRILLAL / RECOMPENSAS</span><a href="perfil.php" class="juego-link">← Volver a mi perfil</a></div>
<div class="cultura-caja"><p class="cultura-encabezado">♛ Cultura acumulada</p><div class="cultura-grande"><?= number_format((int)$usuario['cultura'],0,',','.') ?></div><p class="cultura-nota">La Cultura no es la puntuación de una partida. En trivias públicas los premios se conceden a los tres primeros al finalizar el día (hora de Ciudad de México). En trivias privadas, al terminar la sala. La rápida añade 10 puntos a tu puntuación, no a Cultura.</p></div>
<div class="cultura-grid"><section class="cultura-caja"><h2>Jornadas pendientes</h2>
<?php if(!$pendientes): ?><p class="cultura-vacio">No tienes premios públicos pendientes de cierre.</p><?php endif; ?>
<?php foreach ($pendientes as $p): ?>
<div class="cultura-fila"><b><?= escapar($p['titulo']) ?></b><br><small><?= escapar($p['fecha_competencia']) ?> · <?= $p['puesto'] ? 'Puesto provisional #'.(int)$p['puesto'] : 'Esperando clasificación' ?> · Aún sin premio definitivo</small></div>
<?php endforeach; ?>
</section><section class="cultura-caja"><h2>Últimas recompensas</h2>
<?php if(!$recompensas): ?><p class="cultura-vacio">Aún no has recibido Cultura. Si jugaste hoy una trivia pública, consulta de nuevo después del cierre del día.</p><?php endif; ?>
<?php foreach($recompensas as $r): ?><div class="cultura-fila"><b>+<?= (int)$r['cantidad'] ?> Cultura</b> · Puesto #<?= (int)$r['puesto'] ?><br><?= escapar($r['titulo']) ?><br><small><?= escapar($r['otorgado_en']) ?></small></div><?php endforeach; ?>
</section></div><p class="cultura-nota">Si una jornada antigua permanece pendiente, verifica que todas sus salas hayan terminado. La sincronización se ejecuta al entrar en esta página y en Jugar; no requiere vistas ni triggers.</p>
</main></div></body></html>
