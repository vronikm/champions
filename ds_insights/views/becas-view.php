<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Becas y descuentos
|--------------------------------------------------------------------------
| Cuánto cuesta el beneficio, a cuántos alcanza, cuánto pagaron aun así y
| cómo asisten. Cuatro preguntas que hasta ahora había que responder a mano.
|
|
| QUÉ GUARDA EL BENEFICIO (corregido el 2026-11-19)
|
| El valor del beneficio es la PENSIÓN QUE PAGA el alumno, no el descuento:
| «Descuento $25,00» en una sede de $35,00 paga $25,00 y la escuela deja de
| cobrar $10,00. La Beca 100 % guarda 0,00 porque paga 0. Antes se sumaba el
| valor como si fuera el descuento, y la beca completa aparecía como un
| subsidio «no registrado». Ver insightsController::beneficios().
|
| Las cifras son de alumnos ACTIVOS: el beneficio de un inactivo no cuesta
| nada. Los inactivos con el beneficio vigente se cuentan aparte.
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

/* El mes que se mira: ?anio=&mes=, por omisión el mes en curso (nunca uno
   futuro). Ver insightsController::mesBecas(). */
$pMes       = $insInsights->mesBecas($_GET['anio'] ?? null, $_GET['mes'] ?? null);
$MESES      = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$anioSel    = (int) substr($pMes['desde'], 0, 4);
$mesSel     = (int) substr($pMes['desde'], 5, 2);
$nombreMes  = $MESES[$mesSel] . ' ' . $anioSel;
$esActual   = $pMes['desde'] === date('Y-m-01');
$beneficios = $insInsights->beneficios($pMes);
$control    = $insInsights->sinBeneficio($pMes);
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

$totActivos   = array_sum(array_column($beneficios, 'activos'));
$totInactivos = array_sum(array_column($beneficios, 'inactivos'));
$totPension   = array_sum(array_column($beneficios, 'pension'));
$totPaga      = array_sum(array_column($beneficios, 'paga'));
$totBeneficio = array_sum(array_column($beneficios, 'beneficio'));
$totPagado    = array_sum(array_column($beneficios, 'pagado'));
$totCuotas    = array_sum(array_column($beneficios, 'cuotas'));
$totPct       = $totPension > 0 ? $totBeneficio / $totPension * 100 : null;
$bfDinero     = static fn(float $v) => '$' . number_format($v, 2);
$bfPct        = static fn(?float $v, int $dec = 0) => $v === null ? '—' : number_format($v, $dec) . ' %';

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

    /* «Por tipo de beneficio»: una ficha por tipo, la misma en el
       teléfono y en el escritorio (antes era una tabla de 9 columnas). */
    .ds-benef { border: 1px solid var(--bs-border-color); border-radius: .5rem; padding: 1rem; display: flex; flex-direction: column; gap: .75rem; }
    .ds-benef--control { background: var(--bs-tertiary-bg); border-style: dashed; }
    .ds-benef__cab { display: flex; align-items: center; gap: .5rem; }
    .ds-benef__cab h4 { font-size: 1.0625rem; font-weight: 600; margin: 0 auto 0 0; }
    .ds-benef__cab h4 small { font-size: .8125rem; font-weight: 400; color: var(--bs-secondary-color); }
    .ds-benef__vacio { margin: 0; font-size: .875rem; color: var(--bs-secondary-color); }
    .ds-benef__cifra strong { display: block; font-size: 1.75rem; line-height: 1.1; font-variant-numeric: tabular-nums; }
    .ds-benef__cifra span { font-size: .8125rem; color: var(--bs-secondary-color); }
    /* Parte y todo: lo que pagan (gris) y el descuento (acento), con 2 px de
       separación entre tramos. El texto de la leyenda lleva las cifras. */
    .ds-benef__barra { display: flex; gap: 2px; height: .625rem; border-radius: 999px; overflow: hidden; }
    .ds-benef__paga { background: var(--bs-secondary-bg); box-shadow: inset 0 0 0 1px var(--bs-border-color); }
    .ds-benef__desc { background: #d97706; }
    .ds-benef__barra > span { display: block; height: 100%; }
    .ds-benef__barra > span:first-child { border-radius: 999px 0 0 999px; }
    .ds-benef__barra > span:last-child { border-radius: 0 999px 999px 0; }
    .ds-benef__barra > span:only-child { border-radius: 999px; }
    .ds-benef__leyenda { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .25rem .75rem; font-size: .8125rem; margin-top: -.375rem; }
    .ds-benef__muestra { display: inline-block; width: .625rem; height: .625rem; border-radius: 2px; margin-right: .375rem; vertical-align: -.05em; }
    .ds-benef__datos { margin: 0; display: grid; gap: .5rem; }
    .ds-benef__datos > div { display: grid; gap: .125rem; padding-top: .5rem; border-top: 1px solid var(--bs-border-color-translucent); }
    .ds-benef__datos dt { font-weight: 400; font-size: .8125rem; color: var(--bs-secondary-color); }
    .ds-benef__datos dd { margin: 0; font-weight: 600; font-size: .9375rem; font-variant-numeric: tabular-nums; }
    .ds-benef__datos dd small { display: block; font-weight: 400; font-size: .75rem; color: var(--bs-secondary-color); }
    .ds-benef__notas { list-style: none; margin: auto 0 0; padding: .5rem 0 0; border-top: 1px dashed var(--bs-border-color); font-size: .8125rem; display: grid; gap: .375rem; }
    .ds-benef__notas li { display: grid; grid-template-columns: 1rem 1fr; gap: .5rem; align-items: baseline; }
    .ds-benef__ayuda { margin-top: 1rem; font-size: .875rem; }
    .ds-benef__ayuda summary { cursor: pointer; color: var(--bs-link-color); padding: .25rem 0; }
    .ds-benef__ayuda dl { margin: .5rem 0 0; display: grid; gap: .125rem; }
    .ds-benef__ayuda dt { font-weight: 600; margin-top: .5rem; }
    .ds-benef__ayuda dd { margin: 0; color: var(--bs-secondary-color); }

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

<!-- ==================== El mes ==================== -->
<?php /* GET: el mes queda en la dirección (se puede guardar o compartir) y
         volver atrás no pide reenviar un formulario. Cambiar el mes o el año
         lo aplica solo; «Ver» queda para quien navega sin JavaScript. */ ?>
<form method="get" class="card mb-3 ds-becas__mes" aria-label="Mes de la información">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="becasMes" class="form-label small text-body-secondary mb-1">Mes</label>
                <select id="becasMes" name="mes" class="form-select">
                    <?php foreach ($MESES as $n => $m): ?>
                        <option value="<?php echo $n; ?>"<?php echo $n === $mesSel ? ' selected' : ''; ?><?php echo $anioSel === (int) date('Y') && $n > (int) date('n') ? ' disabled' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="becasAnio" class="form-label small text-body-secondary mb-1">Año</label>
                <select id="becasAnio" name="anio" class="form-select">
                    <?php for ($y = (int) date('Y'); $y >= $insInsights->primerAnioBecas(); $y--): ?>
                        <option value="<?php echo $y; ?>"<?php echo $y === $anioSel ? ' selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <noscript><button type="submit" class="btn btn-primary">Ver</button></noscript>
            <?php if (!$esActual): ?>
                <a href="<?php echo APP_URL; ?>becas/" class="btn btn-outline-secondary"><i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Mes en curso</a>
            <?php endif; ?>
            <p class="small text-body-secondary mb-0 ms-sm-auto ds-becas__mes-nota">
                <i class="fas fa-calendar-days me-1" aria-hidden="true"></i>Información de <strong><?php echo $rvH($nombreMes); ?></strong><?php echo $esActual ? ' (mes en curso)' : ''; ?>
            </p>
        </div>
    </div>
</form>

<!-- ==================== Las cifras ==================== -->
<div class="row">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-primary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo number_format($totActivos); ?></h3>
                <p class="mb-0">Alumnos activos con beneficio en <?php echo $rvH($nombreMes); ?></p>
                <p class="mb-0 small opacity-75"><?php echo $totInactivos > 0 ? '+ ' . number_format($totInactivos) . ' inactivos con el beneficio vigente' : 'ningún inactivo con beneficio vigente'; ?></p>
            </div>
            <i class="fas fa-user-graduate small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-danger h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $bfDinero($totBeneficio); ?></h3>
                <p class="mb-0">Se deja de cobrar en <?php echo $rvH($nombreMes); ?></p>
                <p class="mb-0 small opacity-75"><?php echo $totPct === null ? '—' : 'el ' . $bfPct($totPct) . ' de su pensión normal'; ?></p>
            </div>
            <i class="fas fa-tags small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-secondary h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $bfDinero($totPaga); ?></h3>
                <p class="mb-0">Se les cobra en <?php echo $rvH($nombreMes); ?></p>
                <p class="mb-0 small opacity-75">en vez de <?php echo $bfDinero($totPension); ?> sin beneficio</p>
                <?php /* Lo cobrado de ese mes a los beneficiarios: la suma de
                         «Pagado de <mes>» de cada tipo (pensiones de ese mes). */ ?>
                <p class="mb-0 small fw-semibold">cobrado <?php echo $bfDinero($totPagado); ?><?php echo $totPaga > 0 ? ' (' . $bfPct($totPagado / $totPaga * 100) . ')' : ''; ?></p>
            </div>
            <i class="fas fa-receipt small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="small-box text-bg-success h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $bfDinero($control['deberian']); ?></h3>
                <p class="mb-0">Debería ingresar sin beneficio</p>
                <p class="mb-0 small opacity-75">pensión de <?php echo number_format($control['alumnos']); ?> alumnos sin beca ni descuento · cobrado <?php echo $bfDinero($control['pagado']); ?></p>
            </div>
            <i class="fas fa-money-bill-wave small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<!-- ==================== Por tipo de beneficio ==================== -->
<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h3 class="card-title mb-0 me-auto">Por tipo de beneficio</h3>
        <span class="text-body-secondary small">alumnos activos · <?php echo $rvH($nombreMes); ?></span>
        <?php echo ds_botones_exportar('becas', 'becas', $pMes); ?>
    </div>
    <div class="card-body">
        <?php if (count($beneficios) === 0): ?>
            <p class="text-body-secondary mb-0">No hay becas ni descuentos vigentes.</p>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($beneficios as $b):
                    $completa   = $b['cod'] === 'DBC';
                    $pctDesc    = $b['porcentaje'] ?? 0;
                    $pensionMed = $b['activos'] > 0 ? $b['pension'] / $b['activos'] : null;
                    $difAsis    = ($b['asistencia'] !== null && $control['asistencia'] !== null) ? $b['asistencia'] - $control['asistencia'] : null;
                ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <article class="ds-benef h-100" aria-labelledby="benef-<?php echo $rvH($b['cod']); ?>">
                            <header class="ds-benef__cab">
                                <h4 id="benef-<?php echo $rvH($b['cod']); ?>"><?php echo $rvH($b['tipo']); ?></h4>
                                <span class="badge text-bg-light border"><?php echo number_format($b['activos']) . ($b['activos'] === 1 ? ' activo' : ' activos'); ?></span>
                            </header>

                            <?php if ($b['activos'] === 0): ?>
                                <p class="ds-benef__vacio">Ningún alumno activo lo tenía en ese mes: no costó nada.</p>
                            <?php else: ?>
                                <div class="ds-benef__cifra">
                                    <strong><?php echo $bfDinero($b['beneficio']); ?></strong>
                                    <span>se deja de cobrar en <?php echo $rvH($nombreMes); ?></span>
                                </div>

                                <div class="ds-benef__barra" role="img"
                                     aria-label="<?php echo $rvH('Pagan ' . $bfPct(100 - $pctDesc) . ' de la pensión; descuento ' . $bfPct($pctDesc)); ?>">
                                    <?php if ($pctDesc < 100): ?><span class="ds-benef__paga" style="width:<?php echo round(100 - $pctDesc, 2); ?>%"></span><?php endif; ?>
                                    <?php if ($pctDesc > 0): ?><span class="ds-benef__desc" style="width:<?php echo round($pctDesc, 2); ?>%"></span><?php endif; ?>
                                </div>
                                <div class="ds-benef__leyenda">
                                    <span><i class="ds-benef__muestra ds-benef__paga" aria-hidden="true"></i>Pagan <?php echo $bfDinero($b['paga']); ?></span>
                                    <span><i class="ds-benef__muestra ds-benef__desc" aria-hidden="true"></i>Descuento del <?php echo $bfPct($pctDesc); ?></span>
                                </div>

                                <dl class="ds-benef__datos">
                                    <div>
                                        <dt>Pensión que paga cada uno</dt>
                                        <dd>
                                            <?php if ($completa || $b['pagaMax'] === 0.0): ?>
                                                No paga pensión
                                            <?php else: ?>
                                                <?php echo $bfDinero($b['pagaMedia']); ?> en promedio
                                                <?php if ($b['pagaMin'] !== $b['pagaMax']): ?>
                                                    <small>entre <?php echo $bfDinero($b['pagaMin']); ?> y <?php echo $bfDinero($b['pagaMax']); ?></small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <small>pensión normal <?php echo $bfDinero($pensionMed); ?></small>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Pagado de <?php echo $rvH($nombreMes); ?></dt>
                                        <dd>
                                            <?php echo $bfDinero($b['pagado']); ?>
                                            <small><?php echo $completa
                                                ? ($b['pagado'] > 0 ? 'no deberían pagar · <a href="#rev-beca">ver abajo</a>' : 'no pagan pensión')
                                                : 'de ' . $bfDinero($b['paga']) . ' que deberían pagar · ' . number_format($b['cuotas']) . ($b['cuotas'] === 1 ? ' cuota' : ' cuotas'); ?></small>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Asistencia</dt>
                                        <dd>
                                            <?php if ($b['asistencia'] === null): ?>
                                                <span class="text-body-secondary">Sin registros</span>
                                            <?php else: ?>
                                                <?php echo $bfPct($b['asistencia'], 1); ?>
                                                <?php if ($difAsis !== null): ?>
                                                    <small><?php echo ($difAsis >= 0 ? '+' : '−') . number_format(abs($difAsis), 1) . ' puntos frente a quienes no tienen beneficio'; ?></small>
                                                <?php endif; ?>
                                                <small><?php echo number_format($b['marcas']); ?> días registrados</small>
                                            <?php endif; ?>
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Faltas avisadas</dt>
                                        <dd>
                                            <?php echo $b['avisadas'] === null ? '<span class="text-body-secondary">Sin faltas</span>' : $bfPct($b['avisadas'], 1) . ' <small>de sus faltas fueron justificadas</small>'; ?>
                                        </dd>
                                    </div>
                                </dl>
                            <?php endif; ?>

                            <?php if ($b['sinRebaja'] > 0 || $b['inactivos'] > 0): ?>
                                <ul class="ds-benef__notas">
                                    <?php if ($b['sinRebaja'] > 0): ?>
                                        <li><i class="fas fa-triangle-exclamation text-warning" aria-hidden="true"></i>
                                            <span><?php echo $b['sinRebaja'] === 1 ? '1 alumno lo tiene pero paga' : $b['sinRebaja'] . ' alumnos lo tienen pero pagan'; ?> la pensión completa: el descuento no rebaja nada.</span></li>
                                    <?php endif; ?>
                                    <?php if ($b['inactivos'] > 0): ?>
                                        <li><i class="fas fa-user-slash text-body-secondary" aria-hidden="true"></i>
                                            <span><a href="#rev-inactivos"><?php echo number_format($b['inactivos']) . ($b['inactivos'] === 1 ? ' inactivo lo tiene' : ' inactivos lo tienen'); ?> vigente</a> (no cuesta, pero conviene desactivarlo).</span></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </article>
                    </div>
                <?php endforeach; ?>

                <!-- El grupo de comparación -->
                <div class="col-12 col-md-6 col-xl-4">
                    <article class="ds-benef ds-benef--control h-100" aria-labelledby="benef-control">
                        <header class="ds-benef__cab">
                            <h4 id="benef-control">Sin beneficio <small>para comparar</small></h4>
                            <span class="badge text-bg-light border"><?php echo number_format($control['alumnos']); ?> activos</span>
                        </header>
                        <div class="ds-benef__cifra">
                            <strong><?php echo $bfDinero($control['deberian']); ?></strong>
                            <span>debería ingresar en <?php echo $rvH($nombreMes); ?>: la pensión completa de su sede</span>
                        </div>
                        <dl class="ds-benef__datos">
                            <div>
                                <dt>Pagado de <?php echo $rvH($nombreMes); ?></dt>
                                <dd>
                                    <?php echo $bfDinero($control['pagado']); ?>
                                    <small><?php echo $control['deberian'] > 0 ? $bfPct($control['pagado'] / $control['deberian'] * 100) . ' de lo que debería ingresar' : '—'; ?></small>
                                </dd>
                            </div>
                            <div>
                                <dt>Asistencia</dt>
                                <dd>
                                    <?php if ($control['asistencia'] === null): ?>
                                        <span class="text-body-secondary">Sin registros</span>
                                    <?php else: ?>
                                        <?php echo $bfPct($control['asistencia'], 1); ?>
                                        <small><?php echo number_format($control['marcas']); ?> días registrados</small>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <div>
                                <dt>Faltas avisadas</dt>
                                <dd><?php echo $control['avisadas'] === null ? '<span class="text-body-secondary">Sin faltas</span>' : $bfPct($control['avisadas'], 1) . ' <small>de sus faltas fueron justificadas</small>'; ?></dd>
                            </div>
                        </dl>
                    </article>
                </div>
            </div>

            <details class="ds-benef__ayuda">
                <summary><i class="fas fa-circle-question me-1" aria-hidden="true"></i>Cómo se calculan estas cifras</summary>
                <dl>
                    <dt>El mes</dt>
                    <dd>Todo se calcula para el mes elegido arriba (por omisión, el mes en curso). La base no guarda cuándo se quitó un beneficio ni cuándo un alumno pasó a inactivo: para un mes pasado se cuentan los alumnos activos hoy que ya habían ingresado y ya tenían el beneficio concedido. Los inactivos con el beneficio vigente y «Conviene revisar» son de hoy.</dd>
                    <dt>Alumnos</dt>
                    <dd>Sólo los activos con el beneficio vigente. A un inactivo no se le cobra pensión, así que su beneficio no cuesta nada; se cuenta aparte.</dd>
                    <dt>Se deja de cobrar al mes</dt>
                    <dd>Pensión normal de la sede de cada alumno menos la pensión que paga con el beneficio. En el beneficio se guarda <em>lo que paga</em>: «Descuento $25» en una sede de $35 paga $25 y se dejan de cobrar $10. La beca del 100 % paga $0.</dd>
                    <dt>Descuento del %</dt>
                    <dd>Lo que se deja de cobrar sobre la pensión normal de esos alumnos.</dd>
                    <dt>Pagado del mes</dt>
                    <dd>Las pensiones de ese mes que se cobraron: por el mes que dice el pago («Mayo / 2026»); si no se entiende, por su fecha. Completos y abonos; nunca los eliminados.</dd>
                    <dt>Debería ingresar sin beneficio</dt>
                    <dd>La pensión completa de la sede de cada alumno activo sin beca ni descuento en ese mes: lo que debería cobrarse por ellos. Al lado, lo cobrado de ese mes.</dd>
                    <dt>Asistencia</dt>
                    <dd>Días presentes o con atraso sobre los días registrados por los profesores en ese mes (el justificado cuenta como falta). Se compara con los activos sin beneficio. Con pocos días registrados, unas pocas faltas mueven el porcentaje varios puntos.</dd>
                    <dt>Faltas avisadas</dt>
                    <dd>De las faltas, cuántas fueron justificadas (avisaron).</dd>
                </dl>
            </details>
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

            </div>
        </div>
    </div>
</div>

<script>
/* Cambiar el mes o el año muestra ese mes (el formulario es GET). */
document.querySelectorAll('#becasMes, #becasAnio').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
});
</script>
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
