<?php

namespace app\controllers;

use app\models\mainModel;

require_once __DIR__ . "/../../../ds_core/inc/alumno_adulto.php";

/**
 * Alta manual de un alumno ADULTO desde el panel (migración 086).
 *
 * Es un proceso aparte del alta de siempre (alumnoController), que no se
 * toca: aquí no hay representante que elegir, porque el alumno es su propio
 * titular. Valida y graba con ds_core/inc/alumno_adulto.php, lo mismo que el
 * enlace público ds_form/adulto.php.
 */
class alumnoAdultoController extends mainModel
{
    /** Sedes de la sesión, activas y que admiten alumnos adultos. */
    public function sedesAdultos(): array
    {
        return (new inscripcionController())->listarSedesAdultos();
    }

    public function registrarAdultoControlador(): string
    {
        if (!puede('crear', 'alumnoAdultoNew')) {
            return $this->alerta('Acceso denegado', 'Su rol no puede registrar alumnos adultos.');
        }

        $sedeId = (int)($_POST['alumno_sedeid'] ?? 0);
        if (!in_array($sedeId, array_map('intval', array_column($this->sedesAdultos(), 'sede_id')), true)) {
            return $this->alerta('Sede no permitida',
                'Seleccione una sede suya, activa y que admita alumnos adultos (Core › Sedes).');
        }

        $pdo = $this->conectar();

        try {
            $datos = adulto_validar($pdo, [
                'tipoidentificacion' => $_POST['alumno_tipoidentificacion'] ?? '',
                'identificacion'     => $_POST['alumno_identificacion'] ?? '',
                'primernombre'       => $_POST['alumno_primernombre'] ?? '',
                'segundonombre'      => $_POST['alumno_segundonombre'] ?? '',
                'apellidopaterno'    => $_POST['alumno_apellidopaterno'] ?? '',
                'apellidomaterno'    => $_POST['alumno_apellidomaterno'] ?? '',
                'fechanacimiento'    => $_POST['alumno_fechanacimiento'] ?? '',
                'genero'             => $_POST['alumno_genero'] ?? '',
                'nacionalidadid'     => $_POST['alumno_nacionalidadid'] ?? '',
                'direccion'          => $_POST['alumno_direccion'] ?? '',
                'correo'             => $_POST['alumno_correo'] ?? '',
                'celular'            => $_POST['alumno_celular'] ?? '',
            ]);
        } catch (\AdultoError $e) {
            return $this->alerta($e->titulo, $e->getMessage());
        }

        /*----------  Foto (opcional)  ----------*/
        $dir  = __DIR__ . "/../views/imagenes/fotos/alumno/";
        $foto = '';
        if (isset($_FILES['alumno_foto']['error']) && $_FILES['alumno_foto']['error'] === UPLOAD_ERR_OK
            && $_FILES['alumno_foto']['size'] > 0) {

            $mime = mime_content_type($_FILES['alumno_foto']['tmp_name']);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                return $this->alerta('Formato no permitido', 'La foto debe ser JPG o PNG.');
            }
            if ($_FILES['alumno_foto']['size'] / 1024 > 4000) {
                return $this->alerta('Archivo muy grande', 'La foto no debe superar los 4 MB.');
            }
            if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
                return $this->alerta('Ocurrió un error', 'No fue posible crear el directorio de fotos.');
            }
            $foto = preg_replace('/[^0-9A-Za-z]/', '', $datos['identificacion']) . '_' . random_int(0, 100)
                  . ($mime === 'image/jpeg' ? '.jpg' : '.png');
            if (!$this->resizeImageGD($_FILES['alumno_foto']['tmp_name'], 800, 600, $dir . $foto)) {
                return $this->alerta('Ocurrió un error', 'No fue posible guardar la foto.');
            }
        }

        try {
            $r = adulto_registrar($pdo, $datos, $sedeId, $foto);
        } catch (\Throwable $e) {
            if ($foto !== '' && is_file($dir . $foto)) {
                @unlink($dir . $foto);
            }
            error_log('[alumnoAdultoNew] ' . $e->getMessage());
            return $this->alerta('Ocurrió un error', 'No se pudo registrar al alumno. Intente nuevamente.');
        }

        $texto = $r['nombre'] . ' quedó registrado como alumno titular: sus recibos y facturas saldrán a su nombre.'
               . ($r['titular_nuevo'] ? '' : ' Ya figuraba como representante, así que se usó esa ficha tal como está.')
               . ' Complete ahora horario, contacto de emergencia y el pago de inscripción.';

        return json_encode([
            'tipo'   => 'redireccionar',
            'titulo' => 'Alumno adulto registrado',
            'texto'  => $texto,
            'icono'  => 'success',
            'url'    => APP_URL . 'alumnoUpdate/' . $r['alumno_id'] . '/',
        ], JSON_UNESCAPED_UNICODE);
    }

    private function alerta(string $titulo, string $texto): string
    {
        return json_encode(['tipo' => 'simple', 'titulo' => $titulo, 'texto' => $texto, 'icono' => 'error'],
                           JSON_UNESCAPED_UNICODE);
    }
}
