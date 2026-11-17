<?php

namespace app\controllers;

use app\models\mainModel;

require_once __DIR__ . "/../../../ds_core/inc/alumno_adulto.php";

/**
 * Registro público de un ALUMNO ADULTO desde ds_form/adulto.php (migración 086).
 *
 * Aparte de registroController, que no se toca: aquí no hay representante.
 * El alumno es su propio titular —recibos y facturas a su nombre— y la
 * validación y el alta las hace ds_core/inc/alumno_adulto.php, lo mismo que
 * el alta manual del panel. La sede la impone el token (nunca el POST).
 */
class registroAdultoController extends mainModel
{
    private function directorioFotos(): string
    {
        return defined('DIR_FOTOS_ALUMNO')
            ? DIR_FOTOS_ALUMNO
            : __DIR__ . "/../../../ds_futbol/app/views/imagenes/fotos/alumno/";
    }

    /** Nombre de la sede si sigue activa y admite adultos; null si no. */
    public function sedeAdmite(int $sedeId): ?string
    {
        return adulto_sede_admite($this->conectar(), $sedeId);
    }

    public function listarNacionalidades(): array
    {
        return $this->ejecutarConsulta(
            "SELECT catalogo_valor, catalogo_descripcion FROM general_tabla_catalogo
              WHERE catalogo_tablaid = 2 AND catalogo_estado = 'A' ORDER BY catalogo_descripcion"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /** Textos para adultos, con el nombre de la escuela ya puesto. Lo que se muestra es lo que se firma. */
    public function textosConsentimiento(): array
    {
        static $textos = null;
        if ($textos === null) {
            $textos = require __DIR__ . '/../../config/consentimientos_adulto.php';
            foreach ($textos as $tipo => $b) {
                $textos[$tipo]['texto'] = str_replace('{ESCUELA}', ESCUELA_NOMBRE, $b['texto']);
            }
        }
        return $textos;
    }

    public function registrar(int $sedeId): string
    {
        if (!isset($_POST['csrf_token'], $_SESSION['csrf_token'])
            || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
            return $this->respuesta('simple', 'Acceso denegado', 'La solicitud no es válida. Recargue la página e intente nuevamente.', 'error');
        }

        if (($_POST['acepta_datos'] ?? '') !== 'S') {
            return $this->respuesta('simple', 'Autorización requerida', 'Debe aceptar el tratamiento de sus datos personales conforme a la LOPDP.', 'error');
        }
        if (($_POST['acepta_imagen'] ?? '') !== 'S') {
            return $this->respuesta('simple', 'Autorización requerida', 'Debe autorizar el uso de su imagen para completar la inscripción.', 'error');
        }

        $pdo = $this->conectar();

        try {
            $datos = adulto_validar($pdo, [
                'tipoidentificacion' => $_POST['tipoidentificacion'] ?? '',
                'identificacion'     => $_POST['identificacion'] ?? '',
                'primernombre'       => $_POST['primernombre'] ?? '',
                'segundonombre'      => $_POST['segundonombre'] ?? '',
                'apellidopaterno'    => $_POST['apellidopaterno'] ?? '',
                'apellidomaterno'    => $_POST['apellidomaterno'] ?? '',
                'fechanacimiento'    => $_POST['fechanacimiento'] ?? '',
                'genero'             => $_POST['genero'] ?? '',
                'nacionalidadid'     => $_POST['nacionalidadid'] ?? '',
                'direccion'          => $_POST['direccion'] ?? '',
                'correo'             => $_POST['correo'] ?? '',
                'celular'            => $_POST['celular'] ?? '',
            ]);
        } catch (\AdultoError $e) {
            return $this->respuesta('simple', $e->titulo, $e->getMessage(), 'error');
        }

        /*----------  Foto (opcional)  ----------*/
        $foto = '';
        $dir  = $this->directorioFotos();
        if (isset($_FILES['foto']['error']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK && $_FILES['foto']['size'] > 0) {
            $mime = mime_content_type($_FILES['foto']['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                return $this->respuesta('simple', 'Formato no permitido', 'La foto debe ser JPG o PNG.', 'error');
            }
            if (($_FILES['foto']['size'] / 1024) > 4000) {
                return $this->respuesta('simple', 'Archivo muy grande', 'La foto no debe superar los 4 MB.', 'error');
            }
            $nombre = preg_replace('/[^0-9A-Za-z]/', '', $datos['identificacion']) . '_' . random_int(0, 100)
                    . ($mime === 'image/jpeg' ? '.jpg' : '.png');
            $accesible = is_dir($dir) || @mkdir($dir, 0755, true) || is_dir($dir);
            if ($accesible && $this->resizeImageGD($_FILES['foto']['tmp_name'], 800, 600, $dir . $nombre)) {
                $foto = $nombre;
            } else {
                /* Igual que el formulario de menores: sin poder escribir en la
                   carpeta del panel, se inscribe sin foto antes que perder la
                   inscripción. */
                error_log("[inscripcion adulto] No se pudo escribir la foto en {$dir}; el alumno queda sin foto.");
            }
        }

        try {
            $r = adulto_registrar($pdo, $datos, $sedeId, $foto);
            $this->registrarConsentimientos($pdo, $r['alumno_id'], $r['repre_id']);
        } catch (\Throwable $e) {
            if ($foto !== '' && is_file($dir . $foto)) {
                @unlink($dir . $foto);
            }
            error_log('[inscripcion adulto] ' . $e->getMessage());
            return $this->respuesta('simple', 'Error del servidor', 'Ocurrió un error inesperado. Intente más tarde.', 'error');
        }

        /* Rotar el CSRF para que el mismo formulario no se reenvíe. */
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        return $this->respuesta('exito', 'Registro exitoso',
            $r['nombre'] . ', tu inscripción quedó registrada correctamente.', 'success');
    }

    /** Una fila por consentimiento, con el hash del texto de adultos que se mostró. */
    private function registrarConsentimientos(\PDO $pdo, int $alumnoId, int $repreId): void
    {
        $ip    = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $ahora = date('Y-m-d H:i:s');
        $st = $pdo->prepare(
            "INSERT INTO alumno_consentimiento
                (consent_alumnoid, consent_repreid, consent_tipo, consent_otorgado,
                 consent_origen, consent_usuario, consent_fecha, consent_ip, consent_textohash,
                 consent_observacion)
             VALUES (:alumno, :repre, :tipo, 'S', 'FORMULARIO', NULL, :fecha, :ip, :hash, 'ADULTO')"
        );
        foreach ($this->textosConsentimiento() as $tipo => $b) {
            $st->execute([
                ':alumno' => $alumnoId, ':repre' => $repreId, ':tipo' => $tipo,
                ':fecha' => $ahora, ':ip' => $ip, ':hash' => hash('sha256', $b['texto']),
            ]);
        }
    }

    private function respuesta(string $tipo, string $titulo, string $texto, string $icono): string
    {
        return json_encode(['tipo' => $tipo, 'titulo' => $titulo, 'texto' => $texto, 'icono' => $icono],
                           JSON_UNESCAPED_UNICODE);
    }
}
