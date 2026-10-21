<?php
/* Índice de competencias publicadas. */
$titulo      = 'Competencias';
$descripcion = 'Resultados, posiciones y calendario de las competencias en curso.';
require __DIR__ . '/_marco.php';

$torneos = $datos['torneos'] ?? [];
?>
<?php if (!$torneos): ?>
    <div class="caja"><div class="vacio">
        <p><strong>Todavía no hay nada publicado.</strong></p>
        <p>Las competencias aparecen aquí cuando la organización las publica.</p>
    </div></div>
<?php else: ?>
    <div class="caja">
        <p class="caja__t">En curso</p>
        <?php foreach ($torneos as $t): ?>
            <a href="<?php echo APP_URL; ?>publico/t/<?php echo $h($t['torneo_slug']); ?>/"
               class="fila" style="color:inherit;">
                <span class="fila__n">
                    <b><?php echo $h($t['torneo_nombre']); ?></b>
                    <small><?php echo $h($t['temporada_nombre']); ?>
                        · <?php echo (int)$t['categorias']; ?>
                        categoría<?php echo (int)$t['categorias'] === 1 ? '' : 's'; ?></small>
                </span>
                <span style="color:var(--suave);">&#8250;</span>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/_pie.php'; ?>
