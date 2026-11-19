<?php
/*
| Reportes › Pagos (2026-11-19, migración 088)
|
| Para cerrar el día: cuánto se recaudó, en qué forma, en qué horario, y cuánto
| efectivo tiene que haber en la caja. Cómo se cuenta cada peso está en
| arenaController::reportePagos().
|
| ORDEN DE LECTURA (pensado para el teléfono, de arriba abajo)
|
|   1. El periodo: hoy por defecto, con atajos de un toque.
|   2. Las cifras: recaudado, efectivo, bancos, monedero, número de cobros.
|   3. El cuadre de la caja: lo que debe haber y, si se escribe lo contado,
|      la diferencia (no se guarda: es una cuenta en pantalla).
|   4. Por forma de pago y por horario (las franjas de Arena › Horarios).
|   5. El detalle de cada cobro, con su recibo.
|
| Acceso: permiso «ver» sobre reportePagos (Core › Permisos). Imprimir y
| descargar el CSV piden además «exportar»: es la acción por la que la
| información sale del sistema (ds_core/database/045).
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Reporte de pagos';
$vistaActual = 'reportePagos';

$f     = $insArena->filtroReportePagos($_GET);
$datos = $insArena->reportePagos($f);
$r     = $datos['resumen'];
$movs  = $datos['movimientos'];

$puedeExportar = puede_exportar('reportePagos');
$unDia         = $f['desde'] === $f['hasta'];

/*----------  CSV: antes de pintar nada  ----------*/
if (isset($_GET['csv'])) {
    if (!$puedeExportar) {
        http_response_code(403);
        exit('Su rol no puede exportar este reporte.');
    }
    $nombre = 'pagos_' . $f['desde'] . ($unDia ? '' : '_' . $f['hasta']) . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Cache-Control: private, no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");                       /* BOM: Excel lee bien las tildes */
    /* Una celda que empieza por = + - @ la ejecuta Excel como fórmula. */
    $celda = static fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
    $num   = static fn ($v) => $v === null ? '' : number_format((float)$v, 2, ',', '');
    fputcsv($out, ['Fecha', 'Hora', 'Tipo', 'Recibo', 'Reserva', 'Cliente', 'Detalle', 'Forma', 'Referencia',
                   'Valor', 'Entra a caja', 'Recibido', 'Vuelto', 'Vuelto al monedero', 'Cajero'], ';');
    foreach ($movs as $m) {
        fputcsv($out, array_map($celda, [
            date('d/m/Y', $m['ts']), date('H:i', $m['ts']),
            ['pago' => 'Cobro', 'deposito' => 'Depósito al monedero', 'devolucion' => 'Devolución del monedero'][$m['tipo']],
            $m['recibo'], $m['reserva'], $m['cliente'], $m['detalle'], $m['forma'], $m['referencia'],
            $num($m['valor']), $num($m['entra']), $num($m['recibido']),
            $m['vuelto'] > 0 ? $num($m['vuelto']) : '', $m['vueltoAMonedero'] ? 'Sí' : '', $m['cajero'],
        ]), ';');
    }
    fclose($out);
    exit();
}

$cajeros = $insArena->cajerosPagos();
$sedes   = $insArena->sedesConInstalaciones();

$e   = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$din = static fn ($v) => '$' . number_format((float)$v, 2);

$meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
          'septiembre', 'octubre', 'noviembre', 'diciembre'];
$diasS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
$largo = static function (string $d) use ($meses, $diasS): string {
    $t = strtotime($d);
    return ucfirst($diasS[(int)date('w', $t)]) . ' ' . (int)date('j', $t) . ' de ' . $meses[(int)date('n', $t)] . ' de ' . date('Y', $t);
};
$periodo = $unDia ? $largo($f['desde'])
                  : 'Del ' . date('d/m/Y', strtotime($f['desde'])) . ' al ' . date('d/m/Y', strtotime($f['hasta']));

/* Los atajos conservan el cajero y la sede elegidos. */
$url = static function (array $cambios) use ($f): string {
    $q = array_filter(array_merge(
        ['desde' => $f['desde'], 'hasta' => $f['hasta'], 'cajero' => $f['cajero'] ?: null, 'sede' => $f['sede'] ?: null],
        $cambios), static fn ($v) => $v !== null && $v !== '');
    return APP_URL . 'reportePagos/?' . http_build_query($q);
};
$hoy = date('Y-m-d');
$atajos = [
    'Hoy'          => [$hoy, $hoy],
    'Ayer'         => [date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day'))],
    'Últimos 7 días' => [date('Y-m-d', strtotime('-6 days')), $hoy],
    'Este mes'     => [date('Y-m-01'), $hoy],
];

/* Ingresos por horario: las franjas de Horarios (horarioList), cada una
   con lo que pagaron las reservas que se jugaron en ella. Las vigentes
   salen aunque no hayan cobrado nada: una franja sin ingresos también es
   un dato (la cancha estuvo parada). */
$horas = $datos['horas'];
$maxHora = 0.0; $picoHora = null; $totalHorarios = 0.0;
foreach ($horas as $k => $v) {
    $totalHorarios += $v['total'];
    if ($v['total'] > $maxHora) { $maxHora = $v['total']; $picoHora = $k; }
}
$franjaTexto = static fn (array $v) => $v['desde'] . ' – ' . $v['hasta'];

$cajeroNombre = '';
foreach ($cajeros as $c) { if ((int)$c['usuario_id'] === $f['cajero']) { $cajeroNombre = $c['usuario_usuario']; } }
$sedeNombre = '';
foreach ($sedes as $s) { if ((int)$s['sede_id'] === $f['sede']) { $sedeNombre = $s['sede_nombre']; } }

$sinDatos = !$movs;

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="ar-rp">

<?php /* ===================== Periodo y filtros ===================== */ ?>
<form method="GET" action="<?php echo APP_URL; ?>reportePagos/" class="card ar-rp__filtros d-print-none" autocomplete="off">
    <div class="card-body">
        <div class="ar-rp__atajos" role="group" aria-label="Periodos frecuentes">
            <?php foreach ($atajos as $nombre => [$d, $h]):
                $activo = $f['desde'] === $d && $f['hasta'] === $h; ?>
                <a href="<?php echo $e($url(['desde' => $d, 'hasta' => $h])); ?>"
                   class="btn btn-sm <?php echo $activo ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                   <?php echo $activo ? 'aria-current="true"' : ''; ?>><?php echo $e($nombre); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="row g-2 align-items-end mt-1">
            <div class="col-6 col-md-3">
                <label for="rpDesde" class="form-label small mb-1">Desde</label>
                <input type="date" class="form-control" id="rpDesde" name="desde" value="<?php echo $e($f['desde']); ?>" max="<?php echo $hoy; ?>">
            </div>
            <div class="col-6 col-md-3">
                <label for="rpHasta" class="form-label small mb-1">Hasta</label>
                <input type="date" class="form-control" id="rpHasta" name="hasta" value="<?php echo $e($f['hasta']); ?>" max="<?php echo $hoy; ?>">
            </div>
            <div class="<?php echo count($sedes) > 1 ? 'col-6 col-md-2' : 'col-12 col-md-3'; ?>">
                <label for="rpCajero" class="form-label small mb-1">Cajero</label>
                <select class="form-select" id="rpCajero" name="cajero">
                    <option value="0">Todos</option>
                    <?php foreach ($cajeros as $c): ?>
                        <option value="<?php echo (int)$c['usuario_id']; ?>" <?php echo (int)$c['usuario_id'] === $f['cajero'] ? 'selected' : ''; ?>><?php echo $e($c['usuario_usuario']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if (count($sedes) > 1): ?>
            <div class="col-6 col-md-2">
                <label for="rpSede" class="form-label small mb-1">Sede</label>
                <select class="form-select" id="rpSede" name="sede">
                    <option value="0">Todas</option>
                    <?php foreach ($sedes as $s): ?>
                        <option value="<?php echo (int)$s['sede_id']; ?>" <?php echo (int)$s['sede_id'] === $f['sede'] ? 'selected' : ''; ?>><?php echo $e($s['sede_nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-12 col-md">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-magnifying-glass me-1" aria-hidden="true"></i>Consultar</button>
            </div>
        </div>
    </div>
</form>

<?php /* ===================== Encabezado del periodo ===================== */ ?>
<div class="ar-rp__cabecera">
    <div class="min-w-0">
        <h2 class="ar-rp__titulo"><?php echo $e($periodo); ?></h2>
        <p class="ar-rp__sub mb-0">
            <?php echo $r['nPagos']; ?> cobro<?php echo $r['nPagos'] === 1 ? '' : 's'; ?>
            <?php if ($r['nMovimientos']): ?>· <?php echo $r['nMovimientos']; ?> movimiento<?php echo $r['nMovimientos'] === 1 ? '' : 's'; ?> de monedero<?php endif; ?>
            <?php if ($cajeroNombre !== ''): ?>· cajero <strong><?php echo $e($cajeroNombre); ?></strong><?php endif; ?>
            <?php if ($sedeNombre !== ''): ?>· sede <strong><?php echo $e($sedeNombre); ?></strong><?php endif; ?>
            <span class="d-none d-print-inline">· impreso el <?php echo date('d/m/Y H:i'); ?></span>
        </p>
    </div>
    <?php if ($puedeExportar && !$sinDatos): ?>
        <div class="ar-rp__exportar d-print-none">
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir</button>
            <a class="btn btn-outline-success" href="<?php echo $e($url(['csv' => 1])); ?>"><i class="fas fa-file-csv me-1" aria-hidden="true"></i>CSV</a>
        </div>
    <?php endif; ?>
</div>

<?php if ($f['recortado']): ?>
    <div class="alert alert-warning py-2 small d-print-none" role="status">
        <i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>El periodo se acortó a <?php echo arenaController::REPORTE_MAX_DIAS; ?> días.
    </div>
<?php endif; ?>
<?php if ($f['sede'] > 0): ?>
    <div class="alert alert-info py-2 small" role="note">
        <i class="fas fa-circle-info me-1" aria-hidden="true"></i>Con una sede elegida no se incluyen los depósitos ni las devoluciones del monedero: no pertenecen a ninguna sede.
    </div>
<?php endif; ?>

<?php if ($sinDatos): ?>
    <div class="card"><div class="card-body ar-rp__vacio">
        <i class="fas fa-cash-register" aria-hidden="true"></i>
        <p class="mb-1 fw-semibold">No hubo cobros en este periodo.</p>
        <p class="mb-0 small text-body-secondary">Pruebe con otro día o quite el filtro de cajero<?php echo count($sedes) > 1 ? ' o de sede' : ''; ?>.</p>
    </div></div>
<?php else: ?>

<?php /* ===================== Cifras ===================== */ ?>
<div class="ar-rp__cifras">
    <div class="ar-rp__cifra ar-rp__cifra--principal">
        <span class="ar-rp__rotulo"><i class="fas fa-sack-dollar" aria-hidden="true"></i>Recaudado</span>
        <strong class="ar-rp__valor"><?php echo $din($r['recaudado']); ?></strong>
        <span class="ar-rp__nota">Dinero que entró: cobros y depósitos al monedero</span>
    </div>
    <div class="ar-rp__cifra">
        <span class="ar-rp__rotulo"><i class="fas fa-money-bill-wave" aria-hidden="true"></i>Efectivo</span>
        <strong class="ar-rp__valor"><?php echo $din($r['efectivo']); ?></strong>
        <span class="ar-rp__nota">Debe estar en la caja</span>
    </div>
    <div class="ar-rp__cifra">
        <span class="ar-rp__rotulo"><i class="fas fa-building-columns" aria-hidden="true"></i>Transferencia y tarjeta</span>
        <strong class="ar-rp__valor"><?php echo $din($r['bancos']); ?></strong>
        <span class="ar-rp__nota">Se verifica en el banco</span>
    </div>
    <div class="ar-rp__cifra ar-rp__cifra--info">
        <span class="ar-rp__rotulo"><i class="fas fa-wallet" aria-hidden="true"></i>Pagado con monedero</span>
        <strong class="ar-rp__valor"><?php echo $din($r['monederoAplicado']); ?></strong>
        <span class="ar-rp__nota">Saldo a favor ya cobrado antes: no suma</span>
    </div>
</div>

<div class="row g-3">
    <?php /* ===================== Cuadre de caja ===================== */ ?>
    <div class="col-lg-5">
        <div class="card h-100 ar-rp__cuadre" id="cuadre"
             data-esperado="<?php echo number_format($r['efectivo'], 2, '.', ''); ?>">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-scale-balanced me-2" aria-hidden="true"></i>Cuadre de caja · efectivo</h3>
            </div>
            <div class="card-body">
                <dl class="ar-rp__cuentas">
                    <div><dt>Cobros en efectivo</dt><dd><?php echo $din($r['cobrosEfectivo']); ?></dd></div>
                    <?php if ($r['vueltoMonederoEfectivo'] > 0): ?>
                        <div><dt>+ Vuelto que quedó en el monedero <small>se cobró y no se devolvió</small></dt><dd><?php echo $din($r['vueltoMonederoEfectivo']); ?></dd></div>
                    <?php endif; ?>
                    <?php if ($r['depositosEfectivo'] > 0): ?>
                        <div><dt>+ Depósitos en efectivo al monedero</dt><dd><?php echo $din($r['depositosEfectivo']); ?></dd></div>
                    <?php endif; ?>
                    <div class="ar-rp__cuentas-total"><dt>Efectivo cobrado</dt><dd><?php echo $din($r['efectivo']); ?></dd></div>
                </dl>

                <?php if ($r['devoluciones'] > 0): ?>
                    <p class="ar-rp__aviso">
                        <i class="fas fa-circle-exclamation me-1" aria-hidden="true"></i>
                        Hubo <strong><?php echo $din($r['devoluciones']); ?></strong> en devoluciones del monedero.
                        Si se entregaron en efectivo, réstelas al contar.
                    </p>
                <?php endif; ?>

                <?php /* La cuenta se hace en pantalla y no se guarda. */ ?>
                <div class="row g-2 d-print-none">
                    <div class="col-6">
                        <label for="rpFondo" class="form-label small mb-1">Fondo inicial</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="rpFondo" inputmode="decimal" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                    <div class="col-6">
                        <label for="rpContado" class="form-label small mb-1">Efectivo contado</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="rpContado" inputmode="decimal" step="0.01" min="0" placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="ar-rp__resultado mt-3" id="rpResultado" aria-live="polite">
                    <div class="ar-rp__debe">Debe haber en caja <strong id="rpDebe"><?php echo $din($r['efectivo']); ?></strong></div>
                    <div class="ar-rp__diferencia" id="rpDiferencia" hidden></div>
                </div>
            </div>
        </div>
    </div>

    <?php /* ===================== Por forma de pago ===================== */ ?>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="fas fa-layer-group me-2" aria-hidden="true"></i>Por forma de pago</h3>
            </div>
            <div class="card-body p-0">
                <ul class="ar-rp__formas">
                    <?php foreach ($datos['formas'] as $nombre => $fm):
                        $info = $fm['monedero'] || str_starts_with($nombre, 'Devolución'); ?>
                        <li class="<?php echo $info ? 'ar-rp__forma--info' : ''; ?>">
                            <span class="ar-rp__forma-nombre"><?php echo $e($nombre); ?>
                                <small><?php echo $fm['n']; ?> movimiento<?php echo $fm['n'] === 1 ? '' : 's'; ?><?php
                                    echo $fm['monedero'] ? ' · no suma: saldo a favor' : ''; ?><?php
                                    echo str_starts_with($nombre, 'Devolución') ? ' · salida, no se resta sola' : ''; ?></small></span>
                            <strong><?php echo str_starts_with($nombre, 'Devolución') ? '−' : ''; ?><?php echo $din($fm['total']); ?></strong>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($r['vueltoMonedero'] > 0): ?>
                        <li>
                            <span class="ar-rp__forma-nombre">Vuelto dejado en el monedero
                                <small>incluido en lo recaudado</small></span>
                            <strong><?php echo $din($r['vueltoMonedero']); ?></strong>
                        </li>
                    <?php endif; ?>
                    <li class="ar-rp__formas-total"><span>Recaudado</span><strong><?php echo $din($r['recaudado']); ?></strong></li>
                </ul>
            </div>
        </div>
    </div>

    <?php /* ===================== Por horario ===================== */ ?>
    <?php if ($horas): ?>
    <div class="col-lg-<?php echo count($datos['dias']) > 1 ? '7' : '12'; ?>">
        <div class="card h-100">
            <div class="card-header d-flex flex-wrap align-items-baseline gap-2">
                <h3 class="card-title mb-0"><i class="far fa-clock me-2" aria-hidden="true"></i>Ingresos por horario</h3>
                <?php if ($picoHora !== null): ?>
                    <small class="text-body-secondary ms-auto">Horario más fuerte: <strong class="text-nowrap"><?php echo $franjaTexto($horas[$picoHora]); ?></strong></small>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php /* Una fila por franja de Horarios, una barra en un solo tono.
                         El valor va al lado de la barra (debajo, en el teléfono):
                         la lista sirve también de tabla. */ ?>
                <ol class="ar-rp__horas" aria-label="Ingresos de cada horario<?php echo $unDia ? '' : ', sumando los días del periodo'; ?>">
                    <?php foreach ($horas as $k => $v):
                        $ancho = $maxHora > 0 ? max($v['total'] > 0 ? 2 : 0, round($v['total'] / $maxHora * 100, 1)) : 0;
                        $meta = array_filter([$v['dias'], implode(', ', $v['instalaciones'])]);
                        $cuantas = $v['reservas'] . ' reserva' . ($v['reservas'] === 1 ? '' : 's'); ?>
                        <li class="<?php echo $k === $picoHora ? 'ar-rp__hora--pico' : ''; ?><?php echo $v['n'] === 0 ? ' ar-rp__hora--vacia' : ''; ?>"
                            title="<?php echo $e($franjaTexto($v) . ' · ' . $din($v['total']) . ($v['n'] ? ' · ' . $cuantas . ' · ' . $v['n'] . ' cobro' . ($v['n'] === 1 ? '' : 's') : ' · sin ingresos')); ?>"
                            tabindex="0">
                            <span class="ar-rp__hora-etq">
                                <strong><?php echo $franjaTexto($v); ?></strong>
                                <?php if (!$v['definida']): ?><span class="badge text-bg-warning ar-rp__fuera">Fuera de horario</span><?php endif; ?>
                                <?php if ($meta): ?><small><?php echo $e(implode(' · ', $meta)); ?></small><?php endif; ?>
                            </span>
                            <span class="ar-rp__hora-pista"><span class="ar-rp__hora-barra" style="width:<?php echo $ancho; ?>%"></span></span>
                            <span class="ar-rp__hora-val">
                                <strong><?php echo $v['n'] ? $din($v['total']) : '—'; ?></strong>
                                <small><?php echo $v['n'] ? $cuantas : 'sin ingresos'; ?><?php
                                    echo $v['monedero'] > 0 ? ' · ' . $din($v['monedero']) . ' monedero' : ''; ?></small>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    <li class="ar-rp__horas-total">
                        <span class="ar-rp__hora-etq"><strong>Total en horarios</strong></span>
                        <span class="ar-rp__hora-pista" aria-hidden="true"></span>
                        <span class="ar-rp__hora-val"><strong><?php echo $din($totalHorarios); ?></strong></span>
                    </li>
                </ol>
                <p class="small text-body-secondary mb-0 mt-2">
                    Lo que pagaron las reservas según el horario (Arena › Horarios) en que se juegan, no la hora en que se cobró<?php echo $unDia ? '' : ', sumando todos los días del periodo'; ?>.
                    Incluye lo pagado con monedero.
                    <?php if ($r['depositos'] > 0): ?>Los depósitos al monedero (<?php echo $din($r['depositos']); ?>) no tienen horario y no se cuentan aquí.<?php endif; ?>
                    <?php if (array_filter($horas, static fn ($v) => !$v['definida'])): ?>«Fuera de horario»: reservas en una hora que no está (o ya no está) en Horarios.<?php endif; ?>
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ===================== Por día (periodos de varios días) ===================== */ ?>
    <?php if (count($datos['dias']) > 1): ?>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0"><i class="far fa-calendar me-2" aria-hidden="true"></i>Por día</h3>
            </div>
            <div class="card-body p-0">
                <ul class="ar-rp__formas">
                    <?php foreach ($datos['dias'] as $d => $v): ?>
                        <li>
                            <a class="ar-rp__forma-nombre" href="<?php echo $e($url(['desde' => $d, 'hasta' => $d])); ?>">
                                <?php echo $e(ucfirst($diasS[(int)date('w', strtotime($d))]) . ' ' . date('d/m', strtotime($d))); ?>
                                <small><?php echo $v['n']; ?> movimiento<?php echo $v['n'] === 1 ? '' : 's'; ?></small></a>
                            <strong><?php echo $din($v['total']); ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php /* ===================== Detalle ===================== */ ?>
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <h3 class="card-title mb-0"><i class="fas fa-list me-2" aria-hidden="true"></i>Detalle</h3>
                <small class="text-body-secondary ms-auto"><?php echo count($movs); ?> movimiento<?php echo count($movs) === 1 ? '' : 's'; ?>, en orden de hora</small>
            </div>
            <div class="card-body p-0">
                <?php /* Tabla en escritorio; una ficha por cobro por debajo de 992 px. */ ?>
                <table class="table mb-0 ar-rp__tabla">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Cliente y reserva</th>
                            <th>Forma</th>
                            <th>Cajero</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movs as $m):
                        $clase = $m['tipo'] === 'devolucion' ? 'ar-rp__mov--salida' : ($m['esMonedero'] ? 'ar-rp__mov--monedero' : ''); ?>
                        <tr class="<?php echo $clase; ?>">
                            <td class="ar-rp__c-hora">
                                <strong><?php echo date('H:i', $m['ts']); ?></strong>
                                <?php if (!$unDia): ?><small class="d-block text-body-secondary"><?php echo date('d/m/Y', $m['ts']); ?></small><?php endif; ?>
                            </td>
                            <td class="ar-rp__c-quien">
                                <strong><?php echo $e($m['cliente']); ?></strong>
                                <small class="d-block text-body-secondary">
                                    <?php if ($m['reserva'] !== ''): ?>
                                        <a href="<?php echo APP_URL; ?>reservaDetalle/?id=<?php echo $m['reservaId']; ?>"><?php echo $e($m['reserva']); ?></a> ·
                                    <?php endif; ?>
                                    <?php echo $e($m['detalle']); ?>
                                </small>
                            </td>
                            <td class="ar-rp__c-forma">
                                <?php echo $e($m['forma']); ?>
                                <?php if ($m['referencia'] !== ''): ?><small class="d-block text-body-secondary">Ref. <?php echo $e($m['referencia']); ?></small><?php endif; ?>
                                <?php if ($m['recibido'] !== null && $m['vuelto'] > 0): ?>
                                    <small class="d-block text-body-secondary">Recibió <?php echo $din($m['recibido']); ?> · vuelto <?php echo $din($m['vuelto']); ?><?php echo $m['vueltoAMonedero'] ? ' al monedero' : ' entregado'; ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="ar-rp__c-cajero"><i class="fas fa-user me-1 text-body-secondary d-lg-none" aria-hidden="true"></i><?php echo $e($m['cajero']); ?></td>
                            <td class="ar-rp__c-valor text-end">
                                <strong><?php echo $m['tipo'] === 'devolucion' ? '−' : ''; ?><?php echo $din($m['valor']); ?></strong>
                                <?php if ($m['esMonedero']): ?><small class="d-block text-body-secondary">con saldo a favor</small><?php endif; ?>
                                <?php if ($m['recibo'] !== ''): ?>
                                    <a class="ar-rp__recibo d-print-none" href="<?php echo APP_URL; ?>reciboPago/?id=<?php echo $m['id']; ?>"
                                       title="Ver el recibo <?php echo $e($m['recibo']); ?>"><i class="fas fa-receipt me-1" aria-hidden="true"></i><?php echo $e($m['recibo']); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
/* Cuadre en pantalla: fondo + efectivo cobrado frente a lo contado. No se
   guarda ni se envía. */
(function () {
    var cuadre = document.getElementById('cuadre');
    if (!cuadre) { return; }
    var esperado = parseFloat(cuadre.dataset.esperado) || 0;
    var fondo = document.getElementById('rpFondo'), contado = document.getElementById('rpContado');
    var debe = document.getElementById('rpDebe'), dif = document.getElementById('rpDiferencia');
    var dinero = function (v) { return '$' + Math.abs(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); };
    function calcular() {
        var total = Math.round((esperado + (parseFloat(fondo.value) || 0)) * 100) / 100;
        debe.textContent = dinero(total);
        if (contado.value === '') { dif.hidden = true; return; }
        var d = Math.round(((parseFloat(contado.value) || 0) - total) * 100) / 100;
        dif.hidden = false;
        dif.className = 'ar-rp__diferencia ' + (d === 0 ? 'is-ok' : (d > 0 ? 'is-sobra' : 'is-falta'));
        dif.innerHTML = d === 0
            ? '<i class="fas fa-circle-check me-1" aria-hidden="true"></i>La caja cuadra'
            : (d > 0 ? '<i class="fas fa-circle-up me-1" aria-hidden="true"></i>Sobrante de <strong>' + dinero(d) + '</strong>'
                     : '<i class="fas fa-circle-down me-1" aria-hidden="true"></i>Faltante de <strong>' + dinero(d) + '</strong>');
    }
    fondo.addEventListener('input', calcular);
    contado.addEventListener('input', calcular);
})();
</script>
<?php endif; ?>

</div>

<style>
.ar-rp { --rp-tono: #0e7490; --rp-pista: var(--bs-tertiary-bg); }
[data-bs-theme="dark"] .ar-rp { --rp-tono: #22d3ee; }

.ar-rp__atajos { display: flex; flex-wrap: wrap; gap: .4rem; }
.ar-rp__atajos .btn { border-radius: 2rem; }
.ar-rp__cabecera { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: .75rem; margin: .25rem 0 1rem; }
.ar-rp__titulo { font-size: 1.25rem; font-weight: 700; margin: 0; }
.ar-rp__sub { color: var(--bs-secondary-color); font-size: .9rem; }
.ar-rp__exportar { display: flex; gap: .5rem; }

/* ---- Cifras ---- */
.ar-rp__cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 12rem), 1fr)); gap: .75rem; margin-bottom: 1rem; }
.ar-rp__cifra {
    display: flex; flex-direction: column; gap: .2rem; padding: .85rem 1rem;
    border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg);
}
.ar-rp__cifra--principal { border-color: var(--rp-tono); box-shadow: inset 4px 0 0 var(--rp-tono); }
.ar-rp__cifra--info { background: var(--bs-tertiary-bg); }
.ar-rp__rotulo { display: flex; align-items: center; gap: .45rem; font-size: .8rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); }
.ar-rp__rotulo i { color: var(--rp-tono); }
.ar-rp__valor { font-size: 1.6rem; line-height: 1.15; font-variant-numeric: tabular-nums; color: var(--bs-emphasis-color); }
.ar-rp__cifra--principal .ar-rp__valor { font-size: 2rem; }
.ar-rp__nota { font-size: .8rem; color: var(--bs-secondary-color); }

/* ---- Cuadre ---- */
.ar-rp__cuentas { margin: 0 0 1rem; }
.ar-rp__cuentas > div { display: flex; justify-content: space-between; gap: 1rem; padding: .4rem 0; border-bottom: 1px dashed var(--bs-border-color); }
.ar-rp__cuentas dt { font-weight: 400; }
.ar-rp__cuentas dt small { display: block; color: var(--bs-secondary-color); }
.ar-rp__cuentas dd { margin: 0; font-variant-numeric: tabular-nums; white-space: nowrap; }
.ar-rp__cuentas-total { border-bottom: 0 !important; font-weight: 700; font-size: 1.05rem; }
.ar-rp__cuentas-total dt { font-weight: 700; }
.ar-rp__aviso { font-size: .85rem; padding: .5rem .65rem; border-radius: .4rem; background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
.ar-rp__debe { display: flex; justify-content: space-between; gap: .75rem; font-size: 1.05rem; }
.ar-rp__debe strong { font-variant-numeric: tabular-nums; }
.ar-rp__diferencia { margin-top: .5rem; padding: .55rem .75rem; border-radius: .4rem; font-weight: 600; }
.ar-rp__diferencia.is-ok    { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
.ar-rp__diferencia.is-sobra { background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
.ar-rp__diferencia.is-falta { background: var(--bs-danger-bg-subtle);  color: var(--bs-danger-text-emphasis); }

/* ---- Listas (por forma, por día) ---- */
.ar-rp__formas { list-style: none; margin: 0; padding: 0; }
.ar-rp__formas li { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .65rem 1rem; border-bottom: 1px solid var(--bs-border-color-translucent); }
.ar-rp__formas li strong { font-variant-numeric: tabular-nums; white-space: nowrap; }
.ar-rp__forma-nombre small { display: block; font-size: .8rem; color: var(--bs-secondary-color); }
.ar-rp__forma--info { color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }
.ar-rp__formas-total { border-bottom: 0 !important; font-weight: 700; font-size: 1.05rem; }

/* ---- Por horario: una serie, un tono; barra fina con el extremo redondeado.
        Escritorio: franja (con días y canchas) · barra · valor. ---- */
.ar-rp__horas { list-style: none; margin: 0; padding: 0; display: grid; gap: .15rem; }
.ar-rp__horas li {
    display: grid; grid-template-columns: minmax(9rem, 14rem) minmax(0, 1fr) 8.5rem; align-items: center; gap: .75rem;
    padding: .45rem .5rem; border-radius: .4rem;
}
.ar-rp__horas li:hover, .ar-rp__horas li:focus-visible { background: var(--bs-tertiary-bg); outline: none; }
.ar-rp__hora-etq { min-width: 0; }
.ar-rp__hora-etq strong { font-variant-numeric: tabular-nums; font-weight: 600; white-space: nowrap; }
.ar-rp__hora-etq small, .ar-rp__hora-val small { display: block; font-size: .78rem; color: var(--bs-secondary-color); line-height: 1.3; }
.ar-rp__fuera { font-size: .68rem; font-weight: 600; vertical-align: .1em; margin-left: .25rem; }
.ar-rp__hora-pista { height: .7rem; }
.ar-rp__hora-barra { display: block; height: 100%; background: var(--rp-tono); border-radius: 0 4px 4px 0; opacity: .75; }
.ar-rp__hora--pico .ar-rp__hora-barra { opacity: 1; }
.ar-rp__hora--pico .ar-rp__hora-etq strong { color: var(--bs-emphasis-color); font-weight: 700; }
.ar-rp__hora-val { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.ar-rp__hora-val strong { font-weight: 600; }
.ar-rp__hora--vacia .ar-rp__hora-etq strong, .ar-rp__hora--vacia .ar-rp__hora-val strong { color: var(--bs-secondary-color); font-weight: 400; }
.ar-rp__horas li.ar-rp__horas-total { border-top: 1px solid var(--bs-border-color); border-radius: 0; margin-top: .25rem; padding-top: .65rem; }
.ar-rp__horas li.ar-rp__horas-total:hover { background: none; }
.ar-rp__horas-total strong { font-weight: 700 !important; font-size: 1.05rem; }

/* ---- Detalle ---- */
.ar-rp__tabla td { vertical-align: top; }
.ar-rp__mov--monedero td { color: var(--bs-secondary-color); }
.ar-rp__mov--salida .ar-rp__c-valor strong { color: var(--bs-danger-text-emphasis); }
.ar-rp__c-valor strong { display: block; }
.ar-rp__recibo { display: inline-block; margin-top: .2rem; font-size: .8rem; white-space: nowrap; }
.ar-rp__vacio { text-align: center; padding: 2.5rem 1rem; }
.ar-rp__vacio > i { font-size: 2rem; color: var(--bs-secondary-color); margin-bottom: .75rem; }

@media (max-width: 991.98px) {
    .ar-rp__tabla, .ar-rp__tabla > tbody { display: block; }
    .ar-rp__tabla > thead { display: none; }
    .ar-rp__tabla > tbody { padding: .6rem; }
    .ar-rp__tabla > tbody > tr {
        display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: .2rem .75rem;
        padding: .75rem; margin-bottom: .6rem;
        border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg); background: var(--bs-body-bg);
    }
    .ar-rp__tabla > tbody > tr.ar-rp__mov--monedero { background: var(--bs-tertiary-bg); }
    .ds-core .ar-rp__tabla > tbody > tr > td { display: block; padding: 0 !important; border: 0; background: transparent; }
    .ar-rp__c-hora   { grid-column: 1; grid-row: 1 / span 2; min-width: 3.2rem; }
    .ar-rp__c-quien  { grid-column: 2; grid-row: 1; min-width: 0; overflow-wrap: anywhere; }
    .ar-rp__c-valor  { grid-column: 3; grid-row: 1 / span 2; }
    .ar-rp__c-forma  { grid-column: 2; grid-row: 2; font-size: .9rem; }
    .ar-rp__c-cajero { grid-column: 2; grid-row: 3; font-size: .8rem; color: var(--bs-secondary-color); }
    .ar-rp__recibo { padding: .35rem 0; }
}
@media (max-width: 575.98px) {
    .ar-rp__atajos .btn { flex: 1 1 auto; min-height: 2.5rem; display: inline-flex; align-items: center; justify-content: center; }
    .ar-rp__filtros .form-control, .ar-rp__filtros .form-select { min-height: 2.75rem; font-size: 1rem; }
    .ar-rp__filtros .btn-primary { min-height: 2.75rem; }
    .ar-rp__cifras { grid-template-columns: 1fr 1fr; gap: .5rem; }
    .ar-rp__cifra { padding: .7rem .75rem; }
    /* Principal arriba a lo ancho, dos en fila y la de monedero (informativa) a lo ancho al pie. */
    .ar-rp__cifra--principal, .ar-rp__cifra--info { grid-column: 1 / -1; }
    .ar-rp__valor { font-size: 1.3rem; }
    .ar-rp__cifra--principal .ar-rp__valor { font-size: 1.9rem; }
    .ar-rp__exportar { width: 100%; }
    .ar-rp__exportar .btn { flex: 1 1 0; min-height: 2.75rem; }
    .ar-rp__cuadre input { min-height: 2.75rem; font-size: 1rem; }
    /* Teléfono: cada franja es una ficha. Arriba la franja y el valor, la
       barra debajo a lo ancho; los días y canchas, y las reservas, al pie
       de cada lado. */
    .ar-rp__horas { gap: .5rem; }
    .ar-rp__horas li {
        grid-template-columns: minmax(0, 1fr) auto;
        grid-template-areas: "etq val" "pista pista";
        gap: .35rem .75rem; padding: .7rem .75rem;
        border: 1px solid var(--bs-border-color-translucent); border-radius: var(--bs-border-radius-lg);
    }
    .ar-rp__hora-etq { grid-area: etq; }
    .ar-rp__hora-val { grid-area: val; }
    .ar-rp__hora-pista { grid-area: pista; height: .55rem; }
    .ar-rp__hora-etq strong, .ar-rp__hora-val strong { font-size: 1rem; }
    .ar-rp__hora-etq small { white-space: normal; }
    .ar-rp__horas li.ar-rp__horas-total { grid-template-areas: "etq val"; border: 0; border-top: 1px solid var(--bs-border-color); border-radius: 0; }
    .ar-rp__horas-total .ar-rp__hora-pista { display: none; }
}

/* ---- Impresión: sólo el reporte ---- */
@media print {
    .app-sidebar, .app-header, .app-footer, .app-content-header .breadcrumb { display: none !important; }
    .app-wrapper, .app-main { margin: 0 !important; padding: 0 !important; }
    .ar-rp .card { break-inside: avoid; box-shadow: none !important; }
    .ar-rp__tabla > tbody > tr { break-inside: avoid; }
    .ar-rp__hora-barra { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .ar-rp a { color: inherit !important; text-decoration: none !important; }
}
</style>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
