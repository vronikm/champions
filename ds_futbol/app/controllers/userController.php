<?php

	namespace app\controllers;
	use app\models\mainModel;

	class userController extends mainModel{

		/*----------  Controlador registrar usuario  ----------*/
		/* Listar todos los usuarios*/
		/*----------  Controlador actualizar usuario  ----------*/
		/*----------  Controlador actualizar estado usuario  ----------*/
		/*----------  Controlador eliminar foto usuario  ----------*/
		/*----------  Controlador actualizar foto usuario  ----------*/
		/* ==================================== Roles ==================================== */
		
		

		
		
	

		public function actualizarClaveUsuarioControlador(){			
			
			/*
			| Las contrasenas NO pasan por limpiarCadena().
			|
			| Ese metodo aplica htmlspecialchars(), asi que una clave con &,
			| <, >, " o ' se cifraba transformada: se guardaba el hash de
			| "Pa&amp;ss" en lugar del de "Pa&ss". El login no limpia nada,
			| de modo que la comparacion fallaba siempre y el usuario quedaba
			| fuera con la contrasena que acababa de elegir.
			|
			| Una contrasena no se sanea: se compara y se cifra tal cual.
			*/
			$usuarioid = (int)($_POST['usuario_id'] ?? 0);
			$clave_actual    = (string)($_POST['usuario_clave'] ?? '');
			$clave_nueva     = (string)($_POST['usuario_clave_nueva'] ?? '');
			$clave_confirmar = (string)($_POST['usuario_clave_confirmar'] ?? '');

			/* Nadie cambia la clave de otro desde aqui: esto es autoservicio. */
			if ($usuarioid !== usuario_actual_id()) {
				return json_encode([
					"tipo"   => "simple",
					"titulo" => "Acción no permitida",
					"texto"  => "Sólo puede cambiar la contraseña de su propia cuenta.",
					"icono"  => "error"
				]);
			}

			/* La comprobación y el guardado viven en el núcleo
			   (clave_cambiar_propia): exige la actual, valida la nueva, no
			   admite repetir la misma y quita la marca de cambio pendiente
			   (la de una contraseña que puso otra persona). */
			if (!clave_cambiar_propia($usuarioid, $clave_actual, $clave_nueva, $clave_confirmar, $motivo)) {
				return json_encode([
					"tipo"   => "simple",
					"titulo" => "No se cambió la contraseña",
					"texto"  => $motivo,
					"icono"  => "error"
				]);
			}

			$alerta=[
				"tipo"=>"Toast_Success_simple",
				"titulo"=>"Contraseña actualizada",
				"texto"=>"la contraseña se actualizó correctamente",
				"icono"=>"success"
			];
			return json_encode($alerta);
			
		}
	}