<?php
/*
| Arena › Punto de Venta y Caja › Egresos (migración 100).
|
| Los gastos del bar con su fecha, categoría, detalle y valor (desde el
| 2026-11-26 no se pregunta de dónde salió el dinero ni sale de la caja: el
| efectivo que sale de una caja se anota en Caja). Arriba, el resumen del período elegido (hoy,
| la semana, el mes…): lo vendido y los otros ingresos de caja frente a todo
| lo que salió, día por día y por categoría.
|
| La pantalla es inc/egresos-vista.php, común con Egresos Instalaciones.
*/

use arena\controllers\posController;

$insArena = $pos = new posController();

$tituloVista = 'Egresos';
$vistaActual = 'posEgresos';

require __DIR__ . '/inc/egresos-periodo.php';

$listo        = $pos->egresosListos();
$salidas      = $pos->salidasPeriodo($desde, $hasta, 'B');
$r            = $pos->resumenBar($desde, $hasta, $salidas);
$puedeCrear   = puede_crear('posEgresos') && $listo;
$puedeAnular  = puede_eliminar('posEgresos');
$categorias   = $pos->categoriasEgreso('B');
$sedes        = $pos->sedesCaja();
$egFormasPago = $pos->formasEgresoBar();   /* Banco de Loja o Banco Pichincha (103) */

require_once __DIR__ . "/inc/layout-top.php";
require_once __DIR__ . "/inc/pos-comun.php";

$egAmbito = 'B';
$egVista  = 'posEgresos';
$egIntro  = 'Registre los <strong>gastos del bar</strong>: el detalle, el valor y desde qué banco se transfirió.'
          . ' Abajo, lo vendido frente a lo gastado en el período. Los de las canchas van en «Egresos Instalaciones».';
$egConcepto = 'Ej.: 2 fundas de hielo, cilindro de gas';
$nSalidas = count(array_filter($salidas, static fn ($s) => !$s['anulado']));
$egKpis = [
    ['Vendido', 'fa-basket-shopping', $r['vendido'], ($r['nPos'] + $r['nReg']) . ' venta(s)', 'pos-kpi--acento'],
    ['Egresos', 'fa-arrow-up', $r['totalEgresos'], $nSalidas . ' salida(s)', ''],
    ['Resultado', 'fa-scale-balanced', $r['resultado'], $r['resultado'] < 0 ? 'Se gastó más de lo que entró' : 'Ingresos menos egresos',
     $r['resultado'] < 0 ? 'pos-kpi--alerta' : 'pos-kpi--ok'],
];
$egTituloCuadro = 'Ingresos y egresos';
$egCuadro = [
    ['Ventas en «Vender» <small class="text-body-secondary">(' . $r['nPos'] . ')</small>', $r['ventasPos'], '', ''],
    ['Ventas registradas <small class="text-body-secondary">(' . $r['nReg'] . ')</small>', $r['ventasReg'], '', ''],
    ['Otros ingresos de caja', $r['otrosIngresos'], '', ''],
    ['Total ingresos', $r['ingresos'], 'eg-total', 'pos-pos'],
    ['Egresos del bar', -$r['egresos'], '', ''],
];
/* De qué banco salieron los egresos del bar. */
if (count($r['porForma']) > 1 || !isset($r['porForma']['Sin indicar'])) {
    foreach ($r['porForma'] as $n => $v) { $egCuadro[] = [$posH($n), -$v, 'eg-sub', '']; }
}
array_push($egCuadro,
    ['Egresos anotados en la caja', -$r['egresosCaja'], '', ''],
    ['Pagos de compras', -$r['compras'], '', ''],
    ['Total egresos', -$r['totalEgresos'], 'eg-total', 'pos-neg'],
    ['Resultado', $r['resultado'], 'eg-resultado', $r['resultado'] < 0 ? 'pos-neg' : 'pos-pos']);
$egNotaCuadro = $r['costoVendido'] > 0
    ? 'Costo de lo vendido (según el costo de cada producto): ' . $posDinero($r['costoVendido']) . ' · margen ' . $posDinero($r['vendido'] - $r['costoVendido']) . '.'
    : '';
$egPorCategoria = $r['porCategoria'];
$egPorPersona   = [];
$egPorInstalacion = [];
$egPorDia = [];
foreach ($r['porDia'] as $f => $d) {
    $meta = ['Vendido ' . $posDinero($d['ventas'])];
    if ($d['ingresos'] > 0) { $meta[] = 'Otros ingresos ' . $posDinero($d['ingresos']); }
    $meta[] = 'Egresos ' . $posDinero($d['egresos']);
    $egPorDia[$f] = ['resultado' => round($d['ventas'] + $d['ingresos'] - $d['egresos'], 2), 'meta' => $meta];
}

require __DIR__ . '/inc/egresos-vista.php';
require_once __DIR__ . "/inc/layout-bottom.php";
