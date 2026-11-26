<?php

namespace arena\controllers;

use PDO;

/**
 * DigiSports Arena · Punto de Venta y Caja (migración 095, 2026-11-24).
 *
 * Ventas de productos y servicios, inventario con kardex, compras
 * (separadas de la caja) y la caja de cada usuario: apertura, ingresos y
 * egresos, cierre y arqueo.
 *
 * QUÉ ENTRA EN LA CAJA
 *
 * Lo del punto de venta mientras está abierta: las ventas (las de Vender y
 * las registradas con fecha de hoy) y lo devuelto al anularlas, los
 * ingresos y egresos registrados en ella, los egresos del bar pagados con su
 * efectivo (Egresos, 100) y los pagos de compras con su efectivo. El arqueo compara el efectivo que debería haber
 * con el contado. Los cobros de reservas y los depósitos al monedero NO
 * entran (2026-11-25): se cuadran en Reportes › Pagos.
 *
 * LO QUE DICE EL SERVIDOR, NO LA PANTALLA
 *
 * Precios, costos, subtotales, descuentos, cambio y stock se calculan aquí,
 * con la base, dentro de una transacción. La pantalla los muestra para que
 * se vean al instante, pero lo que llega de ella son sólo productos y
 * cantidades.
 *
 * AUTORIZACIÓN
 *
 * Los descuentos y las anulaciones los autoriza quien tenga el permiso
 * «eliminar» sobre Ventas (o el superadministrador): si es quien vende, no
 * se le pide nada más; si no, un supervisor escribe su usuario y su clave
 * en el mismo equipo. Queda quién vendió, quién autorizó y por qué. Los
 * fallos cuentan en el freno de intentos del inicio de sesión.
 */
class posController extends arenaController
{
    /** Billetes y monedas en circulación en Ecuador (USD). */
    public const DENOMINACIONES = [100, 50, 20, 10, 5, 1, 0.50, 0.25, 0.10, 0.05, 0.01];

    public const ORIGENES_KARDEX = [
        'INI' => 'Stock inicial',        'COM' => 'Compra',
        'VEN' => 'Venta',                'ANV' => 'Venta anulada',
        'AJE' => 'Ajuste (entrada)',     'AJS' => 'Ajuste (salida)',
        'VEC' => 'Baja por vencimiento', 'ANC' => 'Compra anulada',
    ];

    /** Carpeta pública de las imágenes de productos (no son datos personales). */
    private const DIR_IMAGENES = 'imagenes/productos/';

    /*======================================================================
      Utilidades
      ====================================================================*/

    private function uid(): int
    {
        return (int)($_SESSION['usuarioid'] ?? 0);
    }

    /** Respuesta JSON de las operaciones del punto de venta. */
    private function json(array $a): string
    {
        return json_encode($a, JSON_UNESCAPED_UNICODE);
    }

    private function error(string $texto, string $titulo = 'No se pudo completar'): string
    {
        return $this->json(['ok' => false, 'tipo' => 'simple', 'icono' => 'error', 'titulo' => $titulo, 'texto' => $texto]);
    }

    /** «$ 1,250.50» o «1.250,50» → 1250.5 (dos decimales). */
    private static function numero($v): float
    {
        $s = trim(str_replace(['$', ' '], '', (string)$v));
        if (preg_match('/^\d{1,3}(\.\d{3})+,\d+$/', $s) || preg_match('/^\d+,\d{1,2}$/', $s)) {
            $s = str_replace(['.', ','], ['', '.'], $s);          /* 1.250,50 o 2,50 */
        } else {
            $s = str_replace(',', '', $s);                          /* 1,250.50 */
        }
        return is_numeric($s) ? round((float)$s, 2) : 0.0;
    }

    private static function texto($v, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string)$v) ?? ''), 0, $max);
    }

    /** Un motivo que explique algo: MOTIVO_MINIMO letras o números. */
    private static function motivo($v): string
    {
        $m = self::texto($v, 200);
        return preg_match_all('/[\p{L}\p{N}]/u', $m) >= self::MOTIVO_MINIMO ? $m : '';
    }

    public function formasCobro(): array
    {
        return $this->formasIngreso();
    }

    private function forma(int $id): ?array
    {
        $f = $this->filas("SELECT * FROM dsa_forma_ingreso WHERE forma_id = :f AND forma_estado = 'A'", [':f' => $id]);
        return $f[0] ?? null;
    }

    /*======================================================================
      Autorización de un supervisor
      ====================================================================*/

    /** ¿El usuario de la sesión puede autorizar descuentos y anulaciones? */
    public function puedeAutorizar(): bool
    {
        return es_superadministrador() || puede_eliminar('posVentas');
    }

    /**
     * Quién autoriza: el propio usuario si puede, o el supervisor cuyo usuario
     * y clave llegan en aut_usuario / aut_clave. [id, error].
     */
    private function autorizador(): array
    {
        if ($this->puedeAutorizar()) {
            return [$this->uid(), null];
        }

        $usuario = self::texto($_POST['aut_usuario'] ?? '', 20);
        $clave   = (string)($_POST['aut_clave'] ?? '');
        if ($usuario === '' || $clave === '') {
            return [0, 'Hace falta la autorización de un supervisor: su usuario y su contraseña.'];
        }
        if (strlen($clave) > 200) {
            return [0, 'Usuario o contraseña del supervisor incorrectos.'];
        }

        $segundos = 0;
        if (intentos_frenado($usuario, $segundos)) {
            return [0, 'Demasiados intentos fallidos con ese usuario. Espere ' . max(1, (int)ceil($segundos / 60)) . ' minuto(s).'];
        }

        $u = $this->filas(
            "SELECT usuario_id, usuario_rolid, usuario_clave, usuario_estado, usuario_tienebloqueo
               FROM seguridad_usuario WHERE usuario_usuario = :u", [':u' => $usuario])[0] ?? null;

        if (!$u || !password_verify($clave, (string)$u['usuario_clave'])) {
            if (!$u) { password_verify($clave, intentos_hash_senuelo()); }
            intentos_registrar($usuario, false);
            return [0, 'Usuario o contraseña del supervisor incorrectos.'];
        }
        if ($u['usuario_estado'] !== 'A' || $u['usuario_tienebloqueo'] === 'S') {
            intentos_registrar($usuario, false);
            return [0, 'Ese usuario no está activo.'];
        }

        $rol = (int)$u['usuario_rolid'];
        $permiso = $rol === self_rol_superadmin() || (int)$this->escalar(
            "SELECT COUNT(*) FROM seguridad_permiso p
               JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
              WHERE p.permiso_rolid = :r AND p.permiso_estado = 'A' AND p.permiso_eliminar = 'S'
                AND m.menu_modulo = 'arena' AND m.menu_vista = 'posVentas'", [':r' => $rol]) > 0;

        if (!$permiso) {
            /* La clave era buena: no es un intento fallido de acceso. */
            return [0, 'Ese usuario no tiene permiso para autorizar descuentos ni anulaciones.'];
        }
        intentos_registrar($usuario, true);
        return [(int)$u['usuario_id'], null];
    }

    /*======================================================================
      Caja
      ====================================================================*/

    /** La caja abierta de un usuario (por omisión, el de la sesión). */
    public function cajaAbierta(int $usuario = 0): ?array
    {
        $usuario = $usuario ?: $this->uid();
        return $this->filas(
            "SELECT c.*, u.usuario_usuario, s.sede_nombre
               FROM dsa_caja c
               LEFT JOIN seguridad_usuario u ON u.usuario_id = c.caja_usuarioid
               LEFT JOIN general_sede s ON s.sede_id = c.caja_sedeid
              WHERE c.caja_usuarioid = :u AND c.caja_estado = 'A'", [':u' => $usuario])[0] ?? null;
    }

    public function caja(int $id): ?array
    {
        return $this->filas(
            "SELECT c.*, u.usuario_usuario, s.sede_nombre
               FROM dsa_caja c
               LEFT JOIN seguridad_usuario u ON u.usuario_id = c.caja_usuarioid
               LEFT JOIN general_sede s ON s.sede_id = c.caja_sedeid
              WHERE c.caja_id = :c", [':c' => $id])[0] ?? null;
    }

    /** ¿Puede ver y cerrar las cajas de los demás? */
    public function supervisaCajas(): bool
    {
        return es_superadministrador() || puede_editar('posCaja');
    }

    /** Las últimas cajas: las propias, o todas si supervisa. */
    public function cajas(int $limite = 40): array
    {
        $sql = "SELECT c.caja_id, c.caja_usuarioid, c.caja_apertura, c.caja_cierre, c.caja_saldoinicial,
                       c.caja_esperado, c.caja_contado, c.caja_diferencia, c.caja_estado, c.caja_observacion,
                       u.usuario_usuario, s.sede_nombre,
                       (SELECT COUNT(*) FROM dsa_venta v WHERE v.venta_cajaid = c.caja_id AND v.venta_estado = 'A') AS ventas,
                       (SELECT COALESCE(SUM(v.venta_total), 0) FROM dsa_venta v WHERE v.venta_cajaid = c.caja_id AND v.venta_estado = 'A') AS vendido
                  FROM dsa_caja c
                  LEFT JOIN seguridad_usuario u ON u.usuario_id = c.caja_usuarioid
                  LEFT JOIN general_sede s ON s.sede_id = c.caja_sedeid";
        $p = [];
        if (!$this->supervisaCajas()) { $sql .= " WHERE c.caja_usuarioid = :u"; $p[':u'] = $this->uid(); }
        return $this->filas($sql . " ORDER BY c.caja_estado = 'A' DESC, c.caja_apertura DESC LIMIT " . max(1, min(200, $limite)), $p);
    }

    /** Sedes de alquiler para elegir al abrir (si hay más de una). */
    public function sedesCaja(): array
    {
        return $this->sedesAlquiler();
    }

    /**
     * Las cifras de una caja: lo que entró y salió, por medio, y el efectivo
     * que debería haber. Sirve igual para una abierta (hasta ahora) que para
     * una cerrada (hasta su cierre).
     */
    public function resumenCaja(array $c): array
    {
        $id    = (int)$c['caja_id'];

        $r = [
            'inicial' => (float)$c['caja_saldoinicial'],
            'ventasEfectivo' => 0.0, 'reservasEfectivo' => 0.0, 'depositosEfectivo' => 0.0,
            'ingresosEfectivo' => 0.0, 'egresosEfectivo' => 0.0, 'devolucionesEfectivo' => 0.0,
            'ventas' => 0, 'vendido' => 0.0, 'anuladas' => 0, 'anulado' => 0.0, 'descuentos' => 0.0,
            'cobrosReservas' => 0, 'monederoAplicado' => 0.0,
            'porForma' => [],      // nombre => ['ventas' => x, 'reservas' => y, 'otros' => z, 'total' => t, 'efectivo' => bool]
        ];
        $sumarForma = static function (array &$r, string $nombre, string $clave, float $v, bool $efectivo) {
            $r['porForma'][$nombre] ??= ['ventas' => 0.0, 'reservas' => 0.0, 'otros' => 0.0, 'total' => 0.0, 'efectivo' => $efectivo];
            $r['porForma'][$nombre][$clave] += $v;
            $r['porForma'][$nombre]['total'] += $v;
        };

        /* Ventas del punto de venta en esta caja. */
        foreach ($this->filas(
            "SELECT f.forma_codigo, f.forma_nombre, f.forma_esmonedero, vp.pago_valor
               FROM dsa_venta v
               JOIN dsa_venta_pago vp ON vp.pago_ventaid = v.venta_id
               JOIN dsa_forma_ingreso f ON f.forma_id = vp.pago_formaid
              WHERE v.venta_cajaid = :c AND v.venta_estado = 'A'", [':c' => $id]) as $p) {
            $v = (float)$p['pago_valor'];
            if ($p['forma_esmonedero'] === 'S') { $r['monederoAplicado'] += $v; continue; }
            $sumarForma($r, $p['forma_nombre'], 'ventas', $v, $p['forma_codigo'] === 'EFE');
            if ($p['forma_codigo'] === 'EFE') { $r['ventasEfectivo'] += $v; }
        }
        foreach ($this->filas(
            "SELECT venta_estado, COUNT(*) n, COALESCE(SUM(venta_total), 0) t, COALESCE(SUM(venta_descuento), 0) d
               FROM dsa_venta WHERE venta_cajaid = :c GROUP BY venta_estado", [':c' => $id]) as $v) {
            if ($v['venta_estado'] === 'A') { $r['ventas'] = (int)$v['n']; $r['vendido'] = (float)$v['t']; $r['descuentos'] = (float)$v['d']; }
            else { $r['anuladas'] = (int)$v['n']; $r['anulado'] = (float)$v['t']; }
        }

        /* Ingresos y egresos registrados en la caja (manuales, pagos de
           compras, devoluciones de ventas anuladas). */
        $nombresForma = array_column($this->formasIngreso(), 'forma_nombre', 'forma_codigo');
        foreach ($this->filas(
            "SELECT movimiento_tipo, movimiento_forma, SUM(movimiento_valor) v
               FROM dsa_caja_movimiento WHERE movimiento_cajaid = :c AND movimiento_estado = 'A'
              GROUP BY movimiento_tipo, movimiento_forma", [':c' => $id]) as $m) {
            $v = (float)$m['v'];
            $ef = $m['movimiento_forma'] === 'EFE';
            if ($m['movimiento_tipo'] === 'I') {
                if ($ef) { $r['ingresosEfectivo'] += $v; }
                $sumarForma($r, $nombresForma[$m['movimiento_forma']] ?? $m['movimiento_forma'], 'otros', $v, $ef);
            } else {
                if ($ef) { $r['egresosEfectivo'] += $v; }
                $sumarForma($r, $nombresForma[$m['movimiento_forma']] ?? $m['movimiento_forma'], 'otros', -$v, $ef);
            }
        }

        /* Los cobros de reservas, los depósitos al monedero y sus devoluciones
           NO entran en la caja (2026-11-25): la caja es del punto de venta
           (ventas, sus anulaciones, ingresos, egresos y pagos de compras).
           Lo de las reservas se cuadra en Reportes › Pagos, que tiene su
           propio cuadre de efectivo. reservasEfectivo, depositosEfectivo,
           devolucionesEfectivo y cobrosReservas quedan en 0; sólo las cajas
           cerradas antes de este cambio los tienen en su resumen guardado. */

        foreach ($r as $k => $v) { if (is_float($v)) { $r[$k] = round($v, 2); } }
        $r['esperado'] = round($r['inicial'] + $r['ventasEfectivo'] + $r['reservasEfectivo'] + $r['depositosEfectivo']
                             + $r['ingresosEfectivo'] - $r['egresosEfectivo'] - $r['devolucionesEfectivo'], 2);
        uasort($r['porForma'], static fn ($a, $b) => [$b['efectivo'], $b['total']] <=> [$a['efectivo'], $a['total']]);
        return $r;
    }

    /** Todo lo que pasó en la caja, en orden de hora (para la pantalla Caja). */
    public function movimientosCaja(array $c): array
    {
        $id = (int)$c['caja_id'];
        $movs = [];
        foreach ($this->filas(
            "SELECT v.venta_id, v.venta_numero, v.venta_total, v.venta_estado, UNIX_TIMESTAMP(v.venta_fecharegistro) ts,
                    GROUP_CONCAT(f.forma_nombre SEPARATOR ', ') formas, cl.cliente_nombre
               FROM dsa_venta v
               LEFT JOIN dsa_venta_pago vp ON vp.pago_ventaid = v.venta_id
               LEFT JOIN dsa_forma_ingreso f ON f.forma_id = vp.pago_formaid
               LEFT JOIN dsa_cliente cl ON cl.cliente_id = v.venta_clienteid
              WHERE v.venta_cajaid = :c GROUP BY v.venta_id", [':c' => $id]) as $v) {
            $movs[] = ['ts' => (int)$v['ts'], 'tipo' => 'venta', 'id' => (int)$v['venta_id'],
                       'titulo' => 'Venta ' . $v['venta_numero'], 'detalle' => (string)($v['cliente_nombre'] ?? ''),
                       'forma' => (string)$v['formas'], 'valor' => (float)$v['venta_total'], 'signo' => 1,
                       'anulada' => $v['venta_estado'] === 'X'];
        }
        $nombres = array_column($this->formasIngreso(), 'forma_nombre', 'forma_codigo');
        foreach ($this->filas(
            "SELECT m.*, UNIX_TIMESTAMP(m.movimiento_fecharegistro) ts FROM dsa_caja_movimiento m
              WHERE m.movimiento_cajaid = :c AND m.movimiento_estado = 'A'", [':c' => $id]) as $m) {
            $movs[] = ['ts' => (int)$m['ts'], 'tipo' => $m['movimiento_tipo'] === 'I' ? 'ingreso' : 'egreso', 'id' => (int)$m['movimiento_id'],
                       'titulo' => $m['movimiento_concepto'], 'detalle' => (string)$m['movimiento_detalle'],
                       'forma' => $nombres[$m['movimiento_forma']] ?? $m['movimiento_forma'], 'valor' => (float)$m['movimiento_valor'],
                       'signo' => $m['movimiento_tipo'] === 'I' ? 1 : -1, 'anulada' => false];
        }
        /* Los cobros de reservas y del monedero no son de la caja (ver resumenCaja). */
        usort($movs, static fn ($a, $b) => [$b['ts'], $b['id']] <=> [$a['ts'], $a['id']]);
        return $movs;
    }

    /** Suma un conteo de billetes y monedas: {"20": 3, "0.25": 4} → 61.00. */
    private static function sumarConteo($json): array
    {
        $c = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($c)) { return [null, null]; }
        $limpio = []; $total = 0.0;
        foreach (self::DENOMINACIONES as $d) {
            $k = number_format($d, 2, '.', '');
            $n = (int)($c[$k] ?? $c[(string)$d] ?? 0);
            if ($n > 0 && $n < 100000) { $limpio[$k] = $n; $total += $n * $d; }
        }
        return [round($total, 2), $limpio ? json_encode($limpio) : null];
    }

    /** AJAX posAbrirCaja. */
    public function abrirCaja(): string
    {
        if (!puede_crear('posCaja') && !puede_crear('posVenta')) {
            return $this->error('Su rol no puede abrir caja.', 'Acceso denegado');
        }
        if ($this->cajaAbierta()) {
            return $this->error('Ya tiene una caja abierta. Ciérrela antes de abrir otra.');
        }
        [$contado, $conteo] = self::sumarConteo($_POST['conteo'] ?? '');
        $inicial = $contado ?? self::numero($_POST['saldo_inicial'] ?? 0);
        if ($inicial < 0 || $inicial > 100000) {
            return $this->error('El saldo inicial no es válido.');
        }
        $sedes = $this->sedesCaja();
        $sede  = (int)($_POST['sede'] ?? 0);
        if (!in_array($sede, array_map('intval', array_column($sedes, 'sede_id')), true)) {
            $sede = (int)($sedes[0]['sede_id'] ?? 0);
        }
        $n = $this->escribir(
            "INSERT INTO dsa_caja (caja_usuarioid, caja_sedeid, caja_apertura, caja_saldoinicial, caja_conteo)
             VALUES (:u, :s, NOW(), :i, :c)",
            [':u' => $this->uid(), ':s' => $sede ?: null, ':i' => $inicial, ':c' => $conteo]);
        if ($n !== 1) {
            return $this->error('No fue posible abrir la caja. ¿Ya hay una abierta con su usuario?');
        }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Caja abierta',
                            'texto' => 'Saldo inicial: $' . number_format($inicial, 2) . '. '
                                     . (puede_crear('posVenta') ? 'Ya puede vender.' : 'Ya puede registrar ingresos y egresos en ella.')]);
    }

    /** AJAX posMovimientoCaja: un ingreso o egreso adicional. */
    public function movimientoCaja(): string
    {
        if (!puede_crear('posCaja')) {
            return $this->error('Su rol no puede registrar movimientos de caja.', 'Acceso denegado');
        }
        $caja = $this->cajaAbierta();
        if (!$caja) { return $this->error('Primero abra su caja.'); }

        $tipo     = ($_POST['tipo'] ?? '') === 'E' ? 'E' : 'I';
        $concepto = self::motivo($_POST['concepto'] ?? '');
        $valor    = self::numero($_POST['valor'] ?? 0);
        $forma    = strtoupper(substr((string)($_POST['forma'] ?? 'EFE'), 0, 3));
        $formas   = array_column(array_filter($this->formasIngreso(), static fn ($f) => $f['forma_esmonedero'] !== 'S'), 'forma_codigo');
        if ($concepto === '') { return $this->error('Escriba el concepto: en qué se gastó o de dónde vino el dinero.'); }
        if ($valor <= 0 || $valor > 100000) { return $this->error('El valor debe ser mayor que cero.'); }
        if (!in_array($forma, $formas, true)) { $forma = 'EFE'; }

        if ($tipo === 'E' && $forma === 'EFE') {
            $r = $this->resumenCaja($caja);
            if ($valor > $r['esperado'] + 0.001) {
                return $this->error('En la caja debería haber $' . number_format($r['esperado'], 2) . ' en efectivo: no alcanza para este egreso.');
            }
        }
        $n = $this->escribir(
            "INSERT INTO dsa_caja_movimiento (movimiento_cajaid, movimiento_tipo, movimiento_origen, movimiento_concepto,
                                              movimiento_forma, movimiento_valor, movimiento_detalle, movimiento_usuarioid)
             VALUES (:c, :t, 'MAN', :co, :f, :v, :d, :u)",
            [':c' => $caja['caja_id'], ':t' => $tipo, ':co' => $concepto, ':f' => $forma, ':v' => $valor,
             ':d' => self::texto($_POST['detalle'] ?? '', 200) ?: null, ':u' => $this->uid()]);
        return $n === 1
            ? $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success',
                           'titulo' => $tipo === 'I' ? 'Ingreso registrado' : 'Egreso registrado',
                           'texto' => $concepto . ': $' . number_format($valor, 2)])
            : $this->error('No fue posible registrar el movimiento.');
    }

    /** AJAX posCerrarCaja: arqueo y cierre. */
    public function cerrarCaja(): string
    {
        $cajaId = (int)($_POST['caja_id'] ?? 0);
        $caja = $cajaId ? $this->caja($cajaId) : $this->cajaAbierta();
        if (!$caja || $caja['caja_estado'] !== 'A') { return $this->error('No hay una caja abierta para cerrar.'); }
        $propia = (int)$caja['caja_usuarioid'] === $this->uid();
        if (!($propia && (puede_crear('posCaja') || puede_crear('posVenta'))) && !$this->supervisaCajas()) {
            return $this->error('Sólo quien la abrió o un supervisor puede cerrar esta caja.', 'Acceso denegado');
        }

        [$contado, $conteo] = self::sumarConteo($_POST['conteo'] ?? '');
        if ($contado === null) {
            if (!isset($_POST['contado']) || trim((string)$_POST['contado']) === '') {
                return $this->error('Cuente el efectivo del cajón y escriba cuánto hay.');
            }
            $contado = self::numero($_POST['contado']);
        }
        if ($contado < 0) { return $this->error('El efectivo contado no es válido.'); }

        $r = $this->resumenCaja($caja);
        $diferencia = round($contado - $r['esperado'], 2);
        $obs = self::texto($_POST['observacion'] ?? '', 250);
        if (abs($diferencia) >= 0.01 && self::motivo($obs) === '') {
            return $this->error(($diferencia < 0 ? 'Faltan $' : 'Sobran $') . number_format(abs($diferencia), 2)
                . ' en el cajón. Escriba una observación que lo explique antes de cerrar.', 'Falta la observación');
        }

        $n = $this->escribir(
            "UPDATE dsa_caja SET caja_estado = 'C', caja_cierre = NOW(), caja_esperado = :e, caja_contado = :c,
                    caja_diferencia = :d, caja_conteo = COALESCE(:co, caja_conteo), caja_observacion = :o, caja_resumen = :r
              WHERE caja_id = :id AND caja_estado = 'A'",
            [':e' => $r['esperado'], ':c' => $contado, ':d' => $diferencia, ':co' => $conteo,
             ':o' => $obs ?: null, ':r' => json_encode($r, JSON_UNESCAPED_UNICODE), ':id' => $caja['caja_id']]);
        if ($n !== 1) { return $this->error('No fue posible cerrar la caja.'); }

        $texto = 'Esperado $' . number_format($r['esperado'], 2) . ' · contado $' . number_format($contado, 2) . ' · '
               . (abs($diferencia) < 0.01 ? 'cuadra exacto.' : ($diferencia > 0 ? 'sobran $' . number_format($diferencia, 2) . '.' : 'faltan $' . number_format(-$diferencia, 2) . '.'));
        return $this->json(['ok' => true, 'tipo' => 'redireccionar', 'icono' => abs($diferencia) < 0.01 ? 'success' : 'warning',
                            'titulo' => 'Caja cerrada', 'texto' => $texto,
                            'url' => APP_URL . 'posCaja/?id=' . (int)$caja['caja_id']]);
    }

    /*======================================================================
      Productos e inventario
      ====================================================================*/

    public function categorias(bool $todas = false): array
    {
        return $this->filas(
            "SELECT c.*, (SELECT COUNT(*) FROM dsa_producto p WHERE p.producto_categoriaid = c.categoria_id AND p.producto_estado = 'A') AS productos
               FROM dsa_producto_categoria c" . ($todas ? '' : " WHERE c.categoria_estado = 'A'") . "
              ORDER BY c.categoria_orden, c.categoria_nombre");
    }

    /** Productos: activos para vender, o todos (menos los borrados) para administrar. */
    public function productos(bool $soloActivos = false): array
    {
        return $this->filas(
            "SELECT p.*, c.categoria_nombre,
                    (SELECT COALESCE(SUM(d.detalle_cantidad), 0) FROM dsa_venta_detalle d JOIN dsa_venta v ON v.venta_id = d.detalle_ventaid
                      WHERE d.detalle_productoid = p.producto_id AND v.venta_estado = 'A'
                        AND v.venta_fecharegistro >= CURDATE() - INTERVAL 30 DAY) AS vendidos30
               FROM dsa_producto p
               LEFT JOIN dsa_producto_categoria c ON c.categoria_id = p.producto_categoriaid
              WHERE p.producto_estado " . ($soloActivos ? "= 'A'" : "IN ('A','I')") . "
              ORDER BY vendidos30 DESC, p.producto_nombre");
    }

    public function producto(int $id): ?array
    {
        return $this->filas(
            "SELECT p.*, c.categoria_nombre FROM dsa_producto p
               LEFT JOIN dsa_producto_categoria c ON c.categoria_id = p.producto_categoriaid
              WHERE p.producto_id = :p", [':p' => $id])[0] ?? null;
    }

    public function urlImagen(?string $archivo): string
    {
        $archivo = basename((string)$archivo);
        return $archivo !== '' && is_file(__DIR__ . '/../' . self::DIR_IMAGENES . $archivo)
            ? APP_URL . self::DIR_IMAGENES . rawurlencode($archivo) : '';
    }

    /**
     * Mueve el stock de un producto dentro de la transacción en curso y deja
     * su línea en el kardex. Una salida que dejaría el stock en negativo
     * lanza excepción (quien llama deshace todo).
     */
    private function moverStock(PDO $con, int $productoId, string $tipo, string $origen, float $cantidad,
                                float $costo, array $extra = []): void
    {
        $st = $con->prepare("SELECT producto_stock, producto_nombre, producto_tipo FROM dsa_producto WHERE producto_id = :p FOR UPDATE");
        $st->execute([':p' => $productoId]);
        $p = $st->fetch(PDO::FETCH_ASSOC);
        if (!$p) { throw new \RuntimeException('El producto ya no existe.'); }
        if ($p['producto_tipo'] === 'S') { return; }   /* un servicio no lleva stock */

        $antes = (float)$p['producto_stock'];
        $nuevo = round($tipo === 'E' ? $antes + $cantidad : $antes - $cantidad, 2);
        if ($nuevo < 0) {
            throw new \RuntimeException('No hay stock suficiente de «' . $p['producto_nombre'] . '»: quedan '
                . rtrim(rtrim(number_format($antes, 2), '0'), '.') . '.');
        }
        $con->prepare("UPDATE dsa_producto SET producto_stock = :s WHERE producto_id = :p")->execute([':s' => $nuevo, ':p' => $productoId]);
        $con->prepare(
            "INSERT INTO dsa_kardex (kardex_productoid, kardex_tipo, kardex_origen, kardex_cantidad, kardex_costo,
                                     kardex_stockanterior, kardex_stocknuevo, kardex_ventaid, kardex_compraid, kardex_detalle, kardex_usuarioid)
             VALUES (:p, :t, :o, :c, :co, :a, :n, :v, :cm, :d, :u)"
        )->execute([':p' => $productoId, ':t' => $tipo, ':o' => $origen, ':c' => $cantidad, ':co' => $costo,
                    ':a' => $antes, ':n' => $nuevo, ':v' => $extra['venta'] ?? null, ':cm' => $extra['compra'] ?? null,
                    ':d' => $extra['detalle'] ?? null, ':u' => $this->uid() ?: null]);
    }

    public function kardex(int $productoId, int $limite = 300): array
    {
        return $this->filas(
            "SELECT k.*, u.usuario_usuario, v.venta_numero, c.compra_proveedor
               FROM dsa_kardex k
               LEFT JOIN seguridad_usuario u ON u.usuario_id = k.kardex_usuarioid
               LEFT JOIN dsa_venta v ON v.venta_id = k.kardex_ventaid
               LEFT JOIN dsa_compra c ON c.compra_id = k.kardex_compraid
              WHERE k.kardex_productoid = :p
              ORDER BY k.kardex_id DESC LIMIT " . max(1, min(1000, $limite)), [':p' => $productoId]);
    }

    /**
     * Lo que pide atención en el inventario: agotados, bajo el mínimo y lotes
     * vencidos o por vencer (30 días).
     *
     * Los lotes no se descuentan uno a uno: se supone que se vende primero lo
     * más antiguo, así que el stock actual es de las compras más recientes.
     * Un lote «quedan N» es esa estimación.
     */
    public function alertasInventario(int $diasAviso = 30): array
    {
        $a = ['agotados' => [], 'bajos' => [], 'vencidos' => [], 'porVencer' => []];
        foreach ($this->productos(true) as $p) {
            if ($p['producto_tipo'] !== 'P') { continue; }
            $s = (float)$p['producto_stock'];
            if ($s <= 0) { $a['agotados'][] = $p; }
            elseif ((float)$p['producto_stockminimo'] > 0 && $s <= (float)$p['producto_stockminimo']) { $a['bajos'][] = $p; }
        }
        $lotes = $this->filas(
            "SELECT p.producto_id, p.producto_nombre, p.producto_codigo, p.producto_stock,
                    d.detalle_cantidad, d.detalle_vence, c.compra_fecha, d.detalle_id
               FROM dsa_producto p
               JOIN dsa_compra_detalle d ON d.detalle_productoid = p.producto_id
               JOIN dsa_compra c ON c.compra_id = d.detalle_compraid AND c.compra_estado = 'A'
              WHERE p.producto_estado = 'A' AND p.producto_vence = 'S' AND p.producto_stock > 0
              ORDER BY p.producto_id, c.compra_fecha DESC, d.detalle_id DESC");
        $restante = [];
        $hoy = date('Y-m-d');
        $limite = date('Y-m-d', strtotime("+$diasAviso days"));
        foreach ($lotes as $l) {
            $pid = (int)$l['producto_id'];
            $restante[$pid] = $restante[$pid] ?? (float)$l['producto_stock'];
            $quedan = min((float)$l['detalle_cantidad'], $restante[$pid]);
            $restante[$pid] -= $quedan;
            if ($quedan <= 0 || !$l['detalle_vence']) { continue; }
            $l['quedan'] = round($quedan, 2);
            if ($l['detalle_vence'] < $hoy) { $a['vencidos'][] = $l; }
            elseif ($l['detalle_vence'] <= $limite) { $a['porVencer'][] = $l; }
        }
        usort($a['porVencer'], static fn ($x, $y) => strcmp($x['detalle_vence'], $y['detalle_vence']));
        return $a;
    }

    /** Siguiente código libre: P0001, P0002… */
    public function codigoProductoSugerido(): string
    {
        $max = (int)$this->escalar("SELECT COALESCE(MAX(CAST(SUBSTRING(producto_codigo, 2) AS UNSIGNED)), 0)
                                      FROM dsa_producto WHERE producto_codigo REGEXP '^P[0-9]+$'");
        return 'P' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** AJAX posGuardarProducto (alta y edición, con imagen opcional). */
    public function guardarProducto(): string
    {
        $id = (int)($_POST['producto_id'] ?? 0);
        if (!($id ? puede_editar('posProductos') : puede_crear('posProductos'))) {
            return $this->error('Su rol no puede ' . ($id ? 'editar' : 'crear') . ' productos.', 'Acceso denegado');
        }
        $actual = $id ? $this->producto($id) : null;
        if ($id && !$actual) { return $this->error('El producto no existe.'); }

        $codigo = strtoupper(self::texto($_POST['codigo'] ?? '', 30));
        $nombre = self::texto($_POST['nombre'] ?? '', 100);
        $tipo   = ($_POST['tipo'] ?? 'P') === 'S' ? 'S' : 'P';
        $pc     = self::numero($_POST['precio_compra'] ?? 0);
        $pv     = self::numero($_POST['precio_venta'] ?? 0);
        $min    = $tipo === 'P' ? max(0.0, self::numero($_POST['stock_minimo'] ?? 0)) : 0.0;
        $vence  = $tipo === 'P' && ($_POST['vence'] ?? 'N') === 'S' ? 'S' : 'N';
        $estado = ($_POST['estado'] ?? 'A') === 'I' ? 'I' : 'A';
        $inicial = !$id && $tipo === 'P' ? max(0.0, self::numero($_POST['stock_inicial'] ?? 0)) : 0.0;

        if ($codigo === '' || !preg_match('/^[A-Z0-9._-]+$/', $codigo)) {
            return $this->error('El código sólo puede tener letras, números, punto, guion y guion bajo.');
        }
        if (mb_strlen($nombre) < 2) { return $this->error('Escriba el nombre del producto.'); }
        if ($pv <= 0) { return $this->error('El precio de venta debe ser mayor que cero.'); }
        if ((int)$this->escalar("SELECT COUNT(*) FROM dsa_producto WHERE producto_codigo = :c AND producto_id <> :i",
                                [':c' => $codigo, ':i' => $id]) > 0) {
            return $this->error('Ya hay otro producto con el código ' . $codigo . '.');
        }

        /* Categoría: una existente o una nueva escrita en el momento. */
        $cat = (int)($_POST['categoria_id'] ?? 0);
        $nuevaCat = self::texto($_POST['categoria_nueva'] ?? '', 60);
        if ($nuevaCat !== '') {
            $cat = (int)$this->escalar("SELECT categoria_id FROM dsa_producto_categoria WHERE categoria_nombre = :n", [':n' => $nuevaCat]);
            if (!$cat) {
                $this->escribir("INSERT INTO dsa_producto_categoria (categoria_nombre, categoria_orden)
                                 SELECT :n, COALESCE(MAX(categoria_orden), 0) + 1 FROM dsa_producto_categoria", [':n' => $nuevaCat]);
                $cat = (int)$this->escalar("SELECT categoria_id FROM dsa_producto_categoria WHERE categoria_nombre = :n", [':n' => $nuevaCat]);
            }
        }
        $cat = $cat > 0 && (int)$this->escalar("SELECT COUNT(*) FROM dsa_producto_categoria WHERE categoria_id = :c", [':c' => $cat]) ? $cat : null;

        /* La imagen, antes de tocar la base: si no es válida no se guarda nada. */
        [$imagen, $errImg] = $this->subirImagen($_FILES['imagen'] ?? null);
        if ($errImg !== null) { return $this->error($errImg); }
        $quitar = ($_POST['quitar_imagen'] ?? '') === '1';

        $con = $this->con();
        if ($con === null) { return $this->error('Sin conexión con la base de datos.'); }
        try {
            $con->beginTransaction();
            if ($id) {
                $con->prepare(
                    "UPDATE dsa_producto SET producto_codigo = :co, producto_nombre = :n, producto_categoriaid = :ca,
                            producto_tipo = :t, producto_preciocompra = :pc, producto_precioventa = :pv,
                            producto_stockminimo = :m, producto_vence = :v, producto_estado = :e"
                    . ($imagen !== null || $quitar ? ", producto_imagen = :img" : "") . "
                      WHERE producto_id = :id"
                )->execute(array_merge([':co' => $codigo, ':n' => $nombre, ':ca' => $cat, ':t' => $tipo, ':pc' => $pc, ':pv' => $pv,
                                        ':m' => $min, ':v' => $vence, ':e' => $estado, ':id' => $id],
                                       $imagen !== null || $quitar ? [':img' => $imagen] : []));
            } else {
                $con->prepare(
                    "INSERT INTO dsa_producto (producto_codigo, producto_nombre, producto_categoriaid, producto_tipo,
                            producto_preciocompra, producto_precioventa, producto_stock, producto_stockminimo,
                            producto_vence, producto_imagen, producto_estado, producto_usuarioid)
                     VALUES (:co, :n, :ca, :t, :pc, :pv, 0, :m, :v, :img, :e, :u)"
                )->execute([':co' => $codigo, ':n' => $nombre, ':ca' => $cat, ':t' => $tipo, ':pc' => $pc, ':pv' => $pv,
                            ':m' => $min, ':v' => $vence, ':img' => $imagen, ':e' => $estado, ':u' => $this->uid() ?: null]);
                $id = (int)$con->lastInsertId();
                if ($inicial > 0) {
                    $this->moverStock($con, $id, 'E', 'INI', $inicial, $pc, ['detalle' => 'Stock al crear el producto']);
                }
            }
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            if ($imagen) { @unlink(__DIR__ . '/../' . self::DIR_IMAGENES . $imagen); }
            return $this->error('No fue posible guardar el producto.');
        }
        /* La imagen anterior ya no se usa. */
        if ($actual && ($imagen !== null || $quitar) && $actual['producto_imagen']) {
            @unlink(__DIR__ . '/../' . self::DIR_IMAGENES . basename($actual['producto_imagen']));
        }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success',
                            'titulo' => $actual ? 'Producto actualizado' : 'Producto creado', 'texto' => $codigo . ' · ' . $nombre]);
    }

    /**
     * Guarda la imagen subida: JPG, PNG o WebP de hasta 4 MB, reducida a 600 px
     * y con un nombre que pone el servidor. [archivo|null, error|null].
     */
    private function subirImagen($f): array
    {
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || (int)($f['size'] ?? 0) === 0) {
            return [null, null];
        }
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) { return [null, 'La imagen no llegó completa. Inténtelo de nuevo.']; }
        if ($f['size'] > 4 * 1024 * 1024) { return [null, 'La imagen pesa más de 4 MB.']; }
        $info = @getimagesize($f['tmp_name']);
        $tipos = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if (!$info || !isset($tipos[$info[2]])) { return [null, 'La imagen debe ser JPG, PNG o WebP.']; }
        if (!function_exists('imagecreatetruecolor')) { return [null, 'El servidor no puede procesar imágenes (falta GD).']; }

        $origen = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']),
            IMAGETYPE_PNG  => @imagecreatefrompng($f['tmp_name']),
            default        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false,
        };
        if (!$origen) { return [null, 'No se pudo leer la imagen.']; }

        [$w, $h] = [$info[0], $info[1]];
        $esc = min(1, 600 / max($w, $h));
        [$nw, $nh] = [max(1, (int)round($w * $esc)), max(1, (int)round($h * $esc))];
        $lienzo = imagecreatetruecolor($nw, $nh);
        $png = $info[2] !== IMAGETYPE_JPEG;
        if ($png) { imagealphablending($lienzo, false); imagesavealpha($lienzo, true); }
        imagecopyresampled($lienzo, $origen, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $dir = __DIR__ . '/../' . self::DIR_IMAGENES;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { return [null, 'No se pudo crear la carpeta de imágenes.']; }
        $nombre = 'prod_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . ($png ? '.png' : '.jpg');
        $ok = $png ? imagepng($lienzo, $dir . $nombre, 6) : imagejpeg($lienzo, $dir . $nombre, 82);
        /* Sin imagedestroy(): desde PHP 8 la imagen se libera sola (y en 8.5 está obsoleta). */
        return $ok ? [$nombre, null] : [null, 'No se pudo guardar la imagen.'];
    }

    /** AJAX posAjustarStock: entrada, salida o baja por vencimiento. */
    public function ajustarStock(): string
    {
        if (!puede_editar('posProductos')) { return $this->error('Su rol no puede ajustar el inventario.', 'Acceso denegado'); }
        $p = $this->producto((int)($_POST['producto_id'] ?? 0));
        if (!$p || $p['producto_tipo'] !== 'P') { return $this->error('El producto no existe o no lleva stock.'); }
        $origen = ['AJE' => 'E', 'AJS' => 'S', 'VEC' => 'S'];
        $o = (string)($_POST['origen'] ?? '');
        if (!isset($origen[$o])) { return $this->error('Elija si entra, sale o se da de baja por vencimiento.'); }
        $cant = self::numero($_POST['cantidad'] ?? 0);
        $motivo = self::motivo($_POST['motivo'] ?? '');
        if ($cant <= 0) { return $this->error('La cantidad debe ser mayor que cero.'); }
        if ($motivo === '') { return $this->error('Escriba el motivo del ajuste.'); }

        $con = $this->con();
        try {
            $con->beginTransaction();
            $this->moverStock($con, (int)$p['producto_id'], $origen[$o], $o, $cant, (float)$p['producto_preciocompra'], ['detalle' => $motivo]);
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible ajustar el stock.');
        }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Inventario actualizado',
                            'texto' => self::ORIGENES_KARDEX[$o] . ': ' . rtrim(rtrim(number_format($cant, 2), '0'), '.') . ' de ' . $p['producto_nombre']]);
    }

    /*======================================================================
      Ventas
      ====================================================================*/

    /** AJAX posBuscarClientes: clientes de Arena, con su saldo de monedero. */
    public function buscarClientesPos(): string
    {
        if (!puede_crear('posVenta')) { return $this->json(['clientes' => []]); }
        $q = self::texto($_POST['q'] ?? '', 60);
        if (mb_strlen($q) < 2) { return $this->json(['clientes' => []]); }
        $filas = $this->filas(
            "SELECT c.cliente_id, c.cliente_nombre, c.cliente_identificacion, c.cliente_celular,
                    COALESCE(m.monedero_saldo, 0) AS saldo
               FROM dsa_cliente c
               LEFT JOIN dsa_monedero m ON m.monedero_clienteid = c.cliente_id
              WHERE c.cliente_estado = 'A'
                AND (c.cliente_nombre LIKE :t OR c.cliente_identificacion LIKE :i OR c.cliente_celular LIKE :i2)
              ORDER BY c.cliente_identificacion = :e DESC, c.cliente_nombre LIMIT 8",
            [':t' => '%' . $q . '%', ':i' => $q . '%', ':i2' => $q . '%', ':e' => $q]);
        return $this->json(['clientes' => array_map(static fn ($c) => [
            'id' => (int)$c['cliente_id'], 'nombre' => $c['cliente_nombre'], 'ident' => $c['cliente_identificacion'],
            'celular' => (string)$c['cliente_celular'], 'saldo' => (float)$c['saldo']], $filas)]);
    }

    /**
     * AJAX posVender. De la pantalla llegan productos y cantidades, el
     * descuento pedido, el cliente (opcional) y cómo paga; todo lo demás se
     * calcula aquí.
     */
    public function vender(): string
    {
        if (!puede_crear('posVenta')) { return $this->error('Su rol no puede vender.', 'Acceso denegado'); }
        $caja = $this->cajaAbierta();
        if (!$caja) { return $this->error('Abra su caja antes de vender.', 'Caja cerrada'); }

        /* Los productos y sus cantidades (repetidos se suman). */
        $items = json_decode((string)($_POST['items'] ?? '[]'), true);
        $pedido = [];
        foreach (is_array($items) ? $items : [] as $it) {
            $pid = (int)($it['id'] ?? 0);
            $cant = round((float)($it['cantidad'] ?? 0), 2);
            if ($pid > 0 && $cant > 0 && $cant <= 10000) { $pedido[$pid] = ($pedido[$pid] ?? 0) + $cant; }
        }
        if (!$pedido || count($pedido) > 100) { return $this->error('Agregue al menos un producto.'); }

        $forma = $this->forma((int)($_POST['forma_id'] ?? 0));
        if (!$forma) { return $this->error('Elija cómo paga el cliente.'); }
        $cliente = (int)($_POST['cliente_id'] ?? 0);
        if ($cliente > 0 && !(int)$this->escalar("SELECT COUNT(*) FROM dsa_cliente WHERE cliente_id = :c AND cliente_estado = 'A'", [':c' => $cliente])) {
            $cliente = 0;
        }
        if ($forma['forma_esmonedero'] === 'S' && $cliente <= 0) {
            return $this->error('Para pagar con monedero hay que elegir al cliente.');
        }
        $referencia = self::texto($_POST['referencia'] ?? '', 60);
        if ($forma['forma_requiereref'] === 'S' && $referencia === '') {
            return $this->error('Escriba el número de comprobante de ' . mb_strtolower($forma['forma_nombre']) . '.');
        }

        $con = $this->con();
        if ($con === null) { return $this->error('Sin conexión con la base de datos.'); }
        try {
            $con->beginTransaction();

            /* Precios y costos de la base, con los productos bloqueados. */
            $marcas = implode(',', array_fill(0, count($pedido), '?'));
            $st = $con->prepare("SELECT producto_id, producto_nombre, producto_tipo, producto_precioventa, producto_preciocompra,
                                        producto_stock, producto_estado
                                   FROM dsa_producto WHERE producto_id IN ($marcas) FOR UPDATE");
            $st->execute(array_keys($pedido));
            $prods = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) { $prods[(int)$p['producto_id']] = $p; }

            $lineas = []; $subtotal = 0.0;
            foreach ($pedido as $pid => $cant) {
                $p = $prods[$pid] ?? null;
                if (!$p || $p['producto_estado'] !== 'A') { throw new \RuntimeException('Un producto del carrito ya no está a la venta. Quítelo y vuelva a intentar.'); }
                if ($p['producto_tipo'] === 'P' && $cant > (float)$p['producto_stock'] + 0.0001) {
                    throw new \RuntimeException('No hay stock suficiente de «' . $p['producto_nombre'] . '»: quedan '
                        . rtrim(rtrim(number_format((float)$p['producto_stock'], 2), '0'), '.') . '.');
                }
                $precio = (float)$p['producto_precioventa'];
                $sub = round($precio * $cant, 2);
                $subtotal += $sub;
                $lineas[] = ['id' => $pid, 'nombre' => $p['producto_nombre'], 'cant' => $cant, 'precio' => $precio,
                             'costo' => (float)$p['producto_preciocompra'], 'sub' => $sub];
            }
            $subtotal = round($subtotal, 2);

            /* El descuento: en valor o en porcentaje; con motivo y autorizado. */
            $pedidoDesc = self::numero($_POST['descuento'] ?? 0);
            $descuento = ($_POST['descuento_tipo'] ?? 'valor') === 'porcentaje'
                ? round($subtotal * min(100, max(0, $pedidoDesc)) / 100, 2)
                : min($subtotal, max(0, $pedidoDesc));
            $motivoDesc = null; $autoriza = null;
            if ($descuento > 0) {
                $motivoDesc = self::motivo($_POST['descuento_motivo'] ?? '');
                if ($motivoDesc === '') { throw new \RuntimeException('Escriba el motivo del descuento.'); }
                [$autoriza, $err] = $this->autorizador();
                if ($err !== null) { throw new \RuntimeException($err); }
            }
            $total = round($subtotal - $descuento, 2);

            /* El pago y el cambio. */
            $recibido = null; $cambio = 0.0;
            if ($forma['forma_codigo'] === 'EFE') {
                $recibido = trim((string)($_POST['recibido'] ?? '')) === '' ? $total : self::numero($_POST['recibido']);
                if ($recibido + 0.001 < $total) {
                    throw new \RuntimeException('El efectivo recibido ($' . number_format($recibido, 2) . ') no cubre el total ($' . number_format($total, 2) . ').');
                }
                $cambio = round($recibido - $total, 2);
            }

            $con->prepare(
                "INSERT INTO dsa_venta (venta_cajaid, venta_clienteid, venta_subtotal, venta_descuento, venta_total,
                                        venta_descuentomotivo, venta_descuentoautoriza, venta_usuarioid)
                 VALUES (:c, :cl, :s, :d, :t, :dm, :da, :u)"
            )->execute([':c' => $caja['caja_id'], ':cl' => $cliente ?: null, ':s' => $subtotal, ':d' => $descuento, ':t' => $total,
                        ':dm' => $motivoDesc, ':da' => $autoriza, ':u' => $this->uid()]);
            $ventaId = (int)$con->lastInsertId();
            $numero = 'NV-' . str_pad((string)$ventaId, 6, '0', STR_PAD_LEFT);
            $con->prepare("UPDATE dsa_venta SET venta_numero = :n WHERE venta_id = :v")->execute([':n' => $numero, ':v' => $ventaId]);

            $insDet = $con->prepare(
                "INSERT INTO dsa_venta_detalle (detalle_ventaid, detalle_productoid, detalle_nombre, detalle_cantidad, detalle_precio, detalle_costo, detalle_subtotal)
                 VALUES (:v, :p, :n, :c, :pr, :co, :s)");
            foreach ($lineas as $l) {
                $insDet->execute([':v' => $ventaId, ':p' => $l['id'], ':n' => $l['nombre'], ':c' => $l['cant'], ':pr' => $l['precio'],
                                  ':co' => $l['costo'], ':s' => $l['sub']]);
                $this->moverStock($con, $l['id'], 'S', 'VEN', $l['cant'], $l['costo'], ['venta' => $ventaId, 'detalle' => $numero]);
            }
            $con->prepare(
                "INSERT INTO dsa_venta_pago (pago_ventaid, pago_formaid, pago_valor, pago_recibido, pago_cambio, pago_referencia)
                 VALUES (:v, :f, :val, :r, :c, :ref)"
            )->execute([':v' => $ventaId, ':f' => $forma['forma_id'], ':val' => $total, ':r' => $recibido, ':c' => $cambio, ':ref' => $referencia ?: null]);

            if ($forma['forma_esmonedero'] === 'S' && $total > 0) {
                $this->moverMonedero($con, $this->asegurarMonedero($cliente), 'E', 'VTA', $total,
                                     ['detalle' => 'Compra ' . $numero . ' en el punto de venta']);
            }
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible registrar la venta.');
        }

        $enlace = $this->enlacePublicoVenta($ventaId);
        return $this->json(['ok' => true, 'venta' => $ventaId, 'numero' => $numero, 'subtotal' => $subtotal, 'descuento' => $descuento,
                            'total' => $total, 'recibido' => $recibido, 'cambio' => $cambio, 'forma' => $forma['forma_nombre'],
                            'ticket' => APP_URL . 'posTicket/?id=' . $ventaId, 'enlace' => $enlace['url']]);
    }

    public function venta(int $id): ?array
    {
        $v = $this->filas(
            "SELECT v.*, c.caja_estado, " . ($this->ventasConRegistro() ? "COALESCE(c.caja_sedeid, v.venta_sedeid)" : "c.caja_sedeid") . " AS caja_sedeid,
                    c.caja_usuarioid, u.usuario_usuario AS vendedor,
                    ua.usuario_usuario AS autoriza_descuento, ux.usuario_usuario AS anulo, uxa.usuario_usuario AS autoriza_anulacion,
                    cl.cliente_nombre, cl.cliente_identificacion, cl.cliente_celular,
                    s.sede_nombre, s.sede_direccion, s.sede_telefono,
                    (SELECT GROUP_CONCAT(i.instalacion_nombre ORDER BY i.instalacion_id SEPARATOR ' · ')
                       FROM dsa_instalacion i
                      WHERE i.instalacion_sedeid = " . ($this->ventasConRegistro() ? "COALESCE(c.caja_sedeid, v.venta_sedeid)" : "c.caja_sedeid") . " AND i.instalacion_estado = 'A') AS instalacion_nombre
               FROM dsa_venta v
               LEFT JOIN dsa_caja c ON c.caja_id = v.venta_cajaid   /* sin caja: registrada después (099) */
               LEFT JOIN seguridad_usuario u   ON u.usuario_id   = v.venta_usuarioid
               LEFT JOIN seguridad_usuario ua  ON ua.usuario_id  = v.venta_descuentoautoriza
               LEFT JOIN seguridad_usuario ux  ON ux.usuario_id  = v.venta_anulausuario
               LEFT JOIN seguridad_usuario uxa ON uxa.usuario_id = v.venta_anulaautoriza
               LEFT JOIN dsa_cliente cl ON cl.cliente_id = v.venta_clienteid
               LEFT JOIN general_sede s ON s.sede_id = " . ($this->ventasConRegistro() ? "COALESCE(c.caja_sedeid, v.venta_sedeid)" : "c.caja_sedeid") . "
              WHERE v.venta_id = :v", [':v' => $id])[0] ?? null;
        if (!$v) { return null; }
        $v['lineas'] = $this->filas("SELECT d.*, p.producto_codigo FROM dsa_venta_detalle d LEFT JOIN dsa_producto p ON p.producto_id = d.detalle_productoid
                                      WHERE d.detalle_ventaid = :v ORDER BY d.detalle_id", [':v' => $id]);
        $v['pagos'] = $this->filas("SELECT vp.*, f.forma_nombre, f.forma_codigo, f.forma_esmonedero FROM dsa_venta_pago vp
                                      JOIN dsa_forma_ingreso f ON f.forma_id = vp.pago_formaid WHERE vp.pago_ventaid = :v", [':v' => $id]);
        return $v;
    }

    /** Ventas de un día (o una búsqueda por número), con su forma de pago. */
    public function ventas(string $dia, string $buscar = '', int $usuario = 0): array
    {
        $sql = "SELECT v.venta_id, v.venta_numero, v.venta_total, v.venta_descuento, v.venta_estado, v.venta_fecharegistro,
                       " . ($this->ventasConRegistro() ? "v.venta_origen," : "'P' AS venta_origen,") . "
                       u.usuario_usuario AS vendedor, cl.cliente_nombre,
                       (SELECT GROUP_CONCAT(f.forma_nombre SEPARATOR ', ') FROM dsa_venta_pago vp JOIN dsa_forma_ingreso f ON f.forma_id = vp.pago_formaid
                         WHERE vp.pago_ventaid = v.venta_id) AS formas,
                       (SELECT COUNT(*) FROM dsa_venta_detalle d WHERE d.detalle_ventaid = v.venta_id) AS lineas,
                       (SELECT GROUP_CONCAT(CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM d.detalle_cantidad)), ' ', d.detalle_nombre) SEPARATOR ', ')
                          FROM dsa_venta_detalle d WHERE d.detalle_ventaid = v.venta_id) AS resumen
                  FROM dsa_venta v
                  LEFT JOIN seguridad_usuario u ON u.usuario_id = v.venta_usuarioid
                  LEFT JOIN dsa_cliente cl ON cl.cliente_id = v.venta_clienteid
                 WHERE 1 = 1";
        $p = [];
        if ($buscar !== '') {
            $sql .= " AND (v.venta_numero LIKE :b OR cl.cliente_nombre LIKE :b2)";
            $p[':b'] = '%' . $buscar . '%'; $p[':b2'] = '%' . $buscar . '%';
        } else {
            $sql .= " AND v.venta_fecharegistro >= :d AND v.venta_fecharegistro < :d2";
            $p[':d'] = $dia . ' 00:00:00'; $p[':d2'] = date('Y-m-d', strtotime($dia . ' +1 day')) . ' 00:00:00';
        }
        if ($usuario > 0) { $sql .= " AND v.venta_usuarioid = :u"; $p[':u'] = $usuario; }
        return $this->filas($sql . " ORDER BY v.venta_id DESC LIMIT 300", $p);
    }

    /** Las cifras de las ventas de un día: vigentes, anuladas, por forma, margen y lo más vendido. */
    public function resumenVentasDia(string $dia, int $usuario = 0): array
    {
        $p = [':d' => $dia . ' 00:00:00', ':d2' => date('Y-m-d', strtotime($dia . ' +1 day')) . ' 00:00:00'];
        $filtroU = '';
        if ($usuario > 0) { $filtroU = ' AND v.venta_usuarioid = :u'; $p[':u'] = $usuario; }
        $base = "FROM dsa_venta v WHERE v.venta_fecharegistro >= :d AND v.venta_fecharegistro < :d2" . $filtroU;
        $t = $this->filas("SELECT SUM(v.venta_estado = 'A') n, SUM(v.venta_estado = 'X') nx,
                                  COALESCE(SUM(CASE WHEN v.venta_estado = 'A' THEN v.venta_total END), 0) total,
                                  COALESCE(SUM(CASE WHEN v.venta_estado = 'A' THEN v.venta_descuento END), 0) descuentos,
                                  COALESCE(SUM(CASE WHEN v.venta_estado = 'X' THEN v.venta_total END), 0) anulado " . $base, $p)[0] ?? [];
        $costo = (float)$this->escalar("SELECT COALESCE(SUM(d.detalle_costo * d.detalle_cantidad), 0)
                                          FROM dsa_venta_detalle d JOIN dsa_venta v ON v.venta_id = d.detalle_ventaid
                                         WHERE v.venta_estado = 'A' AND v.venta_fecharegistro >= :d AND v.venta_fecharegistro < :d2" . $filtroU, $p);
        $formas = $this->filas("SELECT f.forma_nombre, SUM(vp.pago_valor) total, COUNT(*) n
                                  FROM dsa_venta v JOIN dsa_venta_pago vp ON vp.pago_ventaid = v.venta_id
                                  JOIN dsa_forma_ingreso f ON f.forma_id = vp.pago_formaid
                                 WHERE v.venta_estado = 'A' AND v.venta_fecharegistro >= :d AND v.venta_fecharegistro < :d2" . $filtroU . "
                                 GROUP BY f.forma_id ORDER BY total DESC", $p);
        $top = $this->filas("SELECT d.detalle_nombre nombre, SUM(d.detalle_cantidad) cantidad, SUM(d.detalle_subtotal) total
                               FROM dsa_venta_detalle d JOIN dsa_venta v ON v.venta_id = d.detalle_ventaid
                              WHERE v.venta_estado = 'A' AND v.venta_fecharegistro >= :d AND v.venta_fecharegistro < :d2" . $filtroU . "
                              GROUP BY d.detalle_productoid, d.detalle_nombre ORDER BY cantidad DESC LIMIT 5", $p);
        $total = (float)($t['total'] ?? 0);
        return ['ventas' => (int)($t['n'] ?? 0), 'anuladas' => (int)($t['nx'] ?? 0), 'total' => $total,
                'descuentos' => (float)($t['descuentos'] ?? 0), 'anulado' => (float)($t['anulado'] ?? 0),
                'costo' => round($costo, 2), 'margen' => round($total - $costo, 2),
                'ticket' => (int)($t['n'] ?? 0) > 0 ? round($total / (int)$t['n'], 2) : 0.0,
                'formas' => $formas, 'top' => $top];
    }

    /**
     * AJAX posAnularVenta. Devuelve el stock, el monedero si se pagó con él
     * y, si la venta es de una caja ya cerrada, registra la devolución del
     * dinero como egreso en la caja abierta de quien anula.
     */
    public function anularVenta(): string
    {
        if (!puede_crear('posVenta') && !$this->puedeAutorizar()) { return $this->error('Su rol no puede anular ventas.', 'Acceso denegado'); }
        $v = $this->venta((int)($_POST['venta_id'] ?? 0));
        if (!$v) { return $this->error('La venta no existe.'); }
        if ($v['venta_estado'] !== 'A') { return $this->error('La venta ya estaba anulada.'); }
        $motivo = self::motivo($_POST['motivo'] ?? '');
        if ($motivo === '') { return $this->error('Escriba por qué se anula la venta.'); }
        [$autoriza, $err] = $this->autorizador();
        if ($err !== null) { return $this->error($err, 'Falta la autorización'); }

        /* Una venta registrada después (099) no tiene caja: no se devuelve dinero de ninguna. */
        $cajaCerrada = !empty($v['venta_cajaid']) && $v['caja_estado'] !== 'A';
        $miCaja = $cajaCerrada ? $this->cajaAbierta() : null;
        $devolver = array_filter($v['pagos'], static fn ($p) => $p['forma_esmonedero'] !== 'S' && (float)$p['pago_valor'] > 0);
        if ($cajaCerrada && $devolver && !$miCaja) {
            return $this->error('La venta es de una caja ya cerrada: abra su caja para registrar la devolución del dinero.');
        }

        $con = $this->con();
        $volvio = 0;
        try {
            $con->beginTransaction();
            $st = $con->prepare("SELECT venta_estado FROM dsa_venta WHERE venta_id = :v FOR UPDATE");
            $st->execute([':v' => $v['venta_id']]);
            if ($st->fetchColumn() !== 'A') { throw new \RuntimeException('La venta ya estaba anulada.'); }

            foreach ($v['lineas'] as $l) {
                /* El stock vuelve sólo si salió con esta venta (una registrada
                   después pudo hacerse sin descontar del inventario, 099). */
                $salio = (int)$this->escalar("SELECT COUNT(*) FROM dsa_kardex WHERE kardex_ventaid = :v AND kardex_productoid = :p AND kardex_origen = 'VEN'",
                                              [':v' => $v['venta_id'], ':p' => $l['detalle_productoid']]);
                if (!$salio) { continue; }
                $volvio++;
                $this->moverStock($con, (int)$l['detalle_productoid'], 'E', 'ANV', (float)$l['detalle_cantidad'], (float)$l['detalle_costo'],
                                  ['venta' => (int)$v['venta_id'], 'detalle' => 'Anulación ' . $v['venta_numero']]);
            }
            foreach ($v['pagos'] as $p) {
                if ($p['forma_esmonedero'] === 'S' && (float)$p['pago_valor'] > 0) {
                    $this->moverMonedero($con, $this->asegurarMonedero((int)$v['venta_clienteid']), 'I', 'AVT', (float)$p['pago_valor'],
                                         ['detalle' => 'Anulación de la venta ' . $v['venta_numero']]);
                } elseif ($cajaCerrada && (float)$p['pago_valor'] > 0) {
                    $con->prepare(
                        "INSERT INTO dsa_caja_movimiento (movimiento_cajaid, movimiento_tipo, movimiento_origen, movimiento_concepto,
                                                          movimiento_forma, movimiento_valor, movimiento_ventaid, movimiento_detalle, movimiento_usuarioid)
                         VALUES (:c, 'E', 'ANV', :co, :f, :v, :vid, :d, :u)"
                    )->execute([':c' => $miCaja['caja_id'], ':co' => 'Devolución por anulación ' . $v['venta_numero'],
                                ':f' => $p['forma_codigo'], ':v' => (float)$p['pago_valor'], ':vid' => $v['venta_id'],
                                ':d' => $motivo, ':u' => $this->uid()]);
                }
            }
            $con->prepare(
                "UPDATE dsa_venta SET venta_estado = 'X', venta_anulamotivo = :m, venta_anulaautoriza = :a,
                        venta_anulausuario = :u, venta_anulafecha = NOW() WHERE venta_id = :v"
            )->execute([':m' => $motivo, ':a' => $autoriza, ':u' => $this->uid(), ':v' => $v['venta_id']]);
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible anular la venta.');
        }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Venta anulada',
                            'texto' => $v['venta_numero'] . ($volvio ? ': el stock volvió al inventario' : ': quedó anulada')
                                     . ($cajaCerrada && $devolver ? ' y la devolución quedó como egreso de su caja.' : '.')]);
    }

    /*======================================================================
      Registrar ventas ya hechas (migración 099, 2026-11-25)

      Lo vendido antes de usar el punto de venta, o un día en que no se usó.
      Diferencias con «Vender»:
        · la fecha la elige quien registra (hoy o hasta un año atrás);
        · la de HOY entra en la caja abierta de quien registra (2026-11-26):
          su efectivo está en el cajón y debe cuadrar en el arqueo; sin caja
          abierta no se registra. La de OTRO DÍA no entra en ninguna caja
          (venta_cajaid NULL): queda como constancia de lo vendido, porque
          el dinero de ese día ya se contó y no debe descuadrar el de hoy;
        · el precio de cada línea es el que se escribe (el de ese día), no el
          del catálogo;
        · un producto que no existe se escribe por su nombre y se crea en
          Productos como servicio (sin stock), para no inventar existencias;
        · descontar del inventario es opcional: las ventas anteriores al
          conteo del stock no deben volver a descontarse.
      Aparece en Reportes › Ventas del día elegido, con su comprobante, y se
      anula como cualquier venta.
      ====================================================================*/

    /** ¿Está aplicada la 099 (columna venta_origen)? Una vez por petición. */
    public function ventasConRegistro(): bool
    {
        static $hay = null;
        if ($hay === null) {
            try {
                $hay = (int)$this->escalar("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_venta' AND COLUMN_NAME = 'venta_origen'") === 1;
            } catch (\Throwable $e) { $hay = false; }
        }
        return $hay;
    }

    /** Días hacia atrás que admite el registro de ventas. */
    public const REGISTRO_DIAS_ATRAS = 366;

    /** Las ventas registradas después, de la más reciente a la más antigua. */
    public function ventasRegistradas(string $desde, string $hasta, int $limite = 300): array
    {
        if (!$this->ventasConRegistro()) { return []; }
        return $this->filas(
            "SELECT v.venta_id, v.venta_numero, v.venta_total, v.venta_estado, v.venta_fecharegistro, v.venta_registro,
                    u.usuario_usuario AS vendedor,
                    (SELECT GROUP_CONCAT(f.forma_nombre SEPARATOR ', ') FROM dsa_venta_pago vp JOIN dsa_forma_ingreso f ON f.forma_id = vp.pago_formaid
                      WHERE vp.pago_ventaid = v.venta_id) AS formas,
                    (SELECT GROUP_CONCAT(CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM d.detalle_cantidad)), ' ', d.detalle_nombre) SEPARATOR ', ')
                       FROM dsa_venta_detalle d WHERE d.detalle_ventaid = v.venta_id) AS resumen
               FROM dsa_venta v
               LEFT JOIN seguridad_usuario u ON u.usuario_id = v.venta_usuarioid
              WHERE v.venta_origen = 'R' AND v.venta_fecharegistro >= :d AND v.venta_fecharegistro < :h
              ORDER BY v.venta_fecharegistro DESC, v.venta_id DESC LIMIT " . max(1, min(1000, $limite)),
            [':d' => $desde . ' 00:00:00', ':h' => date('Y-m-d', strtotime($hasta . ' +1 day')) . ' 00:00:00']);
    }

    /**
     * AJAX posRegistrarVentas. Llegan la fecha, la sede, la forma de pago y
     * las líneas: {producto_id (0 = nuevo), nombre, cantidad, precio}.
     */
    public function registrarVentas(): string
    {
        if (!puede_crear('posRegistro')) { return $this->error('Su rol no puede registrar ventas.', 'Acceso denegado'); }
        if (!$this->ventasConRegistro()) { return $this->error('Aplique la migración 099 para poder registrar ventas.', 'Falta una actualización'); }

        $hoy   = date('Y-m-d');
        $fecha = (string)($_POST['fecha'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !checkdate((int)substr($fecha, 5, 2), (int)substr($fecha, 8, 2), (int)substr($fecha, 0, 4))
            || $fecha > $hoy || $fecha < date('Y-m-d', strtotime($hoy . ' -' . self::REGISTRO_DIAS_ATRAS . ' days'))) {
            return $this->error('Elija la fecha de la venta: hoy o un día anterior (hasta un año atrás).', 'Fecha no válida');
        }
        /* Las de hoy entran en la caja abierta de quien registra (2026-11-26):
           su efectivo es parte del arqueo. Las de días anteriores, en ninguna. */
        $caja = null;
        if ($fecha === $hoy) {
            $caja = $this->cajaAbierta();
            if (!$caja) {
                return $this->error('Las ventas de hoy entran en su caja: ábrala en Punto de Venta y Caja › Caja y vuelva a registrarlas.'
                                  . ' Si son de otro día, cambie la fecha.', 'Abra su caja');
            }
        }
        $sedes = $this->sedesCaja();
        $sede  = (int)($_POST['sede'] ?? 0);
        if (!in_array($sede, array_map('intval', array_column($sedes, 'sede_id')), true)) { $sede = (int)($sedes[0]['sede_id'] ?? 0); }
        if ($caja && (int)$caja['caja_sedeid'] > 0) { $sede = (int)$caja['caja_sedeid']; }

        $forma = $this->forma((int)($_POST['forma_id'] ?? 0));
        if (!$forma || $forma['forma_esmonedero'] === 'S') { return $this->error('Elija la forma de pago.'); }
        $referencia = self::texto($_POST['referencia'] ?? '', 60);
        $descontar  = ($_POST['descontar_stock'] ?? 'S') === 'S';

        /* Las líneas, validadas. Un producto nuevo que ya existe con ese nombre se reutiliza. */
        $entrada = json_decode((string)($_POST['lineas'] ?? '[]'), true);
        $lineas = [];
        foreach (is_array($entrada) ? $entrada : [] as $l) {
            $pid    = (int)($l['producto_id'] ?? 0);
            $nombre = self::texto($l['nombre'] ?? '', 100);
            $cant   = round((float)str_replace(',', '.', (string)($l['cantidad'] ?? 0)), 2);
            $precio = self::numero($l['precio'] ?? 0);
            if ($pid > 0) {
                $p = $this->producto($pid);
                if (!$p || $p['producto_estado'] === 'E') { return $this->error('Un producto de la lista ya no existe. Quítelo y vuelva a agregarlo.'); }
            } else {
                if (mb_strlen($nombre) < 2) { return $this->error('Escriba el nombre del producto nuevo.'); }
                $p = $this->filas("SELECT * FROM dsa_producto WHERE producto_nombre = :n AND producto_estado <> 'E' ORDER BY producto_estado, producto_id LIMIT 1",
                                  [':n' => $nombre])[0] ?? null;
            }
            $etq = $p['producto_nombre'] ?? $nombre;
            if ($cant <= 0 || $cant > 10000) { return $this->error('La cantidad de «' . $etq . '» debe ser mayor que cero.'); }
            if ($precio <= 0 || $precio > 100000) { return $this->error('Escriba el precio de «' . $etq . '».'); }
            $lineas[] = ['p' => $p, 'nombre' => $etq, 'cant' => $cant, 'precio' => $precio, 'sub' => round($cant * $precio, 2)];
        }
        if (!$lineas) { return $this->error('Agregue al menos un producto vendido.'); }
        if (count($lineas) > 100) { return $this->error('Registre como máximo 100 productos a la vez.'); }

        /* El stock, antes de escribir nada (moverStock lo vuelve a comprobar dentro de la transacción). */
        if ($descontar) {
            $pedido = [];
            foreach ($lineas as $l) {
                if ($l['p'] !== null && $l['p']['producto_tipo'] === 'P') {
                    $pid = (int)$l['p']['producto_id'];
                    $pedido[$pid] = ['p' => $l['p'], 'cant' => ($pedido[$pid]['cant'] ?? 0) + $l['cant']];
                }
            }
            foreach ($pedido as $x) {
                if ($x['cant'] > (float)$x['p']['producto_stock'] + 0.0001) {
                    return $this->error('No hay stock suficiente de «' . $x['p']['producto_nombre'] . '»: quedan '
                        . rtrim(rtrim(number_format((float)$x['p']['producto_stock'], 2), '0'), '.') . '.'
                        . ' Si estas ventas son anteriores al conteo del stock, desmarque «Descontar del inventario».');
                }
            }
        }
        $total = round(array_sum(array_column($lineas, 'sub')), 2);

        $con = $this->con();
        if ($con === null) { return $this->error('Sin conexión con la base de datos.'); }
        $nuevos = [];
        try {
            $con->beginTransaction();

            /* 1. Los productos que no existen: como servicio (sin stock), con el precio escrito. */
            $creados = [];
            foreach ($lineas as $i => $l) {
                if ($l['p'] !== null) { continue; }
                $clave = mb_strtolower($l['nombre']);
                if (!isset($creados[$clave])) {
                    $codigo = $this->codigoProductoSugerido();
                    $con->prepare(
                        "INSERT INTO dsa_producto (producto_codigo, producto_nombre, producto_categoriaid, producto_tipo, producto_preciocompra,
                                                   producto_precioventa, producto_stock, producto_stockminimo, producto_vence, producto_estado, producto_usuarioid)
                         VALUES (:co, :n, NULL, 'S', 0, :pv, 0, 0, 'N', 'A', :u)"
                    )->execute([':co' => $codigo, ':n' => $l['nombre'], ':pv' => $l['precio'], ':u' => $this->uid() ?: null]);
                    $creados[$clave] = ['producto_id' => (int)$con->lastInsertId(), 'producto_nombre' => $l['nombre'],
                                        'producto_tipo' => 'S', 'producto_preciocompra' => 0];
                    $nuevos[] = $l['nombre'];
                }
                $lineas[$i]['p'] = $creados[$clave];
            }

            /* 2. La venta: con la fecha elegida y la hora del registro; en la
                  caja abierta si es de hoy, sin caja si es de otro día. */
            if ($caja) {
                $st = $con->prepare("SELECT caja_estado FROM dsa_caja WHERE caja_id = :c FOR UPDATE");
                $st->execute([':c' => $caja['caja_id']]);
                if ($st->fetchColumn() !== 'A') { throw new \RuntimeException('Su caja se cerró mientras registraba. Ábrala de nuevo y vuelva a registrar.'); }
            }
            $con->prepare(
                "INSERT INTO dsa_venta (venta_cajaid, venta_sedeid, venta_subtotal, venta_descuento, venta_total, venta_usuarioid,
                                        venta_origen, venta_registro, venta_fecharegistro)
                 VALUES (:c, :s, :t, 0, :t2, :u, 'R', NOW(), :f)"
            )->execute([':c' => $caja['caja_id'] ?? null, ':s' => $sede ?: null, ':t' => $total, ':t2' => $total, ':u' => $this->uid(),
                        ':f' => $fecha . ' ' . date('H:i:s')]);
            $ventaId = (int)$con->lastInsertId();
            $numero = 'NV-' . str_pad((string)$ventaId, 6, '0', STR_PAD_LEFT);
            $con->prepare("UPDATE dsa_venta SET venta_numero = :n WHERE venta_id = :v")->execute([':n' => $numero, ':v' => $ventaId]);

            /* 3. Las líneas y, si se pidió, la salida del inventario. */
            $insDet = $con->prepare(
                "INSERT INTO dsa_venta_detalle (detalle_ventaid, detalle_productoid, detalle_nombre, detalle_cantidad, detalle_precio, detalle_costo, detalle_subtotal)
                 VALUES (:v, :p, :n, :c, :pr, :co, :s)");
            foreach ($lineas as $l) {
                $costo = (float)$l['p']['producto_preciocompra'];
                $insDet->execute([':v' => $ventaId, ':p' => $l['p']['producto_id'], ':n' => $l['p']['producto_nombre'], ':c' => $l['cant'],
                                  ':pr' => $l['precio'], ':co' => $costo, ':s' => $l['sub']]);
                if ($descontar && $l['p']['producto_tipo'] === 'P') {
                    try {
                        $this->moverStock($con, (int)$l['p']['producto_id'], 'S', 'VEN', $l['cant'], $costo,
                                          ['venta' => $ventaId, 'detalle' => 'Registro ' . $numero . ' del ' . date('d/m/Y', strtotime($fecha))]);
                    } catch (\RuntimeException $e) {
                        throw new \RuntimeException($e->getMessage() . ' Si estas ventas son anteriores al conteo del stock, desmarque «Descontar del inventario».');
                    }
                }
            }

            /* 4. El pago. */
            $con->prepare(
                "INSERT INTO dsa_venta_pago (pago_ventaid, pago_formaid, pago_valor, pago_recibido, pago_cambio, pago_referencia)
                 VALUES (:v, :f, :val, NULL, 0, :ref)"
            )->execute([':v' => $ventaId, ':f' => $forma['forma_id'], ':val' => $total, ':ref' => $referencia ?: null]);
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible registrar las ventas.');
        }

        $texto = $numero . ': ' . count($lineas) . ' producto(s) por $' . number_format($total, 2) . ' en ' . mb_strtolower($forma['forma_nombre'])
               . ', con fecha ' . date('d/m/Y', strtotime($fecha)) . '.'
               . ($caja ? ' Entró en su caja.' : ' No entra en ninguna caja: queda como constancia.');
        if ($nuevos) { $texto .= ' Se crearon en Productos: ' . implode(', ', $nuevos) . '.'; }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Ventas registradas', 'texto' => $texto,
                            'venta' => $ventaId, 'numero' => $numero]);
    }

    /*======================================================================
      Compras
      ====================================================================*/

    public function compras(string $estadoPago = '', int $limite = 100): array
    {
        $sql = "SELECT c.*, u.usuario_usuario,
                       (SELECT COUNT(*) FROM dsa_compra_detalle d WHERE d.detalle_compraid = c.compra_id) AS lineas
                  FROM dsa_compra c LEFT JOIN seguridad_usuario u ON u.usuario_id = c.compra_usuarioid WHERE 1 = 1";
        if ($estadoPago === 'pendiente') { $sql .= " AND c.compra_estado = 'A' AND c.compra_saldo > 0"; }
        return $this->filas($sql . " ORDER BY c.compra_fecha DESC, c.compra_id DESC LIMIT " . max(1, min(500, $limite)));
    }

    public function compra(int $id): ?array
    {
        $c = $this->filas("SELECT c.*, u.usuario_usuario FROM dsa_compra c LEFT JOIN seguridad_usuario u ON u.usuario_id = c.compra_usuarioid
                            WHERE c.compra_id = :c", [':c' => $id])[0] ?? null;
        if (!$c) { return null; }
        $c['lineas'] = $this->filas("SELECT d.*, p.producto_nombre, p.producto_codigo FROM dsa_compra_detalle d
                                       JOIN dsa_producto p ON p.producto_id = d.detalle_productoid WHERE d.detalle_compraid = :c", [':c' => $id]);
        $c['pagos'] = $this->filas("SELECT cp.*, f.forma_nombre, u.usuario_usuario FROM dsa_compra_pago cp
                                      JOIN dsa_forma_ingreso f ON f.forma_id = cp.pago_formaid
                                      LEFT JOIN seguridad_usuario u ON u.usuario_id = cp.pago_usuarioid
                                     WHERE cp.pago_compraid = :c AND cp.pago_estado = 'A' ORDER BY cp.pago_id", [':c' => $id]);
        return $c;
    }

    public function totalPorPagar(): float
    {
        return (float)$this->escalar("SELECT COALESCE(SUM(compra_saldo), 0) FROM dsa_compra WHERE compra_estado = 'A' AND compra_saldo > 0");
    }

    /**
     * Registra un pago de compra dentro de la transacción: de la caja (sale
     * del efectivo del cajón) o por otro medio (no toca la caja).
     */
    private function pagarCompraEn(PDO $con, int $compraId, float $valor, string $modo, ?array $forma, string $ref, ?array $caja): void
    {
        if ($modo === 'caja') {
            $efe = array_values(array_filter($this->formasIngreso(), static fn ($f) => $f['forma_codigo'] === 'EFE'))[0] ?? null;
            if (!$efe) { throw new \RuntimeException('No hay forma de pago «Efectivo».'); }
            $con->prepare("INSERT INTO dsa_compra_pago (pago_compraid, pago_formaid, pago_valor, pago_referencia, pago_fecha, pago_cajaid, pago_usuarioid)
                           VALUES (:c, :f, :v, :r, CURDATE(), :caja, :u)")
                ->execute([':c' => $compraId, ':f' => $efe['forma_id'], ':v' => $valor, ':r' => $ref ?: null, ':caja' => $caja['caja_id'], ':u' => $this->uid()]);
            $con->prepare("INSERT INTO dsa_caja_movimiento (movimiento_cajaid, movimiento_tipo, movimiento_origen, movimiento_concepto,
                                                            movimiento_forma, movimiento_valor, movimiento_compraid, movimiento_usuarioid)
                           VALUES (:caja, 'E', 'COM', :co, 'EFE', :v, :c, :u)")
                ->execute([':caja' => $caja['caja_id'], ':co' => 'Pago de compra C-' . str_pad((string)$compraId, 5, '0', STR_PAD_LEFT),
                           ':v' => $valor, ':c' => $compraId, ':u' => $this->uid()]);
        } else {
            $con->prepare("INSERT INTO dsa_compra_pago (pago_compraid, pago_formaid, pago_valor, pago_referencia, pago_fecha, pago_usuarioid)
                           VALUES (:c, :f, :v, :r, CURDATE(), :u)")
                ->execute([':c' => $compraId, ':f' => $forma['forma_id'], ':v' => $valor, ':r' => $ref ?: null, ':u' => $this->uid()]);
        }
        $con->prepare("UPDATE dsa_compra SET compra_pagado = compra_pagado + :v WHERE compra_id = :c")->execute([':v' => $valor, ':c' => $compraId]);
    }

    /** Valida «cómo se paga»: [modo, forma, caja, error]. */
    private function modoPago(): array
    {
        $modo = (string)($_POST['pago_modo'] ?? 'pendiente');
        if (!in_array($modo, ['pendiente', 'caja', 'otro'], true)) { $modo = 'pendiente'; }
        if ($modo === 'caja') {
            $caja = $this->cajaAbierta();
            return $caja ? ['caja', null, $caja, null] : ['caja', null, null, 'Para pagar con el efectivo de la caja, primero abra su caja.'];
        }
        if ($modo === 'otro') {
            $f = $this->forma((int)($_POST['pago_forma'] ?? 0));
            if (!$f || $f['forma_esmonedero'] === 'S') { return ['otro', null, null, 'Elija cómo se pagó.']; }
            return ['otro', $f, null, null];
        }
        return ['pendiente', null, null, null];
    }

    /** AJAX posGuardarCompra: entra el stock y, si se pagó, su pago. */
    public function guardarCompra(): string
    {
        if (!puede_crear('posCompras')) { return $this->error('Su rol no puede registrar compras.', 'Acceso denegado'); }
        $proveedor = self::texto($_POST['proveedor'] ?? '', 120);
        $documento = self::texto($_POST['documento'] ?? '', 40);
        $fecha = (string)($_POST['fecha'] ?? date('Y-m-d'));
        if (mb_strlen($proveedor) < 2) { return $this->error('Escriba el proveedor.'); }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > date('Y-m-d') || $fecha < date('Y-m-d', strtotime('-1 year'))) {
            return $this->error('La fecha de la compra no es válida.');
        }
        $lineas = json_decode((string)($_POST['lineas'] ?? '[]'), true);
        $ok = [];
        foreach (is_array($lineas) ? $lineas : [] as $l) {
            $p = $this->producto((int)($l['producto_id'] ?? 0));
            $cant = round((float)($l['cantidad'] ?? 0), 2);
            $costo = self::numero($l['costo'] ?? 0);
            $vence = (string)($l['vence'] ?? '');
            if (!$p || $p['producto_tipo'] !== 'P' || $p['producto_estado'] === 'E') { return $this->error('Hay una línea con un producto que no lleva stock.'); }
            if ($cant <= 0) { return $this->error('La cantidad de «' . $p['producto_nombre'] . '» debe ser mayor que cero.'); }
            if ($costo < 0) { return $this->error('El costo de «' . $p['producto_nombre'] . '» no es válido.'); }
            if ($vence !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vence)) { $vence = ''; }
            if ($p['producto_vence'] === 'S' && $vence === '') { return $this->error('«' . $p['producto_nombre'] . '» controla vencimiento: escriba su fecha de vencimiento.'); }
            $ok[] = ['p' => $p, 'cant' => $cant, 'costo' => $costo, 'vence' => $vence ?: null, 'sub' => round($cant * $costo, 2)];
        }
        if (!$ok) { return $this->error('Agregue al menos un producto a la compra.'); }
        $total = round(array_sum(array_column($ok, 'sub')), 2);

        [$modo, $forma, $caja, $err] = $this->modoPago();
        if ($err) { return $this->error($err); }
        if ($modo === 'caja' && $total > 0) {
            $r = $this->resumenCaja($caja);
            if ($total > $r['esperado'] + 0.001) { return $this->error('En su caja debería haber $' . number_format($r['esperado'], 2) . ' en efectivo: no alcanza para pagar $' . number_format($total, 2) . '.'); }
        }

        $con = $this->con();
        try {
            $con->beginTransaction();
            $con->prepare("INSERT INTO dsa_compra (compra_proveedor, compra_documento, compra_fecha, compra_total, compra_observacion, compra_usuarioid)
                           VALUES (:p, :d, :f, :t, :o, :u)")
                ->execute([':p' => $proveedor, ':d' => $documento ?: null, ':f' => $fecha, ':t' => $total,
                           ':o' => self::texto($_POST['observacion'] ?? '', 250) ?: null, ':u' => $this->uid() ?: null]);
            $id = (int)$con->lastInsertId();
            $ins = $con->prepare("INSERT INTO dsa_compra_detalle (detalle_compraid, detalle_productoid, detalle_cantidad, detalle_costo, detalle_subtotal, detalle_vence)
                                  VALUES (:c, :p, :q, :co, :s, :v)");
            foreach ($ok as $l) {
                $ins->execute([':c' => $id, ':p' => $l['p']['producto_id'], ':q' => $l['cant'], ':co' => $l['costo'], ':s' => $l['sub'], ':v' => $l['vence']]);
                $this->moverStock($con, (int)$l['p']['producto_id'], 'E', 'COM', $l['cant'], $l['costo'],
                                  ['compra' => $id, 'detalle' => $proveedor . ($documento ? ' · ' . $documento : '')]);
                /* El costo del producto pasa a ser el de la última compra. */
                $con->prepare("UPDATE dsa_producto SET producto_preciocompra = :c WHERE producto_id = :p")->execute([':c' => $l['costo'], ':p' => $l['p']['producto_id']]);
            }
            if ($modo !== 'pendiente' && $total > 0) {
                $this->pagarCompraEn($con, $id, $total, $modo, $forma, self::texto($_POST['pago_referencia'] ?? '', 60), $caja);
            }
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible registrar la compra.');
        }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Compra registrada',
                            'texto' => 'Entraron ' . count($ok) . ' producto(s) por $' . number_format($total, 2)
                                     . ($modo === 'pendiente' ? '. Queda por pagar.' : ($modo === 'caja' ? '. Se pagó con el efectivo de su caja.' : '. Pagada.'))]);
    }

    /** AJAX posPagarCompra: un pago (total o parcial) de una compra. */
    public function pagarCompra(): string
    {
        if (!puede_editar('posCompras') && !puede_crear('posCompras')) { return $this->error('Su rol no puede pagar compras.', 'Acceso denegado'); }
        $c = $this->compra((int)($_POST['compra_id'] ?? 0));
        if (!$c || $c['compra_estado'] !== 'A') { return $this->error('La compra no existe o está anulada.'); }
        $valor = self::numero($_POST['valor'] ?? 0);
        if ($valor <= 0 || $valor > (float)$c['compra_saldo'] + 0.001) { return $this->error('El valor debe ser mayor que cero y no pasar del saldo ($' . number_format((float)$c['compra_saldo'], 2) . ').'); }
        [$modo, $forma, $caja, $err] = $this->modoPago();
        if ($err || $modo === 'pendiente') { return $this->error($err ?: 'Elija cómo se paga.'); }
        if ($modo === 'caja') {
            $r = $this->resumenCaja($caja);
            if ($valor > $r['esperado'] + 0.001) { return $this->error('En su caja debería haber $' . number_format($r['esperado'], 2) . ' en efectivo.'); }
        }
        $con = $this->con();
        try {
            $con->beginTransaction();
            $this->pagarCompraEn($con, (int)$c['compra_id'], $valor, $modo, $forma, self::texto($_POST['pago_referencia'] ?? '', 60), $caja);
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error('No fue posible registrar el pago.');
        }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Pago registrado',
                            'texto' => '$' . number_format($valor, 2) . ' a ' . $c['compra_proveedor']]);
    }

    /** AJAX posAnularCompra: sólo sin pagos; el stock que entró vuelve a salir. */
    public function anularCompra(): string
    {
        if (!puede_eliminar('posCompras')) { return $this->error('Su rol no puede anular compras.', 'Acceso denegado'); }
        $c = $this->compra((int)($_POST['compra_id'] ?? 0));
        if (!$c || $c['compra_estado'] !== 'A') { return $this->error('La compra no existe o ya está anulada.'); }
        if ((float)$c['compra_pagado'] > 0) { return $this->error('La compra tiene pagos registrados: no se puede anular.'); }
        $motivo = self::motivo($_POST['motivo'] ?? '');
        if ($motivo === '') { return $this->error('Escriba por qué se anula la compra.'); }
        $con = $this->con();
        try {
            $con->beginTransaction();
            foreach ($c['lineas'] as $l) {
                $this->moverStock($con, (int)$l['detalle_productoid'], 'S', 'ANC', (float)$l['detalle_cantidad'], (float)$l['detalle_costo'],
                                  ['compra' => (int)$c['compra_id'], 'detalle' => 'Anulación: ' . $motivo]);
            }
            $con->prepare("UPDATE dsa_compra SET compra_estado = 'X', compra_observacion = CONCAT_WS(' · ', compra_observacion, :m) WHERE compra_id = :c")
                ->execute([':m' => 'Anulada: ' . $motivo, ':c' => $c['compra_id']]);
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error($e instanceof \RuntimeException ? $e->getMessage() . ' Ya se vendió: no se puede anular la compra.' : 'No fue posible anular la compra.');
        }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Compra anulada', 'texto' => 'El stock que entró volvió a salir.']);
    }

    /*======================================================================
      Egresos del bar (migración 100, 2026-11-26)

      Los gastos del bar con su fecha, categoría, detalle y valor. Desde el
      2026-11-26 no se pregunta de dónde salió el dinero (egreso_origen N) y
      no tocan ninguna caja: el efectivo que sale de una caja se anota con
      «Egreso» en la vista Caja y aparece aquí como «egreso anotado en la
      caja». Los orígenes C (caja, con su movimiento EGR), P (dinero propio)
      y O (otro medio) quedan sólo para leer lo registrado antes.

      El resumen del período compara lo vendido (Vender y Registrar ventas)
      y los otros ingresos de caja con todo lo que salió: estos egresos, los
      egresos anotados directamente en una caja y los pagos de compras. No
      cuenta las devoluciones por ventas anuladas (la venta anulada ya no
      suma) ni los pagos de compras como egreso de caja (ya están en compras).

      EGRESOS DE INSTALACIONES (migración 101, egreso_ambito = 'I')

      Luz, agua, mantenimiento de la cancha, arreglo del césped… Misma tabla
      y mismo formulario, con estas diferencias:
        · nunca tocan la caja del punto de venta ni el cuadre de efectivo
          de Reportes › Pagos;
        · pueden llevar la cancha a la que corresponden (o ninguna: general);
        · su resumen compara lo que entró por reservas (Reportes › Pagos:
          cobros y depósitos al monedero, menos devoluciones) con estos
          egresos: la ganancia de las instalaciones.
      ====================================================================*/

    public const EGRESO_ORIGENES = ['C' => 'Efectivo de la caja', 'P' => 'Dinero propio', 'O' => 'Otro medio'];

    /** Ámbito de un egreso → la vista cuyos permisos lo gobiernan. */
    public const EGRESO_AMBITOS = ['B' => 'posEgresos', 'I' => 'egresoInstalaciones'];

    /** Categorías sugeridas; se suman las que ya se usaron. */
    public const EGRESO_CATEGORIAS = ['Insumos del bar', 'Hielo y agua', 'Gas', 'Limpieza', 'Transporte',
                                      'Ayudantes y sueldos', 'Mantenimiento', 'Servicios básicos', 'Otros'];
    public const EGRESO_CATEGORIAS_INSTALACIONES = ['Luz', 'Agua', 'Mantenimiento de la cancha', 'Arreglo del césped',
                                                    'Iluminación', 'Limpieza', 'Guardianía', 'Sueldos', 'Internet y teléfono',
                                                    'Arriendo', 'Impuestos y permisos', 'Otros'];

    /** El nombre del origen del dinero según el ámbito. */
    public static function origenEgreso(string $codigo, string $ambito = 'B'): string
    {
        if ($codigo === 'N') { return ''; }              /* no se pregunta (2026-11-26) */
        return self::EGRESO_ORIGENES[$codigo] ?? $codigo;
    }

    /** ¿Está aplicada la 100 (tabla dsa_egreso)? Una vez por petición. */
    public function egresosListos(): bool
    {
        static $hay = null;
        if ($hay === null) {
            try {
                $hay = (int)$this->escalar("SELECT COUNT(*) FROM information_schema.TABLES
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dsa_egreso'") === 1;
            } catch (\Throwable $e) { $hay = false; }
        }
        return $hay;
    }

    /** Filtro SQL del ámbito (sin la 101, todo es del bar). */
    private function filtroAmbito(string $ambito, string $alias = ''): string
    {
        if (!$this->egresosConAmbito()) { return $ambito === 'B' ? '1 = 1' : '1 = 0'; }
        return ($alias ? $alias . '.' : '') . "egreso_ambito = '" . ($ambito === 'I' ? 'I' : 'B') . "'";
    }

    public function categoriasEgreso(string $ambito = 'B'): array
    {
        $usadas = $this->egresosListos()
            ? array_column($this->filas("SELECT egreso_categoria c, COUNT(*) n FROM dsa_egreso WHERE egreso_estado = 'A' AND " . $this->filtroAmbito($ambito) . "
                                          GROUP BY egreso_categoria ORDER BY n DESC LIMIT 30"), 'c')
            : [];
        $todas = [];
        foreach (array_merge($usadas, $ambito === 'I' ? self::EGRESO_CATEGORIAS_INSTALACIONES : self::EGRESO_CATEGORIAS) as $c) {
            $todas[mb_strtolower($c)] ??= $c;
        }
        return array_values($todas);
    }

    public function egreso(int $id): ?array
    {
        if (!$this->egresosListos()) { return null; }
        return $this->filas("SELECT e.*, c.caja_estado FROM dsa_egreso e LEFT JOIN dsa_caja c ON c.caja_id = e.egreso_cajaid
                              WHERE e.egreso_id = :e", [':e' => $id])[0] ?? null;
    }

    /** [desde 00:00, día siguiente a hasta 00:00] para comparar fechas con hora. */
    private static function rango(string $desde, string $hasta): array
    {
        return [$desde . ' 00:00:00', date('Y-m-d', strtotime($hasta . ' +1 day')) . ' 00:00:00'];
    }

    /**
     * Todo lo que salió en el período, del más reciente al más antiguo:
     * los egresos (anulables) y, en el bar, los egresos anotados en una
     * caja y los pagos de compras (éstos dos, de consulta).
     */
    public function salidasPeriodo(string $desde, string $hasta, string $ambito = 'B'): array
    {
        [$d, $h] = self::rango($desde, $hasta);
        $formas = array_column($this->formasIngreso(), 'forma_nombre', 'forma_codigo');
        $conAmbito = $this->egresosConAmbito();
        $out = [];
        if ($this->egresosListos()) {
            foreach ($this->filas(
                "SELECT e.*, u.usuario_usuario, ux.usuario_usuario AS anulo, c.caja_estado,
                        " . ($conAmbito ? "i.instalacion_nombre" : "NULL AS instalacion_nombre") . "
                   FROM dsa_egreso e
                   LEFT JOIN seguridad_usuario u  ON u.usuario_id  = e.egreso_usuarioid
                   LEFT JOIN seguridad_usuario ux ON ux.usuario_id = e.egreso_anulausuario
                   LEFT JOIN dsa_caja c ON c.caja_id = e.egreso_cajaid
                   " . ($conAmbito ? "LEFT JOIN dsa_instalacion i ON i.instalacion_id = e.egreso_instalacionid" : "") . "
                  WHERE e.egreso_fecha BETWEEN :d AND :h AND " . $this->filtroAmbito($ambito, 'e') . "
                  ORDER BY e.egreso_fecha DESC, e.egreso_id DESC LIMIT 1000", [':d' => $desde, ':h' => $hasta]) as $e) {
                $origen = self::origenEgreso($e['egreso_origen'], $ambito);
                if ($e['egreso_origen'] === 'P' && $e['egreso_pagadopor']) { $origen .= ' de ' . $e['egreso_pagadopor']; }
                if ($e['egreso_origen'] === 'O') { $origen = $formas[$e['egreso_forma']] ?? $origen; }
                if ($e['egreso_origen'] === 'C' && $ambito === 'B' && !$e['egreso_cajaid']) { $origen .= ' (sin caja)'; }
                $out[] = ['tipo' => 'egreso', 'id' => (int)$e['egreso_id'], 'fecha' => $e['egreso_fecha'],
                          'instalacion' => (string)($e['instalacion_nombre'] ?? ''),
                          'registro' => $e['egreso_fecharegistro'], 'categoria' => $e['egreso_categoria'],
                          'concepto' => $e['egreso_concepto'], 'valor' => (float)$e['egreso_valor'], 'origen' => $origen,
                          'codigoOrigen' => $e['egreso_origen'], 'pagadoPor' => (string)$e['egreso_pagadopor'],
                          'referencia' => (string)$e['egreso_referencia'],
                          'usuario' => (string)$e['usuario_usuario'], 'anulado' => $e['egreso_estado'] === 'X',
                          'anulacion' => $e['egreso_estado'] === 'X' ? trim(($e['egreso_anulamotivo'] ?? '') . ' · ' . ($e['anulo'] ?? ''), ' ·') : '',
                          'enCaja' => (bool)$e['egreso_cajaid'], 'cajaCerrada' => $e['egreso_cajaid'] && $e['caja_estado'] !== 'A', 'url' => ''];
            }
        }
        if ($ambito !== 'B') {
            return $out;
        }
        foreach ($this->filas(
            "SELECT m.movimiento_id, m.movimiento_cajaid, m.movimiento_concepto, m.movimiento_detalle, m.movimiento_forma,
                    m.movimiento_valor, m.movimiento_fecharegistro, u.usuario_usuario
               FROM dsa_caja_movimiento m LEFT JOIN seguridad_usuario u ON u.usuario_id = m.movimiento_usuarioid
              WHERE m.movimiento_tipo = 'E' AND m.movimiento_origen = 'MAN' AND m.movimiento_estado = 'A'
                AND m.movimiento_fecharegistro >= :d AND m.movimiento_fecharegistro < :h", [':d' => $d, ':h' => $h]) as $m) {
            $out[] = ['tipo' => 'caja', 'id' => (int)$m['movimiento_id'], 'fecha' => substr($m['movimiento_fecharegistro'], 0, 10),
                      'registro' => $m['movimiento_fecharegistro'], 'categoria' => 'Egreso anotado en la caja',
                      'concepto' => $m['movimiento_concepto'] . ($m['movimiento_detalle'] ? ' · ' . $m['movimiento_detalle'] : ''),
                      'valor' => (float)$m['movimiento_valor'], 'origen' => 'Caja #' . (int)$m['movimiento_cajaid'] . ' · ' . ($formas[$m['movimiento_forma']] ?? $m['movimiento_forma']),
                      'codigoOrigen' => 'C', 'pagadoPor' => '', 'referencia' => '', 'usuario' => (string)$m['usuario_usuario'], 'anulado' => false, 'anulacion' => '',
                      'enCaja' => true, 'cajaCerrada' => true, 'url' => APP_URL . 'posCaja/?id=' . (int)$m['movimiento_cajaid']];
        }
        foreach ($this->filas(
            "SELECT cp.pago_id, cp.pago_compraid, cp.pago_valor, cp.pago_fecha, cp.pago_cajaid, cp.pago_referencia, cp.pago_fecharegistro,
                    c.compra_proveedor, f.forma_nombre, u.usuario_usuario
               FROM dsa_compra_pago cp
               JOIN dsa_compra c ON c.compra_id = cp.pago_compraid
               JOIN dsa_forma_ingreso f ON f.forma_id = cp.pago_formaid
               LEFT JOIN seguridad_usuario u ON u.usuario_id = cp.pago_usuarioid
              WHERE cp.pago_estado = 'A' AND c.compra_estado = 'A' AND cp.pago_fecha BETWEEN :d AND :h", [':d' => $desde, ':h' => $hasta]) as $p) {
            $out[] = ['tipo' => 'compra', 'id' => (int)$p['pago_id'], 'fecha' => $p['pago_fecha'], 'registro' => $p['pago_fecharegistro'],
                      'categoria' => 'Compras de productos',
                      'concepto' => 'Pago de la compra C-' . str_pad((string)$p['pago_compraid'], 5, '0', STR_PAD_LEFT) . ' · ' . $p['compra_proveedor'],
                      'valor' => (float)$p['pago_valor'], 'origen' => $p['pago_cajaid'] ? 'Efectivo de la caja #' . (int)$p['pago_cajaid'] : $p['forma_nombre'],
                      'codigoOrigen' => $p['pago_cajaid'] ? 'C' : 'O', 'pagadoPor' => '', 'referencia' => (string)$p['pago_referencia'],
                      'usuario' => (string)$p['usuario_usuario'], 'anulado' => false, 'anulacion' => '', 'enCaja' => (bool)$p['pago_cajaid'], 'cajaCerrada' => true,
                      'url' => APP_URL . 'posCompras/?id=' . (int)$p['pago_compraid']];
        }
        usort($out, static fn ($a, $b) => [$b['fecha'], $b['registro']] <=> [$a['fecha'], $a['registro']]);
        return $out;
    }

    /**
     * Ventas e ingresos frente a egresos en el período, con el detalle por
     * día, por categoría y lo pagado con dinero propio por persona.
     */
    public function resumenBar(string $desde, string $hasta, ?array $salidas = null): array
    {
        [$d, $h] = self::rango($desde, $hasta);
        $r = ['ventasPos' => 0.0, 'nPos' => 0, 'ventasReg' => 0.0, 'nReg' => 0, 'otrosIngresos' => 0.0,
              'egresos' => 0.0, 'egresosCaja' => 0.0, 'compras' => 0.0, 'porOrigen' => ['C' => 0.0, 'P' => 0.0, 'O' => 0.0],
              'porCategoria' => [], 'porPersona' => [], 'porDia' => [], 'costoVendido' => 0.0];
        $dia = static function (array &$r, string $f, string $k, float $v) {
            $r['porDia'][$f] ??= ['ventas' => 0.0, 'ingresos' => 0.0, 'egresos' => 0.0];
            $r['porDia'][$f][$k] += $v;
        };

        $origen = $this->ventasConRegistro() ? 'v.venta_origen' : "'P'";
        foreach ($this->filas(
            "SELECT DATE(v.venta_fecharegistro) f, $origen o, COUNT(*) n, SUM(v.venta_total) t
               FROM dsa_venta v WHERE v.venta_estado = 'A' AND v.venta_fecharegistro >= :d AND v.venta_fecharegistro < :h
              GROUP BY f, o", [':d' => $d, ':h' => $h]) as $v) {
            $t = (float)$v['t'];
            if ($v['o'] === 'R') { $r['ventasReg'] += $t; $r['nReg'] += (int)$v['n']; }
            else { $r['ventasPos'] += $t; $r['nPos'] += (int)$v['n']; }
            $dia($r, $v['f'], 'ventas', $t);
        }
        $r['costoVendido'] = (float)$this->escalar(
            "SELECT COALESCE(SUM(dd.detalle_costo * dd.detalle_cantidad), 0) FROM dsa_venta_detalle dd JOIN dsa_venta v ON v.venta_id = dd.detalle_ventaid
              WHERE v.venta_estado = 'A' AND v.venta_fecharegistro >= :d AND v.venta_fecharegistro < :h", [':d' => $d, ':h' => $h]);
        foreach ($this->filas(
            "SELECT DATE(movimiento_fecharegistro) f, SUM(movimiento_valor) t FROM dsa_caja_movimiento
              WHERE movimiento_tipo = 'I' AND movimiento_origen = 'MAN' AND movimiento_estado = 'A'
                AND movimiento_fecharegistro >= :d AND movimiento_fecharegistro < :h GROUP BY f", [':d' => $d, ':h' => $h]) as $m) {
            $r['otrosIngresos'] += (float)$m['t'];
            $dia($r, $m['f'], 'ingresos', (float)$m['t']);
        }

        foreach ($salidas ?? $this->salidasPeriodo($desde, $hasta) as $s) {
            if ($s['anulado']) { continue; }
            $v = $s['valor'];
            if ($s['tipo'] === 'egreso') {
                $r['egresos'] += $v;
                $r['porOrigen'][$s['codigoOrigen']] = ($r['porOrigen'][$s['codigoOrigen']] ?? 0) + $v;
                if ($s['codigoOrigen'] === 'P') {
                    $quien = $s['pagadoPor'] ?: 'Sin nombre';
                    $r['porPersona'][$quien] = ($r['porPersona'][$quien] ?? 0) + $v;
                }
            } elseif ($s['tipo'] === 'caja') { $r['egresosCaja'] += $v; }
            else { $r['compras'] += $v; }
            $r['porCategoria'][$s['categoria']] = ($r['porCategoria'][$s['categoria']] ?? 0) + $v;
            $dia($r, $s['fecha'], 'egresos', $v);
        }

        arsort($r['porCategoria']); arsort($r['porPersona']); krsort($r['porDia']);
        foreach ($r as $k => $v) { if (is_float($v)) { $r[$k] = round($v, 2); } }
        $r['vendido']       = round($r['ventasPos'] + $r['ventasReg'], 2);
        $r['ingresos']      = round($r['vendido'] + $r['otrosIngresos'], 2);
        $r['totalEgresos']  = round($r['egresos'] + $r['egresosCaja'] + $r['compras'], 2);
        $r['resultado']     = round($r['ingresos'] - $r['totalEgresos'], 2);
        return $r;
    }

    /**
     * Lo que entró por reservas frente a los egresos de las instalaciones.
     *
     * Lo que entró es lo de Reportes › Pagos (el mismo cálculo, así que las
     * dos pantallas dicen lo mismo): los cobros que trajeron dinero (los
     * pagados con monedero no: ese dinero entró al depositarlo) y los
     * depósitos al monedero, menos las devoluciones de reservas en efectivo
     * o transferencia y las devoluciones del monedero.
     */
    public function resumenInstalaciones(string $desde, string $hasta, ?array $salidas = null): array
    {
        $rp = $this->reportePagos($this->filtroReportePagos(['desde' => $desde, 'hasta' => $hasta]));
        $p  = $rp['resumen'];
        $r = ['cobros' => round($p['recaudado'] - $p['depositos'], 2), 'nCobros' => (int)$p['nPagos'],
              'depositos' => round($p['depositos'], 2), 'devReservas' => round($p['devEfectivo'] + $p['devBancos'], 2),
              'devMonedero' => round($p['devoluciones'], 2), 'monederoAplicado' => round($p['monederoAplicado'], 2),
              'egresos' => 0.0, 'porOrigen' => ['C' => 0.0, 'P' => 0.0, 'O' => 0.0],
              'porCategoria' => [], 'porPersona' => [], 'porInstalacion' => [], 'porDia' => []];
        $dia = static function (array &$r, string $f, string $k, float $v) {
            $r['porDia'][$f] ??= ['ingresos' => 0.0, 'egresos' => 0.0];
            $r['porDia'][$f][$k] += $v;
        };
        foreach ($rp['movimientos'] as $m) {
            /* Una devolución del monedero no dice cómo salió (entra = 0): se resta su valor. */
            $v = $m['tipo'] === 'devolucion' ? -(float)$m['valor'] : (float)$m['entra'];
            if ($v != 0.0) { $dia($r, $m['dia'] ?? date('Y-m-d', $m['ts']), 'ingresos', $v); }
        }
        foreach ($salidas ?? $this->salidasPeriodo($desde, $hasta, 'I') as $s) {
            if ($s['anulado']) { continue; }
            $v = $s['valor'];
            $r['egresos'] += $v;
            $r['porOrigen'][$s['codigoOrigen']] = ($r['porOrigen'][$s['codigoOrigen']] ?? 0) + $v;
            if ($s['codigoOrigen'] === 'P') { $q = $s['pagadoPor'] ?: 'Sin nombre'; $r['porPersona'][$q] = ($r['porPersona'][$q] ?? 0) + $v; }
            $r['porCategoria'][$s['categoria']] = ($r['porCategoria'][$s['categoria']] ?? 0) + $v;
            $inst = $s['instalacion'] !== '' ? $s['instalacion'] : 'General (todas)';
            $r['porInstalacion'][$inst] = ($r['porInstalacion'][$inst] ?? 0) + $v;
            $dia($r, $s['fecha'], 'egresos', $v);
        }
        arsort($r['porCategoria']); arsort($r['porPersona']); arsort($r['porInstalacion']); krsort($r['porDia']);
        foreach ($r['porDia'] as &$x) { $x = array_map(static fn ($v) => round($v, 2), $x); }
        unset($x);
        $r['egresos']  = round($r['egresos'], 2);
        $r['ingresos'] = round($r['cobros'] + $r['depositos'] - $r['devReservas'] - $r['devMonedero'], 2);
        $r['ganancia'] = round($r['ingresos'] - $r['egresos'], 2);
        return $r;
    }

    /**
     * Reportes › Ganancias: instalaciones (reservas − sus egresos) y bar
     * (ventas − sus egresos), con el total y el detalle por día o por mes.
     */
    public function resumenGanancias(string $desde, string $hasta): array
    {
        $inst = $this->resumenInstalaciones($desde, $hasta);
        $bar  = $this->resumenBar($desde, $hasta);
        $porMes = (strtotime($hasta) - strtotime($desde)) / 86400 > 62;
        $filas = [];
        $sumar = static function (array &$filas, string $clave, string $k, float $v) {
            $filas[$clave] ??= ['instIngresos' => 0.0, 'instEgresos' => 0.0, 'barIngresos' => 0.0, 'barEgresos' => 0.0];
            $filas[$clave][$k] += $v;
        };
        foreach ($inst['porDia'] as $f => $d) {
            $c = $porMes ? substr($f, 0, 7) : $f;
            $sumar($filas, $c, 'instIngresos', $d['ingresos']); $sumar($filas, $c, 'instEgresos', $d['egresos']);
        }
        foreach ($bar['porDia'] as $f => $d) {
            $c = $porMes ? substr($f, 0, 7) : $f;
            $sumar($filas, $c, 'barIngresos', $d['ventas'] + $d['ingresos']); $sumar($filas, $c, 'barEgresos', $d['egresos']);
        }
        krsort($filas);
        foreach ($filas as &$x) {
            $x = array_map(static fn ($v) => round($v, 2), $x);
            $x['instGanancia'] = round($x['instIngresos'] - $x['instEgresos'], 2);
            $x['barGanancia']  = round($x['barIngresos'] - $x['barEgresos'], 2);
            $x['total']        = round($x['instGanancia'] + $x['barGanancia'], 2);
        }
        unset($x);
        return ['inst' => $inst, 'bar' => $bar, 'porMes' => $porMes, 'filas' => $filas,
                'ingresos' => round($inst['ingresos'] + $bar['ingresos'], 2),
                'egresos'  => round($inst['egresos'] + $bar['totalEgresos'], 2),
                'total'    => round($inst['ganancia'] + $bar['resultado'], 2)];
    }

    /** AJAX posGuardarEgreso (del bar o, con ambito=I, de las instalaciones). */
    public function guardarEgreso(): string
    {
        $ambito = ($_POST['ambito'] ?? 'B') === 'I' ? 'I' : 'B';
        if (!puede_crear(self::EGRESO_AMBITOS[$ambito])) { return $this->error('Su rol no puede registrar egresos.', 'Acceso denegado'); }
        if (!$this->egresosListos()) { return $this->error('Aplique la migración 100 para registrar egresos.', 'Falta una actualización'); }
        if ($ambito === 'I' && !$this->egresosConAmbito()) {
            return $this->error('Aplique la migración 101 para registrar egresos de instalaciones.', 'Falta una actualización');
        }

        $hoy   = date('Y-m-d');
        $fecha = (string)($_POST['fecha'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !checkdate((int)substr($fecha, 5, 2), (int)substr($fecha, 8, 2), (int)substr($fecha, 0, 4))
            || $fecha > $hoy || $fecha < date('Y-m-d', strtotime($hoy . ' -' . self::REGISTRO_DIAS_ATRAS . ' days'))) {
            return $this->error('Elija la fecha del gasto: hoy o un día anterior (hasta un año atrás).', 'Fecha no válida');
        }
        $categoria = self::texto($_POST['categoria'] ?? '', 60);
        $concepto  = self::texto($_POST['concepto'] ?? '', 120);
        $valor     = self::numero($_POST['valor'] ?? 0);
        $ref       = self::texto($_POST['referencia'] ?? '', 60);
        if (mb_strlen($categoria) < 2) { return $this->error('Elija o escriba la categoría del gasto.'); }
        if (preg_match_all('/[\p{L}\p{N}]/u', $concepto) < 3) { return $this->error('Escriba en qué se gastó.'); }
        if ($valor <= 0 || $valor > 100000) { return $this->error('El valor debe ser mayor que cero.'); }
        /* Sólo el detalle y el valor: no se pregunta de dónde salió el dinero ni sale de ninguna caja (2026-11-26). */
        $origen = 'N'; $forma = null; $pagadoPor = null; $caja = null;

        $sedes = $this->sedesCaja();
        $sede  = (int)($_POST['sede'] ?? 0);
        if (!in_array($sede, array_map('intval', array_column($sedes, 'sede_id')), true)) { $sede = (int)($sedes[0]['sede_id'] ?? 0); }

        /* La cancha (instalaciones): opcional; su sede manda. */
        $instalacion = null;
        if ($ambito === 'I' && (int)($_POST['instalacion_id'] ?? 0) > 0) {
            $inst = $this->filas("SELECT instalacion_id, instalacion_sedeid FROM dsa_instalacion WHERE instalacion_id = :i AND instalacion_estado <> 'E'",
                                 [':i' => (int)$_POST['instalacion_id']])[0] ?? null;
            if (!$inst) { return $this->error('La instalación elegida ya no existe.'); }
            $instalacion = (int)$inst['instalacion_id'];
            if ((int)$inst['instalacion_sedeid'] > 0) { $sede = (int)$inst['instalacion_sedeid']; }
        }

        $con = $this->con();
        if ($con === null) { return $this->error('Sin conexión con la base de datos.'); }
        try {
            $con->beginTransaction();
            $movId = null;
            $cols = ['egreso_fecha' => $fecha, 'egreso_categoria' => $categoria, 'egreso_concepto' => $concepto, 'egreso_valor' => $valor,
                     'egreso_origen' => $origen, 'egreso_forma' => $forma, 'egreso_pagadopor' => $pagadoPor, 'egreso_referencia' => $ref ?: null,
                     'egreso_cajaid' => $caja['caja_id'] ?? null, 'egreso_movimientoid' => $movId, 'egreso_sedeid' => $sede ?: null,
                     'egreso_usuarioid' => $this->uid() ?: null];
            if ($this->egresosConAmbito()) { $cols['egreso_ambito'] = $ambito; $cols['egreso_instalacionid'] = $instalacion; }
            $con->prepare("INSERT INTO dsa_egreso (" . implode(', ', array_keys($cols)) . ") VALUES (:" . implode(', :', array_keys($cols)) . ")")
                ->execute(array_combine(array_map(static fn ($k) => ':' . $k, array_keys($cols)), array_values($cols)));
            $con->commit();
        } catch (\Throwable $e) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error($e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible registrar el egreso.');
        }
        $donde = '';
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Egreso registrado',
                            'texto' => trim($concepto . ': $' . number_format($valor, 2) . ' del ' . date('d/m/Y', strtotime($fecha)) . '. ' . $donde)]);
    }

    /** AJAX posAnularEgreso: con justificación; si salió de una caja, ésta debe seguir abierta. */
    public function anularEgreso(): string
    {
        $e = $this->egreso((int)($_POST['egreso_id'] ?? 0));
        if (!$e) { return $this->error('El egreso no existe.'); }
        if (!puede_eliminar(self::EGRESO_AMBITOS[($e['egreso_ambito'] ?? 'B') === 'I' ? 'I' : 'B'])) {
            return $this->error('Su rol no puede anular egresos.', 'Acceso denegado');
        }
        if ($e['egreso_estado'] !== 'A') { return $this->error('El egreso ya estaba anulado.'); }
        $motivo = self::motivo($_POST['motivo'] ?? '');
        if ($motivo === '') { return $this->error('Escriba por qué se anula el egreso.'); }
        if ($e['egreso_cajaid'] && $e['caja_estado'] !== 'A') {
            return $this->error('Salió de una caja que ya se cerró: es parte de su arqueo y no se puede anular.'
                              . ' Si el dinero volvió, regístrelo como ingreso en su caja.');
        }
        $con = $this->con();
        try {
            $con->beginTransaction();
            $st = $con->prepare("SELECT egreso_estado FROM dsa_egreso WHERE egreso_id = :e FOR UPDATE");
            $st->execute([':e' => $e['egreso_id']]);
            if ($st->fetchColumn() !== 'A') { throw new \RuntimeException('El egreso ya estaba anulado.'); }
            if ($e['egreso_movimientoid']) {
                $con->prepare("UPDATE dsa_caja_movimiento SET movimiento_estado = 'X' WHERE movimiento_id = :m")->execute([':m' => $e['egreso_movimientoid']]);
            }
            $con->prepare("UPDATE dsa_egreso SET egreso_estado = 'X', egreso_anulamotivo = :m, egreso_anulausuario = :u, egreso_anulafecha = NOW()
                            WHERE egreso_id = :e")->execute([':m' => $motivo, ':u' => $this->uid() ?: null, ':e' => $e['egreso_id']]);
            $con->commit();
        } catch (\Throwable $ex) {
            if ($con->inTransaction()) { $con->rollBack(); }
            return $this->error($ex instanceof \RuntimeException ? $ex->getMessage() : 'No fue posible anular el egreso.');
        }
        return $this->json(['ok' => true, 'tipo' => 'recargar', 'icono' => 'success', 'titulo' => 'Egreso anulado',
                            'texto' => $e['egreso_concepto'] . ': $' . number_format((float)$e['egreso_valor'], 2)
                                     . ($e['egreso_movimientoid'] ? '. El dinero vuelve a contar en su caja.' : '.')]);
    }

    /*======================================================================
      Nota de venta: ticket y PDF, con enlace para WhatsApp
      ====================================================================*/

    const VENTA_ENLACE_DIAS = 7;

    private function firmaVenta(int $id, int $vence): string
    {
        return substr(hash_hmac('sha256', 'arena-venta|' . $id . '|' . $vence, TOKEN_SECRET), 0, 32);
    }

    public function enlacePublicoVenta(int $id): array
    {
        if (!defined('TOKEN_SECRET') || TOKEN_SECRET === '' || $id <= 0) { return ['url' => '', 'vence' => 0]; }
        $vence = strtotime('today 23:59:59 +' . self::VENTA_ENLACE_DIAS . ' days');
        return ['url' => APP_URL . 'ventaPublica/' . $id . '-' . $vence . '-' . $this->firmaVenta($id, $vence) . '/', 'vence' => $vence];
    }

    public function ventaDeEnlacePublico(string $token): int
    {
        if (!defined('TOKEN_SECRET') || TOKEN_SECRET === '' || !preg_match('/^(\d{1,10})-(\d{9,11})-([0-9a-f]{32})$/', $token, $m)) { return 0; }
        [$_, $id, $vence, $firma] = $m;
        if ((int)$vence < time() || !hash_equals($this->firmaVenta((int)$id, (int)$vence), $firma)) { return 0; }
        return $this->venta((int)$id) ? (int)$id : 0;
    }

    /**
     * El nombre que encabeza la nota de venta: el de la instalación de la
     * sede de la caja («CHAMPIONS CANCHA SINTÉTICA»), no el de la
     * organización y la sede. Sin instalación activa, la sede; sin sede, la
     * organización.
     */
    public function cabeceraTicket(array $v): string
    {
        foreach ([$v['instalacion_nombre'] ?? '', $v['sede_nombre'] ?? '', ds_nombre_organizacion()] as $nombre) {
            if (trim((string)$nombre) !== '') { return mb_strtoupper(trim((string)$nombre), 'UTF-8'); }
        }
        return '';
    }

    /**
     * El ticket en PDF, 80 mm de ancho y tan largo como haga falta.
     *
     * Se dibuja dos veces: la primera en una hoja muy larga, sólo para saber
     * dónde termina; la segunda en una hoja de esa altura. Así no sobra papel
     * ni se corta nada, por largos que sean los nombres o el pie.
     *
     * El pie de Core › Organización (escuela_pie_pdf) va al final, en
     * varias líneas si hace falta: el que pone FPDF solo (ds_pdf_pie) es de
     * una línea y en 70 mm se recortaba con «…».
     */
    public function ticketPdf(array $v): string
    {
        require_once DS_HUB_PATH . 'ds_futbol/app/lib/fpdf.php';
        $t   = static fn ($s) => mb_convert_encoding((string)$s, 'Windows-1252', 'UTF-8');
        $din = static fn ($x) => '$' . number_format((float)$x, 2);
        $sede = (int)($v['caja_sedeid'] ?? 0);
        $cabecera = $this->cabeceraTicket($v);
        $pie = function_exists('ds_pie_pdf_texto') ? ds_pie_pdf_texto() : '';

        $dibujar = function (float $alto) use ($v, $t, $din, $sede, $cabecera, $pie): array {
            $pdf = new \FPDF('P', 'mm', [80, $alto]);
            $pdf->dsSinPie = true;
            $pdf->SetAutoPageBreak(false);
            $pdf->SetMargins(5, 5, 5);
            $pdf->AddPage();
            $pdf->SetTitle($t('Nota de venta ' . $v['venta_numero']));
            $ancho = 70;
            $raya = static function () use ($pdf) {
                $pdf->Ln(1.5);
                /* Línea de guiones, como la del ticket en pantalla. */
                $pdf->SetDrawColor(120, 120, 120);
                $pdf->SetLineWidth(0.2);
                for ($x = 5; $x < 75; $x += 1.6) { $pdf->Line($x, $pdf->GetY(), min($x + 0.9, 75), $pdf->GetY()); }
                $pdf->Ln(2);
            };

            /* Cabecera: logo, instalación, dirección y teléfono. */
            if (function_exists('ds_logo_dibujar') && ds_logo_dibujar($pdf, $sede, 5, 5, $ancho, 16, 'C')) {
                $pdf->SetY(22.5);
            }
            $pdf->SetFont('Arial', 'B', 11.5); $pdf->MultiCell($ancho, 5, $t($cabecera), 0, 'C');
            $pdf->SetFont('Arial', '', 8);
            if (!empty($v['sede_direccion'])) { $pdf->MultiCell($ancho, 3.8, $t($v['sede_direccion']), 0, 'C'); }
            if (!empty($v['sede_telefono']))  { $pdf->MultiCell($ancho, 3.8, $t('Tel. ' . $v['sede_telefono']), 0, 'C'); }
            $raya();

            $pdf->SetFont('Arial', 'B', 10.5); $pdf->Cell($ancho, 5, $t('NOTA DE VENTA ' . $v['venta_numero']), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 8);
            $pdf->MultiCell($ancho, 4, $t(date('d/m/Y H:i', strtotime($v['venta_fecharegistro'])) . ' · Atendió: ' . $v['vendedor']), 0, 'C');
            $pdf->MultiCell($ancho, 4, $t('Cliente: ' . ($v['cliente_nombre'] ? $v['cliente_nombre'] . ' · ' . $v['cliente_identificacion'] : 'Consumidor final')), 0, 'C');
            if ($v['venta_estado'] === 'X') {
                $pdf->Ln(1); $pdf->SetFont('Arial', 'B', 12); $pdf->SetTextColor(200, 0, 0); $pdf->SetDrawColor(200, 0, 0);
                $pdf->Cell($ancho, 6, 'ANULADA', 1, 1, 'C'); $pdf->SetTextColor(0, 0, 0); $pdf->SetFont('Arial', '', 8);
                if (!empty($v['venta_anulamotivo'])) { $pdf->MultiCell($ancho, 3.8, $t($v['venta_anulamotivo']), 0, 'C'); }
            }
            $raya();

            /* Productos */
            foreach ($v['lineas'] as $l) {
                $cant = rtrim(rtrim(number_format((float)$l['detalle_cantidad'], 2), '0'), '.');
                $pdf->SetFont('Arial', 'B', 8.5);
                $pdf->MultiCell($ancho, 4, $t($l['detalle_nombre']), 0, 'L');
                $pdf->SetFont('Arial', '', 8.5);
                $pdf->Cell(45, 4, $t($cant . ' x ' . $din($l['detalle_precio'])), 0, 0, 'L');
                $pdf->Cell(25, 4, $din($l['detalle_subtotal']), 0, 1, 'R');
                $pdf->Ln(0.6);
            }
            $raya();

            /* Totales y pagos */
            $fila = static function ($et, $val, bool $neg = false) use ($pdf, $t) {
                $pdf->SetFont('Arial', $neg ? 'B' : '', $neg ? 12 : 9);
                $pdf->Cell(40, $neg ? 6.5 : 4.5, $t($et), 0, 0, 'L'); $pdf->Cell(30, $neg ? 6.5 : 4.5, $t($val), 0, 1, 'R');
            };
            if ((float)$v['venta_descuento'] > 0) {
                $fila('Subtotal', $din($v['venta_subtotal']));
                $fila('Descuento', '-' . $din($v['venta_descuento']));
            }
            $fila('TOTAL', $din($v['venta_total']), true);
            foreach ($v['pagos'] as $p) {
                $fila($p['forma_nombre'] . ($p['pago_referencia'] ? ' · ' . $p['pago_referencia'] : ''), $din($p['pago_recibido'] ?? $p['pago_valor']));
                if ((float)$p['pago_cambio'] > 0) { $fila('Cambio', $din($p['pago_cambio'])); }
            }
            $raya();

            /* Pie: agradecimiento, aviso y el pie de la organización. */
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell($ancho, 4.5, $t('¡Gracias por su compra!'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 7);
            $pdf->MultiCell($ancho, 3.4, $t('Documento interno sin valor tributario.'), 0, 'C');
            if ($pie !== '') {
                $pdf->Ln(1.5);
                $pdf->SetFont('Arial', '', 6.5); $pdf->SetTextColor(110, 110, 110);
                $pdf->MultiCell($ancho, 3.1, $t($pie), 0, 'C');
                $pdf->SetTextColor(0, 0, 0);
            }
            return [$pdf, $pdf->GetY()];
        };

        [, $fin] = $dibujar(1000);
        [$pdf]   = $dibujar(max(90, ceil($fin + 5)));
        return $pdf->Output('S');
    }
}
