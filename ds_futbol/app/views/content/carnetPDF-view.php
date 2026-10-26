<?php
use app\controllers\carnetController;

include 'app/lib/barcode.php';
include 'app/lib/alphapdf.php';
include_once 'app/lib/carnet_render.php';

$insCarnet = new carnetController();

$generator  = new barcode_generator();
$symbology  = "qr";
$optionsQR  = array('sx'=>4, 'sy'=>4, 'p'=>-12);
$tempDir    = "app/views/dist/img/temp/";
if(!is_dir($tempDir)) {
    @mkdir($tempDir, 0775, true);
}

$alumno_ids_reimpresion = '';
$carnet_ids_mensual = '';
$carnet_ids_atrasada = '';
$modo_reimpresion = false;
$modo_mensual = (isset($_GET['modo']) && $_GET['modo'] === 'mensual');
$modo_atrasada = (isset($_GET['modo']) && $_GET['modo'] === 'atrasada');
$carnetsData = [];

// Respaldo para producciÃ³n: si RewriteRule pierde el query string, recuperarlo desde REQUEST_URI.
if(!isset($_GET['modo']) && !isset($_GET['reimpresion']) && !isset($_GET['mensual']) && isset($_SERVER['REQUEST_URI'])) {
    $query_string_recuperado = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
    if(!empty($query_string_recuperado)) {
        parse_str($query_string_recuperado, $parametros_recuperados);
        foreach(['modo', 'reimpresion', 'mensual', 'atrasada', 'firma'] as $parametro_pdf) {
            if(!isset($_GET[$parametro_pdf]) && isset($parametros_recuperados[$parametro_pdf])) {
                $_GET[$parametro_pdf] = $parametros_recuperados[$parametro_pdf];
            }
        }
    }
}

$contexto_pdf = 'sin_modo';
$query_keys_pdf = implode(',', array_keys($_GET));
$session_keys_pdf = implode(',', array_keys($_SESSION ?? []));

// Reimpresion tiene prioridad. Si el servidor pierde query string, se usa la sesion generada por AJAX.
if(isset($_GET['reimpresion'], $_GET['firma']) && $_GET['reimpresion'] !== '' && $_GET['firma'] !== '') {
    $token_reimpresion = strtr($_GET['reimpresion'], '-_', '+/');
    $padding = strlen($token_reimpresion) % 4;
    if($padding > 0) {
        $token_reimpresion .= str_repeat('=', 4 - $padding);
    }

    $ids_decodificados = base64_decode($token_reimpresion, true);
    if($ids_decodificados !== false && preg_match('/^\d+(,\d+)*$/', $ids_decodificados)) {
        $firma_esperada = hash_hmac('sha256', $ids_decodificados, session_id());
        if(hash_equals($firma_esperada, $_GET['firma'])) {
            $alumno_ids_reimpresion = $ids_decodificados;
            $modo_reimpresion = true;
            $contexto_pdf = 'reimpresion_url_firmada';
        }
    }
}

if(!$modo_reimpresion && isset($_SESSION['carnet_reimpresion_ids']) && !empty($_SESSION['carnet_reimpresion_ids'])) {
    $alumno_ids_reimpresion = $_SESSION['carnet_reimpresion_ids'];
    $modo_reimpresion = true;
    $contexto_pdf = 'reimpresion_sesion';
}

if(isset($_SESSION['carnet_reimpresion_ids'])) {
    unset($_SESSION['carnet_reimpresion_ids']);
}

// Leer lote mensual firmado. Permite reabrir el PDF aunque los carnets ya queden marcados como impresos.
if(!$modo_reimpresion && isset($_GET['mensual'], $_GET['firma']) && $_GET['mensual'] !== '' && $_GET['firma'] !== '') {
    $token_mensual = strtr($_GET['mensual'], '-_', '+/');
    $padding_mensual = strlen($token_mensual) % 4;
    if($padding_mensual > 0) {
        $token_mensual .= str_repeat('=', 4 - $padding_mensual);
    }

    $ids_mensual_decodificados = base64_decode($token_mensual, true);
    if($ids_mensual_decodificados !== false && preg_match('/^\d+(,\d+)*$/', $ids_mensual_decodificados)) {
        $firma_mensual_esperada = hash_hmac('sha256', $ids_mensual_decodificados, session_id());
        if(hash_equals($firma_mensual_esperada, $_GET['firma'])) {
            $carnet_ids_mensual = $ids_mensual_decodificados;
            $modo_mensual = true;
            $contexto_pdf = 'mensual_url_firmada';
        }
    }
}

if(!$modo_reimpresion && empty($carnet_ids_mensual) && isset($_SESSION['carnet_impresion_mensual_ids']) && !empty($_SESSION['carnet_impresion_mensual_ids'])) {
    $carnet_ids_mensual = $_SESSION['carnet_impresion_mensual_ids'];
    $modo_mensual = true;
    $contexto_pdf = 'mensual_sesion';
}

if(isset($_SESSION['carnet_impresion_mensual_ids'])) {
    unset($_SESSION['carnet_impresion_mensual_ids']);
}

// Leer lote atrasado firmado. Se usa para imprimir carnets de meses anteriores sin cobro ROT.
if(!$modo_reimpresion && isset($_GET['atrasada'], $_GET['firma']) && $_GET['atrasada'] !== '' && $_GET['firma'] !== '') {
    $token_atrasada = strtr($_GET['atrasada'], '-_', '+/');
    $padding_atrasada = strlen($token_atrasada) % 4;
    if($padding_atrasada > 0) {
        $token_atrasada .= str_repeat('=', 4 - $padding_atrasada);
    }

    $ids_atrasada_decodificados = base64_decode($token_atrasada, true);
    if($ids_atrasada_decodificados !== false && preg_match('/^\d+(,\d+)*$/', $ids_atrasada_decodificados)) {
        $firma_atrasada_esperada = hash_hmac('sha256', $ids_atrasada_decodificados, session_id());
        if(hash_equals($firma_atrasada_esperada, $_GET['firma'])) {
            $carnet_ids_atrasada = $ids_atrasada_decodificados;
            $modo_atrasada = true;
            $contexto_pdf = 'atrasada_url_firmada';
        }
    }
}

if(!$modo_reimpresion && empty($carnet_ids_atrasada) && isset($_SESSION['carnet_impresion_atrasada_ids']) && !empty($_SESSION['carnet_impresion_atrasada_ids'])) {
    $carnet_ids_atrasada = $_SESSION['carnet_impresion_atrasada_ids'];
    $modo_atrasada = true;
    $contexto_pdf = 'atrasada_sesion';
}

if(isset($_SESSION['carnet_impresion_atrasada_ids'])) {
    unset($_SESSION['carnet_impresion_atrasada_ids']);
}

// Obtener carnets segun proceso firmado:
// - reimpresion: solo alumnos seleccionados con check.
// - atrasada: solo carnet_id preparados por cedulas y periodo anterior.
// - mensual: solo carnet_id preparados por el boton Imprimir Todos.
if($modo_reimpresion && !empty($alumno_ids_reimpresion)) {
    $carnetsData = $insCarnet->obtenerCarnetsReimpresion($alumno_ids_reimpresion);
} elseif($modo_atrasada && !empty($carnet_ids_atrasada)) {
    $carnetsData = $insCarnet->obtenerCarnetsAtrasadosPorIds($carnet_ids_atrasada);
} elseif($modo_mensual && !empty($carnet_ids_mensual)) {
    $carnetsData = $insCarnet->obtenerCarnetsMensualesPorIds($carnet_ids_mensual);
} elseif($modo_mensual) {
    $carnetsData = $insCarnet->obtenerCarnetsPendientesMesActual();
    if(empty($carnetsData)) {
        $carnetsData = $insCarnet->obtenerCarnetsNoImpresosMesActual();
    }
}
if(empty($carnetsData)) {
    $mensaje_pdf = "No hay carnets para imprimir.\nProceso detectado: " . $contexto_pdf .
        "\nParametros recibidos: " . ($query_keys_pdf !== "" ? $query_keys_pdf : "ninguno") .
        "\nSesion disponible: " . ($session_keys_pdf !== "" ? "si" : "no");
    error_log("[carnetPDF] sin datos contexto={$contexto_pdf} query_keys={$query_keys_pdf} session_keys={$session_keys_pdf}");
    echo "<script>
        alert(" . json_encode($mensaje_pdf, JSON_UNESCAPED_UNICODE) . ");
        window.history.back();
    </script>";
    exit;
}

// Obtener resumen de impresión
$resumen = $insCarnet->obtenerResumenImpresion($carnetsData);

// Obtener información de la escuela
// La información de la sede se obtiene dentro del bucle de cada carnet
// ya que cada alumno puede estar en una sede diferente

// ============================================
// CONFIGURACIÓN PDF
// ============================================
$pdf = new AlphaPDF('P', 'mm', 'A4');
$pdf->SetAutoPagebreak(false);
$pdf->SetMargins(0, 0, 0);

// Dimensiones del carnet (tamaño tarjeta de crédito estándar)
$carnetWidth = 85.6;   // mm
$carnetHeight = 53.98; // mm
$carnetsPerRow = 2;
$carnetsPerCol = 5;
$carnetsPerPage = 10;

// Dimensiones de la página A4
$pageWidth = 210;  // mm
$pageHeight = 297; // mm

// Espaciado entre carnets
$espacioX = 0;
$espacioY = 0;

// Calcular márgenes para centrar
$totalWidth = ($carnetsPerRow * $carnetWidth) + (($carnetsPerRow - 1) * $espacioX);
$totalHeight = ($carnetsPerCol * $carnetHeight) + (($carnetsPerCol - 1) * $espacioY);
$margenX = ($pageWidth - $totalWidth) / 2;
$margenY = ($pageHeight - $totalHeight) / 2;

$totalCarnets = count($carnetsData);
// El diseño (Core › Carnets) se lee una vez para todo el lote.
$disenoCarnet = $insCarnet->disenoCarnet();
$carnetCounter = 0;

// Nombres de meses
$nombresMeses = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// ============================================
// GENERAR CARNETS
// ============================================
foreach($carnetsData as $carnet) {
    // Nueva página cada 10 carnets
    if($carnetCounter % $carnetsPerPage == 0) {
        $pdf->AddPage();
        
        // ============================================
        // ENCABEZADO DE PÁGINA CON RESUMEN
        // ============================================
        if($carnetCounter == 0) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY(10, 5);
            $pdf->Cell(0, 5, 'IMPRESION DE CARNETS - ' . strtoupper($nombresMeses[date('n')]) . ' ' . date('Y'), 0, 0, 'L');
            
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetXY(10, 10);
            $resumenTexto = 'Total: ' . $resumen['total'] . ' carnets';
            if($resumen['nuevos'] > 0) {
                $resumenTexto .= ' | Nuevos: ' . $resumen['nuevos'];
            }
            if($resumen['reimpresiones'] > 0) {
                $resumenTexto .= ' | Reimpresiones: ' . $resumen['reimpresiones'];
            }
            $pdf->Cell(0, 4, $resumenTexto, 0, 0, 'L');
        }
    }
    
    // Calcular posición del carnet
    $posEnPagina = $carnetCounter % $carnetsPerPage;
    $fila = floor($posEnPagina / $carnetsPerRow);
    $columna = $posEnPagina % $carnetsPerRow;
    
    $x = $margenX + ($columna * ($carnetWidth + $espacioX));
    $y = $margenY + ($fila * ($carnetHeight + $espacioY));
    
    // Determinar si es reimpresión
    $esReimpresion = ($carnet['es_reimpresion'] == 1);
    
    // Obtener información de la sede del alumno
    $sede = $insCarnet->informacionSede($carnet['alumno_sedeid']);
    if($sede->rowCount() != 1) {
        // Si no se encuentra la sede, usar una por defecto (sede 1)
        $sede = $insCarnet->informacionSede(1);
        if($sede->rowCount() != 1) {
            continue; // Saltar este carnet si no hay sede disponible
        }
    }
    $sede = $sede->fetch();
    
    // Color del mes
    $colorHex = $carnet['color_hex'];
    list($r, $g, $b) = sscanf($colorHex, "#%02x%02x%02x");
    
    // ====================
    // EL CARNET: lo dibuja app/lib/carnet_render.php con el diseño de
    // Core › Carnets (el mismo que usa el carnet suelto).
    // ====================
    $estadoAlumno = $insCarnet->EstadoAlumno($carnet['alumno_id']);
    if($estadoAlumno->rowCount() == 1) {
        $estadoAlumno = $estadoAlumno->fetch();
        $condicion = $estadoAlumno['Condicion'];
        $fechaUltPension = $estadoAlumno['FechaUltPension'];
    } else {
        $condicion = 'Pendiente';
        $fechaUltPension = date('Y-m-d');
    }
    $marcaReimpresion = $esReimpresion ? "\nREIMPRESION: SI" : "";
    $qrData = "Estado pension: " . $condicion . "\n" .
              "Fecha ultimo pago: " . $fechaUltPension . "\n" .
              "Sede: " . $sede['sede_nombre'] . "\n" .
              $sede['sede_telefono'] . $marcaReimpresion;
    $qrFile = $tempDir . "qr_" . $carnet['alumno_id'] . "_" . time() . "_" . rand(1000,9999) . ".jpeg";
    $image = $generator->render_image($symbology, $qrData, $optionsQR);
    imagejpeg($image, $qrFile);
    imagedestroy($image);

    $fotoPath = "./app/views/imagenes/fotos/alumno/" . $carnet['alumno_imagen'];
    if(!file_exists($fotoPath) || empty($carnet['alumno_imagen'])) {
        $fotoPath = "./app/views/imagenes/fotos/alumno/alumno.jpg";
    }

    $infoAlumno = $insCarnet->infoAlumnoCarnet($carnet['alumno_id']);
    $edad = 0;
    if($infoAlumno->rowCount() == 1) {
        $infoAlumno = $infoAlumno->fetch();
        $edad = date_diff(date_create($infoAlumno['alumno_fechanacimiento']), date_create('today'))->y;
    }

    carnet_dibujar($pdf, $x, $y, [
        'color'       => [$r, $g, $b],
        'img_fondo'   => carnet_dir_imagenes() . $sede['escuela_verticalfondo'],
        'img_linea'   => carnet_dir_imagenes() . $sede['escuela_verticalprincipal'],
        'sede_id'     => (int)$sede['sede_id'],
        'qr'          => $qrFile,
        'foto'        => $fotoPath,
        'nombre'      => $carnet['alumno_nombre'],
        'ci'          => $carnet['alumno_identificacion'],
        'horario'     => (string)$carnet['horario_nombre'],
        'edad'        => $edad,
        'mes'         => $nombresMeses[$carnet['carnet_mes']] ?? 'N/A',
        'sede_nombre' => $sede['sede_nombre'],
        'correo'      => ds_email_organizacion((int)$sede['sede_id']),
        'telefono'    => ds_telefono_organizacion((int)$sede['sede_id']),
        'reimpresion' => $esReimpresion,
        'fecha'       => date('d/m/Y'),
    ], $disenoCarnet);
    @unlink($qrFile);

    $carnetCounter++;
}

// Registrar impresión solo de carnets NUEVOS
$carnet_ids_nuevos = [];
foreach($carnetsData as $carnet) {
    if($carnet['es_reimpresion'] == 0 && !empty($carnet['carnet_id'])) {
        $carnet_ids_nuevos[] = $carnet['carnet_id'];
    }
}

if(!empty($carnet_ids_nuevos)) {
    $insCarnet->registrarImpresion($carnet_ids_nuevos);
}

// Nombre del archivo
$nombreArchivo = "Carnets_" . date('Y-m-d_His') . ".pdf";
if($modo_atrasada) {
    $nombreArchivo = "Carnets_Atrasados_" . date('Y-m-d_His') . ".pdf";
} elseif($resumen['reimpresiones'] > 0 && $resumen['nuevos'] == 0) {
    $nombreArchivo = "Carnets_Reimpresion_" . date('Y-m-d_His') . ".pdf";
} elseif($resumen['reimpresiones'] > 0 && $resumen['nuevos'] > 0) {
    $nombreArchivo = "Carnets_Mixto_" . date('Y-m-d_His') . ".pdf";
}

// Salida del PDF
$pdf->Output($nombreArchivo, "I", "T");
?>
