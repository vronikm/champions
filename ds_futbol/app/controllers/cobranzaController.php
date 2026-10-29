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
		/*==============================================================
		| Cobranza de pensiones (cobranzaPension): una fila por alumno activo
		| con mora.
		|
		| TOTAL MORA = saldos pendientes de pensión + pensiones no pagadas
		| desde la última, al valor del descuento si lo tiene o al de su sede.
		|
		| Junto a la mora, lo que ayuda a decidir a quién llamar primero:
		| fecha de ingreso, último pago de pensión (fecha y mes que cubrió) y
		| asistencia, con las mismas fórmulas que el listado de pagos
		| (pagosController::listarAlumnosPagos).
		|
		| 2026-10-29: fuera el cruce con las transacciones (FECHATRX). No se
		| mostraba, y un alumno con dos abonos parciales salía dos veces en
		| la suma y duplicaba su mora. Con los datos de hoy no cambiaba
		| ninguna cifra (99 alumnos, mismo total).
		|==============================================================*/
		public function pensionesValormora(){
			$consulta_datos = "SELECT V.*, UP.ultima, UP.periodo,
										COALESCE(AS1.dias, 0) AS dias, COALESCE(AS1.asistidos, 0) AS asistidos
									FROM (
										SELECT alumno_id, alumno_repreid AS repre_id, sede_nombre, repre_identificacion, REPRE,
											   repre_celular, repre_correo, ALUMNO, alumno_identificacion, alumno_estado, edad, alumno_fechaingreso,
											   SUM(SALDO) + SUM(PENSION) AS TOTAL_MORA, SUM(SALDO) AS SALDO, SUM(MESES) AS MESES
										  FROM (
											SELECT  A.alumno_id, alumno_repreid,
													repre_identificacion, repre_celular, repre_correo,
													CONCAT_WS(' ',repre_primernombre, repre_segundonombre, repre_apellidopaterno, repre_apellidomaterno) as REPRE,
													IFNULL(P.SALDO,0) AS SALDO,
													IFNULL(PEN.TOTAL,0) AS PENSION,
													IFNULL(PEN.PENSIONES,0) AS MESES,
													sede_nombre,
													CONCAT_WS(' ',alumno_primernombre, alumno_segundonombre, alumno_apellidopaterno, alumno_apellidomaterno) as ALUMNO,
													alumno_identificacion, alumno_estado, alumno_fechaingreso,
													TIMESTAMPDIFF(YEAR, alumno_fechanacimiento, CURDATE()) AS edad
												FROM sujeto_alumno A
												inner join general_sede on sede_id = alumno_sedeid
												LEFT JOIN (
													SELECT pago_alumnoid, SUM(pago_saldo) AS SALDO
													  FROM alumno_pago
													 WHERE pago_rubroid = 'RPE' AND pago_estado = 'P' AND pago_saldo > 0
													 GROUP BY pago_alumnoid
												) P ON P.pago_alumnoid = A.alumno_id
												LEFT JOIN (
													SELECT
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
												WHERE (PEN.TOTAL > 0 OR P.SALDO > 0)
												  AND A.alumno_estado = 'A'  -- CONDICIÓN PARA ALUMNOS ACTIVOS
										  ) as VALORESMORA
										 GROUP BY alumno_id, alumno_repreid, sede_nombre, repre_identificacion, REPRE, repre_celular, repre_correo,
												  ALUMNO, alumno_identificacion, alumno_estado, edad, alumno_fechaingreso
									) V
									LEFT JOIN (SELECT pago_alumnoid, MAX(pago_fecha) AS ultima,
													  SUBSTRING_INDEX(GROUP_CONCAT(pago_periodo ORDER BY pago_fecha DESC, pago_id DESC SEPARATOR '|'), '|', 1) AS periodo
												 FROM alumno_pago WHERE pago_rubroid = 'RPE' AND pago_estado <> 'E'
												GROUP BY pago_alumnoid) UP ON UP.pago_alumnoid = V.alumno_id
									LEFT JOIN (SELECT dia_alumnoid, COUNT(*) AS dias, SUM(dia_marca IN ('P','A')) AS asistidos
												 FROM insights_v_asistencia_dia GROUP BY dia_alumnoid) AS1 ON AS1.dia_alumnoid = V.alumno_id
									ORDER BY V.REPRE, V.ALUMNO";

			$datos = $this->ejecutarConsulta($consulta_datos)->fetchAll();

			$puedeNotificar = puede('editar', 'cobranzaPension');

			$this->totalMora = 0.0;
			$tabla = "";
			foreach($datos as $rows){
				$c    = $this->datosCobranza($rows);
				$mora = round((float)$rows['TOTAL_MORA'], 2);
				$this->totalMora += $mora;

				/* De qué se compone la mora: «19 pensiones + saldo $75.00». */
				$meses = (int)$rows['MESES'];
				$saldo = round((float)$rows['SALDO'], 2);
				$detalleMora = [];
				if ($meses > 0) { $detalleMora[] = $meses . ($meses === 1 ? ' pensión' : ' pensiones'); }
				if ($saldo > 0) { $detalleMora[] = 'saldo $' . number_format($saldo, 2, '.', ','); }

				/* Último pago de pensión: la fecha y el mes que cubrió. */
				$ultima = $this->fechaValida($rows['ultima']);
				$celdaUltima = $ultima !== ''
					? date('d/m/Y', strtotime($ultima)) . (trim((string)$rows['periodo']) !== '' ? '<span class="d-block small text-body-secondary">' . $this->html($rows['periodo']) . '</span>' : '')
					: '<span class="text-body-secondary">Sin pagos de pensión</span>';

				$tabla .= '
					<tr>
						' . $c['tdRepre'] . '
						' . $c['tdAlumno'] . '
						' . $c['tdIngreso'] . '
						<td class="ds-ficha__dato" data-etiqueta="Último pago de pensión" data-order="' . $this->html($ultima) . '">' . $celdaUltima . '</td>
						' . $c['tdAsistencia'] . '
						' . $this->tdMora($mora, implode(' + ', $detalleMora)) . '
						<td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">
							' . ($puedeNotificar ? $this->botonNotificar($c, (int)$rows['alumno_id'], $mora) : '') . '
							<a href="' . APP_URL . 'cobranzaDetallePension/' . (int)$rows['repre_id'] . '/" class="btn btn-sm btn-secondary"><i class="fas fa-list me-1" aria-hidden="true"></i>Detalle</a>
						</div></td>
					</tr>';
			}
			return $tabla;
		}

		/*==============================================================
		| Piezas comunes de las dos cobranzas (pensiones y uniformes)
		|==============================================================*/

		/** Texto de la base, listo para HTML (deshace entidades viejas antes de escapar). */
		protected function html($v): string
		{
			return htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
		}

		/** Una fecha Y-m-d de la base, o '' si viene vacía o en ceros. */
		protected function fechaValida($v): string
		{
			$v = (string)$v;
			return ($v !== '' && !str_starts_with($v, '0000')) ? $v : '';
		}

		/**
		 * Lo que comparten las filas de pensiones y de uniformes: el
		 * representante (cédula, celular, correo), el alumno (cédula, edad,
		 * sede y estado), la fecha de ingreso y la asistencia.
		 *
		 * Espera en $rows: REPRE, repre_identificacion, repre_celular,
		 * repre_correo, ALUMNO, alumno_identificacion, edad, sede_nombre,
		 * alumno_estado, alumno_fechaingreso, dias y asistidos.
		 */
		protected function datosCobranza(array $rows): array
		{
			$junta   = static fn($v) => preg_replace('/\s+/', ' ', trim(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
			$repre   = $junta($rows['REPRE']);
			$alumno  = $junta($rows['ALUMNO']);
			$celular = preg_replace('/\D+/', '', (string)$rows['repre_celular']);
			$correo  = trim((string)$rows['repre_correo']);
			$correo  = filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '';

			/* Representante: cómo ubicarlo. */
			$contacto = [];
			if (trim((string)$rows['repre_identificacion']) !== '') {
				$contacto[] = '<span><i class="fas fa-id-card" aria-hidden="true"></i> ' . $this->html($rows['repre_identificacion']) . '</span>';
			}
			$contacto[] = $celular !== ''
				? '<span><i class="fas fa-mobile-alt" aria-hidden="true"></i> ' . $this->html($celular) . '</span>'
				: '<span class="text-warning-emphasis"><i class="fas fa-mobile-alt" aria-hidden="true"></i> Sin celular</span>';
			$contacto[] = $correo !== ''
				? '<span class="ds-cobranza__correo"><i class="fas fa-envelope" aria-hidden="true"></i> ' . $this->html($correo) . '</span>'
				: '<span class="text-body-secondary"><i class="fas fa-envelope" aria-hidden="true"></i> Sin correo</span>';

			/* Alumno: cédula, edad y sede, y debajo su estado. */
			$meta = [];
			if (trim((string)$rows['alumno_identificacion']) !== '') {
				$meta[] = '<i class="fas fa-id-card" aria-hidden="true"></i> ' . $this->html($rows['alumno_identificacion']);
			}
			if ($rows['edad'] !== null) {
				$meta[] = '<i class="fas fa-cake-candles" aria-hidden="true"></i> ' . (int)$rows['edad'] . ((int)$rows['edad'] === 1 ? ' año' : ' años');
			}
			$meta[] = '<i class="fas fa-location-dot" aria-hidden="true"></i> ' . $this->html($rows['sede_nombre']);
			[$textoEstado, $claseEstado] = [
				'A' => ['Activo', 'bg-success-subtle text-success-emphasis border border-success-subtle'],
				'I' => ['Inactivo', 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle'],
				'E' => ['Eliminado', 'bg-danger-subtle text-danger-emphasis border border-danger-subtle'],
			][$rows['alumno_estado']] ?? [$rows['alumno_estado'], 'text-bg-secondary'];

			/* Fecha de ingreso, y cuánto lleva en la escuela. */
			$ingreso = $this->fechaValida($rows['alumno_fechaingreso']);
			$antiguedad = '';
			if ($ingreso !== '') {
				$dif = (new \DateTime($ingreso))->diff(new \DateTime('today'));
				$antiguedad = $dif->invert ? '' : ($dif->y > 0 ? $dif->y . ($dif->y === 1 ? ' año' : ' años')
					: ($dif->m > 0 ? $dif->m . ($dif->m === 1 ? ' mes' : ' meses') : 'menos de un mes'));
			}
			$celdaIngreso = $ingreso !== ''
				? date('d/m/Y', strtotime($ingreso)) . ($antiguedad !== '' ? '<span class="d-block small text-body-secondary">' . $antiguedad . '</span>' : '')
				: '<span class="text-body-secondary">—</span>';

			/* Asistencia, con cuántos días la sustentan. */
			$dias = (int)$rows['dias'];
			$pct  = $dias > 0 ? round((int)$rows['asistidos'] / $dias * 100) : null;
			$celdaAsis = $pct === null
				? '<span class="text-body-secondary">Sin registros</span>'
				: '<span class="ds-pagos__asis"><span class="ds-pagos__barra" aria-hidden="true"><span style="width:' . $pct . '%" class="' . ($pct >= 75 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger')) . '"></span></span>'
				  . '<strong>' . $pct . ' %</strong></span><span class="d-block small text-body-secondary">de ' . $dias . ($dias === 1 ? ' día' : ' días') . '</span>';

			return [
				'repre'   => $repre,
				'alumno'  => $alumno,
				'celular' => $celular,
				'correo'  => $correo,
				'tdRepre' => '<td class="ds-ficha__principal" data-order="' . $this->html($repre) . '">
							<div class="ds-ficha__nombre">' . $this->html($repre) . '</div>
							<div class="ds-ficha__meta ds-cobranza__contacto">' . implode('', $contacto) . '</div>
						</td>',
				'tdAlumno' => '<td class="ds-ficha__dato" data-etiqueta="Alumno" data-order="' . $this->html($alumno) . '">
							<span class="ds-cobranza__alumno">' . $this->html($alumno) . '</span>
							<span class="ds-cobranza__meta"><span>' . implode('</span><span>', $meta) . '</span></span>
							<span class="ds-cobranza__estado-linea"><span class="badge ds-cobranza__estado ' . $claseEstado . '">'
								. '<i class="fas fa-circle" aria-hidden="true"></i>' . $this->html($textoEstado) . '</span></span>
						</td>',
				'tdIngreso' => '<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Fecha de ingreso" data-order="' . $this->html($ingreso) . '">' . $celdaIngreso . '</td>',
				'tdAsistencia' => '<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Asistencia" data-order="' . ($pct ?? -1) . '">' . $celdaAsis . '</td>',
			];
		}

		/** El importe en mora y, debajo, de qué se compone. */
		protected function tdMora(float $mora, string $detalle): string
		{
			return '<td class="ds-ficha__estado ds-cobranza__mora" data-order="' . number_format($mora, 2, '.', '') . '">
							<span class="badge text-bg-danger ds-pagos__deuda">$' . number_format($mora, 2, '.', ',') . '</span>
							<span class="d-block small text-body-secondary">' . $detalle . '</span>
						</td>';
		}

		/** Notificar: el canal (WhatsApp o correo) se elige en el modal, viendo el mensaje. */
		protected function botonNotificar(array $c, int $alumnoId, float $mora): string
		{
			return '<button type="button" class="btn btn-sm btn-success ds-ficha__primario" data-notificar'
			     . ' data-alumno-id="' . $alumnoId . '"'
			     . ' data-repre="' . $this->html($c['repre']) . '" data-alumno="' . $this->html($c['alumno']) . '"'
			     . ' data-celular="' . $this->html($c['celular']) . '" data-correo="' . $this->html($c['correo']) . '"'
			     . ' data-valor="' . number_format($mora, 2, '.', '') . '">'
			     . '<i class="fas fa-paper-plane me-1" aria-hidden="true"></i>Notificar</button>';
		}

		/** Lo que suma la columna «Total mora» del último pensionesValormora() o uniformesValormora(). */
		public float $totalMora = 0.0;

		/**
		 * Notificación de cobro por correo, desde el modal «Notificar» de
		 * cobranzaPension o cobranzaUniforme. Op «enviar_correo» (permiso
		 * editar sobre esa vista, como el botón; lo comprueba la guardia de
		 * cobranzaAjax.php).
		 *
		 * El texto lo pudo corregir el usuario en el modal. El alumno tiene
		 * que ser de los que lista esa cobranza (activo en pensiones; no
		 * eliminado en uniformes): el id llega del navegador y no se confía
		 * en él.
		 */
		public function enviarCorreoControlador(string $vista = 'cobranzaPension') {
			$alumnoId = intval($_POST['alumno_id'] ?? 0);
			$estado = $vista === 'cobranzaUniforme' ? "a.alumno_estado <> 'E'" : "a.alumno_estado = 'A'";
			$fila = $this->ejecutarConsulta(
				"SELECT r.repre_correo,
						CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno) AS alumno
				   FROM sujeto_alumno a
				   INNER JOIN alumno_representante r ON r.repre_id = a.alumno_repreid
				  WHERE a.alumno_id = :a AND $estado",
				[':a' => $alumnoId]
			)->fetch(\PDO::FETCH_ASSOC);
			if (!$fila) {
				return $this->alertaCobranza('No encontrado', $vista === 'cobranzaUniforme'
					? 'El alumno no existe o fue eliminado.' : 'El alumno no existe o ya no está activo.', 'error');
			}

			$destino = trim((string)($_POST['correo'] ?? ''));
			$destino = $destino !== '' ? $destino : trim((string)$fila['repre_correo']);
			if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
				return $this->alertaCobranza('Correo no válido', 'Escriba un correo válido para el representante.', 'error');
			}
			$cuerpo = trim(str_replace("\r", '', strip_tags((string)($_POST['cuerpo'] ?? ''))));
			if ($cuerpo === '') {
				return $this->alertaCobranza('Mensaje vacío', 'Escriba el mensaje que recibirá el representante.', 'error');
			}
			$cuerpo = mb_substr($cuerpo, 0, 1500);

			try {
				$asunto = 'Recordatorio de pago — ' . ds_nombre_organizacion();
				(new facturasController())->enviarCorreoTexto($destino, $asunto, $cuerpo);
			} catch (\Throwable $e) {
				return $this->alertaCobranza('No se envió el correo', $e->getMessage(), 'error');
			}
			return $this->alertaCobranza('Correo enviado', 'Se envió la notificación a ' . $destino . '.', 'success');
		}

		private function alertaCobranza(string $titulo, string $texto, string $icono): string {
			return json_encode(['tipo' => 'simple', 'titulo' => $titulo, 'texto' => $texto, 'icono' => $icono], JSON_UNESCAPED_UNICODE);
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

		/*==============================================================
		| Cobranza de uniformes (cobranzaUniforme): una fila por alumno con
		| uniformes por pagar (rubro RNU, pago pendiente con saldo). Los
		| eliminados no cuentan; los inactivos sí, porque lo que compraron se
		| les sigue cobrando.
		|
		| Las mismas piezas que la de pensiones (datosCobranza), y lo propio
		| de los uniformes:
		|
		|   Uniforme pendiente   qué se debe (el concepto de cada compra) y
		|                        desde cuándo
		|   Último pago          el último dinero que entró por uniformes:
		|                        un abono, o lo que se pagó al comprar
		|   Total mora           el saldo, y cuántas compras lo forman
		|
		| 2026-10-29: fuera el cruce con las transacciones (FECHATRX), que no
		| se mostraba y no filtraba por rubro: un alumno con dos abonos de
		| cualquier cosa salía repetido en la suma. Con los datos de hoy no
		| cambiaba ninguna cifra (34 alumnos, $960).
		|==============================================================*/
		public function uniformesValormora(){
			$consulta_datos = "SELECT A.alumno_id, A.alumno_repreid AS repre_id, S.sede_nombre,
										R.repre_identificacion, R.repre_celular, R.repre_correo,
										CONCAT_WS(' ', R.repre_primernombre, R.repre_segundonombre, R.repre_apellidopaterno, R.repre_apellidomaterno) AS REPRE,
										CONCAT_WS(' ', A.alumno_primernombre, A.alumno_segundonombre, A.alumno_apellidopaterno, A.alumno_apellidomaterno) AS ALUMNO,
										A.alumno_identificacion, A.alumno_estado, A.alumno_fechaingreso,
										TIMESTAMPDIFF(YEAR, A.alumno_fechanacimiento, CURDATE()) AS edad,
										P.SALDO AS TOTAL_MORA, P.PENDIENTES, P.DESDE, P.CONCEPTOS,
										UP.ultima, UP.valor AS ultimo_valor,
										COALESCE(AS1.dias, 0) AS dias, COALESCE(AS1.asistidos, 0) AS asistidos
									FROM sujeto_alumno A
									INNER JOIN general_sede S ON S.sede_id = A.alumno_sedeid
									INNER JOIN alumno_representante R ON R.repre_id = A.alumno_repreid
									INNER JOIN (
										SELECT pago_alumnoid, SUM(pago_saldo) AS SALDO, COUNT(*) AS PENDIENTES, MIN(pago_fecha) AS DESDE,
											   GROUP_CONCAT(pago_concepto ORDER BY pago_fecha, pago_id SEPARATOR '|') AS CONCEPTOS
										  FROM alumno_pago
										 WHERE pago_rubroid = 'RNU' AND pago_estado = 'P' AND pago_saldo > 0
										 GROUP BY pago_alumnoid
									) P ON P.pago_alumnoid = A.alumno_id
									LEFT JOIN (
										SELECT alumnoid, MAX(fecha) AS ultima,
											   SUBSTRING_INDEX(GROUP_CONCAT(valor ORDER BY fecha DESC SEPARATOR '|'), '|', 1) AS valor
										  FROM (
											SELECT pago_alumnoid AS alumnoid, pago_fecha AS fecha, pago_valor AS valor
											  FROM alumno_pago
											 WHERE pago_rubroid = 'RNU' AND pago_estado <> 'E' AND pago_valor > 0
											UNION ALL
											SELECT p.pago_alumnoid, t.transaccion_fecha, t.transaccion_valor
											  FROM alumno_pago_transaccion t
											  INNER JOIN alumno_pago p ON p.pago_id = t.transaccion_pagoid
											 WHERE p.pago_rubroid = 'RNU' AND t.transaccion_estado = 'C' AND t.transaccion_valor > 0
										  ) PAGOS
										 GROUP BY alumnoid
									) UP ON UP.alumnoid = A.alumno_id
									LEFT JOIN (SELECT dia_alumnoid, COUNT(*) AS dias, SUM(dia_marca IN ('P','A')) AS asistidos
												 FROM insights_v_asistencia_dia GROUP BY dia_alumnoid) AS1 ON AS1.dia_alumnoid = A.alumno_id
									WHERE A.alumno_estado <> 'E'   -- sin eliminados
									ORDER BY REPRE, ALUMNO";

			$datos = $this->ejecutarConsulta($consulta_datos)->fetchAll();
			$puedeNotificar = puede('editar', 'cobranzaUniforme');

			$this->totalMora = 0.0;
			$tabla = "";
			foreach($datos as $rows){
				$c    = $this->datosCobranza($rows);
				$mora = round((float)$rows['TOTAL_MORA'], 2);
				$this->totalMora += $mora;

				/* Uniforme pendiente: el concepto de cada compra, sin el «Pago»
				   con que se registran, y desde cuándo se debe. */
				$conceptos = array_values(array_filter(array_map(
					fn($x) => trim(preg_replace('/^pago\s+(de\s+)?/iu', '', trim(html_entity_decode($x, ENT_QUOTES | ENT_HTML5, 'UTF-8')))),
					explode('|', (string)$rows['CONCEPTOS'])
				), 'strlen'));
				$conceptos = array_map(fn($x) => mb_strtoupper(mb_substr($x, 0, 1)) . mb_substr($x, 1), $conceptos);
				$visibles  = array_slice($conceptos, 0, 2);
				$desde     = $this->fechaValida($rows['DESDE']);
				$celdaPendiente = ($visibles
						? '<span class="ds-cobranza__conceptos">' . implode('', array_map(fn($x) => '<span>' . $this->html($x) . '</span>', $visibles))
						  . (count($conceptos) > 2 ? '<span class="text-body-secondary">y ' . (count($conceptos) - 2) . ' más</span>' : '') . '</span>'
						: '<span class="text-body-secondary">Uniforme</span>')
					. ($desde !== '' ? '<span class="d-block small text-body-secondary">desde ' . date('d/m/Y', strtotime($desde)) . '</span>' : '');

				/* Último pago de uniforme: la fecha y cuánto entró. */
				$ultima = $this->fechaValida($rows['ultima']);
				$celdaUltima = $ultima !== ''
					? date('d/m/Y', strtotime($ultima)) . '<span class="d-block small text-body-secondary">$' . number_format((float)$rows['ultimo_valor'], 2, '.', ',') . '</span>'
					: '<span class="text-body-secondary">Sin pagos de uniforme</span>';

				$pendientes = (int)$rows['PENDIENTES'];
				$detalleMora = $pendientes . ($pendientes === 1 ? ' compra pendiente' : ' compras pendientes');

				$tabla .= '
					<tr>
						' . $c['tdRepre'] . '
						' . $c['tdAlumno'] . '
						' . $c['tdIngreso'] . '
						<td class="ds-ficha__dato" data-etiqueta="Uniforme pendiente" data-order="' . $this->html($desde) . '">' . $celdaPendiente . '</td>
						<td class="ds-ficha__dato" data-etiqueta="Último pago de uniforme" data-order="' . $this->html($ultima) . '">' . $celdaUltima . '</td>
						' . $c['tdAsistencia'] . '
						' . $this->tdMora($mora, $detalleMora) . '
						<td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">
							' . ($puedeNotificar ? $this->botonNotificar($c, (int)$rows['alumno_id'], $mora) : '') . '
							<a href="' . APP_URL . 'cobranzaDetalleUniforme/' . (int)$rows['repre_id'] . '/" class="btn btn-sm btn-secondary"><i class="fas fa-list me-1" aria-hidden="true"></i>Detalle</a>
						</div></td>
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
		
