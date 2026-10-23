<?php

	namespace app\controllers;
	use app\models\mainModel;

	class torneoController extends mainModel{

		/*----------  Controlador registrar usuario  ----------*/
		/*----------  Fechas del torneo  ----------*/
		/*
		| Las dos son NOT NULL en torneo_torneo y no se validaban. Con el MySQL
		| local en modo permisivo, una fecha vacía entraba como 0000-00-00; en
		| un servidor en modo estricto —lo normal en un hosting— el alta moría
		| con un error de SQL en vez de un aviso. Devuelve la alerta en JSON, o
		| null si las fechas son válidas.
		*/
		private function validarFechasTorneo(string $inicio, string $fin): ?string {
			$valida = static function (string $f): bool {
				$d = \DateTime::createFromFormat('Y-m-d', $f);
				return $d !== false && $d->format('Y-m-d') === $f;
			};
			if ($inicio === '' || $fin === '') {
				return json_encode(["tipo"=>"simple", "titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios", "icono"=>"error"]);
			}
			if (!$valida($inicio) || !$valida($fin)) {
				return json_encode(["tipo"=>"simple", "titulo"=>"Fecha no válida",
					"texto"=>"Revise las fechas de inicio y de finalización del torneo.", "icono"=>"error"]);
			}
			if ($fin < $inicio) {
				return json_encode(["tipo"=>"simple", "titulo"=>"Fechas invertidas",
					"texto"=>"La fecha de finalización no puede ser anterior a la de inicio.", "icono"=>"error"]);
			}
			return null;
		}

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
			if(($error = $this->validarFechasTorneo($torneo_fechainicio, $torneo_fechafin)) !== null){
				return $error;
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
			/* Equipos inscritos por torneo: los no eliminados (activos e
			   inactivos), igual que los que muestra equipoList. Una sola
			   subconsulta agrupada —usa el índice fk_torneo— en vez de una
			   consulta por fila; el LEFT JOIN deja a 0 los torneos sin equipos. */
			$consulta_datos="SELECT t.torneo_id, t.torneo_nombre, t.torneo_ciudad, t.torneo_lugar, t.torneo_foto, t.torneo_fechainicio,
									t.torneo_fechafin, t.torneo_organizador, t.torneo_descripcion,
								COALESCE(eq.total, 0) AS EQUIPOS,
								CASE WHEN t.torneo_estado='A' THEN 'Activo'
									 WHEN t.torneo_estado = 'I' THEN 'Inactivo'
									 ELSE t.torneo_estado
								END AS ESTADO
							 FROM torneo_torneo t
							 LEFT JOIN (SELECT equipo_torneoid, COUNT(*) AS total
							              FROM torneo_equipo
							             WHERE equipo_estado IN ('A','I')
							             GROUP BY equipo_torneoid) eq ON eq.equipo_torneoid = t.torneo_id
							 WHERE t.torneo_estado IN ('A','I')
							 ORDER BY t.torneo_fechainicio DESC, t.torneo_id DESC";

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();

			/*
			| UNA FILA, DOS PRESENTACIONES
			|
			| En escritorio es una fila de tabla; en el teléfono, futbol.css la
			| convierte en ficha (.ds-crud). El marcado es uno solo: los
			| formularios de eliminar y de cambiar el estado no se duplican.
			|
			| Lo que viene de la base se escapa: el nombre o la descripción de un
			| torneo los escribe un usuario, y antes se imprimían tal cual.
			|
			| Se decodifica ANTES de escapar porque la base tiene de los dos:
			| limpiarHtml() guarda lo escrito en el sistema ya escapado
			| («WOMEN&quot;S»), y lo heredado de SoccerEasy está en crudo. Escapar
			| sin más mostraría «&quot;» al pie de la letra en lo primero.
			*/
			$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
			$fecha = static fn($d) => ($d && $d !== '0000-00-00') ? date('d/m/Y', strtotime($d)) : '—';

			foreach($datos as $rows){
				$id = (int)$rows['torneo_id'];
				if($rows['ESTADO']=='Activo'){
					$badge = '<span class="badge text-bg-success">Activo</span>';
					$texto = "Inactivar";
					$icono = "fa-toggle-on";
				}else{
					$badge = '<span class="badge text-bg-secondary">Inactivo</span>';
					$texto = "Activar";
					$icono = "fa-toggle-off";
				}

				$sitio = implode(' · ', array_filter([$h($rows['torneo_ciudad']), $h($rows['torneo_lugar'])], 'strlen'));
				$descripcion = trim((string)$rows['torneo_descripcion']) !== ''
					? '<div class="ds-ficha__desc">'.$h($rows['torneo_descripcion']).'</div>' : '';

				$tabla.='
					<tr>
						<td class="ds-ficha__principal">
							<div class="ds-ficha__nombre">'.$h($rows['torneo_nombre']).'</div>
							'.($sitio !== '' ? '<div class="ds-ficha__meta"><i class="fas fa-location-dot" aria-hidden="true"></i> '.$sitio.'</div>' : '').'
							'.$descripcion.'
						</td>
						<td class="ds-ficha__dato ds-ficha__nowrap" data-order="'.$h($rows['torneo_fechainicio']).'">
							<i class="far fa-calendar" aria-hidden="true"></i>
							<span>'.$fecha($rows['torneo_fechainicio']).'</span> – <span>'.$fecha($rows['torneo_fechafin']).'</span>
						</td>
						<td class="ds-ficha__dato ds-torneo__equipos" data-etiqueta="Equipos" data-order="'.(int)$rows['EQUIPOS'].'">
							<span class="badge rounded-pill '.((int)$rows['EQUIPOS'] > 0 ? 'text-bg-primary' : 'text-bg-light border').'" title="Equipos inscritos">'.(int)$rows['EQUIPOS'].'</span>
						</td>
						<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Organiza">'.($rows['torneo_organizador'] !== '' ? $h($rows['torneo_organizador']) : '<span class="text-body-secondary">—</span>').'</td>
						<td class="ds-ficha__estado" data-order="'.$h($rows['ESTADO']).'">'.$badge.'</td>
						<td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">
							<a href="'.APP_URL.'equipoList/'.$id.'/" class="btn btn-primary btn-sm ds-ficha__primario"><i class="fas fa-users me-1" aria-hidden="true"></i>Equipos</a>
							'.$this->siPuede('editar','torneoList','<a href="'.APP_URL.'torneoList/'.$id.'/" class="btn btn-outline-success btn-sm" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
							'.$this->siPuede('editar','torneoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/torneoAjax.php" method="POST" autocomplete="off">
								<input type="hidden" name="modulo_torneo" value="actualizarestado">
								<input type="hidden" name="torneo_id" value="'.$id.'">
								<button type="submit" class="btn btn-outline-secondary btn-sm" title="'.$texto.'" aria-label="'.$texto.'"><i class="fas '.$icono.'"></i></button>
							</form>').'
							'.$this->siPuede('eliminar','torneoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/torneoAjax.php" method="POST" autocomplete="off">
								<input type="hidden" name="modulo_torneo" value="eliminar">
								<input type="hidden" name="torneo_id" value="'.$id.'">
								'.ds_boton_eliminar('el torneo '.$rows['torneo_nombre']).'
							</form>').'
						</div></td>
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
			if(($error = $this->validarFechasTorneo($torneo_fechainicio, $torneo_fechafin)) !== null){
				return $error;
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