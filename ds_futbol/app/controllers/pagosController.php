<?php

	namespace app\controllers;
	use app\models\mainModel;

	class pagosController extends mainModel{
		/*
		| Busca alumnos para la pantalla de pagos.
		|
		| Los criterios van como parametros ligados. Antes se concatenaban, y
		| aunque la comilla llegaba escapada por limpiarCadena() —lo que de
		| rebote impedia la inyeccion—, ese mismo escape rompia las busquedas
		| legitimas: un apellido como O'Brien viajaba convertido en
		| O&#039;Brien y no coincidia con nada.
		|
		| Ahora el valor viaja tal cual lo escribio el usuario y es PDO quien
		| lo separa de la consulta.
		*/
		/*----------  Pensiones que debe desde su último pago de pensión  ----------*/
		/*
		| La MISMA regla que pensionesPendientes(): cada mes cumplido desde la
		| fecha del último pago de pensión, hasta el último día del mes en
		| curso, es una pensión adeudada. Al sumar meses, el día se recorta al
		| final del mes, como DATE_ADD de MySQL (31 de enero + 1 mes = 28 de
		| febrero); el +1 month de PHP lo pasaría a marzo.
		*/
		private function mesesPensionAdeudados(?string $ultimoPago): int{
			if (!$ultimoPago || str_starts_with($ultimoPago, '0000')) { return 0; }
			$base  = new \DateTime($ultimoPago);
			$tope  = new \DateTime(date('Y-m-t'));
			$meses = 0;
			for ($m = 1; $m <= 120; $m++) {
				$y = (int)$base->format('Y');
				$mes = (int)$base->format('n') + $m;
				$y += intdiv($mes - 1, 12);
				$mes = ($mes - 1) % 12 + 1;
				/* date('t') y no cal_days_in_month(): ésta es de la extensión
				   calendar, que no todos los hosting traen. */
				$dia = min((int)$base->format('j'), (int)date('t', mktime(0, 0, 0, $mes, 1, $y)));
				if (new \DateTime(sprintf('%04d-%02d-%02d', $y, $mes, $dia)) > $tope) { break; }
				$meses++;
			}
			return $meses;
		}

		/*----------  Listado de pagos (pagosList)  ----------*/
		/*
		| Una fila por alumno con lo que se necesita para cobrar: cuándo pagó
		| la última pensión, cuánto debe y cómo asiste. Marcado de fichas
		| (.ds-crud): tabla en escritorio y ficha por debajo de 992 px.
		|
		| UNA consulta para todo el listado. Antes, por cada alumno, se hacían
		| cuatro o cinco (descuento, pagos, estado, pensiones): con 223 activos,
		| un millar de consultas por carga.
		|
		| VALOR ADEUDADO = saldo de los pagos pendientes (estado P, cualquier
		| rubro) + pensiones no pagadas desde la última (mesesPensionAdeudados)
		| por el valor de la pensión: el descuento activo (DDS) si lo tiene, si
		| no la de su sede. Las pensiones sin pagar sólo corren para alumnos
		| ACTIVOS: un inactivo no entrena, y contarle los meses desde que dejó
		| de venir inflaba la deuda con meses que nadie va a cobrar.
		|
		| ASISTENCIA = (presentes + atrasos) / días marcados, la fórmula de
		| Insights (el justificado cuenta como inasistencia), sobre todo lo
		| registrado: la asistencia no se registra desde hace meses y un
		| periodo corto dejaría a todos en blanco.
		|
		| $estado: A (por defecto), I o T. La sede se acota a la del usuario.
		*/
		public function listarAlumnosPagos($identificacion, $apellidopaterno, $primernombre, $estado, $sede){
			$condiciones = [];
			$parametros  = [];
			$busqueda    = [];

			if ($identificacion != "") {
				$busqueda[] = "A.alumno_identificacion LIKE :identificacion";
				$parametros[':identificacion'] = $identificacion.'%';
			}
			if ($primernombre != "") {
				$busqueda[] = "A.alumno_primernombre LIKE :primernombre";
				$parametros[':primernombre'] = $primernombre.'%';
			}
			if ($apellidopaterno != "") {
				$busqueda[] = "A.alumno_apellidopaterno LIKE :apellidopaterno";
				$parametros[':apellidopaterno'] = $apellidopaterno.'%';
			}
			if ($busqueda) {
				$condiciones[] = "(" . implode(" OR ", $busqueda) . ")";
			}
			$condiciones[] = $this->condicionEstadoAlumno('A.alumno_estado', $estado);
			$condiciones[] = $this->condicionSede('A.alumno_sedeid', $sede, $parametros);

			$filas = $this->ejecutarConsulta("SELECT A.alumno_id, A.alumno_identificacion, A.alumno_primernombre, A.alumno_segundonombre,
						A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_estado, A.alumno_fechaingreso,
						TIMESTAMPDIFF(YEAR, A.alumno_fechanacimiento, CURDATE()) AS edad,
						S.sede_nombre, S.sede_pension, D.descuento_valor,
						UP.ultima, UP.periodo, COALESCE(PP.saldo, 0) AS saldo,
						COALESCE(AS1.dias, 0) AS dias, COALESCE(AS1.asistidos, 0) AS asistidos
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
					LEFT JOIN alumno_pago_descuento D ON D.descuento_alumnoid = A.alumno_id
					       AND D.descuento_estado = 'S' AND D.descuento_rubroid = 'DDS'
					LEFT JOIN (SELECT pago_alumnoid, MAX(pago_fecha) AS ultima,
					                  SUBSTRING_INDEX(GROUP_CONCAT(pago_periodo ORDER BY pago_fecha DESC, pago_id DESC SEPARATOR '|'), '|', 1) AS periodo
					             FROM alumno_pago WHERE pago_rubroid = 'RPE' AND pago_estado <> 'E'
					            GROUP BY pago_alumnoid) UP ON UP.pago_alumnoid = A.alumno_id
					LEFT JOIN (SELECT pago_alumnoid, SUM(pago_saldo) AS saldo
					             FROM alumno_pago WHERE pago_estado = 'P'
					            GROUP BY pago_alumnoid) PP ON PP.pago_alumnoid = A.alumno_id
					LEFT JOIN (SELECT dia_alumnoid, COUNT(*) AS dias, SUM(dia_marca IN ('P','A')) AS asistidos
					             FROM insights_v_asistencia_dia GROUP BY dia_alumnoid) AS1 ON AS1.dia_alumnoid = A.alumno_id
					WHERE " . implode(" AND ", $condiciones) . "
					ORDER BY A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_primernombre", $parametros)->fetchAll();

			$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
			$junta = static fn(...$p) => preg_replace('/\s+/', ' ', trim(implode(' ', $p)));
			$dinero = static fn(float $v) => '$' . number_format($v, 2, '.', ',');

			$this->totalAdeudado = 0.0;
			$tabla = "";
			foreach ($filas as $al) {
				$id      = (int)$al['alumno_id'];
				$activo  = $al['alumno_estado'] === 'A';
				$nombre  = $junta($al['alumno_primernombre'], $al['alumno_segundonombre'], $al['alumno_apellidopaterno'], $al['alumno_apellidomaterno']);
				$orden   = $junta($al['alumno_apellidopaterno'], $al['alumno_apellidomaterno'], $al['alumno_primernombre']);
				$inicial = mb_strtoupper(mb_substr(html_entity_decode((string)$al['alumno_primernombre']), 0, 1) . mb_substr(html_entity_decode((string)$al['alumno_apellidopaterno']), 0, 1));

				/* Lo que debe. */
				$pension = $al['descuento_valor'] !== null ? (float)$al['descuento_valor'] : (float)$al['sede_pension'];
				$meses   = $activo ? $this->mesesPensionAdeudados($al['ultima']) : 0;
				$deuda   = round((float)$al['saldo'] + $meses * $pension, 2);
				$this->totalAdeudado += $deuda;
				$detalle = [];
				if ($meses > 0) { $detalle[] = $meses . ($meses === 1 ? ' pensión' : ' pensiones'); }
				if ((float)$al['saldo'] > 0) { $detalle[] = 'saldo ' . $dinero((float)$al['saldo']); }
				$celdaDeuda = $deuda > 0
					? '<span class="badge text-bg-danger ds-pagos__deuda">' . $dinero($deuda) . '</span>'
					  . '<span class="d-block small text-body-secondary">' . implode(' + ', $detalle) . '</span>'
					: '<span class="badge text-bg-success">Al día</span>';

				/* Fecha de ingreso, y cuánto lleva en la escuela. */
				$fing = (string)$al['alumno_fechaingreso'];
				$ingreso = ($fing !== '' && !str_starts_with($fing, '0000')) ? $fing : '';
				$antiguedad = '';
				if ($ingreso !== '') {
					$dif = (new \DateTime($ingreso))->diff(new \DateTime('today'));
					$antiguedad = $dif->invert ? '' : ($dif->y > 0 ? $dif->y . ($dif->y === 1 ? ' año' : ' años') : $dif->m . ($dif->m === 1 ? ' mes' : ' meses'));
				}
				$celdaIngreso = $ingreso !== ''
					? date('d/m/Y', strtotime($ingreso)) . ($antiguedad !== '' ? '<span class="d-block small text-body-secondary">' . $antiguedad . '</span>' : '')
					: '<span class="text-body-secondary">—</span>';

				/* Último pago de pensión: la fecha y el mes que cubrió. */
				$ultima = $al['ultima'] && !str_starts_with((string)$al['ultima'], '0000') ? (string)$al['ultima'] : '';
				$celdaUltima = $ultima !== ''
					? date('d/m/Y', strtotime($ultima)) . (trim((string)$al['periodo']) !== '' ? '<span class="d-block small text-body-secondary">' . $h($al['periodo']) . '</span>' : '')
					: '<span class="text-body-secondary">Sin pagos de pensión</span>';

				/* Asistencia, con cuántos días la sustentan. */
				$dias = (int)$al['dias'];
				$pct  = $dias > 0 ? round((int)$al['asistidos'] / $dias * 100) : null;
				$celdaAsis = $pct === null
					? '<span class="text-body-secondary">Sin registros</span>'
					: '<span class="ds-pagos__asis"><span class="ds-pagos__barra" aria-hidden="true"><span style="width:' . $pct . '%" class="' . ($pct >= 75 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger')) . '"></span></span>'
					  . '<strong>' . $pct . ' %</strong></span><span class="d-block small text-body-secondary">de ' . $dias . ($dias === 1 ? ' día' : ' días') . '</span>';

				$conDescuento = $al['descuento_valor'] !== null;
				$tabla .= '
					<tr>
						<td class="ds-ficha__principal" data-order="' . $h($orden) . '">
							<div class="ds-ficha__con-imagen">
								<span class="ds-ficha__imagen ds-alumnos__inicial" aria-hidden="true">' . $h($inicial) . '</span>
								<div class="min-w-0">
									<div class="ds-ficha__nombre">' . $h($nombre) . '</div>
									<div class="ds-ficha__meta"><i class="fas fa-id-card" aria-hidden="true"></i> ' . $h($al['alumno_identificacion'])
										. ($al['edad'] !== null ? ' · ' . (int)$al['edad'] . ((int)$al['edad'] === 1 ? ' año' : ' años') : '')
										. ($activo ? '' : ' · <span class="badge text-bg-secondary">Inactivo</span>') . '</div>
								</div>
							</div>
						</td>
						<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Fecha de ingreso" data-order="' . $h($ingreso) . '">' . $celdaIngreso . '</td>
						<td class="ds-ficha__dato" data-etiqueta="Último pago de pensión" data-order="' . $h($ultima) . '">' . $celdaUltima . '</td>
						<td class="ds-ficha__dato" data-etiqueta="Valor adeudado" data-order="' . number_format($deuda, 2, '.', '') . '">' . $celdaDeuda . '</td>
						<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Asistencia" data-order="' . ($pct ?? -1) . '">' . $celdaAsis . '</td>
						<td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">
							<a href="' . APP_URL . 'pagosNew/' . $id . '/" class="btn btn-primary btn-sm ds-ficha__primario"><i class="fas fa-dollar-sign me-1" aria-hidden="true"></i>Registrar pago</a>
							<a href="' . APP_URL . 'pagosDescuento/' . $id . '/" class="btn btn-sm ' . ($conDescuento ? 'btn-info' : 'btn-secondary') . '"'
								. ' title="' . ($conDescuento ? 'Descuentos (tiene uno activo)' : 'Descuentos') . '" aria-label="Descuentos de ' . $h($nombre) . '"><i class="fas fa-percent" aria-hidden="true"></i></a>
						</div></td>
					</tr>';
			}
			return $tabla;
		}

		/** Lo que suma la columna «Valor adeudado» del último listarAlumnosPagos(). */
		public float $totalAdeudado = 0.0;

		public function listarOptionSede($sedeid, $rolid = null, $usuario = null){
			$option="";
			/* El parametro acompana solo a la rama que lo usa: la consulta
			   del administrador no lleva :usuario y PDO rechaza los que
			   sobran. */
			$parametros=[];

			if($rolid != 1 && $rolid != 2){
				$consulta_datos="SELECT S.sede_id, S.sede_nombre 
									FROM general_sede S
									INNER JOIN seguridad_usuario_sede US ON US.usuariosede_sedeid = S.sede_id
									INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
									WHERE U.usuario_usuario = :usuario";
				$parametros[':usuario'] = $usuario;
			}else{
				$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede";
			}	
					
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
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

		/* ---------------------------Freddy----------------------- */
		public function BuscarAlumno($alumnoid){		
			$consulta_datos="SELECT S.sede_nombre, CASE WHEN alumno_estado = 'A' THEN 'Activo' WHEN alumno_estado = 'I' THEN 'Inactivo' ELSE 'Sin definir' END estado, Year(alumno_fechanacimiento) anio
					,CASE WHEN IFNULL(R.total, 0) > 0 THEN 1 ELSE 0 END pendiente,  IFNULL(R.total, 0) total
					,A.* 
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
					LEFT JOIN(
						SELECT COUNT(RP.pago_id) total, RA.alumno_id alumno
						FROM sujeto_alumno RA
						INNER JOIN alumno_pago RP ON RP.pago_alumnoid = RA.alumno_id
						WHERE RP.pago_estado = 'P'
						GROUP BY RA.alumno_id
					)R ON R.alumno = A.alumno_id
				WHERE A.alumno_id = :alumno";	
			$datos = $this->ejecutarConsulta($consulta_datos, [':alumno' => (int)$alumnoid]);
			return $datos;
		}
		
		public function BuscarAlumnoDescuento($alumnoid){		
			$consulta_datos="SELECT S.sede_nombre, 
								case when alumno_estado = 'A' then 'Activo' when alumno_estado = 'I' then 'Inactivo' else 'Sin definir' end estado, 
								Year(alumno_fechanacimiento) anio,A.*
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid					
				WHERE A.alumno_id = :alumno";				
			$datos = $this->ejecutarConsulta($consulta_datos, [':alumno' => (int)$alumnoid]);				
			return $datos;
		}
		
		public function AlumnoDescuento($alumnoid){		
			$consulta_datos="SELECT D.* FROM alumno_pago_descuento D					
							 INNER JOIN general_tabla_catalogo RD ON RD.catalogo_valor = D.descuento_rubroid 
							 WHERE D.descuento_alumnoid = :alumno AND D.descuento_estado = 'S' ";
				
			$datos = $this->ejecutarConsulta($consulta_datos, [':alumno' => (int)$alumnoid]);				
			return $datos;
		}
		
		public function BuscarDescuento($alumnoid){		
			$consulta_datos="SELECT D.* FROM alumno_pago_descuento D 
				WHERE D.descuento_alumnoid = :alumno";

			$datos = $this->ejecutarConsulta($consulta_datos, [':alumno' => (int)$alumnoid]);					
			return $datos;
		}
		
		public function pagosPendintes($alumnoid){
			$tabla="";
			$consulta_datos="SELECT C.catalogo_descripcion rubro, P.pago_periodo periodo
							,P.pago_saldo saldo
							FROM sujeto_alumno A
							INNER JOIN alumno_pago P ON P.pago_alumnoid = A.alumno_id
							INNER JOIN general_tabla_catalogo C ON C.catalogo_valor = P.pago_rubroid
						WHERE P.pago_estado = 'P' AND A.alumno_id = :alumno";	
			
			$datos = $this->ejecutarConsulta($consulta_datos, [':alumno' => (int)$alumnoid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){		
				$tabla.='
					<tr style="font-size: 14px" class="text-danger">
						<td>'.$rows['rubro'].'</td>
						<td>'.$rows['periodo'].'</td>
						<td>'.$rows['saldo'].'</td>
						</td>
					</tr>';						
			}
			return $tabla;
		}

		public function pensionesPendientes($alumnoid){
			$valor_pension = 0.0;
			$descuento=$this->AlumnoDescuento($alumnoid);
			if($descuento->rowCount()==1){
				$descuento=$descuento->fetch(); 
				if($descuento["descuento_rubroid"] == 'DDS'){					
					$valor_pension = $descuento["descuento_valor"];								
				}
			}else{
				$check_pension=$this->ejecutarConsulta("SELECT sede_pension	FROM general_sede, sujeto_alumno WHERE alumno_sedeid = sede_id AND alumno_id = :alumno", [':alumno' => (int)$alumnoid]);
				$valor_pension="";
				if($check_pension->rowCount()>0){				
					foreach($check_pension as $rows){	
						$valor_pension = $rows["sede_pension"]; 					
					}
				}
			}

			$tabla="";
			$ultimafecha = "SELECT MAX(P.pago_fecha) fecha
			FROM sujeto_alumno A
			INNER JOIN alumno_pago P ON P.pago_alumnoid = A.alumno_id
			WHERE P.pago_rubroid = 'RPE' AND pago_estado != 'E' AND A.alumno_id = :alumno";

			$datos = $this->ejecutarConsulta($ultimafecha, [':alumno' => (int)$alumnoid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
				$ultimafecha = $rows["fecha"];
			}	

			$fecha_final =  date('Y-m-d');
			// Convertir la fecha a un timestamp
			$timestamp = strtotime($fecha_final);
			// Obtener el último día del mes de la fecha dada
			$ultimo_dia = date('Y-m-t', $timestamp);

			$consulta_datos="SELECT CONCAT(
					CASE DATE_FORMAT(DATE_ADD(:f1, INTERVAL (units.i + tens.i * 10 + 1) MONTH), '%m')
						WHEN '01' THEN 'Enero'
						WHEN '02' THEN 'Febrero'
						WHEN '03' THEN 'Marzo'
						WHEN '04' THEN 'Abril'
						WHEN '05' THEN 'Mayo'
						WHEN '06' THEN 'Junio'
						WHEN '07' THEN 'Julio'
						WHEN '08' THEN 'Agosto'
						WHEN '09' THEN 'Septiembre'
						WHEN '10' THEN 'Octubre'
						WHEN '11' THEN 'Noviembre'
						WHEN '12' THEN 'Diciembre'
					END, ' / ', DATE_FORMAT(DATE_ADD(:f2, INTERVAL (units.i + tens.i * 10 + 1) MONTH), '%Y')
					) AS mes, DATE_ADD(:f3, INTERVAL (units.i + tens.i * 10 + 1) MONTH) AS fecha_ordenar    
				FROM ( SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 ) AS units  
				JOIN ( SELECT 0 AS i UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9 ) AS tens 
				WHERE DATE_ADD(:f4, INTERVAL (units.i + tens.i * 10 + 1) MONTH) <= :ultimo ORDER BY fecha_ordenar;";	
			
			$datos = $this->ejecutarConsulta($consulta_datos, [':f1' => $ultimafecha, ':f2' => $ultimafecha, ':f3' => $ultimafecha, ':f4' => $ultimafecha, ':ultimo' => $ultimo_dia]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){					
				$tabla.='
					<tr style="font-size: 14px"  class="text-danger">
						<td>Pensión</td>
						<td>'.$rows['mes'].'</td>
						<td>'.$valor_pension.'</td>
						</td>
					</tr>';							
			}
			return $tabla;
		}
		public function listarOptionPago(){
			$option="";

			$consulta_datos="SELECT *FROM general_tabla_catalogo WHERE catalogo_tablaid = 6 AND catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){			
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';					
			}
			return $option;
		}

		public function listarCampeonatos($torneo_id = null){
			$option="";

			$consulta_datos="select torneo_id, torneo_nombre from torneo_torneo where torneo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
				if($torneo_id == $rows['torneo_id']){	
					$option.='<option value='.$rows['torneo_id'].' selected="selected">'.$rows['torneo_nombre'].'</option>';
				}else{		
					$option.='<option value='.$rows['torneo_id'].'>'.$rows['torneo_nombre'].'</option>';					
				}
			}
			return $option;
		}

		public function listarOptionTalla($talla){
			$option ='<option value=0> Seleccione la talla</option>';
			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'tallas'
									AND T.tabla_estado = 'A'
									AND C.catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($talla == $rows['catalogo_valor']){	
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{		
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
                }						
			}
			return $option;
		}

		public function listarOptionDescuento($descuento_rubroid){
			$option="";

			$consulta_datos="SELECT *FROM general_tabla_catalogo WHERE catalogo_tablaid = 7 AND catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){			
				if($descuento_rubroid == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';	
				}else{
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}									
			}
			return $option;
		}

		public function registrarPagoControlador(){
			# Almacenando datos#
			$pago_alumnoid 		= $this->limpiarCadena($_POST['pago_alumnoid']);
			$pago_fecha			= $this->limpiarCadena($_POST['pago_fecha']);
			$pago_fecharegistro	= $this->limpiarCadena($_POST['pago_fecharegistro']);
			$pago_periodo 		= $this->limpiarCadena($_POST['pago_periodo']);	
			$pago_valor 		= $_POST['pago_valor'];
			$pago_saldo 		= $_POST['pago_saldo'];
			$pago_formapagoid 	= $this->limpiarCadena($_POST['pago_formapagoid']);
			$pago_concepto 		= $this->limpiarCadena($_POST['pago_concepto']);			
			$pago_rubro			= $this->limpiarCadena($_POST['pago_rubro']);
			$rubroid 			= ""; 
			
			if ($pago_valor =="") {$pago_valor = 0;}
			if ($pago_saldo =="") {$pago_saldo = 0;}

			if($pago_rubro == "pension"){
				$rubroid="RPE";			
			}elseif($pago_rubro == "inscripcion"){
				$rubroid="RIN";				
			} elseif($pago_rubro == "uniforme"){
				$rubroid="RNU";				
			}elseif($pago_rubro == "kit"){
				$rubroid="RKE";				
			}elseif($pago_rubro == "otros"){
				$rubroid="ROT";				
			}elseif($pago_rubro == "campeonato"){
				$rubroid="RPC";				
			}
			
			if($pago_valor < 1 && $pago_saldo < 1){
				$estado = "J";
			}elseif($pago_saldo != "" && $pago_saldo > 0){
				$estado = "P";
			}else{
				$estado = "C";
			}		

			# Verificando campos obligatorios #
		    if($pago_fecha=="" || $pago_fecharegistro=="" || $pago_periodo=="" || $pago_valor=="" || $pago_formapagoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No ha completado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

			# Directorio de imagenes #
			$img_dir="../views/imagenes/pagos/";

			# Comprobar si se selecciono una imagen #
    		if($_FILES['pago_archivo']['name']!="" && $_FILES['pago_archivo']['size']>0){
    			# Creando directorio #
		        if(!file_exists($img_dir)){
		            if(!mkdir($img_dir,0777)){
		            	$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
		            } 
		        }

		        # Verificando formato de imagenes #
		        if(mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['pago_archivo']['size']/1024)>3000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Nombre de la foto #
		        $foto=str_ireplace(" ","_",$pago_alumnoid);
		        $foto=$foto."_".rand(0,100);

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['pago_archivo']['tmp_name'])){
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
				$inputFile = ($_FILES['pago_archivo']['tmp_name']);
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

			$check_recibo=$this->ejecutarConsulta("SELECT escuela_recibo FROM general_escuela WHERE escuela_id = 1");
			//$check_recibo=$this->seleccionarDatos("Unico","general_escuela","escuela_id","1");
			if($check_recibo->rowCount()>0){				
				foreach($check_recibo as $rows){	
					$num_recibo = $rows["escuela_recibo"] + 1; 					
				}
				
				// Establecer la zona horaria de Ecuador
				date_default_timezone_set('America/Guayaquil');

				// Obtener la fecha y hora actual
				$fecha_actual = date('Y-m-d H:i:s');

				// Descomponer la fecha y hora en sus componentes individuales
				$anio = date('y');
				$mes = date('m');
				$dia = date('d');
				$hora = date('H');
				$minuto = date('i');
				$segundo = date('s');

				// Generar número de recibo con fecha y hora al revés
				$numero_recibo = $segundo . $anio . $minuto . $mes . $hora . $dia;
				$pago_recibo = strrev($numero_recibo)."".$num_recibo;					
			}
			else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No se puede generar el recibo en este momento",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			$pago_datos_reg=[
				[
					"campo_nombre"=>"pago_rubroid",
					"campo_marcador"=>":Rubroid",
					"campo_valor"=>$rubroid
				],
				[
					"campo_nombre"=>"pago_formapagoid",
					"campo_marcador"=>":Formapagoid",
					"campo_valor"=>$pago_formapagoid
				],				
				[
					"campo_nombre"=>"pago_alumnoid",
					"campo_marcador"=>":Alumnoid",
					"campo_valor"=>$pago_alumnoid
				],
				[
					/* Sede CONGELADA en el momento de cobrar. No se deriva despues
					   del alumno: si se traslada, sus pagos anteriores seguirian
					   contando en la sede donde se cobraron. Ver database/044. */
					"campo_nombre"=>"pago_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$this->sedeDelAlumno($pago_alumnoid)
				],				
				[
					"campo_nombre"=>"pago_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$pago_valor
				],
				[
					"campo_nombre"=>"pago_saldo",
					"campo_marcador"=>":Saldo",
					"campo_valor"=>$pago_saldo
				],		
				[
					"campo_nombre"=>"pago_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$pago_concepto
				],
				[
					"campo_nombre"=>"pago_fecha",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$pago_fecha
				],				
				[
					"campo_nombre"=>"pago_fecharegistro",
					"campo_marcador"=>":Fecharegistro",
					"campo_valor"=>$pago_fecharegistro
				],
				[
					"campo_nombre"=>"pago_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$pago_periodo
				],				
				[
					"campo_nombre"=>"pago_recibo",
					"campo_marcador"=>":Recibo",
					"campo_valor"=>$pago_recibo
				],
				[
					"campo_nombre"=>"pago_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$estado 
				],
				[
					"campo_nombre"=>"pago_archivo",
					"campo_marcador"=>":Imagenpago",
					"campo_valor"=>$foto
				]
			];		

			if($pago_fecharegistro > date(date("Y-m-d H:i:s"))){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"La fecha de registro del pago es mayor a la fecha actual",
					"icono"=>"error"
				];
				return json_encode($alerta);

			}else{
				$registrar_pago=$this->guardarDatos("alumno_pago",$pago_datos_reg);
				if($registrar_pago->rowCount()>0){
					$alerta=[
						"tipo"=>"recargar",
						"titulo"=>"Pago registrado",
						"texto"=>"El pago se registró correctamente",
						"icono"=>"success"
					];
	
					// Actualizar numero de recibo
					$this->ejecutarConsulta("UPDATE general_escuela SET escuela_recibo = :recibo WHERE escuela_id = 1", [':recibo' => (int)$num_recibo]);
				
				}else{
					
					if(is_file($img_dir.$foto)){
						chmod($img_dir.$foto,0777);
						unlink($img_dir.$foto);
					}
	
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"No se pudo registrar el pago, por favor intente nuevamente",
						"icono"=>"error"
					];
				}
	
				return json_encode($alerta);
			}
		}

		public function registrarPagoCampeonato(){
			# Almacenando datos#
			$pago_alumnoid 		= $this->limpiarCadena($_POST['pago_alumnoid']);
			$pago_fecha			= $this->limpiarCadena($_POST['pago_fecha']);
			$pago_fecharegistro	= $this->limpiarCadena($_POST['pago_fecharegistro']);
			$pago_campeonatoid 	= $this->limpiarCadena($_POST['pago_campeonatoid']);	
			$pago_valor 		= $_POST['pago_valor'];
			$pago_saldo 		= $_POST['pago_saldo'];
			$pago_formapagoid 	= $this->limpiarCadena($_POST['pago_formapagoid']);
			$pago_concepto 		= $this->limpiarCadena($_POST['pago_concepto']);			
			$pago_rubro			= $this->limpiarCadena($_POST['pago_rubro']);
			$rubroid 			= ""; 
			
			if ($pago_valor =="") {$pago_valor = 0;}
			if ($pago_saldo =="") {$pago_saldo = 0;}

			if($pago_rubro == "pension"){
				$rubroid="RPE";			
			}elseif($pago_rubro == "inscripcion"){
				$rubroid="RIN";				
			} elseif($pago_rubro == "uniforme"){
				$rubroid="RNU";				
			}elseif($pago_rubro == "kit"){
				$rubroid="RKE";				
			}elseif($pago_rubro == "otros"){
				$rubroid="ROT";				
			}elseif($pago_rubro == "campeonato"){
				$rubroid="RPC";				
			}
			
			if($pago_valor < 1 && $pago_saldo < 1){
				$estado = "J";
			}elseif($pago_saldo != "" && $pago_saldo > 0){
				$estado = "P";
			}else{
				$estado = "C";
			}		

			# Verificando campos obligatorios #
		    if($pago_fecha=="" || $pago_fecharegistro=="" || $pago_campeonatoid=="" || $pago_valor=="" || $pago_formapagoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No ha completado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

			# Directorio de imagenes #
			$img_dir="../views/imagenes/pagos/";

			# Comprobar si se selecciono una imagen #
    		if($_FILES['pago_archivo']['name']!="" && $_FILES['pago_archivo']['size']>0){
    			# Creando directorio #
		        if(!file_exists($img_dir)){
		            if(!mkdir($img_dir,0777)){
		            	$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
		            } 
		        }

		        # Verificando formato de imagenes #
		        if(mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['pago_archivo']['size']/1024)>3000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Nombre de la foto #
		        $foto=str_ireplace(" ","_",$pago_alumnoid);
		        $foto=$foto."_".rand(0,100);

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['pago_archivo']['tmp_name'])){
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
				$inputFile = ($_FILES['pago_archivo']['tmp_name']);
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

			$check_recibo=$this->ejecutarConsulta("SELECT escuela_recibo FROM general_escuela WHERE escuela_id = 1");
			//$check_recibo=$this->seleccionarDatos("Unico","general_escuela","escuela_id","1");
			if($check_recibo->rowCount()>0){				
				foreach($check_recibo as $rows){	
					$num_recibo = $rows["escuela_recibo"] + 1; 					
				}
				
				// Establecer la zona horaria de Ecuador
				date_default_timezone_set('America/Guayaquil');

				// Obtener la fecha y hora actual
				$fecha_actual = date('Y-m-d H:i:s');

				// Descomponer la fecha y hora en sus componentes individuales
				$anio = date('y');
				$mes = date('m');
				$dia = date('d');
				$hora = date('H');
				$minuto = date('i');
				$segundo = date('s');

				// Generar número de recibo con fecha y hora al revés
				$numero_recibo = $segundo . $anio . $minuto . $mes . $hora . $dia;
				$pago_recibo = strrev($numero_recibo)."".$num_recibo;					
			}
			else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No se puede generar el recibo en este momento",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			$pago_datos_reg=[
				[
					"campo_nombre"=>"pago_rubroid",
					"campo_marcador"=>":Rubroid",
					"campo_valor"=>$rubroid
				],
				[
					"campo_nombre"=>"pago_formapagoid",
					"campo_marcador"=>":Formapagoid",
					"campo_valor"=>$pago_formapagoid
				],				
				[
					"campo_nombre"=>"pago_alumnoid",
					"campo_marcador"=>":Alumnoid",
					"campo_valor"=>$pago_alumnoid
				],
				[
					/* Sede CONGELADA en el momento de cobrar. No se deriva despues
					   del alumno: si se traslada, sus pagos anteriores seguirian
					   contando en la sede donde se cobraron. Ver database/044. */
					"campo_nombre"=>"pago_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$this->sedeDelAlumno($pago_alumnoid)
				],				
				[
					"campo_nombre"=>"pago_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$pago_valor
				],
				[
					"campo_nombre"=>"pago_saldo",
					"campo_marcador"=>":Saldo",
					"campo_valor"=>$pago_saldo
				],		
				[
					"campo_nombre"=>"pago_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$pago_concepto
				],
				[
					"campo_nombre"=>"pago_fecha",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$pago_fecha
				],				
				[
					"campo_nombre"=>"pago_fecharegistro",
					"campo_marcador"=>":Fecharegistro",
					"campo_valor"=>$pago_fecharegistro
				],
				[
					"campo_nombre"=>"pago_campeonatoid",
					"campo_marcador"=>":Campeonatoid",
					"campo_valor"=>$pago_campeonatoid
				],				
				[
					"campo_nombre"=>"pago_recibo",
					"campo_marcador"=>":Recibo",
					"campo_valor"=>$pago_recibo
				],
				[
					"campo_nombre"=>"pago_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$estado 
				],
				[
					"campo_nombre"=>"pago_archivo",
					"campo_marcador"=>":Imagenpago",
					"campo_valor"=>$foto
				]
			];		

			if($pago_fecharegistro > date(date("Y-m-d H:i:s"))){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"La fecha de registro del pago es mayor a la fecha actual",
					"icono"=>"error"
				];
				return json_encode($alerta);

			}else{
				$registrar_pago=$this->guardarDatos("alumno_pago",$pago_datos_reg);
				if($registrar_pago->rowCount()>0){
					$alerta=[
						"tipo"=>"recargar",
						"titulo"=>"Pago registrado",
						"texto"=>"El pago se registró correctamente",
						"icono"=>"success"
					];
	
					// Actualizar numero de recibo
					$this->ejecutarConsulta("UPDATE general_escuela SET escuela_recibo = :recibo WHERE escuela_id = 1", [':recibo' => (int)$num_recibo]);
				
				}else{
					
					if(is_file($img_dir.$foto)){
						chmod($img_dir.$foto,0777);
						unlink($img_dir.$foto);
					}
	
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"No se pudo registrar el pago, por favor intente nuevamente",
						"icono"=>"error"
					];
				}
	
				return json_encode($alerta);
			}
		}

		public function registrarPagoUniforme(){
			# Almacenando datos#
			$pago_alumnoid 		= $this->limpiarCadena($_POST['pago_alumnoid']);
			$pago_fecha			= $this->limpiarCadena($_POST['pago_fecha']);
			$pago_fecharegistro	= $this->limpiarCadena($_POST['pago_fecharegistro']);
			$pago_periodo 		= $this->limpiarCadena($_POST['pago_periodo']);	
			$pago_valor 		= $_POST['pago_valor'];
			$pago_saldo 		= $_POST['pago_saldo'];
			$pago_formapagoid 	= $this->limpiarCadena($_POST['pago_formapagoid']);
			$pago_concepto 		= $this->limpiarCadena($_POST['pago_concepto']);			
			$pago_rubro			= $this->limpiarCadena($_POST['pago_rubro']);
			$rubroid 			= ""; 
			
			if ($pago_valor =="") {$pago_valor = 0;}
			if ($pago_saldo =="") {$pago_saldo = 0;}

			if(isset($_POST['pago_talla'])){
				$pago_talla = $this->limpiarCadena($_POST['pago_talla']);
			}else{
				$pago_talla = "";
			}

			if($pago_rubro == "pension"){
				$rubroid="RPE";			
			}elseif($pago_rubro == "inscripcion"){
				$rubroid="RIN";				
			} elseif($pago_rubro == "uniforme"){
				$rubroid="RNU";				
			}elseif($pago_rubro == "kit"){
				$rubroid="RKE";				
			}elseif($pago_rubro == "otros"){
				$rubroid="ROT";				
			}	
			
			if($pago_valor < 1 && $pago_saldo < 1){
				$estado = "J";
			}elseif($pago_saldo != "" && $pago_saldo > 0){
				$estado = "P";
			}else{
				$estado = "C";
			}		

			# Verificando campos obligatorios #
		    if($pago_fecha=="" || $pago_fecharegistro=="" || $pago_periodo=="" || $pago_valor=="" || $pago_formapagoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No ha completado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

			# Directorio de imagenes #
			$img_dir="../views/imagenes/pagos/";

			# Comprobar si se selecciono una imagen #
    		if($_FILES['pago_archivo']['name']!="" && $_FILES['pago_archivo']['size']>0){
    			# Creando directorio #
		        if(!file_exists($img_dir)){
		            if(!mkdir($img_dir,0777)){
		            	$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
		            } 
		        }

		        # Verificando formato de imagenes #
		        if(mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['pago_archivo']['size']/1024)>3000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Nombre de la foto #
		        $foto=str_ireplace(" ","_",$pago_alumnoid);
		        $foto=$foto."_".rand(0,100);

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['pago_archivo']['tmp_name'])){
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
				$inputFile = ($_FILES['pago_archivo']['tmp_name']);
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

			$check_recibo=$this->ejecutarConsulta("SELECT escuela_recibo FROM general_escuela WHERE escuela_id = 1");
			//$check_recibo=$this->seleccionarDatos("Unico","general_escuela","escuela_id","1");
			if($check_recibo->rowCount()>0){				
				foreach($check_recibo as $rows){	
					$num_recibo = $rows["escuela_recibo"] + 1; 					
				}
				
				// Establecer la zona horaria de Ecuador
				date_default_timezone_set('America/Guayaquil');

				// Obtener la fecha y hora actual
				$fecha_actual = date('Y-m-d H:i:s');

				// Descomponer la fecha y hora en sus componentes individuales
				$anio = date('y');
				$mes = date('m');
				$dia = date('d');
				$hora = date('H');
				$minuto = date('i');
				$segundo = date('s');

				// Generar número de recibo con fecha y hora al revés
				$numero_recibo = $segundo . $anio . $minuto . $mes . $hora . $dia;
				$pago_recibo = strrev($numero_recibo)."".$num_recibo;					
			}
			else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No se puede generar el recibo en este momento",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			$pago_datos_reg=[
				[
					"campo_nombre"=>"pago_rubroid",
					"campo_marcador"=>":Rubroid",
					"campo_valor"=>$rubroid
				],
				[
					"campo_nombre"=>"pago_formapagoid",
					"campo_marcador"=>":Formapagoid",
					"campo_valor"=>$pago_formapagoid
				],				
				[
					"campo_nombre"=>"pago_alumnoid",
					"campo_marcador"=>":Alumnoid",
					"campo_valor"=>$pago_alumnoid
				],
				[
					/* Sede CONGELADA en el momento de cobrar. No se deriva despues
					   del alumno: si se traslada, sus pagos anteriores seguirian
					   contando en la sede donde se cobraron. Ver database/044. */
					"campo_nombre"=>"pago_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$this->sedeDelAlumno($pago_alumnoid)
				],				
				[
					"campo_nombre"=>"pago_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$pago_valor
				],
				[
					"campo_nombre"=>"pago_saldo",
					"campo_marcador"=>":Saldo",
					"campo_valor"=>$pago_saldo
				],		
				[
					"campo_nombre"=>"pago_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$pago_concepto
				],
				[
					"campo_nombre"=>"pago_fecha",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$pago_fecha
				],				
				[
					"campo_nombre"=>"pago_fecharegistro",
					"campo_marcador"=>":Fecharegistro",
					"campo_valor"=>$pago_fecharegistro
				],
				[
					"campo_nombre"=>"pago_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$pago_periodo
				],	
				[
					"campo_nombre"=>"pago_talla",
					"campo_marcador"=>":Talla",
					"campo_valor"=>$pago_talla
				],				
				[
					"campo_nombre"=>"pago_recibo",
					"campo_marcador"=>":Recibo",
					"campo_valor"=>$pago_recibo
				],
				[
					"campo_nombre"=>"pago_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$estado 
				],
				[
					"campo_nombre"=>"pago_archivo",
					"campo_marcador"=>":Imagenpago",
					"campo_valor"=>$foto
				]
			];		

			if($pago_fecharegistro > date(date("Y-m-d H:i:s"))){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"La fecha de registro del pago es mayor a la fecha actual",
					"icono"=>"error"
				];
				return json_encode($alerta);

			}else{
				$registrar_pago=$this->guardarDatos("alumno_pago",$pago_datos_reg);
				if($registrar_pago->rowCount()>0){
					$alerta=[
						"tipo"=>"recargar",
						"titulo"=>"Pago registrado",
						"texto"=>"El pago se registró correctamente",
						"icono"=>"success"
					];
	
					// Actualizar numero de recibo
					$this->ejecutarConsulta("UPDATE general_escuela SET escuela_recibo = :recibo WHERE escuela_id = 1", [':recibo' => (int)$num_recibo]);
				
				}else{
					
					if(is_file($img_dir.$foto)){
						chmod($img_dir.$foto,0777);
						unlink($img_dir.$foto);
					}
	
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"No se pudo registrar el pago, por favor intente nuevamente",
						"icono"=>"error"
					];
				}
	
				return json_encode($alerta);
			}
		}

		public function registrarPagoPendiente(){
			
			# Almacenando datos#
			$transaccion_pagoid			= $this->limpiarCadena($_POST['pago_id']);
			$total						= $this->limpiarCadena($_POST['pago_total']);
			$transaccion_valorcalculado	= $this->limpiarCadena($_POST['pago_saldo']);
			$transaccion_fecha			= $this->limpiarCadena($_POST['pago_fecha']);
			$transaccion_fecharegistro	= $this->limpiarCadena($_POST['pago_fecharegistro']);
			//$transaccion_periodo 		= $this->limpiarCadena($_POST['pago_periodo']);			
			$transaccion_valor 			= $_POST['pago_valor'];
			$transaccion_formapagoid 	= $this->limpiarCadena($_POST['pago_formapagoid']);
			$transaccion_concepto 		= $this->limpiarCadena($_POST['pago_concepto']);			
			$pago_rubro					= $this->limpiarCadena($_POST['pago_rubro']);
			$estado 					= "C";

			$transaccion_periodo = isset($_POST['pago_periodo']) && !empty(trim($_POST['pago_periodo'])) ? $this->limpiarCadena($_POST['pago_periodo']) : date("Y");

			// Actualizar saldo del rubro				
			$saldo = $transaccion_valorcalculado - $transaccion_valor;
			$total += $transaccion_valor;

			if ($saldo == 0){
				$estado_saldo='C';
			}else{
				$estado_saldo = 'P';
			}
			
			# Verificando campos obligatorios #
		    if($transaccion_fecha=="" || $transaccion_fecharegistro=="" || $transaccion_valorcalculado=="" || $transaccion_valor=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

			if($saldo < 0 ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error en el valor ingresado",
					"texto"=>"El valor ingresado supera el monto del saldo",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

			# Directorio de imagenes #
			$img_dir="../views/imagenes/pagos/";

			# Comprobar si se selecciono una imagen #
    		if($_FILES['pago_archivo']['name']!="" && $_FILES['pago_archivo']['size']>0){

    			# Creando directorio #
		        if(!file_exists($img_dir)){
		            if(!mkdir($img_dir,0777)){
		            	$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
		                //exit();
		            } 
		        }

		        # Verificando formato de imagenes #
		        if(mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		            //exit();
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['pago_archivo']['size']/1024)>3000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		            //exit();
		        }

		        # Nombre de la foto #
		        $foto=str_ireplace(" ","_","pagopendiente".$transaccion_pagoid);
		        $foto=$foto."_".rand(0,100);

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['pago_archivo']['tmp_name'])){
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
				$inputFile = ($_FILES['pago_archivo']['tmp_name']);
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
			
			$check_recibo=$this->ejecutarConsulta("SELECT escuela_recibo FROM general_escuela WHERE escuela_id = 1");
			//$check_recibo=$this->seleccionarDatos("Unico","general_escuela","escuela_id","1");
			if($check_recibo->rowCount()>0){				
				foreach($check_recibo as $rows){	
					$num_recibo = $rows["escuela_recibo"] + 1; 					
				}
				
				// Establecer la zona horaria de Ecuador
				date_default_timezone_set('America/Guayaquil');

				// Obtener la fecha y hora actual
				$fecha_actual = date('Y-m-d H:i:s');

				// Descomponer la fecha y hora en sus componentes individuales
				$anio = date('y');
				$mes = date('m');
				$dia = date('d');
				$hora = date('H');
				$minuto = date('i');
				$segundo = date('s');

				// Generar número de recibo con fecha y hora al revés
				$numero_recibo = $segundo . $anio . $minuto . $mes . $hora . $dia;
				$pago_recibo = strrev($numero_recibo)."".$num_recibo;	
				
			}
			else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No se puede generar el recibo en este momento",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}
									
			$pago_datos_reg=[
				[
					"campo_nombre"=>"transaccion_pagoid",
					"campo_marcador"=>":Pagoid",
					"campo_valor"=>$transaccion_pagoid
				],							
				[
					"campo_nombre"=>"transaccion_valorcalculado",
					"campo_marcador"=>":Valorcalculado",
					"campo_valor"=>$transaccion_valorcalculado
				],				
				[
					"campo_nombre"=>"transaccion_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$transaccion_valor
				],
				[
					"campo_nombre"=>"transaccion_fecha",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$transaccion_fecha
				],				
				[
					"campo_nombre"=>"transaccion_fecharegistro",
					"campo_marcador"=>":transaccion_fecharegistro",
					"campo_valor"=>$transaccion_fecharegistro
				],		
				[
					"campo_nombre"=>"transaccion_formapagoid",
					"campo_marcador"=>":Formapagoid",
					"campo_valor"=>$transaccion_formapagoid
				],	
				[
					"campo_nombre"=>"transaccion_concepto",
					"campo_marcador"=>":Concepto",
					"campo_valor"=>$transaccion_concepto
				],
				
				[
					"campo_nombre"=>"transaccion_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$transaccion_periodo
				],
				[
					"campo_nombre"=>"transaccion_recibo",
					"campo_marcador"=>":Recibo",
					"campo_valor"=>$pago_recibo
				],
				[
					"campo_nombre"=>"transaccion_estado",
					"campo_marcador"=>":transaccion_estado",
					"campo_valor"=>$estado
				],
				[
					"campo_nombre"=>"transaccion_archivo",
					"campo_marcador"=>":Imagenpago",
					"campo_valor"=>$foto
				]
			];		

			$registrar_pago=$this->guardarDatos("alumno_pago_transaccion",$pago_datos_reg);

			if($registrar_pago->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Pago registrado",
					"texto"=>"El pago pendiente se registró correctamente",
					"icono"=>"success"
				];
				
				
				// Actualizar saldo y valor
				$this->ejecutarConsulta("UPDATE alumno_pago SET pago_valor = :valor, pago_saldo = :saldo, pago_estado = :estado WHERE pago_id = :pago", [':valor' => $total, ':saldo' => $saldo, ':estado' => $estado_saldo, ':pago' => (int)$transaccion_pagoid]);
			

				// Actualizar numero de recibo
				$this->ejecutarConsulta("UPDATE general_escuela SET escuela_recibo = :recibo WHERE escuela_id = 1", [':recibo' => (int)$num_recibo]);
				
			}else{
				
				if(is_file($img_dir.$foto)){
		            chmod($img_dir.$foto,0777);
		            unlink($img_dir.$foto);
		        }

				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No se pudo registrar el pago, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);

		}

		public function registrarDescuento(){
			# Almacenando datos#
			$descuento_alumnoid	= $this->limpiarCadena($_POST['descuento_alumnoid']);
			$descuento_rubroid	= $this->limpiarCadena($_POST['descuento_rubroid']);
			$descuento_valor	= $this->limpiarCadena($_POST['descuento_valor']);
			$descuento_fecha 	= $this->limpiarCadena($_POST['descuento_fecha']);
			$descuento_detalle 	= $this->limpiarCadena($_POST['descuento_detalle']);			
			$descuento_estado 	= $this->limpiarCadena($_POST['descuento_estado']);			
			
			if ($descuento_valor =="") {$descuento_valor = 0;}
			
			# Verificando campos obligatorios #
		    if($descuento_rubroid=="" || $descuento_valor=="" || $descuento_fecha=="" || $descuento_estado=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }			

			#Verificar si el descuento ya fue ingresado
			$check_descuento=$this->ejecutarConsulta("SELECT * FROM alumno_pago_descuento WHERE descuento_alumnoid = :alumno", [':alumno' => (int)$descuento_alumnoid]);
		    if($check_descuento->rowCount()>0){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El descuento ya se encuentra registrado",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }

			$descuento_datos_reg=[
				[
					"campo_nombre"=>"descuento_rubroid",
					"campo_marcador"=>":Rubroid",
					"campo_valor"=>$descuento_rubroid
				],
				[
					"campo_nombre"=>"descuento_alumnoid",
					"campo_marcador"=>":Alumnoid",
					"campo_valor"=>$descuento_alumnoid
				],						
				[
					"campo_nombre"=>"descuento_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$descuento_valor
				],		
				[
					"campo_nombre"=>"descuento_detalle",
					"campo_marcador"=>":Detalle",
					"campo_valor"=>$descuento_detalle
				],
				[
					"campo_nombre"=>"descuento_fecha",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$descuento_fecha
				],				
				[
					"campo_nombre"=>"descuento_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$descuento_estado
				]
			];		

			$registrar_descuento=$this->guardarDatos("alumno_pago_descuento",$descuento_datos_reg);

			if($registrar_descuento->rowCount()==1){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Registro de descuento",
					"texto"=>"El descuento se registró correctamente",
					"icono"=>"success"
				];
				// Actualizar numero de recibo				
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No se pudo registrar el pago, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function listarPagosPendientes($pagoid){			
			$tabla="";
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY PT.transaccion_id) AS fila_numero, PT.*, P.* 
				FROM alumno_pago_transaccion PT
				INNER JOIN alumno_pago P ON P.pago_id = PT.transaccion_pagoid  
				WHERE (PT.transaccion_pagoid = :pago AND PT.transaccion_estado NOT IN ('E')) ORDER BY transaccion_id DESC";		

			$datos = $this->ejecutarConsulta($consulta_datos, [':pago' => (int)$pagoid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
			
			if ($rows['transaccion_estado'] == 'C'){
				$estado = 'Cancelado';
				$class = '';
			}elseif($rows['transaccion_estado'] == 'P'){
				$estado = '<span class="badge bg-danger"> Pendiente';
				$class = 'class="text-danger"';
			}elseif($rows['transaccion_estado'] == 'J'){
				$estado = ' Justificado';
				$class = 'class="text-primary"';
			}

			if ($rows['transaccion_archivo']!=""){
					$imagen = media_url('pago', $rows['transaccion_archivo']);
			}else{
				$imagen = ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg');
			}
				
			$tabla.='
				<tr '.$class.'>
					<td>'.$rows['fila_numero'].'</td>
					<td>'.$rows['transaccion_fecha'].'</td>
					<td>'.$rows['transaccion_periodo'].'</td>
					<td><a href="'.$imagen.'" data-bs-toggle="lightbox" data-title="Pago" data-gallery="gallery">'.$rows['transaccion_valor'].'</a></td>				
					<td>'.$rows['transaccion_valorcalculado'].'</td>	
					<td>'.$rows['transaccion_recibo'].'</td>	
					<td>
						'.$this->siPuede('eliminar','pagosList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/pagosAjax.php" method="POST" autocomplete="off" >
							<input type="hidden" name="modulo_pagos" value="eliminarpendiente">
							<input type="hidden" name="transaccion_id" value="'.$rows['transaccion_id'].'">	
							<input type="hidden" name="transaccion_pagoid" value="'.$rows['transaccion_pagoid'].'">						
							<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 5px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
						</form>').'							

						'.$this->siPuede('editar','pagosList','<a href="'.APP_URL.'pagospendienteUpdate/'.$rows['transaccion_id'].'/" class="btn float-end btn-success btn-sm" style="margin-right: 5px;" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
						<a href="'.APP_URL.'pagospendienteRecibo/'.$rows['transaccion_id'].'/" class="btn float-end btn-secondary btn-sm" style="margin-right: 5px;" target="_blank"><i class="fas fa-print me-1"></i>Recibo</a>
					</td>
				</tr>';	
			}
			return $tabla;			
		}

		public function listarPagosRubro($alumnoid, $rubro){ //29052024			
			$tabla="";
			$eliminarpago="";
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY pago_id) AS fila_numero, IFNULL(P.PAGOS_PENDIENTES, 0)PAGOS_PENDIENTES , A.*, T.torneo_nombre
				FROM alumno_pago A  
				LEFT JOIN (
					SELECT COUNT(1)PAGOS_PENDIENTES, transaccion_pagoid 
					FROM alumno_pago_transaccion
					where transaccion_estado NOT IN ('E')
					GROUP BY transaccion_pagoid
				)P ON P.transaccion_pagoid = A.pago_id 
				LEFT JOIN torneo_torneo T ON T.torneo_id = A.pago_campeonatoid 
				WHERE (A.pago_alumnoid = :alumno AND A.pago_rubroid = :rubro AND A.pago_estado NOT IN ('E')) ORDER BY pago_id DESC";		

			$datos = $this->ejecutarConsulta($consulta_datos, [':alumno' => (int)$alumnoid, ':rubro' => $rubro]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){			
				if ($rows['pago_estado'] == 'C'){
					$estado = 'Cancelado';
					$class = '';
				}elseif($rows['pago_estado'] == 'P'){
					$estado = '<span class="badge bg-danger"> Pendiente';
					$class = 'class="text-danger"';
				}elseif($rows['pago_estado'] == 'J'){
					$estado = ' Justificado';
					$class = 'class="text-primary"';
				}

				if($rows['pago_saldo'] > 0 ){
					$btnPagar = '<a href="'.APP_URL.'pagosPendiente/'.$rows['pago_id'].'/" class="btn float-end btn-info btn-sm" style="margin-right: 5px;"><i class="fas fa-dollar-sign me-1"></i>Pagar</a>';
				}elseif($rows['pago_saldo'] == 0 && $rows['PAGOS_PENDIENTES']>0){
					$btnPagar = '<a href="'.APP_URL.'pagosPendiente/'.$rows['pago_id'].'/" class="btn float-end btn-dark btn-sm" style="margin-right: 5px;"><i class="fas fa-dollar-sign me-1"></i>Pagos</a>';
				}else{				
					$btnPagar ="";
				}

				if($rows['PAGOS_PENDIENTES']>0){
					$eliminarpago="disabled";
				}else{
					$eliminarpago="";
				}

				if ($rows['pago_archivo']!=""){
					$imagen = media_url('pago', $rows['pago_archivo']);
				}else{
					$imagen = ds_recurso('ds_futbol/app/views/dist/img/sinpago.jpg');
				}
					
				if($rubro == 'RPE'){
					$tabla.='
						<tr '.$class.'>
							<td>'.$rows['fila_numero'].'</td>
							<td>'.$rows['pago_fecharegistro'].'</td>
							<td>'.$rows['pago_periodo'].'</td>								
							<td><a href="'.$imagen.'" data-bs-toggle="lightbox" data-title="Pago" data-gallery="gallery">'.$rows['pago_valor'].'</a></td>
							<td>'.$rows['pago_saldo'].'</td>						
							<td>'.$rows['pago_recibo'].'</td>								
							<td>'.$estado.'</td>
							<td>
								'.$this->siPuede('eliminar','pagosList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/pagosAjax.php" method="POST" autocomplete="off" >
									<input type="hidden" name="modulo_pagos" value="eliminar">
									<input type="hidden" name="pago_id" value="'.$rows['pago_id'].'">						
									<button type="submit" class="btn float-end btn-danger btn-sm " style="margin-right: 5px;" '.$eliminarpago.' title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
								</form>').'							

								'.$this->siPuede('editar','pagosList','<a href="'.APP_URL.'pagosUpdate/'.$rows['pago_id'].'/" class="btn float-end btn-success btn-sm '.$eliminarpago.'" style="margin-right: 5px;"  title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
								'.$btnPagar.'
								<a href="'.APP_URL.'pagosRecibo/'.$rows['pago_id'].'/" class="btn float-end btn-secondary btn-sm" style="margin-right: 5px;" target="_blank"><i class="fas fa-print me-1"></i>Recibo</a>
							</td>
						</tr>';	
				}elseif($rubro == 'RPC'){
					$tabla.='
						<tr '.$class.'>
							<td>'.$rows['fila_numero'].'</td>
							<td>'.$rows['pago_fecharegistro'].'</td>							
							<td>'.$rows['torneo_nombre'].'</td>							
							<td><a href="'.$imagen.'" data-bs-toggle="lightbox" data-title="Pago" data-gallery="gallery">'.$rows['pago_valor'].'</a></td>
							<td>'.$rows['pago_saldo'].'</td>						
							<td>'.$rows['pago_recibo'].'</td>
							<td>'.$estado.'</td>
							<td>
								'.$this->siPuede('eliminar','pagosList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/pagosAjax.php" method="POST" autocomplete="off" >
									<input type="hidden" name="modulo_pagos" value="eliminar">
									<input type="hidden" name="pago_id" value="'.$rows['pago_id'].'">						
									<button type="submit" class="btn float-end btn-danger btn-sm " style="margin-right: 5px;" '.$eliminarpago.' title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
								</form>').'							

								'.$this->siPuede('editar','pagosList','<a href="'.APP_URL.'pagosUpdate/'.$rows['pago_id'].'/" class="btn float-end btn-success btn-sm '.$eliminarpago.'" style="margin-right: 5px;"  title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
								'.$btnPagar.'
								<a href="'.APP_URL.'pagosRecibo/'.$rows['pago_id'].'/" class="btn float-end btn-secondary btn-sm" style="margin-right: 5px;" target="_blank"><i class="fas fa-print me-1"></i>Recibo</a>
							</td>
						</tr>';	
				}elseif($rubro == 'RNU'){
					$tabla.='
						<tr '.$class.'>
							<td>'.$rows['fila_numero'].'</td>
							<td>'.$rows['pago_fecharegistro'].'</td>
							<td>'.$rows['pago_periodo'].'</td>
							<td>'.$rows['pago_talla'].'</td>
							<td><a href="'.$imagen.'" data-bs-toggle="lightbox" data-title="Pago" data-gallery="gallery">'.$rows['pago_valor'].'</a></td>
							<td>'.$rows['pago_saldo'].'</td>
							<td>'.$rows['pago_recibo'].'</td>							
							<td>'.$estado.'</td>
							<td>
								'.$this->siPuede('eliminar','pagosList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/pagosAjax.php" method="POST" autocomplete="off" >
									<input type="hidden" name="modulo_pagos" value="eliminar">
									<input type="hidden" name="pago_id" value="'.$rows['pago_id'].'">						
									<button type="submit" class="btn float-end btn-danger btn-sm " style="margin-right: 5px;" '.$eliminarpago.' title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
								</form>').'							

								'.$this->siPuede('editar','pagosList','<a href="'.APP_URL.'pagosUpdate/'.$rows['pago_id'].'/" class="btn float-end btn-success btn-sm '.$eliminarpago.'" style="margin-right: 5px;"  title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
								'.$btnPagar.'
								<a href="'.APP_URL.'pagosRecibo/'.$rows['pago_id'].'/" class="btn float-end btn-secondary btn-sm" style="margin-right: 5px;" target="_blank"><i class="fas fa-print me-1"></i>Recibo</a>
							</td>
						</tr>';	
				}else{
					$tabla.='
					<tr '.$class.'>
						<td>'.$rows['fila_numero'].'</td>
						<td>'.$rows['pago_fecharegistro'].'</td>
						<td>'.$rows['pago_periodo'].'</td>
						<td><a href="'.$imagen.'" data-bs-toggle="lightbox" data-title="Pago" data-gallery="gallery">'.$rows['pago_valor'].'</a></td>
						<td>'.$rows['pago_saldo'].'</td>
						<td>'.$rows['pago_recibo'].'</td>						
						<td>'.$estado.'</td>
						<td>
							'.$this->siPuede('eliminar','pagosList','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/pagosAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_pagos" value="eliminar">
								<input type="hidden" name="pago_id" value="'.$rows['pago_id'].'">						
								<button type="submit" class="btn float-end btn-danger btn-sm " style="margin-right: 5px;" '.$eliminarpago.' title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>').'							

							'.$this->siPuede('editar','pagosList','<a href="'.APP_URL.'pagosUpdate/'.$rows['pago_id'].'/" class="btn float-end btn-success btn-sm '.$eliminarpago.'" style="margin-right: 5px;"  title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>').'
							'.$btnPagar.'
							<a href="'.APP_URL.'pagosRecibo/'.$rows['pago_id'].'/" class="btn float-end btn-secondary btn-sm" style="margin-right: 5px;" target="_blank"><i class="fas fa-print me-1"></i>Recibo</a>
						</td>
					</tr>';	
				}
			}
			return $tabla;			
		}

		public function eliminarPagoControlador(){
			$pagoid=$this->limpiarCadena($_POST['pago_id']);
			$pago_datos=[
				[
					"campo_nombre"=>"pago_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> 'E'
				]
			];

			$condicion=[
				"condicion_campo"=>"pago_id",
				"condicion_marcador"=>":Pagoid",
				"condicion_valor"=>$pagoid
			];

			if($this->actualizarDatos("alumno_pago", $pago_datos, $condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Pago actualizado",
					"texto"=>"La pensión fue eliminada correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar la pensión, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}

		public function eliminarPagoPendiente(){	

			$transaccion_id=$this->limpiarCadena($_POST['transaccion_id']);
			$transaccion_pagoid=$this->limpiarCadena($_POST['transaccion_pagoid']);

			$datos = $this->ejecutarConsulta("SELECT * FROM alumno_pago_transaccion WHERE transaccion_id = :trx", [':trx' => (int)$transaccion_id]);			
			if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el pago en el sistema: ".$transaccion_id,
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();				
		    }

			
			$pago_datos=[
				[
					"campo_nombre"=>"transaccion_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> 'E'
				]
			];

			$condicion=[
				"condicion_campo"=>"transaccion_id",
				"condicion_marcador"=>":Transaccion_id",
				"condicion_valor"=>$transaccion_id
			];

			if($this->actualizarDatos("alumno_pago_transaccion", $pago_datos, $condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Pago eliminado",
					"texto"=>"El rubro fue eliminado correctamente",
					"icono"=>"success"
				];

				$check_pago=$this->ejecutarConsulta("SELECT pago_valor, pago_saldo FROM alumno_pago WHERE pago_id = :pago", [':pago' => (int)$transaccion_pagoid]);
				//$check_pago=$this->seleccionarDatos("Unico","general_escuela","escuela_id","1");
				if($check_pago->rowCount()>0){				
					foreach($check_pago as $rows){	
						$pago_saldo = $rows["pago_saldo"]; 	
						$pago_valor = $rows["pago_valor"];
					}					
				}			
				// Actualizar saldo del rubro				
				$pago_saldo += $datos["transaccion_valor"]; 	
				$pago_valor -= $datos["transaccion_valor"];

				// Actualizar saldo y valor
				$this->ejecutarConsulta("UPDATE alumno_pago SET pago_valor = :valor, pago_saldo = :saldo, pago_estado = 'P' WHERE pago_id = :pago", [':valor' => $pago_valor, ':saldo' => $pago_saldo, ':pago' => (int)$transaccion_pagoid]);
			
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar el pago, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function BuscarPago($pagoid){

			/* El id llega de la URL. Antes se concatenaba tal cual, y
			   limpiarCadena() no defiende de nada aqui: aplica
			   htmlspecialchars, que es para HTML, no para SQL. Con un id
			   vacio la consulta quedaba en "WHERE P.pago_id = " y con texto
			   se colaba en la clausula. Va como parametro ligado. */
			$consulta_datos="SELECT R.catalogo_descripcion RUBRO, T.torneo_nombre, P.*
					FROM alumno_pago P
						INNER JOIN general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid
						LEFT JOIN torneo_torneo T ON T.torneo_id = P.pago_campeonatoid
					WHERE P.pago_id = :pago";

			$datos = $this->ejecutarConsulta($consulta_datos, [':pago' => (int)$pagoid]);
			return $datos;
		}

		/*==============================================================
		| ENLACE PÚBLICO DEL RECIBO, PARA ENVIARLO POR WHATSAPP
		|
		| Un enlace wa.me sólo lleva texto: no puede adjuntar el PDF. Para
		| que el representante pueda descargar su recibo sin cuenta en el
		| sistema, el mensaje lleva un enlace FIRMADO que sirve únicamente
		| ese recibo y caduca solo.
		|
		| Formato:  {pago_id}-{vence_unix}-{firma}
		|   firma = 32 primeros hex de HMAC-SHA256(TOKEN_SECRET) sobre
		|           «recibo-publico|{pago_id}|{vence}»
		|
		| El prefijo «recibo-publico» separa esta firma de la de los
		| enlaces de inscripción, que usan el mismo secreto: una no puede
		| hacerse pasar por la otra. Sólo dígitos, hex y guiones, que es lo
		| que deja pasar el .htaccess del módulo.
		|==============================================================*/
		const RECIBO_ENLACE_DIAS = 7;

		/*
		| Dos clases de recibo, dos firmas que no se pueden intercambiar:
		|   pago   {pago_id}-{vence}-{firma}         recibo del pago (pagosRecibo)
		|   abono  A{transaccion_id}-{vence}-{firma} recibo de un abono a un pago
		|                                             pendiente (pagospendienteRecibo)
		| La del pago conserva su texto original para que los enlaces ya
		| enviados sigan valiendo. La del abono lleva además «abono» dentro de
		| lo firmado: sin eso, el abono 378 y el pago 378 compartirían firma.
		*/
		private function firmaRecibo(int $id, int $vence, string $tipo = 'pago'): string
		{
			$texto = $tipo === 'abono'
				? 'recibo-publico|abono|' . $id . '|' . $vence
				: 'recibo-publico|' . $id . '|' . $vence;
			return substr(hash_hmac('sha256', $texto, TOKEN_SECRET), 0, 32);
		}

		/** URL pública firmada del PDF de un recibo y su fecha de caducidad. */
		public function enlacePublicoRecibo(int $id, int $dias = self::RECIBO_ENLACE_DIAS, string $tipo = 'pago'): array
		{
			if (!defined('TOKEN_SECRET') || TOKEN_SECRET === '' || $id <= 0) {
				return ['url' => '', 'vence' => 0];
			}
			/* Caduca al final del día, para que «válido hasta el 27» no
			   deje de funcionar a media mañana del 27. */
			$vence  = strtotime('today 23:59:59 +' . $dias . ' days');
			$prefijo = $tipo === 'abono' ? 'A' : '';
			return ['url'   => APP_URL . 'reciboPublico/' . $prefijo . $id . '-' . $vence . '-' . $this->firmaRecibo($id, $vence, $tipo) . '/',
			        'vence' => $vence];
		}

		/** Lo mismo para el recibo de un abono. */
		public function enlacePublicoAbono(int $transaccionId, int $dias = self::RECIBO_ENLACE_DIAS): array
		{
			return $this->enlacePublicoRecibo($transaccionId, $dias, 'abono');
		}

		/**
		 * Qué recibo autoriza un token: ['tipo' => 'pago'|'abono', 'id' => int],
		 * o null si es falso, está alterado, caducó o el registro se anuló.
		 */
		public function reciboDeEnlacePublico(string $token): ?array
		{
			if (!defined('TOKEN_SECRET') || TOKEN_SECRET === ''
			    || !preg_match('/^(A?)(\d{1,10})-(\d{9,11})-([0-9a-f]{32})$/', $token, $m)) {
				return null;
			}
			[$_, $marca, $id, $vence, $firma] = $m;
			$tipo = $marca === 'A' ? 'abono' : 'pago';
			if ((int)$vence < time() || !hash_equals($this->firmaRecibo((int)$id, (int)$vence, $tipo), $firma)) {
				return null;
			}
			/* Anulado el pago —o el abono—, deja de haber recibo aunque el
			   enlace no haya caducado. */
			$sql = $tipo === 'abono'
				? "SELECT COUNT(1) FROM alumno_pago_transaccion PT
				     JOIN alumno_pago P ON P.pago_id = PT.transaccion_pagoid
				    WHERE PT.transaccion_id = :i AND PT.transaccion_estado <> 'E' AND P.pago_estado <> 'E'"
				: "SELECT COUNT(1) FROM alumno_pago WHERE pago_id = :i AND pago_estado <> 'E'";
			$vivo = $this->ejecutarConsulta($sql, [':i' => (int)$id])->fetchColumn();
			return (int)$vivo === 1 ? ['tipo' => $tipo, 'id' => (int)$id] : null;
		}

		public function generarRecibo($pagoid){
			$consulta_datos="SELECT sede_nombre,
						 IFNULL(T.total, 0) TOTAL_PP,
						(P.pago_saldo + P.pago_valor) DEUDA_INICIAL,
						((P.pago_saldo + P.pago_valor) - (IFNULL(PT.transaccion_valorcalculado, P.pago_saldo)))PAGO_INICIAL,
						IFNULL(PT.transaccion_valorcalculado, P.pago_saldo) SALDO_INICIAL,
						R.catalogo_descripcion RUBRO,
						F.catalogo_descripcion FORMAPAGO,
						concat(E.repre_primernombre, ' ', E.repre_segundonombre, ' ', E.repre_apellidopaterno, ' ', E.repre_apellidomaterno) REPRESENTANTE,
						E.repre_correo CORREO_REP,
						E.repre_celular CELULAR_REP,
						P.pago_periodo,
						P.pago_fecharegistro,
						P.pago_recibo,
						P.pago_archivo,
						P.pago_fecha,
						P.pago_concepto,
						A.alumno_apellidomaterno,
						A.alumno_apellidopaterno,
						A.alumno_primernombre,
						A.alumno_segundonombre,
						A.alumno_fechanacimiento,
						TOR.torneo_nombre,
						P.*,
						A.*
						FROM alumno_pago P	
							INNER JOIN sujeto_alumno A ON A.alumno_id = P.pago_alumnoid
							INNER JOIN general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 
							INNER JOIN general_tabla_catalogo F ON F.catalogo_valor = P.pago_formapagoid
							INNER JOIN general_sede S on S.sede_id = A.alumno_sedeid 
							LEFT JOIN alumno_representante E on E.repre_id = A.alumno_repreid
							LEFT JOIN torneo_torneo TOR ON TOR.torneo_id = P.pago_campeonatoid   /* TOR y no T: el alias T es el de las transacciones de abajo, y MariaDB / MySQL 5.7 rechazan un alias repetido */
							LEFT JOIN(SELECT COUNT(1) total, PT.transaccion_pagoid, MIN(PT.transaccion_id) IDT
								FROM alumno_pago_transaccion PT
								WHERE PT.transaccion_estado = 'C'
								GROUP BY PT.transaccion_pagoid)T ON T.transaccion_pagoid = P.pago_id
								LEFT JOIN alumno_pago_transaccion PT ON PT.transaccion_id  = T.IDT
								WHERE P.pago_id = :pago";

			$datos = $this->ejecutarConsulta($consulta_datos, [':pago' => (int)$pagoid]);		
			return $datos;
		}

		public function generarReciboPendiente($transaccion_id){
		
			$consulta_datos="SELECT sede_nombre, R.catalogo_descripcion RUBRO, F.catalogo_descripcion FORMAPAGO, 
					concat(E.repre_primernombre, ' ', E.repre_segundonombre, ' ', E.repre_apellidopaterno, ' ', E.repre_apellidomaterno) REPRESENTANTE,
					E.repre_correo CORREO_REP, E.repre_celular CELULAR_REP, T.torneo_nombre TORNEO,
					P.*, A.*, PT.* 
				FROM alumno_pago P	
					INNER JOIN sujeto_alumno A ON A.alumno_id = P.pago_alumnoid 
					INNER JOIN alumno_pago_transaccion PT ON PT.transaccion_pagoid = P.pago_id
					INNER JOIN general_sede S on S.sede_id = A.alumno_sedeid 
					LEFT JOIN alumno_representante E on E.repre_id = A.alumno_repreid
 					INNER JOIN general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 
					INNER JOIN general_tabla_catalogo F ON F.catalogo_valor = PT.transaccion_formapagoid	
					LEFT JOIN torneo_torneo T ON T.torneo_id = P.pago_campeonatoid			
				WHERE PT.transaccion_id = :transaccion";	

			$datos = $this->ejecutarConsulta($consulta_datos, [':transaccion' => (int)$transaccion_id]);		
			return $datos;
		}

		public function informacionEscuela(){		
			$consulta_datos="SELECT * FROM general_escuela WHERE escuela_id  = 1";
			$datos = $this->ejecutarConsulta($consulta_datos);		
			return $datos;
		}

		public function informacionSede($sedeid){		
			$consulta_datos="SELECT * FROM general_sede WHERE sede_id = :sede";
			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sedeid]);		
			return $datos;
		}

		public function numeroALetras($numero) {
			$unidades = array(
				'', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
				'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco',
				'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'
			);
		
			$decenas = array(
				'', 'diez', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'
			);
		
			$centenas = array(
				'', 'cien', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'
			);
		
			if ($numero == 0) {
				return 'cero';
			}
		
			$numeroEnLetras = '';
		
			if ($numero >= 1000) {
				$miles = floor($numero / 1000);
				$numeroEnLetras .= $miles == 1 ? 'mil' : $unidades[$miles] . ' mil ';
				$numero %= 1000;
			}
		
			if ($numero >= 100) {
				$numeroEnLetras .= $centenas[floor($numero / 100)] . ' ';
				$numero %= 100;
			}
		
			if ($numero >= 30) {
				$numeroEnLetras .= $decenas[floor($numero / 10)];
				$numero %= 10;
				if ($numero > 0) {
					$numeroEnLetras .= ' y ' . $unidades[$numero];
				}
			} else {
				$numeroEnLetras .= $unidades[$numero];
			}
		
			return trim($numeroEnLetras);
		}

		public function textoLetras($numero){
			$partes = explode('.', $numero);
			$entero = intval($partes[0]);
			$decimal = isset($partes[1]) ? intval(str_pad($partes[1], 2, '0', STR_PAD_RIGHT)) : 0;

			$enteroEnLetras = $this->numeroALetras($entero);
			$decimalEnLetras = $decimal > 0 ? $this->numeroALetras($decimal) : '';

			if ($decimalEnLetras) {
				return $enteroEnLetras . ' dólares con ' . $decimalEnLetras . ' centavos';
			} else {
				return $enteroEnLetras ." dólares con cero centavos";
			}
		}

		public function BuscarPagoPendiente($transaccion_id ){
		
			$consulta_datos="SELECT R.catalogo_descripcion RUBRO, T.* 
					FROM alumno_pago_transaccion T 
						INNER JOIN alumno_pago P ON P.pago_id = T.transaccion_pagoid 
						INNER JOIN general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 				
					WHERE T.transaccion_id = :transaccion";	

			$datos = $this->ejecutarConsulta($consulta_datos, [':transaccion' => (int)$transaccion_id]);		
			return $datos;
		}

		public function listarOptionPagoid($idformapago){
			$option="";

			$consulta_datos="SELECT *FROM general_tabla_catalogo WHERE catalogo_tablaid = 6 AND catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){		
				if($idformapago == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}									
			}
			return $option;
		}

		/*----------  Controlador actualizar pago  ----------*/
		public function actualizarPagoControlador(){			
			$pagoid=$this->limpiarCadena($_POST['pago_id']);

			# Verificando pago #
			$datos = $this->ejecutarConsulta("SELECT * FROM alumno_pago WHERE pago_id = :pago", [':pago' => (int)$pagoid]);			
			if($datos->rowCount()<=0){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el pago en el sistema: ".$pagoid,
					"icono"=>"error"
				];
				return json_encode($alerta);
			}else{
				$datos=$datos->fetch();				
			}				

			# Almacenando datos#
			$pago_fecha			= $this->limpiarCadena($_POST['pago_fecha']);
			$pago_fecharegistro	= $this->limpiarCadena($_POST['pago_fecharegistro']);			
			$pago_valor 		= $_POST['pago_valor'];
			$pago_saldo 		= $_POST['pago_saldo'];
			$pago_formapagoid 	= $this->limpiarCadena($_POST['pago_formapagoid']);
			$pago_concepto 		= $this->limpiarCadena($_POST['pago_concepto']);

			if ($pago_valor =="") {$pago_valor = 0;}
			if ($pago_saldo =="") {$pago_saldo = 0;}

			if($pago_valor < 1 && $pago_saldo < 1){
				$estado = "J";
			}elseif($pago_saldo != "" && $pago_saldo > 0){
				$estado = "P";
			}else{
				$estado = "C";
			}
			
			
			# Verificando campos obligatorios #
			if($pago_fecha=="" || $pago_fecharegistro=="" || $pago_valor=="" || $pago_formapagoid=="" ){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
			}			
		
			# Directorio de imagenes #
			$img_dir="../views/imagenes/pagos/";

			# Comprobar si se selecciono una imagen #
			if($_FILES['pago_archivo']['name']=="" && $_FILES['pago_archivo']['size']<=0){
					
				$pago_datos_reg=[
					[
						"campo_nombre"=>"pago_formapagoid",
						"campo_marcador"=>":Formapagoid",
						"campo_valor"=>$pago_formapagoid
					],				
					[
						"campo_nombre"=>"pago_valor",
						"campo_marcador"=>":Valor",
						"campo_valor"=>$pago_valor
					],
					[
						"campo_nombre"=>"pago_saldo",
						"campo_marcador"=>":Saldo",
						"campo_valor"=>$pago_saldo
					],		
					[
						"campo_nombre"=>"pago_concepto",
						"campo_marcador"=>":Concepto",
						"campo_valor"=>$pago_concepto
					],
					[
						"campo_nombre"=>"pago_fecha",
						"campo_marcador"=>":Fecha",
						"campo_valor"=>$pago_fecha
					],				
					[
						"campo_nombre"=>"pago_fecharegistro",
						"campo_marcador"=>":Fecharegistro",
						"campo_valor"=>$pago_fecharegistro
					],					
					[
						"campo_nombre"=>"pago_estado",
						"campo_marcador"=>":Estado",
						"campo_valor"=> $estado
					]
				];
				
			
			}ELSE{
				# Creando directorio #
				if(!file_exists($img_dir)){
					if(!mkdir($img_dir,0777)){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
					} 
				}

				# Verificando formato de imagenes #
				if(mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['pago_archivo']['size']/1024)>3000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				$foto=str_ireplace(" ","_",$datos['pago_id']);
				$foto=$foto."_".rand(0,100);			

				# Extension de la imagen #
				switch(mime_content_type($_FILES['pago_archivo']['tmp_name'])){
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
				$inputFile = ($_FILES['pago_archivo']['tmp_name']);
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
				if(is_file($img_dir.$datos['pago_archivo']) && $datos['pago_archivo']!=$foto){
					chmod($img_dir.$datos['pago_archivo'], 0777);
					unlink($img_dir.$datos['pago_archivo']);
				}	
				
				$pago_datos_reg[]=[						
						"campo_nombre"=>"pago_archivo",
						"campo_marcador"=>":Imagenpago",
						"campo_valor"=>$foto					
					];
			}

			# Verificando si el pago es por Campeonato #
			if($datos['pago_rubroid'] == 'RPC'){
				$pago_campeonatoid = $this->limpiarCadena($_POST['pago_campeonatoid']);
				if($pago_campeonatoid==""){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"No has seleccionado el campeonato al que pertenece el pago",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}else{
					$pago_datos_reg[]=[
						"campo_nombre"=>"pago_campeonatoid",
						"campo_marcador"=>":Campeonatoid",
						"campo_valor"=>$pago_campeonatoid
					];
				}
			}else{
				$pago_periodo = $this->limpiarCadena($_POST['pago_periodo']);
				$pago_datos_reg[]=[						
					"campo_nombre"=>"pago_periodo",
					"campo_marcador"=>":Periodo",
					"campo_valor"=>$pago_periodo					
				];
			}

			$condicion=[
				"condicion_campo"=>"pago_id",
				"condicion_marcador"=>":Pagoid",
				"condicion_valor"=>$pagoid
			];			

			if($this->actualizarDatos("alumno_pago",$pago_datos_reg,$condicion)){				
				
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Pago actualizado",
					"texto"=>"Los datos del pago ".$pagoid." se actualizaron correctamente",
					"icono"=>"success"
				];								

			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar los datos del pago ".$pagoid.", por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);		
		}
		/*----------  Controlador actualizar pago de uniforme ----------*/
		public function actualizarPagoUniforme(){			
			$pagoid=$this->limpiarCadena($_POST['pago_id']);

			# Verificando pago #
			$datos = $this->ejecutarConsulta("SELECT * FROM alumno_pago WHERE pago_id = :pago", [':pago' => (int)$pagoid]);			
			if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el pago en el sistema: ".$pagoid,
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();				
		    }				

			# Almacenando datos#
			$pago_fecha			= $this->limpiarCadena($_POST['pago_fecha']);
			$pago_fecharegistro	= $this->limpiarCadena($_POST['pago_fecharegistro']);
			$pago_periodo 		= $this->limpiarCadena($_POST['pago_periodo']);
			$pago_valor 		= $_POST['pago_valor'];
			$pago_saldo 		= $_POST['pago_saldo'];
			$pago_formapagoid 	= $this->limpiarCadena($_POST['pago_formapagoid']);
			$pago_concepto 		= $this->limpiarCadena($_POST['pago_concepto']);
			
			if(isset($_POST['pago_talla'])){
				$pago_talla = $this->limpiarCadena($_POST['pago_talla']);
			}else{
				$pago_talla = "";
			}

			if ($pago_valor =="") {$pago_valor = 0;}
			if ($pago_saldo =="") {$pago_saldo = 0;}

			if($pago_valor < 1 && $pago_saldo < 1){
				$estado = "J";
			}elseif($pago_saldo != "" && $pago_saldo > 0){
				$estado = "P";
			}else{
				$estado = "C";
			}
			
			# Verificando campos obligatorios #
		    if($pago_fecha=="" || $pago_fecharegistro=="" || $pago_periodo=="" || $pago_valor=="" || $pago_formapagoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }			
		
			# Directorio de imagenes #
			$img_dir="../views/imagenes/pagos/";

    		# Comprobar si se selecciono una imagen #
    		if($_FILES['pago_archivo']['name']=="" && $_FILES['pago_archivo']['size']<=0){
					
				$pago_datos_reg=[
					[
						"campo_nombre"=>"pago_formapagoid",
						"campo_marcador"=>":Formapagoid",
						"campo_valor"=>$pago_formapagoid
					],				
					[
						"campo_nombre"=>"pago_valor",
						"campo_marcador"=>":Valor",
						"campo_valor"=>$pago_valor
					],
					[
						"campo_nombre"=>"pago_saldo",
						"campo_marcador"=>":Saldo",
						"campo_valor"=>$pago_saldo
					],		
					[
						"campo_nombre"=>"pago_concepto",
						"campo_marcador"=>":Concepto",
						"campo_valor"=>$pago_concepto
					],
					[
						"campo_nombre"=>"pago_fecha",
						"campo_marcador"=>":Fecha",
						"campo_valor"=>$pago_fecha
					],				
					[
						"campo_nombre"=>"pago_fecharegistro",
						"campo_marcador"=>":Fecharegistro",
						"campo_valor"=>$pago_fecharegistro
					],
					[
						"campo_nombre"=>"pago_periodo",
						"campo_marcador"=>":Periodo",
						"campo_valor"=>$pago_periodo
					],
					[
						"campo_nombre"=>"pago_talla",
						"campo_marcador"=>":Talla",
						"campo_valor"=>$pago_talla
					],	
					[
						"campo_nombre"=>"pago_estado",
						"campo_marcador"=>":Estado",
						"campo_valor"=> $estado
					]
				];
			
    		}ELSE{
				# Creando directorio #
				if(!file_exists($img_dir)){
					if(!mkdir($img_dir,0777)){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
					} 
				}

				# Verificando formato de imagenes #
				if(mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['pago_archivo']['size']/1024)>3000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				$foto=str_ireplace(" ","_",$datos['pago_id']);
				$foto=$foto."_".rand(0,100);			

				# Extension de la imagen #
				switch(mime_content_type($_FILES['pago_archivo']['tmp_name'])){
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
				$inputFile = ($_FILES['pago_archivo']['tmp_name']);
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
				if(is_file($img_dir.$datos['pago_archivo']) && $datos['pago_archivo']!=$foto){
					chmod($img_dir.$datos['pago_archivo'], 0777);
					unlink($img_dir.$datos['pago_archivo']);
				}				
				
				$pago_datos_reg=[					
					[
						"campo_nombre"=>"pago_formapagoid",
						"campo_marcador"=>":Formapagoid",
						"campo_valor"=>$pago_formapagoid
					],			
								
					[
						"campo_nombre"=>"pago_valor",
						"campo_marcador"=>":Valor",
						"campo_valor"=>$pago_valor
					],
					[
						"campo_nombre"=>"pago_saldo",
						"campo_marcador"=>":Saldo",
						"campo_valor"=>$pago_saldo
					],		
					[
						"campo_nombre"=>"pago_concepto",
						"campo_marcador"=>":Concepto",
						"campo_valor"=>$pago_concepto
					],
					[
						"campo_nombre"=>"pago_fecha",
						"campo_marcador"=>":Fecha",
						"campo_valor"=>$pago_fecha
					],				
					[
						"campo_nombre"=>"pago_fecharegistro",
						"campo_marcador"=>":Fecharegistro",
						"campo_valor"=>$pago_fecharegistro
					],
					[
						"campo_nombre"=>"pago_periodo",
						"campo_marcador"=>":Periodo",
						"campo_valor"=>$pago_periodo
					],
					[
						"campo_nombre"=>"pago_talla",
						"campo_marcador"=>":Talla",
						"campo_valor"=>$pago_talla
					],
					[
						"campo_nombre"=>"pago_estado",
						"campo_marcador"=>":Estado",
						"campo_valor"=>$estado
					],
					[
						"campo_nombre"=>"pago_archivo",
						"campo_marcador"=>":Imagenpago",
						"campo_valor"=>$foto
					]
				];
			}

			$condicion=[
				"condicion_campo"=>"pago_id",
				"condicion_marcador"=>":Pagoid",
				"condicion_valor"=>$pagoid
			];			

			if($this->actualizarDatos("alumno_pago",$pago_datos_reg,$condicion)){				
				
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Pago actualizado",
					"texto"=>"Los datos del pago ".$pagoid." se actualizaron correctamente",
					"icono"=>"success"
				];								

			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar los datos del pago ".$pagoid.", por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);		
		}

		public function actualizarPagoPendiente(){			
			$transaccion_id = $this->limpiarCadena($_POST['transaccion_id']);

			# Verificando pago #
			$datos = $this->ejecutarConsulta("SELECT * FROM alumno_pago_transaccion WHERE transaccion_id = :trx", [':trx' => (int)$transaccion_id]);			
			if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el pago en el sistema: ".$transaccion_id,
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();				
		    }
			
			# Almacenando datos#			
			$transaccion_pagoid 		= $this->limpiarCadena($_POST['transaccion_pagoid']);
			$transaccion_valorcalculado = $this->limpiarCadena($_POST['transaccion_valorcalculado']);
			$valor						= $this->limpiarCadena($_POST['transaccion_valor']);
			$transaccion_fecha			= $this->limpiarCadena($_POST['pago_fecha']);
			$transaccion_fecharegistro	= $this->limpiarCadena($_POST['pago_fecharegistro']);
			$transaccion_periodo		= $this->limpiarCadena($_POST['pago_periodo']);
			$transaccion_valor 			= $_POST['pago_valor'];
			$transaccion_formapagoid 	= $this->limpiarCadena($_POST['pago_formapagoid']);
			$transaccion_concepto 		= $this->limpiarCadena($_POST['pago_concepto']);				

			$saldo = $transaccion_valorcalculado - $transaccion_valor;			

			if ($saldo == 0){
				$estado_saldo='C';
			}else{
				$estado_saldo = 'P';
			}
						
			# Verificando campos obligatorios #
		    if($transaccion_fecha=="" || $transaccion_fecharegistro=="" || $transaccion_periodo=="" || $transaccion_valor=="" || $transaccion_formapagoid=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }	
			
			if($saldo < 0 ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error en el valor ingresado",
					"texto"=>"El valor ingresado supera el monto del saldo",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }
		
			# Directorio de imagenes #
			$img_dir="../views/imagenes/pagos/";

    		# Comprobar si se selecciono una imagen #
    		if($_FILES['pago_archivo']['name']=="" && $_FILES['pago_archivo']['size']<=0){
					
				$pago_datos_reg=[								
					[
						"campo_nombre"=>"transaccion_valor",
						"campo_marcador"=>":Valor",
						"campo_valor"=>$transaccion_valor
					],
					[
						"campo_nombre"=>"transaccion_fecha",
						"campo_marcador"=>":Fecha",
						"campo_valor"=>$transaccion_fecha
					],				
					[
						"campo_nombre"=>"transaccion_fecharegistro",
						"campo_marcador"=>":transaccion_fecharegistro",
						"campo_valor"=>$transaccion_fecharegistro
					],		
					[
						"campo_nombre"=>"transaccion_formapagoid",
						"campo_marcador"=>":Formapagoid",
						"campo_valor"=>$transaccion_formapagoid
					],	
					[
						"campo_nombre"=>"transaccion_concepto",
						"campo_marcador"=>":Concepto",
						"campo_valor"=>$transaccion_concepto
					],					
					[
						"campo_nombre"=>"transaccion_periodo",
						"campo_marcador"=>":Periodo",
						"campo_valor"=>$transaccion_periodo
					]
				];
			
    		}ELSE{
				# Creando directorio #
				if(!file_exists($img_dir)){
					if(!mkdir($img_dir,0777)){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error inesperado",
							"texto"=>"Error al crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
					} 
				}

				# Verificando formato de imagenes #
				if(mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['pago_archivo']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['pago_archivo']['size']/1024)>3000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 3MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				$foto=str_ireplace(" ","_","pagopendiente".$transaccion_id);
				$foto=$foto."_".rand(0,100);				

				# Extension de la imagen #
				switch(mime_content_type($_FILES['pago_archivo']['tmp_name'])){
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
				$inputFile = ($_FILES['pago_archivo']['tmp_name']);
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
				if(is_file($img_dir.$datos['transaccion_archivo']) && $datos['transaccion_archivo']!=$foto){
					chmod($img_dir.$datos['transaccion_archivo'], 0777);
					unlink($img_dir.$datos['transaccion_archivo']);
				}				
				
				$pago_datos_reg=[					
					[
						"campo_nombre"=>"transaccion_valor",
						"campo_marcador"=>":Valor",
						"campo_valor"=>$transaccion_valor
					],
					[
						"campo_nombre"=>"transaccion_fecha",
						"campo_marcador"=>":Fecha",
						"campo_valor"=>$transaccion_fecha
					],				
					[
						"campo_nombre"=>"transaccion_fecharegistro",
						"campo_marcador"=>":transaccion_fecharegistro",
						"campo_valor"=>$transaccion_fecharegistro
					],		
					[
						"campo_nombre"=>"transaccion_formapagoid",
						"campo_marcador"=>":Formapagoid",
						"campo_valor"=>$transaccion_formapagoid
					],	
					[
						"campo_nombre"=>"transaccion_concepto",
						"campo_marcador"=>":Concepto",
						"campo_valor"=>$transaccion_concepto
					],
					
					[
						"campo_nombre"=>"transaccion_periodo",
						"campo_marcador"=>":Periodo",
						"campo_valor"=>$transaccion_periodo
					],
					[
						"campo_nombre"=>"transaccion_archivo",
						"campo_marcador"=>":Imagenpago",
						"campo_valor"=>$foto
					]
				];
			}
			$condicion=[
				"condicion_campo"=>"transaccion_id",
				"condicion_marcador"=>":Pagoid",
				"condicion_valor"=>$transaccion_id 
			];			

			if($this->actualizarDatos("alumno_pago_transaccion",$pago_datos_reg,$condicion)){				
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Pago actualizado",
					"texto"=>"Los datos del pago ".$transaccion_id ." se actualizaron correctamente",
					"icono"=>"success"
				];		
				
				$check_pago=$this->ejecutarConsulta("SELECT pago_valor, pago_saldo FROM alumno_pago WHERE pago_id = :pago", [':pago' => (int)$transaccion_pagoid]);
				//$check_pago=$this->seleccionarDatos("Unico","general_escuela","escuela_id","1");
				if($check_pago->rowCount()>0){				
					foreach($check_pago as $rows){	
						$pago_saldo = $rows["pago_saldo"]; 	
						$pago_valor = $rows["pago_valor"];
					}					
				}			

				if($valor > $transaccion_valor){
					$nuevo_valor = $valor - $transaccion_valor;	
					$pago_saldo += $nuevo_valor; 	
					$pago_valor -= $nuevo_valor;									
				}elseif($valor < $transaccion_valor){
					$nuevo_valor = $transaccion_valor - $valor;
					$pago_saldo -= $nuevo_valor; 	
					$pago_valor += $nuevo_valor;
				}				

				// Actualizar saldo y valor
				$this->ejecutarConsulta("UPDATE alumno_pago SET pago_valor = :valor, pago_saldo = :saldo, pago_estado = :estado WHERE pago_id = :pago", [':valor' => $pago_valor, ':saldo' => $pago_saldo, ':estado' => $estado_saldo, ':pago' => (int)$transaccion_pagoid]);
			
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar los datos del pago ".$transaccion_id.", por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}
	
		public function actualizarDescuento(){		
			
			$descuento_alumnoid = $this->limpiarCadena($_POST['descuento_alumnoid']);

			# Verificando pago #
			$datos = $this->ejecutarConsulta("SELECT * FROM alumno_pago_descuento WHERE descuento_alumnoid = :alumno", [':alumno' => (int)$descuento_alumnoid]);			
			if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos encontrado el descuento en el sistema: ".$descuento_alumnoid,
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();				
		    }				

			# Almacenando datos#
			$descuento_rubroid	= $this->limpiarCadena($_POST['descuento_rubroid']);
			$descuento_valor	= $this->limpiarCadena($_POST['descuento_valor']);
			$descuento_fecha 	= $this->limpiarCadena($_POST['descuento_fecha']);
			$descuento_detalle 	= $this->limpiarCadena($_POST['descuento_detalle']);			
			$descuento_estado 	= $this->limpiarCadena($_POST['descuento_estado']);			
			
			if ($descuento_valor =="") {$descuento_valor = 0;}
			
			# Verificando campos obligatorios #
		    if($descuento_rubroid=="" || $descuento_valor=="" || $descuento_fecha=="" || $descuento_estado=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No has llenado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }			

			$descuento_datos_reg=[
				[
					"campo_nombre"=>"descuento_rubroid",
					"campo_marcador"=>":Rubroid",
					"campo_valor"=>$descuento_rubroid
				],
				[
					"campo_nombre"=>"descuento_alumnoid",
					"campo_marcador"=>":Alumnoid",
					"campo_valor"=>$descuento_alumnoid
				],						
				[
					"campo_nombre"=>"descuento_valor",
					"campo_marcador"=>":Valor",
					"campo_valor"=>$descuento_valor
				],		
				[
					"campo_nombre"=>"descuento_detalle",
					"campo_marcador"=>":Detalle",
					"campo_valor"=>$descuento_detalle
				],
				[
					"campo_nombre"=>"descuento_fecha",
					"campo_marcador"=>":Fecha",
					"campo_valor"=>$descuento_fecha
				],				
				[
					"campo_nombre"=>"descuento_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$descuento_estado
				]
			];	

			$condicion=[
				"condicion_campo"=>"descuento_alumnoid ",
				"condicion_marcador"=>":Alumnoid ",
				"condicion_valor"=>$descuento_alumnoid 
			];			

			if($this->actualizarDatos("alumno_pago_descuento",$descuento_datos_reg,$condicion)){				
				
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Descuento actualizado",
					"texto"=>"Los datos del descuento se actualizaron correctamente",
					"icono"=>"success"
				];								

			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar los datos del descuento, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}
		
	}
			