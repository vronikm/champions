<?php

	namespace app\controllers;
	use app\models\mainModel;
	use PDO;

	/**
	 * Consulta y registro de consentimientos LOPDP.
	 *
	 * Los que llegan por el formulario público se graban con origen FORMULARIO
	 * desde ds_form (registroController). Acá se consultan todos y se registran los
	 * que la escuela recibe por otra vía (formulario en papel, correo), con
	 * origen ADMIN y el usuario que los ingresó.
	 */
	class consentimientoController extends mainModel {

		const TIPOS   = ['DATOS', 'IMAGEN'];
		const ORIGENES = ['FORMULARIO', 'ADMIN'];

		/**
		 * Alumnos activos con el estado actual de cada consentimiento.
		 * El estado vigente es la última fila de cada tipo.
		 */
		public function listarConsentimientos($sedeid = '', $busqueda = '') {

			$consulta = "SELECT a.alumno_id,
								a.alumno_identificacion,
								CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno, a.alumno_apellidomaterno) AS alumno,
								a.alumno_repreid,
								CONCAT_WS(' ', r.repre_primernombre, r.repre_apellidopaterno) AS representante,
								s.sede_nombre,
								r.repre_firmado,
								cd.consent_otorgado AS datos_otorgado,
								cd.consent_origen   AS datos_origen,
								cd.consent_fecha    AS datos_fecha,
								cd.consent_usuario  AS datos_usuario,
								ci.consent_otorgado AS imagen_otorgado,
								ci.consent_origen   AS imagen_origen,
								ci.consent_fecha    AS imagen_fecha,
								ci.consent_usuario  AS imagen_usuario
						 FROM sujeto_alumno a
						 INNER JOIN alumno_representante r ON r.repre_id = a.alumno_repreid
						 INNER JOIN general_sede s ON s.sede_id = a.alumno_sedeid
						 LEFT JOIN alumno_consentimiento cd
								ON cd.consent_id = (SELECT c2.consent_id FROM alumno_consentimiento c2
													 WHERE c2.consent_alumnoid = a.alumno_id AND c2.consent_tipo = 'DATOS'
													 ORDER BY c2.consent_fecha DESC, c2.consent_id DESC LIMIT 1)
						 LEFT JOIN alumno_consentimiento ci
								ON ci.consent_id = (SELECT c3.consent_id FROM alumno_consentimiento c3
													 WHERE c3.consent_alumnoid = a.alumno_id AND c3.consent_tipo = 'IMAGEN'
													 ORDER BY c3.consent_fecha DESC, c3.consent_id DESC LIMIT 1)
						 WHERE a.alumno_estado = 'A'";

			$parametros = [];

			// Los roles no administrativos solo ven sus sedes asignadas
			$rolid   = $_SESSION['rol'] ?? null;
			$usuario = $_SESSION['usuario'] ?? '';

			if ($rolid != 1 && $rolid != 2) {
				$consulta .= " AND a.alumno_sedeid IN (
									SELECT US.usuariosede_sedeid
									  FROM seguridad_usuario_sede US
									  INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
									 WHERE U.usuario_usuario = :usuario)";
				$parametros[':usuario'] = $usuario;
			}

			if ($sedeid !== '' && intval($sedeid) > 0) {
				$consulta .= " AND a.alumno_sedeid = :sede";
				$parametros[':sede'] = intval($sedeid);
			}

			if (trim($busqueda) !== '') {
				$consulta .= " AND (a.alumno_identificacion LIKE :b
								 OR a.alumno_primernombre LIKE :b
								 OR a.alumno_apellidopaterno LIKE :b
								 OR a.alumno_apellidomaterno LIKE :b)";
				$parametros[':b'] = '%' . trim($busqueda) . '%';
			}

			$consulta .= " ORDER BY a.alumno_apellidopaterno, a.alumno_primernombre";

			return $this->ejecutarConsulta($consulta, $parametros)->fetchAll(PDO::FETCH_ASSOC);
		}

		/**
		 * Historial completo de un alumno: cada otorgamiento y cada revocatoria.
		 */
		public function historialAlumno($alumnoid) {
			return $this->ejecutarConsulta(
				"SELECT consent_tipo, consent_otorgado, consent_origen, consent_usuario,
						consent_fecha, consent_ip, consent_textohash, consent_observacion
				   FROM alumno_consentimiento
				  WHERE consent_alumnoid = :id
				  ORDER BY consent_fecha DESC, consent_id DESC",
				[':id' => intval($alumnoid)]
			)->fetchAll(PDO::FETCH_ASSOC);
		}

		/**
		 * Registra un consentimiento (o su revocatoria) desde el panel.
		 * Nunca actualiza filas previas: agrega una nueva, para que el
		 * historial siga siendo demostrable.
		 */
		public function registrarConsentimientoControlador() {

			$alumnoid    = intval($this->limpiarCadena($_POST['alumno_id'] ?? '0'));
			$tipo        = strtoupper($this->limpiarCadena($_POST['tipo'] ?? ''));
			$otorgado    = strtoupper($this->limpiarCadena($_POST['otorgado'] ?? 'S'));
			$observacion = $this->limpiarCadena($_POST['observacion'] ?? '');
			$usuario     = $_SESSION['usuario'] ?? '';

			if ($usuario === '') {
				return $this->alerta('Sesión expirada', 'Vuelva a iniciar sesión.', 'error');
			}

			if (!in_array($tipo, self::TIPOS, true)) {
				return $this->alerta('Dato inválido', 'El tipo de consentimiento no es válido.', 'error');
			}

			if (!in_array($otorgado, ['S', 'N'], true)) {
				return $this->alerta('Dato inválido', 'La acción indicada no es válida.', 'error');
			}

			// El alumno debe existir y estar dentro del alcance del usuario
			$fila = $this->alumnoEnAlcance($alumnoid);

			if ($fila === null) {
				return $this->alerta('No encontrado', 'El alumno indicado no existe o no pertenece a sus sedes.', 'error');
			}

			try {
				$this->ejecutarConsulta(
					"INSERT INTO alumno_consentimiento
						(consent_alumnoid, consent_repreid, consent_tipo, consent_otorgado,
						 consent_origen, consent_usuario, consent_fecha, consent_ip,
						 consent_textohash, consent_observacion)
					 VALUES
						(:alumno, :repre, :tipo, :otorgado, 'ADMIN', :usuario, :fecha, NULL, NULL, :obs)",
					[
						':alumno'   => $alumnoid,
						':repre'    => $fila['alumno_repreid'],
						':tipo'     => $tipo,
						':otorgado' => $otorgado,
						':usuario'  => $usuario,
						':fecha'    => date('Y-m-d H:i:s'),
						':obs'      => $observacion !== '' ? $observacion : null,
					]
				);
			} catch (\Exception $e) {
				return $this->alerta('Error', 'No se pudo guardar el consentimiento.', 'error');
			}

			$accion = ($otorgado === 'S') ? 'registrado' : 'revocado';
			$nombre = ($tipo === 'DATOS') ? 'tratamiento de datos personales' : 'uso de imagen';

			return $this->alerta('Consentimiento ' . $accion,
				'Se ha ' . $accion . ' el consentimiento de ' . $nombre . '.', 'success');
		}

		/**
		 * Alumno con su representante, solo si el usuario en sesión puede verlo.
		 * Los roles administrativos (1 y 2) alcanzan a todos; el resto queda
		 * limitado a las sedes que tiene asignadas, igual que el listado.
		 */
		private function alumnoEnAlcance($alumnoid) {

			$consulta   = "SELECT alumno_id, alumno_repreid FROM sujeto_alumno WHERE alumno_id = :id";
			$parametros = [':id' => intval($alumnoid)];

			$rolid = $_SESSION['rol'] ?? null;

			if ($rolid != 1 && $rolid != 2) {
				$consulta .= " AND alumno_sedeid IN (
									SELECT US.usuariosede_sedeid
									  FROM seguridad_usuario_sede US
									  INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
									 WHERE U.usuario_usuario = :usuario)";
				$parametros[':usuario'] = $_SESSION['usuario'] ?? '';
			}

			$alumno = $this->ejecutarConsulta($consulta, $parametros);

			return ($alumno->rowCount() === 1) ? $alumno->fetch(PDO::FETCH_ASSOC) : null;
		}

		/**
		 * Historial de un alumno en HTML, para el modal de la vista.
		 */
		public function historialHTMLControlador() {
			$alumnoid = intval($this->limpiarCadena($_POST['alumno_id'] ?? '0'));

			/* Sin este filtro, cambiar el alumno_id del POST deja leer los
			   consentimientos (y la IP del representante) de cualquier sede. */
			if ($this->alumnoEnAlcance($alumnoid) === null) {
				return '<p class="text-muted mb-0">No tiene acceso a la información de este alumno.</p>';
			}

			$historial = $this->historialAlumno($alumnoid);

			if (count($historial) === 0) {
				return '<p class="text-muted mb-0">Este alumno no tiene consentimientos registrados.</p>';
			}

			$html = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">'
				  . '<thead class="thead-light"><tr>'
				  . '<th>Tipo</th><th>Acción</th><th>Origen</th><th>Registrado por</th>'
				  . '<th>Fecha</th><th>IP</th><th>Versión del texto</th><th>Observación</th>'
				  . '</tr></thead><tbody>';

			foreach ($historial as $h) {
				$badgeTipo  = ($h['consent_tipo'] === 'DATOS') ? 'text-bg-danger' : 'text-bg-success';
				$accion     = ($h['consent_otorgado'] === 'S')
							? '<span class="badge text-bg-success">Otorgado</span>'
							: '<span class="badge text-bg-secondary">Revocado</span>';
				$origen     = ($h['consent_origen'] === 'FORMULARIO')
							? '<span class="badge text-bg-info">Formulario de inscripción</span>'
							: '<span class="badge text-bg-warning">Registrado por administrador</span>';

				$html .= '<tr>'
					  . '<td><span class="badge ' . $badgeTipo . '">' . htmlspecialchars($h['consent_tipo']) . '</span></td>'
					  . '<td>' . $accion . '</td>'
					  . '<td>' . $origen . '</td>'
					  . '<td>' . htmlspecialchars($h['consent_usuario'] ?? '—') . '</td>'
					  . '<td>' . htmlspecialchars($h['consent_fecha']) . '</td>'
					  . '<td>' . htmlspecialchars($h['consent_ip'] ?? '—') . '</td>'
					  . '<td><code style="font-size:.75rem">' . htmlspecialchars(substr($h['consent_textohash'] ?? '—', 0, 12)) . '</code></td>'
					  . '<td>' . htmlspecialchars($h['consent_observacion'] ?? '') . '</td>'
					  . '</tr>';
			}

			return $html . '</tbody></table></div>';
		}

		private function alerta($titulo, $texto, $icono) {
			return json_encode([
				'tipo'   => 'simple',
				'titulo' => $titulo,
				'texto'  => $texto,
				'icono'  => $icono
			], JSON_UNESCAPED_UNICODE);
		}
	}
