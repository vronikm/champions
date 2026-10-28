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
		/**
		 * Los jugadores del equipo (jugadorLista), con lo pagado de la
		 * inscripción del torneo (rubro Campeonato) y el correo del
		 * representante, para quitar a quien se agregó por error.
		 */
		public function jugadoresDelEquipo(int $equipo_id): array
		{
			return $this->ejecutarConsulta(
				"SELECT j.jugador_alumnoid, a.alumno_identificacion, YEAR(a.alumno_fechanacimiento) AS anio,
				        CONCAT_WS(' ', a.alumno_primernombre, a.alumno_segundonombre, a.alumno_apellidopaterno, a.alumno_apellidomaterno) AS nombre,
				        COALESCE(P.catalogo_descripcion, 'Sin definir') AS posicion, COALESCE(T.catalogo_descripcion, 'Sin definir') AS tipo,
				        CONCAT_WS(' ', r.repre_primernombre, r.repre_apellidopaterno) AS representante, r.repre_correo,
				        pg.pago_id, pg.pago_recibo, pg.pago_valor
				   FROM torneo_jugador j
				   JOIN sujeto_alumno a  ON a.alumno_id = j.jugador_alumnoid
				   JOIN torneo_equipo e  ON e.equipo_id = j.jugador_equipoid
				   LEFT JOIN general_tabla_catalogo P ON P.catalogo_valor = j.jugador_posicioncod
				   LEFT JOIN general_tabla_catalogo T ON T.catalogo_valor = j.jugador_tipocod
				   LEFT JOIN alumno_representante r ON r.repre_id = a.alumno_repreid
				   LEFT JOIN alumno_pago pg ON pg.pago_id = (SELECT p2.pago_id FROM alumno_pago p2
				                                              WHERE p2.pago_alumnoid = a.alumno_id AND p2.pago_rubroid = 'RPC'
				                                                AND p2.pago_campeonatoid = e.equipo_torneoid AND p2.pago_estado <> 'E'
				                                              ORDER BY p2.pago_id DESC LIMIT 1)
				  WHERE j.jugador_equipoid = :equipo
				  ORDER BY a.alumno_apellidopaterno, a.alumno_primernombre",
				[':equipo' => $equipo_id]
			)->fetchAll(\PDO::FETCH_ASSOC);
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

		/**
		 * Quitar a un jugador del equipo. Op «eliminar» (permiso eliminar).
		 *
		 * Sólo de ESTE equipo: antes se borraba por alumno, y quitarlo de un
		 * equipo lo sacaba de todos los suyos.
		 *
		 * Con anular_recibo = S (lo agregaron por error), además se anula el
		 * pago de la inscripción al torneo —como «eliminar» en Pagos: estado E—
		 * y se avisa por correo al representante de que el recibo ya no vale.
		 * Para anular hace falta el permiso de eliminar en Pagos. Sin marcar
		 * (lo cambian de equipo), el pago del torneo se mantiene.
		 */
		public function eliminarJugador()
		{
			$error = static fn($t) => json_encode(['tipo' => 'simple', 'titulo' => 'No se quitó', 'texto' => $t, 'icono' => 'error'], JSON_UNESCAPED_UNICODE);
			$alumnoId = (int)($_POST['jugador_alumnoid'] ?? 0);
			$equipoId = (int)($_POST['equipo_id'] ?? 0);
			$anular   = ($_POST['anular_recibo'] ?? '') === 'S';
			if ($alumnoId <= 0 || $equipoId <= 0) {
				return $error('Falta el jugador o el equipo.');
			}
			if ($anular && !puede('eliminar', 'pagosList')) {
				return $error('Su rol no puede anular pagos. Quite al jugador sin anular el recibo y pida a quien gestiona Pagos que lo anule.');
			}

			$fila = $this->ejecutarConsulta(
				"SELECT e.equipo_nombre, e.equipo_categoria, e.equipo_torneoid, t.torneo_nombre,
				        CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno) AS alumno,
				        CONCAT_WS(' ', r.repre_primernombre, r.repre_apellidopaterno) AS representante, r.repre_correo
				   FROM torneo_jugador j
				   JOIN torneo_equipo e ON e.equipo_id = j.jugador_equipoid
				   LEFT JOIN torneo_torneo t ON t.torneo_id = e.equipo_torneoid
				   JOIN sujeto_alumno a ON a.alumno_id = j.jugador_alumnoid
				   LEFT JOIN alumno_representante r ON r.repre_id = a.alumno_repreid
				  WHERE j.jugador_alumnoid = :a AND j.jugador_equipoid = :e",
				[':a' => $alumnoId, ':e' => $equipoId])->fetch(\PDO::FETCH_ASSOC);
			if (!$fila) {
				return $error('El jugador ya no está en este equipo.');
			}

			$pago = null;
			$pdo = $this->conectar();
			try {
				$pdo->beginTransaction();
				$pdo->prepare("DELETE FROM torneo_jugador WHERE jugador_alumnoid = :a AND jugador_equipoid = :e")
				    ->execute([':a' => $alumnoId, ':e' => $equipoId]);
				if ($anular) {
					$q = $pdo->prepare("SELECT pago_id, pago_recibo, pago_valor, pago_fecharegistro FROM alumno_pago
					                     WHERE pago_alumnoid = :a AND pago_rubroid = 'RPC' AND pago_campeonatoid = :t AND pago_estado <> 'E'
					                     ORDER BY pago_id DESC LIMIT 1 FOR UPDATE");
					$q->execute([':a' => $alumnoId, ':t' => (int)$fila['equipo_torneoid']]);
					$pago = $q->fetch(\PDO::FETCH_ASSOC) ?: null;
					if ($pago) {
						$pdo->prepare("UPDATE alumno_pago SET pago_estado = 'E' WHERE pago_id = :p")->execute([':p' => $pago['pago_id']]);
					}
				}
				$pdo->commit();
			} catch (\Throwable $e) {
				if ($pdo->inTransaction()) { $pdo->rollBack(); }
				error_log('[eliminarJugador] ' . $e->getMessage());
				return $error('No fue posible quitar al jugador. Inténtelo de nuevo.');
			}

			$d = static fn($v) => html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$res = ['tipo' => 'jugador_eliminado', 'icono' => 'success', 'alumno' => $d($fila['alumno']),
			        'anulado' => (bool)$pago, 'recibo' => $pago['pago_recibo'] ?? '', 'correo' => null, 'destino' => ''];

			/* El correo aclaratorio, sólo si se anuló un recibo. */
			if ($pago) {
				$correo = trim((string)$fila['repre_correo']);
				$res['destino'] = $correo;
				if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
					$res['correo'] = 'sin_correo';
				} else {
					$escuela = ds_nombre_organizacion() ?: DS_HUB_NAME;
					$asunto = 'Recibo ' . $pago['pago_recibo'] . ' anulado — ' . $escuela;
					$mensaje = 'Estimado/a ' . $d($fila['representante']) . ":\n\n"
					         . 'Le informamos que ' . $d($fila['alumno']) . ' fue retirado/a del equipo ' . $d($fila['equipo_nombre'])
					         . ' (' . $d($fila['equipo_categoria']) . ')' . ($fila['torneo_nombre'] ? ' del torneo ' . $d($fila['torneo_nombre']) : '')
					         . " porque se registró por error.\n\n"
					         . 'Por ese motivo, el recibo N.º ' . $pago['pago_recibo'] . ' por $' . number_format((float)$pago['pago_valor'], 2, '.', '')
					         . ' del ' . date('d/m/Y', strtotime($pago['pago_fecharegistro'])) . " queda ANULADO y ya no tiene validez.\n\n"
					         . "Si ese valor ya fue cancelado, la escuela se comunicará con usted para su devolución o para aplicarlo donde corresponda.\n\n"
					         . "Disculpe las molestias.\n\n" . $escuela;
					try {
						(new facturasController())->enviarCorreoTexto($correo, $asunto, $mensaje);
						$res['correo'] = 'enviado';
					} catch (\Throwable $e) {
						error_log('[eliminarJugador] correo de anulación: ' . $e->getMessage());
						$res['correo'] = 'fallo';
					}
				}
			}
			return json_encode($res, JSON_UNESCAPED_UNICODE);
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
			/* LEFT JOIN con la sede: un equipo de «todas las sedes»
			   (equipo_sedeid = 0, migración 076) no tiene fila en general_sede
			   y, con el JOIN de antes, sus jugadores no se podían ver. */
			$consulta_datos=("SELECT equipo_id, equipo_nombre, equipo_torneoid, equipo_categoria, equipo_sedeid,
								COALESCE(sede_nombre, 'Todas las sedes') AS sede_nombre, equipo_foto, equipo_valorinscripcion, torneo_nombre,
								empleado_nombre, CASE WHEN equipo_estado = 'A' THEN 'Activo'
													  WHEN equipo_estado = 'I' THEN 'Inactivo'
													  ELSE equipo_estado END AS ESTADO
							 FROM torneo_equipo
							 INNER JOIN sujeto_empleado ON equipo_profesorid = empleado_id
							 LEFT JOIN general_sede ON sede_id = equipo_sedeid
							 LEFT JOIN torneo_torneo ON torneo_id = equipo_torneoid
							 WHERE equipo_estado IN ('A','I')
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
		/**
		 * Los jugadores para el PDF (jugadorListaPDF), por apellido, con la
		 * posición y el tipo: «Sin definir» donde no se eligió (LEFT JOIN;
		 * con el INNER de antes, ese jugador no salía en la lista).
		 */
		public function listaJugadorPDF($equipo_id){
			$consulta_datos = "SELECT j.jugador_alumnoid,
			                          CONCAT_WS(' ', a.alumno_primernombre, a.alumno_segundonombre) AS NOMBRES,
			                          CONCAT_WS(' ', a.alumno_apellidopaterno, a.alumno_apellidomaterno) AS APELLIDOS,
			                          a.alumno_fechanacimiento AS FECHANAC,
			                          a.alumno_identificacion AS CEDULA,
			                          COALESCE(P.catalogo_descripcion, 'Sin definir') AS POSICION,
			                          COALESCE(T.catalogo_descripcion, 'Sin definir') AS TIPO,
			                          CASE WHEN a.alumno_numcamiseta = 0 THEN NULL ELSE a.alumno_numcamiseta END AS NUMCAMISETA
			                     FROM torneo_jugador j
			                     JOIN sujeto_alumno a ON a.alumno_id = j.jugador_alumnoid
			                     LEFT JOIN general_tabla_catalogo P ON P.catalogo_valor = j.jugador_posicioncod
			                     LEFT JOIN general_tabla_catalogo T ON T.catalogo_valor = j.jugador_tipocod
			                    WHERE j.jugador_equipoid = :equipo
			                    ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno, a.alumno_primernombre";
			return $this->ejecutarConsulta($consulta_datos, [':equipo' => (int)$equipo_id]);
		}
		/*======================================================================
		  ASIGNAR JUGADORES (jugadorNew): búsqueda y «Pagar y agregar»
		  ======================================================================*/

		/* Formas de pago que son un cobro de verdad: «Justificado» y «No
		   aplica» no se ofrecen al pagar una inscripción. */
		const FORMAS_NO_COBRO = ['FJU', 'FNA'];

		/**
		 * Los años del filtro «Año»: «2014», «2014, 2015» o rangos «2014-2016».
		 * Sólo años de cuatro cifras razonables; como mucho 20.
		 */
		public static function aniosDelFiltro(string $texto): array
		{
			$anios = [];
			foreach (preg_split('/[,;\s]+/', trim($texto)) ?: [] as $parte) {
				if (preg_match('/^(\d{4})-(\d{4})$/', $parte, $m)) {
					[$a, $b] = [(int)$m[1], (int)$m[2]];
					if ($a > $b) { [$a, $b] = [$b, $a]; }
					for ($y = $a; $y <= $b && count($anios) < 20; $y++) { $anios[$y] = $y; }
				} elseif (preg_match('/^\d{4}$/', $parte)) {
					$anios[(int)$parte] = (int)$parte;
				}
			}
			$anios = array_filter($anios, static fn($y) => $y >= 1990 && $y <= (int)date('Y'));
			sort($anios);
			return array_slice($anios, 0, 20);
		}

		/**
		 * Los alumnos que se pueden agregar al equipo: ACTIVOS, de las sedes de
		 * la sesión y que no juegan ya en otro equipo de la misma categoría de
		 * este torneo. Con lo que haya pagado de la inscripción del torneo
		 * (rubro Campeonato) y el correo del representante, para el recibo.
		 *
		 * $f: identificacion, apellido, nombre (prefijos), anios [int], sede (0 = todas).
		 */
		public function buscarAlumnosParaEquipo(array $f, array $equipo): array
		{
			$donde = ["a.alumno_estado = 'A'", sql_sedes_de_la_sesion('a.alumno_sedeid')];
			$par = [':torneo' => (int)$equipo['equipo_torneoid'], ':categoria' => $equipo['equipo_categoria'], ':torneo2' => (int)$equipo['equipo_torneoid']];
			foreach (['identificacion' => 'a.alumno_identificacion', 'apellido' => 'a.alumno_apellidopaterno', 'nombre' => 'a.alumno_primernombre'] as $clave => $col) {
				if (trim((string)($f[$clave] ?? '')) !== '') {
					$donde[] = "$col LIKE :$clave";
					$par[":$clave"] = trim($f[$clave]) . '%';
				}
			}
			if (!empty($f['anios'])) {
				$donde[] = 'YEAR(a.alumno_fechanacimiento) IN (' . implode(',', array_map('intval', $f['anios'])) . ')';
			}
			if ((int)($f['sede'] ?? 0) > 0) {
				$donde[] = 'a.alumno_sedeid = :sede';
				$par[':sede'] = (int)$f['sede'];
			}
			return $this->ejecutarConsulta(
				"SELECT a.alumno_id, a.alumno_identificacion, a.alumno_fechanacimiento, s.sede_nombre,
				        CONCAT_WS(' ', a.alumno_primernombre, a.alumno_segundonombre, a.alumno_apellidopaterno, a.alumno_apellidomaterno) AS nombre,
				        CONCAT_WS(' ', r.repre_primernombre, r.repre_apellidopaterno) AS representante, r.repre_correo,
				        (SELECT p.pago_id FROM alumno_pago p
				          WHERE p.pago_alumnoid = a.alumno_id AND p.pago_rubroid = 'RPC' AND p.pago_campeonatoid = :torneo2 AND p.pago_estado <> 'E'
				          ORDER BY p.pago_id DESC LIMIT 1) AS pago_id,
				        (SELECT p.pago_recibo FROM alumno_pago p
				          WHERE p.pago_alumnoid = a.alumno_id AND p.pago_rubroid = 'RPC' AND p.pago_campeonatoid = :torneo2b AND p.pago_estado <> 'E'
				          ORDER BY p.pago_id DESC LIMIT 1) AS pago_recibo
				   FROM sujeto_alumno a
				   LEFT JOIN general_sede s ON s.sede_id = a.alumno_sedeid
				   LEFT JOIN alumno_representante r ON r.repre_id = a.alumno_repreid
				  WHERE " . implode(' AND ', $donde) . "
				    AND a.alumno_id NOT IN (SELECT j.jugador_alumnoid FROM torneo_jugador j
				                              JOIN torneo_equipo e ON e.equipo_id = j.jugador_equipoid
				                             WHERE e.equipo_torneoid = :torneo AND e.equipo_categoria = :categoria AND e.equipo_estado <> 'E')
				  ORDER BY a.alumno_fechanacimiento DESC, a.alumno_apellidopaterno, a.alumno_primernombre
				  LIMIT 300",
				$par + [':torneo2b' => (int)$equipo['equipo_torneoid']]
			)->fetchAll(\PDO::FETCH_ASSOC);
		}

		/** Las sedes de la sesión (id => nombre), para el filtro. */
		public function sedesDeLaBusqueda(): array
		{
			return $this->ejecutarConsulta(
				"SELECT sede_id, sede_nombre FROM general_sede WHERE " . sql_sedes_de_la_sesion('sede_id') . " ORDER BY sede_nombre"
			)->fetchAll(\PDO::FETCH_KEY_PAIR);
		}

		/** ¿El código es del catálogo indicado? Vacío vale: es «Sin definir». */
		private function codigoDeCatalogo(string $tabla, string $codigo): bool
		{
			if ($codigo === '') { return true; }
			return (int)$this->ejecutarConsulta(
				"SELECT COUNT(*) FROM general_tabla_catalogo C JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid
				  WHERE T.tabla_nombre = :t AND C.catalogo_valor = :v", [':t' => $tabla, ':v' => $codigo])->fetchColumn() > 0;
		}

		/** Opciones de forma de pago que son un cobro (sin Justificado ni No aplica). */
		public function opcionesFormaPagoCobro(): array
		{
			return $this->ejecutarConsulta(
				"SELECT catalogo_valor, catalogo_descripcion FROM general_tabla_catalogo
				  WHERE catalogo_tablaid = 6 AND catalogo_estado = 'A'
				    AND catalogo_valor NOT IN ('" . implode("','", self::FORMAS_NO_COBRO) . "')
				  ORDER BY catalogo_descripcion"
			)->fetchAll(\PDO::FETCH_KEY_PAIR);
		}

		/**
		 * «Pagar y agregar»: registra el pago de la inscripción al torneo
		 * (rubro Campeonato, ligado al torneo, sin saldo) y agrega al alumno
		 * al equipo, todo o nada. Op «registrar_pago_inscripcion». El recibo
		 * por correo lo manda después la pantalla con pagosReciboEnvio, el
		 * mismo envío que el botón «Enviar por correo» del recibo.
		 */
		public function pagarInscripcionControlador()
		{
			$error = static fn($t) => json_encode(['tipo' => 'simple', 'titulo' => 'No se registró', 'texto' => $t, 'icono' => 'warning'], JSON_UNESCAPED_UNICODE);
			if (!puede('crear', 'pagosList')) {
				return $error('Su rol no puede registrar pagos. Use «Agregar» y registre el pago en Pagos.');
			}

			$equipo = $this->BuscarEquipo((int)($_POST['equipo_id'] ?? 0))->fetch(\PDO::FETCH_ASSOC);
			if (!$equipo) { return $error('El equipo no existe.'); }
			$alumnoId = (int)($_POST['alumno_id'] ?? 0);
			$posicion = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($_POST['posicion'] ?? ''))), 0, 3);
			$tipo     = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string)($_POST['tipo'] ?? ''))), 0, 3);
			$forma    = (string)($_POST['formapago'] ?? '');
			$fecha    = (string)($_POST['fecha'] ?? '');
			$valor    = trim(str_replace(',', '.', (string)($_POST['valor'] ?? '')));

			/* Posición y tipo pueden quedar «Sin definir» (vacío); si vienen, del catálogo. */
			if (!$this->codigoDeCatalogo('posicion_juego', $posicion) || !$this->codigoDeCatalogo('tipo_participacion', $tipo)) {
				return $error('La posición o el tipo no son válidos.');
			}
			if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $valor) || (float)$valor <= 0) {
				return $error('Escriba el valor de la inscripción (por ejemplo 25 o 25.50).');
			}
			if (!isset($this->opcionesFormaPagoCobro()[$forma])) { return $error('Elija la forma de pago.'); }
			$d = \DateTime::createFromFormat('Y-m-d', $fecha);
			if (!$d || $d->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d')) { return $error('La fecha del pago no es válida o es posterior a hoy.'); }

			/* El alumno: activo, de las sedes de la sesión. */
			$alumno = $this->ejecutarConsulta(
				"SELECT alumno_id, alumno_sedeid, CONCAT_WS(' ', alumno_primernombre, alumno_apellidopaterno) AS nombre
				   FROM sujeto_alumno WHERE alumno_id = :a AND alumno_estado = 'A' AND " . sql_sedes_de_la_sesion('alumno_sedeid'),
				[':a' => $alumnoId])->fetch(\PDO::FETCH_ASSOC);
			if (!$alumno) { return $error('El alumno no existe, no está activo o no es de sus sedes.'); }

			$torneo = (int)$equipo['equipo_torneoid'];
			$concepto = mb_substr('Inscripción equipo ' . html_entity_decode($equipo['equipo_nombre'], ENT_QUOTES, 'UTF-8')
			          . ' (' . html_entity_decode($equipo['equipo_categoria'], ENT_QUOTES, 'UTF-8') . ')', 0, 300);

			$pdo = $this->conectar();
			try {
				$pdo->beginTransaction();

				/* Ya juega en un equipo de esta categoría del torneo, o ya pagó: nada. */
				$ya = $pdo->prepare("SELECT COUNT(*) FROM torneo_jugador j JOIN torneo_equipo e ON e.equipo_id = j.jugador_equipoid
				                      WHERE j.jugador_alumnoid = :a AND e.equipo_torneoid = :t AND e.equipo_categoria = :c AND e.equipo_estado <> 'E'");
				$ya->execute([':a' => $alumnoId, ':t' => $torneo, ':c' => $equipo['equipo_categoria']]);
				if ((int)$ya->fetchColumn() > 0) { $pdo->rollBack(); return $error('El alumno ya está en un equipo de esta categoría del torneo.'); }
				$pagado = $pdo->prepare("SELECT pago_recibo FROM alumno_pago WHERE pago_alumnoid = :a AND pago_rubroid = 'RPC' AND pago_campeonatoid = :t AND pago_estado <> 'E' LIMIT 1");
				$pagado->execute([':a' => $alumnoId, ':t' => $torneo]);
				if ($recibo = $pagado->fetchColumn()) {
					$pdo->rollBack();
					return $error('Este alumno ya pagó la inscripción del torneo (recibo ' . $recibo . '). Use «Agregar».');
				}

				/* El número de recibo: el contador de la escuela, bloqueado
				   hasta terminar (dos cobros a la vez no repiten número), con
				   el mismo formato que Pagos (registrarPagoCampeonato). */
				$num = (int)$pdo->query("SELECT escuela_recibo FROM general_escuela WHERE escuela_id = 1 FOR UPDATE")->fetchColumn() + 1;
				$pago_recibo = strrev(date('s') . date('y') . date('i') . date('m') . date('H') . date('d')) . $num;

				$pdo->prepare("INSERT INTO alumno_pago (pago_rubroid, pago_formapagoid, pago_campeonatoid, pago_alumnoid, pago_sedeid, pago_valor, pago_saldo,
				                 pago_concepto, pago_fecha, pago_fecharegistro, pago_periodo, pago_recibo, pago_estado, pago_archivo)
				               VALUES ('RPC', :forma, :torneo, :alumno, :sede, :valor, 0, :concepto, :fecha, :fecha2, '', :recibo, 'C', '')")
				    ->execute([':forma' => $forma, ':torneo' => $torneo, ':alumno' => $alumnoId, ':sede' => (int)$alumno['alumno_sedeid'],
				               ':valor' => $valor, ':concepto' => $concepto, ':fecha' => $fecha, ':fecha2' => $fecha, ':recibo' => $pago_recibo]);
				$pagoId = (int)$pdo->lastInsertId();
				$pdo->prepare("UPDATE general_escuela SET escuela_recibo = :n WHERE escuela_id = 1")->execute([':n' => $num]);
				$pdo->prepare("INSERT INTO torneo_jugador (jugador_alumnoid, jugador_equipoid, jugador_posicioncod, jugador_tipocod) VALUES (:a, :e, :p, :t)")
				    ->execute([':a' => $alumnoId, ':e' => (int)$equipo['equipo_id'], ':p' => $posicion, ':t' => $tipo]);

				$pdo->commit();
			} catch (\Throwable $e) {
				if ($pdo->inTransaction()) { $pdo->rollBack(); }
				error_log('[pagarInscripcion] ' . $e->getMessage());
				return $error('No se pudo registrar el pago. Inténtelo de nuevo.');
			}

			return json_encode([
				'tipo'    => 'pago_inscripcion',
				'icono'   => 'success',
				'pago_id' => $pagoId,
				'recibo'  => $pago_recibo,
				'valor'   => number_format((float)$valor, 2, '.', ''),
				'alumno'  => html_entity_decode($alumno['nombre'], ENT_QUOTES, 'UTF-8'),
			], JSON_UNESCAPED_UNICODE);
		}
    }
