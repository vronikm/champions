<?php

	namespace app\controllers;
	use app\models\mainModel;

	class balanceController extends mainModel{
        public function registrarIngreso(){							
			
			# Almacenando datos#
			$ingreso_sedeid			= $this->limpiarCadena($_POST['ingreso_sedeid']);
			$ingreso_fecharecepcion = $this->limpiarCadena($_POST['ingreso_fecharecepcion']);
			$ingreso_empresa	    = $this->limpiarCadena($_POST['ingreso_empresa']);
            $ingreso_monto	        = $this->limpiarCadena($_POST['ingreso_monto']);
            $ingreso_formaentrega	= $this->limpiarCadena($_POST['ingreso_formaentrega']);
			$ingreso_concepto		= $this->limpiarCadena($_POST['ingreso_concepto']);
            $ingreso_descripcion	= $this->limpiarCadena($_POST['ingreso_descripcion']);
			$ingreso_estado			= "A";
			$foto					= "";
			$ingreso_monto = str_replace(['$', ',', ' '], '', $ingreso_monto);

			# Verificando campos obligatorios #
		    if($ingreso_fecharecepcion=="" || $ingreso_empresa=="" || $ingreso_monto=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }		

            # Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/ingresos/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['ingreso_imagenpago']['name']!="" && $_FILES['ingreso_imagenpago']['size']>0){

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
				if(mime_content_type($_FILES['ingreso_imagenpago']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['ingreso_imagenpago']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['ingreso_imagenpago']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$ingreso_empresa);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['ingreso_imagenpago']['tmp_name'])){
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
				$inputFile = ($_FILES['ingreso_imagenpago']['tmp_name']);
					$outputFile = $img_dir.$foto;

				# Moviendo imagen al directorio #
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
            }
			$ingreso_datos_reg=[
				[
					"campo_nombre"=>"ingreso_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$ingreso_sedeid
				],
				[
					"campo_nombre"=>"ingreso_fecharecepcion",
					"campo_marcador"=>":Fecharecepcion",
					"campo_valor"=>$ingreso_fecharecepcion
				],				
				[
					"campo_nombre"=>"ingreso_empresa",
					"campo_marcador"=>":Empresa",
					"campo_valor"=>$ingreso_empresa
				],
                [
					"campo_nombre"=>"ingreso_monto",
					"campo_marcador"=>":Monto",
					"campo_valor"=>$ingreso_monto
				],				
				[
					"campo_nombre"=>"ingreso_formaentrega",
					"campo_marcador"=>":FormaEntrega",
					"campo_valor"=>$ingreso_formaentrega
				],				
				[
					"campo_nombre"=>"ingreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$ingreso_concepto
				],				
				[
					"campo_nombre"=>"ingreso_descripcion",
					"campo_marcador"=>":Descripcion",
					"campo_valor"=>$ingreso_descripcion
				],
				[
					"campo_nombre"=>"ingreso_imagenpago",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				],
				[
					"campo_nombre"=>"ingreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$ingreso_estado
				],
				[
					"campo_nombre"=>"ingreso_fechaactualizado",
					"campo_marcador"=>":Actualizado",
					"campo_valor"=>date("Y-m-d H:i:s")
				]
			];		

			$registrar_ingreso=$this->guardarDatos("balance_ingreso",$ingreso_datos_reg);

			if($registrar_ingreso->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Registro exitoso",
					"texto"=>"El ingreso se registró correctamente",
					"icono"=>"success"
				];				
			
			}else{				

				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible registrar el ingreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

        public function BuscarIngreso($ingresoid){		
			$consulta_datos=("SELECT ingreso_sedeid, ingreso_fecharecepcion, ingreso_empresa, ingreso_monto, ingreso_formaentrega,
                                    ingreso_concepto, ingreso_descripcion, ingreso_imagenpago 
							 FROM balance_ingreso
							 WHERE ingreso_id = :ingreso
							 	AND ingreso_estado = 'A'");

			$datos = $this->ejecutarConsulta($consulta_datos, [':ingreso' => (int)$ingresoid]);
			return $datos;
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

        public function listarFormaEntregaIngreso($ingreso_formaentrega){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'forma_entregaingreso'
									AND T.tabla_estado = 'A'
									AND C.catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($ingreso_formaentrega == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';	
				}else{			
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}				
			}
			return $option;
		}

		public function listarTipoIngreso($ingreso_concepto){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'balance_ingreso'
									AND T.tabla_estado = 'A'
									AND C.catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($ingreso_concepto == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';	
				}else{			
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}				
			}
			return $option;
		}

        public function listarIngresos(){
			$tabla="";
			$consulta_datos="SELECT ingreso_id, sede_nombre, ingreso_empresa, ingreso_monto, ingreso_fecharecepcion
							 FROM balance_ingreso, general_sede
							 WHERE ingreso_sedeid = sede_id
							 	AND ingreso_estado = 'A'
							  ORDER BY ingreso_fecharecepcion DESC";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>	
						<td>'.$rows['sede_nombre'].'</td>					
						<td>'.$rows['ingreso_empresa'].'</td>
						<td>'.$rows['ingreso_monto'].'</td>
						<td>'.$rows['ingreso_fecharecepcion'].'</td>
						<td>
							'.$this->siPuede('eliminar','ingresoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/balanceAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_ingreso" value="eliminar">
								<input type="hidden" name="ingreso_id" value="'.$rows['ingreso_id'].'">						
								<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 3px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>').'

							'.$this->siPuede('editar','ingresoList','<a href="'.APP_URL.'ingresoList/'.$rows['ingreso_id'].'/" class="btn float-end btn-success btn-sm" style="margin-right: 3px;" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
						</td>
					</tr>';	
			}
			return $tabla;
		}

        public function actualizarIngreso(){							
			$ingresoid=$this->limpiarCadena($_POST['ingreso_id']);

			# Verificando existencia de torneo #
			$ingreso=$this->ejecutarConsulta("SELECT * FROM balance_ingreso WHERE ingreso_id = :ingreso", [':ingreso' => (int)$ingresoid]);
			if($ingreso->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El ingreso no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);		    
			}else{
				$ingreso=$ingreso->fetch();
			}	
			
			# Almacenando datos#
			$ingreso_sedeid			= $this->limpiarCadena($_POST['ingreso_sedeid']);
			$ingreso_fecharecepcion = $this->limpiarCadena($_POST['ingreso_fecharecepcion']);
			$ingreso_empresa	    = $this->limpiarCadena($_POST['ingreso_empresa']);
            $ingreso_monto	        = $this->limpiarCadena($_POST['ingreso_monto']);
            $ingreso_formaentrega	= $this->limpiarCadena($_POST['ingreso_formaentrega']);
			$ingreso_concepto		= $this->limpiarCadena($_POST['ingreso_concepto']);
            $ingreso_descripcion	= $this->limpiarCadena($_POST['ingreso_descripcion']);

			$ingreso_monto = str_replace(['$', ',', ' '], '', $ingreso_monto);

			# Verificando campos obligatorios #
		    if($ingreso_fecharecepcion=="" || $ingreso_empresa=="" || $ingreso_monto=="" ){
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
					"campo_nombre"=>"ingreso_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$ingreso_sedeid
				],
				[
					"campo_nombre"=>"ingreso_fecharecepcion",
					"campo_marcador"=>":Fechainicio",
					"campo_valor"=>$ingreso_fecharecepcion
				],				
				[
					"campo_nombre"=>"ingreso_empresa",
					"campo_marcador"=>":Empresa",
					"campo_valor"=>$ingreso_empresa
				],
                [
					"campo_nombre"=>"ingreso_monto",
					"campo_marcador"=>":Monto",
					"campo_valor"=>$ingreso_monto
				],				
				[
					"campo_nombre"=>"ingreso_formaentrega",
					"campo_marcador"=>":FormaEntrega",
					"campo_valor"=>$ingreso_formaentrega
				],				
				[
					"campo_nombre"=>"ingreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$ingreso_concepto
				],				
				[
					"campo_nombre"=>"ingreso_descripcion",
					"campo_marcador"=>":Descripcion",
					"campo_valor"=>$ingreso_descripcion
				]
			];		

			# Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/ingresos/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['ingreso_imagenpago']['name']!="" && $_FILES['ingreso_imagenpago']['size']>0){

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
				if(mime_content_type($_FILES['ingreso_imagenpago']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['ingreso_imagenpago']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['ingreso_imagenpago']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$ingreso_empresa);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['ingreso_imagenpago']['tmp_name'])){
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
				$inputFile = ($_FILES['ingreso_imagenpago']['tmp_name']);
					$outputFile = $img_dir.$foto;

				# Moviendo imagen al directorio #
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
				if(is_file($img_dir.$ingreso['ingreso_imagenpago']) && $ingreso['ingreso_imagenpago']!=$foto){
					chmod($img_dir.$ingreso['ingreso_imagenpago'], 0777);
					unlink($img_dir.$ingreso['ingreso_imagenpago']);
				}	

				$ingreso_datos_reg[] = [
					"campo_nombre"=>"ingreso_imagenpago",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				];
			}

			$condicion=[
				"condicion_campo"=>"ingreso_id",
				"condicion_marcador"=>":Ingresoid",
				"condicion_valor"=>$ingresoid
			];

			if($this->actualizarDatos("balance_ingreso",$ingreso_datos_reg,$condicion)){					
				
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'ingresoList/',
					"titulo"=>"Ingreso actualizado",
					"texto"=>"El ingreso ".$ingreso_empresa." se actualizó correctamente",
					"icono"=>"success"
				];
			}else{				
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible actualizar el ingreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}
        
		public function eliminarIngreso(){

			$ingresoid=$this->limpiarCadena($_POST['ingreso_id']);

			# Verificando usuario #
		    $ingreso=$this->ejecutarConsulta("SELECT * FROM balance_ingreso WHERE ingreso_id = :ingreso", [':ingreso' => (int)$ingresoid]);
			if($ingreso->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El ingreso no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}else{
		    	$ingreso=$ingreso->fetch();
		    }
			if($ingreso['ingreso_estado']=='A'){
				$estadoA = 'E';
			}else{
				$estadoA = 'A';
			}
            $ingreso_datos_up=[
				[
					"campo_nombre"=>"ingreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"ingreso_id",
				"condicion_marcador"=>":Ingresoid",
				"condicion_valor"=>$ingresoid
			];

			if($this->actualizarDatos("balance_ingreso",$ingreso_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Ingreso eliminado correctamente",
					"texto"=>"El ingreso fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No fue posible eliminar el ingreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function BuscarEgreso($egresoid){		
			$consulta_datos=("SELECT egreso_sedeid, egreso_fechapago, egreso_empresa, egreso_monto, egreso_formaentrega,
                                     egreso_concepto, egreso_descripcion, egreso_imagenpago 
							 FROM balance_egreso
							 WHERE egreso_id = :egreso");	

			$datos = $this->ejecutarConsulta($consulta_datos, [':egreso' => (int)$egresoid]);		
			return $datos;
		}

		public function listarTipoEgreso($egreso_concepto){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'balance_egreso'
									AND T.tabla_estado = 'A'
									AND C.catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($egreso_concepto == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';	
				}else{			
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}				
			}
			return $option;
		}

		public function listarEgresos(){
			$tabla="";
			$consulta_datos="SELECT egreso_id, sede_nombre, egreso_empresa, egreso_monto, egreso_fechapago
							 FROM balance_egreso, general_sede
							 WHERE egreso_sedeid = sede_id
							 	AND egreso_estado = 'A'
							 ORDER BY egreso_fechapago DESC";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['sede_nombre'].'</td>						
						<td>'.$rows['egreso_empresa'].'</td>
						<td>'.$rows['egreso_monto'].'</td>
						<td>'.$rows['egreso_fechapago'].'</td>
						<td>
							'.$this->siPuede('eliminar','egresoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/balanceAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_egreso" value="eliminar">
								<input type="hidden" name="egreso_id" value="'.$rows['egreso_id'].'">						
								<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 3px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>').'

							'.$this->siPuede('editar','egresoList','<a href="'.APP_URL.'egresoList/'.$rows['egreso_id'].'/" class="btn float-end btn-success btn-sm" style="margin-right: 3px;" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
						</td>
					</tr>';	
			}
			return $tabla;
		}

		public function registrarEgreso(){							
			
			# Almacenando datos#
			$egreso_sedeid			= $this->limpiarCadena($_POST['egreso_sedeid']);
			$egreso_fechapago 		= $this->limpiarCadena($_POST['egreso_fechapago']);
			$egreso_empresa	    	= $this->limpiarCadena($_POST['egreso_empresa']);
            $egreso_monto	        = $this->limpiarCadena($_POST['egreso_monto']);
            $egreso_formaentrega	= $this->limpiarCadena($_POST['egreso_formaentrega']);
			$egreso_concepto		= $this->limpiarCadena($_POST['egreso_concepto']);
            $egreso_descripcion		= $this->limpiarCadena($_POST['egreso_descripcion']);
			$egreso_estado			= "A";
			$foto					="";
			
			$egreso_monto = str_replace(['$', ',', ' '], '', $egreso_monto);

			# Verificando campos obligatorios #
		    if($egreso_fechapago=="" || $egreso_empresa=="" || $egreso_monto=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }		

            # Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/egresos/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['egreso_imagenpago']['name']!="" && $_FILES['egreso_imagenpago']['size']>0){

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
				if(mime_content_type($_FILES['egreso_imagenpago']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['egreso_imagenpago']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['egreso_imagenpago']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$egreso_empresa);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['egreso_imagenpago']['tmp_name'])){
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
				$inputFile = ($_FILES['egreso_imagenpago']['tmp_name']);
					$outputFile = $img_dir.$foto;

				# Moviendo imagen al directorio #
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
            }
			$egreso_datos_reg=[
				[
					"campo_nombre"=>"egreso_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$egreso_sedeid
				],
				[
					"campo_nombre"=>"egreso_fechapago",
					"campo_marcador"=>":Fechainicio",
					"campo_valor"=>$egreso_fechapago
				],		
				[
					"campo_nombre"=>"egreso_empresa",
					"campo_marcador"=>":Empresa",
					"campo_valor"=>$egreso_empresa
				],
                [
					"campo_nombre"=>"egreso_monto",
					"campo_marcador"=>":Monto",
					"campo_valor"=>$egreso_monto
				],				
				[
					"campo_nombre"=>"egreso_formaentrega",
					"campo_marcador"=>":FormaEntrega",
					"campo_valor"=>$egreso_formaentrega
				],				
				[
					"campo_nombre"=>"egreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$egreso_concepto
				],				
				[
					"campo_nombre"=>"egreso_descripcion",
					"campo_marcador"=>":Descripcion",
					"campo_valor"=>$egreso_descripcion
				],
				[
					"campo_nombre"=>"egreso_imagenpago",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				],
				[
					"campo_nombre"=>"egreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$egreso_estado
				],
				[
					"campo_nombre"=>"egreso_fechaactualizado",
					"campo_marcador"=>":Actualizado",
					"campo_valor"=>date("Y-m-d H:i:s")
				]
			];		

			$registrar_egreso=$this->guardarDatos("balance_egreso",$egreso_datos_reg);

			if($registrar_egreso->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Registro exitoso",
					"texto"=>"El egreso se registró correctamente",
					"icono"=>"success"
				];				
			
			}else{				

				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible registrar el egreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function actualizarEgreso(){							
			$egresoid=$this->limpiarCadena($_POST['egreso_id']);

			# Verificando existencia de torneo #
			$egreso=$this->ejecutarConsulta("SELECT * FROM balance_egreso WHERE egreso_id = :egreso", [':egreso' => (int)$egresoid]);
			if($egreso->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El egreso no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);		    
			}else{
				$egreso=$egreso->fetch();
			}	
			
			# Almacenando datos#
			$egreso_sedeid			= $this->limpiarCadena($_POST['egreso_sedeid']);
			$egreso_fechapago 		= $this->limpiarCadena($_POST['egreso_fechapago']);
			$egreso_empresa	    	= $this->limpiarCadena($_POST['egreso_empresa']);
            $egreso_monto	        = $this->limpiarCadena($_POST['egreso_monto']);
            $egreso_formaentrega	= $this->limpiarCadena($_POST['egreso_formaentrega']);
			$egreso_concepto		= $this->limpiarCadena($_POST['egreso_concepto']);
            $egreso_descripcion		= $this->limpiarCadena($_POST['egreso_descripcion']);

			$egreso_monto = str_replace(['$', ',', ' '], '', $egreso_monto);

			# Verificando campos obligatorios #
		    if($egreso_fechapago=="" || $egreso_empresa=="" || $egreso_monto=="" ){
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
					"campo_nombre"=>"egreso_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$egreso_sedeid
				],
				[
					"campo_nombre"=>"egreso_fechapago",
					"campo_marcador"=>":Fechapago",
					"campo_valor"=>$egreso_fechapago
				],							
				[
					"campo_nombre"=>"egreso_empresa",
					"campo_marcador"=>":Empresa",
					"campo_valor"=>$egreso_empresa
				],
                [
					"campo_nombre"=>"egreso_monto",
					"campo_marcador"=>":Monto",
					"campo_valor"=>$egreso_monto
				],				
				[
					"campo_nombre"=>"egreso_formaentrega",
					"campo_marcador"=>":FormaEntrega",
					"campo_valor"=>$egreso_formaentrega
				],				
				[
					"campo_nombre"=>"egreso_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$egreso_concepto
				],				
				[
					"campo_nombre"=>"egreso_descripcion",
					"campo_marcador"=>":Descripcion",
					"campo_valor"=>$egreso_descripcion
				]
			];		

			# Directorio de imagenes #
			$img_dir="../views/imagenes/fotos/egresos/";
			$codigo=rand(0,100);

			# Comprobar si se selecciono una imagen #
			if($_FILES['egreso_imagenpago']['name']!="" && $_FILES['egreso_imagenpago']['size']>0){

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
				if(mime_content_type($_FILES['egreso_imagenpago']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['egreso_imagenpago']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['egreso_imagenpago']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Error",
						"texto"=>"Imagen seleccionada supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$foto=str_ireplace(" ","_",$egreso_empresa);
				$foto=$foto."_".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['egreso_imagenpago']['tmp_name'])){
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
				$inputFile = ($_FILES['egreso_imagenpago']['tmp_name']);
					$outputFile = $img_dir.$foto;

				# Moviendo imagen al directorio #
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
				if(is_file($img_dir.$egreso['egreso_imagenpago']) && $egreso['egreso_imagenpago']!=$foto){
					chmod($img_dir.$egreso['egreso_imagenpago'], 0777);
					unlink($img_dir.$egreso['egreso_imagenpago']);
				}	

				$egreso_datos_reg[] = [
					"campo_nombre"=>"egreso_imagenpago",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				];
			}

			$condicion=[
				"condicion_campo"=>"egreso_id",
				"condicion_marcador"=>":Egresoid",
				"condicion_valor"=>$egresoid
			];

			if($this->actualizarDatos("balance_egreso",$egreso_datos_reg,$condicion)){					
				
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>APP_URL.'egresoList/',
					"titulo"=>"Egreso actualizado",
					"texto"=>"El egreso ".$egreso_empresa." se actualizó correctamente",
					"icono"=>"success"
				];
			}else{				
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible actualizar el egreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function eliminarEgreso(){

			$egresoid=$this->limpiarCadena($_POST['egreso_id']);

			# Verificando usuario #
		    $egreso=$this->ejecutarConsulta("SELECT * FROM balance_egreso WHERE egreso_id = :egreso", [':egreso' => (int)$egresoid]);
			if($egreso->rowCount()<=0){	
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El egreso no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}else{
		    	$egreso=$egreso->fetch();
		    }
			if($egreso['egreso_estado']=='A'){
				$estadoA = 'E';
			}else{
				$estadoA = 'A';
			}
            $egreso_datos_up=[
				[
					"campo_nombre"=>"egreso_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"egreso_id",
				"condicion_marcador"=>":Egresoid",
				"condicion_valor"=>$egresoid
			];

			if($this->actualizarDatos("balance_egreso",$egreso_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Egreso eliminado correctamente",
					"texto"=>"El egreso fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No fue posible eliminar el egreso, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}
    }