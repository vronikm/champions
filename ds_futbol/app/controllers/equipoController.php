<?php
	namespace app\controllers;
	use app\models\mainModel;

	class equipoController extends mainModel{

		/* «Todas las sedes» en el formulario: un equipo con niños de todas.
		   Se guarda como equipo_sedeid = 0 (migración 076). */
		const TODAS_LAS_SEDES = 'T';

		/**
		 * Sede y valor de inscripción del formulario, validados.
		 * Devuelve ['sede' => int (0 = todas), 'valor' => ?string] o un
		 * mensaje de error. «Todas las sedes» sólo la elige quien ve todas las
		 * sedes (o deja la que ya tenía el equipo): un usuario de una sede no
		 * arma equipos de las demás. Una sede concreta, de las suyas.
		 */
		private function sedeYValorDelFormulario(?int $sedeActual = null)
		{
			$sede = (string)($_POST['equipo_sedeid'] ?? '');
			if ($sede === self::TODAS_LAS_SEDES) {
				if (sedes_de_la_sesion() !== null && $sedeActual !== 0) {
					return 'Sólo quien tiene acceso a todas las sedes puede armar un equipo de todas las sedes.';
				}
				$sedeId = 0;
			} else {
				$sedeId = (int)$sede;
				if ($sedeId <= 0) {
					return 'Elija la sede del equipo.';
				}
				if ($sedeId !== $sedeActual && !sede_en_la_sesion($sedeId)) {
					return 'La sede elegida no está entre las suyas.';
				}
			}

			/* Valor: «25», «25.50» o «25,50»; vacío = sin definir. */
			$valor = trim(str_replace(',', '.', (string)($_POST['equipo_valorinscripcion'] ?? '')));
			if ($valor === '') {
				$valor = null;
			} elseif (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $valor)) {
				return 'El valor de inscripción debe ser un número, con hasta dos decimales (por ejemplo 25 o 25.50).';
			} else {
				$valor = number_format((float)$valor, 2, '.', '');
			}

			return ['sede' => $sedeId, 'valor' => $valor];
		}

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
			/* (int): en PHP 8, "" == 0 es FALSO, así que un profesor sin
			   elegir —valor vacío— pasaba esta comprobación. */
			if($equipo_nombre=="" || $equipo_categoria=="" || (int)$equipo_profesorid <= 0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}
			$sedeValor = $this->sedeYValorDelFormulario();
			if (is_string($sedeValor)) {
				return json_encode(["tipo"=>"simple", "titulo"=>"Revise los datos", "texto"=>$sedeValor, "icono"=>"warning"], JSON_UNESCAPED_UNICODE);
			}
			$equipo_sedeid = $sedeValor['sede'];			

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
					"campo_nombre"=>"equipo_valorinscripcion",
					"campo_marcador"=>":ValorInscripcion",
					"campo_valor"=>$sedeValor['valor']
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
					"url"=>APP_URL.'equipoList/'.(int)$equipo_torneoid.'/',   /* a la lista: con el id abría el modal otra vez */
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
		/*
		| CRITERIOS DE BÚSQUEDA (2026-11-25)
		|
		|   q          equipo, categoría o profesor
		|   categoria  exacta ('' = todas)
		|   sede       id; 'T' = equipos de todas las sedes; '' = cualquiera
		|   profesor   empleado_id (0 = todos)
		|   estado     'A', 'I' o '' (los dos)
		|
		| $resumen recibe: total, activos, jugadores inscritos y equipos sin
		| valor de inscripción.
		*/

		/** Lo que ofrecen los criterios: categorías, sedes y profesores de los equipos del torneo. */
		public function opcionesFiltroEquipos(int $torneo): array{
			$filas = $this->ejecutarConsulta("SELECT e.equipo_categoria, e.equipo_sedeid, s.sede_nombre, e.equipo_profesorid, emp.empleado_nombre
					FROM torneo_equipo e
					LEFT JOIN general_sede s ON s.sede_id = e.equipo_sedeid
					LEFT JOIN sujeto_empleado emp ON emp.empleado_id = e.equipo_profesorid
					WHERE e.equipo_torneoid = :t AND e.equipo_estado IN ('A','I')", [':t' => $torneo])->fetchAll();
			$dec = static fn($v) => html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$r = ['categorias' => [], 'sedes' => [], 'profesores' => []];
			/* El filtro «Sede» sólo ofrece las activas (2026-11-27). */
			$activas = array_map('intval', $this->ejecutarConsulta(
				"SELECT sede_id FROM general_sede WHERE " . sql_sede_activa('sede_id'))->fetchAll(\PDO::FETCH_COLUMN));
			foreach ($filas as $f) {
				if (trim((string)$f['equipo_categoria']) !== '') { $r['categorias'][$dec($f['equipo_categoria'])] = true; }
				if ((int)$f['equipo_sedeid'] === 0 || in_array((int)$f['equipo_sedeid'], $activas, true)) {
					$r['sedes'][(int)$f['equipo_sedeid'] === 0 ? self::TODAS_LAS_SEDES : (string)(int)$f['equipo_sedeid']] =
						(int)$f['equipo_sedeid'] === 0 ? 'Todas las sedes' : $dec($f['sede_nombre']);
				}
				if ((int)$f['equipo_profesorid'] > 0 && $f['empleado_nombre'] !== null) { $r['profesores'][(int)$f['equipo_profesorid']] = $dec($f['empleado_nombre']); }
			}
			$r['categorias'] = array_keys($r['categorias']);
			natcasesort($r['categorias']); $r['categorias'] = array_values($r['categorias']);
			asort($r['sedes']); asort($r['profesores']);
			return $r;
		}

		public function listarEquiposTorneo($equipo_torneoid, array $f = [], ?array &$resumen = null){
			$tabla="";
			$torneo = (int)$equipo_torneoid;
			$cond = ["e.equipo_torneoid = :torneo", "e.equipo_estado IN ('A','I')"];
			$par  = [':torneo' => $torneo];
			$q = trim((string)($f['q'] ?? ''));
			if ($q !== '') {
				$cond[] = "(e.equipo_nombre LIKE :q1 OR e.equipo_categoria LIKE :q2 OR emp.empleado_nombre LIKE :q3)";
				foreach ([':q1', ':q2', ':q3'] as $k) { $par[$k] = '%' . $q . '%'; }
			}
			if ((string)($f['categoria'] ?? '') !== '') {
				/* Guardada escapada (limpiarHtml) o en crudo: se comparan las dos. */
				$cond[] = "(e.equipo_categoria = :cat OR e.equipo_categoria = :catH)";
				$par[':cat'] = $f['categoria']; $par[':catH'] = htmlspecialchars($f['categoria'], ENT_QUOTES, 'UTF-8');
			}
			$sede = (string)($f['sede'] ?? '');
			if ($sede === self::TODAS_LAS_SEDES) { $cond[] = "e.equipo_sedeid = 0"; }
			elseif ((int)$sede > 0) { $cond[] = "e.equipo_sedeid = :sede"; $par[':sede'] = (int)$sede; }
			if ((int)($f['profesor'] ?? 0) > 0) { $cond[] = "e.equipo_profesorid = :prof"; $par[':prof'] = (int)$f['profesor']; }
			if (in_array($f['estado'] ?? '', ['A', 'I'], true)) { $cond[] = "e.equipo_estado = :estado"; $par[':estado'] = $f['estado']; }

			$consulta_datos="SELECT s.sede_nombre, e.equipo_sedeid, e.equipo_valorinscripcion, e.equipo_id, e.equipo_nombre, e.equipo_foto, e.equipo_categoria,
								e.equipo_profesorid, emp.empleado_nombre, COALESCE(J.n, 0) AS jugadores,
								CASE WHEN e.equipo_estado='A' THEN 'Activo'
								     WHEN e.equipo_estado='I' THEN 'Inactivo'
								     ELSE e.equipo_estado END AS ESTADO
							 FROM torneo_equipo e
							 LEFT JOIN general_sede s      ON s.sede_id = e.equipo_sedeid
							 LEFT JOIN sujeto_empleado emp ON emp.empleado_id = e.equipo_profesorid
							 LEFT JOIN (SELECT jugador_equipoid, COUNT(*) AS n FROM torneo_jugador GROUP BY jugador_equipoid) J ON J.jugador_equipoid = e.equipo_id
							 WHERE " . implode(' AND ', $cond) . "
							 ORDER BY e.equipo_categoria, e.equipo_nombre";

			$datos = $this->ejecutarConsulta($consulta_datos, $par);
			$datos = $datos->fetchAll();

			$resumen = ['total' => count($datos), 'activos' => 0, 'jugadores' => 0, 'sinValor' => 0];
			foreach ($datos as $r) {
				if ($r['ESTADO'] === 'Activo') { $resumen['activos']++; }
				$resumen['jugadores'] += (int)$r['jugadores'];
				if ($r['equipo_valorinscripcion'] === null) { $resumen['sinValor']++; }
			}
			$puedeEditar = puede('editar', 'torneoList');
			$dec = static fn($v) => html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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

				/* Sede 0: un equipo con niños de todas las sedes (migración 076). */
				$sede = (int)$rows['equipo_sedeid'] === 0
					? '<i class="fas fa-layer-group" aria-hidden="true"></i> Todas las sedes'
					: ($rows['sede_nombre'] !== null ? $h($rows['sede_nombre']) : '');
				$valor = $rows['equipo_valorinscripcion'] !== null
					? '$' . number_format((float)$rows['equipo_valorinscripcion'], 2, '.', ',')
					: '<span class="text-body-secondary">Sin definir</span>';
				$nJug = (int)$rows['jugadores'];

				/* Lo que el modal necesita para editar (decodificado; sólo para quien edita). */
				$tieneFoto = $foto !== '' && is_file($dirFotos . basename($foto));
				$datosModal = $puedeEditar ? htmlspecialchars(json_encode([
					'id' => $id, 'nombre' => $dec($rows['equipo_nombre']), 'categoria' => $dec($rows['equipo_categoria']),
					'sede' => (int)$rows['equipo_sedeid'] === 0 ? self::TODAS_LAS_SEDES : (string)(int)$rows['equipo_sedeid'],
					'profesor' => (string)(int)$rows['equipo_profesorid'],
					'valor' => $rows['equipo_valorinscripcion'] !== null ? number_format((float)$rows['equipo_valorinscripcion'], 2, '.', '') : '',
					'foto' => $tieneFoto ? APP_URL . 'app/views/imagenes/fotos/equipos/' . rawurlencode(basename($foto)) : '',
				], JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') : '';

				$tabla.='
					<tr>
						<td class="ds-ficha__principal">
							<div class="ds-ficha__con-imagen">
								'.$imagen.'
								<div>
									<div class="ds-ficha__nombre">'.$h($rows['equipo_nombre']).'</div>
									<div class="ds-ficha__meta"><i class="fas fa-tag" aria-hidden="true"></i> '.$h($rows['equipo_categoria']).'
										'.($sede !== '' ? ' · '.$sede : '').'<span class="ds-equipo__valor-meta"> · Inscripción '.$valor.'</span></div>
								</div>
							</div>
						</td>
						<td class="ds-ficha__dato" data-etiqueta="Profesor">'.$profesor.'</td>
						<td class="ds-ficha__dato eq-jugadores" data-etiqueta="Jugadores" data-order="'.$nJug.'">
							<a href="'.APP_URL.'jugadorLista/'.$torneo.'/'.$id.'/" class="badge rounded-pill text-decoration-none '.($nJug > 0 ? 'text-bg-primary' : 'text-bg-light border').'" title="Ver los jugadores">'.$nJug.'</a>
						</td>
						<td class="ds-ficha__dato ds-equipo__valor ds-col-opcional" data-etiqueta="Inscripción por alumno" data-order="'.(float)$rows['equipo_valorinscripcion'].'">'.$valor.'</td>
						<td class="ds-ficha__estado" data-order="'.$h($rows['ESTADO']).'">'.$badge.'</td>
						<td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">
							<a href="'.APP_URL.'jugadorLista/'.$torneo.'/'.$id.'/" class="btn btn-primary btn-sm ds-ficha__primario"><i class="fas fa-list me-1" aria-hidden="true"></i>Jugadores</a>
							<a href="'.APP_URL.'jugadorNew/'.$torneo.'/'.$id.'/" class="btn btn-outline-primary btn-sm" title="Asignar jugadores" aria-label="Asignar jugadores"><i class="fas fa-user-plus"></i></a>
							'.$this->siPuede('editar','torneoList','<a href="'.APP_URL.'equipoList/'.$torneo.'/'.$id.'/" class="btn btn-outline-success btn-sm eq-editar" data-equipo="'.$datosModal.'" title="Editar" aria-label="Editar el equipo '.$h($rows['equipo_nombre']).'"><i class="fas fa-pen"></i></a>').'
							'.$this->siPuede('editar','torneoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/equipoAjax.php" method="POST" autocomplete="off">
								<input type="hidden" name="modulo_equipo" value="actualizarestado">
								<input type="hidden" name="equipo_id" value="'.$id.'">
								<button type="submit" class="btn btn-outline-secondary btn-sm" title="'.$texto.'" aria-label="'.$texto.'"><i class="fas '.$icono.'"></i></button>
							</form>').'
							'.$this->siPuede('eliminar','torneoList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/equipoAjax.php" method="POST" autocomplete="off">
								<input type="hidden" name="modulo_equipo" value="eliminar">
								<input type="hidden" name="equipo_id" value="'.$id.'">
								'.ds_boton_eliminar('el equipo '.$rows['equipo_nombre']).'
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

		/* LEFT JOIN con la sede: un equipo de «todas las sedes» (equipo_sedeid
		   = 0) no tiene fila en general_sede y, con el JOIN de antes, no se
		   encontraba. sede_id sigue siendo el del equipo (0 = todas). */
		public function BuscarEquipo($equipo_id){
			$consulta_datos=("SELECT e.equipo_id, e.equipo_profesorid, e.equipo_nombre, e.equipo_torneoid, e.equipo_sedeid AS sede_id,
								COALESCE(s.sede_nombre, 'Todas las sedes') AS sede_nombre, e.equipo_categoria, e.equipo_foto, e.equipo_valorinscripcion,
								CASE WHEN e.equipo_estado = 'A' THEN 'Activo'
									 WHEN e.equipo_estado = 'I' THEN 'Inactivo'
									 ELSE e.equipo_estado
								END AS ESTADO
							 FROM torneo_equipo e
							 LEFT JOIN general_sede s ON s.sede_id = e.equipo_sedeid
							 WHERE e.equipo_estado IN ('A','I')
							 	AND e.equipo_id = :equipo");

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
			/* (int): en PHP 8, "" == 0 es FALSO, así que un profesor sin
			   elegir —valor vacío— pasaba esta comprobación. */
			if($equipo_nombre=="" || $equipo_categoria=="" || (int)$equipo_profesorid <= 0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}
			$sedeValor = $this->sedeYValorDelFormulario((int)$equipo['equipo_sedeid']);
			if (is_string($sedeValor)) {
				return json_encode(["tipo"=>"simple", "titulo"=>"Revise los datos", "texto"=>$sedeValor, "icono"=>"warning"], JSON_UNESCAPED_UNICODE);
			}
			$equipo_sedeid = $sedeValor['sede'];

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
					"campo_nombre"=>"equipo_valorinscripcion",
					"campo_marcador"=>":ValorInscripcion",
					"campo_valor"=>$sedeValor['valor']
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
					"url"=>APP_URL.'equipoList/'.(int)$equipo_torneoid.'/',   /* a la lista: con el id abría el modal otra vez */
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
					"url"=>APP_URL.'equipoList/'.(int)$equipo_torneoid.'/',   /* a la lista: con el id abría el modal otra vez */
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
		
		/**
		 * Las sedes para el formulario: las de la sesión y, para quien ve todas
		 * (o si el equipo ya es de todas), «Todas las sedes». $sedeid null = alta
		 * sin elegir; 0 = todas.
		 */
		public function listarOptionSede($sedeid){
			$option="";
			$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

			if (sedes_de_la_sesion() === null || $sedeid === 0) {
				$option .= '<option value="' . self::TODAS_LAS_SEDES . '"' . ($sedeid === 0 ? ' selected' : '') . '>Todas las sedes</option>';
			}

			/* La sede que ya tiene el equipo se ofrece aunque no sea de las suyas:
			   si no, al editar caería en «Seleccione» sin que nadie lo pidiera. */
			$datos = $this->ejecutarConsulta(
				"SELECT sede_id, sede_nombre FROM general_sede
				  WHERE (" . sql_sedes_de_la_sesion('sede_id') . " AND " . sql_sede_activa('sede_id') . ") OR sede_id = :actual
				  ORDER BY sede_nombre", [':actual' => (int)$sedeid])->fetchAll();
			foreach($datos as $rows){
				$sel = ($sedeid !== null && (int)$sedeid === (int)$rows['sede_id']) ? ' selected' : '';
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