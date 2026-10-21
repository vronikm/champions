<?php
$torneo     = $datos['torneo'];
$categorias = $datos['categorias'] ?? [];
$campeones  = $datos['campeones']  ?? [];

$titulo      = $torneo['torneo_nombre'];
$subtitulo   = $torneo['temporada_nombre'];
$descripcion = 'Categorías, resultados y posiciones de ' . $torneo['torneo_nombre'] . '.';
$volver      = APP_URL . 'publico/';

require __DIR__ . '/_marco.php';

$generos = ['M' => 'Masculino', 'F' => 'Femenino', 'X' => 'Mixto'];
?>

<?php if ($campeones): ?>
<div class="caja">
    <p class="caja__t">Campeones</p>
    <?php foreach ($campeones as $c): ?>
        <div class="fila">
            <span style="font-size:1.4rem;flex:0 0 auto;">🏆</span>
            <span class="fila__n">
                <b><?php echo $h($c['campeon']); ?></b>
                <small><?php echo $h($c['categoria_nombre']); ?>
                       · <?php echo $h($c['fase_nombre']); ?></small>
            </span>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="caja">
    <p class="caja__t">Categorías</p>
    <?php if (!$categorias): ?>
        <div class="vacio">Este torneo todavía no tiene categorías publicadas.</div>
    <?php else: foreach ($categorias as $c): ?>
        <a href="<?php echo APP_URL; ?>publico/c/<?php echo (int)$c['categoria_id']; ?>/"
           class="fila" style="color:inherit;">
            <span class="fila__n">
                <b><?php echo $h($c['categoria_nombre']); ?></b>
                <small><?php echo $h($generos[$c['categoria_genero']] ?? ''); ?>
                    · <?php echo (int)$c['equipos']; ?>
                    equipo<?php echo (int)$c['equipos'] === 1 ? '' : 's'; ?></small>
            </span>
            <span style="color:var(--suave);">&#8250;</span>
        </a>
    <?php endforeach; endif; ?>
</div>

<?php require __DIR__ . '/_pie.php'; ?>
