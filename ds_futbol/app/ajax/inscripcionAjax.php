<?php

    require_once "../../config/app.php";
    require_once "../views/inc/session_start.php";
    require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol.
	   Este endpoint sirve una sola pantalla —generar enlaces— y ahora que
	   esa vista esta registrada en el menu se valida contra ella. Antes
	   apuntaba a inscripcionPendientes solo porque inscripcionEnlace no
	   figuraba en seguridad_menu, y eso mezclaba dos permisos distintos:
	   consultar inscripciones y emitir enlaces de alta. */
	$GUARD_VISTA = "inscripcionEnlace";
	require_once "../views/inc/ajax_guard.php";

    use app\controllers\inscripcionController;

    /* El token de inscripción se firma con la clave del sistema: solo un
       usuario autenticado puede pedir que se genere uno. */
    if (empty($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode([
            "tipo"   => "simple",
            "titulo" => "Sesión expirada",
            "texto"  => "Vuelva a iniciar sesión para generar enlaces de inscripción.",
            "icono"  => "error"
        ]);
        exit();
    }

    if (isset($_POST['modulo_inscripcion'])) {

        /* Cualquier fallo se responde como JSON. Un 500 con HTML de error
           llega al navegador como "No se pudo generar el enlace", que no
           dice nada de la causa real. */
        try {

            $insInscripcion = new inscripcionController();

            if ($_POST['modulo_inscripcion'] == "generar_enlace") {
                echo $insInscripcion->generarEnlaceControlador();
            }

        } catch (\Throwable $e) {

            error_log("[inscripcionEnlace] " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());

            echo json_encode([
                "tipo"   => "simple",
                "titulo" => "Error del servidor",
                "texto"  => "No se pudo generar el enlace: " . $e->getMessage(),
                "icono"  => "error"
            ], JSON_UNESCAPED_UNICODE);
        }

    } else {
        session_destroy();
        header("Location: " . APP_URL . "login/");
    }
