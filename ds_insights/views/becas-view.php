<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Becas y descuentos
|--------------------------------------------------------------------------
| Cuánto cuesta el beneficio, a cuántos alcanza, cuánto pagaron aun así y
| cómo asisten. Cuatro preguntas que hasta ahora había que responder a mano.
|
|
| LO QUE ESTA PANTALLA DICE Y NO ESTABA A LA VISTA
|
| La Beca 50 % guarda su importe bien: $15,00 sobre una pensión de $30,00, la
| mitad exacta. La Beca 100 % se guarda con importe 0,00, así que el subsidio
| que la escuela concede no aparecía en ninguna suma. Aquí se calcula desde
| la pensión de la sede y se rotula como DEDUCIDO, separado de lo registrado:
| una cosa es un hecho escrito en la fila y otra una consecuencia inferida.
|
|
| SOBRE LA ASISTENCIA, UNA CAUTELA
|
| Los grupos son de tamaño muy distinto —150 alumnos sin beneficio frente a 7
| con beca completa—. Con siete alumnos, unas pocas faltas mueven el
| porcentaje varios puntos. La pantalla muestra el número de marcas de cada
| grupo justamente para que no se lea una diferencia como si fuera un
| hallazgo cuando puede ser ruido.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$beneficios = $insInsights->beneficios();
$control    = $insInsights->sinBeneficio();
$anomalias  = $insInsights->anomaliasBeneficio();
$becaPagada = $anomalias['becaPagada'];
$sinActivo  = $anomalias['sinActivo'];

/* «Conviene revisar»: quién es cada alumno, no su número. */
$rvH        = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rvFecha    = static fn($f) => ($f && !str_starts_with((string) $f, '0000')) ? date('d/m/Y', strtotime((string) $f)) : '—';
$rvIniciales  = static function (string $n): string {
    $p = preg_split('/\s+/', trim($n)) ?: [];
    $i = mb_substr($p[0] ?? '', 0, 1) . mb_substr($p[count($p) > 2 ? 2 : 1] ?? '', 0, 1);   /* nombre + primer apellido */
    return $i !== '' ? mb_strtoupper($i) : '?';
};
$rvEstado = static function (string $e) use ($rvH): string {
    [$texto, $clase] = [
        'A' => ['Activo',    'bg-success-subtle text-success-emphasis border border-success-subtle'],
        'I' => ['Inactivo',  'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle'],
        'E' => ['Eliminado', 'bg-danger-subtle text-danger-emphasis border border-danger-subtle'],
    ][$e] ?? [$e, 'text-bg-secondary'];
    return '<span class="badge ' . $clase . '">' . $rvH($texto) . '</span>';
};
/* Por tipo de beneficio y estado: «Descuento · inactivos 71». */
$resumenSinActivo = [];
foreach ($sinActivo as $al) {
    $clave = $al['tipo'] . ' · ' . ($al['estado'] === 'E' ? 'eliminados' : 'inactivos');
    $resumenSinActivo[$clave] = ($resumenSinActivo[$clave] ?? 0) + 1;
}
arsort($resumenSinActivo);
$totalRevisar = count($becaPagada) + count($sinActivo);

$totAlumnos  = array_sum(array_column($beneficios, 'alumnos'));
$totActivos  = array_sum(array_column($beneficios, 'activos'));
$totMensual  = array_sum(array_column($beneficios, 'mensual'));
$totDeducido = array_sum(array_column($beneficios, 'deducido'));
$totPagado   = array_sum(array_column($beneficios, 'pagado'));
$totCuotas   = array_sum(array_column($beneficios, 'cuotas'));

$tituloVista = 'Becas y descuentos';
$vistaActual = 'becas';

require_once __DIR__ . "/inc/layout-top.php";
?>

<style>
    /* «Conviene revisar»: una ficha por alumno, pensada primero para el
       teléfono. Colores de Bootstrap (claro y oscuro). */
    .ds-revisar__grupo + .ds-revisar__grupo { margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--bs-border-color); }
    .ds-revisar__titulo { display: flex; align-items: center; gap: .5rem; font-size: 1rem; font-weight: 600; margin: 0 0 .25rem; }
    .ds-revisar__ayuda { font-size: .875rem; color: var(--bs-secondary-color); margin: 0 0 .75rem; }
    .ds-revisar__lista, .ds-revisar__pagos, .ds-revisar__filas { list-style: none; margin: 0; padding: 0; }
    .ds-revisar__alumno { border: 1px solid var(--bs-border-color); border-radius: .5rem; padding: .75rem; }
    .ds-revisar__alumno + .ds-revisar__alumno { margin-top: .75rem; }
    .ds-revisar__cab { display: flex; align-items: flex-start; gap: .75rem; }
    .ds-revisar__avatar { flex: 0 0 2.5rem; height: 2.5rem; border-radius: 50%; display: grid; place-items: center; font-weight: 600; font-size: .875rem;
                          background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis); }
    .ds-revisar__quien { min-width: 0; flex: 1 1 auto; }
    .ds-revisar__nombre { font-weight: 600; line-height: 1.3; overflow-wrap: anywhere; }
    .ds-revisar__meta { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem .75rem; font-size: .8125rem; color: var(--bs-secondary-color); margin-top: .125rem; }
    .ds-revisar__monto { flex: 0 0 auto; text-align: right; line-height: 1.2; }
    .ds-revisar__monto strong { display: block; font-size: 1.125rem; font-variant-numeric: tabular-nums; }
    .ds-revisar__monto span { font-size: .75rem; color: var(--bs-secondary-color); }
    .ds-revisar__nota { font-size: .8125rem; color: var(--bs-secondary-color); margin: .625rem 0 .25rem; }
    .ds-revisar__nota i { color: var(--bs-warning-text-emphasis); }
    .ds-revisar__pagos li { display: grid; grid-template-columns: 6.25rem 1fr auto; gap: .125rem .75rem; align-items: baseline;
                            padding: .375rem 0; border-top: 1px dashed var(--bs-border-color); font-size: .875rem; }
    .ds-revisar__pago-aviso { grid-column: 1 / -1; font-size: .75rem; color: var(--bs-warning-text-emphasis); }
    .ds-revisar__pago-mes { color: var(--bs-secondary-color); }
    .ds-revisar__pago-valor { font-weight: 600; font-variant-numeric: tabular-nums; }
    .ds-revisar__acciones { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .625rem; }
    .ds-revisar__resumen { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem; }
    .ds-revisar__chip { display: inline-flex; align-items: center; gap: .375rem; padding: .25rem .625rem; border-radius: 999px;
                        background: var(--bs-tertiary-bg); font-size: .8125rem; }
    .ds-revisar__detalle summary { cursor: pointer; font-weight: 500; padding: .5rem 0; color: var(--bs-link-color); }
    .ds-revisar__filas { max-height: 26rem; overflow-y: auto; }
    .ds-revisar__filas li { display: flex; align-items: center; gap: .75rem; padding: .5rem 0; border-top: 1px solid var(--bs-border-color); }
    .ds-revisar__filas .btn { flex: 0 0 auto; }
    /* El gris de btn-outline-secondary no llega al contraste en modo oscuro. */
    .ds-revisar__acciones .btn, .ds-revisar__filas .btn {
        --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color);
        --bs-btn-hover-color: var(--bs-body-color); --bs-btn-hover-bg: var(--bs-tertiary-bg); --bs-btn-hover-border-color: var(--bs-border-color);
    }

    @media (max-width: 575.98px) {
        /* Teléfono: el mes debajo de la fecha y botones anchos, fáciles de tocar. */
        .ds-revisar__pagos li { grid-template-columns: 1fr auto; }
        .ds-revisar__pago-mes { grid-column: 1; grid-row: 2; }
        .ds-revisar__pago-valor { grid-column: 2; grid-row: 1 / span 2; align-self: center; }
        .ds-revisar__pago-aviso { grid-column: 1 / -1; grid-row: 3; }
        .ds-revisar__acciones .btn { flex: 1 1 0; min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; }
        .ds-revisar__filas .btn { min-height: 2.75rem; display: inline-flex; align-items: center; }
    }
</style>

<!-- ==================== Las cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($totAlumnos); ?></h3>
                <p class="mb-0">Alumnos con beneficio</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($totActivos); ?> activos</span></p>
            </div>
            <i class="fas fa-user-graduate small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-danger h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($totMensual, 2); ?></h3>
                <p class="mb-0">Valor mensual</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75">que la escuela deja de cobrar</span></p>
            </div>
            <i class="fas fa-tags small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-warning h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($totDeducido, 2); ?></h3>
                <p class="mb-0">De eso, no registrado</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"
                          title="Las becas del 100 % se guardan con importe 0,00. Esta cifra se deduce de la pensión de la sede de cada alumno.">
                        <i class="fas fa-info-circle"></i> deducido, no escrito
                    </span></p>
            </div>
            <i class="fas fa-eye-slash small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-success h-100">
            <div class="inner">
                <h3 class="text-truncate">$<?php echo number_format($totPagado, 2); ?></h3>
                <p class="mb-0">Pagado por los beneficiarios</p>
                <p class="mb-0 small opacity-75"><span class="opacity-75"><?php echo number_format($totCuotas); ?> cuotas de pensión</span></p>
            </div>
            <i class="fas fa-money-bill-wave small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<!-- ==================== El cuadro por tipo ==================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Por tipo de beneficio</h3>
        <div class="d-flex align-items-center gap-3">
            <span class="text-muted small">beneficios vigentes</span>
            <?php echo ds_botones_exportar('becas', 'becas', $insInsights->periodo()); ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($beneficios) === 0): ?>
            <p class="text-muted mb-0">No hay becas ni descuentos vigentes.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th class="text-end">Alumnos</th>
                            <th class="text-end">Activos</th>
                            <th class="text-end">Registrado</th>
                            <th class="text-end">Valor real / mes</th>
                            <th class="text-end">Pagado</th>
                            <th class="text-end">Cuotas</th>
                            <th class="text-end">Asistencia</th>
                            <th class="text-end">Avisadas</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($beneficios as $b): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($b['tipo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($b['deducido'] > 0): ?>
                                    <i class="fas fa-info-circle text-warning ms-1"
                                       title="Se guarda con importe 0,00: su valor real se deduce de la pensión de la sede."></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?php echo number_format($b['alumnos']); ?></td>
                            <td class="text-end text-muted"><?php echo number_format($b['activos']); ?></td>
                            <td class="text-end">
                                <?php echo $b['registrado'] > 0
                                    ? '$' . number_format($b['registrado'], 2)
                                    : '<span class="text-danger">$0,00</span>'; ?>
                            </td>
                            <td class="text-end fw-semibold">$<?php echo number_format($b['mensual'], 2); ?></td>
                            <td class="text-end">$<?php echo number_format($b['pagado'], 2); ?></td>
                            <td class="text-end text-muted"><?php echo number_format($b['cuotas']); ?></td>
                            <td class="text-end">
                                <?php if ($b['asistencia'] === null): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <?php echo number_format($b['asistencia'], 1); ?> %
                                    <small class="text-muted d-block"><?php echo number_format($b['marcas']); ?> marcas</small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php echo $b['avisadas'] === null
                                    ? '<span class="text-muted">—</span>'
                                    : number_format($b['avisadas'], 1) . ' %'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="border-top">
                            <th>Sin beneficio <span class="text-muted fw-normal small">(comparación)</span></th>
                            <th class="text-end"><?php echo number_format($control['alumnos']); ?></th>
                            <th class="text-end text-muted"><?php echo number_format($control['alumnos']); ?></th>
                            <th class="text-end text-muted">—</th>
                            <th class="text-end text-muted">—</th>
                            <th class="text-end text-muted">—</th>
                            <th class="text-end text-muted">—</th>
                            <th class="text-end">
                                <?php if ($control['asistencia'] === null): ?>
                                    <span class="text-muted">—</span>
                                <?php else: ?>
                                    <?php echo number_format($control['asistencia'], 1); ?> %
                                    <small class="text-muted d-block fw-normal"><?php echo number_format($control['marcas']); ?> marcas</small>
                                <?php endif; ?>
                            </th>
                            <th class="text-end">
                                <?php echo $control['avisadas'] === null
                                    ? '<span class="text-muted">—</span>'
                                    : number_format($control['avisadas'], 1) . ' %'; ?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- ==================== Asistencia comparada ==================== -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Asistencia según el beneficio</h3>
            </div>
            <div class="card-body">
                <div id="grafico-becas" style="min-height:280px;"></div>
                <div class="callout mt-3 mb-0">
                    <strong>Léase con cautela.</strong>
                    Los grupos son de tamaño muy distinto: <?php echo number_format($control['marcas']); ?>
                    marcas de asistencia en el grupo sin beneficio frente a
                    <?php
                    $menor = null;
                    foreach ($beneficios as $b) {
                        if ($menor === null || $b['marcas'] < $menor['marcas']) { $menor = $b; }
                    }
                    echo $menor ? number_format($menor['marcas']) . ' en «' . htmlspecialchars($menor['tipo'], ENT_QUOTES, 'UTF-8') . '»' : '—';
                    ?>.
                    Con pocas marcas, unas pocas faltas mueven el porcentaje varios puntos:
                    una diferencia no es un hallazgo hasta que se sostiene con más datos.
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== Lo que conviene revisar ==================== -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title mb-0 me-auto"><i class="fas fa-search me-2" aria-hidden="true"></i>Conviene revisar</h3>
                <?php if ($totalRevisar > 0): ?><span class="badge text-bg-secondary"><?php echo $totalRevisar; ?></span><?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!$becaPagada && !$sinActivo): ?>
                    <p class="text-body-secondary mb-0"><i class="fas fa-circle-check text-success me-2" aria-hidden="true"></i>Nada incoherente en los beneficios vigentes.</p>
                <?php endif; ?>

                <?php if ($becaPagada): ?>
                    <!-- Beca del 100 % y aun así pagó pensión -->
                    <section class="ds-revisar__grupo" aria-labelledby="rev-beca">
                        <h4 class="ds-revisar__titulo" id="rev-beca">
                            <i class="fas fa-circle-exclamation text-warning" aria-hidden="true"></i>
                            <span>Beca del 100 % y pagó pensión</span>
                            <span class="badge text-bg-warning ms-auto"><?php echo count($becaPagada) . (count($becaPagada) === 1 ? ' alumno' : ' alumnos'); ?></span>
                        </h4>
                        <p class="ds-revisar__ayuda">Pagaron pensión después de concedida la beca: o la beca no corresponde, o lo pagado se debe devolver o abonar.</p>
                        <ul class="ds-revisar__lista">
                            <?php foreach ($becaPagada as $al): ?>
                                <li class="ds-revisar__alumno">
                                    <div class="ds-revisar__cab">
                                        <span class="ds-revisar__avatar" aria-hidden="true"><?php echo $rvH($rvIniciales($al['nombre'])); ?></span>
                                        <div class="ds-revisar__quien">
                                            <div class="ds-revisar__nombre"><?php echo $rvH($al['nombre'] !== '' ? $al['nombre'] : 'Alumno sin nombre'); ?></div>
                                            <div class="ds-revisar__meta">
                                                <?php if (trim($al['cedula']) !== ''): ?><span><i class="fas fa-id-card" aria-hidden="true"></i> <?php echo $rvH($al['cedula']); ?></span><?php endif; ?>
                                                <?php if (trim($al['sede']) !== ''): ?><span><i class="fas fa-location-dot" aria-hidden="true"></i> <?php echo $rvH($al['sede']); ?></span><?php endif; ?>
                                                <?php echo $rvEstado($al['estado']); ?>
                                            </div>
                                        </div>
                                        <div class="ds-revisar__monto">
                                            <strong>$<?php echo number_format($al['total'], 2); ?></strong>
                                            <span><?php echo count($al['pagos']) . (count($al['pagos']) === 1 ? ' pago' : ' pagos'); ?></span>
                                        </div>
                                    </div>
                                    <p class="ds-revisar__nota">
                                        <i class="fas fa-award" aria-hidden="true"></i>
                                        <?php echo $al['concedida'] ? 'Beca concedida el ' . $rvFecha($al['concedida']) : 'Beca sin fecha de concesión'; ?>
                                    </p>
                                    <ul class="ds-revisar__pagos" aria-label="Pagos de pensión después de la beca">
                                        <?php foreach ($al['pagos'] as $pg): ?>
                                            <li>
                                                <span class="ds-revisar__pago-fecha"><?php echo $rvFecha($pg['fecha']); ?></span>
                                                <span class="ds-revisar__pago-mes"><?php echo $rvH($pg['periodo'] !== '' ? $pg['periodo'] : '—'); ?></span>
                                                <span class="ds-revisar__pago-valor">$<?php echo number_format($pg['valor'], 2); ?></span>
                                                <?php if ($al['concedida'] && $pg['fecha'] === $al['concedida']): ?>
                                                    <span class="ds-revisar__pago-aviso">Pagado el mismo día en que se concedió la beca</span>
                                                <?php endif; ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <div class="ds-revisar__acciones">
                                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo DS_FUTBOL_URL . 'pagosNew/' . $al['id'] . '/'; ?>" target="_blank" rel="noopener"
                                           title="Pagos de <?php echo $rvH($al['nombre']); ?> (se abre en una pestaña nueva)"><i class="fas fa-list me-1" aria-hidden="true"></i>Ver pagos</a>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo DS_FUTBOL_URL . 'pagosDescuento/' . $al['id'] . '/'; ?>" target="_blank" rel="noopener"
                                           title="Beca de <?php echo $rvH($al['nombre']); ?> (se abre en una pestaña nueva)"><i class="fas fa-award me-1" aria-hidden="true"></i>Ver beca</a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <?php if ($sinActivo): ?>
                    <!-- Beneficios vigentes en alumnos que ya no están activos -->
                    <section class="ds-revisar__grupo" aria-labelledby="rev-inactivos">
                        <h4 class="ds-revisar__titulo" id="rev-inactivos">
                            <i class="fas fa-circle-info text-info" aria-hidden="true"></i>
                            <span>Beneficio vigente en alumnos que no están activos</span>
                            <span class="badge text-bg-info ms-auto"><?php echo count($sinActivo); ?></span>
                        </h4>
                        <p class="ds-revisar__ayuda">No cuestan dinero, pero inflan el recuento de becados. Si el alumno no va a volver, desactive el beneficio en su ficha.</p>
                        <div class="ds-revisar__resumen">
                            <?php foreach ($resumenSinActivo as $tipo => $n): ?>
                                <span class="ds-revisar__chip"><?php echo $rvH($tipo); ?> <strong><?php echo $n; ?></strong></span>
                            <?php endforeach; ?>
                        </div>
                        <details class="ds-revisar__detalle">
                            <summary>Ver <?php echo count($sinActivo) === 1 ? 'el alumno' : 'los ' . count($sinActivo) . ' alumnos'; ?></summary>
                            <ul class="ds-revisar__filas">
                                <?php foreach ($sinActivo as $al): ?>
                                    <li>
                                        <div class="ds-revisar__quien">
                                            <div class="ds-revisar__nombre"><?php echo $rvH($al['nombre'] !== '' ? $al['nombre'] : 'Alumno sin nombre'); ?></div>
                                            <div class="ds-revisar__meta">
                                                <span><?php echo $rvH($al['tipo']); ?><?php echo $al['desde'] ? ' desde el ' . $rvFecha($al['desde']) : ''; ?></span>
                                                <?php if (trim((string) $al['cedula']) !== ''): ?><span><i class="fas fa-id-card" aria-hidden="true"></i> <?php echo $rvH($al['cedula']); ?></span><?php endif; ?>
                                                <?php echo $rvEstado($al['estado']); ?>
                                            </div>
                                        </div>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo DS_FUTBOL_URL . 'pagosDescuento/' . $al['id'] . '/'; ?>" target="_blank" rel="noopener"
                                           title="Beneficio de <?php echo $rvH($al['nombre']); ?> (se abre en una pestaña nueva)"><i class="fas fa-award me-1" aria-hidden="true"></i>Beneficio</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    </section>
                <?php endif; ?>

                <?php if ($totDeducido > 0): ?>
                    <div class="callout mt-3 mb-0">
                        <strong>$<?php echo number_format($totDeducido, 2); ?> al mes</strong>
                        de beca completa no constan como descuento en la base: se guardan
                        con importe 0,00. Registrar su valor real haría que el total de
                        descuentos y la cartera proyectada dejaran de estar sesgados.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo DS_INSIGHTS_GRAFICOS_JS; ?>"></script>
<script src="<?php echo ds_recurso('ds_insights/assets/js/graficos.js'); ?>"></script>
<script>
/* Barras horizontales: los nombres de los tipos no caben girados y girar el
   texto del eje es lo primero que hace ilegible un gráfico. */
(function () {
    dsGrafico('grafico-becas', {
        chart: { type: 'bar', height: 280, toolbar: { show: false } },
        colors: ['#22c55e'],
        plotOptions: { bar: { horizontal: true, borderRadius: 3, barHeight: '55%' } },
        dataLabels: { enabled: true, formatter: function (v) { return v.toFixed(1) + '%'; } },
        series: [{ name: 'Asistencia', data: <?php
            $datos = [];
            foreach ($beneficios as $b) {
                if ($b['asistencia'] !== null) { $datos[] = round($b['asistencia'], 1); }
            }
            if ($control['asistencia'] !== null) { $datos[] = round($control['asistencia'], 1); }
            echo json_encode($datos);
        ?> }],
        xaxis: { categories: <?php
            $cats = [];
            foreach ($beneficios as $b) {
                if ($b['asistencia'] !== null) { $cats[] = $b['tipo']; }
            }
            if ($control['asistencia'] !== null) { $cats[] = 'Sin beneficio'; }
            echo json_encode($cats, JSON_UNESCAPED_UNICODE);
        ?>, max: 100 },
        yaxis: {},
        tooltip: { y: { formatter: function (v) { return v.toFixed(1) + ' % de asistencia'; } } }
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
