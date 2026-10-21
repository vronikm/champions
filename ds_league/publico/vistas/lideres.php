<?php
$categoria = $datos['categoria'];
$rankings  = $datos['rankings'] ?? [];

$titulo      = 'Líderes';
$subtitulo   = $categoria['categoria_nombre'];
$descripcion = 'Máximos anotadores y líderes de ' . $categoria['categoria_nombre'] . '.';
$volver      = APP_URL . 'publico/c/' . (int)$categoria['categoria_id'] . '/';

$cid = (int)$categoria['categoria_id'];
$pestanas = [
    ['texto' => 'Resumen',       'url' => APP_URL . 'publico/c/' . $cid . '/'],
    ['texto' => 'Equipos',       'url' => APP_URL . 'publico/c/' . $cid . '/equipos/'],
    ['texto' => 'Líderes',       'url' => APP_URL . 'publico/c/' . $cid . '/lideres/', 'activa' => true],
    ['texto' => 'Eliminatorias', 'url' => APP_URL . 'publico/c/' . $cid . '/llaves/'],
];
require __DIR__ . '/_marco.php';
?>
<?php if (!$rankings): ?>
    <div class="caja"><div class="vacio">
        <p><strong>Todavía no hay estadísticas.</strong></p>
        <p>Los líderes aparecen a medida que se cargan las actas.</p>
    </div></div>
<?php else: foreach ($rankings as $titRank => $lista): ?>
    <div class="caja">
        <p class="caja__t"><?php echo $h($titRank); ?></p>
        <table>
            <thead>
                <tr>
                    <th class="pos">#</th>
                    <th>Jugador</th>
                    <th class="opc">PJ</th>
                    <th>Total</th>
                    <th>Prom.</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lista as $i => $l): ?>
                <tr>
                    <td class="pos"><?php echo $i + 1; ?></td>
                    <td>
                        <?php echo $h($l['persona_apellidos'] . ' ' . $l['persona_nombres']); ?><br>
                        <small style="color:var(--suave);"><?php echo $h($l['equipo_nombre']); ?></small>
                    </td>
                    <td class="opc"><?php echo (int)$l['partidos']; ?></td>
                    <td class="pts"><?php echo (int)round($l['total']); ?></td>
                    <td><?php echo $h(number_format((float)$l['promedio'], 1, ',', '')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endforeach; endif; ?>
<?php require __DIR__ . '/_pie.php'; ?>
