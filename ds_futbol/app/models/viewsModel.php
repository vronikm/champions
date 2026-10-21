<?php
	namespace app\models;

	class viewsModel{

		/*---------- Modelo obtener vista ----------*/
		protected function obtenerVistasModelo($vista){

			/* La lista blanca vive en config/vistas.php: es la misma que
			   consulta DigiSports Core para validar que un menú apunte a
			   una vista real, de modo que no puedan divergir. */
			$listaBlanca = require __DIR__ . "/../../config/vistas.php";

			if(in_array($vista, $listaBlanca, true)){
				if(is_file("./app/views/content/".$vista."-view.php")){
					$contenido="./app/views/content/".$vista."-view.php";
				}else{
					$contenido="404";
				}
			}elseif($vista=="login" || $vista=="index"){
				$contenido="login";
			}else{
				$contenido="404";
			}

			return $contenido;
		}
	}
