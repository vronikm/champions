<?php
	namespace app\controllers;
	use app\models\mainModel;

	class equipoController extends mainModel{

		/*----------  Controlador registrar usuario  ----------*/
		public function registrarEquipoControlador(){
			# Almacenando datos#
			$equipo_id			= $this->limpiarCadena($_POST['equipo_id']);
			$equipo_torneoid	= $this->limpiarCadena($_POST['equipo_torneoid']);
			$equipo_sedeid		= $this->limpiarCadena($_POST['equipo_sedeid']);
			$equipo_profesorid	= $this->limpiarCadena($_POST['equipo_profesorid']);
			$equipo_nombre		= $this->limpiarCadena($_POST['equipo_nombre']);
			$equipo_categoria	= $this->limpiarCadena($_POST['equipo_categoria']);
			$equipo_estado		= "A";

			# Verificando campos obligatorios #
			/* (int): en PHP 8, "" == 0 es FALSO, así que una sede o un profesor
			   sin elegir —valor vacío— pasaba esta comprobación. */
			if($equipo_nombre=="" || $equipo_categoria=="" || (int)$equipo_sedeid <= 0 || (int)$equipo_profesorid <= 0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}			

			# Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/equipos/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['equipo_foto']['name']!="" && $_FILES['equipo_foto']['size']>0){

				# Creando directorio #
				if(!file_exists($img_dir)){
					if(!mkdir($img_dir,0777)){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
					} 
				}

				# Verificando formato de imagenes #
				if(mime_content_type($_FILES['equipo_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['equipo_foto']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen tiene un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['equipo_foto']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$equipo_nombre);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['equipo_foto']['tmp_name'])){
					case 'image/jpeg':
						$foto=$foto.".jpg";
					break;
					case 'image/png':
						$foto=$foto.".png";
					break;
				}

				$maxWidth = 800;
				$maxHeight = 600;

				chmod($img_dir,0777);
				$inputFile = ($_FILES['equipo_foto']['tmp_name']);
					$outputFile = $img_dir.$foto;

				# Moviendo imagen al directorio #
				//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

			}else{
				$foto="";
			}
			$equipo_datos_reg=[
				[
					"campo_nombre"=>"equipo_torneoid",
					"campo_marcador"=>":TorneoEquipo",
					"campo_valor"=>$equipo_torneoid
				],
				[
					"campo_nombre"=>"equipo_sedeid",
					"campo_marcador"=>":Sede",
					"campo_valor"=>$equipo_sedeid
				],
				[
					"campo_nombre"=>"equipo_profesorid",
					"campo_marcador"=>":Profesor",
					"campo_valor"=>$equipo_profesorid
				],
				[
					"campo_nombre"=>"equipo_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$equipo_nombre
				],
				[
					"campo_nombre"=>"equipo_categoria",
					"campo_marcador"=>":Categoria",
					"campo_valor"=>$equipo_categoria
				],		
				[
					"campo_nombre"=>"equipo_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$equipo_estado
				],
				[
					"campo_nombre"=>"equipo_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				],				[
					"campo_nombre"=>"equipo_fechaactualizado",
					"campo_marcador"=>":Actualizado",
					"campo_valor"=>date("Y-m-d H:i:s")
				]
			];

			$registrar_equipo=$this->guardarDatos("torneo_equipo",$equipo_datos_reg);

			if($registrar_equipo->rowCount()==1){
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'equipoList/'.$equipo_torneoid.'/'.$equipo_id,
					"titulo"=>"Equipo registrado",
					"texto"=>"El equipo ".$equipo_nombre." se registró correctamente",
					"icono"=>"success"
				];
			}else{
				
				if(is_file($img_dir.$foto)){
					chmod($img_dir.$foto,0777);
					unlink($img_dir.$foto);
				}

				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible registrar el equipo, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
        }

		/* Listar los equipos de un torneo*/
		/*
		| Los equipos de un torneo, como filas de .ds-crud__tabla (futbol.css):
		| tabla en escritorio, ficha en el teléfono.
		|
		| LEFT JOIN con sede y profesor: antes era un JOIN implícito, y un
		| equipo cuyo profesor se hubiera dado de baja desaparecía del listado
		| sin aviso, aunque siguiera inscrito. Ahora sale con «Sin profesor».
		|
		| Textos decodificados y vueltos a escapar: limpiarHtml() los guarda
		| ya escapados y lo heredado está en crudo (ver torneoController).
		*/
		public function listarEquiposTorneo($equipo_torneoid){
			$tabla="";
			$torneo = (int)$equipo_torneoid;
			$consulta_datos="SELECT s.sede_nombre, e.equipo_id, e.equipo_nombre, e.equipo_foto, e.equipo_categoria,
								emp.empleado_nombre,
								CASE WHEN e.equipo_estado='A' THEN 'Activo'
								     WHEN e.equipo_estado='I' THEN 'Inactivo'
								     ELSE e.equipo_estado END AS ESTADO
							 FROM torneo_equipo e
							 LEFT JOIN general_sede s      ON s.sede_id = e.equipo_sedeid
							 LEFT JOIN sujeto_empleado emp ON emp.empleado_id = e.equipo_profesorid
							 WHERE e.equipo_torneoid = :torneo
							   AND e.equipo_estado IN ('A','I')
							 ORDER BY e.equipo_categoria, e.equipo_nombre";

			$datos = $this->ejecutarConsulta($consulta_datos, [':torneo' => $torneo]);
			$datos = $datos->fetchAll();

			$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
			$dirFotos = __DIR__ . '/../views/imagenes/fotos/equipos/';

			foreach($datos as $rows){
				$id = (int)$rows['equipo_id'];
				if($rows['ESTADO']=='Activo'){
					$badge = '<span class="badge text-bg-success">Activo</span>';
					$texto = "Inactivar";
					$icono = "fa-toggle-on";
				}else{
					$badge = '<span class="badge text-bg-secondary">Inactivo</span>';
					$texto = "Activar";
					$icono = "fa-toggle-off";
				}

				/* El escudo sólo si el archivo existe: en una base restaurada
				   sin la carpeta de imágenes saldría un icono roto. */
				$foto = (string)$rows['equipo_foto'];
				$imagen = ($foto !== '' && is_file($dirFotos . basename($foto)))
					? '<img class="ds-ficha__imagen" src="'.APP_URL.'app/views/imagenes/fotos/equipos/'.rawurlencode(basename($foto)).'" alt="">'
					: '<span class="ds-ficha__imagen" aria-hidden="true"><i class="fas fa-shield-halved"></i></span>';

				$profesor = trim((string)$rows['empleado_nombre']) !== ''
					? $h($rows['empleado_nombre']) : '<span class="text-body-secondary">Sin profesor</span>';

				$tabla.='
					<tr>
						<td class="ds-ficha__principal">
							<div class="ds-ficha__con-imagen">
								'.$imagen.'
								<div>
									<div class="ds-ficha__nombre">'.$h($rows['equipo_nombre']).'</div>
									<div class="ds-ficha__meta"><i class="fas fa-tag" aria-hidden="true"></i> '.$h($rows['equipo_categoria']).'
										'.($rows['sede_nombre'] !== null ? ' · '.$h($rows['sede_nombre']) : '').'</div>
								</div>
							</div>
						</td>
						<td class="ds-ficha__dato" data-etiqueta="Profesor">'.$profesor.'</td>
						<td class="ds-ficha__estado" data-order="'.$h($rows['ESTADO']).'">'.$badge.'</td>
						<td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">
							<a href="'.APP_URL.'jugadorLista/'.$torneo.'/'.$id.'/" class="btn btn-primary btn-sm ds-ficha__primario"><i class="fas fa-list me-1" aria-hidden="true"></i>Jugadores</a>
							<a href="'.APP_URL.'jugadorNew/'.$torneo.'/'.$id.'/" class="btn btn-outline-primary btn-sm" title="Asignar jugadores" aria-label="Asignar jugadores"><i class="fas fa-user-plus"></i></a>
							'.$this->siPuede('editar','torneoList','<a href="'.APP_URL.'equipoList/'.$torneo.'/'.$id.'/" class="btn btn-outline-success btn-sm" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
							'.$this->siPuede('editar','torneoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/equipoAjax.php" method="POST" autocomplete="off">
								<input type="hidden" name="modulo_equipo" value="actualizarestado">
								<input type="hidden" name="equipo_id" value="'.$id.'">
								<button type="submit" class="btn btn-outline-secondary btn-sm" title="'.$texto.'" aria-label="'.$texto.'"><i class="fas '.$icono.'"></i></button>
							</form>').'
							'.$this->siPuede('eliminar','torneoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/equipoAjax.php" method="POST" autocomplete="off">
								<input type="hidden" name="modulo_equipo" value="eliminar">
								<input type="hidden" name="equipo_id" value="'.$id.'">
								<button type="submit" class="btn btn-outline-danger btn-sm" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>').'
						</div></td>
					</tr>';
			}
			return $tabla;
		}

		public function BuscarTorneoEquipo($equipo_torneoid){		
			$consulta_datos=("SELECT torneo_id, torneo_nombre
							 FROM torneo_torneo
							 WHERE torneo_id = :torneo");	

			$datos = $this->ejecutarConsulta($consulta_datos, [':torneo' => (int)$equipo_torneoid]);		
			return $datos;
		}

		public function BuscarEquipo($equipo_id){		
			$consulta_datos=("SELECT equipo_id, equipo_profesorid, equipo_nombre, equipo_torneoid, sede_id, sede_nombre, equipo_categoria, equipo_foto,
								CASE WHEN equipo_estado = 'A' THEN 'Activo' 
									 WHEN equipo_estado = 'I' THEN 'Inactivo' 
									 ELSE equipo_estado 
								END AS ESTADO 
							 FROM torneo_equipo, general_sede
							 WHERE equipo_sedeid = sede_id
							 	AND equipo_estado IN ('A','I')
							 	AND equipo_id = :equipo");	

			$datos = $this->ejecutarConsulta($consulta_datos, [':equipo' => (int)$equipo_id]);		
			return $datos;
		}
		public function actualizarEquipoControlador(){

			$equipoid=$this->limpiarCadena($_POST['equipo_id']);

			# Verificando existencia de equipo #
			$equipo=$this->ejecutarConsulta("SELECT * FROM torneo_equipo WHERE equipo_id = :equipo", [':equipo' => (int)$equipoid]);
			if($equipo->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El equipo no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);		    
			}else{
				$equipo=$equipo->fetch();				
				$equipo_torneoid	= $equipo['equipo_torneoid'];
				$equipo_sedeid		= $equipo['equipo_sedeid'];
				$equipo_profesorid	= $equipo['equipo_profesorid'];
				$equipo_estado 		= $equipo['equipo_estado'];
			}	
			
			# Almacenando datos#
			$equipo_sedeid		= $this->limpiarCadena($_POST['equipo_sedeid']);
			$equipo_profesorid	= $this->limpiarCadena($_POST['equipo_profesorid']);
			$equipo_nombre		= $this->limpiarCadena($_POST['equipo_nombre']);			
			$equipo_categoria	= $this->limpiarCadena($_POST['equipo_categoria']);
						
			# Verificando campos obligatorios #
			/* (int): en PHP 8, "" == 0 es FALSO, así que una sede o un profesor
			   sin elegir —valor vacío— pasaba esta comprobación. */
			if($equipo_nombre=="" || $equipo_categoria=="" || (int)$equipo_sedeid <= 0 || (int)$equipo_profesorid <= 0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			$equipo_datos_reg=[
				[
					"campo_nombre"=>"equipo_torneoid",
					"campo_marcador"=>":TorneoEquipo",
					"campo_valor"=>$equipo_torneoid
				],
				[
					"campo_nombre"=>"equipo_sedeid",
					"campo_marcador"=>":Sede",
					"campo_valor"=>$equipo_sedeid
				],	
				[
					"campo_nombre"=>"equipo_profesorid",
					"campo_marcador"=>":Profesor",
					"campo_valor"=>$equipo_profesorid
				],	
				[
					"campo_nombre"=>"equipo_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$equipo_nombre
				],
				[
					"campo_nombre"=>"equipo_categoria",
					"campo_marcador"=>":Categoria",
					"campo_valor"=>$equipo_categoria
				],		
				[
					"campo_nombre"=>"equipo_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$equipo_estado
				],
				[
					"campo_nombre"=>"equipo_fechaactualizado",
					"campo_marcador"=>":Actualizado",
					"campo_valor"=>date("Y-m-d H:i:s")
				]
			];
			
			# Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/equipos/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['equipo_foto']['name']!="" && $_FILES['equipo_foto']['size']>0){

				# Creando directorio #
				if(!file_exists($img_dir)){
					if(!mkdir($img_dir,0777)){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
					} 
				}

				# Verificando formato de imagenes #
				if(mime_content_type($_FILES['equipo_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['equipo_foto']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['equipo_foto']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$equipo_nombre);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['equipo_foto']['tmp_name'])){
					case 'image/jpeg':
						$foto=$foto.".jpg";
					break;
					case 'image/png':
						$foto=$foto.".png";
					break;
				}

				$maxWidth = 800;
				$maxHeight = 600;

				chmod($img_dir,0777);
				$inputFile = ($_FILES['equipo_foto']['tmp_name']);
					$outputFile = $img_dir.$foto;

				# Moviendo imagen al directorio #
				//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Eliminando imagen anterior #
				if(is_file($img_dir.$equipo['equipo_foto']) && $equipo['equipo_foto']!=$foto){
					chmod($img_dir.$equipo['equipo_foto'], 0777);
					unlink($img_dir.$equipo['equipo_foto']);
				}	

				$equipo_datos_reg[] = [
					"campo_nombre"=>"equipo_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				];
			}

			$condicion=[
				"condicion_campo"=>"equipo_id",
				"condicion_marcador"=>":Equipoid",
				"condicion_valor"=>$equipoid
			];

			if($this->actualizarDatos("torneo_equipo",$equipo_datos_reg,$condicion)){					
				
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'equipoList/'.$equipo_torneoid.'/'.$equipoid,
					"titulo"=>"Equipo actualizado",
					"texto"=>"El equipo ".$equipo_nombre." se actualizó correctamente",
					"icono"=>"success"
				];
			}else{				
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible actualizar el equipo, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
        }

		public function actualizarEstadoEquipoControlador(){
			$equipoid=$this->limpiarCadena($_POST['equipo_id']);

			# Verificando equipo #
			$equipo=$this->ejecutarConsulta("SELECT * FROM torneo_equipo WHERE equipo_id = :equipo", [':equipo' => (int)$equipoid]);
			if($equipo->rowCount()<=0){	
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El equipo no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$equipo=$equipo->fetch();
		    }
			if($equipo['equipo_estado']=='A'){
				$estadoA = 'I';
			}else{
				$estadoA = 'A';
			}
            $equipo_datos_up=[
				[
					"campo_nombre"=>"equipo_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"equipo_id",
				"condicion_marcador"=>":Equipoid",
				"condicion_valor"=>$equipoid
			];

			if($this->actualizarDatos("torneo_equipo",$equipo_datos_up,$condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Estado actualizado correctamente",
					"texto"=>"El estado del equipo ".$equipo['equipo_nombre']." fue actualizado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No hemos podido actualizar el estado del torneo ".$equipo['equipo_nombre']." por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		/*----------  Controlador eliminar equipo  ----------*/
		public function eliminarEquipoControlador(){

			$equipoid=$this->limpiarCadena($_POST['equipo_id']);
			# Verificando usuario #
			$equipo=$this->ejecutarConsulta("SELECT * FROM torneo_equipo WHERE equipo_id = :equipo", [':equipo' => (int)$equipoid]);
			if($equipo->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El equipo no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}else{
				$equipo=$equipo->fetch();
				$equipo_torneoid	= $equipo['equipo_torneoid'];
			}
			if($equipo['equipo_estado']=='A' || $equipo['equipo_estado']=='I'){
				$estadoA = 'E';
			}else{
				$estadoA = 'X';
			}
			$equipo_datos_up=[
				[
					"campo_nombre"=>"equipo_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"equipo_id",
				"condicion_marcador"=>":Equipoid",
				"condicion_valor"=>$equipoid
			];

			if($this->actualizarDatos("torneo_equipo",$equipo_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'equipoList/'.$equipo_torneoid.'/'.$equipoid,
					"titulo"=>"Equipo eliminado",
					"texto"=>"El equipo ".$equipo['equipo_nombre']." se eliminó correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No hemos podido eliminar el equipo ".$equipo['equipo_nombre']." por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}
		
		public function listarOptionSede($sedeid){
			$option="";

			$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede ORDER BY sede_nombre";

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
			foreach($datos as $rows){
				$sel = ((int)$sedeid === (int)$rows['sede_id']) ? ' selected' : '';
				$option.='<option value="'.(int)$rows['sede_id'].'"'.$sel.'>'.$h($rows['sede_nombre']).'</option>';
			}
			return $option;
		}

		public function listarResponsable($equipo_profesorid){
			$option="";

			/* El profesor ya asignado se incluye aunque hoy esté inactivo: si no,
			   al editar el equipo el desplegable caería en «Seleccionar» y guardar
			   lo dejaría sin profesor sin que nadie lo hubiera pedido. */
			$consulta_datos="SELECT empleado_id, empleado_nombre FROM sujeto_empleado
							  WHERE (empleado_tipopersonalid = 'TPP' AND empleado_estado = 'A')
							     OR empleado_id = :actual
							  ORDER BY empleado_nombre";

			$datos = $this->ejecutarConsulta($consulta_datos, [':actual' => (int)$equipo_profesorid]);
			$datos = $datos->fetchAll();
			$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
			foreach($datos as $rows){
				$sel = ((int)$equipo_profesorid === (int)$rows['empleado_id']) ? ' selected' : '';
				$option.='<option value="'.(int)$rows['empleado_id'].'"'.$sel.'>'.$h($rows['empleado_nombre']).'</option>';
			}
			return $option;
		}
    }