<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/creador.php';
require __DIR__ . '/config/perfil.php';
$usuario = exigirSesion();
$db = conexion();
$uid = (int)$usuario['id_usuario'];
$listaTrivias = misTrivias($db, $uid);
$totalTrivias = count($listaTrivias);
$idSeleccionado = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$idSeleccionado = $idSeleccionado ?: 0;
$nueva = isset($_GET['nueva']) && $_GET['nueva'] === '1';
$trivia = $idSeleccionado > 0 ? triviaPropia($db, $uid, $idSeleccionado) : null;
$mostrarEditor = ($trivia !== null || ($nueva && $totalTrivias < TRILLAL_LIMITE_TRIVIAS));
$errorSeleccion = $idSeleccionado > 0 && !$trivia ? 'La trivia no existe o no es de tu cuenta.' : null;
if ($nueva && $totalTrivias >= TRILLAL_LIMITE_TRIVIAS) {
    $errorSeleccion = 'Ya tienes cinco trivias. Edita una de ellas desde tu lista.';
}
if (!$idSeleccionado && !$nueva && isset($_GET['editar']) && $totalTrivias === 1) {
    // Compatibilidad con enlaces antiguos para editar preguntas.
    redirigir('crear.php?id=' . (int)$listaTrivias[0]['id_trivia'] . '&editar=' . (int)$_GET['editar'] . '#preguntas');
}
$categorias = $db->query('SELECT id_categoria, nombre FROM categorias ORDER BY nombre')->fetchAll();
$preguntas = $trivia ? preguntasDeTrivia($db, (int) $trivia['id_trivia']) : [];
$editable = !$trivia || $trivia['estado'] === 'borrador';
$preguntaEditar = null;
if ($trivia && $editable && isset($_GET['editar'])) {
    $id = filter_var($_GET['editar'], FILTER_VALIDATE_INT);
    foreach ($preguntas as $item) {
        if ($id && (int) $item['id_pregunta'] === $id) {
            $preguntaEditar = $item;
            break;
        }
    }
}
$flashOk = $_SESSION['flash']['crear_ok'] ?? null;
$flashError = $_SESSION['flash']['crear_error'] ?? null;
unset($_SESSION['flash']['crear_ok'], $_SESSION['flash']['crear_error']);
$modoActual = $trivia['modalidad'] ?? 'libre';
$minutosActual = isset($trivia['tiempo_limite_segundos']) && (int) $trivia['tiempo_limite_segundos'] > 0
    ? (int) ((int) $trivia['tiempo_limite_segundos'] / 60) : 0;
$letras = ['A', 'B', 'C', 'D'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141414">
    <title>Trillal | Mis trivias</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="stylesheet" href="assets/css/creador.css?v=5trivias1">
    <script src="assets/js/creador.js" defer></script>
</head>
<body class="pagina-inicio pagina-creador">
<div class="app-layout">
    <aside class="barra-lateral">
        <a href="inicio.php" class="marca-lateral" aria-label="Trillal Inicio"><img src="assets/img/logo-trillal.png" width="92" alt="Trillal"></a>
        <nav class="navegacion-lateral" aria-label="Navegación">
            <a class="enlace-menu" href="jugar.php"><span class="icono-menu" aria-hidden="true">?!</span> Jugar</a>
            <a class="enlace-menu activo" href="crear.php"><span class="icono-menu" aria-hidden="true">✎</span> Crear</a>
            <a class="enlace-menu" href="perfil.php"><span class="icono-menu" aria-hidden="true">♛</span> Perfil</a>
                <a class="enlace-menu" href="ranking.php"><span class="icono-menu" aria-hidden="true">★</span> Ranking</a>
        </nav>
        <div class="usuario-lateral">
            <a href="perfil.php" class="avatar-letra" aria-label="Abrir mi perfil" style="overflow:hidden;text-decoration:none">
                <?php if ($a = perfilAvatarSeguro($usuario['avatar_url'] ?? null)): ?>
                    <img src="<?= escapar($a) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                <?php else: ?><?= escapar(perfilInicial($usuario['nombre_usuario'])) ?><?php endif; ?>
            </a>
            <div class="usuario-datos"><a href="perfil.php" style="text-decoration:none"><strong><?= escapar($usuario['nombre_usuario']) ?></strong></a><span><?= (int) $usuario['cultura'] ?> Cultura</span></div>
        </div>
        <form class="form-cerrar" action="logout.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
            <button class="cerrar-sesion" type="submit">Cerrar sesión <span aria-hidden="true">↗</span></button>
        </form>
    </aside>
    <main class="contenido-creador">
        <div class="topbar-creador"><span class="etiqueta">TALLER DE TRIVIAS · <?= (int)$totalTrivias ?> / 5</span><a href="<?= $mostrarEditor ? 'crear.php' : 'inicio.php' ?>" class="volver-inicio">← <?= $mostrarEditor ? 'Mis trivias' : 'Volver al inicio' ?></a></div>
        <header class="hero-creador">
            <div><h1><?= $mostrarEditor ? 'La próxima gran pregunta <span>la creas tú.</span>' : 'Tus ideas, <span>tus trivias.</span>' ?></h1><p><?= $mostrarEditor ? 'Diseña tu trivia, prepara las respuestas y compártela con la comunidad.' : 'Crea hasta cinco trivias y administra cada una desde tu estudio.' ?></p></div>
            <div class="distintivo-creador"><span aria-hidden="true">♛</span><strong>TRILLAL</strong><small>CREATOR STUDIO</small></div>
        </header>
        <?php if ($flashOk): ?><div class="creador-alerta exito" role="status">✓ <?= escapar($flashOk) ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="creador-alerta error" role="alert">! <?= escapar($flashError) ?></div><?php endif; ?>
        <?php if ($errorSeleccion): ?><div class="creador-alerta error" role="alert">! <?= escapar($errorSeleccion) ?></div><?php endif; ?>
        <?php if (!$mostrarEditor): ?>
        <section class="biblioteca-trivias" aria-label="Mis trivias">
            <div class="biblioteca-titulo"><div><span class="etiqueta">TU COLECCIÓN</span><h2>Mis trivias <span><?= $totalTrivias ?> / 5</span></h2><p>Elige una creación para continuar editándola o publica una nueva idea.</p></div>
            <?php if ($totalTrivias < TRILLAL_LIMITE_TRIVIAS): ?><a class="btn-creador-principal" href="crear.php?nueva=1">+ Nueva trivia <span>↗</span></a><?php else: ?><span class="biblioteca-limite">♛ Has utilizado tus 5 espacios</span><?php endif; ?></div>
            <?php if (!$listaTrivias): ?>
            <div class="biblioteca-vacia"><span aria-hidden="true">✎</span><h3>Tu primera trivia te espera</h3><p>Elige un tema, crea las preguntas y compártela con otros jugadores.</p><a class="btn-creador-principal" href="crear.php?nueva=1">Crear mi primera trivia ↗</a></div>
            <?php else: ?>
            <div class="biblioteca-grid">
            <?php foreach ($listaTrivias as $item): ?>
               <article class="biblioteca-card">
                 <div class="biblioteca-card-arriba"><span class="biblioteca-icono" aria-hidden="true">♛</span><span class="biblioteca-estado <?= escapar($item['estado']) ?>"><?= escapar(ucfirst($item['estado'])) ?></span></div>
                 <small class="biblioteca-categoria"><?= escapar($item['categoria_nombre']) ?> · <?= escapar(ucfirst($item['modalidad'])) ?></small>
                 <h3><?= escapar($item['titulo']) ?></h3>
                 <p><?= escapar($item['descripcion'] ?: 'Tu trivia de Trillal.') ?></p>
                 <div class="biblioteca-info"><span><?= (int)$item['preguntas'] ?> preguntas</span><span><?= (int)$item['salas'] ?> salas</span></div>
                 <a class="biblioteca-abrir" href="crear.php?id=<?= (int)$item['id_trivia'] ?>">Abrir editor <span>↗</span></a>
               </article>
            <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <p class="biblioteca-nota">Las partidas y la Cultura no se alteran cuando creas otra trivia. Cada una conserva sus preguntas y clasificaciones.</p>
        </section>
        <?php else: ?>
        <div class="editor-regreso"><a class="btn-creador-secundario" href="crear.php">← Mis trivias (<?= $totalTrivias ?>/5)</a><?php if (!$trivia): ?><span>Creando trivia <?= $totalTrivias + 1 ?> de 5</span><?php else: ?><span>Editando: <?= escapar($trivia['titulo']) ?></span><?php endif; ?></div>
        <div class="estadisticas-creador">
            <div><small>MIS TRIVIAS</small><strong><?= $totalTrivias ?> <span>/ 5</span></strong></div>
            <div><small>PREGUNTAS</small><strong><?= count($preguntas) ?> <span>/ 40</span></strong></div>
            <div><small>ESTADO</small><strong class="estado-creador <?= $trivia && $trivia['estado']==='publicada' ? 'publicada' : '' ?>"><?= $trivia ? escapar(ucfirst($trivia['estado'])) : 'Sin crear' ?></strong></div>
        </div>
        <div class="creador-seccion" id="configuracion">
            <div class="cabecera-seccion-creador"><div><span class="etiqueta">PASO 01</span><h2>Configura tu trivia</h2></div><span>01 — 02</span></div>
            <section class="panel-creador">
                <?php if ($trivia && !$editable): ?>
                    <div class="aviso-publicada">Tu trivia ya está publicada. Para cambiarla primero debes devolverla a borrador. Solo se permitirá si todavía no tiene salas o partidas.</div>
                <?php endif; ?>
                <form method="post" action="crear_accion.php" class="form-creador" id="form-configuracion">
                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                    <input type="hidden" name="accion" value="guardar_trivia"><input type="hidden" name="id_trivia" value="<?= (int)($trivia['id_trivia'] ?? 0) ?>">
                    <div class="dos-columnas-campos">
                        <label class="campo-creador"><span>Nombre de la trivia <em>*</em></span><input type="text" name="titulo" maxlength="150" required placeholder="Ej. ¿Cuánto sabes de ajedrez?" value="<?= escapar($trivia['titulo'] ?? '') ?>" <?= !$editable ? 'disabled' : '' ?>></label>
                        <label class="campo-creador"><span>Categoría <em>*</em></span><select name="id_categoria" required <?= !$editable ? 'disabled' : '' ?>><option value="">Selecciona una categoría</option><?php foreach ($categorias as $c): ?><option value="<?= (int) $c['id_categoria'] ?>" <?= (int) ($trivia['id_categoria'] ?? 0) === (int) $c['id_categoria'] ? 'selected' : '' ?>><?= escapar($c['nombre']) ?></option><?php endforeach; ?></select></label>
                    </div>
                    <label class="campo-creador"><span>Descripción <small>(opcional)</small></span><textarea name="descripcion" rows="3" maxlength="1500" placeholder="Cuenta un poco sobre el reto que estás preparando..." <?= !$editable ? 'disabled' : '' ?>><?= escapar($trivia['descripcion'] ?? '') ?></textarea></label>
                    <div class="grupo-subtitulo"><strong>Selecciona una modalidad</strong><small>Elige cómo podrá entrar la comunidad</small></div>
                    <div class="modalidades-grid" id="modalidades-grid">
                        <label class="tarjeta-modalidad"><input type="radio" name="modalidad" value="libre" <?= $modoActual === 'libre' ? 'checked' : '' ?> <?= !$editable ? 'disabled' : '' ?>><span class="modalidad-icono">♟</span><strong>LIBRE</strong><small>Abierta a todos. Juegan a su ritmo.</small><span class="indicador-modalidad" aria-hidden="true"></span></label>
                        <label class="tarjeta-modalidad"><input type="radio" name="modalidad" value="privada" <?= $modoActual === 'privada' ? 'checked' : '' ?> <?= !$editable ? 'disabled' : '' ?>><span class="modalidad-icono">◇</span><strong>PRIVADA</strong><small>Solo entra quien tenga la contraseña.</small><span class="indicador-modalidad" aria-hidden="true"></span></label>
                        <label class="tarjeta-modalidad"><input type="radio" name="modalidad" value="concurso" <?= $modoActual === 'concurso' ? 'checked' : '' ?> <?= !$editable ? 'disabled' : '' ?>><span class="modalidad-icono">♛</span><strong>CONCURSO</strong><small>Competencia pública en salas y con reloj.</small><span class="indicador-modalidad" aria-hidden="true"></span></label>
                    </div>
                    <div class="dos-columnas-campos secundarios">
                        <label class="campo-creador"><span>Temporizador total</span><select name="tiempo_minutos" id="tiempo-minutos" <?= !$editable ? 'disabled' : '' ?>><option value="0" <?= $minutosActual === 0 ? 'selected' : '' ?>>Sin límite</option><?php foreach ([1,2,3,5,10,15,20,30] as $min): ?><option value="<?= $min ?>" <?= $minutosActual === $min ? 'selected' : '' ?>><?= $min ?> <?= $min === 1 ? 'minuto' : 'minutos' ?></option><?php endforeach; ?></select><small>Los concursos necesitan un tiempo límite.</small></label>
                        <label class="campo-creador" id="campo-clave-privada"><span>Contraseña de acceso <?= $modoActual === 'privada' ? '<em>*</em>' : '' ?></span><input type="password" name="contrasena_trivia" id="clave-privada" minlength="6" maxlength="72" autocomplete="new-password" placeholder="Mínimo 6 caracteres" <?= !$editable ? 'disabled' : '' ?>><small><?= $trivia && $modoActual === 'privada' ? 'Deja vacío para conservar la actual.' : 'Solo se usa en modo privado.' ?></small></label>
                    </div>
                    <div class="pie-form-creador"><p>Puedes crear hasta cinco trivias por cuenta. Cada una tiene sus propias preguntas y salas.</p><?php if ($editable): ?><button class="btn-creador-principal" type="submit"><?= $trivia ? 'Guardar configuración' : 'Crear mi trivia' ?> <span>↗</span></button><?php endif; ?></div>
                </form>
            </section>
        </div>
        <div class="creador-seccion" id="preguntas">
            <div class="cabecera-seccion-creador"><div><span class="etiqueta">PASO 02</span><h2>Diseña las preguntas</h2></div><span><?= count($preguntas) ?> guardadas</span></div>
            <?php if (!$trivia): ?>
                <div class="panel-creador aviso-bloqueado"><span>✎</span><h3>Primero crea tu trivia</h3><p>Guarda la configuración del paso 1 para desbloquear el editor de preguntas.</p><a class="btn-creador-principal" href="#configuracion">Ir a configurar ↑</a></div>
            <?php elseif (!$editable): ?>
                <div class="panel-creador aviso-bloqueado"><span>✓</span><h3>Tu trivia está publicada</h3><p>Las preguntas están protegidas mientras la trivia esté publicada.</p></div>
            <?php else: ?>
                <form method="post" action="crear_accion.php" enctype="multipart/form-data" class="panel-creador form-creador form-pregunta" id="editor-pregunta">
                    <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                    <input type="hidden" name="accion" value="guardar_pregunta"><input type="hidden" name="id_trivia" value="<?= (int)$trivia['id_trivia'] ?>">
                    <input type="hidden" name="id_pregunta" value="<?= (int) ($preguntaEditar['id_pregunta'] ?? 0) ?>">
                    <div class="encabezado-editor"><div><span class="etiqueta"><?= $preguntaEditar ? 'MODO EDICIÓN' : 'NUEVA PREGUNTA' ?></span><h3><?= $preguntaEditar ? 'Editando pregunta' : 'Haz una buena pregunta' ?></h3></div><span class="chip-editor">4 OPCIONES</span></div>
                    <label class="campo-creador"><span>Enunciado de la pregunta <em>*</em></span><textarea name="enunciado" rows="3" maxlength="1600" required placeholder="Ej. ¿Cuál de estas piezas se mueve en forma de letra L?"><?= escapar($preguntaEditar['enunciado'] ?? '') ?></textarea></label>
                    <div class="fila-imagen-editor">
                        <label class="campo-creador"><span>Imagen de la pregunta <small>(opcional)</small></span><input type="file" name="imagen_pregunta" accept="image/jpeg,image/png,image/webp" data-preview="imagen-pregunta-preview"><small>JPG, PNG o WEBP · hasta 1.5 MB por imagen</small></label>
                        <div class="vista-previa-imagen" id="imagen-pregunta-preview"><?php if ($preguntaEditar && $preguntaEditar['imagen_url']): ?><img src="<?= escapar($preguntaEditar['imagen_url']) ?>" alt="Imagen actual de la pregunta"><?php else: ?><span>Sin imagen</span><?php endif; ?></div>
                    </div>
                    <?php if ($preguntaEditar && $preguntaEditar['imagen_url']): ?><label class="casilla-quitar"><input type="checkbox" name="quitar_imagen_pregunta" value="1"> Quitar imagen actual de la pregunta</label><?php endif; ?>
                    <div class="grupo-subtitulo"><strong>Opciones de respuesta</strong><small>Incluye texto o imagen en cada opción. Marca la correcta.</small></div>
                    <div class="opciones-editor">
                        <?php for ($i = 1; $i <= 4; $i++): $op = $preguntaEditar['opciones'][$i - 1] ?? null; ?>
                            <div class="opcion-editor">
                                <div class="opcion-cabecera"><span class="opcion-letra"><?= $letras[$i - 1] ?></span><strong>Opción <?= $letras[$i - 1] ?></strong><label class="selector-correcta"><input type="radio" name="respuesta_correcta" value="<?= $i ?>" <?= $op && (int) $op['es_correcta'] === 1 ? 'checked' : '' ?> required> Respuesta correcta</label></div>
                                <div class="fila-opcion-editor">
                                    <div class="campos-opcion"><input type="text" name="opcion_texto_<?= $i ?>" maxlength="500" placeholder="Escribe una respuesta (o utiliza una imagen)" aria-label="Texto opción <?= $letras[$i - 1] ?>" value="<?= escapar($op['texto'] ?? '') ?>"><label class="archivo-opcion">+ Seleccionar imagen <input type="file" name="opcion_imagen_<?= $i ?>" accept="image/jpeg,image/png,image/webp" data-preview="imagen-opcion-<?= $i ?>"></label></div>
                                    <div class="vista-previa-imagen mini" id="imagen-opcion-<?= $i ?>"><?php if ($op && $op['imagen_url']): ?><img src="<?= escapar($op['imagen_url']) ?>" alt="Imagen opción <?= $letras[$i - 1] ?>"><?php else: ?><span>Imagen</span><?php endif; ?></div>
                                </div>
                                <?php if ($op && $op['imagen_url']): ?><label class="casilla-quitar"><input type="checkbox" name="quitar_imagen_opcion_<?= $i ?>" value="1"> Quitar imagen actual</label><?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <div class="pie-form-creador"><p>Los cambios se guardan en MySQL; las imágenes se almacenan en tu hosting.</p><div class="botones-pregunta"><?php if ($preguntaEditar): ?><a class="btn-creador-secundario" href="crear.php?id=<?= (int)$trivia['id_trivia'] ?>#preguntas">Cancelar edición</a><?php endif; ?><button class="btn-creador-principal" type="submit"><?= $preguntaEditar ? 'Guardar cambios' : 'Guardar y siguiente pregunta' ?> <span>↗</span></button></div></div>
                </form>
            <?php endif; ?>
            <?php if ($preguntas): ?>
                <div class="lista-preguntas"><div class="cabecera-lista"><h3>Preguntas guardadas</h3><span><?= count($preguntas) ?> de 40</span></div>
                    <?php foreach ($preguntas as $numero => $pregunta): ?>
                        <article class="item-pregunta">
                            <div class="numero-pregunta"><?= str_pad((string) ($numero + 1), 2, '0', STR_PAD_LEFT) ?></div>
                            <?php if ($pregunta['imagen_url']): ?><img class="miniatura-pregunta" src="<?= escapar($pregunta['imagen_url']) ?>" alt="Imagen de la pregunta"><?php endif; ?>
                            <div class="cuerpo-pregunta"><strong><?= escapar($pregunta['enunciado']) ?></strong><span>4 opciones · <?= (int) $pregunta['puntos'] ?> puntos</span></div>
                            <?php if ($editable): ?><div class="acciones-pregunta"><a class="btn-editar-pregunta" href="crear.php?id=<?= (int)$trivia['id_trivia'] ?>&amp;editar=<?= (int) $pregunta['id_pregunta'] ?>#preguntas">Editar</a><form action="crear_accion.php" method="post" data-confirmar="¿Eliminar definitivamente esta pregunta?"><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="eliminar_pregunta"><input type="hidden" name="id_trivia" value="<?= (int)$trivia['id_trivia'] ?>"><input type="hidden" name="id_pregunta" value="<?= (int) $pregunta['id_pregunta'] ?>"><button type="submit" class="btn-eliminar-pregunta">Eliminar</button></form></div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($trivia): ?>
            <div class="final-creador" id="publicar"><div><span class="etiqueta">ÚLTIMO PASO</span><h2><?= $trivia['estado'] === 'publicada' ? '¡Tu trivia ya está publicada!' : '¿Listo para compartir tu trivia?' ?></h2><p><?= $trivia['estado'] === 'publicada' ? 'La comunidad podrá descubrirla. Después programaremos las salas y el juego en tiempo real.' : 'Puedes seguir editando mientras esté en borrador. Al publicarla se bloquean las preguntas para proteger las partidas.' ?></p></div>
                <?php if ($trivia['estado'] === 'borrador'): ?><form method="post" action="crear_accion.php" data-confirmar="¿Publicar tu trivia? Las preguntas quedarán bloqueadas mientras esté publicada."><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="publicar"><input type="hidden" name="id_trivia" value="<?= (int)$trivia['id_trivia'] ?>"><button class="btn-creador-principal" type="submit" <?= !$preguntas ? 'disabled' : '' ?>>Publicar trivia <span>↗</span></button></form>
                <?php elseif ($trivia['estado'] === 'publicada'): ?><form method="post" action="crear_accion.php" data-confirmar="¿Volver esta trivia a borrador? Solo será posible si todavía no tiene partidas ni salas."><input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="accion" value="volver_borrador"><input type="hidden" name="id_trivia" value="<?= (int)$trivia['id_trivia'] ?>"><button class="btn-creador-secundario" type="submit">Volver a borrador</button></form><?php endif; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>
        <footer class="pie-creador">TRILLAL © 2026 — Pregunta. Aprende. Compite.</footer>
    </main>
</div>
</body>
</html>
