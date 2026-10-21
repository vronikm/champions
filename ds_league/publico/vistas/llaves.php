<?php
/*
| Eliminatorias.
|
| Se muestra el marcador DE LA SERIE —partidos ganados— y no la suma de
| puntos: es lo que decide quien pasa, y confundirlos es el malentendido
| habitual de quien mira un cuadro de playoffs.
*/
$categoria = $datos['categoria'];
$llaves    = $datos['llaves'] ?? [];

$titulo      = 'Eliminatorias';
$subtitulo   = $categoria['categoria_nombre'];
$descripcion = 'Cuadro eliminatorio de ' . $categoria['categoria_nombre'] . '.';
$volver      = APP_URL . 'publico/c/' . (int)$categoria['categoria_id'] . '/';

$cid = (int)$categoria['categoria_id'];
$pestanas = [
    ['texto' => 'Resumen',       'url' => APP_URL . 'publico/c/' . $cid . '/'],
    ['texto' => 'Equipos',       'url' => APP_URL . 'publico/c/' . $cid . '/equipos/'],
    ['texto' => 'Líderes',       'url' => APP_URL . 'publico/c/' . $cid . '/lideres/'],
    ['texto' => 'Eliminatorias', 'url' => APP_URL . 'publico/c/' . $cid . '/llaves/', 'activa' => true],
];
require __DIR__ . '/_marco.php';

/* Agrupadas por fase: cuartos, semis, final. */
$porFase = [];
foreach ($llaves as $l) { $porFase[$l['fase_nombre']][] = $l; }
?>

<?php if (!$llaves): ?>
    <div class="caja"><div class="vacio">
        <p><strong>Todavía no hay eliminatorias.</strong></p>
        <p>El cuadro aparece cuando termina la fase de grupos.</p>
    </div></div>
<?php else: foreach ($porFase as $fase => $lista): ?>
    <div class="caja">
        <p class="caja__t"><?php echo $h($fase); ?></p>
        <?php foreach ($lista as $s):
            $cerrada = $s['serie_estado'] === 'CERRADA';
            $gl = (int)$s['serie_ganadas_local'];
            $gv = (int)$s['serie_ganadas_visitante'];
        ?>
            <div class="pt">
                <span class="pt__eq">
                    <?php if ($s['escudo_local']): ?>
                        <img class="pt__esc" alt=""
                             src="<?php echo APP_URL . 'assets/img/escudos/' . rawurlencode($s['escudo_local']); ?>">
                    <?php endif; ?>
                    <span class="pt__n<?php echo $cerrada && $gl > $gv ? ' pt__n--gana' : ''; ?>">
                        <?php echo $h($s['local'] ?? 'Por definir'); ?>
                    </span>
                </span>
                <span class="pt__m"><?php echo $gl; ?> – <?php echo $gv; ?></span>
                <span class="pt__eq pt__eq--v">
                    <span class="pt__n<?php echo $cerrada && $gv > $gl ? ' pt__n--gana' : ''; ?>">
                        <?php echo $h($s['visitante'] ?? 'Por definir'); ?>
                    </span>
                    <?php if ($s['escudo_visitante']): ?>
                        <img class="pt__esc" alt=""
                             src="<?php echo APP_URL . 'assets/img/escudos/' . rawurlencode($s['escudo_visitante']); ?>">
                    <?php endif; ?>
                </span>
            </div>
            <div class="pt__pie">
                <span>Al mejor de <?php echo (int)$s['serie_mejorde']; ?></span>
                <?php if ($cerrada): ?>
                    <span class="et et--ok">Pasa <?php echo $h($s['ganador']); ?></span>
                <?php else: ?>
                    <span class="et et--ne">En juego</span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; endif; ?>

<?php require __DIR__ . '/_pie.php'; ?>
