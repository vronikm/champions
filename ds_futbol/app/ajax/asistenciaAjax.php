<?php
	
	require_once __DIR__ . "/../../../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "asistencia";
	require_once "../views/inc/ajax_guard.php";
	
	use app\controllers\asistenciaController;

	if(isset($_POST['modulo_asistencia'])){

		$insPago = new asistenciaController();

		if($_POST['modulo_asistencia']=="registrar"){
			echo $insPago->registrarHoraControlador();
		}		
		if($_POST['modulo_asistencia']=="actualizar"){
			echo $insPago->actualizarHoraControlador();
		}
		if($_POST['modulo_asistencia']=="eliminar"){
			echo $insPago->eliminarHoraControlador();
		}
		if($_POST['modulo_asistencia']=="registrar_lugar"){
			echo $insPago->registrarLugarControlador();
		}
		if($_POST['modulo_asistencia']=="actualizar_lugar"){
			echo $insPago->actualizarLugarControlador();
		}		
		if($_POST['modulo_asistencia']=="eliminar_lugar"){
			echo $insPago->eliminarLugarControlador();
		}
		if($_POST['modulo_asistencia']=="registrar_horario"){
			echo $insPago->registrarHorario();
		}
		if($_POST['modulo_asistencia']=="actualizar_horario"){
			echo $insPago->actualizarHorario();
		}
		if($_POST['modulo_asistencia']=="eliminar_horario"){
			echo $insPago->eliminarHorario();
		}
		if($_POST['modulo_asistencia']=="asignar_alumno"){
			echo $insPago->asignarAlumno();
		}	
		if($_POST['modulo_asistencia']=="eliminar_alumnolista"){
			echo $insPago->eliminar_alumnolista();
		}
		if($_POST['modulo_asistencia']=="asistencia"){
			echo $insPago->registro_asistencia();
		}
		/* Lista del horario para otra fecha, sin recargar la página
		   («cargar…» es una lectura para el guardia: permiso de ver). */
		if($_POST['modulo_asistencia']=="cargar_lista"){
			echo $insPago->listaAsistenciaJson();
		}
	}else{
		session_destroy();
		header("Location: ".APP_URL."login/");
	}