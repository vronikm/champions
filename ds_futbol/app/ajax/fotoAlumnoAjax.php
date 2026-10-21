<?php

	/*
	| Endpoint de las fotos de alumnos.
	|
	| Declara su PROPIA vista y no reutiliza la de alumnoList. Podría parecer
	| lo cómodo —al fin y al cabo son los mismos alumnos— pero entonces quien
	| tuviera permiso de editar alumnos podría subir fotos aunque la escuela no
	| le haya dado esta pantalla, y quien tenga esta pantalla no podría usarla
	| sin permisos sobre la ficha completa. El permiso ha de ser el de la
	| pantalla que se está usando.
	*/

	require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + testigo CSRF + permiso de rol */
	$GUARD_VISTA = "alumnoFotos";
	require_once "../views/inc/ajax_guard.php";

	use app\controllers\fotoAlumnoController;

	if (!isset($_POST['modulo_fotoalumno'])) {
		http_response_code(400);
		echo json_encode([
			"tipo"   => "simple",
			"titulo" => "Solicitud incompleta",
			"texto"  => "No se indicó la operación a realizar.",
			"icono"  => "error",
		], JSON_UNESCAPED_UNICODE);
		exit;
	}

	$insFoto = new fotoAlumnoController();

	switch ($_POST['modulo_fotoalumno']) {

		case 'guardarFoto':
			echo $insFoto->guardarFoto();
			break;

		default:
			http_response_code(400);
			echo json_encode([
				"tipo"   => "simple",
				"titulo" => "Operación desconocida",
				"texto"  => "La operación solicitada no existe en este módulo.",
				"icono"  => "error",
			], JSON_UNESCAPED_UNICODE);
	}
