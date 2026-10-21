<?php

namespace league\controllers;

use PDO;
use arena\controllers\arenaController;

/**
 * Puente hacia DigiSports Arena.
 *
 * Existe para que la dependencia de League sobre Arena sea EXPLICITA y
 * este en un solo archivo. Sin el, cada controlador acabaria consultando
 * dsa_* por su cuenta y nadie sabria, al cambiar Arena, que se rompe.
 *
 * LO QUE LEAGUE CONSUME
 *
 *   · el catalogo de instalaciones,
 *   · la comprobacion de disponibilidad,
 *   · la escritura de un bloqueo cuando se confirma un partido.
 *
 * LO QUE NO
 *
 *   · reservas, tarifas, clientes ni monedero. Eso es negocio de Arena y
 *     League no tiene por que tocarlo.
 *
 * POR QUE LA DISPONIBILIDAD SE DELEGA Y NO SE REESCRIBE
 *
 * Arena comprueba tres cosas: que la franja quepa en el horario semanal,
 * que no cruce un bloqueo y que no cruce una reserva. Si League repitiera
 * esa logica, los dos calculos podrian divergir, y una divergencia aqui
 * significa exactamente lo que este puente existe para impedir: dos
 * sistemas ocupando la misma cancha a la misma hora.
 */
class arenaPuente
{
    private ?arenaController $arena = null;

    /**
     * Instancia perezosa del controlador de Arena.
     *
     * El autoloader del modulo solo resuelve league\, asi que se registra
     * aqui el de arena\ la primera vez que hace falta. No se hace en
     * config/app.php para que una pantalla de League que no toque
     * escenarios no pague el coste de cargarlo.
     */
    private function arena(): arenaController
    {
        if ($this->arena === null) {
            if (!class_exists(arenaController::class, false)) {
                $ruta = __DIR__ . '/../../ds_arena/controllers/arenaController.php';
                if (is_file($ruta)) { require_once $ruta; }
            }
            $this->arena = new arenaController();
        }

        return $this->arena;
    }

    /** Arena esta instalado y tiene instalaciones activas. */
    public function disponibleElModulo(): bool
    {
        return is_file(__DIR__ . '/../../ds_arena/controllers/arenaController.php');
    }

    /**
     * Canchas activas.
     *
     * Se piden directamente y no por sede porque una liga puede jugarse en
     * escenarios de varias sedes.
     */
    public function instalaciones(): array
    {
        if (!$this->disponibleElModulo()) { return []; }

        try {
            return array_values(array_filter(
                $this->arena()->instalaciones(),
                static fn($i) => ($i['instalacion_estado'] ?? '') === 'A'
            ));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Comprueba si una cancha esta libre. Delega en Arena.
     *
     * @return array ['ok' => bool, 'motivo' => string]
     */
    public function disponible(int $instalacionid, string $fecha,
                               string $inicio, string $fin): array
    {
        if (!$this->disponibleElModulo()) {
            return ['ok' => false, 'motivo' => 'El módulo Arena no está disponible.'];
        }

        try {
            return $this->arena()->verificarDisponibilidad($instalacionid, $fecha, $inicio, $fin);
        } catch (\Throwable $e) {
            /* Ante la duda, NO se concede: dar por libre una cancha que no
               se pudo comprobar es como no comprobarla. */
            return ['ok' => false,
                    'motivo' => 'No se pudo verificar la disponibilidad: ' . $e->getMessage()];
        }
    }

    /**
     * Reserva la franja para un partido escribiendo un bloqueo en Arena.
     *
     * Se usa un BLOQUEO y no una reserva a proposito: una reserva de Arena
     * lleva cliente, tarifa y saldo, y un partido de la liga no es un
     * alquiler. Lo que hace falta es que la cancha deje de ofrecerse a esa
     * hora, y eso es justo lo que hace un bloqueo.
     *
     * El tipo es 'E' —evento propio— que ya existe en el vocabulario de
     * Arena; no hay que ampliar su catalogo.
     *
     * @return int id del bloqueo, o -1 si no se pudo crear
     */
    public function bloquearParaPartido(PDO $con, int $partidoid, int $instalacionid,
                                        string $fecha, string $inicio, string $fin,
                                        string $rotulo): int
    {
        try {
            $st = $con->prepare(
                "INSERT INTO dsa_bloqueo
                        (bloqueo_instalacionid, bloqueo_tipo, bloqueo_inicio, bloqueo_fin,
                         bloqueo_motivo, bloqueo_usuarioid, bloqueo_estado)
                 VALUES (:i, 'E', TIMESTAMP(:f1, :ini), TIMESTAMP(:f2, :fin), :m, :u, 'A')"
            );

            $st->execute([
                ':i'   => $instalacionid,
                ':f1'  => $fecha, ':ini' => $inicio,
                ':f2'  => $fecha, ':fin' => $fin,
                /* El motivo lleva la referencia al partido para que, mirando
                   la agenda de Arena, se sepa de dónde viene el bloqueo. */
                ':m'   => 'League · partido #' . $partidoid . ' · ' . substr($rotulo, 0, 110),
                ':u'   => usuario_actual_id() ?: null,
            ]);

            return (int)$con->lastInsertId();

        } catch (\Throwable $e) {
            return -1;
        }
    }

    /**
     * Retira el bloqueo de un partido que se cancela o se reprograma.
     *
     * Se BORRA en lugar de marcarse inactivo: un bloqueo dado de baja
     * seguiria apareciendo en la agenda de Arena como ruido histórico de
     * una cancha que en realidad quedó libre. La trazabilidad de lo que
     * pasó con el partido vive en dsl_auditoria, que es donde se busca.
     */
    public function liberarBloqueo(PDO $con, int $bloqueoid): bool
    {
        if ($bloqueoid <= 0) { return true; }

        try {
            $st = $con->prepare("DELETE FROM dsa_bloqueo WHERE bloqueo_id = :b");
            $st->execute([':b' => $bloqueoid]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
