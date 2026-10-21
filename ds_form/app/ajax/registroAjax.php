<?php

/**
 * Endpoint del formulario público de inscripción.
 *
 * Solo acepta POST y solo actúa si la sesión fue abierta por un enlace con
 * token válido y todavía vigente. La sede sale de la sesión, nunca del POST.
 */

	header("Content-Type: application/json; charset=utf-8");
	header("X-Content-Type-Options: nosniff");
	header("X-Frame-Options: DENY");

	require_once "../../config/app.php";
	require_once "../../autoload.php";

	use app\controllers\registroController;

	/*----------  Sesión (mismos parámetros que index.php)  ----------*/
	session_set_cookie_params([
		'lifetime' => 0,
		'path'     => '/',
		'httponly' => true,
		'samesite' => 'Lax'
	]);
	require_once "../views/inc/session_start.php";

	function salir($titulo, $texto, $codigo = 403) {
		http_response_code($codigo);
		echo json_encode([
			"tipo"   => "simple",
			"titulo" => $titulo,
			"texto"  => $texto,
			"icono"  => "error"
		], JSON_UNESCAPED_UNICODE);
		exit();
	}

	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		salir("Método no permitido", "Solicitud inválida.", 405);
	}

	/*----------  Guardia del enlace  ----------*/
	$sedeId   = intval($_SESSION['inscripcion_sedeid'] ?? 0);
	$expToken = intval($_SESSION['inscripcion_exp'] ?? 0);

	if (empty($_SESSION['inscripcion_valida']) || $sedeId <= 0) {
		salir("Acceso denegado", "El enlace de inscripción no es válido. Utilice el enlace que le compartió la escuela.");
	}

	// El enlace pudo vencer mientras el representante llenaba el formulario
	if (time() > $expToken) {
		salir("Enlace expirado", "El tiempo para completar la inscripción ha finalizado. Solicite un nuevo enlace a la escuela.", 410);
	}

	$insRegistro = new registroController();
	echo $insRegistro->registrar($sedeId);
