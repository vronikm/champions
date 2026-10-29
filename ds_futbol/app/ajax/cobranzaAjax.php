<?php

	require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol. «enviar_*» pide editar,
	   lo mismo que muestra el botón Notificar, sobre la cobranza de la que
	   viene el aviso: pensiones o uniformes (sólo esas dos). */
	$GUARD_VISTA = (($_POST['vista'] ?? '') === 'cobranzaUniforme') ? "cobranzaUniforme" : "cobranzaPension";
	require_once "../views/inc/ajax_guard.php";

	use app\controllers\cobranzaController;

	header('Content-Type: application/json; charset=utf-8');

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

	if (($_POST['modulo_cobranza'] ?? '') === "enviar_correo") {
		echo (new cobranzaController())->enviarCorreoControlador($GUARD_VISTA);
	} else {
		session_destroy();
		header("Location: " . APP_URL . "login/");
	}
