<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Cartera
|--------------------------------------------------------------------------
| El tablero dice CUÁNTO se debe. Aquí se dice de qué, de quién y desde
| cuándo, que es lo único que permite hacer algo al respecto.
|
|
| NO LLEVA FILTRO DE PERIODO, Y ES A PROPÓSITO
|
| La deuda es un saldo vivo, no un flujo: no se debe «en agosto», se debe
| hoy. Poner un selector de fechas invitaría a preguntar «cuánto se debía en
| marzo», y la base no puede responder a eso —los saldos de marzo que ya se
| cobraron valen cero hoy—. Esa pregunta la contesta la evolución de más
| abajo, que se lee del snapshot mensual y no se recalcula.
|
|
| LA ANTIGÜEDAD DICE DOS COSAS DISTINTAS SEGÚN EL MÓDULO
|
| Sólo League tiene fecha de vencimiento real. En Arena se cuenta desde el
| día de la reserva y en Basketball desde la fecha del pago, porque no hay
| otra. La tabla lo rotula en vez de disimularlo: llamar «mora de 90 días» a
| lo que en realidad es «registrado hace 90 días» sería afirmar más de lo que
| el dato aguanta.
|
| Las reservas FUTURAS con saldo van a su propia columna. No son mora: son
| dinero que todavía no es exigible, y meterlas en el tramo más reciente
| abultaría la deuda joven con algo que nadie debe aún.
|
|
| LA LISTA DE DEUDORES TRAE DATOS PERSONALES
|
| Por eso muestra el nombre corto y nada más —ni cédula, ni nombre completo,
| ni teléfono—, se limita a alumnos activos y está acotada en número. Sirve
| para gestionar un cobro, no para tener un fichero de morosos.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$porCobrar  = $insInsights->porCobrar();
$porSede    = $insInsights->carteraPorSede();
$antiguedad = $insInsights->carteraAntiguedad();
$evolucion  = $insInsights->carteraEvolucion();
$retirados  = $insInsights->carteraRetirados();
$deudores   = $insInsights->carteraDeudores();

$tramos = [
    'porVencer' => 'Aún no vencida',
    'd30'       => 'Hasta 30 días',
    'd60'       => '31 a 60 días',
    'd90'       => '61 a 90 días',
    'mas'       => 'Más de 90 días',
];

/* Lo vencido a más de 90 días, que es la parte que de verdad preocupa. */
$masDe90 = 0.0;
$vencido = 0.0;
foreach ($antiguedad as $mod) {
    foreach ($tramos as $k => $_) {
        if ($k === 'porVencer') { continue; }
        $vencido += $mod[$k]['v'];
        if ($k === 'mas') { $masDe90 += $mod[$k]['v']; }
    }
}
$pctMas90 = $vencido > 0 ? $masDe90 / $vencido * 100 : null;

$deudoresActivos = count($deudores);

$tituloVista = 'Cartera';
$vistaActual = 'cartera';

require_once __DIR__ . "/inc/layout-top.php";
?>

<!-- ==================== Las cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-danger h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($porCobrar['total'], 2); ?></h3>
                <p class="mb-0">Por cobrar</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75">saldo vivo de los tres módulos</span></p>
            </div>
            <i class="fas fa-hand-holding-usd small-box-icon" aria-hidden="true"></i>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-warning h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($masDe90, 2); ?></h3>
                <p class="mb-0">Vencido a más de 90 días</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75">
                        <?php echo $pctMas90 === null
                            ? 'sin deuda vencida'
                            : number_format($pctMas90, 1) . ' % de lo vencido'; ?>
                    </span></p>
            </div>
            <i class="fas fa-hourglass-half small-box-icon" aria-hidden="true"></i>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $deudoresActivos; ?></h3>
                <p class="mb-0">Alumnos activos que deben</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75">con al menos una cuota pendiente</span></p>
            </div>
            <i class="fas fa-users small-box-icon" aria-hidden="true"></i>
        </div>
    </div>

    <!--
    | Los retirados van en su propia tarjeta y NO dentro del total: es la
    | decisión R12. Un alumno que ya no está y debe no se cobra igual que uno
    | activo, y sumarlos da una cifra que nadie puede gestionar.
    -->
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-secondary h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($retirados['valor'], 2); ?></h3>
                <p class="mb-0">Deuda de retirados</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo $retirados['alumnos']; ?> alumno(s) ya inactivo(s)</span></p>
            </div>
            <i class="fas fa-user-slash small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<!-- ==================== Antigüedad ==================== -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Antigüedad de la deuda</h3>
                <span class="text-muted small">importe · número de documentos</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Módulo</th>
                                <th scope="col">Se cuenta desde</th>
                                <?php foreach ($tramos as $nombre): ?>
                                    <th scope="col" class="text-end"><?php echo htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php endforeach; ?>
                                <th scope="col" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $desde = [
                                'basketball' => ['Fecha del pago', 'La tabla no guarda vencimiento: es una aproximación'],
                                'arena'      => ['Día de la reserva', 'Tampoco hay vencimiento propio'],
                                'league'     => ['Vencimiento', 'Es el único con fecha de vencimiento real'],
                            ];
                            $totalCol = array_fill_keys(array_keys($tramos), 0.0);
                            foreach (['basketball' => 'Fútbol', 'arena' => 'Arena', 'league' => 'League'] as $k => $etq):
                                $fila = $antiguedad[$k];
                                $tot = 0.0;
                                foreach ($tramos as $tk => $_) { $tot += $fila[$tk]['v']; $totalCol[$tk] += $fila[$tk]['v']; }
                            ?>
                                <tr>
                                    <th scope="row"><?php echo $etq; ?></th>
                                    <td class="small text-muted" title="<?php echo htmlspecialchars($desde[$k][1], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo $desde[$k][0]; ?>
                                        <?php if ($k !== 'league'): ?>
                                            <i class="fas fa-circle-info ms-1" aria-hidden="true"></i>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($tramos as $tk => $_): ?>
                                        <td class="text-end <?php echo $tk === 'mas' && $fila[$tk]['v'] > 0 ? 'text-danger fw-semibold' : ''; ?>">
                                            <?php if ($fila[$tk]['v'] > 0): ?>
                                                $<?php echo number_format($fila[$tk]['v'], 2); ?>
                                                <span class="text-muted small d-block"><?php echo $fila[$tk]['n']; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="text-end fw-semibold">$<?php echo number_format($tot, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="border-top">
                                <th scope="row" colspan="2">Total</th>
                                <?php foreach ($tramos as $tk => $_): ?>
                                    <td class="text-end fw-semibold">$<?php echo number_format($totalCol[$tk], 2); ?></td>
                                <?php endforeach; ?>
                                <td class="text-end fw-bold">$<?php echo number_format(array_sum($totalCol), 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <p class="text-muted small mb-0 mt-3">
                    «Aún no vencida» son reservas y obligaciones con fecha futura: dinero
                    comprometido que todavía no es exigible. No cuenta como mora.
                </p>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Por sede y evolución ==================== -->
<div class="row">
    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Deuda por sede</h3>
                <?php echo ds_botones_exportar('cartera', 'cartera', $insInsights->periodo()); ?>
            </div>
            <div class="card-body">
                <?php if (count($porSede) === 0): ?>
                    <p class="text-muted mb-0">Ninguna sede tiene saldos pendientes.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Sede</th>
                                    <th scope="col" class="text-end">Fútbol</th>
                                    <th scope="col" class="text-end">Arena</th>
                                    <th scope="col" class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($porSede as $s): ?>
                                    <tr>
                                        <th scope="row" class="fw-normal"><?php echo htmlspecialchars($s['nombre'], ENT_QUOTES, 'UTF-8'); ?></th>
                                        <td class="text-end">
                                            <?php echo $s['basketball'] > 0 ? '$' . number_format($s['basketball'], 2) : '<span class="text-muted">—</span>'; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo $s['arena'] > 0 ? '$' . number_format($s['arena'], 2) : '<span class="text-muted">—</span>'; ?>
                                        </td>
                                        <td class="text-end fw-semibold">$<?php echo number_format($s['total'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($porCobrar['league'] > 0): ?>
                        <p class="text-muted small mb-0 mt-3">
                            League suma $<?php echo number_format($porCobrar['league'], 2); ?> y no figura
                            en la tabla: sus torneos pueden organizarse fuera de las canchas del club,
                            así que no tiene sede a la que atribuirlo.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Evolución de la cartera</h3>
                <span class="text-muted small">fotografía mensual</span>
            </div>
            <div class="card-body">
                <?php if (count($evolucion) < 2): ?>
                    <!--
                    | Con una sola fotografía no hay evolución que enseñar. Se dice
                    | por qué y cuándo habrá más, en vez de pintar una línea de un
                    | punto que no significa nada.
                    -->
                    <p class="text-muted mb-0">
                        Todavía no hay suficientes fotografías mensuales para dibujar una
                        evolución. La cartera se retrata una vez al mes
                        (<code>cli/capturar_cartera.php</code>) porque la deuda pasada no se
                        puede reconstruir: los saldos que ya se cobraron valen cero hoy.
                        <?php if (count($evolucion) === 1): ?>
                            Hay <?php echo count($evolucion); ?> toma, de
                            <?php echo htmlspecialchars($evolucion[0]['periodo'], ENT_QUOTES, 'UTF-8'); ?>.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <div id="grafico-cartera" style="min-height:280px;"></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Quiénes deben ==================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Alumnos activos con saldo</h3>
        <span class="text-muted small">los <?php echo count($deudores); ?> de mayor importe</span>
    </div>
    <div class="card-body">
        <?php if (count($deudores) === 0): ?>
            <p class="text-muted mb-0">Ningún alumno activo tiene cuotas pendientes.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Alumno</th>
                            <th scope="col">Sede</th>
                            <th scope="col" class="text-end">Saldo</th>
                            <th scope="col" class="text-end">Cuotas</th>
                            <th scope="col" class="text-end">Más antiguo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deudores as $d): ?>
                            <tr>
                                <th scope="row" class="fw-normal">
                                    <?php echo htmlspecialchars((string) $d['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </th>
                                <td class="small text-muted"><?php echo htmlspecialchars((string) $d['sede'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="text-end">$<?php echo number_format((float) $d['saldo'], 2); ?></td>
                                <td class="text-end"><?php echo (int) $d['cuotas']; ?></td>
                                <td class="text-end">
                                    <?php $dias = (int) $d['dias']; ?>
                                    <span class="<?php echo $dias > 90 ? 'text-danger fw-semibold' : ''; ?>">
                                        <?php echo $dias; ?> día<?php echo $dias === 1 ? '' : 's'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mb-0 mt-3">
                Se listan sólo alumnos activos, con el nombre corto y sin más datos
                personales de los necesarios para gestionar el cobro. La columna
                «más antiguo» son los días transcurridos desde la cuota pendiente más
                vieja: ordena por importe, pero conviene mirar quién debe poco desde
                hace mucho.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php if (count($evolucion) >= 2): ?>
<script src="<?php echo ds_recurso('ds_insights/assets/js/graficos.js'); ?>"></script>
<script>
dsGrafico('grafico-cartera', {
    chart:  { type: 'line', height: 280, toolbar: { show: false } },
    stroke: { width: 2, curve: 'smooth' },
    series: [
        { name: 'Fútbol', data: <?php echo json_encode(array_map(static fn(array $e): float => round($e['basketball'], 2), $evolucion)); ?> },
        { name: 'Arena',      data: <?php echo json_encode(array_map(static fn(array $e): float => round($e['arena'], 2), $evolucion)); ?> },
        { name: 'League',     data: <?php echo json_encode(array_map(static fn(array $e): float => round($e['league'], 2), $evolucion)); ?> },
    ],
    xaxis:  { categories: <?php echo json_encode(array_column($evolucion, 'periodo')); ?> },
    yaxis:  { labels: { formatter: function (v) { return '$' + v.toLocaleString('es-EC'); } } },
    tooltip:{ y: { formatter: function (v) { return '$' + v.toLocaleString('es-EC', { minimumFractionDigits: 2 }); } } },
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
