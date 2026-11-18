<?php

	require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol sobre «Marcar entrada y
	   salida». Antes la marcación iba por empleadoAjax.php, que exige el
	   permiso de la pantalla Empleados: un entrenador sin acceso a la
	   ficha de empleados recibía «Acceso denegado» al marcar su asistencia.
	   El controlador toma al empleado de la sesión, nunca del POST. */
	$GUARD_VISTA = "empleadoEntrada";
	require_once "../views/inc/ajax_guard.php";

	use app\controllers\empleadoController;

	if (isset($_POST['modulo_asistencia']) && $_POST['modulo_asistencia'] === "coordenadas") {
		$insAsistencia = new empleadoController();
		echo $insAsistencia->AsistenciaCoordenadas();
	} else {
		session_destroy();
		header("Location: " . APP_URL . "login/");
	}
