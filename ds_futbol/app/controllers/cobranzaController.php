<?php

	namespace app\controllers;
	use app\models\mainModel;

	class cobranzaController extends mainModel{	
        /*----------  Matriz de representantes con opciones Ver, Actualizar, Eliminar  ----------*/

		/*==============================================================
		| El botón de aviso por WhatsApp
		|
		| DOS PROBLEMAS QUE RESUELVE
		|
		| 1. EL MENSAJE ESTABA TRES VECES. Escrito a mano en la cobranza de
		|    pensiones (dos consultas) y en la de uniformes, con el mismo
		|    texto copiado. Cambiar la redacción obligaba a acordarse de los
		|    tres sitios, y renombrar la organización dejaba dos mintiendo.
		|
		| 2. VIAJABA EN CADA FILA. El texto completo iba dentro del href:
		|    unos 280 bytes por fila que, con 220 morosos, son 62 KB de la
		|    misma frase repetida. Era un tercio del <tbody>.
		|
		| Ahora la fila lleva sólo los datos —teléfono, valor y de qué es la
		| deuda— y el enlace lo arma el navegador con la plantilla, que está
		| una sola vez en la página. Sigue siendo un <a> de verdad: se puede
		| abrir en otra pestaña y copiar, como antes.
		|==============================================================*/
		protected function botonWhatsapp(string $celular, string $valor, string $concepto): string
		{
			/* El celular llega con el 0 inicial ya recortado por quien llama:
			   wa.me quiere el número con código de país y sin ese cero. */
			return '<a class="btn float-end btn-actualizar btn-sm ms-1 js-wasap"'
			     . ' target="_blank" rel="noopener"'
			     . ' data-tel="' . htmlspecialchars($celular, ENT_QUOTES, 'UTF-8') . '"'
			     . ' data-valor="' . htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') . '"'
			     . ' data-concepto="' . htmlspecialchars($concepto, ENT_QUOTES, 'UTF-8') . '">'
			     . '<i class="fas fa-paper-plane me-1"></i>Notificar</a>';
		}

		/**
		 * La plantilla del mensaje, para que la pinte la vista UNA vez.
		 *
		 * Lleva dos marcas que sustituye el navegador: {VALOR} y {CONCEPTO}.
		 * Se devuelve sin escapar porque la vista la mete en json_encode,
		 * que ya se encarga.
		 */
		public function plantillaWhatsapp(): string
		{
			return 'Estimado representante, ' . ds_nombre_organizacion(0)
			     . ' le recuerda que a la presente fecha usted mantiene un saldo pendiente'
			     . ' de {CONCEPTO}, por el valor de USD ${VALOR}, agradecemos su gentileza'
			     . ' en realizar el pago correspondiente.';
		}
		public function pensionesValormora(){
			$tabla="";
			$consulta_datos = "select alumno_repreid as repre_id, sede_nombre, repre_identificacion, REPRE, repre_celular, ALUMNO,
                                    SUM(SALDO) + SUM(PENSION) AS TOTAL_MORA
                                     FROM(
                                        SELECT  alumno_repreid, 
                                                repre_identificacion,
                                                CONCAT_WS(' ',repre_primernombre, repre_segundonombre, repre_apellidopaterno, repre_apellidomaterno) as REPRE,
                                                IFNULL(P.SALDO,0) AS SALDO, 
                                                IFNULL(PEN.TOTAL,0) AS PENSION, 
                                                PEN.FECHA,
                                                P.FECHASALDOS,
                                                FECHATRX.FECHATRX,
                                                sede_nombre,
                                                repre_celular,
												CONCAT_WS(' ',alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) as ALUMNO
                                            FROM sujeto_alumno A
                                            inner join general_sede on sede_id = alumno_sedeid
                                            LEFT JOIN (
                                                SELECT 
                                                pago_alumnoid, alumno_repreid as REPRESALDOS,
                                                MAX(pago_fecha) AS FECHASALDOS,
                                                COUNT(pago_saldo) AS TOTAL, 
                                                SUM(pago_saldo) AS SALDO
                                                FROM alumno_pago
                                                    INNER JOIN sujeto_alumno ON alumno_id = pago_alumnoid
                                                WHERE pago_rubroid = 'RPE' AND pago_estado = 'P' AND pago_saldo > 0
                                                GROUP BY pago_alumnoid, alumno_repreid
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
                                                WHERE pago_rubroid = 'RPE' AND alumno_estado <> 'I' 
                                                GROUP BY 
                                                    pago_alumnoid
                                                ) BASE
                                            ) PEN ON PEN.pago_alumnoid = A.alumno_id
                                            inner join alumno_representante R on R.repre_id = A.alumno_repreid
                                            left join(					
                                                select pago_alumnoid, transaccion_pagoid, transaccion_estado, max(transaccion_fecha) FECHATRX
                                                    from alumno_pago_transaccion
                                                    inner join alumno_pago on pago_id = transaccion_pagoid
                                                    where transaccion_estado = 'C'
                                                        and pago_estado = 'P'
                                                    group by pago_alumnoid, transaccion_pagoid, transaccion_estado						
                                            ) FECHATRX on FECHATRX.pago_alumnoid = A.alumno_id
                                            WHERE (PEN.TOTAL > 0 OR P.SALDO > 0) 
      											  AND A.alumno_estado = 'A'  -- CONDICIÓN PARA ALUMNOS ACTIVOS
                                        ) as VALORESMORA			
                                    group by alumno_repreid, repre_identificacion, REPRE, sede_nombre, repre_celular, ALUMNO";
				
			$datos = $this->ejecutarConsulta($consulta_datos);
		
			if($datos->rowCount()>0){
				$datos = $datos->fetchAll();
			}

			foreach($datos as $rows){
                $celular = substr($rows['repre_celular'], 1);				
				$tabla.='				
					<tr>
                        <td>'.$rows['sede_nombre'].'</td>
						<td>'.$rows['repre_identificacion'].'</td>
						<td>'.$rows['REPRE'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['TOTAL_MORA'].'</td>
						<td>
							'.$this->siPuede('editar','cobranzaPension',$this->botonWhatsapp($celular, $rows['TOTAL_MORA'], 'pensiones')).'
                            <a href="'.APP_URL.'cobranzaDetallePension/'.$rows['repre_id'].'/" class="btn float-end btn-ver btn-sm ds-accion-sep"><i class="fas fa-list me-1"></i>Detalle</a>
						</td>
					</tr>';	
			}
			return $tabla;			
		}

		public function pensionesValormoraInactivos(){
			$tabla="";
			$consulta_datos = "select alumno_repreid as repre_id, sede_nombre, repre_identificacion, REPRE, repre_celular, ALUMNO,
                                    SUM(SALDO) + SUM(PENSION) AS TOTAL_MORA
                                     FROM(
                                        SELECT  alumno_repreid, 
                                                repre_identificacion,
                                                CONCAT_WS(' ',repre_primernombre, repre_segundonombre, repre_apellidopaterno, repre_apellidomaterno) as REPRE,
                                                IFNULL(P.SALDO,0) AS SALDO, 
                                                IFNULL(PEN.TOTAL,0) AS PENSION, 
                                                PEN.FECHA,
                                                P.FECHASALDOS,
                                                FECHATRX.FECHATRX,
                                                sede_nombre,
                                                repre_celular,
												CONCAT_WS(' ',alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) as ALUMNO
                                            FROM sujeto_alumno A
                                            inner join general_sede on sede_id = alumno_sedeid
                                            LEFT JOIN (
                                                SELECT 
                                                pago_alumnoid, alumno_repreid as REPRESALDOS,
                                                MAX(pago_fecha) AS FECHASALDOS,
                                                COUNT(pago_saldo) AS TOTAL, 
                                                SUM(pago_saldo) AS SALDO
                                                FROM alumno_pago
                                                    INNER JOIN sujeto_alumno ON alumno_id = pago_alumnoid
                                                WHERE pago_rubroid = 'RPE' AND pago_estado = 'P' AND pago_saldo > 0
                                                GROUP BY pago_alumnoid, alumno_repreid
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
                                                WHERE pago_rubroid = 'RPE' AND alumno_estado <> 'I' 
                                                GROUP BY 
                                                    pago_alumnoid
                                                ) BASE
                                            ) PEN ON PEN.pago_alumnoid = A.alumno_id
                                            inner join alumno_representante R on R.repre_id = A.alumno_repreid
                                            left join(					
                                                select pago_alumnoid, transaccion_pagoid, transaccion_estado, max(transaccion_fecha) FECHATRX
                                                    from alumno_pago_transaccion
                                                    inner join alumno_pago on pago_id = transaccion_pagoid
                                                    where transaccion_estado = 'C'
                                                        and pago_estado = 'P'
                                                    group by pago_alumnoid, transaccion_pagoid, transaccion_estado						
                                            ) FECHATRX on FECHATRX.pago_alumnoid = A.alumno_id
                                            WHERE (PEN.TOTAL > 0 OR P.SALDO > 0) 
      											  AND A.alumno_estado = 'I'  -- CONDICIÓN PARA ALUMNOS ACTIVOS
                                        ) as VALORESMORA			
                                    group by alumno_repreid, repre_identificacion, REPRE, sede_nombre, repre_celular, ALUMNO";
				
			$datos = $this->ejecutarConsulta($consulta_datos);
		
			if($datos->rowCount()>0){
				$datos = $datos->fetchAll();
			}

			foreach($datos as $rows){
                $celular = substr($rows['repre_celular'], 1);				
				$tabla.='				
					<tr>
                        <td>'.$rows['sede_nombre'].'</td>
						<td>'.$rows['repre_identificacion'].'</td>
						<td>'.$rows['REPRE'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['TOTAL_MORA'].'</td>
						<td>
							'.$this->siPuede('editar','cobranzaPension',$this->botonWhatsapp($celular, $rows['TOTAL_MORA'], 'pensiones')).'
                            <a href="'.APP_URL.'cobranzaDetallePension/'.$rows['repre_id'].'/" class="btn float-end btn-ver btn-sm ds-accion-sep"><i class="fas fa-list me-1"></i>Detalle</a>
						</td>
					</tr>';	
			}
			return $tabla;			
		}

		public function uniformesValormora(){
			$tabla="";
			$consulta_datos = "SELECT alumno_repreid as repre_id, sede_nombre, repre_identificacion, REPRE, repre_celular, ALUMNO, SUM(SALDO) AS TOTAL_MORA
									FROM(
										SELECT 
												alumno_repreid, 
												repre_identificacion,
												CONCAT_WS(' ',repre_primernombre, repre_segundonombre, repre_apellidopaterno, repre_apellidomaterno) as REPRE,
												IFNULL(P.SALDO,0) AS SALDO, 
												P.FECHASALDOS,
												FECHATRX.FECHATRX,
												sede_nombre,
												repre_celular,
												CONCAT_WS(' ',alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) as ALUMNO
											FROM sujeto_alumno A
											inner join general_sede on sede_id = alumno_sedeid
											LEFT JOIN (
												SELECT 
												pago_alumnoid, alumno_repreid as REPRESALDOS,
												MAX(pago_fecha) AS FECHASALDOS,
												COUNT(pago_saldo) AS TOTAL, 
												SUM(pago_saldo) AS SALDO
												FROM alumno_pago
													INNER JOIN sujeto_alumno ON alumno_id = pago_alumnoid
												WHERE pago_rubroid = 'RNU' AND pago_estado = 'P' AND pago_saldo > 0
												GROUP BY pago_alumnoid, alumno_repreid
											) P ON P.pago_alumnoid = A.alumno_id                                      
											inner join alumno_representante R on R.repre_id = A.alumno_repreid
											left join(					
												select pago_alumnoid, transaccion_pagoid, transaccion_estado, max(transaccion_fecha) FECHATRX
													from alumno_pago_transaccion
													inner join alumno_pago on pago_id = transaccion_pagoid
													where transaccion_estado = 'C'
														and pago_estado = 'P'
															group by pago_alumnoid, transaccion_pagoid, transaccion_estado						
													) FECHATRX on FECHATRX.pago_alumnoid = A.alumno_id
													WHERE P.SALDO > 0 AND A.alumno_estado <> 'E'   -- sin eliminados
												) as VALORESMORA			
											group by alumno_repreid, repre_identificacion, REPRE, sede_nombre, repre_celular, ALUMNO";
				
			$datos = $this->ejecutarConsulta($consulta_datos);
		
			if($datos->rowCount()>0){
				$datos = $datos->fetchAll();
			}

			foreach($datos as $rows){
                $celular = substr($rows['repre_celular'], 1);				
				$tabla.='				
					<tr>
                        <td>'.$rows['sede_nombre'].'</td>
						<td>'.$rows['repre_identificacion'].'</td>
						<td>'.$rows['REPRE'].'</td>
						<td>'.$rows['ALUMNO'].'</td>
						<td>'.$rows['TOTAL_MORA'].'</td>
						<td>
							'.$this->siPuede('editar','cobranzaUniforme',$this->botonWhatsapp($celular, $rows['TOTAL_MORA'], 'uniformes')).'
                            <a href="'.APP_URL.'cobranzaDetalleUniforme/'.$rows['repre_id'].'/" class="btn float-end btn-ver btn-sm ds-accion-sep"><i class="fas fa-list me-1"></i>Detalle</a>
						</td>
					</tr>';	
			}
			return $tabla;			
		}

		public function pensionesPendientes($repre_id){		
			$tabla="";
			$NUM_SALDO = 0;
			$SALDO = 0;
			$NUM_PENSION = 0;
			$PENSION = 0;
			$consulta_datos="SELECT 
									alumno_id, 
									alumno_identificacion,
                                    sede_nombre, 
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
									WHERE pago_rubroid = 'RPE' AND pago_estado = 'P' AND pago_saldo > 0 AND alumno_repreid = :repre 
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
									WHERE pago_rubroid = 'RPE' AND alumno_estado <> 'I' AND alumno_repreid = :repre 
									GROUP BY 
										pago_alumnoid
									) BASE
								) PEN ON PEN.pago_alumnoid = A.alumno_id
                                 inner join general_sede on alumno_sedeid = sede_id
								WHERE A.alumno_estado <> 'E' AND (PEN.TOTAL > 0 OR P.SALDO > 0)   -- sin eliminados
								ORDER BY PEN.PENSIONES DESC";
			
			$datos = $this->ejecutarConsulta($consulta_datos, [':repre' => (int)$repre_id]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
				$NUM_SALDO += $rows['NUM_SALDO'];
				$SALDO += $rows['SALDO'];
				$NUM_PENSION += $rows['NUM_PENSION'];
				$PENSION += $rows['PENSION'];

				$tabla.='
					<tr data-widget="expandable-table" aria-expanded="false">
                        <td>'.$rows['sede_nombre'].'</td>
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['NOMBRES'].'</td>
						<td>'.$rows['NUM_SALDO'].'</td>
						<td>'.$rows['SALDO'].'</td>
						<td>'.$rows['NUM_PENSION'].'</td>
						<td>'.$rows['PENSION'].'</td>						
					</tr>';							
			}

			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td colspan="3">SUB TOTAL</td>					
					<td>'.$NUM_SALDO.'</td>
					<td>'.number_format($SALDO, 2, '.',',').'</td>
					<td>'.$NUM_PENSION.'</td>
					<td>'.number_format($PENSION, 2, '.',',').'</td>						
				</tr>';	

			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td colspan="5">TOTAL</td>				
					<td>'.$NUM_PENSION + $NUM_SALDO.'</td>
					<td>'.number_format($PENSION + $SALDO, 2, '.',',').'</td>						
				</tr>';
				
			return $tabla;
		}
		
		public function uniformesPendientes($repre_id){		
			$tabla="";
			$NUM_SALDO = 0;
			$SALDO = 0;
			$NUM_PENSION = 0;
			$PENSION = 0;
			$consulta_datos="SELECT 
									alumno_id, 
									alumno_identificacion,
                                    sede_nombre, 
									CONCAT_WS(' ', alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) AS NOMBRES,  
									IFNULL(P.TOTAL,0) AS NUM_SALDO, 
									IFNULL(P.SALDO,0) AS SALDO
								FROM sujeto_alumno A
								LEFT JOIN (
									SELECT 
									pago_alumnoid, 
									COUNT(pago_saldo) AS TOTAL, 
									SUM(pago_saldo) AS SALDO
									FROM alumno_pago
										INNER JOIN sujeto_alumno ON alumno_id = pago_alumnoid
									WHERE pago_rubroid = 'RNU' AND pago_estado = 'P' AND pago_saldo > 0 AND alumno_repreid = :repre 
									GROUP BY pago_alumnoid
								) P ON P.pago_alumnoid = A.alumno_id
                                 inner join general_sede on alumno_sedeid = sede_id
								WHERE P.SALDO > 0 AND A.alumno_estado <> 'E'";   /* sin eliminados */
			
			$datos = $this->ejecutarConsulta($consulta_datos, [':repre' => (int)$repre_id]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){	
				$NUM_SALDO += $rows['NUM_SALDO'];
				$SALDO += $rows['SALDO'];

				$tabla.='
					<tr data-widget="expandable-table" aria-expanded="false">
                        <td>'.$rows['sede_nombre'].'</td>
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['NOMBRES'].'</td>
						<td>'.$rows['NUM_SALDO'].'</td>
						<td>'.$rows['SALDO'].'</td>					
					</tr>';							
			}

			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td colspan="3">SUB TOTAL</td>					
					<td>'.$NUM_SALDO.'</td>
					<td>'.number_format($SALDO, 2, '.',',').'</td>					
				</tr>';	

			$tabla.='
				<tr data-widget="expandable-table" aria-expanded="false">
					<td colspan="3">TOTAL</td>				
					<td>'.$NUM_SALDO.'</td>
					<td>'.number_format($SALDO, 2, '.',',').'</td>						
				</tr>';
				
			return $tabla;
		}
    }
		
