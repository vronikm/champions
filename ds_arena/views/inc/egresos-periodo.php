<?php
/*
| El período de las pantallas de egresos y de Reportes › Ganancias: desde y
| hasta de la URL (este mes por omisión, hasta 400 días) y los atajos.
| Deja: $hoy, $minimo, $desde, $hasta, $periodos, $periodoActivo.
*/

use arena\controllers\posController;

$hoy    = date('Y-m-d');
$minimo = date('Y-m-d', strtotime($hoy . ' -' . posController::REGISTRO_DIAS_ATRAS . ' days'));
$valida = static fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)
                            && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4)) ? $d : null;
$desde = $valida($_GET['desde'] ?? null) ?? date('Y-m-01');
$hasta = $valida($_GET['hasta'] ?? null) ?? $hoy;
if ($desde > $hasta) { [$desde, $hasta] = [$hasta, $desde]; }
if ($desde < date('Y-m-d', strtotime($hasta . ' -365 days'))) { $desde = date('Y-m-d', strtotime($hasta . ' -365 days')); }

$periodos = [
    'Hoy'          => [$hoy, $hoy],
    'Esta semana'  => [date('Y-m-d', strtotime('monday this week')), $hoy],
    'Este mes'     => [date('Y-m-01'), $hoy],
    'Mes anterior' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
    'Este año'     => [date('Y-01-01'), $hoy],
];
$periodoActivo = '';
foreach ($periodos as $n => [$a, $b]) { if ($a === $desde && $b === $hasta) { $periodoActivo = $n; } }
