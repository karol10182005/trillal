<?php
declare(strict_types=1);
require_once __DIR__.'/perfil.php';
function jMenu(array $usuario, string $activo): void { ?>
<aside class="barra-lateral">
  <a href="inicio.php" class="marca-lateral" aria-label="Trillal inicio"><img src="assets/img/logo-trillal.png" alt="Trillal" width="92"></a>
  <nav class="navegacion-lateral" aria-label="Menú principal">
    <a href="jugar.php" class="enlace-menu <?= $activo==='jugar'?'activo':'' ?>"><span class="icono-menu">?!</span> Jugar</a>
    <a href="crear.php" class="enlace-menu <?= $activo==='crear'?'activo':'' ?>"><span class="icono-menu">✎</span> Crear</a>
    <a href="perfil.php" class="enlace-menu <?= $activo==='perfil'?'activo':'' ?>"><span class="icono-menu">♛</span> Perfil</a>
    <a href="ranking.php" class="enlace-menu <?= $activo==='ranking'?'activo':'' ?>"><span class="icono-menu">★</span> Ranking</a>
  </nav>
  <div class="usuario-lateral">
    <a href="perfil.php" class="avatar-letra" aria-label="Abrir mi perfil" style="overflow:hidden;text-decoration:none">
      <?php if ($a = perfilAvatarSeguro($usuario['avatar_url'] ?? null)): ?>
        <img src="<?= escapar($a) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
      <?php else: ?><?= escapar(perfilInicial($usuario['nombre_usuario'])) ?><?php endif; ?>
    </a>
    <div class="usuario-datos"><a href="perfil.php" style="text-decoration:none"><strong><?= escapar($usuario['nombre_usuario']) ?></strong></a><span><?= (int)$usuario['cultura'] ?> Cultura</span></div>
  </div>
  <form action="logout.php" method="post" class="form-cerrar"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><button type="submit" class="cerrar-sesion">Cerrar sesión <span>↗</span></button></form>
</aside>
<?php }
function jMetaFecha(string $utc): string {
    return (new DateTimeImmutable($utc,jUTC()))->setTimezone(jZona())->format('d/m/Y · h:i a');
}
function jHeader(string $titulo): void { ?>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#141414"><title><?= escapar($titulo) ?> | Trillal</title>
<link rel="stylesheet" href="assets/css/estilos.css"><link rel="stylesheet" href="assets/css/juego.css"><script src="assets/js/juego.js" defer></script>
<?php }
