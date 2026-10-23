<?php

	namespace app\controllers;
	use app\models\mainModel;
	use DateTime;

	class empleadoController extends mainModel{

		/*----------  Módulo empleado  ----------*/
		public function registrarEmpleadoControlador(){
			# Almacenando datos#
			$empleado_sedeid 			= $this->limpiarCadena($_POST['empleado_sedeid']);
			$empleado_tipoidentificacion= $this->limpiarCadena($_POST['empleado_tipoidentificacion']);
			$empleado_identificacion	= $this->limpiarCadena($_POST['empleado_identificacion']);
			$empleado_nombre			= $this->limpiarCadena($_POST['empleado_nombre']);
			$empleado_correo			= $this->limpiarCadena($_POST['empleado_correo']);		    		    
			$empleado_celular			= $this->limpiarCadena($_POST['empleado_celular']);
			$empleado_direccion			= $this->limpiarCadena($_POST['empleado_direccion']);
			$empleado_tipopersonalid 	= $this->limpiarCadena($_POST['empleado_tipopersonalid']);
			$empleado_especialidadid	= $this->limpiarCadena($_POST['empleado_especialidadid'] ?? '');
			$empleado_fechaingreso		= $this->limpiarCadena($_POST['empleado_fechaingreso']);
			$empleado_genero 			= $this->limpiarCadena($_POST['empleado_genero']);
			$empleado_sueldo			= $this->limpiarCadena($_POST['empleado_sueldo']);			
			$empleado_estado			= "A";

			$empleado_sueldo = str_replace(['$', ',', ' '], '', $empleado_sueldo);

			# Verificando campos obligatorios #
			if($empleado_identificacion=="" || $empleado_nombre=="" || $empleado_celular=="" || $empleado_correo==""){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			# Verificando campos obligatorios #
			if($empleado_tipopersonalid == "TPP"){
				$empleado_especialidadid = $this->limpiarCadena($_POST['empleado_especialidadid'] ?? '');
			}else{				
				$empleado_especialidadid = "";
			}

			# Verificando email #
			if($empleado_correo!=""){
				if(filter_var($empleado_correo, FILTER_VALIDATE_EMAIL)){
					$check_email=$this->ejecutarConsulta("SELECT empleado_correo FROM sujeto_empleado WHERE empleado_correo = :correo", [':correo' => $empleado_correo]);
					if($check_email->rowCount()>0){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"El EMAIL ya se encuentra registrado en el sistema, por favor verifique e intente nuevamente",
							"icono"=>"error"
						];
						return json_encode($alerta);
					}
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Ha ingresado un correo electrónico no válido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}
			}

				# Verificando celular #
				if($empleado_celular!=""){
				$check_movil=$this->ejecutarConsulta("SELECT empleado_celular FROM sujeto_empleado WHERE empleado_celular = :celular", [':celular' => $empleado_celular]);
				if($check_movil->rowCount()>0){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Número de celular ya existe",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}
			}

			# Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/empleado/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['empleado_foto']['name']!="" && $_FILES['empleado_foto']['size']>0){

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
				if(mime_content_type($_FILES['empleado_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['empleado_foto']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen tiene un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['empleado_foto']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$empleado_identificacion);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['empleado_foto']['tmp_name'])){
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
				$inputFile = ($_FILES['empleado_foto']['tmp_name']);
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
			$empleado_datos_reg=[
				[
					"campo_nombre"=>"empleado_sedeid",
					"campo_marcador"=>":Sede",
					"campo_valor"=>$empleado_sedeid
				],
				[
					"campo_nombre"=>"empleado_tipoidentificacion",
					"campo_marcador"=>":TipoIdentificacion",
					"campo_valor"=>$empleado_tipoidentificacion
				],
				[
					"campo_nombre"=>"empleado_identificacion",
					"campo_marcador"=>":Identificacion",
					"campo_valor"=>$empleado_identificacion
				],
				[
					"campo_nombre"=>"empleado_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$empleado_nombre
				],				
				[
					"campo_nombre"=>"empleado_correo",
					"campo_marcador"=>":Correo",
					"campo_valor"=>$empleado_correo
				],				
				[
					"campo_nombre"=>"empleado_celular",
					"campo_marcador"=>":Celular",
					"campo_valor"=>$empleado_celular
				],
				[
					"campo_nombre"=>"empleado_direccion",
					"campo_marcador"=>":Direccion",
					"campo_valor"=>$empleado_direccion
				],
				[
					"campo_nombre"=>"empleado_tipopersonalid",
					"campo_marcador"=>":TipoPersonal",
					"campo_valor"=>$empleado_tipopersonalid
				],	
				[
					"campo_nombre"=>"empleado_especialidadid",
					"campo_marcador"=>":Especialidad",
					"campo_valor"=>$empleado_especialidadid
				],			
				[
					"campo_nombre"=>"empleado_fechaingreso",
					"campo_marcador"=>":Fechaingreso",
					"campo_valor"=>$empleado_fechaingreso
				],
				[
					"campo_nombre"=>"empleado_genero",
					"campo_marcador"=>":Genero",
					"campo_valor"=>$empleado_genero
				],
				[
					"campo_nombre"=>"empleado_sueldo",
					"campo_marcador"=>":Sueldo",
					"campo_valor"=>$empleado_sueldo
				],
				[
					"campo_nombre"=>"empleado_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$empleado_estado
				],
				[
					"campo_nombre"=>"empleado_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				]
			];

			$registrar_empleado=$this->guardarDatos("sujeto_empleado",$empleado_datos_reg);

			if($registrar_empleado->rowCount()==1){
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'empleadoList/',
					"titulo"=>"Empleado registrado",
					"texto"=>"El empleado ".$empleado_nombre." se registró correctamente",
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
					"texto"=>"No se pudo registrar el empleado, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
        }

        public function listarOptionSede($sedeid){
			$option="";

			$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($sedeid == $rows['sede_id']){	
					$option.='<option value='.$rows['sede_id'].' selected="selected">'.$rows['sede_nombre'].'</option>';
				}else{
					$option.='<option value='.$rows['sede_id'].'>'.$rows['sede_nombre'].'</option>';
				}					
			}
			return $option;
		}

		/*----------  Empleados ingresados (vista empleadoList)  ----------*/
		/*
		| Una fila por empleado: quién es (foto, cédula, tipo y especialidad),
		| sede, contacto, acceso al sistema y estado. Marcado de fichas
		| (.ds-crud): tabla en escritorio y ficha por debajo de 992 px.
		|
		| ANTES: ocho columnas con float-end, sin el estado a la vista (sólo el
		| texto del botón Activar/Inactivar lo delataba) y los datos sin
		| escapar. El botón «Registrar» de honorarios abría otra pestaña.
		*/
		public function listarEmpleados(){
			$datos = $this->ejecutarConsulta("SELECT E.empleado_id, E.empleado_identificacion, E.empleado_nombre, E.empleado_correo,
						E.empleado_celular, E.empleado_foto, E.empleado_estado, S.sede_nombre,
						TP.catalogo_descripcion AS tipo, ES.catalogo_descripcion AS especialidad,
						U.usuario_estado
					FROM sujeto_empleado E
					LEFT JOIN general_sede S ON S.sede_id = E.empleado_sedeid
					LEFT JOIN seguridad_usuario U ON U.usuario_empleadoid = E.empleado_id
					LEFT JOIN general_tabla_catalogo TP ON TP.catalogo_valor = E.empleado_tipopersonalid
					       AND TP.catalogo_tablaid = (SELECT tabla_id FROM general_tabla WHERE tabla_nombre = 'tipo_personal' LIMIT 1)
					LEFT JOIN general_tabla_catalogo ES ON ES.catalogo_valor = E.empleado_especialidadid
					       AND ES.catalogo_tablaid = (SELECT tabla_id FROM general_tabla WHERE tabla_nombre = 'especialidad_empleado' LIMIT 1)
					WHERE E.empleado_estado IN ('A','I')
					ORDER BY E.empleado_estado, E.empleado_nombre")->fetchAll();

			/* Crear el usuario de sistema de un empleado es competencia de
			   DigiSports Core; el enlace sale de aquí por comodidad, pero
			   sólo se ofrece a quien tenga acceso a ese módulo. */
			$accesoCore = usuario_tiene_modulo('core');

			$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

			$tabla = "";
			foreach ($datos as $rows) {
				$id     = (int)$rows['empleado_id'];
				$activo = $rows['empleado_estado'] === 'A';
				$nombre = preg_replace('/\s+/', ' ', trim(html_entity_decode((string)$rows['empleado_nombre'], ENT_QUOTES | ENT_HTML5, 'UTF-8')));

				/* Foto, o las iniciales si no tiene. */
				$partes  = preg_split('/\s+/', $nombre);
				$inicial = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));
				$imagen  = trim((string)$rows['empleado_foto']) !== ''
					? '<img src="' . $h(media_url('empleado', $rows['empleado_foto'])) . '" alt="" loading="lazy" width="40" height="40" class="ds-ficha__imagen">'
					: '<span class="ds-ficha__imagen ds-alumnos__inicial" aria-hidden="true">' . $h($inicial) . '</span>';

				$puesto = implode(' · ', array_filter([$h($rows['tipo']), $h($rows['especialidad'])], 'strlen'));

				/* Contacto: el celular llama y el correo abre el cliente de correo. */
				$celular = preg_replace('/\D/', '', (string)$rows['empleado_celular']);
				$correo  = trim((string)$rows['empleado_correo']);
				$contacto = ($celular !== '' ? '<div><a href="tel:' . $celular . '" class="text-decoration-none text-nowrap"><i class="fas fa-phone me-1 text-body-secondary" aria-hidden="true"></i>' . $h($rows['empleado_celular']) . '</a></div>' : '')
					. ($correo !== '' ? '<div class="small text-break"><a href="mailto:' . $h($correo) . '" class="text-decoration-none">' . $h($correo) . '</a></div>' : '');

				/* Acceso al sistema: el usuario de DigiSports Core, si lo tiene. */
				if ($rows['usuario_estado'] === 'A') {
					$acceso = '<span class="badge text-bg-success">Con acceso</span>';
				} elseif ($rows['usuario_estado'] === 'I') {
					$acceso = '<span class="badge text-bg-secondary">Usuario inactivo</span>';
				} else {
					$acceso = '<span class="text-body-secondary small">Sin usuario</span>'
						. ($accesoCore ? ' <a href="' . DS_HUB_URL . 'ds_core/admin/usuarioForm/?empleado=' . $id . '" class="btn btn-secondary btn-sm ms-1" target="_blank" rel="noopener" title="Crear su usuario en DigiSports Core"><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Asignar</a>' : '');
				}

				$botones = '<a href="' . APP_URL . 'empleadoIE/' . $id . '/" class="btn btn-primary btn-sm ds-ficha__primario"><i class="fas fa-dollar-sign me-1" aria-hidden="true"></i>Honorarios</a>';
				$botones .= $this->siPuede('editar', 'empleadoList',
					'<a href="' . APP_URL . 'empleadoList/' . $id . '/#empleado-form" class="btn btn-info btn-sm" title="Editar" aria-label="Editar a ' . $h($nombre) . '"><i class="fas fa-pen" aria-hidden="true"></i></a>'
					. '<form class="FormularioAjax" action="' . APP_URL . 'app/ajax/empleadoAjax.php" method="POST" autocomplete="off">
						<input type="hidden" name="modulo_empleado" value="actualizarestado">
						<input type="hidden" name="empleado_id" value="' . $id . '">
						<button type="submit" class="btn btn-sm ' . ($activo ? 'btn-secondary' : 'btn-success') . '" title="' . ($activo ? 'Inactivar' : 'Activar') . '" aria-label="' . ($activo ? 'Inactivar' : 'Activar') . ' a ' . $h($nombre) . '"><i class="fas ' . ($activo ? 'fa-user-slash' : 'fa-user-check') . '" aria-hidden="true"></i></button>
					</form>');
				$botones .= $this->siPuede('eliminar', 'empleadoList',
					'<form class="FormularioAjax" action="' . APP_URL . 'app/ajax/empleadoAjax.php" method="POST" autocomplete="off">
						<input type="hidden" name="modulo_empleado" value="eliminar">
						<input type="hidden" name="empleado_id" value="' . $id . '">
						' . ds_boton_eliminar('a ' . $nombre) . '
					</form>');

				$tabla .= '
					<tr>
						<td class="ds-ficha__principal" data-order="' . $h($nombre) . '">
							<div class="ds-ficha__con-imagen">' . $imagen . '
								<div class="min-w-0">
									<div class="ds-ficha__nombre">' . $h($nombre) . '</div>
									<div class="ds-ficha__meta"><i class="fas fa-id-card" aria-hidden="true"></i> ' . $h($rows['empleado_identificacion']) . ($puesto !== '' ? ' · ' . $puesto : '') . '</div>
								</div>
							</div>
						</td>
						<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Sede">' . $h($rows['sede_nombre']) . '</td>
						<td class="ds-ficha__dato" data-etiqueta="Contacto">' . ($contacto !== '' ? $contacto : '<span class="text-body-secondary">—</span>') . '</td>
						<td class="ds-ficha__dato" data-etiqueta="Acceso al sistema">' . $acceso . '</td>
						<td class="ds-ficha__estado" data-order="' . ($activo ? '0' : '1') . '"><span class="badge ' . ($activo ? 'text-bg-success' : 'text-bg-secondary') . '">' . ($activo ? 'Activo' : 'Inactivo') . '</span></td>
						<td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">' . $botones . '</div></td>
					</tr>';
			}
			return $tabla;
		}

		public function OptionTipoIdentificacion($tipoidentificacion){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'tipo_documento'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($tipoidentificacion == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';	
				}else{
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}
			}
			return $option;
		}

		public function OptionEspecialidad($especialidadid){
			$option ='<option value=0> Seleccione la especialidad</option>';
			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'especialidad_empleado'
									AND T.tabla_estado = 'A'
									AND C.catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($especialidadid == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';	
				}else{
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}
			}
			return $option;
		}

		public function actualizarEmpleadoControlador(){

			$empleadoid=$this->limpiarCadena($_POST['empleado_id']);

			# Verificando existencia de empleado #
			$empleado=$this->ejecutarConsulta("SELECT * FROM sujeto_empleado WHERE empleado_id = :id", [':id' => (int)$empleadoid]);
			if($empleado->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El empleado no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);		    
			}else{
				$empleado=$empleado->fetch();
			}	
			
			# Almacenando datos#
			$empleado_sedeid 			= $this->limpiarCadena($_POST['empleado_sedeid']);
			$empleado_tipoidentificacion= $this->limpiarCadena($_POST['empleado_tipoidentificacion']);
			$empleado_identificacion	= $this->limpiarCadena($_POST['empleado_identificacion']);
			$empleado_nombre			= $this->limpiarCadena($_POST['empleado_nombre']);
			$empleado_correo			= $this->limpiarCadena($_POST['empleado_correo']);		    		    
			$empleado_celular			= $this->limpiarCadena($_POST['empleado_celular']);
			$empleado_direccion			= $this->limpiarCadena($_POST['empleado_direccion']);
			$empleado_tipopersonalid 	= $this->limpiarCadena($_POST['empleado_tipopersonalid']);
			$empleado_fechaingreso		= $this->limpiarCadena($_POST['empleado_fechaingreso']);
			$empleado_sueldo			= $this->limpiarCadena($_POST['empleado_sueldo']);
			$empleado_genero 			= $this->limpiarCadena($_POST['empleado_genero']);

			$empleado_sueldo = str_replace(['$', ',', ' '], '', $empleado_sueldo);

			# Verificando campos obligatorios #
			if($empleado_identificacion=="" || $empleado_nombre=="" || $empleado_celular=="" || $empleado_correo==""){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			# Verificando campos obligatorios #
			if($empleado_tipopersonalid == "TPP"){
				$empleado_especialidadid = $this->limpiarCadena($_POST['empleado_especialidadid'] ?? '');
			}else{				
				$empleado_especialidadid = "";
			}

			$empleado_datos_reg=[
				[
					"campo_nombre"=>"empleado_sedeid",
					"campo_marcador"=>":Sede",
					"campo_valor"=>$empleado_sedeid
				],
				[
					"campo_nombre"=>"empleado_tipoidentificacion",
					"campo_marcador"=>":TipoIdentificacion",
					"campo_valor"=>$empleado_tipoidentificacion
				],
				[
					"campo_nombre"=>"empleado_identificacion",
					"campo_marcador"=>":Identificacion",
					"campo_valor"=>$empleado_identificacion
				],
				[
					"campo_nombre"=>"empleado_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$empleado_nombre
				],				
				[
					"campo_nombre"=>"empleado_correo",
					"campo_marcador"=>":Correo",
					"campo_valor"=>$empleado_correo
				],				
				[
					"campo_nombre"=>"empleado_celular",
					"campo_marcador"=>":Celular",
					"campo_valor"=>$empleado_celular
				],
				[
					"campo_nombre"=>"empleado_direccion",
					"campo_marcador"=>":Direccion",
					"campo_valor"=>$empleado_direccion
				],		
				[
					"campo_nombre"=>"empleado_tipopersonalid",
					"campo_marcador"=>":TipoPersonal",
					"campo_valor"=>$empleado_tipopersonalid
				],	
				[
					"campo_nombre"=>"empleado_especialidadid",
					"campo_marcador"=>":Especialidad",
					"campo_valor"=>$empleado_especialidadid
				],
				[
					"campo_nombre"=>"empleado_fechaingreso",
					"campo_marcador"=>":Fechaingreso",
					"campo_valor"=>$empleado_fechaingreso
				],
				[
					"campo_nombre"=>"empleado_genero",
					"campo_marcador"=>":Genero",
					"campo_valor"=>$empleado_genero
				],
				[
					"campo_nombre"=>"empleado_sueldo",
					"campo_marcador"=>":Sueldo",
					"campo_valor"=>$empleado_sueldo
				]
			];
			
			# Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/empleado/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['empleado_foto']['name']!="" && $_FILES['empleado_foto']['size']>0){

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
				if(mime_content_type($_FILES['empleado_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['empleado_foto']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['empleado_foto']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$empleado_identificacion);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['empleado_foto']['tmp_name'])){
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
				$inputFile = ($_FILES['empleado_foto']['tmp_name']);
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
				if(is_file($img_dir.$empleado['empleado_foto']) && $empleado['empleado_foto']!=$foto){
					chmod($img_dir.$empleado['empleado_foto'], 0777);
					unlink($img_dir.$empleado['empleado_foto']);
				}	

				$empleado_datos_reg[] = [
					"campo_nombre"=>"empleado_foto",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				];
			}

			$condicion=[
				"condicion_campo"=>"empleado_id",
				"condicion_marcador"=>":Empleadoid",
				"condicion_valor"=>$empleadoid
			];

			if($this->actualizarDatos("sujeto_empleado",$empleado_datos_reg,$condicion)){					
				
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'empleadoList/',
					"titulo"=>"Empleado actualizado",
					"texto"=>"El empleado ".$empleado_nombre." se actualizó correctamente",
					"icono"=>"success"
				];
			}else{				
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible actualizar el empleado, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
        }

		public function actualizarEstadoEmpleadoControlador(){
			$empleado_id=$this->limpiarCadena($_POST['empleado_id']);

			# Verificando usuario #
		    $datos=$this->ejecutarConsulta("SELECT * FROM sujeto_empleado WHERE empleado_id = :id", [':id' => (int)$empleado_id]);
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El empleado no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();
		    }
			if($datos['empleado_estado']=='A'){
				$estadoA = 'I';
			}else{
				$estadoA = 'A';
			}
            $empleado_datos_up=[
				[
					"campo_nombre"=>"empleado_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"empleado_id",
				"condicion_marcador"=>":Empleadoid",
				"condicion_valor"=>$empleado_id
			];

			if($this->actualizarDatos("sujeto_empleado",$empleado_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Estado actualizado correctamente",
					"texto"=>"El estado del empleado ".$datos['empleado_nombre']." fue actualizado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar el estado del empleado ".$datos['empleado_nombre']." por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function eliminarEmpleadoControlador(){

			$empleado_id=$this->limpiarCadena($_POST['empleado_id']);

			# Verificando usuario #
			$datos=$this->ejecutarConsulta("SELECT * FROM sujeto_empleado WHERE empleado_id = :id", [':id' => (int)$empleado_id]);
			if($datos->rowCount()<=0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El empleado no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}else{
				$datos=$datos->fetch();
			}
			if($datos['empleado_estado']=='A' || $datos['empleado_estado']=='I'){
				$estadoA = 'E';
			}else{
				$estadoA = 'X';
			}
			$empleado_datos_up=[
				[
					"campo_nombre"=>"empleado_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"empleado_id",
				"condicion_marcador"=>":Empleadoid",
				"condicion_valor"=>$empleado_id
			];

			if($this->actualizarDatos("sujeto_empleado",$empleado_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"El empleado fue eliminado correctamente",
					"texto"=>"El empleado ".$datos['empleado_nombre']." fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar el empleado ".$datos['empleado_nombre']." por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function listarTipoPersonal($ingreso_tipopersonalid){
			$option="";

			$consulta_datos="SELECT * FROM general_tabla, general_tabla_catalogo WHERE tabla_id = catalogo_tablaid AND tabla_nombre = 'tipo_personal' AND catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
                if($ingreso_tipopersonalid == $rows['catalogo_valor']){	
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{		
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
                }				
			}
			return $option;
		}

		/*----------  Módulo ingresos  ----------*/
		public function BuscarEmpleado($empleadoid){		
			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion AS Especialidad, 
                                CASE WHEN empleado_estado = 'A' THEN 'Activo' WHEN empleado_estado = 'I' THEN 'Inactivo' ELSE 'Sin definir' END estado, 
                                empleado_identificacion as identificacion, P.* 
                                FROM sujeto_empleado P
                                LEFT JOIN general_tabla_catalogo C ON P.empleado_especialidadid = C.catalogo_valor
								WHERE P.empleado_id = :empleado";
			$datos = $this->ejecutarConsulta($consulta_datos, [':empleado' => (int)$empleadoid]);
			return $datos;
		}

        public function listarOptionPago($ingreso_formapagoid){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'forma_pago' AND catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
                if($ingreso_formapagoid == $rows['catalogo_valor']){	
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{		
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
                }				
			}
			return $option;
		}

        public function listarTipoIngreso($ingreso_tipoingresoid){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'tipo_ingreso' AND catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
                if($ingreso_tipoingresoid == $rows['catalogo_valor']){	
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{		
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
                }				
			}
			return $option;
		}

		public function registrarIngreso(){

            # Almacenando datos#
			$ingreso_formapagoid 		= $this->limpiarCadena($_POST['ingreso_formapagoid']);
            $ingreso_tipoingresoid 		= $this->limpiarCadena($_POST['ingreso_tipoingresoid']);
            $ingreso_empleadoid 		= $this->limpiarCadena($_POST['ingreso_empleadoid']);
            $ingreso_valor     			= $this->limpiarCadena($_POST['ingreso_valor']);
            $ingreso_concepto 			= $this->limpiarCadena($_POST['ingreso_concepto']);
            $ingreso_fechafactura 		= $this->limpiarCadena($_POST['ingreso_fechafactura']);
            $ingreso_fechapago			= $this->limpiarCadena($_POST['ingreso_fechapago']);
            $ingreso_periodo 		    = $this->limpiarCadena($_POST['ingreso_periodo']);
            $ingreso_estado           = "C";
           
            if ($ingreso_valor =="") {$ingreso_valor = 0;}

			# Verificando campos obligatorios #
		    if($ingreso_fechafactura=="" || $ingreso_fechapago=="" || $ingreso_periodo=="" || $ingreso_valor=="" || $ingreso_formapagoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

			# Directorio de imagenes #
			$img_dir="../views/imagenes/ingresos/";
            $codigo=rand(0,100);

			# Comprobar si selecciono la factura #
    		if($_FILES['ingreso_factura']['name']!="" && $_FILES['ingreso_factura']['size']>0){
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
		        if(mime_content_type($_FILES['ingreso_factura']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['ingreso_factura']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['ingreso_factura']['size']/1024)>3000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Nombre de la foto #
		        $factura=str_ireplace(" ","_",$ingreso_empleadoid);
		        $factura=$factura."_".$codigo;

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['ingreso_factura']['tmp_name'])){
		            case 'image/jpeg':
		                $factura="F".$factura.".jpg";
		            break;
		            case 'image/png':
		                $factura="F".$factura.".png";
		            break;
		        }

                $maxWidth = 800;
    			$maxHeight = 600;

				chmod($img_dir,0777);
				$inputFile = ($_FILES['ingreso_factura']['tmp_name']);
       			$outputFile = $img_dir.$factura;

				# Moviendo imagen al directorio #
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}
    		}else{
    			$factura="";
    		}	

            # Comprobar si selecciono el comprobante de pago #
    		if($_FILES['ingreso_comprobante']['name']!="" && $_FILES['ingreso_comprobante']['size']>0){
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
		        if(mime_content_type($_FILES['ingreso_comprobante']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['ingreso_comprobante']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['ingreso_comprobante']['size']/1024)>3000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Nombre de la foto #
		        $comprobante=str_ireplace(" ","_",$ingreso_empleadoid);
		        $comprobante=$comprobante."_".$codigo;

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['ingreso_comprobante']['tmp_name'])){
		            case 'image/jpeg':
		                $comprobante="C".$comprobante.".jpg";
		            break;
		            case 'image/png':
		                $comprobante="C".$comprobante.".png";
		            break;
		        }

                $maxWidth = 800;
    			$maxHeight = 600;

				chmod($img_dir,0777);
				$inputFile = ($_FILES['ingreso_comprobante']['tmp_name']);
       			$outputFile = $img_dir.$comprobante;

				# Moviendo imagen al directorio #
				//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}
    		}else{
    			$comprobante="";
    		}

            $ingreso_datos_reg=[
				[
					"campo_nombre"=>"ingreso_formapagoid",
					"campo_marcador"=>":Formapagoid",
					"campo_valor"=>$ingreso_formapagoid
				],		
                [
					"campo_nombre"=>"ingreso_tipoingresoid",
					"campo_marcador"=>":Tipopagoid",
					"campo_valor"=>$ingreso_tipoingresoid
				],			
				[
					"campo_nombre"=>"ingreso_empleadoid",
					"campo_marcador"=>":Empleadoid",
					"campo_valor"=>$ingreso_empleadoid
				],				
				[
					"campo_nombre"=>"ingreso_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$ingreso_valor
				],	
				[
					"campo_nombre"=>"ingreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$ingreso_concepto
				],
				[
					"campo_nombre"=>"ingreso_fechafactura",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$ingreso_fechafactura
				],				
				[
					"campo_nombre"=>"ingreso_fechapago",
					"campo_marcador"=>":Fechapago",
					"campo_valor"=>$ingreso_fechapago
				],
				[
					"campo_nombre"=>"ingreso_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$ingreso_periodo
				],				
				[
					"campo_nombre"=>"ingreso_factura",
					"campo_marcador"=>":Factura",
					"campo_valor"=>$factura
				],
				[
					"campo_nombre"=>"ingreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$ingreso_estado 
				],
				[
					"campo_nombre"=>"ingreso_comprobante",
					"campo_marcador"=>":Comprobante",
					"campo_valor"=>$comprobante
				]
			];	

            $registrar_ingreso=$this->guardarDatos("empleado_ingreso",$ingreso_datos_reg);
            if($registrar_ingreso->rowCount()>0){
                $alerta=[
                    "tipo"=>"recargar",
                    "titulo"=>"ingreso registrado",
                    "texto"=>"El ingreso se registró correctamente",
                    "icono"=>"success"
                ];

            }else{
                
                if(is_file($img_dir.$factura)){
                    chmod($img_dir.$factura,0777);
                    unlink($img_dir.$factura);
                }

                $alerta=[
                    "tipo"=>"simple",
                    "titulo"=>"Ocurrió un error inesperado",
                    "texto"=>"No se pudo registrar el ingreso, por favor intente nuevamente",
                    "icono"=>"error"
                ];
            }
            return json_encode($alerta);
        }

        public function listarPagosIngreso($empleadoid){            	
			$tabla="";
			$eliminarpago="";
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY ingreso_id) AS fila_numero, IFNULL(P.PAGOS_PENDIENTES, 0)PAGOS_PENDIENTES, 
                                F.catalogo_descripcion as FormaPago, T.catalogo_descripcion as TipoPago, H.* 
                                FROM empleado_ingreso H  
                                LEFT JOIN (
                                    SELECT COUNT(1)PAGOS_PENDIENTES, trxh_pagoid 
                                    FROM empleado_ingreso_trx
                                    GROUP BY trxh_pagoid
                                )P ON P.trxh_pagoid = H.ingreso_id
                                LEFT JOIN general_tabla_catalogo F on F.catalogo_valor = ingreso_formapagoid 
                                LEFT JOIN general_tabla_catalogo T on T.catalogo_valor = ingreso_tipoingresoid 
                                WHERE (H.ingreso_empleadoid = :empleado AND H.ingreso_estado NOT IN ('E')) ORDER BY ingreso_id DESC";
                $datos = $this->ejecutarConsulta($consulta_datos, [':empleado' => (int)$empleadoid]);
                $datos = $datos->fetchAll();
                foreach($datos as $rows){

                if ($rows['ingreso_estado'] == 'C'){
                    $ingreso_estado = 'Cancelado';
                    $class = '';
                }elseif($rows['ingreso_estado'] == 'P'){
                    $ingreso_estado = '<span class="badge bg-danger"> Pendiente';
                    $class = 'class="text-danger"';
                }elseif($rows['ingreso_estado'] == 'J'){
                    $ingreso_estado = ' Justificado';
                    $class = 'class="text-primary"';
                }

                if($rows['PAGOS_PENDIENTES']>0){
                    $eliminarpago="disabled";
                }else{
                    $eliminarpago="";
                }
				
			$tabla.='
				<tr '.$class.'>
					<td>'.$rows['fila_numero'].'</td>
					<td>'.$rows['ingreso_periodo'].'</td>
					<td>'.$rows['ingreso_valor'].'</td>
					<td>'.$rows['FormaPago'].'</td>
                    <td>'.$rows['TipoPago'].'</td>
					<td>'.$ingreso_estado.'</td>
					<td>
						'.$this->siPuede('eliminar','empleadoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/ingresoAjax.php" method="POST" autocomplete="off" >
							<input type="hidden" name="modulo_ingreso" value="eliminar">
							<input type="hidden" name="ingreso_id" value="'.$rows['ingreso_id'].'">						
							<button type="submit" class="btn float-end btn-danger btn-sm " style="margin-right: 5px;" '.$eliminarpago.' title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
						</form>').'
						'.$this->siPuede('editar','empleadoList','<a href="'.APP_URL.'empleadoIE/'.$empleadoid.'/'.$rows['ingreso_id'].'/" class="btn float-end btn-success btn-sm" style="margin-right: 5px;"  title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'						
					</td>
				</tr>';	
			}
			return $tabla;		
        }

		public function actualizarIngreso(){
            $ingresoid=$this->limpiarCadena($_POST['ingreso_id']);

			# Verificando existencia de equipo #
			$ingreso=$this->ejecutarConsulta("SELECT * FROM empleado_ingreso WHERE ingreso_id = :id", [':id' => (int)$ingresoid]);
			if($ingreso->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El ingreso no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);		    
			}else{
				$ingreso              	= $ingreso->fetch();
                $ingreso_formapagoid	= $ingreso['ingreso_formapagoid'];
                $ingreso_tipoingresoid 	= $ingreso['ingreso_tipoingresoid'];
                $ingreso_empleadoid 	= $ingreso['ingreso_empleadoid'];
                $ingreso_valor     		= $ingreso['ingreso_valor'];
                $ingreso_concepto 		= $ingreso['ingreso_concepto'];
                $ingreso_fechafactura	= $ingreso['ingreso_fechafactura'];
                $ingreso_fechapago		= $ingreso['ingreso_fechapago'];
                $ingreso_periodo 		= $ingreso['ingreso_periodo'];
				$ingreso_estado 		= $ingreso['ingreso_estado'];
			}	

            # Almacenando datos#
			$ingreso_formapagoid 	= $this->limpiarCadena($_POST['ingreso_formapagoid']);
            $ingreso_tipoingresoid 	= $this->limpiarCadena($_POST['ingreso_tipoingresoid']);
            $ingreso_empleadoid 	= $this->limpiarCadena($_POST['ingreso_empleadoid']);
            $ingreso_valor     		= $this->limpiarCadena($_POST['ingreso_valor']);
            $ingreso_concepto 		= $this->limpiarCadena($_POST['ingreso_concepto']);
            $ingreso_fechafactura   = $this->limpiarCadena($_POST['ingreso_fechafactura']);
            $ingreso_fechapago		= $this->limpiarCadena($_POST['ingreso_fechapago']);
            $ingreso_periodo 		= $this->limpiarCadena($_POST['ingreso_periodo']);
            $ingreso_estado         = "C";
           
            if ($ingreso_valor =="") {$ingreso_valor = 0;}
            
			# Verificando campos obligatorios #
		    if($ingreso_fechafactura=="" || $ingreso_fechapago=="" || $ingreso_periodo=="" || $ingreso_valor=="" || $ingreso_formapagoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

            $ingreso_datos_reg=[
				[
					"campo_nombre"=>"ingreso_formapagoid",
					"campo_marcador"=>":Formapagoid",
					"campo_valor"=>$ingreso_formapagoid
				],	
                [
					"campo_nombre"=>"ingreso_tipoingresoid",
					"campo_marcador"=>":Tipoingresoid",
					"campo_valor"=>$ingreso_tipoingresoid
				],				
				[
					"campo_nombre"=>"ingreso_empleadoid",
					"campo_marcador"=>":Empleadoid",
					"campo_valor"=>$ingreso_empleadoid
				],				
				[
					"campo_nombre"=>"ingreso_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$ingreso_valor
				],	
				[
					"campo_nombre"=>"ingreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$ingreso_concepto
				],
				[
					"campo_nombre"=>"ingreso_fechafactura",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$ingreso_fechafactura
				],				
				[
					"campo_nombre"=>"ingreso_fechapago",
					"campo_marcador"=>":Fechapago",
					"campo_valor"=>$ingreso_fechapago
				],
				[
					"campo_nombre"=>"ingreso_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$ingreso_periodo
				],				
				[
					"campo_nombre"=>"ingreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$ingreso_estado 
				]
			];	

			# Directorio de imagenes #
			$img_dir="../views/imagenes/ingresos/";
            $codigo=rand(0,100);

			# Comprobar si selecciono la factura #
    		if($_FILES['ingreso_factura']['name']!="" && $_FILES['ingreso_factura']['size']>0){
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
		        if(mime_content_type($_FILES['ingreso_factura']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['ingreso_factura']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['ingreso_factura']['size']/1024)>3000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Nombre de la foto #
		        $factura=str_ireplace(" ","_",$ingreso_empleadoid);
		        $factura=$factura."_".$codigo;

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['ingreso_factura']['tmp_name'])){
		            case 'image/jpeg':
		                $factura="F".$factura.".jpg";
		            break;
		            case 'image/png':
		                $factura="F".$factura.".png";
		            break;
		        }

                $maxWidth = 800;
    			$maxHeight = 600;

				chmod($img_dir,0777);
				$inputFile = ($_FILES['ingreso_factura']['tmp_name']);
       			$outputFile = $img_dir.$factura;

				# Moviendo imagen al directorio #
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}
                
                # Eliminando imagen anterior #
                if(is_file($img_dir.$ingreso['ingreso_factura']) && $ingreso['ingreso_factura']!=$factura){
                    chmod($img_dir.$ingreso['ingreso_factura'], 0777);
                    unlink($img_dir.$ingreso['ingreso_factura']);
                }				
                
                $ingreso_datos_reg[] = [
                    "campo_nombre" => "ingreso_factura",
                    "campo_marcador" => ":Factura",
                    "campo_valor" => $factura
                ];	            
    		}
                   
            # Comprobar si selecciono el comprobante de pago #
    		if($_FILES['ingreso_comprobante']['name']!="" && $_FILES['ingreso_comprobante']['size']>0){
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
		        if(mime_content_type($_FILES['ingreso_comprobante']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['ingreso_comprobante']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['ingreso_comprobante']['size']/1024)>3000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Nombre de la foto #
		        $comprobante=str_ireplace(" ","_",$ingreso_empleadoid);
		        $comprobante=$comprobante."_".$codigo;

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['ingreso_comprobante']['tmp_name'])){
		            case 'image/jpeg':
		                $comprobante="C".$comprobante.".jpg";
		            break;
		            case 'image/png':
		                $comprobante="C".$comprobante.".png";
		            break;
		        }

                $maxWidth = 800;
    			$maxHeight = 600;

				chmod($img_dir,0777);
				$inputFile = ($_FILES['ingreso_comprobante']['tmp_name']);
       			$outputFile = $img_dir.$comprobante;

				# Moviendo imagen al directorio #
				//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}
                # Eliminando imagen anterior #
                if(is_file($img_dir.$ingreso['ingreso_comprobante']) && $ingreso['ingreso_comprobante']!=$comprobante){
                    chmod($img_dir.$ingreso['ingreso_comprobante'], 0777);
                    unlink($img_dir.$ingreso['ingreso_comprobante']);
                }				
                
                $ingreso_datos_reg[] = [
                    "campo_nombre" => "ingreso_comprobante",
                    "campo_marcador" => ":Comprobante",
                    "campo_valor" => $comprobante
                ];	        
    		}
            $condicion=[
				"condicion_campo"=>"ingreso_id",
				"condicion_marcador"=>":ingresoid",
				"condicion_valor"=>$ingresoid
			];

			if($this->actualizarDatos("empleado_ingreso",$ingreso_datos_reg,$condicion)){					
				
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"ingreso actualizado",
					"texto"=>"El ingreso se actualizó correctamente",
					"icono"=>"success"
				];  
            }
            else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ingreso no actualizado",
					"texto"=>"No fue posible actualizar el ingreso, por favor intente nuevamente",
					"icono"=>"success"
				];
			}
			return json_encode($alerta);
        }
    
        public function eliminarIngreso(){			
			$ingresoid=$this->limpiarCadena($_POST['ingreso_id']);

			$ingreso_datos=[
				[
					"campo_nombre"=>"ingreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> 'E'
				]
			];

			$condicion=[
				"condicion_campo"=>"ingreso_id",
				"condicion_marcador"=>":Ingresoid",
				"condicion_valor"=>$ingresoid
			];

			if($this->actualizarDatos("empleado_ingreso", $ingreso_datos, $condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Ingreso actualizado",
					"texto"=>"El ingreso fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar el ingreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}

        public function BuscarIngreso($ingreso_id){		
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY ingreso_id) AS fila_numero, IFNULL(P.PAGOS_PENDIENTES, 0)PAGOS_PENDIENTES , H.* 
				FROM empleado_ingreso H  
				LEFT JOIN (
					SELECT COUNT(1)PAGOS_PENDIENTES, trxh_pagoid 
					FROM empleado_ingreso_trx
					GROUP BY trxh_pagoid
				)P ON P.trxh_pagoid = H.ingreso_id 
				WHERE (H.ingreso_id = :ingreso AND H.ingreso_estado NOT IN ('E')) ORDER BY ingreso_id DESC";		


			$datos = $this->ejecutarConsulta($consulta_datos, [':ingreso' => (int)$ingreso_id]);		
			return $datos;
		}

		/*----------  Módulo egresos  ----------*/
		public function listarTipoEgreso($egreso_tipoid){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'tipo_egreso'
									AND T.tabla_estado = 'A'
									AND C.catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
                if($egreso_tipoid == $rows['catalogo_valor']){	
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{		
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
                }				
			}
			return $option;
		}

		public function registrarEgreso(){

            # Almacenando datos#
			$egreso_formaegresoid 	= $this->limpiarCadena($_POST['egreso_formaegresoid']);
            $egreso_tipoid 			= $this->limpiarCadena($_POST['egreso_tipoid']);
            $egreso_empleadoid 		= $this->limpiarCadena($_POST['egreso_empleadoid']);
            $egreso_valor     		= $this->limpiarCadena($_POST['egreso_valor']);
			$egreso_pendiente     	= $this->limpiarCadena($_POST['egreso_valor']);
            $egreso_concepto 		= $this->limpiarCadena($_POST['egreso_concepto']);
            $egreso_fechaegreso 	= $this->limpiarCadena($_POST['egreso_fechaegreso']);
            $egreso_fecharegistro	= $this->limpiarCadena($_POST['egreso_fecharegistro']);
            $egreso_periodo 		= $this->limpiarCadena($_POST['egreso_periodo']);
			$egreso_descargado     	= 0;
            $egreso_estado          = "P";
           
            if ($egreso_valor =="") {$egreso_valor = 0;}

			# Verificando campos obligatorios #
		    if($egreso_fechaegreso=="" || $egreso_fecharegistro=="" || $egreso_periodo=="" || $egreso_valor=="" || $egreso_formaegresoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }
            $egreso_datos_reg=[
				[
					"campo_nombre"=>"egreso_formaegresoid",
					"campo_marcador"=>":Formaegresoid",
					"campo_valor"=>$egreso_formaegresoid
				],		
                [
					"campo_nombre"=>"egreso_tipoid",
					"campo_marcador"=>":Tipoegresoid",
					"campo_valor"=>$egreso_tipoid
				],			
				[
					"campo_nombre"=>"egreso_empleadoid",
					"campo_marcador"=>":Empleadoid",
					"campo_valor"=>$egreso_empleadoid
				],				
				[
					"campo_nombre"=>"egreso_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$egreso_valor
				],	
				[
					"campo_nombre"=>"egreso_pendiente",
					"campo_marcador"=>":Saldo",
					"campo_valor"=>$egreso_pendiente
				],	
				[
					"campo_nombre"=>"egreso_descargado",
					"campo_marcador"=>":Saldo",
					"campo_valor"=>$egreso_descargado
				],	
				[
					"campo_nombre"=>"egreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$egreso_concepto
				],
				[
					"campo_nombre"=>"egreso_fechaegreso",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$egreso_fechaegreso
				],				
				[
					"campo_nombre"=>"egreso_fecharegistro",
					"campo_marcador"=>":Fechapago",
					"campo_valor"=>$egreso_fecharegistro
				],
				[
					"campo_nombre"=>"egreso_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$egreso_periodo
				],				
				[
					"campo_nombre"=>"egreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$egreso_estado 
				]
			];	

            $registrar_egreso=$this->guardarDatos("empleado_egreso",$egreso_datos_reg);
            if($registrar_egreso->rowCount()>0){
                $alerta=[
                    "tipo"=>"recargar",
                    "titulo"=>"Egreso registrado",
                    "texto"=>"El egreso se registró correctamente",
                    "icono"=>"success"
                ];

            }
            return json_encode($alerta);
        }

		public function listarOptionDescuento($ingreso_formapagoid){
			$option="";

			$consulta_datos="SELECT * FROM general_tabla_catalogo WHERE catalogo_tablaid = 6 AND catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
                if($ingreso_formapagoid == $rows['catalogo_valor']){	
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{		
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
                }				
			}
			return $option;
		}

		public function listarEgresos($empleadoid){            	
			$tabla="";
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY egreso_id) AS fila_numero, IFNULL(P.PAGOS_PENDIENTES, 0)PAGOS_PENDIENTES, 
                                F.catalogo_descripcion as FormaEgreso, T.catalogo_descripcion as TipoEgreso, E.* 
                                FROM empleado_egreso E
                                LEFT JOIN (
                                    SELECT COUNT(1)PAGOS_PENDIENTES, trxegreso_egresoid 
                                    FROM empleado_egreso_trx
                                    GROUP BY trxegreso_egresoid
                                )P ON P.trxegreso_egresoid = E.egreso_id
                                LEFT JOIN general_tabla_catalogo F on F.catalogo_valor = egreso_formaegresoid 
                                LEFT JOIN general_tabla_catalogo T on T.catalogo_valor = egreso_tipoid 
                                WHERE (E.egreso_empleadoid = :empleado AND E.egreso_estado NOT IN ('E')) ORDER BY egreso_id DESC";
                $datos = $this->ejecutarConsulta($consulta_datos, [':empleado' => (int)$empleadoid]);
                $datos = $datos->fetchAll();
                foreach($datos as $rows){

                if ($rows['egreso_estado'] == 'C'){
                    $egreso_estado = 'Cancelado';
                    $class = '';
                }elseif($rows['egreso_estado'] == 'P'){
                    $egreso_estado = '<span class="badge bg-danger"> Pendiente';
                    $class = 'class="text-danger"';
                }elseif($rows['egreso_estado'] == 'J'){
                    $egreso_estado = ' Justificado';
                    $class = 'class="text-primary"';
                }

				if($rows['egreso_pendiente'] > 0 ){
					$btnDescargar = '<a href="'.APP_URL.'empleadoDescargaEgreso/'.$rows['egreso_id'].'/" class="btn float-end btn-info btn-sm" style="margin-right: 5px;"><i class="fas fa-download me-1"></i>Descargar</a>';
				}elseif($rows['egreso_pendiente'] == 0 && $rows['PAGOS_PENDIENTES']>0){
					$btnDescargar = '<a href="'.APP_URL.'empleadoDescargaEgreso/'.$rows['egreso_id'].'/" class="btn float-end btn-dark btn-sm" style="margin-right: 5px;"><i class="fas fa-dollar-sign me-1"></i>Pagos</a>';
				}else{				
					$btnDescargar ="";
				}
				
                if($rows['PAGOS_PENDIENTES']>0){
                    $eliminaregreso="disabled";
                }else{
                    $eliminaregreso="";
                }
				
			$tabla.='
				<tr '.$class.'>
					<td>'.$rows['fila_numero'].'</td>
					<td>'.$rows['egreso_periodo'].'</td>
					<td>'.$rows['egreso_valor'].'</td>
					<td>'.$rows['egreso_pendiente'].'</td>
					<td>'.$rows['TipoEgreso'].'</td>
					<td>'.$rows['FormaEgreso'].'</td>                    
					<td>'.$egreso_estado.'</td>
					<td>
						'.$this->siPuede('eliminar','empleadoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/empleadoAjax.php" method="POST" autocomplete="off" >
							<input type="hidden" name="modulo_egreso" value="eliminar">
							<input type="hidden" name="egreso_id" value="'.$rows['egreso_id'].'">						
							<button type="submit" class="btn float-end btn-danger btn-sm " style="margin-right: 5px;" '.$eliminaregreso.' title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
						</form>').'
						'.$this->siPuede('editar','empleadoList','<a href="'.APP_URL.'empleadoEgresoUpdate/'.$empleadoid.'/'.$rows['egreso_id'].'/" class="btn float-end btn-success btn-sm '.$eliminaregreso.'" style="margin-right: 5px;"  title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
						'.$btnDescargar.'
					</td>
				</tr>';	
			}
			return $tabla;		
        }

		public function BuscarEgreso($egreso_id){		
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY egreso_id) AS fila_numero, IFNULL(P.PAGOS_PENDIENTES, 0)PAGOS_PENDIENTES , E.* 
				FROM empleado_egreso E  
				LEFT JOIN (
					SELECT COUNT(1)PAGOS_PENDIENTES, trxegreso_egresoid  
					FROM empleado_egreso_trx
					GROUP BY trxegreso_egresoid 
				)P ON P.trxegreso_egresoid  = E.egreso_id 
				WHERE (E.egreso_id = :egreso AND E.egreso_estado NOT IN ('E')) ORDER BY egreso_id DESC";		


			$datos = $this->ejecutarConsulta($consulta_datos, [':egreso' => (int)$egreso_id]);		
			return $datos;
		}

		public function actualizarEgreso(){
            $egresoid=$this->limpiarCadena($_POST['egreso_id']);

			# Verificando existencia de equipo #
			$egreso=$this->ejecutarConsulta("SELECT * FROM empleado_egreso WHERE egreso_id = :id", [':id' => (int)$egresoid]);
			if($egreso->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El egreso no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);		    
			}else{
				$egreso             	= $egreso->fetch();
                $egreso_formaegresoid	= $egreso['egreso_formaegresoid'];
                $egreso_tipoid			= $egreso['egreso_tipoid'];
                $egreso_empleadoid 		= $egreso['egreso_empleadoid'];
                $egreso_valor     		= $egreso['egreso_valor'];
				$egreso_pendiente     	= $egreso['egreso_valor'];
                $egreso_concepto 		= $egreso['egreso_concepto'];
                $egreso_fechaegreso		= $egreso['egreso_fechaegreso'];
                $egreso_fecharegistro	= $egreso['egreso_fecharegistro'];
                $egreso_periodo 		= $egreso['egreso_periodo'];
			}	

            # Almacenando datos#
			$egreso_formaegresoid 	= $this->limpiarCadena($_POST['egreso_formaegresoid']);
            $egreso_tipoid 			= $this->limpiarCadena($_POST['egreso_tipoid']);
            $egreso_empleadoid 		= $this->limpiarCadena($_POST['egreso_empleadoid']);
            $egreso_valor     		= $this->limpiarCadena($_POST['egreso_valor']);
			$egreso_pendiente     	= $this->limpiarCadena($_POST['egreso_valor']);
            $egreso_concepto 		= $this->limpiarCadena($_POST['egreso_concepto']);
            $egreso_fechaegreso   	= $this->limpiarCadena($_POST['egreso_fechaegreso']);
            $egreso_fecharegistro	= $this->limpiarCadena($_POST['egreso_fecharegistro']);
            $egreso_periodo 		= $this->limpiarCadena($_POST['egreso_periodo']);
           
            if ($egreso_valor =="") {$egreso_valor = 0;}

			# Verificando campos obligatorios #
		    if($egreso_fechaegreso=="" || $egreso_fecharegistro=="" || $egreso_periodo=="" || $egreso_valor=="" || $egreso_formaegresoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

            $egreso_datos_reg=[
				[
					"campo_nombre"=>"egreso_formaegresoid",
					"campo_marcador"=>":Formaegresoid",
					"campo_valor"=>$egreso_formaegresoid
				],	
                [
					"campo_nombre"=>"egreso_tipoid",
					"campo_marcador"=>":Tipoid",
					"campo_valor"=>$egreso_tipoid
				],				
				[
					"campo_nombre"=>"egreso_empleadoid",
					"campo_marcador"=>":Empleadoid",
					"campo_valor"=>$egreso_empleadoid
				],				
				[
					"campo_nombre"=>"egreso_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$egreso_valor
				],	
				[
					"campo_nombre"=>"egreso_pendiente",
					"campo_marcador"=>":Saldo",
					"campo_valor"=>$egreso_pendiente
				],	
				[
					"campo_nombre"=>"egreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$egreso_concepto
				],
				[
					"campo_nombre"=>"egreso_fechaegreso",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$egreso_fechaegreso
				],				
				[
					"campo_nombre"=>"egreso_fecharegistro",
					"campo_marcador"=>":Fecharegistro",
					"campo_valor"=>$egreso_fecharegistro
				],
				[
					"campo_nombre"=>"egreso_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$egreso_periodo
				]
			];	

            $condicion=[
				"condicion_campo"=>"egreso_id",
				"condicion_marcador"=>":egresoid",
				"condicion_valor"=>$egresoid
			];

			if($this->actualizarDatos("empleado_egreso",$egreso_datos_reg,$condicion)){					
				
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Egreso actualizado",
					"texto"=>"El egreso se actualizó correctamente",
					"icono"=>"success"
				];  
            }
            else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Egreso no actualizado",
					"texto"=>"No fue posible actualizar el egreso, por favor intente nuevamente",
					"icono"=>"success"
				];
			}
			return json_encode($alerta);
        }

		public function eliminarEgreso(){			
			$egresoid=$this->limpiarCadena($_POST['egreso_id']);

			$egreso_datos=[
				[
					"campo_nombre"=>"egreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> 'E'
				]
			];

			$condicion=[
				"condicion_campo"=>"egreso_id",
				"condicion_marcador"=>":Egresoid",
				"condicion_valor"=>$egresoid
			];

			if($this->actualizarDatos("empleado_egreso", $egreso_datos, $condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Egreso actualizado",
					"texto"=>"El egreso fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar el egreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}

		public function BuscarRubroEgreso($egresoid){
		
			$consulta_datos="SELECT  R.catalogo_descripcion RUBRO, E.* 
					FROM empleado_egreso E
						INNER JOIN general_tabla_catalogo R ON R.catalogo_valor = E.egreso_tipoid 				
					WHERE E.egreso_id = :egreso";	

			$datos = $this->ejecutarConsulta($consulta_datos, [':egreso' => (int)$egresoid]);		
			return $datos;
		}

		public function registrarDescargoEgreso(){

            # Almacenando datos#
			$trxegreso_egresoid 		= $this->limpiarCadena($_POST['trxegreso_egresoid']);
			$descargado					= $this->limpiarCadena($_POST['trxegreso_descargado']);
			$total						= $this->limpiarCadena($_POST['trxegreso_total']);
            $trxegreso_valorcalculado	= $this->limpiarCadena($_POST['trxegreso_pendiente']);
            $trxegreso_descargo			= $this->limpiarCadena($_POST['trxegreso_descargo']);
            $trxegreso_fecha     		= $this->limpiarCadena($_POST['trxegreso_fecha']);
            $trxegreso_fecharegistro	= $this->limpiarCadena($_POST['trxegreso_fecharegistro']);
			$trxegreso_formaegresoid	= $this->limpiarCadena($_POST['trxegreso_formaegresoid']);
			$trxegreso_concepto			= $this->limpiarCadena($_POST['trxegreso_concepto']);
            $trxegreso_periodo 			= $this->limpiarCadena($_POST['trxegreso_periodo']);
            $trxegreso_estado          	= "P";
           
            if ($trxegreso_descargo =="") {$trxegreso_descargo = 0;}
			
			if ($descargado == $total){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El egreso ha sido descargado en su totalidad, no existen valores pendientes",
					"icono"=>"error"
				];
				return json_encode($alerta);		 
			}
			// Actualizar saldo del egreso				
			$saldo = $trxegreso_valorcalculado - $trxegreso_descargo;
			$descargado += $trxegreso_descargo;

			if ($saldo == 0){
				$estado_saldo='C';
			}else{
				$estado_saldo = 'P';
			}
			
			# Verificando campos obligatorios #
		    if($trxegreso_fecha=="" || $trxegreso_fecharegistro=="" || $trxegreso_periodo=="" || $trxegreso_descargo=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }
            $trxegreso_datos_reg=[
				[
					"campo_nombre"=>"trxegreso_egresoid",
					"campo_marcador"=>":Egresoid",
					"campo_valor"=>$trxegreso_egresoid
				],		
                [
					"campo_nombre"=>"trxegreso_valorcalculado",
					"campo_marcador"=>":ValorCalculado",
					"campo_valor"=>$trxegreso_valorcalculado
				],			
				[
					"campo_nombre"=>"trxegreso_descargo",
					"campo_marcador"=>":Descargo",
					"campo_valor"=>$trxegreso_descargo
				],				
				[
					"campo_nombre"=>"trxegreso_fecha",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$trxegreso_fecha
				],	
				[
					"campo_nombre"=>"trxegreso_fecharegistro",
					"campo_marcador"=>":Fecharegistro",
					"campo_valor"=>$trxegreso_fecharegistro
				],	
				[
					"campo_nombre"=>"trxegreso_formaegresoid",
					"campo_marcador"=>":FormaEgreso",
					"campo_valor"=>$trxegreso_formaegresoid
				],
				[
					"campo_nombre"=>"trxegreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$trxegreso_concepto
				],
				[
					"campo_nombre"=>"trxegreso_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$trxegreso_periodo
				],				
				[
					"campo_nombre"=>"trxegreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$trxegreso_estado 
				]
			];	

            $registrar_trxegreso=$this->guardarDatos("empleado_egreso_trx",$trxegreso_datos_reg);
            if($registrar_trxegreso->rowCount()>0){
                $alerta=[
                    "tipo"=>"recargar",
                    "titulo"=>"Descargo de egreso registrado",
                    "texto"=>"El descargo del egreso se registró correctamente",
                    "icono"=>"success"
                ];
				// Actualizar saldo y valor
				$this->ejecutarConsulta("UPDATE empleado_egreso SET egreso_descargado = :descargado, egreso_pendiente = :saldo, egreso_estado = :estado WHERE egreso_id = :egreso", [':descargado' => $descargado, ':saldo' => $saldo, ':estado' => $estado_saldo, ':egreso' => (int)$trxegreso_egresoid]);
            }
            return json_encode($alerta);
        }

		public function listarDescargosPendientes($egresoid){			
			$tabla="";
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY PT.trxegreso_id) AS fila_numero, PT.*, P.* 
				FROM empleado_egreso_trx PT
				INNER JOIN empleado_egreso P ON P.egreso_id = PT.trxegreso_egresoid  
				WHERE (PT.trxegreso_egresoid = :egreso AND PT.trxegreso_estado NOT IN ('E')) ORDER BY trxegreso_id DESC";		

			$datos = $this->ejecutarConsulta($consulta_datos, [':egreso' => (int)$egresoid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
			
			if ($rows['trxegreso_estado'] == 'C'){
				$estado = 'Cancelado';
				$class = '';
			}elseif($rows['trxegreso_estado'] == 'P'){
				$estado = '<span class="badge bg-danger"> Pendiente';
				$class = 'class="text-danger"';
			}elseif($rows['trxegreso_estado'] == 'J'){
				$estado = ' Justificado';
				$class = 'class="text-primary"';
			}
				
			$tabla.='
				<tr '.$class.'>
					<td>'.$rows['fila_numero'].'</td>
					<td>'.$rows['trxegreso_fecha'].'</td>
					<td>'.$rows['trxegreso_periodo'].'</td>
					<td>'.$rows['trxegreso_valorcalculado'].'</td>					
					<td>'.$rows['trxegreso_descargo'].'</td>
					<td>
						'.$this->siPuede('eliminar','empleadoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/empleadoAjax.php" method="POST" autocomplete="off" >
							<input type="hidden" name="modulo_egreso" value="eliminardescargo">
							<input type="hidden" name="trxegreso_id" value="'.$rows['trxegreso_id'].'">	
							<input type="hidden" name="trxegreso_egresoid" value="'.$rows['trxegreso_egresoid'].'">	
							<input type="hidden" name="trxegreso_descargo" value="'.$rows['trxegreso_descargo'].'">						
							<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 5px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
						</form>').'
					</td>
				</tr>';	
			}
			return $tabla;			
		}

		public function eliminarDescargoEgreso(){
			# Almacenando datos#			
			$descargo_id		=$this->limpiarCadena($_POST['trxegreso_id']);
			$egreso_id			=$this->limpiarCadena($_POST['trxegreso_egresoid']);
			$trxegreso_descargo =$this->limpiarCadena($_POST['trxegreso_descargo']);

			$descargo_datos=[
				[
					"campo_nombre"=>"trxegreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> 'E'
				]
			];

			$condicion=[
				"condicion_campo"=>"trxegreso_id",
				"condicion_marcador"=>":Egreso_id",
				"condicion_valor"=>$descargo_id
			];

			// Actualizar saldo y valor
			$actualizar= $this->ejecutarConsulta("UPDATE empleado_egreso 
													SET egreso_descargado = egreso_descargado - :descargoResta, 
														egreso_pendiente = egreso_pendiente + :descargoSuma 
													WHERE egreso_id = :egreso", [':descargoResta' => $trxegreso_descargo, ':descargoSuma' => $trxegreso_descargo, ':egreso' => (int)$egreso_id]);

			if($actualizar->rowCount()<=0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No fue posible actualizar los valores",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			if($this->actualizarDatos("empleado_egreso_trx", $descargo_datos, $condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Descargo eliminado",
					"texto"=>"El descargo del egreso fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar el descargo del egreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function listarPeriodicidadDescuento($egreso_formaegresoid){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'periodicidad'
									AND T.tabla_estado = 'A'
									AND C.catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
                if($egreso_formaegresoid == $rows['catalogo_valor']){	
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{		
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
                }				
			}
			return $option;
		}

		public function AnticipoPendiente($empleadoid){
			$tabla="";
			$consulta_datos="SELECT C.catalogo_descripcion tipo_egreso, egreso_fecharegistro fecha, egreso_pendiente pendiente
								FROM empleado_egreso 
								INNER JOIN general_tabla_catalogo C ON C.catalogo_valor = egreso_tipoid
								WHERE egreso_estado = 'P' AND egreso_empleadoid = :empleado";	
			
			$datos = $this->ejecutarConsulta($consulta_datos, [':empleado' => (int)$empleadoid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){		
				$tabla.='
					<tr style="font-size: 14px" class="text-danger">
						<td>'.$rows['tipo_egreso'].'</td>
						<td>'.$rows['fecha'].'</td>
						<td>'.$rows['pendiente'].'</td>
						</td>
					</tr>';						
			}
			return $tabla;
		}

		public function ConsolidadoAnticipo($empleadoid){		
			$consulta_datos="SELECT SUM(egreso_valor) VALOR_ANTICIPO, SUM(egreso_pendiente) ANTICIPO_PENDIENTE
						from empleado_egreso 
						where egreso_estado = 'P'
							AND egreso_empleadoid = :empleado";
				
			$datos = $this->ejecutarConsulta($consulta_datos, [':empleado' => (int)$empleadoid]);				
			return $datos;
		}

		/*==============================================================
		| Marcar entrada y salida (vista empleadoEntrada)
		|
		| QUIÉN MARCA
		|
		| El empleado sale SIEMPRE de la sesión: empleado_actual(), el
		| usuario_empleadoid que guarda el login. ANTES la vista buscaba
		| $_SESSION['identificacion'], que nadie guarda, así que nunca
		| encontraba al empleado; y el servidor registraba la marcación
		| para el «empleadoid» y el «estado_es» que llegaran en campos
		| ocultos: bastaba cambiarlos para marcar a nombre de otro.
		|
		| QUÉ SE MARCA
		|
		| Lo decide el servidor: si la última marcación de HOY es una
		| entrada, toca salida; si no, entrada.
		|==============================================================*/

		/** El empleado ACTIVO vinculado al usuario de la sesión, o null. */
		public function empleadoDeLaSesion(): ?array {
			$id = empleado_actual();
			if ($id <= 0) { return null; }
			$fila = $this->ejecutarConsulta("SELECT E.empleado_id, E.empleado_identificacion, E.empleado_nombre, E.empleado_foto,
						E.empleado_fechaingreso, S.sede_nombre, ES.catalogo_descripcion AS especialidad
					FROM sujeto_empleado E
					LEFT JOIN general_sede S ON S.sede_id = E.empleado_sedeid
					LEFT JOIN general_tabla_catalogo ES ON ES.catalogo_valor = E.empleado_especialidadid
					       AND ES.catalogo_tablaid = (SELECT tabla_id FROM general_tabla WHERE tabla_nombre = 'especialidad_empleado' LIMIT 1)
					WHERE E.empleado_id = :id AND E.empleado_estado = 'A'", [':id' => $id])->fetch();
			return $fila ?: null;
		}

		/** Las marcaciones de hoy del empleado, en orden. «Hoy» lo dice PHP
		    (America/Guayaquil), no CURDATE(): MySQL puede estar en otra zona. */
		public function marcacionesDeHoy(int $empleadoId): array {
			return $this->ejecutarConsulta("SELECT asistencia_hora, asistencia_tipo, asistencia_latitud, asistencia_longitud
					FROM empleado_asistencia
					WHERE asistencia_empleadoid = :empleado AND asistencia_hora >= :desde AND asistencia_hora < :hasta
					ORDER BY asistencia_hora, asistencia_id",
				[':empleado' => $empleadoId, ':desde' => date('Y-m-d 00:00:00'), ':hasta' => date('Y-m-d 00:00:00', strtotime('+1 day'))])->fetchAll();
		}

		public function AsistenciaCoordenadas(){
			$aviso = static fn(string $titulo, string $texto, string $icono = 'error') =>
				json_encode(["tipo" => "simple", "titulo" => $titulo, "texto" => $texto, "icono" => $icono]);

			$empleado = $this->empleadoDeLaSesion();
			if ($empleado === null) {
				return $aviso("Sin ficha de empleado", "Su usuario no está vinculado a un empleado activo. Pida a administración que lo vincule.");
			}
			$empleadoid = (int)$empleado['empleado_id'];

			/* Coordenadas: números dentro del rango, y no el 0,0 que deja
			   un formulario enviado sin ubicación. */
			$latitude  = filter_var($_POST['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
			$longitude = filter_var($_POST['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
			if ($latitude === false || $longitude === false || abs($latitude) > 90 || abs($longitude) > 180
				|| (abs($latitude) < 0.0001 && abs($longitude) < 0.0001)) {
				return $aviso("Falta la ubicación", "No se recibió su ubicación. Permita el acceso a la ubicación y vuelva a intentarlo.");
			}

			/* Entrada o salida según la última marcación de hoy. Un doble
			   toque en el botón marcaba dos veces seguidas: se ignora lo que
			   llegue menos de un minuto después de la anterior. */
			$hoy    = $this->marcacionesDeHoy($empleadoid);
			$ultima = $hoy ? end($hoy) : null;
			if ($ultima && time() - strtotime($ultima['asistencia_hora']) < 60) {
				return $aviso("Ya registrado", "Su " . ($ultima['asistencia_tipo'] === 'E' ? "entrada" : "salida")
					. " quedó registrada a las " . date('H:i', strtotime($ultima['asistencia_hora'])) . ".", 'info');
			}
			$estado_es = ($ultima && $ultima['asistencia_tipo'] === 'E') ? 'S' : 'E';

			$fechahora = date('Y-m-d H:i:s');
			$googleMapsLink = "https://www.google.com/maps?q={$latitude},{$longitude}";

			$empleadoasistencia_reg=[
				["campo_nombre"=>"asistencia_empleadoid", "campo_marcador"=>":Empleadoid", "campo_valor"=>$empleadoid],
				["campo_nombre"=>"asistencia_hora",       "campo_marcador"=>":Hora",       "campo_valor"=>$fechahora],
				["campo_nombre"=>"asistencia_tipo",       "campo_marcador"=>":Tipo",       "campo_valor"=>$estado_es],
				["campo_nombre"=>"asistencia_latitud",    "campo_marcador"=>":Latitud",    "campo_valor"=>$latitude],
				["campo_nombre"=>"asistencia_longitud",   "campo_marcador"=>":Longitud",   "campo_valor"=>$longitude],
				["campo_nombre"=>"asistencia_ubicacion",  "campo_marcador"=>":Ubicacion",  "campo_valor"=>$googleMapsLink],
			];

			$registrar_asistenciaempleado=$this->guardarDatos("empleado_asistencia",$empleadoasistencia_reg);

			if($registrar_asistenciaempleado->rowCount()==1){
				return json_encode([
					"tipo"   => "redireccionar",
					"url"    => APP_URL . 'empleadoEntrada/',
					"titulo" => $estado_es === 'E' ? "Entrada registrada" : "Salida registrada",
					"texto"  => ($estado_es === 'E' ? "Entrada" : "Salida") . " a las " . date('H:i', strtotime($fechahora)) . ".",
					"icono"  => "success"
				]);
			}
			return $aviso("Error", "No se pudo registrar su asistencia, por favor intente nuevamente");
		}
	}
