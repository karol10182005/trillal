<?php
declare(strict_types=1);
require __DIR__.'/config/bootstrap.php';
require __DIR__.'/config/perfil.php';
require __DIR__.'/config/juego_ui.php';
$usuario = exigirSesion();
$db = conexion();
$fechaCuenta = $db->prepare('SELECT creado_en FROM usuarios WHERE id_usuario=? LIMIT 1');
$fechaCuenta->execute([(int)$usuario['id_usuario']]);
$usuario['creado_en'] = $fechaCuenta->fetchColumn() ?: null;
$stats = perfilEstadisticas($db, $usuario);
$misTriviasPerfil = perfilMisTrivias($db, (int)$usuario['id_usuario']);
$historial = perfilHistorial($db, (int)$usuario['id_usuario']);
$premios = perfilPremios($db, (int)$usuario['id_usuario']);
$avatar = perfilAvatarSeguro($usuario['avatar_url'] ?? null);
$ok = $_SESSION['flash']['perfil_ok'] ?? null;
$error = $_SESSION['flash']['perfil_error'] ?? null;
unset($_SESSION['flash']['perfil_ok'], $_SESSION['flash']['perfil_error']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#141414">
  <meta name="description" content="Tu perfil de Trillal: Cultura, partidas, premios e identidad de jugador.">
  <title>Mi perfil | Trillal</title>
  <link rel="stylesheet" href="assets/css/estilos.css">
  <link rel="stylesheet" href="assets/css/perfil.css?v=5trivias1">
  <script src="assets/js/perfil.js" defer></script>
</head>
<body class="pagina-inicio pagina-perfil">
<div class="app-layout">
  <?php jMenu($usuario, 'perfil'); ?>
  <main class="perfil-main">
    <div class="perfil-topline"><span class="etiqueta">TU HISTORIA EN TRILLAL / <b>PERFIL</b></span><a href="inicio.php">← Volver al inicio</a></div>
    <div class="perfil-section-heading"><div><p class="perfil-kicker">JUGADOR / <?= str_pad((string)(int)$usuario['id_usuario'], 5, '0', STR_PAD_LEFT) ?></p><h1>Mi <span>perfil.</span></h1></div><a href="#ajustes" class="perfil-action-link">⚙ Editar perfil <span>↗</span></a></div>
    <?php if ($ok): ?><div class="perfil-alerta bien" role="status">✓ <?= escapar($ok) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="perfil-alerta mal" role="alert">! <?= escapar($error) ?></div><?php endif; ?>

    <section class="perfil-hero" aria-label="Resumen del jugador">
      <div class="perfil-hero-halo" aria-hidden="true">?!</div>
      <div class="perfil-identidad">
        <div class="perfil-avatar-grande" id="avatar">
          <?php if ($avatar): ?><img src="<?= escapar($avatar) ?>" alt="Avatar de <?= escapar($usuario['nombre_usuario']) ?>" width="128" height="128">
          <?php else: ?><span aria-hidden="true"><?= escapar(perfilInicial((string)$usuario['nombre_usuario'])) ?></span><?php endif; ?>
          <a href="#ajustes" class="perfil-avatar-editar" title="Cambiar avatar" aria-label="Cambiar foto de perfil">✎</a>
        </div>
        <div class="perfil-nombre">
          <span class="perfil-sello">✦ MIEMBRO DE LA COMUNIDAD</span>
          <h2><?= escapar($usuario['nombre_usuario']) ?></h2>
          <span class="perfil-metadatos">Miembro desde <?= escapar(perfilFecha($usuario['creado_en'] ?? null)) ?> <span>·</span> ID #<?= (int)$usuario['id_usuario'] ?></span>
        </div>
      </div>
      <div class="perfil-cultura">
        <span>♛ TU CULTURA</span>
        <strong data-contador="<?= (int)$usuario['cultura'] ?>"><?= number_format((int)$usuario['cultura'], 0, ',', '.') ?></strong>
        <div><small>POSICIÓN GLOBAL</small><b>#<?= number_format($stats['posicion'], 0, ',', '.') ?></b></div>
      </div>
    </section>

    <nav class="perfil-anclas" aria-label="Secciones del perfil">
      <a href="#estadisticas">Estadísticas</a><a href="#historial">Partidas</a><a href="#premios">Recompensas</a><a href="#ajustes">Configuración</a>
    </nav>

    <section class="perfil-bloque" id="estadisticas">
      <div class="perfil-titulos"><div><span class="etiqueta">01 / TU PROGRESO</span><h2>Los números hablan.</h2></div><span class="perfil-leyenda">Datos reales de MySQL</span></div>
      <div class="perfil-stats-grid">
        <article class="perfil-stat"><span class="perfil-stat-icono">⚑</span><small>PARTIDAS TERMINADAS</small><strong data-contador="<?= $stats['partidas'] ?>"><?= $stats['partidas'] ?></strong><p>Trivias que lograste completar</p></article>
        <article class="perfil-stat"><span class="perfil-stat-icono">✧</span><small>PRECISIÓN</small><strong><?= $stats['precision'] ?>%</strong><div class="perfil-progreso" aria-label="<?= $stats['precision'] ?>% de aciertos"><span style="width:<?= $stats['precision'] ?>%"></span></div><p><?= $stats['aciertos'] ?> de <?= $stats['respondidas'] ?> respuestas acertadas</p></article>
        <article class="perfil-stat"><span class="perfil-stat-icono">♛</span><small>PRIMEROS LUGARES</small><strong data-contador="<?= $stats['oro'] ?>"><?= $stats['oro'] ?></strong><p>Premios de primer puesto recibidos</p></article>
        <article class="perfil-stat"><span class="perfil-stat-icono">★</span><small>MEJOR PUNTUACIÓN</small><strong data-contador="<?= $stats['mejor_puntuacion'] ?>"><?= number_format($stats['mejor_puntuacion'], 0, ',', '.') ?></strong><p>Tu puntuación más alta en una partida</p></article>
      </div>
    </section>

    <div class="perfil-dos-columnas">
      <section class="perfil-bloque" id="premios">
        <div class="perfil-titulos"><div><span class="etiqueta">02 / VITRINA</span><h2>Tu colección.</h2></div></div>
        <div class="perfil-panel perfil-vitrina">
          <p class="perfil-descripcion-panel">Cada premio representa una clasificación premiada en Trillal.</p>
          <div class="perfil-medallas">
            <div class="perfil-medalla medalla-oro"><span>Ⅰ</span><strong><?= $stats['oro'] ?></strong><small>ORO</small></div>
            <div class="perfil-medalla medalla-plata"><span>Ⅱ</span><strong><?= $stats['plata'] ?></strong><small>PLATA</small></div>
            <div class="perfil-medalla medalla-bronce"><span>Ⅲ</span><strong><?= $stats['bronce'] ?></strong><small>BRONCE</small></div>
          </div>
          <div class="perfil-nota-premio"><span>♛</span><p>Premios recibidos: <b><?= (int)$stats['premios'] ?></b>. Primero: +25 Cultura; segundo y tercero: +20 Cultura.</p></div>
        </div>
      </section>
      <section class="perfil-bloque" id="mi-trivia">
        <div class="perfil-titulos"><div><span class="etiqueta">03 / MI CREACIÓN</span><h2>Tus trivias.</h2></div></div>
        <div class="perfil-panel perfil-tarjeta-trivia">
          <p class="perfil-descripcion-panel">Tienes <?= count($misTriviasPerfil) ?> de 5 trivias. Cada una se administra por separado.</p>
          <?php if ($misTriviasPerfil): ?>
            <div class="perfil-lista-trivias">
            <?php foreach ($misTriviasPerfil as $item): ?>
              <div class="perfil-trivia-fila">
                <div><span class="perfil-estado <?= escapar($item['estado']) ?>">● <?= escapar(ucfirst($item['estado'])) ?></span>
                  <h3><?= escapar($item['titulo']) ?></h3>
                  <p><?= escapar(ucfirst($item['modalidad'])) ?> · <?= (int)$item['preguntas'] ?> preguntas · <?= (int)$item['salas'] ?> salas</p></div>
                <a href="crear.php?id=<?= (int)$item['id_trivia'] ?>" class="perfil-btn secundaria">Editar ↗</a>
              </div>
            <?php endforeach; ?>
            </div>
          <?php else: ?>
            <h3>Tu primera trivia te espera.</h3><p>Diseña las preguntas y compártela con la comunidad.</p>
          <?php endif; ?>
          <a href="crear.php" class="perfil-btn secundaria">Administrar mis trivias ↗</a>
          <?php if (count($misTriviasPerfil) < 5): ?><a href="crear.php?nueva=1" class="perfil-btn principal">+ Nueva trivia ↗</a><?php endif; ?>
        </div>
      </section>
    </div>

    <section class="perfil-bloque" id="historial">
      <div class="perfil-titulos"><div><span class="etiqueta">04 / ARCHIVO DE JUEGO</span><h2>Tus últimas partidas.</h2></div><a href="jugar.php" class="perfil-enlace-inline">Jugar otra vez ↗</a></div>
      <div class="perfil-panel perfil-historial">
        <?php if (!$historial): ?>
          <div class="perfil-vacio"><span>◇</span><h3>Todavía no hay partidas.</h3><p>Cuando juegues, podrás consultar aquí tus puntuaciones, aciertos y tiempos.</p><a href="jugar.php" class="perfil-btn principal">Explorar trivias ↗</a></div>
        <?php else: ?>
          <div class="perfil-tabla-contenedor"><table class="perfil-tabla"><thead><tr><th>TRIVIA</th><th>RESULTADO</th><th>ACIERTOS</th><th>TIEMPO</th><th>FECHA</th><th></th></tr></thead><tbody>
          <?php foreach ($historial as $h): ?>
            <tr><td><strong><?= escapar($h['titulo']) ?></strong><span><?= escapar($h['categoria']) ?> · <?= escapar(ucfirst($h['modalidad'])) ?></span></td>
              <td><b><?= number_format((int)$h['puntuacion_total'], 0, ',', '.') ?> pts</b><span class="perfil-estado-partida <?= escapar($h['estado']) ?>"><?= ['finalizado'=>'Finalizada','en_curso'=>'En curso','abandonado'=>'Abandonada'][$h['estado']] ?? '—' ?></span></td>
              <td><?= (int)$h['respuestas_correctas'] ?></td><td><?= escapar(perfilSegundos($h['tiempo_total_segundos'])) ?></td>
              <td><?= escapar(perfilFecha($h['iniciado_en'], true)) ?></td>
              <td><a href="<?= $h['id_sala'] !== null ? 'sala.php?id='.(int)$h['id_sala'] : 'trivia.php?id='.(int)$h['id_trivia'] ?>" class="perfil-fila-link" aria-label="Ver partida de <?= escapar($h['titulo']) ?>">↗</a></td>
            </tr>
          <?php endforeach; ?></tbody></table></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="perfil-bloque" id="actividad">
      <div class="perfil-titulos"><div><span class="etiqueta">05 / CULTURA RECIBIDA</span><h2>Recompensas recientes.</h2></div></div>
      <div class="perfil-panel perfil-actividad">
        <?php if (!$premios): ?><div class="perfil-vacio compacto"><span>♛</span><h3>Aún no tienes premios.</h3><p>Compite por los primeros tres puestos para empezar a sumar Cultura.</p></div>
        <?php else: ?>
          <?php foreach ($premios as $p): ?>
          <div class="perfil-actividad-fila"><div class="perfil-actividad-icono">♛</div><div class="perfil-actividad-info"><strong><?= escapar($p['titulo']) ?></strong><span><?= (int)$p['puesto'] ?>.º lugar · <?= $p['tipo'] === 'privada_sala' ? 'Sala privada' : 'Clasificación diaria' ?> · <?= escapar(perfilFecha($p['otorgado_en'], true)) ?></span></div><b>+<?= (int)$p['cantidad'] ?> Cultura</b></div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </section>

    <section class="perfil-bloque perfil-ajustes" id="ajustes">
      <div class="perfil-titulos"><div><span class="etiqueta">06 / TU CUENTA</span><h2>Hazlo a tu manera.</h2></div><span class="perfil-leyenda">Datos protegidos</span></div>
      <div class="perfil-ajustes-grid">
        <section class="perfil-panel perfil-config-card" aria-labelledby="titulo-avatar">
          <div class="perfil-config-head"><span class="perfil-config-icono">◉</span><div><h3 id="titulo-avatar">Foto de perfil</h3><p>Una imagen que te represente.</p></div></div>
          <form method="post" action="perfil_accion.php" enctype="multipart/form-data" class="perfil-config-form" id="form-avatar">
            <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="avatar">
            <div class="perfil-upload"><div class="perfil-avatar-preview" id="avatar-preview">
              <?php if ($avatar): ?><img src="<?= escapar($avatar) ?>" alt="Tu imagen actual">
              <?php else: ?><span><?= escapar(perfilInicial((string)$usuario['nombre_usuario'])) ?></span><?php endif; ?></div>
              <label for="avatar-input" class="perfil-archivo-btn">Elegir imagen <span>↗</span></label><input type="file" accept="image/jpeg,image/png,image/webp" name="avatar" id="avatar-input" required><small id="nombre-archivo">JPG, PNG o WEBP · Máximo 2 MB</small>
            </div>
            <button type="submit" class="perfil-btn principal">Guardar foto <span>↗</span></button>
          </form>
          <?php if ($avatar): ?><form action="perfil_accion.php" method="post" class="perfil-eliminar-form" data-confirmar="¿Quitar tu foto actual?"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="quitar_avatar"><button type="submit">Eliminar foto actual</button></form><?php endif; ?>
        </section>
        <section class="perfil-panel perfil-config-card" aria-labelledby="titulo-datos">
          <div class="perfil-config-head"><span class="perfil-config-icono">✎</span><div><h3 id="titulo-datos">Datos personales</h3><p>Actualiza tu usuario y tu correo.</p></div></div>
          <form method="post" action="perfil_accion.php" class="perfil-config-form">
            <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="datos">
            <label>Nombre de usuario <input type="text" name="nombre_usuario" required minlength="3" maxlength="30" value="<?= escapar($usuario['nombre_usuario']) ?>" autocomplete="nickname"></label>
            <label>Correo electrónico <input type="email" name="correo" required maxlength="150" value="<?= escapar($usuario['correo']) ?>" autocomplete="email"></label>
            <label>Contraseña actual <input type="password" name="contrasena_actual" required autocomplete="current-password" placeholder="Confirma tu identidad"></label>
            <button type="submit" class="perfil-btn principal">Guardar cambios <span>↗</span></button>
          </form>
        </section>
        <section class="perfil-panel perfil-config-card perfil-seguridad" id="seguridad" aria-labelledby="titulo-seguridad">
          <div class="perfil-config-head"><span class="perfil-config-icono">◇</span><div><h3 id="titulo-seguridad">Cambiar contraseña</h3><p>Protege tu cuenta y tu Cultura.</p></div></div>
          <form method="post" action="perfil_accion.php" class="perfil-config-form">
            <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="clave">
            <label>Contraseña actual <input type="password" name="contrasena_actual" required autocomplete="current-password"></label>
            <label>Nueva contraseña <input type="password" name="contrasena_nueva" required minlength="8" maxlength="72" autocomplete="new-password"></label>
            <label>Confirmar nueva contraseña <input type="password" name="contrasena_confirmar" required minlength="8" maxlength="72" autocomplete="new-password"></label>
            <button type="submit" class="perfil-btn principal">Actualizar contraseña <span>↗</span></button>
          </form>
        </section>
        <div class="perfil-tip"><span>✦</span><div><h3>Tu Cultura, a salvo.</h3><p>Cambiar el nombre, correo, foto o contraseña no modifica tus partidas, premios ni puntos de Cultura. Todos siguen vinculados al identificador de tu cuenta.</p><a href="jugar.php">Seguir compitiendo ↗</a></div></div>
      </div>
    </section>
    <footer class="perfil-footer"><span>TRILLAL / TU CULTURA TE ACOMPAÑA</span><span>Hecho para los que nunca dejan de aprender. ✦</span></footer>
  </main>
</div>
</body>
</html>
