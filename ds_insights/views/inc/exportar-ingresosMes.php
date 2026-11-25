<?php
/*
|--------------------------------------------------------------------------
| Insights › Ingresos del mes — Excel, CSV y PDF con la forma del reporte
|--------------------------------------------------------------------------
| La escuela armaba este reporte a mano en Excel (2026-11-25). Su forma:
|
|   · un bloque por rubro (campeonatos, inscripciones, kits, uniformes,
|     pensiones por sede y lugar), con su título «ESCUELA | REPORTE DE PAGO
|     DE …»;
|   · columnas Sede, Identificación, Nombres y apellidos, Fecha pago,
|     F. registro, Periodo, Rubro y F. pago, y luego UNA columna por forma
|     de pago (efectivo, Banco de Loja, Banco Pichincha), con el importe en
|     la de su forma;
|   · la franja amarilla «EGRESO» con lo gastado en ese bloque;
|   · la fila de totales por columna y el recuadro TOTAL INGRESOS / TOTAL
|     EGRESOS / TOTAL INGRESO MES (en naranja);
|   · al final, el «REPORTE GENERAL MES».
|
| Lo incluye exportar-view.php DESPUÉS de comprobar el permiso y auditar;
| recibe $datos (con $datos['informe'] de datosExportables()), $formato y
| $nombreArchivo. Las cifras son las de la pantalla: salen del mismo
| insightsController::ingresosDelMes().
|
| Diferencias con el Excel, a propósito:
|   · los egresos van en POSITIVO dentro de su franja «EGRESO», como en el
|     Excel, y los totales los restan;
|   · el campeonato agrupa sus cobros por campeonato, con su subtotal;
|   · el PDF repite el título del bloque y las cabeceras al cambiar de
|     página, y numera las páginas.
*/

$inf  = $datos['informe'];
$r    = $inf['r'];
$cols = $r['columnas'];                     /* clave => «Banco de Loja» */
$org  = trim((string) $inf['organizacion']);
$MES  = mb_strtoupper($inf['mes'], 'UTF-8'); /* «OCTUBRE 2026» */
$marca = $org !== '' ? $org . ($inf['sedeId'] > 0 ? ' ' . $inf['sede'] : '') . ' | ' : '';

$CABECERAS = ['Sede', 'Identificación', 'Nombres y apellidos', 'Fecha pago', 'F. registro',
              'Periodo', 'Rubro', 'F. pago'];
$NT = count($CABECERAS);                    /* columnas de texto */

$dia = static fn(string $f): string => $f !== '' && !str_starts_with($f, '0000') ? date('d/m/Y', strtotime($f)) : '';

/*
| Los bloques, ya en filas de la tabla del reporte. Lo usan el CSV y el PDF:
| así los dos dicen exactamente lo mismo.
*/
$secciones = [];
foreach ($r['bloques'] as $b) {
    $titulo = $b['rubro'] === 'EGR'
        ? 'EGRESOS GENERALES'
        : 'REPORTE DE PAGO DE ' . mb_strtoupper($b['titulo'] . ($b['lugar'] !== null ? ' · ' . $b['lugar'] : ''), 'UTF-8');

    $fila = static fn(array $f): array => [
        'texto' => [$f['sede'], $f['ident'], $f['alumno'], $dia($f['fecha']), $dia($f['registro']),
                    $f['periodo'], $f['rubro'] . ($f['tipo'] === 'abono' ? ' (abono)' : ''), $f['forma']],
        'col'   => $f['col'],
        'valor' => $f['valor'],
        'camp'  => $f['campeonato'],
    ];
    $grupos = [];
    if (($b['grupos'] ?? []) !== []) {
        foreach ($b['grupos'] as $g) {
            $grupos[] = ['nombre' => $g['nombre'], 'formas' => $g['formas'], 'total' => $g['total'],
                         'filas' => array_map($fila, $g['filas'])];
        }
    } elseif ($b['filas'] !== []) {
        $grupos[] = ['nombre' => null, 'formas' => [], 'total' => 0, 'filas' => array_map($fila, $b['filas'])];
    }

    $egresos = [];
    foreach ($b['egresos'] as $e) {
        $egresos[] = [
            'texto' => [$e['sede'], '', $e['detalle'] . ($e['descripcion'] !== '' ? ' · ' . $e['descripcion'] : ''),
                        $dia($e['fecha']), '', '', $e['concepto'], $e['forma']],
            'col'   => $e['col'],
            'valor' => $e['valor'],
        ];
    }

    $secciones[] = [
        'titulo'  => $titulo,
        'corto'   => $b['titulo'] . ($b['lugar'] !== null ? ' · ' . $b['lugar'] : ''),
        'grupos'  => $grupos,
        'egresos' => $egresos,
        'n'       => count($b['filas']),
        'ne'      => count($b['egresos']),
        'formas'  => $b['formas'],
        'egresosFormas' => $b['egresosFormas'],
        'total'   => $b['total'],
        'egresosTotal' => $b['egresosTotal'],
        'neto'    => $b['neto'],
    ];
}

/*==============================================================
| CSV — la misma tabla, bloque tras bloque
|==============================================================
| «;» y coma decimal, sin separador de miles: es lo que Excel espera en
| es-EC (ver exportar-view.php). Cada bloque lleva su título, sus
| cabeceras, sus filas, la franja EGRESO y sus totales; al final, el
| reporte general. Las filas de detalle llevan el campeonato en el rubro
| para que, filtrando en Excel, cada una se entienda sola.
*/
if ($formato === 'csv') {
    $num   = static fn(float $v): string => number_format($v, 2, ',', '');
    $ancho = $NT + count($cols);
    $linea = static function (array $celdas) use ($ancho): array {
        return array_pad($celdas, $ancho, '');
    };
    /* Una fila de totales: la etiqueta en «F. pago» y una cifra por columna. */
    $totales = static function (string $etiqueta, array $formas) use ($cols, $NT, $num): array {
        $f = array_fill(0, $NT - 1, '');
        $f[] = $etiqueta;
        foreach ($cols as $k => $etq) { $f[] = $num((float) ($formas[$k] ?? 0)); }
        return $f;
    };
    /* El recuadro del bloque: la etiqueta en «F. pago», la cifra en la última columna. */
    $recuadro = static function (string $etiqueta, float $v) use ($cols, $NT, $num): array {
        $f = array_fill(0, $NT - 1, '');
        $f[] = $etiqueta;
        $f = array_merge($f, array_fill(0, max(0, count($cols) - 1), ''));
        $f[] = $num($v);
        return $f;
    };

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    $put = static function (array $f) use ($out): void { fputcsv($out, $f, ';', '"', '\\'); };

    $put([$marca . 'INGRESOS Y EGRESOS DEL MES']);
    $put(['Mes', $inf['mes']]);
    $put(['Sede', $inf['sede']]);
    $put(['Cobros', (string) $r['cobros']]);
    $put(['Egresos', (string) $r['nEgresos']]);
    $put(['Generado', date('d/m/Y H:i')]);
    $put(['Usuario', ds_nombre_usuario()]);

    $cabeceras = array_merge($CABECERAS, array_map(static fn($e) => mb_strtoupper($e, 'UTF-8'), array_values($cols)));
    foreach ($secciones as $s) {
        $put([]);
        $put($linea([$marca . $s['titulo']]));
        $put($cabeceras);
        foreach ($s['grupos'] as $g) {
            if ($g['nombre'] !== null) {
                $f = ['CAMPEONATO: ' . $g['nombre'], '', count($g['filas']) . (count($g['filas']) === 1 ? ' cobro' : ' cobros')];
                $f = array_pad($f, $NT, '');
                foreach ($cols as $k => $etq) { $f[] = isset($g['formas'][$k]) ? $num($g['formas'][$k]) : ''; }
                $put($f);
            }
            foreach ($g['filas'] as $f) {
                $t = $f['texto'];
                if ($f['camp'] !== '') { $t[6] .= ' · ' . $f['camp']; }
                foreach ($cols as $k => $etq) { $t[] = $k === $f['col'] ? $num($f['valor']) : ''; }
                $put($t);
            }
        }
        if ($s['egresos'] !== []) {
            $put($linea(['EGRESO']));
            foreach ($s['egresos'] as $e) {
                $t = $e['texto'];
                foreach ($cols as $k => $etq) { $t[] = $k === $e['col'] ? $num($e['valor']) : ''; }
                $put($t);
            }
        }
        if ($s['n'] > 0)  { $put($totales('TOTAL INGRESOS', $s['formas'])); }
        if ($s['ne'] > 0) { $put($totales('TOTAL EGRESOS', $s['egresosFormas'])); }
        $put($recuadro('TOTAL INGRESOS', $s['total']));
        $put($recuadro('TOTAL EGRESOS', $s['egresosTotal']));
        $put($recuadro('TOTAL INGRESO MES ' . $MES, $s['neto']));
    }

    /* El reporte general */
    $put([]);
    $put([$marca . 'REPORTE GENERAL MES ' . $MES]);
    $put(['Detalle', 'Ingresos', 'Egresos', 'Neto']);
    foreach ($secciones as $s) {
        $put([$s['corto'], $num($s['total']), $num($s['egresosTotal']), $num($s['neto'])]);
    }
    $put([]);
    $put(['Forma de pago', 'Ingresos', 'Egresos', 'Neto']);
    foreach ($cols as $k => $etq) {
        $in = (float) ($r['totales'][$k] ?? 0); $eg = (float) ($r['egresosTotales'][$k] ?? 0);
        $put([$etq, $num($in), $num($eg), $num($in - $eg)]);
    }
    $put([]);
    $put(['MONTO TOTAL DE INGRESOS', $num($r['total'])]);
    $put(['MONTO TOTAL EGRESOS', $num($r['egresosTotal'])]);
    $put(['TOTAL: INGRESOS - EGRESOS', $num($r['neto'])]);

    fclose($out);
    exit();
}

/*==============================================================
| EXCEL (.xlsx) — la misma hoja que el PDF, con colores y logo
|==============================================================
| Es el formato que ofrece la pantalla (2026-11-25): la escuela trabaja en
| Excel. Los importes y las fechas van como números y fechas de verdad,
| así que se pueden sumar y filtrar; la hoja queda lista para imprimir
| (apaisada, ajustada al ancho, «Página X de N»). Ver inc/xlsx.php.
*/
if ($formato === 'xlsx') {
    require_once __DIR__ . '/xlsx.php';

    $nm   = count($cols);
    $ULT  = $NT + $nm - 1;                   /* última columna */
    $x    = new DsXlsx($inf['mes']);
    $x->anchos(array_merge([13, 13, 36, 11.5, 11.5, 14, 22, 28], array_fill(0, $nm, 15.5)));
    $x->impresion(true, 'Ingresos del mes ' . $inf['mes'],
                  'Generado el ' . date('d/m/Y H:i') . ' por ' . ds_nombre_usuario() . ' · DigiSports Insights');

    $B = ['borde' => true, 'tam' => 9];      /* celda de tabla */
    $GRIS = 'D9D9D9'; $GRIS_CLARO = 'F2F2F2'; $AMARILLO = 'FFF200'; $NARANJA = 'F4B084'; $VERDE = 'E2EFDA'; $CEBRA = 'F7F7F7';
    $serie = static function (string $dmy): ?int {
        $d = \DateTime::createFromFormat('!d/m/Y', $dmy);
        return $d ? DsXlsx::serie($d->format('Y-m-d')) : null;
    };

    /*----------  Cabecera: logo, título y contexto  ----------*/
    $logo = function_exists('ds_logo_ruta') ? ds_logo_ruta((int) $inf['sedeId']) : '';
    $conLogo = $x->imagen($logo, 0, 0, 62, 52);
    $c0 = $conLogo ? 1 : 0;
    $x->alto(0, 24); $x->alto(1, 17); $x->alto(2, 15);
    $x->celda(0, $c0, ($org !== '' ? $org . ' | ' : '') . 'Ingresos y egresos del mes', ['b' => true, 'tam' => 16, 'color' => '1F2937']);
    $x->celda(1, $c0, $inf['mes'] . ' · ' . $inf['sede'] . ' · ' . number_format($r['cobros']) . ' cobros · '
              . number_format($r['nEgresos']) . ($r['nEgresos'] === 1 ? ' egreso' : ' egresos'), ['tam' => 11, 'color' => '595959']);
    $x->celda(2, $c0, 'Generado el ' . date('d/m/Y H:i') . ' por ' . ds_nombre_usuario(), ['tam' => 9, 'i' => true, 'color' => '7F7F7F']);
    $x->combinar(3, 0, 3, $ULT, ['borde' => false]);
    $x->alto(3, 8);

    /*----------  Las cifras del mes, en tarjetas  ----------*/
    $f = 4;
    /* Una franja continua: cada tarjeta ocupa sus columnas y la nota va
       en dos líneas si no cabe (así no se sale del ancho de la hoja). */
    $x->alto($f, 15); $x->alto($f + 1, 24); $x->alto($f + 2, 22);
    $tarjeta = static function (int $c1, int $c2, string $etq, $valor, string $nota, string $color, string $fmt = 'usd') use ($x, $f): void {
        $fondo = ['fondo' => 'F7F7F7', 'h' => 'left', 'v' => 'top'];
        $x->combinar($f, $c1, $f, $c2, ['tam' => 8, 'color' => '595959'] + $fondo);
        $x->celda($f, $c1, mb_strtoupper($etq, 'UTF-8'), ['tam' => 8, 'color' => '595959'] + $fondo);
        $x->combinar($f + 1, $c1, $f + 1, $c2, ['b' => true, 'tam' => 14, 'color' => $color, 'fmt' => $fmt] + $fondo);
        $x->celda($f + 1, $c1, $valor, ['b' => true, 'tam' => 14, 'color' => $color, 'fmt' => $fmt] + $fondo);
        $x->combinar($f + 2, $c1, $f + 2, $c2, ['tam' => 8, 'color' => '7F7F7F', 'ajustar' => true] + $fondo);
        $x->celda($f + 2, $c1, $nota, ['tam' => 8, 'color' => '7F7F7F', 'ajustar' => true] + $fondo);
    };
    $tarjeta(0, 1, 'Ingresos', round($r['total'], 2), number_format($r['cobros']) . ' cobros · ' . number_format($r['alumnos']) . ' alumnos', '15803D');
    $tarjeta(2, 2, 'Egresos', round($r['egresosTotal'], 2), number_format($r['nEgresos']) . ($r['nEgresos'] === 1 ? ' egreso' : ' egresos'), 'B91C1C');
    $tarjeta(3, 5, 'Ingresos − egresos', round($r['neto'], 2), 'neto del mes', '1F2937');
    $tarjeta(6, 7, 'Movimientos', $r['cobros'] + $r['nEgresos'], number_format($r['cobros']) . ' cobros y ' . number_format($r['nEgresos'])
             . ($r['nEgresos'] === 1 ? ' egreso' : ' egresos') . '; cada importe en la columna de su forma de pago', '404040', 'entero');
    $i = $NT;
    foreach ($cols as $k => $etq) {
        $in = (float) ($r['totales'][$k] ?? 0); $eg = (float) ($r['egresosTotales'][$k] ?? 0);
        $tarjeta($i, $i, $etq, round($in - $eg, 2), 'entró ' . number_format($in, 2) . ($eg > 0 ? "\nsalió " . number_format($eg, 2) : ''), '404040');
        $i++;
    }
    $f += 4;

    /*----------  Los bloques  ----------*/
    $cabeceras = array_merge($CABECERAS, array_map(static fn($e) => mb_strtoupper($e, 'UTF-8'), array_values($cols)));
    $importes = static function (int $fila, array $porColumna, array $estilo) use ($x, $cols, $NT): void {
        $i = $NT;
        foreach ($cols as $k => $etq) {
            $x->celda($fila, $i++, array_key_exists($k, $porColumna) ? round((float) $porColumna[$k], 2) : null, $estilo + ['fmt' => 'dinero', 'h' => 'right']);
        }
    };
    $detalle = static function (int $fila, array $mov, bool $cebra) use ($x, $importes, $B, $serie, $CEBRA): void {
        $e = $B + ($cebra ? ['fondo' => $CEBRA] : []);
        foreach ($mov['texto'] as $i => $t) {
            if (($i === 3 || $i === 4) && ($s = $serie((string) $t)) !== null) {
                $x->celda($fila, $i, $s, $e + ['fmt' => 'fecha', 'h' => 'center']);
            } else {
                $x->celda($fila, $i, (string) $t, $e + ($i === 1 ? ['h' => 'center'] : []));
            }
        }
        $importes($fila, [$mov['col'] => $mov['valor']], $e);
    };

    foreach ($secciones as $s) {
        $x->alto($f, 20);
        $x->combinar($f, 0, $f, $ULT, ['b' => true, 'tam' => 11, 'fondo' => $VERDE, 'borde' => true, 'h' => 'center']);
        $x->celda($f, 0, $marca . $s['titulo'], ['b' => true, 'tam' => 11, 'fondo' => $VERDE, 'borde' => true, 'h' => 'center']);
        $f++;
        $x->alto($f, 18);
        foreach ($cabeceras as $i => $c) {
            $x->celda($f, $i, $c, ['b' => true, 'tam' => 9, 'fondo' => $GRIS, 'borde' => true, 'h' => 'center', 'ajustar' => true]);
        }
        $f++;

        $cebra = false;
        foreach ($s['grupos'] as $g) {
            if ($g['nombre'] !== null) {
                $n = count($g['filas']);
                $eg = ['b' => true, 'tam' => 9, 'fondo' => $GRIS_CLARO, 'borde' => true];
                $x->combinar($f, 0, $f, $NT - 1, $eg + ['h' => 'left']);
                $x->celda($f, 0, 'Campeonato: ' . $g['nombre'] . '  (' . $n . ($n === 1 ? ' cobro)' : ' cobros)'), $eg + ['h' => 'left']);
                $importes($f, $g['formas'], $eg);
                $f++;
            }
            foreach ($g['filas'] as $m) {
                $detalle($f++, $m, $cebra);
                $cebra = !$cebra;
            }
        }
        if ($s['egresos'] !== []) {
            $ey = ['b' => true, 'tam' => 9, 'fondo' => $AMARILLO, 'borde' => true, 'h' => 'left'];
            $x->combinar($f, 0, $f, $ULT, $ey);
            $x->celda($f++, 0, 'EGRESO', $ey);
            foreach ($s['egresos'] as $m) { $detalle($f++, $m, false); }
        }

        /* Totales por columna. */
        $et = ['b' => true, 'tam' => 9, 'fondo' => $GRIS, 'borde' => true];
        foreach ([['TOTAL INGRESOS', $s['formas'], $s['n'] > 0], ['TOTAL EGRESOS', $s['egresosFormas'], $s['ne'] > 0]] as [$etq, $formas, $mostrar]) {
            if (!$mostrar) { continue; }
            $x->combinar($f, 0, $f, $NT - 1, $et + ['h' => 'right']);
            $x->celda($f, 0, $etq, $et + ['h' => 'right']);
            $importes($f, $formas + array_fill_keys(array_keys($cols), 0), $et);
            $f++;
        }

        /* El recuadro del bloque, bajo las columnas de dinero. */
        $f++;
        $c1 = max(0, $ULT - 3);
        foreach ([['TOTAL INGRESOS', $s['total'], null], ['TOTAL EGRESOS', $s['egresosTotal'], null],
                  ['TOTAL INGRESO MES ' . $MES, $s['neto'], $NARANJA]] as [$etq, $v, $color]) {
            $er = ['b' => true, 'tam' => 9, 'borde' => true] + ($color ? ['fondo' => $color] : []);
            $x->combinar($f, $c1, $f, $ULT - 1, $er + ['h' => 'right']);
            $x->celda($f, $c1, $etq, $er + ['h' => 'right']);
            $x->celda($f, $ULT, round($v, 2), $er + ['fmt' => 'usd', 'h' => 'right', 'tam' => 10]);
            $f++;
        }
        $f += 2;
    }

    /*----------  El reporte general  ----------*/
    $x->alto($f, 22);
    $x->combinar($f, 0, $f, $ULT, ['b' => true, 'tam' => 13, 'h' => 'center']);
    $x->celda($f++, 0, ($marca !== '' ? $marca : '') . 'REPORTE GENERAL MES ' . $MES, ['b' => true, 'tam' => 13, 'h' => 'center']);
    $f++;

    /* Dos tablas: por bloque (A:F) y por forma de pago (H:…), lado a lado. */
    $tabla = static function (int $f, int $c, array $cab, array $filas, array $pie) use ($x, $GRIS, $NARANJA, $B): int {
        $eh = ['b' => true, 'tam' => 9, 'fondo' => $GRIS, 'borde' => true];
        $span = $c === 0 ? 2 : 0;            /* el detalle ocupa A:C */
        $x->combinar($f, $c, $f, $c + $span, $eh + ['h' => 'left']);
        foreach ($cab as $i => $t) {
            $x->celda($f, $i === 0 ? $c : $c + $span + $i, $t, $eh + ['h' => $i === 0 ? 'left' : 'right']);
        }
        $f++;
        foreach ($filas as $fila) {
            $x->combinar($f, $c, $f, $c + $span, $B);
            foreach ($fila as $i => $v) {
                $x->celda($f, $i === 0 ? $c : $c + $span + $i, $v, $B + ($i === 0 ? [] : ['fmt' => 'dinero', 'h' => 'right']));
            }
            $f++;
        }
        foreach ($pie as $j => $fila) {
            $ep = ['b' => true, 'tam' => 9, 'borde' => true, 'fondo' => $j === count($pie) - 1 ? $NARANJA : $GRIS];
            $x->combinar($f, $c, $f, $c + $span, $ep);
            foreach ($fila as $i => $v) {
                $x->celda($f, $i === 0 ? $c : $c + $span + $i, $v, $ep + ($i === 0 ? [] : ['fmt' => 'usd', 'h' => 'right']));
            }
            $f++;
        }
        return $f;
    };
    $porBloque = array_map(static fn($s) => [$s['corto'], round($s['total'], 2), round($s['egresosTotal'], 2), round($s['neto'], 2)], $secciones);
    $fin1 = $tabla($f, 0, ['DETALLE', 'INGRESOS', 'EGRESOS', 'NETO'], $porBloque, [
        ['MONTO TOTAL DE INGRESOS', null, null, round($r['total'], 2)],
        ['MONTO TOTAL EGRESOS', null, null, round($r['egresosTotal'], 2)],
        ['TOTAL: INGRESOS - EGRESOS', null, null, round($r['neto'], 2)],
    ]);
    $porForma = [];
    foreach ($cols as $k => $etq) {
        $in = (float) ($r['totales'][$k] ?? 0); $eg = (float) ($r['egresosTotales'][$k] ?? 0);
        $porForma[] = [$etq, round($in, 2), round($eg, 2), round($in - $eg, 2)];
    }
    /* La segunda tabla, a la derecha si hay tres columnas de dinero donde ponerla; si no, debajo. */
    $fin2 = $nm >= 3
        ? $tabla($f, $NT - 1, ['FORMA DE PAGO', 'INGRESOS', 'EGRESOS', 'NETO'], $porForma,
                 [['TOTAL', round($r['total'], 2), round($r['egresosTotal'], 2), round($r['neto'], 2)]])
        : $tabla($fin1 + 1, 0, ['FORMA DE PAGO', 'INGRESOS', 'EGRESOS', 'NETO'], $porForma,
                 [['TOTAL', round($r['total'], 2), round($r['egresosTotal'], 2), round($r['neto'], 2)]]);
    $f = max($fin1, $fin2) + 1;

    $x->alto($f, 26);
    $x->combinar($f, 0, $f, $ULT, ['i' => true, 'tam' => 8, 'color' => '7F7F7F', 'ajustar' => true, 'v' => 'top', 'h' => 'left']);
    $x->celda($f, 0, 'Cada cobro cuenta en el mes de su fecha de pago y en la sede donde se cobró; los abonos, en su propia fecha. '
        . 'Los egresos son los de Fútbol › Balance › Egresos del mes, en el bloque que se les asignó. '
        . 'Cada importe va en la columna de su forma de pago.', ['i' => true, 'tam' => 8, 'color' => '7F7F7F', 'ajustar' => true, 'v' => 'top', 'h' => 'left']);

    $bin = $x->binario();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xlsx"');
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: no-store');
    echo $bin;
    exit();
}

/*==============================================================
| PDF — la hoja del Excel, en A4 apaisado
|==============================================================
*/
require_once DS_HUB_PATH . 'ds_futbol/app/lib/fpdf.php';

if (!class_exists('DsPdfIngresosMes')) {
    /** FPDF con la cabecera y el pie de página del reporte. */
    class DsPdfIngresosMes extends FPDF
    {
        public string $titulo = '';
        public string $subtitulo = '';
        public string $pieTexto = '';
        public int $sedeLogo = 0;

        /** UTF-8 → Windows-1252, que es lo que hablan las fuentes de FPDF. */
        public function t(string $s): string
        {
            $c = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            return is_string($c) ? $c : $s;
        }

        /** El texto recortado con «…» para que quepa en $ancho. */
        public function cabe(string $s, float $ancho): string
        {
            $s = $this->t($s);
            $max = $ancho - 2 * $this->cMargin;
            if ($this->GetStringWidth($s) <= $max) { return $s; }
            $p = chr(133);
            while ($s !== '' && $this->GetStringWidth($s . $p) > $max) { $s = substr($s, 0, -1); }
            return rtrim($s) . $p;
        }

        public function Header(): void
        {
            $x = $this->lMargin;
            $conLogo = function_exists('ds_logo_dibujar') && ds_logo_dibujar($this, $this->sedeLogo, $x, 6, 22, 12, 'L');
            $tx = $conLogo ? $x + 25 : $x;
            $this->SetXY($tx, 7);
            $this->SetFont('Helvetica', 'B', 12);
            $this->SetTextColor(20, 20, 20);
            $this->Cell(0, 5.5, $this->t($this->titulo), 0, 2, 'L');
            $this->SetFont('Helvetica', '', 8.5);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(0, 4.5, $this->t($this->subtitulo), 0, 0, 'L');
            $this->SetDrawColor(21, 128, 61);
            $this->SetLineWidth(0.5);
            $this->Line($x, 20, $this->GetPageWidth() - $this->rMargin, 20);
            $this->SetLineWidth(0.2);
            $this->SetDrawColor(0, 0, 0);
            $this->SetTextColor(0, 0, 0);
            $this->SetY(23);
        }

        public function Footer(): void
        {
            $this->SetY(-13);
            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(110, 110, 110);
            $this->Cell(0, 4, $this->t($this->pieTexto), 0, 0, 'L');
            $this->SetX($this->lMargin);
            $this->Cell(0, 4, $this->t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
            $this->SetTextColor(0, 0, 0);
        }
    }
}

$pdf = new DsPdfIngresosMes('L', 'mm', 'A4');
$pdf->titulo    = ($org !== '' ? $org . ' | ' : '') . 'Ingresos y egresos del mes';
$pdf->subtitulo = $inf['mes'] . ' · ' . $inf['sede'] . ' · ' . number_format($r['cobros']) . ' cobros · '
                . number_format($r['nEgresos']) . ($r['nEgresos'] === 1 ? ' egreso' : ' egresos');
$pdf->pieTexto  = 'Generado el ' . date('d/m/Y H:i') . ' por ' . ds_nombre_usuario() . ' · DigiSports Insights';
$pdf->sedeLogo  = (int) $inf['sedeId'];
$pdf->SetTitle($pdf->t('Ingresos del mes ' . $inf['mes']));
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AliasNbPages();
$pdf->AddPage();

$UTIL  = $pdf->GetPageWidth() - 20;
$LIMITE = $pdf->GetPageHeight() - 17;       /* donde empieza el pie */
$H     = 4.6;                                /* alto de fila */
$nm    = count($cols);
$wNum  = $nm <= 3 ? 24.0 : max(18.0, 96.0 / $nm);

/* En el PDF la forma de pago se abrevia («Transf. Banco de Loja»): la
   columna del importe ya dice el banco. El CSV la lleva completa. */
$abreviar = static fn(string $t): string => preg_replace('~^Transferencia(?=\s|$)~u', 'Transf.', $t);
foreach ($secciones as &$s) {
    foreach ($s['grupos'] as &$g) {
        foreach ($g['filas'] as &$f) { $f['texto'][7] = $abreviar((string) $f['texto'][7]); }
        unset($f);
    }
    unset($g);
    foreach ($s['egresos'] as &$e) { $e['texto'][7] = $abreviar((string) $e['texto'][7]); }
    unset($e);
}
unset($s);

/*
| Anchos de las columnas de texto, a la medida de lo que llevan: las fechas,
| la cédula y la sede enteras siempre; si no cabe todo, ceden nombre, rubro,
| forma de pago y, un poco, el periodo; el texto que no quepa se corta
| con «…».
*/

$pdf->SetFont('Helvetica', '', 7);
$MIN  = [10, 18.5, 34, 15.2, 15.2, 10, 14, 30.5];
$TOPE = [22, 20, 64, 15.2, 15.2, 26, 36, 40];
$W = $MIN;
foreach ($secciones as $s) {
    foreach (array_merge(array_merge(...array_map(static fn($g) => $g['filas'], $s['grupos']) ?: [[]]), $s['egresos']) as $f) {
        foreach ($f['texto'] as $i => $t) {
            $W[$i] = max($W[$i], min($TOPE[$i], $pdf->GetStringWidth($pdf->t((string) $t)) + 2.2));
        }
    }
}
$disponible = $UTIL - $nm * $wNum;
$sobra = $disponible - array_sum($W);
if ($sobra < 0) {
    /* La sede no cede y el periodo cede poco: son cortos y se leen enteros. */
    $PESO = [0, 0, 1, 0, 0, 0.5, 1, 1];
    $holgura = array_map(static fn($w, $m, $p) => ($w - $m) * $p, $W, $MIN, $PESO);
    $total = array_sum($holgura) ?: 1;
    foreach ($W as $i => $w) { $W[$i] = $w + $sobra * $holgura[$i] / $total; }
} else {
    $W[2] += $sobra;                         /* lo que sobra, al nombre */
}
$wTexto = array_sum($W);
$dec   = static fn(float $v): string => number_format($v, 2);
$usd   = static fn(float $v): string => ($v < 0 ? '-$' : '$') . number_format(abs($v), 2);

/* Colores del Excel de la escuela. */
$GRIS = [217, 217, 217]; $GRIS_CLARO = [242, 242, 242]; $AMARILLO = [255, 242, 0];
$NARANJA = [244, 176, 132]; $VERDE = [226, 239, 218]; $CEBRA = [250, 250, 250];
$relleno = static function (array $c) use ($pdf): void { $pdf->SetFillColor($c[0], $c[1], $c[2]); };

$queda = static fn(float $alto): bool => $pdf->GetY() + $alto <= $LIMITE;

/* Las cabeceras de la tabla. */
$cabeceras = static function () use ($pdf, $W, $wNum, $cols, $CABECERAS, $relleno, $GRIS): void {
    $pdf->SetFont('Helvetica', 'B', 6.3);
    $relleno($GRIS);
    foreach ($CABECERAS as $i => $c) { $pdf->Cell($W[$i], 5.6, $pdf->cabe($c, $W[$i]), 1, 0, 'C', true); }
    foreach ($cols as $etq) { $pdf->Cell($wNum, 5.6, $pdf->cabe(mb_strtoupper($etq, 'UTF-8'), $wNum), 1, 0, 'C', true); }
    $pdf->Ln();
};
/* El título del bloque, a todo lo ancho. */
$tituloBloque = static function (string $t, bool $sigue = false) use ($pdf, $UTIL, $relleno, $VERDE, $marca): void {
    $pdf->SetFont('Helvetica', 'B', 9);
    $relleno($VERDE);
    $pdf->Cell($UTIL, 6.4, $pdf->cabe($marca . $t . ($sigue ? ' (continúa)' : ''), $UTIL), 1, 1, 'C', true);
};
/* Antes de cada fila: si no cabe, página nueva con el título y las cabeceras. */
$fila = static function (callable $pintar, float $alto, string $titulo, ?callable $tras = null) use ($pdf, $queda, $tituloBloque, $cabeceras): void {
    if (!$queda($alto)) {
        $pdf->AddPage();
        $tituloBloque($titulo, true);
        $cabeceras();
        if ($tras) { $tras(); }
    }
    $pintar();
};
$importes = static function (array $formas, ?string $sola, float $valor, bool $fill, string $estilo = '') use ($pdf, $cols, $wNum, $dec, $H): void {
    $pdf->SetFont('Helvetica', $estilo, 7);
    foreach ($cols as $k => $etq) {
        $v = $sola !== null ? ($k === $sola ? $valor : null) : (isset($formas[$k]) ? (float) $formas[$k] : null);
        $pdf->Cell($wNum, $H, $v === null ? '' : $dec($v), 1, 0, 'R', $fill);
    }
    $pdf->Ln();
};

/*----------  Resumen del mes, arriba de la primera página  ----------*/
$cajas = [['Ingresos', $usd($r['total']), number_format($r['cobros']) . ' cobros · ' . number_format($r['alumnos']) . ' alumnos', [21, 128, 61]],
          ['Egresos', $usd($r['egresosTotal']), number_format($r['nEgresos']) . ($r['nEgresos'] === 1 ? ' egreso' : ' egresos'), [185, 28, 28]],
          ['Ingresos - egresos', $usd($r['neto']), 'neto del mes', [30, 30, 30]]];
foreach ($cols as $k => $etq) {
    $in = (float) ($r['totales'][$k] ?? 0); $eg = (float) ($r['egresosTotales'][$k] ?? 0);
    $cajas[] = [$etq, $usd($in - $eg), 'entró ' . $usd($in) . ($eg > 0 ? ' · salió ' . $usd($eg) : ''), [80, 80, 80]];
}
$sep  = 2.5;
$wCaja = ($UTIL - $sep * (count($cajas) - 1)) / count($cajas);
$y0 = $pdf->GetY();
foreach ($cajas as $i => [$etq, $cifra, $nota, $color]) {
    $x = 10 + $i * ($wCaja + $sep);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Rect($x, $y0, $wCaja, 15);
    $pdf->SetDrawColor($color[0], $color[1], $color[2]);
    $pdf->SetLineWidth(0.8);
    $pdf->Line($x, $y0, $x, $y0 + 15);
    $pdf->SetLineWidth(0.2);
    $pdf->SetXY($x + 1.5, $y0 + 1.2);
    $pdf->SetFont('Helvetica', '', 6.8); $pdf->SetTextColor(90, 90, 90);
    $pdf->Cell($wCaja - 3, 3.5, $pdf->cabe(mb_strtoupper($etq, 'UTF-8'), $wCaja - 3), 0, 2);
    $pdf->SetFont('Helvetica', 'B', 11); $pdf->SetTextColor($color[0], $color[1], $color[2]);
    $pdf->Cell($wCaja - 3, 5.5, $pdf->t($cifra), 0, 2);
    $pdf->SetFont('Helvetica', '', 6.3); $pdf->SetTextColor(110, 110, 110);
    $pdf->Cell($wCaja - 3, 3.2, $pdf->cabe($nota, $wCaja - 3), 0, 2);
}
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetY($y0 + 19);

/*----------  Los bloques  ----------*/
foreach ($secciones as $s) {
    /* Que el título no quede solo al pie: título, cabeceras y tres filas. */
    if (!$queda(6.4 + 5.6 + 3 * $H)) { $pdf->AddPage(); }
    $tituloBloque($s['titulo']);
    $cabeceras();

    /* La última fila baja de página junto con los totales y el recuadro:
       así el cierre del bloque nunca queda solo en una hoja. */
    $filasTot = ($s['n'] > 0 ? 1 : 0) + ($s['ne'] > 0 ? 1 : 0);
    $altoCierre = $filasTot * 5.2 + 3 * 5.2 + 1.5;
    $quedan = $s['n'] + count($s['egresos']);

    $cebra = false;
    foreach ($s['grupos'] as $g) {
        if ($g['nombre'] !== null) {
            $n = count($g['filas']);
            $fila(static function () use ($pdf, $g, $n, $wTexto, $relleno, $GRIS_CLARO, $importes, $H): void {
                $relleno($GRIS_CLARO);
                $pdf->SetFont('Helvetica', 'B', 7);
                $pdf->Cell($wTexto, $H, $pdf->cabe('Campeonato: ' . $g['nombre'] . '  (' . $n . ($n === 1 ? ' cobro)' : ' cobros)'), $wTexto), 1, 0, 'L', true);
                $importes($g['formas'], null, 0, true, 'B');
            }, $H, $s['titulo']);
        }
        foreach ($g['filas'] as $f) {
            $fila(static function () use ($pdf, $f, $W, $importes, $relleno, $CEBRA, $cebra, $H): void {
                $relleno($CEBRA);
                $pdf->SetFont('Helvetica', '', 7);
                foreach ($f['texto'] as $i => $t) {
                    $pdf->Cell($W[$i], $H, $pdf->cabe((string) $t, $W[$i]), 1, 0, in_array($i, [1, 3, 4], true) ? 'C' : 'L', $cebra);
                }
                $importes([], $f['col'], $f['valor'], $cebra);
            }, --$quedan === 0 ? $H + $altoCierre : $H, $s['titulo']);
            $cebra = !$cebra;
        }
    }

    if ($s['egresos'] !== []) {
        /* La franja baja de página con su primer egreso (y, si es el único,
           con el cierre del bloque); si la lista sigue en otra hoja, se
           repite arriba. */
        $franja = static function () use ($pdf, $UTIL, $relleno, $AMARILLO, $H): void {
            $relleno($AMARILLO);
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->Cell($UTIL, $H, 'EGRESO', 1, 1, 'L', true);
        };
        $fila($franja, 2 * $H + (count($s['egresos']) === 1 ? $altoCierre : 0), $s['titulo']);
        foreach ($s['egresos'] as $e) {
            $fila(static function () use ($pdf, $e, $W, $importes, $H): void {
                $pdf->SetFont('Helvetica', '', 7);
                foreach ($e['texto'] as $i => $t) {
                    $pdf->Cell($W[$i], $H, $pdf->cabe((string) $t, $W[$i]), 1, 0, in_array($i, [1, 3, 4], true) ? 'C' : 'L');
                }
                $importes([], $e['col'], $e['valor'], false);
            }, --$quedan === 0 ? $H + $altoCierre : $H, $s['titulo'], $franja);
        }
    }

    /* Totales por columna y el recuadro del bloque: juntos, sin partirse. */
    if (!$queda($altoCierre)) {
        $pdf->AddPage();
        $tituloBloque($s['titulo'], true);
        $cabeceras();
    }
    $relleno($GRIS);
    foreach ([['TOTAL INGRESOS', $s['formas'], $s['n'] > 0], ['TOTAL EGRESOS', $s['egresosFormas'], $s['ne'] > 0]] as [$etq, $formas, $mostrar]) {
        if (!$mostrar) { continue; }
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->Cell($wTexto, 5.2, $pdf->t($etq), 1, 0, 'R', true);
        foreach ($cols as $k => $x) { $pdf->Cell($wNum, 5.2, $dec((float) ($formas[$k] ?? 0)), 1, 0, 'R', true); }
        $pdf->Ln();
    }
    /* El recuadro: bajo las columnas de dinero, como en el Excel. */
    $pdf->Ln(1.5);
    $wEtq = max(52.0, $W[7] + $W[6]);
    $wVal = max(30.0, $wNum * 1.25);
    $x0   = 10 + $UTIL - $wEtq - $wVal;
    foreach ([['TOTAL INGRESOS', $s['total'], null], ['TOTAL EGRESOS', $s['egresosTotal'], null],
              ['TOTAL INGRESO MES ' . $MES, $s['neto'], $NARANJA]] as [$etq, $v, $color]) {
        $pdf->SetX($x0);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        if ($color) { $relleno($color); }
        $pdf->Cell($wEtq, 5.2, $pdf->cabe($etq, $wEtq), 1, 0, 'R', $color !== null);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->Cell($wVal, 5.2, $pdf->t($usd($v)), 1, 1, 'R', $color !== null);
    }
    $pdf->Ln(6);
}

/*----------  El reporte general  ----------*/
/* Dos tablas lado a lado: por bloque y por forma de pago, con el total. */
$nB = count($secciones);
$altoGeneral = 7 + 5.6 + ($nB + 4) * 5.2;
if (!$queda($altoGeneral)) { $pdf->AddPage(); }
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell($UTIL, 7, $pdf->t('REPORTE GENERAL MES ' . $MES), 0, 1, 'C');
$yT = $pdf->GetY();

$tabla = static function (float $x, array $anchos, array $cab, array $filas, array $pie) use ($pdf, $relleno, $GRIS, $NARANJA): void {
    $pdf->SetX($x);
    $pdf->SetFont('Helvetica', 'B', 7.2);
    $relleno($GRIS);
    foreach ($cab as $i => $c) { $pdf->Cell($anchos[$i], 5.6, $pdf->t($c), 1, 0, $i === 0 ? 'L' : 'R', true); }
    $pdf->Ln();
    $pdf->SetFont('Helvetica', '', 7.2);
    foreach ($filas as $f) {
        $pdf->SetX($x);
        foreach ($f as $i => $c) { $pdf->Cell($anchos[$i], 5.2, $pdf->cabe((string) $c, $anchos[$i]), 1, 0, $i === 0 ? 'L' : 'R'); }
        $pdf->Ln();
    }
    foreach ($pie as $j => $f) {
        $pdf->SetX($x);
        $ultima = $j === count($pie) - 1;
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $relleno($ultima ? $NARANJA : $GRIS);
        foreach ($f as $i => $c) { $pdf->Cell($anchos[$i], 5.4, $pdf->cabe((string) $c, $anchos[$i]), 1, 0, $i === 0 ? 'L' : 'R', true); }
        $pdf->Ln();
    }
};

$porBloque = array_map(static fn($s) => [$s['corto'], $dec($s['total']), $dec($s['egresosTotal']), $dec($s['neto'])], $secciones);
$tabla(10, [84, 24, 24, 24], ['DETALLE', 'INGRESOS', 'EGRESOS', 'NETO'], $porBloque, [
    ['MONTO TOTAL DE INGRESOS', '', '', $usd($r['total'])],
    ['MONTO TOTAL EGRESOS', '', '', $usd($r['egresosTotal'])],
    ['TOTAL: INGRESOS - EGRESOS', '', '', $usd($r['neto'])],
]);
$yFin = $pdf->GetY();

$porForma = [];
foreach ($cols as $k => $etq) {
    $in = (float) ($r['totales'][$k] ?? 0); $eg = (float) ($r['egresosTotales'][$k] ?? 0);
    $porForma[] = [$etq, $dec($in), $dec($eg), $dec($in - $eg)];
}
$pdf->SetY($yT);
$tabla(10 + 156 + 11, [41, 23, 23, 23],['FORMA DE PAGO', 'INGRESOS', 'EGRESOS', 'NETO'], $porForma, [
    ['TOTAL', $dec($r['total']), $dec($r['egresosTotal']), $usd($r['neto'])],
]);
$pdf->SetY(max($yFin, $pdf->GetY()) + 4);

$pdf->SetFont('Helvetica', 'I', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell($UTIL, 3.6, $pdf->t(
    'Cada cobro cuenta en el mes de su fecha de pago y en la sede donde se cobró; los abonos, en su propia fecha. '
  . 'Los egresos son los de Fútbol › Balance › Egresos del mes, en el bloque que se les asignó. '
  . 'Cada importe va en la columna de su forma de pago.'));
$pdf->SetTextColor(0, 0, 0);

$pdf->Output('D', $nombreArchivo . '.pdf');
exit();
