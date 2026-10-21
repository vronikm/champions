<?php

	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "alumnoList";
	require_once "../views/inc/ajax_guard.php";

	use app\controllers\importarAlumnosController;

	if (isset($_POST['modulo_import'])) {

		$insImport = new importarAlumnosController();

		if ($_POST['modulo_import'] === 'analizar') {
			echo $insImport->analizarCSVControlador();
		} elseif ($_POST['modulo_import'] === 'cargar') {
			echo $insImport->cargarCSVControlador();
		}

	} else {
		session_destroy();
		header("Location: ".APP_URL."login/");
	}
