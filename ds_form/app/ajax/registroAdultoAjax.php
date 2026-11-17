<?php

/**
 * Endpoint del formulario público de ALUMNOS ADULTOS (ds_form/adulto.php, migración 086).
 *
 * Sólo POST y sólo si la sesión la abrió un enlace de adultos válido y
 * vigente. La sede sale de la sesión, nunca del POST. Sesión propia
 * (adulto_*): no comparte nada con el enlace de menores.
 */

	header("Content-Type: application/json; charset=utf-8");
	header("X-Content-Type-Options: nosniff");
	header("X-Frame-Options: DENY");

	require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
	require_once "../../config/app.php";
	require_once "../../autoload.php";

	use app\controllers\registroAdultoController;

	session_set_cookie_params([
		'lifetime' => 0,
		'path'     => '/',
		'httponly' => true,
		'samesite' => 'Lax'
	]);
	require_once "../views/inc/session_start.php";

	function salir($titulo, $texto, $codigo = 403) {
		http_response_code($codigo);
		echo json_encode(["tipo" => "simple", "titulo" => $titulo, "texto" => $texto, "icono" => "error"],
		                 JSON_UNESCAPED_UNICODE);
		exit();
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		salir("Método no permitido", "Solicitud inválida.", 405);
	}

	$sedeId   = intval($_SESSION['adulto_sedeid'] ?? 0);
	$expToken = intval($_SESSION['adulto_exp'] ?? 0);

	if (empty($_SESSION['adulto_valida']) || $sedeId <= 0) {
		salir("Acceso denegado", "El enlace de inscripción no es válido. Utilice el enlace que le compartió la escuela.");
	}

	if (time() > $expToken) {
		salir("Enlace expirado", "El tiempo para completar la inscripción ha finalizado. Solicite un nuevo enlace a la escuela.", 410);
	}

	$insRegistro = new registroAdultoController();

	/* La sede pudo inactivarse, o dejar de admitir adultos, mientras se llenaba el formulario. */
	if ($insRegistro->sedeAdmite($sedeId) === null) {
		salir("Inscripciones cerradas", "Esta sede no está recibiendo inscripciones de alumnos adultos. Comuníquese con la escuela.", 410);
	}

	echo $insRegistro->registrar($sedeId);
