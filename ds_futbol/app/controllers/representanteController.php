<?php
	namespace app\controllers;
	use app\models\mainModel;

	class representanteController extends mainModel{

		/*----------  Matriz de representantes con opciones Ver, Actualizar, Eliminar  ----------*/

		/*==================================================================
		  Listado servido POR EL SERVIDOR (DataTables serverSide)

		  POR QUE

		  listarRepresentantes() devolvia las 265 filas de una vez y la
		  pantalla pesaba 410 KB, de los cuales 377 eran el <tbody>: seis
		  enlaces con URL absoluta por fila. DataTables paginaba despues, en
		  el navegador, asi que el peso se pagaba entero para enseñar
		  veinticinco.

		  Con 265 representantes son 410 KB; con el doble de alumnos serian
		  800, y eso ya no se abre con datos moviles.

		  LO QUE NO SE PIERDE

		  La busqueda y el orden siguen alcanzando a TODAS las filas, porque
		  los resuelve la base y no el navegador. Es la diferencia entre
		  paginar en servidor bien y hacerlo a medias: un LIMIT sin busqueda
		  en servidor deja al usuario buscando dentro de una pagina.

		  SEGURIDAD DE ESTA CONSULTA

		  - El texto buscado va como parametro, nunca concatenado.
		  - La columna de orden se elige de una LISTA BLANCA. Interpolar el
		    nombre que llega por la peticion seria inyeccion aunque el
		    valor viniera de un <select>: la peticion la arma quien quiera.
		  - LIMIT y OFFSET se convierten a entero, que es la unica forma en
		    MySQL —no admiten parametro preparado.
		  - Los botones siguen pasando por siPuede(): quien no puede editar
		    no recibe el boton, igual que antes.
		  ==================================================================*/

		/** Columnas por las que se puede ordenar, en el orden de la tabla. */
		private function columnasRepresentante(): array
		{
			return [
				0 => 'repre_identificacion',
				1 => 'repre_primernombre',
				2 => 'repre_apellidopaterno',
				3 => 'alumnos',               // el recuento de representados (alias)
			];
		}

		public function representantesServidor(array $peticion): array
		{
			$dibujo  = (int)($peticion['draw'] ?? 1);
			$inicio  = max(0, (int)($peticion['start'] ?? 0));
			$largo   = (int)($peticion['length'] ?? 25);
			$largo   = ($largo < 1 || $largo > 200) ? 25 : $largo;
			$buscado = trim((string)($peticion['search']['value'] ?? ''));

			/*----------  Orden: lista blanca, nunca lo que llegue  ----------*/
			$columnas = $this->columnasRepresentante();
			$idxOrden = (int)($peticion['order'][0]['column'] ?? 2);
			$colOrden = $columnas[$idxOrden] ?? 'repre_apellidopaterno';
			$dirOrden = strtolower((string)($peticion['order'][0]['dir'] ?? 'asc')) === 'desc'
			          ? 'DESC' : 'ASC';

			/*----------  Filtro  ----------*/
			/* $donde va aparte de la tabla: la página le une el recuento de
			   alumnos y los COUNT no lo necesitan. */
			$donde  = "WHERE repre_estado IN ('A','I') AND repre_primernombre <> ''";
			$par    = [];

			if ($buscado !== '') {
				/* Una sola condicion sobre los tres campos que se ven: el
				   usuario busca «lo que sea que recuerde», no una columna. */
				$donde .= " AND (repre_identificacion LIKE :b
				             OR repre_primernombre   LIKE :b
				             OR repre_segundonombre  LIKE :b
				             OR repre_apellidopaterno LIKE :b
				             OR repre_apellidomaterno LIKE :b)";
				$par[':b'] = '%' . $buscado . '%';
			}

			/*----------  Recuentos  ----------*/
			$total = (int)$this->ejecutarConsulta(
				"SELECT COUNT(1) FROM alumno_representante
				  WHERE repre_estado IN ('A','I') AND repre_primernombre <> ''")->fetchColumn();

			$filtrados = $buscado === ''
				? $total
				: (int)$this->ejecutarConsulta("SELECT COUNT(1) FROM alumno_representante " . $donde, $par)->fetchColumn();

			/*----------  La pagina  ----------*/
			/* Cuántos alumnos representa cada uno, y cuántos siguen activos.
			   Un recuento agrupado y unido, no una subconsulta por fila: así
			   también se puede ORDENAR por él en la base. Los eliminados ('E')
			   no cuentan. */
			$sql = "SELECT repre_id, repre_identificacion, repre_primernombre,
			               repre_segundonombre, repre_apellidopaterno,
			               repre_apellidomaterno, repre_firmado,
			               COALESCE(AC.total, 0) AS alumnos, COALESCE(AC.activos, 0) AS activos
			        FROM alumno_representante
			        LEFT JOIN (SELECT alumno_repreid, COUNT(*) AS total, SUM(alumno_estado = 'A') AS activos
			                     FROM sujeto_alumno
			                    WHERE alumno_estado <> 'E'
			                    GROUP BY alumno_repreid) AC ON AC.alumno_repreid = repre_id
			        " . $donde
			    . " ORDER BY " . $colOrden . " " . $dirOrden . ", repre_id ASC"
			    . " LIMIT " . (int)$largo . " OFFSET " . (int)$inicio;

			$filas = $this->ejecutarConsulta($sql, $par)->fetchAll(\PDO::FETCH_ASSOC);

			$datos = [];
			foreach ($filas as $r) {
				$id = (int)$r['repre_id'];

				$firmado = $r['repre_firmado'] === 'S';
				$texto   = $firmado ? 'Firmado' : 'Pendiente';
				$boton   = $firmado ? 'btn-secondary' : 'btn-info';

				/* Los alumnos: el total como insignia, que lleva a la ficha del
				   representante (ahí está la lista). Azul si alguno está activo,
				   gris si ninguno; y debajo cuántos activos, sólo si no lo están
				   todos (de 853 alumnos, 630 están inactivos). */
				$nAlumnos = (int)$r['alumnos'];
				$nActivos = (int)$r['activos'];
				$celdaAlumnos = '<a href="' . APP_URL . 'representanteProfile/' . $id . '/" class="badge rounded-pill text-decoration-none '
				              . ($nActivos > 0 ? 'text-bg-primary' : 'text-bg-secondary') . '"'
				              . ' title="Ver sus representados" aria-label="' . $nAlumnos . ($nAlumnos === 1 ? ' alumno' : ' alumnos') . '">' . $nAlumnos . '</a>'
				              . ($nAlumnos > 0 && $nActivos < $nAlumnos
				                  ? '<span class="d-block small text-body-secondary text-nowrap">' . ($nActivos === 0 ? 'ninguno activo' : $nActivos . ($nActivos === 1 ? ' activo' : ' activos')) . '</span>'
				                  : '');

				$datos[] = [
					htmlspecialchars((string)$r['repre_identificacion'], ENT_QUOTES, 'UTF-8'),
					htmlspecialchars(trim($r['repre_primernombre'] . ' ' . $r['repre_segundonombre']), ENT_QUOTES, 'UTF-8'),
					htmlspecialchars(trim($r['repre_apellidopaterno'] . ' ' . $r['repre_apellidomaterno']), ENT_QUOTES, 'UTF-8'),
					$celdaAlumnos,

					$this->siPuede('crear', 'representanteList',
						'<a href="' . APP_URL . 'alumnoNew/' . $id . '/" class="btn float-end btn-secondary btn-sm ms-1"><i class="fas fa-plus me-1"></i>Nuevo Alumno</a>')
					. $this->siPuede('crear', 'representanteList',
						'<a href="' . APP_URL . 'representanteVinc/' . $id . '/" class="btn float-end btn-warning btn-sm ms-1"><i class="fas fa-link me-1"></i>Vincular alumno</a>'),

					'<a href="' . APP_URL . 'representanteFLPD/' . $id . '/" target="_blank" class="nav-icon far fa-file float-end ms-1" title="Formulario LPD"></a>'
					. $this->siPuede('editar', 'representanteList',
						'<button type="button" class="btn float-end ' . $boton . ' btn-sm ms-1 js-repre" data-accion="estadofirmado" data-id="' . $id . '"> ' . $texto . ' </button>'),

					$this->siPuede('eliminar', 'representanteList',
						'<button type="button" class="btn float-end btn-danger btn-sm ms-1 js-repre" data-accion="eliminar" data-id="' . $id . '" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>')
					. $this->siPuede('editar', 'representanteList',
						'<a href="' . APP_URL . 'representanteUpdate/' . $id . '/" class="btn float-end btn-actualizar btn-sm ms-1" title="Actualizar" aria-label="Actualizar"><i class="fas fa-pen"></i></a>')
					. '<a href="' . APP_URL . 'representanteProfile/' . $id . '/" class="btn float-end btn-ver btn-sm ms-1" title="Ver" aria-label="Ver"><i class="fas fa-eye"></i></a>',
				];
			}

			return [
				'draw'            => $dibujo,
				'recordsTotal'    => $total,
				'recordsFiltered' => $filtrados,
				'data'            => $datos,
			];
		}
		/*----------  ¿Ya existe un representante con esta identificación?  ----------*/
		/*
		| Para el alta: los padres de un segundo hijo ya están registrados, y
		| antes se enteraban al guardar («El representante ya se encuentra
		| creado») después de rellenarlo todo. Ahora se pregunta al salir del
		| campo y se ofrece ir a él. Sólo lectura: el nombre y cuántos alumnos
		| tiene, lo mismo que ya muestra el listado a quien puede verlo.
		*/
		public function consultarIdentificacion(string $identificacion): array {
			$identificacion = trim($identificacion);
			if ($identificacion === '') {
				return ['existe' => false];
			}
			$r = $this->ejecutarConsulta("SELECT repre_id, repre_primernombre, repre_apellidopaterno,
						(SELECT COUNT(*) FROM sujeto_alumno WHERE alumno_repreid = repre_id AND alumno_estado <> 'E') AS alumnos
					FROM alumno_representante WHERE repre_identificacion = :ident LIMIT 1", [':ident' => $identificacion])->fetch();
			if (!$r) {
				return ['existe' => false];
			}
			return [
				'existe'  => true,
				'id'      => (int)$r['repre_id'],
				'nombre'  => html_entity_decode(trim($r['repre_primernombre'] . ' ' . $r['repre_apellidopaterno']), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				'alumnos' => (int)$r['alumnos'],
			];
		}

		public function listarRepresentantes($identificacion, $apellidopaterno, $primernombre){
			$estado = "";
			$texto = "";
			$boton = "";

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
			$consulta_datos="SELECT * FROM alumno_representante
								WHERE repre_estado in ('A','I')
									AND (repre_primernombre LIKE :nombre 
										OR repre_identificacion LIKE :ident 
										OR repre_apellidopaterno LIKE :apellido)";			
			$parametros = [':nombre' => $primernombre, ':ident' => $identificacion,
			                ':apellido' => $apellidopaterno];
			
			if($identificacion=="" && $primernombre=="" && $apellidopaterno==""){
				$consulta_datos = "SELECT * FROM alumno_representante WHERE repre_primernombre <> '' AND repre_estado in ('A','I') ";
				/* Consulta nueva sin filtros: el array se vacia. */
				$parametros = [];
			}			
										
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
		
			if($datos->rowCount()>0){
				$datos = $datos->fetchAll();
			}

			foreach($datos as $rows){	
				if($rows['repre_firmado']=='S'){
					$estado = "N";
					$texto = "Firmado";
					$boton = "btn-secondary";
				}else{
					$estado = "S";
					$texto = "Pendiente";
					$boton = "btn-info";
				}
			
				$tabla.='				
					<tr>
						<td>'.$rows['repre_identificacion'].'</td>
						<td>'.$rows['repre_primernombre'].' '.$rows['repre_segundonombre'].'</td>
						<td>'.$rows['repre_apellidopaterno'].' '.$rows['repre_apellidomaterno'].'</td>
						<td>							
							'.$this->siPuede('crear','representanteList','<a href="'.APP_URL.'alumnoNew/'.$rows['repre_id'].'/" class="btn float-end btn-secondary btn-sm ms-1"><i class="fas fa-plus me-1"></i>Nuevo Alumno</a>').'	
							'.$this->siPuede('crear','representanteList','<a href="'.APP_URL.'representanteVinc/'.$rows['repre_id'].'/" class="btn float-end btn-warning btn-sm ms-1"><i class="fas fa-link me-1"></i>Vincular alumno</a>').'
						</td>
						<td>
							<a href="'.APP_URL.'representanteFLPD/'.$rows['repre_id'].'/" target="_blank" class="nav-icon far fa-file float-end ms-1" title="Formulario LPD"></a>
							'.$this->siPuede('editar','representanteList','<button type="button" class="btn float-end '.$boton.' btn-sm ms-1 js-repre" data-accion="estadofirmado" data-id="'.$rows['repre_id'].'"> '.$texto.' </button>').'
						</td>
						<td>
							'.$this->siPuede('eliminar','representanteList','<button type="button" class="btn float-end btn-danger btn-sm ms-1 js-repre" data-accion="eliminar" data-id="'.$rows['repre_id'].'" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>').'
													
							'.$this->siPuede('editar','representanteList','<a href="'.APP_URL.'representanteUpdate/'.$rows['repre_id'].'/" class="btn float-end btn-actualizar btn-sm ms-1" title="Actualizar" aria-label="Actualizar"><i class="fas fa-pen"></i></a>').'							
							<a href="'.APP_URL.'representanteProfile/'.$rows['repre_id'].'/" class="btn float-end btn-ver btn-sm ms-1" title="Ver" aria-label="Ver"><i class="fas fa-eye"></i></a>							
						</td>
					</tr>';	
			}
			return $tabla;			
		}
		
		public function registrarRepresentanteControlador(){	
			/*---------------Variables para el registro del tab Representante del alumno----------------*/
			$repre_tipoidentificacion 	= $this->limpiarCadena($_POST['repre_tipoidentificacion']);
			$repre_identificacion 	  	= $this->limpiarCadena($_POST['repre_identificacion']);
			$repre_primernombre		  	= $this->limpiarCadena($_POST['repre_primernombre']);
			$repre_segundonombre 	 	= $this->limpiarCadena($_POST['repre_segundonombre']);
			$repre_apellidopaterno 	  	= $this->limpiarCadena($_POST['repre_apellidopaterno']);
			$repre_apellidomaterno 	 	= $this->limpiarCadena($_POST['repre_apellidomaterno']);
			$repre_direccion 		  	= $this->limpiarCadena($_POST['repre_direccion']);
			$repre_correo 			  	= $this->limpiarCadena($_POST['repre_correo']);
			$repre_celular 			  	= $this->limpiarCadena($_POST['repre_celular']);
			$repre_parentesco 		  	= $this->limpiarCadena($_POST['repre_parentesco']);
			$repre_sexo 			  	= "";
			$repre_factura 			  	= "";

			if (isset($_POST['repre_sexo'])){$repre_sexo = $_POST['repre_sexo'];}	
			if (isset($_POST['repre_factura'])){$repre_factura = $_POST['repre_factura'];}	

			if($repre_identificacion=="" || $repre_primernombre=="" || $repre_apellidopaterno=="" || 
				$repre_direccion=="" || $repre_correo=="" || $repre_celular==""){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"No ha completado los campos obligatorios del representante del alumno",
						"icono"=>"error"
					];
					return json_encode($alerta);
			}

			$consultarepre=$this->seleccionarDatos("Unico","alumno_representante","repre_identificacion",$repre_identificacion);
			if($consultarepre->rowCount()>0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Representante ya existe",
					"texto"=>"El representante ya se encuentra creado, por favor revisar.",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			/*---------------Registro del tab Representante del alumno----------------*/
			$representante_reg=[		
				[
					"campo_nombre"=>"repre_tipoidentificacion",
					"campo_marcador"=>":TipoIdentificacionRep",
					"campo_valor"=>$repre_tipoidentificacion
				],
				[
					"campo_nombre"=>"repre_identificacion",
					"campo_marcador"=>":IdnetificacionRep",
					"campo_valor"=>$repre_identificacion
				],
				[
					"campo_nombre"=>"repre_primernombre",
					"campo_marcador"=>":PrimerNombreRep",
					"campo_valor"=>$repre_primernombre
				],						
				[
					"campo_nombre"=>"repre_segundonombre",
					"campo_marcador"=>":SegundoNombreRep",
					"campo_valor"=>$repre_segundonombre
				],						
				[
					"campo_nombre"=>"repre_apellidopaterno",
					"campo_marcador"=>":ApellidoPatRep",
					"campo_valor"=>$repre_apellidopaterno
				],
				[
					"campo_nombre"=>"repre_apellidomaterno",
					"campo_marcador"=>":ApellidoMaternoRep",
					"campo_valor"=>$repre_apellidomaterno
				],
				[
					"campo_nombre"=>"repre_direccion",
					"campo_marcador"=>":DireccionRep",
					"campo_valor"=>$repre_direccion
				],						
				[
					"campo_nombre"=>"repre_correo",
					"campo_marcador"=>":CorreoRep",
					"campo_valor"=>$repre_correo
				],						
				[
					"campo_nombre"=>"repre_celular",
					"campo_marcador"=>":CelularRep",
					"campo_valor"=>$repre_celular
				],
				[
					"campo_nombre"=>"repre_sexo",
					"campo_marcador"=>":SexoRep",
					"campo_valor"=>$repre_sexo
				],
				[
					"campo_nombre"=>"repre_parentesco",
					"campo_marcador"=>":ParentescoRep",
					"campo_valor"=>$repre_parentesco
				],
				[
					"campo_nombre"=>"repre_factura",
					"campo_marcador"=>":RepFactura",
					"campo_valor"=>$repre_factura
				]
			];

			$registrar_alumno_representante=$this->guardarDatos("alumno_representante",$representante_reg);
			if($registrar_alumno_representante->rowCount()>0){
				$obtener_repreid=$this->ejecutarConsulta("SELECT repre_id FROM alumno_representante WHERE repre_identificacion = :ident", [':ident' => $repre_identificacion]);
				if($obtener_repreid->rowCount()==1){
					$repre=$obtener_repreid->fetchAll(); 					
					foreach( $repre as $rows ){
						$repreid = $rows['repre_id'];
					}
				}

				$alerta=[
					"tipo"=>"redireccionar",			
					"url"=>APP_URL.'alumnoNew/'.$repreid,					
					"titulo"=>"Representante registrado",
					"texto"=>"El representante ".$repre_identificacion." | ".$repre_primernombre." ".$repre_apellidopaterno." se registró correctamente",
					"icono"=>"success"	
				];

				/*---------------Obtengo campo repreid para la tabla alumno_representanteconyuge-------------*/
				$check_representanteid=$this->ejecutarConsulta("SELECT repre_id FROM alumno_representante WHERE repre_identificacion = :ident", [':ident' => $repre_identificacion]);
		
				if($check_representanteid->rowCount()==1){
					$representante=$check_representanteid->fetchAll(); 					
					foreach( $representante as $rows ){
						$representanteid = $rows['repre_id'];
					}				

					/*---------------Registro de la información del cónyuge del representante del alumno---------*/
					
					/*---------------Variables para el registro del cónyuge del representante del alumno----------------*/
					$conyuge_tipoidentificacion = $this->limpiarCadena($_POST['conyuge_tipoidentificacion']);
					$conyuge_identificacion 	= $this->limpiarCadena($_POST['conyuge_identificacion']);
					$conyuge_primernombre		= $this->limpiarCadena($_POST['conyuge_primernombre']);
					$conyuge_segundonombre 	 	= $this->limpiarCadena($_POST['conyuge_segundonombre']);
					$conyuge_apellidopaterno 	= $this->limpiarCadena($_POST['conyuge_apellidopaterno']);
					$conyuge_apellidomaterno 	= $this->limpiarCadena($_POST['conyuge_apellidomaterno']);
					$conyuge_direccion 		  	= $this->limpiarCadena($_POST['conyuge_direccion']);
					$conyuge_correo 			= $this->limpiarCadena($_POST['conyuge_correo']);
					$conyuge_celular 		  	= $this->limpiarCadena($_POST['conyuge_celular']);
					$conyuge_sexo 			  	= "";

					if (isset($_POST['conyuge_sexo'])){$conyuge_sexo = $_POST['conyuge_sexo'];}else{$conyuge_sexo = "";}
								
					if($conyuge_identificacion!="" || $conyuge_primernombre!="" || $conyuge_segundonombre!="" || $conyuge_apellidopaterno!=""||
						$conyuge_apellidomaterno!="" || $conyuge_direccion!="" || $conyuge_correo!="" || $conyuge_celular!=""){

						$conyuge_reg=[
							[
								"campo_nombre"=>"conyuge_repid",
								"campo_marcador"=>":Representanteid",
								"campo_valor"=>$representanteid
							],					
							[
								"campo_nombre"=>"conyuge_tipoidentificacion",
								"campo_marcador"=>":TipoIdentificacionCRep",
								"campo_valor"=>$conyuge_tipoidentificacion
							],
							[
								"campo_nombre"=>"conyuge_identificacion",
								"campo_marcador"=>":IdentificacionCRep",
								"campo_valor"=>$conyuge_identificacion
							],
							[
								"campo_nombre"=>"conyuge_primernombre",
								"campo_marcador"=>":PrimerNombreCRep",
								"campo_valor"=>$conyuge_primernombre
							],						
							[
								"campo_nombre"=>"conyuge_segundonombre",
								"campo_marcador"=>":SegundoNombreCRep",
								"campo_valor"=>$conyuge_segundonombre
							],						
							[
								"campo_nombre"=>"conyuge_apellidopaterno",
								"campo_marcador"=>":ApellidoPatCRep",
								"campo_valor"=>$conyuge_apellidopaterno
							],
							[
								"campo_nombre"=>"conyuge_apellidomaterno",
								"campo_marcador"=>":ApellidoMaternoCRep",
								"campo_valor"=>$conyuge_apellidomaterno
							],
							[
								"campo_nombre"=>"conyuge_direccion",
								"campo_marcador"=>":DireccionCRep",
								"campo_valor"=>$conyuge_direccion
							],						
							[
								"campo_nombre"=>"conyuge_correo",
								"campo_marcador"=>":CorreoCRep",
								"campo_valor"=>$conyuge_correo
							],						
							[
								"campo_nombre"=>"conyuge_celular",
								"campo_marcador"=>":CelularCRep",
								"campo_valor"=>$conyuge_celular
							],
							[
								"campo_nombre"=>"conyuge_sexo",
								"campo_marcador"=>":SexoCRep",
								"campo_valor"=>$conyuge_sexo
							]
						];

						$registrar_conyuge_rep=$this->guardarDatos("alumno_representanteconyuge",$conyuge_reg);
						if($registrar_conyuge_rep->rowCount()>0){
							$alerta=[
								"tipo"=>"redireccionar",			
								"url"=>APP_URL.'alumnoNew/'.$repreid,					
								"titulo"=>"Representante registrado",
								"texto"=>"El representante ".$repre_identificacion." | ".$repre_primernombre." ".$repre_apellidopaterno." se registró correctamente",
								"icono"=>"success"	
							]; 
						}else{
							$alerta=[
								"tipo"=>"simple",
								"titulo"=>"Ocurrió un error",
								"texto"=>"No fue posible registrar la información de cónyuge del representante del alumno, por favor intente nuevamente",
								"icono"=>"error"
							];
						}
					}				
				}/*---------------Fin de registro de la información del cónyuge del representante del alumno*/ 					
			}	
			return json_encode($alerta);
		}	
		public function listarCatalogoTipoDocumento(){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'tipo_documento'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
			}
			return $option;
		}
		
		/* En el formulario de alta no hay parentesco previo que preseleccionar,
		   asi que el argumento es opcional: exigirlo obligaba a la vista a
		   pasar una variable inexistente y eso imprimia un aviso de PHP. */
		public function listarCatalogoParentesco($repre_parentesco = ''){
			$option ='<option value=0> Seleccione una opción</option>';


			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'parentesco'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($repre_parentesco == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}
			}
			return $option;
		}
		
		/*----------  Obtener el tipo de documento guardado  ----------*/
		public function listarOptionTipoIdentificacion($tipoidentificacion){
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

		/*----------  Matriz de representados  ----------*/
		/*----------  La ficha del representante, ya en texto  ----------*/
		/*
		| De solo lectura. Antes eran campos deshabilitados y, para mostrar el
		| parentesco o el tipo de documento, se generaban los <option> de cada
		| catálogo. Cada catálogo se filtra por SU tabla.
		|
		| Los representados van con lo que se busca de un vistazo —edad,
		| categoría, sede, si está activo— y su id para abrir su ficha. Los
		| activos primero. Los eliminados ('E') no salen.
		|
		| Textos sin escapar: la vista los escapa una vez. Null si no existe.
		*/
		public function fichaRepresentante($repreid){
			$p = [':repre' => (int)$repreid];
			$catalogo = static fn(string $alias, string $tabla, string $valor): string =>
				"LEFT JOIN (general_tabla_catalogo $alias
				            INNER JOIN general_tabla {$alias}T ON {$alias}T.tabla_id = $alias.catalogo_tablaid
				                                            AND {$alias}T.tabla_nombre = '$tabla')
				       ON $alias.catalogo_valor = $valor";

			$repre = $this->ejecutarConsulta("SELECT R.*, PA.catalogo_descripcion AS parentesco, TI.catalogo_descripcion AS tipo_identificacion
					FROM alumno_representante R
					" . $catalogo('PA', 'parentesco', 'R.repre_parentesco') . "
					" . $catalogo('TI', 'tipo_documento', 'R.repre_tipoidentificacion') . "
					WHERE R.repre_id = :repre", $p)->fetch();
			if (!$repre) {
				return null;
			}

			$conyuge = $this->ejecutarConsulta("SELECT C.*, TI.catalogo_descripcion AS tipo_identificacion
					FROM alumno_representanteconyuge C
					" . $catalogo('TI', 'tipo_documento', 'C.conyuge_tipoidentificacion') . "
					WHERE C.conyuge_repid = :repre ORDER BY C.conyuge_id DESC LIMIT 1", $p)->fetch();

			$alumnos = $this->ejecutarConsulta("SELECT A.alumno_id, A.alumno_identificacion, A.alumno_primernombre, A.alumno_segundonombre,
						A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_fechanacimiento, A.alumno_fechaingreso,
						A.alumno_estado, A.alumno_imagen, S.sede_nombre
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
					WHERE A.alumno_repreid = :repre AND A.alumno_estado IN ('A','I')
					ORDER BY A.alumno_estado = 'A' DESC, A.alumno_fechanacimiento", $p)->fetchAll();

			$texto = static fn($v) => $v === null ? '' : html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$limpio = static function (array $fila) use ($texto): array {
				return array_map($texto, $fila);
			};
			return [
				'representante' => $limpio($repre),
				'conyuge'       => $conyuge ? $limpio($conyuge) : null,
				'alumnos'       => array_map($limpio, $alumnos),
			];
		}

		/*----------  Controlador actualizar representante  ----------*/
		public function actualizarRepresentanteControlador(){
	
			$repreid=$this->limpiarCadena($_POST['repre_id']);
			
			# Verificando existencia de representante #
			$representante=$this->ejecutarConsulta("SELECT * FROM alumno_representante WHERE repre_id = :repre", [':repre' => (int)$repreid]);
			if($representante->rowCount()<=0){	
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El representante no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);		    
		    }else{
		    	$representante=$representante->fetch();
		    }	

			/*---------------Variables para el registro del tab Representante del alumno----------------*/
			$repre_tipoidentificacion 	= $this->limpiarCadena($_POST['repre_tipoidentificacion']);
			$repre_identificacion 	  	= $this->limpiarCadena($_POST['repre_identificacion']);
			$repre_primernombre		  	= $this->limpiarCadena($_POST['repre_primernombre']);
			$repre_segundonombre 	 	= $this->limpiarCadena($_POST['repre_segundonombre']);
			$repre_apellidopaterno 	  	= $this->limpiarCadena($_POST['repre_apellidopaterno']);
			$repre_apellidomaterno 	 	= $this->limpiarCadena($_POST['repre_apellidomaterno']);
			$repre_direccion 		  	= $this->limpiarCadena($_POST['repre_direccion']);
			$repre_correo 			  	= $this->limpiarCadena($_POST['repre_correo']);
			$repre_celular 			  	= $this->limpiarCadena($_POST['repre_celular']);
			$repre_parentesco 		  	= $this->limpiarCadena($_POST['repre_parentesco']);
			$repre_sexo 			  	= "";
			$repre_factura			  	= "";

			# Verificando campos obligatorios #
			if($repre_identificacion=="" || $repre_primernombre=="" || $repre_apellidopaterno=="" || 
				$repre_direccion=="" || $repre_correo=="" || $repre_celular==""){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No ha completado los campos obligatorios del representante del alumno",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}		
						
			if (isset($_POST['repre_sexo'])) {
				$repre_sexo = $_POST['repre_sexo'];
			}else{
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No ha completado los campos obligatorios del representante",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			if (isset($_POST['repre_factura'])) {
				$repre_factura = $_POST['repre_factura'];
			}else{
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No ha completado el campo obligatorio requiere factura",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}
				$representante_reg=[										
					[
						"campo_nombre"=>"repre_tipoidentificacion",
						"campo_marcador"=>":TipoIdentificacionRep",
						"campo_valor"=>$repre_tipoidentificacion
					],
					[
						"campo_nombre"=>"repre_identificacion",
						"campo_marcador"=>":IdnetificacionRep",
						"campo_valor"=>$repre_identificacion
					],
					[
						"campo_nombre"=>"repre_primernombre",
						"campo_marcador"=>":PrimerNombreRep",
						"campo_valor"=>$repre_primernombre
					],						
					[
						"campo_nombre"=>"repre_segundonombre",
						"campo_marcador"=>":SegundoNombreRep",
						"campo_valor"=>$repre_segundonombre
					],						
					[
						"campo_nombre"=>"repre_apellidopaterno",
						"campo_marcador"=>":ApellidoPatRep",
						"campo_valor"=>$repre_apellidopaterno
					],
					[
						"campo_nombre"=>"repre_apellidomaterno",
						"campo_marcador"=>":ApellidoMaternoRep",
						"campo_valor"=>$repre_apellidomaterno
					],
					[
						"campo_nombre"=>"repre_direccion",
						"campo_marcador"=>":DireccionRep",
						"campo_valor"=>$repre_direccion
					],						
					[
						"campo_nombre"=>"repre_correo",
						"campo_marcador"=>":CorreoRep",
						"campo_valor"=>$repre_correo
					],						
					[
						"campo_nombre"=>"repre_celular",
						"campo_marcador"=>":CelularRep",
						"campo_valor"=>$repre_celular
					],
					[
						"campo_nombre"=>"repre_sexo",
						"campo_marcador"=>":SexoRep",
						"campo_valor"=>$repre_sexo
					],
					[
						"campo_nombre"=>"repre_parentesco",
						"campo_marcador"=>":ParentescoRep",
						"campo_valor"=>$repre_parentesco
					],
					[
						"campo_nombre"=>"repre_factura",
						"campo_marcador"=>":RepFactura",
						"campo_valor"=>$repre_factura
					]
				];
				$condicion=[
					"condicion_campo"=>"repre_id",
					"condicion_marcador"=>":Repreid",
					"condicion_valor"=>$repreid
				];				

				if($this->actualizarDatos("alumno_representante",$representante_reg,$condicion)){
					$alerta=[
						"tipo"=>"recargar",
						"titulo"=>"Representante actualizado",
						"texto"=>"El representante ".$repre_identificacion." | ".$repre_primernombre." ".$repre_apellidopaterno." se actualizó correctamente",
						"icono"=>"success"
					];
	
					/*---------------Registro de la información del cónyuge del representante del alumno---------*/
				
					/*---------------Variables para el registro del cónyuge del representante del alumno----------------*/
					$check_representanteid=$this->ejecutarConsulta("SELECT repre_id FROM alumno_representante WHERE repre_identificacion = :ident", [':ident' => $repre_identificacion]);
					if($check_representanteid->rowCount()>0){
						$representante=$check_representanteid->fetchAll(); 					
						foreach( $representante as $rows ){
							$representanteid = $rows['repre_id'];							
						}

						$conyuge_tipoidentificacion = $this->limpiarCadena($_POST['conyuge_tipoidentificacion']);
						$conyuge_identificacion 	= $this->limpiarCadena($_POST['conyuge_identificacion']);
						$conyuge_primernombre		= $this->limpiarCadena($_POST['conyuge_primernombre']);
						$conyuge_segundonombre 	 	= $this->limpiarCadena($_POST['conyuge_segundonombre']);
						$conyuge_apellidopaterno 	= $this->limpiarCadena($_POST['conyuge_apellidopaterno']);
						$conyuge_apellidomaterno 	= $this->limpiarCadena($_POST['conyuge_apellidomaterno']);
						$conyuge_direccion 		  	= $this->limpiarCadena($_POST['conyuge_direccion']);
						$conyuge_correo 			= $this->limpiarCadena($_POST['conyuge_correo']);
						$conyuge_celular 		  	= $this->limpiarCadena($_POST['conyuge_celular']);
						$conyuge_sexo 			  	= "";

						if (isset($_POST['conyuge_sexo'])){$conyuge_sexo = $_POST['conyuge_sexo'];}	

						$conyuge=$this->ejecutarConsulta("SELECT * FROM alumno_representanteconyuge WHERE conyuge_repid = :repre", [':repre' => (int)$representanteid]);
						if($conyuge->rowCount()>0){				
							
							$conyuge_reg=[
								[
									"campo_nombre"=>"conyuge_repid",
									"campo_marcador"=>":Representanteid",
									"campo_valor"=>$representanteid
								],					
								[
									"campo_nombre"=>"conyuge_tipoidentificacion",
									"campo_marcador"=>":TipoIdentificacionCRep",
									"campo_valor"=>$conyuge_tipoidentificacion
								],
								[
									"campo_nombre"=>"conyuge_identificacion",
									"campo_marcador"=>":IdentificacionCRep",
									"campo_valor"=>$conyuge_identificacion
								],
								[
									"campo_nombre"=>"conyuge_primernombre",
									"campo_marcador"=>":PrimerNombreCRep",
									"campo_valor"=>$conyuge_primernombre
								],						
								[
									"campo_nombre"=>"conyuge_segundonombre",
									"campo_marcador"=>":SegundoNombreCRep",
									"campo_valor"=>$conyuge_segundonombre
								],						
								[
									"campo_nombre"=>"conyuge_apellidopaterno",
									"campo_marcador"=>":ApellidoPatCRep",
									"campo_valor"=>$conyuge_apellidopaterno
								],
								[
									"campo_nombre"=>"conyuge_apellidomaterno",
									"campo_marcador"=>":ApellidoMaternoCRep",
									"campo_valor"=>$conyuge_apellidomaterno
								],
								[
									"campo_nombre"=>"conyuge_direccion",
									"campo_marcador"=>":DireccionCRep",
									"campo_valor"=>$conyuge_direccion
								],						
								[
									"campo_nombre"=>"conyuge_correo",
									"campo_marcador"=>":CorreoCRep",
									"campo_valor"=>$conyuge_correo
								],						
								[
									"campo_nombre"=>"conyuge_celular",
									"campo_marcador"=>":CelularCRep",
									"campo_valor"=>$conyuge_celular
								],
								[
									"campo_nombre"=>"conyuge_sexo",
									"campo_marcador"=>":SexoCRep",
									"campo_valor"=>$conyuge_sexo
								]
							];

							$condicion=[
								"condicion_campo"=>"conyuge_repid",
								"condicion_marcador"=>":Representanteid",
								"condicion_valor"=>$representanteid
							];
							
							$this->actualizarDatos("alumno_representanteconyuge",$conyuge_reg,$condicion);

						}else{			

							if($conyuge_identificacion!="" || $conyuge_primernombre!="" || $conyuge_segundonombre!="" || $conyuge_apellidopaterno!=""||
							$conyuge_apellidomaterno!="" || $conyuge_direccion!="" || $conyuge_correo!="" || $conyuge_celular!=""){

								$conyuge_reg=[
									[
										"campo_nombre"=>"conyuge_repid",
										"campo_marcador"=>":Representanteid",
										"campo_valor"=>$representanteid
									],					
									[
										"campo_nombre"=>"conyuge_tipoidentificacion",
										"campo_marcador"=>":TipoIdentificacionCRep",
										"campo_valor"=>$conyuge_tipoidentificacion
									],
									[
										"campo_nombre"=>"conyuge_identificacion",
										"campo_marcador"=>":IdentificacionCRep",
										"campo_valor"=>$conyuge_identificacion
									],
									[
										"campo_nombre"=>"conyuge_primernombre",
										"campo_marcador"=>":PrimerNombreCRep",
										"campo_valor"=>$conyuge_primernombre
									],						
									[
										"campo_nombre"=>"conyuge_segundonombre",
										"campo_marcador"=>":SegundoNombreCRep",
										"campo_valor"=>$conyuge_segundonombre
									],						
									[
										"campo_nombre"=>"conyuge_apellidopaterno",
										"campo_marcador"=>":ApellidoPatCRep",
										"campo_valor"=>$conyuge_apellidopaterno
									],
									[
										"campo_nombre"=>"conyuge_apellidomaterno",
										"campo_marcador"=>":ApellidoMaternoCRep",
										"campo_valor"=>$conyuge_apellidomaterno
									],
									[
										"campo_nombre"=>"conyuge_direccion",
										"campo_marcador"=>":DireccionCRep",
										"campo_valor"=>$conyuge_direccion
									],						
									[
										"campo_nombre"=>"conyuge_correo",
										"campo_marcador"=>":CorreoCRep",
										"campo_valor"=>$conyuge_correo
									],						
									[
										"campo_nombre"=>"conyuge_celular",
										"campo_marcador"=>":CelularCRep",
										"campo_valor"=>$conyuge_celular
									],
									[
										"campo_nombre"=>"conyuge_sexo",
										"campo_marcador"=>":SexoCRep",
										"campo_valor"=>$conyuge_sexo
									]
								];

								$this->guardarDatos("alumno_representanteconyuge",$conyuge_reg);						
							}
						}				
					}
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Representante no actualizado",
						"texto"=>"No fue posible actualizar los datos del representante ".$repre_identificacion." | ".$repre_primernombre." ".$repre_apellidopaterno.", por favor intente nuevamente",
						"icono"=>"success"
					];
				}
			return json_encode($alerta);
		}

		
		/*----------  Controlador eliminar representante  ----------*/
		public function eliminarRepresentanteControlador(){

			$repre_id=$this->limpiarCadena($_POST['repre_id']);

			# Verificando existencia de representante #
		    $datos=$this->ejecutarConsulta("SELECT * FROM alumno_representante WHERE repre_id = :repre", [':repre' => (int)$repre_id]);
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Representante no se encuentra en el sistema",
					"texto"=>"El representante no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();
				
				# Validar los representados
				/* La consulta de arriba ya iba parametrizada y esta no: la
				   migracion se quedo a medias. limpiarCadena() es
				   htmlspecialchars(), que protege el HTML, no el SQL; que
				   aqui no se pudiera romper era merito de las comillas
				   simples que rodean el valor, no de la limpieza. */
				$alumno_estado=$this->ejecutarConsulta("SELECT alumno_id, alumno_repreid, alumno_primernombre, alumno_apellidopaterno, repre_identificacion
															FROM alumno_representante, sujeto_alumno
															WHERE alumno_repreid = repre_id
																AND alumno_estado in ('A','I')
																AND repre_id = :repre", [':repre' => (int)$repre_id]);
				if($alumno_estado->rowCount()>0){
					$alerta=[
						"tipo"	=>"simple",
						"titulo"=>"Acción no permitida",
						"texto"	=>"El representante mantiene alumnos vigentes, por favor revisar",
						"icono"	=>"error"
					];
					return json_encode($alerta);
				}
		    }
			
			$estadoR = '';
			if($datos['repre_estado']=='A' || $datos['repre_estado']=='I'){
				$estadoR = 'E';
			}
			
            $repre_datos_up=[
				[
					"campo_nombre"=>"repre_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoR
				]
			];
			$condicion=[
				"condicion_campo"=>"repre_id",
				"condicion_marcador"=>":Repreid",
				"condicion_valor"=>$repre_id
			];

			if($this->actualizarDatos("alumno_representante",$repre_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Representante eliminado",
					"texto"=>"El representante ".$datos['repre_primernombre']." | ".$datos['repre_apellidopaterno']." fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No hemos podido eliminar el representante ".$datos['repre_primernombre']." ".$datos['repre_apellidopaterno'].", por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		/*----------  Búsqueda de alumno para asociar a un representante  ----------*/
		public function buscarRepresentado($identificacion, $apellidopaterno, $primernombre, $repreid){
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
			$consulta_datos="SELECT * FROM sujeto_alumno 
								WHERE alumno_estado in ('A','I')
									AND (alumno_primernombre LIKE :nombre 
									OR alumno_identificacion LIKE :ident 
									OR alumno_apellidopaterno LIKE :apellido) ";			
			$parametros = [':nombre' => $primernombre, ':ident' => $identificacion,
			                ':apellido' => $apellidopaterno];
			
			if($identificacion=="" && $primernombre=="" && $apellidopaterno==""){
				$consulta_datos = "SELECT * FROM sujeto_alumno WHERE alumno_primernombre <> '' AND alumno_estado in ('A','I')";
				/* Consulta nueva sin filtros: el array se vacia. */
				$parametros = [];
			}
			
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['alumno_primernombre'].' '.$rows['alumno_segundonombre'].'</td>
						<td>'.$rows['alumno_apellidopaterno'].' '.$rows['alumno_apellidomaterno'].'</td>
						<td>
							'.$this->siPuede('editar','representanteList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/representanteAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_repre" value="vincularepresentado">
								<input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">		
								<input type="hidden" name="alumno_repreid" value="'.$repreid.'">
								<button type="submit" href="'.APP_URL.'representanteList/" class="btn float-end btn-success btn-sm"><i class="fas fa-link me-1"></i>Vincular</button>							

								</form>').'
						</td>
					</tr>';	
			}
			return $tabla;			
		}

		# Vincular representante con alumno
		public function vincularRepresentado(){

			$alumnoid 		= $this->limpiarCadena($_POST['alumno_id']);
			$alumnorepreid 	= $this->limpiarCadena($_POST['alumno_repreid']);

			# Verificando pago #
			$datos = $this->ejecutarConsulta("SELECT * FROM sujeto_alumno WHERE alumno_id = :alumno", [':alumno' => (int)$alumnoid]);			
			if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el alumnoen el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();				
		    }
			$vinculado_reg=[
				[										
					"campo_nombre"=>"alumno_repreid",
					"campo_marcador"=>":RepreAlumno",
					"campo_valor"=>$alumnorepreid
				],

			];

			$condicion=[
				"condicion_campo"=>"alumno_id",
				"condicion_marcador"=>":Alumnoid",
				"condicion_valor"=>$alumnoid
			];			

			if($this->actualizarDatos("sujeto_alumno",$vinculado_reg,$condicion)){
				$alerta=[
					"tipo"=>"redireccionar",
					"titulo"=>"Representante vinculado",
					"texto"=>"El representante fue vinculado correctamente",
					"icono"=>"success"
				];
				
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Representante no vinculado",
					"texto"=>"No fue posible vincular el representante al alumno, por favor intente nuevamente",
					"icono"=>"success"
				];
			}
			return json_encode($alerta);
		}

		/*
		| Datos del representante para la vista de vincular alumno.
		|
		| EL IDENTIFICADOR VA COMO PARAMETRO
		|
		| Estaba concatenado en las dos posiciones. Hoy llega saneado —las
		| tres vistas que llaman aqui lo sacan de ds_id_de_url(), que exige
		| ctype_digit() y redirige si no—, asi que no era explotable. Pero la
		| seguridad de una consulta no puede depender de que ningun futuro
		| llamador se salte ese paso: el metodo tiene que ser seguro solo.
		|
		| DOS MARCADORES PARA EL MISMO VALOR
		|
		| :repre y :repre_sub llevan lo mismo. Repetir un marcador con nombre
		| unicamente funciona con prepares emulados, que es como esta hoy la
		| conexion por omision; el dia que alguien ponga EMULATE_PREPARES en
		| false —que es lo recomendable— la consulta moriria con HY093. Con
		| dos marcadores funciona en los dos modos.
		*/
		public function datosRepresentante($repreid){
			$consulta_repre = "SELECT
									repre_identificacion,
									CONCAT(repre_primernombre, ' ', repre_segundonombre, ' ', repre_apellidopaterno, ' ', repre_apellidomaterno) AS REPRESENTANTE,
									(SELECT alumno_sedeid
										FROM sujeto_alumno
										WHERE alumno_repreid = :repre_sub
										ORDER BY alumno_fechaingreso DESC
										LIMIT 1) AS SEDE
								FROM alumno_representante
								WHERE repre_id = :repre";
			$datos = $this->ejecutarConsulta($consulta_repre, [
				':repre'     => (int)$repreid,
				':repre_sub' => (int)$repreid,
			]);
			return $datos;
		}

		public function informacionSede($sedeid){		
			$consulta_datos="SELECT * FROM general_sede WHERE sede_id = :sede";
			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sedeid]);		
			return $datos;
		}

		public function actualizarEstadoFormulario(){
			$repre_id=$this->limpiarCadena($_POST['repre_id']);

			# Verificando usuario #
		    $datos=$this->ejecutarConsulta("SELECT * FROM alumno_representante WHERE repre_id = :repre", [':repre' => (int)$repre_id]);
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El representante no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();
		    }
			if($datos['repre_firmado']=='N'){
				$estadoF = 'S';
			}else{
				$estadoF = 'N';
			}
            $firmado_datos_up=[
				[
					"campo_nombre"=>"repre_firmado",
					"campo_marcador"=>":Firmado",
					"campo_valor"=> $estadoF
				]
			];
			$condicion=[
				"condicion_campo"=>"repre_id",
				"condicion_marcador"=>":Repreid",
				"condicion_valor"=>$repre_id
			];

			if($this->actualizarDatos("alumno_representante",$firmado_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Estado actualizado correctamente",
					"texto"=>"El estado del formulario fue actualizado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar el estado del formulario por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}
	}
