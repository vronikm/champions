<?php
/*
| Acta de un partido.
|
| Sólo aparecen los jugadores HABILITADOS: ofrecer a quien no podía jugar
| es justo lo que se impugna después, y una pantalla que lo permite acaba
| produciendo actas que hay que anular.
|
| Los puntos, los rebotes totales y la valoración NO se teclean. Salen de
| los tiros y de los rebotes por zona, y se recalculan al guardar. Pedirlos
| aparte permitiría que el acta se contradijera a sí misma sin que nadie lo
| notara hasta el final de la temporada.
|
| Se guarda jugador a jugador y no el acta entera de golpe: en una mesa de
| control se va anotando sobre la marcha, y perder veinte filas porque una
| tenía un tiro anotado de más sería inaceptable.
*/

use league\controllers\estadisticaController;

$insLeague = new estadisticaController();

$partidoId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);
$partido   = $insLeague->partidoConContexto($partidoId);

if (!$partido) {
    $tituloVista = 'Acta';
    $vistaActual = 'actaPartido';
    require_once __DIR__ . "/inc/layout-top.php";
    echo '<div class="callout callout-warning"><h6 class="mb-1">'
       . '<i class="fas fa-exclamation-circle me-2"></i>Partido no encontrado</h6>'
       . '<p class="mb-0 text-muted">Ábralo desde el calendario de su categoría.</p></div>';
    require_once __DIR__ . "/inc/layout-bottom.php";
    return;
}

$tituloVista = $partido['local'] . ' vs ' . $partido['visitante'];
$vistaActual = 'actaPartido';

$deporte  = $partido['torneo_deporte'] ?: 'baloncesto';
$captura  = $insLeague->tipos($deporte, true);
$todos    = $insLeague->tiposPorCodigo($deporte);
$acta     = $insLeague->acta($partidoId, $deporte);
$totales  = $insLeague->totalesActa($acta, $todos);

/* Las derivadas, para la columna de sólo lectura. */
$derivadas = [];
foreach ($todos as $codigo => $t) {
    if ($t['tipo_captura'] === 'N') { $derivadas[$codigo] = $t; }
}

$equipos = [
    ['ins' => (int)$partido['ins_local'],     'nombre' => $partido['local'],     'lado' => 'Local'],
    ['ins' => (int)$partido['ins_visitante'], 'nombre' => $partido['visitante'], 'lado' => 'Visitante'],
];

/*
| LA PANTALLA Y EL SERVIDOR LEEN LO MISMO
|
| Aqui se comparaba contra el literal 'CANCELADO', y el servidor no
| comprobaba el estado en absoluto: ocultar los campos no impedia nada,
| porque el acta se guarda por fetch y bastaba repetir la peticion.
|
| Ahora la regla es un dato del catalogo —dsl_estado.estado_admiteacta,
| migracion 064— y guardarActa() la aplica de verdad. Esta linea sigue
| existiendo para no ofrecer lo que el servidor va a rechazar.
*/
$puedeEditar = puede_editar('actaPartido') && ($partido['estado_admiteacta'] ?? 'S') === 'S';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<p class="text-muted mb-3">
    <a href="<?php echo APP_URL; ?>categoriaPanel/<?php echo (int)$partido['categoria_id']; ?>/"
       class="ds-link">← <?php echo $h($partido['categoria_nombre']); ?></a>
    <span class="mx-2">·</span><?php echo $h($partido['torneo_nombre']); ?>
    <?php if ($partido['partido_fecha']): ?>
        <span class="mx-2">·</span><?php echo $h($partido['partido_fecha']); ?>
        <?php echo $h(substr((string)$partido['partido_hora'], 0, 5)); ?>
    <?php endif; ?>
</p>

<!-- ==================== Marcador ==================== -->
<div class="card mb-3">
    <div class="card-body py-3" style="background:var(--bs-tertiary-bg);border-bottom:1px solid var(--bs-border-color);">
        <div class="d-flex align-items-center justify-content-center flex-wrap" style="gap:1.5rem;">
            <span style="font-size:1.1rem;"><?php echo $h($partido['local']); ?></span>
            <strong style="font-size:1.7rem;font-variant-numeric:tabular-nums;">
                <?php echo $partido['partido_puntoslocal'] !== null
                        ? (int)$partido['partido_puntoslocal'] . ' – ' . (int)$partido['partido_puntosvisitante']
                        : '– – –'; ?>
            </strong>
            <span style="font-size:1.1rem;"><?php echo $h($partido['visitante']); ?></span>
            <span class="badge text-bg-<?php
                echo ['exito'=>'success','aviso'=>'warning','peligro'=>'danger',
                      'info'=>'info','neutro'=>'secondary'][$partido['estado_tono']] ?? 'secondary'; ?>">
                <?php echo $h($partido['estado_nombre']); ?>
            </span>
        </div>
        <?php if (!empty($totales)): ?>
        <p class="text-center text-muted mb-0 mt-2" style="font-size:.85rem;">
            Puntos del acta:
            <?php
            /* Cada equipo en su propio <span> con data-puntos-equipo: así el
               guardado automático repinta sólo el número que cambió, en vez
               de rehacer la frase entera. */
            $partes = [];
            foreach ($totales as $eq => $t) {
                $partes[] = $h($eq) . ' <span data-puntos-equipo="' . $h($eq) . '">'
                          . (int)($t['PTS'] ?? 0) . '</span>';
            }
            echo implode(' · ', $partes);
            ?>
            <?php /* Si el acta y el marcador no coinciden, se dice. Un acta
                     que suma distinto al resultado publicado es un error que
                     hay que ver, no uno que se descubra al reclamar. */ ?>
            <?php
            $sumaLocal = (int)($totales[$partido['local']]['PTS'] ?? 0);
            $sumaVisit = (int)($totales[$partido['visitante']]['PTS'] ?? 0);
            $hayActa   = $sumaLocal + $sumaVisit > 0;
            $marcador  = $partido['partido_puntoslocal'] !== null;
            if ($hayActa && $marcador
                && ($sumaLocal !== (int)$partido['partido_puntoslocal']
                 || $sumaVisit !== (int)$partido['partido_puntosvisitante'])): ?>
                <br><span class="text-danger">
                    <i class="fas fa-exclamation-triangle me-1"></i>
                    No coincide con el marcador registrado. Revise el acta.
                </span>
            <?php endif; ?>
        </p>
        <?php endif; ?>
    </div>
</div>

<?php if (!$puedeEditar): ?>
<div class="callout callout-warning">
    <p class="mb-0 text-muted">
        <?php echo $partido['estado_codigo'] === 'CANCELADO'
            ? 'El partido está cancelado: su acta es sólo de lectura.'
            : 'Su rol no puede modificar el acta.'; ?>
    </p>
</div>
<?php endif; ?>

<!-- ==================== Un acta por equipo ==================== -->
<?php foreach ($equipos as $e):
    $jugadores = $insLeague->convocables($e['ins'], $partidoId);
?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-users me-2"></i><?php echo $h($e['nombre']); ?>
            <span class="text-muted" style="font-weight:400;font-size:.85rem;">
                · <?php echo $h($e['lado']); ?>
            </span>
        </h3>
    </div>
    <div class="card-body p-0">
        <?php if (!$jugadores): ?>
            <p class="text-center text-muted py-4 mb-0">
                No hay jugadores habilitados en este equipo.
                <a href="<?php echo APP_URL; ?>plantillaPanel/<?php echo $e['ins']; ?>/">Revise la plantilla</a>.
            </p>
        <?php else: ?>
        <?php /* Los formularios se declaran AQUI, fuera de la tabla: un
                 <form> hijo de <tr> es HTML invalido y el navegador lo
                 saca del cuerpo, dejando los campos huerfanos. Cada campo
                 los referencia con el atributo form. */ ?>
        <?php if ($puedeEditar): foreach ($jugadores as $j): ?>
            <?php
            /*
            | SIN la clase FormularioAjax, y es deliberado.
            |
            | Ese manejador compartido responde a lo que el servidor mande, y
            | «guardarActa» devolvía «recargar»: cada línea guardada volvía a
            | pedir la página entera. Aquí el envío lo hace acta.js, que
            | actualiza sólo lo que cambió.
            |
            | El <form> se conserva porque es quien agrupa los campos del
            | jugador —los <input> lo referencian con el atributo form— y de
            | él sale el FormData que se envía.
            */
            ?>
            <form class="ds-acta-form" method="POST"
                  id="acta-<?php echo $e['ins'] . '-' . (int)$j['persona_id']; ?>"
                  data-persona="<?php echo (int)$j['persona_id']; ?>"
                  data-equipo="<?php echo $h($e['nombre']); ?>"
                  action="<?php echo APP_URL; ?>ajax/leagueAjax.php">
                <input type="hidden" name="modulo_league" value="guardarActa">
                <input type="hidden" name="partido_id" value="<?php echo $partidoId; ?>">
                <input type="hidden" name="persona_id" value="<?php echo (int)$j['persona_id']; ?>">
                <input type="hidden" name="inscripcion_id" value="<?php echo $e['ins']; ?>">
            </form>
        <?php endforeach; endif; ?>

        <?php
        /*
        | LA MISMA TABLA SE LEE DE DOS MANERAS
        |
        | En un teléfono esta tabla medía 1.186 px de ancho con 38 columnas:
        | se veían el jugador y DOS de las dieciocho estadísticas, y para
        | anotar un triple había que arrastrar de lado hasta perder de vista
        | en qué fila se estaba. La pantalla se llena en la mesa de anotación,
        | en directo y con el partido corriendo, así que ahí es donde más
        | importa y donde peor estaba.
        |
        | La clase ds-acta hace que, por debajo de 768 px, cada fila se
        | convierta en una tarjeta del jugador con las estadísticas en
        | rejilla de tres. La regla vive en core.css.
        |
        | NO SE DUPLICA EL MARCADO
        |
        | Lo evidente sería pintar una tabla para escritorio y unas tarjetas
        | para el teléfono, ocultando una con d-none. Sería un error: los
        | campos llevan name="stat[...]" y el atributo form, así que los dos
        | juegos se enviarían juntos y el segundo pisaría al primero. Un solo
        | marcado, dos presentaciones.
        |
        | El rótulo de cada celda viaja en data-rotulo porque en la tarjeta ya
        | no hay <thead> que consultar: sin él, quince casillas sin nombre.
        */
        ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0 ds-acta" style="font-variant-numeric:tabular-nums;">
                <thead>
                    <tr>
                        <th style="width:2.5rem;">#</th>
                        <th style="min-width:11rem;">Jugador</th>
                        <?php foreach ($captura as $t): ?>
                            <th class="text-center" style="width:3.6rem;"
                                title="<?php echo $h($t['tipo_nombre']); ?>">
                                <?php echo $h($t['tipo_abrev']); ?>
                            </th>
                        <?php endforeach; ?>
                        <?php foreach ($derivadas as $c => $t): ?>
                            <th class="text-center" style="width:3.4rem;background:var(--bs-tertiary-bg);"
                                title="<?php echo $h($t['tipo_nombre']); ?> · se calcula">
                                <?php echo $h($t['tipo_abrev']); ?>
                            </th>
                        <?php endforeach; ?>
                        <?php if ($puedeEditar): ?><th style="width:3rem;"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($jugadores as $j):
                    /* Las derivadas se resuelven aquí para que la fila las
                       muestre sin esperar a guardar. */
                    $calc = [];
                    foreach ($derivadas as $c => $t) {
                        $calc[$c] = $insLeague->valorDe($c, $j['stats'], $todos);
                    }
                ?>
                    <?php $idForm = 'acta-' . $e['ins'] . '-' . (int)$j['persona_id']; ?>
                    <?php /* data-form ata la fila a su formulario: el guardado
                             automatico llega por el campo que cambia y necesita
                             subir hasta aqui para repintar celdas y estado. */ ?>
                    <tr data-form="<?php echo $h($idForm); ?>">
                            <td class="text-muted ds-acta-dorsal"><?php echo $j['plantilla_dorsal'] !== null
                                    ? (int)$j['plantilla_dorsal'] : '—'; ?></td>
                            <td class="ds-acta-nombre">
                                <?php echo $h($j['persona_apellidos'] . ' ' . $j['persona_nombres']); ?>
                            </td>

                            <?php foreach ($captura as $t):
                                $c = $t['tipo_codigo'];
                            ?>
                                <td class="text-center" data-rotulo="<?php echo $h($t['tipo_abrev']); ?>">
                                    <?php if ($puedeEditar): ?>
                                        <input type="number" name="stat[<?php echo $h($c); ?>]"
                                               form="<?php echo $idForm; ?>"
                                               class="form-control form-control-sm text-center px-1"
                                               style="width:3.1rem;" min="0" max="999" step="<?php
                                                   echo $c === 'MIN' ? '0.5' : '1'; ?>"
                                               value="<?php echo isset($j['stats'][$c])
                                                   ? $h(rtrim(rtrim(number_format($j['stats'][$c], 1, '.', ''), '0'), '.'))
                                                   : ''; ?>"
                                               placeholder="0">
                                    <?php else: ?>
                                        <?php echo (int)($j['stats'][$c] ?? 0); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>

                            <?php /* La primera derivada se marca para que en la ficha del
                                     telefono el grupo calculado empiece en renglon propio.
                                     Sin eso PTS caia al lado de FAL —una casilla que se
                                     teclea— y las dos parecian lo mismo. */ ?>
                            <?php $primeraCalc = true; ?>
                            <?php foreach ($derivadas as $c => $t): ?>
                                <?php /* data-calc: el guardado automatico repinta esta celda
                                         con el valor que devuelve el servidor. */ ?>
                                <td class="text-center ds-acta-calc<?php
                                        echo $primeraCalc ? ' ds-acta-calc-primera' : ''; ?>"
                                    data-rotulo="<?php echo $h($t['tipo_abrev']); ?>"
                                    data-calc="<?php echo $h($c); ?>"
                                    style="background:var(--bs-secondary-bg);font-weight:600;">
                                    <?php echo (int)round($calc[$c]); ?>
                                </td>
                                <?php $primeraCalc = false; ?>
                            <?php endforeach; ?>

                            <?php if ($puedeEditar): ?>
                            <?php
                            /*
                            | AQUI HABIA UN BOTON DE GUARDAR
                            |
                            | Se retiro: el acta se guarda sola. En una mesa de
                            | anotacion, pulsar un boton por jugador despues de cada
                            | canasta es trabajo que la pantalla puede ahorrarse, y
                            | olvidarlo significa perder la linea.
                            |
                            | En su lugar queda el ESTADO de la fila, que es lo que
                            | de verdad hace falta saber cuando nadie pulsa nada:
                            | si lo escrito ya esta guardado.
                            */
                            ?>
                            <td class="text-center ds-acta-guardar">
                                <span class="ds-acta-estado" data-estado="listo"
                                      aria-live="polite"></span>
                            </td>
                            <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if (isset($totales[$e['nombre']])): ?>
                <?php /* data-equipo y data-total: el guardado automatico repinta el
                         pie sin recargar, con los totales que devuelve el servidor. */ ?>
                <tfoot data-equipo="<?php echo $h($e['nombre']); ?>">
                    <tr style="background:var(--bs-tertiary-bg);font-weight:600;">
                        <td colspan="2" class="ds-acta-nombre">Totales</td>
                        <?php foreach ($captura as $t): ?>
                            <td class="text-center" data-rotulo="<?php echo $h($t['tipo_abrev']); ?>"
                                data-total="<?php echo $h($t['tipo_codigo']); ?>">
                                <?php echo (int)round($totales[$e['nombre']][$t['tipo_codigo']] ?? 0); ?>
                            </td>
                        <?php endforeach; ?>
                        <?php foreach ($derivadas as $c => $t): ?>
                            <td class="text-center" data-rotulo="<?php echo $h($t['tipo_abrev']); ?>"
                                data-total="<?php echo $h($c); ?>">
                                <?php echo (int)round($totales[$e['nombre']][$c] ?? 0); ?>
                            </td>
                        <?php endforeach; ?>
                        <?php /* La celda vacia de la columna del boton estorba en la
                                 tarjeta: una casilla sin rotulo ni contenido. */ ?>
                        <?php if ($puedeEditar): ?><td class="ds-acta-guardar"></td><?php endif; ?>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-footer text-muted" style="font-size:.85rem;">
        Las columnas sombreadas se calculan de las demás y no se teclean.
        <?php /* Decía «con su botón» y ya no hay botón: el pie de una pantalla
                 que explica cómo se usa es lo primero que miente cuando la
                 pantalla cambia. */ ?>
        <strong>El acta se guarda sola</strong>: cada línea se registra al salir
        del campo, sin recargar la página. La última columna dice cómo va.
    </div>
</div>
<?php endforeach; ?>

<?php if ($puedeEditar): ?>
	<?php /* Sólo si se puede editar: a quien sólo mira, el guardado
	         automático no le sirve de nada y el navegador no tiene por qué
	         descargarlo. */ ?>
	<script src="<?php echo ds_recurso('ds_league/assets/js/acta.js'); ?>" defer></script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
