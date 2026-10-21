<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Exportación
|--------------------------------------------------------------------------
| Entrega un reporte en CSV o PDF. No pinta nada: escribe una descarga.
|
|
| ES UNA VISTA Y NO UN ENDPOINT SUELTO, A PROPÓSITO
|
| Al pasar por el front controller hereda los cuatro controles de acceso ya
| probados: sesión, módulo, vista registrada y permiso de lectura. Un archivo
| aparte los tendría que repetir, y repetirlos es como se olvidan.
|
|
| EXPORTAR ES UNA ACCIÓN PROPIA
|
| Ver la cartera en pantalla y llevársela en un archivo son dos decisiones
| distintas: la segunda saca la información del sistema. Por eso se comprueba
| `puede_exportar()` sobre la vista del reporte, no `usuario_tiene_permiso()`.
| Es la acción que se añadió en la migración 045.
|
|
| LOS DATOS SALEN DE LAS MISMAS CONSULTAS QUE LA PANTALLA
|
| No hay una versión «para exportar». Si el tablero dice 33,5 %, el archivo
| dice 33,5 %, porque es la misma llamada al mismo método.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$reporte = isset($_GET['reporte']) ? (string) $_GET['reporte'] : '';
$formato = isset($_GET['formato']) ? strtolower((string) $_GET['formato']) : 'csv';
$p       = $insInsights->periodo($_GET['desde'] ?? null, $_GET['hasta'] ?? null);

if (!in_array($formato, ['csv', 'pdf'], true)) { $formato = 'csv'; }

$datos = $insInsights->datosExportables($reporte, $p);

/*----------  Nada que exportar  ----------*/
if ($datos === null) {
    http_response_code(404);
    $insInsights->auditar('EXPORTAR_' . strtoupper($formato), $reporte ?: '(vacío)',
        ['ok' => false, 'desde' => $p['desde'], 'hasta' => $p['hasta']]);
    require __DIR__ . '/accesoDenegado-view.php';
    exit();
}

/*----------  El permiso de exportar, que es propio  ----------*/
if (!puede_exportar($datos['vista'])) {
    http_response_code(403);
    /* Se registra el intento denegado: el §45 lo pide, y es justo el evento
       que interesa cuando alguien pregunta quién intentó llevarse qué. */
    $insInsights->auditar('EXPORTAR_' . strtoupper($formato), $reporte,
        ['ok' => false, 'desde' => $p['desde'], 'hasta' => $p['hasta']]);
    require __DIR__ . '/accesoDenegado-view.php';
    exit();
}

$insInsights->auditar('EXPORTAR_' . strtoupper($formato), $reporte, [
    'desde' => $p['desde'], 'hasta' => $p['hasta'], 'filas' => count($datos['filas']),
]);

/* Cabecera de contexto: sin ella, un archivo suelto en un correo no dice de
   qué periodo es ni quién lo sacó, y se interpreta mal meses después. */
$contexto = [
    ['Reporte',    $datos['titulo']],
    ['Sistema',    'DigiSports Insights'],
    ['Periodo',    $p['desde'] . ' a ' . $p['hasta']],
    ['Generado',   date('Y-m-d H:i')],
    ['Usuario',    ds_nombre_usuario()],
    ['Filas',      (string) count($datos['filas'])],
];

$nombreArchivo = 'insights_' . preg_replace('~[^a-z0-9]+~i', '_', $reporte)
               . '_' . date('Ymd_Hi');

/*==============================================================
| CSV
|==============================================================
| Con BOM UTF-8 y separador «;».
|
| El BOM es lo que hace que Excel en Windows abra las tildes bien: sin él
| interpreta el archivo como ANSI y «Ocupación» se convierte en «OcupaciÃ³n».
| Y el separador es «;» porque en la configuración regional de Ecuador la
| coma es el separador DECIMAL: con «,» Excel parte los importes en dos
| columnas.
|
| Se entrega CSV y no .xlsx porque un xlsx real necesitaría PhpSpreadsheet,
| que el proyecto no tiene. Conviene saber la diferencia: en CSV los tipos
| los adivina Excel al abrir; en xlsx irían declarados. Está anotado como
| decisión pendiente en MODELO_INSIGHTS.md.
*/
if ($formato === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
    header('Cache-Control: no-store');

    $salida = fopen('php://output', 'w');
    fwrite($salida, "\xEF\xBB\xBF");            /* BOM */

    foreach ($contexto as $linea) { fputcsv($salida, $linea, ';', '"', '\\'); }
    fputcsv($salida, [], ';', '"', '\\');

    fputcsv($salida, $datos['cabeceras'], ';', '"', '\\');

    foreach ($datos['filas'] as $fila) {
        /* Los decimales van con coma: es lo que espera Excel en es-EC, y el
           separador de columna ya es «;», así que no hay ambigüedad. */
        $limpia = [];
        foreach ($fila as $i => $v) {
            $tipo = $datos['tipos'][$i] ?? 'texto';
            $limpia[] = in_array($tipo, ['dinero', 'decimal'], true) && $v !== ''
                ? str_replace('.', ',', (string) $v)
                : $v;
        }
        fputcsv($salida, $limpia, ';', '"', '\\');
    }

    fclose($salida);
    exit();
}

/*==============================================================
| PDF
|==============================================================
| Con FPDF 1.86, que el proyecto ya usa para las facturas y los listados de
| alumnos. No se añade dependencia.
|
| FPDF trabaja en ISO-8859-1, así que todo texto pasa por una conversión: sin
| ella las tildes salen como signos de interrogación, que es el defecto
| clásico de FPDF y no avisa de ninguna manera.
*/
require_once DS_HUB_PATH . 'ds_futbol/app/lib/fpdf.php';

/** FPDF habla latin1; el sistema entero habla UTF-8. */
function ds_pdf(string $t): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $t) ?: $t;
}

$pdf = new FPDF('L', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 14);
$pdf->AddPage();

/* Cabecera */
$pdf->SetFont('Helvetica', 'B', 14);
$pdf->Cell(0, 8, ds_pdf($datos['titulo']), 0, 1);

$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(90, 90, 90);
foreach ($contexto as [$etiqueta, $valor]) {
    if ($etiqueta === 'Reporte') { continue; }
    $pdf->Cell(0, 4.5, ds_pdf($etiqueta . ': ' . $valor), 0, 1);
}
$pdf->Ln(3);
$pdf->SetTextColor(0, 0, 0);

/* Anchos proporcionales al contenido, dentro del ancho útil. */
$util   = $pdf->GetPageWidth() - 20;
$cols   = count($datos['cabeceras']);
$anchos = [];
foreach ($datos['cabeceras'] as $i => $h) {
    $largo = strlen((string) $h);
    foreach ($datos['filas'] as $f) {
        $largo = max($largo, strlen((string) ($f[$i] ?? '')));
    }
    $anchos[$i] = $largo;
}
$suma = array_sum($anchos) ?: 1;
foreach ($anchos as $i => $a) { $anchos[$i] = max(16, $util * $a / $suma); }
/* Reajuste para que la suma no exceda el ancho útil. */
$exceso = array_sum($anchos) / $util;
if ($exceso > 1) { foreach ($anchos as $i => $a) { $anchos[$i] = $a / $exceso; } }

$pdf->SetFont('Helvetica', 'B', 8);
$pdf->SetFillColor(238, 242, 247);
foreach ($datos['cabeceras'] as $i => $h) {
    $pdf->Cell($anchos[$i], 6, ds_pdf((string) $h), 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('Helvetica', '', 8);
$alterna = false;
foreach ($datos['filas'] as $fila) {
    $pdf->SetFillColor(249, 250, 251);
    foreach ($fila as $i => $v) {
        $tipo = $datos['tipos'][$i] ?? 'texto';
        $alineacion = $tipo === 'texto' ? 'L' : 'R';
        $texto = in_array($tipo, ['dinero'], true) && $v !== ''
            ? number_format((float) $v, 2, ',', '.')
            : (string) $v;
        $pdf->Cell($anchos[$i], 5.5, ds_pdf($texto), 1, 0, $alineacion, $alterna);
    }
    $pdf->Ln();
    $alterna = !$alterna;
}

$pdf->Output('D', $nombreArchivo . '.pdf');
exit();
