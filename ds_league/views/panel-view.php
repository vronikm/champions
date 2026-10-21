<?php
/*
| Panel de entrada de DigiSports League.
|
|
| QUÉ PREGUNTA CONTESTA ESTA PANTALLA
|
| Antes contestaba «¿quedó bien instalado el módulo?»: cinco tarjetas con
| los estados catalogados, el punto de emisión, los permisos y las filas de
| auditoría. Esa pregunta se hace UNA VEZ, el día del montaje, y desde
| entonces ocupaba la pantalla que todo el mundo abre al entrar.
|
| Peor todavía: el encabezado anunciaba «League está en fase 0 — todavía no
| hay temporadas, torneos ni equipos» sobre una base con dos torneos,
| diecinueve equipos, noventa partidos y ciento noventa y nueve jugadores.
| Una pantalla que se contradice con los datos que tiene delante no se
| corrige sola: se deja de mirar.
|
| Ahora contesta «¿qué pasa hoy en la competencia y qué necesita mi
| atención?». El diagnóstico de instalación no se tira —sigue siendo útil
| el día del montaje y cuando algo se desconfigura—, baja al final y va
| plegado.
|
|
| ORDEN DE LECTURA
|
|   1. Lo que hay que hacer          (requiere atención, sólo si lo hay)
|   2. Cuándo se vuelve a jugar      (próxima jornada)
|   3. Cómo va la competencia        (avance, equipos, jugadores, dinero)
|   4. El detalle                    (próximos partidos, últimos resultados)
|   5. La instalación                (plegado)
|
| Lo accionable va primero porque es lo único que cambia lo que el usuario
| hace a continuación. El resto es contexto.
|
|
| EL TELÉFONO ES EL CASO PRINCIPAL, NO LA EXCEPCIÓN
|
| Un torneo se dirige desde la cancha, no desde un escritorio. Por eso los
| partidos NO van en <table>: una tabla de seis columnas en 390 px obliga a
| arrastrar de lado para leer un marcador. Van como filas flexibles que se
| reordenan solas, con el marcador siempre visible.
*/

use league\controllers\leagueController;

$insLeague = new leagueController();

$tituloVista = 'Panel';
$vistaActual = 'panel';

$resumen   = $insLeague->resumenCompetencia();
$atencion  = $insLeague->requiereAtencion();
$jornada   = $insLeague->proximaJornada();
$proximos  = $insLeague->proximosPartidos(5);
$ultimos   = $insLeague->ultimosResultados(5);

/*
| EL DINERO SÓLO SE CONSULTA SI SE PUEDE VER
|
| No se trae y luego se oculta en la plantilla: si el rol no tiene permiso
| sobre la cobranza, la consulta no llega a ejecutarse. Ocultar en el HTML
| lo que ya se cargó deja la cifra en el código fuente de la página.
*/
$verDinero = usuario_tiene_permiso('cobranzaPanel');
$economia  = $verDinero ? $insLeague->economia() : null;

$h = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* Fecha corta y legible: «sáb 19 sep». En una tarjeta de teléfono no cabe
   «sábado 19 de septiembre de 2026», y 2026-09-19 no se lee de un vistazo. */
$fechaCorta = static function (?string $iso): string {
    if (!$iso) { return '—'; }
    $t = strtotime($iso);
    if ($t === false) { return '—'; }
    $dias  = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
    $meses = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun',
                  'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    return $dias[(int)date('w', $t)] . ' ' . (int)date('j', $t)
         . ' ' . $meses[(int)date('n', $t)];
};

/* Cuántos días faltan, dicho como lo diría una persona. */
$cuandoEs = static function (?string $iso): string {
    if (!$iso) { return ''; }
    $d = (int)floor((strtotime($iso . ' 00:00:00') - strtotime('today')) / 86400);
    if ($d < 0)  { return 'ya pasó'; }
    if ($d === 0) { return 'hoy'; }
    if ($d === 1) { return 'mañana'; }
    if ($d < 7)  { return 'en ' . $d . ' días'; }
    if ($d < 14) { return 'en una semana'; }
    return 'en ' . (int)round($d / 7) . ' semanas';
};

/* El tono del catálogo de estados traducido a una clase de Bootstrap. La
   correspondencia vive aquí y no repartida por la vista. */
$tono = static fn (?string $t) => [
    'exito' => 'success', 'aviso' => 'warning',
    'peligro' => 'danger', 'info' => 'info',
][$t ?? ''] ?? 'secondary';

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php
/*==================================================================
  1 · REQUIERE ATENCIÓN

  Sólo se pinta lo que de verdad está mal. Una tira de avisos que enseña
  cuatro ceros enseña a no mirarla, y el día que uno deje de ser cero ya
  nadie lo verá.
  ==================================================================*/
$avisos = [];

if ($atencion['vencidos'] > 0) {
    $avisos[] = ['n' => $atencion['vencidos'], 'color' => 'danger',
                 'icono' => 'fa-calendar-xmark',
                 'texto' => 'partidos con fecha pasada sin resolver',
                 'accion' => 'Reprogramar o cerrar',
                 'url' => APP_URL . 'torneoList/'];
}
if ($atencion['sinActa'] > 0) {
    $avisos[] = ['n' => $atencion['sinActa'], 'color' => 'warning',
                 'icono' => 'fa-clipboard-list',
                 'texto' => 'partidos jugados sin acta',
                 'accion' => 'Cargar estadísticas',
                 'url' => APP_URL . 'torneoList/'];
}
if ($atencion['noHabilitados'] > 0) {
    $avisos[] = ['n' => $atencion['noHabilitados'], 'color' => 'warning',
                 'icono' => 'fa-user-slash',
                 'texto' => 'jugadores no habilitados',
                 'accion' => 'Revisar requisitos',
                 'url' => APP_URL . 'equipoList/'];
}
if ($atencion['sinDorsal'] > 0) {
    /* «info» y no «secondary»: medido, text-secondary sobre el fondo del
       tema oscuro da 3.29 y AA pide 4.5. Es el aviso menos urgente de los
       cinco, pero eso se dice con el orden, no con un color ilegible. */
    $avisos[] = ['n' => $atencion['sinDorsal'], 'color' => 'info',
                 'icono' => 'fa-shirt',
                 'texto' => 'jugadores sin dorsal',
                 'accion' => 'Asignar dorsal',
                 'url' => APP_URL . 'equipoList/'];
}
if ($verDinero && $economia['nVencidas'] > 0) {
    $avisos[] = ['n' => $economia['nVencidas'], 'color' => 'danger',
                 'icono' => 'fa-file-invoice-dollar',
                 'texto' => 'cobros vencidos · $' . number_format($economia['vencido'], 2),
                 'accion' => 'Ir a cobranza',
                 'url' => APP_URL . 'cobranzaPanel/'];
}
?>

<?php if ($avisos): ?>
<div class="card mb-3 border-warning-subtle">
    <div class="card-header bg-warning-subtle">
        <h3 class="card-title mb-0 fw-bold">
            <i class="fas fa-triangle-exclamation me-2" aria-hidden="true"></i>REQUIERE ATENCIÓN
        </h3>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($avisos as $a): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <?php /* p-3 y h-100: el area pulsable ocupa toda la tarjeta,
                         que en un telefono es lo unico que se acierta con el dedo. */ ?>
                <a href="<?php echo $h($a['url']); ?>"
                   class="d-flex align-items-center gap-3 text-decoration-none p-3 rounded border h-100">
                    <i class="fas <?php echo $a['icono']; ?> fa-2x text-<?php echo $a['color']; ?>"
                       aria-hidden="true"></i>
                    <span class="min-w-0">
                        <span class="d-block fw-bold fs-4 lh-1 text-body">
                            <?php echo number_format($a['n'], 0, ',', '.'); ?>
                        </span>
                        <span class="d-block small text-body-secondary">
                            <?php echo $h($a['texto']); ?>
                        </span>
                        <span class="d-block small fw-semibold text-<?php echo $a['color']; ?>">
                            <?php echo $h($a['accion']); ?>
                            <i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
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
    <span>Nada pendiente: no hay partidos sin resolver, actas por cargar ni jugadores sin habilitar.</span>
</div>
<?php endif; ?>

<?php
/*==================================================================
  2 · LA PRÓXIMA JORNADA

  Lo primero que quiere saber quien dirige un torneo. Va en una franja
  propia porque es UN dato, y meterlo entre las métricas lo escondería.
  ==================================================================*/
?>
<?php if ($jornada): ?>
<div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
        <div class="d-flex align-items-center gap-3 flex-grow-1 min-w-0">
            <i class="fas fa-calendar-day fa-2x text-body-secondary" aria-hidden="true"></i>
            <div class="min-w-0">
                <div class="small text-body-secondary text-uppercase fw-semibold"
                     style="letter-spacing:.04em;">Próxima jornada</div>
                <?php /* text-capitalize va SOLO en la fecha. Aplicado a toda
                         la linea escribia «En Una Semana»: la clase pone
                         mayuscula en cada palabra, no en la primera. */ ?>
                <div class="fs-4 fw-bold lh-1">
                    <span class="text-capitalize"><?php echo $h($fechaCorta($jornada['fecha'])); ?></span>
                    <span class="fs-6 fw-normal text-body-secondary">
                        · <?php echo $h($cuandoEs($jornada['fecha'])); ?>
                    </span>
                </div>
                <div class="small text-body-secondary">
                    <?php echo (int)$jornada['partidos']; ?>
                    partido<?php echo (int)$jornada['partidos'] === 1 ? '' : 's'; ?>
                    <?php if (!empty($jornada['desde'])): ?>
                        · desde las <?php echo $h(substr((string)$jornada['desde'], 0, 5)); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
/*==================================================================
  3 · CÓMO VA LA COMPETENCIA

  col-6 en telefono y no col-12: cuatro tarjetas apiladas a pantalla
  completa son ochocientos pixeles de arrastre antes de llegar al
  contenido. De dos en dos caben en una pantalla.
  ==================================================================*/
$metricas = [
    ['valor' => $resumen['avance'] . '%',
     'etiqueta' => 'Fixture jugado',
     'detalle' => $resumen['jugados'] . ' de ' . $resumen['total'] . ' partidos',
     'clase' => 'text-bg-primary', 'icono' => 'fa-basketball',
     'url' => APP_URL . 'torneoList/', 'pie' => 'Ver torneos'],

    ['valor' => $resumen['porJugar'],
     'etiqueta' => 'Por jugar',
     'detalle' => $resumen['vencidos'] > 0
                  ? $resumen['vencidos'] . ' con fecha pasada'
                  : 'ninguno atrasado',
     'clase' => $resumen['vencidos'] > 0 ? 'text-bg-warning' : 'text-bg-secondary',
     'icono' => 'fa-hourglass-half',
     'url' => APP_URL . 'partidoAgenda/', 'pie' => 'Mis partidos'],

    ['valor' => $resumen['equipos'],
     'etiqueta' => 'Equipos habilitados',
     'detalle' => $resumen['torneos'] . ' torneo' . ($resumen['torneos'] === 1 ? '' : 's') . ' activo' . ($resumen['torneos'] === 1 ? '' : 's'),
     'clase' => 'text-bg-info', 'icono' => 'fa-users',
     'url' => APP_URL . 'equipoList/', 'pie' => 'Ver equipos'],

    ['valor' => $resumen['jugadores'],
     'etiqueta' => 'Jugadores habilitados',
     'detalle' => $atencion['noHabilitados'] > 0
                  ? $atencion['noHabilitados'] . ' sin habilitar'
                  : 'todos habilitados',
     'clase' => 'text-bg-success', 'icono' => 'fa-id-card',
     'url' => APP_URL . 'equipoList/', 'pie' => 'Ver plantillas'],
];

if ($verDinero) {
    $metricas[] = [
        'valor' => '$' . number_format($economia['cobrado'], 0, ',', '.'),
        'etiqueta' => 'Recaudado',
        'detalle' => $economia['avance'] . '% de $' . number_format($economia['emitido'], 0, ',', '.'),
        'clase' => 'text-bg-success', 'icono' => 'fa-hand-holding-dollar',
        'url' => APP_URL . 'cobranzaPanel/', 'pie' => 'Ver cobranza'];

    $metricas[] = [
        'valor' => '$' . number_format($economia['saldo'], 0, ',', '.'),
        'etiqueta' => 'Saldo por cobrar',
        'detalle' => $economia['nVencidas'] > 0
                     ? '$' . number_format($economia['vencido'], 0, ',', '.') . ' ya vencido'
                     : 'nada vencido',
        'clase' => $economia['vencido'] > 0 ? 'text-bg-danger' : 'text-bg-secondary',
        'icono' => 'fa-file-invoice-dollar',
        'url' => APP_URL . 'cobranzaPanel/', 'pie' => 'Ver cobranza'];
}
?>
<div class="row g-3 mb-1">
    <?php foreach ($metricas as $m): ?>
    <div class="col-6 col-lg-4 col-xxl-2">
        <div class="small-box <?php echo $m['clase']; ?> h-100">
            <div class="inner">
                <h3 class="text-truncate"><?php echo $h($m['valor']); ?></h3>
                <p class="mb-0"><?php echo $h($m['etiqueta']); ?></p>
                <p class="mb-0 small opacity-75"><?php echo $h($m['detalle']); ?></p>
            </div>
            <i class="fas <?php echo $m['icono']; ?> small-box-icon" aria-hidden="true"></i>
            <a href="<?php echo $h($m['url']); ?>"
               class="small-box-footer link-underline-opacity-0 link-underline-opacity-50-hover">
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
      4 · EL DETALLE

      Ni próximos ni últimos van en <table>. Cada partido es una fila
      flexible: los equipos ocupan el ancho que sobre y se recortan con
      puntos suspensivos, y el marcador o la hora se quedan fijos a la
      derecha. Así el mismo marcado sirve en 390 px y en 1400 sin que
      nadie tenga que arrastrar de lado.
      ==================================================================*/
    ?>
    <!-- ==================== Próximos partidos ==================== -->
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title mb-0">Próximos partidos</h3>
                <a href="<?php echo APP_URL; ?>partidoAgenda/" class="btn btn-sm btn-outline-secondary">
                    Agenda
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (!$proximos): ?>
                    <p class="text-body-secondary m-3 mb-0">No hay partidos programados por delante.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($proximos as $p): ?>
                    <li class="list-group-item d-flex align-items-center gap-3">
                        <div class="text-center flex-shrink-0" style="min-width:3.5rem;">
                            <div class="small fw-semibold text-capitalize">
                                <?php echo $h($fechaCorta($p['partido_fecha'])); ?>
                            </div>
                            <div class="small text-body-secondary">
                                <?php echo $h(substr((string)$p['partido_hora'], 0, 5)); ?>
                            </div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="text-truncate fw-semibold"><?php echo $h($p['local']); ?></div>
                            <div class="text-truncate text-body-secondary">
                                <span class="small">vs</span> <?php echo $h($p['visitante']); ?>
                            </div>
                            <?php if (!empty($p['cancha']) || !empty($p['categoria_nombre'])): ?>
                            <div class="small text-body-secondary text-truncate">
                                <?php echo $h(trim(($p['categoria_nombre'] ?? '')
                                        . (!empty($p['cancha']) ? ' · ' . $p['cancha'] : ''), ' ·')); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <span class="badge text-bg-<?php echo $tono($p['estado_tono']); ?> flex-shrink-0">
                            <?php echo $h($p['estado_nombre']); ?>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ==================== Últimos resultados ==================== -->
    <div class="col-lg-6 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Últimos resultados</h3>
            </div>
            <div class="card-body p-0">
                <?php if (!$ultimos): ?>
                    <p class="text-body-secondary m-3 mb-0">Todavía no hay partidos jugados.</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($ultimos as $p):
                        /* El ganador en negrita. Es la lectura que se hace de un
                           marcador, y ahorra comparar dos cifras mentalmente. */
                        $pl = $p['pl'] === null ? null : (int)$p['pl'];
                        $pv = $p['pv'] === null ? null : (int)$p['pv'];
                        $ganaL = $pl !== null && $pv !== null && $pl > $pv;
                        $ganaV = $pl !== null && $pv !== null && $pv > $pl;
                    ?>
                    <li class="list-group-item d-flex align-items-center gap-3">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex justify-content-between gap-2">
                                <span class="text-truncate <?php echo $ganaL ? 'fw-bold' : ''; ?>">
                                    <?php echo $h($p['local']); ?>
                                </span>
                                <span class="fw-bold flex-shrink-0 font-monospace">
                                    <?php echo $pl === null ? '–' : $pl; ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between gap-2">
                                <span class="text-truncate <?php echo $ganaV ? 'fw-bold' : ''; ?>">
                                    <?php echo $h($p['visitante']); ?>
                                </span>
                                <span class="fw-bold flex-shrink-0 font-monospace">
                                    <?php echo $pv === null ? '–' : $pv; ?>
                                </span>
                            </div>
                            <div class="small text-body-secondary">
                                <?php /* La mayuscula, solo en la fecha: el nombre del
                                         estado ya viene bien escrito del catalogo y
                                         text-capitalize lo volveria «En Juego». */ ?>
                                <span class="text-capitalize"><?php echo $h($fechaCorta($p['partido_fecha'])); ?></span>
                                <?php if ($p['estado_tono'] !== 'exito'): ?>
                                    · <?php echo $h($p['estado_nombre']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
/*==================================================================
  5 · LA INSTALACIÓN

  Sigue siendo útil —el día del montaje, y cuando alguien desconfigura el
  punto de emisión— pero no es lo que se viene a mirar todos los días.
  Va plegado, en un <details> nativo: sin JavaScript, accesible por
  teclado y con el estado de apertura que el navegador ya sabe manejar.
  ==================================================================*/
$diagnostico = $insLeague->diagnostico();
$problemas   = 0;
foreach ($diagnostico as $d) { if (!$d['ok']) { $problemas++; } }
?>
<div class="card mb-3">
    <details<?php echo $problemas > 0 ? ' open' : ''; ?>>
        <?php /* El <summary> es la cabecera pulsable. min-height 44px porque
                 es el objetivo tactil minimo que se acierta con el pulgar. */ ?>
        <summary class="card-header d-flex align-items-center gap-2"
                 style="cursor:pointer; min-height:44px;">
            <i class="fas fa-screwdriver-wrench text-body-secondary" aria-hidden="true"></i>
            <span class="fw-semibold">Estado de la instalación</span>
            <?php if ($problemas > 0): ?>
                <span class="badge text-bg-warning ms-auto">
                    <?php echo $problemas; ?> por revisar
                </span>
            <?php else: ?>
                <span class="badge text-bg-success ms-auto">Todo en orden</span>
            <?php endif; ?>
        </summary>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($diagnostico as $d): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="d-flex align-items-start gap-3 p-2">
                        <i class="fas <?php echo $d['ok'] ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning'; ?> fa-lg mt-1"
                           aria-hidden="true"></i>
                        <div class="min-w-0">
                            <div class="fw-semibold"><?php echo $h($d['etiqueta']); ?></div>
                            <div class="fs-5 lh-1 my-1"><?php echo $h($d['valor']); ?></div>
                            <div class="small text-body-secondary"><?php echo $h($d['detalle']); ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <p class="small text-body-secondary mb-0 mt-2">
                Los movimientos legales de cada entidad viven en
                <code>dsl_estado_transicion</code>: lo que no está ahí no se
                puede hacer, y no porque un controlador se acuerde de
                comprobarlo.
            </p>
        </div>
    </details>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
