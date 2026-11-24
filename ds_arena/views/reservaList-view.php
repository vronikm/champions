<?php
/*
| Reservas con su estado de cobro, POR DÍAS (2026-11-24).
|
| Al entrar se ve UN día —hoy—, con sus reservas por hora y sus cifras: es
| lo que se mira en el mostrador. Se cambia de día con las flechas, con la
| franja de la semana (cada día dice cuántas reservas tiene) o con la fecha.
|
| Los enlaces que piden un rango —desde/hasta, las semanas de una reserva
| fija, lo pendiente de cobro de todo el histórico— siguen funcionando, y su
| lista también se lee por días: cada día con su encabezado y sus cifras.
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Reservas';
$vistaActual = 'reservaList';

$instalaciones = $insArena->instalaciones();

/* Las semanas de las reservas fijas, al día antes de listar. */
$insArena->extenderReservasFijas();
$fijas      = $insArena->reservasFijas();
$fijaFiltro = (int)($_GET['fija'] ?? 0);
$fijaVista  = $fijaFiltro > 0 ? $insArena->reservaFija($fijaFiltro) : null;
$diasSemana = $insArena->dias();

$hoy = date('Y-m-d');
$fechaValida = static function ($f): bool {
    return is_string($f) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $f, $m) === 1 && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
};

/*
| EL MODO
|
| Por día, salvo que la dirección pida un rango (desde/hasta) o las semanas
| de una reserva fija. Se mira la URL y no los valores: «desde=» vacío es el
| histórico entero (lo usa el panel para lo pendiente de cobro).
*/
$modoDia = !isset($_GET['desde']) && !isset($_GET['hasta']) && $fijaFiltro <= 0;
$dia     = $fechaValida($_GET['dia'] ?? null) ? (string)$_GET['dia'] : $hoy;

$filtros = [
    'fija'        => $fijaFiltro,
    'instalacion' => (int)($_GET['instalacion'] ?? 0),
    'estado'      => (string)($_GET['estado'] ?? ''),
    'desde'       => $modoDia ? $dia : ($fechaValida($_GET['desde'] ?? null) ? (string)$_GET['desde'] : ''),
    'hasta'       => $modoDia ? $dia : ($fechaValida($_GET['hasta'] ?? null) ? (string)$_GET['hasta'] : ''),
    'saldo'       => isset($_GET['saldo']) ? 1 : 0,
];

/* Un día cabe entero en una página; un rango se pagina. */
$porPagina  = $modoDia ? 200 : arena\controllers\arenaController::POR_PAGINA;
$totalFilas = $insArena->contarReservas($filtros);
$totalPags  = max(1, (int)ceil($totalFilas / $porPagina));
$pagina     = max(1, min($totalPags, (int)($_GET['pag'] ?? 1)));

/* Lo que viene se lee hacia adelante; lo pasado, de lo último a lo primero. */
$diasAsc  = $modoDia || ($filtros['desde'] !== '' && $filtros['desde'] >= $hoy);
$reservas = $insArena->reservas($filtros, $pagina, $porPagina, $diasAsc);

/* Los recibos de las reservas de esta página, en una sola consulta. */
$recibosPorReserva = $insArena->pagosDeReservas(array_column($reservas, 'reserva_id'));

/*
| LAS CIFRAS, POR DÍA
|
| Del día elegido (modo día) o de cada día que aparece en la página (rango).
| Salen de la BASE sobre el día entero, no de las filas de la página: un día
| partido entre dos páginas suma igual en las dos.
*/
$vacioDia = ['reservas' => 0, 'canceladas' => 0, 'horas' => 0.0, 'total' => 0.0, 'abonado' => 0.0, 'saldo' => 0.0];
if ($modoDia) {
    $cifrasDia = $insArena->resumenPorDia($filtros)[$dia] ?? $vacioDia;
    $lunes     = date('Y-m-d', strtotime('monday this week', strtotime($dia)));
    $domingo   = date('Y-m-d', strtotime($lunes . ' +6 days'));
    $semana    = $insArena->resumenPorDia(array_merge($filtros, ['desde' => $lunes, 'hasta' => $domingo]));
    $porDia    = [];
} else {
    $fechasPagina = array_column($reservas, 'reserva_fecha');
    $porDia = $fechasPagina
        ? $insArena->resumenPorDia(array_merge($filtros, ['desde' => min($fechasPagina), 'hasta' => max($fechasPagina)]))
        : [];
}

/*
| UN SOLO CONSTRUCTOR DE ENLACES
|
| Cada enlace conserva lo que se está mirando: el día (o el rango) y los
| filtros. En un rango «desde» viaja aunque esté vacío: es lo que dice
| «todo el histórico».
*/
$url = static function (array $cambios = []) use ($filtros, $modoDia, $dia, $hoy): string {
    $base = [
        'instalacion' => $filtros['instalacion'] ?: null,
        'estado'      => $filtros['estado'] !== '' ? $filtros['estado'] : null,
        'saldo'       => $filtros['saldo'] ? 1 : null,
    ];
    if ($modoDia) {
        $base = ['dia' => $dia !== $hoy ? $dia : null] + $base;
    } else {
        $base = ['fija' => $filtros['fija'] ?: null, 'desde' => $filtros['desde'], 'hasta' => $filtros['hasta'] !== '' ? $filtros['hasta'] : null] + $base;
        if ($filtros['fija']) { $base['desde'] = null; }
    }
    $q = array_filter(array_merge($base, $cambios), static fn ($v) => $v !== null);
    return APP_URL . 'reservaList/' . ($q ? '?' . http_build_query($q) : '');
};
$urlPagina = static fn (int $p): string => $url(['pag' => $p > 1 ? $p : null]);
$urlDia    = static fn (string $d): string => $url(['dia' => $d !== $hoy ? $d : null]);

/* El catálogo de estados vive en el controlador. */
$catalogo = $insArena->estadosReserva();
$estados  = array_map(static fn ($e) => $e['nombre'], $catalogo);
$colores  = array_map(static fn ($e) => $e['tono'],   $catalogo);

$puedeCrear  = puede_crear('reservaList');
$puedeEditar = puede_editar('reservaList');

/* Los totales del rango: de la BASE y sobre el filtro entero. */
$sumas        = $insArena->totalesReservas($filtros);
$totalGeneral = $sumas['total'];
$totalSaldo   = $sumas['saldo'];

/* Fechas en palabras. */
$DIAS_LARGOS = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$DIAS_CORTOS = [1 => 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'];
$MESES       = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$relativo = static function (string $f) use ($hoy): string {
    $d = (int)round((strtotime($f) - strtotime($hoy)) / 86400);
    return [-1 => 'Ayer', 0 => 'Hoy', 1 => 'Mañana'][$d] ?? '';
};
$fechaLarga = static function (string $f) use ($DIAS_LARGOS, $MESES): string {
    $t = strtotime($f);
    return $DIAS_LARGOS[(int)date('N', $t)] . ' ' . date('j', $t) . ' de ' . $MESES[(int)date('n', $t)]
         . (date('Y', $t) !== date('Y') ? ' de ' . date('Y', $t) : '');
};
$dinero = static fn (float $v): string => '$' . number_format($v, 2);
$horasTxt = static fn (float $h): string => rtrim(rtrim(number_format($h, 2), '0'), '.') . ' h';

/* Cuántos filtros (además del día) hay puestos: se dice en el botón de filtros. */
$filtrosActivos = ($filtros['instalacion'] ? 1 : 0) + ($filtros['estado'] !== '' ? 1 : 0) + ($filtros['saldo'] ? 1 : 0);

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if ($modoDia):
    $anterior  = date('Y-m-d', strtotime($dia . ' -1 day'));
    $siguiente = date('Y-m-d', strtotime($dia . ' +1 day'));
    $rel       = $relativo($dia);
?>
<!-- ==================== El día ==================== -->
<div class="card ar-dia">
    <div class="ar-dia__barra">
        <a class="btn ar-dia__flecha" href="<?php echo htmlspecialchars($urlDia($anterior), ENT_QUOTES, 'UTF-8'); ?>"
           aria-label="Día anterior" title="Día anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
        <div class="ar-dia__titulo">
            <h2 class="ar-dia__fecha mb-0"><?php echo htmlspecialchars($fechaLarga($dia), ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if ($rel !== ''): ?>
                <span class="ar-dia__rel<?php echo $rel === 'Hoy' ? ' ar-dia__rel--hoy' : ''; ?>"><?php echo $rel; ?></span>
            <?php else: ?>
                <a class="ar-dia__rel ar-dia__rel--ir" href="<?php echo htmlspecialchars($urlDia($hoy), ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Ir a hoy</a>
            <?php endif; ?>
        </div>
        <a class="btn ar-dia__flecha" href="<?php echo htmlspecialchars($urlDia($siguiente), ENT_QUOTES, 'UTF-8'); ?>"
           aria-label="Día siguiente" title="Día siguiente"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
    </div>

    <?php /* La semana del día: cada día dice cuántas reservas tiene (sin las canceladas). */ ?>
    <nav class="ar-semana" aria-label="Días de la semana">
        <?php for ($i = 0; $i < 7; $i++):
            $d  = date('Y-m-d', strtotime($lunes . " +$i days"));
            $n  = $semana[$d]['reservas'] ?? 0;
            $sd = (float)($semana[$d]['saldo'] ?? 0);
            $clases = 'ar-semana__dia' . ($d === $dia ? ' is-activo' : '') . ($d === $hoy ? ' is-hoy' : '');
        ?>
            <a class="<?php echo $clases; ?>" href="<?php echo htmlspecialchars($urlDia($d), ENT_QUOTES, 'UTF-8'); ?>"
               <?php echo $d === $dia ? 'aria-current="date"' : ''; ?>
               <?php $etq = $fechaLarga($d) . ': ' . $n . ($n === 1 ? ' reserva' : ' reservas') . ($sd > 0 ? ', $' . number_format($sd, 2) . ' por cobrar' : ''); ?>
               aria-label="<?php echo htmlspecialchars($etq, ENT_QUOTES, 'UTF-8'); ?>" title="<?php echo htmlspecialchars($etq, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="ar-semana__nombre"><?php echo $DIAS_CORTOS[$i + 1]; ?></span>
                <strong class="ar-semana__num"><?php echo (int)date('j', strtotime($d)); ?></strong>
                <span class="ar-semana__n<?php echo $n === 0 ? ' is-cero' : ''; ?><?php echo $sd > 0 ? ' is-saldo' : ''; ?>"><?php echo $n; ?></span>
            </a>
        <?php endfor; ?>
    </nav>

    <?php /* Filtros del día. En el teléfono se pliegan (se abren solos si hay alguno puesto). */ ?>
    <details class="ar-filtros" id="arFiltros" open>
        <summary class="ar-filtros__abrir">
            <i class="fas fa-sliders me-1" aria-hidden="true"></i>Filtros
            <?php if ($filtrosActivos): ?><span class="badge text-bg-primary ms-1"><?php echo $filtrosActivos; ?></span><?php endif; ?>
            <i class="fas fa-chevron-down ar-filtros__flecha" aria-hidden="true"></i>
        </summary>
        <form method="GET" action="<?php echo APP_URL; ?>reservaList/" class="ar-filtros__form" id="arFiltrosForm">
            <div class="ar-filtros__campo">
                <label class="form-label small mb-1" for="arDia">Día</label>
                <input type="date" id="arDia" name="dia" class="form-control form-control-sm" value="<?php echo htmlspecialchars($dia, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="ar-filtros__campo ar-filtros__campo--ancho">
                <label class="form-label small mb-1" for="arInst">Instalación</label>
                <select id="arInst" name="instalacion" class="form-select form-select-sm">
                    <option value="0">Todas</option>
                    <?php foreach ($instalaciones as $i): ?>
                        <option value="<?php echo (int)$i['instalacion_id']; ?>" <?php echo $filtros['instalacion'] === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ar-filtros__campo">
                <label class="form-label small mb-1" for="arEstado">Estado</label>
                <select id="arEstado" name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo $filtros['estado'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ar-filtros__acciones">
                <label class="form-check mb-0 small">
                    <input type="checkbox" class="form-check-input" name="saldo" value="1" <?php echo $filtros['saldo'] ? 'checked' : ''; ?>>
                    Sólo con saldo
                </label>
                <noscript><button type="submit" class="btn btn-sm btn-outline-secondary">Aplicar</button></noscript>
                <a class="small ms-auto" href="<?php echo htmlspecialchars(APP_URL . 'reservaList/?desde=' . date('Y-m-01', strtotime($dia)) . '&hasta=' . date('Y-m-t', strtotime($dia)), ENT_QUOTES, 'UTF-8'); ?>"
                   title="Ver varias fechas a la vez, agrupadas por día">
                    <i class="fas fa-calendar-week me-1" aria-hidden="true"></i>Ver un rango de fechas</a>
            </div>
        </form>
    </details>
</div>

<!-- ==================== Las cifras del día ==================== -->
<div class="ar-kpis" aria-label="Cifras del día">
    <div class="ar-kpi">
        <span class="ar-kpi__etq"><i class="fas fa-calendar-check" aria-hidden="true"></i>Reservas</span>
        <strong class="ar-kpi__cifra"><?php echo $cifrasDia['reservas']; ?></strong>
        <span class="ar-kpi__nota"><?php echo $cifrasDia['canceladas'] > 0
            ? $cifrasDia['canceladas'] . ($cifrasDia['canceladas'] === 1 ? ' cancelada' : ' canceladas') : 'ninguna cancelada'; ?></span>
    </div>
    <div class="ar-kpi">
        <span class="ar-kpi__etq"><i class="far fa-clock" aria-hidden="true"></i>Horas</span>
        <strong class="ar-kpi__cifra"><?php echo $horasTxt($cifrasDia['horas']); ?></strong>
        <span class="ar-kpi__nota">reservadas</span>
    </div>
    <div class="ar-kpi">
        <span class="ar-kpi__etq"><i class="fas fa-receipt" aria-hidden="true"></i>Total</span>
        <strong class="ar-kpi__cifra"><?php echo $dinero($cifrasDia['total']); ?></strong>
        <span class="ar-kpi__nota">del día</span>
    </div>
    <div class="ar-kpi ar-kpi--abonado">
        <span class="ar-kpi__etq"><i class="fas fa-coins" aria-hidden="true"></i>Abonado</span>
        <strong class="ar-kpi__cifra"><?php echo $dinero($cifrasDia['abonado']); ?></strong>
        <?php $pct = $cifrasDia['total'] > 0 ? min(100, $cifrasDia['abonado'] / $cifrasDia['total'] * 100) : 0; ?>
        <span class="ar-kpi__barra" role="progressbar" aria-label="Abonado del total" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int)round($pct); ?>"><span style="width:<?php echo round($pct, 1); ?>%"></span></span>
    </div>
    <div class="ar-kpi ar-kpi--saldo<?php echo $cifrasDia['saldo'] > 0 ? ' is-deuda' : ''; ?>">
        <span class="ar-kpi__etq"><i class="fas fa-hand-holding-dollar" aria-hidden="true"></i>Por cobrar</span>
        <strong class="ar-kpi__cifra"><?php echo $dinero($cifrasDia['saldo']); ?></strong>
        <span class="ar-kpi__nota"><?php echo $cifrasDia['saldo'] > 0 ? 'saldo del día' : ($cifrasDia['reservas'] > 0 ? 'todo pagado' : '—'); ?></span>
    </div>
</div>
<?php endif; ?>

<div class="card ar-reservas">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
        <h3 class="card-title mb-0">
            <?php if ($modoDia): ?>
                <i class="fas fa-list-ol me-1 text-body-secondary" aria-hidden="true"></i>Agenda del día
                <small class="text-body-secondary ms-1">· <?php echo $totalFilas; ?> <?php echo $totalFilas === 1 ? 'reserva' : 'reservas'; ?></small>
            <?php else: ?>
                <?php echo number_format($totalFilas, 0, ',', '.'); ?> reserva<?php echo $totalFilas === 1 ? '' : 's'; ?>
                <?php if ($totalSaldo > 0): ?>
                    <small class="text-danger ms-2">· $<?php echo number_format($totalSaldo, 2); ?> por cobrar</small>
                <?php endif; ?>
            <?php endif; ?>
        </h3>

        <?php if ($puedeCrear && $instalaciones): ?>
            <div class="d-flex flex-wrap gap-2 ar-reservas__nuevas">
                <a href="<?php echo APP_URL; ?>reservaForm/?repetir=1" class="btn btn-outline-primary btn-sm"
                   title="La misma hora un día de la semana, hasta que se cancele">
                    <i class="fas fa-repeat me-1"></i> Reserva fija
                </a>
                <a href="<?php echo APP_URL; ?>reservaForm/" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Nueva reserva
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$modoDia): ?>
    <div class="card-body border-bottom ar-reservas__filtros">
        <?php /* Rango: los filtros de siempre, y la vuelta a la vista por día. */ ?>
        <form method="GET" action="<?php echo APP_URL; ?>reservaList/" class="row g-2 align-items-end">
            <?php if ($filtros['fija']): ?><input type="hidden" name="fija" value="<?php echo (int)$filtros['fija']; ?>"><?php endif; ?>
            <div class="col-12 col-md-3 mb-2">
                <label class="mb-1 small" for="arInstR">Instalación</label>
                <select id="arInstR" name="instalacion" class="form-select form-select-sm">
                    <option value="0">Todas</option>
                    <?php foreach ($instalaciones as $i): ?>
                        <option value="<?php echo (int)$i['instalacion_id']; ?>"
                            <?php echo $filtros['instalacion'] === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
                <label class="mb-1 small" for="arEstadoR">Estado</label>
                <select id="arEstadoR" name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo $filtros['estado'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2 mb-2">
                <label class="mb-1 small" for="arDesde">Desde</label>
                <input type="date" id="arDesde" name="desde" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($filtros['desde'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-6 col-md-2 mb-2">
                <label class="mb-1 small" for="arHasta">Hasta</label>
                <input type="date" id="arHasta" name="hasta" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($filtros['hasta'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-12 col-md-3 mb-2 d-flex align-items-center ar-reservas__filtrar" style="gap:12px;">
                <label class="mb-0 small form-check">
                    <input type="checkbox" class="form-check-input" name="saldo" value="1" <?php echo $filtros['saldo'] ? 'checked' : ''; ?>>
                    Sólo con saldo
                </label>
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1" aria-hidden="true"></i>Filtrar</button>
            </div>
        </form>
    </div>

    <div class="card-body py-2 border-bottom small text-body-secondary d-flex flex-wrap gap-2 align-items-center">
        <span>
            <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
            <?php if ($totalFilas === 0): ?>
                Ninguna reserva con este filtro.
            <?php else: ?>
                <strong><?php echo number_format(($pagina - 1) * $porPagina + 1, 0, ',', '.'); ?></strong>–<strong><?php
                    echo number_format(min($pagina * $porPagina, $totalFilas), 0, ',', '.'); ?></strong>
                de <strong><?php echo number_format($totalFilas, 0, ',', '.'); ?></strong> reservas, por día
            <?php endif; ?>
        </span>
        <?php if ($fijaVista): ?>
            <span class="ms-auto">
                <i class="fas fa-repeat me-1 text-primary" aria-hidden="true"></i>
                Semanas de la reserva fija <strong><?php echo htmlspecialchars($fijaVista['fija_codigo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                (<?php echo htmlspecialchars($fijaVista['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?>, cada <?php
                    echo htmlspecialchars(mb_strtolower($diasSemana[(int)$fijaVista['fija_dia']] ?? ''), ENT_QUOTES, 'UTF-8'); ?> de <?php
                    echo substr($fijaVista['fija_horainicio'], 0, 5); ?> a <?php echo substr($fijaVista['fija_horafin'], 0, 5); ?><?php
                    echo $fijaVista['fija_estado'] === 'X' ? ', cancelada' : ''; ?>)
                · <a href="<?php echo APP_URL; ?>reservaList/">ver por día</a>
                <?php if ($fijaVista['fija_estado'] === 'X' && trim((string)$fijaVista['fija_motivocancelacion']) !== ''): ?>
                    <span class="d-block"><i class="fas fa-comment-dots me-1" aria-hidden="true"></i>Motivo de la cancelación: <?php
                        echo htmlspecialchars($fijaVista['fija_motivocancelacion'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </span>
        <?php else: ?>
            <a class="ms-auto" href="<?php echo APP_URL; ?>reservaList/"><i class="fas fa-calendar-day me-1" aria-hidden="true"></i>Ver por día</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php
    /*
    | TABLA EN ESCRITORIO, FICHAS EN EL TELÉFONO (< 992 px)
    |
    | Cada reserva es una ficha en el teléfono: código y estado arriba,
    | cliente, la hora y dónde, los tres importes en fila y los botones a
    | tamaño de dedo al pie. En un rango, cada día abre con su encabezado y
    | sus cifras; en la vista por día las cifras van arriba.
    */
    ?>
    <div class="card-body p-0">
        <div class="table-responsive ar-tabla__marco">
            <table class="table mb-0 ar-tabla">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Instalación</th>
                        <th>Hora</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Abonado</th>
                        <th class="text-end">Saldo</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$reservas): ?>
                    <tr class="ar-tabla__vacia"><td colspan="9" class="text-center text-muted py-4">
                        <?php if ($modoDia): ?>
                            <i class="far fa-calendar fa-2x d-block mb-2 opacity-50" aria-hidden="true"></i>
                            No hay reservas <?php echo $relativo($dia) === 'Hoy' ? 'hoy' : 'este día'; ?><?php echo $filtrosActivos ? ' con estos filtros' : ''; ?>.
                            <?php if ($puedeCrear && $instalaciones): ?>
                                <a class="d-block mt-2" href="<?php echo APP_URL; ?>reservaForm/"><i class="fas fa-plus me-1" aria-hidden="true"></i>Registrar una reserva</a>
                            <?php endif; ?>
                        <?php else: ?>
                            No hay reservas que coincidan.
                        <?php endif; ?>
                    </td></tr>
                <?php endif; ?>

                <?php
                $diaActual = null;
                foreach ($reservas as $r):
                    $saldo    = (float)$r['reserva_saldo'];
                    $abonado  = (float)$r['reserva_abonado'];
                    $total    = (float)$r['reserva_total'];
                    $progreso = $total > 0 ? round($abonado / $total * 100) : 0;
                    $cancelada = $r['reserva_estado'] === 'X';
                    $motivoX   = $cancelada ? trim((string)($r['reserva_motivocancelacion'] ?? '')) : '';

                    /* En un rango, cada día abre con su encabezado y sus cifras. */
                    if (!$modoDia && $r['reserva_fecha'] !== $diaActual):
                        $diaActual = $r['reserva_fecha'];
                        $c = $porDia[$diaActual] ?? $vacioDia;
                        $relD = $relativo($diaActual);
                ?>
                    <tr class="ar-tabla__dia">
                        <th colspan="9" scope="rowgroup">
                            <div class="ar-tabla__dia-cab">
                                <a class="ar-tabla__dia-fecha" href="<?php echo htmlspecialchars($url(['dia' => $diaActual, 'desde' => null, 'hasta' => null, 'fija' => null, 'pag' => null]), ENT_QUOTES, 'UTF-8'); ?>"
                                   title="Ver sólo este día">
                                    <i class="far fa-calendar me-1" aria-hidden="true"></i><?php echo htmlspecialchars($fechaLarga($diaActual), ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($relD !== ''): ?><span class="badge text-bg-primary ms-1"><?php echo $relD; ?></span><?php endif; ?>
                                </a>
                                <span class="ar-tabla__dia-cifras">
                                    <span><?php echo $c['reservas']; ?> <?php echo $c['reservas'] === 1 ? 'reserva' : 'reservas'; ?><?php
                                        echo $c['canceladas'] ? ' · ' . $c['canceladas'] . ($c['canceladas'] === 1 ? ' cancelada' : ' canceladas') : ''; ?></span>
                                    <span><?php echo $horasTxt($c['horas']); ?></span>
                                    <span>Total <strong><?php echo $dinero($c['total']); ?></strong></span>
                                    <span class="text-success">Abonado <strong><?php echo $dinero($c['abonado']); ?></strong></span>
                                    <span class="<?php echo $c['saldo'] > 0 ? 'text-danger' : 'text-body-secondary'; ?>">Por cobrar <strong><?php echo $dinero($c['saldo']); ?></strong></span>
                                </span>
                            </div>
                        </th>
                    </tr>
                <?php endif; ?>
                    <tr class="<?php echo $cancelada ? 'ar-tabla__cancelada' : ''; ?>">
                        <td class="ar-tabla__codigo">
                            <code><?php echo htmlspecialchars($r['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?></code>
                            <?php if ($r['reserva_fijaid']): ?>
                                <a href="<?php echo APP_URL; ?>reservaList/?fija=<?php echo (int)$r['reserva_fijaid']; ?>"
                                   class="badge text-bg-primary text-decoration-none ar-marca-fija"
                                   title="Semana de la reserva fija <?php echo htmlspecialchars((string)$r['fija_codigo'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-repeat" aria-hidden="true"></i>Fija
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="ar-tabla__cliente">
                            <strong><?php echo htmlspecialchars($r['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($r['cliente_identificacion'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td class="ar-tabla__donde">
                            <i class="fas fa-location-dot me-1 text-body-secondary d-lg-none" aria-hidden="true"></i><?php echo htmlspecialchars($r['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            <small class="d-block text-muted"><?php echo htmlspecialchars((string)$r['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td class="ar-tabla__cuando">
                            <?php /* La fecha la dice el día (arriba o en su encabezado): aquí, la hora. */ ?>
                            <i class="far fa-clock me-1 text-body-secondary d-lg-none" aria-hidden="true"></i><strong class="ar-tabla__hora"><?php
                                echo substr($r['reserva_horainicio'], 0, 5); ?>–<?php echo substr($r['reserva_horafin'], 0, 5); ?></strong>
                            <small class="d-block text-muted"><?php echo $horasTxt((float)$r['reserva_horas']); ?></small>
                        </td>
                        <td class="text-end ar-tabla__importe" data-rotulo="Total">$<?php echo number_format($total, 2); ?></td>
                        <td class="text-end ar-tabla__importe" data-rotulo="Abonado">
                            <span class="text-success">$<?php echo number_format($abonado, 2); ?></span>
                            <?php if ($total > 0): ?>
                                <div class="progress mt-1" style="height:4px;">
                                    <div class="progress-bar bg-success" style="width:<?php echo $progreso; ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end ar-tabla__importe" data-rotulo="Saldo">
                            <?php if ($cancelada): ?>
                                <span class="text-body-secondary small" title="Una reserva cancelada no se cobra">No se cobra</span>
                            <?php elseif ($saldo > 0): ?>
                                <strong class="text-danger">$<?php echo number_format($saldo, 2); ?></strong>
                            <?php else: ?>
                                <span class="badge text-bg-success">Pagada</span>
                            <?php endif; ?>
                        </td>
                        <td class="ar-tabla__estado">
                            <span class="badge text-bg-<?php echo $colores[$r['reserva_estado']] ?? 'secondary'; ?>">
                                <?php echo $estados[$r['reserva_estado']] ?? $r['reserva_estado']; ?>
                            </span>
                            <?php if ($cancelada): ?>
                                <?php /* Por qué se canceló, a la vista. Las anteriores a la
                                         migración 087 no lo tienen y se dice así. */ ?>
                                <span class="ar-motivo-x<?php echo $motivoX === '' ? ' ar-motivo-x--sin' : ''; ?>"
                                      <?php echo $motivoX !== '' ? 'title="' . htmlspecialchars($motivoX, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
                                    <i class="fas fa-comment-dots me-1" aria-hidden="true"></i><?php
                                    echo $motivoX !== '' ? htmlspecialchars($motivoX, ENT_QUOTES, 'UTF-8') : 'Sin motivo registrado'; ?>
                                </span>
                            <?php endif; ?>
                            <?php if ((float)($r['devuelto'] ?? 0) > 0): ?>
                                <a class="ar-devuelto" href="<?php echo APP_URL; ?>reservaDetalle/?id=<?php echo (int)$r['reserva_id']; ?>#devoluciones"
                                   title="Ver las devoluciones de esta reserva">
                                    <i class="fas fa-rotate-left me-1" aria-hidden="true"></i>Devuelto $<?php echo number_format((float)$r['devuelto'], 2); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td class="ds-tabla-acciones ar-tabla__acciones">
                            <?php if ($cancelada): ?>
                                <?php /* Cancelada: no se cobra. Se puede ver, y reactivar si fue un error. */ ?>
                                <a href="<?php echo APP_URL; ?>reservaDetalle/?id=<?php echo (int)$r['reserva_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                                    <i class="fas fa-eye"></i><span class="ar-solo-movil ms-1">Ver detalle</span>
                                </a>
                            <?php else: ?>
                                <a href="<?php echo APP_URL; ?>reservaDetalle/?id=<?php echo (int)$r['reserva_id']; ?>"
                                   class="btn btn-sm <?php echo $saldo > 0 ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                                   title="<?php echo $saldo > 0 ? 'Registrar un abono' : 'Ver abonos'; ?>">
                                    <i class="fas fa-coins"></i><?php echo $saldo > 0 ? ' Cobrar' : ''; ?>
                                </a>
                            <?php endif; ?>

                            <?php
                            /* Recibos de sus pagos: con uno, directo; con varios,
                               un desplegable con cada recibo, su fecha y su valor. */
                            $recibos = $recibosPorReserva[(int)$r['reserva_id']] ?? [];
                            ?>
                            <?php if (count($recibos) === 1): ?>
                                <a href="<?php echo APP_URL; ?>reciboPago/?id=<?php echo (int)$recibos[0]['pago_id']; ?>"
                                   class="btn btn-sm btn-outline-success" title="Recibo <?php echo $recibos[0]['recibo_numero']; ?>: ver, descargar o enviar">
                                    <i class="fas fa-receipt"></i>
                                </a>
                            <?php elseif ($recibos): ?>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-success dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false" data-bs-popper-config='{"strategy":"fixed"}'
                                            title="<?php echo count($recibos); ?> recibos: ver, descargar o enviar">
                                        <i class="fas fa-receipt"></i> <?php echo count($recibos); ?>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><h6 class="dropdown-header">Recibos de <?php echo htmlspecialchars($r['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?></h6></li>
                                        <?php foreach ($recibos as $p): ?>
                                            <li>
                                                <a class="dropdown-item d-flex justify-content-between gap-3"
                                                   href="<?php echo APP_URL; ?>reciboPago/?id=<?php echo (int)$p['pago_id']; ?>">
                                                    <span><?php echo $p['recibo_numero']; ?>
                                                        <small class="text-body-secondary ms-1"><?php echo date('d/m/Y', strtotime($p['pago_fecha'])); ?></small></span>
                                                    <strong>$<?php echo number_format((float)$p['pago_valor'], 2); ?></strong>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <?php if ($puedeEditar && $r['reserva_estado'] !== 'X'): ?>
                                <a href="<?php echo APP_URL; ?>reservaForm/?id=<?php echo (int)$r['reserva_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>

                                <?php
                                /* UN BOTÓN, NO UN FORMULARIO POR FILA
                                   Cada fila llevaba hasta DOS <form> con tres campos
                                   ocultos cada uno: 701 formularios en la página. Ahora
                                   son botones con sus datos en atributos y un único
                                   formulario oculto al final que se rellena al pulsar. */
                                ?>
                                <?php if ($r['reserva_estado'] === 'P'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info js-estado"
                                            data-id="<?php echo (int)$r['reserva_id']; ?>"
                                            data-estado="C" title="Confirmar">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>

                                <?php /* Abre el modal que pide el motivo (obligatorio) y, si
                                         tiene abonos, cómo se devuelven (migración 092). */ ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-cancelar-reserva"
                                            data-abonado="<?php echo number_format($abonado, 2, '.', ''); ?>"
                                            data-id="<?php echo (int)$r['reserva_id']; ?>"
                                            data-codigo="<?php echo htmlspecialchars($r['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-detalle="<?php echo htmlspecialchars($r['cliente_nombre'] . ' · ' . $r['instalacion_nombre'] . ' · ' .
                                                date('d/m/Y', strtotime($r['reserva_fecha'])) . ' de ' . substr($r['reserva_horainicio'], 0, 5) .
                                                ' a ' . substr($r['reserva_horafin'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-fija="<?php echo $r['reserva_fijaid'] ? '1' : ''; ?>"
                                            title="Cancelar" aria-label="Cancelar la reserva <?php echo htmlspecialchars($r['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="fas fa-times"></i><span class="ar-solo-movil ms-1">Cancelar</span>
                                    </button>
                            <?php elseif ($puedeEditar && $cancelada): ?>
                                <button type="button" class="btn btn-sm btn-outline-success js-estado"
                                        data-id="<?php echo (int)$r['reserva_id']; ?>"
                                        data-codigo="<?php echo htmlspecialchars($r['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-estado="P" title="Reactivar: vuelve a Pendiente si el horario sigue libre">
                                    <i class="fas fa-undo me-1"></i>Reactivar
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>

                <?php if ($reservas && !$modoDia): ?>
                    <tfoot>
                        <tr style="background:var(--core-suave);font-weight:700;">
                            <?php /* Se dice de qué son: con la tabla paginada, un
                                     «Totales» a secas se lee como el de la página. */ ?>
                            <td colspan="4" class="text-end ar-tabla__pie-titulo">
                                Totales del filtro
                                <span class="fw-normal text-body-secondary">(<?php
                                    echo number_format($totalFilas, 0, ',', '.'); ?> reservas)</span>
                            </td>
                            <td class="text-end ar-tabla__importe" data-rotulo="Total">$<?php echo number_format($totalGeneral, 2); ?></td>
                            <td class="text-end text-success ar-tabla__importe" data-rotulo="Abonado">$<?php echo number_format($totalGeneral - $totalSaldo, 2); ?></td>
                            <td class="text-end text-danger ar-tabla__importe" data-rotulo="Saldo">$<?php echo number_format($totalSaldo, 2); ?></td>
                            <td colspan="2" class="ar-tabla__pie-hueco"></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>

    <?php if ($totalPags > 1): ?>
    <?php
    /*
    | La navegación: primera, anterior, una ventana de cinco, siguiente y
    | última.
    |
    | Con 19 páginas caben todas, pero con 400 no: la ventana deslizante
    | mantiene el paginador del mismo tamaño sea cual sea el filtro, y
    | «primera» y «última» evitan tener que pulsar veinte veces.
    |
    | Los enlaces llevan los filtros dentro (urlPagina), así que cambiar de
    | página nunca cambia lo que se está buscando.
    */
    $ventana = 2;
    $desdeP  = max(1, $pagina - $ventana);
    $hastaP  = min($totalPags, $pagina + $ventana);
    ?>
    <div class="card-body border-top py-2">
        <nav aria-label="Páginas de reservas">
            <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center">
                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($urlPagina(1), ENT_QUOTES, 'UTF-8'); ?>"
                       aria-label="Primera página">&laquo;</a>
                </li>
                <li class="page-item <?php echo $pagina <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($urlPagina($pagina - 1), ENT_QUOTES, 'UTF-8'); ?>"
                       aria-label="Página anterior">&lsaquo;</a>
                </li>

                <?php for ($n = $desdeP; $n <= $hastaP; $n++): ?>
                <li class="page-item <?php echo $n === $pagina ? 'active' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($urlPagina($n), ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo $n === $pagina ? 'aria-current="page"' : ''; ?>><?php echo $n; ?></a>
                </li>
                <?php endfor; ?>

                <li class="page-item <?php echo $pagina >= $totalPags ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($urlPagina($pagina + 1), ENT_QUOTES, 'UTF-8'); ?>"
                       aria-label="Página siguiente">&rsaquo;</a>
                </li>
                <li class="page-item <?php echo $pagina >= $totalPags ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($urlPagina($totalPags), ENT_QUOTES, 'UTF-8'); ?>"
                       aria-label="Última página">&raquo;</a>
                </li>
            </ul>
        </nav>
        <p class="text-center small text-body-secondary mb-0 mt-1">
            Página <?php echo $pagina; ?> de <?php echo $totalPags; ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<?php /* Las reservas fijas, debajo de la agenda: lo primero que se ve es el día. */ ?>
<?php if ($fijas): ?>
<?php /*
| RESERVAS FIJAS (2026-11-02): las series activas, una tarjeta por serie.
| «Semanas» filtra el listado de abajo por la serie; «Cancelar» la termina
| desde el día que se indique.
*/ ?>
<div class="card ar-fijas">
    <div class="card-header d-flex align-items-center gap-2">
        <h3 class="card-title mb-0"><i class="fas fa-repeat me-2 text-primary" aria-hidden="true"></i><?php
            echo count($fijas); ?> reserva<?php echo count($fijas) === 1 ? '' : 's'; ?> fija<?php echo count($fijas) === 1 ? '' : 's'; ?></h3>
        <small class="text-body-secondary ms-auto d-none d-md-inline">
            Apartan su hora todas las semanas hasta que se cancelan
        </small>
    </div>
    <div class="card-body ar-fijas__lista">
        <?php foreach ($fijas as $f):
            $deuda = (float)$f['saldo_usado'];
            $nombreDia = $diasSemana[(int)$f['fija_dia']] ?? '';
        ?>
            <div class="ar-fija<?php echo $fijaFiltro === (int)$f['fija_id'] ? ' ar-fija--activa' : ''; ?>">
                <div class="ar-fija__cuando">
                    <span class="ar-fija__dia"><?php echo htmlspecialchars($nombreDia, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span class="ar-fija__hora"><?php echo substr($f['fija_horainicio'], 0, 5); ?>–<?php echo substr($f['fija_horafin'], 0, 5); ?></span>
                </div>
                <div class="ar-fija__datos">
                    <strong><?php echo htmlspecialchars($f['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo htmlspecialchars($f['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?> · <code><?php echo htmlspecialchars($f['fija_codigo'], ENT_QUOTES, 'UTF-8'); ?></code></span>
                    <span class="ar-fija__estado">
                        <?php if ($f['proxima']): ?>
                            <span>Próxima: <strong><?php echo date('d/m/Y', strtotime($f['proxima'])); ?></strong></span>
                        <?php endif; ?>
                        <?php if ((int)$f['pagadas_adelantado'] > 0): ?>
                            <span class="badge text-bg-success"><?php echo (int)$f['pagadas_adelantado']; ?> pagada<?php echo (int)$f['pagadas_adelantado'] === 1 ? '' : 's'; ?> por adelantado</span>
                        <?php endif; ?>
                        <?php if ($deuda > 0): ?>
                            <span class="badge text-bg-danger" title="Semanas ya usadas sin pagar">Debe $<?php echo number_format($deuda, 2); ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="ar-fija__acciones">
                    <a href="<?php echo APP_URL; ?>reservaList/?fija=<?php echo (int)$f['fija_id']; ?>"
                       class="btn btn-sm btn-outline-secondary" title="Ver y cobrar sus semanas">
                        <i class="fas fa-list me-1" aria-hidden="true"></i>Semanas
                    </a>
                    <?php if ($puedeEditar): ?>
                        <button type="button" class="btn btn-sm btn-outline-danger js-cancelar-fija"
                                data-id="<?php echo (int)$f['fija_id']; ?>"
                                data-codigo="<?php echo htmlspecialchars($f['fija_codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                data-detalle="<?php echo htmlspecialchars($f['cliente_nombre'] . ' · cada ' . mb_strtolower($nombreDia) . ' de ' . substr($f['fija_horainicio'], 0, 5) . ' a ' . substr($f['fija_horafin'], 0, 5), ENT_QUOTES, 'UTF-8'); ?>"
                                title="Terminar la reserva fija">
                            <i class="fas fa-ban me-1" aria-hidden="true"></i>Cancelar
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($puedeEditar): ?>
<?php
/*
| EL ÚNICO FORMULARIO DE ACCIÓN DE LA PANTALLA
|
| Antes había uno por cada botón de cada fila —701 en total— con tres
| campos ocultos cada uno. Ahora los botones sólo llevan sus datos en
| atributos y este formulario se rellena al pulsar.
|
| Va fuera de la tabla a propósito: un <form> hijo de <tr> es HTML inválido
| y el navegador lo saca del cuerpo, dejando los campos huérfanos.
*/
?>
<form class="FormularioAjax d-none" id="formEstadoReserva" method="POST"
      action="<?php echo APP_URL; ?>ajax/arenaAjax.php" autocomplete="off"
      data-confirmar="¿Confirma el cambio de estado de la reserva?">
    <input type="hidden" name="modulo_arena"  value="cambiarEstadoReserva">
    <input type="hidden" name="reserva_id"    id="estadoReservaId"     value="">
    <input type="hidden" name="reserva_estado" id="estadoReservaValor" value="">
</form>

<script>
/* Delegado en el documento y no en cada botón: si mañana esta tabla pasa
   por DataTables, las filas se rehacen al paginar y los oyentes puestos
   fila a fila se pierden ahí. */
document.addEventListener('click', function (e) {
    var b = e.target.closest('.js-estado');
    if (!b) { return; }
    var f = document.getElementById('formEstadoReserva');
    document.getElementById('estadoReservaId').value    = b.dataset.id     || '';
    document.getElementById('estadoReservaValor').value = b.dataset.estado || '';
    /* Cada cambio dice qué va a pasar, con el código de la reserva.
       Cancelar NO pasa por aquí: tiene su modal, que pide el motivo. */
    var codigo = b.dataset.codigo ? ' ' + b.dataset.codigo : '';
    var avisos = {
        C: '¿Confirmar la reserva' + codigo + '?',
        P: 'La reserva' + codigo + ' volverá a estar activa como Pendiente. ' +
           'Antes se comprobará que el horario siga libre.'
    };
    f.setAttribute('data-confirmar', avisos[b.dataset.estado] || '¿Cambiar el estado de esta reserva?');
    f.requestSubmit();
});
</script>

<?php
/*
| CANCELAR UNA RESERVA: SIEMPRE CON MOTIVO
|
| Antes era un «¿Está seguro?» y nada más: no quedaba por qué se canceló.
| El modal ES la confirmación (el formulario no lleva data-confirmar, que
| añadiría una segunda pregunta). Los atajos rellenan el motivo con un
| toque y se puede completar a mano; el servidor exige al menos
| MOTIVO_MINIMO letras.
|
| En el teléfono se abre como hoja desde abajo, con los botones a lo
| ancho y al alcance del pulgar.
*/
$motivosReserva = ['Lo pidió el cliente', 'Lluvia o mal clima', 'El cliente no se presentó',
                   'Mantenimiento de la cancha', 'Cambio de horario', 'Error al registrar'];
?>
<div class="modal fade ar-hoja" id="modalCancelarReserva" tabindex="-1" aria-labelledby="cancelarReservaTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form class="FormularioAjax ar-cancelar" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" autocomplete="off">
                <input type="hidden" name="modulo_arena"   value="cambiarEstadoReserva">
                <input type="hidden" name="reserva_id"     id="cancelarReservaId" value="">
                <input type="hidden" name="reserva_estado" value="X">

                <div class="modal-header">
                    <h5 class="modal-title" id="cancelarReservaTitulo"><i class="fas fa-ban me-2 text-danger" aria-hidden="true"></i>Cancelar reserva</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="ar-cancelar__resumen">
                        <code id="cancelarReservaCodigo"></code>
                        <span id="cancelarReservaDetalle"></span>
                    </div>

                    <fieldset class="mb-2">
                        <legend class="form-label fs-6 mb-2">¿Por qué se cancela? <span class="text-danger">*</span></legend>
                        <div class="ar-motivos" role="group" aria-label="Motivos frecuentes">
                            <?php foreach ($motivosReserva as $m): ?>
                                <button type="button" class="btn btn-outline-secondary ar-motivos__op" aria-pressed="false"
                                        data-texto="<?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <label for="cancelarReservaMotivo" class="form-label small text-body-secondary mb-1">Motivo (puede detallarlo)</label>
                    <textarea class="form-control ar-motivo__texto" id="cancelarReservaMotivo" name="reserva_motivo" rows="2"
                              maxlength="250" minlength="<?php echo arena\controllers\arenaController::MOTIVO_MINIMO; ?>" required
                              placeholder="Elija un motivo o escríbalo"></textarea>
                    <div class="d-flex justify-content-between form-text">
                        <span>Queda guardado con la fecha y su usuario.</span>
                        <span class="ar-motivo__cuenta" aria-live="polite">0/250</span>
                    </div>

                    <?php /* Con abonos: se devuelve todo al cancelar, por el medio
                             elegido. Sin abonos la sección no se ve y sus campos
                             van desactivados (no se envían ni se exigen). */ ?>
                    <fieldset class="ar-devolver mt-3" id="cancelarDevolucion" hidden disabled>
                        <legend class="form-label fs-6 mb-1">
                            <i class="fas fa-rotate-left me-1 text-warning" aria-hidden="true"></i>
                            Devolver <strong id="cancelarDevolucionValor">$0.00</strong> abonados <span class="text-danger">*</span>
                        </legend>
                        <p class="small text-body-secondary mb-2">¿Cómo se le devuelve al cliente?</p>
                        <div class="ar-devolver__medios" role="radiogroup" aria-label="Medio de devolución">
                            <input type="radio" class="btn-check disable-adminlte-validations" name="devolucion_medio" id="devMedioEFE" value="EFE" required>
                            <label class="btn btn-outline-secondary" for="devMedioEFE"><i class="fas fa-money-bill-wave me-1" aria-hidden="true"></i>Efectivo</label>
                            <input type="radio" class="btn-check disable-adminlte-validations" name="devolucion_medio" id="devMedioTRA" value="TRA">
                            <label class="btn btn-outline-secondary" for="devMedioTRA"><i class="fas fa-building-columns me-1" aria-hidden="true"></i>Transferencia</label>
                            <input type="radio" class="btn-check disable-adminlte-validations" name="devolucion_medio" id="devMedioMON" value="MON">
                            <label class="btn btn-outline-secondary" for="devMedioMON"><i class="fas fa-wallet me-1" aria-hidden="true"></i>Monedero</label>
                        </div>
                        <div class="ar-devolver__error" role="alert" hidden>Elija cómo se le devuelve al cliente.</div>
                        <div class="mt-2" id="cancelarDevolucionRef" hidden>
                            <label for="devReferencia" class="form-label small mb-1">Número o comprobante de la transferencia <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="devReferencia" name="devolucion_referencia" maxlength="60" disabled>
                        </div>
                        <p class="small text-body-secondary mb-0 mt-2" id="cancelarDevolucionNota"></p>
                    </fieldset>

                    <p class="ar-cancelar__aviso mb-0 mt-3">
                        <i class="fas fa-circle-info me-1" aria-hidden="true"></i>El horario quedará libre.
                        <span class="ar-cancelar__fija d-none">Sólo se cancela esta semana; la reserva fija sigue.</span>
                        Si fue un error, se puede reactivar.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-ban me-1" aria-hidden="true"></i><span id="cancelarReservaBoton">Cancelar reserva</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('.js-cancelar-reserva');
    if (!b) { return; }
    var modal = document.getElementById('modalCancelarReserva');
    document.getElementById('cancelarReservaId').value = b.dataset.id;
    document.getElementById('cancelarReservaCodigo').textContent  = b.dataset.codigo;
    document.getElementById('cancelarReservaDetalle').textContent = b.dataset.detalle;
    modal.querySelector('.ar-cancelar__fija').classList.toggle('d-none', !b.dataset.fija);
    arLimpiarMotivo(modal);

    /* Con abonos, la devolución: se muestra, se exige y el botón lo dice. */
    var abonado = parseFloat(b.dataset.abonado || '0') || 0;
    var dev = document.getElementById('cancelarDevolucion');
    var txt = '$' + abonado.toFixed(2);
    dev.hidden = dev.disabled = !(abonado > 0);
    dev.querySelectorAll('input[name="devolucion_medio"]').forEach(function (r) { r.checked = false; });
    document.getElementById('devReferencia').value = '';
    dev.querySelector('.ar-devolver__error').hidden = true;
    document.getElementById('cancelarDevolucionValor').textContent = txt;
    document.getElementById('cancelarReservaBoton').textContent = abonado > 0 ? 'Cancelar y devolver ' + txt : 'Cancelar reserva';
    arMedioDevolucion();

    bootstrap.Modal.getOrCreateInstance(modal).show();
});

/* Transferencia pide su comprobante; cada medio dice qué pasará. */
function arMedioDevolucion() {
    var elegido = document.querySelector('#cancelarDevolucion input[name="devolucion_medio"]:checked');
    var medio = elegido ? elegido.value : '';
    var caja = document.getElementById('cancelarDevolucionRef'), ref = document.getElementById('devReferencia');
    caja.hidden = medio !== 'TRA';
    ref.disabled = medio !== 'TRA';
    ref.required = medio === 'TRA';
    document.getElementById('cancelarDevolucionNota').textContent = {
        EFE: 'Se entrega en efectivo: el cuadre de caja de hoy lo resta.',
        TRA: 'Se devuelve por transferencia a la cuenta del cliente.',
        MON: 'Queda como saldo a favor del cliente para su próxima reserva.'
    }[medio] || '';
}
document.addEventListener('change', function (e) {
    if (e.target.name === 'devolucion_medio') {
        document.querySelector('#cancelarDevolucion .ar-devolver__error').hidden = true;
        arMedioDevolucion();
    }
});
/* Sin medio: un solo aviso para el grupo, no uno por opción (las opciones
   llevan disable-adminlte-validations para que AdminLTE no los ponga). */
document.addEventListener('invalid', function (e) {
    if (e.target.name === 'devolucion_medio') {
        document.querySelector('#cancelarDevolucion .ar-devolver__error').hidden = false;
    }
}, true);
</script>
<?php endif; ?>

<?php if ($puedeEditar): ?>
<script>
/*
| Atajos de motivo, comunes a los dos modales. Un toque escribe el motivo
| en el cuadro (y lo marca); escribir a mano desmarca el atajo. Si ya había
| algo escrito a mano, el atajo va delante y se conserva lo escrito.
*/
function arLimpiarMotivo(modal) {
    var t = modal.querySelector('.ar-motivo__texto');
    t.value = '';
    t.dataset.atajo = '';
    modal.querySelectorAll('.ar-motivos__op').forEach(function (o) { o.setAttribute('aria-pressed', 'false'); });
    arContarMotivo(t);
    /* Al abrir de nuevo, sin el aviso de la vez anterior. */
    t.classList.remove('is-invalid');
    var aviso = document.getElementById(t.id + '-error');
    if (aviso) { aviso.textContent = ''; }
}
function arContarMotivo(t) {
    var c = t.closest('.modal').querySelector('.ar-motivo__cuenta');
    if (c) { c.textContent = t.value.length + '/250'; }
    /* AdminLTE marca el campo y añade su aviso al fallar la validación,
       pero no los quita al corregirlo: se quitan aquí. */
    t.setCustomValidity('');
    if (t.validity.valid) {   /* validity, no checkValidity(): ésa dispara «invalid» */
        t.classList.remove('is-invalid');
        var aviso = document.getElementById(t.id + '-error');
        if (aviso) { aviso.textContent = ''; }
    }
}
document.addEventListener('click', function (e) {
    var op = e.target.closest('.ar-motivos__op');
    if (!op) { return; }
    var modal = op.closest('.modal');
    var t = modal.querySelector('.ar-motivo__texto');
    var actual = t.value.trim();
    var previo = t.dataset.atajo || '';
    /* Lo escrito a mano después del atajo anterior se conserva. */
    var resto = previo && actual.indexOf(previo) === 0 ? actual.slice(previo.length).replace(/^[\s:.,-]+/, '')
              : (previo ? '' : actual);
    t.value = op.dataset.texto + (resto ? ': ' + resto : '');
    t.dataset.atajo = op.dataset.texto;
    modal.querySelectorAll('.ar-motivos__op').forEach(function (o) { o.setAttribute('aria-pressed', o === op ? 'true' : 'false'); });
    arContarMotivo(t);
    t.setCustomValidity('');
});
document.addEventListener('input', function (e) {
    var t = e.target.closest('.ar-motivo__texto');
    if (!t) { return; }
    arContarMotivo(t);
    t.setCustomValidity('');
    if (t.dataset.atajo && t.value.indexOf(t.dataset.atajo) !== 0) {
        t.dataset.atajo = '';
        t.closest('.modal').querySelectorAll('.ar-motivos__op').forEach(function (o) { o.setAttribute('aria-pressed', 'false'); });
    }
});
/* Mensaje propio en lugar del genérico del navegador. Va en captura para
   correr antes que el envío por AJAX. */
document.addEventListener('invalid', function (e) {
    if (e.target.classList && e.target.classList.contains('ar-motivo__texto')) {
        e.target.setCustomValidity('Elija un motivo o escriba por qué se cancela.');
    }
}, true);
/* En el teléfono el teclado tapa medio modal: el foco va al cuadro sólo en
   pantallas anchas; en el teléfono primero se ven los atajos. */
['modalCancelarReserva', 'modalCancelarFija'].forEach(function (id) {
    var m = document.getElementById(id);
    if (!m) { return; }
    m.addEventListener('shown.bs.modal', function () {
        if (window.matchMedia('(min-width: 576px)').matches) { m.querySelector('.ar-motivo__texto').focus(); }
    });
});
</script>
<?php endif; ?>

<?php if ($puedeEditar && $fijas): ?>
<?php
$motivosFija = ['El cliente ya no vendrá', 'Lo pidió el cliente', 'Cambio de horario',
                'Terminó la temporada', 'Falta de pago'];
?>
<div class="modal fade ar-hoja" id="modalCancelarFija" tabindex="-1" aria-labelledby="cancelarFijaTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form class="FormularioAjax ar-cancelar" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" autocomplete="off"
                  data-confirmar="La hora dejará de estar apartada y se cancelarán las semanas sin pagos desde ese día.">
                <input type="hidden" name="modulo_arena" value="cancelarReservaFija">
                <input type="hidden" name="fija_id" id="cancelarFijaId" value="">

                <div class="modal-header">
                    <h5 class="modal-title" id="cancelarFijaTitulo"><i class="fas fa-repeat me-2 text-danger" aria-hidden="true"></i>Cancelar reserva fija</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <div class="modal-body">
                    <div class="ar-cancelar__resumen">
                        <code id="cancelarFijaCodigo"></code>
                        <span id="cancelarFijaDetalle"></span>
                    </div>

                    <div class="mb-3">
                        <label for="cancelarFijaDesde" class="form-label">Cancelar desde <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="cancelarFijaDesde" name="fija_cancelar_desde" required
                               min="<?php echo date('Y-m-d'); ?>" value="<?php echo date('Y-m-d'); ?>">
                        <div class="form-text">
                            Las semanas anteriores quedan como están, para cobrarlas. Desde ese día se cancelan
                            las que no tienen pagos; las pagadas por adelantado siguen en pie y se le indicarán.
                        </div>
                    </div>

                    <fieldset class="mb-2">
                        <legend class="form-label fs-6 mb-2">¿Por qué se cancela? <span class="text-danger">*</span></legend>
                        <div class="ar-motivos" role="group" aria-label="Motivos frecuentes">
                            <?php foreach ($motivosFija as $m): ?>
                                <button type="button" class="btn btn-outline-secondary ar-motivos__op" aria-pressed="false"
                                        data-texto="<?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($m, ENT_QUOTES, 'UTF-8'); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <label for="cancelarFijaMotivo" class="form-label small text-body-secondary mb-1">Motivo (puede detallarlo)</label>
                    <textarea class="form-control ar-motivo__texto" id="cancelarFijaMotivo" name="fija_motivo" rows="2"
                              maxlength="250" minlength="<?php echo arena\controllers\arenaController::MOTIVO_MINIMO; ?>" required
                              placeholder="Elija un motivo o escríbalo"></textarea>
                    <div class="d-flex justify-content-between form-text">
                        <span>Se guarda en la serie y en cada semana cancelada.</span>
                        <span class="ar-motivo__cuenta" aria-live="polite">0/250</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger"><i class="fas fa-ban me-1" aria-hidden="true"></i>Cancelar reserva fija</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest('.js-cancelar-fija');
    if (!b) { return; }
    var modal = document.getElementById('modalCancelarFija');
    document.getElementById('cancelarFijaId').value = b.dataset.id;
    document.getElementById('cancelarFijaCodigo').textContent = b.dataset.codigo;
    document.getElementById('cancelarFijaDetalle').textContent = b.dataset.detalle;
    arLimpiarMotivo(modal);
    bootstrap.Modal.getOrCreateInstance(modal).show();
});
</script>
<?php endif; ?>

<?php if ($modoDia): ?>
<script>
/* Vista por día: cambiar el día o un filtro lo aplica solo. En el teléfono
   los filtros empiezan plegados, salvo que haya alguno puesto. */
(function () {
    var form = document.getElementById('arFiltrosForm');
    if (form) {
        form.addEventListener('change', function () {
            if (form.reportValidity()) { form.submit(); }
        });
    }
    var det = document.getElementById('arFiltros');
    if (det && window.matchMedia('(max-width: 767.98px)').matches && <?php echo $filtrosActivos ? 'false' : 'true'; ?>) {
        det.open = false;
    }
})();
</script>
<?php endif; ?>

<style>
/* Reservas fijas: una tarjeta por serie; en el teléfono, una debajo de otra. */
.ar-fijas__lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 360px), 1fr)); gap: .75rem; }
.ar-fija {
    display: grid; grid-template-columns: auto 1fr; gap: .25rem .75rem; align-items: start;
    padding: .75rem; border: 1px solid var(--bs-border-color); border-radius: .5rem;
}
.ar-fija--activa { border-color: var(--bs-primary); box-shadow: 0 0 0 1px var(--bs-primary); }
.ar-fija__cuando {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-width: 5.5rem; padding: .4rem .5rem; border-radius: .4rem;
    background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis); line-height: 1.2;
}
.ar-fija__dia { font-weight: 700; }
.ar-fija__hora { font-size: .85rem; white-space: nowrap; }
.ar-fija__datos { display: flex; flex-direction: column; gap: .15rem; min-width: 0; }
.ar-fija__datos > span { font-size: .875rem; color: var(--bs-secondary-color); }
.ar-fija__estado { display: flex; flex-wrap: wrap; gap: .35rem; align-items: center; }
.ar-fija__acciones { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: .5rem; margin-top: .25rem; }
/* La marca «Fija» de cada semana, bajo su código. */
.ar-marca-fija { display: inline-flex; align-items: center; gap: .3rem; margin-top: .25rem; white-space: nowrap; }

/* ---- Motivo de la cancelación, en la fila ---- */
.ar-motivo-x {
    display: block; max-width: 15rem; margin-top: .3rem;
    font-size: .8rem; line-height: 1.3; color: var(--bs-secondary-color);
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.ar-motivo-x--sin { font-style: italic; opacity: .8; }
/* Lo que se devolvió al cancelar (migración 092). */
.ar-devuelto {
    display: inline-block; margin-top: .3rem; font-size: .8rem; text-decoration: none;
    color: var(--bs-warning-text-emphasis); white-space: nowrap;
}
.ar-devuelto:hover { text-decoration: underline; }

/* ---- Devolución, dentro del modal de cancelación ---- */
.ar-devolver {
    border: 1px solid var(--bs-warning-border-subtle) !important; border-radius: .5rem;
    padding: .75rem !important; background: var(--bs-warning-bg-subtle);
}
.ar-devolver legend { float: none; width: auto; margin-bottom: .25rem; }
.ar-devolver__medios { display: flex; flex-wrap: wrap; gap: .5rem; }
.ar-devolver__medios .btn { --bs-btn-padding-y: .35rem; --bs-btn-font-size: .9rem; background: var(--bs-body-bg); }
.ar-devolver__medios .btn-check:checked + .btn { background: var(--ds-acento, #0e7490); border-color: var(--ds-acento, #0e7490); color: #fff; }
.ar-devolver__error { margin-top: .4rem; font-size: .85rem; color: var(--bs-danger-text-emphasis); }
.ar-tabla__cancelada > td:not(.ar-tabla__acciones):not(.ar-tabla__estado) { color: var(--bs-secondary-color); }
.ar-solo-movil { display: none; }

/* ---- Modal de cancelación ---- */
.ar-cancelar__resumen {
    display: flex; flex-direction: column; gap: .15rem;
    padding: .6rem .75rem; margin-bottom: 1rem; border-radius: .5rem;
    background: var(--bs-tertiary-bg); font-size: .9rem;
}
.ar-cancelar__resumen code { font-size: .95rem; font-weight: 600; }
.ar-cancelar__aviso { font-size: .85rem; color: var(--bs-secondary-color); }
/* Atajos de motivo: botones-ficha que se envuelven. El elegido, relleno. */
.ar-motivos { display: flex; flex-wrap: wrap; gap: .4rem; }
.ar-motivos__op { --bs-btn-padding-y: .3rem; --bs-btn-padding-x: .7rem; --bs-btn-font-size: .875rem; border-radius: 2rem; }
/* Sin elegir, con el color del texto y no el gris de btn-outline-secondary: en oscuro parecían desactivados. */
.ar-motivos__op, .ar-devolver__medios .btn {
    --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color);
    --bs-btn-hover-color: var(--bs-body-color); --bs-btn-hover-bg: var(--bs-tertiary-bg); --bs-btn-hover-border-color: var(--bs-secondary-color);
}
.ar-motivos .ar-motivos__op[aria-pressed="true"] {
    --bs-btn-color: #fff; --bs-btn-bg: var(--bs-danger); --bs-btn-border-color: var(--bs-danger);
    --bs-btn-hover-color: #fff; --bs-btn-hover-bg: var(--bs-danger); --bs-btn-hover-border-color: var(--bs-danger);
    --bs-btn-active-color: #fff; --bs-btn-active-bg: var(--bs-danger); --bs-btn-active-border-color: var(--bs-danger);
}
.ar-motivo__texto { resize: vertical; }
.ar-cancelar fieldset { border: 0; padding: 0; margin-left: 0; margin-right: 0; min-width: 0; }
/*
| EL PIE SIEMPRE A LA VISTA
| El <form> va entre .modal-content y .modal-body, y eso rompe
| modal-dialog-scrollable: el cuerpo no se encogía y, con la devolución
| (2026-11-23), los botones quedaban fuera de la pantalla. El formulario
| pasa a ser la columna flexible: cabecera y pie fijos, el cuerpo se
| desplaza.
*/
.ar-hoja .modal-content > form.ar-cancelar {
    display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; max-height: 100%;
}
.ar-hoja .modal-content > form.ar-cancelar > .modal-header,
.ar-hoja .modal-content > form.ar-cancelar > .modal-footer { flex-shrink: 0; }
.ar-hoja .modal-content > form.ar-cancelar > .modal-body { overflow-y: auto; overscroll-behavior: contain; min-height: 0; }
/* Cuando el cuerpo se desplaza, una sombra sobre el pie dice que hay más arriba. */
.ar-hoja .modal-footer { box-shadow: 0 -6px 12px -10px rgba(0, 0, 0, .35); position: relative; z-index: 1; }
.ar-cancelar legend { float: none; width: auto; padding: 0; font-weight: 600; }

/* ---- Teléfono y tableta: fichas en lugar de tabla (< 992 px) ---- */
@media (max-width: 991.98px) {
    .ar-tabla__marco { overflow: visible; padding: .75rem; }
    .ar-tabla, .ar-tabla > tbody, .ar-tabla > tfoot { display: block; }
    .ar-tabla > thead { display: none; }
    .ar-tabla > tbody > tr, .ar-tabla > tfoot > tr {
        display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .35rem .75rem;
        padding: .85rem; margin-bottom: .75rem;
        border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg);
        background: var(--bs-body-bg);
    }
    .ar-tabla > tbody > tr.ar-tabla__cancelada { background: var(--bs-tertiary-bg); }
    .ar-tabla > tbody > tr.ar-tabla__vacia { display: block; }
    /* .ds-core delante: «.ds-core .table td» pone borde y relleno a cada celda. */
    .ds-core .ar-tabla > tbody > tr > td, .ds-core .ar-tabla > tfoot > tr > td {
        display: block; padding: 0; border: 0; background: transparent; text-align: left;
    }
    /* AdminLTE sangra la primera celda de las tablas en .card-body.p-0. */
    .ds-core .ar-tabla > tbody > tr > td:first-child,
    .ds-core .ar-tabla > tfoot > tr > td:first-child { padding-left: 0 !important; }
    /*
    | Cada parte en su fila, a mano: con colocación automática, en las
    | canceladas (estado en la fila 3) «Cuándo» y «Dónde» quedaban en filas
    | distintas y los importes se descolocaban. Fila 3 vacía en las demás:
    | sólo suma un hueco.
    |   1 código · estado      2 cliente       3 estado de la cancelada
    |   4 cuándo · dónde       5 importes      6 botones
    */
    .ar-tabla__codigo  { grid-column: 1 / 5; grid-row: 1; display: flex !important; flex-wrap: wrap; align-items: center; gap: .4rem; }
    .ar-tabla__cuando, .ar-tabla__donde { grid-row: 4; }
    .ar-tabla > tbody .ar-tabla__importe { grid-row: 5; }
    td.ar-tabla__acciones { grid-row: 6; }
    .ar-tabla__codigo .ar-marca-fija { margin-top: 0; }
    .ar-tabla__estado  { grid-column: 5 / 7; grid-row: 1; justify-self: end; text-align: right !important; }
    /* Cancelada: el estado baja a su propia línea para que el motivo se lea entero. */
    .ar-tabla__cancelada .ar-tabla__estado {
        grid-column: 1 / -1; grid-row: 3; justify-self: stretch; text-align: left !important;
        padding: .5rem .65rem !important; border-radius: .4rem;
        background: var(--bs-body-bg) !important; border-left: 3px solid var(--bs-secondary) !important;
    }
    .ar-tabla__cancelada .ar-motivo-x { max-width: none; white-space: normal; font-size: .875rem; }
    .ar-tabla__cliente { grid-column: 1 / -1; grid-row: 2; font-size: 1.02rem; }
    .ar-tabla__cuando  { grid-column: 1 / 4; font-size: .9rem; }
    .ar-tabla__donde   { grid-column: 4 / 7; font-size: .9rem; min-width: 0; overflow-wrap: anywhere; }
    .ar-tabla__importe { grid-column: span 2; font-size: .95rem; padding-top: .35rem !important; border-top: 1px dashed var(--bs-border-color) !important; }
    .ar-tabla__importe::before {
        content: attr(data-rotulo); display: block;
        font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); font-weight: 400;
    }
    /* Botones de dedo, en una fila a lo ancho; el principal (Cobrar) ocupa el sobrante. */
    td.ar-tabla__acciones {
        grid-column: 1 / -1; display: flex !important; flex-wrap: wrap; align-items: stretch; gap: .4rem;
        margin-top: .35rem; padding-top: .6rem !important;
        border-top: 1px solid var(--bs-border-color-translucent) !important; white-space: normal;
    }
    td.ds-tabla-acciones.ar-tabla__acciones .btn,
    td.ds-tabla-acciones.ar-tabla__acciones .btn-group > .btn {
        min-height: 2.75rem; min-width: 2.75rem; margin-left: 0 !important;
        display: inline-flex; align-items: center; justify-content: center;
    }
    td.ar-tabla__acciones > a.btn:first-child { flex: 1 1 auto; }
    td.ar-tabla__acciones .btn-outline-danger.js-cancelar-reserva { flex: 0 1 auto; }
    .ar-solo-movil { display: inline; }
    /* Pie: los totales del filtro como una ficha más. */
    .ar-tabla > tfoot > tr { background: var(--core-suave, var(--bs-tertiary-bg)) !important; }
    .ar-tabla__pie-titulo { grid-column: 1 / -1; text-align: left !important; }
    .ar-tabla__pie-hueco { display: none !important; }
}
@media (max-width: 575.98px) {
    /* Encabezado: los dos botones de alta, a lo ancho. */
    .ar-reservas__nuevas { width: 100%; }
    .ar-reservas__nuevas .btn { flex: 1 1 0; min-height: 2.5rem; display: inline-flex; align-items: center; justify-content: center; }
    .ar-reservas__filtros .form-select, .ar-reservas__filtros .form-control { min-height: 2.5rem; font-size: 1rem; }
    .ar-reservas__filtrar .btn { flex: 1 1 auto; min-height: 2.5rem; }
    .ar-fija__acciones .btn { flex: 1 1 0; min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; }
    .ar-tabla__marco { padding: .5rem; }

    /* El modal, como hoja que sube desde abajo: al alcance del pulgar. */
    .ar-hoja .modal-dialog { margin: 0; min-height: 100%; align-items: flex-end; max-width: none; }
    .ar-hoja.fade .modal-dialog { transform: translateY(100%); }
    .ar-hoja.show .modal-dialog { transform: none; }
    /* dvh: la altura que de verdad queda con la barra del navegador. */
    .ar-hoja .modal-content { border-radius: 1rem 1rem 0 0; border: 0; max-height: 92vh; max-height: 92dvh; }
    .ar-hoja .modal-dialog-scrollable { height: auto; max-height: none; }
    /* El asa de la hoja: se reconoce que es un panel que se cierra. */
    .ar-hoja .modal-header { position: relative; padding: 1rem 1rem .6rem; }
    .ar-hoja .modal-header::before {
        content: ""; position: absolute; top: .4rem; left: 50%; width: 2.5rem; height: .25rem;
        margin-left: -1.25rem; border-radius: 1rem; background: var(--bs-border-color);
    }
    .ar-hoja .modal-title { font-size: 1.1rem; }
    .ar-hoja .modal-body { padding: .75rem 1rem; }
    .ar-hoja .ar-cancelar__resumen { margin-bottom: .75rem; padding: .5rem .65rem; font-size: .85rem; }
    .ar-hoja .modal-footer { flex-wrap: nowrap; gap: .5rem; padding: .6rem 1rem max(.75rem, env(safe-area-inset-bottom)); }
    .ar-hoja .modal-footer > .btn { flex: 1 1 0; min-height: 3rem; margin: 0; }
    .ar-hoja .modal-footer > .btn-danger { flex-grow: 2.2; font-weight: 600; }
    /* Motivos: dos columnas parejas en lugar de tres filas desiguales. */
    .ar-motivos { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; }
    .ar-motivos__op { --bs-btn-padding-y: .55rem; --bs-btn-padding-x: .5rem; --bs-btn-font-size: .85rem; border-radius: .6rem; line-height: 1.2; }
    .ar-motivo__texto { font-size: 1rem; }
    /* Medios de devolución: tres botones iguales, icono arriba y texto abajo. */
    .ar-devolver { padding: .65rem !important; }
    .ar-devolver__medios { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; }
    .ar-devolver__medios .btn {
        display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .2rem;
        min-height: 3.6rem; --bs-btn-font-size: .85rem; --bs-btn-padding-x: .25rem;
    }
    .ar-devolver__medios .btn i { margin: 0 !important; font-size: 1.15rem; }
    .ar-devolver input[type="text"] { font-size: 1rem; min-height: 2.75rem; }   /* 16 px: iOS no hace zoom al escribir */
}

/* =====================================================================
   POR DÍAS (2026-11-24)
   ===================================================================== */
/* ---- El día: flechas, fecha y la semana ---- */
.ar-dia { overflow: hidden; }
.ar-dia__barra { display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: .5rem; padding: .75rem 1rem .5rem; }
.ar-dia__flecha {
    width: 2.75rem; height: 2.75rem; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 50%; border: 1px solid var(--bs-border-color); color: var(--bs-body-color); background: var(--bs-body-bg);
}
.ar-dia__flecha:hover, .ar-dia__flecha:focus-visible { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
.ar-dia__titulo { text-align: center; min-width: 0; }
.ar-dia__fecha { font-size: 1.25rem; font-weight: 700; line-height: 1.2; }
.ar-dia__rel { display: inline-block; margin-top: .2rem; font-size: .78rem; font-weight: 600; padding: .1rem .55rem; border-radius: 1rem;
               background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); text-decoration: none; }
.ar-dia__rel--hoy { background: var(--bs-primary); color: #fff; }
.ar-dia__rel--ir { color: var(--bs-link-color); }
.ar-semana { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .3rem; padding: 0 .75rem .75rem; }
.ar-semana__dia {
    display: flex; flex-direction: column; align-items: center; gap: .05rem; padding: .4rem .1rem .45rem;
    border-radius: .65rem; text-decoration: none; color: var(--bs-body-color); border: 1px solid transparent;
}
.ar-semana__dia:hover { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
.ar-semana__nombre { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); }
.ar-semana__num { font-size: 1.05rem; line-height: 1.2; }
.ar-semana__n {
    min-width: 1.4rem; padding: 0 .3rem; border-radius: 1rem; font-size: .72rem; font-weight: 700; text-align: center;
    background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis);
}
.ar-semana__n.is-cero { background: transparent; color: var(--bs-secondary-color); font-weight: 400; }
.ar-semana__n.is-saldo { box-shadow: inset 0 0 0 1px var(--bs-danger); }
.ar-semana__dia.is-hoy { border-color: var(--bs-primary-border-subtle); }
.ar-semana__dia.is-activo { background: var(--bs-primary); border-color: var(--bs-primary); color: #fff; }
.ar-semana__dia.is-activo .ar-semana__nombre { color: rgba(255,255,255,.85); }
.ar-semana__dia.is-activo .ar-semana__n { background: rgba(255,255,255,.25); color: #fff; }

/* ---- Filtros del día (plegables en el teléfono) ---- */
.ar-filtros { border-top: 1px solid var(--bs-border-color); }
.ar-filtros__abrir { display: none; list-style: none; cursor: pointer; padding: .7rem 1rem; font-weight: 600; font-size: .9rem; }
.ar-filtros__abrir::-webkit-details-marker { display: none; }
.ar-filtros__flecha { float: right; margin-top: .2rem; transition: transform .2s; color: var(--bs-secondary-color); }
.ar-filtros[open] .ar-filtros__flecha { transform: rotate(180deg); }
.ar-filtros__form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: .5rem .75rem; padding: .75rem 1rem; }
.ar-filtros__campo { flex: 1 1 9rem; min-width: 0; }
.ar-filtros__campo--ancho { flex-basis: 14rem; }
.ar-filtros__acciones { flex: 1 1 100%; display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; }

/* ---- Las cifras del día ---- */
.ar-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
.ar-kpi { border: 1px solid var(--bs-border-color); border-radius: .75rem; padding: .75rem .9rem; background: var(--bs-body-bg); min-width: 0; }
.ar-kpi__etq { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: var(--bs-secondary-color); }
.ar-kpi__cifra { display: block; font-size: 1.4rem; font-weight: 700; line-height: 1.2; margin-top: .15rem; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.ar-kpi__nota { font-size: .75rem; color: var(--bs-secondary-color); }
.ar-kpi--abonado .ar-kpi__cifra { color: var(--bs-success-text-emphasis); }
.ar-kpi__barra { display: block; height: .35rem; margin-top: .35rem; border-radius: 1rem; background: var(--bs-tertiary-bg); overflow: hidden; }
.ar-kpi__barra > span { display: block; height: 100%; background: var(--bs-success); border-radius: 1rem; }
.ar-kpi--saldo.is-deuda { border-color: var(--bs-danger-border-subtle); background: var(--bs-danger-bg-subtle); }
.ar-kpi--saldo.is-deuda .ar-kpi__cifra { color: var(--bs-danger-text-emphasis); }

/* ---- La hora, protagonista de cada reserva ---- */
.ar-tabla__hora { font-variant-numeric: tabular-nums; white-space: nowrap; }

/* ---- Encabezado de cada día en un rango ---- */
.ds-core .ar-tabla > tbody > tr.ar-tabla__dia > th {
    background: var(--bs-tertiary-bg); padding: .55rem .75rem; border-top: 2px solid var(--bs-border-color);
    font-weight: 400; text-transform: none; letter-spacing: 0;
}
.ar-tabla__dia-cab { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem 1.25rem; }
.ar-tabla__dia-fecha { font-weight: 700; color: var(--bs-body-color); text-decoration: none; }
.ar-tabla__dia-fecha:hover { text-decoration: underline; }
.ar-tabla__dia-cifras { display: flex; flex-wrap: wrap; gap: .2rem 1rem; font-size: .85rem; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; }

@media (max-width: 991.98px) {
    .ar-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    /* El encabezado del día, como una banda entre fichas. */
    .ar-tabla > tbody > tr.ar-tabla__dia { display: block; padding: .35rem .15rem .4rem; margin: .25rem 0 .5rem; border: 0; background: transparent; }
    .ds-core .ar-tabla > tbody > tr.ar-tabla__dia > th { display: block; padding: .6rem .7rem; border: 0; border-radius: .6rem; }
    /* La hora arriba y grande: en la agenda del día es lo que se busca. */
    .ar-tabla__hora { font-size: 1.05rem; }
}
@media (max-width: 767.98px) {
    /* Filtros plegados tras «Filtros»; abiertos, en columna. */
    .ar-filtros__abrir { display: block; }
    .ar-filtros__form { padding-top: 0; }
    .ar-filtros__campo, .ar-filtros__campo--ancho { flex-basis: 100%; }
    .ar-filtros .form-select, .ar-filtros .form-control { min-height: 2.75rem; font-size: 1rem; }
}
@media (max-width: 575.98px) {
    .ar-dia__barra { padding: .6rem .6rem .4rem; }
    .ar-dia__fecha { font-size: 1.05rem; }
    .ar-semana { padding: 0 .4rem .6rem; gap: .15rem; }
    .ar-semana__num { font-size: .95rem; }
    /* Dos cifras por fila; «Por cobrar», que es lo que se persigue, a lo ancho. */
    .ar-kpis { grid-template-columns: 1fr 1fr; gap: .5rem; }
    .ar-kpi { padding: .6rem .7rem; }
    .ar-kpi__cifra { font-size: 1.15rem; }
    .ar-kpi--saldo { grid-column: 1 / -1; }
    .ar-tabla__dia-cifras { font-size: .8rem; gap: .15rem .75rem; }
}
</style>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
