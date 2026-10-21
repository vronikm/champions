<?php
/* Reservas con su estado de cobro. */

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Reservas';
$vistaActual = 'reservaList';

$instalaciones = $insArena->instalaciones();

/*
| UNA VENTANA POR DEFECTO, NO TODO EL HISTÓRICO
|
| Sin filtros la pantalla traía las 5.851 reservas y pesaba 18,7 MB. En un
| teléfono con datos móviles no es lenta: no carga.
|
| Al entrar sin filtros se propone el mes en curso —615 filas—, que es lo
| que se viene a mirar. La ventana NO se impone: aparece escrita en los
| campos de fecha, así que se ve, se cambia y se puede vaciar para buscar
| en todo el histórico.
|
| El indicador se mira en la URL y no en los valores ya resueltos: si se
| comprobara «desde está vacío», borrar la fecha a mano volvería a
| rellenarla sola y sería imposible ampliar la búsqueda.
*/
$sinFiltrar = !isset($_GET['instalacion']) && !isset($_GET['estado'])
           && !isset($_GET['desde']) && !isset($_GET['hasta'])
           && !isset($_GET['saldo']);

$filtros = [
    'instalacion' => (int)($_GET['instalacion'] ?? 0),
    'estado'      => (string)($_GET['estado'] ?? ''),
    'desde'       => $sinFiltrar ? date('Y-m-01') : (string)($_GET['desde'] ?? ''),
    'hasta'       => (string)($_GET['hasta'] ?? ''),
    'saldo'       => isset($_GET['saldo']) ? 1 : 0,
];

/*
| PAGINACIÓN DE VERDAD
|
| El primer arreglo fue un tope de 500 filas con un aviso de cuántas
| faltaban: honesto, pero dejaba fuera de alcance todo lo que hubiera
| detrás. Ahora se navega.
|
| La página se acota contra el total ANTES de consultar: pedir la 900 de 19
| devolvía una tabla vacía sin explicación, y una URL manipulada no debe
| poder producir una pantalla que parece rota.
*/
$totalFilas = $insArena->contarReservas($filtros);
$porPagina  = arena\controllers\arenaController::POR_PAGINA;
$totalPags  = max(1, (int)ceil($totalFilas / $porPagina));
$pagina     = max(1, min($totalPags, (int)($_GET['pag'] ?? 1)));

$reservas   = $insArena->reservas($filtros, $pagina, $porPagina);

/* Los recibos de las reservas de esta página, en una sola consulta. */
$recibosPorReserva = $insArena->pagosDeReservas(array_column($reservas, 'reserva_id'));

/* El enlace de cada página conserva los filtros: cambiar de página no
   puede cambiar lo que se está buscando. Se reconstruye desde $filtros y
   no desde $_GET para que la ventana por defecto viaje también. */
$urlPagina = static function (int $p) use ($filtros): string {
    $q = array_filter([
        'instalacion' => $filtros['instalacion'] ?: null,
        'estado'      => $filtros['estado'] ?: null,
        'desde'       => $filtros['desde'] ?: null,
        'hasta'       => $filtros['hasta'] ?: null,
        'saldo'       => $filtros['saldo'] ? 1 : null,
        'pag'         => $p > 1 ? $p : null,
    ], static fn ($v) => $v !== null);

    return APP_URL . 'reservaList/' . ($q ? '?' . http_build_query($q) : '');
};

/* El catálogo de estados vive en el controlador. Estas dos líneas eran una
   copia —había cinco repartidas por el módulo— y podían quedarse atrás al
   añadir o renombrar un estado. */
$catalogo = $insArena->estadosReserva();
$estados  = array_map(static fn ($e) => $e['nombre'], $catalogo);
$colores  = array_map(static fn ($e) => $e['tono'],   $catalogo);

$puedeCrear  = puede_crear('reservaList');
$puedeEditar = puede_editar('reservaList');

/* Los totales salen de la BASE y sobre el filtro entero, no de $reservas:
   al paginar, sumar el array daría el total de las cincuenta filas visibles
   bajo un rótulo que dice «Totales». */
$sumas        = $insArena->totalesReservas($filtros);
$totalGeneral = $sumas['total'];
$totalSaldo   = $sumas['saldo'];

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
        <h3 class="card-title mb-0">
            <?php echo count($reservas); ?> reserva<?php echo count($reservas) === 1 ? '' : 's'; ?>
            <?php if ($totalSaldo > 0): ?>
                <small class="text-danger ms-2">· $<?php echo number_format($totalSaldo, 2); ?> por cobrar</small>
            <?php endif; ?>
        </h3>

        <?php if ($puedeCrear && $instalaciones): ?>
            <a href="<?php echo APP_URL; ?>reservaForm/" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Nueva reserva
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body border-bottom">
        <form method="GET" action="<?php echo APP_URL; ?>reservaList/" class="row g-2 align-items-end">
            <div class="col-md-3 mb-2">
                <label class="mb-1 small">Instalación</label>
                <select name="instalacion" class="form-select form-select-sm">
                    <option value="0">Todas</option>
                    <?php foreach ($instalaciones as $i): ?>
                        <option value="<?php echo (int)$i['instalacion_id']; ?>"
                            <?php echo $filtros['instalacion'] === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 mb-2">
                <label class="mb-1 small">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $k => $v): ?>
                        <option value="<?php echo $k; ?>" <?php echo $filtros['estado'] === $k ? 'selected' : ''; ?>><?php echo $v; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-2 mb-2">
                <label class="mb-1 small">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($filtros['desde'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="col-md-2 mb-2">
                <label class="mb-1 small">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($filtros['hasta'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="col-md-3 mb-2 d-flex align-items-center" style="gap:12px;">
                <label class="mb-0 small">
                    <input type="checkbox" name="saldo" value="1" <?php echo $filtros['saldo'] ? 'checked' : ''; ?>>
                    Sólo con saldo
                </label>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Filtrar</button>
            </div>
        </form>
    </div>

    <?php
    /* Lo que se recorta se dice. Un listado que esconde filas en silencio
       hace que alguien concluya que una reserva no existe. */
    ?>
    <div class="card-body py-2 border-bottom small text-body-secondary d-flex flex-wrap gap-2 align-items-center">
        <span>
            <i class="fas fa-circle-info me-1" aria-hidden="true"></i>
            <?php if ($totalFilas === 0): ?>
                Ninguna reserva con este filtro.
            <?php else: ?>
                <?php /* Se dice qué tramo se está viendo del total: un listado
                         paginado sin ese dato obliga a contar páginas a mano. */ ?>
                <strong><?php echo number_format(($pagina - 1) * $porPagina + 1, 0, ',', '.'); ?></strong>–<strong><?php
                    echo number_format(min($pagina * $porPagina, $totalFilas), 0, ',', '.'); ?></strong>
                de <strong><?php echo number_format($totalFilas, 0, ',', '.'); ?></strong> reservas
            <?php endif; ?>
        </span>
        <?php if ($sinFiltrar): ?>
            <span class="ms-auto">
                <?php /* Lo que dice es lo que hace: el filtro pone «desde» y no
                         «hasta», así que entran también las reservas por venir. */ ?>
                Desde el <strong>1 de <?php
                    echo ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                          'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'][(int)date('n')];
                ?></strong> en adelante · vacíe «desde» para ver todo el histórico
            </span>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Cliente</th>
                        <th>Instalación</th>
                        <th>Cuándo</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Abonado</th>
                        <th class="text-end">Saldo</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$reservas): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay reservas que coincidan.</td></tr>
                <?php endif; ?>

                <?php foreach ($reservas as $r):
                    $saldo    = (float)$r['reserva_saldo'];
                    $abonado  = (float)$r['reserva_abonado'];
                    $total    = (float)$r['reserva_total'];
                    $progreso = $total > 0 ? round($abonado / $total * 100) : 0;
                    $cancelada = $r['reserva_estado'] === 'X';
                ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($r['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td>
                            <strong><?php echo htmlspecialchars($r['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small class="d-block text-muted"><?php echo htmlspecialchars($r['cliente_identificacion'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($r['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            <small class="d-block text-muted"><?php echo htmlspecialchars((string)$r['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </td>
                        <td>
                            <?php echo $r['reserva_fecha']; ?>
                            <small class="d-block text-muted">
                                <?php echo substr($r['reserva_horainicio'], 0, 5); ?>–<?php echo substr($r['reserva_horafin'], 0, 5); ?>
                                · <?php echo rtrim(rtrim(number_format((float)$r['reserva_horas'], 2), '0'), '.'); ?> h
                            </small>
                        </td>
                        <td class="text-end">$<?php echo number_format($total, 2); ?></td>
                        <td class="text-end">
                            <span class="text-success">$<?php echo number_format($abonado, 2); ?></span>
                            <?php if ($total > 0): ?>
                                <div class="progress mt-1" style="height:4px;">
                                    <div class="progress-bar bg-success" style="width:<?php echo $progreso; ?>%"></div>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($cancelada): ?>
                                <span class="text-body-secondary small" title="Una reserva cancelada no se cobra">No se cobra</span>
                            <?php elseif ($saldo > 0): ?>
                                <strong class="text-danger">$<?php echo number_format($saldo, 2); ?></strong>
                            <?php else: ?>
                                <span class="badge text-bg-success">Pagada</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?php echo $colores[$r['reserva_estado']] ?? 'secondary'; ?>">
                                <?php echo $estados[$r['reserva_estado']] ?? $r['reserva_estado']; ?>
                            </span>
                        </td>
                        <td class="ds-tabla-acciones">
                            <?php if ($cancelada): ?>
                                <?php /* Cancelada: no se cobra. Se puede ver, y reactivar si fue un error. */ ?>
                                <a href="<?php echo APP_URL; ?>reservaDetalle/?id=<?php echo (int)$r['reserva_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Ver detalle">
                                    <i class="fas fa-eye"></i>
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

                                <?php if ($abonado > 0): ?>
                                    <?php /* El servidor no deja cancelar con abonos: no se ofrece un botón que va a fallar. */ ?>
                                    <span class="d-inline-block" tabindex="0"
                                          title="Tiene $<?php echo number_format($abonado, 2); ?> abonados: devuélvalos o páselos al monedero antes de cancelar">
                                        <button type="button" class="btn btn-sm btn-outline-danger" disabled aria-label="Cancelar (no disponible)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </span>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-estado"
                                            data-id="<?php echo (int)$r['reserva_id']; ?>"
                                            data-codigo="<?php echo htmlspecialchars($r['reserva_codigo'], ENT_QUOTES, 'UTF-8'); ?>"
                                            data-estado="X" title="Cancelar">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
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

                <?php if ($reservas): ?>
                    <tfoot>
                        <tr style="background:var(--core-suave);font-weight:700;">
                            <?php /* Se dice de qué son: con la tabla paginada, un
                                     «Totales» a secas se lee como el de la página. */ ?>
                            <td colspan="4" class="text-end">
                                Totales del filtro
                                <span class="fw-normal text-body-secondary">(<?php
                                    echo number_format($totalFilas, 0, ',', '.'); ?> reservas)</span>
                            </td>
                            <td class="text-end">$<?php echo number_format($totalGeneral, 2); ?></td>
                            <td class="text-end text-success">$<?php echo number_format($totalGeneral - $totalSaldo, 2); ?></td>
                            <td class="text-end text-danger">$<?php echo number_format($totalSaldo, 2); ?></td>
                            <td colspan="2"></td>
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
    /* Cada cambio dice qué va a pasar, con el código de la reserva. */
    var codigo = b.dataset.codigo ? ' ' + b.dataset.codigo : '';
    var avisos = {
        X: 'La reserva' + codigo + ' quedará cancelada y la franja se liberará.',
        C: '¿Confirmar la reserva' + codigo + '?',
        P: 'La reserva' + codigo + ' volverá a estar activa como Pendiente. ' +
           'Antes se comprobará que el horario siga libre.'
    };
    f.setAttribute('data-confirmar', avisos[b.dataset.estado] || '¿Cambiar el estado de esta reserva?');
    f.requestSubmit();
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
