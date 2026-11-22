<?php
/*
|--------------------------------------------------------------------------
| DigiCheck › Carnets — el carnet de cada alumno con su QR
|--------------------------------------------------------------------------
| Tres modos en una vista (con DS_PERMISOS_ESTRICTOS, una vista más sería
| otra fila de permisos que conceder):
|
|   carnets/                         el listado: filtros, selección e imprimir
|   carnets/?alumno=ID               el carnet de un alumno en grande, para
|                                    mostrarlo en el celular o guardarlo como
|                                    imagen
|   carnets/?imprimir=1&ids=1,2,3    la hoja para imprimir (A4, 10 por hoja,
|                                    tamaño tarjeta CR80 85,6 × 54 mm)
|
| El QR lleva digicheck_codigo() (ds_core/inc/digicheck.php): DSC1:<id>:<firma>.
| Se genera en este servidor (qr_svg), nunca en un servicio externo.
| Sólo alumnos ACTIVOS de las sedes del usuario.
*/

use check\controllers\checkController;

$insCheck = new checkController();
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* Un carnet (el mismo marcado en pantalla y en la hoja impresa). */
$carnet = static function (array $c) use ($h): string {
    $logo    = function_exists('ds_logo_url') ? ds_logo_url($c['sedeId']) : '';
    $escuela = function_exists('ds_nombre_organizacion') ? ds_nombre_organizacion($c['sedeId']) : DS_HUB_NAME;
    $qr      = qr_svg($c['codigo'], 4);
    $foto    = $c['foto'] !== ''
        ? '<img src="' . $h($c['foto']) . '" alt="" loading="lazy">'
        : '<span aria-hidden="true">' . $h($c['iniciales']) . '</span>';
    return '<article class="dcc" data-id="' . (int)$c['id'] . '">'
        . '<header class="dcc__cab">'
        .   ($logo !== '' ? '<img class="dcc__logo" src="' . $h($logo) . '" alt="">' : '')
        .   '<span class="dcc__escuela">' . $h($escuela) . '</span>'
        .   '<span class="dcc__marca"><i class="fas fa-qrcode" aria-hidden="true"></i> DigiCheck</span>'
        . '</header>'
        . '<div class="dcc__cuerpo">'
        .   '<div class="dcc__foto">' . $foto . '</div>'
        .   '<div class="dcc__datos">'
        .     '<strong class="dcc__nombres">' . $h($c['nombres']) . '</strong>'
        .     '<strong class="dcc__apellidos">' . $h($c['apellidos']) . '</strong>'
        .     ($c['cedula'] !== '' ? '<span>C.I. ' . $h($c['cedula']) . '</span>' : '')
        .     '<span>' . ($c['categoria'] !== '' ? 'Cat. ' . $h($c['categoria']) . ' · ' : '') . $h($c['sede']) . '</span>'
        .   '</div>'
        .   '<div class="dcc__qr" role="img" aria-label="Código QR de asistencia">' . ($qr !== '' ? $qr : '<small>' . $h($c['codigo']) . '</small>') . '</div>'
        . '</div>'
        . '<footer class="dcc__pie">Porque cada entrenamiento cuenta</footer>'
        . '</article>';
};

/*==============  Hoja para imprimir  ==============*/
if (isset($_GET['imprimir'])) {
    $ids = array_filter(array_map('intval', explode(',', (string)($_GET['ids'] ?? ''))));
    $carnets = $insCheck->carnetsDe($ids);
    ?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Carnets DigiCheck (<?php echo count($carnets); ?>)</title>
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_check/assets/css/carnet.css'); ?>">
</head>
<body class="dcc-hoja">
    <div class="dcc-hoja__barra">
        <strong><?php echo count($carnets); ?> carnets</strong>
        <span>Imprima en A4 al 100 % (sin «ajustar a la página»): cada carnet mide 85,6 × 54 mm.</span>
        <button type="button" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
    </div>
    <?php if (!$carnets): ?>
        <p class="dcc-hoja__vacio">No hay carnets para imprimir: los alumnos elegidos no están activos o no son de sus sedes.</p>
    <?php else: ?>
        <main class="dcc-hoja__grilla">
            <?php foreach ($carnets as $c) { echo $carnet($c); } ?>
        </main>
    <?php endif; ?>
</body>
</html>
<?php
    return;
}

/*==============  Un carnet en grande (celular)  ==============*/
$alumnoSolo = (int)($_GET['alumno'] ?? 0);
if ($alumnoSolo > 0) {
    $uno = $insCheck->carnetsDe([$alumnoSolo])[0] ?? null;
}

/*==============  El listado  ==============*/
$sedes    = $insCheck->sedesDelUsuario();
$horarios = $insCheck->horariosDelUsuario();
$fSede    = (int)($_GET['sede'] ?? 0);
$fHorario = (int)($_GET['horario'] ?? 0);
$fBuscar  = trim((string)($_GET['buscar'] ?? ''));
$alumnos  = $alumnoSolo > 0 ? [] : $insCheck->alumnosCarnets($fSede, $fHorario, $fBuscar);

$tituloVista   = 'Carnets';
$vistaActual   = 'carnets';
$cabeceraExtra = '<link rel="stylesheet" href="' . ds_recurso('ds_check/assets/css/carnet.css') . '">';
require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if ($alumnoSolo > 0): ?>
    <!-- ===== Un carnet ===== -->
    <div class="dcc-uno">
        <a href="<?php echo APP_URL; ?>carnets/" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Todos los carnets</a>
        <?php if (!$uno): ?>
            <div class="alert alert-warning">El alumno no está activo o no es de sus sedes.</div>
        <?php else: ?>
            <div class="dcc-uno__marco"><?php echo $carnet($uno); ?></div>
            <p class="dcc-uno__ayuda"><i class="fas fa-circle-info me-1" aria-hidden="true"></i>
                Se puede mostrar en la pantalla del celular: DigiCheck lo lee igual que impreso. Suba el brillo de la pantalla.</p>
            <div class="dcc-uno__acciones">
                <button type="button" class="btn btn-primary" id="dccImagen"><i class="fas fa-image me-1" aria-hidden="true"></i>Guardar imagen</button>
                <a class="btn btn-outline-secondary" target="_blank" rel="noopener"
                   href="<?php echo APP_URL; ?>carnets/?imprimir=1&amp;ids=<?php echo (int)$uno['id']; ?>"><i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir</a>
            </div>
        <?php endif; ?>
    </div>
    <script src="<?php echo ds_recurso('ds_check/assets/js/carnet.js'); ?>"></script>
<?php else: ?>
    <!-- ===== Filtros ===== -->
    <form method="get" class="card mb-3" autocomplete="off">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-4">
                    <label for="dccBuscar" class="form-label small text-body-secondary mb-1">Buscar</label>
                    <input type="search" class="form-control" id="dccBuscar" name="buscar" value="<?php echo $h($fBuscar); ?>" placeholder="Nombre, apellido o cédula">
                </div>
                <div class="col-6 col-md-3">
                    <label for="dccSede" class="form-label small text-body-secondary mb-1">Sede</label>
                    <select class="form-select" id="dccSede" name="sede">
                        <option value="0">Todas</option>
                        <?php foreach ($sedes as $s): ?>
                            <option value="<?php echo $s['id']; ?>"<?php echo $s['id'] === $fSede ? ' selected' : ''; ?>><?php echo $h($s['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label for="dccHorario" class="form-label small text-body-secondary mb-1">Horario</label>
                    <select class="form-select" id="dccHorario" name="horario">
                        <option value="0">Todos</option>
                        <?php foreach ($horarios as $hr): ?>
                            <option value="<?php echo $hr['id']; ?>"<?php echo $hr['id'] === $fHorario ? ' selected' : ''; ?>><?php echo $h($hr['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass me-1" aria-hidden="true"></i>Buscar</button>
                </div>
            </div>
        </div>
    </form>

    <!-- ===== Resultado ===== -->
    <form class="card" method="get" action="<?php echo APP_URL; ?>carnets/" target="_blank" id="dccForm">
        <input type="hidden" name="imprimir" value="1">
        <input type="hidden" name="ids" id="dccIds" value="">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h3 class="card-title me-auto mb-0"><i class="fas fa-id-card me-2" aria-hidden="true"></i>Alumnos activos
                <span class="badge text-bg-secondary ms-1"><?php echo count($alumnos); ?></span></h3>
            <label class="form-check mb-0 dcc-todos">
                <input type="checkbox" class="form-check-input" id="dccTodos"> <span class="form-check-label">Todos</span>
            </label>
            <button type="submit" class="btn btn-primary btn-sm" id="dccImprimir" disabled>
                <i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir <span id="dccCuenta">0</span>
            </button>
        </div>
        <div class="card-body">
            <?php if (!$alumnos): ?>
                <p class="text-body-secondary mb-0">Ningún alumno activo coincide con la búsqueda.</p>
            <?php else: ?>
                <p class="small text-body-secondary">Marque los que quiere imprimir (A4, 10 por hoja) o toque «Ver» para el carnet en grande.</p>
                <ul class="dcc-lista">
                    <?php foreach ($alumnos as $a): ?>
                        <li>
                            <label class="dcc-lista__sel">
                                <input type="checkbox" class="form-check-input" value="<?php echo $a['id']; ?>" aria-label="Elegir a <?php echo $h($a['nombres'] . ' ' . $a['apellidos']); ?>">
                                <span class="dcc-lista__ini" aria-hidden="true"><?php echo $h($a['iniciales']); ?></span>
                                <span class="dcc-lista__nombre"><?php echo $h($a['nombres'] . ' ' . $a['apellidos']); ?>
                                    <small><?php echo $h(implode(' · ', array_filter([$a['cedula'], $a['categoria'] !== '' ? 'Cat. ' . $a['categoria'] : '', $a['sede']]))); ?></small></span>
                            </label>
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo APP_URL; ?>carnets/?alumno=<?php echo $a['id']; ?>"><i class="fas fa-qrcode me-1" aria-hidden="true"></i>Ver</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </form>
    <script>
    /* Selección para imprimir: «Todos», el contador y los ids al enviar. */
    (function () {
        var f = document.getElementById('dccForm');
        if (!f) { return; }
        var cajas = [].slice.call(f.querySelectorAll('.dcc-lista input[type=checkbox]'));
        var todos = document.getElementById('dccTodos'), boton = document.getElementById('dccImprimir');
        function contar() {
            var n = cajas.filter(function (c) { return c.checked; }).length;
            document.getElementById('dccCuenta').textContent = n;
            boton.disabled = n === 0;
            todos.checked = n > 0 && n === cajas.length;
            todos.indeterminate = n > 0 && n < cajas.length;
        }
        cajas.forEach(function (c) { c.addEventListener('change', contar); });
        todos.addEventListener('change', function () { cajas.forEach(function (c) { c.checked = todos.checked; }); contar(); });
        f.addEventListener('submit', function (e) {
            var ids = cajas.filter(function (c) { return c.checked; }).map(function (c) { return c.value; });
            if (!ids.length) { e.preventDefault(); return; }
            if (ids.length > 300) { e.preventDefault(); alert('Se imprimen hasta 300 carnets por vez. Use los filtros para dividir.'); return; }
            document.getElementById('dccIds').value = ids.join(',');
        });
        contar();
    })();
    </script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
