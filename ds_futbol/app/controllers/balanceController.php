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
				/* De vuelta a la lista en el mes del ingreso, para verlo. */
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>$this->urlListaIngresos($ingreso_fecharecepcion),
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

			/* Ingresos y egresos: las activas y la que ya tiene el registro. */
			$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede WHERE " . sql_sede_activa('sede_id', (int)$sedeid);	
					
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

		/*
		| INGRESOS CON CRITERIOS (vista ingresoList, 2026-11-25)
		|
		| Igual que los egresos: mes ('' = todos), sede, concepto, forma de
		| recepción y texto (empresa o descripción). Ver egresosFiltrados().
		*/

		/** Meses con ingresos activos, del más reciente: ['2026-11' => 2, …]. */
		public function ingresosMeses(): array{
			$meses = [];
			foreach ($this->ejecutarConsulta("SELECT DATE_FORMAT(ingreso_fecharecepcion, '%Y-%m') AS mes, COUNT(*) AS n
					FROM balance_ingreso WHERE ingreso_estado = 'A'
					GROUP BY mes ORDER BY mes DESC")->fetchAll() as $f) {
				$meses[$f['mes']] = (int)$f['n'];
			}
			return $meses;
		}

		/** Sedes para el filtro: sólo las activas (2026-11-27). */
		public function sedesIngreso(): array{
			$lista = [];
			foreach ($this->ejecutarConsulta("SELECT sede_id, sede_nombre FROM general_sede
					WHERE " . sql_sede_activa('sede_id', 0) . "
					ORDER BY sede_nombre")->fetchAll() as $f) {
				$lista[(int)$f['sede_id']] = $f['sede_nombre'];
			}
			return $lista;
		}

		/** Ingresos activos según los criterios, del más reciente al más antiguo. */
		public function ingresosFiltrados(array $f): array{
			$sql = "SELECT I.ingreso_id, I.ingreso_sedeid, S.sede_nombre, I.ingreso_fecharecepcion, I.ingreso_empresa, I.ingreso_monto,
					       I.ingreso_formaentrega, I.ingreso_concepto, I.ingreso_descripcion, I.ingreso_imagenpago
					  FROM balance_ingreso I
					  INNER JOIN general_sede S ON S.sede_id = I.ingreso_sedeid
					 WHERE I.ingreso_estado = 'A'";
			$p = [];
			if (preg_match('/^\d{4}-\d{2}$/', (string)($f['mes'] ?? ''))) {
				$sql .= " AND I.ingreso_fecharecepcion BETWEEN :ini AND LAST_DAY(:ini2)";
				$p[':ini'] = $f['mes'] . '-01'; $p[':ini2'] = $f['mes'] . '-01';
			}
			if ((int)($f['sede'] ?? 0) > 0) { $sql .= " AND I.ingreso_sedeid = :sede"; $p[':sede'] = (int)$f['sede']; }
			if ((string)($f['concepto'] ?? '') !== '') { $sql .= " AND I.ingreso_concepto = :concepto"; $p[':concepto'] = (string)$f['concepto']; }
			if ((string)($f['forma'] ?? '') !== '') { $sql .= " AND I.ingreso_formaentrega = :forma"; $p[':forma'] = (string)$f['forma']; }
			$q = trim((string)($f['q'] ?? ''));
			if ($q !== '') {
				$sql .= " AND (I.ingreso_empresa LIKE :q1 OR I.ingreso_descripcion LIKE :q2)";
				$p[':q1'] = '%' . $q . '%'; $p[':q2'] = '%' . $q . '%';
			}
			$sql .= " ORDER BY I.ingreso_fecharecepcion DESC, I.ingreso_id DESC";
			return $this->ejecutarConsulta($sql, $p)->fetchAll();
		}

		/** El mes de un ingreso ('YYYY-MM'): el enlace antiguo /ingresoList/{id}/ lo abre en su mes. */
		public function mesDeIngreso(int $id): string{
			$fecha = (string)$this->ejecutarConsulta("SELECT ingreso_fecharecepcion FROM balance_ingreso WHERE ingreso_id = :i AND ingreso_estado = 'A'", [':i' => $id])->fetchColumn();
			return $fecha !== '' ? substr($fecha, 0, 7) : '';
		}

		/** A dónde vuelve la lista tras guardar: el mes del ingreso. */
		private function urlListaIngresos(string $fecha): string{
			return APP_URL . 'ingresoList/' . (preg_match('/^\d{4}-\d{2}/', $fecha) ? '?mes=' . substr($fecha, 0, 7) : '');
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
					"url"=>$this->urlListaIngresos($ingreso_fecharecepcion),
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
                                     egreso_concepto, egreso_rubroid, egreso_lugarid, egreso_descripcion, egreso_imagenpago
							 FROM balance_egreso
							 WHERE egreso_id = :egreso");

			$datos = $this->ejecutarConsulta($consulta_datos, [':egreso' => (int)$egresoid]);
			return $datos;
		}

		/*
		| BLOQUE DEL REPORTE (migración 094)
		|
		| A qué bloque de Insights › Ingresos del mes pertenece el egreso: el
		| rubro de los pagos de alumnos que lo origina (la confección de
		| polines va con «Kit entrenamiento», el pago de los profesores con las
		| pensiones de su sede). Vacío = «Egresos generales».
		|
		| Las pensiones pueden afinarse por lugar de entrenamiento cuando la
		| sede tiene más de uno (el pago de la cancha de un lugar va con las
		| pensiones de ese lugar).
		*/
		private const RUBROS_BLOQUE = ['RPC', 'RIN', 'RKE', 'RNU', 'ROT', 'RPE'];

		public function listarBloqueEgreso($rubro){
			$option = '<option value="">Sin asignar (egresos generales)</option>';
			$nombres = [];
			foreach ($this->ejecutarConsulta("SELECT C.catalogo_valor, C.catalogo_descripcion
					FROM general_tabla_catalogo C
					INNER JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid
					WHERE T.tabla_nombre = 'rubros'")->fetchAll() as $f) {
				$nombres[$f['catalogo_valor']] = $f['catalogo_descripcion'];
			}
			foreach (self::RUBROS_BLOQUE as $r) {
				$texto = $r === 'RPE' ? 'Pensiones' : ($nombres[$r] ?? $r);
				$option .= '<option value="' . $r . '"' . ((string)$rubro === $r ? ' selected' : '') . '>'
				         . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</option>';
			}
			return $option;
		}

		/* Lugares de entrenamiento con su sede: la vista muestra sólo los de
		   la sede elegida. Los activos y, si el egreso ya tiene uno, ése. */
		public function listarLugarEgreso($lugarid){
			$option = '<option value="" data-sede="">Toda la sede</option>';
			$filas = $this->ejecutarConsulta("SELECT lugar_id, lugar_sedeid, lugar_nombre
					FROM asistencia_lugar
					WHERE lugar_estado = 'A' OR lugar_id = :lugar
					ORDER BY lugar_nombre", [':lugar' => (int)$lugarid])->fetchAll();
			foreach ($filas as $f) {
				$option .= '<option value="' . (int)$f['lugar_id'] . '" data-sede="' . (int)$f['lugar_sedeid'] . '"'
				         . ((int)$lugarid === (int)$f['lugar_id'] ? ' selected' : '') . '>'
				         . htmlspecialchars($f['lugar_nombre'], ENT_QUOTES, 'UTF-8') . '</option>';
			}
			return $option;
		}

		/**
		 * Bloque y lugar validados: [rubro|null, lugar|null, error|null].
		 * El lugar sólo vale con pensiones y si es de la sede del egreso.
		 */
		private function bloqueEgresoDePost($sedeid): array{
			$rubro = strtoupper(trim((string)($_POST['egreso_rubroid'] ?? '')));
			$lugar = (int)($_POST['egreso_lugarid'] ?? 0);
			if ($rubro === '') { return [null, null, null]; }
			if (!in_array($rubro, self::RUBROS_BLOQUE, true)) {
				return [null, null, 'El bloque del reporte no es válido'];
			}
			if ($rubro !== 'RPE' || $lugar <= 0) { return [$rubro, null, null]; }
			$deLaSede = $this->ejecutarConsulta("SELECT COUNT(*) FROM asistencia_lugar WHERE lugar_id = :l AND lugar_sedeid = :s",
				[':l' => $lugar, ':s' => (int)$sedeid])->fetchColumn();
			if ((int)$deLaSede !== 1) {
				return [null, null, 'El lugar de entrenamiento no pertenece a la sede del egreso'];
			}
			return [$rubro, $lugar, null];
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
			$consulta_datos="SELECT E.egreso_id, S.sede_nombre, E.egreso_empresa, E.egreso_monto, E.egreso_fechapago,
									E.egreso_rubroid, R.catalogo_descripcion AS rubro, L.lugar_nombre
							 FROM balance_egreso E
							 INNER JOIN general_sede S ON S.sede_id = E.egreso_sedeid
							 LEFT JOIN general_tabla_catalogo R ON R.catalogo_valor = E.egreso_rubroid
							 LEFT JOIN asistencia_lugar L ON L.lugar_id = E.egreso_lugarid
							 WHERE E.egreso_estado = 'A'
							 ORDER BY E.egreso_fechapago DESC";

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				/* El bloque del reporte, o el aviso de que falta. */
				if ($rows['egreso_rubroid'] === null || $rows['egreso_rubroid'] === '') {
					$bloque = '<span class="badge text-bg-warning" title="Sale en «Egresos generales» del reporte Ingresos del mes">Sin bloque</span>';
				} else {
					$bloque = htmlspecialchars($rows['egreso_rubroid'] === 'RPE' ? 'Pensiones' : (string)$rows['rubro'], ENT_QUOTES, 'UTF-8')
					        . ($rows['lugar_nombre'] ? '<br><small class="text-body-secondary">' . htmlspecialchars($rows['lugar_nombre'], ENT_QUOTES, 'UTF-8') . '</small>' : '');
				}
				$tabla.='
					<tr>
						<td>'.$rows['sede_nombre'].'</td>
						<td>'.$rows['egreso_empresa'].'</td>
						<td>'.$bloque.'</td>
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

		/*
		| EGRESOS POR MES (vista egresoList, 2026-11-25)
		|
		| La lista se ve por mes, con «Criterios de búsqueda»: mes, sede,
		| bloque del reporte, concepto y texto (empresa o detalle). Los
		| catálogos se unen por su tabla: un mismo código podría existir en
		| otra (el JOIN de listarEgresos sólo miraba el valor).
		*/

		/** Meses con egresos activos, del más reciente: ['2026-11' => 5, …]. */
		public function egresosMeses(): array{
			$meses = [];
			foreach ($this->ejecutarConsulta("SELECT DATE_FORMAT(egreso_fechapago, '%Y-%m') AS mes, COUNT(*) AS n
					FROM balance_egreso WHERE egreso_estado = 'A'
					GROUP BY mes ORDER BY mes DESC")->fetchAll() as $f) {
				$meses[$f['mes']] = (int)$f['n'];
			}
			return $meses;
		}

		/** Valor => descripción de un catálogo (general_tabla_catalogo). */
		public function catalogoEgreso(string $tabla): array{
			$lista = [];
			foreach ($this->ejecutarConsulta("SELECT C.catalogo_valor, C.catalogo_descripcion
					FROM general_tabla_catalogo C
					INNER JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid
					WHERE T.tabla_nombre = :t ORDER BY C.catalogo_descripcion", [':t' => $tabla])->fetchAll() as $f) {
				$lista[$f['catalogo_valor']] = $f['catalogo_descripcion'];
			}
			return $lista;
		}

		/** Bloques del reporte, en su orden: ['RPC' => 'Campeonatos', …, 'RPE' => 'Pensiones']. */
		public function bloquesEgreso(): array{
			$nombres = $this->catalogoEgreso('rubros');
			$lista = [];
			foreach (self::RUBROS_BLOQUE as $r) { $lista[$r] = $r === 'RPE' ? 'Pensiones' : ($nombres[$r] ?? $r); }
			return $lista;
		}

		/** Sedes para el filtro: sólo las activas (2026-11-27). */
		public function sedesEgreso(): array{
			$lista = [];
			foreach ($this->ejecutarConsulta("SELECT sede_id, sede_nombre FROM general_sede
					WHERE " . sql_sede_activa('sede_id', 0) . "
					ORDER BY sede_nombre")->fetchAll() as $f) {
				$lista[(int)$f['sede_id']] = $f['sede_nombre'];
			}
			return $lista;
		}

		/**
		 * Egresos activos según los criterios:
		 *   mes 'YYYY-MM' ('' = todos), sede (0 = todas), bloque ('' todos,
		 *   'SIN' sin bloque, o un rubro), concepto ('' todos), q (texto).
		 * Del más reciente al más antiguo.
		 */
		public function egresosFiltrados(array $f): array{
			$sql = "SELECT E.egreso_id, E.egreso_sedeid, S.sede_nombre, E.egreso_fechapago, E.egreso_empresa, E.egreso_monto,
					       E.egreso_formaentrega, E.egreso_concepto, E.egreso_rubroid, E.egreso_lugarid, L.lugar_nombre,
					       E.egreso_descripcion, E.egreso_imagenpago
					  FROM balance_egreso E
					  INNER JOIN general_sede S ON S.sede_id = E.egreso_sedeid
					  LEFT JOIN asistencia_lugar L ON L.lugar_id = E.egreso_lugarid
					 WHERE E.egreso_estado = 'A'";
			$p = [];
			if (preg_match('/^\d{4}-\d{2}$/', (string)($f['mes'] ?? ''))) {
				$sql .= " AND E.egreso_fechapago BETWEEN :ini AND LAST_DAY(:ini2)";
				$p[':ini'] = $f['mes'] . '-01'; $p[':ini2'] = $f['mes'] . '-01';
			}
			if ((int)($f['sede'] ?? 0) > 0) { $sql .= " AND E.egreso_sedeid = :sede"; $p[':sede'] = (int)$f['sede']; }
			$bloque = (string)($f['bloque'] ?? '');
			if ($bloque === 'SIN') {
				$sql .= " AND (E.egreso_rubroid IS NULL OR E.egreso_rubroid = '')";
			} elseif (in_array($bloque, self::RUBROS_BLOQUE, true)) {
				$sql .= " AND E.egreso_rubroid = :bloque"; $p[':bloque'] = $bloque;
			}
			if ((string)($f['concepto'] ?? '') !== '') { $sql .= " AND E.egreso_concepto = :concepto"; $p[':concepto'] = (string)$f['concepto']; }
			$q = trim((string)($f['q'] ?? ''));
			if ($q !== '') {
				$sql .= " AND (E.egreso_empresa LIKE :q1 OR E.egreso_descripcion LIKE :q2)";
				$p[':q1'] = '%' . $q . '%'; $p[':q2'] = '%' . $q . '%';
			}
			$sql .= " ORDER BY E.egreso_fechapago DESC, E.egreso_id DESC";
			return $this->ejecutarConsulta($sql, $p)->fetchAll();
		}

		/** El mes de un egreso ('YYYY-MM'), para volver a él tras guardarlo. */
		public function mesDeEgreso(int $id): string{
			$fecha = (string)$this->ejecutarConsulta("SELECT egreso_fechapago FROM balance_egreso WHERE egreso_id = :e", [':e' => $id])->fetchColumn();
			return $fecha !== '' ? substr($fecha, 0, 7) : '';
		}

		/** A dónde vuelve la lista tras guardar: el mes del egreso. */
		private function urlListaEgresos(string $fecha): string{
			return APP_URL . 'egresoList/' . (preg_match('/^\d{4}-\d{2}/', $fecha) ? '?mes=' . substr($fecha, 0, 7) : '');
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

			# Bloque del reporte (migración 094) #
			[$egreso_rubroid, $egreso_lugarid, $errorBloque] = $this->bloqueEgresoDePost($egreso_sedeid);
			if ($errorBloque !== null) {
				return json_encode(["tipo"=>"simple", "titulo"=>"Error", "texto"=>$errorBloque, "icono"=>"error"]);
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
					"campo_nombre"=>"egreso_rubroid",
					"campo_marcador"=>":Rubroid",
					"campo_valor"=>$egreso_rubroid
				],
				[
					"campo_nombre"=>"egreso_lugarid",
					"campo_marcador"=>":Lugarid",
					"campo_valor"=>$egreso_lugarid
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
				/* De vuelta a la lista en el mes del egreso, para verlo. */
				$alerta=[
					"tipo"=>"redireccionar",
					"url"=>$this->urlListaEgresos($egreso_fechapago),
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

			# Bloque del reporte (migración 094) #
			[$egreso_rubroid, $egreso_lugarid, $errorBloque] = $this->bloqueEgresoDePost($egreso_sedeid);
			if ($errorBloque !== null) {
				return json_encode(["tipo"=>"simple", "titulo"=>"Error", "texto"=>$errorBloque, "icono"=>"error"]);
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
					"campo_nombre"=>"egreso_rubroid",
					"campo_marcador"=>":Rubroid",
					"campo_valor"=>$egreso_rubroid
				],
				[
					"campo_nombre"=>"egreso_lugarid",
					"campo_marcador"=>":Lugarid",
					"campo_valor"=>$egreso_lugarid
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
					"url"=>$this->urlListaEgresos($egreso_fechapago),
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