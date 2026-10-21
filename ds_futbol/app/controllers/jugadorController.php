<?php
	namespace app\controllers;
	use app\models\mainModel;

	class jugadorController extends mainModel{        
		public function listarSedebusqueda($sedeid){
			$option="";

			$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($sedeid == $rows['sede_id']){
					$option.='<option value='.$rows['sede_id'].' selected>'.$rows['sede_nombre'].'</option>';
				}else{
					$option.='<option value='.$rows['sede_id'].'>'.$rows['sede_nombre'].'</option>';	
				}		
			}
			return $option;
		}

        /*----------  Encabezado de búsqueda de alumnos para agregarlos como jugadores  ----------*/
		public function listarAlumnos($equipo_torneoid, $equipo_id, $identificacion, $apellidopaterno, $primernombre, $anio, $sede, $equipo_categoria){	
			if($identificacion!=""){
				$identificacion .= '%'; 
			}
			if($primernombre!=""){
				$primernombre .= '%';
			} 
			if($apellidopaterno!=""){
				$apellidopaterno .= '%';
			} 			

			$tabla="";

			/* El array acompaña a la consulta. Las ramas que la REEMPLAZAN
			   lo vacían: pasar un parámetro que la consulta ya no usa es
			   SQLSTATE[HY093]. */
			$parametros = [];

			$consulta_datos="SELECT * FROM sujeto_alumno
								WHERE (alumno_primernombre LIKE :nombre
								OR alumno_identificacion LIKE :ident
								OR alumno_apellidopaterno LIKE :apellido) ";
			$parametros[':nombre']   = $primernombre;
			$parametros[':ident']    = $identificacion;
			$parametros[':apellido'] = $apellidopaterno;

			if($anio!=""){
				$consulta_datos .= " and YEAR(alumno_fechanacimiento) = :anio";
				$parametros[':anio'] = $anio;
			}

			if($identificacion=="" && $primernombre=="" && $apellidopaterno==""){
				$consulta_datos="SELECT * FROM sujeto_alumno WHERE YEAR(alumno_fechanacimiento) = :anio";
				$parametros = [':anio' => $anio];
			}

			if($identificacion=="" && $primernombre=="" && $apellidopaterno=="" && $anio == ""){
				$consulta_datos = "SELECT * FROM sujeto_alumno WHERE alumno_primernombre <> '' ";
				$parametros = [];
			}

			if($sede!=""){
				if($sede == 0){
					$consulta_datos .= " and alumno_sedeid <> :sede";
				}else{
					$consulta_datos .= " and alumno_sedeid = :sede";
				}
				$parametros[':sede'] = $sede;
			}else{
				$consulta_datos = "SELECT * FROM sujeto_alumno WHERE alumno_primernombre = ''";
				$parametros = [];
			}

			$consulta_datos .= " AND alumno_estado = 'A'";
			/* Estos dos se interpolaban directamente y el inventario no los
			   marcaba: el patrón buscaba comillas y aquí no las hay. */
			$consulta_datos .= " AND alumno_id NOT IN (SELECT jugador_alumnoid FROM torneo_equipo, torneo_jugador
																			   WHERE equipo_id = jugador_equipoid
																			   	AND equipo_torneoid = :torneo
																				AND equipo_categoria = :categoria
																				AND equipo_estado <> 'E')";
			$parametros[':torneo']    = $equipo_torneoid;
			$parametros[':categoria'] = $equipo_categoria;

			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
            $posicion = "<select class='form-control' style='font-size: 13px; height: 31px;' id='posicion' name='posicion'>".$this->listarCatalogoPosicion()."</select>";
            $tipo = "<select class='form-control' style='font-size: 13px; height: 31px;' id='tipo' name='tipo'>".$this->listarCatalogoTipo()."</select>";
			foreach($datos as $rows){
				$tabla.='					
					<tr>
						'.$this->siPuede('crear','torneoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/jugadorAjax.php" method="POST" autocomplete="off" data-recargar-directo>
						<td><input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['alumno_primernombre'].' '.$rows['alumno_segundonombre'].' '.$rows['alumno_apellidopaterno'].' '.$rows['alumno_apellidomaterno'].'</td>
						<td>'.$rows['alumno_fechanacimiento'].'</td>
                        <td>'.$posicion.'</td>
                        <td>'.$tipo.'</td>
						<td>												
							<input type="hidden" name="modulo_jugador" value="agregar">
							<input type="hidden" name="equipo_id" value="'.$equipo_id.'">						
							<button type="submit" class="btn float-end btn-actualizar btn-sm" style="margin-right: 5px;"><i class="fas fa-plus me-1"></i>Agregar</button>					
						</td>
						</form>').'
					</tr>
					';
			}
			return $tabla;			
		}
		public function listarjugadores($equipo_id){			
			$tabla="";
			$consulta_datos = "SELECT jugador_alumnoid, jugador_equipoid,   
									alumno_identificacion, CONCAT(alumno_primernombre, ' ',alumno_segundonombre) AS NOMBRES,  
									CONCAT(alumno_apellidopaterno, ' ',alumno_apellidomaterno) AS APELLIDOS,
									YEAR(alumno_fechanacimiento) AS CATEGORIA,
										P.catalogo_descripcion AS POSICION, T.catalogo_descripcion AS TIPO
								FROM torneo_jugador 
									INNER JOIN sujeto_alumno ON alumno_id = jugador_alumnoid
									INNER JOIN torneo_equipo ON equipo_id = jugador_equipoid
									INNER JOIN general_tabla_catalogo P ON P.catalogo_valor = jugador_posicioncod
									INNER JOIN general_tabla_catalogo T ON T.catalogo_valor = jugador_tipocod
								WHERE jugador_equipoid = $equipo_id";
			
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='					
					<tr>
						'.$this->siPuede('eliminar','torneoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/jugadorAjax.php" method="POST" autocomplete="off" >
						<td><input type="hidden" name="jugador_alumnoid" value="'.$rows['jugador_alumnoid'].'">'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['NOMBRES'].'</td>
						<td>'.$rows['APELLIDOS'].'</td>
						<td>'.$rows['CATEGORIA'].'</td>
                      	<td>'.$rows['POSICION'].'</td>
					  	<td>'.$rows['TIPO'].'</td>
						<td>												
							<input type="hidden" name="modulo_jugador" value="eliminar">												
							<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 5px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>					
						</td>
						</form>').'
					</tr>
					';
			}
			return $tabla;			
		}

		public function agregarJugador(){	
			# Almacenando datos 			
			$equipo_id = $this->limpiarCadena($_POST['equipo_id']);
			$alumno_id = $_POST['alumno_id'];
			$posicion_id = $_POST['posicion'];
			$tipo_id = $_POST['tipo'];
			
			# Verificando campos obligatorios #
			if($equipo_id=="" || $alumno_id== ""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No existe información del equipo",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }				
			$torneo_jugador_reg = [
				[
					"campo_nombre" => "jugador_alumnoid",
					"campo_marcador" => ":Alumnoid",
					"campo_valor" => $alumno_id
				],
				[
					"campo_nombre" => "jugador_equipoid",
					"campo_marcador" => ":Equipoid",
					"campo_valor" => $equipo_id
				],
				[
					"campo_nombre" => "jugador_posicioncod",
					"campo_marcador" => ":Posicioncod",
					"campo_valor" => $posicion_id
				],
				[
					"campo_nombre" => "jugador_tipocod",
					"campo_marcador" => ":Tipocod",
					"campo_valor" => $tipo_id
				]
			];
			
			$agregar_jugador=$this->guardarDatos("torneo_jugador",$torneo_jugador_reg);
			
			if($agregar_jugador->rowCount()==1){
				$alerta=[
					"tipo"=>"Toast_Success",
					"titulo"=>"Jugador agregado correctamente..!!" 
				];
				/*
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Jugador agregado",
					"texto"=>"El jugador fue agregado correctamente",
					"icono"=>"success"
				];
				*/
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible agregar el jugador",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);			
		}

		public function eliminarJugador(){	
			# Almacenando datos
			$jugador_alumnoid = $_POST['jugador_alumnoid'];
					
			# Verificando campos obligatorios #
			if($jugador_alumnoid == ""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No existe información del jugador",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }				
			
			$eliminar_jugador = $this->eliminarRegistro("torneo_jugador","jugador_alumnoid",$jugador_alumnoid);
			
			if($eliminar_jugador->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Jugador eliminado",
					"texto"=> "El jugador fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible eliminar el jugador",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}        			
		public function listarCatalogoPosicion(){
			$option="";
			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'posicion_juego'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
			}
			return $option;
		}
        public function listarCatalogoTipo(){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'tipo_participacion'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
			}
			return $option;
		}        
		public function BuscarEquipo($equipo_id){		
			$consulta_datos=("SELECT equipo_id, equipo_nombre, equipo_torneoid, equipo_categoria, equipo_sedeid, sede_nombre, equipo_foto,
								empleado_nombre, CASE WHEN equipo_estado = 'A' THEN 'Activo' 
													  WHEN equipo_estado = 'I' THEN 'Inactivo' 
													  ELSE equipo_estado END AS ESTADO 
							 FROM torneo_equipo, general_sede, sujeto_empleado
							 WHERE equipo_sedeid = sede_id
							 	AND equipo_profesorid = empleado_id
							 	AND equipo_estado IN ('A','I')
							 	AND equipo_id = :equipo");	

			$datos = $this->ejecutarConsulta($consulta_datos, [':equipo' => (int)$equipo_id]);		
			return $datos;
		}
			
		public function guardarListaJugadores(){
			# Almacenando datos 			
			$equipo_id = $this->limpiarCadena($_POST['equipo_id']);
			$alumno_id = $_POST['alumno_id'];
			$posicion_id = $_POST['posicion'];
			$tipo_id = $_POST['tipo'];
			$selected = $_POST['jugador_selected'];
			
			# Verificando campos obligatorios #
			if($equipo_id=="" || $alumno_id== ""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No existe información del equipo",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }	
			for ($i = 0; $i < count($alumno_id); $i++) { 
				if (isset($selected[$i])) {
					$torneo_jugador_reg = [
						[
							"campo_nombre" => "jugador_alumnoid",
							"campo_marcador" => ":Alumnoid",
							"campo_valor" => $alumno_id[$i]
						],
						[
							"campo_nombre" => "jugador_equipoid",
							"campo_marcador" => ":Equipoid",
							"campo_valor" => $equipo_id
						],
						[
							"campo_nombre" => "jugador_posicioncod",
							"campo_marcador" => ":Posicioncod",
							"campo_valor" => $posicion_id[$i]
						],
						[
							"campo_nombre" => "jugador_tipocod",
							"campo_marcador" => ":Tipocod",
							"campo_valor" => $tipo_id[$i]
						]
					];
			
					$this->guardarDatos("torneo_jugador",$torneo_jugador_reg);
				}
			}
			$alerta=[
				"tipo"=>"recargar",
				"titulo"=>"Jugador agregado",
				"texto"=>"El jugador fue agregado correctamente",
				"icono"=>"success"
			];
			return json_encode($alerta);			
		}

		public function informacionSede($sedeid){		
			$consulta_datos="SELECT * FROM general_sede WHERE sede_id = :sede";
			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sedeid]);
			return $datos;
		}
		public function listaJugadorPDF($equipo_id){		
			$consulta_datos=("SELECT jugador_alumnoid, jugador_equipoid,   
									alumno_identificacion, CONCAT(alumno_primernombre, ' ',alumno_segundonombre) AS NOMBRES,  
									CONCAT(alumno_apellidopaterno, ' ',alumno_apellidomaterno) AS APELLIDOS,
									alumno_fechanacimiento AS FECHANAC,
									alumno_identificacion as CEDULA,
									catalogo_descripcion POSICION,
									case when alumno_numcamiseta = 0 then null else alumno_numcamiseta end AS NUMCAMISETA
								FROM torneo_jugador 
									INNER JOIN sujeto_alumno ON alumno_id = jugador_alumnoid
									INNER JOIN torneo_equipo ON equipo_id = jugador_equipoid
									INNER JOIN general_tabla_catalogo ON catalogo_valor = jugador_posicioncod
								WHERE jugador_equipoid = $equipo_id");	

			$datos = $this->ejecutarConsulta($consulta_datos);		
			return $datos;
		}
    }