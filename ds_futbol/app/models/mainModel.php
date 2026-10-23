<?php

namespace app\models;

use PDO;
use PDOException;

if (file_exists(__DIR__ . "/../../config/server.php")) {
    require_once __DIR__ . "/../../config/server.php";
}

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
		| null = todas (los roles de administración, 1 y 2, como en
		| listarSedebusqueda). Para el resto, las suyas (seguridad_usuario_sede).
		|
		| ANTES: el listado convertía «sede 0» en «alumno_sedeid <> 0», o sea,
		| TODAS las sedes, para cualquiera. La opción «Todas» sólo se le
		| ofrecía a administración, pero el servidor no lo comprobaba: bastaba
		| con enviar alumno_sedeid=0 para ver los alumnos —menores, con sus
		| datos— de las otras sedes. Ahora la sede se acota aquí, siempre.
		*/
		protected function sedesDeLaSesion(): ?array{
			$rol = (int)($_SESSION['rol'] ?? 0);
			if ($rol === 1 || $rol === 2) {
				return null;
			}
			$ids = $this->ejecutarConsulta("SELECT US.usuariosede_sedeid
					FROM seguridad_usuario_sede US
					INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
					WHERE U.usuario_usuario = :usuario", [':usuario' => (string)($_SESSION['usuario'] ?? '')])->fetchAll(\PDO::FETCH_COLUMN);
			return array_values(array_unique(array_map('intval', $ids)));
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
}
