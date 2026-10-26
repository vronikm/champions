<?php
/*
|--------------------------------------------------------------------------
| Dibujo del carnet en FPDF (AlphaPDF)
|--------------------------------------------------------------------------
| Antes el carnet se dibujaba dos veces —carnetPDF (lote del mes) y
| carnetFotoPDF (carnet suelto)—, con las medidas escritas en cada una. Ahora
| las dos llaman a carnet_dibujar() y el diseño (ds_core/inc/carnet_diseno.php,
| editable en Core › Carnets) decide qué elementos lleva y con qué medidas.
| Con el diseño por defecto sale idéntico al de siempre.
|
| $c, los datos del carnet (texto en UTF-8; aquí se pasa a ISO-8859-1):
|   color [r,g,b] · img_fondo · img_linea · sede_id · qr (archivo) · foto
|   nombre · ci · horario · edad · mes · sede_nombre · correo · telefono
|   reimpresion (bool) · fecha (dd/mm/aaaa)
*/

const CARNET_ANCHO = 85.6;    // mm, tarjeta de crédito estándar
const CARNET_ALTO  = 53.98;

/** Carpeta de las imágenes del carnet (franja y línea decorativa). */
function carnet_dir_imagenes(): string
{
    return dirname(__DIR__) . '/views/imagenes/carnet/';
}

function carnet_iso(string $t): string
{
    $c = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $t);
    return $c !== false ? $c : mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');
}

function carnet_dibujar($pdf, float $x, float $y, array $c, array $d): void
{
    $W = CARNET_ANCHO;
    $H = CARNET_ALTO;
    [$r, $g, $b] = $c['color'] ?? [204, 204, 204];
    $franja = (float)$d['franja_ancho'];
    $infoOff = $franja + 2;                       // el texto empieza 2 mm después de la franja

    /* Fondo blanco y borde */
    $pdf->SetFillColor(255, 255, 255);
    $pdf->Rect($x, $y, $W, $H, 'F');
    $pdf->SetLineWidth(0.3);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Rect($x, $y, $W, $H);

    /* Franja lateral: imagen y, encima, el color del mes */
    if (!empty($d['franja_imagen']) && !empty($c['img_fondo']) && is_file($c['img_fondo'])) {
        $pdf->Image($c['img_fondo'], $x, $y, $franja, $H);
    }
    if ((int)$d['franja_opacidad'] > 0) {
        $pdf->SetAlpha(((int)$d['franja_opacidad']) / 100);
        $pdf->SetFillColor($r, $g, $b);
        $pdf->Rect($x, $y, $franja, $H, 'F');
        $pdf->SetAlpha(1);
    }

    /* Línea decorativa vertical */
    if (!empty($d['linea']) && !empty($c['img_linea']) && is_file($c['img_linea'])) {
        $pdf->Image($c['img_linea'], $x + $W - 65, $y, 1, $H);
    }

    /* Logo de la sede, a su proporción; centrado donde siempre (x + 49 mm) */
    if (!empty($d['logo'])) {
        $t = (float)$d['logo_tamano'];
        ds_logo_dibujar($pdf, (int)$c['sede_id'], $x + 49 - $t / 2, $y + 2, $t, $t * 17 / 18);
    }

    /* QR */
    if (!empty($d['qr']) && !empty($c['qr']) && is_file($c['qr'])) {
        $pdf->Image($c['qr'], $x + $W - 15, $y + 2, 12, 12);
    }

    /* Foto */
    $fotoX = $x + $W - 23; $fotoY = $y + 20;
    if (!empty($c['foto']) && is_file($c['foto'])) {
        $pdf->Image($c['foto'], $fotoX, $fotoY, 20, 25);
    }
    $pdf->SetLineWidth(0.3);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->Rect($fotoX, $fotoY, 20, 25);

    /* Datos del alumno */
    $infoX = $x + $infoOff;
    $infoY = $y + 17;
    $anchoTexto = 64 - $infoOff;                  // hasta la foto (42 mm con la franja de 20)

    $pdf->SetFont('Arial', 'B', 6);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($infoX, $infoY);
    $pdf->Cell(40, 3, carnet_iso((string)$d['titulo']), 0, 0, 'L');
    $infoY += 4;

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY($infoX, $infoY);
    $nombre = carnet_iso(mb_strtoupper((string)$c['nombre'], 'UTF-8'));
    if (mb_strlen((string)$c['nombre'], 'UTF-8') > 21) {
        $pdf->MultiCell($anchoTexto, 3, $nombre, 0, 'L');
        $infoY = $pdf->GetY();
    } else {
        $pdf->Cell($anchoTexto - 2, 3, $nombre, 0, 0, 'L');
        $infoY += 3;
    }
    $infoY += 1;

    $linea = function (string $rotulo, float $sangria, string $valor, float $tamano = 8) use ($pdf, $infoX, &$infoY) {
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetXY($infoX, $infoY);
        $pdf->Cell(15, 2.5, carnet_iso($rotulo), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', $tamano);
        $pdf->SetXY($infoX + $sangria, $infoY);
        $pdf->Cell(40 - $sangria, 2.5, carnet_iso($valor), 0, 0, 'L');
        $infoY += 3;
    };

    if (!empty($d['ci']))      { $linea('C.I.:', 8, (string)$c['ci']); }
    if (!empty($d['horario'])) { $linea('Horario:', 12, (string)$c['horario']); }
    if (!empty($d['edad']))    { $linea('Edad:', 10, ((int)$c['edad']) . ' años', 7.5); }

    /* Mes de vigencia, siempre: es lo que se mira en la puerta */
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetXY($infoX, $infoY);
    $pdf->Cell(15, 2.5, 'Mes vigencia:', 0, 0, 'L');
    $pdf->SetFillColor($r, $g, $b);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetXY($infoX + 18, $infoY - 0.5);
    $pdf->Cell(20, 3, carnet_iso(mb_strtoupper((string)$c['mes'], 'UTF-8')), 0, 0, 'C', true);
    $infoY += 3;

    if (!empty($d['sede'])) { $linea('Sede:', 10, (string)$c['sede_nombre']); }

    $infoY += 0.5;
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetTextColor(100, 100, 100);
    if (!empty($d['correo'])) {
        $pdf->SetXY($infoX, $infoY);
        $pdf->Cell(40, 2, carnet_iso((string)$c['correo']), 0, 0, 'L');
        $infoY += 3;
    }
    if (!empty($d['telefono'])) {
        $pdf->SetXY($infoX, $infoY);
        $pdf->Cell(40, 2, carnet_iso((string)$c['telefono']), 0, 0, 'L');
    }

    /* Pie: reimpresión o fecha de impresión */
    if (!empty($c['reimpresion'])) {
        $pdf->SetFont('Arial', 'B', 6);
        $pdf->SetTextColor(255, 0, 0);
        $pdf->SetXY($x + $infoOff, $y + $H - 4);
        $pdf->Cell(30, 2, 'REIMPRESION', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 4.5);
        $pdf->SetXY($x + $W - 25, $y + $H - 4.5);
        $pdf->Cell(22, 2, 'Reimp: ' . ($c['fecha'] ?? date('d/m/Y')), 0, 0, 'R');
    } elseif (!empty($d['fecha'])) {
        $pdf->SetFont('Arial', '', 5);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetXY($x + $W - 25, $y + $H - 4.5);
        $pdf->Cell(22, 2, 'Impreso: ' . ($c['fecha'] ?? date('d/m/Y')), 0, 0, 'R');
    }

    $pdf->SetDrawColor(220, 220, 220);
    $pdf->Line($x + $infoOff, $y + $H - 2, $x + $W - 3, $y + $H - 2);
}
