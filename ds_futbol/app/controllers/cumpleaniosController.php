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
								alumno_imagen,
								TIMESTAMPDIFF(YEAR, alumno_fechanacimiento, :fecha_hoy) AS edad
						 FROM sujeto_alumno
						 WHERE DAY(alumno_fechanacimiento)   = :dia
						   AND MONTH(alumno_fechanacimiento) = :mes
						   AND alumno_estado = 'A'
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
						   AND alumno_estado = 'A'";

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
						 WHERE alumno_id = :alumno_id";

			return $this->ejecutarConsulta($consulta, [':alumno_id' => (int)$alumno_id]);
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
