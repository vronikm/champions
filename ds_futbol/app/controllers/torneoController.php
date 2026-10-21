<?php

	namespace app\controllers;
	use app\models\mainModel;

	class torneoController extends mainModel{

		/*----------  Controlador registrar usuario  ----------*/
		public function registrarTorneoControlador(){
			# Almacenando datos#
			$torneo_nombre		= $this->limpiarCadena($_POST['torneo_nombre']);
			$torneo_ciudad		= $this->limpiarCadena($_POST['torneo_ciudad']);
			$torneo_lugar		= $this->limpiarCadena($_POST['torneo_lugar']);
			$torneo_fechainicio	= $this->limpiarCadena($_POST['torneo_fechainicio']);
			$torneo_fechafin	= $this->limpiarCadena($_POST['torneo_fechafin']);		    		    
			$torneo_organizador	= $this->limpiarCadena($_POST['torneo_organizador']);
			$torneo_descripcion	= $this->limpiarCadena($_POST['torneo_descripcion']);
			$torneo_estado		= "A";

			# Verificando campos obligatorios #
			if($torneo_nombre=="" || $torneo_ciudad=="" || $torneo_lugar==""){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}			

			# Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/torneos/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['torneo_foto']['name']!="" && $_FILES['torneo_foto']['size']>0){

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
				if(mime_content_type($_FILES['torneo_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['torneo_foto']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen tiene un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['torneo_foto']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$torneo_nombre);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['torneo_foto']['tmp_name'])){
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
				$inputFile = ($_FILES['torneo_foto']['tmp_name']);
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
			$torneo_datos_reg=[
				[
					"campo_nombre"=>"torneo_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$torneo_nombre
				],
				[
					"campo_nombre"=>"torneo_ciudad",
					"campo_marcador"=>":Ciudad",
					"campo_valor"=>$torneo_ciudad
				],
				[
					"campo_nombre"=>"torneo_lugar",
					"campo_marcador"=>":Lugar",
					"campo_valor"=>$torneo_lugar
				],
				[
					"campo_nombre"=>"torneo_fechainicio",
					"campo_marcador"=>":Fechainicio",
					"campo_valor"=>$torneo_fechainicio
				],				
				[
					"campo_nombre"=>"torneo_fechafin",
					"campo_marcador"=>":Fechafin",
					"campo_valor"=>$torneo_fechafin
				],				
				[
					"campo_nombre"=>"torneo_organizador",
					"campo_marcador"=>":Organizador",
					"campo_valor"=>$torneo_organizador
				],
				[
					"campo_nombre"=>"torneo_descripcion",
					"campo_marcador"=>":Descripcion",
					"campo_valor"=>$torneo_descripcion
				],
				[
					"campo_nombre"=>"torneo_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$torneo_estado
				],
				[
					"campo_nombre"=>"torneo_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				],
				[
					"campo_nombre"=>"torneo_fechaactualizado",
					"campo_marcador"=>":Actualizado",
					"campo_valor"=>date("Y-m-d H:i:s")
				]
			];

			$registrar_torneo=$this->guardarDatos("torneo_torneo",$torneo_datos_reg);

			if($registrar_torneo->rowCount()==1){
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'torneoList/',
					"titulo"=>"Torneo registrado",
					"texto"=>"El torneo ".$torneo_nombre." se registró correctamente",
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
					"texto"=>"No fue posible registrar el torneo, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
        }

		/* Listar todos los torneos*/
		public function listarTorneos(){
			$tabla="";
			$estado = "";
			$texto = "";
			$boton = "";
			$consulta_datos="SELECT torneo_id, torneo_nombre, torneo_ciudad, torneo_lugar, torneo_foto, torneo_fechainicio,
									torneo_fechafin, torneo_organizador, torneo_descripcion,
								CASE WHEN torneo_estado='A' THEN 'Activo' 
									 WHEN torneo_estado = 'I' THEN 'Inactivo' 
									 ELSE torneo_estado 
								END AS ESTADO 
							 FROM torneo_torneo
							 WHERE torneo_estado IN ('A','I')
							 ORDER BY torneo_fechaactualizado asc";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($rows['ESTADO']=='Activo'){
					$estado = "Activo";
					$texto = "Inactivar";
					$boton = "btn-secondary";
				}else{
					$estado = "Inactivo";
					$texto = "Activar";
					$boton = "btn-info";
				}

				$tabla.='
					<tr>						
						<td>'.$rows['torneo_nombre'].'</td>
						<td>'.$rows['torneo_ciudad'].'</td>
						<td>'.$rows['torneo_lugar'].'</td>
						<td>'.$rows['torneo_fechainicio'].'</td>
						<td>'.$rows['torneo_fechafin'].'</td>
						<td>'.$rows['torneo_organizador'].'</td>
						<td>'.$rows['torneo_descripcion'].'</td>
						<td>'.$estado.'</td>
						<td class="ds-tabla-acciones">
							'.$this->siPuede('eliminar','torneoList','<form class="FormularioAjax d-inline-flex" action="'.APP_URL.'app/ajax/torneoAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_torneo" value="eliminar">
								<input type="hidden" name="torneo_id" value="'.$rows['torneo_id'].'">						
								<button type="submit" class="btn btn-danger btn-sm ms-1" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>').'
							
							'.$this->siPuede('editar','torneoList','<form class="FormularioAjax d-inline-flex" action="'.APP_URL.'app/ajax/torneoAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_torneo" value="actualizarestado">
								<input type="hidden" name="torneo_id" value="'.$rows['torneo_id'].'">						
								<button type="submit" class="btn '.$boton.' btn-sm ms-1"> '.$texto.' </button>
							</form>').'

							'.$this->siPuede('editar','torneoList','<a href="'.APP_URL.'torneoList/'.$rows['torneo_id'].'/" class="btn btn-success btn-sm ms-1" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
							<a href="'.APP_URL.'equipoList/'.$rows['torneo_id'].'/" class="btn btn-primary btn-sm ms-1"><i class="fas fa-users me-1"></i>Equipos</a>
						</td>
					</tr>';	
			}
			return $tabla;
		}

		public function BuscarTorneo($torneoid){		
			$consulta_datos=("SELECT torneo_id, torneo_nombre, torneo_ciudad, torneo_lugar, torneo_foto, torneo_fechainicio,
									torneo_fechafin, torneo_organizador, torneo_descripcion,
								CASE WHEN torneo_estado='A' THEN 'Activo' 
									 WHEN torneo_estado = 'I' THEN 'Inactivo' 
									 ELSE torneo_estado 
								END AS ESTADO 
							 FROM torneo_torneo
							 WHERE torneo_id = :torneo
							 	AND torneo_estado IN ('A','I')");

			$datos = $this->ejecutarConsulta($consulta_datos, [':torneo' => (int)$torneoid]);
			return $datos;
		}

		public function actualizarTorneoControlador(){

			$torneoid=$this->limpiarCadena($_POST['torneo_id']);

			# Verificando existencia de torneo #
			$torneo=$this->ejecutarConsulta("SELECT * FROM torneo_torneo WHERE torneo_id = :torneo", [':torneo' => (int)$torneoid]);
			if($torneo->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El torneo no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);		    
			}else{
				$torneo=$torneo->fetch();
				$torneo_estado = $torneo['torneo_estado'];
			}	
			
			# Almacenando datos#
			$torneo_nombre		= $this->limpiarCadena($_POST['torneo_nombre']);
			$torneo_ciudad		= $this->limpiarCadena($_POST['torneo_ciudad']);
			$torneo_lugar		= $this->limpiarCadena($_POST['torneo_lugar']);
			$torneo_fechainicio	= $this->limpiarCadena($_POST['torneo_fechainicio']);
			$torneo_fechafin	= $this->limpiarCadena($_POST['torneo_fechafin']);		    		    
			$torneo_organizador	= $this->limpiarCadena($_POST['torneo_organizador']);
			$torneo_descripcion	= $this->limpiarCadena($_POST['torneo_descripcion']);
			
			# Verificando campos obligatorios #
			if($torneo_nombre=="" || $torneo_ciudad=="" || $torneo_lugar==""){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			$torneo_datos_reg=[
				[
					"campo_nombre"=>"torneo_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$torneo_nombre
				],
				[
					"campo_nombre"=>"torneo_ciudad",
					"campo_marcador"=>":Ciudad",
					"campo_valor"=>$torneo_ciudad
				],
				[
					"campo_nombre"=>"torneo_lugar",
					"campo_marcador"=>":Lugar",
					"campo_valor"=>$torneo_lugar
				],
				[
					"campo_nombre"=>"torneo_fechainicio",
					"campo_marcador"=>":Fechainicio",
					"campo_valor"=>$torneo_fechainicio
				],				
				[
					"campo_nombre"=>"torneo_fechafin",
					"campo_marcador"=>":Fechafin",
					"campo_valor"=>$torneo_fechafin
				],				
				[
					"campo_nombre"=>"torneo_organizador",
					"campo_marcador"=>":Organizador",
					"campo_valor"=>$torneo_organizador
				],
				[
					"campo_nombre"=>"torneo_descripcion",
					"campo_marcador"=>":Descripcion",
					"campo_valor"=>$torneo_descripcion
				],
				[
					"campo_nombre"=>"torneo_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$torneo_estado
				],
				[
					"campo_nombre"=>"torneo_fechaactualizado",
					"campo_marcador"=>":Actualizado",
					"campo_valor"=>date("Y-m-d H:i:s")
				]
			];
			
			# Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/torneos/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['torneo_foto']['name']!="" && $_FILES['torneo_foto']['size']>0){

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
				if(mime_content_type($_FILES['torneo_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['torneo_foto']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['torneo_foto']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$torneo_nombre);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['torneo_foto']['tmp_name'])){
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
				$inputFile = ($_FILES['torneo_foto']['tmp_name']);
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
				if(is_file($img_dir.$torneo['torneo_foto']) && $torneo['torneo_foto']!=$foto){
					chmod($img_dir.$torneo['torneo_foto'], 0777);
					unlink($img_dir.$torneo['torneo_foto']);
				}	

				$torneo_datos_reg[] = [
					"campo_nombre"=>"torneo_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				];
			}

			$condicion=[
				"condicion_campo"=>"torneo_id",
				"condicion_marcador"=>":Torneoid",
				"condicion_valor"=>$torneoid
			];

			if($this->actualizarDatos("torneo_torneo",$torneo_datos_reg,$condicion)){					
				
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'torneoList/',
					"titulo"=>"Torneo actualizado",
					"texto"=>"El torneo ".$torneo_nombre." se actualizó correctamente",
					"icono"=>"success"
				];
			}else{				
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible actualizar el torneo, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
        }

		public function actualizarEstadoTorneoControlador(){
			$torneoid=$this->limpiarCadena($_POST['torneo_id']);

			# Verificando usuario #
			$torneo=$this->ejecutarConsulta("SELECT * FROM torneo_torneo WHERE torneo_id = :torneo", [':torneo' => (int)$torneoid]);
			if($torneo->rowCount()<=0){	
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El torneo no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$torneo=$torneo->fetch();
		    }
			if($torneo['torneo_estado']=='A'){
				$estadoA = 'I';
			}else{
				$estadoA = 'A';
			}
            $torneo_datos_up=[
				[
					"campo_nombre"=>"torneo_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"torneo_id",
				"condicion_marcador"=>":Torneoid",
				"condicion_valor"=>$torneoid
			];

			if($this->actualizarDatos("torneo_torneo",$torneo_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Estado actualizado correctamente",
					"texto"=>"El estado del torneo ".$torneo['torneo_nombre']." fue actualizado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No hemos podido actualizar el estado del torneo ".$torneo['torneo_nombre']." por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		/*----------  Controlador eliminar torneo  ----------*/
		public function eliminarTorneoControlador(){

			$torneoid=$this->limpiarCadena($_POST['torneo_id']);

			# Verificando existencia de torneo #
			$torneo=$this->ejecutarConsulta("SELECT * FROM torneo_torneo WHERE torneo_id = :torneo", [':torneo' => (int)$torneoid]);
			if($torneo->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El torneo no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}else{					
				/* Misma correccion que en representanteController: la consulta
				   de existencia de arriba ya usaba marcador y esta seguia
				   interpolando el valor del POST. */
				$equipostorneo=$this->ejecutarConsulta("SELECT *
															FROM torneo_equipo
															WHERE equipo_estado in ('A','I')
																AND equipo_torneoid = :torneo", [':torneo' => (int)$torneoid]);
				if($equipostorneo->rowCount()>0){	
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"El torneo tiene equipos activos, para continuar debe eliminarlos",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}else{
					$torneo=$torneo->fetch();				
				}			
			}
			if($torneo['torneo_estado']=='A' || $torneo['torneo_estado']=='I'){
				$estadoA = 'E';
			}else{
				$estadoA = 'X';
			}
			$torneo_datos_up=[
				[
					"campo_nombre"=>"torneo_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"torneo_id",
				"condicion_marcador"=>":Torneoid",
				"condicion_valor"=>$torneoid
			];

			if($this->actualizarDatos("torneo_torneo",$torneo_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"El torneo fue eliminado correctamente",
					"texto"=>"El torneo ".$torneo['torneo_nombre']." fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No hemos podido eliminar el torneo ".$torneo['torneo_nombre']." por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}
    }