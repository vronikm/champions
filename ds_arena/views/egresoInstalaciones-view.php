<?php
/*
| Arena › Egresos Instalaciones (migración 101).
|
| Los gastos de las instalaciones: luz, agua, mantenimiento de la cancha,
| arreglo del césped… con su fecha, la cancha (o general), el detalle y el
| valor. No se pregunta de dónde salió el dinero (2026-11-26).
| Arriba, lo que entró por reservas frente a estos egresos: la ganancia de
| las instalaciones en el período. La pantalla es inc/egresos-vista.php,
| común con los egresos del bar.
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Egresos Instalaciones';
$vistaActual = 'egresoInstalaciones';

require __DIR__ . '/inc/egresos-periodo.php';

$listo        = $pos->egresosListos() && $pos->egresosConAmbito();
$salidas      = $listo ? $pos->salidasPeriodo($desde, $hasta, 'I') : [];
$r            = $pos->resumenInstalaciones($desde, $hasta, $salidas);
$puedeCrear   = puede_crear('egresoInstalaciones') && $listo;
$puedeAnular  = puede_eliminar('egresoInstalaciones');
$categorias   = $pos->categoriasEgreso('I');
$sedes        = [];
$egInstalaciones = $pos->instalaciones();

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";

$egAmbito = 'I';
$egVista  = 'egresoInstalaciones';
$egIntro  = 'Registre los <strong>gastos de las instalaciones</strong>: luz, agua, mantenimiento de la cancha, arreglo del césped…'
          . ' Abajo, lo que entró por reservas frente a lo gastado: la <strong>ganancia</strong> del período.'
          . ' El total con el bar está en <a href="' . APP_URL . 'reporteGanancias/?desde=' . $desde . '&amp;hasta=' . $hasta . '">Reportes › Ganancias</a>.';
$egConcepto = 'Ej.: planilla de luz de noviembre, resiembra del césped';
$nSalidas = count(array_filter($salidas, static fn ($s) => !$s['anulado']));
$egKpis = [
    ['Ingresos por reservas', 'fa-calendar-check', $r['ingresos'], $r['nCobros'] . ' cobro(s)', 'pos-kpi--acento'],
    ['Egresos', 'fa-arrow-up', $r['egresos'], $nSalidas . ' egreso(s)', ''],
    ['Ganancia', 'fa-sack-dollar', $r['ganancia'], $r['ganancia'] < 0 ? 'Se gastó más de lo que entró' : 'Ingresos menos egresos',
     $r['ganancia'] < 0 ? 'pos-kpi--alerta' : 'pos-kpi--ok'],
];
$egTituloCuadro = 'Ingresos por reservas y egresos';
$egCuadro = [
    ['Cobros de reservas <small class="text-body-secondary">(' . $r['nCobros'] . ')</small>', $r['cobros'], '', ''],
    ['Depósitos al monedero', $r['depositos'], '', ''],
];
if ($r['devReservas'] > 0) { $egCuadro[] = ['Devoluciones de reservas', -$r['devReservas'], '', '']; }
if ($r['devMonedero'] > 0) { $egCuadro[] = ['Devoluciones del monedero', -$r['devMonedero'], '', '']; }
$egCuadro[] = ['Total ingresos', $r['ingresos'], 'eg-total', 'pos-pos'];
array_push($egCuadro,
    ['Total egresos', -$r['egresos'], 'eg-total', 'pos-neg'],
    ['Ganancia', $r['ganancia'], 'eg-resultado', $r['ganancia'] < 0 ? 'pos-neg' : 'pos-pos']);
$egNotaCuadro = 'Los ingresos son los de <a href="' . APP_URL . 'reportePagos/?desde=' . $desde . '&amp;hasta=' . $hasta . '">Reportes › Pagos Reservas</a>.'
              . ($r['monederoAplicado'] > 0 ? ' Además se pagaron ' . $posDinero($r['monederoAplicado']) . ' con monedero: ese dinero ya contó al depositarse.' : '');
$egPorCategoria   = $r['porCategoria'];
$egPorPersona     = [];
$egPorInstalacion = count($r['porInstalacion']) > 1 || !isset($r['porInstalacion']['General (todas)']) ? $r['porInstalacion'] : [];
$egPorDia = [];
foreach ($r['porDia'] as $f => $d) {
    $egPorDia[$f] = ['resultado' => round($d['ingresos'] - $d['egresos'], 2),
                     'meta' => ['Reservas ' . $posDinero($d['ingresos']), 'Egresos ' . $posDinero($d['egresos'])]];
}

require __DIR__ . '/inc/egresos-vista.php';
require_once __DIR__ . "/inc/layout-bottom.php";
