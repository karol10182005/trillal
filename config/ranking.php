<?php
declare(strict_types=1);
/** Ranking de Cultura: consultas de lectura, MySQL 5.7+/MariaDB sin vistas ni triggers. */
const RANKING_POR_PAGINA = 15;

function rankingConsulta(string $busqueda): string {
    // El signo ! es el carácter de escape del LIKE. Trata % y _ como texto literal.
    return '%' . strtr($busqueda, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
}

function rankingDatos(PDO $db, string $busqueda, int $paginaSolicitada, array $usuarioActual): array {
    $filtro = $busqueda !== '';
    $donde = "estado='activo'";
    if ($filtro) $donde .= " AND nombre_usuario LIKE ? ESCAPE '!'";
    $parametros = $filtro ? [rankingConsulta($busqueda)] : [];

    $st = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE ' . $donde);
    $st->execute($parametros);
    $cuenta = (int)$st->fetchColumn();
    $paginas = max(1, (int)ceil($cuenta / RANKING_POR_PAGINA));
    $pagina = max(1, min($paginaSolicitada, $paginas));
    $desde = ($pagina - 1) * RANKING_POR_PAGINA;

    $sql = 'SELECT u.id_usuario, u.nombre_usuario, u.avatar_url, u.cultura';
    if ($filtro) {
        // Calcula puesto GLOBAL, no el orden dentro de la búsqueda.
        $sql .= ', (SELECT COUNT(*)+1 FROM usuarios x WHERE x.estado=\'activo\'
                     AND (x.cultura>u.cultura OR
                         (x.cultura=u.cultura AND x.id_usuario<u.id_usuario))) AS puesto';
    }
    $sql .= " FROM usuarios u WHERE u.estado='activo'";
    if ($filtro) $sql .= " AND u.nombre_usuario LIKE ? ESCAPE '!'";
    $sql .= ' ORDER BY u.cultura DESC, u.id_usuario ASC LIMIT ? OFFSET ?';
    $st = $db->prepare($sql);
    $n = 1;
    if ($filtro) $st->bindValue($n++, rankingConsulta($busqueda), PDO::PARAM_STR);
    $st->bindValue($n++, RANKING_POR_PAGINA, PDO::PARAM_INT);
    $st->bindValue($n, $desde, PDO::PARAM_INT);
    $st->execute();
    $filas = $st->fetchAll();
    if (!$filtro) {
        foreach ($filas as $i => &$fila) $fila['puesto'] = $desde + $i + 1;
        unset($fila);
    }

    $top = $db->query("SELECT id_usuario, nombre_usuario, avatar_url, cultura FROM usuarios
          WHERE estado='activo' ORDER BY cultura DESC, id_usuario ASC LIMIT 3")->fetchAll();
    $medallas=[];
    if ($top) {
        $ids=array_map('intval',array_column($top, 'id_usuario'));
        $marcadores=implode(',',array_fill(0,count($ids),'?'));
        $st=$db->prepare("SELECT id_usuario, COUNT(*) AS premios,
             SUM(CASE WHEN puesto=1 THEN 1 ELSE 0 END) AS oros
             FROM movimientos_cultura WHERE id_usuario IN ($marcadores) GROUP BY id_usuario");
        $st->execute($ids);
        foreach ($st->fetchAll() as $r) $medallas[(int)$r['id_usuario']]=$r;
    }

    $st=$db->prepare("SELECT COUNT(*) + 1 FROM usuarios WHERE estado='activo'
      AND (cultura > ? OR (cultura = ? AND id_usuario < ?))");
    $st->execute([
        (int)$usuarioActual['cultura'], (int)$usuarioActual['cultura'], (int)$usuarioActual['id_usuario']
    ]);
    $miPuesto = (int)$st->fetchColumn();

    $resumen = $db->query("SELECT COUNT(*) AS usuarios, COALESCE(SUM(cultura),0) AS cultura_total,
              SUM(CASE WHEN cultura > 0 THEN 1 ELSE 0 END) AS jugadores_premiados
              FROM usuarios WHERE estado='activo'")->fetch() ?: [];
    return [
        'filas'=>$filas,'top'=>$top,'medallas'=>$medallas,'pagina'=>$pagina,
        'paginas'=>$paginas,'cuenta'=>$cuenta,'mi_puesto'=>$miPuesto,
        'resumen'=>$resumen,'por_pagina'=>RANKING_POR_PAGINA
    ];
}

function rankingUrl(string $busqueda, int $pagina, string $ancla=''): string {
    $query = ['pagina'=> $pagina];
    if ($busqueda !== '') $query['q'] = $busqueda;
    return 'ranking.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) . $ancla;
}
