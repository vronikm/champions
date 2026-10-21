<?php
	
	require_once "../../config/app.php";
	require_once "../views/inc/session_start.php";
	require_once "../../autoload.php";

	/* Guardia: sesion + origen + permiso de rol */
	$GUARD_VISTA = "pagosList";
	require_once "../views/inc/ajax_guard.php";
	
	use app\controllers\pagosController;

	if(isset($_POST['modulo_pagos'])){

		$insPago = new pagosController();

		if($_POST['modulo_pagos']=="registrar"){
			echo $insPago->registrarPagoControlador();
		}

		if($_POST['modulo_pagos']=="registraruniforme"){
			echo $insPago->registrarPagoUniforme();
		}

		if($_POST['modulo_pagos']=="pagopendiente"){
			echo $insPago->registrarPagoPendiente();
		}

		if($_POST['modulo_pagos']=="editarpagopendiente"){
			echo $insPago->actualizarPagoPendiente();
		}

		if($_POST['modulo_pagos']=="eliminar"){
			echo $insPago->eliminarPagoControlador();
		}
		if($_POST['modulo_pagos']=="eliminarpendiente"){
			echo $insPago->eliminarPagoPendiente();
		}		
		if($_POST['modulo_pagos']=="actualizar"){
			echo $insPago->actualizarPagoControlador();
		}
		if($_POST['modulo_pagos']=="actualizaruniforme"){
			echo $insPago->actualizarPagoUniforme();
		}
		if($_POST['modulo_pagos']=="descuento"){
			echo $insPago->registrarDescuento();
		}	
		if($_POST['modulo_pagos']=="descuentoUP"){
			echo $insPago->actualizarDescuento();
		}
		if($_POST['modulo_pagos']=="registrarcampeonato"){
			echo $insPago->registrarPagoCampeonato();
		}

	}else{
		session_destroy();
		header("Location: ".APP_URL."login/");
	}