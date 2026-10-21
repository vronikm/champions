<?php
	
	namespace app\models;

	use PDO;
	use PDOException;

	if(file_exists(__DIR__."/../../config/server.php")){
		require_once __DIR__."/../../config/server.php";
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
            /* charset=utf8 era utf8mb3: tres bytes, y la base esta en utf8mb4.
               Era el ultimo sitio del ecosistema con el juego viejo. */
            $conexion = new PDO("mysql:host={$this->server};dbname={$this->db};charset=utf8mb4",
                                 $this->user, $this->pass,
                                 defined("DS_DB_INIT_COMANDO")
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
     * Escapa una cadena para que sea INOFENSIVA EN HTML.
     *
     * Copia exacta de la de Basketball, y el aviso es el mismo: esto NO
     * protege una consulta. htmlspecialchars() vuelve inocuo lo que se va a
     * pintar en una página; en un SQL sólo parece protector cuando el valor
     * va entre comillas, y en un contexto numérico no hace nada en absoluto.
     *
     * Para las consultas, parámetros:
     * ejecutarConsulta($sql, [':x' => $valor]).
     */
    public function limpiarHtml($cadena)
    {
        if (!isset($cadena)) return '';

        $cadena = htmlspecialchars(trim($cadena), ENT_QUOTES, 'UTF-8');

        /* Cinturón encima del escapado, no la defensa principal: las listas
           negras no se sostienen solas. */
        $cadena = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $cadena);

        return $cadena;
    }

    /** Nombre histórico de limpiarHtml(). El código nuevo usa el otro. */
    public function limpiarCadena($cadena)
    {
        return $this->limpiarHtml($cadena);
    }

    protected function verificarDatos($filtro, $cadena)
    {
        return !preg_match("/^$filtro$/", $cadena);
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

        switch ($imageType) {
            case IMAGETYPE_JPEG:
                imagejpeg($newImage, $outputFile, 80);
                break;
            case IMAGETYPE_PNG:
                imagepng($newImage, $outputFile, 8);
                break;
            case IMAGETYPE_GIF:
                imagegif($newImage, $outputFile);
                break;
        }

        imagedestroy($image);
        imagedestroy($newImage);

        return true;
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

}