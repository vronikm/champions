<?php
/*
|--------------------------------------------------------------------------
| Alumnos adultos (migración 086)
|--------------------------------------------------------------------------
| Un alumno mayor de edad no tiene representante: él mismo recibe el
| recibo, paga y se le factura. Para que recibos, facturas, cobranza y
| consentimientos funcionen sin tocar su lógica —todos parten de
| alumno_representante—, el alta crea con los mismos datos del alumno una
| ficha de TITULAR: parentesco 4TT y repre_factura = 'S'.
|
| La usan los dos caminos de alta, para que validen y graben lo mismo:
|   · el enlace público   ds_form/adulto.php  (registroAdultoController)
|   · el panel            Fútbol › Nuevo alumno adulto (alumnoAdultoController)
|
| Sólo en sedes con general_sede.sede_adultos = 'S' (Core › Sedes).
*/

if (!function_exists('adulto_registrar')) {

    /* define() y no const: const no se admite dentro de un bloque condicional. */
    /** Parentesco de la ficha de titular (inactivo en el catálogo: ver 086). */
    define('ADULTO_PARENTESCO', '4TT');
    /** Edad mínima para inscribirse sin representante. */
    define('ADULTO_EDAD_MINIMA', 18);

    /** Error de validación con un mensaje que se le puede mostrar a quien llena el formulario. */
    class AdultoError extends \RuntimeException
    {
        public function __construct(public string $titulo, string $mensaje)
        {
            parent::__construct($mensaje);
        }
    }

    /** ¿Existe la columna sede_adultos? (antes de la migración 086, no). */
    function adulto_hay_columna(PDO $pdo): bool
    {
        static $hay = null;
        return $hay ??= (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'general_sede'
                AND COLUMN_NAME = 'sede_adultos'"
        )->fetchColumn() > 0;
    }

    /** Nombre de la sede si está activa y admite alumnos adultos; null si no. */
    function adulto_sede_admite(PDO $pdo, int $sedeId): ?string
    {
        if ($sedeId <= 0 || !adulto_hay_columna($pdo)) {
            return null;
        }
        $st = $pdo->prepare(
            "SELECT sede_nombre FROM general_sede
              WHERE sede_id = :id AND sede_adultos = 'S'
                AND COALESCE(sede_estado, 'A') <> 'I'"
        );
        try {
            $st->execute([':id' => $sedeId]);
        } catch (\PDOException $e) {
            /* Sin sede_estado (antes de la 078): basta la bandera. */
            $st = $pdo->prepare("SELECT sede_nombre FROM general_sede WHERE sede_id = :id AND sede_adultos = 'S'");
            $st->execute([':id' => $sedeId]);
        }
        $nombre = $st->fetchColumn();
        return $nombre === false ? null : (string)$nombre;
    }

    /** Cédula ecuatoriana (módulo 10), la misma regla de mainModel::validarCedula. */
    function adulto_cedula_valida(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula) || preg_match('/^(.)\1{9}$/', $cedula)) {
            return false;
        }
        $provincia = (int)substr($cedula, 0, 2);
        if ($provincia < 1 || $provincia > 24 || (int)$cedula[2] > 5) {
            return false;
        }
        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $d = (int)$cedula[$i];
            if ($i % 2 === 0) {
                $d *= 2;
                if ($d > 9) { $d -= 9; }
            }
            $suma += $d;
        }
        $verificador = (10 - $suma % 10) % 10;
        return $verificador === (int)$cedula[9];
    }

    /** Mismo escapado que limpiarCadena(): así se guarda el resto del padrón. */
    function adulto_limpiar(mixed $valor): string
    {
        $v = htmlspecialchars(trim((string)$valor), ENT_QUOTES, 'UTF-8');
        return (string)preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $v);
    }

    function adulto_en_catalogo(PDO $pdo, int $tabla, string $valor): bool
    {
        $st = $pdo->prepare(
            "SELECT 1 FROM general_tabla_catalogo
              WHERE catalogo_tablaid = :t AND catalogo_valor = :v AND catalogo_estado = 'A'"
        );
        $st->execute([':t' => $tabla, ':v' => $valor]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Valida lo enviado y devuelve los datos limpios. Lanza AdultoError.
     *
     * Campos: tipoidentificacion (CED|PAS), identificacion, primernombre,
     * segundonombre, apellidopaterno, apellidomaterno, fechanacimiento,
     * genero (M|F), nacionalidadid, direccion, correo, celular.
     */
    function adulto_validar(PDO $pdo, array $in): array
    {
        $d = [];
        foreach (['tipoidentificacion', 'identificacion', 'primernombre', 'segundonombre',
                  'apellidopaterno', 'apellidomaterno', 'fechanacimiento', 'genero',
                  'nacionalidadid', 'direccion', 'correo', 'celular'] as $campo) {
            $d[$campo] = adulto_limpiar($in[$campo] ?? '');
        }
        $d['tipoidentificacion'] = strtoupper($d['tipoidentificacion']);
        $d['identificacion']     = strtoupper(preg_replace('/\s+/', '', $d['identificacion']));
        $d['correo']             = strtolower($d['correo']);
        $d['celular']            = preg_replace('/\D+/', '', $d['celular']);
        $d['nacionalidadid']     = $d['nacionalidadid'] !== '' ? $d['nacionalidadid'] : 'ECU';

        if ($d['identificacion'] === '' || $d['primernombre'] === '' || $d['apellidopaterno'] === ''
            || $d['fechanacimiento'] === '' || $d['genero'] === '' || $d['direccion'] === ''
            || $d['correo'] === '' || $d['celular'] === '') {
            throw new AdultoError('Campos incompletos', 'Complete todos los campos obligatorios.');
        }

        /* Cédula o pasaporte: con eso se emite la factura (código SRI 05 o 06). */
        if (!in_array($d['tipoidentificacion'], ['CED', 'PAS'], true)) {
            throw new AdultoError('Dato inválido', 'El tipo de identificación debe ser cédula o pasaporte.');
        }
        if ($d['tipoidentificacion'] === 'CED' && !adulto_cedula_valida($d['identificacion'])) {
            throw new AdultoError('Cédula inválida', 'La cédula no es válida. Revise los 10 dígitos.');
        }
        /* 13: el largo de repre_identificacion, donde también se guarda. */
        if ($d['tipoidentificacion'] === 'PAS' && !preg_match('/^[A-Z0-9]{5,13}$/', $d['identificacion'])) {
            throw new AdultoError('Pasaporte inválido', 'El pasaporte debe tener entre 5 y 13 letras o números, sin espacios ni guiones.');
        }

        $nombre = '/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ ]{2,50}$/u';
        $opcional = '/^[a-zA-ZáéíóúÁÉÍÓÚñÑüÜ ]{0,50}$/u';
        if (!preg_match($nombre, $d['primernombre']) || !preg_match($nombre, $d['apellidopaterno'])) {
            throw new AdultoError('Nombre inválido', 'Nombre y apellido: sólo letras, de 2 a 50 caracteres.');
        }
        if (!preg_match($opcional, $d['segundonombre']) || !preg_match($opcional, $d['apellidomaterno'])) {
            throw new AdultoError('Nombre inválido', 'Segundo nombre y apellido materno: sólo letras, hasta 50 caracteres.');
        }

        $fecha = date_create_from_format('!Y-m-d', $d['fechanacimiento']);
        if (!$fecha || $fecha->format('Y-m-d') !== $d['fechanacimiento']) {
            throw new AdultoError('Fecha inválida', 'La fecha de nacimiento no es válida.');
        }
        $hoy = new DateTime('today');
        $edad = $fecha->diff($hoy)->y;
        if ($fecha > $hoy || $edad > 100) {
            throw new AdultoError('Fecha inválida', 'La fecha de nacimiento no es válida.');
        }
        if ($edad < ADULTO_EDAD_MINIMA) {
            throw new AdultoError('Menor de edad',
                'Esta inscripción es sólo para mayores de ' . ADULTO_EDAD_MINIMA . ' años. '
                . 'Un menor se inscribe con el enlace para representantes.');
        }

        if (!in_array($d['genero'], ['M', 'F'], true)) {
            throw new AdultoError('Dato inválido', 'Seleccione el sexo.');
        }
        if (!adulto_en_catalogo($pdo, 2, $d['nacionalidadid'])) {
            throw new AdultoError('Dato inválido', 'La nacionalidad seleccionada no es válida.');
        }
        if (mb_strlen($d['direccion']) > 400) {
            throw new AdultoError('Dirección muy larga', 'La dirección no debe superar los 400 caracteres.');
        }
        if (strlen($d['correo']) > 50 || !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
            throw new AdultoError('Correo inválido', 'Revise el correo electrónico: a él llegan sus recibos y facturas.');
        }
        if (!preg_match('/^09\d{8}$/', $d['celular'])) {
            throw new AdultoError('Celular inválido', 'El celular debe tener 10 dígitos y empezar con 09.');
        }

        $st = $pdo->prepare("SELECT COUNT(*) FROM sujeto_alumno WHERE alumno_identificacion = :id");
        $st->execute([':id' => $d['identificacion']]);
        if ((int)$st->fetchColumn() > 0) {
            throw new AdultoError('Alumno ya registrado',
                'Esa identificación ya está registrada como alumno. Comuníquese con la escuela.');
        }

        return $d;
    }

    /**
     * Da de alta al alumno adulto y su ficha de titular, en una transacción.
     *
     * Si ya existe un representante con esa identificación (p. ej. es padre
     * de otro alumno) se reutiliza tal como está: es la misma persona, y sus
     * datos de contacto y de facturación ya son los suyos.
     *
     * @return array{alumno_id:int, repre_id:int, nombre:string, titular_nuevo:bool}
     */
    function adulto_registrar(PDO $pdo, array $d, int $sedeId, string $foto = ''): array
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $tieneObservacion = (int)$pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sujeto_alumno'
                AND COLUMN_NAME = 'alumno_observacion'"
        )->fetchColumn() > 0;

        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("SELECT repre_id FROM alumno_representante WHERE repre_identificacion = :id LIMIT 1");
            $st->execute([':id' => $d['identificacion']]);
            $repreId = (int)$st->fetchColumn();
            $titularNuevo = $repreId === 0;

            if ($titularNuevo) {
                $pdo->prepare(
                    "INSERT INTO alumno_representante
                        (repre_tipoidentificacion, repre_identificacion, repre_primernombre,
                         repre_segundonombre, repre_apellidopaterno, repre_apellidomaterno,
                         repre_direccion, repre_correo, repre_celular, repre_sexo,
                         repre_parentesco, repre_estado, repre_firmado, repre_factura)
                     VALUES (:tipo, :id, :n1, :n2, :a1, :a2, :dir, :correo, :cel, :sexo,
                             :parentesco, 'A', 'N', 'S')"
                )->execute([
                    ':tipo' => $d['tipoidentificacion'], ':id' => $d['identificacion'],
                    ':n1' => $d['primernombre'], ':n2' => $d['segundonombre'],
                    ':a1' => $d['apellidopaterno'], ':a2' => $d['apellidomaterno'],
                    ':dir' => $d['direccion'], ':correo' => $d['correo'], ':cel' => $d['celular'],
                    ':sexo' => $d['genero'], ':parentesco' => ADULTO_PARENTESCO,
                ]);
                $repreId = (int)$pdo->lastInsertId();
            }

            $campos = "alumno_repreid, alumno_sedeid, alumno_nacionalidadid, alumno_tipoidentificacion,
                       alumno_identificacion, alumno_primernombre, alumno_segundonombre,
                       alumno_apellidopaterno, alumno_apellidomaterno, alumno_direccion,
                       alumno_fechanacimiento, alumno_fechaingreso, alumno_genero, alumno_hermanos,
                       alumno_estado, alumno_imagen, alumno_numcamiseta";
            $marcas = ":repre, :sede, :nac, :tipo, :id, :n1, :n2, :a1, :a2, :dir, :fnac, CURDATE(),
                       :genero, 'N', 'A', :foto, 0";
            $valores = [
                ':repre' => $repreId, ':sede' => $sedeId, ':nac' => $d['nacionalidadid'],
                ':tipo' => $d['tipoidentificacion'], ':id' => $d['identificacion'],
                ':n1' => $d['primernombre'], ':n2' => $d['segundonombre'],
                ':a1' => $d['apellidopaterno'], ':a2' => $d['apellidomaterno'],
                ':dir' => $d['direccion'], ':fnac' => $d['fechanacimiento'],
                ':genero' => $d['genero'], ':foto' => $foto,
            ];
            if ($tieneObservacion) {
                $campos .= ", alumno_observacion";
                $marcas .= ", 'Alumno adulto (titular)'";
            }
            $pdo->prepare("INSERT INTO sujeto_alumno ($campos) VALUES ($marcas)")->execute($valores);
            $alumnoId = (int)$pdo->lastInsertId();

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        return [
            'alumno_id'     => $alumnoId,
            'repre_id'      => $repreId,
            'nombre'        => html_entity_decode($d['primernombre'] . ' ' . $d['apellidopaterno'], ENT_QUOTES, 'UTF-8'),
            'titular_nuevo' => $titularNuevo,
        ];
    }
}
