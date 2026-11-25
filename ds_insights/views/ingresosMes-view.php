<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Ingresos del mes
|--------------------------------------------------------------------------
| Lo cobrado a los alumnos y lo gastado en un mes, como el reporte que la
| escuela armaba a mano en Excel: un bloque por rubro (campeonatos,
| inscripciones, kits, uniformes) y las pensiones por sede y lugar de
| entrenamiento. Cada bloque lleva sus cobros, sus egresos y su neto, y
| cada importe va en la columna de su forma de pago (efectivo, Banco de
| Loja, Banco Pichincha…), sea un ingreso o un egreso.
|
| Qué se cuenta y por qué: insightsController::ingresosDelMes(). El bloque
| de un egreso lo elige quien lo registra en Fútbol › Balance › Egresos
| (migración 094); sin bloque sale en «Egresos generales».
|
|
| PENSADA PRIMERO PARA EL TELÉFONO
|
| La tabla del Excel tiene nueve columnas: en 360 px no cabe. En el teléfono
| cada movimiento es una ficha y cada bloque se pliega; en el escritorio se
| ve la tabla con una columna por forma de pago. Las cifras son las mismas:
| salen del mismo arreglo.
|
|
| PERMISO PROPIO
|
| Muestra nombres y cédulas, así que se concede aparte (migración 093), como
| Transacciones, y cada consulta queda en la auditoría.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$MESES = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto',
          'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

$pMes      = $insInsights->mesIngresos(isset($_GET['mes']) ? (string) $_GET['mes'] : null);
$mesValor  = substr($pMes['desde'], 0, 7);
$nombreMes = $MESES[(int) substr($pMes['desde'], 5, 2)] . ' ' . substr($pMes['desde'], 0, 4);
$esActual  = $pMes['desde'] === date('Y-m-01');

/* La sede sólo vale si es una de las del usuario. */
$sedes    = $insInsights->sedesIngresos();
$sedeSel  = isset($_GET['sede']) ? (int) $_GET['sede'] : 0;
if ($sedeSel > 0 && !in_array($sedeSel, array_map('intval', array_column($sedes, 'id')), true)) {
    $sedeSel = 0;
}
$nombreSede = 'Todas las sedes';
foreach ($sedes as $s) {
    if ((int) $s['id'] === $sedeSel) { $nombreSede = $s['nombre']; }
}

$r = $insInsights->ingresosDelMes($pMes, $sedeSel);
$hayDatos = $r['cobros'] + $r['nEgresos'] > 0;

$insInsights->auditar('VER_INGRESOS_MES', 'ingresosMes', [
    'desde' => $pMes['desde'], 'hasta' => $pMes['hasta'], 'filas' => $r['cobros'] + $r['nEgresos'],
]);

$h      = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$dinero = static fn(float $v): string => ($v < 0 ? '−$' : '$') . number_format(abs($v), 2);
$fecha  = static fn(string $f): string => $f !== '' && !str_starts_with($f, '0000') ? date('d/m/Y', strtotime($f)) : '—';
$cols   = $r['columnas'];

/* Un color por columna, siempre acompañado de su nombre (nunca sólo el color). */
$TONO = ['EFE' => 'success', 'BLO' => 'primary', 'BPI' => 'warning', 'TRA' => 'info', 'TAR' => 'secondary', 'CHE' => 'secondary', 'DES' => 'secondary'];
$tono = static fn(string $k): string => $TONO[$k] ?? 'secondary';

$ICONO = ['RPC' => 'fa-trophy', 'RIN' => 'fa-user-plus', 'RKE' => 'fa-socks',
          'RNU' => 'fa-shirt', 'ROT' => 'fa-receipt', 'RPE' => 'fa-calendar-check', 'EGR' => 'fa-file-invoice-dollar'];

$tituloVista = 'Ingresos del mes';
$vistaActual = 'reporteList';

require_once __DIR__ . "/inc/layout-top.php";
?>

<style>
    /* Colores de cada columna: los de Bootstrap, que ya tienen su versión
       para el modo oscuro. */
    .ds-im { --im-success: var(--bs-success); --im-primary: var(--bs-primary); --im-warning: #e0a800;
             --im-info: var(--bs-info); --im-secondary: var(--bs-secondary); }
    .ds-im-punto { display: inline-block; width: .625rem; height: .625rem; border-radius: 50%; flex: 0 0 auto; }
    .ds-im-punto--success { background: var(--im-success); } .ds-im-punto--primary { background: var(--im-primary); }
    .ds-im-punto--warning { background: var(--im-warning); } .ds-im-punto--info { background: var(--im-info); }
    .ds-im-punto--secondary { background: var(--im-secondary); }
    .ds-im-egreso { color: var(--bs-danger-text-emphasis); }
    .ds-im-neto-neg { color: var(--bs-danger-text-emphasis); }

    /* Filtros */
    .ds-im-filtros .form-control, .ds-im-filtros .form-select { min-height: 2.5rem; }
    .ds-im-filtros__campo { flex: 1 1 10rem; }
    .ds-im-filtros__acciones { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }

    /* Cifras */
    .ds-im-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: .75rem; margin-bottom: .75rem; }
    .ds-im-kpi { border: 1px solid var(--bs-border-color); border-radius: .75rem; padding: .875rem 1rem; background: var(--bs-body-bg); }
    .ds-im-kpi--total { background: #15803d; border-color: #15803d; color: #fff; }
    .ds-im-kpi--egreso { border-color: var(--bs-danger-border-subtle); }
    .ds-im-kpi--neto { border-width: 2px; border-color: #15803d; }
    .ds-im-kpi__etq { display: flex; align-items: center; gap: .4rem; font-size: .8125rem; color: var(--bs-secondary-color); }
    .ds-im-kpi--total .ds-im-kpi__etq, .ds-im-kpi--total .ds-im-kpi__nota { color: rgba(255,255,255,.85); }
    .ds-im-kpi__cifra { display: block; font-size: 1.5rem; font-weight: 700; line-height: 1.2; margin-top: .25rem; font-variant-numeric: tabular-nums; }
    .ds-im-kpi__nota { font-size: .75rem; color: var(--bs-secondary-color); }
    .ds-im-columnas { display: grid; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .ds-im-col { border: 1px solid var(--bs-border-color); border-radius: .75rem; padding: .75rem 1rem; background: var(--bs-body-bg); }
    .ds-im-col__etq { display: flex; align-items: center; gap: .4rem; font-weight: 600; font-size: .875rem; margin-bottom: .375rem; }
    .ds-im-col dl { display: grid; grid-template-columns: 1fr auto; gap: .125rem .75rem; margin: 0; font-size: .8125rem; }
    .ds-im-col dt { font-weight: 400; color: var(--bs-secondary-color); }
    .ds-im-col dd { margin: 0; text-align: right; font-variant-numeric: tabular-nums; }
    .ds-im-col .ds-im-col__neto { font-weight: 700; font-size: .9375rem; border-top: 1px solid var(--bs-border-color); padding-top: .25rem; }

    /* Índice de bloques: en el teléfono, una fila que se arrastra. */
    .ds-im-indice { display: flex; gap: .5rem; overflow-x: auto; padding: .125rem 0 .5rem; margin-bottom: .5rem;
                    scroll-snap-type: x proximity; -webkit-overflow-scrolling: touch; }
    .ds-im-indice a { flex: 0 0 auto; scroll-snap-align: start; display: inline-flex; flex-direction: column; gap: .125rem;
                      padding: .5rem .75rem; border: 1px solid var(--bs-border-color); border-radius: .625rem;
                      background: var(--bs-body-bg); color: var(--bs-body-color); text-decoration: none; min-width: 8.5rem; }
    .ds-im-indice a:hover, .ds-im-indice a:focus-visible { border-color: #15803d; }
    .ds-im-indice small { color: var(--bs-secondary-color); font-size: .75rem; max-width: 13rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .ds-im-indice strong { font-variant-numeric: tabular-nums; }

    /* Bloques plegables */
    .ds-im-bloque { border: 1px solid var(--bs-border-color); border-radius: .75rem; background: var(--bs-body-bg);
                    margin-bottom: .875rem; overflow: hidden; scroll-margin-top: 4.5rem; }
    .ds-im-bloque > summary { list-style: none; cursor: pointer; padding: .875rem 1rem; display: grid;
                              grid-template-columns: 2.5rem 1fr auto; gap: .25rem .75rem; align-items: center; }
    .ds-im-bloque > summary::-webkit-details-marker { display: none; }
    .ds-im-bloque > summary:focus-visible { outline: 2px solid #15803d; outline-offset: -2px; }
    .ds-im-bloque__icono { width: 2.5rem; height: 2.5rem; border-radius: .625rem; display: grid; place-items: center;
                           background: rgba(21,128,61,.12); color: #15803d; grid-row: span 2; }
    .ds-im-bloque--egr .ds-im-bloque__icono { background: var(--bs-danger-bg-subtle); color: var(--bs-danger-text-emphasis); }
    [data-bs-theme="dark"] .ds-im-bloque__icono { color: #4ade80; background: rgba(74,222,128,.12); }
    [data-bs-theme="dark"] .ds-im-bloque--egr .ds-im-bloque__icono { color: var(--bs-danger-text-emphasis); background: var(--bs-danger-bg-subtle); }
    .ds-im-bloque__titulo { font-weight: 600; font-size: 1rem; line-height: 1.25; margin: 0; overflow-wrap: anywhere; }
    .ds-im-bloque__lugar { display: block; font-weight: 400; font-size: .8125rem; color: var(--bs-secondary-color); }
    .ds-im-bloque__total { text-align: right; line-height: 1.15; }
    .ds-im-bloque__total strong { display: block; font-size: 1.125rem; font-variant-numeric: tabular-nums; }
    .ds-im-bloque__total span { font-size: .75rem; color: var(--bs-secondary-color); }
    .ds-im-bloque__chevron { transition: transform .2s; color: var(--bs-secondary-color); margin-left: .375rem; }
    .ds-im-bloque[open] > summary .ds-im-bloque__chevron { transform: rotate(180deg); }
    .ds-im-bloque__reparto { grid-column: 2 / -1; display: flex; flex-wrap: wrap; gap: .25rem .875rem; font-size: .75rem; color: var(--bs-secondary-color); }
    .ds-im-bloque__reparto span { display: inline-flex; align-items: center; gap: .3rem; font-variant-numeric: tabular-nums; }
    .ds-im-bloque__cuentas { grid-column: 2 / -1; display: flex; flex-wrap: wrap; gap: .25rem 1rem; font-size: .8125rem; font-variant-numeric: tabular-nums; }
    .ds-im-barra { grid-column: 2 / -1; display: flex; gap: 2px; height: .375rem; border-radius: 999px; overflow: hidden; background: var(--bs-tertiary-bg); }
    .ds-im-barra > span { display: block; height: 100%; }
    .ds-im-bloque__cuerpo { border-top: 1px solid var(--bs-border-color); }
    .ds-im-sub { font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: .5rem 1rem;
                 background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); border-bottom: 1px solid var(--bs-border-color); }
    .ds-im-sub--egreso { color: var(--bs-danger-text-emphasis); border-top: 1px solid var(--bs-border-color); }

    /* Campeonato: los cobros agrupados por campeonato */
    .ds-im-camps { display: flex; flex-wrap: wrap; gap: .5rem; padding: .75rem 1rem; border-bottom: 1px solid var(--bs-border-color); }
    .ds-im-camps a { display: inline-flex; align-items: baseline; gap: .5rem; padding: .3rem .75rem; border-radius: 999px; font-size: .8125rem;
                     border: 1px solid var(--bs-border-color); color: var(--bs-body-color); text-decoration: none; background: var(--bs-body-bg); }
    .ds-im-camps a:hover, .ds-im-camps a:focus-visible { border-color: #15803d; }
    .ds-im-camps strong { font-variant-numeric: tabular-nums; }
    .ds-im-tabla tr.ds-im-grupo th { position: static; background: rgba(21,128,61,.08); color: var(--bs-emphasis-color);
                                     text-transform: none; letter-spacing: 0; font-size: .8125rem; border-top: 2px solid var(--bs-border-color); scroll-margin-top: 2.5rem; }
    .ds-im-tabla tr.ds-im-grupo th i { color: #15803d; }
    [data-bs-theme="dark"] .ds-im-tabla tr.ds-im-grupo th { background: rgba(74,222,128,.10); }
    [data-bs-theme="dark"] .ds-im-tabla tr.ds-im-grupo th i { color: #4ade80; }
    .ds-im-grupo__cuenta { font-weight: 400; color: var(--bs-secondary-color); margin-left: .5rem; }
    .ds-im-grupo-m { border-bottom: 1px solid var(--bs-border-color); }
    .ds-im-grupo-m > summary { list-style: none; cursor: pointer; display: grid; grid-template-columns: 1fr auto; gap: .125rem .75rem;
                               padding: .75rem 1rem; min-height: 2.75rem;
                               /* Fija arriba mientras se recorren sus fichas: fondo opaco. */
                               position: sticky; top: 0; z-index: 1;
                               background: linear-gradient(rgba(21,128,61,.06), rgba(21,128,61,.06)), var(--bs-body-bg); }
    [data-bs-theme="dark"] .ds-im-grupo-m > summary { background: linear-gradient(rgba(74,222,128,.08), rgba(74,222,128,.08)), var(--bs-body-bg); }
    .ds-im-grupo-m > summary::-webkit-details-marker { display: none; }
    .ds-im-grupo-m > summary:focus-visible { outline: 2px solid #15803d; outline-offset: -2px; }
    .ds-im-grupo-m[open] > summary { border-bottom: 1px solid var(--bs-border-color-translucent); }
    .ds-im-grupo-m[open] > summary .ds-im-bloque__chevron { transform: rotate(180deg); }
    .ds-im-grupo-m__nombre { font-weight: 600; line-height: 1.3; overflow-wrap: anywhere; }
    .ds-im-grupo-m__nombre i { color: #15803d; margin-right: .25rem; }
    [data-bs-theme="dark"] .ds-im-grupo-m__nombre i { color: #4ade80; }
    .ds-im-grupo-m__total { font-variant-numeric: tabular-nums; white-space: nowrap; text-align: right; }
    .ds-im-grupo-m__meta { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .125rem .75rem; font-size: .75rem; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; }
    .ds-im-grupo-m__meta > span { display: inline-flex; align-items: center; gap: .3rem; }
    .ds-im-grupo-m .ds-im-ficha { padding-left: 1.5rem; }
    .ds-im-resumen__sub td { font-size: .8125rem; color: var(--bs-secondary-color); border-top: 0; padding-top: .125rem; padding-bottom: .125rem; }
    .ds-im-resumen__sub td:first-child { padding-left: 1.5rem; }

    /* Escritorio: la tabla del reporte */
    .ds-im-tabla { margin: 0; font-size: .875rem; }
    .ds-im-tabla th { font-size: .75rem; text-transform: uppercase; letter-spacing: .02em; color: var(--bs-secondary-color);
                      white-space: nowrap; background: var(--bs-tertiary-bg); }
    /* Cada sección: la cabecera y el pie quedan fijos; sólo se mueven las filas. */
    .ds-im-tabla-caja { max-height: min(36rem, 70vh); overflow: auto; }
    .ds-im-tabla thead { position: sticky; top: 0; z-index: 2; }
    .ds-im-tabla thead th { box-shadow: inset 0 -1px 0 var(--bs-border-color); }
    .ds-im-tabla tfoot { position: sticky; bottom: 0; z-index: 2; }
    .ds-im-telefono__filas { max-height: min(60vh, 32rem); overflow-y: auto; }
    .ds-im-tabla td { vertical-align: middle; }
    .ds-im-tabla .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .ds-im-tabla tfoot td { font-weight: 700; background: var(--bs-tertiary-bg); }
    .ds-im-tabla tfoot tr:first-child td { box-shadow: inset 0 2px 0 var(--bs-border-color); }
    .ds-im-tabla .ds-im-tabla__alumno { min-width: 13rem; }
    .ds-im-tabla .ds-im-tabla__ident { font-variant-numeric: tabular-nums; white-space: nowrap; color: var(--bs-secondary-color); }
    .ds-im-tabla__detalle small { display: block; color: var(--bs-secondary-color); }
    .ds-im-forma { display: inline-flex; align-items: center; gap: .35rem; white-space: nowrap; }
    .ds-im-abono { font-size: .6875rem; font-weight: 600; padding: .1em .45em; border-radius: .35rem; vertical-align: .1em;
                   background: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis); border: 1px solid var(--bs-info-border-subtle); }

    /* Teléfono: una ficha por movimiento */
    .ds-im-fichas { list-style: none; margin: 0; padding: 0; }
    .ds-im-ficha { display: grid; grid-template-columns: 1fr auto; gap: .125rem .75rem; padding: .75rem 1rem; }
    .ds-im-ficha + .ds-im-ficha { border-top: 1px solid var(--bs-border-color-translucent); }
    .ds-im-ficha__nombre { font-weight: 600; line-height: 1.3; overflow-wrap: anywhere; }
    .ds-im-ficha__valor { font-weight: 700; font-size: 1.0625rem; font-variant-numeric: tabular-nums; text-align: right; align-self: start; white-space: nowrap; }
    .ds-im-ficha__meta { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .125rem .75rem; font-size: .8125rem; color: var(--bs-secondary-color); }
    .ds-im-ficha__meta i { width: .875rem; text-align: center; opacity: .8; }
    .ds-im-cuadre { display: grid; grid-template-columns: 1fr auto; gap: .25rem .75rem; padding: .75rem 1rem;
                    background: var(--bs-tertiary-bg); font-size: .875rem; border-top: 2px solid var(--bs-border-color); font-variant-numeric: tabular-nums; }
    .ds-im-cuadre dt { font-weight: 400; } .ds-im-cuadre dd { margin: 0; text-align: right; font-weight: 600; }
    .ds-im-cuadre .ds-im-cuadre__neto { font-weight: 700; font-size: 1rem; border-top: 1px solid var(--bs-border-color); padding-top: .25rem; }

    /* Resumen general */
    .ds-im-resumen th:first-child, .ds-im-resumen td:first-child { position: sticky; left: 0; background: var(--bs-body-bg); z-index: 1; }
    .ds-im-resumen thead th { font-size: .75rem; text-transform: uppercase; letter-spacing: .02em; color: var(--bs-secondary-color); white-space: nowrap; }
    .ds-im-resumen thead th:first-child, .ds-im-resumen tfoot td:first-child { background: var(--bs-tertiary-bg); }
    .ds-im-resumen tfoot td { font-weight: 700; background: var(--bs-tertiary-bg); }
    .ds-im-resumen .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    @media (max-width: 575.98px) {
        .ds-im-kpis { grid-template-columns: 1fr 1fr; gap: .5rem; }
        .ds-im-kpi { padding: .75rem; }
        .ds-im-kpi--total { grid-column: 1 / -1; }
        .ds-im-kpi__cifra { font-size: 1.25rem; }
        .ds-im-columnas { grid-template-columns: 1fr; gap: .5rem; }
        .ds-im-bloque > summary { grid-template-columns: 2.25rem 1fr auto; padding: .75rem; }
        .ds-im-bloque__icono { width: 2.25rem; height: 2.25rem; }
        .ds-im-filtros__acciones { width: 100%; }
        .ds-im-filtros__acciones > * { flex: 1 1 auto; }
        .ds-im-filtros__acciones .btn { min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; }
    }

    @media print {
        .app-header, .app-sidebar, .app-footer, .ds-im-filtros, .ds-im-indice, .ds-im-no-imprimir { display: none !important; }
        .ds-im-bloque { break-inside: avoid-page; border-color: #999; }
        .ds-im-bloque__chevron { display: none; }
        .ds-im-escritorio { display: block !important; }
        .ds-im-telefono { display: none !important; }
        .ds-im-camps { display: none !important; }
        .ds-im-tabla thead, .ds-im-tabla tfoot { position: static; }
        .ds-im-tabla-caja, .ds-im-telefono__filas { max-height: none !important; overflow: visible !important; }
    }
</style>

<div class="ds-im">

<!-- ==================== Filtros ==================== -->
<?php /* GET: el mes y la sede quedan en la dirección (se puede guardar o
         compartir) y volver atrás no pide reenviar nada. Cambiarlos lo
         aplica solo; «Ver» queda para quien navega sin JavaScript. */ ?>
<form method="get" class="card mb-3 ds-im-filtros" id="imFiltros" aria-label="Mes y sede del reporte">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap align-items-end gap-2">
            <div class="ds-im-filtros__campo">
                <label for="imMes" class="form-label small text-body-secondary mb-1">Mes</label>
                <input type="month" id="imMes" name="mes" class="form-control"
                       value="<?php echo $h($mesValor); ?>" max="<?php echo date('Y-m'); ?>" required>
            </div>
            <div class="ds-im-filtros__campo">
                <label for="imSede" class="form-label small text-body-secondary mb-1">Sede</label>
                <select id="imSede" name="sede" class="form-select">
                    <option value="0"><?php echo count($sedes) > 1 ? 'Todas' : 'Mi sede'; ?></option>
                    <?php foreach ($sedes as $s): ?>
                        <option value="<?php echo (int) $s['id']; ?>"<?php echo (int) $s['id'] === $sedeSel ? ' selected' : ''; ?>><?php echo $h($s['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ds-im-filtros__acciones">
                <noscript><button type="submit" class="btn btn-primary">Ver</button></noscript>
                <?php if (!$esActual): ?>
                    <a href="<?php echo APP_URL; ?>ingresosMes/<?php echo $sedeSel > 0 ? '?sede=' . $sedeSel : ''; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Mes en curso</a>
                <?php endif; ?>
                <?php if ($hayDatos): ?>
                    <?php echo ds_botones_exportar('ingresosMes', 'ingresosMes', $pMes, $sedeSel > 0 ? ['sede' => $sedeSel] : [], ['xlsx', 'pdf']); ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="imImprimir">
                        <i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<h2 class="h5 mb-3">
    <?php echo $h($nombreMes); ?><span class="text-body-secondary fw-normal"> · <?php echo $h($nombreSede); ?></span>
    <?php if ($esActual): ?><span class="badge text-bg-secondary align-middle ms-1 fw-normal">mes en curso</span><?php endif; ?>
</h2>

<?php if (!$hayDatos): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="fas fa-cash-register fa-2x text-body-secondary mb-3" aria-hidden="true"></i>
            <p class="mb-1 fw-semibold">No hay cobros ni egresos registrados en <?php echo $h($nombreMes); ?>.</p>
            <p class="text-body-secondary small mb-0">Elija otro mes u otra sede.</p>
        </div>
    </div>
<?php else: ?>

<!-- ==================== Cifras del mes ==================== -->
<div class="ds-im-kpis">
    <div class="ds-im-kpi ds-im-kpi--total">
        <span class="ds-im-kpi__etq"><i class="fas fa-sack-dollar" aria-hidden="true"></i>Ingresos</span>
        <strong class="ds-im-kpi__cifra"><?php echo $dinero($r['total']); ?></strong>
        <span class="ds-im-kpi__nota"><?php echo number_format($r['cobros']); ?> cobros · <?php echo number_format($r['alumnos']); ?> alumnos</span>
    </div>
    <div class="ds-im-kpi ds-im-kpi--egreso">
        <span class="ds-im-kpi__etq"><i class="fas fa-arrow-trend-down text-danger" aria-hidden="true"></i>Egresos</span>
        <strong class="ds-im-kpi__cifra ds-im-egreso"><?php echo $dinero($r['egresosTotal']); ?></strong>
        <span class="ds-im-kpi__nota"><?php echo number_format($r['nEgresos']); ?> <?php echo $r['nEgresos'] === 1 ? 'egreso' : 'egresos'; ?></span>
    </div>
    <div class="ds-im-kpi ds-im-kpi--neto">
        <span class="ds-im-kpi__etq"><i class="fas fa-scale-balanced" aria-hidden="true"></i>Ingresos − egresos</span>
        <strong class="ds-im-kpi__cifra<?php echo $r['neto'] < 0 ? ' ds-im-neto-neg' : ''; ?>"><?php echo $dinero($r['neto']); ?></strong>
        <span class="ds-im-kpi__nota">neto del mes</span>
    </div>
</div>

<!-- Por columna: lo que entró, lo que salió y lo que queda en cada una -->
<div class="ds-im-columnas" aria-label="Por forma de pago">
    <?php foreach ($cols as $k => $etq):
        $in = $r['totales'][$k] ?? 0; $out = $r['egresosTotales'][$k] ?? 0; ?>
        <div class="ds-im-col">
            <div class="ds-im-col__etq"><span class="ds-im-punto ds-im-punto--<?php echo $tono($k); ?>" aria-hidden="true"></span><?php echo $h($etq); ?></div>
            <dl>
                <dt>Ingresos</dt><dd><?php echo $dinero($in); ?></dd>
                <dt>Egresos</dt><dd class="ds-im-egreso"><?php echo $out > 0 ? $dinero(-$out) : '—'; ?></dd>
                <dt class="ds-im-col__neto">Neto</dt><dd class="ds-im-col__neto<?php echo $in - $out < 0 ? ' ds-im-neto-neg' : ''; ?>"><?php echo $dinero($in - $out); ?></dd>
            </dl>
        </div>
    <?php endforeach; ?>
</div>

<!-- ==================== Índice ==================== -->
<nav class="ds-im-indice" aria-label="Ir a un bloque">
    <?php foreach ($r['bloques'] as $b): ?>
        <a href="#im-<?php echo $h($b['clave']); ?>" data-bloque="im-<?php echo $h($b['clave']); ?>">
            <small><?php echo $h($b['titulo'] . ($b['lugar'] !== null ? ' · ' . $b['lugar'] : '')); ?></small>
            <strong class="<?php echo $b['neto'] < 0 ? 'ds-im-neto-neg' : ''; ?>"><?php echo $dinero($b['neto']); ?></strong>
        </a>
    <?php endforeach; ?>
</nav>

<!-- ==================== Los bloques ==================== -->
<?php foreach ($r['bloques'] as $b):
    $n  = count($b['filas']);
    $ne = count($b['egresos']);
    $esEgr = $b['rubro'] === 'EGR';
    /* Campeonato: los cobros, agrupados por campeonato; el resto, una sola tanda. */
    $grupos = $b['grupos'] ?? [];
    $ngr = count($grupos);
    $segmentos = $ngr > 0 ? $grupos : [['nombre' => null, 'filas' => $b['filas'], 'formas' => [], 'total' => 0]]; ?>
    <details class="ds-im-bloque<?php echo $esEgr ? ' ds-im-bloque--egr' : ''; ?>" id="im-<?php echo $h($b['clave']); ?>" open>
        <summary>
            <span class="ds-im-bloque__icono" aria-hidden="true"><i class="fas <?php echo $ICONO[$b['rubro']] ?? 'fa-receipt'; ?>"></i></span>
            <h3 class="ds-im-bloque__titulo">
                <?php echo $h($b['titulo']); ?>
                <?php if ($b['lugar'] !== null): ?><span class="ds-im-bloque__lugar"><i class="fas fa-location-dot me-1" aria-hidden="true"></i><?php echo $h($b['lugar']); ?></span><?php endif; ?>
                <?php if ($esEgr): ?><span class="ds-im-bloque__lugar">Egresos sin bloque asignado en Fútbol › Balance › Egresos</span><?php endif; ?>
            </h3>
            <span class="ds-im-bloque__total">
                <strong class="<?php echo $b['neto'] < 0 ? 'ds-im-neto-neg' : ''; ?>"><?php echo $dinero($b['neto']); ?><i class="fas fa-chevron-down ds-im-bloque__chevron small" aria-hidden="true"></i></strong>
                <span><?php echo $ne > 0 ? 'neto' : ($n === 1 ? '1 cobro' : $n . ' cobros'); ?><?php if ($ngr > 0): ?> · <?php echo $ngr === 1 ? '1 campeonato' : $ngr . ' campeonatos'; ?><?php endif; ?></span>
            </span>
            <?php if ($b['total'] > 0): ?>
                <span class="ds-im-barra" aria-hidden="true">
                    <?php foreach ($cols as $k => $etq):
                        $v = $b['formas'][$k] ?? 0;
                        if ($v <= 0) { continue; } ?>
                        <span class="ds-im-punto--<?php echo $tono($k); ?>" style="width:<?php echo round($v / $b['total'] * 100, 2); ?>%"></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
            <?php if ($ne > 0): ?>
                <span class="ds-im-bloque__cuentas">
                    <?php if ($n > 0): ?><span>Ingresos <strong><?php echo $dinero($b['total']); ?></strong> <span class="text-body-secondary">(<?php echo $n; ?>)</span></span><?php endif; ?>
                    <span class="ds-im-egreso">Egresos <strong><?php echo $dinero(-$b['egresosTotal']); ?></strong> <span class="text-body-secondary">(<?php echo $ne; ?>)</span></span>
                </span>
            <?php else: ?>
                <span class="ds-im-bloque__reparto">
                    <?php foreach ($cols as $k => $etq):
                        $v = $b['formas'][$k] ?? 0;
                        if ($v <= 0) { continue; } ?>
                        <span><span class="ds-im-punto ds-im-punto--<?php echo $tono($k); ?>" aria-hidden="true"></span><?php echo $h($etq); ?> <?php echo $dinero($v); ?></span>
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </summary>

        <div class="ds-im-bloque__cuerpo">
            <!-- Escritorio: la tabla del reporte -->
            <div class="ds-im-escritorio d-none d-md-block">
                <?php if ($ngr > 1): ?>
                    <nav class="ds-im-camps" aria-label="Ir a un campeonato">
                        <?php foreach ($grupos as $gi => $g): ?>
                            <a href="#im-<?php echo $h($b['clave']); ?>-g<?php echo $gi; ?>"><span><?php echo $h($g['nombre']); ?></span><strong><?php echo $dinero($g['total']); ?></strong></a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
                <div class="ds-im-tabla-caja">
                    <table class="table table-sm table-hover ds-im-tabla">
                        <thead>
                            <tr>
                                <th scope="col">Sede</th>
                                <th scope="col">Identificación</th>
                                <th scope="col">Alumno / detalle</th>
                                <th scope="col">Fecha pago</th>
                                <th scope="col">F. registro</th>
                                <th scope="col">Periodo / concepto</th>
                                <th scope="col">Forma de pago</th>
                                <?php foreach ($cols as $k => $etq): ?>
                                    <th scope="col" class="num"><?php echo $h($etq); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <?php if ($n > 0): ?>
                            <tbody>
                                <?php if ($ne > 0): ?><tr><th colspan="<?php echo 7 + count($cols); ?>" class="ds-im-sub">Ingresos</th></tr><?php endif; ?>
                                <?php foreach ($segmentos as $gi => $g): ?>
                                <?php if ($g['nombre'] !== null):
                                    $ng = count($g['filas']); ?>
                                    <tr class="ds-im-grupo" id="im-<?php echo $h($b['clave']); ?>-g<?php echo $gi; ?>">
                                        <th colspan="7" scope="rowgroup"><i class="fas fa-trophy me-2" aria-hidden="true"></i><?php echo $h($g['nombre']); ?>
                                            <span class="ds-im-grupo__cuenta"><?php echo $ng === 1 ? '1 cobro' : $ng . ' cobros'; ?> · <?php echo $dinero($g['total']); ?></span></th>
                                        <?php foreach ($cols as $k => $etq): $vg = $g['formas'][$k] ?? 0; ?>
                                            <th class="num"><?php echo $vg > 0 ? $dinero($vg) : ''; ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($g['filas'] as $f): ?>
                                    <tr>
                                        <td><?php echo $h($f['sede']); ?></td>
                                        <td class="ds-im-tabla__ident"><?php echo $h($f['ident']); ?></td>
                                        <td class="ds-im-tabla__alumno"><?php echo $h($f['alumno']); ?><?php if ($f['tipo'] === 'abono'): ?> <span class="ds-im-abono" title="Abono a un pago con saldo pendiente">Abono</span><?php endif; ?></td>
                                        <td class="text-nowrap"><?php echo $fecha($f['fecha']); ?></td>
                                        <td class="text-nowrap<?php echo $f['registro'] !== $f['fecha'] ? ' text-body-secondary' : ''; ?>"><?php echo $fecha($f['registro']); ?></td>
                                        <td class="text-nowrap"><?php echo $h($f['periodo'] !== '' ? $f['periodo'] : '—'); ?></td>
                                        <td title="<?php echo $h($f['forma']); ?>"><span class="ds-im-forma"><span class="ds-im-punto ds-im-punto--<?php echo $tono($f['col']); ?>" aria-hidden="true"></span><?php echo $h($cols[$f['col']] ?? $f['forma']); ?></span></td>
                                        <?php foreach ($cols as $k => $etq): ?>
                                            <td class="num"><?php echo $k === $f['col'] ? $dinero($f['valor']) : ''; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endif; ?>
                        <?php if ($ne > 0): ?>
                            <tbody>
                                <tr><th colspan="<?php echo 7 + count($cols); ?>" class="ds-im-sub ds-im-sub--egreso">Egresos</th></tr>
                                <?php foreach ($b['egresos'] as $e): ?>
                                    <tr>
                                        <td><?php echo $h($e['sede']); ?></td>
                                        <td class="ds-im-tabla__ident">—</td>
                                        <td class="ds-im-tabla__alumno ds-im-tabla__detalle"><?php echo $h($e['detalle']); ?><?php if ($e['descripcion'] !== ''): ?><small><?php echo $h($e['descripcion']); ?></small><?php endif; ?></td>
                                        <td class="text-nowrap"><?php echo $fecha($e['fecha']); ?></td>
                                        <td></td>
                                        <td><?php echo $h($e['concepto']); ?></td>
                                        <td title="<?php echo $h($e['forma']); ?>"><span class="ds-im-forma"><span class="ds-im-punto ds-im-punto--<?php echo $tono($e['col']); ?>" aria-hidden="true"></span><?php echo $h($cols[$e['col']] ?? $e['forma']); ?></span></td>
                                        <?php foreach ($cols as $k => $etq): ?>
                                            <td class="num ds-im-egreso"><?php echo $k === $e['col'] ? $dinero(-$e['valor']) : ''; ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        <?php endif; ?>
                        <tfoot>
                            <?php if ($n > 0): ?>
                            <tr>
                                <td colspan="7">Total ingresos · <?php echo $dinero($b['total']); ?></td>
                                <?php foreach ($cols as $k => $etq): ?>
                                    <td class="num"><?php echo $dinero($b['formas'][$k] ?? 0); ?></td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endif; ?>
                            <?php if ($ne > 0): ?>
                                <tr>
                                    <td colspan="7" class="ds-im-egreso">Total egresos · <?php echo $dinero(-$b['egresosTotal']); ?></td>
                                    <?php foreach ($cols as $k => $etq): ?>
                                        <td class="num ds-im-egreso"><?php echo ($b['egresosFormas'][$k] ?? 0) > 0 ? $dinero(-$b['egresosFormas'][$k]) : '—'; ?></td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr<?php echo $n === 0 ? ' hidden' : ''; ?>>
                                    <td colspan="7">Ingresos − egresos · <span class="<?php echo $b['neto'] < 0 ? 'ds-im-neto-neg' : ''; ?>"><?php echo $dinero($b['neto']); ?></span></td>
                                    <?php foreach ($cols as $k => $etq):
                                        $nk = ($b['formas'][$k] ?? 0) - ($b['egresosFormas'][$k] ?? 0); ?>
                                        <td class="num<?php echo $nk < 0 ? ' ds-im-neto-neg' : ''; ?>"><?php echo $dinero($nk); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Teléfono: una ficha por movimiento -->
            <div class="ds-im-telefono d-md-none">
                <div class="ds-im-telefono__filas">
                <?php if ($n > 0): ?>
                    <?php if ($ne > 0): ?><div class="ds-im-sub">Ingresos</div><?php endif; ?>
                    <?php foreach ($segmentos as $gi => $g):
                        $ng = count($g['filas']); ?>
                    <?php if ($g['nombre'] !== null): ?>
                    <details class="ds-im-grupo-m"<?php echo $ngr === 1 ? ' open' : ''; ?>>
                        <summary>
                            <span class="ds-im-grupo-m__nombre"><i class="fas fa-trophy" aria-hidden="true"></i> <?php echo $h($g['nombre']); ?></span>
                            <strong class="ds-im-grupo-m__total"><?php echo $dinero($g['total']); ?><i class="fas fa-chevron-down ds-im-bloque__chevron small" aria-hidden="true"></i></strong>
                            <span class="ds-im-grupo-m__meta">
                                <span><?php echo $ng === 1 ? '1 cobro' : $ng . ' cobros'; ?></span>
                                <?php foreach ($cols as $k => $etq):
                                    $vg = $g['formas'][$k] ?? 0;
                                    if ($vg <= 0) { continue; } ?>
                                    <span><span class="ds-im-punto ds-im-punto--<?php echo $tono($k); ?>" aria-hidden="true"></span><?php echo $h($etq); ?> <?php echo $dinero($vg); ?></span>
                                <?php endforeach; ?>
                            </span>
                        </summary>
                    <?php endif; ?>
                    <ul class="ds-im-fichas">
                        <?php foreach ($g['filas'] as $f): ?>
                            <li class="ds-im-ficha">
                                <span class="ds-im-ficha__nombre"><?php echo $h($f['alumno']); ?><?php if ($f['tipo'] === 'abono'): ?> <span class="ds-im-abono">Abono</span><?php endif; ?></span>
                                <span class="ds-im-ficha__valor"><?php echo $dinero($f['valor']); ?></span>
                                <span class="ds-im-ficha__meta">
                                    <span class="ds-im-forma"><span class="ds-im-punto ds-im-punto--<?php echo $tono($f['col']); ?>" aria-hidden="true"></span><?php echo $h($cols[$f['col']] ?? $f['forma']); ?></span>
                                    <span><i class="far fa-calendar" aria-hidden="true"></i> <?php echo $fecha($f['fecha']); ?><?php if ($f['registro'] !== $f['fecha']): ?> <span title="Fecha de registro">(reg. <?php echo $fecha($f['registro']); ?>)</span><?php endif; ?></span>
                                    <?php if ($f['periodo'] !== ''): ?><span><i class="fas fa-tag" aria-hidden="true"></i> <?php echo $h($f['periodo']); ?></span><?php endif; ?>
                                    <span><i class="fas fa-id-card" aria-hidden="true"></i> <?php echo $h($f['ident']); ?></span>
                                    <?php if ($sedeSel === 0 && count($sedes) > 1 && $b['rubro'] !== 'RPE'): ?><span><i class="fas fa-building" aria-hidden="true"></i> <?php echo $h($f['sede']); ?></span><?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($g['nombre'] !== null): ?></details><?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($ne > 0): ?>
                    <div class="ds-im-sub ds-im-sub--egreso">Egresos</div>
                    <ul class="ds-im-fichas">
                        <?php foreach ($b['egresos'] as $e): ?>
                            <li class="ds-im-ficha">
                                <span class="ds-im-ficha__nombre"><?php echo $h($e['detalle']); ?></span>
                                <span class="ds-im-ficha__valor ds-im-egreso"><?php echo $dinero(-$e['valor']); ?></span>
                                <span class="ds-im-ficha__meta">
                                    <span class="ds-im-forma"><span class="ds-im-punto ds-im-punto--<?php echo $tono($e['col']); ?>" aria-hidden="true"></span><?php echo $h($cols[$e['col']] ?? $e['forma']); ?></span>
                                    <span><i class="far fa-calendar" aria-hidden="true"></i> <?php echo $fecha($e['fecha']); ?></span>
                                    <span><i class="fas fa-folder" aria-hidden="true"></i> <?php echo $h($e['concepto']); ?></span>
                                    <?php if ($e['descripcion'] !== ''): ?><span><?php echo $h($e['descripcion']); ?></span><?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                </div>
                <dl class="ds-im-cuadre m-0">
                    <?php if ($ne > 0 && $n === 0): ?>
                        <dt class="ds-im-cuadre__neto">Total egresos</dt><dd class="ds-im-cuadre__neto ds-im-neto-neg"><?php echo $dinero(-$b['egresosTotal']); ?></dd>
                    <?php elseif ($ne > 0): ?>
                        <dt>Ingresos</dt><dd><?php echo $dinero($b['total']); ?></dd>
                        <dt>Egresos</dt><dd class="ds-im-egreso"><?php echo $dinero(-$b['egresosTotal']); ?></dd>
                        <dt class="ds-im-cuadre__neto">Ingresos − egresos</dt><dd class="ds-im-cuadre__neto<?php echo $b['neto'] < 0 ? ' ds-im-neto-neg' : ''; ?>"><?php echo $dinero($b['neto']); ?></dd>
                    <?php else: ?>
                        <?php foreach ($cols as $k => $etq):
                            $v = $b['formas'][$k] ?? 0;
                            if ($v <= 0) { continue; } ?>
                            <dt><?php echo $h($etq); ?></dt><dd><?php echo $dinero($v); ?></dd>
                        <?php endforeach; ?>
                        <dt class="ds-im-cuadre__neto">Total</dt><dd class="ds-im-cuadre__neto"><?php echo $dinero($b['total']); ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </details>
<?php endforeach; ?>

<!-- ==================== Resumen del mes ==================== -->
<div class="card mt-3">
    <div class="card-header">
        <h3 class="card-title mb-0">Resumen de <?php echo $h($nombreMes); ?></h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 ds-im-resumen">
                <thead>
                    <tr>
                        <th scope="col">Bloque</th>
                        <th scope="col" class="num">Ingresos</th>
                        <th scope="col" class="num">Egresos</th>
                        <th scope="col" class="num">Neto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($r['bloques'] as $b): ?>
                        <tr>
                            <td><a href="#im-<?php echo $h($b['clave']); ?>" data-bloque="im-<?php echo $h($b['clave']); ?>" class="link-body-emphasis"><?php echo $h($b['titulo']); ?></a><?php if ($b['lugar'] !== null): ?><br><small class="text-body-secondary"><?php echo $h($b['lugar']); ?></small><?php endif; ?></td>
                            <td class="num"><?php echo $b['total'] > 0 ? $dinero($b['total']) : '<span class="text-body-secondary">—</span>'; ?></td>
                            <td class="num ds-im-egreso"><?php echo $b['egresosTotal'] > 0 ? $dinero(-$b['egresosTotal']) : '<span class="text-body-secondary">—</span>'; ?></td>
                            <td class="num fw-semibold<?php echo $b['neto'] < 0 ? ' ds-im-neto-neg' : ''; ?>"><?php echo $dinero($b['neto']); ?></td>
                        </tr>
                        <?php if (count($b['grupos'] ?? []) > 1): ?>
                            <?php foreach ($b['grupos'] as $g): ?>
                                <tr class="ds-im-resumen__sub">
                                    <td><?php echo $h($g['nombre']); ?></td>
                                    <td class="num"><?php echo $dinero($g['total']); ?></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total del mes</td>
                        <td class="num"><?php echo $dinero($r['total']); ?></td>
                        <td class="num ds-im-egreso"><?php echo $dinero(-$r['egresosTotal']); ?></td>
                        <td class="num<?php echo $r['neto'] < 0 ? ' ds-im-neto-neg' : ''; ?>"><?php echo $dinero($r['neto']); ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <div class="card-footer small text-body-secondary">
        <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
        Cada cobro cuenta en el mes de su <strong>fecha de pago</strong> y en la sede donde se cobró; los abonos, en su
        propia fecha. Los egresos son los de Fútbol › Balance › Egresos de ese mes, en el bloque que se les asignó.
        Las pensiones se separan por lugar de entrenamiento cuando la sede tiene más de uno.
    </div>
</div>

<?php endif; ?>
</div>

<script>
(function () {
    /* Cambiar el mes o la sede aplica el filtro. */
    var form = document.getElementById('imFiltros');
    if (form) {
        ['imMes', 'imSede'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) { el.addEventListener('change', function () { if (form.checkValidity()) { form.submit(); } }); }
        });
    }

    var bloques = [].slice.call(document.querySelectorAll('.ds-im-bloque'));

    /* En el teléfono sólo el primer bloque abierto: la lista entera sería
       un scroll interminable. En el escritorio, todos. */
    if (window.matchMedia('(max-width: 767.98px)').matches) {
        bloques.forEach(function (b, i) { if (i > 0) { b.open = false; } });
    }

    /* El índice y el resumen abren el bloque al que llevan. */
    document.querySelectorAll('[data-bloque]').forEach(function (a) {
        a.addEventListener('click', function () {
            var b = document.getElementById(a.getAttribute('data-bloque'));
            if (b) { b.open = true; }
        });
    });

    /* Imprimir con todo abierto, y dejarlo como estaba después. */
    var estado = [];
    window.addEventListener('beforeprint', function () {
        estado = bloques.map(function (b) { return b.open; });
        bloques.forEach(function (b) { b.open = true; });
    });
    window.addEventListener('afterprint', function () {
        bloques.forEach(function (b, i) { b.open = estado[i]; });
    });
    var imprimir = document.getElementById('imImprimir');
    if (imprimir) { imprimir.addEventListener('click', function () { window.print(); }); }
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
