<?php

	namespace app\controllers;
	use app\models\mainModel;
	use app\models\TokenHelper;
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
		/* ENLACE: lo respondió el representante en ds_form/autorizacion.php,
		   desde el enlace que se le envió desde esta pantalla (migración 074). */
		const ORIGENES = ['FORMULARIO', 'ADMIN', 'ENLACE'];

		/* El enlace de autorización vale una semana y una sola respuesta. */
		const VIGENCIA_ENLACE = 7 * 86400;

		/**
		 * Alumnos activos con el estado actual de cada consentimiento.
		 * El estado vigente es la última fila de cada tipo.
		 *
		 * Ya no recibe una búsqueda: la caja de la tabla filtra al vuelo.
		 */
		public function listarConsentimientos($sedeid = '') {

			$consulta = "SELECT a.alumno_id,
								a.alumno_identificacion,
								CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno, a.alumno_apellidomaterno) AS alumno,
								CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno) AS alumno_corto,
								a.alumno_repreid,
								CONCAT_WS(' ', r.repre_primernombre, r.repre_apellidopaterno) AS representante,
								r.repre_identificacion,
								r.repre_celular,
								r.repre_correo,
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

			/* Las sedes de la sesión: sedes_de_la_sesion() (ds_core/inc/seguridad.php). */
			$consulta .= " AND " . sql_sedes_de_la_sesion('a.alumno_sedeid');

			if ($sedeid !== '' && intval($sedeid) > 0) {
				$consulta .= " AND a.alumno_sedeid = :sede";
				$parametros[':sede'] = intval($sedeid);
			}

			$consulta .= " ORDER BY a.alumno_apellidopaterno, a.alumno_primernombre";

			return $this->ejecutarConsulta($consulta, $parametros)->fetchAll(PDO::FETCH_ASSOC);
		}

		/**
		 * Un alumno está pendiente si no tiene autorizado el tratamiento de
		 * datos (sin registro o revocado) o si nunca se decidió sobre su
		 * imagen. Un «no autorizo» la imagen es una respuesta: no se insiste.
		 */
		public static function alumnoPendiente(array $f): bool {
			return $f['datos_otorgado'] !== 'S' || $f['imagen_otorgado'] === null;
		}

		/**
		 * Los representantes con algún hijo pendiente, a partir de las filas de
		 * listarConsentimientos() (ya filtradas por sede), con el último enlace
		 * que se les envió.
		 */
		public function representantesPendientes(array $filas) {
			$repres = [];
			foreach ($filas as $f) {
				if (!self::alumnoPendiente($f)) { continue; }
				$id = (int)$f['alumno_repreid'];
				$repres[$id] ??= [
					'repre_id'      => $id,
					'representante' => $f['representante'],
					'identificacion'=> $f['repre_identificacion'],
					'celular'       => $f['repre_celular'],
					'correo'        => filter_var(trim((string)$f['repre_correo']), FILTER_VALIDATE_EMAIL) ? trim((string)$f['repre_correo']) : '',
					'whatsapp'      => self::celularWhatsApp($f['repre_celular']),
					'alumnos'       => [],
					'ultimo'        => null,
				];
				$repres[$id]['alumnos'][] = [
					'nombre' => $f['alumno_corto'],
					'sede'   => $f['sede_nombre'],
					'datos'  => $f['datos_otorgado'],
					'imagen' => $f['imagen_otorgado'],
				];
			}

			foreach ($this->ultimosEnlaces(array_keys($repres)) as $id => $e) {
				$repres[$id]['ultimo'] = $e;
			}

			uasort($repres, static fn($a, $b) => strcmp($a['representante'], $b['representante']));
			return array_values($repres);
		}

		/**
		 * El último enlace ENVIADO de cada representante ([] sin la migración
		 * 074). Los que sólo se prepararon al abrir el modal (SIN_ENVIAR) no
		 * cuentan: nadie los recibió.
		 */
		private function ultimosEnlaces(array $repreIds) {
			if (!$repreIds) { return []; }
			try {
				$ids = implode(',', array_map('intval', $repreIds));
				$filas = $this->ejecutarConsulta(
					"SELECT e.enlace_repreid, e.enlace_canal, e.enlace_usuario, e.enlace_fecha, e.enlace_expira, e.enlace_usado
					   FROM consentimiento_enlace e
					  WHERE e.enlace_repreid IN ($ids)
					    AND e.enlace_id = (SELECT MAX(e2.enlace_id) FROM consentimiento_enlace e2
					                        WHERE e2.enlace_repreid = e.enlace_repreid AND e2.enlace_canal <> 'SIN_ENVIAR')"
				)->fetchAll(PDO::FETCH_ASSOC);
			} catch (\Throwable $e) {
				return [];
			}
			$salida = [];
			foreach ($filas as $f) { $salida[(int)$f['enlace_repreid']] = $f; }
			return $salida;
		}

		/** 0991234567 → 593991234567 para wa.me; '' si no parece un celular. */
		public static function celularWhatsApp($celular) {
			$n = preg_replace('/\D+/', '', (string)$celular);
			if (preg_match('/^09\d{8}$/', $n)) { return '593' . substr($n, 1); }
			if (preg_match('/^5939\d{8}$/', $n)) { return $n; }
			return '';
		}

		/*----------  Enlace de autorización (migración 074)  ----------*/

		/**
		 * Prepara el enlace al abrir el modal «Enviar autorización», para que
		 * se vea el mensaje y el enlace antes de mandarlos. Op «generar_enlace»
		 * (permiso crear, como registrar).
		 *
		 * Queda como SIN_ENVIAR hasta que se envía (generar_envio). Si este
		 * usuario ya tiene uno así, abierto y vigente, se reutiliza: abrir y
		 * cerrar el modal no llena la tabla de enlaces.
		 */
		public function generarEnlaceControlador() {
			$enlace = $this->crearEnlace(intval($_POST['repre_id'] ?? 0), 'SIN_ENVIAR');
			if (isset($enlace['error'])) {
				return $this->alerta('No se preparó el enlace', $enlace['error'], 'error');
			}

			return json_encode([
				'tipo'      => 'enlace',
				'enlace_id' => $enlace['id'],
				'url'       => $enlace['url'],
				'cuerpo'    => $enlace['cuerpo'],
				'pie'       => $enlace['pie'],
				'mensaje'   => $enlace['cuerpo'] . "\n\n" . $enlace['pie'],
				'expira'    => date('d/m/Y', $enlace['expira']),
				'correo'    => $enlace['repre']['correo'],
				'celular'   => $enlace['repre']['celular'],
				'hijos'     => $enlace['repre']['lista'],
			], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		}

		/**
		 * Envía (o anota que se envió) el enlace preparado. Op «generar_envio».
		 *
		 *   WHATSAPP, COPIA   el navegador ya abrió WhatsApp o copió el mensaje;
		 *                     aquí sólo queda constancia del canal.
		 *   CORREO            sale desde el servidor, al correo indicado en el
		 *                     modal (o al de la ficha), con el texto que se
		 *                     editó. El enlace va siempre: no depende del texto.
		 *
		 * Si el correo no sale, el enlace sigue SIN_ENVIAR.
		 */
		public function generarEnvioControlador() {
			$canal = strtoupper($this->limpiarCadena($_POST['canal'] ?? ''));
			if (!in_array($canal, ['WHATSAPP', 'CORREO', 'COPIA'], true)) {
				return $this->alerta('Dato inválido', 'El canal indicado no es válido.', 'error');
			}

			try {
				$fila = $this->ejecutarConsulta(
					"SELECT enlace_id, enlace_repreid, enlace_codigo, enlace_expira, enlace_usado
					   FROM consentimiento_enlace WHERE enlace_id = :id",
					[':id' => intval($_POST['enlace_id'] ?? 0)]
				)->fetch(PDO::FETCH_ASSOC);
			} catch (\Throwable $e) {
				$fila = false;
			}
			$repre = $fila ? $this->representanteEnAlcance((int)$fila['enlace_repreid']) : null;
			if (!$fila || $repre === null) {
				return $this->alerta('No encontrado', 'El enlace no existe o el representante no pertenece a sus sedes.', 'error');
			}
			if ($fila['enlace_usado'] !== null || strtotime($fila['enlace_expira']) < time()) {
				return $this->alerta('Enlace no vigente', 'Ese enlace ya se respondió o venció. Cierre y vuelva a abrir «Enviar autorización».', 'warning');
			}

			$expira = strtotime($fila['enlace_expira']);
			$url    = TokenHelper::urlAutorizacion((int)$fila['enlace_repreid'], $fila['enlace_codigo'], $expira);
			$destino = '';

			if ($canal === 'CORREO') {
				$destino = trim((string)($_POST['correo'] ?? ''));
				$destino = $destino !== '' ? $destino : $repre['correo'];
				if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
					return $this->alerta('Correo no válido', 'Escriba un correo válido para el representante.', 'error');
				}
				/* El cuerpo lo pudo corregir el usuario; el pie con el enlace no. */
				$cuerpo = trim(str_replace("\r", '', strip_tags((string)($_POST['cuerpo'] ?? ''))));
				$cuerpo = $cuerpo !== '' ? mb_substr($cuerpo, 0, 1500) : $this->cuerpoMensaje($repre);
				try {
					$asunto = 'Autorización de datos e imagen — ' . ds_nombre_organizacion();
					(new facturasController())->enviarCorreoTexto($destino, $asunto, $cuerpo . "\n\n" . $this->pieMensaje($url, $expira));
				} catch (\Throwable $e) {
					return $this->alerta('No se envió el correo', $e->getMessage(), 'error');
				}
			}

			$this->ejecutarConsulta(
				"UPDATE consentimiento_enlace SET enlace_canal = :canal, enlace_fecha = NOW(), enlace_usuario = :u WHERE enlace_id = :id",
				[':canal' => $canal, ':u' => $_SESSION['usuario'] ?? '', ':id' => $fila['enlace_id']]
			);

			$textos = [
				'CORREO'   => ['Correo enviado', 'Se envió la autorización a ' . $destino . '. El enlace vence el ' . date('d/m/Y', $expira) . '.'],
				'WHATSAPP' => ['Listo', 'Quedó anotado el envío por WhatsApp.'],
				'COPIA'    => ['Mensaje copiado', 'Péguelo donde quiera enviarlo. El enlace vence el ' . date('d/m/Y', $expira) . '.'],
			][$canal];
			return $this->alerta($textos[0], $textos[1], 'success');
		}

		/**
		 * Envío masivo: un enlace nuevo por correo al de la ficha, con el texto
		 * de siempre. Op «generar_correo». Si el correo no sale, el enlace se
		 * borra: no queda constancia de un envío que no ocurrió.
		 */
		public function generarCorreoControlador() {
			$enlace = $this->crearEnlace(intval($_POST['repre_id'] ?? 0), 'CORREO');
			if (isset($enlace['error'])) {
				return $this->alerta('No se envió', $enlace['error'], 'error');
			}
			$correo = $enlace['repre']['correo'];

			try {
				$asunto = 'Autorización de datos e imagen — ' . ds_nombre_organizacion();
				(new facturasController())->enviarCorreoTexto($correo, $asunto, $enlace['cuerpo'] . "\n\n" . $enlace['pie']);
			} catch (\Throwable $e) {
				$this->ejecutarConsulta("DELETE FROM consentimiento_enlace WHERE enlace_id = :id", [':id' => $enlace['id']]);
				return $this->alerta('No se envió el correo', $e->getMessage(), 'error');
			}

			return $this->alerta('Correo enviado', 'Se envió el enlace a ' . $correo . '. Vence el ' . date('d/m/Y', $enlace['expira']) . '.', 'success');
		}

		/**
		 * Crea (o, para SIN_ENVIAR, reutiliza) la fila del enlace y arma la
		 * dirección y el mensaje.
		 * Devuelve ['id','url','cuerpo','pie','expira','repre'] o ['error' => texto].
		 */
		private function crearEnlace(int $repreId, string $canal) {
			$usuario = $_SESSION['usuario'] ?? '';
			if ($usuario === '') {
				return ['error' => 'Vuelva a iniciar sesión.'];
			}
			if ($faltan = TokenHelper::configFaltante()) {
				return ['error' => 'Falta configurar en el servidor: ' . implode(', ', $faltan) . '.'];
			}

			$repre = $this->representanteEnAlcance($repreId);
			if ($repre === null) {
				return ['error' => 'El representante no existe o no tiene alumnos activos en sus sedes.'];
			}
			if ($canal === 'CORREO' && $repre['correo'] === '') {
				return ['error' => 'El representante no tiene un correo válido registrado. Corríjalo en su ficha o use WhatsApp.'];
			}

			try {
				$previo = $canal !== 'SIN_ENVIAR' ? false : $this->ejecutarConsulta(
					"SELECT enlace_id, enlace_codigo, enlace_expira FROM consentimiento_enlace
					  WHERE enlace_repreid = :r AND enlace_usuario = :u AND enlace_canal = 'SIN_ENVIAR'
					    AND enlace_usado IS NULL AND enlace_expira > NOW() + INTERVAL 1 DAY
					  ORDER BY enlace_id DESC LIMIT 1",
					[':r' => $repreId, ':u' => $usuario]
				)->fetch(PDO::FETCH_ASSOC);

				if ($previo) {
					[$id, $codigo, $expira] = [(int)$previo['enlace_id'], $previo['enlace_codigo'], strtotime($previo['enlace_expira'])];
				} else {
					$codigo = bin2hex(random_bytes(16));
					$expira = time() + self::VIGENCIA_ENLACE;
					$this->ejecutarConsulta(
						"INSERT INTO consentimiento_enlace (enlace_repreid, enlace_codigo, enlace_canal, enlace_usuario, enlace_fecha, enlace_expira)
						 VALUES (:r, :c, :canal, :u, NOW(), :exp)",
						[':r' => $repreId, ':c' => $codigo, ':canal' => $canal, ':u' => $usuario, ':exp' => date('Y-m-d H:i:s', $expira)]
					);
					$id = (int)$this->ejecutarConsulta("SELECT enlace_id FROM consentimiento_enlace WHERE enlace_codigo = :c", [':c' => $codigo])->fetchColumn();
				}
			} catch (\Throwable $e) {
				return ['error' => 'Falta aplicar la migración 074 (consentimiento_enlace) en la base de datos.'];
			}

			$url = TokenHelper::urlAutorizacion($repreId, $codigo, $expira);
			return [
				'id'     => $id,
				'url'    => $url,
				'expira' => $expira,
				'repre'  => $repre,
				'cuerpo' => $this->cuerpoMensaje($repre),
				'pie'    => $this->pieMensaje($url, $expira),
			];
		}

		/*
		 * El texto del WhatsApp y del correo, en dos partes: el cuerpo, que se
		 * puede corregir en el modal, y el pie con el enlace, que no. Sin
		 * cédulas ni datos de los menores más allá del nombre.
		 */
		private static function nombrePropio($t) {
			return mb_convert_case(mb_strtolower(trim(html_entity_decode((string)$t, ENT_QUOTES, 'UTF-8')), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
		}

		private function cuerpoMensaje(array $repre) {
			return 'Hola, ' . self::nombrePropio($repre['primernombre']) . ".\n\n"
				 . ds_nombre_organizacion() . ' necesita su autorización como representante de ' . $repre['lista']
				 . ' para el tratamiento de datos personales y para el uso de imagen (LOPDP).';
		}

		private function pieMensaje(string $url, int $expira) {
			return "Puede leerlas y responder en este enlace:\n" . $url . "\n\n"
				 . 'El enlace es personal, sirve una sola vez y vence el ' . date('d/m/Y', $expira) . '.';
		}

		/**
		 * El representante, si tiene algún alumno activo en las sedes de la
		 * sesión, con los nombres de esos alumnos para el mensaje.
		 */
		private function representanteEnAlcance(int $repreId) {
			$alumnos = $this->ejecutarConsulta(
				"SELECT CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno) AS nombre,
						r.repre_primernombre, r.repre_celular, r.repre_correo
				   FROM sujeto_alumno a
				   INNER JOIN alumno_representante r ON r.repre_id = a.alumno_repreid
				  WHERE a.alumno_repreid = :r AND a.alumno_estado = 'A' AND " . sql_sedes_de_la_sesion('a.alumno_sedeid') . "
				  ORDER BY a.alumno_fechanacimiento, a.alumno_primernombre",
				[':r' => $repreId]
			)->fetchAll(PDO::FETCH_ASSOC);
			if (!$alumnos) { return null; }

			$correo = trim((string)$alumnos[0]['repre_correo']);
			$hijos  = array_map([self::class, 'nombrePropio'], array_column($alumnos, 'nombre'));
			return [
				'primernombre' => $alumnos[0]['repre_primernombre'],
				'correo'       => filter_var($correo, FILTER_VALIDATE_EMAIL) ? $correo : '',
				'celular'      => preg_replace('/\D+/', '', (string)$alumnos[0]['repre_celular']),
				'whatsapp'     => self::celularWhatsApp($alumnos[0]['repre_celular']),
				'alumnos'      => $hijos,
				/* «Pedro», «Pedro y Lucía», «Pedro, Lucía y Tomás» */
				'lista'        => count($hijos) > 1 ? implode(', ', array_slice($hijos, 0, -1)) . ' y ' . end($hijos) : $hijos[0],
			];
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

			/* Las sedes de la sesión: sedes_de_la_sesion() (ds_core/inc/seguridad.php). */
			$consulta .= " AND " . sql_sedes_de_la_sesion('alumno_sedeid');

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
				$origen     = match ($h['consent_origen']) {
								'FORMULARIO' => '<span class="badge text-bg-info">Formulario de inscripción</span>',
								'ENLACE'     => '<span class="badge text-bg-primary">Enlace de autorización</span>',
								default      => '<span class="badge text-bg-warning">Registrado por administrador</span>',
							};

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
