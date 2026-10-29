<?php
require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
require_once "../../config/app.php";
require_once "../views/inc/session_start.php";
require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol. La configuración de carnets
	   (colores y diseño) es de la pantalla Core › Carnets: su permiso es el de
	   ESA pantalla (Core › Permisos), no el de «Carnets del mes». */
	$GUARD_VISTA = "carnetList";
	if (in_array($_POST['modulo_carnet'] ?? '', ['actualizar_colores', 'crear_color', 'editar_color', 'retirar_color',
	        'guardar_diseno', 'restaurar_diseno', 'subir_imagen_carnet', 'restaurar_imagen_carnet'], true)
	    || ($_GET['modulo_carnet'] ?? '') === 'muestra_pdf') {
		$GUARD_VISTA_CORE = "carnetConfig";
	}
	require_once "../views/inc/ajax_guard.php";

use app\controllers\carnetController;

/* Carnet de muestra en PDF (Core › Carnets › Diseño del carnet). GET: sólo
   lee y dibuja. Quien puede ver la pantalla Core › Carnets. */
if(($_GET['modulo_carnet'] ?? '') === 'muestra_pdf') {
    if(!usuario_tiene_permiso('carnetConfig', 'core')) { http_response_code(403); exit('Acceso restringido'); }
    (new carnetController())->muestraCarnetPdf();
    exit();
}

if(isset($_POST['modulo_carnet'])) {
    
    $insCarnet = new carnetController();
    
    switch($_POST['modulo_carnet']) {
        
        /* Configuracion de carnets: color de cada mes y politica de
           reimpresion. La pantalla vive en Core (permiso de Core › Carnets);
           esto cierra la puerta de atras. Imprimir carnets, en cambio,
           depende del permiso de rol sobre carnetList, como el resto. */
        case 'actualizar_colores':
            if(!puede('editar', 'carnetConfig', 'core')){
                echo json_encode([
                    "tipo"   => "simple",
                    "titulo" => "Acceso restringido",
                    "texto"  => "Su rol no puede cambiar la configuracion de carnets.",
                    "icono"  => "error"
                ]);
                break;
            }
            echo $insCarnet->actualizarColoresMeses();
            break;

        /* Catalogo de colores: alta, cambio de tonalidad y retiro.
           Misma puerta que actualizar_colores —la pantalla vive en Core y
           es de superadministrador—, y por el mismo motivo: el color del
           carnet es el dato que permite reconocer el mes de un vistazo, y
           tocarlo afecta a todo lo que ya circula.

           La comprobacion de si un color tiene carnets NO esta aqui sino
           en el controlador: una regla de datos no se guarda en la puerta,
           porque la puerta se puede rodear llamando al controlador desde
           otro sitio. */
        case 'crear_color':
        case 'editar_color':
        case 'retirar_color':
            if(!puede(['crear_color' => 'crear', 'editar_color' => 'editar', 'retirar_color' => 'eliminar'][$_POST['modulo_carnet']], 'carnetConfig', 'core')){
                echo json_encode([
                    "tipo"   => "simple",
                    "titulo" => "Acceso restringido",
                    "texto"  => "Su rol no puede administrar el catálogo de colores.",
                    "icono"  => "error"
                ], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo match($_POST['modulo_carnet']) {
                'crear_color'   => $insCarnet->crearColorCarnet(),
                'editar_color'  => $insCarnet->editarColorCarnet(),
                'retirar_color' => $insCarnet->retirarColorCarnet(),
            };
            break;


        /* Diseño del carnet: qué elementos lleva, medidas e imágenes.
           Pantalla de Core (permiso de Core › Carnets); aquí se cierra la
           puerta de atrás, como con los colores. */
        case 'guardar_diseno':
        case 'restaurar_diseno':
        case 'subir_imagen_carnet':
        case 'restaurar_imagen_carnet':
            if(!puede('editar', 'carnetConfig', 'core')){
                echo json_encode(["tipo" => "simple", "titulo" => "Acceso restringido",
                    "texto" => "Su rol no puede cambiar el diseño del carnet.", "icono" => "error"], JSON_UNESCAPED_UNICODE);
                break;
            }
            echo match($_POST['modulo_carnet']) {
                'guardar_diseno'          => $insCarnet->guardarDisenoCarnet(),
                'restaurar_diseno'        => $insCarnet->restaurarDisenoCarnet(),
                'subir_imagen_carnet'     => $insCarnet->subirImagenCarnet(),
                'restaurar_imagen_carnet' => $insCarnet->restaurarImagenCarnet(),
            };
            break;

        case 'obtener_color':
            // Para obtener el color hexadecimal via AJAX
            if(isset($_POST['color_id'])) {
                $color_id = $_POST['color_id'];
                $color_hex = $insCarnet->obtenerColorHex($color_id);
                echo json_encode(['color_hex' => $color_hex]);
            }
            break;
            
        case 'imprimir_carnetspendientes':
            // Obtener carnets pendientes
            $resultado = $insCarnet->carnetPendientesImpresion((int)($_POST['sede'] ?? 0));
            
            // Retornar JSON con el total
            if(isset($resultado[0]['total'])) {
                echo json_encode([
                    "tipo" => "success",
                    "total" => (int)$resultado[0]['total']
                ]);
            } else {
                echo json_encode([
                    "tipo" => "error",
                    "total" => 0,
                    "mensaje" => "No se pudo obtener el total de carnets"
                ]);
            }
            break;


        case 'preparar_impresion_mensual':
            echo $insCarnet->prepararImpresionMensual();
            break;
        case 'preparar_impresion_atrasada':
            echo $insCarnet->prepararImpresionAtrasada();
            break;
        case 'procesar_reimpresion':
            echo $insCarnet->procesarReimpresion();
            break;

        default:
            $alerta = [
                "tipo" => "simple",
                "titulo" => "Error",
                "texto" => "Módulo no reconocido",
                "icono" => "error"
            ];
            echo json_encode($alerta);
            break;
    }
    
} else {
    session_destroy();
    header("Location: ".APP_URL."login/");
}
?>
