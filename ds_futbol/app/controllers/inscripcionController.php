<?php

namespace app\controllers;

use app\models\mainModel;
use app\models\TokenHelper;

class inscripcionController extends mainModel
{
    /**
     * Genera el enlace de inscripción para una sede.
     */
    public function generarEnlaceControlador()
    {
        /* Al desplegar es fácil subir el código y dejar el config viejo. Sin
           esta comprobación, TOKEN_SECRET indefinido tumbaba la petición con
           un 500 y el usuario solo veía "No se pudo generar el enlace". */
        $faltante = TokenHelper::configFaltante();

        if (count($faltante) > 0) {
            error_log("[inscripcionEnlace] Faltan constantes en config/app.php: " . implode(', ', $faltante));

            return json_encode([
                'tipo'   => 'simple',
                'titulo' => 'Configuración incompleta',
                'texto'  => 'Falta definir en config/app.php: ' . implode(', ', $faltante)
                          . '. Avise al administrador del sistema.',
                'icono'  => 'error'
            ], JSON_UNESCAPED_UNICODE);
        }

        $sede_id = intval($this->limpiarCadena($_POST['sede_id'] ?? '0'));
        $horas   = intval($this->limpiarCadena($_POST['horas_vigencia'] ?? '72'));

        if ($sede_id <= 0) {
            return json_encode([
                'tipo'   => 'simple',
                'titulo' => 'Error',
                'texto'  => 'Debe seleccionar una sede.',
                'icono'  => 'error'
            ]);
        }

        if ($horas < 1 || $horas > 720) {
            $horas = 72;
        }

        $expiraEnSegundos = $horas * 3600;

        /* El usuario solo puede generar enlaces de las sedes que tiene asignadas.
           Se valida contra la BD y no contra el <select>, que es manipulable. */
        $sedeNombre = $this->obtenerSedePermitida($sede_id);

        if ($sedeNombre === null) {
            return json_encode([
                'tipo'   => 'simple',
                'titulo' => 'Sede no permitida',
                'texto'  => 'La sede seleccionada no existe o no está asignada a su usuario.',
                'icono'  => 'error'
            ]);
        }

        $urlFormulario  = TokenHelper::generarURL($sede_id, $expiraEnSegundos);
        $enlaceWhatsApp = TokenHelper::generarEnlaceWhatsApp($sede_id, $sedeNombre, $expiraEnSegundos);

        return json_encode([
            'tipo'            => 'enlace',
            'titulo'          => 'Enlace generado',
            'texto'           => 'El enlace de inscripción para la sede ' . $sedeNombre . ' ha sido generado.',
            'icono'           => 'success',
            'url_formulario'  => $urlFormulario,
            'enlace_whatsapp' => $enlaceWhatsApp,
            'sede_nombre'     => $sedeNombre,
            'vigencia_horas'  => $horas
        ]);
    }

    /**
     * Lista las sedes que el usuario en sesión puede usar para generar enlaces.
     * Los roles administrativos (1 y 2) ven todas; el resto solo las asignadas.
     */
    public function listarSedesActivas()
    {
        $rolid   = $_SESSION['rol'] ?? null;
        $usuario = $_SESSION['usuario'] ?? '';

        if ($rolid != 1 && $rolid != 2) {
            $consulta = $this->ejecutarConsulta(
                "SELECT S.sede_id, S.sede_nombre
                   FROM general_sede S
                   INNER JOIN seguridad_usuario_sede US ON US.usuariosede_sedeid = S.sede_id
                   INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
                  WHERE U.usuario_usuario = :usuario
                  ORDER BY S.sede_nombre",
                [":usuario" => $usuario]
            );
        } else {
            $consulta = $this->ejecutarConsulta(
                "SELECT sede_id, sede_nombre FROM general_sede ORDER BY sede_nombre"
            );
        }

        return $consulta->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Alumnos que entraron por el enlace de inscripción y a los que todavía
     * les falta algo para quedar operativos en el sistema.
     *
     * El formulario público solo graba los datos personales: no asigna horario,
     * no registra el rubro de inscripción, no pide contacto de emergencia y
     * la foto es opcional. Sin esta pantalla esos alumnos quedan invisibles
     * entre el resto del listado y nadie los completa.
     *
     * Se identifican por su consentimiento de origen FORMULARIO, que es la
     * única huella que deja el formulario público. Los alumnos inscritos en
     * línea ANTES de que existiera alumno_consentimiento no aparecen acá.
     */
    /**
     * Cuantas inscripciones en linea estan a medias.
     *
     * Se apoya en listarPendientesInscripcion y cuenta sus filas, en lugar de
     * escribir un COUNT aparte. Un COUNT propio se desincronizaria el dia que
     * alguien cambie que significa «pendiente», y entonces el numerito del
     * menu diria una cosa y la pantalla otra. Dos cifras distintas para lo
     * mismo son peores que ninguna.
     *
     * La cola de inscripciones sin terminar es corta por naturaleza —es una
     * lista de tareas—, asi que traer las filas para contarlas no pesa. Medido:
     * 2,4 ms.
     */
    public function contarPendientesInscripcion(): int
    {
        return count($this->listarPendientesInscripcion());
    }

    public function listarPendientesInscripcion($sedeid = '', $busqueda = '')
    {
        $interna = "SELECT a.alumno_id,
                           a.alumno_identificacion,
                           CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno, a.alumno_apellidomaterno) AS alumno,
                           TIMESTAMPDIFF(YEAR, a.alumno_fechanacimiento, CURDATE()) AS edad,
                           s.sede_nombre,
                           CONCAT_WS(' ', r.repre_primernombre, r.repre_apellidopaterno) AS representante,
                           r.repre_celular,
                           o.fecha_online,
                           DATEDIFF(CURDATE(), DATE(o.fecha_online)) AS dias_espera,
                           (SELECT COUNT(*) FROM asistencia_asignahorario ah
                             WHERE ah.asignahorario_alumnoid = a.alumno_id) AS tiene_horario,
                           (SELECT COUNT(*) FROM alumno_cemergencia ce
                             WHERE ce.cemer_alumnoid = a.alumno_id) AS tiene_emergencia,
                           (SELECT COUNT(*) FROM alumno_pago p
                             WHERE p.pago_alumnoid = a.alumno_id
                               AND p.pago_rubroid = 'RIN'
                               AND p.pago_estado <> 'E') AS tiene_inscripcion,
                           (SELECT MAX(p2.pago_estado) FROM alumno_pago p2
                             WHERE p2.pago_alumnoid = a.alumno_id
                               AND p2.pago_rubroid = 'RIN'
                               AND p2.pago_estado <> 'E') AS estado_inscripcion,
                           CASE WHEN a.alumno_imagen IS NULL OR a.alumno_imagen = '' THEN 1 ELSE 0 END AS sin_foto
                      FROM sujeto_alumno a
                      INNER JOIN alumno_representante r ON r.repre_id = a.alumno_repreid
                      INNER JOIN general_sede s ON s.sede_id = a.alumno_sedeid
                      INNER JOIN (SELECT consent_alumnoid, MIN(consent_fecha) AS fecha_online
                                    FROM alumno_consentimiento
                                   WHERE consent_origen = 'FORMULARIO'
                                   GROUP BY consent_alumnoid) o ON o.consent_alumnoid = a.alumno_id
                     WHERE a.alumno_estado = 'A'";

        $parametros = [];

        // Los roles no administrativos solo ven sus sedes asignadas
        $rolid   = $_SESSION['rol'] ?? null;
        $usuario = $_SESSION['usuario'] ?? '';

        if ($rolid != 1 && $rolid != 2) {
            $interna .= " AND a.alumno_sedeid IN (
                                SELECT US.usuariosede_sedeid
                                  FROM seguridad_usuario_sede US
                                  INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
                                 WHERE U.usuario_usuario = :usuario)";
            $parametros[':usuario'] = $usuario;
        }

        if ($sedeid !== '' && intval($sedeid) > 0) {
            $interna .= " AND a.alumno_sedeid = :sede";
            $parametros[':sede'] = intval($sedeid);
        }

        if (trim($busqueda) !== '') {
            $interna .= " AND (a.alumno_identificacion LIKE :b
                            OR a.alumno_primernombre LIKE :b
                            OR a.alumno_apellidopaterno LIKE :b
                            OR a.alumno_apellidomaterno LIKE :b)";
            $parametros[':b'] = '%' . trim($busqueda) . '%';
        }

        /* El filtro "le falta algo" va en una consulta externa: los contadores
           son subconsultas escalares y no se pueden reusar en el WHERE de su
           propio SELECT. */
        $consulta = "SELECT * FROM ($interna) X
                      WHERE X.tiene_horario = 0
                         OR X.tiene_inscripcion = 0
                         OR X.tiene_emergencia = 0
                         OR X.sin_foto = 1
                      ORDER BY X.fecha_online DESC";

        return $this->ejecutarConsulta($consulta, $parametros)->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Devuelve el nombre de la sede si el usuario en sesión tiene acceso a ella,
     * o null si no existe o no le corresponde.
     */
    private function obtenerSedePermitida($sede_id)
    {
        $rolid   = $_SESSION['rol'] ?? null;
        $usuario = $_SESSION['usuario'] ?? '';

        if ($rolid != 1 && $rolid != 2) {
            $consulta = $this->ejecutarConsulta(
                "SELECT S.sede_nombre
                   FROM general_sede S
                   INNER JOIN seguridad_usuario_sede US ON US.usuariosede_sedeid = S.sede_id
                   INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
                  WHERE S.sede_id = :sede AND U.usuario_usuario = :usuario",
                [":sede" => $sede_id, ":usuario" => $usuario]
            );
        } else {
            $consulta = $this->ejecutarConsulta(
                "SELECT sede_nombre FROM general_sede WHERE sede_id = :sede",
                [":sede" => $sede_id]
            );
        }

        $sede = $consulta->fetch(\PDO::FETCH_ASSOC);

        return $sede ? $sede['sede_nombre'] : null;
    }
}
