<?php

namespace app\controllers;

use app\models\mainModel;
use PDO;

/**
 * Registro público de representante + alumno desde el enlace de inscripción.
 *
 * Todo lo que entra aquí viene de internet abierta: la sede la impone el token
 * (nunca el POST), el CSRF se verifica antes que nada y los consentimientos
 * LOPDP son obligatorios.
 */
class registroController extends mainModel
{
    /**
     * Directorio de fotos del sistema administrativo, para que la imagen sea
     * visible desde el panel sin copiarla dos veces. Lo define config/app.php
     * porque depende de dónde quede instalado este proyecto respecto al principal.
     */
    private function directorioFotos(): string
    {
        /* El respaldo apuntaba a la carpeta de otra escuela: si la constante
           faltaba, las fotos de menores acababan en un proyecto ajeno sin
           que nadie lo notara. Ahora cae dentro del propio ecosistema. */
        return defined('DIR_FOTOS_ALUMNO')
            ? DIR_FOTOS_ALUMNO
            : __DIR__ . "/../../../ds_futbol/app/views/imagenes/fotos/alumno/";
    }

    /**
     * Registra representante + alumno. $sedeId proviene del token validado.
     */
    public function registrar(int $sedeId): string
    {
        /*----------  CSRF  ----------*/
        if (
            !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            return $this->respuesta('simple', 'Acceso denegado', 'La solicitud no es válida. Recargue la página e intente nuevamente.', 'error');
        }

        /*----------  Consentimientos LOPDP  ----------*/
        $acepta_datos  = $_POST['acepta_datos'] ?? '';
        $acepta_imagen = $_POST['acepta_imagen'] ?? '';

        if ($acepta_datos !== 'S') {
            return $this->respuesta('simple', 'Autorización requerida', 'Debe aceptar el consentimiento para el tratamiento de datos personales conforme a la LOPDP.', 'error');
        }

        if ($acepta_imagen !== 'S') {
            return $this->respuesta('simple', 'Autorización requerida', 'Debe autorizar el uso de la imagen del alumno/a para completar la inscripción.', 'error');
        }

        /*----------  Representante  ----------*/
        $repre_tipoidentificacion = $this->limpiarCadena($_POST['repre_tipoidentificacion'] ?? '');
        $repre_identificacion     = $this->limpiarCadena($_POST['repre_identificacion'] ?? '');
        $repre_primernombre       = $this->limpiarCadena($_POST['repre_primernombre'] ?? '');
        $repre_segundonombre      = $this->limpiarCadena($_POST['repre_segundonombre'] ?? '');
        $repre_apellidopaterno    = $this->limpiarCadena($_POST['repre_apellidopaterno'] ?? '');
        $repre_apellidomaterno    = $this->limpiarCadena($_POST['repre_apellidomaterno'] ?? '');
        $repre_direccion          = $this->limpiarCadena($_POST['repre_direccion'] ?? '');
        $repre_correo             = $this->limpiarCadena($_POST['repre_correo'] ?? '');
        $repre_celular            = $this->limpiarCadena($_POST['repre_celular'] ?? '');
        $repre_parentesco         = $this->limpiarCadena($_POST['repre_parentesco'] ?? '');
        $repre_sexo               = $this->limpiarCadena($_POST['repre_sexo'] ?? '');

        if (
            $repre_identificacion === '' || $repre_primernombre === '' ||
            $repre_apellidopaterno === '' || $repre_direccion === '' ||
            $repre_correo === '' || $repre_celular === '' || $repre_parentesco === ''
        ) {
            return $this->respuesta('simple', 'Campos incompletos', 'Complete todos los campos obligatorios del representante.', 'error');
        }

        if (!$this->valorEnCatalogo(1, $repre_tipoidentificacion)) {
            return $this->respuesta('simple', 'Dato inválido', 'El tipo de identificación del representante no es válido.', 'error');
        }

        if (!$this->valorEnCatalogo(4, $repre_parentesco)) {
            return $this->respuesta('simple', 'Dato inválido', 'El parentesco seleccionado no es válido.', 'error');
        }

        if ($repre_tipoidentificacion === 'CED' && !$this->validarCedula($repre_identificacion)) {
            return $this->respuesta('simple', 'Cédula inválida', 'La cédula del representante no es válida según el algoritmo ecuatoriano.', 'error');
        }

        if (!filter_var($repre_correo, FILTER_VALIDATE_EMAIL)) {
            return $this->respuesta('simple', 'Correo inválido', 'El correo electrónico del representante no tiene un formato válido.', 'error');
        }

        if ($this->verificarDatos("09[0-9]{8}", $repre_celular)) {
            return $this->respuesta('simple', 'Celular inválido', 'El número celular debe tener 10 dígitos y comenzar con 09.', 'error');
        }

        if ($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,50}", $repre_primernombre)) {
            return $this->respuesta('simple', 'Nombre inválido', 'El nombre del representante contiene caracteres no permitidos.', 'error');
        }

        if ($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,50}", $repre_apellidopaterno)) {
            return $this->respuesta('simple', 'Apellido inválido', 'El apellido del representante contiene caracteres no permitidos.', 'error');
        }

        // ¿El representante ya está en el sistema?
        $checkRepre = $this->ejecutarConsulta(
            "SELECT repre_id FROM alumno_representante WHERE repre_identificacion = :id",
            [':id' => $repre_identificacion]
        );

        /*----------  Alumno  ----------*/
        $alumno_tipoidentificacion = $this->limpiarCadena($_POST['alumno_tipoidentificacion'] ?? '');
        $alumno_identificacion     = $this->limpiarCadena($_POST['alumno_identificacion'] ?? '');
        $alumno_primernombre       = $this->limpiarCadena($_POST['alumno_primernombre'] ?? '');
        $alumno_segundonombre      = $this->limpiarCadena($_POST['alumno_segundonombre'] ?? '');
        $alumno_apellidopaterno    = $this->limpiarCadena($_POST['alumno_apellidopaterno'] ?? '');
        $alumno_apellidomaterno    = $this->limpiarCadena($_POST['alumno_apellidomaterno'] ?? '');
        $alumno_fechanacimiento    = $this->limpiarCadena($_POST['alumno_fechanacimiento'] ?? '');
        $alumno_genero             = $this->limpiarCadena($_POST['alumno_genero'] ?? '');
        $alumno_direccion          = $this->limpiarCadena($_POST['alumno_direccion'] ?? '');
        $alumno_hermanos           = $this->limpiarCadena($_POST['alumno_hermanos'] ?? 'N');
        $alumno_nacionalidadid     = $this->limpiarCadena($_POST['alumno_nacionalidadid'] ?? 'ECU');
        $alumno_estado             = 'A';
        $alumno_fechaingreso       = date('Y-m-d');

        if (
            $alumno_identificacion === '' || $alumno_primernombre === '' ||
            $alumno_apellidopaterno === '' || $alumno_fechanacimiento === '' ||
            $alumno_genero === ''
        ) {
            return $this->respuesta('simple', 'Campos incompletos', 'Complete todos los campos obligatorios del alumno.', 'error');
        }

        if (!$this->valorEnCatalogo(1, $alumno_tipoidentificacion)) {
            return $this->respuesta('simple', 'Dato inválido', 'El tipo de identificación del alumno no es válido.', 'error');
        }

        if (!$this->valorEnCatalogo(2, $alumno_nacionalidadid)) {
            return $this->respuesta('simple', 'Dato inválido', 'La nacionalidad seleccionada no es válida.', 'error');
        }

        if (!in_array($alumno_genero, ['M', 'F'], true)) {
            return $this->respuesta('simple', 'Dato inválido', 'Seleccione el género del alumno.', 'error');
        }

        if ($alumno_tipoidentificacion === 'CED' && !$this->validarCedula($alumno_identificacion)) {
            return $this->respuesta('simple', 'Cédula inválida', 'La cédula del alumno no es válida según el algoritmo ecuatoriano.', 'error');
        }

        if ($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,150}", $alumno_primernombre)) {
            return $this->respuesta('simple', 'Nombre inválido', 'El nombre del alumno contiene caracteres no permitidos.', 'error');
        }

        if ($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ ]{2,150}", $alumno_apellidopaterno)) {
            return $this->respuesta('simple', 'Apellido inválido', 'El apellido del alumno contiene caracteres no permitidos.', 'error');
        }

        $checkAlumno = $this->ejecutarConsulta(
            "SELECT alumno_id FROM sujeto_alumno WHERE alumno_identificacion = :id",
            [':id' => $alumno_identificacion]
        );
        if ($checkAlumno->rowCount() > 0) {
            return $this->respuesta('simple', 'Alumno ya registrado', 'La identificación del alumno ya se encuentra en el sistema. Comuníquese con la escuela.', 'error');
        }

        // Fecha de nacimiento: real, no futura y dentro de un rango razonable
        $fechaNac = date_create_from_format('Y-m-d', $alumno_fechanacimiento);
        if (!$fechaNac || $fechaNac->format('Y-m-d') !== $alumno_fechanacimiento) {
            return $this->respuesta('simple', 'Fecha inválida', 'La fecha de nacimiento del alumno no es válida.', 'error');
        }

        $edad = $fechaNac->diff(new \DateTime())->y;
        if ($fechaNac > new \DateTime() || $edad > 100) {
            return $this->respuesta('simple', 'Fecha inválida', 'La fecha de nacimiento del alumno no es válida.', 'error');
        }

        /*----------  Foto del alumno  ----------*/
        $foto = '';

        if (
            isset($_FILES['alumno_foto']['error']) &&
            $_FILES['alumno_foto']['error'] === UPLOAD_ERR_OK &&
            $_FILES['alumno_foto']['name'] !== '' &&
            $_FILES['alumno_foto']['size'] > 0
        ) {
            $mime = mime_content_type($_FILES['alumno_foto']['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                return $this->respuesta('simple', 'Formato no permitido', 'La foto debe ser JPG o PNG.', 'error');
            }

            if (($_FILES['alumno_foto']['size'] / 1024) > 4000) {
                return $this->respuesta('simple', 'Archivo muy grande', 'La foto no debe superar los 4 MB.', 'error');
            }

            // El nombre lo arma el servidor: nada del cliente llega al filesystem
            $nombreFoto  = preg_replace('/[^0-9A-Za-z]/', '', $alumno_identificacion) . '_' . rand(0, 100);
            $nombreFoto .= ($mime === 'image/jpeg') ? '.jpg' : '.png';

            $img_dir  = $this->directorioFotos();
            $accesible = is_dir($img_dir) || @mkdir($img_dir, 0755, true) || is_dir($img_dir);

            if ($accesible && $this->resizeImageGD($_FILES['alumno_foto']['tmp_name'], 800, 600, $img_dir . $nombreFoto)) {

                $foto = $nombreFoto;

            } else {

                /* El panel administrativo lee las fotos de un único directorio.
                   Si no se pudo escribir ahí, la foto se conserva localmente para
                   no perder lo que subió el representante, pero NO se guarda su
                   nombre en la base: registrar una imagen que el panel no puede
                   mostrar deja fichas con la foto rota y nadie se entera.
                   La inscripción continúa; el alumno queda registrado sin foto. */
                $respaldo = __DIR__ . "/../views/fotos/";

                if (!is_dir($respaldo)) {
                    @mkdir($respaldo, 0755, true);
                }

                @$this->resizeImageGD($_FILES['alumno_foto']['tmp_name'], 800, 600, $respaldo . $nombreFoto);

                error_log(
                    "[inscripcion] No se pudo escribir la foto en el directorio del panel: {$img_dir} "
                    . "(revise DIR_FOTOS_ALUMNO en config/app.php y los permisos). "
                    . "Copia de respaldo en {$respaldo}{$nombreFoto}. "
                    . "El alumno {$alumno_identificacion} quedo registrado SIN foto."
                );
            }
        }

        /*----------  Guardar  ----------*/
        try {
            $repreid = null;

            if ($checkRepre->rowCount() > 0) {
                $row     = $checkRepre->fetch(PDO::FETCH_ASSOC);
                $repreid = $row['repre_id'];
            } else {
                $representante_reg = [
                    ['campo_nombre' => 'repre_tipoidentificacion', 'campo_marcador' => ':TipoId',         'campo_valor' => $repre_tipoidentificacion],
                    ['campo_nombre' => 'repre_identificacion',     'campo_marcador' => ':Identificacion', 'campo_valor' => $repre_identificacion],
                    ['campo_nombre' => 'repre_primernombre',       'campo_marcador' => ':Nombre1',        'campo_valor' => $repre_primernombre],
                    ['campo_nombre' => 'repre_segundonombre',      'campo_marcador' => ':Nombre2',        'campo_valor' => $repre_segundonombre],
                    ['campo_nombre' => 'repre_apellidopaterno',    'campo_marcador' => ':Apellido1',      'campo_valor' => $repre_apellidopaterno],
                    ['campo_nombre' => 'repre_apellidomaterno',    'campo_marcador' => ':Apellido2',      'campo_valor' => $repre_apellidomaterno],
                    ['campo_nombre' => 'repre_direccion',          'campo_marcador' => ':Direccion',      'campo_valor' => $repre_direccion],
                    ['campo_nombre' => 'repre_correo',             'campo_marcador' => ':Correo',         'campo_valor' => $repre_correo],
                    ['campo_nombre' => 'repre_celular',            'campo_marcador' => ':Celular',        'campo_valor' => $repre_celular],
                    ['campo_nombre' => 'repre_sexo',               'campo_marcador' => ':Sexo',           'campo_valor' => $repre_sexo],
                    ['campo_nombre' => 'repre_parentesco',         'campo_marcador' => ':Parentesco',     'campo_valor' => $repre_parentesco],
                    ['campo_nombre' => 'repre_estado',             'campo_marcador' => ':Estado',         'campo_valor' => 'A'],
                    /* 'N' a propósito: repre_firmado significa "firmó el formulario
                       LOPDP en papel" (ver formularioLPPDF + estadofirmado en el
                       panel). La aceptación en línea NO es esa firma y se registra
                       aparte, en alumno_consentimiento. */
                    ['campo_nombre' => 'repre_firmado',            'campo_marcador' => ':Firmado',        'campo_valor' => 'N'],
                ];

                $this->guardarDatos('alumno_representante', $representante_reg);

                $getRepre = $this->ejecutarConsulta(
                    "SELECT repre_id FROM alumno_representante WHERE repre_identificacion = :id",
                    [':id' => $repre_identificacion]
                );
                if ($getRepre->rowCount() === 1) {
                    $row     = $getRepre->fetch(PDO::FETCH_ASSOC);
                    $repreid = $row['repre_id'];
                }
            }

            if (!$repreid) {
                return $this->respuesta('simple', 'Error interno', 'No se pudo registrar al representante. Comuníquese con la escuela.', 'error');
            }

            $alumno_reg = [
                ['campo_nombre' => 'alumno_repreid',            'campo_marcador' => ':Repreid',        'campo_valor' => $repreid],
                ['campo_nombre' => 'alumno_sedeid',             'campo_marcador' => ':Sedeid',         'campo_valor' => $sedeId],
                ['campo_nombre' => 'alumno_nacionalidadid',     'campo_marcador' => ':Nacionalidad',   'campo_valor' => $alumno_nacionalidadid],
                ['campo_nombre' => 'alumno_tipoidentificacion', 'campo_marcador' => ':TipoId',         'campo_valor' => $alumno_tipoidentificacion],
                ['campo_nombre' => 'alumno_identificacion',     'campo_marcador' => ':Identificacion', 'campo_valor' => $alumno_identificacion],
                ['campo_nombre' => 'alumno_primernombre',       'campo_marcador' => ':Nombre1',        'campo_valor' => $alumno_primernombre],
                ['campo_nombre' => 'alumno_segundonombre',      'campo_marcador' => ':Nombre2',        'campo_valor' => $alumno_segundonombre],
                ['campo_nombre' => 'alumno_apellidopaterno',    'campo_marcador' => ':Apellido1',      'campo_valor' => $alumno_apellidopaterno],
                ['campo_nombre' => 'alumno_apellidomaterno',    'campo_marcador' => ':Apellido2',      'campo_valor' => $alumno_apellidomaterno],
                ['campo_nombre' => 'alumno_direccion',          'campo_marcador' => ':Direccion',      'campo_valor' => $alumno_direccion],
                ['campo_nombre' => 'alumno_fechanacimiento',    'campo_marcador' => ':FechaNac',       'campo_valor' => $alumno_fechanacimiento],
                ['campo_nombre' => 'alumno_fechaingreso',       'campo_marcador' => ':FechaIngreso',   'campo_valor' => $alumno_fechaingreso],
                ['campo_nombre' => 'alumno_genero',             'campo_marcador' => ':Genero',         'campo_valor' => $alumno_genero],
                ['campo_nombre' => 'alumno_hermanos',           'campo_marcador' => ':Hermanos',       'campo_valor' => $alumno_hermanos],
                ['campo_nombre' => 'alumno_estado',             'campo_marcador' => ':Estado',         'campo_valor' => $alumno_estado],
                ['campo_nombre' => 'alumno_imagen',             'campo_marcador' => ':Foto',           'campo_valor' => $foto],
                ['campo_nombre' => 'alumno_numcamiseta',        'campo_marcador' => ':Camiseta',       'campo_valor' => 0],
                /* alumno_observacion no existe en el esquema de este
                   proyecto: venia de la escuela de la que se copio el
                   formulario y habria hecho fallar la insercion entera.
                   El origen queda registrado igualmente en
                   alumno_consentimiento.consent_origen. */
            ];

            $resultado = $this->guardarDatos('sujeto_alumno', $alumno_reg);

            if ($resultado->rowCount() === 1) {

                /* Constancia LOPDP. guardarDatos() abre una conexión nueva en
                   cada llamada, así que lastInsertId() no sirve: se recupera el
                   alumno por su identificación, que ya se verificó única. */
                $getAlumno = $this->ejecutarConsulta(
                    "SELECT alumno_id FROM sujeto_alumno WHERE alumno_identificacion = :id",
                    [':id' => $alumno_identificacion]
                );

                if ($getAlumno->rowCount() === 1) {
                    $filaAlumno = $getAlumno->fetch(PDO::FETCH_ASSOC);
                    $this->registrarConsentimientos((int) $filaAlumno['alumno_id'], (int) $repreid);
                }

                // Rotar el CSRF para que el mismo formulario no se reenvíe
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                return $this->respuesta(
                    'exito',
                    'Registro exitoso',
                    'El alumno ' . $alumno_primernombre . ' ' . $alumno_apellidopaterno . ' ha sido registrado correctamente.',
                    'success'
                );
            }

            return $this->respuesta('simple', 'Error', 'No se pudo completar el registro. Intente nuevamente.', 'error');

        } catch (\Exception $e) {
            error_log("[inscripcion] " . $e->getMessage());
            return $this->respuesta('simple', 'Error del servidor', 'Ocurrió un error inesperado. Intente más tarde.', 'error');
        }
    }

    /*----------  Consentimientos LOPDP  ----------*/

    /**
     * Textos de los consentimientos, con el nombre de la escuela ya sustituido.
     * Es la misma fuente que se muestra en pantalla y que se firma por hash.
     */
    public function textosConsentimiento(): array
    {
        static $textos = null;

        if ($textos === null) {
            $textos = require __DIR__ . "/../../config/consentimientos.php";

            foreach ($textos as $tipo => $bloque) {
                $textos[$tipo]['texto'] = str_replace('{ESCUELA}', ESCUELA_NOMBRE, $bloque['texto']);
            }
        }

        return $textos;
    }

    /**
     * Huella del texto exacto que aceptó el representante. Si mañana cambia la
     * redacción, el hash cambia y las aceptaciones viejas siguen siendo
     * atribuibles a su versión.
     */
    private function hashTexto(string $tipo): string
    {
        $textos = $this->textosConsentimiento();

        return isset($textos[$tipo]) ? hash('sha256', $textos[$tipo]['texto']) : '';
    }

    /**
     * Deja una fila por cada consentimiento otorgado en el formulario público.
     */
    private function registrarConsentimientos(int $alumnoId, int $repreId): void
    {
        // REMOTE_ADDR y no X-Forwarded-For: la cabecera la controla el cliente
        $ip    = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $ahora = date('Y-m-d H:i:s');

        foreach (['DATOS', 'IMAGEN'] as $tipo) {
            $this->ejecutarConsulta(
                "INSERT INTO alumno_consentimiento
                    (consent_alumnoid, consent_repreid, consent_tipo, consent_otorgado,
                     consent_origen, consent_usuario, consent_fecha, consent_ip, consent_textohash)
                 VALUES
                    (:alumno, :repre, :tipo, 'S', 'FORMULARIO', NULL, :fecha, :ip, :hash)",
                [
                    ':alumno' => $alumnoId,
                    ':repre'  => $repreId,
                    ':tipo'   => $tipo,
                    ':fecha'  => $ahora,
                    ':ip'     => $ip,
                    ':hash'   => $this->hashTexto($tipo),
                ]
            );
        }
    }

    /*----------  Catálogos  ----------*/

    /**
     * Opciones activas de un catálogo (general_tabla_catalogo).
     */
    public function listarCatalogo(int $tablaId): array
    {
        $consulta = $this->ejecutarConsulta(
            "SELECT catalogo_valor, catalogo_descripcion
               FROM general_tabla_catalogo
              WHERE catalogo_tablaid = :tabla AND catalogo_estado = 'A'
              ORDER BY catalogo_descripcion",
            [':tabla' => $tablaId]
        );

        return $consulta->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica que un valor pertenezca al catálogo indicado y esté activo.
     * Evita que el POST inyecte códigos que la escuela no maneja.
     */
    private function valorEnCatalogo(int $tablaId, string $valor): bool
    {
        if ($valor === '') {
            return false;
        }

        $consulta = $this->ejecutarConsulta(
            "SELECT catalogo_valor
               FROM general_tabla_catalogo
              WHERE catalogo_tablaid = :tabla AND catalogo_valor = :valor AND catalogo_estado = 'A'",
            [':tabla' => $tablaId, ':valor' => $valor]
        );

        return $consulta->rowCount() > 0;
    }

    /**
     * Nombre de la sede fijada por el token.
     */
    public function nombreSede(int $sedeId): string
    {
        $consulta = $this->ejecutarConsulta(
            "SELECT sede_nombre FROM general_sede WHERE sede_id = :id",
            [':id' => $sedeId]
        );

        $sede = $consulta->fetch(PDO::FETCH_ASSOC);

        return $sede ? $sede['sede_nombre'] : 'Sede #' . $sedeId;
    }

    private function respuesta(string $tipo, string $titulo, string $texto, string $icono): string
    {
        return json_encode([
            'tipo'   => $tipo,
            'titulo' => $titulo,
            'texto'  => $texto,
            'icono'  => $icono
        ], JSON_UNESCAPED_UNICODE);
    }
}
