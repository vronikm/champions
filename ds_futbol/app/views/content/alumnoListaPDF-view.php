<?php
/*
| Reporte de alumnos en PDF: alumnoListaPDF/{categoría}/{sede}/{estado}/.
| Los tres son opcionales: 0 es «todas» y el estado, A (activos, por
| defecto), I (inactivos) o T (todos), el mismo filtro que el listado.
|
| QUÉ CAMBIÓ (2026-10-23)
|
|   · El logo se dibuja a su proporción (ds_logo_dibujar): iba metido en
|     47 x 26 mm y el escudo, casi cuadrado, salía casi el doble de ancho.
|   · La cabecera dice qué se filtró (sede, categoría) y cuándo se generó.
|   · La tabla repite su encabezado en cada página, alterna el fondo de las
|     filas, da la fecha como dd/mm/aaaa y la edad.
|   · Al pie, el total de alumnos y «Página X de Y».
|   · Los textos se guardan escapados (limpiarHtml): se deshacen antes de
|     pasarlos a la codificación de FPDF, o salía «&amp;» en el papel.
*/

use app\controllers\alumnoController;

include 'app/lib/fpdf.php';

$insLista = new alumnoController();

/* Leídos con isset: al faltar el segmento en la URL, PHP emitía un aviso,
   ese aviso ya era salida enviada y FPDF abortaba con «Some data has
   already been output». El PDF moría por un warning, no por los datos. */
$categoriaid = isset($url[1]) && $url[1] !== "" ? (int)$url[1] : 0;
$sedeid      = isset($url[2]) && $url[2] !== "" ? (int)$url[2] : 0;
$estado      = in_array($url[3] ?? "", ["A", "I", "T"], true) ? $url[3] : "A";
$rotuloEstado = ["A" => "Alumnos activos", "I" => "Alumnos inactivos", "T" => "Activos e inactivos"][$estado];

/* Los datos de cabecera (logo, dirección) son los de la sede filtrada o,
   con todas, los de la principal. */
$sede = $insLista->informacionSede($sedeid > 0 ? $sedeid : 1);
$sede = $sede->rowCount() == 1 ? $sede->fetch() : ['sede_id' => 0, 'sede_nombre' => '', 'sede_direccion' => '', 'sede_telefono' => ''];

$lista = $insLista->listarAlumnosPDF($categoriaid, $sedeid, $estado);
$lista = $lista->rowCount() > 0 ? $lista->fetchAll() : [];

/* Texto para FPDF: deshace el escapado y pasa a ISO-8859-1. */
function rep_t($s): string
{
    return mb_convert_encoding(html_entity_decode(trim((string)$s), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'ISO-8859-1', 'UTF-8');
}

class ReporteAlumnosPDF extends FPDF
{
    public array $sede = [];
    public string $filtro = '';
    public string $generado = '';

    /* Columnas: ancho y rótulo. Suman 190 mm (A4 con márgenes de 10). */
    public const COLUMNAS = [
        [10, 'No.'], [24, 'SEDE'], [28, 'IDENTIFICACIÓN'], [44, 'NOMBRES'],
        [48, 'APELLIDOS'], [24, 'NACIMIENTO'], [12, 'EDAD'],
    ];

    function Header()
    {
        if ($this->PageNo() === 1) {
            /* Recuadro de cabecera: logo a la izquierda, a su proporción. */
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            $this->Rect(10, 10, 190, 34);
            ds_logo_dibujar($this, (int)$this->sede['sede_id'], 14, 12, 30, 30);

            $this->SetTextColor(33, 37, 41);
            $this->SetXY(48, 14);
            $this->SetFont('Arial', 'B', 13);
            $this->Cell(148, 6, rep_t(ds_nombre_organizacion_may((int)$this->sede['sede_id'])), 0, 2, 'C');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            if (trim((string)$this->sede['sede_direccion']) !== '') {
                $this->Cell(148, 5, rep_t('Dirección: ' . $this->sede['sede_direccion']), 0, 2, 'C');
            }
            if (trim((string)$this->sede['sede_telefono']) !== '') {
                $this->Cell(148, 5, rep_t('Celular: ' . $this->sede['sede_telefono']), 0, 2, 'C');
            }
            $this->SetXY(48, $this->GetY() + 1);   /* Ln() volvería al margen y descentraría el título */
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(148, 7, 'REPORTE DE ALUMNOS', 0, 2, 'C');

            /* Qué se filtró y cuándo: una hoja impresa no dice de qué filtro salió. */
            $this->SetXY(10, 47);
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(120, 5, rep_t($this->filtro), 0, 0, 'L');
            $this->Cell(70, 5, rep_t('Generado: ' . $this->generado), 0, 1, 'R');
            $this->Ln(2);
        } else {
            /* Páginas siguientes: una línea con el título y el filtro. */
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(95, 6, 'REPORTE DE ALUMNOS', 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(95, 6, rep_t($this->filtro), 0, 1, 'R');
            $this->Ln(1);
        }
        $this->encabezadoTabla();
    }

    function encabezadoTabla()
    {
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(33, 37, 41);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(33, 37, 41);
        foreach (self::COLUMNAS as $i => [$ancho, $rotulo]) {
            $this->Cell($ancho, 8, rep_t($rotulo), 1, 0, in_array($i, [0, 5, 6], true) ? 'C' : 'L', true);
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
        $this->Cell(95, 6, rep_t(ds_nombre_organizacion((int)$this->sede['sede_id'])), 0, 0, 'L');
        $this->Cell(95, 6, rep_t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }
}

$pdf = new ReporteAlumnosPDF('P', 'mm', 'A4');
$pdf->sede     = $sede;
$pdf->generado = date('d/m/Y H:i');
$pdf->filtro   = 'Sede: ' . ($sedeid > 0 ? html_entity_decode((string)$sede['sede_nombre']) : 'todas')
               . '  ·  Categoría: ' . ($categoriaid > 0 ? $categoriaid : 'todas')
               . '  ·  ' . $rotuloEstado;
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);   /* el salto lo da FPDF y Header() repite el encabezado */
$pdf->AddPage();

$pdf->SetFont('Arial', '', 9);
$hoy = new DateTime('today');
foreach ($lista as $n => $al) {
    $nacio = $al['alumno_fechanacimiento'] && !str_starts_with((string)$al['alumno_fechanacimiento'], '0000')
        ? new DateTime($al['alumno_fechanacimiento']) : null;
    $fila = [
        $n + 1,
        rep_t($al['sede_nombre']),
        rep_t($al['alumno_identificacion']),
        rep_t($al['alumno_primernombre'] . ' ' . $al['alumno_segundonombre']),
        rep_t($al['alumno_apellidopaterno'] . ' ' . $al['alumno_apellidomaterno']),
        $nacio ? $nacio->format('d/m/Y') : '',
        $nacio ? $nacio->diff($hoy)->y : '',
    ];
    /* Filas alternas, para seguir la línea con la vista en un listado largo. */
    $pdf->SetFillColor(...($n % 2 ? [255, 255, 255] : [243, 245, 247]));
    foreach (ReporteAlumnosPDF::COLUMNAS as $i => [$ancho]) {
        $pdf->Cell($ancho, 7, (string)$fila[$i], 1, 0, in_array($i, [0, 5, 6], true) ? 'C' : 'L', true);
    }
    $pdf->Ln();
}

/* Total al cierre, o un aviso si el filtro no trae a nadie. */
$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 7, rep_t($lista ? 'Total: ' . count($lista) . (count($lista) === 1 ? ' alumno' : ' alumnos')
                                 : 'No hay alumnos con este filtro.'), 0, 1, $lista ? 'R' : 'C');

$pdf->Output(ds_nombre_archivo("Alumnos") . ".pdf", "I", "T");
