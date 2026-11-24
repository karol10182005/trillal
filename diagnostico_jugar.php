<?php
declare(strict_types=1);
// TEMPORAL: herramienta autenticada; bórrala después de diagnosticar.
require __DIR__ . '/config/bootstrap.php';
$usuario = exigirSesion();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { http_response_code(405); exit; }
$pruebas = [];
function prueba(string $nombre, bool $ok, string $detalle): void {
    global $pruebas;
    $pruebas[] = [$nombre, $ok, $detalle];
}
try {
    $db = conexion();
    $db->query('SELECT 1')->fetchColumn();
    prueba('Conexión MySQL', true, 'La conexión responde correctamente.');
    foreach (['usuarios','trivias','preguntas','opciones','salas','inscripciones_sala','intentos','respuestas'] as $tabla) {
        try { $db->query('SELECT 1 FROM `' . $tabla . '` LIMIT 1')->fetch(); prueba('Tabla '.$tabla, true, 'Disponible.'); }
        catch (Throwable $e) { prueba('Tabla '.$tabla, false, 'Falta la tabla o el usuario MySQL no puede leerla.'); }
    }
    $columnas = [
        'salas'=>['tipo_partida','fecha_competencia','inicio_programado_en','duracion_segundos','cupo_maximo'],
        'intentos'=>['id_sala','id_trivia','id_usuario','numero_intento','estado','puntuacion_total'],
        'inscripciones_sala'=>['id_sala','id_usuario','estado']
    ];
    foreach ($columnas as $tabla=>$nombres) {
        try {
            $q = $db->query('SHOW COLUMNS FROM `'.$tabla.'`');
            $actual = array_column($q->fetchAll(PDO::FETCH_ASSOC),'Field');
            foreach ($nombres as $nombre) prueba($tabla.'.'.$nombre, in_array($nombre,$actual,true), in_array($nombre,$actual,true)?'Disponible.':'Falta columna. Importa la actualización SQL adecuada.');
        } catch (Throwable $e) { prueba('Columnas de '.$tabla, false, 'No se pudieron consultar las columnas.'); }
    }
    try {
        $q = $db->query("SHOW INDEX FROM intentos WHERE Key_name = 'uq_intentos_numero'");
        $existe = (bool)$q->fetch(PDO::FETCH_ASSOC);
        prueba('Índice antiguo de intentos', !$existe, $existe ? 'Detectado uq_intentos_numero. La corrección asigna números de intento consecutivos para evitar duplicados. Idealmente completa la migración original tras un respaldo.' : 'No hay restricción antigua por número de intento.');
    } catch (Throwable $e) { prueba('Índices de intentos',false,'No pudieron examinarse los índices.'); }
    $q=$db->prepare("SELECT COUNT(*) FROM trivias WHERE id_creador=? AND estado='publicada'");
    $q->execute([(int)$usuario['id_usuario']]);
    prueba('Trivias publicadas de tu cuenta', true, 'Encontradas: '.(int)$q->fetchColumn().'.');
} catch (Throwable $e) {
    prueba('Conexión MySQL', false, 'No se pudo establecer conexión; comprueba config/db.php.');
    error_log('[TRILLAL DIAGNOSTICO] '.$e->getMessage());
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico Jugar · Trillal</title>
<style>body{font:15px system-ui,Arial;background:#171513;color:#f9efdf;margin:0;padding:35px 14px}main{max-width:780px;margin:auto}h1{color:#f5d29e}p{color:#bdb2a4;line-height:1.65}.fila{border:1px solid #514737;border-radius:11px;background:#27231f;margin:7px 0;padding:13px;display:flex;gap:11px;align-items:start}.fila.ok{border-color:#476b50}.fila.fail{border-color:#9b4f52}strong{display:block}.texto{color:#bfb5a8;font-size:12px;margin-top:4px}a{color:#f5d29e}</style></head><body><main><h1>Trillal · diagnóstico de Jugar</h1><p>Esta página comprueba tablas, columnas e índices. No inicia partidas, no modifica datos ni muestra contraseñas. Cuando termines, elimina <code>diagnostico_jugar.php</code> de tu servidor.</p>
<?php foreach($pruebas as $p): ?><section class="fila <?= $p[1]?'ok':'fail' ?>"><span><?= $p[1]?'✓':'✕' ?></span><div><strong><?= escapar($p[0]) ?></strong><div class="texto"><?= escapar($p[2]) ?></div></div></section><?php endforeach; ?>
<p><a href="jugar.php">← Volver a Jugar</a></p></main></body></html>
