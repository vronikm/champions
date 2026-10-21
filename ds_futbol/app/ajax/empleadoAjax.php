<?php
	
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "empleadoList";
	require_once "../views/inc/ajax_guard.php";
	
	use app\controllers\empleadoController;

	if(isset($_POST['modulo_empleado'])){

		$insEmpleado = new empleadoController();

		if($_POST['modulo_empleado']=="registrar"){
			echo $insEmpleado->registrarEmpleadoControlador();
		}

		if($_POST['modulo_empleado']=="actualizar"){
			echo $insEmpleado->actualizarEmpleadoControlador();
		}

		if($_POST['modulo_empleado']=="actualizarestado"){
			echo $insEmpleado->actualizarEstadoEmpleadoControlador();
		}		

		if($_POST['modulo_empleado']=="eliminar"){
			echo $insEmpleado->eliminarEmpleadoControlador();
		}
		
		/* RETIRADA la operacion "asignarsistema". Llamaba a
		   eliminarEmpleadoControlador(), y el guardian la clasifica como
		   «crear» (empieza por "asignar"): un rol con permiso de crear en
		   Empleados, pero no de eliminar, podia BORRAR empleados con una
		   peticion hecha a mano. Ninguna pantalla la envia ya: crear el
		   usuario de un empleado se hace en Core (usuarioForm). */
	}elseif(isset($_POST['modulo_ingreso'])){

		$insIngreso = new empleadoController();

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
		$insEgreso = new empleadoController();

		if($_POST['modulo_egreso']=="registrar"){
			echo $insEgreso->registrarEgreso();
		}

		if($_POST['modulo_egreso']=="descargoegreso"){
			echo $insEgreso->registrarDescargoEgreso();
		}

        if($_POST['modulo_egreso']=="actualizar"){
			echo $insEgreso->actualizarEgreso();
		}

        if($_POST['modulo_egreso']=="eliminar"){
			echo $insEgreso->eliminarEgreso();
		}

		if($_POST['modulo_egreso']=="eliminardescargo"){
			echo $insEgreso->eliminarDescargoEgreso();
		}
	}elseif(isset($_POST['modulo_asistencia'])){
		$insAsistencia = new empleadoController();

		if($_POST['modulo_asistencia']=="coordenadas"){
			echo $insAsistencia->AsistenciaCoordenadas();
		}
	}	
	else{
		session_destroy();
		header("Location: ".APP_URL."login/");
	}