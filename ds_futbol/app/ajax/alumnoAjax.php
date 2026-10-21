<?php
	
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "alumnoList";
	require_once "../views/inc/ajax_guard.php";
	
	use app\controllers\alumnoController;

	if(isset($_POST['modulo_alumno'])){

		$insAlumno = new alumnoController();

		if($_POST['modulo_alumno']=="registrar"){
			echo $insAlumno->registrarAlumnoControlador();
		}

		if($_POST['modulo_alumno']=="eliminar"){
			echo $insAlumno->eliminarAlumnoControlador();
		}

		if($_POST['modulo_alumno']=="actualizar"){
			echo $insAlumno->actualizarAlumnoControlador();
		}

		if($_POST['modulo_alumno']=="actualizarestado"){
			echo $insAlumno->actualizarEstadoAlumnoControlador();
		}

		/* eliminarFoto / actualizarFoto se retiraron: venían de la plantilla
		   y escribían en la tabla «usuario», que en esta base no existe, así
		   que acababan en error fatal. Ninguna pantalla las llamaba. La foto
		   del alumno la gestiona fotoAlumnoAjax.php. */

		// Nuevo bloque para cargar horarios dinámicamente
		//echo $datos=$insAlumno->generarHorarioProfile($horarioid);
		if ($_POST['modulo_alumno'] == "cargarHorario" && isset($_POST['horarioid'])) {
			echo $insAlumno->generarHorarioProfile($_POST['horarioid']);
		}
		
	}else{
		session_destroy();
		header("Location: ".APP_URL."login/");
	}