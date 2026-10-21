<?php
	namespace app\controllers;
	use app\models\mainModel;

	class dashboardController extends mainModel{

		/*----------  Listar sedes para el dashboard  ----------*/
		public function obtenerSedes(){
			$sedes=$this->ejecutarConsulta("SELECT sede_id, sede_nombre FROM general_sede ORDER BY sede_id ASC");
			return $sedes;
		}

		/*----------  Obtener total alumnos activos  ----------*/
		public function obtenerAlumnosActivos($sedeid){
			$alumnosActivos=$this->ejecutarConsulta("SELECT count(*) totalActivos FROM sujeto_alumno WHERE alumno_estado='A' and alumno_sedeid = :sede", [':sede' => (int)$sedeid]);
		    return $alumnosActivos;
		}

		/*----------  Obtener total alumnos inactivos  ----------*/
		public function obtenerAlumnosInactivos($sedeid){
			$alumnosInactivos=$this->ejecutarConsulta("SELECT count(*) totalInactivos FROM sujeto_alumno WHERE alumno_estado='I' and alumno_sedeid = :sede", [':sede' => (int)$sedeid]);
		    return $alumnosInactivos;
		}

		/*----------  Obtener total pagos cancelados  ----------*/
		public function obtenerPagosCancelados($sede_id){
			// Fechas dinámicas
			$fecha_inicio = date('Y-m-01'); // Primer día del mes actual
			$fecha_fin = date('Y-m-t');     // Último día del mes actual

			$pagosCancelados=$this->ejecutarConsulta("SELECT sum(totalCancelado) totalCancelados from (
																	SELECT COUNT(*) totalCancelado 
																		FROM alumno_pago, sujeto_alumno 
																		WHERE pago_alumnoid = alumno_id 
																			AND alumno_sedeid = :sede1 
																			AND pago_fecharegistro between :ini1 and :fin1
																			AND pago_estado <> 'E'
																	UNION ALL
																	SELECT COUNT(*) totalCancelado
																		FROM alumno_pago, alumno_pago_transaccion, sujeto_alumno 
																		WHERE pago_alumnoid = alumno_id 
																			AND pago_id = transaccion_pagoid 
																			AND alumno_sedeid = :sede2 
																			AND transaccion_fecharegistro between :ini2 and :fin2
																			AND transaccion_estado<> 'E') AS DATOS", [':sede1' => (int)$sede_id, ':ini1' => $fecha_inicio, ':fin1' => $fecha_fin, ':sede2' => (int)$sede_id, ':ini2' => $fecha_inicio, ':fin2' => $fecha_fin]);
			return $pagosCancelados;
		}

		/*----------  Obtener total pagos pendientes  ----------*/
		public function obtenerPagosPendientes($sedeid){
			$pagosPendientes=$this->ejecutarConsulta("SELECT SUM(IFNULL(subconsulta.NUM_SALDO,0)) + SUM(IFNULL(subconsulta.NUM_PENSION,0)) as totalPendientes
															FROM (
																SELECT 
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
																	WHERE pago_estado = 'P' AND pago_saldo > 0 AND alumno_sedeid = :sede1 
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
																		WHERE pago_rubroid = 'RPE' AND alumno_estado <> 'I' AND alumno_sedeid = :sede2
																		GROUP BY 
																			pago_alumnoid
																	) BASE
																) PEN ON PEN.pago_alumnoid = A.alumno_id
																WHERE A.alumno_estado <> 'E'
																	AND PEN.TOTAL > 0 OR P.SALDO > 0 
															) AS subconsulta;", [':sede1' => (int)$sedeid, ':sede2' => (int)$sedeid]);
			return $pagosPendientes;
		}

		/**
		 * Cuántos ALUMNOS distintos tienen mora, en todas las sedes.
		 *
		 *
		 * POR QUE HACE FALTA UN METODO APARTE
		 *
		 * obtenerPagosPendientes() suma NUM_SALDO + NUM_PENSION: son PARTIDAS
		 * de pago pendientes, no personas. Para la tarjeta por sede eso es lo
		 * correcto y su rotulo lo dice —«Pagos pendientes»—, pero el
		 * consolidado usaba ese mismo numero bajo el rotulo «Alumnos con
		 * mora».
		 *
		 * El resultado era una cifra imposible: 433 morosos con 266 alumnos
		 * activos. Quien entra al panel lee eso y decide con ello.
		 *
		 * Se cuentan alumnos, con el MISMO criterio de mora que usa la otra
		 * consulta: o le quedan pensiones sin pagar, o tiene saldo pendiente.
		 *
		 *
		 * EL PARENTESIS NO ES DECORATIVO
		 *
		 * La consulta original termina en:
		 *
		 *     WHERE A.alumno_estado <> 'E' AND PEN.TOTAL > 0 OR P.SALDO > 0
		 *
		 * Como AND liga mas fuerte que OR, eso se lee «(no eliminado Y debe
		 * pensiones) O (tiene saldo)», y un alumno ELIMINADO con saldo entra
		 * igual. Aqui va con parentesis explicitos para que el estado se
		 * aplique siempre. La consulta vieja se deja como esta: cambiarla
		 * moveria las cifras por sede, y eso es una decision de la escuela y
		 * no un arreglo de rotulo.
		 */
		public function alumnosConMora(){
			return $this->ejecutarConsulta(
				"SELECT COUNT(*) AS alumnosConMora
				   FROM (
						SELECT A.alumno_id
						  FROM sujeto_alumno A
						  LEFT JOIN (
								SELECT pago_alumnoid, SUM(pago_saldo) AS SALDO
								  FROM alumno_pago
								 WHERE pago_estado = 'P' AND pago_saldo > 0
								 GROUP BY pago_alumnoid
						  ) P ON P.pago_alumnoid = A.alumno_id
						  LEFT JOIN (
								SELECT BASE.pago_alumnoid,
									   CASE WHEN BASE.FECHA > CURDATE() THEN 0 ELSE
											GREATEST(0, TIMESTAMPDIFF(MONTH, BASE.FECHA, CURDATE())
													  + (DAY(CURDATE()) < DAY(BASE.FECHA))) END AS PENSIONES
								  FROM (
										SELECT MAX(pago_fecha) AS FECHA, pago_alumnoid
										  FROM sujeto_alumno
										  LEFT JOIN alumno_pago ON pago_alumnoid = alumno_id
										 WHERE pago_rubroid = 'RPE' AND alumno_estado <> 'I'
										 GROUP BY pago_alumnoid
								  ) BASE
						  ) PEN ON PEN.pago_alumnoid = A.alumno_id
						 WHERE A.alumno_estado <> 'E'
						   AND (COALESCE(PEN.PENSIONES, 0) > 0 OR COALESCE(P.SALDO, 0) > 0)
						 GROUP BY A.alumno_id
				   ) AS conMora;");
		}

		/**
		 * Lo que requiere accion, para la franja de arriba del panel.
		 *
		 *
		 * POR QUE EXISTE ESTA FRANJA
		 *
		 * El panel enseñaba treinta y dos tarjetas del mismo peso visual
		 * —cuatro consolidadas y cuatro por cada una de las siete sedes—. Para
		 * saber si algo iba mal habia que leerlas todas y comparar de cabeza.
		 * Un panel que no dice que hacer hoy es un informe, no un panel.
		 *
		 * Aqui van solo las cosas sobre las que se puede ACTUAR, cada una con
		 * el sitio donde se resuelve. Las que estan a cero no se pintan: una
		 * franja llena de ceros vuelve a ser ruido.
		 *
		 *
		 * TODO EN SQL, NADA DE DISCO
		 *
		 * La cuenta de fotos mira solo si la ficha tiene NOMBRE de archivo. Si
		 * ademas ese archivo esta en el servidor lo dice la pantalla de Fotos
		 * de alumnos, que para eso mira el disco; hacerlo aqui serian
		 * trescientas comprobaciones de fichero en cada carga del panel.
		 */
		public function requiereAtencion(){
			return $this->ejecutarConsulta(
				"SELECT
					(SELECT COUNT(*) FROM sujeto_alumno
					  WHERE alumno_estado = 'A'
						AND (alumno_imagen IS NULL OR alumno_imagen = '')) AS sinFoto,
					(SELECT COUNT(*) FROM sujeto_alumno a
					  WHERE a.alumno_estado = 'A'
						AND NOT EXISTS (SELECT 1 FROM alumno_consentimiento c
										 WHERE c.consent_alumnoid = a.alumno_id
										   AND c.consent_tipo     = 'DATOS'
										   AND c.consent_otorgado = 'S')) AS sinConsentimiento");
		}

		public function ingresosLugarEntr(){
			// Fechas dinámicas
			$fecha_inicio = date('Y-m-01'); // Primer día del mes actual
			$fecha_fin = date('Y-m-t');     // Último día del mes actual
			$consulta_datos="SELECT sede_nombre, lugar_nombre, ALUMNOS_ENTRENAN, PENSIONES_ESTIMADAS as TOTALPENSIONES, IFNULL(PA.VALOR_PAGADO,0) + IFNULL(Abonos.VALOR_PAGADO,0) as TOTALRECAUDADO, IFNULL(NP.Total,0) as ALUMNOS_ADEUDAN, IFNULL(SR.SinRegistro,0) as ALUMNOS_SINREGPAGOS
								FROM(select sede_id, sede_nombre, lugar_id ,lugar_nombre, count(1) as ALUMNOS_ENTRENAN, sum(IFNULL(descuento_valor,sede_pension)) as PENSIONES_ESTIMADAS
										from( 
												SELECT distinct detalle_lugarid, asignahorario_alumnoid 
												from asistencia_asignahorario
														inner join asistencia_horario_detalle on detalle_horarioid = asignahorario_horarioid
														inner join sujeto_alumno on alumno_id = asignahorario_alumnoid                                                                  
												where alumno_estado = 'A' and alumno_fechaingreso <= :f1
										)l
												inner join asistencia_lugar on lugar_id = l.detalle_lugarid
												inner join general_sede on lugar_sedeid = sede_id 
												left join alumno_pago_descuento d on d.descuento_alumnoid = l.asignahorario_alumnoid and descuento_estado = 'S' and descuento_fecha <= :f2
										group by sede_id, sede_nombre, lugar_id ,lugar_nombre
										
										union 
										
										select SLE.sede_id, SLE.sede_nombre, 0,'SIN LUGAR DE ENTRENAMIENTO' lugar_nombre, count(1) as ALUMNOS_ENTRENAN, sum(SLE.pension_estimada) PENSIONES_ESTIMADAS
												FROM(select sede_id, sede_nombre, 0,'SIN LUGAR DE ENTRENAMIENTO' lugar_nombre, IFNULL(descuento_valor,s.sede_pension) pension_estimada
																from sujeto_alumno a
																left join alumno_pago_descuento d on d.descuento_alumnoid = a.alumno_id and descuento_estado = 'S' and descuento_fecha <= :f3              
																left join general_sede s on s.sede_id = a.alumno_sedeid
																where a.alumno_id not in (select asignahorario_alumnoid from asistencia_asignahorario)
																and a.alumno_estado = 'A' and a.alumno_fechaingreso <= :f4) SLE
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
																																and P.pago_fecha BETWEEN :i1 and :f5) Pagos   
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
																and transaccion_fecha BETWEEN :i2 and :f6
																and pago_fecha BETWEEN :i3 and :f7
																group by sedeid, lugarid
												)Abonos on Abonos.sedeid = Base.sede_id AND Abonos.lugarid = Base.lugar_id 
												
								left join(select A.alumno_sedeid as sedeid, IFNULL(h.detalle_lugarid,0)  AS lugarid, count(1) as Total
												from(select P.pago_alumnoid, max(P.pago_fecha) fecha
																from alumno_pago P
																where P.pago_rubroid = 'RPE' and P.pago_estado <> 'E'
																GROUP BY P.pago_alumnoid
																having  max(P.pago_fecha) < :i4
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

			$datos = $this->ejecutarConsulta($consulta_datos, [':f1' => $fecha_fin, ':f2' => $fecha_fin, ':f3' => $fecha_fin, ':f4' => $fecha_fin, ':i1' => $fecha_inicio, ':f5' => $fecha_fin, ':i2' => $fecha_inicio, ':f6' => $fecha_fin, ':i3' => $fecha_inicio, ':f7' => $fecha_fin, ':i4' => $fecha_inicio]);			
			return $datos;		
		}
		public function obtenerRepresentantes(){
			$representantes=$this->ejecutarConsulta("SELECT count(*) totalRepresentantes FROM alumno_representante WHERE repre_estado='A'");
		    return $representantes;
		}

		public function totalAlumnosActivos(){
			$alumnosActivos=$this->ejecutarConsulta("SELECT count(*) totalAlumnosActivos FROM sujeto_alumno WHERE alumno_estado='A'");
		    return $alumnosActivos;
		}
		public function totalAlumnosInactivos(){
			$alumnosInactivos=$this->ejecutarConsulta("SELECT count(*) totalAlumnosInactivos FROM sujeto_alumno WHERE alumno_estado='I'");
		    return $alumnosInactivos;
		}

		/*==================================================================
		  Panel operativo: lo que necesita quien da clase, no quien cobra
		  ==================================================================
		  Un profesor no tiene por qué ver recaudación ni morosidad. Lo que
		  le sirve es saber qué horarios lleva, cuántos alumnos hay en cada
		  uno y en qué días del mes ya registró la asistencia.
		*/

		/**
		 * Horarios que atiende un empleado.
		 *
		 * Un horario se reparte en franjas (día + hora + lugar) dentro de
		 * asistencia_horario_detalle, así que se agrupan para presentar una
		 * fila por horario con sus días.
		 */
		public function horariosDelEmpleado(int $empleadoid){
			$consulta = "SELECT h.horario_id, h.horario_nombre, h.horario_detalle,
								h.horario_sedeid, s.sede_nombre,
								GROUP_CONCAT(DISTINCT d.detalle_dia ORDER BY d.detalle_dia) AS dias,
								MIN(ho.hora_inicio) AS hora_inicio,
								MAX(ho.hora_fin)    AS hora_fin,
								GROUP_CONCAT(DISTINCT l.lugar_nombre ORDER BY l.lugar_nombre SEPARATOR ', ') AS lugares,
								(SELECT COUNT(*) FROM asistencia_asignahorario a
								  WHERE a.asignahorario_horarioid = h.horario_id) AS alumnos
						   FROM asistencia_horario_detalle d
						   JOIN asistencia_horario h ON h.horario_id = d.detalle_horarioid
													AND h.horario_estado = 'A'
						   LEFT JOIN general_sede      s  ON s.sede_id  = h.horario_sedeid
						   LEFT JOIN asistencia_hora   ho ON ho.hora_id = d.detalle_horaid
						   LEFT JOIN asistencia_lugar  l  ON l.lugar_id = d.detalle_lugarid
						  WHERE d.detalle_profesorid = :emp
						  GROUP BY h.horario_id, h.horario_nombre, h.horario_detalle,
								   h.horario_sedeid, s.sede_nombre
						  ORDER BY s.sede_nombre, MIN(ho.hora_inicio), h.horario_nombre";

			return $this->ejecutarConsulta($consulta, [':emp' => $empleadoid])->fetchAll();
		}

		/**
		 * Horarios de un conjunto de sedes.
		 *
		 * Es el alcance de quien no da clase pero acompaña la operación —un
		 * asistente de sede—: no tiene horarios propios, pero sí necesita
		 * ver los de las sedes que atiende.
		 */
		public function horariosDeSedes(array $sedes){
			if (!$sedes) { return []; }

			$marcas = implode(',', array_fill(0, count($sedes), '?'));
			$consulta = "SELECT h.horario_id, h.horario_nombre, h.horario_detalle,
								h.horario_sedeid, s.sede_nombre,
								GROUP_CONCAT(DISTINCT d.detalle_dia ORDER BY d.detalle_dia) AS dias,
								MIN(ho.hora_inicio) AS hora_inicio,
								MAX(ho.hora_fin)    AS hora_fin,
								GROUP_CONCAT(DISTINCT l.lugar_nombre ORDER BY l.lugar_nombre SEPARATOR ', ') AS lugares,
								GROUP_CONCAT(DISTINCT e.empleado_nombre ORDER BY e.empleado_nombre SEPARATOR ', ') AS profesores,
								(SELECT COUNT(*) FROM asistencia_asignahorario a
								  WHERE a.asignahorario_horarioid = h.horario_id) AS alumnos
						   FROM asistencia_horario h
						   LEFT JOIN asistencia_horario_detalle d ON d.detalle_horarioid = h.horario_id
						   LEFT JOIN general_sede      s  ON s.sede_id  = h.horario_sedeid
						   LEFT JOIN asistencia_hora   ho ON ho.hora_id = d.detalle_horaid
						   LEFT JOIN asistencia_lugar  l  ON l.lugar_id = d.detalle_lugarid
						   LEFT JOIN sujeto_empleado   e  ON e.empleado_id = d.detalle_profesorid
						  WHERE h.horario_estado = 'A' AND h.horario_sedeid IN ($marcas)
						  GROUP BY h.horario_id, h.horario_nombre, h.horario_detalle,
								   h.horario_sedeid, s.sede_nombre
						  ORDER BY s.sede_nombre, MIN(ho.hora_inicio), h.horario_nombre";

			return $this->ejecutarConsulta($consulta, array_values($sedes))->fetchAll();
		}

		/** Sedes asignadas a un usuario. Vacío = sin restricción por sede. */
		public function sedesDelUsuario(int $usuarioid){
			$filas = $this->ejecutarConsulta(
				"SELECT us.usuariosede_sedeid AS sede_id, s.sede_nombre
				   FROM seguridad_usuario_sede us
				   LEFT JOIN general_sede s ON s.sede_id = us.usuariosede_sedeid
				  WHERE us.usuariosede_usuarioid = :u
				  ORDER BY s.sede_nombre",
				[':u' => $usuarioid]
			)->fetchAll();

			return $filas;
		}

		/**
		 * Días del mes en que se registró asistencia, por horario.
		 *
		 * La asistencia vive en 31 columnas (asistencia_D01..D31), una por
		 * día, así que se suman las 31 de una vez en lugar de consultar día
		 * a día. Devuelve [horario_id => [dia => marcas]].
		 */
		public function diasConAsistencia(array $horarios, int $aniomes){
			if (!$horarios) { return []; }

			$sumas = [];
			for ($d = 1; $d <= 31; $d++) {
				$c = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
				$sumas[] = "SUM(a.asistencia_D$c <> '') AS d$c";
			}

			$marcas = implode(',', array_fill(0, count($horarios), '?'));
			$consulta = "SELECT ah.asignahorario_horarioid AS horario, " . implode(', ', $sumas) . "
						   FROM asistencia_asignahorario ah
						   JOIN asistencia_asistencia a
								 ON a.asistencia_alumnoid = ah.asignahorario_alumnoid
								AND a.asistencia_aniomes  = ?
						  WHERE ah.asignahorario_horarioid IN ($marcas)
						  GROUP BY ah.asignahorario_horarioid";

			$parametros = array_merge([$aniomes], array_values($horarios));
			$filas = $this->ejecutarConsulta($consulta, $parametros)->fetchAll();

			$salida = [];
			foreach ($filas as $f) {
				$dias = [];
				for ($d = 1; $d <= 31; $d++) {
					$n = (int)$f['d' . str_pad((string)$d, 2, '0', STR_PAD_LEFT)];
					if ($n > 0) { $dias[$d] = $n; }
				}
				$salida[(int)$f['horario']] = $dias;
			}

			return $salida;
		}
	}

		