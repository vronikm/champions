<?php
/*
| Panel de entrada de DigiSports Arena.
|
|
| QUÉ CONTESTABA ANTES Y QUÉ CONTESTA AHORA
|
| Las cuatro tarjetas de arriba contaban INVENTARIO: cuántas canchas hay,
| cuántas residencias, cuántos clientes. Son cifras que no se mueven en
| meses. Un recinto de alquiler se dirige con otra pregunta: «¿cómo va el
| día de hoy, qué cancha está parada y qué he dejado sin cerrar?».
|
| Con 6.006 reservas y 4.726 pagos en la base, el panel tenía delante todo
| lo necesario para contestarla y no lo usaba.
|
|
| EL DINERO YA NO SE ENSEÑA A CUALQUIERA
|
| La tabla de saldos pendientes se pintaba sin comprobar ningún permiso:
| quien pudiera abrir el panel veía importes. Ahora la consulta ni siquiera
| se ejecuta sin permiso sobre reservaList, que es la pantalla donde ese
| dato vive.
|
|
| ORDEN DE LECTURA
|
|   1. Lo que hay que hacer      (sólo si lo hay)
|   2. Cómo va el día            (ocupación, que es LA cifra de un recinto)
|   3. El pulso                  (reservas, semana, clientes, dinero)
|   4. La agenda de hoy          (lo que mira quien atiende el mostrador)
|   5. Qué cancha está parada    (donde se pierde dinero)
|   6. Saldos y configuración
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

/* «Dashboard», como en Fútbol (2026-10-30). La dirección sigue siendo
   panel/: la usan el Hub, los marcadores y el menú. */
$tituloVista = 'Dashboard';
$vistaActual = 'panel';

/* Enlaces a Reservas con el filtro de lo que se está contando: sin él la
   lista abre en el mes en curso y no se ve lo que el aviso señala. */
$reservas = static fn (array $f) => APP_URL . 'reservaList/?' . http_build_query($f);
$hoyIso   = date('Y-m-d');

$ocupacion = $insArena->ocupacionHoy();
$atencion  = $insArena->requiereAtencion();
$pulso     = $insArena->pulso();
$agenda    = $insArena->agendaHoy(10);
$porInst   = $insArena->ocupacionPorInstalacion();
$sedes     = $insArena->sedesAlquiler();

/* El dinero, sólo si el rol puede verlo. No se trae y se oculta: lo que se
   carga acaba en el código fuente de la página aunque no se pinte. */
$verDinero = usuario_tiene_permiso('reservaList');
$economia  = $verDinero ? $insArena->economiaMes() : null;
$saldos    = $verDinero ? $insArena->saldosPendientes() : [];

$h = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* Del catálogo del controlador. Escribirlos aquí habría sido la sexta copia
   de la misma verdad en el módulo. */
$catalogo = $insArena->estadosReserva();
$estados  = array_map(static fn ($e) => $e['nombre'], $catalogo);
$tonos    = array_map(static fn ($e) => $e['tono'],   $catalogo);

$hora = static fn ($t) => substr((string)$t, 0, 5);

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php
/*==================================================================
  1 · REQUIERE ATENCIÓN

  Sólo se pinta lo que está mal. Una tira que enseña tres ceros enseña a
  no mirarla, y el día que uno deje de ser cero ya nadie lo verá.

  2026-10-30: fuera «reservas pasadas sin cerrar». Pedía marcar una a una
  como cumplida cada reserva ya jugada; una reserva cuya fecha pasó ya está
  jugada, y si se debe algo lo dice el aviso de saldos. Cada aviso abre
  Reservas con el filtro de lo que cuenta.
  ==================================================================*/
$avisos = [];

if ($atencion['sinConfirmar'] > 0) {
    $avisos[] = ['n' => $atencion['sinConfirmar'], 'color' => 'warning',
                 'icono' => 'fa-clock',
                 'texto' => 'reservas de hoy sin confirmar',
                 'accion' => 'Confirmar',
                 'url' => $reservas(['estado' => 'P', 'desde' => $hoyIso, 'hasta' => $hoyIso])];
}
if ($atencion['sinHorario'] > 0) {
    $avisos[] = ['n' => $atencion['sinHorario'], 'color' => 'info',
                 'icono' => 'fa-calendar-day',
                 'texto' => 'instalaciones activas sin horario',
                 'accion' => 'Asignar horario',
                 'url' => APP_URL . 'horarioList/'];
}
if ($verDinero && $economia['conSaldo'] > 0) {
    $avisos[] = ['n' => $economia['conSaldo'], 'color' => 'danger',
                 'icono' => 'fa-file-invoice-dollar',
                 /* saldoTotal, no saldo: el recuento es de TODAS las reservas
                    pendientes, así que el importe tiene que ser el de todas. */
                 'texto' => 'reservas con saldo · $' . number_format($economia['saldoTotal'], 2),
                 'accion' => 'Cobrar',
                 /* desde vacío: todas, no sólo las del mes. */
                 'url' => $reservas(['saldo' => 1, 'desde' => ''])];
}
?>

<div class="ar-dash">
<?php if ($avisos): ?>
<div class="card mb-3 ar-dash__atencion">
    <div class="card-header">
        <h3 class="card-title mb-0 fw-bold">
            <i class="fas fa-triangle-exclamation text-danger me-2" aria-hidden="true"></i>REQUIERE ATENCIÓN
        </h3>
    </div>
    <div class="card-body">
        <div class="row g-2 g-md-3">
            <?php foreach ($avisos as $a): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?php echo $h($a['url']); ?>" class="ar-dash__aviso text-decoration-none rounded border h-100">
                    <i class="fas <?php echo $a['icono']; ?> ar-dash__aviso-icono text-<?php echo $a['color']; ?>" aria-hidden="true"></i>
                    <span class="ar-dash__aviso-cuerpo">
                        <span class="ar-dash__aviso-n text-body"><?php echo number_format($a['n'], 0, ',', '.'); ?></span>
                        <span class="ar-dash__aviso-texto text-body-secondary"><?php echo $h($a['texto']); ?></span>
                        <span class="ar-dash__aviso-accion fw-semibold text-<?php echo $a['color']; ?>">
                            <?php echo $h($a['accion']); ?><i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
                        </span>
                    </span>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-success d-flex align-items-center gap-2 mb-3" role="status">
    <i class="fas fa-circle-check" aria-hidden="true"></i>
    <span>Nada pendiente: reservas de hoy confirmadas, instalaciones con horario<?php echo $verDinero ? ' y sin saldos por cobrar' : ''; ?>.</span>
</div>
<?php endif; ?>

<?php
/*==================================================================
  2 · LA OCUPACIÓN DE HOY

  Es LA cifra de un recinto de alquiler: decide precios, promociones y si
  sobra o falta cancha. No estaba en ninguna pantalla.

  Va en franja propia y con barra porque un porcentaje suelto no dice si
  es bueno; junto a «30 de 84 horas» sí.
  ==================================================================*/
$pc = (int)$ocupacion['porcentaje'];
/* El color dice salud de negocio, no estética: por debajo de 30 la cancha
   está parada, por encima de 75 se está llenando. */
$tonoOcup = $pc >= 75 ? 'success' : ($pc >= 30 ? 'info' : 'warning');
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
            <i class="fas fa-chart-simple fa-2x text-body-secondary" aria-hidden="true"></i>
            <div class="min-w-0 flex-grow-1">
                <div class="small text-body-secondary text-uppercase fw-semibold"
                     style="letter-spacing:.04em;">Ocupación de hoy</div>
                <div class="fs-3 fw-bold lh-1">
                    <?php echo $pc; ?>%
                    <span class="fs-6 fw-normal text-body-secondary">
                        · <?php echo rtrim(rtrim(number_format((float)$ocupacion['reservadas'], 1, '.', ''), '0'), '.'); ?>
                        de <?php echo rtrim(rtrim(number_format((float)$ocupacion['disponibles'], 1, '.', ''), '0'), '.'); ?> horas
                    </span>
                </div>
            </div>
        </div>
        <div class="progress" role="progressbar" style="height:.75rem;"
             aria-valuenow="<?php echo $pc; ?>" aria-valuemin="0" aria-valuemax="100"
             aria-label="Ocupación de hoy">
            <div class="progress-bar text-bg-<?php echo $tonoOcup; ?>"
                 style="width:<?php echo max(1, min(100, $pc)); ?>%;"></div>
        </div>
    </div>
</div>

<?php
/*==================================================================
  3 · EL PULSO

  col-6 en teléfono: cuatro tarjetas a pantalla completa son ochocientos
  píxeles de arrastre antes de llegar al contenido.
  ==================================================================*/
$metricas = [
    ['valor' => $pulso['hoy'], 'etiqueta' => 'Reservas hoy',
     'detalle' => $atencion['sinConfirmar'] > 0
                  ? $atencion['sinConfirmar'] . ' sin confirmar' : 'todas confirmadas',
     'clase' => $atencion['sinConfirmar'] > 0 ? 'text-bg-warning' : 'text-bg-success',
     'icono' => 'fa-calendar-check', 'url' => $reservas(['desde' => $hoyIso, 'hasta' => $hoyIso]), 'pie' => 'Ver reservas'],

    ['valor' => $pulso['semana'], 'etiqueta' => 'Próximos 7 días',
     'detalle' => 'reservas por venir',
     'clase' => 'text-bg-primary', 'icono' => 'fa-calendar-days',
     'url' => $reservas(['desde' => date('Y-m-d', strtotime('+1 day')), 'hasta' => date('Y-m-d', strtotime('+7 days'))]), 'pie' => 'Ver agenda'],

    ['valor' => count($porInst), 'etiqueta' => 'Instalaciones activas',
     'detalle' => $atencion['sinHorario'] > 0
                  ? $atencion['sinHorario'] . ' sin horario' : 'todas con horario',
     'clase' => $atencion['sinHorario'] > 0 ? 'text-bg-warning' : 'text-bg-info',
     'icono' => 'fa-location-dot', 'url' => APP_URL . 'instalacionList/', 'pie' => 'Ver instalaciones'],

    ['valor' => $pulso['clientes'], 'etiqueta' => 'Clientes activos',
     'detalle' => 'con ficha vigente',
     'clase' => 'text-bg-secondary', 'icono' => 'fa-user-group',
     'url' => APP_URL . 'clienteList/', 'pie' => 'Ver clientes'],
];

if ($verDinero) {
    $metricas[] = ['valor' => '$' . number_format($economia['cobrado'], 0, ',', '.'),
        'etiqueta' => 'Cobrado del mes',
        'detalle' => $economia['avance'] . '% de $' . number_format($economia['facturado'], 0, ',', '.'),
        'clase' => 'text-bg-success', 'icono' => 'fa-hand-holding-dollar',
        'url' => APP_URL . 'reservaList/', 'pie' => 'Ver reservas'];

    $metricas[] = ['valor' => '$' . number_format($economia['saldo'], 0, ',', '.'),
        'etiqueta' => 'Saldo del mes',
        /* La tarjeta habla del MES, así que su recuento también. */
        'detalle' => $economia['conSaldoMes'] . ' reservas del mes con saldo',
        'clase' => $economia['saldo'] > 0 ? 'text-bg-danger' : 'text-bg-secondary',
        'icono' => 'fa-file-invoice-dollar',
        'url' => $reservas(['saldo' => 1, 'desde' => date('Y-m-01'), 'hasta' => date('Y-m-t')]), 'pie' => 'Ver reservas'];
}
?>
<?php /* mb-0 en la tarjeta: con su margen de AdminLTE más el hueco de la
         fila, entre una fila y otra quedaba el doble de aire. */ ?>
<div class="row g-3 mb-3 ar-dash__cifras">
    <?php foreach ($metricas as $m):
        /* Sobre amarillo y cian el texto que contrasta es el oscuro; sobre
           el resto, el claro. Sin la clase el pie salía subrayado en azul. */
        $enlacePie = in_array($m['clase'], ['text-bg-warning', 'text-bg-info'], true) ? 'link-dark' : 'link-light';
    ?>
    <div class="col-6 col-lg-4 col-xxl-2">
        <div class="small-box <?php echo $m['clase']; ?> h-100 mb-0">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $h($m['valor']); ?></h3>
                <p class="mb-0"><?php echo $h($m['etiqueta']); ?></p>
                <p class="mb-0 small opacity-75"><?php echo $h($m['detalle']); ?></p>
            </div>
            <i class="fas <?php echo $m['icono']; ?> small-box-icon" aria-hidden="true"></i>
            <a href="<?php echo $h($m['url']); ?>"
               class="small-box-footer <?php echo $enlacePie; ?> link-underline-opacity-0 link-underline-opacity-50-hover">
                <?php echo $h($m['pie']); ?>
                <i class="fas fa-arrow-circle-right ms-1" aria-hidden="true"></i>
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row">
    <?php
    /*==================================================================
      4 · LA AGENDA DE HOY

      Lo que mira quien atiende el mostrador. Hasta ahora había que ir a
      Reservas y filtrar a mano por la fecha de hoy.

      No va en <table>: seis columnas en 390 px obligan a arrastrar de
      lado. Cada reserva es una fila flexible con la hora fija a la
      izquierda y el nombre recortándose con puntos suspensivos.
      ==================================================================*/
    ?>
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0">Agenda de hoy</h3>
                <a href="<?php echo $h($reservas(['desde' => $hoyIso, 'hasta' => $hoyIso])); ?>" class="btn btn-sm btn-outline-secondary">
                    Reservas
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!$agenda): ?>
                    <p class="text-body-secondary m-3 mb-0">Hoy no hay reservas.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($agenda as $r): ?>
                    <li class="list-group-item d-flex align-items-center gap-3">
                        <div class="text-center flex-shrink-0 font-monospace" style="min-width:3.5rem;">
                            <div class="fw-semibold"><?php echo $h($hora($r['reserva_horainicio'])); ?></div>
                            <div class="small text-body-secondary"><?php echo $h($hora($r['reserva_horafin'])); ?></div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate fw-semibold"><?php echo $h($r['cliente_nombre']); ?></div>
                            <div class="text-truncate small text-body-secondary">
                                <?php echo $h($r['instalacion_codigo'] . ' · ' . $r['instalacion_nombre']); ?>
                            </div>
                        </div>
                        <div class="flex-shrink-0 text-end">
                            <span class="badge text-bg-<?php echo $tonos[$r['reserva_estado']] ?? 'secondary'; ?>">
                                <?php echo $h($estados[$r['reserva_estado']] ?? $r['reserva_estado']); ?>
                            </span>
                            <?php if ($verDinero && (float)$r['reserva_saldo'] > 0): ?>
                                <div class="small text-danger fw-semibold">
                                    $<?php echo number_format((float)$r['reserva_saldo'], 2); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php
    /*==================================================================
      5 · QUÉ CANCHA ESTÁ PARADA

      Donde se pierde dinero. La barra permite comparar de un vistazo sin
      leer seis cifras.
      ==================================================================*/
    ?>
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0">Ocupación por instalación</h3>
                <a href="<?php echo APP_URL; ?>horarioList/" class="btn btn-sm btn-outline-secondary">
                    Horarios
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!$porInst): ?>
                    <p class="text-body-secondary m-3 mb-0">No hay instalaciones activas.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($porInst as $i):
                        $disp = (float)$i['disponibles'];
                        $res  = (float)$i['reservadas'];
                        $p    = $disp > 0 ? (int)round($res * 100 / $disp) : 0;
                        $t    = $disp <= 0 ? 'secondary'
                                : ($p >= 75 ? 'success' : ($p >= 30 ? 'info' : 'warning'));
                    ?>
                    <li class="list-group-item">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="fas <?php echo $i['instalacion_clase'] === 'R' ? 'fa-bed' : 'fa-basketball'; ?> text-body-secondary"
                               aria-hidden="true"></i>
                            <span class="text-truncate flex-grow-1 min-w-0">
                                <?php echo $h($i['instalacion_nombre']); ?>
                            </span>
                            <span class="small text-body-secondary flex-shrink-0 font-monospace">
                                <?php if ($disp <= 0): ?>
                                    sin horario hoy
                                <?php else: ?>
                                    <?php echo $p; ?>%
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if ($disp > 0): ?>
                        <div class="progress" role="progressbar" style="height:.4rem;"
                             aria-valuenow="<?php echo $p; ?>" aria-valuemin="0" aria-valuemax="100"
                             aria-label="Ocupación de <?php echo $h($i['instalacion_nombre']); ?>">
                            <div class="progress-bar text-bg-<?php echo $t; ?>"
                                 style="width:<?php echo max(1, min(100, $p)); ?>%;"></div>
                        </div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <?php
    /*==================================================================
      6 · SALDOS Y CONFIGURACIÓN

      La tabla de saldos se conserva —es útil— pero ahora detrás del
      permiso, y sin <table> para que quepa en un teléfono.
      ==================================================================*/
    ?>
    <?php if ($verDinero): ?>
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0">Reservas con saldo pendiente</h3>
                <a href="<?php echo $h($reservas(['saldo' => 1, 'desde' => ''])); ?>" class="btn btn-sm btn-outline-secondary">
                    Ver todas
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!$saldos): ?>
                    <p class="text-body-secondary m-3 mb-0">No hay abonos pendientes.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($saldos as $s): ?>
                    <li class="list-group-item d-flex align-items-center gap-3">
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate fw-semibold"><?php echo $h($s['cliente_nombre']); ?></div>
                            <div class="small text-body-secondary text-truncate">
                                <code><?php echo $h($s['reserva_codigo']); ?></code>
                                · <?php echo $h(date('d/m/Y', strtotime((string)$s['reserva_fecha']))); ?>
                            </div>
                        </div>
                        <div class="text-end flex-shrink-0 font-monospace">
                            <div class="fw-bold text-danger">
                                $<?php echo number_format((float)$s['reserva_saldo'], 2); ?>
                            </div>
                            <div class="small text-body-secondary">
                                de $<?php echo number_format((float)$s['reserva_total'], 2); ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-lg-<?php echo $verDinero ? '5' : '12'; ?> mb-3">
        <div class="card h-100">
            <div class="card-header"><h3 class="card-title mb-0">Sedes con alquiler</h3></div>
            <div class="card-body">
                <?php if (!$sedes): ?>
                    <div class="aviso-superadmin">
                        <i class="fas fa-info-circle fa-lg mt-1"></i>
                        <div>
                            <strong>Ninguna sede ofrece alquiler todavía.</strong><br>
                            Marque las sedes como <em>Alquiler</em> o <em>Formativa y Alquiler</em>
                            para que Arena pueda administrarlas.
                        </div>
                    </div>
                <?php else: ?>
                    <ul class="ds-list">
                        <?php foreach ($sedes as $s): ?>
                            <li>
                                <span class="ds-dot ds-dot--success"></span>
                                <span>
                                    <span class="ds-item__title"><?php echo $h($s['sede_nombre']); ?></span>
                                    <span class="ds-item__meta">
                                        <?php echo $s['sede_tipoingreso'] === 'STA' ? 'Sólo alquiler' : 'Formativa y alquiler'; ?>
                                    </span>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- /.ar-dash -->

<style>
/* Dashboard de Arena (2026-10-30). En el escritorio, como estaba; en el
   teléfono, más compacto: avisos en una línea, cifras de dos en dos y más
   bajas. Los mismos criterios que el Dashboard de Fútbol. */
/* Las listas llegan al borde de la tarjeta: sin recortar, la última fila
   pintaba sus esquinas rectas encima del borde redondeado. */
.ar-dash .card { overflow: hidden; }
.ar-dash__atencion { border-left: 4px solid var(--bs-danger); }
.ar-dash__aviso { display: flex; align-items: center; gap: 1rem; padding: 1rem; }
.ar-dash__aviso:hover { background: var(--bs-tertiary-bg); }
.ar-dash__aviso-icono { font-size: 2rem; width: 2.25rem; text-align: center; flex: none; }
.ar-dash__aviso-cuerpo { display: flex; flex-direction: column; min-width: 0; }
.ar-dash__aviso-n { font-size: 1.5rem; font-weight: 700; line-height: 1; font-variant-numeric: tabular-nums; }
.ar-dash__aviso-texto, .ar-dash__aviso-accion { font-size: .875rem; }

@media (max-width: 575.98px) {
    .ar-dash .card-body { padding: .75rem; }
    .ar-dash__aviso { gap: .75rem; padding: .6rem .75rem; }
    .ar-dash__aviso-icono { font-size: 1.35rem; width: 1.75rem; }
    .ar-dash__aviso-cuerpo { flex-direction: row; flex-wrap: wrap; align-items: baseline; column-gap: .35rem; flex: 1; }
    .ar-dash__aviso-n { font-size: 1.2rem; }
    .ar-dash__aviso-texto { font-size: .82rem; line-height: 1.25; }
    .ar-dash__aviso-accion { flex-basis: 100%; font-size: .8rem; margin-top: .1rem; }

    .ar-dash__cifras { --bs-gutter-x: .6rem; --bs-gutter-y: .6rem; }
    .ar-dash__cifras .small-box > .inner { padding: .5rem .5rem .4rem; }
    .ar-dash__cifras .small-box h3 { font-size: 1.5rem; margin-bottom: .15rem; }
    .ar-dash__cifras .small-box p { font-size: .78rem; line-height: 1.2; }
    .ar-dash__cifras .small-box > .small-box-footer { font-size: .8rem; padding: .3rem 0; }
}
</style>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
