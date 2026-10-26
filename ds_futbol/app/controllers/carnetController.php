<?php
/**
 * ============================================================
 * CONTROLADOR DE CARNETS - Sistema de Colores por Mes
 * ============================================================
 * Funcionalidades:
 * - Asignar colores únicos a cada mes
 * - Validar que no haya carnets emitidos antes de modificar
 * - Prevenir colores duplicados entre meses
 * ============================================================
 */

/**
 * Listar colores del CATÁLOGO disponibles para asignar a un mes
 * @param int $color_id_actual Color actualmente asignado al mes
 * @param int $mes_actual Mes que se está configurando
 * @return string HTML con opciones del select
 */
	namespace app\controllers;
	use app\models\mainModel;
	use Exception;

	class carnetController extends mainModel{

		/** Conexión para leer el diseño del carnet (carnet_diseno_leer). */
		public function conexionCarnet(): \PDO {
			return $this->conectar();
		}

		/** El diseño del carnet guardado (ds_core/inc/carnet_diseno.php). */
		public function disenoCarnet(): array {
			return carnet_diseno_leer($this->conectar());
		}
        public function informacionSede($sedeid){
            $consulta_datos="SELECT *, escuela_nombre, escuela_verticalfondo, escuela_verticalprincipal, escuela_verticalcolor
								 FROM general_sede
								 INNER JOIN general_escuela on escuela_id = sede_escuelaid
								 WHERE sede_id  = $sedeid";
            $datos = $this->ejecutarConsulta($consulta_datos);
            return $datos;
        }

		/**
		 * Alumnos con pago de pensión del mes (o beca completa): los que
		 * llevan carnet este mes, con si ya está impreso.
		 *
		 * Devuelve filas, no HTML: las pinta carnetList-view.php como fichas.
		 * Acotado a las sedes del usuario (condicionSede); con $sede > 0, a
		 * esa sede. Antes listaba los alumnos de todas las sedes a cualquiera.
		 *
		 * Sólo los alumnos CON horario asignado: el carnet lleva el horario y
		 * la impresión sólo emite los que lo tienen. Los que no, los avisa la
		 * vista aparte (verificarAlumnosSinHorarioAsignado). Antes, uno solo
		 * sin horario dejaba la lista entera vacía hasta corregirlo.
		 */
		public function alumnosCarnetMes(int $sede = 0): array {
			$parametros = [];
			$sedeSql = $this->condicionSede('A.alumno_sedeid', $sede, $parametros);

			$consulta = "SELECT A.alumno_id, A.alumno_identificacion, A.alumno_sedeid, S.sede_nombre,
								CONCAT_WS(' ', A.alumno_primernombre, A.alumno_segundonombre) AS nombres,
								CONCAT_WS(' ', A.alumno_apellidopaterno, A.alumno_apellidomaterno) AS apellidos,
								EP.FechaUltPension AS ultima_pension,
								EP.beca,
								(SELECT MAX(ac.carnet_fecha_impresion)
								   FROM alumno_carnet ac
								  WHERE ac.carnet_alumnoid = A.alumno_id
									AND ac.carnet_mes = MONTH(CURDATE())
									AND ac.carnet_anio = YEAR(CURDATE())) AS fecha_impresion
						   FROM sujeto_alumno A
						   INNER JOIN (
								SELECT pago_alumnoid, MAX(FechaPension) AS FechaUltPension, MAX(beca) AS beca
								  FROM (
										SELECT pago_alumnoid, pago_fecha AS FechaPension, 0 AS beca
										  FROM alumno_pago
										 WHERE pago_fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
										   AND pago_estado NOT IN ('E', 'J')
										   AND pago_rubroid = 'RPE'
										UNION ALL
										SELECT descuento_alumnoid, NULL, 1
										  FROM alumno_pago_descuento
										 WHERE descuento_rubroid = 'DBC'
										   AND descuento_valor = 0
										   AND descuento_estado = 'S'
								  ) P
								 GROUP BY pago_alumnoid
						   ) EP ON EP.pago_alumnoid = A.alumno_id
						   LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
						  WHERE A.alumno_estado = 'A' AND $sedeSql
							AND EXISTS (SELECT 1 FROM asistencia_asignahorario AH
										INNER JOIN asistencia_horario H ON H.horario_id = AH.asignahorario_horarioid
										WHERE AH.asignahorario_alumnoid = A.alumno_id)
						  ORDER BY (fecha_impresion IS NOT NULL), A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_primernombre";

			return $this->ejecutarConsulta($consulta, $parametros)->fetchAll();
		}

		/** Sedes del usuario para el filtro (todas si es administración). */
		public function opcionesSedeCarnet($sedeid): string {
			$permitidas = $this->sedesDeLaSesion();
			$option = '';
			foreach($this->ejecutarConsulta("SELECT sede_id, sede_nombre FROM general_sede ORDER BY sede_nombre")->fetchAll() as $s) {
				if($permitidas !== null && !in_array((int)$s['sede_id'], $permitidas, true)) { continue; }
				$option .= '<option value="' . (int)$s['sede_id'] . '"' . ((int)$sedeid === (int)$s['sede_id'] ? ' selected' : '') . '>'
					. htmlspecialchars(html_entity_decode((string)$s['sede_nombre'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</option>';
			}
			return $option;
		}

		/** Los alumnos (ids) que el usuario puede tocar: los de sus sedes. */
		private function alumnosEnSedesDeLaSesion(array $ids): array {
			$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
			if(!$ids) { return []; }
			$parametros = [];
			$sedeSql = $this->condicionSede('alumno_sedeid', 0, $parametros);
			return array_map('intval', $this->ejecutarConsulta(
				"SELECT alumno_id FROM sujeto_alumno WHERE alumno_id IN (" . implode(',', $ids) . ") AND $sedeSql", $parametros
			)->fetchAll(\PDO::FETCH_COLUMN));
		}

		/**
		 * Verificar alumnos candidatos a carnet que no tienen horario de entrenamiento asignado
		 * @return array
		 */
		public function verificarAlumnosSinHorarioAsignado($sede = 0) {
			$parametros = [];
			$sedeSql = $this->condicionSede('a.alumno_sedeid', $sede, $parametros);
			$consulta = "SELECT
						a.alumno_id,
						a.alumno_identificacion,
						CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
							a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) AS alumno_nombre
						FROM sujeto_alumno a
						INNER JOIN (
							SELECT pago_alumnoid
							FROM alumno_pago
							WHERE pago_fecha >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
								AND pago_estado NOT IN ('E', 'J')
								AND pago_rubroid = 'RPE'
							GROUP BY pago_alumnoid
							UNION
							SELECT descuento_alumnoid AS pago_alumnoid
							FROM alumno_pago_descuento
							WHERE descuento_rubroid = 'DBC'
								AND descuento_valor = 0
								AND descuento_estado = 'S'
						) EstadoPagos ON EstadoPagos.pago_alumnoid = a.alumno_id
						WHERE a.alumno_estado = 'A' AND $sedeSql
							AND NOT EXISTS(
								SELECT 1
								FROM asistencia_asignahorario ah
								INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
								WHERE ah.asignahorario_alumnoid = a.alumno_id
							)
						ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno,
							a.alumno_primernombre, a.alumno_segundonombre";

			$datos = $this->ejecutarConsulta($consulta, $parametros);
			if($datos && $datos->rowCount() > 0) {
				return $datos->fetchAll();
			}

			return [];
		}

		public function infoAlumnoCarnet($alumnoid){
            $consulta_datos="SELECT alumno_identificacion,
									CONCAT(alumno_primernombre, ' ', alumno_segundonombre) Nombres,
									CONCAT(alumno_apellidopaterno, ' ',  alumno_apellidomaterno) Apellidos,
									alumno_fechanacimiento, horario_nombre, alumno_imagen, alumno_sedeid
								FROM sujeto_alumno
								INNER JOIN asistencia_asignahorario on asignahorario_alumnoid = alumno_id
								INNER JOIN asistencia_horario on asignahorario_horarioid = horario_id
								WHERE alumno_id = $alumnoid";
            $datos = $this->ejecutarConsulta($consulta_datos);
			if($datos && $datos->rowCount() <=0) {
				$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"Alumno no tiene un horario asignado, asigne un horario para generar el carnet.",
							"icono"=>"error"
				];
				return json_encode($alerta);
			}else{
				return $datos;
			}
        }

        public function EstadoAlumno($alumnoid){
			$consulta_datos="SELECT FechaUltPension, Estado,
								CASE
										WHEN FechaUltPension >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
										THEN 'Al dia'
										ELSE 'Pendiente'
										END Condicion
								FROM(SELECT max(pago_fecha) FechaUltPension, max(pago_estado)Estado
										from(
												SELECT pago_fecha, pago_estado
														FROM alumno_pago
														WHERE pago_alumnoid = $alumnoid
															AND pago_estado NOT IN ('J','E')
														GROUP BY pago_estado, pago_fecha) as subquery) AS Total
												UNION
												SELECT descuento_alumnoid, DATE_FORMAT(CURDATE(), '%Y-%m-01') FechaPago, 'Al dìa' as Estado
                                from alumno_pago_descuento
                                where descuento_rubroid = 'DBC'
                                                and descuento_valor = 0
                                                and descuento_estado = 'S'
												and descuento_alumnoid = $alumnoid";
			$datos = $this->ejecutarConsulta($consulta_datos);
			return $datos;
		}

		/**
		 * Listar colores del CATÁLOGO disponibles para asignar a un mes
		 * @param int $color_id_actual Color actualmente asignado al mes
		 * @param int $mes_actual Mes que se está configurando (para excluirlo de validación)
		 * @return string HTML con opciones del select
		 */
		/**
		 * Listar colores del catálogo disponibles
		 * ✅ CORREGIDO: Usa mcolor_catcolorid en lugar de mcolor_id
		 */
		public function listarOptionColor($color_id_actual = 0, $mes_actual = 0) {
			$option = "";

			$consulta = "SELECT
							cc.catcolor_id,
							cc.catcolor_nombre,
							cc.catcolor_hex,
							(SELECT COUNT(*)
							FROM carnet_mes_color cmc
							WHERE cmc.mcolor_catcolorid = cc.catcolor_id
							AND cmc.mcolor_activo = 1
							AND cmc.mcolor_mes != :mes_actual
							) as veces_asignado
						FROM carnet_catcolor cc
						WHERE cc.catcolor_activo = 1
						ORDER BY cc.catcolor_nombre ASC";

			$parametros = [':mes_actual' => $mes_actual];
			$datos = $this->ejecutarConsulta($consulta, $parametros);
			$datos = $datos->fetchAll();

			$option = '<option value="0">-- Seleccione un color --</option>';

			foreach($datos as $row) {
				// Disponible si no está asignado O es el actual
				$esta_disponible = ($row['veces_asignado'] == 0 || $color_id_actual == $row['catcolor_id']);

				$selected = ($color_id_actual == $row['catcolor_id']) ? 'selected="selected"' : '';
				$disabled = (!$esta_disponible) ? 'disabled' : '';
				$texto_ocupado = (!$esta_disponible) ? ' (Ya asignado)' : '';

				// ✅ CORREGIDO: Usar catcolor_id, catcolor_nombre, catcolor_hex
				$option .= '<option value="' . $row['catcolor_id'] . '"
									data-color="' . $row['catcolor_hex'] . '"
									' . $selected . '
									' . $disabled . '>
								' . $row['catcolor_nombre'] . $texto_ocupado . '
							</option>';
			}
			return $option;
		}


		/**
		 * Buscar color asignado a un mes específico
		 * @param int $mes Número del mes (1-12)
		 * @return object PDOStatement
		 */
		/**
		 * El color de un mes EN UN ANIO.
		 *
		 * El anio no es opcional por comodidad: es lo que hace correcta la
		 * reimpresion. Desde la migracion 062 la asignacion es por anio, de
		 * modo que reimprimir un carnet de abril de 2026 en 2027 devuelve el
		 * color con el que se imprimio, no el que abril tenga ahora.
		 *
		 * Sin anio se usa el actual, que es lo que quiere quien imprime hoy.
		 */
		public function BuscarColorPorMes($mes, $anio = null) {
			$anio = (int)($anio ?: date('Y'));

			$consulta = "SELECT
							cmc.mcolor_id,
							cmc.mcolor_mes,
							cmc.mcolor_anio,
							cmc.mcolor_catcolorid as color_id,
							cmc.mcolor_bloqueado as color_bloqueado,
							cc.catcolor_nombre as color_nombre,
							cc.catcolor_hex as color_hex,
							(SELECT COUNT(*)
							FROM alumno_carnet ac
							WHERE ac.carnet_mes = cmc.mcolor_mes
							AND ac.carnet_anio = cmc.mcolor_anio) as total_carnets
						FROM carnet_mes_color cmc
						INNER JOIN carnet_catcolor cc ON cmc.mcolor_catcolorid = cc.catcolor_id
						WHERE cmc.mcolor_mes = :mes
						AND cmc.mcolor_anio = :anio
						AND cmc.mcolor_activo = 1";

			$parametros = [':mes' => $mes, ':anio' => $anio];
			$datos = $this->ejecutarConsulta($consulta, $parametros);

			return $datos;
		}
		/**
		 * Obtener código hexadecimal de un color del catálogo
		 * @param int $color_id ID del color en catalogo_colores
		 * @return string Código hexadecimal del color
		 */
		public function obtenerColorHex($color_id) {
			if($color_id == 0 || empty($color_id)) {
				return '#FFFFFF';
			}

			$sql = "SELECT catcolor_hex
					FROM carnet_catcolor
					WHERE catcolor_id = :id
					AND catcolor_activo = 1";

			$parametros = [':id' => $color_id];
			$datos = $this->ejecutarConsulta($sql, $parametros);

			if($datos && $datos->rowCount() == 1) {
				$resultado = $datos->fetch();
				return $resultado['catcolor_hex'];
			}

			return '#CCCCCC';
		}


		/**
		 * Verificar si un mes tiene carnets emitidos (está bloqueado)
		 * @param int $mes Número del mes
		 * @return bool True si está bloqueado
		 */
		/**
		 * Un mes esta bloqueado EN UN ANIO.
		 *
		 * Antes se contaban los carnets del mes sin mirar el anio, asi que
		 * abril quedaba bloqueado para siempre en cuanto abril de 2026 tuvo
		 * su primer carnet. Desde la migracion 062 la asignacion es por
		 * anio y el bloqueo tambien: al cambiar de anio los doce meses
		 * quedan libres sin que nadie tenga que desbloquear nada.
		 */
		public function mesBloqueado($mes, $anio = null) {
			$anio = (int)($anio ?: date('Y'));

			$sql = "SELECT
						cmc.mcolor_bloqueado,
						(SELECT COUNT(*)
						FROM alumno_carnet ac
						WHERE ac.carnet_mes = :mes
						AND ac.carnet_anio = :anio) as total_carnets
					FROM carnet_mes_color cmc
					WHERE cmc.mcolor_mes = :mes
					AND cmc.mcolor_anio = :anio
					AND cmc.mcolor_activo = 1";

			$parametros = [':mes' => $mes, ':anio' => $anio];
			$datos = $this->ejecutarConsulta($sql, $parametros);

			if($datos && $datos->rowCount() == 1) {
				$resultado = $datos->fetch();
				return ($resultado['mcolor_bloqueado'] == 1 || $resultado['total_carnets'] > 0);
			}

			return false;
		}

		/**
		 * Verificar si un color ya está asignado a otro mes
		 * @param int $color_id ID del color
		 * @param int $mes_excluir Mes a excluir de la validación
		 * @return bool True si ya está asignado
		 */
		public function colorYaAsignado($color_id, $mes_excluir = 0, $anio = null) {
			/* DENTRO DEL MISMO ANIO. Un color puede —y debe poder— repetirse
			   de un anio a otro: la paleta se reutiliza, no se agota. Lo que
			   no puede es estar en dos meses del MISMO anio, porque entonces
			   dos meses serian indistinguibles. */
			$anio = (int)($anio ?: date('Y'));

			$sql = "SELECT COUNT(*) as total
					FROM carnet_mes_color
					WHERE mcolor_catcolorid = :color_id
					AND mcolor_mes != :mes_excluir
					AND mcolor_anio = :anio
					AND mcolor_activo = 1";

			$parametros = [
				':color_id' => $color_id,
				':mes_excluir' => $mes_excluir,
				':anio' => $anio
			];
			$datos = $this->ejecutarConsulta($sql, $parametros);

			if($datos && $datos->rowCount() == 1) {
				$resultado = $datos->fetch();
				return ($resultado['total'] > 0);
			}

			return false;
		}
		/**
		 * Asegurar tabla de configuracion de carnets.
		 */
		private function asegurarTablaConfiguracionCarnet() {
			$sql = "CREATE TABLE IF NOT EXISTS carnet_configuracion (
				config_id INT AUTO_INCREMENT PRIMARY KEY,
				config_clave VARCHAR(80) NOT NULL UNIQUE,
				config_valor VARCHAR(255) NOT NULL,
				config_descripcion VARCHAR(255) NULL,
				config_fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
			) ENGINE=InnoDB DEFAULT CHARSET=utf8";

			return $this->ejecutarConsulta($sql);
		}

		/**
		 * Obtener valor de configuracion de carnets.
		 */
		private function obtenerValorConfiguracionCarnet($clave, $valor_defecto = '') {
			$this->asegurarTablaConfiguracionCarnet();

			$sql = "SELECT config_valor FROM carnet_configuracion WHERE config_clave = :clave LIMIT 1";
			$datos = $this->ejecutarConsulta($sql, [':clave' => $clave]);

			if($datos && $datos->rowCount() == 1) {
				$fila = $datos->fetch();
				return $fila['config_valor'];
			}

			$this->guardarValorConfiguracionCarnet($clave, $valor_defecto, 'Configuracion automatica de carnets');
			return $valor_defecto;
		}

		/**
		 * Guardar valor de configuracion de carnets.
		 */
		private function guardarValorConfiguracionCarnet($clave, $valor, $descripcion = '') {
			$this->asegurarTablaConfiguracionCarnet();

			$sql = "INSERT INTO carnet_configuracion (config_clave, config_valor, config_descripcion)
					VALUES (:clave, :valor, :descripcion)
					ON DUPLICATE KEY UPDATE
						config_valor = VALUES(config_valor),
						config_descripcion = VALUES(config_descripcion)";

			return $this->ejecutarConsulta($sql, [
				':clave' => $clave,
				':valor' => $valor,
				':descripcion' => $descripcion
			]);
		}

		/**
		 * Indica si se debe generar cobro por reimpresion de carnet.
		 */
		public function cobrarReimpresionCarnet() {
			return $this->obtenerValorConfiguracionCarnet('cobrar_reimpresion', '1') === '1';
		}

		/**
		 * Valor configurado para el rubro de reimpresion de carnet.
		 */
		public function valorReimpresionCarnet() {
			$valor = str_replace(',', '.', $this->obtenerValorConfiguracionCarnet('valor_reimpresion', '3.00'));

			if(!is_numeric($valor) || (float)$valor <= 0) {
				return 3.00;
			}

			return round((float)$valor, 2);
		}


		/**
		 * Actualizar asignación de colores por mes
		 * CON VALIDACIONES de bloqueo y duplicados
		 * @return string JSON con resultado
		 */

		/*==============================================================
		| CATALOGO DE COLORES: alta, cambio de tonalidad y retiro
		|
		| POR QUE UN COLOR CON CARNETS NO SE PUEDE TOCAR
		|
		| El color del carnet ES el dato: se mira un carnet y se sabe de
		| que mes es. alumno_carnet guarda mes y anio, pero NO el color con
		| el que se imprimio —el color se deduce de la asignacion vigente—.
		|
		| Por eso cambiarle la tonalidad a un color que ya circula no
		| «actualiza» nada: deja en la calle carnets de un tono que el
		| sistema ya no reconoce como suyo, y el mes que representan pasa a
		| leerse mal. No es una restriccion de la pantalla, es una
		| consecuencia del modelo.
		|
		| SE DERIVA, NO SE CONFIA EN LA BANDERA
		|
		| carnet_mes_color tiene mcolor_bloqueado, y hoy esta desincronizado:
		| los meses 4 a 8 acumulan 529 carnets con la bandera en 0, y los
		| meses 1 y 2 la tienen en 1 sin un solo carnet. Fiarse de ella
		| dejaria cambiar justo los colores que ya circulan.
		|
		| Aqui se cuenta contra alumno_carnet, igual que hace
		| coreController::coloresCarnetPorMes(). La bandera se respeta como
		| bloqueo ADICIONAL —alguien puede querer congelar un mes antes de
		| imprimir—, nunca como unica fuente.
		|==============================================================*/

		/**
		 * El catalogo con lo que hace falta para decidir sobre cada color:
		 * a que mes esta asignado, cuantos carnets lo respaldan y si se
		 * puede editar.
		 */
		public function catalogoColoresAdmin($anio = null): array
		{
			/* El estado de un color depende del ANIO que se esté mirando:
			   un tono que en 2026 lleva 104 carnets impresos está libre en
			   2027 mientras nadie imprima. */
			$anio = (int)($anio ?: date('Y'));

			$sql = "SELECT c.catcolor_id, c.catcolor_nombre, c.catcolor_hex,
			               m.mcolor_mes,
			               m.mcolor_bloqueado,
			               (SELECT COUNT(*) FROM alumno_carnet a
			                 WHERE a.carnet_mes = m.mcolor_mes
			                   AND a.carnet_anio = m.mcolor_anio) AS carnets
			          FROM carnet_catcolor c
			          LEFT JOIN carnet_mes_color m
			                 ON m.mcolor_catcolorid = c.catcolor_id
			                AND m.mcolor_activo = 1
			                AND m.mcolor_anio = :anio
			         WHERE c.catcolor_activo = 1
			         ORDER BY c.catcolor_nombre";

			$filas = $this->ejecutarConsulta($sql, [':anio' => $anio])->fetchAll(\PDO::FETCH_ASSOC);

			$salida = [];
			foreach ($filas as $f) {
				$mes      = $f['mcolor_mes'] !== null ? (int)$f['mcolor_mes'] : 0;
				$carnets  = (int)($f['carnets'] ?? 0);
				$congelado = (int)($f['mcolor_bloqueado'] ?? 0) === 1;

				$salida[] = [
					'id'        => (int)$f['catcolor_id'],
					'nombre'    => (string)$f['catcolor_nombre'],
					'hex'       => strtoupper((string)$f['catcolor_hex']),
					'mes'       => $mes,
					'mes_nombre'=> $mes > 0 ? $this->nombreMes($mes) : '',
					'carnets'   => $carnets,
					/* Editable: nadie lo ha impreso todavia y su mes no esta
					   congelado a mano. */
					'editable'  => $carnets === 0 && !$congelado,
					/* Retirable: ademas, no esta asignado a ningun mes. */
					'retirable' => $carnets === 0 && !$congelado && $mes === 0,
					'motivo'    => $carnets > 0
						? $carnets . ' carnet(s) impresos en ' . $this->nombreMes($mes)
						: ($congelado ? 'El mes está congelado' : ''),
				];
			}

			return $salida;
		}

		/**
		 * Un color se identifica por su TONO, no por su nombre.
		 *
		 * Dos colores con el mismo hexadecimal son indistinguibles en un
		 * carnet, y con eso se pierde justo lo que el sistema pretende: que
		 * mirando el carnet se sepa el mes. Se rechaza el duplicado.
		 */
		private function hexRepetido(string $hex, int $excluirId = 0): bool
		{
			$sql = "SELECT COUNT(1) FROM carnet_catcolor
			         WHERE catcolor_activo = 1
			           AND UPPER(catcolor_hex) = :h
			           AND catcolor_id <> :id";

			return (int)$this->ejecutarConsulta($sql,
				[':h' => strtoupper($hex), ':id' => $excluirId])->fetchColumn() > 0;
		}

		private function nombreRepetido(string $nombre, int $excluirId = 0): bool
		{
			$sql = "SELECT COUNT(1) FROM carnet_catcolor
			         WHERE catcolor_activo = 1
			           AND LOWER(catcolor_nombre) = :n
			           AND catcolor_id <> :id";

			return (int)$this->ejecutarConsulta($sql,
				[':n' => mb_strtolower($nombre, 'UTF-8'), ':id' => $excluirId])->fetchColumn() > 0;
		}

		/** Nombre y tono validados, o el motivo del rechazo. */
		private function validarColor(string $nombre, string $hex, int $excluirId = 0): array
		{
			$nombre = trim($nombre);

			if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 50) {
				return ['error' => 'El nombre es obligatorio y no puede pasar de 50 caracteres.'];
			}

			/* Estricto y no «lo que se parezca»: el hexadecimal acaba dentro
			   de un atributo de estilo y en la impresion del carnet. */
			if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
				return ['error' => 'El tono debe ser un hexadecimal de seis dígitos, como #1B4F72.'];
			}

			if ($this->nombreRepetido($nombre, $excluirId)) {
				return ['error' => 'Ya existe un color con ese nombre.'];
			}

			if ($this->hexRepetido($hex, $excluirId)) {
				return ['error' => 'Ya existe un color con ese mismo tono. '
				                 . 'Dos colores iguales harían indistinguibles sus meses.'];
			}

			return ['nombre' => $nombre, 'hex' => strtoupper($hex)];
		}

		/** Alta de un color en el catálogo. */
		/*======================================================================
		  Diseño del carnet (Core › Carnets › Diseño del carnet)
		  ======================================================================
		  Superadministrador (lo comprueba carnetAjax.php). Los ajustes se
		  acotan con carnet_diseno_normalizar() —un solo sitio para las
		  reglas— y se guardan uno por clave en carnet_configuracion.
		  ====================================================================*/

		public function guardarDisenoCarnet(): string {
			$entrada = [];
			foreach (array_keys(carnet_diseno_defecto()) as $clave) {
				/* Los interruptores sin marcar no llegan: valen 0. */
				$entrada[$clave] = $_POST['diseno'][$clave] ?? (in_array($clave, ['franja_ancho', 'franja_opacidad', 'logo_tamano', 'titulo'], true) ? null : 0);
			}
			$entrada = array_filter($entrada, static fn($v) => $v !== null);
			foreach (carnet_diseno_normalizar($entrada) as $clave => $valor) {
				$this->guardarValorConfiguracionCarnet('diseno_' . $clave, (string)$valor, 'Diseño del carnet');
			}
			return $this->alertaCarnet('Diseño guardado', 'Los próximos carnets se imprimirán con este diseño.', 'success', 'recargar');
		}

		public function restaurarDisenoCarnet(): string {
			$this->asegurarTablaConfiguracionCarnet();
			$this->ejecutarConsulta("DELETE FROM carnet_configuracion WHERE config_clave LIKE 'diseno\\_%'");
			return $this->alertaCarnet('Diseño original', 'El carnet vuelve a su diseño de siempre.', 'success', 'recargar');
		}

		/** Imágenes que se pueden cambiar: campo de general_escuela y archivo original. */
		private function imagenesCarnet(): array {
			return [
				'fondo' => ['campo' => 'escuela_verticalfondo',     'original' => 'vertical_fondo.jpg',     'nombre' => 'La imagen de la franja'],
				'linea' => ['campo' => 'escuela_verticalprincipal', 'original' => 'vertical_principal.jpg', 'nombre' => 'La línea decorativa'],
			];
		}

		/**
		 * Cambia la imagen de la franja o de la línea decorativa.
		 *
		 * Se vuelve a codificar SIEMPRE como JPEG con GD: lo que se guarda es
		 * una imagen limpia (nada de lo que traiga el archivo subido llega
		 * al disco) y FPDF la dibuja sin depender de zlib —un PNG con
		 * transparencia sin zlib tumbaba los PDF en producción—. La
		 * transparencia se aplana sobre blanco. El archivo anterior no se
		 * borra: el cambio se puede deshacer con «Restaurar».
		 */
		public function subirImagenCarnet(): string {
			$tipos = $this->imagenesCarnet();
			$tipo = (string)($_POST['tipo'] ?? '');
			if (!isset($tipos[$tipo])) {
				return $this->alertaCarnet('Imagen no válida', 'Indique qué imagen del carnet quiere cambiar.', 'warning');
			}
			$archivo = $_FILES['imagen'] ?? null;
			if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
				return $this->alertaCarnet('Sin imagen', 'Elija una imagen JPG, PNG o WebP.', 'warning');
			}
			if ($archivo['size'] > 4 * 1024 * 1024) {
				return $this->alertaCarnet('Imagen muy pesada', 'La imagen no puede pasar de 4 MB.', 'warning');
			}
			$info = @getimagesize($archivo['tmp_name']);
			$crear = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'];
			if (!$info || !isset($crear[$info[2]]) || !function_exists($crear[$info[2]])) {
				return $this->alertaCarnet('Formato no admitido', 'La imagen debe ser JPG, PNG o WebP.', 'warning');
			}
			if ($info[0] < 20 || $info[1] < 100 || $info[0] > 4000 || $info[1] > 4000) {
				return $this->alertaCarnet('Medidas no válidas', 'La imagen es vertical: al menos 100 px de alto, y no más de 4000 px por lado.', 'warning');
			}
			$origen = @($crear[$info[2]])($archivo['tmp_name']);
			if (!$origen) {
				return $this->alertaCarnet('Imagen dañada', 'No se pudo leer la imagen.', 'warning');
			}
			$lienzo = imagecreatetruecolor($info[0], $info[1]);
			imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
			imagecopy($lienzo, $origen, 0, 0, 0, 0, $info[0], $info[1]);

			require_once dirname(__DIR__) . '/lib/carnet_render.php';
			$nombre = 'carnet_' . $tipo . '_' . date('Ymd_His') . '.jpg';
			$ok = imagejpeg($lienzo, carnet_dir_imagenes() . $nombre, 90);
			unset($origen, $lienzo);
			if (!$ok) {
				return $this->alertaCarnet('No se guardó', 'No se pudo escribir la imagen en el servidor.', 'error');
			}
			$this->ejecutarConsulta("UPDATE general_escuela SET " . $tipos[$tipo]['campo'] . " = :archivo
				WHERE escuela_id IN (SELECT sede_escuelaid FROM general_sede)", [':archivo' => $nombre]);
			return $this->alertaCarnet('Imagen cambiada', $tipos[$tipo]['nombre'] . ' ya está en el carnet.', 'success', 'recargar');
		}

		public function restaurarImagenCarnet(): string {
			$tipos = $this->imagenesCarnet();
			$tipo = (string)($_POST['tipo'] ?? '');
			if (!isset($tipos[$tipo])) {
				return $this->alertaCarnet('Imagen no válida', 'Indique qué imagen del carnet quiere restaurar.', 'warning');
			}
			$this->ejecutarConsulta("UPDATE general_escuela SET " . $tipos[$tipo]['campo'] . " = :archivo
				WHERE escuela_id IN (SELECT sede_escuelaid FROM general_sede)", [':archivo' => $tipos[$tipo]['original']]);
			return $this->alertaCarnet('Imagen original', $tipos[$tipo]['nombre'] . ' vuelve a ser la de siempre.', 'success', 'recargar');
		}

		/**
		 * Un carnet de muestra en PDF, con el diseño guardado y el renderizador
		 * real (el mismo de los lotes): lo que se ve es lo que se imprime. Los
		 * datos son de ejemplo, no de un alumno.
		 */
		public function muestraCarnetPdf(): void {
			require_once dirname(__DIR__) . '/lib/alphapdf.php';
			require_once dirname(__DIR__) . '/lib/barcode.php';
			require_once dirname(__DIR__) . '/lib/carnet_render.php';

			$sede = $this->informacionSede((int)($_GET['sede'] ?? 1));
			$sede = ($sede && $sede->rowCount() === 1) ? $sede->fetch() : $this->informacionSede(1)->fetch();
			$mes = (int)date('n');
			$color = $this->BuscarColorPorMes($mes)->fetch();
			[$r, $g, $b] = sscanf($color['color_hex'] ?? '#CCCCCC', '#%02x%02x%02x');

			/* .jpeg: FPDF deduce el tipo por la extensión (tempnam daría .tmp). */
			$qr = dirname(__DIR__) . '/views/dist/img/temp/qr_muestra_' . bin2hex(random_bytes(6)) . '.jpeg';
			$img = (new \barcode_generator())->render_image('qr', "Carnet de muestra\nSede: " . $sede['sede_nombre'], ['sx' => 4, 'sy' => 4, 'p' => -12]);
			imagejpeg($img, $qr);
			unset($img);

			$pdf = new \AlphaPDF('L', 'mm', [CARNET_ANCHO, CARNET_ALTO]);
			$pdf->SetAutoPagebreak(false);
			$pdf->SetMargins(0, 0, 0);
			$pdf->AddPage();
			carnet_dibujar($pdf, 0, 0, [
				'color' => [$r, $g, $b],
				'img_fondo' => carnet_dir_imagenes() . $sede['escuela_verticalfondo'],
				'img_linea' => carnet_dir_imagenes() . $sede['escuela_verticalprincipal'],
				'sede_id' => (int)$sede['sede_id'],
				'qr' => $qr,
				'foto' => dirname(__DIR__) . '/views/imagenes/fotos/alumno/alumno.jpg',
				'nombre' => 'Nombre Apellido Ejemplo',
				'ci' => '0000000000',
				'horario' => '16H00 PM',
				'edad' => 10,
				'mes' => $this->nombreMes($mes),
				'sede_nombre' => $sede['sede_nombre'],
				'correo' => ds_email_organizacion((int)$sede['sede_id']),
				'telefono' => ds_telefono_organizacion((int)$sede['sede_id']),
				'reimpresion' => false,
				'fecha' => date('d/m/Y'),
			], $this->disenoCarnet());
			@unlink($qr);
			$pdf->Output('carnet_muestra.pdf', 'I');
		}

		public function crearColorCarnet(): string
		{
			$v = $this->validarColor(
				(string)($_POST['color_nombre'] ?? ''),
				(string)($_POST['color_hex'] ?? ''));

			if (isset($v['error'])) {
				return $this->alertaCarnet('Color no válido', $v['error'], 'warning');
			}

			$n = $this->ejecutarConsulta(
				"INSERT INTO carnet_catcolor (catcolor_nombre, catcolor_hex, catcolor_activo)
				 VALUES (:n, :h, 1)",
				[':n' => $v['nombre'], ':h' => $v['hex']])->rowCount();

			return $n > 0
				? $this->alertaCarnet('Color agregado',
					'«' . $v['nombre'] . '» ya se puede asignar a un mes.', 'success', 'recargar')
				: $this->alertaCarnet('Error', 'No fue posible guardar el color.', 'error');
		}

		/**
		 * Cambio de nombre o tonalidad.
		 *
		 * La comprobacion de carnets se repite AQUI y no solo en la
		 * pantalla: la pantalla deshabilita el campo, pero una peticion se
		 * arma a mano y el servidor no puede fiarse de lo que se pinto.
		 */
		public function editarColorCarnet(): string
		{
			$id = (int)($_POST['color_id'] ?? 0);
			if ($id <= 0) {
				return $this->alertaCarnet('Error', 'No se indicó el color.', 'error');
			}

			$estado = $this->estadoColor($id);
			if ($estado === null) {
				return $this->alertaCarnet('Error', 'El color no existe o está retirado.', 'error');
			}

			if (!$estado['editable']) {
				return $this->alertaCarnet('Color en circulación',
					'No se puede cambiar: ' . $estado['motivo'] . '. '
					. 'Los carnets ya impresos llevan este tono y dejarían de coincidir. '
					. 'Agregue un color nuevo y asígnelo a un mes libre.',
					'warning');
			}

			$v = $this->validarColor(
				(string)($_POST['color_nombre'] ?? ''),
				(string)($_POST['color_hex'] ?? ''),
				$id);

			if (isset($v['error'])) {
				return $this->alertaCarnet('Color no válido', $v['error'], 'warning');
			}

			$this->ejecutarConsulta(
				"UPDATE carnet_catcolor
				    SET catcolor_nombre = :n, catcolor_hex = :h
				  WHERE catcolor_id = :id",
				[':n' => $v['nombre'], ':h' => $v['hex'], ':id' => $id]);

			return $this->alertaCarnet('Color actualizado',
				'«' . $v['nombre'] . '» quedó en ' . $v['hex'] . '.', 'success', 'recargar');
		}

		/** Retiro del catálogo. No borra: desactiva, para no perder el histórico. */
		public function retirarColorCarnet(): string
		{
			$id = (int)($_POST['color_id'] ?? 0);
			$estado = $id > 0 ? $this->estadoColor($id) : null;

			if ($estado === null) {
				return $this->alertaCarnet('Error', 'El color no existe o ya está retirado.', 'error');
			}

			if (!$estado['retirable']) {
				/* «Este color » + motivo daba «Este color 104 carnet(s)
				   impresos en Abril»: el motivo es una frase nominal y hace
				   falta el verbo. Se compone entera en cada rama. */
				$porque = $estado['carnets'] > 0
					? 'tiene ' . $estado['motivo']
					: 'está asignado a algún mes'
					  . ($estado['anios'] ? ' de ' . $estado['anios'] : '');

				return $this->alertaCarnet('No se puede retirar',
					'Este color ' . $porque . '. Libere el mes antes de retirarlo.', 'warning');
			}

			/* Desactivar y no borrar: la fila puede estar referenciada por
			   asignaciones antiguas de carnet_mes_color, y borrarla dejaria
			   huerfanas esas filas o rompería la clave foránea. */
			$this->ejecutarConsulta(
				"UPDATE carnet_catcolor SET catcolor_activo = 0 WHERE catcolor_id = :id",
				[':id' => $id]);

			return $this->alertaCarnet('Color retirado',
				'«' . $estado['nombre'] . '» ya no se ofrece para asignar.', 'success', 'recargar');
		}

		/** El estado de UN color, con la misma regla que el catálogo. */
		/**
		 * El estado de UN color para decidir si se puede tocar.
		 *
		 * OJO CON LA DIFERENCIA, QUE ES LA CLAVE DE TODO ESTO
		 *
		 * La ASIGNACION —qué color va en qué mes— es por año y se libera al
		 * cambiar de año: en 2027 abril vuelve a estar disponible aunque en
		 * 2026 se imprimieran 104 carnets.
		 *
		 * El TONO de un color, en cambio, no puede cambiar NUNCA si algún
		 * carnet de CUALQUIER año lo lleva impreso. Esos carnets siguen en
		 * circulación y siguen siendo de ese tono.
		 *
		 * Por eso aquí se miran TODOS los años y no el que se esté
		 * configurando. Usar el catálogo del año en curso dejaría cambiarle
		 * el tono en 2027 a un color que en 2026 imprimió cien carnets.
		 */
		private function estadoColor(int $id): ?array
		{
			$sql = "SELECT c.catcolor_id, c.catcolor_nombre, c.catcolor_hex,
			               (SELECT COUNT(*)
			                  FROM carnet_mes_color m
			                  JOIN alumno_carnet a
			                    ON a.carnet_mes  = m.mcolor_mes
			                   AND a.carnet_anio = m.mcolor_anio
			                 WHERE m.mcolor_catcolorid = c.catcolor_id) AS carnets,
			               (SELECT COUNT(*)
			                  FROM carnet_mes_color m2
			                 WHERE m2.mcolor_catcolorid = c.catcolor_id
			                   AND m2.mcolor_activo = 1) AS asignaciones,
			               (SELECT GROUP_CONCAT(DISTINCT m3.mcolor_anio ORDER BY m3.mcolor_anio)
			                  FROM carnet_mes_color m3
			                 WHERE m3.mcolor_catcolorid = c.catcolor_id
			                   AND m3.mcolor_activo = 1) AS anios
			          FROM carnet_catcolor c
			         WHERE c.catcolor_id = :id AND c.catcolor_activo = 1";

			$f = $this->ejecutarConsulta($sql, [':id' => $id])->fetch(\PDO::FETCH_ASSOC);
			if (!$f) { return null; }

			$carnets = (int)$f['carnets'];

			return [
				'id'        => (int)$f['catcolor_id'],
				'nombre'    => (string)$f['catcolor_nombre'],
				'hex'       => strtoupper((string)$f['catcolor_hex']),
				'carnets'   => $carnets,
				'anios'     => (string)($f['anios'] ?? ''),
				/* El tono se puede cambiar si NINGÚN carnet lo lleva. */
				'editable'  => $carnets === 0,
				/* Retirar exige además que no esté asignado a ningún año. */
				'retirable' => $carnets === 0 && (int)$f['asignaciones'] === 0,
				'motivo'    => $carnets > 0
					? $carnets . ' carnet(s) impresos con este tono'
					  . ($f['anios'] ? ' (años ' . $f['anios'] . ')' : '')
					: '',
				'mes_nombre' => '',
			];
		}

		/** Respuesta con el formato que espera alertas_ajax(). */
		private function alertaCarnet(string $titulo, string $texto,
		                              string $icono = 'success', string $tipo = 'simple'): string
		{
			return json_encode([
				'tipo'   => $tipo,
				'titulo' => $titulo,
				'texto'  => $texto,
				'icono'  => $icono,
			], JSON_UNESCAPED_UNICODE);
		}
		public function actualizarColoresMeses() {
			if(!isset($_POST['color_mes']) || !is_array($_POST['color_mes'])) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Error",
					"texto" => "No se recibieron datos de colores",
					"icono" => "error"
				]);
			}

			$colores_mes = $_POST['color_mes'];
			$errores = [];
			$bloqueados = [];
			$actualizados = 0;
			$cobrar_reimpresion = (isset($_POST['cobrar_reimpresion']) && $_POST['cobrar_reimpresion'] === '1') ? '1' : '0';
			$valor_reimpresion = str_replace(',', '.', $_POST['valor_reimpresion'] ?? $this->valorReimpresionCarnet());
			$valor_anterior = number_format($this->valorReimpresionCarnet(), 2, '.', '');
			$cobro_anterior = $this->cobrarReimpresionCarnet() ? '1' : '0';
			$cobro_actualizado = false;
			$valor_actualizado = false;

			if(!is_numeric($valor_reimpresion) || (float)$valor_reimpresion <= 0) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Valor invalido",
					"texto" => "El valor de reimpresion debe ser mayor a 0",
					"icono" => "warning"
				]);
			}

			$valor_reimpresion = number_format((float)$valor_reimpresion, 2, '.', '');

			// Validar duplicados
			$colores_seleccionados = array_filter($colores_mes, function($v) { return $v > 0; });
			$colores_unicos = array_unique($colores_seleccionados);

			if(count($colores_seleccionados) != count($colores_unicos)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Error: Colores duplicados",
					"texto" => "No puede asignar el mismo color a diferentes meses",
					"icono" => "error"
				]);
			}

			/*
			| EL ANIO SOBRE EL QUE SE CONFIGURA
			|
			| Desde la migracion 062 la asignacion es por anio. Se acepta el
			| anio EN CURSO y el SIGUIENTE: preparar la paleta del proximo
			| anio por adelantado es justo el caso de uso, y reescribir un
			| anio pasado seria falsear el color de carnets ya impresos.
			*/
			$anio = (int)($_POST['anio'] ?? date('Y'));
			$anioMin = (int)date('Y');
			$anioMax = $anioMin + 1;

			if ($anio < $anioMin || $anio > $anioMax) {
				return json_encode([
					"tipo"   => "simple",
					"titulo" => "Año no editable",
					"texto"  => "Sólo se puede configurar {$anioMin} o {$anioMax}. "
					          . "Los años pasados quedan como se imprimieron.",
					"icono"  => "warning"
				], JSON_UNESCAPED_UNICODE);
			}

			// Procesar cada mes
			foreach($colores_mes as $mes => $color_id_nuevo) {
				// Validar bloqueo, EN ESTE ANIO
				if($this->mesBloqueado($mes, $anio)) {
					$bloqueados[] = $this->nombreMes($mes);
					continue;
				}

				// Validar asignación duplicada, EN ESTE ANIO
				if($this->colorYaAsignado($color_id_nuevo, $mes, $anio)) {
					$errores[] = "El color para " . $this->nombreMes($mes) . " ya está asignado";
					continue;
				}

				/*
				| «SIN ASIGNAR» ES LA AUSENCIA DE FILA, NO UNA FILA CON 0
				|
				| El desplegable ofrece «— Sin asignar —» con valor 0. Al
				| probar la configuracion de 2027 se guardo una fila con
				| mcolor_catcolorid = 0, que apunta a un color inexistente:
				| el JOIN con el catalogo no la encuentra y el mes aparece
				| vacio sin que nada explique por que.
				|
				| El UPDATE anterior tenia el mismo defecto —dejaba la fila
				| existente apuntando a 0—, pero no se veia porque los doce
				| meses siempre habian tenido color. El INSERT lo hizo
				| visible.
				|
				| Un mes sin color se representa desactivando su asignacion.
				*/
				if ((int)$color_id_nuevo <= 0) {
					$this->ejecutarConsulta(
						"UPDATE carnet_mes_color SET mcolor_activo = 0
						  WHERE mcolor_mes = :mes AND mcolor_anio = :anio",
						[':mes' => $mes, ':anio' => $anio]);
					continue;
				}

				/*
				| UPSERT y ya no UPDATE.
				|
				| Antes existia una fila por mes y bastaba actualizarla. Con
				| la asignacion por anio, un anio que aun no se configuro no
				| tiene filas: habria que insertarlas, y un UPDATE sobre lo
				| que no existe no falla, simplemente no hace nada —la
				| pantalla diria «guardado» sin guardar—.
				|
				| La clave unica (mcolor_mes, mcolor_anio) es la que resuelve
				| el duplicado, asi que el upsert se apoya en el esquema y no
				| en una consulta previa que otro proceso podria adelantar.
				*/
				$sql = "INSERT INTO carnet_mes_color
							(mcolor_mes, mcolor_anio, mcolor_catcolorid, mcolor_activo)
						VALUES (:mes, :anio, :color_id, 1)
						ON DUPLICATE KEY UPDATE
							mcolor_catcolorid = VALUES(mcolor_catcolorid),
							mcolor_activo = 1";

				$parametros = [
					':color_id' => $color_id_nuevo,
					':mes'      => $mes,
					':anio'     => $anio
				];

				try {
					$result = $this->ejecutarConsulta($sql, $parametros);
					if($result) {
						$actualizados++;
					}
				} catch (Exception $e) {
					$errores[] = "Error en " . $this->nombreMes($mes) . ": " . $e->getMessage();
				}
			}
			try {
				if($cobro_anterior !== $cobrar_reimpresion) {
					$this->guardarValorConfiguracionCarnet(
						'cobrar_reimpresion',
						$cobrar_reimpresion,
						'Define si la reimpresion de carnets genera cargo ROT'
					);
					$cobro_actualizado = true;
				}

				if($valor_anterior !== $valor_reimpresion) {
					$this->guardarValorConfiguracionCarnet(
						'valor_reimpresion',
						$valor_reimpresion,
						'Valor del rubro ROT para reimpresion de carnets'
					);
					$valor_actualizado = true;
				}
			} catch (Exception $e) {
				$errores[] = "Error guardando configuracion de cobro por reimpresion: " . $e->getMessage();
			}

			// Construir respuesta
			if(count($bloqueados) > 0 || count($errores) > 0) {
				$mensaje = "";

				if($actualizados > 0) {
					$mensaje .= "Actualizados: $actualizados meses. ";
				}

				if($cobro_actualizado) {
					$mensaje .= "Politica de cobro por reimpresion actualizada. ";
				}

				if($valor_actualizado) {
					$mensaje .= "Valor de reimpresion actualizado. ";
				}

				if(count($bloqueados) > 0) {
					$mensaje .= "🔒 Bloqueados: " . implode(", ", $bloqueados) . ". ";
				}

				if(count($errores) > 0) {
					$mensaje .= "❌ " . implode(", ", $errores);
				}

				return json_encode([
					"tipo" => (($actualizados > 0 || $cobro_actualizado || $valor_actualizado) ? "recargar" : "simple"),
					"titulo" => "Actualización parcial",
					"texto" => $mensaje,
					"icono" => "warning"
				]);
			}

			if($actualizados > 0 || $cobro_actualizado || $valor_actualizado) {
				return json_encode([
					"tipo" => "recargar",
					"titulo" => "Configuracion actualizada",
					"texto" => "La configuracion de carnets se guardo correctamente",
					"icono" => "success"
				]);
			}

			return json_encode([
				"tipo" => "simple",
				"titulo" => "Sin cambios",
				"texto" => "No se realizaron modificaciones",
				"icono" => "info"
			]);
		}

		private function nombreMes($mes) {
			$meses = [
				1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
				5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
				9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
			];
			return $meses[$mes] ?? 'Mes desconocido';
		}


		/**
		 * ============================================================
		 * MÉTODOS PARA GENERACIÓN E IMPRESIÓN DE CARNETS
		 * ============================================================
		 */

		/**
		 * Obtener carnets del mes actual listos para imprimir
		 * Incluye todos los alumnos con pagos de pensión (RPE) del mes
		 * @return array Array con datos de carnets
		 */
		public function obtenerCarnetsMesActual($sede = 0) {
			$parSede = [];
			$sedeSql = $this->condicionSede('a.alumno_sedeid', $sede, $parSede);
			$fecha_actual = date('Y-m-d');
			$mes_actual = date('n'); // Mes actual (1-12)
			$anio_actual = date('Y');

			// Obtener color asignado al mes
			$colorMes = $this->BuscarColorPorMes($mes_actual);
			$colorData = $colorMes->fetch();

			$consulta = "SELECT
							a.alumno_id,
							a.alumno_identificacion,
							CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
								a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
							a.alumno_imagen,
							a.alumno_sedeid,
							h.horario_nombre,
							ac.carnet_id,
							ac.carnet_alumnoid,
							:mes as carnet_mes,
							:anio as carnet_anio,
							:fecha_actual as carnet_fecha_emision,
							:fecha_actual as carnet_fecha_impresion,
							0 as es_reimpresion,
							:color_hex as color_hex,
							:mes_nombre as mes_nombre
							FROM sujeto_alumno a
							INNER JOIN asistencia_asignahorario ah ON ah.asignahorario_alumnoid = a.alumno_id
							INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
							INNER JOIN alumno_pago ap ON ap.pago_alumnoid = a.alumno_id
							LEFT JOIN alumno_carnet ac ON ac.carnet_alumnoid = a.alumno_id
													AND ac.carnet_mes = :mes
													AND ac.carnet_anio = :anio
							WHERE a.alumno_estado = 'A' AND $sedeSql
								AND ap.pago_estado NOT IN ('E', 'J')
								AND MONTH(ap.pago_fecha) = :mes
								AND YEAR(ap.pago_fecha) = :anio
								AND ap.pago_rubroid = 'RPE'
								AND ac.carnet_alumnoid IS NULL
						UNION ALL

						SELECT
							a.alumno_id,
							a.alumno_identificacion,
							CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
								a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
							a.alumno_imagen,
							a.alumno_sedeid,
							h.horario_nombre,
							ac.carnet_id,
							ac.carnet_alumnoid,
							:mes as carnet_mes,
							:anio as carnet_anio,
							:fecha_actual as carnet_fecha_emision,
							:fecha_actual as carnet_fecha_impresion,
							0 as es_reimpresion,
							:color_hex as color_hex,
							:mes_nombre as mes_nombre
							FROM sujeto_alumno a
							INNER JOIN asistencia_asignahorario ah ON ah.asignahorario_alumnoid = a.alumno_id
							INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
							INNER JOIN alumno_pago_descuento apd ON apd.descuento_alumnoid = a.alumno_id
							LEFT JOIN alumno_carnet ac ON ac.carnet_alumnoid = a.alumno_id
													AND ac.carnet_mes = :mes
													AND ac.carnet_anio = :anio
							WHERE a.alumno_estado = 'A' AND $sedeSql
								AND apd.descuento_rubroid = 'DBC'
								AND apd.descuento_valor = 0
								AND apd.descuento_estado = 'S'
								AND ac.carnet_alumnoid IS NULL";

			$parametros = $parSede + [
				':fecha_actual' => $fecha_actual,
				':mes' => $mes_actual,
				':anio' => $anio_actual,
				':color_hex' => $colorData['color_hex'] ?? '#CCCCCC',
				':mes_nombre' => $this->nombreMes($mes_actual)
			];

			$datos = $this->ejecutarConsulta($consulta, $parametros);
			$carnets = $datos->fetchAll();

			// Generar carnets si no existen
			$carnetsFinales = [];
			foreach($carnets as $carnet) {
				if(empty($carnet['carnet_id'])) {
					// Crear nuevo carnet
					$nuevoCarnet = $this->crearCarnet(
						$carnet['alumno_id'],
						$mes_actual,
						$anio_actual
					);
					$carnet['carnet_id'] = $nuevoCarnet['carnet_id'];
					$carnet['carnet_fecha_emision'] = $nuevoCarnet['carnet_fecha_emision'];
				}
				$carnetsFinales[] = $carnet;
			}

			return $carnetsFinales;
		}

		/**
		 * Crear un nuevo carnet para un alumno
		 * @param int $alumno_id ID del alumno
		 * @param int $mes Mes de vigencia
		 * @param int $anio Año de vigencia
		 * @return array Datos del carnet creado
		 */
		private function crearCarnet($alumno_id, $mes, $anio) {
			// El carnet se crea SIN fecha de impresion; se marca como impreso
			// solo cuando realmente se genera el PDF (registrarImpresion).
			$conexion = $this->conectar();
			$sql = $conexion->prepare(
				"INSERT INTO alumno_carnet
					(carnet_mes, carnet_anio, carnet_alumnoid, carnet_fecha_emision, carnet_fecha_impresion)
				 VALUES
					(:mes, :anio, :alumno_id, CURDATE(), NULL)"
			);
			$sql->execute([
				':mes' => $mes,
				':anio' => $anio,
				':alumno_id' => $alumno_id
			]);

			// lastInsertId() debe leerse en la MISMA conexion del INSERT.
			return [
				'carnet_id' => $conexion->lastInsertId(),
				'carnet_fecha_emision' => date('Y-m-d')
			];
		}

		/**
		 * Obtener el último ID insertado
		 * @return int ID insertado
		 */
		private function obtenerUltimoId() {
			$sql = "SELECT LAST_INSERT_ID() as ultimo_id";
			$datos = $this->ejecutarConsulta($sql);
			$resultado = $datos->fetch();
			return $resultado['ultimo_id'];
		}

		/**
		 * Registrar impresión de carnets
		 * @param array $carnet_ids IDs de carnets impresos
		 * @return bool
		 */
		public function registrarImpresion($carnet_ids) {
			if(empty($carnet_ids)) {
				return false;
			}

			$ids_string = implode(',', array_map('intval', $carnet_ids));

			$sql = "UPDATE alumno_carnet
					SET carnet_fecha_impresion = NOW()
					WHERE carnet_id IN ($ids_string)
						AND carnet_fecha_impresion IS NULL";

			return $this->ejecutarConsulta($sql);
		}

		public function procesarReimpresion() {
			// Limpiar y validar datos
			$alumno_ids = $_POST['pagos_seleccionados'] ?? [];

			if(empty($alumno_ids)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Sin selección",
					"texto" => "Debe seleccionar al menos un alumno para reimprimir",
					"icono" => "warning"
				]);
			}

			// Limpiar IDs
			$alumno_ids = array_map([$this, 'limpiarCadena'], $alumno_ids);
			$alumno_ids = array_filter($alumno_ids, 'is_numeric');

			/* Sólo alumnos de las sedes del usuario: los ids llegan del navegador. */
			$alumno_ids = $this->alumnosEnSedesDeLaSesion($alumno_ids);

			if(empty($alumno_ids)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Error",
					"texto" => "Los alumnos elegidos no existen o son de otra sede.",
					"icono" => "error"
				]);
			}

			$mes_actual = date('n');
			$name_mesactual = $this->nombreMes($mes_actual);
			$anio_actual = date('Y');
			$fecha_actual = date('Y-m-d');
			$cobrar_reimpresion = $this->cobrarReimpresionCarnet();
			$valor_reimpresion_pago = $this->valorReimpresionCarnet();

			$procesados = 0;
			$pagos_generados = 0;
			$reimpresiones_sin_cobro = 0;
			$errores = [];

			foreach($alumno_ids as $alumno_id) {
				try {
					// Verificar si ya tiene carnet del mes
					$sqlVerificar = "SELECT carnet_id
								FROM alumno_carnet
								WHERE carnet_alumnoid = :alumno_id
								AND carnet_mes = :mes
								AND carnet_anio = :anio";

					$datos = $this->ejecutarConsulta($sqlVerificar, [
						':alumno_id' => $alumno_id,
						':mes' => $mes_actual,
						':anio' => $anio_actual
					]);

					if($datos->rowCount() == 0) {
						$errores[] = "Alumno ID $alumno_id no tiene carnet original del mes";
						continue;
					}

					// Verificar beca 100% (DBC) antes de generar cobro
					$sqlBeca100 = "SELECT 1
									FROM alumno_pago_descuento
									WHERE descuento_alumnoid = :alumno_id
										AND descuento_rubroid = 'DBC'
										AND descuento_valor = 0
										AND descuento_estado = 'S'
									LIMIT 1";

					$datosBeca100 = $this->ejecutarConsulta($sqlBeca100, [
						':alumno_id' => $alumno_id
					]);
					$tiene_beca_100 = ($datosBeca100->rowCount() > 0);

					if($cobrar_reimpresion && !$tiene_beca_100) {
						$recibo = $this->generarNumeroRecibo('ROT');

						// Insertar pago por reimpresión
						$sqlPago = "INSERT INTO alumno_pago
									(pago_rubroid, pago_formapagoid, pago_alumnoid, pago_sedeid, pago_valor,
										pago_saldo, pago_concepto, pago_fecha, pago_fecharegistro,
										pago_periodo, pago_recibo, pago_estado)
									VALUES
									('ROT', 'FEF', :alumno_id, :sede_id, :valor_reimpresion, 0.00,
										'Por reimpresión de carnet extraviado',
										:fecha, :fecha, :periodo, :recibo, 'C')";

						$this->ejecutarConsulta($sqlPago, [
							':alumno_id' => $alumno_id,
							/* Sede CONGELADA al cobrar; no sigue traslados. Ver database/044. */
							':sede_id'   => $this->sedeDelAlumno($alumno_id),
							':valor_reimpresion' => $valor_reimpresion_pago,
							':fecha' => $fecha_actual,
							':periodo' => $name_mesactual . '/' . $anio_actual,
							':recibo' => $recibo
						]);

						$pagos_generados++;
					} else {
						$reimpresiones_sin_cobro++;
					}

					$procesados++;

				} catch (Exception $e) {
					$errores[] = "Error en alumno ID $alumno_id: " . $e->getMessage();
				}
			}

			// Construir respuesta
			if($procesados > 0 && empty($errores)) {
				// ✅ GUARDAR IDS EN SESIÓN en lugar de URL
				$alumno_ids_reimpresion = implode(',', $alumno_ids);
				$_SESSION['carnet_reimpresion_ids'] = $alumno_ids_reimpresion;
				$token_reimpresion = rtrim(strtr(base64_encode($alumno_ids_reimpresion), '+/', '-_'), '=');
				$firma_reimpresion = hash_hmac('sha256', $alumno_ids_reimpresion, session_id());
				session_write_close();
				$texto = "Se generaron $pagos_generados pagos por reimpresion. Redirigiendo a impresion...";
				if($reimpresiones_sin_cobro > 0) {
					$motivo_sin_cobro = $cobrar_reimpresion ? "por beca 100%" : "por configuracion sin cobro";
					$texto = "Se generaron $pagos_generados pagos por reimpresion y $reimpresiones_sin_cobro reimpresiones sin cobro $motivo_sin_cobro. Redirigiendo a impresion...";
				}

				return json_encode([
					"tipo" => "redireccionar",
					"titulo" => "¡Reimpresión procesada!",
					"texto" => $texto,
					"icono" => "success",
					"url" => APP_URL . "carnetPDF/?modo=reimpresion&reimpresion=" . rawurlencode($token_reimpresion) . "&firma=" . $firma_reimpresion
				]);
			}

			if($procesados > 0 && !empty($errores)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Procesamiento parcial",
					"texto" => "Procesados: $procesados (pagos generados: $pagos_generados, sin cobro: $reimpresiones_sin_cobro). Errores: " . implode(", ", $errores),
					"icono" => "warning"
				]);
			}

			return json_encode([
				"tipo" => "simple",
				"titulo" => "Error en procesamiento",
				"texto" => implode(", ", $errores),
				"icono" => "error"
			]);
		}

		/**
		 * Generar número de recibo único
		 * @param string $tipo Tipo de pago (ROT para reimpresión)
		 * @return string Número de recibo
		 */
		private function generarNumeroRecibo($tipo) {
			$sql = "SELECT MAX(CAST(SUBSTRING(pago_recibo, 4) AS UNSIGNED)) as ultimo
					FROM alumno_pago
					WHERE pago_rubroid = :tipo
					AND YEAR(pago_fecha) = YEAR(CURDATE())";

			$datos = $this->ejecutarConsulta($sql, [':tipo' => $tipo]);
			$resultado = $datos->fetch();

			$siguiente = ($resultado['ultimo'] ?? 0) + 1;

			return $tipo . str_pad($siguiente, 6, '0', STR_PAD_LEFT);
		}

		/**
		 * Obtener carnets para reimpresión
		 * @param string $alumno_ids_string IDs separados por coma
		 * @return array Carnets con marca de reimpresión
		 */
		public function obtenerCarnetsReimpresion($alumno_ids_string) {
			$alumno_ids = explode(',', $alumno_ids_string);
			$alumno_ids = array_map('intval', $alumno_ids);
			$alumno_ids = array_filter($alumno_ids);

			if(empty($alumno_ids)) {
				return [];
			}

			$mes_actual = date('n');
			$anio_actual = date('Y');

			$ids_string = implode(',', $alumno_ids);

			// Obtener color del mes
			$colorMes = $this->BuscarColorPorMes($mes_actual);
			$colorData = $colorMes->fetch();

			$consulta = "SELECT
							a.alumno_id,
							a.alumno_identificacion,
							CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
								a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
							a.alumno_imagen,
							a.alumno_sedeid,
							h.horario_nombre,
							ac.carnet_id,
							ac.carnet_mes,
							ac.carnet_anio,
							ac.carnet_fecha_emision,
							1 as es_reimpresion,
							:color_hex as color_hex,
							:mes_nombre as mes_nombre
						FROM sujeto_alumno a
						INNER JOIN asistencia_asignahorario ah ON ah.asignahorario_alumnoid = a.alumno_id
						INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
						INNER JOIN alumno_carnet ac ON ac.carnet_id = (
							SELECT ac2.carnet_id
							FROM alumno_carnet ac2
							WHERE ac2.carnet_alumnoid = a.alumno_id
								AND ac2.carnet_mes = :mes
								AND ac2.carnet_anio = :anio
							ORDER BY ac2.carnet_id DESC
							LIMIT 1
						)
						WHERE a.alumno_id IN ($ids_string)
						AND a.alumno_estado = 'A'
						ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno";

			$parametros = [
				':mes' => $mes_actual,
				':anio' => $anio_actual,
				':color_hex' => $colorData['color_hex'] ?? '#CCCCCC',
				':mes_nombre' => $this->nombreMes($mes_actual)
			];

			$datos = $this->ejecutarConsulta($consulta, $parametros);
			return $datos->fetchAll();
		}

		public function carnetPendientesImpresion($sede = 0) {
			$parametros = [];
			$sedeSql = $this->condicionSede('a.alumno_sedeid', $sede, $parametros);
			$mes_actual = date('n');
			$anio_actual = date('Y');

			// Pendiente = alumno con pago/descuento del mes, con horario asignado,
			// que aun NO tiene un carnet IMPRESO este mes (no existe registro, o existe sin imprimir).
			// El UNION (no UNION ALL) evita contar dos veces a un mismo alumno.
			$consulta = "SELECT COUNT(*) AS total
						FROM (
							SELECT a.alumno_id
							FROM sujeto_alumno a
							INNER JOIN alumno_pago ap ON ap.pago_alumnoid = a.alumno_id
							WHERE a.alumno_estado = 'A' AND $sedeSql
								AND ap.pago_estado NOT IN ('E', 'J')
								AND MONTH(ap.pago_fecha) = :mes
								AND YEAR(ap.pago_fecha) = :anio
								AND ap.pago_rubroid = 'RPE'
								AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
								AND NOT EXISTS (
									SELECT 1 FROM alumno_carnet ac
									WHERE ac.carnet_alumnoid = a.alumno_id
										AND ac.carnet_mes = :mes AND ac.carnet_anio = :anio
										AND ac.carnet_fecha_impresion IS NOT NULL
								)

							UNION

							SELECT a.alumno_id
							FROM sujeto_alumno a
							INNER JOIN alumno_pago_descuento apd ON apd.descuento_alumnoid = a.alumno_id
							WHERE a.alumno_estado = 'A' AND $sedeSql
								AND apd.descuento_rubroid = 'DBC'
								AND apd.descuento_valor = 0
								AND apd.descuento_estado = 'S'
								AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
								AND NOT EXISTS (
									SELECT 1 FROM alumno_carnet ac
									WHERE ac.carnet_alumnoid = a.alumno_id
										AND ac.carnet_mes = :mes AND ac.carnet_anio = :anio
										AND ac.carnet_fecha_impresion IS NOT NULL
								)
						) AS subconsulta";

			$parametros += [
				':mes' => $mes_actual,
				':anio' => $anio_actual
			];

			$datos = $this->ejecutarConsulta($consulta, $parametros);
			return $datos->fetchAll();
		}
		public function obtenerCarnetsPendientesMesActual($sede = 0) {
			$parSede = [];
			$sedeSql = $this->condicionSede('a.alumno_sedeid', $sede, $parSede);
			$mes_actual = date('n');
			$anio_actual = date('Y');
			$fecha_actual = date('Y-m-d');

			$colorMes = $this->BuscarColorPorMes($mes_actual);
			$colorData = $colorMes->fetch();
			$color_hex = $colorData['color_hex'] ?? '#CCCCCC';
			$mes_nombre = $this->nombreMes($mes_actual);

			$consulta = "SELECT
								a.alumno_id,
								a.alumno_identificacion,
								CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
									a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
								a.alumno_imagen,
								a.alumno_sedeid,
								(SELECT h.horario_nombre
									FROM asistencia_asignahorario ah
									INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
									WHERE ah.asignahorario_alumnoid = a.alumno_id
									LIMIT 1) as horario_nombre,
								ac.carnet_id,
								:mes as carnet_mes,
								:anio as carnet_anio,
								COALESCE(ac.carnet_fecha_emision, :fecha_actual) as carnet_fecha_emision,
								0 as es_reimpresion,
								:color_hex as color_hex,
								:mes_nombre as mes_nombre
							FROM (
								SELECT DISTINCT a.alumno_id
								FROM sujeto_alumno a
								INNER JOIN alumno_pago ap ON ap.pago_alumnoid = a.alumno_id
								WHERE a.alumno_estado = 'A' AND $sedeSql
									AND ap.pago_estado NOT IN ('E', 'J')
									AND MONTH(ap.pago_fecha) = :mes
									AND YEAR(ap.pago_fecha) = :anio
									AND ap.pago_rubroid = 'RPE'
									AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
									AND NOT EXISTS (
										SELECT 1 FROM alumno_carnet acp
										WHERE acp.carnet_alumnoid = a.alumno_id
											AND acp.carnet_mes = :mes
											AND acp.carnet_anio = :anio
											AND acp.carnet_fecha_impresion IS NOT NULL
									)

								UNION

								SELECT DISTINCT a.alumno_id
								FROM sujeto_alumno a
								INNER JOIN alumno_pago_descuento apd ON apd.descuento_alumnoid = a.alumno_id
								WHERE a.alumno_estado = 'A' AND $sedeSql
									AND apd.descuento_rubroid = 'DBC'
									AND apd.descuento_valor = 0
									AND apd.descuento_estado = 'S'
									AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
									AND NOT EXISTS (
										SELECT 1 FROM alumno_carnet acp
										WHERE acp.carnet_alumnoid = a.alumno_id
											AND acp.carnet_mes = :mes
											AND acp.carnet_anio = :anio
											AND acp.carnet_fecha_impresion IS NOT NULL
									)
							) elegibles
							INNER JOIN sujeto_alumno a ON a.alumno_id = elegibles.alumno_id
							LEFT JOIN alumno_carnet ac ON ac.carnet_id = (
								SELECT ac2.carnet_id
								FROM alumno_carnet ac2
								WHERE ac2.carnet_alumnoid = a.alumno_id
									AND ac2.carnet_mes = :mes
									AND ac2.carnet_anio = :anio
								ORDER BY ac2.carnet_id DESC
								LIMIT 1
							)
							ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno";

			$datos = $this->ejecutarConsulta($consulta, $parSede + [
				':fecha_actual' => $fecha_actual,
				':mes' => $mes_actual,
				':anio' => $anio_actual,
				':color_hex' => $color_hex,
				':mes_nombre' => $mes_nombre
			]);

			$carnets = $datos->fetchAll();
			$carnetsFinales = [];

			foreach($carnets as $carnet) {
				if(empty($carnet['carnet_id'])) {
					$nuevoCarnet = $this->crearCarnet(
						$carnet['alumno_id'],
						$mes_actual,
						$anio_actual
					);
					$carnet['carnet_id'] = $nuevoCarnet['carnet_id'];
					$carnet['carnet_fecha_emision'] = $nuevoCarnet['carnet_fecha_emision'];
				}

				$carnetsFinales[] = $carnet;
			}

			if(empty($carnetsFinales)) {
				$carnetsFinales = $this->obtenerCarnetsNoImpresosMesActual($sede);
			}

			if(empty($carnetsFinales)) {
				return $this->obtenerCarnetsMesActual($sede);
			}

			return $carnetsFinales;
		}
		public function obtenerCarnetsNoImpresosMesActual($sede = 0) {
			$parSede = [];
			$sedeSql = $this->condicionSede('a.alumno_sedeid', $sede, $parSede);
			$mes_actual = date('n');
			$anio_actual = date('Y');

			$colorMes = $this->BuscarColorPorMes($mes_actual);
			$colorData = $colorMes->fetch();

			$consulta = "SELECT
								a.alumno_id,
								a.alumno_identificacion,
								CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
									a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
								a.alumno_imagen,
								a.alumno_sedeid,
								(SELECT h.horario_nombre
									FROM asistencia_asignahorario ah
									INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
									WHERE ah.asignahorario_alumnoid = a.alumno_id
									LIMIT 1) as horario_nombre,
								ac.carnet_id,
								ac.carnet_mes,
								ac.carnet_anio,
								ac.carnet_fecha_emision,
								0 as es_reimpresion,
								:color_hex as color_hex,
								:mes_nombre as mes_nombre
							FROM alumno_carnet ac
							INNER JOIN sujeto_alumno a ON a.alumno_id = ac.carnet_alumnoid
							WHERE ac.carnet_mes = :mes
								AND ac.carnet_anio = :anio
								AND ac.carnet_fecha_impresion IS NULL
								AND a.alumno_estado = 'A' AND $sedeSql
								AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
							ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno";

			$datos = $this->ejecutarConsulta($consulta, $parSede + [
				':mes' => $mes_actual,
				':anio' => $anio_actual,
				':color_hex' => $colorData['color_hex'] ?? '#CCCCCC',
				':mes_nombre' => $this->nombreMes($mes_actual)
			]);

			return $datos->fetchAll();
		}
		public function obtenerCarnetsMensualesPorIds($carnet_ids_string) {
			$carnet_ids = explode(',', $carnet_ids_string);
			$carnet_ids = array_map('intval', $carnet_ids);
			$carnet_ids = array_filter($carnet_ids);

			if(empty($carnet_ids)) {
				return [];
			}

			$mes_actual = date('n');
			$anio_actual = date('Y');
			$ids_string = implode(',', $carnet_ids);

			$colorMes = $this->BuscarColorPorMes($mes_actual);
			$colorData = $colorMes->fetch();

			$consulta = "SELECT
								a.alumno_id,
								a.alumno_identificacion,
								CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
									a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
								a.alumno_imagen,
								a.alumno_sedeid,
								(SELECT h.horario_nombre
									FROM asistencia_asignahorario ah
									INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
									WHERE ah.asignahorario_alumnoid = a.alumno_id
									LIMIT 1) as horario_nombre,
								ac.carnet_id,
								ac.carnet_mes,
								ac.carnet_anio,
								ac.carnet_fecha_emision,
								0 as es_reimpresion,
								:color_hex as color_hex,
								:mes_nombre as mes_nombre
							FROM alumno_carnet ac
							INNER JOIN sujeto_alumno a ON a.alumno_id = ac.carnet_alumnoid
							WHERE ac.carnet_id IN ($ids_string)
								AND ac.carnet_mes = :mes
								AND ac.carnet_anio = :anio
								AND a.alumno_estado = 'A'
								AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
							ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno";

			$datos = $this->ejecutarConsulta($consulta, [
				':mes' => $mes_actual,
				':anio' => $anio_actual,
				':color_hex' => $colorData['color_hex'] ?? '#CCCCCC',
				':mes_nombre' => $this->nombreMes($mes_actual)
			]);

			return $datos->fetchAll();
		}

		public function obtenerCarnetsAtrasadosPorIds($carnet_ids_string) {
			$carnet_ids = explode(',', $carnet_ids_string);
			$carnet_ids = array_map('intval', $carnet_ids);
			$carnet_ids = array_filter($carnet_ids);

			if(empty($carnet_ids)) {
				return [];
			}

			$ids_string = implode(',', $carnet_ids);

			$consulta = "SELECT
								a.alumno_id,
								a.alumno_identificacion,
								CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
									a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
								a.alumno_imagen,
								a.alumno_sedeid,
								(SELECT h.horario_nombre
									FROM asistencia_asignahorario ah
									INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
									WHERE ah.asignahorario_alumnoid = a.alumno_id
									LIMIT 1) as horario_nombre,
								ac.carnet_id,
								ac.carnet_mes,
								ac.carnet_anio,
								ac.carnet_fecha_emision,
								0 as es_reimpresion,
								COALESCE(cc.catcolor_hex, '#CCCCCC') as color_hex,
								'' as mes_nombre
							FROM alumno_carnet ac
							INNER JOIN sujeto_alumno a ON a.alumno_id = ac.carnet_alumnoid
							LEFT JOIN carnet_mes_color cmc ON cmc.mcolor_mes = ac.carnet_mes
								AND cmc.mcolor_activo = 1
							LEFT JOIN carnet_catcolor cc ON cc.catcolor_id = cmc.mcolor_catcolorid
							WHERE ac.carnet_id IN ($ids_string)
								AND a.alumno_estado = 'A'
								AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
							ORDER BY ac.carnet_anio, ac.carnet_mes, a.alumno_apellidopaterno, a.alumno_apellidomaterno";

			$datos = $this->ejecutarConsulta($consulta);
			return $datos->fetchAll();
		}

		public function prepararImpresionAtrasada() {
			$cedulas_raw = $_POST['cedulas'] ?? '';
			$mes = (int)($_POST['mes'] ?? 0);
			$anio = (int)($_POST['anio'] ?? 0);

			preg_match_all('/\d{6,20}/', $cedulas_raw, $matches);
			$cedulas = array_values(array_unique($matches[0] ?? []));

			if(empty($cedulas)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Sin cedulas",
					"texto" => "Ingrese al menos una cedula valida",
					"icono" => "warning"
				]);
			}

			if($mes < 1 || $mes > 12 || $anio < 2000 || $anio > ((int)date('Y') + 1)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Periodo invalido",
					"texto" => "Seleccione un mes y anio validos para la impresion atrasada",
					"icono" => "warning"
				]);
			}

			$placeholders = [];
			$parametros = [
				':mes' => $mes,
				':anio' => $anio
			];

			foreach($cedulas as $idx => $cedula) {
				$key = ':cedula_' . $idx;
				$placeholders[] = $key;
				$parametros[$key] = $cedula;
			}

			$consulta = "SELECT
							a.alumno_id,
							a.alumno_identificacion,
							CONCAT_WS(' ', a.alumno_primernombre, a.alumno_segundonombre, a.alumno_apellidopaterno, a.alumno_apellidomaterno) as nombre,
							a.alumno_estado,
							EXISTS (
								SELECT 1
								FROM asistencia_asignahorario ah
								WHERE ah.asignahorario_alumnoid = a.alumno_id
							) as tiene_horario,
							(
								EXISTS (
									SELECT 1
									FROM alumno_pago ap
									WHERE ap.pago_alumnoid = a.alumno_id
										AND MONTH(ap.pago_fecha) = :mes
										AND YEAR(ap.pago_fecha) = :anio
										AND ap.pago_estado NOT IN ('E', 'J')
										AND ap.pago_rubroid = 'RPE'
								)
								OR EXISTS (
									SELECT 1
									FROM alumno_pago_descuento apd
									WHERE apd.descuento_alumnoid = a.alumno_id
										AND apd.descuento_rubroid = 'DBC'
										AND apd.descuento_valor = 0
										AND apd.descuento_estado = 'S'
								)
							) as tiene_pago
						FROM sujeto_alumno a
						WHERE a.alumno_identificacion IN (" . implode(',', $placeholders) . ")
						ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno";

			$alumnos = $this->ejecutarConsulta($consulta, $parametros)->fetchAll();
			$encontradas = array_column($alumnos, 'alumno_identificacion');
			$no_encontradas = array_diff($cedulas, $encontradas);
			$errores = [];

			foreach($no_encontradas as $cedula) {
				$errores[] = "Cedula $cedula no encontrada";
			}

			$carnet_ids = [];
			$deMisSedes = $this->alumnosEnSedesDeLaSesion(array_column($alumnos, 'alumno_id'));

			foreach($alumnos as $alumno) {
				if(!in_array((int)$alumno['alumno_id'], $deMisSedes, true)) {
					$errores[] = $alumno['nombre'] . " no pertenece a sus sedes";
					continue;
				}

				if($alumno['alumno_estado'] !== 'A') {
					$errores[] = $alumno['nombre'] . " no esta activo";
					continue;
				}

				if((int)$alumno['tiene_horario'] !== 1) {
					$errores[] = $alumno['nombre'] . " no tiene horario asignado";
					continue;
				}

				if((int)$alumno['tiene_pago'] !== 1) {
					$errores[] = $alumno['nombre'] . " no tiene pago valido para " . $this->nombreMes($mes) . "/" . $anio;
					continue;
				}

				$consultaCarnet = "SELECT carnet_id
									FROM alumno_carnet
									WHERE carnet_alumnoid = :alumno_id
										AND carnet_mes = :mes
										AND carnet_anio = :anio
									ORDER BY carnet_id DESC
									LIMIT 1";

				$datosCarnet = $this->ejecutarConsulta($consultaCarnet, [
					':alumno_id' => $alumno['alumno_id'],
					':mes' => $mes,
					':anio' => $anio
				]);

				if($datosCarnet->rowCount() > 0) {
					$carnet = $datosCarnet->fetch();
					$carnet_ids[] = (int)$carnet['carnet_id'];
				} else {
					$nuevoCarnet = $this->crearCarnet($alumno['alumno_id'], $mes, $anio);
					$carnet_ids[] = (int)$nuevoCarnet['carnet_id'];
				}
			}

			$carnet_ids = array_values(array_unique(array_filter($carnet_ids)));

			if(empty($carnet_ids)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Sin carnets para imprimir",
					"texto" => empty($errores) ? "No se pudo preparar el lote atrasado" : implode("; ", $errores),
					"avisos" => array_values($errores),
					"icono" => "warning"
				]);
			}

			$ids_atrasada = implode(',', $carnet_ids);
			$_SESSION['carnet_impresion_atrasada_ids'] = $ids_atrasada;
			$token_atrasada = rtrim(strtr(base64_encode($ids_atrasada), '+/', '-_'), '=');
			$firma_atrasada = hash_hmac('sha256', $ids_atrasada, session_id());
			session_write_close();

			$texto = "Se prepararon " . count($carnet_ids) . " carnets atrasados para impresion";
			if(!empty($errores)) {
				$texto .= ". Avisos: " . implode("; ", $errores);
			}

			return json_encode([
				"tipo" => "redireccionar",
				"titulo" => "Impresion atrasada preparada",
				"texto" => $texto,
				"icono" => empty($errores) ? "success" : "warning",
				"avisos" => array_values($errores),
				"total" => count($carnet_ids),
				"url" => APP_URL . "carnetPDF/?modo=atrasada&atrasada=" . rawurlencode($token_atrasada) . "&firma=" . $firma_atrasada
			]);
		}

		public function prepararImpresionMensual() {
			/* 0 = todas las sedes del usuario; condicionSede acota siempre. */
			$sede = (int)($_POST['sede'] ?? 0);
			$carnets = $this->obtenerCarnetsPendientesMesActual($sede);

			if(empty($carnets)) {
				$carnets = $this->obtenerCarnetsNoImpresosMesActual($sede);
			}

			if(empty($carnets)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Sin carnets pendientes",
					"texto" => "No hay carnets pendientes de impresion este mes",
					"icono" => "info"
				]);
			}

			$carnet_ids = [];
			foreach($carnets as $carnet) {
				if(!empty($carnet['carnet_id'])) {
					$carnet_ids[] = (int)$carnet['carnet_id'];
				}
			}
			$carnet_ids = array_values(array_unique($carnet_ids));

			if(empty($carnet_ids)) {
				return json_encode([
					"tipo" => "simple",
					"titulo" => "Error",
					"texto" => "No se pudo preparar el lote de impresion",
					"icono" => "error"
				]);
			}

			$ids_mensual = implode(',', $carnet_ids);
			$_SESSION['carnet_impresion_mensual_ids'] = $ids_mensual;
			$token_mensual = rtrim(strtr(base64_encode($ids_mensual), '+/', '-_'), '=');
			$firma_mensual = hash_hmac('sha256', $ids_mensual, session_id());
			session_write_close();

			return json_encode([
				"tipo" => "redireccionar",
				"titulo" => "PDF preparado",
				"texto" => "Se prepararon " . count($carnet_ids) . " carnets para impresion",
				"icono" => "success",
				"url" => APP_URL . "carnetPDF/?modo=mensual&mensual=" . rawurlencode($token_mensual) . "&firma=" . $firma_mensual
			]);
		}

		public function obtenerCarnetsTodosUnificados($alumno_ids_reimpresion = '') {
			$mes_actual = date('n');
			$anio_actual = date('Y');
			$fecha_actual = date('Y-m-d');

			// Obtener color del mes
			$colorMes = $this->BuscarColorPorMes($mes_actual);
			$colorData = $colorMes->fetch();
			$color_hex = $colorData['color_hex'] ?? '#CCCCCC';
			$mes_nombre = $this->nombreMes($mes_actual);

			$carnetsFinales = [];

			// ========================================
			// PASO 1: Crear los carnets que aun NO existen (alumnos con pago/descuento del mes,
			// con horario, sin ningun registro de carnet este mes). Se crean SIN imprimir.
			// ========================================
			$consultaFaltantes = "SELECT a.alumno_id
								FROM sujeto_alumno a
								INNER JOIN alumno_pago ap ON ap.pago_alumnoid = a.alumno_id
								WHERE a.alumno_estado = 'A'
									AND ap.pago_estado NOT IN ('E', 'J')
									AND MONTH(ap.pago_fecha) = :mes
									AND YEAR(ap.pago_fecha) = :anio
									AND ap.pago_rubroid = 'RPE'
									AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
									AND NOT EXISTS (SELECT 1 FROM alumno_carnet ac WHERE ac.carnet_alumnoid = a.alumno_id AND ac.carnet_mes = :mes AND ac.carnet_anio = :anio)

								UNION

								SELECT a.alumno_id
								FROM sujeto_alumno a
								INNER JOIN alumno_pago_descuento apd ON apd.descuento_alumnoid = a.alumno_id
								WHERE a.alumno_estado = 'A'
									AND apd.descuento_rubroid = 'DBC'
									AND apd.descuento_valor = 0
									AND apd.descuento_estado = 'S'
									AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
									AND NOT EXISTS (SELECT 1 FROM alumno_carnet ac WHERE ac.carnet_alumnoid = a.alumno_id AND ac.carnet_mes = :mes AND ac.carnet_anio = :anio)";

			$faltantes = $this->ejecutarConsulta($consultaFaltantes, [
				':mes' => $mes_actual,
				':anio' => $anio_actual
			])->fetchAll();

			foreach($faltantes as $f) {
				$this->crearCarnet($f['alumno_id'], $mes_actual, $anio_actual);
			}

			// ========================================
			// PASO 2: Todos los carnets NO IMPRESOS del mes (recien creados + previos sin imprimir).
			// Trae el carnet_id real, de modo que registrarImpresion() pueda marcarlos como impresos.
			// ========================================
			$consultaNoImpresos = "SELECT
								a.alumno_id,
								a.alumno_identificacion,
								CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
									a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
								a.alumno_imagen,
								(SELECT h.horario_nombre
									FROM asistencia_asignahorario ah
									INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
									WHERE ah.asignahorario_alumnoid = a.alumno_id
									LIMIT 1) as horario_nombre,
								ac.carnet_id,
								ac.carnet_mes,
								ac.carnet_anio,
								ac.carnet_fecha_emision,
								0 as es_reimpresion,
								:color_hex as color_hex,
								:mes_nombre as mes_nombre
							FROM alumno_carnet ac
							INNER JOIN sujeto_alumno a ON a.alumno_id = ac.carnet_alumnoid
							WHERE ac.carnet_mes = :mes
								AND ac.carnet_anio = :anio
								AND ac.carnet_fecha_impresion IS NULL
								AND a.alumno_estado = 'A'
								AND EXISTS (SELECT 1 FROM asistencia_asignahorario ah WHERE ah.asignahorario_alumnoid = a.alumno_id)
							ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno";

			$datos = $this->ejecutarConsulta($consultaNoImpresos, [
				':mes' => $mes_actual,
				':anio' => $anio_actual,
				':color_hex' => $color_hex,
				':mes_nombre' => $mes_nombre
			]);
			$carnetsFinales = $datos->fetchAll();

			// ========================================
			// PARTE 2: REIMPRESIONES
			// ========================================
			if(!empty($alumno_ids_reimpresion)) {
				// ✅ INTENTAR DECODIFICAR BASE64 PRIMERO
				$ids_decodificados = base64_decode($alumno_ids_reimpresion, true);
				if($ids_decodificados !== false && strpos($ids_decodificados, ',') !== false) {
					// Era base64, usar la versión decodificada
					$alumno_ids_reimpresion = $ids_decodificados;
				}

				$alumno_ids = explode(',', $alumno_ids_reimpresion);
				$alumno_ids = array_map('intval', $alumno_ids);
				$alumno_ids = array_filter($alumno_ids);

				if(!empty($alumno_ids)) {
					$ids_string = implode(',', $alumno_ids);

					$consultaReimpresion = "SELECT
											a.alumno_id,
											a.alumno_identificacion,
											CONCAT(a.alumno_primernombre, ' ', a.alumno_segundonombre, ' ',
												a.alumno_apellidopaterno, ' ', a.alumno_apellidomaterno) as alumno_nombre,
											a.alumno_imagen,
											h.horario_nombre,
											ac.carnet_id,
											ac.carnet_mes,
											ac.carnet_anio,
											ac.carnet_fecha_emision,
											1 as es_reimpresion,
											:color_hex as color_hex,
											:mes_nombre as mes_nombre
										FROM sujeto_alumno a
										INNER JOIN asistencia_asignahorario ah ON ah.asignahorario_alumnoid = a.alumno_id
										INNER JOIN asistencia_horario h ON h.horario_id = ah.asignahorario_horarioid
										INNER JOIN alumno_carnet ac ON ac.carnet_alumnoid = a.alumno_id
										WHERE a.alumno_id IN ($ids_string)
										AND ac.carnet_mes = :mes
										AND ac.carnet_anio = :anio
										ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno";

					$parametrosReimpresion = [
						':mes' => $mes_actual,
						':anio' => $anio_actual,
						':color_hex' => $color_hex,
						':mes_nombre' => $mes_nombre
					];

					$datosReimpresion = $this->ejecutarConsulta($consultaReimpresion, $parametrosReimpresion);
					$carnetsReimpresion = $datosReimpresion->fetchAll();

					$carnetsFinales = array_merge($carnetsFinales, $carnetsReimpresion);
				}
			}

			return $carnetsFinales;
		}

		/**
		 * Obtener resumen de carnets a imprimir
		 * @param array $carnets Array de carnets obtenido de obtenerCarnetsTodosUnificados()
		 * @return array Resumen con totales
		 */
		public function obtenerResumenImpresion($carnets) {
			$nuevos = 0;
			$reimpresiones = 0;

			foreach($carnets as $carnet) {
				if($carnet['es_reimpresion'] == 1) {
					$reimpresiones++;
				} else {
					$nuevos++;
				}
			}

			return [
				'total' => count($carnets),
				'nuevos' => $nuevos,
				'reimpresiones' => $reimpresiones
			];
		}
    }
