<?php
/*
| Lista de jugadores de un equipo en PDF: jugadorListaPDF/{equipo}/.
|
| QUÉ CAMBIÓ (2026-10-28): el mismo aspecto que el reporte de alumnos
| (alumnoListaPDF), para que los reportes del sistema se parezcan:
|
|   · Recuadro de cabecera con el logo a su proporción, el nombre, la
|     dirección y el teléfono; el título y el equipo con su categoría.
|   · Debajo, el torneo, la sede, el profesor y cuándo se generó.
|   · La tabla repite su encabezado en cada página, alterna el fondo de las
|     filas, da la fecha como dd/mm/aaaa y suma el TIPO (principal/suplente).
|   · Al cierre, el total de jugadores y las firmas; al pie, «Página X de Y».
|   · Fuera el código QR que se generaba en un archivo temporal y no se
|     dibujaba en ningún sitio.
|   · Un equipo de «todas las sedes» (sede 0) usa los datos de la organización.
*/

use app\controllers\jugadorController;

include 'app/lib/fpdf.php';

$insLista  = new jugadorController();
$equipo_id = ds_id_de_url($url, 1, APP_URL . 'torneoList/');

$equipo = $insLista->BuscarEquipo($equipo_id)->fetch(PDO::FETCH_ASSOC);
if (!$equipo) {
    header("Location: " . APP_URL . 'torneoList/');
    exit();
}

/* Datos de cabecera: los de la sede del equipo o, si es de todas, los de la organización. */
$sedeId = (int)$equipo['equipo_sedeid'];
$sede   = $sedeId > 0 ? $insLista->informacionSede($sedeId)->fetch(PDO::FETCH_ASSOC) : false;
if (!$sede) {
    $org  = ds_organizacion();
    $sede = ['sede_id' => 0, 'sede_nombre' => 'Todas las sedes', 'sede_direccion' => $org['escuela_direccion'], 'sede_telefono' => $org['escuela_telefono']];
}

$lista = $insLista->listaJugadorPDF($equipo_id)->fetchAll(PDO::FETCH_ASSOC);

/* Texto para FPDF: deshace el escapado y pasa a la codificación de FPDF. */
function jug_t($s): string
{
    return mb_convert_encoding(html_entity_decode(trim((string)$s), ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'ISO-8859-1', 'UTF-8');
}

class ListaJugadoresPDF extends FPDF
{
    public array $sede = [];
    public string $titulo = '';
    public string $datos = '';
    public string $generado = '';

    /* Columnas: ancho y rótulo. Suman 190 mm (A4 con márgenes de 10). */
    public const COLUMNAS = [
        [9, 'No.'], [66, 'APELLIDOS Y NOMBRES'], [23, 'CÉDULA'], [22, 'NACIMIENTO'],
        [27, 'POSICIÓN'], [21, 'TIPO'], [22, 'CAMISETA'],
    ];
    public const CENTRADAS = [0, 2, 3, 6];

    function Header()
    {
        if ($this->PageNo() === 1) {
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.2);
            $this->Rect(10, 10, 190, 38);
            ds_logo_dibujar($this, (int)$this->sede['sede_id'], 14, 13, 32, 32);

            $this->SetTextColor(33, 37, 41);
            $this->SetXY(50, 13);
            $this->SetFont('Arial', 'B', 13);
            $this->Cell(146, 6, jug_t(ds_nombre_organizacion_may((int)$this->sede['sede_id'])), 0, 2, 'C');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            if (trim((string)$this->sede['sede_direccion']) !== '') {
                $this->Cell(146, 5, jug_t('Dirección: ' . $this->sede['sede_direccion']), 0, 2, 'C');
            }
            if (trim((string)$this->sede['sede_telefono']) !== '') {
                $this->Cell(146, 5, jug_t('Celular: ' . $this->sede['sede_telefono']), 0, 2, 'C');
            }
            $this->SetXY(50, $this->GetY() + 1);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(146, 6, 'LISTA DE JUGADORES', 0, 2, 'C');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(146, 5, jug_t($this->titulo), 0, 2, 'C');

            /* El torneo, la sede, el profesor y cuándo: una hoja impresa no lo dice sola. */
            $this->SetXY(10, 51);
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            /* Con un torneo o un profesor de nombre largo, encoge en vez de pisar la fecha. */
            ds_pdf_texto_encajado($this, jug_t($this->datos), 10, 51, 136, 5, '', 9, 6.5);
            $this->SetFont('Arial', '', 9);
            $this->SetXY(150, 51);
            $this->Cell(50, 5, jug_t('Generado: ' . $this->generado), 0, 1, 'R');
            $this->Ln(2);
        } else {
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(95, 6, 'LISTA DE JUGADORES', 0, 0, 'L');
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(90, 90, 90);
            $this->Cell(95, 6, jug_t($this->titulo), 0, 1, 'R');
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
            $this->Cell($ancho, 8, jug_t($rotulo), 1, 0, in_array($i, self::CENTRADAS, true) ? 'C' : 'L', true);
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
        $this->Cell(95, 6, jug_t(ds_nombre_organizacion((int)$this->sede['sede_id'])), 0, 0, 'L');
        $this->Cell(95, 6, jug_t('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }
}

$pdf = new ListaJugadoresPDF('P', 'mm', 'A4');
$pdf->sede     = $sede;
$pdf->titulo   = $equipo['equipo_nombre'] . '  ·  Categoría ' . $equipo['equipo_categoria'];
$pdf->datos    = implode('  ·  ', array_filter([
    trim((string)$equipo['torneo_nombre']) !== '' ? 'Torneo: ' . $equipo['torneo_nombre'] : '',
    'Sede: ' . $equipo['sede_nombre'],
    'Profesor: ' . $equipo['empleado_nombre'],
]));
$pdf->generado = date('d/m/Y H:i');
$pdf->AliasNbPages();
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);   /* el salto lo da FPDF y Header() repite el encabezado */
$pdf->AddPage();

$pdf->SetFont('Arial', '', 9);
foreach ($lista as $n => $j) {
    $nacio = $j['FECHANAC'] && !str_starts_with((string)$j['FECHANAC'], '0000') ? date('d/m/Y', strtotime($j['FECHANAC'])) : '';
    $fila = [
        $n + 1,
        jug_t($j['APELLIDOS'] . ' ' . $j['NOMBRES']),
        jug_t($j['CEDULA']),
        $nacio,
        jug_t($j['POSICION']),
        jug_t(preg_replace('/^Jugador\s+/i', '', (string)$j['TIPO'])),   /* «Principal» / «Suplente»: cabe en su columna */
        $j['NUMCAMISETA'] !== null ? (string)$j['NUMCAMISETA'] : '',
    ];
    $pdf->SetFillColor(...($n % 2 ? [255, 255, 255] : [243, 245, 247]));
    foreach (ListaJugadoresPDF::COLUMNAS as $i => [$ancho]) {
        /* Un nombre muy largo se encoge para no pisar la columna de al lado. */
        $tam = 9;
        while ($i === 1 && $tam > 6.5 && $pdf->GetStringWidth((string)$fila[$i]) > $ancho - 2) { $pdf->SetFontSize($tam -= 0.5); }
        $pdf->Cell($ancho, 7, (string)$fila[$i], 1, 0, in_array($i, ListaJugadoresPDF::CENTRADAS, true) ? 'C' : 'L', true);
        $pdf->SetFontSize(9);
    }
    $pdf->Ln();
}

/* Total, o un aviso si el equipo aún no tiene jugadores. */
$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(190, 7, jug_t($lista ? 'Total: ' . count($lista) . (count($lista) === 1 ? ' jugador' : ' jugadores')
                                : 'Este equipo aún no tiene jugadores.'), 0, 1, $lista ? 'R' : 'C');

/* Firmas: juntas en la misma hoja (si no caben, pasan las dos a la siguiente). */
if ($pdf->GetY() > 297 - 18 - 35) { $pdf->AddPage(); }
$pdf->Ln(14);
$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(33, 37, 41);
$pdf->Cell(95, 6, '______________________________', 0, 0, 'C');
$pdf->Cell(95, 6, '______________________________', 0, 1, 'C');
$pdf->Cell(95, 6, jug_t(ds_nombre_organizacion((int)$sede['sede_id'])), 0, 0, 'C');
$pdf->Cell(95, 6, 'Firma del Organizador', 0, 1, 'C');

$pdf->Output(ds_nombre_archivo('Jugadores ' . html_entity_decode((string)$equipo['equipo_nombre'], ENT_QUOTES, 'UTF-8')) . ".pdf", "I", "T");
