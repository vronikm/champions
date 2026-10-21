<?php
	
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "torneoList";
	require_once "../views/inc/ajax_guard.php";
	
	use app\controllers\equipoController;

	if(isset($_POST['modulo_equipo'])){

		$insEquipo = new equipoController();

		if($_POST['modulo_equipo']=="registrar"){
			echo $insEquipo->registrarEquipoControlador();
		}

		if($_POST['modulo_equipo']=="actualizar"){
			echo $insEquipo->actualizarEquipoControlador();
		}

		if($_POST['modulo_equipo']=="actualizarestado"){
			echo $insEquipo->actualizarEstadoEquipoControlador();
		}		

		if($_POST['modulo_equipo']=="eliminar"){
			echo $insEquipo->eliminarEquipoControlador();
		}		
	}else{
		session_destroy();
		header("Location: ".APP_URL."login/");
	}