<?php

	require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol sobre «Nuevo alumno adulto»
	   (migración 086). La operación «registrar» exige permiso de crear. */
	$GUARD_VISTA = "alumnoAdultoNew";
	require_once "../views/inc/ajax_guard.php";

	use app\controllers\alumnoAdultoController;

	if (isset($_POST['modulo_alumno_adulto'])) {

		try {
			$insAdulto = new alumnoAdultoController();

			if ($_POST['modulo_alumno_adulto'] == "registrar") {
				echo $insAdulto->registrarAdultoControlador();
			}
		} catch (\Throwable $e) {
			error_log("[alumnoAdultoNew] " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
			echo json_encode([
				"tipo"   => "simple",
				"titulo" => "Error del servidor",
				"texto"  => "No se pudo registrar al alumno. Intente nuevamente.",
				"icono"  => "error"
			], JSON_UNESCAPED_UNICODE);
		}

	} else {
		session_destroy();
		header("Location: " . APP_URL . "login/");
	}
