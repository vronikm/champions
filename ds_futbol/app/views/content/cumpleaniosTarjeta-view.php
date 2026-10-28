<?php
/**
 * Genera la tarjeta de cumpleaños como imagen JPEG.
 * Ruta: cumpleaniosTarjeta/{alumno_id}/
 *
 * ---------------------------------------------------------------------------
 * LO QUE CAMBIÓ, Y POR QUÉ
 *
 * Antes todo estaba escrito aquí: el marco era una ruta fija y las áreas eran
 * cuatro fracciones mágicas —0.218, 0.176, 0.558, 0.463— con el comentario
 * «ajustar estos valores si se cambia la plantilla».
 *
 * Es decir: cambiar el marco exigía editar PHP y adivinar números probando.
 * Quien decide cómo se ve la tarjeta no es quien edita PHP.
 *
 * Ahora todo eso vive en cumple_plantilla y se administra desde Core
 * (ds_core/admin → Tarjeta de cumpleaños), con un editor donde las áreas se
 * arrastran sobre el marco.
 *
 * ESTE ARCHIVO SÓLO DIBUJA. No decide nada.
 *
 *
 * LA FOTO VA DETRÁS DEL MARCO
 *
 * Se dibuja primero la foto y luego el marco encima con imagecopy(), así que
 * el PNG del marco necesita su ventana transparente. Eso permite que la foto
 * asome con la forma que tenga esa ventana —redondeada, inclinada, con
 * bordes— sin que este código sepa nada de su contorno.
 */

	ob_start();
	use app\controllers\cumpleaniosController;

	$insCumple = new cumpleaniosController();

	// Validar ID
	$alumno_id = isset($url[1]) ? (int)$insCumple->limpiarCadena($url[1]) : 0;
	if ($alumno_id <= 0) {
		http_response_code(400);
		exit('ID de alumno no válido.');
	}

	// Obtener datos del alumno
	$datos = $insCumple->infoAlumno($alumno_id);
	if ($datos->rowCount() !== 1) {
		http_response_code(404);
		exit('Alumno no encontrado.');
	}
	$alumno = $datos->fetch();

	// ── La plantilla activa ────────────────────────────────────
	$p = $insCumple->plantillaTarjeta();
	if ($p === null) {
		http_response_code(500);
		exit('No hay ninguna plantilla de tarjeta activa. Se configura en Core.');
	}

	/* El dibujo vive en app/lib/tarjeta_cumple.php: lo usa también la
	   publicación en redes sociales. */
	require_once __DIR__ . '/../../lib/tarjeta_cumple.php';
	try {
		/* ?redes=1: la versión para publicar o compartir (nombre corto). */
		$canvas = tarjeta_cumple_imagen($alumno, $p, ($_GET['redes'] ?? '') === '1');
	} catch (\RuntimeException $e) {
		http_response_code(500);
		exit($e->getMessage());
	}

	// ── Enviar imagen al navegador ─────────────────────────────
	ob_end_clean();
	header('Content-Type: image/jpeg');
	header('Content-Disposition: inline; filename="cumpleanios_' . $alumno_id . '.jpg"');
	/* Sin caché mientras se ajusta la plantilla: con max-age el navegador
	   sigue mostrando la tarjeta anterior y parece que el editor no guarda. */
	header('Cache-Control: no-store');
	imagejpeg($canvas, null, 92);
	imagedestroy($canvas);
	exit;
