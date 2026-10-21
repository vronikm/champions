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

			# Verificando campos obligatorios #
		    if($usuarioid=="" || $clave_actual=="" || $clave_nueva=="" || $clave_confirmar==""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No has llenado los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
		        
		    }
			
			# Verificando claves iguales #
			if($clave_nueva!=$clave_confirmar){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"Las contraseñas no coinciden, por favor verifique e intente nuevamente",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			# Verificando la politica de contrasenas del nucleo #
			if(!clave_valida($clave_nueva, $motivo, (string)($_SESSION["usuario"] ?? ""))){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Contraseña no válida",
					"texto"=>$motivo,
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			#Verificar si la clave actual es correcta #
			$datos = $this->ejecutarConsulta(
				"SELECT usuario_clave FROM seguridad_usuario WHERE usuario_id = :id",
				[':id' => $usuarioid]
			);
			if($datos->rowCount()<=0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No hemos encontrado el usuario en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}else{				
				$datos = $datos->fetch();
				if(!password_verify($clave_actual, $datos['usuario_clave'])){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La contraseña actual no es correcta, por favor verifique e intente nuevamente",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}
			}
			
			$clave_nueva=password_hash($clave_nueva,PASSWORD_BCRYPT,["cost"=>10]);
			$usuario_datos_up= [							
				[
					"campo_nombre"	=> "usuario_clave",
					"campo_marcador"=> ":Clave",
					"campo_valor"	=> $clave_nueva					
				],
				[				
					"campo_nombre"	=> "usuario_fechacambioclave",
					"campo_marcador"=> ":FechaClave",
					"campo_valor"	=> date("Y-m-d H:i:s")
				]				
			];
			
			$condicion=[
				"condicion_campo"=>"usuario_id",
				"condicion_marcador"=>":Usuarioid",
				"condicion_valor"=>$usuarioid
			];

			if($this->actualizarDatos("seguridad_usuario",$usuario_datos_up,$condicion)){		
				$alerta=[					
					"tipo"=>"Toast_Success_simple",
					"titulo"=>"Contraseña actualizada",
					"texto"=>"la contraseña se actualizó correctamente",
					"icono"=>"success"
				];
								
			}else{			
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No hemos podido actualizar la contraseña, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
			
		}
	}