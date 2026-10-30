<?php
/*
| Pagos pendientes en PDF: reportePendientesPDF/{sede}/ (0 o sin sede: todas).
|
| El botón «Imprimir» de reportePendientes (2026-10-30). Mismos datos que la
| pantalla (reporteController::datosPendientes) y el mismo diseño que el
| reporte de alumnos (alumnoListaPDF): logo a su proporción, qué se filtró
| y cuándo, encabezado repetido en cada página, filas alternas y
| «Página X de Y». Al final, SUB TOTAL y TOTAL de todo el informe.
|
| Orden: de mayor a menor deuda, que es como se lee un informe de cobro.
*/

use app\controllers\reporteController;
use app\controllers\alumnoController;

include 'app/lib/fpdf.php';

/* Leído con isset: un aviso de PHP sería salida enviada y FPDF abortaría
   con «Some data has already been output». */
$sedeid = isset($url[1]) && $url[1] !== "" ? (int)$url[1] : 0;

$insReporte = new reporteController();
$filas = $insReporte->datosPendientes($sedeid);
usort($filas, static fn($a, $b) =>
    ((float)$b['SALDO'] + (float)$b['PENSION']) <=> ((float)$a['SALDO'] + (float)$a['PENSION'])
    ?: strcmp((string)$a['NOMBRES'], (string)$b['NOMBRES']));
$totales = reporteController::totalesPendientes($filas);

/* Logo y dirección: los de la sede filtrada o, con todas, los de la principal. */
$sede = (new alumnoController())->informacionSede($sedeid > 0 ? $sedeid : 1);
$sede = $sede->rowCount() == 1 ? $sede->fetch() : ['sede_id' => 0, 'sede_nombre' => '', 'sede_direccion' => '', 'sede_telefono' => ''];

/* Texto para FPDF: deshace el escapado y pasa a ISO-8859-1. */
function pend_t($s): string
{
    return mb_convert_encoding(html_entity_decode(trim((string)$s), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'ISO-8859-1', 'UTF-8');
}

class ReportePendientesPDF extends FPDF
{
    public array $sede = [];
    public string $filtro = '';
    public string $generado = '';

    /* Columnas: ancho, rótulo y alineación. Suman 190 mm (A4 con márgenes de 10). */
    public const COLUMNAS = [
        [8,  'No.',         'C'],
        [22, 'CÉDULA',      'L'],
        [66, 'ALUMNO',      'L'],
        [16, 'PARTIDAS',    'C'],
        [20, 'SALDOS',      'R'],
        [17, 'PENSIONES',   'C'],
        [21, 'VALOR PENS.', 'R'],
        [20, 'TOTAL',       'R'],
    ];

    function Header()
    {
        if ($this->PageNo() === 1) {
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            $this->Rect(10, 10, 190, 34);
            ds_logo_dibujar($this, (int)$this->sede['sede_id'], 14, 12, 30, 30);

            $this->SetTextColor(33, 37, 41);
            $this->SetXY(48, 14);
            $this->SetFont('Arial', 'B', 13);
            $this->Cell(148, 6, pend_t(ds_nombre_organizacion_may((int)$this->sede['sede_id'])), 0, 2, 'C');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            if (trim((string)$this->sede['sede_direccion']) !== '') {
                $this->Cell(148, 5, pend_t('Dirección: ' . $this->sede['sede_direccion']), 0, 2, 'C');
            }
            if (trim((string)$this->sede['sede_telefono']) !== '') {
                $this->Cell(148, 5, pend_t('Celular: ' . $this->sede['sede_telefono']), 0, 2, 'C');
            }
            $this->SetXY(48, $this->GetY() + 1);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(148, 7, 'REPORTE DE PAGOS PENDIENTES', 0, 2, 'C');

            $this->SetXY(10, 47);
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(120, 5, pend_t($this->filtro), 0, 0, 'L');
            $this->Cell(70, 5, pend_t('Generado: ' . $this->generado), 0, 1, 'R');
            $this->Ln(2);
        } else {
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(95, 6, 'REPORTE DE PAGOS PENDIENTES', 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(95, 6, pend_t($this->filtro), 0, 1, 'R');
            $this->Ln(1);
        }
        $this->encabezadoTabla();
    }

    function encabezadoTabla()
    {
        $this->SetFont('Arial', 'B', 7);
        $this->SetFillColor(33, 37, 41);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(33, 37, 41);
        foreach (self::COLUMNAS as [$ancho, $rotulo, $alinea]) {
            $this->Cell($ancho, 8, pend_t($rotulo), 1, 0, $alinea === 'L' ? 'L' : 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(33, 37, 41);
        $this->SetDrawColor(210, 210, 210);
    }

    function Footer()
    {
        $this->SetY(-14);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(95, 6, pend_t(ds_nombre_organizacion((int)$this->sede['sede_id'])), 0, 0, 'L');
        $this->Cell(95, 6, pend_t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /* Un nombre largo se recorta con «…» en lugar de pisar la columna vecina. */
    function cabe(string $texto, float $ancho): string
    {
        if ($this->GetStringWidth($texto) <= $ancho - 2) { return $texto; }
        $puntos = chr(133);   /* «…» en ISO-8859-1 */
        while ($texto !== '' && $this->GetStringWidth($texto . $puntos) > $ancho - 2) {
            $texto = substr($texto, 0, -1);
        }
        return rtrim($texto) . $puntos;
    }
}

$pdf = new ReportePendientesPDF('P', 'mm', 'A4');
$pdf->sede     = $sede;
$pdf->generado = date('d/m/Y H:i');
$pdf->filtro   = 'Sede: ' . ($sedeid > 0 ? html_entity_decode((string)$sede['sede_nombre']) : 'todas')
               . '  ·  Alumnos activos';
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);   /* el salto lo da FPDF y Header() repite el encabezado */
$pdf->AddPage();

$pdf->SetFont('Arial', '', 8);
$dinero = static fn($v) => '$' . number_format((float)$v, 2, '.', ',');
foreach ($filas as $n => $f) {
    $fila = [
        $n + 1,
        pend_t($f['alumno_identificacion']),
        $pdf->cabe(pend_t($f['NOMBRES']), ReportePendientesPDF::COLUMNAS[2][0]),
        (int)$f['NUM_SALDO'],
        $dinero($f['SALDO']),
        (int)$f['NUM_PENSION'],
        $dinero($f['PENSION']),
        $dinero((float)$f['SALDO'] + (float)$f['PENSION']),
    ];
    $pdf->SetFillColor(...($n % 2 ? [255, 255, 255] : [243, 245, 247]));
    foreach (ReportePendientesPDF::COLUMNAS as $i => [$ancho, , $alinea]) {
        $pdf->SetFont('Arial', $i === 7 ? 'B' : '', 8);
        $pdf->Cell($ancho, 6.5, (string)$fila[$i], 1, 0, $alinea, true);
    }
    $pdf->Ln();
}

/* SUB TOTAL y TOTAL de todo el informe, o un aviso si no hay pendientes.
   Si no caben juntos al pie de la hoja, pasan los dos a la siguiente. */
if ($filas) {
    if ($pdf->GetY() + 16 > $pdf->GetPageHeight() - 18) { $pdf->AddPage(); }
    $c = ReportePendientesPDF::COLUMNAS;
    $pdf->SetDrawColor(33, 37, 41);
    $pdf->SetFillColor(233, 236, 239);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($c[0][0] + $c[1][0] + $c[2][0], 7, 'SUB TOTAL', 1, 0, 'L', true);
    $pdf->Cell($c[3][0], 7, (string)$totales['num_saldo'], 1, 0, 'C', true);
    $pdf->Cell($c[4][0], 7, $dinero($totales['saldo']), 1, 0, 'R', true);
    $pdf->Cell($c[5][0], 7, (string)$totales['num_pension'], 1, 0, 'C', true);
    $pdf->Cell($c[6][0], 7, $dinero($totales['pension']), 1, 0, 'R', true);
    $pdf->Cell($c[7][0], 7, '', 1, 1, 'R', true);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(190 - $c[7][0], 8, pend_t('TOTAL  ·  ' . $totales['partidas'] . ' partidas de '
        . $totales['alumnos'] . ($totales['alumnos'] === 1 ? ' alumno' : ' alumnos')), 1, 0, 'L', true);
    $pdf->Cell($c[7][0], 8, $dinero($totales['total']), 1, 1, 'R', true);
} else {
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(190, 7, pend_t('No hay pagos pendientes.'), 0, 1, 'C');
}

$pdf->Output(ds_nombre_archivo("Pagos_pendientes") . ".pdf", "I", "T");
