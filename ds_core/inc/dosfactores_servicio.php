<?php
/*
|--------------------------------------------------------------------------
| Segundo factor: acceso a datos y reglas
|--------------------------------------------------------------------------
| dosfactores.php implementa el algoritmo; esto lo conecta con la base y
| con las reglas de uso. Separados porque el algoritmo se puede probar
| contra los vectores del RFC sin tocar la base, y eso es justo lo que se
| hizo.
*/

if (!function_exists('dosf_estado')) {

    /*==================================================================
      Consulta
      ==================================================================*/

    /** Estado del segundo factor de un usuario: 'N', 'P' o 'A'. */
    function dosf_estado(int $usuarioid): string
    {
        $con = seguridad_conexion();
        if ($con === null) { return 'N'; }

        try {
            $st = $con->prepare("SELECT usuario_2fa_estado FROM seguridad_usuario
                                  WHERE usuario_id = :id");
            $st->execute([':id' => $usuarioid]);
            return (string)($st->fetchColumn() ?: 'N');
        } catch (\Throwable $e) {
            return 'N';
        }
    }

    /** ¿Tiene el segundo factor activo? */
    function dosf_activo(int $usuarioid): bool
    {
        return dosf_estado($usuarioid) === 'A';
    }

    /** Secreto guardado, o cadena vacia. */
    function dosf_secreto(int $usuarioid): string
    {
        $con = seguridad_conexion();
        if ($con === null) { return ''; }

        try {
            $st = $con->prepare("SELECT usuario_2fa_secreto FROM seguridad_usuario
                                  WHERE usuario_id = :id");
            $st->execute([':id' => $usuarioid]);
            return (string)($st->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /** Cuantos codigos de recuperacion quedan sin usar. */
    function dosf_recuperacion_restantes(int $usuarioid): int
    {
        $con = seguridad_conexion();
        if ($con === null) { return 0; }

        try {
            $st = $con->prepare("SELECT COUNT(*) FROM seguridad_2fa_recuperacion
                                  WHERE rec_usuarioid = :id AND rec_usado = 'N'");
            $st->execute([':id' => $usuarioid]);
            return (int)$st->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Ultimos movimientos del segundo factor de un usuario. */
    function dosf_historial(int $usuarioid, int $limite = 20): array
    {
        $con = seguridad_conexion();
        if ($con === null) { return []; }

        $limite = max(1, min(100, $limite));

        try {
            $st = $con->prepare("SELECT ev_accion, ev_autor, ev_nota, ev_fecha
                                   FROM seguridad_2fa_evento
                                  WHERE ev_usuarioid = :id
                                  ORDER BY ev_id DESC LIMIT {$limite}");
            $st->execute([':id' => $usuarioid]);
            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /*==================================================================
      Registro
      ==================================================================*/

    /**
     * Anota un movimiento del segundo factor.
     *
     * Se guarda ademas de la auditoria general porque activar, desactivar
     * o restablecer el factor de otra persona es exactamente el
     * movimiento que haria quien quiere entrar en una cuenta ajena: tiene
     * que quedar escrito quien lo hizo y sobre quien.
     */
    function dosf_anotar(int $usuarioid, string $accion, string $nota = ''): void
    {
        $con = seguridad_conexion();
        if ($con === null) { return; }

        try {
            $con->prepare(
                "INSERT INTO seguridad_2fa_evento
                        (ev_usuarioid, ev_accion, ev_autorid, ev_autor, ev_ip, ev_nota)
                 VALUES (:u, :a, :aid, :au, :ip, :n)")
                ->execute([
                    ':u'   => $usuarioid,
                    ':a'   => $accion,
                    ':aid' => usuario_actual_id() ?: null,
                    ':au'  => substr(ds_nombre_usuario(), 0, 20),
                    ':ip'  => intentos_ip_binaria(),
                    ':n'   => substr($nota, 0, 250),
                ]);
        } catch (\Throwable $e) {
            /* Que no se pueda anotar no debe impedir la operación; el
               registro general de auditoría sigue teniendo constancia. */
        }
    }

    /*==================================================================
      Altas y bajas
      ==================================================================*/

    /**
     * Genera un secreto y lo deja PENDIENTE de confirmar.
     *
     * No activa nada: mientras el usuario no demuestre que su teléfono
     * produce el código correcto, entra sólo con la contraseña. Activar
     * antes de comprobarlo es la forma de dejar a alguien fuera de su
     * propia cuenta.
     */
    function dosf_preparar(int $usuarioid): string
    {
        $con = seguridad_conexion();
        if ($con === null) { return ''; }

        $secreto = totp_secreto_nuevo();

        try {
            $con->prepare(
                "UPDATE seguridad_usuario
                    SET usuario_2fa_secreto = :s, usuario_2fa_estado = 'P',
                        usuario_2fa_activado = NULL
                  WHERE usuario_id = :id")
                ->execute([':s' => $secreto, ':id' => $usuarioid]);

            return $secreto;
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Confirma el secreto pendiente y activa el segundo factor.
     *
     * Devuelve los codigos de recuperacion en claro: es la UNICA vez que
     * existen legibles. Despues solo queda su hash.
     *
     * @return array{ok: bool, motivo: string, codigos: array}
     */
    function dosf_activar(int $usuarioid, string $codigo): array
    {
        $con = seguridad_conexion();
        if ($con === null) {
            return ['ok' => false, 'motivo' => 'Sin conexión.', 'codigos' => []];
        }

        if (dosf_estado($usuarioid) !== 'P') {
            return ['ok' => false, 'codigos' => [],
                    'motivo' => 'No hay una configuración pendiente. Empiece de nuevo.'];
        }

        $secreto = dosf_secreto($usuarioid);

        if ($secreto === '' || !totp_valido($secreto, $codigo)) {
            dosf_anotar($usuarioid, 'FALLO', 'Código incorrecto al activar');
            return ['ok' => false, 'codigos' => [],
                    'motivo' => 'El código no coincide. Compruebe que el reloj del '
                              . 'teléfono esté en hora y vuelva a intentarlo.'];
        }

        $codigos = recuperacion_generar();

        try {
            $con->beginTransaction();

            $con->prepare("UPDATE seguridad_usuario
                              SET usuario_2fa_estado = 'A', usuario_2fa_activado = NOW()
                            WHERE usuario_id = :id")
                ->execute([':id' => $usuarioid]);

            /* Los de una configuración anterior dejan de valer. */
            $con->prepare("DELETE FROM seguridad_2fa_recuperacion WHERE rec_usuarioid = :id")
                ->execute([':id' => $usuarioid]);

            $ins = $con->prepare("INSERT INTO seguridad_2fa_recuperacion
                                         (rec_usuarioid, rec_hash) VALUES (:u, :h)");
            foreach ($codigos as $c) {
                $ins->execute([':u' => $usuarioid, ':h' => recuperacion_hash($c)]);
            }

            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return ['ok' => false, 'codigos' => [],
                    'motivo' => 'No se pudo guardar: ' . $e->getMessage()];
        }

        dosf_anotar($usuarioid, 'ACTIVAR', count($codigos) . ' códigos de recuperación');

        return ['ok' => true, 'motivo' => '', 'codigos' => $codigos];
    }

    /**
     * Desactiva el segundo factor y borra todo su rastro utilizable.
     *
     * @param string $motivo por qué; queda registrado
     */
    function dosf_desactivar(int $usuarioid, string $accion = 'DESACTIVAR',
                             string $motivo = ''): bool
    {
        $con = seguridad_conexion();
        if ($con === null) { return false; }

        try {
            $con->beginTransaction();

            $con->prepare("UPDATE seguridad_usuario
                              SET usuario_2fa_estado = 'N', usuario_2fa_secreto = '',
                                  usuario_2fa_activado = NULL
                            WHERE usuario_id = :id")
                ->execute([':id' => $usuarioid]);

            $con->prepare("DELETE FROM seguridad_2fa_recuperacion WHERE rec_usuarioid = :id")
                ->execute([':id' => $usuarioid]);

            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return false;
        }

        dosf_anotar($usuarioid, $accion, $motivo);

        return true;
    }

    /**
     * Consume un codigo de recuperacion.
     *
     * UN CODIGO SIRVE UNA VEZ. Se marca dentro de la misma operacion que
     * lo comprueba: si se comprobara aqui y se marcara despues, dos
     * peticiones simultaneas con el mismo codigo pasarian las dos.
     */
    function dosf_usar_recuperacion(int $usuarioid, string $codigo): bool
    {
        $con = seguridad_conexion();
        if ($con === null) { return false; }

        $limpio = recuperacion_normalizar($codigo);
        if (strlen($limpio) !== 8) { return false; }

        try {
            $st = $con->prepare("SELECT rec_id, rec_hash FROM seguridad_2fa_recuperacion
                                  WHERE rec_usuarioid = :u AND rec_usado = 'N'");
            $st->execute([':u' => $usuarioid]);

            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
                if (!recuperacion_verificar($limpio, $f['rec_hash'])) { continue; }

                /* El WHERE rec_usado = 'N' es lo que hace atómico el
                   consumo: si otra petición lo gastó entre la lectura y
                   este UPDATE, no afecta filas y el código no vale. */
                $up = $con->prepare("UPDATE seguridad_2fa_recuperacion
                                        SET rec_usado = 'S', rec_fechauso = NOW()
                                      WHERE rec_id = :id AND rec_usado = 'N'");
                $up->execute([':id' => $f['rec_id']]);

                if ($up->rowCount() !== 1) { return false; }

                dosf_anotar($usuarioid, 'RECUPERACION',
                    'Quedan ' . dosf_recuperacion_restantes($usuarioid) . ' códigos');

                return true;
            }
        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }

    /** Genera un juego nuevo de codigos y anula los anteriores. */
    function dosf_regenerar_codigos(int $usuarioid): array
    {
        $con = seguridad_conexion();
        if ($con === null || !dosf_activo($usuarioid)) { return []; }

        $codigos = recuperacion_generar();

        try {
            $con->beginTransaction();

            $con->prepare("DELETE FROM seguridad_2fa_recuperacion WHERE rec_usuarioid = :id")
                ->execute([':id' => $usuarioid]);

            $ins = $con->prepare("INSERT INTO seguridad_2fa_recuperacion
                                         (rec_usuarioid, rec_hash) VALUES (:u, :h)");
            foreach ($codigos as $c) {
                $ins->execute([':u' => $usuarioid, ':h' => recuperacion_hash($c)]);
            }

            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return [];
        }

        dosf_anotar($usuarioid, 'CODIGOS_NUEVOS', 'Los anteriores dejan de valer');

        return $codigos;
    }

    /*==================================================================
      Paso intermedio del acceso
      ==================================================================*/

    /** Nombre del emisor que se ve en la aplicacion de codigos. */
    function dosf_emisor(): string
    {
        return defined('DS_HUB_NAME') && DS_HUB_NAME !== '' ? DS_HUB_NAME : 'DigiSports';
    }

    /**
     * Guarda quien acaba de superar la contrasena y espera el codigo.
     *
     * NO se crea la sesion de usuario: hasta que no pase el segundo
     * factor no esta autenticado. Aqui sólo queda una marca con caducidad
     * propia, para que una pestana abierta ayer no sirva de puerta.
     */
    function dosf_pendiente_guardar(array $usuario): void
    {
        $_SESSION['ds_2fa_pendiente'] = [
            'usuario_id' => (int)$usuario['usuario_id'],
            'usuario'    => (string)$usuario['usuario_usuario'],
            'desde'      => time(),
        ];
    }

    /** Minutos que aguanta el paso intermedio. */
    function dosf_pendiente_minutos(): int { return 10; }

    /** Devuelve el pendiente si sigue vigente; si no, lo descarta. */
    function dosf_pendiente(): array
    {
        $p = $_SESSION['ds_2fa_pendiente'] ?? null;
        if (!is_array($p)) { return []; }

        if (time() - (int)($p['desde'] ?? 0) > dosf_pendiente_minutos() * 60) {
            unset($_SESSION['ds_2fa_pendiente']);
            return [];
        }

        return $p;
    }

    function dosf_pendiente_limpiar(): void
    {
        unset($_SESSION['ds_2fa_pendiente']);
    }
}
