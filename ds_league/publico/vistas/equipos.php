<?php
$categoria = $datos['categoria'];
$equipos   = $datos['equipos'] ?? [];

$titulo      = 'Equipos';
$subtitulo   = $categoria['categoria_nombre'];
$descripcion = 'Equipos de ' . $categoria['categoria_nombre'] . '.';
$volver      = APP_URL . 'publico/c/' . (int)$categoria['categoria_id'] . '/';

$cid = (int)$categoria['categoria_id'];
$pestanas = [
    ['texto' => 'Resumen',       'url' => APP_URL . 'publico/c/' . $cid . '/'],
    ['texto' => 'Equipos',       'url' => APP_URL . 'publico/c/' . $cid . '/equipos/', 'activa' => true],
    ['texto' => 'Líderes',       'url' => APP_URL . 'publico/c/' . $cid . '/lideres/'],
    ['texto' => 'Eliminatorias', 'url' => APP_URL . 'publico/c/' . $cid . '/llaves/'],
];
require __DIR__ . '/_marco.php';
?>
<div class="caja">
    <?php if (!$equipos): ?>
        <div class="vacio">Todavía no hay equipos habilitados.</div>
    <?php else: foreach ($equipos as $e): ?>
        <a href="<?php echo APP_URL; ?>publico/e/<?php echo (int)$e['inscripcion_id']; ?>/"
           class="fila" style="color:inherit;">
            <?php if ($e['equipo_escudo']): ?>
                <img class="foto" style="border-radius:.3rem;object-fit:contain;" alt=""
                     src="<?php echo APP_URL . 'assets/img/escudos/' . rawurlencode($e['equipo_escudo']); ?>">
            <?php else: ?>
                <span class="ini">🏀</span>
            <?php endif; ?>
            <span class="fila__n">
                <b><?php echo $h($e['equipo_nombre']); ?></b>
                <small><?php echo (int)$e['jugadores']; ?>
                    jugador<?php echo (int)$e['jugadores'] === 1 ? '' : 'es'; ?></small>
            </span>
            <span style="color:var(--suave);">&#8250;</span>
        </a>
    <?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/_pie.php'; ?>
