<?php
$titulo = 'No encontrado';
$descripcion = 'La página que buscas no existe o no está publicada.';
$volver = APP_URL . 'publico/';
require __DIR__ . '/_marco.php';
?>
<div class="caja">
    <div class="vacio">
        <p style="font-size:2.5rem;margin:0 0 .5rem;">🏀</p>
        <p><strong>Aquí no hay nada.</strong></p>
        <p>El enlace puede estar equivocado, o esta competencia todavía no
           se ha publicado.</p>
        <p><a href="<?php echo APP_URL; ?>publico/">Ver las competencias publicadas</a></p>
    </div>
</div>
<?php require __DIR__ . '/_pie.php'; ?>
