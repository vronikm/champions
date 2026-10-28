<?php

	require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "consentimientoList";
	require_once "../views/inc/ajax_guard.php";

	use app\controllers\consentimientoController;

	if (empty($_SESSION['usuario'])) {
		http_response_code(401);
		echo json_encode([
			"tipo"   => "simple",
			"titulo" => "Sesión expirada",
			"texto"  => "Vuelva a iniciar sesión.",
			"icono"  => "error"
		]);
		exit();
	}

	if (isset($_POST['modulo_consentimiento'])) {

		$insConsentimiento = new consentimientoController();

		if ($_POST['modulo_consentimiento'] == "registrar") {
			echo $insConsentimiento->registrarConsentimientoControlador();
		}

		if ($_POST['modulo_consentimiento'] == "historial") {
			echo $insConsentimiento->historialHTMLControlador();
		}

		/* El enlace de autorización (permiso crear, como registrar). */
		if ($_POST['modulo_consentimiento'] == "generar_enlace") {
			echo $insConsentimiento->generarEnlaceControlador();
		}

		if ($_POST['modulo_consentimiento'] == "generar_envio") {
			echo $insConsentimiento->generarEnvioControlador();
		}

		if ($_POST['modulo_consentimiento'] == "generar_correo") {
			echo $insConsentimiento->generarCorreoControlador();
		}

	} else {
		session_destroy();
		header("Location: " . APP_URL . "login/");
	}
