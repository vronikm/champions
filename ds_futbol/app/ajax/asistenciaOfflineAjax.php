<?php
	/*
	| Asistencia sin conexión (PWA): lo que usa asistencia-offline.js.
	|
	|   GET  ?accion=sesion                     ¿hay sesión?, de quién y un testigo CSRF
	|                                           fresco (el de una página guardada en el
	|                                           celular ya no vale: era de otra sesión).
	|   POST modulo_asistencia=asistencia_lote  las marcas tomadas sin señal, en lote
	|        marcas=<JSON>                      (asistenciaController::registrarMarcasLote).
	|
	| Mismo guardia que asistenciaAjax.php: sesión, origen, testigo CSRF en
	| POST y permiso sobre «asistencia». «asistencia_lote» se clasifica como
	| registrar, igual que la marca de una en una.
	*/

	require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	$GUARD_VISTA = "asistencia";
	require_once "../views/inc/ajax_guard.php";

	use app\controllers\asistenciaController;

	header('Content-Type: application/json; charset=utf-8');
	header('Cache-Control: no-store');

	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['accion'] ?? '') === 'sesion') {
		echo json_encode([
			'ok'      => true,
			'usuario' => (int)($_SESSION['usuarioid'] ?? 0),
			'csrf'    => csrf_token(),
		]);
		exit;
	}

	if (($_POST['modulo_asistencia'] ?? '') === 'asistencia_lote') {
		echo (new asistenciaController())->registrarMarcasLoteJson();
		exit;
	}

	http_response_code(400);
	echo json_encode(['ok' => false, 'texto' => 'Operación no reconocida.'], JSON_UNESCAPED_UNICODE);
