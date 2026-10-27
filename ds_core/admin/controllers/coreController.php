<?php

namespace admin\controllers;

use PDO;

/**
 * Administracion del ecosistema DigiSports.
 *
 * Concentra la lectura y escritura de usuarios, roles, permisos, menus y
 * acceso a modulos. Se apoya en la conexion del nucleo (seguridad_conexion)
 * para no abrir una segunda por peticion.
 *
 * Todas las escrituras exigen la accion correspondiente sobre la vista que
 * las origina; el guard de los endpoints AJAX lo verifica antes de llegar
 * aqui, y los metodos vuelven a comprobarlo por si se invocan desde otro
 * punto.
 */
class coreController
{
    /*----------  Utilidades  ----------*/

    private function con(): ?PDO
    {
        return seguridad_conexion();
    }

    /** Consulta preparada que devuelve todas las filas; [] si falla. */
    private function filas(string $sql, array $params = []): array
    {
        try {
            $con = $this->con();
            if ($con === null) return [];

            $stmt = $con->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** La primera fila, o [] si no hay ninguna. */
    private function fila(string $sql, array $params = []): array
    {
        return $this->filas($sql, $params)[0] ?? [];
    }

    private function escalar(string $sql, array $params = [])
    {
        try {
            $con = $this->con();
            if ($con === null) return 0;

            $stmt = $con->prepare($sql);
            $stmt->execute($params);
            $fila = $stmt->fetch(PDO::FETCH_NUM);
            return $fila === false ? 0 : $fila[0];
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Ejecuta una escritura. Devuelve filas afectadas o -1 si hubo error. */
    private function escribir(string $sql, array $params = []): int
    {
        try {
            $con = $this->con();
            if ($con === null) return -1;

            $stmt = $con->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            return -1;
        }
    }

    /** Alerta con el formato que espera alertas_ajax() en ajax.js */
    public function alerta(string $tipo, string $titulo, string $texto, string $icono = 'success', string $url = ''): string
    {
        $a = ['tipo' => $tipo, 'titulo' => $titulo, 'texto' => $texto, 'icono' => $icono];
        if ($url !== '') $a['url'] = $url;
        return json_encode($a, JSON_UNESCAPED_UNICODE);
    }

    /*======================================================================
      Panel
      ====================================================================*/


    /*======================================================================
      Lo que el panel necesita saber

      resumen() contaba INVENTARIO: cuántos usuarios, roles, vistas y
      permisos hay. Son cifras que no se mueven en semanas y que no cambian
      lo que nadie va a hacer hoy. En un módulo de seguridad la pregunta es
      otra: «¿hay algo mal configurado o algo que vigilar?».

      Ninguna cifra se guarda: todas se derivan del propio modelo de
      permisos.
      ======================================================================*/

    /**
     * Lo accionable. Cada línea es algo que alguien tiene que ir a mirar.
     */
    public function requiereAtencion(): array
    {
        /* Cuentas activas sin segundo factor. Es la puerta de entrada al
           ecosistema entero y la comprobación más barata que existe. */
        $sin2fa = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_usuario
              WHERE usuario_estado = 'A'
                AND (usuario_2fa_estado <> 'A' OR usuario_2fa_estado IS NULL)");

        /* Vistas registradas a las que NINGÚN rol puede entrar. O sobran en
           el menú, o alguien se quedó sin el permiso que esperaba tener. */
        $vistasHuerfanas = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_menu m
              WHERE m.menu_estado = 'A'
                AND NOT EXISTS (SELECT 1 FROM seguridad_permiso p
                                 WHERE p.permiso_menuid = m.menu_id
                                   AND p.permiso_estado = 'A'
                                   AND p.permiso_ver = 'S')");

        /* Roles activos que no usa nadie: cada uno es superficie de
           permisos que hay que mantener sin que sirva a ninguna persona. */
        $rolesVacios = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_rol r
              WHERE r.rol_estado = 'A'
                AND NOT EXISTS (SELECT 1 FROM seguridad_usuario u
                                 WHERE u.usuario_rolid = r.rol_id
                                   AND u.usuario_estado = 'A')");

        /* Accesos fallidos recientes. Se cuentan sólo los fallidos:
           mezclarlos con los correctos daría un número que no significa
           nada. */
        $fallidos = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_intento_acceso
              WHERE intento_exito = 0 AND intento_fecha >= NOW() - INTERVAL 7 DAY");

        $bloqueados = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_usuario
              WHERE usuario_tienebloqueo = 'S' AND usuario_estado = 'A'");

        return [
            'sin2fa'          => $sin2fa,
            'vistasHuerfanas' => $vistasHuerfanas,
            'rolesVacios'     => $rolesVacios,
            'fallidos'        => $fallidos,
            'bloqueados'      => $bloqueados,
        ];
    }

    /** El pulso del ecosistema, con su contexto. */
    public function pulso(): array
    {
        $usuarios = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_usuario WHERE usuario_estado = 'A'");

        $con2fa = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_usuario
              WHERE usuario_estado = 'A' AND usuario_2fa_estado = 'A'");

        return [
            'usuarios' => $usuarios,
            'con2fa'   => $con2fa,
            'cobertura2fa' => $usuarios > 0 ? (int)round($con2fa * 100 / $usuarios) : 0,
            'roles' => (int)$this->escalar(
                "SELECT COUNT(1) FROM seguridad_rol WHERE rol_estado = 'A'"),
            'vistas' => (int)$this->escalar(
                "SELECT COUNT(1) FROM seguridad_menu WHERE menu_estado = 'A'"),
            'permisos' => (int)$this->escalar(
                "SELECT COUNT(1) FROM seguridad_permiso WHERE permiso_estado = 'A'"),
            'sedes' => (int)$this->escalar(
                "SELECT COUNT(1) FROM general_sede"),
        ];
    }

    /**
     * Los últimos accesos fallidos, para poder mirar de dónde vienen.
     *
     * La IP se guarda en binario (VARBINARY 16) para que quepan IPv4 e
     * IPv6 en la misma columna; se convierte a texto aquí y no en la vista.
     */
    public function ultimosFallidos(int $limite = 8): array
    {
        $limite = max(1, min(50, $limite));

        $filas = $this->filas(
            "SELECT intento_usuario, intento_ip, intento_fecha
               FROM seguridad_intento_acceso
              WHERE intento_exito = 0
              ORDER BY intento_fecha DESC
              LIMIT " . $limite);

        foreach ($filas as &$f) {
            $f['ip'] = $f['intento_ip'] !== null
                ? (inet_ntop($f['intento_ip']) ?: '—')
                : '—';
        }

        return $filas;
    }
    public function resumen(): array
    {
        return [
            ['valor' => $this->escalar("SELECT COUNT(1) FROM seguridad_usuario WHERE usuario_estado='A'"),
             'etiqueta' => 'Usuarios activos', 'icono' => 'fas fa-users',       'color' => 'primary'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM seguridad_rol WHERE rol_estado='A'"),
             'etiqueta' => 'Roles',            'icono' => 'fas fa-user-shield', 'color' => 'info'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM seguridad_menu WHERE menu_estado='A'"),
             'etiqueta' => 'Vistas registradas','icono' => 'fas fa-bars',       'color' => 'warning'],
            ['valor' => $this->escalar("SELECT COUNT(1) FROM seguridad_permiso WHERE permiso_estado='A'"),
             'etiqueta' => 'Permisos otorgados','icono' => 'fas fa-key',        'color' => 'success'],
        ];
    }

    /*======================================================================
      Roles
      ====================================================================*/

    public function roles(bool $soloActivos = true): array
    {
        $sql = "SELECT r.*,
                       (SELECT COUNT(1) FROM seguridad_usuario u
                         WHERE u.usuario_rolid = r.rol_id AND u.usuario_estado='A') AS usuarios,
                       (SELECT COUNT(1) FROM seguridad_permiso p
                         WHERE p.permiso_rolid = r.rol_id AND p.permiso_estado='A') AS permisos,
                       (SELECT GROUP_CONCAT(rm.rolmod_modulo ORDER BY rm.rolmod_modulo SEPARATOR ',')
                          FROM seguridad_rol_modulo rm
                         WHERE rm.rolmod_rolid = r.rol_id AND rm.rolmod_estado='A') AS modulos
                  FROM seguridad_rol r"
             . ($soloActivos ? " WHERE r.rol_estado <> 'E'" : "")
             . " ORDER BY r.rol_id";

        return $this->filas($sql);
    }

    public function rol(int $id): ?array
    {
        $r = $this->filas("SELECT * FROM seguridad_rol WHERE rol_id = :id", [':id' => $id]);
        return $r[0] ?? null;
    }

    public function guardarRol(): string
    {
        if (!puede_crear('rolList') && !puede_editar('rolList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede administrar roles.', 'error');
        }

        $id      = (int)($_POST['rol_id'] ?? 0);
        $nombre  = trim((string)($_POST['rol_nombre'] ?? ''));
        $detalle = trim((string)($_POST['rol_detalle'] ?? ''));
        $estado  = ($_POST['rol_estado'] ?? 'A') === 'A' ? 'A' : 'I';

        if ($nombre === '') {
            return $this->alerta('simple', 'Faltan datos', 'El nombre del rol es obligatorio.', 'error');
        }
        if (mb_strlen($nombre) > 20) {
            return $this->alerta('simple', 'Nombre muy largo', 'El nombre no puede superar 20 caracteres.', 'error');
        }

        if ($id > 0) {
            if (!puede_editar('rolList')) {
                return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar roles.', 'error');
            }
            if ($id === self_rol_superadmin()) {
                return $this->alerta('simple', 'Rol protegido',
                    'El Super Administrador no se puede modificar: es el rol que garantiza el acceso al sistema.', 'error');
            }

            $n = $this->escribir(
                "UPDATE seguridad_rol
                    SET rol_nombre = :n, rol_detalle = :d, rol_estado = :e,
                        rol_fechaactualizacion = NOW()
                  WHERE rol_id = :id",
                [':n' => $nombre, ':d' => $detalle, ':e' => $estado, ':id' => $id]
            );

            return $n >= 0
                ? $this->alerta('recargar', 'Rol actualizado', "El rol $nombre se actualizó correctamente.")
                : $this->alerta('simple', 'Error', 'No fue posible actualizar el rol.', 'error');
        }

        if (!puede_crear('rolList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear roles.', 'error');
        }

        $n = $this->escribir(
            "INSERT INTO seguridad_rol (rol_nombre, rol_detalle, rol_estado)
             VALUES (:n, :d, :e)",
            [':n' => $nombre, ':d' => $detalle, ':e' => $estado]
        );

        return $n > 0
            ? $this->alerta('recargar', 'Rol creado', "El rol $nombre se registró correctamente.")
            : $this->alerta('simple', 'Error', 'No fue posible crear el rol.', 'error');
    }

    public function eliminarRol(): string
    {
        if (!puede_eliminar('rolList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar roles.', 'error');
        }

        $id = (int)($_POST['rol_id'] ?? 0);

        if ($id === self_rol_superadmin()) {
            return $this->alerta('simple', 'Rol protegido',
                'El Super Administrador no se puede eliminar.', 'error');
        }

        $enUso = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_usuario WHERE usuario_rolid = :id AND usuario_estado <> 'E'",
            [':id' => $id]
        );
        if ($enUso > 0) {
            return $this->alerta('simple', 'Rol en uso',
                "No se puede eliminar: hay $enUso usuario(s) con este rol. Reasígnelos primero.", 'error');
        }

        $this->escribir("DELETE FROM seguridad_permiso   WHERE permiso_rolid = :id", [':id' => $id]);
        $this->escribir("DELETE FROM seguridad_rol_modulo WHERE rolmod_rolid = :id", [':id' => $id]);
        $n = $this->escribir("DELETE FROM seguridad_rol   WHERE rol_id       = :id", [':id' => $id]);

        return $n > 0
            ? $this->alerta('recargar', 'Rol eliminado', 'El rol y sus permisos fueron eliminados.')
            : $this->alerta('simple', 'Error', 'No fue posible eliminar el rol.', 'error');
    }

    /*======================================================================
      Modulos por rol
      ====================================================================*/

    public function modulosDelRol(int $rolid): array
    {
        $filas = $this->filas(
            "SELECT rolmod_modulo FROM seguridad_rol_modulo
              WHERE rolmod_rolid = :id AND rolmod_estado = 'A'",
            [':id' => $rolid]
        );

        return array_column($filas, 'rolmod_modulo');
    }

    public function guardarModulosRol(): string
    {
        if (!puede_editar('moduloRol')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede asignar módulos.', 'error');
        }

        $rolid    = (int)($_POST['rol_id'] ?? 0);
        $enviados = $_POST['modulos'] ?? [];
        $validos  = array_keys(ds_modulos_conocidos());

        if ($rolid <= 0) {
            return $this->alerta('simple', 'Error', 'Rol no indicado.', 'error');
        }

        $seleccion = array_values(array_intersect((array)$enviados, $validos));

        /* Se marcan como inactivos los que ya no vienen y se activan los
           enviados: asi no se pierde el historico de la fila. */
        $this->escribir(
            "UPDATE seguridad_rol_modulo SET rolmod_estado = 'I' WHERE rolmod_rolid = :id",
            [':id' => $rolid]
        );

        foreach ($seleccion as $modulo) {
            $this->escribir(
                "INSERT INTO seguridad_rol_modulo (rolmod_rolid, rolmod_modulo, rolmod_estado)
                 VALUES (:r, :m, 'A')
                 ON DUPLICATE KEY UPDATE rolmod_estado = 'A'",
                [':r' => $rolid, ':m' => $modulo]
            );
        }

        return $this->alerta('recargar', 'Módulos actualizados',
            count($seleccion) . ' módulo(s) asignados al rol.');
    }

    /*======================================================================
      Permisos: matriz rol x vista x accion
      ====================================================================*/

    /** Menus de un modulo con el permiso que el rol tiene sobre cada uno. */
    public function matrizPermisos(int $rolid, string $modulo): array
    {
        return $this->filas(
            "SELECT m.menu_id,
                    m.menu_nombre,
                    m.menu_vista,
                    m.menu_icono,
                    COALESCE(pa.menu_nombre, '')                 AS padre,
                    COALESCE(p.permiso_ver, 'N')                 AS ver,
                    COALESCE(p.permiso_crear, 'N')               AS crear,
                    COALESCE(p.permiso_editar, 'N')              AS editar,
                    COALESCE(p.permiso_eliminar, 'N')            AS eliminar,
                    COALESCE(p.permiso_exportar, 'N')            AS exportar
               FROM seguridad_menu m
               LEFT JOIN seguridad_menu pa ON pa.menu_id = m.menu_padreid
               LEFT JOIN seguridad_permiso p
                      ON p.permiso_menuid = m.menu_id
                     AND p.permiso_rolid  = :rol
                     AND p.permiso_estado = 'A'
              WHERE m.menu_estado = 'A'
                AND m.menu_modulo = :modulo
                AND m.menu_hijo  <> 'S'
                AND m.menu_vista NOT IN ('', 'No')
              ORDER BY m.menu_padreid, m.menu_orden",
            [':rol' => $rolid, ':modulo' => $modulo]
        );
    }

    public function guardarPermisos(): string
    {
        if (!puede_editar('permisoRol')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede administrar permisos.', 'error');
        }

        $rolid  = (int)($_POST['rol_id'] ?? 0);
        $modulo = (string)($_POST['modulo'] ?? '');
        $perm   = $_POST['perm'] ?? [];     // [menu_id => ['ver'=>'1', ...]]

        if ($rolid <= 0 || $modulo === '') {
            return $this->alerta('simple', 'Error', 'Rol o módulo no indicados.', 'error');
        }

        if ($rolid === self_rol_superadmin()) {
            return $this->alerta('simple', 'Rol protegido',
                'El Super Administrador tiene acceso total por definición: no se le asignan permisos.', 'error');
        }

        /* Menus del modulo: solo se tocan permisos de este modulo, para no
           borrar sin querer los que el rol tenga en otros. */
        $menus = array_column($this->matrizPermisos($rolid, $modulo), 'menu_id');
        if (!$menus) {
            return $this->alerta('simple', 'Sin vistas', 'Ese módulo no tiene vistas registradas.', 'error');
        }

        $otorgados = 0;

        foreach ($menus as $menuid) {
            $a = $perm[$menuid] ?? [];

            $ver      = !empty($a['ver'])      ? 'S' : 'N';
            $crear    = !empty($a['crear'])    ? 'S' : 'N';
            $editar   = !empty($a['editar'])   ? 'S' : 'N';
            $eliminar = !empty($a['eliminar']) ? 'S' : 'N';
            /* Exportar: la accion por la que la informacion SALE del
               sistema. Ver ds_core/database/045. */
            $exportar = !empty($a['exportar']) ? 'S' : 'N';

            /* Sin lectura no hay nada que hacer en la pantalla: se retira
               la fila completa en lugar de dejar acciones huerfanas. */
            if ($ver === 'N') {
                $this->escribir(
                    "DELETE FROM seguridad_permiso
                      WHERE permiso_rolid = :r AND permiso_menuid = :m",
                    [':r' => $rolid, ':m' => $menuid]
                );
                continue;
            }

            $existe = (int)$this->escalar(
                "SELECT COUNT(1) FROM seguridad_permiso
                  WHERE permiso_rolid = :r AND permiso_menuid = :m",
                [':r' => $rolid, ':m' => $menuid]
            );

            if ($existe > 0) {
                $this->escribir(
                    "UPDATE seguridad_permiso
                        SET permiso_ver = :v, permiso_crear = :c,
                            permiso_editar = :e, permiso_eliminar = :d,
                            permiso_exportar = :x,
                            permiso_estado = 'A'
                      WHERE permiso_rolid = :r AND permiso_menuid = :m",
                    [':v' => $ver, ':c' => $crear, ':e' => $editar, ':d' => $eliminar,
                     ':x' => $exportar, ':r' => $rolid, ':m' => $menuid]
                );
            } else {
                $this->escribir(
                    "INSERT INTO seguridad_permiso
                        (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
                         permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
                     VALUES (:r, :m, :v, :c, :e, :d, :x, 'A')",
                    [':r' => $rolid, ':m' => $menuid, ':v' => $ver, ':c' => $crear,
                     ':e' => $editar, ':d' => $eliminar, ':x' => $exportar]
                );
            }

            $otorgados++;
        }

        return $this->alerta('recargar', 'Permisos guardados',
            "$otorgados vista(s) habilitadas para este rol en el módulo.");
    }

    /*======================================================================
      Usuarios
      ====================================================================*/

    public function usuarios(): array
    {
        $filas = $this->filas(
            "SELECT u.usuario_id, u.usuario_usuario, u.usuario_rolid,
                    u.usuario_estado, u.usuario_tienebloqueo, u.usuario_fechacreacion,
                    u.usuario_2fa_estado, u.usuario_fechacambioclave,
                    r.rol_nombre,
                    COALESCE(e.empleado_nombre, '') AS empleado,
                    COALESCE(e.empleado_correo, '') AS correo,
                    COALESCE(e.empleado_celular, '') AS celular,
                    " . ($this->columnaExiste('seguridad_usuario', 'usuario_todassedes') ? 'u.usuario_todassedes' : "IF(u.usuario_rolid IN (1, 2), 'S', 'N')") . " AS usuario_todassedes,
                    (SELECT GROUP_CONCAT(sd.sede_nombre ORDER BY sd.sede_nombre SEPARATOR ', ')
                       FROM seguridad_usuario_sede us JOIN general_sede sd ON sd.sede_id = us.usuariosede_sedeid
                      WHERE us.usuariosede_usuarioid = u.usuario_id) AS sedes_nombres
               FROM seguridad_usuario u
               LEFT JOIN seguridad_rol   r ON r.rol_id      = u.usuario_rolid
               LEFT JOIN sujeto_empleado e ON e.empleado_id = u.usuario_empleadoid
              WHERE u.usuario_estado <> 'E'
              ORDER BY u.usuario_usuario"
        );
        /* Quien ve algunas sedes, sólo a los usuarios de esas sedes. */
        return array_values(array_filter($filas, fn($u) => $this->usuarioEnMiAlcance($u)));
    }

    public function usuario(int $id): ?array
    {
        $r = $this->filas("SELECT * FROM seguridad_usuario WHERE usuario_id = :id", [':id' => $id]);
        return $r[0] ?? null;
    }

    public function empleadosSinUsuario(int $usuarioActual = 0): array
    {
        return $this->filas(
            "SELECT e.empleado_id,
                    COALESCE(e.empleado_nombre, CONCAT('Empleado #', e.empleado_id)) AS nombre,
                    COALESCE(e.empleado_correo, '') AS correo, COALESCE(e.empleado_celular, '') AS celular,
                    COALESCE(e.empleado_sedeid, 0) AS sede
               FROM sujeto_empleado e
              WHERE e.empleado_estado = 'A'
                AND (NOT EXISTS (SELECT 1 FROM seguridad_usuario u
                                  WHERE u.usuario_empleadoid = e.empleado_id
                                    AND u.usuario_estado <> 'E')
                     OR EXISTS (SELECT 1 FROM seguridad_usuario u2
                                 WHERE u2.usuario_empleadoid = e.empleado_id
                                   AND u2.usuario_id = :actual))
              ORDER BY nombre",
            [':actual' => $usuarioActual]
        );
    }

    /*======================================================================
      Sedes de los usuarios (migración 072)
      ======================================================================
      Cada usuario ve la información de sus sedes: una, varias o todas
      (sedes_de_la_sesion() en ds_core/inc/seguridad.php). Aquí se asignan.

      QUIÉN ADMINISTRA A QUIÉN
      Quien sólo ve algunas sedes, sólo ve y administra a los usuarios de
      esas sedes (y a los que aún no tienen ninguna, para poder asignarles
      la suya). Nunca a quien ve todas ni al superadministrador. Y sólo
      reparte sus sedes: «todas» la da únicamente quien ve todas. Sin esto,
      un administrador de una sede se daría a sí mismo —o a otro— el resto.
      ====================================================================*/

    /** Ids de las sedes asignadas a un usuario. */
    private function sedesAsignadas(int $id): array
    {
        return array_map('intval', array_column($this->filas(
            "SELECT usuariosede_sedeid FROM seguridad_usuario_sede WHERE usuariosede_usuarioid = :id", [':id' => $id]),
            'usuariosede_sedeid'));
    }

    /** ¿Ve todas las sedes? (superadministrador o «todas las sedes»). */
    private function veTodas(array $u): bool
    {
        return (int)($u['usuario_rolid'] ?? 0) === self_rol_superadmin() || ($u['usuario_todassedes'] ?? 'N') === 'S';
    }

    /** ¿Puede la sesión ver y administrar a este usuario? */
    public function usuarioEnMiAlcance(array $u): bool
    {
        $mias = sedes_de_la_sesion();
        if ($mias === null) { return true; }
        if ($this->veTodas($u)) { return false; }
        $suyas = $this->sedesAsignadas((int)$u['usuario_id']);
        return !$suyas || array_intersect($suyas, $mias);
    }

    /** Sedes que la sesión puede asignar: [sede_id => nombre]. */
    public function sedesAsignables(): array
    {
        $filas = $this->filas("SELECT sede_id, sede_nombre FROM general_sede WHERE "
                              . sql_sedes_de_la_sesion('sede_id') . " ORDER BY sede_nombre");
        return array_column($filas, 'sede_nombre', 'sede_id');
    }

    /** Las sedes de un usuario para el formulario: ['todas' => bool, 'ids' => [...]]. */
    public function sedesDeUsuario(int $id): array
    {
        $u = $this->usuario($id);
        return ['todas' => $u ? $this->veTodas($u) : false, 'ids' => $id > 0 ? $this->sedesAsignadas($id) : []];
    }

    /**
     * Lee y valida las sedes del formulario. Devuelve ['todas' => 'S'|'N',
     * 'ids' => [...]] con las que quedarán, o ['error' => [título, texto]].
     */
    private function sedesDelFormulario(int $id, int $rolid): array
    {
        $mias  = sedes_de_la_sesion();
        $todas = ($_POST['sedes_modo'] ?? '') === 'todas' || $rolid === self_rol_superadmin();
        $existentes = array_map('intval', array_column($this->filas("SELECT sede_id FROM general_sede"), 'sede_id'));
        $pedidas = array_values(array_intersect(array_unique(array_map('intval', (array)($_POST['sedes'] ?? []))), $existentes));

        if ($todas && $mias !== null) {
            return ['error' => ['Acción no permitida', 'Sólo quien ve todas las sedes puede asignar «Todas las sedes».']];
        }
        if ($mias !== null && array_diff($pedidas, $mias)) {
            return ['error' => ['Acción no permitida', 'Sólo puede asignar las sedes que usted tiene.']];
        }
        /* Las sedes del usuario que quien edita no ve se conservan: no se
           quitan sin saberlo. */
        $ids = $pedidas;
        if ($mias !== null && $id > 0) {
            $ids = array_values(array_unique(array_merge(array_diff($this->sedesAsignadas($id), $mias), $pedidas)));
        }
        if (!$todas && !$ids) {
            return ['error' => ['Falta la sede', 'Elija al menos una sede o «Todas las sedes»: sin sede el usuario no vería ninguna información.']];
        }
        sort($ids);
        return ['todas' => $todas ? 'S' : 'N', 'ids' => $ids];
    }

    /** Guarda las sedes de un usuario (marca «todas» y filas). */
    private function guardarSedesUsuario(int $id, array $sedes): void
    {
        try {
            $this->escribir("UPDATE seguridad_usuario SET usuario_todassedes = :t WHERE usuario_id = :id",
                            [':t' => $sedes['todas'], ':id' => $id]);
        } catch (\Throwable $e) {
            error_log('[DS usuarios] sin la migración 072: ' . $e->getMessage());
        }
        $this->escribir("DELETE FROM seguridad_usuario_sede WHERE usuariosede_usuarioid = :id", [':id' => $id]);
        foreach ($sedes['ids'] as $sede) {
            $this->escribir("INSERT INTO seguridad_usuario_sede (usuariosede_usuarioid, usuariosede_sedeid) VALUES (:u, :s)",
                            [':u' => $id, ':s' => (int)$sede]);
        }
    }

    /** ¿Existe la columna? (para funcionar antes y después de una migración) */
    private function columnaExiste(string $tabla, string $columna): bool
    {
        static $cache = [];
        return $cache["$tabla.$columna"] ??= (int)$this->escalar(
            "SELECT COUNT(1) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c",
            [':t' => $tabla, ':c' => $columna]) > 0;
    }

    public function guardarUsuario(): string
    {
        $id = (int)($_POST['usuario_id'] ?? 0);

        if ($id > 0 && !puede_editar('usuarioList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar usuarios.', 'error');
        }
        if ($id === 0 && !puede_crear('usuarioList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear usuarios.', 'error');
        }

        $usuario  = trim((string)($_POST['usuario_usuario'] ?? ''));
        $rolid    = (int)($_POST['usuario_rolid'] ?? 0);
        $empleado = (int)($_POST['usuario_empleadoid'] ?? 0);
        $estado   = ($_POST['usuario_estado'] ?? 'A') === 'A' ? 'A' : 'I';
        $bloqueo  = ($_POST['usuario_tienebloqueo'] ?? 'N') === 'S' ? 'S' : 'N';
        $clave    = (string)($_POST['usuario_clave'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9]{4,20}$/', $usuario)) {
            return $this->alerta('simple', 'Usuario no válido',
                'Debe tener entre 4 y 20 caracteres, solo letras y números.', 'error');
        }
        if ($rolid <= 0) {
            return $this->alerta('simple', 'Faltan datos', 'Seleccione un rol.', 'error');
        }

        /* Solo el Super Administrador puede otorgar el rol de Super
           Administrador: evita una escalada de privilegios desde Core. */
        if ($rolid === self_rol_superadmin() && !es_superadministrador()) {
            return $this->alerta('simple', 'Acción no permitida',
                'Solo un Super Administrador puede asignar ese rol.', 'error');
        }

        $duplicado = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_usuario
              WHERE usuario_usuario = :u AND usuario_id <> :id",
            [':u' => $usuario, ':id' => $id]
        );
        if ($duplicado > 0) {
            return $this->alerta('simple', 'Usuario repetido',
                "Ya existe un usuario llamado $usuario.", 'error');
        }

        /* Editar a un Super Administrador equivale a poder entrar como él:
           bastaría con ponerle otra contraseña. Así que sólo otro Super
           Administrador puede hacerlo, por mucho permiso de edición que
           tenga el rol sobre usuarioList. */
        if ($id > 0) {
            $actual = $this->usuario($id);
            if ($actual === null) {
                return $this->alerta('simple', 'Error', 'El usuario no existe.', 'error');
            }
            if ((int)$actual['usuario_rolid'] === self_rol_superadmin() && !es_superadministrador()) {
                return $this->alerta('simple', 'Usuario protegido',
                    'Sólo un Super Administrador puede modificar la cuenta de otro Super Administrador.', 'error');
            }
            if (!$this->usuarioEnMiAlcance($actual)) {
                return $this->alerta('simple', 'Fuera de sus sedes',
                    'Ese usuario no es de sus sedes: no puede modificarlo.', 'error');
            }
        }

        $sedes = $this->sedesDelFormulario($id, $rolid);
        if (isset($sedes['error'])) {
            return $this->alerta('simple', $sedes['error'][0], $sedes['error'][1], 'error');
        }

        if ($id > 0) {
            $campos = "usuario_usuario = :u, usuario_rolid = :r,
                       usuario_empleadoid = :e, usuario_estado = :s,
                       usuario_tienebloqueo = :b, usuario_fechaactualizado = NOW()";
            $params = [':u' => $usuario, ':r' => $rolid,
                       ':e' => $empleado > 0 ? $empleado : null,
                       ':s' => $estado, ':b' => $bloqueo, ':id' => $id];

            /* La contrasena solo se toca si se escribio una nueva. */
            if ($clave !== '') {
                if (!clave_valida($clave, $motivo, $usuario)) {
                    return $this->alerta('simple', 'Contraseña no válida', $motivo, 'error');
                }
                $campos .= ", usuario_clave = :c, usuario_fechacambioclave = NOW()";
                $params[':c'] = password_hash($clave, PASSWORD_DEFAULT);
            }

            $n = $this->escribir("UPDATE seguridad_usuario SET $campos WHERE usuario_id = :id", $params);
            if ($n >= 0) { $this->guardarSedesUsuario($id, $sedes); }

            return $n >= 0
                ? $this->alerta('redireccionar', 'Usuario actualizado',
                    "El usuario $usuario se actualizó correctamente.", 'success', APP_URL . 'usuarioList/')
                : $this->alerta('simple', 'Error', 'No fue posible actualizar el usuario.', 'error');
        }

        /*
        | Alta con envío de credenciales (respuesta=json, el formulario de
        | alta): sin contraseña escrita se genera una, los destinos se
        | validan antes de crear nada, y la respuesta trae lo mismo que
        | restablecerClave para mostrar el resultado y abrir WhatsApp. El
        | usuario nace con usuario_cambiaclave = 'S': al entrar se le pide
        | cambiarla (clave_exigir_cambio_si_pendiente).
        */
        $conEnvio = ($_POST['respuesta'] ?? '') === 'json';
        if ($conEnvio && $clave === '') {
            $clave = $this->claveAleatoria($usuario);
        }

        if (!clave_valida($clave, $motivo, $usuario)) {
            return $this->alerta('simple', 'Contraseña no válida', $motivo, 'error');
        }

        $destinos = $conEnvio ? $this->destinosEnvio() : [];
        if (isset($destinos['error'])) {
            return $this->alerta('simple', $destinos['error'][0], $destinos['error'][1], 'error');
        }

        $n = $this->escribir(
            "INSERT INTO seguridad_usuario
                (usuario_usuario, usuario_rolid, usuario_empleadoid, usuario_clave,
                 usuario_estado, usuario_tienebloqueo, usuario_cambiaclave)
             VALUES (:u, :r, :e, :c, :s, :b, 'S')",
            [':u' => $usuario, ':r' => $rolid,
             ':e' => $empleado > 0 ? $empleado : null,
             ':c' => password_hash($clave, PASSWORD_DEFAULT),
             ':s' => $estado, ':b' => $bloqueo]
        );
        if ($n > 0) {
            $this->guardarSedesUsuario((int)$this->escalar("SELECT usuario_id FROM seguridad_usuario WHERE usuario_usuario = :u", [':u' => $usuario]), $sedes);
        }

        if ($n > 0 && $conEnvio) {
            $nombre = $empleado > 0
                ? (string)$this->escalar("SELECT empleado_nombre FROM sujeto_empleado WHERE empleado_id = :e", [':e' => $empleado])
                : '';
            error_log('[DS usuarios] ' . ($_SESSION['usuario'] ?? '?') . ' creó el usuario ' . $usuario
                      . ($destinos['correo'] ? ' · correo' : '') . ($destinos['whatsapp'] ? ' · WhatsApp' : ''));
            return json_encode($this->entregarCredenciales($usuario, $nombre, $clave, $destinos, true)
                               + ['url' => APP_URL . 'usuarioList/'], JSON_UNESCAPED_UNICODE);
        }

        return $n > 0
            ? $this->alerta('redireccionar', 'Usuario creado',
                "El usuario $usuario fue creado correctamente.", 'success', APP_URL . 'usuarioList/')
            : $this->alerta('simple', 'Error', 'No fue posible crear el usuario.', 'error');
    }

    /*----------------------------------------------------------------------
      Restablecer la contraseña de otro usuario y enviársela
      ----------------------------------------------------------------------
      Puerta AJAX «restablecerClave». La contraseña la escribe quien
      administra o, si la deja vacía, se genera una aleatoria. Se guarda
      (hash), se marca usuario_cambiaclave = 'S' y, si se pide:

        · CORREO: sale desde el servidor con mail(), en UTF-8, a la
          dirección indicada (por omisión, la de su ficha de empleado).
        · WHATSAPP: el servidor NO envía nada; devuelve el enlace wa.me con
          el mensaje escrito, y es el teléfono de quien administra el que
          lo manda al pulsarlo.

      La contraseña en claro vuelve UNA vez en la respuesta, sólo a quien
      tiene permiso de editar usuarios, para copiarla o mandarla por
      WhatsApp. No se guarda en ningún otro sitio.

      Las mismas reglas que guardarUsuario: permiso de editar, la cuenta
      de un Super Administrador sólo la toca otro, y la clave pasa por
      clave_valida(). La propia no se restablece aquí: para eso está
      «Cambiar contraseña», que pide la actual.
      ----------------------------------------------------------------------*/
    public function restablecerClave(): string
    {
        $error = fn (string $titulo, string $texto) => json_encode(
            ['ok' => false, 'tipo' => 'simple', 'icono' => 'error', 'titulo' => $titulo, 'texto' => $texto],
            JSON_UNESCAPED_UNICODE);

        if (!puede_editar('usuarioList')) {
            return $error('Acceso denegado', 'Su rol no puede cambiar contraseñas de usuarios.');
        }

        $id = (int)($_POST['usuario_id'] ?? 0);
        if ($id === (int)($_SESSION['usuarioid'] ?? 0)) {
            return $error('Es su propia cuenta', 'Para su contraseña use «Cambiar contraseña» en el menú de su usuario: pide la actual.');
        }

        $u = $this->fila("SELECT u.usuario_id, u.usuario_usuario, u.usuario_rolid, u.usuario_estado,
                                 COALESCE(e.empleado_nombre, '') AS nombre
                            FROM seguridad_usuario u
                            LEFT JOIN sujeto_empleado e ON e.empleado_id = u.usuario_empleadoid
                           WHERE u.usuario_id = :id AND u.usuario_estado <> 'E'", [':id' => $id]);
        if (!$u) {
            return $error('No encontrado', 'Ese usuario no existe o fue dado de baja.');
        }
        $completo = $this->usuario($id);
        if (!$completo || !$this->usuarioEnMiAlcance($completo)) {
            return $error('Fuera de sus sedes', 'Ese usuario no es de sus sedes.');
        }
        if ((int)$u['usuario_rolid'] === self_rol_superadmin() && !es_superadministrador()) {
            return $error('Usuario protegido', 'Sólo un Super Administrador puede cambiar la contraseña de otro Super Administrador.');
        }

        $clave = (string)($_POST['clave'] ?? '');
        if ($clave === '') {
            $clave = $this->claveAleatoria((string)$u['usuario_usuario']);
        }
        if (!clave_valida($clave, $motivo, (string)$u['usuario_usuario'])) {
            return $error('Contraseña no válida', (string)$motivo);
        }

        /* Destinos, validados ANTES de tocar la contraseña: con un correo
           mal escrito no se cambia nada y se corrige. */
        $destinos = $this->destinosEnvio();
        if (isset($destinos['error'])) {
            return $error($destinos['error'][0], $destinos['error'][1]);
        }

        $n = $this->escribir("UPDATE seguridad_usuario
                                 SET usuario_clave = :c, usuario_fechacambioclave = NOW(),
                                     usuario_cambiaclave = 'S', usuario_fechaactualizado = NOW()
                               WHERE usuario_id = :id",
                             [':c' => password_hash($clave, PASSWORD_DEFAULT), ':id' => $id]);
        if ($n < 1) {
            return $error('No se pudo guardar', 'La base de datos rechazó el cambio. Inténtelo de nuevo.');
        }

        /* Rastro de quién lo hizo, sin la contraseña. */
        error_log('[DS usuarios] ' . ($_SESSION['usuario'] ?? '?') . ' restableció la contraseña de '
                  . $u['usuario_usuario'] . ($destinos['correo'] ? ' · correo' : '') . ($destinos['whatsapp'] ? ' · WhatsApp' : ''));

        return json_encode($this->entregarCredenciales((string)$u['usuario_usuario'], (string)$u['nombre'], $clave, $destinos, false),
                           JSON_UNESCAPED_UNICODE);
    }

    /**
     * Por dónde enviar una contraseña (POST enviar_correo/correo y
     * enviar_whatsapp/celular), validado. ['error' => [título, texto]] si
     * algo no sirve, para responder ANTES de guardar nada.
     */
    private function destinosEnvio(): array
    {
        $porCorreo   = ($_POST['enviar_correo'] ?? '') === 'S';
        $porWhatsapp = ($_POST['enviar_whatsapp'] ?? '') === 'S';
        $correo  = trim((string)($_POST['correo'] ?? ''));
        $celular = preg_replace('/\D/', '', (string)($_POST['celular'] ?? ''));
        if ($porCorreo && (!filter_var($correo, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n,;]/', $correo))) {
            return ['error' => ['Correo no válido', 'Revise la dirección de correo.']];
        }
        /* Ecuador: 09XXXXXXXX (10 dígitos) o ya con el 593 delante. */
        $internacional = preg_match('/^09\d{8}$/', $celular) ? '593' . substr($celular, 1)
                       : (preg_match('/^5939\d{8}$/', $celular) ? $celular : '');
        if ($porWhatsapp && $internacional === '') {
            return ['error' => ['Celular no válido', 'Escriba un celular de 10 dígitos que empiece por 09.']];
        }
        return ['correo' => $porCorreo, 'a' => $correo, 'whatsapp' => $porWhatsapp, 'celular' => $internacional];
    }

    /**
     * El mensaje con usuario y contraseña, enviado por correo si se pidió,
     * y la respuesta para la pantalla: la contraseña (una vez, para
     * copiarla), el resultado del correo y el enlace wa.me para WhatsApp.
     * $nuevo: alta del usuario o contraseña restablecida.
     */
    private function entregarCredenciales(string $usuario, string $nombre, string $clave, array $destinos, bool $nuevo): array
    {
        $org    = ds_nombre_organizacion();
        $nombre = trim(html_entity_decode($nombre, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $mensaje = "Hola " . ($nombre !== '' ? $nombre : $usuario) . ",\n\n"
                 . ($nuevo ? "Se creó su usuario de DigiSports" : "Se restableció su contraseña de DigiSports")
                 . ($org !== '' ? " (" . $org . ")" : '') . ".\n\n"
                 . "Usuario: " . $usuario . "\n"
                 . "Contraseña: " . $clave . "\n"
                 . "Ingrese en: " . DS_HUB_URL . "\n\n"
                 . "Al ingresar, el sistema le pedirá cambiarla por una que sólo usted conozca. No la comparta.";

        $enviado = $destinos['correo']
            ? $this->enviarCorreoTexto($destinos['a'], $nuevo ? 'Su usuario de DigiSports' : 'Su contraseña de DigiSports', $mensaje)
            : false;

        return [
            'ok'       => true,
            'usuario'  => $usuario,
            'clave'    => $clave,
            'mensaje'  => $mensaje,
            'nuevo'    => $nuevo,
            'correo'   => ['pedido' => $destinos['correo'], 'enviado' => $enviado, 'a' => $destinos['correo'] ? $destinos['a'] : ''],
            'whatsapp' => ['pedido' => $destinos['whatsapp'],
                           'url'    => $destinos['whatsapp'] ? 'https://wa.me/' . $destinos['celular'] . '?text=' . rawurlencode($mensaje) : ''],
        ];
    }

    /** Una contraseña aleatoria legible (sin 0/O, 1/l/I), en tres grupos: «Kp7m-W3xq-9Fhd». */
    private function claveAleatoria(string $usuario): string
    {
        $letras = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $grupos = [];
            for ($g = 0; $g < 3; $g++) {
                $p = '';
                for ($i = 0; $i < 4; $i++) { $p .= $letras[random_int(0, strlen($letras) - 1)]; }
                $grupos[] = $p;
            }
            $clave = implode('-', $grupos);
        } while (!clave_valida($clave, $motivo, $usuario) || !preg_match('/\d/', $clave));
        return $clave;
    }

    /**
     * Correo de texto en UTF-8 con mail(), como los recibos de Arena:
     * asunto y remitente codificados (RFC 2047) para que una tilde no
     * llegue rota. false si el servidor de correo no lo acepta.
     */
    private function enviarCorreoTexto(string $para, string $asunto, string $texto): bool
    {
        if (!function_exists('mail')) { return false; }
        $org   = ds_nombre_organizacion() ?: 'DigiSports';
        $desde = ds_email_organizacion() ?: (string)ini_get('sendmail_from');
        $enc   = static fn (string $s): string => '=?UTF-8?B?' . base64_encode($s) . '?=';
        $cabeceras = ($desde !== '' ? 'From: ' . $enc($org) . ' <' . $desde . ">\r\n" : '')
                   . "MIME-Version: 1.0\r\n"
                   . "Content-Type: text/plain; charset=UTF-8\r\n"
                   . "Content-Transfer-Encoding: base64";
        /* mail() avisa con un warning si no hay servidor de correo: no debe
           colarse en la respuesta JSON. */
        return (bool)@mail($para, $enc($asunto), chunk_split(base64_encode(str_replace("\n", "\r\n", $texto))), $cabeceras);
    }

    public function eliminarUsuario(): string
    {
        if (!puede_eliminar('usuarioList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar usuarios.', 'error');
        }

        $id = (int)($_POST['usuario_id'] ?? 0);

        if ($id === (int)($_SESSION['usuarioid'] ?? 0)) {
            return $this->alerta('simple', 'Acción no permitida',
                'No puede eliminar su propio usuario.', 'error');
        }

        $datos = $this->usuario($id);
        if ($datos === null) {
            return $this->alerta('simple', 'Error', 'Usuario no encontrado.', 'error');
        }

        /* Un Super Administrador no se da de baja desde aquí.
           Antes sólo se protegía al último, pero la cuenta con acceso total
           es la que sostiene el sistema: si hace falta retirarla, primero se
           le cambia el rol y después se da de baja, de modo que la decisión
           quede en dos pasos deliberados y no en un clic. */
        if (!$this->usuarioEnMiAlcance($datos)) {
            return $this->alerta('simple', 'Fuera de sus sedes', 'Ese usuario no es de sus sedes.', 'error');
        }

        if ((int)$datos['usuario_rolid'] === self_rol_superadmin()) {
            return $this->alerta('simple', 'Usuario protegido',
                'Un Super Administrador no se puede dar de baja. Cámbiele antes el rol si necesita retirarlo.',
                'error');
        }

        /* Baja logica: conserva la trazabilidad de lo que hizo el usuario. */
        $n = $this->escribir(
            "UPDATE seguridad_usuario SET usuario_estado = 'E', usuario_fechaactualizado = NOW()
              WHERE usuario_id = :id",
            [':id' => $id]
        );

        return $n > 0
            ? $this->alerta('recargar', 'Usuario eliminado', 'El usuario fue dado de baja.')
            : $this->alerta('simple', 'Error', 'No fue posible eliminar el usuario.', 'error');
    }

    /*======================================================================
      Menus
      ====================================================================*/

    public function menus(string $modulo = ''): array
    {
        $sql = "SELECT m.*, COALESCE(pa.menu_nombre,'') AS padre,
                       (SELECT COUNT(1) FROM seguridad_permiso p
                         WHERE p.permiso_menuid = m.menu_id AND p.permiso_estado='A') AS roles
                  FROM seguridad_menu m
                  LEFT JOIN seguridad_menu pa ON pa.menu_id = m.menu_padreid
                 WHERE m.menu_estado <> 'E'"
             . ($modulo !== '' ? " AND m.menu_modulo = :modulo" : "")
             . " ORDER BY m.menu_modulo, m.menu_padreid, m.menu_orden";

        return $this->filas($sql, $modulo !== '' ? [':modulo' => $modulo] : []);
    }

    public function menu(int $id): ?array
    {
        $r = $this->filas("SELECT * FROM seguridad_menu WHERE menu_id = :id", [':id' => $id]);
        return $r[0] ?? null;
    }

    /** Menús que pueden actuar como grupo (padre) dentro de un módulo. */
    /** ¿De este menú cuelga alguna entrada? */
    public function menuTieneHijos(int $id): bool
    {
        return (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_menu
              WHERE menu_padreid = :id AND menu_estado <> 'E'", [':id' => $id]) > 0;
    }

    /**
     * Grupos disponibles como padre. Sólo cabeceras: un menú que abre una
     * pantalla no puede tener hijos, porque el menú lateral dibuja un
     * único nivel de anidamiento.
     */
    public function menusPadre(string $modulo): array
    {
        return $this->filas(
            "SELECT menu_id, menu_nombre
               FROM seguridad_menu
              WHERE menu_modulo = :m AND menu_estado = 'A'
                AND menu_padreid = 0 AND menu_hijo = 'S'
              ORDER BY menu_orden",
            [':m' => $modulo]
        );
    }

    /**
     * Vistas del módulo que todavía no tienen menú.
     *
     * Es lo que se ofrece al crear: así el menú nunca puede apuntar a una
     * ruta inexistente ni duplicar una ya registrada. Al editar se incluye
     * la vista actual para que siga siendo seleccionable.
     */
    public function vistasDisponibles(string $modulo, string $incluir = ''): array
    {
        $todas = ds_vistas_modulo($modulo);
        if (!$todas) {
            return [];
        }

        $usadas = array_column(
            $this->filas(
                "SELECT menu_vista FROM seguridad_menu
                  WHERE menu_modulo = :m AND menu_estado <> 'E'",
                [':m' => $modulo]
            ),
            'menu_vista'
        );

        $libres = array_values(array_diff($todas, $usadas));

        if ($incluir !== '' && in_array($incluir, $todas, true) && !in_array($incluir, $libres, true)) {
            $libres[] = $incluir;
            sort($libres);
        }

        return $libres;
    }

    public function guardarMenu(): string
    {
        $id = (int)($_POST['menu_id'] ?? 0);

        if ($id > 0 && !puede_editar('menuList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar menús.', 'error');
        }
        if ($id === 0 && !puede_crear('menuList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear menús.', 'error');
        }

        $modulo  = trim((string)($_POST['menu_modulo'] ?? ''));
        $nombre  = trim((string)($_POST['menu_nombre'] ?? ''));
        $vista   = trim((string)($_POST['menu_vista'] ?? ''));
        $icono   = trim((string)($_POST['menu_icono'] ?? ''));
        $orden   = (int)($_POST['menu_orden'] ?? 0);
        $padre   = (int)($_POST['menu_padreid'] ?? 0);
        $estado  = ($_POST['menu_estado'] ?? 'A') === 'A' ? 'A' : 'I';

        /* Un grupo es sólo una cabecera que agrupa otros menús: no abre
           ninguna pantalla, así que no lleva vista ni permiso propio. */
        $esGrupo = ($_POST['menu_grupo'] ?? '') === '1';

        if ($nombre === '' || $modulo === '') {
            return $this->alerta('simple', 'Faltan datos',
                'Módulo y nombre son obligatorios.', 'error');
        }

        if (!isset(ds_modulos_conocidos()[$modulo])) {
            return $this->alerta('simple', 'Módulo no válido',
                'El módulo indicado no pertenece al ecosistema.', 'error');
        }

        if ($esGrupo) {
            /* Un grupo cuelga siempre del primer nivel: el menú lateral
               sólo dibuja un nivel de anidamiento. */
            $vista = '';
            $padre = 0;
        } else {
            if ($vista === '') {
                return $this->alerta('simple', 'Faltan datos',
                    'Indique la vista que abre este menú, o márquelo como grupo.', 'error');
            }

            /* La validación que evita menús rotos: la vista debe existir en la
               lista blanca del módulo destino. */
            if (!ds_vista_existe($modulo, $vista)) {
                $disponibles = ds_vistas_modulo($modulo);
                /* Las llaves son necesarias: sin ellas PHP toma el » de cierre
                   como parte del nombre de la variable. */
                $texto = $disponibles
                    ? "La vista «{$vista}» no existe en el módulo " . ds_modulos_conocidos()[$modulo] .
                      ". Debe estar declarada en su archivo config/vistas.php."
                    : "El módulo " . ds_modulos_conocidos()[$modulo] . " todavía no publica su lista de vistas.";

                return $this->alerta('simple', 'Vista inexistente', $texto, 'error');
            }

            /* Una vista no puede tener dos menús: haría ambiguo el permiso. */
            $duplicada = (int)$this->escalar(
                "SELECT COUNT(1) FROM seguridad_menu
                  WHERE menu_modulo = :m AND menu_vista = :v
                    AND menu_estado <> 'E' AND menu_id <> :id",
                [':m' => $modulo, ':v' => $vista, ':id' => $id]
            );
            if ($duplicada > 0) {
                return $this->alerta('simple', 'Vista ya registrada',
                    "Ya existe un menú que apunta a «{$vista}» en ese módulo.", 'error');
            }
        }

        /* El padre indicado tiene que ser un grupo del mismo módulo. */
        if (!$esGrupo && $padre > 0) {
            $destino = $this->menu($padre);
            if ($destino === null || $destino['menu_modulo'] !== $modulo) {
                return $this->alerta('simple', 'Grupo no válido',
                    'El grupo elegido no existe en ese módulo.', 'error');
            }
            if ((int)$destino['menu_padreid'] !== 0) {
                return $this->alerta('simple', 'Jerarquía no válida',
                    'Sólo se admite un nivel de agrupación.', 'error');
            }
        }

        if ($icono === '') {
            $icono = 'nav-icon far fa-circle';
        }

        $hijo = $esGrupo ? 'S' : 'N';

        if ($id > 0) {
            /* Un menú no puede ser su propio padre. */
            if ($padre === $id) {
                return $this->alerta('simple', 'Jerarquía no válida',
                    'Un menú no puede depender de sí mismo.', 'error');
            }

            /* Dejar de ser grupo con hijos colgando los dejaría huérfanos:
               desaparecerían del menú sin que nadie lo note. */
            $conHijos = (int)$this->escalar(
                "SELECT COUNT(1) FROM seguridad_menu
                  WHERE menu_padreid = :id AND menu_estado <> 'E'", [':id' => $id]);

            if (!$esGrupo && $conHijos > 0) {
                return $this->alerta('simple', 'El grupo tiene contenido',
                    "«{$nombre}» agrupa {$conHijos} menú(s). Muévalos a otro grupo antes de convertirlo en una entrada normal.",
                    'error');
            }

            $n = $this->escribir(
                "UPDATE seguridad_menu
                    SET menu_modulo = :mo, menu_nombre = :n, menu_vista = :v,
                        menu_icono = :i, menu_orden = :o, menu_padreid = :p,
                        menu_hijo = :h, menu_estado = :e
                  WHERE menu_id = :id",
                [':mo' => $modulo, ':n' => $nombre, ':v' => $vista, ':i' => $icono,
                 ':o' => $orden, ':p' => $padre, ':h' => $hijo, ':e' => $estado, ':id' => $id]
            );

            return $n >= 0
                ? $this->alerta('redireccionar', 'Menú actualizado',
                    "El menú $nombre se actualizó correctamente.", 'success', APP_URL . 'menuList/')
                : $this->alerta('simple', 'Error', 'No fue posible actualizar el menú.', 'error');
        }

        $n = $this->escribir(
            "INSERT INTO seguridad_menu
                (menu_modulo, menu_nombre, menu_vista, menu_icono, menu_orden,
                 menu_padreid, menu_hijo, menu_estado)
             VALUES (:mo, :n, :v, :i, :o, :p, :h, :e)",
            [':mo' => $modulo, ':n' => $nombre, ':v' => $vista, ':i' => $icono,
             ':o' => $orden, ':p' => $padre, ':h' => $hijo, ':e' => $estado]
        );

        $aviso = $esGrupo
            ? "El grupo $nombre quedó creado. Ahora puede colgar menús de él."
            : "El menú $nombre quedó registrado. Asígnelo a los roles desde Permisos.";

        return $n > 0
            ? $this->alerta('redireccionar', 'Menú creado', $aviso, 'success', APP_URL . 'menuList/')
            : $this->alerta('simple', 'Error', 'No fue posible crear el menú.', 'error');
    }

    public function eliminarMenu(): string
    {
        if (!puede_eliminar('menuList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar menús.', 'error');
        }

        $id = (int)($_POST['menu_id'] ?? 0);
        $datos = $this->menu($id);

        if ($datos === null) {
            return $this->alerta('simple', 'Error', 'Menú no encontrado.', 'error');
        }

        /* Los menús del propio Core sostienen la administración: si se
           borran, nadie puede volver a conceder permisos. */
        if ($datos['menu_modulo'] === 'core') {
            return $this->alerta('simple', 'Menú protegido',
                'Los menús del módulo Core no se pueden eliminar: son los que permiten administrar el ecosistema.', 'error');
        }

        $hijos = (int)$this->escalar(
            "SELECT COUNT(1) FROM seguridad_menu WHERE menu_padreid = :id AND menu_estado <> 'E'",
            [':id' => $id]
        );
        if ($hijos > 0) {
            return $this->alerta('simple', 'Menú con dependencias',
                "No se puede eliminar: $hijos menú(s) dependen de este como grupo.", 'error');
        }

        $this->escribir("DELETE FROM seguridad_permiso WHERE permiso_menuid = :id", [':id' => $id]);
        $n = $this->escribir("DELETE FROM seguridad_menu WHERE menu_id = :id", [':id' => $id]);

        return $n > 0
            ? $this->alerta('recargar', 'Menú eliminado',
                'El menú y los permisos asociados fueron eliminados.')
            : $this->alerta('simple', 'Error', 'No fue posible eliminar el menú.', 'error');
    }

    /*======================================================================
      Sedes — dato compartido por todo el ecosistema
      ====================================================================*/

    /**
     * Las sedes las administra Core y las consumen los módulos: Basketball
     * asigna alumnos y horarios, Arena registra instalaciones. Por eso la
     * baja comprueba dependencias en todos ellos, no sólo en uno.
     */
    public function sedes(): array
    {
        return $this->filas(
            "SELECT s.*, e.escuela_nombre,
                    c.catalogo_descripcion AS tipo_nombre,
                    (SELECT COUNT(1) FROM sujeto_alumno a
                      WHERE a.alumno_sedeid = s.sede_id AND a.alumno_estado = 'A') AS alumnos,
                    (SELECT COUNT(1) FROM sujeto_empleado em
                      WHERE em.empleado_sedeid = s.sede_id AND em.empleado_estado = 'A') AS empleados,
                    (SELECT COUNT(1) FROM dsa_instalacion i
                      WHERE i.instalacion_sedeid = s.sede_id AND i.instalacion_estado = 'A') AS instalaciones
               FROM general_sede s
               LEFT JOIN general_escuela e ON e.escuela_id = s.sede_escuelaid
               LEFT JOIN general_tabla_catalogo c
                      ON c.catalogo_valor = s.sede_tipoingreso
                     AND c.catalogo_tablaid = (SELECT tabla_id FROM general_tabla
                                                WHERE tabla_nombre = 'sede_tipoingreso' LIMIT 1)
              ORDER BY s.sede_nombre"
        );
    }

    public function sede(int $id): ?array
    {
        $r = $this->filas("SELECT * FROM general_sede WHERE sede_id = :id", [':id' => $id]);
        return $r[0] ?? null;
    }

    public function escuelas(): array
    {
        return $this->filas("SELECT escuela_id, escuela_nombre FROM general_escuela ORDER BY escuela_nombre");
    }

    /** Valores activos de un catálogo del sistema, por su nombre. */
    public function valoresPorNombre(string $tabla): array
    {
        return $this->filas(
            "SELECT c.catalogo_valor, c.catalogo_descripcion
               FROM general_tabla_catalogo c
               JOIN general_tabla t ON t.tabla_id = c.catalogo_tablaid
              WHERE t.tabla_nombre = :t AND c.catalogo_estado = 'A'
              ORDER BY c.catalogo_descripcion",
            [':t' => $tabla]
        );
    }

    public function guardarSede(): string
    {
        $id = (int)($_POST['sede_id'] ?? 0);

        if ($id > 0 && !puede_editar('sedeList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar sedes.', 'error');
        }
        if ($id === 0 && !puede_crear('sedeList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear sedes.', 'error');
        }

        $escuela     = (int)($_POST['sede_escuelaid'] ?? 0);
        $nombre      = trim((string)($_POST['sede_nombre'] ?? ''));
        $tipo        = trim((string)($_POST['sede_tipoingreso'] ?? 'STF'));
        $direccion   = trim((string)($_POST['sede_direccion'] ?? ''));
        $email       = trim((string)($_POST['sede_email'] ?? ''));
        $telefono    = trim((string)($_POST['sede_telefono'] ?? ''));
        $inscripcion = round((float)str_replace(',', '.', (string)($_POST['sede_inscripcion'] ?? '0')), 2);
        $pension     = round((float)str_replace(',', '.', (string)($_POST['sede_pension'] ?? '0')), 2);

        if ($nombre === '') {
            return $this->alerta('simple', 'Faltan datos', 'El nombre de la sede es obligatorio.', 'error');
        }
        if ($escuela <= 0) {
            return $this->alerta('simple', 'Faltan datos', 'Seleccione la organización a la que pertenece.', 'error');
        }

        /* El tipo debe existir en el catálogo: es el que decide si Arena
           puede alquilar la sede. */
        $tiposValidos = array_column($this->valoresPorNombre('sede_tipoingreso'), 'catalogo_valor');
        if (!in_array($tipo, $tiposValidos, true)) {
            return $this->alerta('simple', 'Tipo no válido',
                'El tipo de sede indicado no existe en el catálogo.', 'error');
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->alerta('simple', 'Correo no válido', 'Revise la dirección de correo.', 'error');
        }
        if ($inscripcion < 0 || $pension < 0) {
            return $this->alerta('simple', 'Importe no válido', 'Los valores no pueden ser negativos.', 'error');
        }

        $duplicado = (int)$this->escalar(
            "SELECT COUNT(1) FROM general_sede WHERE sede_nombre = :n AND sede_id <> :id",
            [':n' => $nombre, ':id' => $id]
        );
        if ($duplicado > 0) {
            return $this->alerta('simple', 'Sede repetida', "Ya existe una sede llamada {$nombre}.", 'error');
        }

        /* Logo y firma propios de la sede. Si no los tiene, hereda los de
           la organización: la regla vive en ds_imagen_marca(). */
        $sedeActual = $id > 0 ? $this->sede($id) : [];

        $logo = $this->resolverImagenMarca('sede_foto', 'sede',
            (string)($sedeActual['sede_foto'] ?? ''), ($_POST['quitar_logo'] ?? '') === '1');
        if (str_starts_with($logo, '!')) {
            return $this->alerta('simple', 'Logo no válido', substr($logo, 1), 'error');
        }

        $firma = $this->resolverImagenMarca('sede_firma', 'firma_sede',
            (string)($sedeActual['sede_firma'] ?? ''), ($_POST['quitar_firma'] ?? '') === '1');
        if (str_starts_with($firma, '!')) {
            return $this->alerta('simple', 'Firma no válida', substr($firma, 1), 'error');
        }

        $params = [':e' => $escuela, ':n' => $nombre, ':t' => $tipo, ':d' => $direccion,
                   ':m' => $email, ':tel' => $telefono !== '' ? $telefono : null,
                   ':i' => $inscripcion, ':p' => $pension, ':f' => $logo, ':fi' => $firma];

        if ($id > 0) {
            $params[':id'] = $id;
            $n = $this->escribir(
                "UPDATE general_sede
                    SET sede_escuelaid = :e, sede_nombre = :n, sede_tipoingreso = :t,
                        sede_direccion = :d, sede_email = :m, sede_telefono = :tel,
                        sede_inscripcion = :i, sede_pension = :p, sede_foto = :f,
                        sede_firma = :fi
                  WHERE sede_id = :id", $params);

            return $n >= 0
                ? $this->alerta('redireccionar', 'Sede actualizada',
                    "{$nombre} se actualizó correctamente.", 'success', APP_URL . 'sedeList/')
                : $this->alerta('simple', 'Error', 'No fue posible actualizar la sede.', 'error');
        }

        $n = $this->escribir(
            "INSERT INTO general_sede
                (sede_escuelaid, sede_nombre, sede_tipoingreso, sede_direccion,
                 sede_email, sede_telefono, sede_inscripcion, sede_pension,
                 sede_foto, sede_firma)
             VALUES (:e, :n, :t, :d, :m, :tel, :i, :p, :f, :fi)", $params);

        return $n > 0
            ? $this->alerta('redireccionar', 'Sede creada',
                "{$nombre} quedó registrada y ya está disponible para los módulos.",
                'success', APP_URL . 'sedeList/')
            : $this->alerta('simple', 'Error', 'No fue posible crear la sede.', 'error');
    }

    public function eliminarSede(): string
    {
        if (!puede_eliminar('sedeList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar sedes.', 'error');
        }

        $id   = (int)($_POST['sede_id'] ?? 0);
        $sede = $this->sede($id);

        if ($sede === null) {
            return $this->alerta('simple', 'Error', 'La sede no existe.', 'error');
        }

        /* Una sede la usan varios módulos: se comprueban todos antes de
           permitir la baja, para no dejar registros huérfanos. */
        $usos = [];

        $alumnos = (int)$this->escalar(
            "SELECT COUNT(1) FROM sujeto_alumno WHERE alumno_sedeid = :id AND alumno_estado <> 'E'", [':id' => $id]);
        if ($alumnos > 0) $usos[] = "$alumnos alumno(s)";

        $empleados = (int)$this->escalar(
            "SELECT COUNT(1) FROM sujeto_empleado WHERE empleado_sedeid = :id AND empleado_estado <> 'E'", [':id' => $id]);
        if ($empleados > 0) $usos[] = "$empleados empleado(s)";

        $instalaciones = (int)$this->escalar(
            "SELECT COUNT(1) FROM dsa_instalacion WHERE instalacion_sedeid = :id AND instalacion_estado <> 'E'", [':id' => $id]);
        if ($instalaciones > 0) $usos[] = "$instalaciones instalación(es) en Arena";

        if ($usos) {
            return $this->alerta('simple', 'Sede en uso',
                'No se puede eliminar: tiene ' . implode(', ', $usos) . '. Reasígnelos primero.', 'error');
        }

        $n = $this->escribir("DELETE FROM general_sede WHERE sede_id = :id", [':id' => $id]);

        return $n > 0
            ? $this->alerta('recargar', 'Sede eliminada', "{$sede['sede_nombre']} fue eliminada.")
            : $this->alerta('simple', 'Error', 'No fue posible eliminar la sede.', 'error');
    }

    /*======================================================================
      Organización: nombre legal y logo
      ====================================================================*/

    /**
     * Guarda un logo en la carpeta de marca del núcleo.
     *
     * Devuelve el nombre del archivo, '' si no se envió imagen, o un
     * mensaje de error precedido de '!' si algo no cuadra.
     */
    private function guardarLogo(string $campo, string $prefijo): string
    {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }

        $f = $_FILES[$campo];

        if ($f['error'] !== UPLOAD_ERR_OK) {
            return '!No se pudo subir la imagen (código ' . $f['error'] . ').';
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            return '!El archivo recibido no proviene de una subida válida.';
        }
        if ($f['size'] > 2 * 1024 * 1024) {
            return '!La imagen no puede superar 2 MB.';
        }

        /* Se confía en el contenido real, no en la extensión enviada. */
        $tipo = @mime_content_type($f['tmp_name']);

        if (!in_array($tipo, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return '!Formato no admitido. Use JPG, PNG o WEBP.';
        }

        $dir = ds_marca_dir();
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return '!No existe la carpeta de marca y no se pudo crear.';
        }

        /* Siempre se reescribe como PNG plano: FPDF revienta al encontrar un
           canal alfa, y estos logos van a parar a recibos y facturas. */
        $nombre = $prefijo . '_' . date('YmdHis') . '.png';
        $error  = $this->normalizarLogo($f['tmp_name'], $dir . $nombre, $tipo);

        if ($error !== '') {
            return '!' . $error;
        }

        return $nombre;
    }

    /**
     * Aplana la imagen sobre fondo blanco y la guarda como PNG sin
     * transparencia ni entrelazado, con el lado mayor limitado a 600 px.
     *
     * Un PNG RGBA subido por el usuario dejaba a FPDF llamando a
     * gzuncompress sobre datos que no sabe separar, y con eso caía cada
     * PDF del sistema. Normalizar en la subida evita el problema de raíz.
     *
     * Devuelve '' si todo fue bien, o el motivo del fallo.
     */
    private function normalizarLogo(string $origen, string $destino, string $mime): string
    {
        $origenImg = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($origen),
            'image/png'  => @imagecreatefrompng($origen),
            'image/webp' => @imagecreatefromwebp($origen),
            default      => false,
        };

        if ($origenImg === false) {
            return 'La imagen está dañada o no se pudo leer.';
        }

        $ancho = imagesx($origenImg);
        $alto  = imagesy($origenImg);
        $lado  = max($ancho, $alto);
        $razon = $lado > 600 ? 600 / $lado : 1;

        $nuevoAncho = max(1, (int)round($ancho * $razon));
        $nuevoAlto  = max(1, (int)round($alto  * $razon));

        $destinoImg = imagecreatetruecolor($nuevoAncho, $nuevoAlto);

        /*
        | LA TRANSPARENCIA SE CONSERVA
        |
        | Aquí se pintaba SIEMPRE un rectángulo blanco antes de copiar el
        | logo encima. Con eso, un PNG con fondo transparente salía del
        | servidor con fondo blanco: la escuela subía su marca recortada y
        | el sistema se la aplanaba sin decir nada.
        |
        | Se vio en el login: el logo aparecía como un rectángulo blanco
        | sobre la tarjeta oscura. No era el archivo de la escuela —era esta
        | función—.
        |
        | El JPEG no tiene canal alfa, así que ahí el blanco sigue siendo lo
        | correcto: sin él, las zonas sin datos salen negras.
        |
        | El orden importa. alphablending en false ANTES de rellenar y de
        | copiar: así el alfa del origen se escribe tal cual en el destino en
        | vez de mezclarse con lo que hubiera debajo. savealpha en true para
        | que imagepng() lo guarde.
        */
        if ($mime === 'image/jpeg') {
            $fondo = imagecolorallocate($destinoImg, 255, 255, 255);
            imagefilledrectangle($destinoImg, 0, 0, $nuevoAncho, $nuevoAlto, $fondo);
        } else {
            imagealphablending($destinoImg, false);
            imagesavealpha($destinoImg, true);
            $fondo = imagecolorallocatealpha($destinoImg, 0, 0, 0, 127);
            imagefilledrectangle($destinoImg, 0, 0, $nuevoAncho, $nuevoAlto, $fondo);
        }

        imagecopyresampled($destinoImg, $origenImg, 0, 0, 0, 0,
                           $nuevoAncho, $nuevoAlto, $ancho, $alto);

        imageinterlace($destinoImg, false);
        $guardado = imagepng($destinoImg, $destino, 6);

        imagedestroy($origenImg);
        imagedestroy($destinoImg);

        return $guardado ? '' : 'No se pudo guardar la imagen en el servidor.';
    }

    /** Borra una imagen de marca anterior que ya no se usa. */
    private function borrarLogo(string $archivo): void
    {
        if ($archivo !== '' && is_file(ds_marca_dir() . $archivo)) {
            @unlink(ds_marca_dir() . $archivo);
        }
    }

    /**
     * Decide con qué archivo se queda un campo de marca (logo o firma):
     * el recién subido, ninguno si se pidió quitarlo, o el que ya había.
     * Borra del disco el que deja de usarse.
     *
     * Devuelve el nombre resultante, o el motivo del fallo precedido de '!'.
     */
    private function resolverImagenMarca(string $campo, string $prefijo,
                                         string $actual, bool $quitar): string
    {
        $subido = $this->guardarLogo($campo, $prefijo);

        if (str_starts_with($subido, '!')) {
            return $subido;
        }
        if ($subido !== '') {
            $this->borrarLogo($actual);
            return $subido;
        }
        if ($quitar) {
            $this->borrarLogo($actual);
            return '';
        }

        return $actual;
    }

    public function organizacion(): array
    {
        return ds_organizacion();
    }

    public function guardarOrganizacion(): string
    {
        if (!puede_editar('organizacionForm')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar la organización.', 'error');
        }

        $id        = (int)($_POST['escuela_id'] ?? 0);
        $nombre    = trim((string)($_POST['escuela_nombre'] ?? ''));
        $ruc       = trim((string)($_POST['escuela_ruc'] ?? ''));
        $direccion = trim((string)($_POST['escuela_direccion'] ?? ''));
        $email     = trim((string)($_POST['escuela_email'] ?? ''));
        $telefono  = trim((string)($_POST['escuela_telefono'] ?? ''));
        $movil     = trim((string)($_POST['escuela_movil'] ?? ''));
        $quitarLogo = ($_POST['quitar_logo']  ?? '') === '1';
        $quitarFirma= ($_POST['quitar_firma'] ?? '') === '1';

        if ($nombre === '') {
            return $this->alerta('simple', 'Faltan datos',
                'El nombre legal es obligatorio: aparece en recibos, facturas y reportes.', 'error');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->alerta('simple', 'Correo no válido', 'Revise la dirección de correo.', 'error');
        }

        $actual = $this->filas("SELECT * FROM general_escuela WHERE escuela_id = :id", [':id' => $id]);
        $actual = $actual[0] ?? null;
        if ($actual === null) {
            return $this->alerta('simple', 'Error', 'La organización no existe.', 'error');
        }

        $logo = $this->resolverImagenMarca(
            'escuela_logo', 'organizacion', (string)$actual['escuela_logo'], $quitarLogo);
        if (str_starts_with($logo, '!')) {
            return $this->alerta('simple', 'Logo no válido', substr($logo, 1), 'error');
        }

        $firma = $this->resolverImagenMarca(
            'escuela_firma', 'firma', (string)($actual['escuela_firma'] ?? ''), $quitarFirma);
        if (str_starts_with($firma, '!')) {
            return $this->alerta('simple', 'Firma no válida', substr($firma, 1), 'error');
        }

        $n = $this->escribir(
            "UPDATE general_escuela
                SET escuela_nombre = :n, escuela_ruc = :r, escuela_direccion = :d,
                    escuela_email = :e, escuela_telefono = :t, escuela_movil = :m,
                    escuela_logo = :l, escuela_firma = :f
              WHERE escuela_id = :id",
            [':n' => $nombre, ':r' => $ruc, ':d' => $direccion, ':e' => $email,
             ':t' => $telefono !== '' ? $telefono : null, ':m' => $movil,
             ':l' => $logo, ':f' => $firma, ':id' => $id]
        );

        return $n >= 0
            ? $this->alerta('recargar', 'Organización actualizada',
                "«{$nombre}» es ahora el nombre que aparecerá en recibos, facturas y reportes.")
            : $this->alerta('simple', 'Error', 'No fue posible guardar la organización.', 'error');
    }

    /*======================================================================
      Facturación electrónica: parámetros del emisor
      ====================================================================*/

    /**
     * Configuración del SRI tal como está guardada.
     *
     * Core sólo lee y presenta la fila: el motor de facturación —firma,
     * certificado, envío a los webservices— sigue viviendo en Basketball,
     * y es ahí donde el formulario escribe. Duplicar aquí la criptografía
     * sería copiar la parte más delicada del sistema.
     */
    public function configuracionSri(): array
    {
        $vacia = [
            'ambiente' => '1', 'tipo_emision' => '1', 'iva_tarifa_default' => '0.00',
            'forma_pago_default' => '20', 'valores_incluyen_iva' => 1, 'secuencial_inicio' => 1,
            'ruc' => '', 'razon_social' => '', 'nombre_comercial' => '',
            'direccion_matriz' => '', 'direccion_establecimiento' => '',
            'codigo_establecimiento' => '001', 'punto_emision' => '001',
            'obligado_contabilidad' => 'NO', 'contribuyente_especial' => '',
            'agente_retencion' => '', 'contribuyente_rimpe' => '',
            /* Migración 071: servicio, teléfono, proveedor y correo. */
            'servicio_activo' => 1, 'mantenimiento_mensaje' => '', 'telefono' => '',
            'ruc_proveedor' => '', 'etiqueta_ruc_proveedor' => 'RUC Proveedor',
            'correo_from' => '', 'correo_from_nombre' => '', 'smtp_activo' => 0, 'smtp_host' => '',
            'smtp_port' => 587, 'smtp_seguridad' => 'tls', 'smtp_usuario' => '', 'smtp_clave_cifrada' => '',
        ];

        try {
            $filas = $this->filas("SELECT * FROM facturas_electronicas_config ORDER BY id LIMIT 1");
            return $filas ? array_merge($vacia, $filas[0]) : $vacia;
        } catch (\Throwable $e) {
            return $vacia;
        }
    }

    /** Catálogo de formas de pago del SRI. */
    public function formasPagoSri(): array
    {
        return [
            '01' => 'SIN UTILIZACION DEL SISTEMA FINANCIERO',
            '15' => 'COMPENSACION DE DEUDAS',
            '16' => 'TARJETA DE DEBITO',
            '17' => 'DINERO ELECTRONICO',
            '18' => 'TARJETA PREPAGO',
            '19' => 'TARJETA DE CREDITO',
            '20' => 'OTROS CON UTILIZACION DEL SISTEMA FINANCIERO',
            '21' => 'ENDOSO DE TITULOS',
        ];
    }

    /*======================================================================
      Carnets: color por mes y política de reimpresión
      ====================================================================*/

    /**
     * Color asignado a cada mes, con el aviso de si ya está bloqueado.
     *
     * Un mes se bloquea en cuanto se emite el primer carnet: cambiarle el
     * color dejaría en circulación carnets de un color que ya no coincide
     * con el del sistema.
     */
    public function coloresCarnetPorMes(?int $anio = null): array
    {
        /* Desde la migración 062 la asignación es por año: los doce meses se
           liberan al cambiar de año sin que nadie desbloquee nada. */
        $anio = $anio ?: (int)date('Y');

        $asignados = [];

        try {
            $filas = $this->filas(
                "SELECT cmc.mcolor_mes            AS mes,
                        cmc.mcolor_catcolorid     AS color_id,
                        cmc.mcolor_bloqueado      AS bloqueado,
                        cc.catcolor_nombre        AS color_nombre,
                        cc.catcolor_hex           AS color_hex,
                        (SELECT COUNT(*) FROM alumno_carnet ac
                          WHERE ac.carnet_mes = cmc.mcolor_mes
                            AND ac.carnet_anio = cmc.mcolor_anio) AS total_carnets
                   FROM carnet_mes_color cmc
                   JOIN carnet_catcolor  cc ON cc.catcolor_id = cmc.mcolor_catcolorid
                  WHERE cmc.mcolor_activo = 1
                    AND cmc.mcolor_anio = :anio",
                [':anio' => $anio]
            );
            foreach ($filas as $f) {
                $asignados[(int)$f['mes']] = $f;
            }
        } catch (\Throwable $e) {
            // Se devuelven los doce meses sin asignar.
        }

        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $f = $asignados[$m] ?? null;
            $meses[$m] = [
                'color_id'      => (int)($f['color_id'] ?? 0),
                'color_hex'     => (string)($f['color_hex'] ?? '#FFFFFF'),
                'color_nombre'  => (string)($f['color_nombre'] ?? 'Sin asignar'),
                'total_carnets' => (int)($f['total_carnets'] ?? 0),
                'bloqueado'     => (int)($f['bloqueado'] ?? 0) === 1
                                   || (int)($f['total_carnets'] ?? 0) > 0,
            ];
        }

        return $meses;
    }

    /** Catálogo de colores activos, con las veces que ya está asignado cada uno. */

    /**
     * El catálogo de colores con lo que hace falta para decidir sobre cada
     * uno: a qué mes está asignado, cuántos carnets lo respaldan y si se
     * puede editar o retirar.
     *
     * POR QUÉ ESTA LECTURA VIVE EN CORE Y LA ESCRITURA EN BASKETBALL
     *
     * La pantalla es de Core —configuración, superadministrador—, pero
     * quien escribe es carnetController, en Basketball, porque allí está el
     * endpoint que ya atendía la asignación de colores. Esta pantalla lleva
     * desde el principio leyendo de Core y enviando a Basketball.
     *
     * Core NO puede instanciar el controlador de Basketball: su cargador no
     * llega, y que no llegue es correcto —un módulo que instancia el
     * controlador de otro deja de tener frontera—.
     *
     * Así que la regla aparece a los dos lados, y eso NO es una duplicación
     * descuidada sino el reparto habitual: aquí se decide QUÉ SE OFRECE, y
     * en el servidor de escritura QUÉ SE ACEPTA. La autoridad es la
     * segunda: esta pantalla deshabilita un botón, pero una petición se
     * arma a mano.
     *
     * LA REGLA
     *
     * Un color está en circulación si el mes al que está asignado ya tiene
     * carnets impresos. Se cuenta contra alumno_carnet y no se confía en
     * mcolor_bloqueado: hoy esa bandera está desincronizada —los meses 4 a
     * 8 acumulan carnets con la bandera en 0—. La bandera se respeta como
     * bloqueo ADICIONAL, nunca como única fuente.
     */
    /**
     * Los años que ya tienen asignación de colores, para poder consultarlos.
     *
     * Los pasados se ven pero no se editan: son el registro de con qué tono
     * se imprimió cada mes, y reescribirlos sería falsear carnets que ya
     * están en circulación.
     */
    public function aniosCarnetConfigurados(): array
    {
        try {
            $filas = $this->filas(
                "SELECT DISTINCT mcolor_anio AS anio
                   FROM carnet_mes_color
                  WHERE mcolor_activo = 1
                  ORDER BY mcolor_anio"
            );
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static fn ($f) => (int) $f['anio'], $filas);
    }

    public function catalogoColoresCarnetAdmin(?int $anio = null): array
    {
        $anio  = $anio ?: (int)date('Y');
        $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                  'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        try {
            /*
            | DOS CIFRAS DISTINTAS Y LAS DOS HACEN FALTA
            |
            | «carnets» son los del año que se está mirando: dicen si la
            | ASIGNACIÓN de ese año está cerrada.
            |
            | «carnets_historicos» son los de cualquier año: dicen si el TONO
            | se puede cambiar. Un color que en 2026 imprimió cien carnets no
            | puede cambiar de tono en 2027 —esos cien siguen en circulación—
            | aunque en 2027 esté libre para asignarse a otro mes.
            */
            $filas = $this->filas(
                "SELECT c.catcolor_id, c.catcolor_nombre, c.catcolor_hex,
                        m.mcolor_mes, m.mcolor_bloqueado,
                        (SELECT COUNT(*) FROM alumno_carnet a
                          WHERE a.carnet_mes = m.mcolor_mes
                            AND a.carnet_anio = m.mcolor_anio) AS carnets,
                        (SELECT COUNT(*)
                           FROM carnet_mes_color mh
                           JOIN alumno_carnet ah
                             ON ah.carnet_mes  = mh.mcolor_mes
                            AND ah.carnet_anio = mh.mcolor_anio
                          WHERE mh.mcolor_catcolorid = c.catcolor_id) AS carnets_historicos
                   FROM carnet_catcolor c
                   LEFT JOIN carnet_mes_color m
                          ON m.mcolor_catcolorid = c.catcolor_id
                         AND m.mcolor_activo = 1
                         AND m.mcolor_anio = :anio
                  WHERE c.catcolor_activo = 1
                  ORDER BY c.catcolor_nombre",
                [':anio' => $anio]
            );
        } catch (\Throwable $e) {
            return [];
        }

        $salida = [];
        foreach ($filas as $f) {
            $mes        = $f['mcolor_mes'] !== null ? (int)$f['mcolor_mes'] : 0;
            $carnets    = (int)($f['carnets'] ?? 0);
            $historicos = (int)($f['carnets_historicos'] ?? 0);
            $congelado  = (int)($f['mcolor_bloqueado'] ?? 0) === 1;

            $salida[] = [
                'id'         => (int)$f['catcolor_id'],
                'nombre'     => (string)$f['catcolor_nombre'],
                'hex'        => strtoupper((string)$f['catcolor_hex']),
                'mes'        => $mes,
                'mes_nombre' => $mes > 0 ? ($meses[$mes] ?? '') : '',
                'carnets'    => $carnets,
                'historicos' => $historicos,
                /*
                | EL TONO DEPENDE SÓLO DEL HISTÓRICO DE CARNETS
                |
                | La primera versión añadía «y su mes no está congelado», y al
                | comparar 2026 con 2027 salió el desajuste: los mismos
                | colores daban 18 editables en un año y 20 en otro. Congelar
                | un mes es una decisión sobre LA ASIGNACIÓN de ese año —no se
                | quiere que nadie le cambie el color— y no dice nada sobre si
                | el tono del color se puede corregir.
                |
                | Un color sin ningún carnet impreso se puede retocar aunque
                | su mes esté congelado; lo que no se puede es sacarlo de ese
                | mes, y de eso se encarga el desplegable de arriba.
                */
                'editable'   => $historicos === 0,
                /* Retirar sí exige además no estar asignado a ESTE año. */
                'retirable'  => $historicos === 0 && $mes === 0,
                'motivo'     => $historicos > 0
                    ? $historicos . ' carnet(s) impresos con este tono'
                      . ($carnets > 0 && $mes > 0
                         ? ' (' . $carnets . ' en ' . ($meses[$mes] ?? '') . ' de este año)'
                         : '')
                    : '',
            ];
        }

        return $salida;
    }
    public function catalogoColoresCarnet(): array
    {
        try {
            return $this->filas(
                "SELECT cc.catcolor_id, cc.catcolor_nombre, cc.catcolor_hex,
                        (SELECT COUNT(*) FROM carnet_mes_color cmc
                          WHERE cmc.mcolor_catcolorid = cc.catcolor_id
                            AND cmc.mcolor_activo = 1) AS asignado_a
                   FROM carnet_catcolor cc
                  WHERE cc.catcolor_activo = 1
                  ORDER BY cc.catcolor_nombre"
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Política de cobro por reimpresión de carnet. */
    /** Imágenes del carnet (franja y línea decorativa) de la escuela. */
    public function imagenesCarnet(): array
    {
        try {
            $f = $this->filas("SELECT E.escuela_verticalfondo AS fondo, E.escuela_verticalprincipal AS linea
                                  FROM general_escuela E WHERE E.escuela_id IN (SELECT sede_escuelaid FROM general_sede)
                                 ORDER BY E.escuela_id LIMIT 1");
        } catch (\Throwable $e) {
            $f = [];
        }
        return $f[0] ?? ['fondo' => 'vertical_fondo.jpg', 'linea' => 'vertical_principal.jpg'];
    }

    public function configuracionCarnet(): array
    {
        $valores = ['cobrar_reimpresion' => '1', 'valor_reimpresion' => '3.00'];

        try {
            foreach ($this->filas("SELECT config_clave, config_valor FROM carnet_configuracion") as $f) {
                $valores[$f['config_clave']] = $f['config_valor'];
            }
        } catch (\Throwable $e) {
            // Quedan los valores por defecto.
        }

        $valor = (float)str_replace(',', '.', (string)$valores['valor_reimpresion']);

        return [
            'cobrar' => (string)$valores['cobrar_reimpresion'] === '1',
            'valor'  => $valor > 0 ? round($valor, 2) : 3.00,
        ];
    }

    /*======================================================================
      Catálogos del sistema
      ====================================================================*/

    public function catalogos(): array
    {
        return $this->filas(
            "SELECT t.tabla_id, t.tabla_nombre, t.tabla_estado,
                    COUNT(c.catalogo_valor) AS valores,
                    SUM(c.catalogo_estado = 'A') AS activos
               FROM general_tabla t
               LEFT JOIN general_tabla_catalogo c ON c.catalogo_tablaid = t.tabla_id
              WHERE t.tabla_estado <> 'E'
              GROUP BY t.tabla_id, t.tabla_nombre, t.tabla_estado
              ORDER BY t.tabla_nombre"
        );
    }

    public function valoresCatalogo(int $tablaid): array
    {
        return $this->filas(
            "SELECT * FROM general_tabla_catalogo
              WHERE catalogo_tablaid = :t ORDER BY catalogo_descripcion",
            [':t' => $tablaid]
        );
    }

    public function guardarValorCatalogo(): string
    {
        if (!puede_crear('catalogoList') && !puede_editar('catalogoList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede administrar catálogos.', 'error');
        }

        $tabla       = (int)($_POST['catalogo_tablaid'] ?? 0);
        $valor       = strtoupper(trim((string)($_POST['catalogo_valor'] ?? '')));
        $descripcion = trim((string)($_POST['catalogo_descripcion'] ?? ''));
        $estado      = ($_POST['catalogo_estado'] ?? 'A') === 'A' ? 'A' : 'I';
        $esNuevo     = ($_POST['es_nuevo'] ?? '0') === '1';

        if ($tabla <= 0 || $descripcion === '') {
            return $this->alerta('simple', 'Faltan datos', 'Indique catálogo y descripción.', 'error');
        }

        /* El código es la clave primaria de la tabla: exactamente 3
           caracteres, como el resto del catálogo del sistema. */
        if (!preg_match('/^[A-Z0-9]{3}$/', $valor)) {
            return $this->alerta('simple', 'Código no válido',
                'El código debe tener exactamente 3 caracteres alfanuméricos en mayúscula.', 'error');
        }

        if ($esNuevo) {
            if (!puede_crear('catalogoList')) {
                return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede crear valores.', 'error');
            }

            $existe = (int)$this->escalar(
                "SELECT COUNT(1) FROM general_tabla_catalogo WHERE catalogo_valor = :v", [':v' => $valor]);
            if ($existe > 0) {
                return $this->alerta('simple', 'Código repetido',
                    "El código {$valor} ya está en uso. Los códigos son únicos en todo el sistema.", 'error');
            }

            $n = $this->escribir(
                "INSERT INTO general_tabla_catalogo
                    (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
                 VALUES (:v, :t, :d, :e)",
                [':v' => $valor, ':t' => $tabla, ':d' => $descripcion, ':e' => $estado]);
        } else {
            if (!puede_editar('catalogoList')) {
                return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede editar valores.', 'error');
            }

            $n = $this->escribir(
                "UPDATE general_tabla_catalogo
                    SET catalogo_descripcion = :d, catalogo_estado = :e
                  WHERE catalogo_valor = :v",
                [':d' => $descripcion, ':e' => $estado, ':v' => $valor]);
        }

        return $n >= 0
            ? $this->alerta('recargar', 'Catálogo actualizado', "El valor {$valor} quedó guardado.")
            : $this->alerta('simple', 'Error', 'No fue posible guardar el valor.', 'error');
    }

    public function eliminarValorCatalogo(): string
    {
        if (!puede_eliminar('catalogoList')) {
            return $this->alerta('simple', 'Acceso denegado', 'Su rol no puede eliminar valores.', 'error');
        }

        $valor = strtoupper(trim((string)($_POST['catalogo_valor'] ?? '')));

        /* Los tipos de sede sostienen la operación de Arena: si se borra el
           código que una sede tiene asignado, esa sede deja de resolverse. */
        $enUso = (int)$this->escalar(
            "SELECT COUNT(1) FROM general_sede WHERE sede_tipoingreso = :v", [':v' => $valor]);
        if ($enUso > 0) {
            return $this->alerta('simple', 'Valor en uso',
                "No se puede eliminar: {$enUso} sede(s) usan ese tipo. Cámbielas primero.", 'error');
        }

        $n = $this->escribir(
            "DELETE FROM general_tabla_catalogo WHERE catalogo_valor = :v", [':v' => $valor]);

        return $n > 0
            ? $this->alerta('recargar', 'Valor eliminado', "El valor {$valor} fue eliminado del catálogo.")
            : $this->alerta('simple', 'Error', 'No fue posible eliminar el valor.', 'error');
    }

    /*======================================================================
      Menu lateral del propio modulo Core
      ====================================================================*/

    public function menuLateral(string $vistaActual): string
    {
        $sql = "SELECT m.menu_vista, m.menu_nombre, m.menu_icono
                  FROM seguridad_menu m
                 WHERE m.menu_estado = 'A' AND m.menu_modulo = 'core'";

        if (!es_superadministrador()) {
            $sql .= " AND EXISTS (SELECT 1 FROM seguridad_permiso p
                                   WHERE p.permiso_menuid = m.menu_id
                                     AND p.permiso_rolid  = :rol
                                     AND p.permiso_estado = 'A'
                                     AND p.permiso_ver    = 'S')";
        }
        $sql .= " ORDER BY m.menu_orden";

        $filas = $this->filas($sql, es_superadministrador() ? [] : [':rol' => rol_actual()]);

        $html = '';
        foreach ($filas as $f) {
            $activo = ($f['menu_vista'] === $vistaActual) ? ' active' : '';
            $html .= '<li class="nav-item">'
                   . '<a href="' . APP_URL . $f['menu_vista'] . '/" class="nav-link' . $activo . '">'
                   /* nav-icon es de AdminLTE 4: sin ella el texto se pega
                      al borde y los iconos no quedan alineados entre si. */
                   . '<i class="nav-icon ' . htmlspecialchars($f['menu_icono'], ENT_QUOTES, 'UTF-8') . '"></i>'
                   . '<p>' . htmlspecialchars($f['menu_nombre'], ENT_QUOTES, 'UTF-8') . '</p>'
                   . '</a></li>';
        }

        return $html;
    }

    /*======================================================================
      Puntos de emisión por módulo

      El SRI numera los comprobantes por (tipo, establecimiento, punto de
      emisión) y exige que el secuencial no se repita dentro de esa terna.
      Dar a cada módulo su propio punto hace imposible la colisión; esta
      pantalla es donde se asigna.
      ====================================================================*/

    /** Puntos configurados, con el uso real de cada uno. */
    public function puntosEmision(): array
    {
        /* Se cuenta desde la vista consolidada y no desde una tabla
           concreta: es lo que permite ver que un punto ya fue usado,
           venga el comprobante del módulo que venga. */
        return $this->filas(
            "SELECT P.*,
                    COALESCE(S.secuencial_actual, 0) AS contador,
                    (SELECT COUNT(*) FROM v_comprobante_emitido V
                      WHERE V.establecimiento = P.punto_establecimiento
                        AND V.punto_emision   = P.punto_codigo) AS emitidos
               FROM facturas_electronicas_punto_emision P
               LEFT JOIN facturas_electronicas_secuenciales S
                      ON S.establecimiento   = P.punto_establecimiento
                     AND S.punto_emision     = P.punto_codigo
                     AND S.tipo_comprobante  = '01'
              ORDER BY P.punto_establecimiento, P.punto_codigo"
        );
    }

    /** Módulos del ecosistema que todavía no tienen punto asignado. */
    public function modulosSinPunto(): array
    {
        $asignados = array_column($this->puntosEmision(), 'punto_modulo');
        $libres    = [];

        foreach (ds_modulos() as $clave => $m) {
            /* Core no factura: administra. */
            if ($clave === 'core') { continue; }
            if (!in_array($clave, $asignados, true)) {
                $libres[$clave] = $m['nombre'];
            }
        }

        return $libres;
    }

    /**
     * Da de alta o modifica un punto de emisión.
     *
     * Sólo el superadministrador. No es una restricción de comodidad: de
     * aquí depende con qué numeración se emiten documentos con validez
     * tributaria.
     */
    public function guardarPuntoEmision(): string
    {
        if (!es_superadministrador()) {
            return $this->alerta('simple', 'Acceso denegado',
                'Sólo el superadministrador puede configurar puntos de emisión.', 'error');
        }

        $id      = (int)($_POST['punto_id'] ?? 0);
        $modulo  = strtolower(trim((string)($_POST['punto_modulo'] ?? '')));
        $estab   = trim((string)($_POST['punto_establecimiento'] ?? ''));
        $codigo  = trim((string)($_POST['punto_codigo'] ?? ''));
        $inicio  = (int)($_POST['punto_secuencialinicio'] ?? 1);
        $desc    = trim((string)($_POST['punto_descripcion'] ?? ''));
        $estado  = ($_POST['punto_estado'] ?? 'I') === 'A' ? 'A' : 'I';

        /*----------  Forma de los códigos  ----------*/
        /* El SRI los define como tres dígitos exactos. Se valida aquí y no
           sólo en el navegador, porque este endpoint es alcanzable sin
           pasar por el formulario. */
        if (!preg_match('/^\d{3}$/', $estab)) {
            return $this->alerta('simple', 'Establecimiento no válido',
                'El código de establecimiento son tres dígitos, por ejemplo 001.', 'error');
        }
        if (!preg_match('/^\d{3}$/', $codigo)) {
            return $this->alerta('simple', 'Punto de emisión no válido',
                'El punto de emisión son tres dígitos, por ejemplo 002.', 'error');
        }
        if ($inicio < 1 || $inicio > 999999999) {
            return $this->alerta('simple', 'Secuencial no válido',
                'El número inicial debe estar entre 1 y 999999999.', 'error');
        }

        /*----------  El módulo tiene que existir  ----------*/
        $conocidos = array_keys(ds_modulos());
        if (!in_array($modulo, $conocidos, true) || $modulo === 'core') {
            return $this->alerta('simple', 'Módulo no válido',
                'Seleccione un módulo del ecosistema que pueda emitir comprobantes.', 'error');
        }

        /*----------  No retroceder por debajo de lo ya emitido  ----------*/
        /* La base impide duplicar el número de un comprobante, pero no
           impide dejar el punto apuntando a un secuencial ya usado: eso se
           descubriría al emitir, con el documento a medio hacer. Se
           comprueba aquí, que es donde el aviso todavía sirve. */
        $emitido = (int)$this->escalar(
            "SELECT COALESCE(MAX(CAST(secuencial AS UNSIGNED)), 0)
               FROM v_comprobante_emitido
              WHERE establecimiento = :e AND punto_emision = :p",
            [':e' => $estab, ':p' => $codigo]
        );

        if ($emitido > 0 && $inicio <= $emitido) {
            return $this->alerta('simple', 'Numeración ya utilizada',
                "Desde {$estab}-{$codigo} ya se emitió hasta el número {$emitido}. "
                . 'El número inicial debe ser mayor.', 'error');
        }

        /*----------  Escritura  ----------*/
        try {
            $con = $this->con();
            if ($con === null) {
                return $this->alerta('simple', 'Sin conexión',
                    'No fue posible conectar con la base de datos.', 'error');
            }

            if ($id > 0) {
                $sql = "UPDATE facturas_electronicas_punto_emision
                           SET punto_modulo = :m, punto_establecimiento = :e,
                               punto_codigo = :c, punto_secuencialinicio = :i,
                               punto_descripcion = :d, punto_estado = :s,
                               punto_usuarioid = :u
                         WHERE punto_id = :id";
                $par = [':id' => $id];
            } else {
                $sql = "INSERT INTO facturas_electronicas_punto_emision
                               (punto_modulo, punto_establecimiento, punto_codigo,
                                punto_secuencialinicio, punto_descripcion,
                                punto_estado, punto_usuarioid)
                        VALUES (:m, :e, :c, :i, :d, :s, :u)";
                $par = [];
            }

            $st = $con->prepare($sql);
            $st->execute($par + [
                ':m' => $modulo, ':e' => $estab, ':c' => $codigo, ':i' => $inicio,
                ':d' => $desc,   ':s' => $estado, ':u' => usuario_actual_id() ?: null,
            ]);

            $accion = $id > 0 ? 'actualizó' : 'creó';
            return $this->alerta('recargar', 'Punto de emisión guardado',
                "Se {$accion} el punto {$estab}-{$codigo} para el módulo {$modulo}.", 'success');

        } catch (\PDOException $e) {
            /* Hay dos claves únicas en la tabla, y decir cuál se violó es
               la diferencia entre un aviso útil y «error de base de datos». */
            if ((int)$e->getCode() === 23000) {
                $msg = strpos($e->getMessage(), 'uk_fepe_punto') !== false
                    ? "El punto {$estab}-{$codigo} ya está asignado a otro módulo. "
                      . 'Un punto de emisión pertenece a uno solo: es lo que impide '
                      . 'que dos módulos generen el mismo número.'
                    : "El módulo «{$modulo}» ya tiene un punto asignado en el "
                      . "establecimiento {$estab}.";
                return $this->alerta('simple', 'Asignación duplicada', $msg, 'error');
            }

            return $this->alerta('simple', 'No se pudo guardar',
                'La base de datos rechazó el cambio.', 'error');
        }
    }

    /*======================================================================
      Segundo factor de autenticación

      CADA UNO CONFIGURA EL SUYO, Y NADIE CONFIGURA EL DE OTRO

      Un administrador no puede activar el segundo factor de otra persona:
      necesitaría su teléfono, y si pudiera hacerlo sin él tendría también
      la forma de generar sus códigos. Lo único que puede hacer sobre una
      cuenta ajena es RESTABLECER —quitarlo— cuando alguien pierde el
      teléfono y se queda fuera, y eso queda registrado con nombre y fecha
      porque es exactamente el movimiento que haría quien quiere entrar en
      una cuenta que no es suya.
      ====================================================================*/

    /**
     * Quita el segundo factor de OTRA persona.
     *
     * Es la salida cuando alguien pierde el teléfono y se queda fuera. Sólo
     * el superadministrador, con motivo obligatorio, y queda anotado quién
     * lo hizo y sobre quién: sin ese rastro, esta función es una puerta
     * trasera a cualquier cuenta.
     */
    public function restablecerSegundoFactor(): string
    {
        if (!es_superadministrador()) {
            return $this->alerta('simple', 'Acceso denegado',
                'Sólo el superadministrador puede restablecer la verificación de otra '
                . 'persona.', 'error');
        }

        $usuarioid = (int)($_POST['usuario_id'] ?? 0);
        $motivo    = trim((string)($_POST['motivo'] ?? ''));

        if ($usuarioid <= 0) {
            return $this->alerta('simple', 'Falta el usuario', 'No se indicó a quién.', 'error');
        }
        if ($motivo === '') {
            return $this->alerta('simple', 'Falta el motivo',
                'Quitarle a alguien su segundo factor necesita una justificación escrita.',
                'error');
        }

        $quien = $this->fila("SELECT usuario_usuario, usuario_2fa_estado
                                FROM seguridad_usuario WHERE usuario_id = :id",
                             [':id' => $usuarioid]);

        if (!$quien) {
            return $this->alerta('simple', 'No encontrado', 'Ese usuario no existe.', 'error');
        }
        if ($quien['usuario_2fa_estado'] === 'N') {
            return $this->alerta('simple', 'No tiene verificación',
                'Esa cuenta no tiene el segundo factor configurado.', 'error');
        }

        if (!dosf_desactivar($usuarioid, 'RESTABLECER', $motivo)) {
            return $this->alerta('simple', 'No se pudo restablecer',
                'La base de datos rechazó el cambio.', 'error');
        }

        return $this->alerta('recargar', 'Verificación restablecida',
            'La cuenta «' . $quien['usuario_usuario'] . '» vuelve a entrar sólo con su '
            . 'contraseña. Pídale que la configure de nuevo.');
    }


    /*==================================================================
    | Tarjeta de cumpleaños
    |==================================================================
    | La tarjeta la dibuja Basketball con GD, pero QUIEN DECIDE cómo se ve es
    | esta pantalla. Antes las medidas eran cuatro fracciones escritas en el
    | PHP —0.218, 0.176, 0.558, 0.463— con el comentario «ajustar estos
    | valores si se cambia la plantilla»: cambiar el marco exigía editar
    | código y adivinar números probando.
    |
    | Todo se guarda en PORCENTAJE del marco, nunca en píxeles. Así una
    | plantilla de 900x1600 y otra de 1800x3200 usan los mismos números y
    | cambiar el marco por uno de más resolución no descoloca nada.
    */

    /** El marco vive con las demás imágenes de Basketball, que es quien dibuja. */
    private function carpetaMarcos(): string
    {
        return DS_HUB_PATH . 'ds_futbol' . DIRECTORY_SEPARATOR . 'app'
             . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'imagenes'
             . DIRECTORY_SEPARATOR . 'cumples' . DIRECTORY_SEPARATOR;
    }

    /**
     * Un alumno cualquiera, para el enlace de «ver una tarjeta real».
     *
     * Se busca en vez de escribir un id: uno fijo deja de existir el dia que
     * se borra esa ficha, y el boton llevaria a un 404 sin que nadie lo note
     * hasta pulsarlo.
     */
    public function unAlumnoParaPrueba(): ?int
    {
        $f = $this->filas(
            "SELECT alumno_id FROM sujeto_alumno
              WHERE alumno_estado = 'A' AND alumno_fechanacimiento IS NOT NULL
              ORDER BY alumno_id LIMIT 1");
        return isset($f[0]) ? (int) $f[0]['alumno_id'] : null;
    }

    public function plantillasCumple(): array
    {
        return $this->filas(
            "SELECT plantilla_id, plantilla_nombre, plantilla_archivo, plantilla_estado,
                    plantilla_modificado
               FROM cumple_plantilla
              ORDER BY plantilla_estado DESC, plantilla_nombre");
    }

    /**
     * Una plantilla con el tamaño real de su marco añadido.
     *
     * El editor necesita el tamaño para dibujar las cajas a escala. Se lee del
     * archivo y no de la base: si alguien sustituye el PNG por otro de
     * distinta resolución, un tamaño guardado quedaría mintiendo.
     */
    public function plantillaCumple(int $id = 0): ?array
    {
        $f = $this->filas(
            $id > 0
                ? "SELECT * FROM cumple_plantilla WHERE plantilla_id = :id"
                : "SELECT * FROM cumple_plantilla WHERE plantilla_estado = 'A' LIMIT 1",
            $id > 0 ? [':id' => $id] : []);

        $p = $f[0] ?? null;
        if ($p === null) { return null; }

        $ruta = $this->carpetaMarcos() . basename($p['plantilla_archivo']);
        $p['marco_existe'] = is_file($ruta);
        $p['marco_ancho']  = 0;
        $p['marco_alto']   = 0;

        if ($p['marco_existe']) {
            $tam = @getimagesize($ruta);
            if ($tam) { $p['marco_ancho'] = $tam[0]; $p['marco_alto'] = $tam[1]; }
        }

        return $p;
    }

    /*==================================================================
    | Varias plantillas
    |==================================================================
    | Al principio había una sola fila y el marco se sustituía encima. Eso
    | tenía dos problemas que se vieron en cuanto se usó de verdad:
    |
    |   1. Subir el marco de este año BORRÓ la geometría del anterior. No se
    |      podía volver atrás sin releer la migración.
    |   2. La fila seguía llamándose «Clásica» mientras contenía otro marco,
    |      así que el nombre dejó de significar nada.
    |
    | Ahora cada marco es su propia plantilla y una sola está activa. Cambiar
    | la tarjeta de la escuela es elegir otra fila, no volver a subir nada.
    |
    | La regla «sólo una activa» se aplica aquí Y en la base, con un índice
    | único sobre una expresión (migración 058). Dejarla sólo en el código
    | significaría que cualquier UPDATE hecho a mano puede dejar dos activas y
    | que el generador coja una al azar según el plan del optimizador.
    */

    /** Cuántas plantillas hay. Se usa para no dejar el sistema con cero. */
    private function cuantasPlantillasCumple(): int
    {
        return (int) $this->escalar("SELECT COUNT(1) FROM cumple_plantilla");
    }

    /**
     * Retira los marcos subidos que no acabaron en ninguna plantilla.
     *
     *
     * POR QUÉ HACE FALTA
     *
     * El archivo se guarda ANTES de analizarlo —hay que tenerlo en disco para
     * poder mirarlo— y sólo se borra en las ramas de error que este código
     * controla. Si el intento muere por fuera —se agotó el tiempo de PHP, se
     * cortó la conexión, el navegador canceló—, el archivo se queda.
     *
     * No es teórico: un guardado que tardaba de más dejó NUEVE marcos de kilo
     * y medio en la carpeta en tres minutos de reintentos, doce megas que
     * nadie iba a mirar nunca.
     *
     * Se barre después de guardar, que es cuando se sabe qué archivos están
     * de verdad en uso.
     *
     *
     * LOS TRES FRENOS
     *
     *   · sólo archivos con el patrón que genera este código, nunca otros;
     *   · nunca uno al que apunte una plantilla;
     *   · nunca uno de la última hora, por si hay otra subida en curso.
     *
     * Devuelve cuántos retiró.
     */
    private function limpiarMarcosHuerfanos(): int
    {
        $carpeta = $this->carpetaMarcos();
        if (!is_dir($carpeta)) { return 0; }

        $enUso = [];
        foreach ($this->filas("SELECT plantilla_archivo FROM cumple_plantilla") as $f) {
            $enUso[strtolower(basename((string) $f['plantilla_archivo']))] = true;
        }

        $limite = time() - 3600;
        $n = 0;

        foreach ((array) glob($carpeta . 'marco_*.png') as $ruta) {
            $nombre = basename($ruta);

            /* Sólo lo que genera guardarMarcoCumple(): marco_<14 dígitos>.png
               y su recorte marco_<14 dígitos>_ventana.png. Cualquier otro
               archivo de la carpeta lo puso una persona y no se toca. */
            if (!preg_match('~^marco_\d{14}(_ventana)?\.png$~', $nombre)) { continue; }
            if (isset($enUso[strtolower($nombre)])) { continue; }
            if (@filemtime($ruta) > $limite) { continue; }

            /*
            | Un original cuyo recorte SÍ está en uso no es basura: es el
            | archivo del que salió, y se conserva a propósito para poder
            | volver atrás. eliminarPlantillaCumple() ya se encarga de él
            | cuando se borra la plantilla.
            */
            if (!str_ends_with($nombre, '_ventana.png')) {
                $recorte = substr($nombre, 0, -4) . '_ventana.png';
                if (isset($enUso[strtolower($recorte)])) { continue; }
            }

            if (@unlink($ruta)) { $n++; }
        }

        return $n;
    }

    /**
     * Crea una plantilla nueva copiando la geometría de la que se indique.
     *
     * Se copia en vez de empezar en blanco porque los marcos de una misma
     * escuela se parecen: casi siempre basta con mover la ventana un poco. Una
     * plantilla en blanco obligaría a recolocar las tres cajas desde cero.
     */
    public function nuevaPlantillaCumple(): string
    {
        if (!csrf_valido()) {
            return $this->alerta('simple', 'Solicitud no verificada',
                'Recargue la página e inténtelo de nuevo.', 'error');
        }
        if (!puede_crear('tarjetaCumple')) {
            return $this->alerta('simple', 'Acceso denegado',
                'Su rol no puede crear plantillas de tarjeta.', 'error');
        }

        $nombre = trim((string)($_POST['plantilla_nombre'] ?? ''));
        if ($nombre === '') {
            return $this->alerta('simple', 'Faltan datos',
                'La plantilla necesita un nombre.', 'error');
        }

        $base = $this->plantillaCumple((int)($_POST['copiar_de'] ?? 0));
        if ($base === null) {
            return $this->alerta('simple', 'Error',
                'No hay ninguna plantilla de la que copiar las medidas.', 'error');
        }

        /* El marco es obligatorio al crear: si se copiara el del original, dos
           plantillas compartirían archivo y borrar una se llevaría el marco de
           la otra. */
        if (empty($_FILES['plantilla_marco']['name'])) {
            return $this->alerta('simple', 'Falta el marco',
                'Una plantilla nueva necesita su propio PNG. Si sólo quiere '
                . 'retocar medidas, edite la plantilla que ya existe.', 'error');
        }

        $rect = ['x' => (float)$base['foto_x'], 'y' => (float)$base['foto_y'],
                 'w' => (float)$base['foto_ancho'], 'h' => (float)$base['foto_alto']];

        $subido = $this->guardarMarcoCumple('plantilla_marco', $rect);
        if (isset($subido['error'])) {
            return $this->alerta('simple', 'Marco no válido', $subido['error'], 'error');
        }
        if ($subido['rect'] !== null) { $rect = $subido['rect']; }

        $n = $this->escribir(
            "INSERT INTO cumple_plantilla
                (plantilla_nombre, plantilla_archivo, plantilla_estado,
                 foto_x, foto_y, foto_ancho, foto_alto,
                 nombre_x, nombre_y, nombre_ancho, nombre_tam, nombre_color, nombre_alinea,
                 edad_x, edad_y, edad_ancho, edad_tam, edad_color, edad_alinea, edad_texto,
                 franja_activa, franja_alto, franja_color, franja_opacidad, angulo,
                 plantilla_usuarioid, plantilla_modificado)
             VALUES
                (:nombre, :archivo, 'I',
                 :fx, :fy, :fw, :fh,
                 :nx, :ny, :nw, :nt, :nc, :na,
                 :ex, :ey, :ew, :et, :ec, :ea, :etx,
                 :ja, :jh, :jc, :jo, :an,
                 :usr, NOW())",
            [
                ':nombre' => $nombre, ':archivo' => $subido['archivo'],
                ':fx' => $rect['x'], ':fy' => $rect['y'],
                ':fw' => $rect['w'], ':fh' => $rect['h'],
                ':nx' => $base['nombre_x'], ':ny' => $base['nombre_y'],
                ':nw' => $base['nombre_ancho'], ':nt' => $base['nombre_tam'],
                ':nc' => $base['nombre_color'], ':na' => $base['nombre_alinea'],
                ':ex' => $base['edad_x'], ':ey' => $base['edad_y'],
                ':ew' => $base['edad_ancho'], ':et' => $base['edad_tam'],
                ':ec' => $base['edad_color'], ':ea' => $base['edad_alinea'],
                ':etx' => $base['edad_texto'],
                ':ja' => $base['franja_activa'], ':jh' => $base['franja_alto'],
                ':jc' => $base['franja_color'], ':jo' => $base['franja_opacidad'],
                ':an' => $base['angulo'],
                ':usr' => usuario_actual_id(),
            ]);

        if ($n < 1) {
            return $this->alerta('simple', 'Error', 'No se pudo crear la plantilla.', 'error');
        }

        $this->limpiarMarcosHuerfanos();

        return $this->alerta('recargar', 'Plantilla creada',
            'Se creó «' . $nombre . '» copiando las medidas de «' . $base['plantilla_nombre']
            . '». Todavía NO es la que se imprime: actívela cuando esté ajustada.'
            . ($subido['aviso'] !== '' ? ' ' . $subido['aviso'] : ''),
            'success');
    }

    /**
     * Pone una plantilla como la que se imprime.
     *
     * Las dos escrituras van en transacción: entre apagar la anterior y
     * encender la nueva hay un instante en que ninguna está activa, y si el
     * proceso muere ahí las tarjetas dejarían de generarse hasta que alguien
     * entrara a mirar por qué.
     */
    public function activarPlantillaCumple(): string
    {
        if (!csrf_valido()) {
            return $this->alerta('simple', 'Solicitud no verificada',
                'Recargue la página e inténtelo de nuevo.', 'error');
        }
        if (!puede_editar('tarjetaCumple')) {
            return $this->alerta('simple', 'Acceso denegado',
                'Su rol no puede cambiar la tarjeta de cumpleaños.', 'error');
        }

        $id = (int)($_POST['plantilla_id'] ?? 0);
        $p  = $this->plantillaCumple($id);
        if ($id <= 0 || $p === null) {
            return $this->alerta('simple', 'Error', 'La plantilla no existe.', 'error');
        }

        /* Activar una plantilla cuyo marco no está en disco dejaría las
           tarjetas sin marco a partir del siguiente cumpleaños. */
        if (!$p['marco_existe']) {
            return $this->alerta('simple', 'No se puede activar',
                'El archivo del marco de esta plantilla no está en su carpeta. '
                . 'Súbalo de nuevo antes de activarla.', 'error');
        }

        $con = $this->con();
        if ($con === null) {
            return $this->alerta('simple', 'Error', 'Sin conexión a la base.', 'error');
        }

        try {
            $con->beginTransaction();
            $con->prepare("UPDATE cumple_plantilla SET plantilla_estado = 'I'
                            WHERE plantilla_estado = 'A'")->execute();
            $con->prepare("UPDATE cumple_plantilla SET plantilla_estado = 'A',
                                  plantilla_usuarioid = :usr, plantilla_modificado = NOW()
                            WHERE plantilla_id = :id")
                ->execute([':id' => $id, ':usr' => usuario_actual_id()]);
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->alerta('simple', 'Error',
                'No se pudo activar la plantilla.', 'error');
        }

        return $this->alerta('recargar', 'Plantilla activa',
            'Las tarjetas de cumpleaños se generarán con «' . $p['plantilla_nombre'] . '».',
            'success');
    }

    /**
     * Borra una plantilla.
     *
     * No se puede borrar la activa ni la última: las dos cosas dejarían el
     * generador sin plantilla, y el error aparecería en la pantalla de
     * cumpleaños de Basketball, lejos de aquí.
     */
    public function eliminarPlantillaCumple(): string
    {
        if (!csrf_valido()) {
            return $this->alerta('simple', 'Solicitud no verificada',
                'Recargue la página e inténtelo de nuevo.', 'error');
        }
        if (!puede_eliminar('tarjetaCumple')) {
            return $this->alerta('simple', 'Acceso denegado',
                'Su rol no puede eliminar plantillas de tarjeta.', 'error');
        }

        $id = (int)($_POST['plantilla_id'] ?? 0);
        $p  = $this->fila("SELECT * FROM cumple_plantilla WHERE plantilla_id = :id", [':id' => $id]);
        if ($p === []) {
            return $this->alerta('simple', 'Error', 'La plantilla no existe.', 'error');
        }
        if ($p['plantilla_estado'] === 'A') {
            return $this->alerta('simple', 'No se puede eliminar',
                'Es la plantilla con la que se imprimen las tarjetas. Active otra primero.',
                'error');
        }
        if ($this->cuantasPlantillasCumple() <= 1) {
            return $this->alerta('simple', 'No se puede eliminar',
                'Es la única plantilla que queda.', 'error');
        }

        if ($this->escribir("DELETE FROM cumple_plantilla WHERE plantilla_id = :id",
                            [':id' => $id]) < 1) {
            return $this->alerta('simple', 'Error', 'No se pudo eliminar.', 'error');
        }

        /*
        | El PNG se borra sólo si no lo usa ninguna otra plantilla y no es el
        | marco original del sistema.
        |
        | Dejarlos todos sería ir acumulando archivos de dos megas que nadie
        | vuelve a abrir; borrarlos sin mirar dejaría a otra plantilla sin su
        | marco, que es peor.
        */
        $archivo = basename((string) $p['plantilla_archivo']);
        $otras   = (int) $this->escalar(
            "SELECT COUNT(1) FROM cumple_plantilla WHERE plantilla_archivo = :a",
            [':a' => $p['plantilla_archivo']]);

        if ($otras === 0 && $archivo !== '' && $archivo !== 'marco.png') {
            @unlink($this->carpetaMarcos() . $archivo);

            /*
            | Y el original del que salió, si lo hubo.
            |
            | Cuando se abre la ventana automáticamente quedan DOS archivos:
            | «marco_<sello>.png» tal como se subió y «marco_<sello>_ventana.png»
            | recortado. Sin esto, borrar la plantilla se lleva el recorte y deja
            | el original de dos megas para siempre, que es justo la basura que
            | se quería evitar.
            */
            if (str_ends_with($archivo, '_ventana.png')) {
                $original = substr($archivo, 0, -strlen('_ventana.png')) . '.png';
                $usado = (int) $this->escalar(
                    "SELECT COUNT(1) FROM cumple_plantilla WHERE plantilla_archivo = :a",
                    [':a' => $original]);
                if ($usado === 0 && $original !== 'marco.png') {
                    @unlink($this->carpetaMarcos() . $original);
                }
            }
        }

        return $this->alerta('recargar', 'Plantilla eliminada',
            'Se eliminó «' . $p['plantilla_nombre'] . '».', 'success');
    }

    /**
     * Guarda las medidas de una plantilla.
     *
     * Cada número se acota al rango que tiene sentido. No es desconfianza del
     * formulario: los mismos campos llegan por POST y un x de 900 % dibujaría
     * la foto fuera del lienzo, que en GD no falla, simplemente no se ve.
     */
    public function guardarPlantillaCumple(): string
    {
        /*
        | El resto de Core se apoya solo en origen_es_valido() —la cabecera
        | Origin, que el navegador pone y el JS de otra pagina no puede
        | falsear—. Es una defensa legitima, pero el formulario de esta
        | pantalla ya lleva su testigo, asi que comprobarlo cuesta una linea
        | y no depende de que el navegador mande la cabecera.
        */
        if (!csrf_valido()) {
            return $this->alerta('simple', 'Solicitud no verificada',
                'Recargue la página e inténtelo de nuevo.', 'error');
        }

        if (!puede_editar('tarjetaCumple')) {
            return $this->alerta('simple', 'Acceso denegado',
                'Su rol no puede editar la tarjeta de cumpleaños.', 'error');
        }

        $id = (int)($_POST['plantilla_id'] ?? 0);
        if ($id <= 0) {
            return $this->alerta('simple', 'Error', 'No se indicó qué plantilla guardar.', 'error');
        }

        $actual = $this->filas("SELECT * FROM cumple_plantilla WHERE plantilla_id = :id", [':id' => $id]);
        $actual = $actual[0] ?? null;
        if ($actual === null) {
            return $this->alerta('simple', 'Error', 'La plantilla no existe.', 'error');
        }

        $nombre = trim((string)($_POST['plantilla_nombre'] ?? ''));
        if ($nombre === '') {
            return $this->alerta('simple', 'Faltan datos', 'La plantilla necesita un nombre.', 'error');
        }

        /** Un porcentaje válido: 0 a 100, con tres decimales. */
        $pct = static function (string $campo, float $porDefecto): float {
            $v = (float) str_replace(',', '.', (string)($_POST[$campo] ?? $porDefecto));
            return round(max(0, min(100, $v)), 3);
        };

        /** Un color #RRGGBB, o el que había si llega cualquier otra cosa. */
        $col = static function (string $campo, string $porDefecto): string {
            $v = strtoupper(trim((string)($_POST[$campo] ?? '')));
            return preg_match('~^#[0-9A-F]{6}$~', $v) ? $v : $porDefecto;
        };

        $alin = static function (string $campo, string $porDefecto): string {
            $v = strtoupper(trim((string)($_POST[$campo] ?? '')));
            return in_array($v, ['I', 'C', 'D'], true) ? $v : $porDefecto;
        };

        /*
        | Las medidas de la foto se calculan ANTES de tocar el archivo.
        |
        | No es un capricho del orden: para decir si un marco nuevo sirve hay
        | que saber por dónde va a asomar la foto. Un PNG puede tener
        | transparencia de sobra y aun así ser opaco justo en el rectángulo que
        | importa, y entonces la tarjeta sale sin alumno.
        */
        $rect = [
            'x' => $pct('foto_x', (float)$actual['foto_x']),
            'y' => $pct('foto_y', (float)$actual['foto_y']),
            'w' => $pct('foto_ancho', (float)$actual['foto_ancho']),
            'h' => $pct('foto_alto', (float)$actual['foto_alto']),
        ];

        /*
        | El marco nuevo, si lo hay. Se sustituye el archivo de esta plantilla:
        | guardar varias versiones del mismo marco llenaría la carpeta de PNG de
        | medio mega que nadie volvería a mirar. Para tener DOS marcos a la vez
        | se crea otra plantilla, que es lo que hace nuevaPlantillaCumple().
        */
        $archivo = (string) $actual['plantilla_archivo'];
        $aviso   = '';

        if (!empty($_FILES['plantilla_marco']['name'])) {
            $subido = $this->guardarMarcoCumple('plantilla_marco', $rect);

            if (isset($subido['error'])) {
                return $this->alerta('simple', 'Marco no válido', $subido['error'], 'error');
            }

            $archivo = $subido['archivo'];
            $aviso   = $subido['aviso'];

            /* Si hubo que abrir la ventana o estaba en otro sitio, el
               rectángulo de la foto se lleva a donde está la ventana de
               verdad: dejarlo donde estaba guardaría una plantilla que ya
               sabemos que no se ve. */
            if ($subido['rect'] !== null) { $rect = $subido['rect']; }
        }

        $datos = [
            ':id'      => $id,
            ':nombre'  => $nombre,
            ':archivo' => $archivo,

            ':fx' => $rect['x'],
            ':fy' => $rect['y'],
            ':fw' => $rect['w'],
            ':fh' => $rect['h'],

            ':nx' => $pct('nombre_x', (float)$actual['nombre_x']),
            ':ny' => $pct('nombre_y', (float)$actual['nombre_y']),
            ':nw' => $pct('nombre_ancho', (float)$actual['nombre_ancho']),
            ':nt' => $pct('nombre_tam', (float)$actual['nombre_tam']),
            ':nc' => $col('nombre_color', (string)$actual['nombre_color']),
            ':na' => $alin('nombre_alinea', (string)$actual['nombre_alinea']),

            ':ex' => $pct('edad_x', (float)$actual['edad_x']),
            ':ey' => $pct('edad_y', (float)$actual['edad_y']),
            ':ew' => $pct('edad_ancho', (float)$actual['edad_ancho']),
            ':et' => $pct('edad_tam', (float)$actual['edad_tam']),
            ':ec' => $col('edad_color', (string)$actual['edad_color']),
            ':ea' => $alin('edad_alinea', (string)$actual['edad_alinea']),
            ':ex2'=> trim((string)($_POST['edad_texto'] ?? $actual['edad_texto'])) ?: '{edad}',

            ':ja' => (($_POST['franja_activa'] ?? '') === '1') ? 'S' : 'N',
            ':jh' => $pct('franja_alto', (float)$actual['franja_alto']),
            ':jc' => $col('franja_color', (string)$actual['franja_color']),
            ':jo' => max(0, min(100, (int)($_POST['franja_opacidad'] ?? $actual['franja_opacidad']))),

            /* El ángulo sí puede ser negativo, así que no pasa por $pct. */
            ':an' => round(max(-45, min(45,
                (float) str_replace(',', '.', (string)($_POST['angulo'] ?? $actual['angulo'])))), 2),

            ':usr' => usuario_actual_id(),
        ];

        $this->escribir(
            "UPDATE cumple_plantilla SET
                plantilla_nombre = :nombre, plantilla_archivo = :archivo,
                foto_x = :fx, foto_y = :fy, foto_ancho = :fw, foto_alto = :fh,
                nombre_x = :nx, nombre_y = :ny, nombre_ancho = :nw,
                nombre_tam = :nt, nombre_color = :nc, nombre_alinea = :na,
                edad_x = :ex, edad_y = :ey, edad_ancho = :ew,
                edad_tam = :et, edad_color = :ec, edad_alinea = :ea, edad_texto = :ex2,
                franja_activa = :ja, franja_alto = :jh, franja_color = :jc,
                franja_opacidad = :jo, angulo = :an,
                plantilla_usuarioid = :usr, plantilla_modificado = NOW()
              WHERE plantilla_id = :id", $datos);

        /* Se barren los marcos que quedaron de intentos que no llegaron a
           buen puerto. Aquí y no antes: ahora se sabe cuáles están en uso. */
        $this->limpiarMarcosHuerfanos();

        return $this->alerta('recargar', 'Guardado',
            $aviso !== ''
                ? $aviso
                : 'La tarjeta se actualizó. La vista previa ya muestra los cambios.',
            'success');
    }

    /**
     * Recibe el PNG del marco.
     *
     * PNG y no JPEG a propósito: el marco necesita su ventana TRANSPARENTE
     * para que la foto asome por detrás, y JPEG no guarda transparencia. Un
     * marco en JPEG saldría con la ventana rellena de un color y taparía la
     * foto entera, que es un fallo difícil de entender cuando ocurre.
     *
     * $rect es el área de la foto en porcentaje: hace falta para comprobar que
     * por ahí se va a ver algo.
     *
     * Devuelve ['error' => motivo] si no vale, o si vale:
     *
     *     ['archivo' => nombre, 'rect' => ?array, 'aviso' => texto]
     *
     * donde «rect» sólo viene lleno cuando hubo que recolocar el área de la
     * foto sobre la ventana real del marco.
     */
    private function guardarMarcoCumple(string $campo, array $rect): array
    {
        if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
            return ['error' => 'No llegó ningún archivo.'];
        }
        if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'No se pudo recibir el archivo. Puede que supere el '
                             . 'tamaño permitido por el servidor.'];
        }

        $tmp = $_FILES[$campo]['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return ['error' => 'El archivo no llegó por una subida válida.'];
        }

        /* getimagesize y no la extensión del nombre: la extensión la escribe
           quien sube, el contenido no se puede falsear igual de fácil. */
        $info = @getimagesize($tmp);
        if ($info === false) {
            return ['error' => 'El archivo no es una imagen.'];
        }
        if ($info[2] !== IMAGETYPE_PNG) {
            return ['error' => 'El marco tiene que ser PNG: es el único formato que guarda '
                             . 'la transparencia por la que asoma la foto. En JPEG la '
                             . 'ventana saldría rellena y taparía la foto.'];
        }
        if ($_FILES[$campo]['size'] > 8 * 1024 * 1024) {
            return ['error' => 'El marco no puede pasar de 8 MB.'];
        }

        $carpeta = $this->carpetaMarcos();
        if (!is_dir($carpeta) && !@mkdir($carpeta, 0775, true)) {
            return ['error' => 'No se pudo crear la carpeta de marcos.'];
        }
        if (!is_writable($carpeta)) {
            return ['error' => 'La carpeta de marcos no admite escritura: ' . $carpeta];
        }

        $sello  = date('YmdHis');
        $nombre = 'marco_' . $sello . '.png';
        if (!@move_uploaded_file($tmp, $carpeta . $nombre)) {
            return ['error' => 'No se pudo guardar el marco.'];
        }

        /*==============================================================
        | ¿Se verá la foto por este marco?
        |==============================================================
        | Antes esto no se comprobaba y pasó lo previsible: se subió un marco
        | cuyo damero gris estaba PINTADO —no era transparencia—, se guardó
        | sin una palabra, y las tarjetas salieron con el alumno tapado. El
        | fallo sólo se descubrió mirando el resultado impreso.
        |
        | Comprobarlo cuesta dos segundos de análisis en una pantalla que se
        | abre dos veces al año.
        */
        require_once DS_HUB_PATH . 'ds_core' . DIRECTORY_SEPARATOR . 'inc'
                   . DIRECTORY_SEPARATOR . 'marco.php';

        $a = ds_marco_analizar($carpeta . $nombre, $rect);

        if ($a['error'] !== '') {
            @unlink($carpeta . $nombre);
            return ['error' => $a['error']];
        }

        /*----------  Caso bueno: el marco ya tiene su ventana  ----------*/
        if ($a['ok']) {
            return ['archivo' => $nombre, 'rect' => null, 'aviso' => ''];
        }

        /*----------  El damero estaba pintado: se abre la ventana  ----------*/
        if ($a['damero'] !== null) {
            $im = @imagecreatefrompng($carpeta . $nombre);
            $abierto = $im
                ? ds_marco_abrir_ventana($im, $a['damero']['px'], $carpeta . 'marco_' . $sello . '_ventana.png')
                : 0;
            if ($im) { imagedestroy($im); }

            if ($abierto > 0) {
                /* El original se conserva. Si el recorte se equivocara, volver
                   atrás es cambiar el nombre del archivo en la plantilla, no
                   pedirle a nadie que busque el PNG que subió hace meses. */
                return [
                    'archivo' => 'marco_' . $sello . '_ventana.png',
                    'rect'    => ['x' => $a['damero']['x'], 'y' => $a['damero']['y'],
                                  'w' => $a['damero']['w'], 'h' => $a['damero']['h']],
                    'aviso'   => 'El marco venía con el damero de la transparencia PINTADO, '
                               . 'así que la foto habría quedado tapada. Se abrió la ventana '
                               . 'automáticamente (' . number_format($abierto, 0, ',', '.')
                               . ' píxeles) y el área de la foto se ajustó a ella. El archivo '
                               . 'original se conservó por si hace falta volver atrás.',
                ];
            }
        }

        /*----------  Tiene ventana, pero la foto no está sobre ella  ----------*/
        if ($a['alfa'] !== null) {
            return [
                'archivo' => $nombre,
                'rect'    => $a['alfa'],
                'aviso'   => 'El marco sí tiene ventana transparente, pero el área de la foto '
                           . 'estaba fuera de ella y la foto no se habría visto. El área se '
                           . 'movió sobre la ventana; revise el encuadre en el editor.',
            ];
        }

        /*----------  No hay por dónde ver la foto  ----------*/
        @unlink($carpeta . $nombre);

        return ['error' => 'Este PNG es opaco en toda el área de la foto y no se le reconoce '
                         . 'ninguna ventana, así que la tarjeta saldría sin la foto del alumno. '
                         . 'Exporte el marco desde su editor con la ventana REALMENTE vacía '
                         . '(no con el damero gris pintado encima) y vuelva a subirlo. '
                         . 'No se guardó nada.'];
    }

}
