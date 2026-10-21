<?php
/*
| Categoría: posiciones, próximos partidos y últimos resultados.
|
| Es la pantalla que más se abre, así que va primero lo que más se busca:
| el resultado del fin de semana y la tabla. El calendario completo está a
| un toque, no delante.
*/

$categoria = $datos['categoria'];
$tablas    = $datos['tablas']   ?? [];
$proximos  = $datos['proximos'] ?? [];
$jugados   = $datos['jugados']  ?? [];

$titulo      = $categoria['categoria_nombre'];
$subtitulo   = $categoria['torneo_nombre'] . ' · ' . $categoria['temporada_nombre'];
$descripcion = 'Posiciones, resultados y calendario de '
             . $categoria['categoria_nombre'] . ' — ' . $categoria['torneo_nombre'] . '.';
$volver      = APP_URL . 'publico/t/' . $categoria['torneo_slug'] . '/';

$cid = (int)$categoria['categoria_id'];
$pestanas = [
    ['texto' => 'Resumen',       'url' => APP_URL . 'publico/c/' . $cid . '/',          'activa' => true],
    ['texto' => 'Equipos',       'url' => APP_URL . 'publico/c/' . $cid . '/equipos/'],
    ['texto' => 'Líderes',       'url' => APP_URL . 'publico/c/' . $cid . '/lideres/'],
    ['texto' => 'Eliminatorias', 'url' => APP_URL . 'publico/c/' . $cid . '/llaves/'],
];

require __DIR__ . '/_marco.php';

/* Pinta un partido. Se reutiliza en las dos listas porque son la misma
   cosa vista en dos momentos. */
$partido = static function (array $p, bool $conMarcador) use ($h) {
    $jugado = $p['estado_efectivo'] === 'S' && $p['partido_puntoslocal'] !== null;
    $pl = (int)$p['partido_puntoslocal'];
    $pv = (int)$p['partido_puntosvisitante'];
    ?>
    <div class="pt">
        <span class="pt__eq">
            <?php if ($p['escudo_local']): ?>
                <img class="pt__esc" alt=""
                     src="<?php echo APP_URL . 'assets/img/escudos/' . rawurlencode($p['escudo_local']); ?>">
            <?php endif; ?>
            <span class="pt__n<?php echo $jugado && $pl > $pv ? ' pt__n--gana' : ''; ?>">
                <?php echo $h($p['local']); ?>
            </span>
        </span>

        <?php if ($jugado): ?>
            <span class="pt__m"><?php echo $pl; ?> – <?php echo $pv; ?></span>
        <?php elseif ($p['partido_hora']): ?>
            <span class="pt__h"><?php echo $h(substr((string)$p['partido_hora'], 0, 5)); ?></span>
        <?php else: ?>
            <span class="pt__h">vs</span>
        <?php endif; ?>

        <span class="pt__eq pt__eq--v">
            <span class="pt__n<?php echo $jugado && $pv > $pl ? ' pt__n--gana' : ''; ?>">
                <?php echo $h($p['visitante']); ?>
            </span>
            <?php if ($p['escudo_visitante']): ?>
                <img class="pt__esc" alt=""
                     src="<?php echo APP_URL . 'assets/img/escudos/' . rawurlencode($p['escudo_visitante']); ?>">
            <?php endif; ?>
        </span>
    </div>
    <?php if ($p['partido_fecha'] || $p['cancha'] || $p['grupo_nombre']): ?>
    <div class="pt__pie">
        <?php if ($p['partido_fecha']): ?>
            <span><?php echo $h(date('d/m/Y', strtotime((string)$p['partido_fecha']))); ?></span>
        <?php endif; ?>
        <?php if ($p['cancha']): ?><span><?php echo $h($p['cancha']); ?></span><?php endif; ?>
        <?php if ($p['grupo_nombre']): ?><span><?php echo $h($p['grupo_nombre']); ?></span><?php endif; ?>
        <?php if ($p['estado_codigo'] === 'WALKOVER'): ?>
            <span class="et et--ma">Walkover</span>
        <?php elseif ($p['estado_codigo'] === 'SUSPENDIDO'): ?>
            <span class="et et--av">Suspendido</span>
        <?php endif; ?>
    </div>
    <?php endif;
};
?>

<!-- ==================== Próximos ==================== -->
<?php if ($proximos): ?>
<div class="caja">
    <p class="caja__t">Próximos partidos</p>
    <?php foreach (array_slice($proximos, 0, 8) as $p) { $partido($p, false); } ?>
</div>
<?php endif; ?>

<!-- ==================== Posiciones ==================== -->
<?php foreach ($tablas as $nombreGrupo => $tabla): ?>
    <?php if (!$tabla) { continue; } ?>
    <div class="caja">
        <p class="caja__t">
            Posiciones<?php echo $nombreGrupo !== '' ? ' · ' . $h($nombreGrupo) : ''; ?>
        </p>
        <table>
            <thead>
                <tr>
                    <th class="pos">#</th>
                    <th>Equipo</th>
                    <th>PJ</th>
                    <th class="opc">PG</th>
                    <th class="opc">PP</th>
                    <th class="opc">PF</th>
                    <th class="opc">PC</th>
                    <th>DIF</th>
                    <th>PTS</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($tabla as $f): ?>
                <tr>
                    <td class="pos"><?php echo (int)$f['posicion']; ?></td>
                    <td><?php echo $h($f['equipo']); ?></td>
                    <td><?php echo (int)$f['pj']; ?></td>
                    <td class="opc"><?php echo (int)$f['pg']; ?></td>
                    <td class="opc"><?php echo (int)$f['pp']; ?></td>
                    <td class="opc"><?php echo (int)$f['pf']; ?></td>
                    <td class="opc"><?php echo (int)$f['pc']; ?></td>
                    <td><?php echo ($f['dif'] > 0 ? '+' : '') . (int)$f['dif']; ?></td>
                    <td class="pts"><?php echo (int)$f['pts']; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; ?>

<!-- ==================== Últimos resultados ==================== -->
<?php if ($jugados): ?>
<div class="caja">
    <p class="caja__t">Últimos resultados</p>
    <?php foreach (array_slice($jugados, 0, 10) as $p) { $partido($p, true); } ?>
</div>
<?php endif; ?>

<?php if (!$proximos && !$jugados): ?>
<div class="caja"><div class="vacio">
    <p><strong>La competencia todavía no ha empezado.</strong></p>
    <p>Aquí aparecerán el calendario y los resultados.</p>
</div></div>
<?php endif; ?>

<?php require __DIR__ . '/_pie.php'; ?>
