<?php

	namespace app\controllers;
	use app\models\mainModel;
	use DateTime;

	class reporteController extends mainModel{	
		public function listarPagos($fecha_inicio, $fecha_fin, $sede_id){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$VALOR_PAGADO = 0;
			$VALOR_PENDIENTE = 0;
			$consulta_datos="SELECT sede_nombre SEDE, A.alumno_identificacion IDENTIFICACION,
								concat(A.alumno_primernombre, ' ', A.alumno_segundonombre, ' ', A.alumno_apellidopaterno, ' ', A.alumno_apellidomaterno) ALUMNO,
								pago_fecha FECHA_PAGO, 
								pago_fecharegistro FECHA_REG_SISTEMA, 
								pago_periodo PERIODO,  
								R.catalogo_descripcion RUBRO,  
								F.catalogo_descripcion FORMA_PAGO, 
								((P.pago_saldo + P.pago_valor) - (IFNULL(PT.transaccion_valorcalculado, P.pago_saldo)))VALOR_PAGADO, 
								IFNULL(PT.transaccion_valorcalculado, P.pago_saldo) VALOR_PENDIENTE,
								case IFNULL(PT.transaccion_valorcalculado, P.pago_saldo) when 0 then 'Cancelado' else 'Pendiente' end ESTADO_PAGO
							from alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 
								inner join general_tabla_catalogo F ON F.catalogo_valor = P.pago_formapagoid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join general_sede S on S.sede_id = alumno_sedeid
								LEFT JOIN(SELECT COUNT(1) total, PT.transaccion_pagoid, MIN(PT.transaccion_id) IDT
								FROM alumno_pago_transaccion PT
								WHERE PT.transaccion_estado = 'C'
								GROUP BY PT.transaccion_pagoid)T ON T.transaccion_pagoid = P.pago_id
								LEFT JOIN alumno_pago_transaccion PT ON PT.transaccion_id  = T.IDT
							where pago_estado <> 'E'
								and alumno_sedeid = :p1
								and pago_fecharegistro between :p2 and :p3
							
							union all 
														
							SELECT sede_nombre SEDE, A.alumno_identificacion IDENTIFICACION,
								concat(A.alumno_primernombre, ' ', A.alumno_segundonombre, ' ', A.alumno_apellidopaterno, ' ', A.alumno_apellidomaterno) ALUMNO,
								transaccion_fecha FECHA_PAGO, 
								transaccion_fecharegistro FECHA_REG_SISTEMA, 
								transaccion_periodo PERIODO,  
								R.catalogo_descripcion RUBRO,  
								F.catalogo_descripcion FORMA_PAGO, 
								transaccion_valor VALOR_PAGADO, 
								transaccion_valorcalculado - transaccion_valor VALOR_PENDIENTE,
								case (transaccion_valorcalculado - transaccion_valor) when 0 then 'Cancelado' else 'Pendiente' end ESTADO_PAGO
							from alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join alumno_pago_transaccion T on T.transaccion_pagoid = P.pago_id 
								inner join general_tabla_catalogo F ON F.catalogo_valor = T.transaccion_formapagoid 								
								inner join general_sede S on S.sede_id = alumno_sedeid
							where transaccion_estado <> 'E'
								and alumno_sedeid = :p4
								and transaccion_fecharegistro between :p5 and :p6";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $sede_id, ':p2' => $fecha_inicio, ':p3' => $fecha_fin, ':p4' => $sede_id, ':p5' => $fecha_inicio, ':p6' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$VALOR_PAGADO += $rows['VALOR_PAGADO'];
				$VALOR_PENDIENTE += $rows['VALOR_PENDIENTE'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['IDENTIFICACION'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['FECHA_PAGO'].'</td>
						<td>'.$rows['FECHA_REG_SISTEMA'].'</td>
						<td>'.$rows['PERIODO'].'</td>
						<td>'.$rows['RUBRO'].'</td>
						<td>'.$rows['FORMA_PAGO'].'</td>
						<td style="text-align: right">'.$rows['VALOR_PAGADO'].'</td>
						<td style="text-align: right">'.$rows['VALOR_PENDIENTE'].'</td>
						<td>'.$rows['ESTADO_PAGO'].'</td>
					</tr>';	
			}
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td ></td>
					<td ></td>
					<td ></td>
					<td ></td>
					<td ></td>
					<td ></td>
					<td ></td>
					<td >TOTAL</td>
					<td style="text-align: right">'.number_format($VALOR_PAGADO, 2, '.',',').'</td>
					<td style="text-align: right">'.number_format($VALOR_PENDIENTE, 2, '.',',').'</td>
					<td> </td>				
				</tr>';	

			return $tabla;			
		}

		public function listarPagosConsolidado($fecha_inicio, $fecha_fin){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$VALOR_PAGADO = 0;
			$VALOR_PENDIENTE = 0;
			$consulta_datos="SELECT sede_nombre SEDE, A.alumno_identificacion IDENTIFICACION,
								concat(A.alumno_primernombre, ' ', A.alumno_segundonombre, ' ', A.alumno_apellidopaterno, ' ', A.alumno_apellidomaterno) ALUMNO,
								pago_fecha FECHA_PAGO, 
								pago_fecharegistro FECHA_REG_SISTEMA, 
								pago_periodo PERIODO,  
								R.catalogo_descripcion RUBRO,  
								F.catalogo_descripcion FORMA_PAGO, 
								pago_concepto CONCEPTO,
								((P.pago_saldo + P.pago_valor) - (IFNULL(PT.transaccion_valorcalculado, P.pago_saldo)))VALOR_PAGADO, 
								IFNULL(PT.transaccion_valorcalculado, P.pago_saldo) VALOR_PENDIENTE,
								case IFNULL(PT.transaccion_valorcalculado, P.pago_saldo) when 0 then 'Cancelado' else 'Pendiente' end ESTADO_PAGO
							from alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 
								inner join general_tabla_catalogo F ON F.catalogo_valor = P.pago_formapagoid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid
								inner join general_sede S on S.sede_id = alumno_sedeid 
								LEFT JOIN(SELECT COUNT(1) total, PT.transaccion_pagoid, MIN(PT.transaccion_id) IDT
								FROM alumno_pago_transaccion PT
								WHERE PT.transaccion_estado = 'C'
								GROUP BY PT.transaccion_pagoid)T ON T.transaccion_pagoid = P.pago_id
								LEFT JOIN alumno_pago_transaccion PT ON PT.transaccion_id  = T.IDT
							where pago_estado <> 'E'
								and pago_fecha between :p1 and :p2
							
							union all 
														
							SELECT sede_nombre SEDE, A.alumno_identificacion IDENTIFICACION,
								concat(A.alumno_primernombre, ' ', A.alumno_segundonombre, ' ', A.alumno_apellidopaterno, ' ', A.alumno_apellidomaterno) ALUMNO,
								transaccion_fecha FECHA_PAGO, 
								transaccion_fecharegistro FECHA_REG_SISTEMA, 
								transaccion_periodo PERIODO,  
								R.catalogo_descripcion RUBRO,  
								F.catalogo_descripcion FORMA_PAGO, 
								transaccion_concepto CONCEPTO,
								transaccion_valor VALOR_PAGADO, 
								transaccion_valorcalculado - transaccion_valor VALOR_PENDIENTE,
								case (transaccion_valorcalculado - transaccion_valor) when 0 then 'Cancelado' else 'Pendiente' end ESTADO_PAGO
							from alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 							
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join alumno_pago_transaccion T on T.transaccion_pagoid = P.pago_id								
								inner join general_tabla_catalogo F ON F.catalogo_valor = T.transaccion_formapagoid 
								inner join general_sede S on S.sede_id = alumno_sedeid
							where transaccion_estado <> 'E'
								and transaccion_fecha between :p3 and :p4
								ORDER BY FECHA_PAGO DESC";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $fecha_inicio, ':p2' => $fecha_fin, ':p3' => $fecha_inicio, ':p4' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$VALOR_PAGADO += $rows['VALOR_PAGADO'];
				$VALOR_PENDIENTE += $rows['VALOR_PENDIENTE'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['IDENTIFICACION'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['FECHA_PAGO'].'</td>
						<td>'.$rows['FECHA_REG_SISTEMA'].'</td>
						<td>'.$rows['PERIODO'].'</td>
						<td>'.$rows['RUBRO'].'</td>
						<td>'.$rows['FORMA_PAGO'].'</td>
						<td style="text-align: right">'.$rows['VALOR_PAGADO'].'</td>
						<td style="text-align: right">'.$rows['VALOR_PENDIENTE'].'</td>
						<td>'.$rows['ESTADO_PAGO'].'</td>
					</tr>';	
			}
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td ></td>
					<td ></td>
					<td ></td>
					<td ></td>
					<td ></td>
					<td ></td>
					<td ></td>
					<td >TOTAL</td>
					<td style="text-align: right">'.number_format($VALOR_PAGADO, 2, '.',',').'</td>
					<td style="text-align: right">'.number_format($VALOR_PENDIENTE, 2, '.',',').'</td>
					<td> </td>				
				</tr>';	
			return $tabla;			
		}

		public function fechaPagosReceptados($sede_id){		
			$consulta_fecham="SELECT max(pago_fecharegistro) AS FECHA_MAXIMA
								FROM alumno_pago, sujeto_alumno
								WHERE pago_alumnoid = alumno_id
									AND alumno_sedeid = :p1
								ORDER BY pago_fecharegistro";
			$fecha_maxima = $this->ejecutarConsulta($consulta_fecham, [':p1' => $sede_id]);		
			return $fecha_maxima;
		}

		public function valoresPendientes($sedeid){		
			$tabla="";
			$NUM_SALDO = 0;
			$SALDO = 0;
			$NUM_PENSION = 0;
			$PENSION = 0;
			$consulta_datos="SELECT 
									alumno_id, 
									alumno_identificacion, 
									CONCAT_WS(' ', alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) AS NOMBRES,  
									IFNULL(P.TOTAL,0) AS NUM_SALDO, 
									IFNULL(P.SALDO,0) AS SALDO, 
									IFNULL(PEN.PENSIONES,0) AS NUM_PENSION, 
									IFNULL(PEN.TOTAL,0) AS PENSION, 
									PEN.FECHA
								FROM sujeto_alumno A
								LEFT JOIN (
									SELECT 
									pago_alumnoid, 
									COUNT(pago_saldo) AS TOTAL, 
									SUM(pago_saldo) AS SALDO
									FROM alumno_pago
										INNER JOIN sujeto_alumno ON alumno_id = pago_alumnoid
									WHERE pago_estado = 'P' AND pago_saldo > 0 AND (:p1 = 0 OR alumno_sedeid = :p1) 
									GROUP BY pago_alumnoid
								) P ON P.pago_alumnoid = A.alumno_id
								LEFT JOIN (
									SELECT 
									BASE.FECHA,
									BASE.pago_alumnoid,
									CASE WHEN BASE.FECHA > CURDATE() THEN 0 ELSE
										GREATEST(0, TIMESTAMPDIFF(MONTH, BASE.FECHA, CURDATE()) + (DAY(CURDATE()) < DAY(BASE.FECHA))) END AS PENSIONES,
									CASE WHEN BASE.FECHA > CURDATE() THEN 0 ELSE
										GREATEST(0, TIMESTAMPDIFF(MONTH, BASE.FECHA, CURDATE()) + (DAY(CURDATE()) < DAY(BASE.FECHA))) * COALESCE(BASE.descuento_valor, BASE.sede_pension) END AS TOTAL
									FROM (
									SELECT 
										MAX(pago_fecha) AS FECHA, 
										pago_alumnoid, 
										MAX(descuento_valor) AS descuento_valor, 
										MAX(sede_pension) AS sede_pension  
									FROM 
										sujeto_alumno
										LEFT JOIN alumno_pago ON pago_alumnoid = alumno_id 
										LEFT JOIN alumno_pago_descuento ON descuento_alumnoid = alumno_id AND descuento_estado = 'S'
										LEFT JOIN general_sede ON sede_id = alumno_sedeid
									WHERE pago_rubroid = 'RPE' AND alumno_estado <> 'I' AND (:p2 = 0 OR alumno_sedeid = :p2) 
									GROUP BY 
										pago_alumnoid
									) BASE
								) PEN ON PEN.pago_alumnoid = A.alumno_id
								WHERE A.alumno_estado <> 'E'
									AND PEN.TOTAL > 0 OR P.SALDO > 0
								ORDER BY PEN.PENSIONES DESC";
			
			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => (int)$sedeid, ':p2' => (int)$sedeid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
				$NUM_SALDO += $rows['NUM_SALDO'];
				$SALDO += $rows['SALDO'];
				$NUM_PENSION += $rows['NUM_PENSION'];
				$PENSION += $rows['PENSION'];

				$tabla.='
					<tr data-widget="expandable-table" aria-expanded="false">
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['NOMBRES'].'</td>
						<td>'.$rows['NUM_SALDO'].'</td>
						<td>'.$rows['SALDO'].'</td>
						<td>'.$rows['NUM_PENSION'].'</td>
						<td>'.$rows['PENSION'].'</td>						
					</tr>';							
			}

			/*
			| LOS TOTALES NO VAN EN EL CUERPO DE LA TABLA
			|
			| Estaban ahi, dentro del <tbody>, y con colspan:
			|
			|     <td colspan="2">SUB TOTAL</td>  ... 5 celdas para 6 columnas
			|     <td colspan="4">TOTAL</td>      ... 3 celdas para 6 columnas
			|
			| DataTables lee las celdas por POSICION y no interpreta colspan en
			| el cuerpo, asi que pedia la columna 5 de una fila que solo tenia
			| tres y reventaba con «Requested unknown parameter 5 for row 0».
			|
			| Se veia sobre todo cuando el informe salia sin datos: entonces la
			| primera fila del cuerpo era el subtotal, y el error saltaba nada
			| mas abrir la pantalla.
			|
			| Se guardan aparte para que la vista los pinte en <tfoot>, que es
			| donde va un total: DataTables no lo recorre como fila, el colspan
			| funciona, y ademas el pie se queda fijo aunque se pagine o se
			| filtre, que es justo lo que se quiere de un total.
			*/
			$this->pieDePendientes =
				'<tr>
					<th colspan="2">SUB TOTAL</th>
					<th>'.$NUM_SALDO.'</th>
					<th>'.$SALDO.'</th>
					<th>'.$NUM_PENSION.'</th>
					<th>'.$PENSION.'</th>
				</tr>
				<tr>
					<th colspan="4">TOTAL</th>
					<th>'.($NUM_PENSION + $NUM_SALDO).'</th>
					<th>'.($PENSION + $SALDO).'</th>
				</tr>';

			return $tabla;
		}

		/**
		 * El pie con los totales del informe de pendientes.
		 *
		 * Lo rellena valoresPendientes() al recorrer las filas, asi que hay
		 * que llamarla ANTES. Si no se llamo, devuelve cadena vacia y la
		 * tabla sale sin pie en lugar de con ceros inventados.
		 */
		private $pieDePendientes = '';

		public function pieValoresPendientes(){
			return $this->pieDePendientes;
		}		
		public function listarOptionRubro($rubro){
			$option ='<option value=0> Seleccione el rubro</option>';
			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'rubros'
									AND T.tabla_estado = 'A'
									AND C.catalogo_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($rubro == $rows['catalogo_valor']){	
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{		
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
                }						
			}
			return $option;
		}

		public function listarAlumnosRubro($sede_id, $rubro){
			$tabla="";
			$consulta_datos="SELECT concat_ws(' ', alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) ALUMNO,
								pago_concepto KIT, 
								year(alumno_fechanacimiento) ANIO_NACIMIENTO, 
								alumno_numcamiseta NUMCAMISETA, 
								catalogo_descripcion TALLA,
								sede_nombre SEDE
								FROM sujeto_alumno 
								INNER JOIN alumno_pago ON alumno_id = pago_alumnoid
								LEFT JOIN general_tabla_catalogo on pago_talla = catalogo_valor
								INNER JOIN general_sede on alumno_sedeid = sede_id
								WHERE pago_estado <> 'E'
									AND alumno_sedeid = :p1
									AND pago_rubroid = :p2";								

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $sede_id, ':p2' => $rubro]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['KIT'].'</td>
						<td>'.$rows['ANIO_NACIMIENTO'].'</td>
						<td>'.$rows['NUMCAMISETA'].'</td>
						<td>'.$rows['TALLA'].'</td>
					</tr>';	
			}
			return $tabla;			
		}

		public function listarAlumnosRubroConsolidado($rubro){
			$tabla="";
			$consulta_datos="SELECT concat_ws(' ', alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) ALUMNO,
								pago_concepto KIT, 
								year(alumno_fechanacimiento) ANIO_NACIMIENTO, 
								alumno_numcamiseta NUMCAMISETA, 
								catalogo_descripcion TALLA,
								sede_nombre SEDE
								FROM sujeto_alumno 
								INNER JOIN alumno_pago ON alumno_id = pago_alumnoid
								INNER JOIN general_sede on alumno_sedeid = sede_id
								LEFT JOIN general_tabla_catalogo on pago_talla = catalogo_valor
								WHERE pago_estado <> 'E'
									AND pago_rubroid = :p1";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $rubro]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['KIT'].'</td>
						<td>'.$rows['ANIO_NACIMIENTO'].'</td>
						<td>'.$rows['NUMCAMISETA'].'</td>
						<td>'.$rows['TALLA'].'</td>
					</tr>';	
			}
			return $tabla;			
		}
		public function listarSedebusqueda($sedeid){
			$option ="0";
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
		/*----------  Representantes por sede (una fila por cada alumno representado)  ----------*/
		public function listarRepresentantesPorSede($sede_id){
			$tabla="";
			$sede_id = (int)$sede_id;

			$consulta_datos="SELECT R.repre_identificacion,
								CONCAT_WS(' ', R.repre_primernombre, R.repre_segundonombre, R.repre_apellidopaterno, R.repre_apellidomaterno) REPRESENTANTE,
								R.repre_celular,
								A.alumno_identificacion,
								CONCAT_WS(' ', A.alumno_primernombre, A.alumno_segundonombre, A.alumno_apellidopaterno, A.alumno_apellidomaterno) ALUMNO,
								S.sede_nombre SEDE
							FROM alumno_representante R
								INNER JOIN sujeto_alumno A ON A.alumno_repreid = R.repre_id
								INNER JOIN general_sede S ON S.sede_id = A.alumno_sedeid
							WHERE R.repre_estado in ('A','I')
								AND A.alumno_estado in ('A','I')";

			/* El array acompaña a la consulta: con sede 0 no hay filtro y
			   tampoco puede haber parámetro. Pasarlo igual es
			   SQLSTATE[HY093], que es como salió al probar «0 = todas». */
			$parametros = [];

			if($sede_id != 0){
				$consulta_datos .= " AND A.alumno_sedeid = :q1";
				$parametros[':q1'] = $sede_id;
			}

			$consulta_datos .= " ORDER BY REPRESENTANTE, S.sede_nombre, ALUMNO";

			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['repre_identificacion'].'</td>
						<td>'.$rows['REPRESENTANTE'].'</td>
						<td>'.$rows['repre_celular'].'</td>
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['SEDE'].'</td>
					</tr>';
			}
			return $tabla;
		}

		/*----------  Contactos de emergencia por sede (una fila por cada alumno que lo refiere)  ----------*/
		public function listarContactosEmergenciaPorSede($sede_id){
			$tabla="";
			$sede_id = (int)$sede_id;

			$consulta_datos="SELECT CE.cemer_nombre,
								CE.cemer_celular,
								PAR.catalogo_descripcion PARENTESCO,
								A.alumno_identificacion,
								CONCAT_WS(' ', A.alumno_primernombre, A.alumno_segundonombre, A.alumno_apellidopaterno, A.alumno_apellidomaterno) ALUMNO,
								S.sede_nombre SEDE
							FROM alumno_cemergencia CE
								INNER JOIN sujeto_alumno A ON A.alumno_id = CE.cemer_alumnoid
								INNER JOIN general_sede S ON S.sede_id = A.alumno_sedeid
								LEFT JOIN (SELECT C.catalogo_valor, C.catalogo_descripcion
											FROM general_tabla_catalogo C
											INNER JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid
											WHERE T.tabla_nombre = 'parentesco') PAR ON PAR.catalogo_valor = CE.cemer_parentesco
							WHERE A.alumno_estado in ('A','I')";

			/* Mismo criterio que en listarRepresentantesPorSede: el
			   parámetro solo existe si el filtro existe. */
			$parametros = [];

			if($sede_id != 0){
				$consulta_datos .= " AND A.alumno_sedeid = :q1";
				$parametros[':q1'] = $sede_id;
			}

			$consulta_datos .= " ORDER BY CE.cemer_nombre, S.sede_nombre, ALUMNO";

			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['cemer_nombre'].'</td>
						<td>'.$rows['cemer_celular'].'</td>
						<td>'.$rows['PARENTESCO'].'</td>
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['SEDE'].'</td>
					</tr>';
			}
			return $tabla;
		}

		public function resumenPagos($fecha_inicio, $fecha_fin, $sede_id){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$VALOR_PAGADO = 0;
			$PAGOS = 0;
			$consulta_datos="SELECT sede_nombre SEDE,
								pago_fecharegistro FECHA_REG_SISTEMA, 
								R.catalogo_descripcion RUBRO,  
								F.catalogo_descripcion FORMA_PAGO,
								count(*) PAGOS, 
								SUM(((P.pago_saldo + P.pago_valor) - (IFNULL(PT.transaccion_valorcalculado, P.pago_saldo))))VALOR_PAGADO
							FROM alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 
								inner join general_tabla_catalogo F ON F.catalogo_valor = P.pago_formapagoid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join general_sede S on S.sede_id = alumno_sedeid
								LEFT JOIN(SELECT COUNT(1) total, PT.transaccion_pagoid, MIN(PT.transaccion_id) IDT
								FROM alumno_pago_transaccion PT
								WHERE PT.transaccion_estado = 'C'
								GROUP BY PT.transaccion_pagoid)T ON T.transaccion_pagoid = P.pago_id
								LEFT JOIN alumno_pago_transaccion PT ON PT.transaccion_id  = T.IDT
							WHERE pago_estado <> 'E'
								and alumno_sedeid = :p1
								and pago_fecharegistro between :p2 and :p3
							GROUP BY SEDE, FECHA_REG_SISTEMA, RUBRO, FORMA_PAGO							
							
							union all 
														
							SELECT sede_nombre SEDE,
								transaccion_fecharegistro FECHA_REG_SISTEMA,  
								CONCAT_WS(' ', R.catalogo_descripcion, ' - Abono') RUBRO,  
								F.catalogo_descripcion FORMA_PAGO, 
								count(*) PAGOS,
								SUM(transaccion_valor) VALOR_PAGADO
							FROM alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join alumno_pago_transaccion T on T.transaccion_pagoid = P.pago_id 
								inner join general_tabla_catalogo F ON F.catalogo_valor = T.transaccion_formapagoid 								
								inner join general_sede S on S.sede_id = alumno_sedeid
							WHERE transaccion_estado <> 'E'
								and alumno_sedeid = :p4
								and transaccion_fecharegistro between :p5 and :p6
							GROUP BY SEDE, FECHA_REG_SISTEMA, RUBRO, FORMA_PAGO
							ORDER BY SEDE, FECHA_REG_SISTEMA, RUBRO";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $sede_id, ':p2' => $fecha_inicio, ':p3' => $fecha_fin, ':p4' => $sede_id, ':p5' => $fecha_inicio, ':p6' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$VALOR_PAGADO += $rows['VALOR_PAGADO'];
				$PAGOS += $rows['PAGOS'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['FECHA_REG_SISTEMA'].'</td>
						<td>'.$rows['RUBRO'].'</td>
						<td>'.$rows['FORMA_PAGO'].'</td>
						<td style="text-align: right">'.$rows['PAGOS'].'</td>
						<td style="text-align: right">'.$rows['VALOR_PAGADO'].'</td>
					</tr>';	
			}
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td ></td>
					<td ></td>
					<td ></td>
					<td >TOTAL</td>
					<td style="text-align: right">'.number_format($PAGOS, 0, '.',',').'</td>
					<td style="text-align: right">'.number_format($VALOR_PAGADO, 2, '.',',').'</td>				
				</tr>';
			return $tabla;			
		}
		public function resumenPagosConsolidado($fecha_inicio, $fecha_fin){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$VALOR_PAGADO = 0;
			$consulta_datos="SELECT sede_nombre SEDE,
								pago_fecharegistro FECHA_REG_SISTEMA, 
								R.catalogo_descripcion RUBRO,   
								F.catalogo_descripcion FORMA_PAGO,
								count(*) PAGOS, 
								SUM(((P.pago_saldo + P.pago_valor) - (IFNULL(PT.transaccion_valorcalculado, P.pago_saldo))))VALOR_PAGADO
							from alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 
								inner join general_tabla_catalogo F ON F.catalogo_valor = P.pago_formapagoid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join general_sede S on S.sede_id = alumno_sedeid
								LEFT JOIN(SELECT COUNT(1) total, PT.transaccion_pagoid, MIN(PT.transaccion_id) IDT
								FROM alumno_pago_transaccion PT
								WHERE PT.transaccion_estado = 'C'
								GROUP BY PT.transaccion_pagoid)T ON T.transaccion_pagoid = P.pago_id
								LEFT JOIN alumno_pago_transaccion PT ON PT.transaccion_id  = T.IDT
							where pago_estado <> 'E'
								and pago_fecharegistro between :p1 and :p2
							GROUP BY SEDE, FECHA_REG_SISTEMA, RUBRO, FORMA_PAGO
							
							union all 
														
							SELECT sede_nombre SEDE,
								transaccion_fecharegistro FECHA_REG_SISTEMA,  
								CONCAT_WS(' ', R.catalogo_descripcion, ' - Abono') RUBRO, 
								F.catalogo_descripcion FORMA_PAGO, 
								count(*) PAGOS,
								SUM(transaccion_valor) VALOR_PAGADO
							from alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join alumno_pago_transaccion T on T.transaccion_pagoid = P.pago_id 
								inner join general_tabla_catalogo F ON F.catalogo_valor = T.transaccion_formapagoid 								
								inner join general_sede S on S.sede_id = alumno_sedeid
							where transaccion_estado <> 'E'
								and transaccion_fecharegistro between :p3 and :p4
							GROUP BY SEDE, FECHA_REG_SISTEMA, RUBRO, FORMA_PAGO
							ORDER BY SEDE, RUBRO";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $fecha_inicio, ':p2' => $fecha_fin, ':p3' => $fecha_inicio, ':p4' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$VALOR_PAGADO += $rows['VALOR_PAGADO'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['FECHA_REG_SISTEMA'].'</td>
						<td>'.$rows['RUBRO'].'</td>
						<td>'.$rows['FORMA_PAGO'].'</td>
						<td>'.$rows['PAGOS'].'</td>
						<td style="text-align: right">'.$rows['VALOR_PAGADO'].'</td>
					</tr>';	
			}
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td colspan="5">TOTAL</td>
					<td style="text-align: right">'.number_format($VALOR_PAGADO, 2, '.',',').'</td>			
				</tr>';	

			return $tabla;			
		}
		public function fechaPagosResumen(){		
			$consulta_fecham="SELECT max(pago_fecharegistro) AS FECHA_MAXIMA
								FROM alumno_pago, sujeto_alumno
								WHERE pago_alumnoid = alumno_id
								ORDER BY pago_fecharegistro";
			$fecha_maxima = $this->ejecutarConsulta($consulta_fecham);		
			return $fecha_maxima;
		}
		public function fechaPagosCompletos(){		
			$consulta_fecham="SELECT max(pago_fecharegistro) AS FECHA_MAXIMA
								FROM alumno_pago, sujeto_alumno
								WHERE pago_alumnoid = alumno_id
									AND pago_estado = 'C'
								ORDER BY pago_fecharegistro";
			$fecha_maxima = $this->ejecutarConsulta($consulta_fecham);		
			return $fecha_maxima;
		}
		public function  pagosFacturacion($fecha_inicio, $fecha_fin){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$consulta_datos="SELECT sede_nombre AS SEDE, repre_identificacion AS IDENTIFICACION, 
								concat_ws(' ', repre_primernombre, repre_segundonombre, repre_apellidopaterno, repre_apellidomaterno) as REPRESENTANTE,
								repre_direccion DIRECCION, repre_correo CORREO, repre_celular CELULAR,
								concat_ws(' ', alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) AS ALUMNO,
								catalogo_descripcion AS RUBRO, pago_valor AS VALOR, pago_fecharegistro AS FECHA_REGISTRO
							from alumno_representante, sujeto_alumno, alumno_pago, general_tabla_catalogo, general_sede
							where alumno_repreid = repre_id
								and alumno_id = pago_alumnoid 
								and pago_rubroid = catalogo_valor
								and alumno_sedeid = sede_id
								and repre_factura = 'S'
								and pago_estado = 'C'
								and pago_fecharegistro between :p1 and :p2";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $fecha_inicio, ':p2' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['IDENTIFICACION'].'</td>
						<td>'.$rows['REPRESENTANTE'].'</td>
						<td>'.$rows['DIRECCION'].'</td>
						<td>'.$rows['CORREO'].'</td>
						<td>'.$rows['CELULAR'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['RUBRO'].'</td>
						<td>'.$rows['VALOR'].'</td>
						<td>'.$rows['FECHA_REGISTRO'].'</td>
					</tr>';	
			}
			return $tabla;
		}
		public function resumenPagosForma($fecha_inicio, $fecha_fin, $sede_id){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$VALOR_PAGADO = 0;
			$PAGOS = 0;
			$consulta_datos=" SELECT SEDE, FECHA_REG_SISTEMA, FORMA_PAGO, SUM(PAGOS) PAGOS, SUM(VALOR_PAGADO) VALOR_PAGADO
								FROM(
									SELECT sede_nombre SEDE,
									pago_fecharegistro FECHA_REG_SISTEMA, 
									F.catalogo_descripcion FORMA_PAGO,
									count(*) PAGOS, 
									SUM(((P.pago_saldo + P.pago_valor) - (IFNULL(PT.transaccion_valorcalculado, P.pago_saldo))))VALOR_PAGADO
								FROM alumno_pago P
									inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 
									inner join general_tabla_catalogo F ON F.catalogo_valor = P.pago_formapagoid
									inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
									inner join general_sede S on S.sede_id = alumno_sedeid
									LEFT JOIN(SELECT COUNT(1) total, PT.transaccion_pagoid, MIN(PT.transaccion_id) IDT
									FROM alumno_pago_transaccion PT
									WHERE PT.transaccion_estado = 'C'
									GROUP BY PT.transaccion_pagoid)T ON T.transaccion_pagoid = P.pago_id
									LEFT JOIN alumno_pago_transaccion PT ON PT.transaccion_id  = T.IDT
								WHERE pago_estado <> 'E'
									and alumno_sedeid = :p1
									and pago_fecharegistro between :p2 and :p3
								GROUP BY SEDE, FECHA_REG_SISTEMA, FORMA_PAGO
								
								union all 
															
								SELECT sede_nombre SEDE,
									transaccion_fecharegistro FECHA_REG_SISTEMA,
									F.catalogo_descripcion FORMA_PAGO, 
									count(*) PAGOS,
									SUM(transaccion_valor) VALOR_PAGADO
								FROM alumno_pago P
									inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid
									inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
									inner join alumno_pago_transaccion T on T.transaccion_pagoid = P.pago_id 
									inner join general_tabla_catalogo F ON F.catalogo_valor = T.transaccion_formapagoid 								
									inner join general_sede S on S.sede_id = alumno_sedeid
								WHERE transaccion_estado <> 'E'
									and alumno_sedeid = :p4
									and transaccion_fecharegistro between :p5 and :p6
								GROUP BY SEDE, FECHA_REG_SISTEMA, FORMA_PAGO
								) FORMAPAGO
								group by SEDE, FECHA_REG_SISTEMA, FORMA_PAGO
								order by SEDE, FECHA_REG_SISTEMA";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $sede_id, ':p2' => $fecha_inicio, ':p3' => $fecha_fin, ':p4' => $sede_id, ':p5' => $fecha_inicio, ':p6' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$VALOR_PAGADO += $rows['VALOR_PAGADO'];
				$PAGOS += $rows['PAGOS'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['FECHA_REG_SISTEMA'].'</td>
						<td>'.$rows['FORMA_PAGO'].'</td>
						<td style="text-align: right">'.$rows['PAGOS'].'</td>
						<td style="text-align: right">'.$rows['VALOR_PAGADO'].'</td>
					</tr>';	
			}
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td ></td>
					<td ></td>
					<td >TOTAL</td>
					<td style="text-align: right">'.number_format($PAGOS, 0, '.',',').'</td>
					<td style="text-align: right">'.number_format($VALOR_PAGADO, 2, '.',',').'</td>				
				</tr>';
			return $tabla;			
		}
		public function resumenPagosFormaConsolidado($fecha_inicio, $fecha_fin){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$VALOR_PAGADO = 0;
			$consulta_datos="SELECT sede_nombre SEDE,
								pago_fecharegistro FECHA_REG_SISTEMA, 
								R.catalogo_descripcion RUBRO,   
								F.catalogo_descripcion FORMA_PAGO,
								count(*) PAGOS, 
								SUM(((P.pago_saldo + P.pago_valor) - (IFNULL(PT.transaccion_valorcalculado, P.pago_saldo))))VALOR_PAGADO
							from alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid 
								inner join general_tabla_catalogo F ON F.catalogo_valor = P.pago_formapagoid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join general_sede S on S.sede_id = alumno_sedeid
								LEFT JOIN(SELECT COUNT(1) total, PT.transaccion_pagoid, MIN(PT.transaccion_id) IDT
								FROM alumno_pago_transaccion PT
								WHERE PT.transaccion_estado = 'C'
								GROUP BY PT.transaccion_pagoid)T ON T.transaccion_pagoid = P.pago_id
								LEFT JOIN alumno_pago_transaccion PT ON PT.transaccion_id  = T.IDT
							where pago_estado <> 'E'
								and pago_fecharegistro between :p1 and :p2
							GROUP BY SEDE, FECHA_REG_SISTEMA, RUBRO, FORMA_PAGO
							
							union all 
														
							SELECT sede_nombre SEDE,
								transaccion_fecharegistro FECHA_REG_SISTEMA,  
								CONCAT_WS(' ', R.catalogo_descripcion, ' - Abono') RUBRO, 
								F.catalogo_descripcion FORMA_PAGO, 
								count(*) PAGOS,
								SUM(transaccion_valor) VALOR_PAGADO
							from alumno_pago P
								inner join general_tabla_catalogo R ON R.catalogo_valor = P.pago_rubroid
								inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
								inner join alumno_pago_transaccion T on T.transaccion_pagoid = P.pago_id 
								inner join general_tabla_catalogo F ON F.catalogo_valor = T.transaccion_formapagoid 								
								inner join general_sede S on S.sede_id = alumno_sedeid
							where transaccion_estado <> 'E'
								and transaccion_fecharegistro between :p3 and :p4
							GROUP BY SEDE, FECHA_REG_SISTEMA, RUBRO, FORMA_PAGO
							ORDER BY SEDE, RUBRO";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $fecha_inicio, ':p2' => $fecha_fin, ':p3' => $fecha_inicio, ':p4' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$VALOR_PAGADO += $rows['VALOR_PAGADO'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['FECHA_REG_SISTEMA'].'</td>
						<td>'.$rows['RUBRO'].'</td>
						<td>'.$rows['FORMA_PAGO'].'</td>
						<td>'.$rows['PAGOS'].'</td>
						<td style="text-align: right">'.$rows['VALOR_PAGADO'].'</td>
					</tr>';	
			}
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td colspan="5">TOTAL</td>
					<td style="text-align: right">'.number_format($VALOR_PAGADO, 2, '.',',').'</td>			
				</tr>';	

			return $tabla;			
		}
		public function balanceResultado($fecha_inicio, $fecha_fin, $sede_id){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$TOTAL_INGRESOS = 0;
			$MONTO_TOTAL_INGRESO = 0;
			$consulta_ingresos="SELECT sede_nombre SEDE, catalogo_descripcion RUBRO, count(*) TOTAL_INGRESOS, SUM(pago_valor) MONTO_TOTAL_INGRESO 
								FROM sujeto_alumno 
								INNER JOIN alumno_pago ON alumno_id = pago_alumnoid
								LEFT JOIN general_tabla_catalogo on pago_rubroid = catalogo_valor
								INNER JOIN general_sede on pago_sedeid = sede_id
								WHERE pago_estado <> 'E'
									AND pago_sedeid =:q1
									and pago_fecharegistro between :q2 and :q3
								GROUP BY sede_nombre, catalogo_descripcion
							
							UNION ALL

							SELECT sede_nombre SEDE, concat_ws(' ', catalogo_descripcion, '-', 'ABONO') RUBRO, 
									count(*) TOTAL_INGRESOS, SUM(transaccion_valor) MONTO_TOTAL_INGRESO 
								from sujeto_alumno
								INNER JOIN alumno_pago ON alumno_id = pago_alumnoid
								INNER JOIN alumno_pago_transaccion ON pago_id = transaccion_pagoid
								LEFT JOIN general_tabla_catalogo on pago_rubroid = catalogo_valor
								INNER JOIN general_sede on pago_sedeid = sede_id
								WHERE transaccion_estado <> 'E'
									AND pago_sedeid =:q4
									and transaccion_fecharegistro between :q5 and :q6
									AND pago_sedeid =1
								GROUP BY sede_nombre, catalogo_descripcion
							
							UNION ALL
							
							SELECT sede_nombre SEDE, concat_ws(' ', catalogo_descripcion, '-', empleado_nombre) RUBRO, 
									count(*) TOTAL_INGRESOS, SUM(egreso_valor) MONTO_TOTAL_INGRESO 
								FROM empleado_egreso
								INNER JOIN sujeto_empleado on empleado_id = egreso_empleadoid 
								LEFT JOIN general_tabla_catalogo on egreso_tipoid = catalogo_valor
								INNER JOIN general_sede on empleado_sedeid = sede_id
								WHERE egreso_estado <> 'E'
									AND empleado_sedeid = :q7
									AND egreso_fechaegreso between :q8 and :q9
								GROUP BY sede_nombre, catalogo_descripcion, empleado_nombre
								
							UNION ALL
							
							SELECT sede_nombre SEDE, concat_ws(' ', catalogo_descripcion, '-', ingreso_empresa) RUBRO, 
									count(*) TOTAL_INGRESOS, SUM(ingreso_monto) MONTO_TOTAL_INGRESO 
								FROM balance_ingreso
								LEFT JOIN general_tabla_catalogo on ingreso_concepto = catalogo_valor
								INNER JOIN general_sede on ingreso_sedeid = sede_id
								WHERE ingreso_estado <> 'E'
									AND ingreso_sedeid = :q10
									and ingreso_fecharecepcion between :q11 and :q12
								GROUP BY sede_nombre, catalogo_descripcion, ingreso_empresa";

			$datos = $this->ejecutarConsulta($consulta_ingresos, [':q1' => $sede_id, ':q2' => $fecha_inicio, ':q3' => $fecha_fin, ':q4' => $sede_id, ':q5' => $fecha_inicio, ':q6' => $fecha_fin, ':q7' => $sede_id, ':q8' => $fecha_inicio, ':q9' => $fecha_fin, ':q10' => $sede_id, ':q11' => $fecha_inicio, ':q12' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$TOTAL_INGRESOS += $rows['TOTAL_INGRESOS'];
				$MONTO_TOTAL_INGRESO += $rows['MONTO_TOTAL_INGRESO'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['RUBRO'].'</td>
						<td style="text-align: right">'.number_format($rows['TOTAL_INGRESOS'], 0, '.',',').'</td>
						<td style="text-align: right">'.number_format($rows['MONTO_TOTAL_INGRESO'], 2, '.',',').'</td>
						<td></td>
					</tr>';	
			}
			
			$TOTAL_EGRESOS = 0;
			$MONTO_TOTAL_EGRESO = 0;
			$consulta_egresos="SELECT sede_nombre SEDE, concat(catalogo_descripcion, '-', ingreso_periodo, '-', empleado_nombre) RUBRO, count(*) TOTAL_EGRESOS, ingreso_valor MONTO_TOTAL_EGRESO 
									FROM empleado_ingreso
									INNER JOIN sujeto_empleado on empleado_id = ingreso_empleadoid 
									LEFT JOIN general_tabla_catalogo on ingreso_tipoingresoid = catalogo_valor
									INNER JOIN general_sede on empleado_sedeid = sede_id
									WHERE ingreso_estado <> 'E'
										AND empleado_sedeid = :q13
										AND ingreso_fechapago BETWEEN :q14 and :q15
									GROUP BY sede_nombre, catalogo_descripcion, ingreso_periodo, empleado_nombre, ingreso_valor

								UNION ALL

								SELECT sede_nombre SEDE, concat_ws(' ', catalogo_descripcion, '-', egreso_empresa) RUBRO, count(*) TOTAL_EGRESOS, SUM(egreso_monto) MONTO_TOTAL_EGRESO 
									FROM balance_egreso
									LEFT JOIN general_tabla_catalogo on egreso_concepto = catalogo_valor
									INNER JOIN general_sede on egreso_sedeid = sede_id
									WHERE egreso_estado <> 'E'
										AND egreso_sedeid = :q16
										AND egreso_fechapago between :q17 and :q18
									GROUP BY sede_nombre, catalogo_descripcion, egreso_empresa";

			$datos = $this->ejecutarConsulta($consulta_egresos, [':q13' => $sede_id, ':q14' => $fecha_inicio, ':q15' => $fecha_fin, ':q16' => $sede_id, ':q17' => $fecha_inicio, ':q18' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$TOTAL_EGRESOS += $rows['TOTAL_EGRESOS'];
				$MONTO_TOTAL_EGRESO += $rows['MONTO_TOTAL_EGRESO'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['RUBRO'].'</td>						
						<td style="text-align: right">'.number_format($rows['TOTAL_EGRESOS'], 0, '.',',').'</td>
						<td></td>
						<td style="text-align: right">'.number_format($rows['MONTO_TOTAL_EGRESO'], 2, '.',',').'</td>
					</tr>';	
			}
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td >SUBTOTAL</td>
					<td ></td>
					<td ></td>
					<td style="text-align: right">'.number_format($MONTO_TOTAL_INGRESO, 2, '.',',').'</td>
					<td style="text-align: right">'.number_format($MONTO_TOTAL_EGRESO, 2, '.',',').'</td>
				</tr>';
			
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="true">
					<td style="text-align: center; font-weight: bold;">TOTAL = Ingresos - Egresos</td>
					<td ></td>
					<td ></td>
					<td style="text-align: right; font-weight: bold;">'.number_format($MONTO_TOTAL_INGRESO - $MONTO_TOTAL_EGRESO, 2, '.',',').'</td>
					<td ></td>
				</tr>';
			return $tabla;			
		}
		public function balanceResultadosConsolidado($fecha_inicio, $fecha_fin){
			/* Rango vacio: la pantalla acaba de abrirse y no se ha pedido
			   nada todavia. Se devuelve el informe vacio, que es lo que
			   hacia antes; con parametros ligados MySQL rechaza '' como
			   fecha y tumbaria la pagina. */
			if (trim((string)$fecha_inicio) === '' || trim((string)$fecha_fin) === '') {
				return '';
			}

			$tabla="";
			$TOTAL_INGRESOS = 0;
			$MONTO_TOTAL_INGRESO = 0;
			$consulta_ingresos="SELECT sede_nombre SEDE, catalogo_descripcion RUBRO, count(*) TOTAL_INGRESOS, SUM(pago_valor) MONTO_TOTAL_INGRESO 
								FROM sujeto_alumno 
								INNER JOIN alumno_pago ON alumno_id = pago_alumnoid
								LEFT JOIN general_tabla_catalogo on pago_rubroid = catalogo_valor
								INNER JOIN general_sede on pago_sedeid = sede_id
								WHERE pago_estado <> 'E'
									and pago_fecharegistro between :q1 and :q2
								GROUP BY sede_nombre, catalogo_descripcion
							
							UNION ALL

							SELECT sede_nombre SEDE, concat_ws(' ', catalogo_descripcion, '-', 'ABONO') RUBRO, 
									count(*) TOTAL_INGRESOS, SUM(transaccion_valor) MONTO_TOTAL_INGRESO 
								from sujeto_alumno
								INNER JOIN alumno_pago ON alumno_id = pago_alumnoid
								INNER JOIN alumno_pago_transaccion ON pago_id = transaccion_pagoid
								LEFT JOIN general_tabla_catalogo on pago_rubroid = catalogo_valor
								INNER JOIN general_sede on pago_sedeid = sede_id
								WHERE transaccion_estado <> 'E'
									and transaccion_fecharegistro between :q3 and :q4
									AND pago_sedeid =1
								GROUP BY sede_nombre, catalogo_descripcion
							
							UNION ALL
							
							SELECT sede_nombre SEDE, concat_ws(' ', catalogo_descripcion, '-', empleado_nombre) RUBRO, 
									count(*) TOTAL_INGRESOS, SUM(egreso_valor) MONTO_TOTAL_INGRESO 
								FROM empleado_egreso
								INNER JOIN sujeto_empleado on empleado_id = egreso_empleadoid 
								LEFT JOIN general_tabla_catalogo on egreso_tipoid = catalogo_valor
								INNER JOIN general_sede on empleado_sedeid = sede_id
								WHERE egreso_estado <> 'E'
									AND egreso_fechaegreso between :q5 and :q6
								GROUP BY sede_nombre, catalogo_descripcion, empleado_nombre
								
							UNION ALL
							
							SELECT sede_nombre SEDE, concat_ws(' ', catalogo_descripcion, '-', ingreso_empresa) RUBRO, 
									count(*) TOTAL_INGRESOS, SUM(ingreso_monto) MONTO_TOTAL_INGRESO 
								FROM balance_ingreso
								LEFT JOIN general_tabla_catalogo on ingreso_concepto = catalogo_valor
								INNER JOIN general_sede on ingreso_sedeid = sede_id
								WHERE ingreso_estado <> 'E'
									and ingreso_fecharecepcion between :q7 and :q8
								GROUP BY sede_nombre, catalogo_descripcion, ingreso_empresa";

			$datos = $this->ejecutarConsulta($consulta_ingresos, [':q1' => $fecha_inicio, ':q2' => $fecha_fin, ':q3' => $fecha_inicio, ':q4' => $fecha_fin, ':q5' => $fecha_inicio, ':q6' => $fecha_fin, ':q7' => $fecha_inicio, ':q8' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$TOTAL_INGRESOS += $rows['TOTAL_INGRESOS'];
				$MONTO_TOTAL_INGRESO += $rows['MONTO_TOTAL_INGRESO'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['RUBRO'].'</td>
						<td style="text-align: right">'.number_format($rows['TOTAL_INGRESOS'], 0, '.',',').'</td>
						<td style="text-align: right">'.number_format($rows['MONTO_TOTAL_INGRESO'], 2, '.',',').'</td>
						<td></td>
					</tr>';	
			}
			
			$TOTAL_EGRESOS = 0;
			$MONTO_TOTAL_EGRESO = 0;
			$consulta_egresos="SELECT sede_nombre SEDE, catalogo_descripcion RUBRO, count(*) TOTAL_EGRESOS, SUM(ingreso_valor) MONTO_TOTAL_EGRESO 
									FROM empleado_ingreso
									INNER JOIN sujeto_empleado on empleado_id = ingreso_empleadoid 
									LEFT JOIN general_tabla_catalogo on ingreso_tipoingresoid = catalogo_valor
									INNER JOIN general_sede on empleado_sedeid = sede_id
									WHERE ingreso_estado <> 'E'
										AND ingreso_fechapago BETWEEN :q9 and :q10
									GROUP BY sede_nombre, catalogo_descripcion

								UNION ALL

								SELECT sede_nombre SEDE, concat_ws(' ', catalogo_descripcion, '-', egreso_empresa) RUBRO, count(*) TOTAL_EGRESOS, SUM(egreso_monto) MONTO_TOTAL_EGRESO 
									FROM balance_egreso
									LEFT JOIN general_tabla_catalogo on egreso_concepto = catalogo_valor
									INNER JOIN general_sede on egreso_sedeid = sede_id
									WHERE egreso_estado <> 'E'
										AND egreso_fechapago between :q11 and :q12
									GROUP BY sede_nombre, catalogo_descripcion, egreso_empresa";

			$datos = $this->ejecutarConsulta($consulta_egresos, [':q9' => $fecha_inicio, ':q10' => $fecha_fin, ':q11' => $fecha_inicio, ':q12' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$TOTAL_EGRESOS += $rows['TOTAL_EGRESOS'];
				$MONTO_TOTAL_EGRESO += $rows['MONTO_TOTAL_EGRESO'];
				$tabla.='
					<tr>
						<td>'.$rows['SEDE'].'</td>
						<td>'.$rows['RUBRO'].'</td>						
						<td style="text-align: right">'.number_format($rows['TOTAL_EGRESOS'], 0, '.',',').'</td>
						<td></td>
						<td style="text-align: right">'.number_format($rows['MONTO_TOTAL_EGRESO'], 2, '.',',').'</td>
					</tr>';	
			}
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td >SUBTOTAL</td>
					<td ></td>
					<td ></td>
					<td style="text-align: right">'.number_format($MONTO_TOTAL_INGRESO, 2, '.',',').'</td>
					<td style="text-align: right">'.number_format($MONTO_TOTAL_EGRESO, 2, '.',',').'</td>
				</tr>';
			
			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="true">
					<td style="text-align: center; font-weight: bold;">TOTAL = Ingresos - Egresos</td>
					<td ></td>
					<td ></td>
					<td style="text-align: right; font-weight: bold;">'.number_format($MONTO_TOTAL_INGRESO - $MONTO_TOTAL_EGRESO, 2, '.',',').'</td>
					<td ></td>
					</tr>';
			return $tabla;		
		}

		/*----------  Matriz de alumnos con opciones Ver, Actualizar, Eliminar  ----------*/
		public function listarAlumnos($apellidopaterno, $primernombre, $anio, $sede){
			if($primernombre!=""){
				$primernombre .= '%';
			} 
			if($apellidopaterno!=""){
				$apellidopaterno .= '%';
			} 					

			$tabla="";

			/* El array se arma a la par que la consulta.
			   Las ramas que REEMPLAZAN $consulta_datos lo vacian: pasar un
			   parametro que la consulta ya no usa es SQLSTATE[HY093], y es
			   el descuido que mas veces se ha colado en este proyecto. */
			$parametros = [];

			$consulta_datos="SELECT distinct alumno_id, alumno_identificacion, alumno_primernombre, alumno_segundonombre,
									alumno_apellidopaterno, alumno_apellidomaterno, alumno_fechanacimiento
								FROM sujeto_alumno
								INNER JOIN asistencia_asistencia ON asistencia_alumnoid = alumno_id
								WHERE (alumno_primernombre LIKE :nombre
										OR alumno_apellidopaterno LIKE :apellido) ";
			$parametros[':nombre']   = $primernombre;
			$parametros[':apellido'] = $apellidopaterno;

			if($anio!=""){
				$consulta_datos .= " and YEAR(alumno_fechanacimiento) = :anio";
				$parametros[':anio'] = $anio;
			}

			if($primernombre=="" && $apellidopaterno==""){
				$consulta_datos="SELECT distinct alumno_id, alumno_identificacion, alumno_primernombre, alumno_segundonombre,
										alumno_apellidopaterno, alumno_apellidomaterno, alumno_fechanacimiento
									FROM sujeto_alumno
									INNER JOIN asistencia_asistencia ON asistencia_alumnoid = alumno_id
									WHERE YEAR(alumno_fechanacimiento) = :anio";
				/* Consulta nueva: solo sobrevive el año. */
				$parametros = [':anio' => $anio];
			}

			if($primernombre=="" && $apellidopaterno=="" && $anio == ""){
				$consulta_datos = "SELECT distinct alumno_id, alumno_identificacion, alumno_primernombre, alumno_segundonombre,
											alumno_apellidopaterno, alumno_apellidomaterno, alumno_fechanacimiento
										FROM sujeto_alumno
										INNER JOIN asistencia_asistencia ON asistencia_alumnoid = alumno_id
										WHERE alumno_primernombre <> '' ";
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
				$consulta_datos = "SELECT distinct alumno_id, alumno_identificacion, alumno_primernombre, alumno_segundonombre,
											alumno_apellidopaterno, alumno_apellidomaterno, alumno_fechanacimiento
										FROM sujeto_alumno
										INNER JOIN asistencia_asistencia ON asistencia_alumnoid = alumno_id
										WHERE alumno_primernombre = ''";
				$parametros = [];
			}

			$consulta_datos .= " AND alumno_estado = 'A' ORDER BY alumno_apellidopaterno, alumno_apellidomaterno, alumno_primernombre";

			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['alumno_primernombre'].' '.$rows['alumno_segundonombre'].'</td>
						<td>'.$rows['alumno_apellidopaterno'].' '.$rows['alumno_apellidomaterno'].'</td>
						<td>'.$rows['alumno_fechanacimiento'].'</td>
						<td>
							<a href="'.APP_URL.'buscarAsistencia/'.$rows['alumno_id'].'/" target="_blank" class="btn float-start btn-ver btn-sm">Ver</a>
						</td>
					</tr>';	
			}
			return $tabla;			
		}
		public function listarEmpleados($empleado_nombre, $fecha_inicio, $fecha_fin){
			$tabla="";
			$consulta_datos="SELECT distinct empleado_id, empleado_identificacion, empleado_nombre
								FROM sujeto_empleado
								INNER JOIN empleado_asistencia ON asistencia_empleadoid = empleado_id
								WHERE (empleado_nombre LIKE :p1
									OR asistencia_hora between :p2 and :p3)
									AND empleado_estado = 'A' ";
			
			/* El comodin va en el VALOR, no en la consulta: escrito como
			   LIKE '%:p1%' el marcador queda dentro de una cadena y PDO no
			   lo ve, que es el "number of bound variables does not match
			   number of tokens" que salio al probarlo. */
			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => '%' . $empleado_nombre . '%', ':p2' => $fecha_inicio, ':p3' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='
					<tr>
						<td>'.$rows['empleado_identificacion'].'</td>
						<td>'.$rows['empleado_nombre'].'</td>
						<td>							
							<form action="'.APP_URL.'empleadoAsistenciasDetalle/" method="POST" autocomplete="off" target="_blank" >
								<input type="hidden" name="empleado_id" value="'.$rows['empleado_id'].'">
								<input type="hidden" name="fecha_inicio" value="'.$fecha_inicio.'">		
								<input type="hidden" name="fecha_fin" value="'.$fecha_fin.'">					
								<button type="submit" class="btn float-end btn-ver btn-sm" style="margin-right: 5px;" ><i class="fas fa-list me-1"></i>Detalle ver</button>
							</form>						
						</td>
					</tr>';	
			}
			return $tabla;			
		}

		public function fechaMarcacion(){		
			$consulta_fecham="SELECT max(asistencia_hora) AS FECHA_MAXIMA FROM empleado_asistencia";
			$fecha_maxima = $this->ejecutarConsulta($consulta_fecham);		
			return $fecha_maxima;
		}

		public function listarMarcacionesEmpleado($empleado_id, $fecha_inicio, $fecha_fin){
			$tabla="";
			$consulta_datos="SELECT distinct empleado_nombre, DATE(asistencia_hora) AS fecha, TIME(asistencia_hora) AS hora, 
									asistencia_tipo, asistencia_ubicacion 
							FROM empleado_asistencia
							INNER JOIN sujeto_empleado ON empleado_id=asistencia_empleadoid
							WHERE empleado_id = $empleado_id
								AND asistencia_hora between :p1 and :p2";	
					
			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $fecha_inicio, ':p2' => $fecha_fin]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($rows['asistencia_tipo']=='E'){
					$asistencia_tipo = "Entrada";
				}else{
					$asistencia_tipo = "Salida";
				}	

				$tabla.='
					<tr>
						<td>'.$rows['empleado_nombre'].'</td>
						<td>'.$rows['fecha'].'</td>						
						<td>'.$rows['hora'].'</td>
						<td>'.$asistencia_tipo.'</td>
						<td><a href="'.$rows['asistencia_ubicacion'].'" target="_blank"> Lugar de marcación</a></td>													
					</tr>';	
			}
			return $tabla;
		}

		public function ingresosLugarEntr($fecha_inicio, $fecha_fin){		
			$tabla = "";
			$consulta_datos="SELECT sede_nombre, lugar_nombre, ALUMNOS_ENTRENAN, PENSIONES_ESTIMADAS as TOTALPENSIONES, IFNULL(PA.VALOR_PAGADO,0) + IFNULL(Abonos.VALOR_PAGADO,0) as TOTALRECAUDADO, IFNULL(NP.Total,0) as ALUMNOS_ADEUDAN, IFNULL(SR.SinRegistro,0) as ALUMNOS_SINREGPAGOS
								FROM(select sede_id, sede_nombre, lugar_id ,lugar_nombre, count(1) as ALUMNOS_ENTRENAN, sum(IFNULL(descuento_valor,sede_pension)) as PENSIONES_ESTIMADAS
										from( 
												SELECT distinct detalle_lugarid, asignahorario_alumnoid 
												from asistencia_asignahorario
														inner join asistencia_horario_detalle on detalle_horarioid = asignahorario_horarioid
														inner join sujeto_alumno on alumno_id = asignahorario_alumnoid                                                                  
												where alumno_estado = 'A' and alumno_fechaingreso <= :p1
										)l
												inner join asistencia_lugar on lugar_id = l.detalle_lugarid
												inner join general_sede on lugar_sedeid = sede_id 
												left join alumno_pago_descuento d on d.descuento_alumnoid = l.asignahorario_alumnoid and descuento_estado = 'S' and descuento_fecha <= :p2
										group by sede_id, sede_nombre, lugar_id ,lugar_nombre
										
										union 
										
										select SLE.sede_id, SLE.sede_nombre, 0,'SIN LUGAR DE ENTRENAMIENTO' lugar_nombre, count(1) as ALUMNOS_ENTRENAN, sum(SLE.pension_estimada) PENSIONES_ESTIMADAS
												FROM(select sede_id, sede_nombre, 0,'SIN LUGAR DE ENTRENAMIENTO' lugar_nombre, IFNULL(descuento_valor,s.sede_pension) pension_estimada
																from sujeto_alumno a
																left join alumno_pago_descuento d on d.descuento_alumnoid = a.alumno_id and descuento_estado = 'S' and descuento_fecha <= :p3              
																left join general_sede s on s.sede_id = a.alumno_sedeid
																where a.alumno_id not in (select asignahorario_alumnoid from asistencia_asignahorario)
																and a.alumno_estado = 'A' and a.alumno_fechaingreso <= :p4) SLE
												group by SLE.sede_id, SLE.sede_nombre
								)Base
								
								left join(select Pagos.sedeid, Pagos.lugarid, sum(IFNULL(Pagos.VALOR_PAGADO,0)) VALOR_PAGADO, count(1) Numero 
																from(select ((P.pago_saldo + P.pago_valor) - (IFNULL(PT.transaccion_valorcalculado, P.pago_saldo)))
																				as VALOR_PAGADO, IFNULL(h.detalle_lugarid,0)  AS lugarid, A.alumno_sedeid as sedeid
																				from alumno_pago P 
																				inner JOIN sujeto_alumno A on A.alumno_id = P.pago_alumnoid 
																				LEFT JOIN(SELECT transaccion_pagoid, MIN(transaccion_id) IDT
																										FROM alumno_pago_transaccion
																										WHERE transaccion_estado = 'C'
																														GROUP BY transaccion_pagoid)T ON T.transaccion_pagoid = P.pago_id                  
																				LEFT JOIN alumno_pago_transaccion PT ON PT.transaccion_id  = T.IDT     
																				left join(SELECT distinct detalle_lugarid, asignahorario_alumnoid 
																												from asistencia_asignahorario
																												left join asistencia_horario_detalle on detalle_horarioid = asignahorario_horarioid)h on h.asignahorario_alumnoid = P.pago_alumnoid
																												where P.pago_rubroid = 'RPE' 
																																and P.pago_estado not in ('E','J') 
																																and P.pago_fecha BETWEEN :p5 and :p6) Pagos   
																group by Pagos.sedeid, Pagos.lugarid)PA on PA.sedeid = Base.sede_id AND PA.lugarid = Base.lugar_id
																
								left join (select A.alumno_sedeid as sedeid, IFNULL(h.detalle_lugarid,0) AS lugarid, sum(IFNULL(T.transaccion_valor,0)) as VALOR_PAGADO, Count(1) as Numero
																from alumno_pago_transaccion T
																inner join alumno_pago P on P.pago_id = T.transaccion_pagoid and P.pago_rubroid = 'RPE'
																inner join sujeto_alumno A on A.alumno_id = P.pago_alumnoid                          
																left join(SELECT distinct detalle_lugarid, asignahorario_alumnoid 
																								from asistencia_asignahorario
																								left join asistencia_horario_detalle on detalle_horarioid = asignahorario_horarioid  
																				)h on h.asignahorario_alumnoid = P.pago_alumnoid          
																where transaccion_estado in ('C')        
																and transaccion_fecha BETWEEN :p7 and :p8
																and pago_fecha BETWEEN :p9 and :p10
																group by sedeid, lugarid
												)Abonos on Abonos.sedeid = Base.sede_id AND Abonos.lugarid = Base.lugar_id 
												
								left join(select A.alumno_sedeid as sedeid, IFNULL(h.detalle_lugarid,0)  AS lugarid, count(1) as Total
												from(select P.pago_alumnoid, max(P.pago_fecha) fecha
																from alumno_pago P
																where P.pago_rubroid = 'RPE' and P.pago_estado <> 'E'
																GROUP BY P.pago_alumnoid
																having  max(P.pago_fecha) < :p11
													)b
												inner join sujeto_alumno A on A.alumno_id = b.pago_alumnoid
												left join(SELECT distinct detalle_lugarid, asignahorario_alumnoid 
																from asistencia_asignahorario
																left join asistencia_horario_detalle on detalle_horarioid = asignahorario_horarioid  
														)h on h.asignahorario_alumnoid = A.alumno_id                                   
												where A.alumno_estado = 'A'
												group by sedeid, lugarid
										)NP on NP.sedeid = Base.sede_id AND NP.lugarid = Base.lugar_id                           
									
											
								left join (select alumno_sedeid as sedeid, IFNULL(le.detalle_lugarid,0)  AS lugarid, count(1) as SinRegistro
												from sujeto_alumno a    
												left join(SELECT distinct detalle_lugarid, asignahorario_alumnoid 
																from asistencia_asignahorario
																left join asistencia_horario_detalle on detalle_horarioid = asignahorario_horarioid  
														)le on le.asignahorario_alumnoid = alumno_id                                                                                                                     
												where a.alumno_id not in (SELECT distinct alumno_id AlumnoId
																				FROM sujeto_alumno
																				LEFT JOIN alumno_pago P ON P.pago_alumnoid = alumno_id
																				WHERE P.pago_rubroid = 'RPE' AND pago_estado != 'E' AND alumno_estado = 'A')
														and alumno_estado = 'A'
												group by sedeid, lugarid
											)SR on SR.sedeid = Base.sede_id AND SR.lugarid = Base.lugar_id                            
														
						order by Base.sede_id";

			$datos = $this->ejecutarConsulta($consulta_datos, [':p1' => $fecha_fin, ':p2' => $fecha_fin, ':p3' => $fecha_fin, ':p4' => $fecha_fin, ':p5' => $fecha_inicio, ':p6' => $fecha_fin, ':p7' => $fecha_inicio, ':p8' => $fecha_fin, ':p9' => $fecha_inicio, ':p10' => $fecha_fin, ':p11' => $fecha_inicio]);
			return $datos;			
		}

		/*
			Ingresos y mora de los alumnos activos para el periodo [fecha_inicio, fecha_fin].
			Una fila por alumno (matriculado hasta fecha_fin):
			  - PAGO_VALOR / PAGO_PERIODO / PAGO_CONCEPTO / ESTADOPAGO: pensiones (RPE) pagadas dentro del periodo.
			  - FECHA_ULTPAGO: ultima pension registrada hasta fecha_fin.
			  - PAGO_SALDO: saldos pendientes de pensiones registradas dentro del periodo.
			  - MESES_MORA / PAGO_MORA: solo deuda del periodo consultado. Meses calendario del periodo
			    posteriores al ultimo pago x pension del alumno, mas PAGO_SALDO. La deuda de meses
			    anteriores a fecha_inicio no se incluye.
			  - SITUACION: becado -> AL DÍA; sin pensiones hasta fecha_fin -> No Registra;
			    PAGO_MORA > 0 -> EN MORA; resto -> AL DÍA.
		*/
		public function ingresosMoraLugarEntr($fecha_inicio, $fecha_fin){
			$consulta_datos="SELECT R.*,
									CASE WHEN R.ES_BECA = 1 THEN 'AL DÍA'
										WHEN R.FECHA_ULTPAGO IS NULL THEN 'No Registra'
										WHEN R.PAGO_MORA > 0 THEN 'EN MORA'
										ELSE 'AL DÍA' END SITUACION
								FROM (SELECT S.sede_nombre SEDE,
											IFNULL(LUG.LUGARES, 'NO ASIGNADO') LUGARENTRENAMIENTO,
											CONCAT_WS(' ', A.alumno_primernombre, A.alumno_segundonombre, A.alumno_apellidopaterno, A.alumno_apellidomaterno) ALUMNO,
											'ACTIVO' ESTADOALUMNO,
											PAG.FECHA_ULTPAGO,
											IFNULL(BEC.ES_BECA, 0) ES_BECA,
											IFNULL(PAG.PERIODOS, '') PAGO_PERIODO,
											IFNULL(PAG.CONCEPTOS, '') PAGO_CONCEPTO,
											IFNULL(PAG.VALOR, 0) PAGO_VALOR,
											IFNULL(PAG.SALDO, 0) PAGO_SALDO,
											IFNULL(PAG.ESTADOPAGO, '') ESTADOPAGO,
											CASE WHEN IFNULL(BEC.ES_BECA, 0) = 1 OR PAG.FECHA_ULTPAGO IS NULL THEN 0
												ELSE GREATEST(0, PERIOD_DIFF(PR.mes_fin, GREATEST(CAST(DATE_FORMAT(PAG.FECHA_ULTPAGO, '%Y%m') AS UNSIGNED), PR.mes_previo))) END MESES_MORA,
											CASE WHEN IFNULL(BEC.ES_BECA, 0) = 1 OR PAG.FECHA_ULTPAGO IS NULL THEN 0
												ELSE GREATEST(0, PERIOD_DIFF(PR.mes_fin, GREATEST(CAST(DATE_FORMAT(PAG.FECHA_ULTPAGO, '%Y%m') AS UNSIGNED), PR.mes_previo)))
													* COALESCE(DES.descuento_valor, S.sede_pension) + IFNULL(PAG.SALDO, 0) END PAGO_MORA
										FROM sujeto_alumno A
										-- mes_previo: mes anterior a fecha_inicio, para que solo cuenten los meses del periodo
										CROSS JOIN (SELECT CAST(:corte_fin AS DATE) fin,
														CAST(DATE_FORMAT(:corte_fin_mes, '%Y%m') AS UNSIGNED) mes_fin,
														PERIOD_ADD(DATE_FORMAT(:corte_inicio, '%Y%m'), -1) mes_previo) PR
										INNER JOIN general_sede S ON S.sede_id = A.alumno_sedeid
										LEFT JOIN (SELECT asignahorario_alumnoid,
														GROUP_CONCAT(DISTINCT lugar_nombre ORDER BY lugar_nombre SEPARATOR ', ') LUGARES
													FROM asistencia_asignahorario
													INNER JOIN asistencia_horario_detalle ON detalle_horarioid = asignahorario_horarioid
													INNER JOIN asistencia_lugar ON lugar_id = detalle_lugarid
													GROUP BY asignahorario_alumnoid
												) LUG ON LUG.asignahorario_alumnoid = A.alumno_id
										LEFT JOIN (SELECT descuento_alumnoid,
														MAX(CASE WHEN descuento_rubroid = 'DBC' AND descuento_valor = 0 THEN 1 ELSE 0 END) AS ES_BECA
													FROM alumno_pago_descuento
													WHERE descuento_estado = 'S'
													GROUP BY descuento_alumnoid
												) BEC ON BEC.descuento_alumnoid = A.alumno_id
										LEFT JOIN (SELECT descuento_alumnoid, MAX(descuento_valor) descuento_valor
													FROM alumno_pago_descuento
													WHERE descuento_estado = 'S'
													GROUP BY descuento_alumnoid
												) DES ON DES.descuento_alumnoid = A.alumno_id
										LEFT JOIN (SELECT P.pago_alumnoid,
														MAX(P.pago_fecha) FECHA_ULTPAGO,
														SUM(CASE WHEN P.pago_fecha >= PP.ini THEN P.pago_valor ELSE 0 END) VALOR,
														SUM(CASE WHEN P.pago_fecha >= PP.ini AND P.pago_estado = 'P' THEN P.pago_saldo ELSE 0 END) SALDO,
														GROUP_CONCAT(DISTINCT CASE WHEN P.pago_fecha >= PP.ini THEN P.pago_periodo END ORDER BY P.pago_fecha SEPARATOR ', ') PERIODOS,
														GROUP_CONCAT(DISTINCT CASE WHEN P.pago_fecha >= PP.ini THEN P.pago_concepto END ORDER BY P.pago_fecha SEPARATOR ' | ') CONCEPTOS,
														CASE WHEN SUM(P.pago_fecha >= PP.ini AND P.pago_estado = 'P') > 0 THEN 'Pendiente'
															WHEN SUM(P.pago_fecha >= PP.ini AND P.pago_estado = 'C') > 0 THEN 'Completo'
															WHEN SUM(P.pago_fecha >= PP.ini AND P.pago_estado = 'J') > 0 THEN 'Justificado'
															ELSE '' END ESTADOPAGO
													FROM alumno_pago P
													CROSS JOIN (SELECT CAST(:fecha_inicio AS DATE) ini, CAST(:fecha_fin AS DATE) fin) PP
													WHERE P.pago_rubroid = 'RPE' AND P.pago_estado != 'E' AND P.pago_fecha <= PP.fin
													GROUP BY P.pago_alumnoid
												) PAG ON PAG.pago_alumnoid = A.alumno_id
										WHERE A.alumno_estado = 'A' AND A.alumno_fechaingreso <= PR.fin
									) R
								ORDER BY R.SEDE, R.LUGARENTRENAMIENTO, R.ALUMNO";

			$datos = $this->ejecutarConsulta($consulta_datos, [
				':fecha_inicio' => $fecha_inicio,
				':fecha_fin'    => $fecha_fin,
				':corte_inicio'  => $fecha_inicio,
				':corte_fin'     => $fecha_fin,
				':corte_fin_mes' => $fecha_fin
			]);
			return $datos;
		}
	}
			
											