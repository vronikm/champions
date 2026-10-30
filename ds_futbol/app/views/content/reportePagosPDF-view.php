<?php
/*
| Pagos realizados en PDF: reportePagosPDF/{sede}/{desde}/{hasta}/.
| Sede 0: todas (el consolidado). Fechas aaaa-mm-dd; sin ellas, el mes actual.
|
| El botón «Imprimir» de reportePagos (2026-10-30). Mismos datos que la
| pantalla (reporteController::datosPagos) y el mismo diseño que los otros
| reportes (alumnoListaPDF, reportePendientesPDF), en horizontal porque son
| doce columnas. Al final, el TOTAL pagado y pendiente de todo el informe.
*/

use app\controllers\reporteController;
use app\controllers\alumnoController;

include 'app/lib/fpdf.php';

/* Leídos con isset: un aviso de PHP sería salida enviada y FPDF abortaría
   con «Some data has already been output». */
$fechaValida = static fn($f) => is_string($f) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) && checkdate((int)substr($f, 5, 2), (int)substr($f, 8, 2), (int)substr($f, 0, 4));
$sedeid = isset($url[1]) && $url[1] !== "" ? (int)$url[1] : 0;
$desde  = $fechaValida($url[2] ?? null) ? $url[2] : date('Y-m-01');
$hasta  = $fechaValida($url[3] ?? null) ? $url[3] : date('Y-m-t');

$insReporte = new reporteController();
$filas   = $insReporte->datosPagos($desde, $hasta, $sedeid > 0 ? $sedeid : '');
$totales = reporteController::totalesPagos($filas);

/* Logo y dirección: los de la sede filtrada o, con todas, los de la principal. */
$sede = (new alumnoController())->informacionSede($sedeid > 0 ? $sedeid : 1);
$sede = $sede->rowCount() == 1 ? $sede->fetch() : ['sede_id' => 0, 'sede_nombre' => '', 'sede_direccion' => '', 'sede_telefono' => ''];

/* Texto para FPDF: deshace el escapado y pasa a ISO-8859-1. */
function pagos_t($s): string
{
    return mb_convert_encoding(html_entity_decode(trim((string)$s), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'ISO-8859-1', 'UTF-8');
}

class ReportePagosPDF extends FPDF
{
    public array $sede = [];
    public string $filtro = '';
    public string $generado = '';

    /* Columnas: ancho, rótulo y alineación. Suman 277 mm (A4 horizontal con márgenes de 10). */
    public const COLUMNAS = [
        [8,  'No.',           'C'],
        [16, 'SEDE',          'L'],
        [22, 'CÉDULA',        'L'],
        [55, 'ALUMNO',        'L'],
        [18, 'F. PAGO',       'C'],
        [18, 'REGISTRADO',    'C'],
        [24, 'PERIODO',       'L'],
        [32, 'RUBRO',         'L'],
        [40, 'FORMA DE PAGO', 'L'],
        [17, 'PAGADO',        'R'],
        [17, 'PENDIENTE',     'R'],
        [10, 'EST.',          'C'],
    ];

    function Header()
    {
        if ($this->PageNo() === 1) {
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            $this->Rect(10, 10, 277, 34);
            ds_logo_dibujar($this, (int)$this->sede['sede_id'], 14, 12, 30, 30);

            $this->SetTextColor(33, 37, 41);
            $this->SetXY(48, 14);
            $this->SetFont('Arial', 'B', 13);
            $this->Cell(235, 6, pagos_t(ds_nombre_organizacion_may((int)$this->sede['sede_id'])), 0, 2, 'C');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            if (trim((string)$this->sede['sede_direccion']) !== '') {
                $this->Cell(235, 5, pagos_t('Dirección: ' . $this->sede['sede_direccion']), 0, 2, 'C');
            }
            if (trim((string)$this->sede['sede_telefono']) !== '') {
                $this->Cell(235, 5, pagos_t('Celular: ' . $this->sede['sede_telefono']), 0, 2, 'C');
            }
            $this->SetXY(48, $this->GetY() + 1);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(235, 7, 'REPORTE DE PAGOS REALIZADOS', 0, 2, 'C');

            $this->SetXY(10, 47);
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(200, 5, pagos_t($this->filtro), 0, 0, 'L');
            $this->Cell(77, 5, pagos_t('Generado: ' . $this->generado), 0, 1, 'R');
            $this->Ln(2);
        } else {
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(100, 6, 'REPORTE DE PAGOS REALIZADOS', 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(177, 6, pagos_t($this->filtro), 0, 1, 'R');
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
            $this->Cell($ancho, 8, pagos_t($rotulo), 1, 0, $alinea === 'L' ? 'L' : 'C', true);
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
        $this->Cell(138, 6, pagos_t(ds_nombre_organizacion((int)$this->sede['sede_id'])), 0, 0, 'L');
        $this->Cell(139, 6, pagos_t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /* Un texto largo se recorta con «…» en lugar de pisar la columna vecina. */
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

$pdf = new ReportePagosPDF('L', 'mm', 'A4');
$pdf->sede     = $sede;
$pdf->generado = date('d/m/Y H:i');
$pdf->filtro   = 'Sede: ' . ($sedeid > 0 ? html_entity_decode((string)$sede['sede_nombre']) : 'todas')
               . '  ·  Del ' . reporteController::fechaCorta($desde) . ' al ' . reporteController::fechaCorta($hasta)
               . '  ·  ' . ($sedeid > 0 ? 'por fecha de registro' : 'por fecha de pago');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);   /* el salto lo da FPDF y Header() repite el encabezado */
$pdf->AddPage();

$c = ReportePagosPDF::COLUMNAS;
foreach ($filas as $n => $f) {
    $pdf->SetFont('Arial', '', 7.5);
    $fila = [
        $n + 1,
        $pdf->cabe(pagos_t($f['SEDE']), $c[1][0]),
        pagos_t($f['IDENTIFICACION']),
        $pdf->cabe(pagos_t($f['ALUMNO']), $c[3][0]),
        reporteController::fechaCorta($f['FECHA_PAGO']),
        reporteController::fechaCorta($f['FECHA_REG_SISTEMA']),
        $pdf->cabe(pagos_t($f['PERIODO']), $c[6][0]),
        $pdf->cabe(pagos_t($f['RUBRO']), $c[7][0]),
        $pdf->cabe(pagos_t($f['FORMA_PAGO']), $c[8][0]),
        reporteController::dinero($f['VALOR_PAGADO']),
        reporteController::dinero($f['VALOR_PENDIENTE']),
        $f['ESTADO_PAGO'] === 'Cancelado' ? 'C' : 'P',
    ];
    $pdf->SetFillColor(...($n % 2 ? [255, 255, 255] : [243, 245, 247]));
    foreach ($c as $i => [$ancho, , $alinea]) {
        $pdf->SetFont('Arial', $i === 9 ? 'B' : '', 7.5);
        $pdf->Cell($ancho, 6, (string)$fila[$i], 1, 0, $alinea, true);
    }
    $pdf->Ln();
}

/* TOTAL de todo el informe, o un aviso si no hay pagos. */
if ($filas) {
    if ($pdf->GetY() + 8 > $pdf->GetPageHeight() - 18) { $pdf->AddPage(); }
    $pdf->SetDrawColor(33, 37, 41);
    $pdf->SetFillColor(233, 236, 239);
    $pdf->SetFont('Arial', 'B', 9);
    $antes = array_sum(array_column(array_slice($c, 0, 9), 0));
    $pdf->Cell($antes, 8, pagos_t('TOTAL  ·  ' . $totales['pagos'] . ($totales['pagos'] === 1 ? ' pago' : ' pagos')), 1, 0, 'L', true);
    $pdf->Cell($c[9][0], 8, reporteController::dinero($totales['pagado']), 1, 0, 'R', true);
    $pdf->Cell($c[10][0], 8, reporteController::dinero($totales['pendiente']), 1, 0, 'R', true);
    $pdf->Cell($c[11][0], 8, '', 1, 1, 'C', true);
    $pdf->Ln(2);
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetTextColor(120, 120, 120);
    $pdf->Cell(277, 5, pagos_t('EST.: C = cancelado, P = con saldo pendiente.'), 0, 1, 'L');
} else {
    $pdf->Ln(3);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(277, 7, pagos_t('No hay pagos entre esas fechas.'), 0, 1, 'C');
}

$pdf->Output(ds_nombre_archivo("Pagos_realizados") . ".pdf", "I", "T");
