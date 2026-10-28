<?php
	namespace app\controllers;
	use app\models\mainModel;

	class cumpleaniosController extends mainModel {

		/**
		 * Lista alumnos activos cuyo cumpleaños coincide con el día/mes indicado.
		 * @param string $fecha Fecha en formato Y-m-d (por defecto hoy)
		 * @return \PDOStatement
		 */
		public function listarCumpleanios($fecha = null): \PDOStatement {
			if (empty($fecha)) {
				$fecha = date('Y-m-d');
			}

			$mes = (int) date('m', strtotime($fecha));
			$dia = (int) date('d', strtotime($fecha));

			$consulta = "SELECT alumno_id,
								alumno_identificacion,
								alumno_primernombre,
								alumno_segundonombre,
								alumno_apellidopaterno,
								alumno_apellidomaterno,
								alumno_fechanacimiento,
								alumno_imagen, alumno_sedeid,
								(SELECT sede_nombre FROM general_sede WHERE sede_id = alumno_sedeid) AS sede_nombre,
								TIMESTAMPDIFF(YEAR, alumno_fechanacimiento, :fecha_hoy) AS edad
						 FROM sujeto_alumno
						 WHERE DAY(alumno_fechanacimiento)   = :dia
						   AND MONTH(alumno_fechanacimiento) = :mes
						   AND alumno_estado = 'A'
						   AND " . sql_sedes_de_la_sesion('alumno_sedeid') . "
						 ORDER BY alumno_apellidopaterno, alumno_apellidomaterno, alumno_primernombre";

			$datos = $this->ejecutarConsulta($consulta, [
				':fecha_hoy' => $fecha,
				':dia'       => $dia,
				':mes'       => $mes
			]);

			return $datos;
		}

		/**
		 * Cuántos alumnos activos cumplen años cada día del año: ['MM-DD' => n].
		 *
		 * Alimenta el calendario de la vista, que marca los días con
		 * cumpleaños. El cumpleaños se repite cada año, así que basta una
		 * consulta agrupada por mes y día para pintar cualquier mes de
		 * cualquier año. Mismo criterio que listarCumpleanios(): alumnos
		 * activos, sin filtro de sede. Son a lo sumo 366 filas.
		 */
		public function cumpleaniosPorDia(): array {
			$filas = $this->ejecutarConsulta(
				"SELECT DATE_FORMAT(alumno_fechanacimiento, '%m-%d') AS dia, COUNT(*) AS n
				   FROM sujeto_alumno
				  WHERE alumno_estado = 'A' AND alumno_fechanacimiento IS NOT NULL
				    AND " . sql_sedes_de_la_sesion('alumno_sedeid') . "
				  GROUP BY dia"
			)->fetchAll();

			$mapa = [];
			foreach ($filas as $f) {
				$mapa[$f['dia']] = (int) $f['n'];
			}
			return $mapa;
		}

		/**
		 * Cuenta los alumnos activos que cumplen años hoy (para el badge del menu).
		 * @return int
		 */
		public function contarCumpleaniosHoy(): int {
			$consulta = "SELECT COUNT(*) AS total
						 FROM sujeto_alumno
						 WHERE DAY(alumno_fechanacimiento)   = :dia
						   AND MONTH(alumno_fechanacimiento) = :mes
						   AND alumno_estado = 'A'
						   AND " . sql_sedes_de_la_sesion('alumno_sedeid');

			$datos = $this->ejecutarConsulta($consulta, [
				':dia' => (int) date('d'),
				':mes' => (int) date('m')
			]);
			$fila = $datos->fetch();
			return (int) ($fila['total'] ?? 0);
		}

		/**
		 * Obtiene los datos de un alumno por su ID.
		 * @param int $alumno_id
		 * @return \PDOStatement
		 */
		public function infoAlumno($alumno_id): \PDOStatement {
			$consulta = "SELECT alumno_id,
								alumno_identificacion,
								alumno_primernombre,
								alumno_segundonombre,
								alumno_apellidopaterno,
								alumno_apellidomaterno,
								alumno_fechanacimiento,
								alumno_imagen,
								TIMESTAMPDIFF(YEAR, alumno_fechanacimiento, CURDATE()) AS edad
						 FROM sujeto_alumno
						 WHERE alumno_id = :alumno_id
						   AND " . sql_sedes_de_la_sesion('alumno_sedeid');   /* la tarjeta lleva la foto de un menor */

			return $this->ejecutarConsulta($consulta, [':alumno_id' => (int)$alumno_id]);
		}

		/*======================================================================
		  Publicar la felicitación en redes (migración 073)
		  ======================================================================
		  La tarjeta se publica en la página de Facebook y/o la cuenta de
		  Instagram configuradas en Core › Redes sociales (ds_core/inc/redes.php).

		  LO QUE EL SERVIDOR NO DEJA PASAR, diga lo que diga la pantalla:
		    · un alumno de otra sede (sedes_de_la_sesion);
		    · sin autorización de uso de imagen vigente (consentimiento LOPDP
		      de tipo IMAGEN, el último registrado): es un menor;
		    · publicar dos veces el mismo cumpleaños en la misma red;
		    · una red que no está activa en Core.
		  Cada intento queda en redes_publicacion, con quién y el resultado.
		  ====================================================================*/

		/** Autorización de uso de imagen vigente por alumno: [alumno_id => 'S'|'N'|null]. */
		public function autorizacionesImagen(array $ids): array {
			$ids = array_values(array_filter(array_map('intval', $ids)));
			if (!$ids) { return []; }
			$filas = $this->ejecutarConsulta(
				"SELECT a.alumno_id,
						(SELECT c.consent_otorgado FROM alumno_consentimiento c
						  WHERE c.consent_alumnoid = a.alumno_id AND c.consent_tipo = 'IMAGEN'
						  ORDER BY c.consent_fecha DESC, c.consent_id DESC LIMIT 1) AS imagen
				   FROM sujeto_alumno a WHERE a.alumno_id IN (" . implode(',', $ids) . ")")->fetchAll();
			return array_column($filas, 'imagen', 'alumno_id');
		}

		/** Lo ya publicado este año: [alumno_id => ['facebook' => 'dd/mm/aaaa · usuario', …]]. */
		public function publicacionesCumple(array $ids, int $anio): array {
			$ids = array_values(array_filter(array_map('intval', $ids)));
			if (!$ids) { return []; }
			try {
				$filas = $this->ejecutarConsulta(
					"SELECT pub_alumnoid, pub_red, pub_fecha, pub_usuario FROM redes_publicacion
					  WHERE pub_tipo = 'CUMPLE' AND pub_estado = 'OK' AND pub_anio = :anio
						AND pub_alumnoid IN (" . implode(',', $ids) . ")", [':anio' => $anio])->fetchAll();
			} catch (\Throwable $e) {
				return [];   /* sin la migración 073 */
			}
			$mapa = [];
			foreach ($filas as $f) {
				$mapa[(int)$f['pub_alumnoid']][$f['pub_red']] = date('d/m/Y H:i', strtotime($f['pub_fecha'])) . ' · ' . $f['pub_usuario'];
			}
			return $mapa;
		}

		private function registrarPublicacion(int $alumno, string $red, int $anio, array $r, string $mensaje): void {
			try {
				$this->ejecutarConsulta(
					"INSERT INTO redes_publicacion (pub_tipo, pub_red, pub_alumnoid, pub_anio, pub_estado, pub_externo_id, pub_mensaje, pub_error, pub_usuario, pub_fecha)
					 VALUES ('CUMPLE', :red, :alumno, :anio, :estado, :ext, :msg, :err, :usuario, NOW())",
					[':red' => $red, ':alumno' => $alumno, ':anio' => $anio, ':estado' => $r['ok'] ? 'OK' : 'ER',
					 ':ext' => $r['id'] ?: null, ':msg' => $mensaje, ':err' => $r['ok'] ? null : mb_substr($r['error'], 0, 500),
					 ':usuario' => mb_substr((string)($_SESSION['usuario'] ?? ''), 0, 50)]);
			} catch (\Throwable $e) {
				error_log('[DS redes] no se registró la publicación: ' . $e->getMessage());
			}
		}

		/** POST alumno_id, redes[] (facebook|instagram), mensaje. JSON con el resultado por red. */
		public function publicarCumple(): string {
			$responder = static fn(string $titulo, string $texto, string $icono, array $extra = []) =>
				json_encode(['tipo' => 'simple', 'titulo' => $titulo, 'texto' => $texto, 'icono' => $icono] + $extra, JSON_UNESCAPED_UNICODE);

			$id = (int)($_POST['alumno_id'] ?? 0);
			$datos = $this->ejecutarConsulta(
				"SELECT a.alumno_id, a.alumno_identificacion, a.alumno_primernombre, a.alumno_segundonombre,
						a.alumno_apellidopaterno, a.alumno_apellidomaterno, a.alumno_fechanacimiento, a.alumno_imagen,
						a.alumno_sedeid, a.alumno_estado, s.sede_nombre,
						TIMESTAMPDIFF(YEAR, a.alumno_fechanacimiento, CURDATE()) AS edad
				   FROM sujeto_alumno a LEFT JOIN general_sede s ON s.sede_id = a.alumno_sedeid
				  WHERE a.alumno_id = :id AND a.alumno_estado = 'A' AND " . sql_sedes_de_la_sesion('a.alumno_sedeid'),
				[':id' => $id])->fetch();
			if (!$datos) {
				return $responder('No encontrado', 'El alumno no existe, no está activo o es de otra sede.', 'error');
			}
			if (($this->autorizacionesImagen([$id])[$id] ?? null) !== 'S') {
				return $responder('Sin autorización de imagen',
					'El representante no ha autorizado el uso de imagen de este alumno en redes sociales (LOPDP). No se puede publicar.', 'warning');
			}

			$cfg = redes_config();
			$activas = array_keys(array_filter(['facebook' => $cfg['fb_activo'] === 'S', 'instagram' => $cfg['ig_activo'] === 'S']));
			$pedidas = array_values(array_intersect(array_unique(array_map('strval', (array)($_POST['redes'] ?? []))), $activas));
			if (!$pedidas) {
				return $responder('Sin redes', $activas ? 'Elija en qué red publicar.' : 'No hay redes activas. Se configuran en Core › Redes sociales.', 'warning');
			}

			$anio = (int)date('Y');
			$ya = $this->publicacionesCumple([$id], $anio)[$id] ?? [];
			$mensaje = trim(strip_tags((string)($_POST['mensaje'] ?? '')));
			$mensaje = mb_substr($mensaje !== '' ? $mensaje : redes_mensaje_cumple($datos, $cfg), 0, 2200);

			/* La tarjeta, en un archivo temporal de nombre imprevisible:
			   Facebook la recibe subida; Instagram la descarga de su dirección. */
			$p = $this->plantillaTarjeta();
			if ($p === null) {
				return $responder('Sin plantilla', 'No hay plantilla de tarjeta activa (Core › Tarjeta de cumpleaños).', 'error');
			}
			require_once dirname(__DIR__) . '/lib/tarjeta_cumple.php';
			try {
				$imagen = tarjeta_cumple_imagen($datos, $p, true);   /* nombre corto: es un menor en una red pública */
			} catch (\RuntimeException $e) {
				return $responder('Sin tarjeta', $e->getMessage(), 'error');
			}
			$nombre = 'cumple_' . bin2hex(random_bytes(16)) . '.jpg';
			$archivo = dirname(__DIR__) . '/views/dist/img/temp/' . $nombre;
			imagejpeg($imagen, $archivo, 92);
			unset($imagen);

			$resultados = [];
			try {
				foreach ($pedidas as $red) {
					if (isset($ya[$red])) {
						$resultados[$red] = ['ok' => false, 'error' => 'Ya se publicó este año (' . $ya[$red] . ').', 'repetida' => true];
						continue;
					}
					$r = $red === 'facebook'
						? redes_publicar_facebook($archivo, $mensaje, $cfg)
						: redes_publicar_instagram(DS_FUTBOL_URL . 'app/views/dist/img/temp/' . $nombre, $mensaje, $cfg);
					$this->registrarPublicacion($id, $red, $anio, $r, $mensaje);
					$resultados[$red] = $r;
				}
			} finally {
				@unlink($archivo);
			}

			$ok = array_keys(array_filter($resultados, static fn($r) => $r['ok']));
			$etiqueta = ['facebook' => 'Facebook', 'instagram' => 'Instagram'];
			$lineas = [];
			foreach ($resultados as $red => $r) {
				$lineas[] = $etiqueta[$red] . ': ' . ($r['ok'] ? 'publicado' : $r['error']);
			}
			return $responder(
				$ok ? (count($ok) === count($resultados) ? 'Felicitación publicada' : 'Publicada en parte') : 'No se publicó',
				implode("\n", $lineas),
				$ok ? (count($ok) === count($resultados) ? 'success' : 'warning') : 'error',
				['publicadas' => $ok, 'resultados' => array_map(static fn($r) => ['ok' => $r['ok'], 'error' => $r['error'] ?? ''], $resultados)]);
		}

		/**
		 * La plantilla de tarjeta que esta activa, o null si no hay ninguna.
		 *
		 * Se administra en Core. Aqui solo se lee: este modulo dibuja la
		 * tarjeta, no decide como se ve.
		 *
		 * Devuelve null en vez de inventar valores por defecto. Una tarjeta
		 * dibujada con medidas supuestas sale torcida y parece un fallo del
		 * generador; decir «no hay plantilla activa» dice donde mirar.
		 */
		public function plantillaTarjeta(): ?array
		{
			$r = $this->ejecutarConsulta(
				"SELECT * FROM cumple_plantilla WHERE plantilla_estado = 'A' LIMIT 1", []);

			$f = $r->fetch(\PDO::FETCH_ASSOC);
			return $f ?: null;
		}
	}
