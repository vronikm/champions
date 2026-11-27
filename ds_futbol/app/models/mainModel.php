<?php

namespace app\models;

use PDO;
use PDOException;

if (file_exists(__DIR__ . "/../../config/server.php")) {
    require_once __DIR__ . "/../../config/server.php";
}

/* Las reglas de acceso del ecosistema (sedes_de_la_sesion y compañía). La
   aplicación ya las carga desde session_start.php, pero un controlador no
   debe depender de eso: sin ellas, el primero que filtra por sede muere con
   «Call to undefined function». Sólo define funciones; cargarlo dos veces
   no hace nada. */
require_once __DIR__ . "/../../../ds_core/inc/seguridad.php";

class mainModel
{
    private $server = DB_SERVER;
    private $db     = DB_NAME;
    private $user   = DB_USER;
    private $pass   = DB_PASS;

    protected function conectar()
    {
        try {
            /* utf8mb4, no utf8: en MySQL «utf8» es el alias de utf8mb3, de
               tres bytes por caracter. Las tablas ya estan en utf8mb4
               (migracion 041); si la conexion sigue en el juego viejo, lo
               que necesite cuatro bytes se pierde aqui, no alli. */
            $conexion = new PDO("mysql:host={$this->server};dbname={$this->db};charset=utf8mb4",
                                 $this->user, $this->pass, defined("DS_DB_INIT_COMANDO")
                                     ? [PDO::MYSQL_ATTR_INIT_COMMAND => DS_DB_INIT_COMANDO] : []);
            $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conexion;
        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
        }
    }

    protected function ejecutarConsulta($consulta, $parametros = [])
    {
        $sql = $this->conectar()->prepare($consulta);
        $sql->execute($parametros);
        return $sql;
    }

    /**
     * Devuelve $html sólo si el rol puede ejecutar $accion sobre $vista.
     *
     * Sirve para no ofrecer botones que el servidor va a rechazar. NO es
     * el control de acceso: ése vive en ajax_guard y se aplica igual
     * aunque alguien reconstruya la petición a mano.
     *
     * Uso dentro de una plantilla concatenada:
     *   <td>'.$this->siPuede('eliminar','alumnoList','<form>…</form>').'</td>
     */
    protected function siPuede(string $accion, string $vista, string $html): string
    {
        return puede($accion, $vista) ? $html : '';
    }

    /**
     * La miniatura del alumno en una tabla (.ds-ficha__imagen): su foto si
     * la tiene y el archivo existe; si no, sus iniciales. media_url()
     * devuelve la genérica cuando falta el archivo: eso cuenta como sin foto.
     * loading="lazy": en un listado de cientos sólo se piden las visibles.
     */
    protected function avatarAlumno($archivo, string $inicial): string
    {
        $url = trim((string)$archivo) !== '' ? media_url('alumno', (string)$archivo) : '';
        if (strpos($url, 'media.php') !== false) {
            return '<img class="ds-ficha__imagen" src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy" decoding="async">';
        }
        return '<span class="ds-ficha__imagen ds-alumnos__inicial" aria-hidden="true">' . htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8') . '</span>';
    }

    /**
     * Escapa una cadena para que sea INOFENSIVA EN HTML.
     *
     * QUE HACE Y QUE NO HACE
     *
     * Hace: htmlspecialchars() con ENT_QUOTES, recorta los extremos y
     * quita cualquier <script>. Con eso, lo que llegue del usuario se puede
     * pintar en una página sin que ejecute nada.
     *
     * NO hace: proteger una consulta. Ni una sola de las cosas que hace
     * sirve para SQL, y conviene decirlo porque el nombre viejo
     * —limpiarCadena()— sugiere una limpieza general que no existe.
     *
     * POR QUE IMPORTA LA DISTINCION
     *
     * ENT_QUOTES convierte la comilla simple en &#039;, y eso hacía que un
     * valor interpolado ENTRE COMILLAS dentro de un SQL pareciera seguro.
     * Lo parecía de rebote, no por diseño: en un contexto numérico
     * —WHERE id = $valor, sin comillas— no protege absolutamente nada,
     * porque «1 OR 1=1» no lleva ningún carácter que esta función toque.
     *
     * Para las consultas hay una sola respuesta: parámetros con
     * ejecutarConsulta($sql, [':x' => $valor]).
     *
     * OJO CON LO QUE SE GUARDA
     *
     * Como escapa ANTES de guardar, en la base quedan entidades: «O'Neill»
     * se almacena como «O&#039;Neill». Al pintarlo en HTML se ve bien; al
     * mandarlo a un PDF o a un CSV, no. No se cambia aquí porque el padrón
     * ya está escrito así y arreglarlo es una migración, no un retoque.
     */
    public function limpiarHtml($cadena)
    {
        if (!isset($cadena)) return '';

        $cadena = htmlspecialchars(trim($cadena), ENT_QUOTES, 'UTF-8');

        /* Las listas negras no se sostienen solas: esto es un cinturón
           encima del escapado, no la defensa principal. */
        $cadena = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $cadena);

        return $cadena;
    }

    /**
     * Nombre histórico de limpiarHtml(). Se conserva porque lo llaman 502
     * sitios y renombrarlos todos es un cambio enorme cuyo único fallo
     * posible —olvidar uno— es un error fatal en producción.
     *
     * El código nuevo debería usar limpiarHtml(), que dice lo que hace.
     */
    public function limpiarCadena($cadena)
    {
        return $this->limpiarHtml($cadena);
    }

    protected function verificarDatos($filtro, $cadena)
    {
        return !preg_match("/^$filtro$/", $cadena);
    }

    /**
     * Sede en la que se registra un pago.
     *
     * La sede del pago se CONGELA al crearlo y no sigue despues los
     * traslados del alumno: un pago es un hecho ocurrido en un sitio, igual
     * que ocurrio en una fecha. Sin esto, mover un alumno de sede reescribia
     * retroactivamente los ingresos de las dos sedes y un cierre de mes ya
     * impreso dejaba de cuadrar. Ver ds_core/database/044.
     *
     * En el momento de cobrar, la sede actual del alumno ES la sede correcta;
     * lo que no vale es volver a preguntarla meses despues.
     *
     * Devuelve 0 si el alumno no existe. La columna es NOT NULL con clave
     * foranea, asi que un 0 hace fallar la insercion en vez de guardar un
     * pago sin sede: es deliberado.
     */
    protected function sedeDelAlumno($alumnoId): int
    {
        $sql = $this->conectar()->prepare(
            "SELECT alumno_sedeid FROM sujeto_alumno WHERE alumno_id = :alumno"
        );
        $sql->execute([":alumno" => (int) $alumnoId]);

        return (int) $sql->fetchColumn();
    }
    protected function guardarDatos($tabla, $datos)
    {
        $campos   = implode(',', array_column($datos, 'campo_nombre'));
        $marcas   = implode(',', array_column($datos, 'campo_marcador'));
        $query    = "INSERT INTO $tabla ($campos) VALUES ($marcas)";
        $sql      = $this->conectar()->prepare($query);

        foreach ($datos as $campo) {
            $sql->bindParam($campo["campo_marcador"], $campo["campo_valor"]);
        }

        $sql->execute();
        return $sql;
    }

    public function seleccionarDatos($tipo, $tabla, $campo, $id = null)
    {
        $tabla = preg_replace('/[^a-zA-Z0-9_]/', '', $tabla); // evitar inyección por nombre de tabla

        if ($tipo === "Unico") {
            $sql = $this->conectar()->prepare("SELECT * FROM $tabla WHERE $campo = :ID");
            $sql->bindParam(":ID", $id);
        } elseif ($tipo === "Normal") {
            $sql = $this->conectar()->prepare("SELECT $campo FROM $tabla");
        } else {
            throw new \Exception("Tipo de consulta no válido.");
        }

        $sql->execute();
        return $sql;
    }

    protected function actualizarDatos($tabla, $datos, $condicion)
    {
        $set = [];
        foreach ($datos as $campo) {
            $set[] = "{$campo['campo_nombre']} = {$campo['campo_marcador']}";
        }

        $query = "UPDATE $tabla SET " . implode(", ", $set) . " WHERE {$condicion['condicion_campo']} = {$condicion['condicion_marcador']}";
        $sql   = $this->conectar()->prepare($query);

        foreach ($datos as $campo) {
            $sql->bindParam($campo['campo_marcador'], $campo['campo_valor']);
        }

        $sql->bindParam($condicion['condicion_marcador'], $condicion['condicion_valor']);
        $sql->execute();
        return $sql;
    }

    protected function eliminarRegistro($tabla, $campo, $id)
    {
        $sql = $this->conectar()->prepare("DELETE FROM $tabla WHERE $campo = :id");
        $sql->bindParam(":id", $id);
        $sql->execute();
        return $sql;
    }

    public function resizeImageGD($file, $maxWidth, $maxHeight, $outputFile)
    {
        [$originalWidth, $originalHeight, $imageType] = getimagesize($file);

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($file);
                break;
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($file);
                break;
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($file);
                break;
            default:
                return false;
        }

        /* Foto vertical de celular: los píxeles llegan apaisados y el giro va
           en el EXIF, que GD ignora y descarta. Se aplica aquí, antes de
           redimensionar (ds_core/inc/imagen_orientacion.php). */
        if ($imageType === IMAGETYPE_JPEG && $image instanceof \GdImage) {
            require_once __DIR__ . "/../../../ds_core/inc/imagen_orientacion.php";
            $image = ds_imagen_orientar($image, ds_imagen_orientacion_exif($file));
            $originalWidth  = imagesx($image);
            $originalHeight = imagesy($image);
        }

        $aspectRatio = $originalWidth / $originalHeight;
        if ($maxWidth / $maxHeight > $aspectRatio) {
            $newWidth = round($maxHeight * $aspectRatio);
            $newHeight = $maxHeight;
        } else {
            $newHeight = round($maxWidth / $aspectRatio);
            $newWidth = $maxWidth;
        }

        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

        $saved = false;
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $saved = imagejpeg($newImage, $outputFile, 80);
                break;
            case IMAGETYPE_PNG:
                $saved = imagepng($newImage, $outputFile, 8);
                break;
            case IMAGETYPE_GIF:
                $saved = imagegif($newImage, $outputFile);
                break;
        }

        imagedestroy($image);
        imagedestroy($newImage);

        return $saved;
    }

    /* funcion para validar el numero de cedula*/	
		public function validarCedula($cedula) {
			// Eliminar caracteres no numéricos
			$cedula = preg_replace('/[^0-9]/', '', $cedula);

			// Validar longitud (solo cédula 10 dígitos)
			if (strlen($cedula) != 10) {
				return false;
			}

			// Validar que no sea una secuencia repetida (ej. 0000000000, 1111111111, etc.)
			if (preg_match('/^(.)\1{9}$/', $cedula)) {
				return false;
			}

			// Validar provincia
			$provincia = intval(substr($cedula, 0, 2));
			if ($provincia < 1 || $provincia > 24) {
				return false;
			}

			// Validar tercer dígito (naturales: 0–5)
			$tercerDigito = intval(substr($cedula, 2, 1));
			if ($tercerDigito > 5) {
				return false;
			}

			// Algoritmo de validación
			$suma = 0;
			for ($i = 0; $i < 9; $i++) {
				$digito = intval($cedula[$i]);
				if ($i % 2 == 0) { // posiciones impares (0,2,4...)
					$digito *= 2;
					if ($digito > 9) {
						$digito -= 9;
					}
				}
				$suma += $digito;
			}

			$digitoVerificador = 10 - ($suma % 10);
			if ($digitoVerificador == 10) {
				$digitoVerificador = 0;
			}

			return $digitoVerificador == intval($cedula[9]);
		}


		/*----------  Las sedes que puede ver el usuario de la sesión  ----------*/
		/*
		| null = todas; si no, las suyas ([] = ninguna). La regla es UNA para
		| todo el ecosistema: sedes_de_la_sesion() en ds_core/inc/seguridad.php
		| (superadministrador o «todas las sedes» en Core › Usuarios = todas).
		| Antes aquí los roles 1 y 2 veían todo por estar escritos en el código.
		|
		| ANTES TAMBIÉN: el listado convertía «sede 0» en «alumno_sedeid <> 0»,
		| o sea, TODAS las sedes, para cualquiera. Bastaba con enviar
		| alumno_sedeid=0 para ver los alumnos —menores, con sus datos— de las
		| otras sedes. Ahora la sede se acota aquí, siempre.
		*/
		/**
		 * ¿Está aplicada la migración 096 (sujeto_alumno.alumno_origenid)? Un
		 * alumno inscrito también en la sede con código (PERSONALIZADO) tiene
		 * un segundo registro que apunta al original. Una vez por petición.
		 */
		public function hayVinculoAlumnos(): bool{
			static $hay = null;
			if ($hay === null) {
				try {
					$hay = (int)$this->ejecutarConsulta("SELECT COUNT(*) FROM information_schema.COLUMNS
							WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sujeto_alumno' AND COLUMN_NAME = 'alumno_origenid'")->fetchColumn() === 1;
				} catch (\Throwable $e) { $hay = false; }
			}
			return $hay;
		}

		protected function sedesDeLaSesion(): ?array{
			return sedes_de_la_sesion();
		}

		/** Para las vistas: ¿ofrecer «Todas» en el filtro de sede? (ve todas o más de una). */
		public function ofrecerTodasLasSedes(): bool{
			$permitidas = sedes_de_la_sesion();
			return $permitidas === null || count($permitidas) > 1;
		}

		/** Condición SQL de sede (0 = todas las permitidas). Añade su parámetro a $parametros. */
		protected function condicionSede(string $columna, $sede, array &$parametros): string{
			$sede = (int)$sede;
			$permitidas = $this->sedesDeLaSesion();
			if ($permitidas === null) {
				if ($sede <= 0) { return "1 = 1"; }
				$parametros[':sede'] = $sede;
				return "$columna = :sede";
			}
			if (!$permitidas) { return "1 = 0"; }
			if ($sede > 0) {
				if (!in_array($sede, $permitidas, true)) { return "1 = 0"; }
				$parametros[':sede'] = $sede;
				return "$columna = :sede";
			}
			/* Son enteros sacados de la base: se pueden escribir en la consulta. */
			return "$columna IN (" . implode(',', $permitidas) . ")";
		}


		/** Estado de alumno para un filtro: A (activos, por defecto), I (inactivos) o T (todos). Nunca los eliminados. */
		protected function condicionEstadoAlumno(string $columna, $estado): string{
			return match ((string)$estado) {
				'I'     => "$columna = 'I'",
				'T'     => "$columna IN ('A','I')",
				default => "$columna = 'A'",
			};
		}

		/*----------  Pensiones que debe desde su último pago de pensión  ----------*/
		/*
		| La MISMA regla que pensionesPendientes(): cada mes cumplido desde la
		| fecha del último pago de pensión, hasta el último día del mes en
		| curso, es una pensión adeudada. Al sumar meses, el día se recorta al
		| final del mes, como DATE_ADD de MySQL (31 de enero + 1 mes = 28 de
		| febrero); el +1 month de PHP lo pasaría a marzo.
		|
		| Vive aquí, y no en pagosController, para que la lista de pagos y la
		| toma de asistencia digan lo mismo sobre quién está en mora.
		|
		| $hasta: contar hasta el mes de esa fecha en vez del mes en curso
		| (lo usa mesesPensionInactivo()).
		*/
		protected function mesesPensionAdeudados(?string $ultimoPago, ?string $hasta = null): int{
			if (!$ultimoPago || str_starts_with($ultimoPago, '0000')) { return 0; }
			$base  = new \DateTime($ultimoPago);
			$tope  = new \DateTime(date('Y-m-t', $hasta ? strtotime($hasta) : time()));
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

		/*
		| SQL del último día que vino cada alumno, para la subconsulta de
		| asistencia de los listados (Pagos y Cobranza) sobre
		| insights_v_asistencia_dia: presente o atraso, lo que tomaron los
		| profesores. Una marca con fecha futura es un error de carga y no
		| cuenta.
		*/
		const SQL_ULTIMA_ASISTENCIA = "MAX(CASE WHEN dia_marca IN ('P','A') AND dia_fecha <= CURDATE() THEN dia_fecha END)";

		/*
		| Pensiones de un alumno INACTIVO: no se le generan desde que dejó
		| de venir, pero si asistió después de su último pago de pensión,
		| los meses hasta su última asistencia los entrenó sin pagar. Sin
		| asistencia registrada, cero. Misma cuenta que mesesPensionAdeudados
		| y que la cobranza de inactivos (cobranzaController).
		|
		| Si nunca pagó una pensión, desde el mes de su ingreso
		| (baseSinPagos), pasando $ingreso.
		*/
		protected function mesesPensionInactivo(?string $ultimoPago, ?string $ultimaAsistencia, ?string $ingreso = null): int{
			if (!$ultimaAsistencia || str_starts_with($ultimaAsistencia, '0000')) { return 0; }
			if (!$ultimoPago || str_starts_with($ultimoPago, '0000')) { $ultimoPago = $this->baseSinPagos($ingreso); }
			return $this->mesesPensionAdeudados($ultimoPago, $ultimaAsistencia);
		}

		/*
		| Inactivo que asistió y NUNCA pagó una pensión: se le cuentan desde
		| el mes de su ingreso. No antes del primer día con asistencia
		| registrada en el sistema: quien entró antes pagaba en el sistema
		| anterior y esos meses no están aquí. Sin fecha de ingreso, desde
		| ese primer día.
		|
		| Devuelve el día 1 del mes ANTERIOR al de inicio: la base que
		| esperan mesesPensionAdeudados() y pensionesPendientes(), que
		| cuentan los meses DESPUÉS del último pagado. La cobranza hace la
		| misma cuenta en SQL (sqlBaseSinPagos).
		*/
		protected function baseSinPagos(?string $ingreso): ?string{
			$inicio = $this->inicioAsistencia();
			if ($inicio === null) { return null; }
			$desde = ($ingreso && $ingreso >= '1900-01-01' && $ingreso > $inicio) ? $ingreso : $inicio;
			return date('Y-m-01', strtotime(date('Y-m-01', strtotime($desde)) . ' -1 month'));
		}

		/** SQL de baseSinPagos() sobre alumno_fechaingreso; :inicio = inicioAsistencia(). */
		protected static function sqlBaseSinPagos(string $col = 'alumno_fechaingreso'): string{
			return "DATE_SUB(DATE_FORMAT(GREATEST(CASE WHEN $col >= '1900-01-01' THEN $col ELSE :inicio END, :inicio2), '%Y-%m-01'), INTERVAL 1 MONTH)";
		}

		/** El primer día con asistencia registrada en el sistema (Y-m-d), o null si no hay. */
		public function inicioAsistencia(): ?string{
			static $inicio = false;
			if ($inicio === false) {
				$inicio = $this->ejecutarConsulta("SELECT MIN(dia_fecha) FROM insights_v_asistencia_dia")->fetchColumn() ?: null;
			}
			return $inicio;
		}

		/*
		| El mes que cubre un pago según su pago_periodo («Junio / 2026»), como
		| fecha del día 1; NULL si el texto no se entiende («Junioo / 2026»).
		|
		| Un pago hecho el 25 de mayo por la pensión de junio tiene pago_fecha
		| en mayo: contar desde la fecha le cobraría junio otra vez. El último
		| mes pagado es el mayor entre el de la fecha y el del periodo
		| (sqlUltimoMesPagado). Desde 2026-11-19 vale para activos e
		| inactivos, y nunca cuentan los pagos eliminados (pago_estado 'E'):
		| quien la use filtra pago_estado <> 'E'.
		*/
		protected static function sqlPeriodoPago(string $col = 'pago_periodo'): string{
			return "STR_TO_DATE(CONCAT(TRIM(SUBSTRING_INDEX($col, '/', -1)), '-',"
			     . " NULLIF(FIELD(LOWER(TRIM(SUBSTRING_INDEX($col, '/', 1))), 'enero', 'febrero', 'marzo', 'abril', 'mayo',"
			     . " 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'), 0), '-01'), '%Y-%c-%d')";
		}

		/*
		| Agregado: el último mes pagado (fecha o periodo, el mayor) de un
		| grupo de pagos de pensión.
		|
		| El periodo sólo cuenta hasta 3 meses después del mes del pago: los
		| adelantos reales son de 1 a 3 meses, y más allá es un año mal
		| escrito («Diciembre / 2026» pagado en enero de 2026 por diciembre
		| de 2025), que dejaría al alumno «al día» casi un año. En ese caso
		| vale la fecha del pago.
		*/
		protected static function sqlUltimoMesPagado(): string{
			$periodo = self::sqlPeriodoPago();
			return "GREATEST(MAX(pago_fecha), COALESCE(MAX(CASE WHEN $periodo < DATE_FORMAT(pago_fecha, '%Y-%m-01') + INTERVAL 4 MONTH"
			     . " THEN $periodo END), MAX(pago_fecha)))";
		}

		/*
		| Estado de cuenta de varios alumnos, para las fichas (alumnoProfile y
		| «Representa a» de representanteProfile). Por alumno_id:
		|
		|   descuento_rubroid, descuento_valor, descuento_nombre, sede_pension
		|       su beneficio vigente (descuento_valor es la pensión que PAGA)
		|   pension_fecha, pension_periodo, pension_valor
		|       su último pago de pensión
		|   pago_fecha, pago_valor, pago_rubroid, pago_concepto, pago_periodo
		|       su último pago de cualquier concepto
		|       (pagos efectivos: completos, o pendientes con algo abonado;
		|       nunca los eliminados)
		|   ultima_asis   el último día que vino (SQL_ULTIMA_ASISTENCIA)
		|   saldo, meses_deuda, deuda
		|       lo que debe, con la MISMA cuenta que Pagos (pagosController::
		|       listarAlumnosPagos), Cobranza y pagosNew: saldos pendientes +
		|       pensiones desde el último mes pagado (si nunca pagó, desde su
		|       ingreso); al inactivo, sólo hasta su última asistencia; la
		|       beca del 100 % no debe pensiones.
		*/
		public function resumenCuentaAlumnos(array $ids): array{
			$ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($i) => $i > 0)));
			if (!$ids) { return []; }
			$en = implode(',', $ids);   /* enteros: se pueden escribir en la consulta */

			$ultimoPago = static fn(string $campo, string $rubro = '') =>
				"(SELECT $campo FROM alumno_pago P
				   WHERE P.pago_alumnoid = A.alumno_id AND P.pago_estado IN ('C','P') AND P.pago_valor > 0"
				   . ($rubro !== '' ? " AND P.pago_rubroid = '$rubro'" : '') . "
				   ORDER BY P.pago_fecha DESC, P.pago_id DESC LIMIT 1)";
			$filas = $this->ejecutarConsulta("SELECT A.alumno_id, A.alumno_estado, A.alumno_fechaingreso, S.sede_pension,
						D.descuento_rubroid, D.descuento_valor, D.descuento_fecha, DC.catalogo_descripcion AS descuento_nombre,
						" . $ultimoPago('P.pago_fecha', 'RPE') . " AS pension_fecha,
						" . $ultimoPago('P.pago_periodo', 'RPE') . " AS pension_periodo,
						" . $ultimoPago('P.pago_valor', 'RPE') . " AS pension_valor,
						" . $ultimoPago('P.pago_fecha') . " AS pago_fecha,
						" . $ultimoPago('P.pago_valor') . " AS pago_valor,
						" . $ultimoPago('P.pago_rubroid') . " AS pago_rubroid,
						" . $ultimoPago('P.pago_periodo') . " AS pago_periodo,
						(SELECT " . self::SQL_ULTIMA_ASISTENCIA . " FROM insights_v_asistencia_dia WHERE dia_alumnoid = A.alumno_id) AS ultima_asis,
						(SELECT " . self::sqlUltimoMesPagado() . " FROM alumno_pago
						  WHERE pago_alumnoid = A.alumno_id AND pago_rubroid = 'RPE' AND pago_estado <> 'E') AS ultimo_mes,
						(SELECT IFNULL(SUM(pago_saldo), 0) FROM alumno_pago
						  WHERE pago_alumnoid = A.alumno_id AND pago_estado = 'P') AS saldo
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
					LEFT JOIN alumno_pago_descuento D ON D.descuento_alumnoid = A.alumno_id AND D.descuento_estado = 'S'
					LEFT JOIN general_tabla_catalogo DC ON DC.catalogo_valor = D.descuento_rubroid AND DC.catalogo_tablaid = 7
					WHERE A.alumno_id IN ($en)")->fetchAll(\PDO::FETCH_ASSOC);

			/* Nombre del concepto del último pago (catálogo de rubros). */
			$rubros = [];
			foreach ($this->ejecutarConsulta("SELECT C.catalogo_valor, C.catalogo_descripcion FROM general_tabla_catalogo C
					INNER JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid AND T.tabla_nombre = 'rubros'")->fetchAll() as $f) {
				$rubros[$f['catalogo_valor']] = $f['catalogo_descripcion'];
			}

			$r = [];
			foreach ($filas as $al) {
				$al['pago_concepto'] = $rubros[$al['pago_rubroid'] ?? ''] ?? (string)($al['pago_rubroid'] ?? '');
				$becado  = $al['descuento_rubroid'] === 'DBC';
				$pension = $al['descuento_valor'] !== null ? (float)$al['descuento_valor'] : (float)$al['sede_pension'];
				$meses   = $becado ? 0
				         : ($al['alumno_estado'] === 'A'
				            ? $this->mesesPensionAdeudados($al['ultimo_mes'] ?: $this->baseSinPagos($al['alumno_fechaingreso']))
				            : ($al['alumno_estado'] === 'I'
				               ? $this->mesesPensionInactivo($al['ultimo_mes'], $al['ultima_asis'], $al['alumno_fechaingreso'])
				               : 0));
				$al['meses_deuda'] = $meses;
				$al['deuda']       = round((float)$al['saldo'] + $meses * $pension, 2);
				unset($al['alumno_estado'], $al['alumno_fechaingreso']);   /* ya los trae quien llama */
				$r[(int)$al['alumno_id']] = $al;
			}
			return $r;
		}

		/** El último día que vino un alumno (Y-m-d), o null si nunca. */
		public function ultimaAsistencia(int $alumno): ?string{
			$f = $this->ejecutarConsulta(
				"SELECT " . self::SQL_ULTIMA_ASISTENCIA . " FROM insights_v_asistencia_dia WHERE dia_alumnoid = :a",
				[':a' => $alumno])->fetchColumn();
			return $f ?: null;
		}

		/*
		| Celda «Asistencia» de los listados de Pagos y Cobranza: la fecha
		| del último día que vino, hace cuánto (en rojo pasados 30 días) y
		| debajo el porcentaje sobre los días registrados. data-order es la
		| fecha ISO, así la columna se ordena por ella.
		*/
		protected function tdUltimaAsistencia(int $dias, int $asistidos, ?string $ultima, string $clases = 'ds-ficha__dato'): string{
			$ultima = ($ultima && !str_starts_with($ultima, '0000')) ? $ultima : '';
			$pct    = $dias > 0 ? (int)round($asistidos / $dias * 100) : null;

			if ($pct === null) {
				$celda = '<span class="text-body-secondary">Sin registros</span>';
			} else {
				if ($ultima !== '') {
					$hace = (int)(new \DateTime($ultima))->diff(new \DateTime('today'))->format('%r%a');
					$textoHace = $hace <= 0 ? 'hoy' : ($hace === 1 ? 'ayer' : 'hace ' . $hace . ' días');
					$celda = '<span class="ds-pagos__ultima-asis">' . date('d/m/Y', strtotime($ultima)) . '</span>'
					       . '<span class="d-block small ' . ($hace > 30 ? 'text-danger' : 'text-body-secondary') . '">' . $textoHace . '</span>';
				} else {
					$celda = '<span class="text-body-secondary">Nunca asistió</span>';
				}
				$celda .= '<span class="ds-pagos__asis ds-pagos__asis-linea d-flex small mt-1" title="Presentes y atrasos sobre '
				        . $dias . ($dias === 1 ? ' día registrado' : ' días registrados') . '">'
				        . '<span class="ds-pagos__barra" aria-hidden="true"><span style="width:' . $pct . '%" class="'
				        . ($pct >= 75 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger')) . '"></span></span>'
				        . $pct . ' % de ' . $dias . ($dias === 1 ? ' día' : ' días') . '</span>';
			}

			return '<td class="' . $clases . '" data-etiqueta="Asistencia" data-order="' . $ultima . '">' . $celda . '</td>';
		}
}
