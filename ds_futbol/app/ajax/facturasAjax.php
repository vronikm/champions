<?php

	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "facturasList";
	require_once "../views/inc/ajax_guard.php";

	use app\controllers\facturasController;

	$insAlumno  = new facturasController();

	if(isset($_GET['modulo_facturas'])){
		$modulo = $_GET['modulo_facturas'];
		$facturaId = $_GET['factura_id'] ?? 0;

		if($modulo=="DESCARGAR_XML"){
			$tipoXml = $_GET['tipo'] ?? 'xml';
			$archivo = $insAlumno->obtenerArchivoFacturaElectronica($facturaId, $tipoXml);
			if(!$archivo){ http_response_code(404); echo "XML no encontrado"; exit(); }
			header('Content-Type: '.$archivo['content_type']);
			header('Content-Disposition: attachment; filename="'.$archivo['filename'].'"');
			readfile($archivo['path']);
			exit();
		}

		if($modulo=="VER_RIDE"){
			$archivo = $insAlumno->obtenerArchivoFacturaElectronica($facturaId, 'ride');
			if(!$archivo){ http_response_code(404); echo "RIDE no encontrado"; exit(); }
			header('Content-Type: '.$archivo['content_type']);
			readfile($archivo['path']);
			exit();
		}

		/* Estado del certificado de firma, en JSON.
		   Lo consume la pantalla de configuracion, que vive en Core y no
		   puede cargar este controlador: los dos modulos declaran su
		   propia constante APP_URL y chocarian. Asi el certificado se lee
		   en un solo sitio, aqui, junto al resto del motor del SRI. */
		if($modulo=="INFO_CERTIFICADO_SRI"){
			header('Content-Type: application/json; charset=utf-8');
			if(!es_superadministrador()){
				http_response_code(403);
				echo json_encode(['estado'=>'NO_AUTORIZADO']);
				exit();
			}
			$info = $insAlumno->obtenerInfoCertificadoSri();
			unset($info['ruta']);   // ruta absoluta del servidor: no sale
			echo json_encode($info);
			exit();
		}
	}

	if(isset($_POST['modulo_facturas'])){

		if($_POST['modulo_facturas']=="ACTUALIZAR_REPRESENTANTE"){
			echo $insAlumno->actualizarRepresentanteFactura();
			exit();
		}

		if($_POST['modulo_facturas']=="GUARDAR_CONFIG_SRI"){
			echo $insAlumno->guardarConfiguracionSri();
			exit();
		}

		if($_POST['modulo_facturas']=="SUBIR_CERTIFICADO_SRI"){
			echo $insAlumno->subirCertificadoSri();
			exit();
		}

		if($_POST['modulo_facturas']=="PROBAR_CERTIFICADO_SRI"){
			echo $insAlumno->probarCertificadoSri();
			exit();
		}

		if($_POST['modulo_facturas']=="PROBAR_CONEXION_SRI"){
			echo $insAlumno->probarConexionSri();
			exit();
		}

		if($_POST['modulo_facturas']=="GENERAR_FACTURA_ELECTRONICA"){
			echo $insAlumno->generarFacturaElectronica();
			exit();
		}

		if($_POST['modulo_facturas']=="EMITIR_FACTURA_SRI"){
			echo $insAlumno->emitirFacturaElectronicaSri();
			exit();
		}

		if($_POST['modulo_facturas']=="REGENERAR_FACTURA_DEVUELTA"){
			echo $insAlumno->regenerarFacturaElectronicaDevuelta();
			exit();
		}

		if($_POST['modulo_facturas']=="CONSULTAR_FACTURA_SRI"){
			echo $insAlumno->consultarFacturaElectronicaSri();
			exit();
		}

		if($_POST['modulo_facturas']=="ENVIAR_FACTURA_CORREO"){
			echo $insAlumno->enviarFacturaElectronicaCorreo();
			exit();
		}

		if($_POST['modulo_facturas']=="CONSULTAR_FACTURAS"){
			$alumno = $_POST['alumno'];
			$fecha_inicio = $_POST['fecha_inicio'];
			$fecha_fin = $_POST['fecha_fin'];

			$datos = $insAlumno->BuscarAlumnoFactura($alumno, $fecha_inicio, $fecha_fin);
			$pagos = $insAlumno->listarPagosFactura($alumno, $fecha_inicio, $fecha_fin);
			// "Facturas generadas" siempre muestra todas (ignora el filtro de fecha)
			$facturas = $insAlumno->listarFacturasGeneradas($alumno, '', '');
			$facturasGeneradas = $insAlumno->contarFacturasGeneradas($alumno, '', '');

			$representante = [];
			if($datos && $datos->rowCount()>0){
				$fila = $datos->fetch();
				$representante = [
					"nombre" => $fila['representante'],
					"identificacion" => $fila['repre_identificacion'],
					"direccion" => $fila['repre_direccion'],
					"correo" => $fila['repre_correo'],
					"celular" => $fila['repre_celular'],
					"pagos" => $fila['pagos'],
					"facturas" => $facturasGeneradas
				];
			}

			header('Content-Type: application/json; charset=UTF-8');
			echo json_encode([
				"pagos" => $pagos,
				"facturas" => $facturas,
				"representante" => $representante
			], JSON_UNESCAPED_UNICODE);
			exit();
		}
	}else{
		session_destroy();
		header("Location: ".APP_URL."login/");
	}
