<?php
	
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "torneoList";
	require_once "../views/inc/ajax_guard.php";
	
	use app\controllers\torneoController;

	if(isset($_POST['modulo_torneo'])){

		$insTorneo = new torneoController();

		if($_POST['modulo_torneo']=="registrar"){
			echo $insTorneo->registrarTorneoControlador();
		}

		if($_POST['modulo_torneo']=="actualizar"){
			echo $insTorneo->actualizarTorneoControlador();
		}

		if($_POST['modulo_torneo']=="actualizarestado"){
			echo $insTorneo->actualizarEstadoTorneoControlador();
		}		

		if($_POST['modulo_torneo']=="eliminar"){
			echo $insTorneo->eliminarTorneoControlador();
		}		
	}else{
		session_destroy();
		header("Location: ".APP_URL."login/");
	}