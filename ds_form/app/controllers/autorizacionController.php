<?php

namespace app\controllers;

use app\models\mainModel;
use PDO;

/**
 * Autorizaciones LOPDP de un representante YA registrado (autorizacion.php).
 *
 * La escuela le envía un enlace desde Fútbol › Consentimientos LOPDP. El
 * enlace lleva el representante y un código firmados; aquí se exige además
 * que el código exista en consentimiento_enlace (migración 074), sea de ese
 * representante, no haya vencido y no se haya usado. Una respuesta lo gasta.
 *
 * Lo que responde queda en alumno_consentimiento con origen ENLACE, su IP y
 * el hash del texto mostrado, igual que en la inscripción.
 */
class autorizacionController extends mainModel
{
    /**
     * Estado del enlace: ['estado' => valido|usado|expirado|invalido, 'enlace' => fila|null].
     * $token es lo que devolvió TokenHelper::validarAutorizacion() (firma ya buena).
     */
    public function estadoEnlace(array $token): array
    {
        try {
            $consulta = $this->ejecutarConsulta(
                "SELECT enlace_id, enlace_repreid, enlace_expira, enlace_usado, enlace_ip
                   FROM consentimiento_enlace
                  WHERE enlace_codigo = :c AND enlace_repreid = :r",
                [':c' => $token['c'], ':r' => $token['r']]
            );
            $enlace = $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            $enlace = null;   // sin la migración 074 ningún enlace vale
        }

        if ($enlace === null) {
            return ['estado' => 'invalido', 'enlace' => null];
        }
        if ($enlace['enlace_usado'] !== null) {
            return ['estado' => 'usado', 'enlace' => $enlace];
        }
        if ($token['expirado'] || strtotime($enlace['enlace_expira']) < time()) {
            return ['estado' => 'expirado', 'enlace' => $enlace];
        }
        return ['estado' => 'valido', 'enlace' => $enlace];
    }

    /**
     * El primer nombre del representante y sus alumnos activos, con el
     * estado vigente de cada autorización. Sólo nombres: ni cédulas ni sede.
     */
    public function datosRepresentante(int $repreId): array
    {
        $repre = $this->ejecutarConsulta(
            "SELECT repre_primernombre FROM alumno_representante WHERE repre_id = :r",
            [':r' => $repreId]
        )->fetchColumn();

        $alumnos = $this->ejecutarConsulta(
            "SELECT a.alumno_id,
                    CONCAT_WS(' ', a.alumno_primernombre, a.alumno_apellidopaterno) AS nombre,
                    (SELECT c.consent_otorgado FROM alumno_consentimiento c
                      WHERE c.consent_alumnoid = a.alumno_id AND c.consent_tipo = 'DATOS'
                      ORDER BY c.consent_fecha DESC, c.consent_id DESC LIMIT 1) AS datos,
                    (SELECT c.consent_otorgado FROM alumno_consentimiento c
                      WHERE c.consent_alumnoid = a.alumno_id AND c.consent_tipo = 'IMAGEN'
                      ORDER BY c.consent_fecha DESC, c.consent_id DESC LIMIT 1) AS imagen
               FROM sujeto_alumno a
              WHERE a.alumno_repreid = :r AND a.alumno_estado = 'A'
              ORDER BY a.alumno_fechanacimiento, a.alumno_primernombre",
            [':r' => $repreId]
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['nombre' => (string)$repre, 'alumnos' => $alumnos];
    }

    /**
     * Registra la respuesta. Devuelve ['ok' => bool, 'error' => texto, 'imagen' => S|N].
     *
     * Todo va en una transacción que empieza gastando el enlace con
     * «WHERE enlace_usado IS NULL»: dos envíos a la vez (doble toque) no
     * registran dos veces, porque sólo uno consigue gastarlo.
     */
    public function responder(array $token, array $enlace, string $aceptaDatos, string $imagen): array
    {
        if ($aceptaDatos !== 'S') {
            return ['ok' => false, 'error' => 'Para continuar debe aceptar el tratamiento de datos personales.'];
        }
        if (!in_array($imagen, ['S', 'N'], true)) {
            return ['ok' => false, 'error' => 'Indique si autoriza o no el uso de la imagen.'];
        }

        $alumnos = $this->datosRepresentante($token['r'])['alumnos'];
        if (!$alumnos) {
            return ['ok' => false, 'error' => 'No hay alumnos activos asociados a este enlace.'];
        }

        $textos = new registroController();
        $hash   = ['DATOS' => $textos->hashTexto('DATOS'), 'IMAGEN' => $textos->hashTexto('IMAGEN')];
        // REMOTE_ADDR y no X-Forwarded-For: la cabecera la controla el cliente
        $ip     = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
        $ahora  = date('Y-m-d H:i:s');
        $obs    = 'Enlace #' . (int)$enlace['enlace_id'];

        $pdo = $this->conectar();
        try {
            $pdo->beginTransaction();

            $gastar = $pdo->prepare(
                "UPDATE consentimiento_enlace SET enlace_usado = :ahora, enlace_ip = :ip
                  WHERE enlace_id = :id AND enlace_usado IS NULL AND enlace_expira > :ahora"
            );
            $gastar->execute([':ahora' => $ahora, ':ip' => $ip, ':id' => $enlace['enlace_id']]);
            if ($gastar->rowCount() !== 1) {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Este enlace ya se respondió o venció.'];
            }

            /* Los otros enlaces abiertos del mismo representante quedan
               anulados (usados, sin IP): ya respondió. */
            $pdo->prepare("UPDATE consentimiento_enlace SET enlace_usado = :ahora
                            WHERE enlace_repreid = :r AND enlace_usado IS NULL")
                ->execute([':ahora' => $ahora, ':r' => $token['r']]);

            /* Una fila sólo donde cambia algo: el historial no se llena de
               repeticiones de lo que ya estaba autorizado. */
            $insertar = $pdo->prepare(
                "INSERT INTO alumno_consentimiento
                    (consent_alumnoid, consent_repreid, consent_tipo, consent_otorgado,
                     consent_origen, consent_usuario, consent_fecha, consent_ip, consent_textohash, consent_observacion)
                 VALUES (:alumno, :repre, :tipo, :otorgado, 'ENLACE', NULL, :fecha, :ip, :hash, :obs)"
            );
            foreach ($alumnos as $a) {
                foreach (['DATOS' => 'S', 'IMAGEN' => $imagen] as $tipo => $decision) {
                    if ($a[strtolower($tipo)] === $decision) { continue; }
                    $insertar->execute([
                        ':alumno' => $a['alumno_id'], ':repre' => $token['r'], ':tipo' => $tipo,
                        ':otorgado' => $decision, ':fecha' => $ahora, ':ip' => $ip,
                        ':hash' => $hash[$tipo], ':obs' => $obs,
                    ]);
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            error_log('[autorizacion] ' . $e->getMessage());
            return ['ok' => false, 'error' => 'No se pudo guardar su respuesta. Intente de nuevo en unos minutos.'];
        }

        return ['ok' => true, 'error' => '', 'imagen' => $imagen];
    }
}
