<?php
declare(strict_types=1);
require __DIR__ . '/config/bootstrap.php';
require __DIR__ . '/config/perfil.php';
$usuario = exigirSesion();

// Catálogo real (si aún no creaste trivias, mostramos las categorías vacías).
try {
    $categorias = conexion()->query(
        "SELECT c.id_categoria, c.nombre, COUNT(t.id_trivia) AS total
         FROM categorias c
         LEFT JOIN trivias t ON t.id_categoria = c.id_categoria AND t.estado = 'publicada'
         GROUP BY c.id_categoria, c.nombre
         ORDER BY c.nombre ASC"
    )->fetchAll();
} catch (Throwable $e) {
    error_log('[TRILLAL] No se pudieron cargar las categorías: ' . $e->getMessage());
    $categorias = [];
}

// Cantidad y última trivia creada. El menú Crear permite administrar hasta cinco.
$misTriviasInicio = [];
try {
    $qInicio = conexion()->prepare(
        'SELECT id_trivia, titulo, estado,
         (SELECT COUNT(*) FROM preguntas p WHERE p.id_trivia=t.id_trivia) AS total_preguntas
         FROM trivias t WHERE t.id_creador=? ORDER BY t.id_trivia DESC LIMIT 5'
    );
    $qInicio->execute([(int)$usuario['id_usuario']]);
    $misTriviasInicio = $qInicio->fetchAll();
} catch (Throwable $e) {
    error_log('[TRILLAL] No se pudieron cargar mis trivias: ' . $e->getMessage());
}

function iconoCategoria(string $nombre): string
{
    $n = strtolower($nombre);
    if (strpos($n, 'ajedrez') !== false) return '♟';
    if (strpos($n, 'video') !== false) return '✦';
    if (strpos($n, 'ciencia') !== false) return '⚙';
    return '¿';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#141414">
    <title>Trillal | Mi inicio</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
    <script src="assets/js/app.js" defer></script>
</head>
<body class="pagina-inicio">
    <div class="app-layout">
        <aside class="barra-lateral">
            <a href="inicio.php" class="marca-lateral" aria-label="Trillal - Inicio">
                <img src="assets/img/logo-trillal.png" width="92" height="87" alt="Trillal">
            </a>
            <nav class="navegacion-lateral" aria-label="Menú principal">
                <a class="enlace-menu activo" href="jugar.php"><span class="icono-menu">?!</span> Jugar</a>
                <a class="enlace-menu" href="crear.php"><span class="icono-menu">✎</span> Crear</a>
                <a class="enlace-menu" href="perfil.php"><span class="icono-menu">♛</span> Perfil</a>
                <a class="enlace-menu" href="ranking.php"><span class="icono-menu" aria-hidden="true">★</span> Ranking</a>
            </nav>
            <div class="usuario-lateral">
                <a class="avatar-letra" href="perfil.php" aria-label="Abrir mi perfil" style="overflow:hidden;text-decoration:none">
                    <?php if ($a = perfilAvatarSeguro($usuario['avatar_url'] ?? null)): ?>
                        <img src="<?= escapar($a) ?>" alt="" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?><?= escapar(perfilInicial($usuario['nombre_usuario'])) ?><?php endif; ?>
                </a>
                <div class="usuario-datos">
                    <a href="perfil.php" style="text-decoration:none"><strong><?= escapar($usuario['nombre_usuario']) ?></strong></a>
                    <span><?= (int) $usuario['cultura'] ?> Cultura</span>
                </div>
            </div>
            <form action="logout.php" method="post" class="form-cerrar">
                <input type="hidden" name="csrf_token" value="<?= escapar(tokenCsrf()) ?>">
                <button class="cerrar-sesion" type="submit">Cerrar sesión <span aria-hidden="true">↗</span></button>
            </form>
        </aside>

        <main class="contenido-inicio" id="explorar">
            <header class="cabecera-inicio">
                <div>
                    <span class="etiqueta">TU ESPACIO EN TRILLAL</span>
                    <h1>Hola, <span><?= escapar($usuario['nombre_usuario']) ?></span><span class="punto-acento">.</span></h1>
                    <p>La próxima pregunta puede cambiar tu posición.</p>
                </div>
                <div class="chip-cultura" aria-label="Puntos de Cultura"><span>♛</span><div><b><?= (int) $usuario['cultura'] ?></b><small>Cultura</small></div></div>
            </header>

            <div class="seccion-titulo"><div><span class="numero-panel">EXPLORA</span><h2>Trivias de la comunidad</h2></div><span class="indicador-estatus">Catálogo</span></div>

            <?php if ($categorias): ?>
                <div class="cuadricula-categorias">
                    <?php foreach ($categorias as $categoria): ?>
                    <a class="tarjeta-categoria" style="text-decoration:none;transition:transform .2s" href="jugar.php?categoria=<?= (int) $categoria['id_categoria'] ?>">
                        <div class="simbolo-categoria" aria-hidden="true"><?= escapar(iconoCategoria($categoria['nombre'])) ?></div>
                        <div class="pie-tarjeta">
                            <strong><?= escapar($categoria['nombre']) ?></strong>
                            <span><?= (int) $categoria['total'] ?> <?= (int) $categoria['total'] === 1 ? 'trivia' : 'trivias' ?></span>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="aviso-vacio">Todavía no hay categorías cargadas. Revisa que hayas importado las categorías de Trillal.</div>
            <?php endif; ?>

            <div class="mensaje-desarrollo"><span class="icono-mini">✧</span><div>
                <?php if ($misTriviasInicio): ?>
                    <strong>Mis trivias: <?= count($misTriviasInicio) ?> de 5</strong>
                    <p>Última: <?= escapar($misTriviasInicio[0]['titulo']) ?> · <?= escapar(ucfirst($misTriviasInicio[0]['estado'])) ?>. <a href="crear.php" style="color:#e4cfaf;font-weight:800">Administrar todas ↗</a></p>
                <?php else: ?>
                    <strong>¡Trillal ya tiene salas en vivo!</strong>
                    <p>Configura el tema, el temporizador y las preguntas desde <a href="crear.php" style="color:#e4cfaf;font-weight:800">Crear mi trivia ↗</a>. Puedes <a href="jugar.php" style="color:#e4cfaf;font-weight:800">explorar las partidas ↗</a>.</p>
                <?php endif; ?>
            </div></div>
        </main>
    </div>
</body>
</html>
<?php
// Alternativa sencilla a mb_substr, por si el hosting no tiene mbstring.
// (Función declarada globalmente por PHP antes de renderizar la plantilla.)
function mb_primera_letra(string $texto): string
{
    if (preg_match('/^./us', $texto, $partes)) {
        return strtoupper($partes[0]);
    }
    return '?';
}
