<?php

    require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
    require_once "../../config/app.php";
    require_once "../views/inc/session_start.php";
    require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol sobre «Enlaces para adultos»
	   (migración 086), que se concede aparte de «Generar enlaces». */
	$GUARD_VISTA = "inscripcionAdultoEnlace";
	require_once "../views/inc/ajax_guard.php";

    use app\controllers\inscripcionController;

    if (empty($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode([
            "tipo"   => "simple",
            "titulo" => "Sesión expirada",
            "texto"  => "Vuelva a iniciar sesión para generar enlaces de inscripción.",
            "icono"  => "error"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    if (isset($_POST['modulo_inscripcion_adulto'])) {

        try {
            $insInscripcion = new inscripcionController();

            if ($_POST['modulo_inscripcion_adulto'] == "generar_enlace") {
                echo $insInscripcion->generarEnlaceAdultoControlador();
            }

        } catch (\Throwable $e) {

            error_log("[inscripcionAdultoEnlace] " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());

            echo json_encode([
                "tipo"   => "simple",
                "titulo" => "Error del servidor",
                "texto"  => "No se pudo generar el enlace. Intente nuevamente.",
                "icono"  => "error"
            ], JSON_UNESCAPED_UNICODE);
        }

    } else {
        session_destroy();
        header("Location: " . APP_URL . "login/");
    }
