<?php
	
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "balanceResultados";
	require_once "../views/inc/ajax_guard.php";
	
	use app\controllers\balanceController;

	if(isset($_POST['modulo_ingreso'])){

		$insIngreso = new balanceController();

		if($_POST['modulo_ingreso']=="registrar"){
			echo $insIngreso->registrarIngreso();
		}		
		if($_POST['modulo_ingreso']=="actualizar"){
			echo $insIngreso->actualizarIngreso();
		}
		if($_POST['modulo_ingreso']=="eliminar"){
			echo $insIngreso->eliminarIngreso();
		}

	}elseif(isset($_POST['modulo_egreso'])){

		$insEgreso = new balanceController();

		if($_POST['modulo_egreso']=="registrar"){
			echo $insEgreso->registrarEgreso();
		}		
		if($_POST['modulo_egreso']=="actualizar"){
			echo $insEgreso->actualizarEgreso();
		}
		if($_POST['modulo_egreso']=="eliminar"){
			echo $insEgreso->eliminarEgreso();
		}
	}
	else{
		session_destroy();
		header("Location: ".APP_URL."login/");
	}