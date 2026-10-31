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
$pdf->dsSinPie = true;   /* un carné no lleva el pie de Core › Organización */
$pdf->SetAutoPagebreak(false);
$pdf->SetMargins(0, 0, 0);
$pdf->AddPage();

$x = 0;
$y = 0;

// ============================================
// EL CARNET: lo dibuja app/lib/carnet_render.php con el diseño de
// Core › Carnets (el mismo que usa el lote del mes).
// ============================================
include_once 'app/lib/carnet_render.php';

// Código QR: el de este carnet suelto lleva también el correo de la sede.
$qrData = "Estado pension: " . $condicion . "\n" .
          "Fecha ultimo pago: " . $fechaUltPension . "\n" .
          "Sede: " . $sede['sede_nombre'] . "\n" .
          $sede['sede_telefono'] . "\n" .
          $sede['sede_email'];
$qrFile = $tempDir . "qr_" . $alumnoid . "_" . time() . "_" . rand(1000,9999) . ".jpeg";
$image  = $generator->render_image($symbology, $qrData, $optionsQR);
imagejpeg($image, $qrFile);
imagedestroy($image);

$fotoPath = "./app/views/imagenes/fotos/alumno/" . $datos['alumno_imagen'];
if(!file_exists($fotoPath) || empty($datos['alumno_imagen'])){
    $fotoPath = "./app/views/imagenes/fotos/alumno/alumno.jpg";
}

carnet_dibujar($pdf, $x, $y, [
    'color'       => [$r, $g, $b],
    'img_fondo'   => carnet_dir_imagenes() . $sede['escuela_verticalfondo'],
    'img_linea'   => carnet_dir_imagenes() . $sede['escuela_verticalprincipal'],
    'sede_id'     => (int)$sede['sede_id'],
    'qr'          => $qrFile,
    'foto'        => $fotoPath,
    'nombre'      => $datos['Nombres'] . ' ' . $datos['Apellidos'],
    'ci'          => $datos['alumno_identificacion'],
    'horario'     => (string)($datos['horario_nombre'] ?? ''),
    'edad'        => $edad,
    'mes'         => $nombresMeses[$mesActual] ?? 'N/A',
    'sede_nombre' => $sede['sede_nombre'],
    'correo'      => ds_email_organizacion((int)$sede['sede_id']),
    'telefono'    => ds_telefono_organizacion((int)$sede['sede_id']),
    'reimpresion' => false,
    'fecha'       => date('d/m/Y'),
], carnet_diseno_leer($insCarnet->conexionCarnet()));
@unlink($qrFile);

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
