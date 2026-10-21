<?php
use app\controllers\carnetController;

include 'app/lib/barcode.php';
include 'app/lib/alphapdf.php';

$insCarnet = new carnetController();

$generator  = new barcode_generator();
$symbology  = "qr";
$optionsQR  = array('sx'=>4, 'sy'=>4, 'p'=>-12);
$tempDir    = "app/views/dist/img/temp/";

// ============================================
// OBTENER DATOS DEL ALUMNO
// ============================================
$alumnoid = ds_id_de_url($url, 1, APP_URL . 'carnetList/');

$datosRaw = $insCarnet->infoAlumnoCarnet($alumnoid);

if(is_string($datosRaw)){
    $alerta = json_decode($datosRaw, true);
    die('<div style="font-family:sans-serif;padding:30px;color:#c0392b;">
            <h2>⚠️ ' . htmlspecialchars($alerta['titulo']) . '</h2>
            <p>' . htmlspecialchars($alerta['texto']) . '</p>
            <a href="javascript:history.back()">← Volver</a>
         </div>');
}

if($datosRaw->rowCount() != 1){
    die('<div style="font-family:sans-serif;padding:30px;color:#c0392b;">
            <h2>⚠️ Alumno no encontrado</h2>
            <a href="javascript:history.back()">← Volver</a>
         </div>');
}
$datos = $datosRaw->fetch();

// Estado de pensión y mes
$estadoAlumno = $insCarnet->EstadoAlumno($alumnoid);
if($estadoAlumno->rowCount() == 1){
    $estadoAlumno    = $estadoAlumno->fetch();
    $condicion       = $estadoAlumno['Condicion'];
    $fechaUltPension = $estadoAlumno['FechaUltPension'];
    $mesActual       = (int)date('n', strtotime($fechaUltPension));
} else {
    $condicion       = 'Pendiente';
    $fechaUltPension = date('Y-m-d');
    $mesActual       = (int)date('n');
}

// Color del mes
$colorMes = $insCarnet->BuscarColorPorMes($mesActual);
$colorHex = '#FF69B4'; // rosa por defecto (mes sin color configurado)
if($colorMes && $colorMes->rowCount() == 1){
    $colorMes = $colorMes->fetch();
    $colorHex = $colorMes['color_hex'];
}
list($r, $g, $b) = sscanf($colorHex, "#%02x%02x%02x");

// Información de sede
$sede = $insCarnet->informacionSede($datos['alumno_sedeid']);
if($sede->rowCount() != 1){
    die('<div style="font-family:sans-serif;padding:30px;color:#c0392b;">
            <h2>⚠️ Sede no encontrada</h2>
            <a href="javascript:history.back()">← Volver</a>
         </div>');
}
$sede = $sede->fetch();

// Nombre del mes
$nombresMeses = [
    1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
    5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
    9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'
];
$mesNombre = strtoupper($nombresMeses[$mesActual] ?? 'N/A');

// Edad
$edad = date_diff(date_create($datos['alumno_fechanacimiento']), date_create('today'))->y;

// Nombre completo
$nombreCompleto = mb_convert_encoding(
    strtoupper($datos['Nombres'] . ' ' . $datos['Apellidos']),
    'ISO-8859-1', 'UTF-8'
);

// ============================================
// CONFIGURACIÓN PDF — tamaño exacto del carnet
// ============================================
$carnetWidth  = 85.6;   // mm (tarjeta de crédito estándar)
$carnetHeight = 53.98;  // mm

$pdf = new AlphaPDF('L', 'mm', array($carnetWidth, $carnetHeight));
$pdf->SetAutoPagebreak(false);
$pdf->SetMargins(0, 0, 0);
$pdf->AddPage();

$x = 0;
$y = 0;

// ============================================
// FONDO BLANCO + BORDE
// ============================================
$pdf->SetFillColor(255, 255, 255);
$pdf->Rect($x, $y, $carnetWidth, $carnetHeight, 'F');
$pdf->SetLineWidth(0.3);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Rect($x, $y, $carnetWidth, $carnetHeight);

// ============================================
// IMAGEN DECORATIVA IZQUIERDA (vertical_fondo)
// ============================================
$imgFondo = "./app/views/imagenes/carnet/" . $sede['escuela_verticalfondo'];
if(file_exists($imgFondo)){
    $pdf->Image($imgFondo, $x, $y, 20, $carnetHeight);
}

// Overlay de color del mes sobre la imagen izquierda
$pdf->SetAlpha(0.5);
$pdf->SetFillColor($r, $g, $b);
$pdf->Rect($x, $y, 20, $carnetHeight, 'F');
$pdf->SetAlpha(1);

// Línea decorativa vertical (vertical_principal)
$imgDerecha = "./app/views/imagenes/carnet/" . $sede['escuela_verticalprincipal'];
if(file_exists($imgDerecha)){
    $pdf->Image($imgDerecha, $x + $carnetWidth - 65, $y, 1, $carnetHeight);
}

// ============================================
// HEADER: LOGO Y QR
// ============================================
$logoPath = ds_logo_ruta((int)$sede["sede_id"]);
if(file_exists($logoPath)){
    $pdf->Image($logoPath, $x + 40, $y + 2, 17, 19);
}

// Código QR
$qrData = "Estado pension: " . $condicion . "\n" .
          "Fecha ultimo pago: " . $fechaUltPension . "\n" .
          "Sede: " . $sede['sede_nombre'] . "\n" .
          $sede['sede_telefono'] . "\n" .
          $sede['sede_email'];

$qrFile = $tempDir . "qr_" . $alumnoid . "_" . time() . "_" . rand(1000,9999) . ".jpeg";
$image  = $generator->render_image($symbology, $qrData, $optionsQR);
imagejpeg($image, $qrFile);
imagedestroy($image);

if(file_exists($qrFile)){
    $pdf->Image($qrFile, $x + $carnetWidth - 15, $y + 2, 12, 12);
    @unlink($qrFile);
}

// ============================================
// FOTO DEL ALUMNO
// ============================================
$fotoPath = "./app/views/imagenes/fotos/alumno/" . $datos['alumno_imagen'];
if(!file_exists($fotoPath) || empty($datos['alumno_imagen'])){
    $fotoPath = "./app/views/imagenes/fotos/alumno/alumno.jpg";
}

$fotoX      = $x + $carnetWidth - 23;
$fotoY      = $y + 20;
$fotoWidth  = 20;
$fotoHeight = 25;

if(file_exists($fotoPath)){
    $pdf->Image($fotoPath, $fotoX, $fotoY, $fotoWidth, $fotoHeight);
}
$pdf->SetLineWidth(0.3);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Rect($fotoX, $fotoY, $fotoWidth, $fotoHeight);

// ============================================
// INFORMACIÓN DEL ALUMNO
// ============================================
$infoX = $x + 22;
$infoY = $y + 17;

$pdf->SetFont('Arial', 'B', 6);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($infoX, $infoY);
$pdf->Cell(40, 3, 'DEPORTISTA', 0, 0, 'L');

$infoY += 4;
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetXY($infoX, $infoY);
if(strlen(mb_convert_encoding($datos['Nombres'] . ' ' . $datos['Apellidos'], 'ISO-8859-1', 'UTF-8')) > 21){
    $pdf->MultiCell(42, 3, $nombreCompleto, 0, 'L');
    $infoY = $pdf->GetY();
} else {
    $pdf->Cell(40, 3, $nombreCompleto, 0, 0, 'L');
    $infoY += 3;
}

$infoY += 1;
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY($infoX, $infoY);
$pdf->Cell(15, 2.5, 'C.I.:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetXY($infoX + 8, $infoY);
$pdf->Cell(32, 2.5, $datos['alumno_identificacion'], 0, 0, 'L');

$infoY += 3;
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY($infoX, $infoY);
$pdf->Cell(15, 2.5, 'Horario:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetXY($infoX + 12, $infoY);
$pdf->Cell(28, 2.5,
    mb_convert_encoding($datos['horario_nombre'], 'ISO-8859-1', 'UTF-8'),
    0, 0, 'L');

$infoY += 3;
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY($infoX, $infoY);
$pdf->Cell(15, 2.5, 'Edad:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 7.5);
$pdf->SetXY($infoX + 10, $infoY);
$pdf->Cell(30, 2.5,
    $edad . ' ' . mb_convert_encoding('años', 'ISO-8859-1', 'UTF-8'),
    0, 0, 'L');

$infoY += 3;
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY($infoX, $infoY);
$pdf->Cell(15, 2.5, 'Mes vigencia:', 0, 0, 'L');

$pdf->SetFillColor($r, $g, $b);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetXY($infoX + 18, $infoY - 0.5);
$pdf->Cell(20, 3,
    mb_convert_encoding($mesNombre, 'ISO-8859-1', 'UTF-8'),
    0, 0, 'C', true);

$infoY += 3;
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->SetXY($infoX, $infoY);
$pdf->Cell(15, 2.5, 'Sede:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 8);
$pdf->SetXY($infoX + 10, $infoY);
$pdf->Cell(30, 2.5,
    mb_convert_encoding($sede['sede_nombre'], 'ISO-8859-1', 'UTF-8'),
    0, 0, 'L');

$infoY += 3.5;
$pdf->SetFont('Arial', '', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->SetXY($infoX, $infoY);
$pdf->Cell(40, 2, ds_email_organizacion((int)$sede['sede_id']), 0, 0, 'L');
$infoY += 3;
$pdf->SetXY($infoX, $infoY);
$pdf->Cell(40, 2, ds_telefono_organizacion((int)$sede['sede_id']), 0, 0, 'L');

// ============================================
// FOOTER
// ============================================
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line($x + 22, $y + $carnetHeight - 2, $x + $carnetWidth - 3, $y + $carnetHeight - 2);

$pdf->SetFont('Arial', '', 5);
$pdf->SetTextColor(150, 150, 150);
$pdf->SetXY($x + $carnetWidth - 25, $y + $carnetHeight - 4.5);
$pdf->Cell(22, 2, 'Impreso: ' . date('d/m/Y'), 0, 0, 'R');

// ============================================
// SALIDA DEL PDF
// ============================================
$nombreArchivo = 'carnet_'
    . preg_replace('/\s+/', '_', mb_convert_encoding($datos['Nombres'], 'ISO-8859-1', 'UTF-8'))
    . '_'
    . preg_replace('/\s+/', '_', mb_convert_encoding($datos['Apellidos'], 'ISO-8859-1', 'UTF-8'))
    . '.pdf';

$pdf->Output($nombreArchivo, 'I');
?>