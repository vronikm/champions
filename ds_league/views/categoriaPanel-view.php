<?php
/*
| Panel de una categoría: inscripciones, calendario y clasificación.
|
| Las tres cosas van juntas porque es un solo flujo de trabajo: se
| inscribe, se habilita, se genera el fixture, se cargan resultados y se
| mira la tabla. Repartirlo en tres pantallas obligaría a ir y volver en
| cada paso.
|
| Los botones de cambio de estado NO están escritos aquí: se piden a
| transicionesDesde(), de modo que la vista no repite las reglas ni puede
| ofrecer un movimiento que el servidor vaya a rechazar.
*/

use league\controllers\competenciaController;

$insLeague = new competenciaController();

$categoriaId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);
$categoria   = $insLeague->categoria($categoriaId);

if (!$categoria) {
    $tituloVista = 'Categoría';
    $vistaActual = 'categoriaPanel';
    require_once __DIR__ . "/inc/layout-top.php";
    echo '<div class="callout callout-warning"><h6 class="mb-1">'
       . '<i class="fas fa-exclamation-circle me-2"></i>Categoría no encontrada</h6>'
       . '<p class="mb-0 text-muted">Elija una desde '
       . '<a href="' . APP_URL . 'categoriaList/">el listado de categorías</a>.</p></div>';
    require_once __DIR__ . "/inc/layout-bottom.php";
    return;
}

$tituloVista = $categoria['categoria_nombre'];
$vistaActual = 'categoriaPanel';

$inscritos = $insLeague->equiposDeCategoria($categoriaId, false);
$equipos   = $insLeague->equipos();

/* Los ya inscritos no vuelven a ofrecerse en el desplegable. */
$yaInscritos = array_column($inscritos, 'equipo_id');
$disponibles = array_filter($equipos,
    static fn($q) => !in_array((int)$q['equipo_id'], array_map('intval', $yaInscritos), true));

/* La fase de grupos. En la fase 1 hay una sola por categoría. */
$fases = $insLeague->fasesDeCategoria($categoriaId);
$fase  = $fases[0] ?? [];
$faseId = (int)($fase['fase_id'] ?? 0);

$partidos = $faseId > 0 ? $insLeague->partidosDeFase($faseId) : [];
$tabla    = $faseId > 0 ? $insLeague->tablaPosiciones($faseId) : [];

/* Las canchas vienen de Arena. League no tiene tabla de escenarios: si la
   tuviera, dos sistemas reservarían el mismo espacio físico sin verse. */
$canchas = $insLeague->instalacionesDisponibles();

$tono = ['exito' => 'success', 'aviso' => 'warning', 'peligro' => 'danger',
         'info' => 'info', 'neutro' => 'secondary'];

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<p class="text-muted mb-3">
    <a href="<?php echo APP_URL; ?>categoriaList/<?php echo (int)$categoria['categoria_torneoid']; ?>/"
       class="ds-link">← <?php echo $h($categoria['torneo_nombre']); ?></a>
    <span class="mx-2">·</span><?php echo $h($categoria['temporada_nombre']); ?>
</p>

<div class="row">
    <!-- ==================== Inscripciones ==================== -->
    <div class="col-lg-5 mb-3">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-clipboard-list me-2"></i>Equipos inscritos</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                        <?php if (!$inscritos): ?>
                            <tr><td class="text-center text-muted py-4">
                                Ningún equipo inscrito todavía.
                            </td></tr>
                        <?php else: foreach ($inscritos as $i):
                            $movs = $insLeague->transicionesDesde('inscripcion', $i['estado_codigo']);
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo $h($i['equipo_nombre']); ?></strong>
                                    <span class="badge text-bg-<?php echo $tono[$i['estado_tono']] ?? 'secondary'; ?> ms-1">
                                        <?php echo $h($i['estado_nombre']); ?>
                                    </span>
                                    <a href="<?php echo APP_URL; ?>plantillaPanel/<?php echo (int)$i['inscripcion_id']; ?>/"
                                       class="btn btn-sm btn-ver ms-1" title="Ver plantilla">
                                        <i class="fas fa-id-card"></i>
                                    </a>
                                    <?php if ($movs && puede_editar('categoriaPanel')): ?>
                                    <div class="mt-1">
                                        <?php foreach ($movs as $m): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-secondary js-mover"
                                                    data-id="<?php echo (int)$i['inscripcion_id']; ?>"
                                                    data-hacia="<?php echo $h($m['hacia']); ?>"
                                                    data-motivo="<?php echo $h($m['exige_motivo']); ?>"
                                                    data-accion="<?php echo $h($m['accion']); ?>">
                                                <?php echo $h($m['accion']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($disponibles && puede_crear('categoriaPanel')): ?>
            <div class="card-footer">
                <form class="FormularioAjax" method="POST"
                      action="<?php echo APP_URL; ?>ajax/leagueAjax.php">
                    <input type="hidden" name="modulo_league" value="inscribirEquipo">
                    <input type="hidden" name="inscripcion_categoriaid" value="<?php echo $categoriaId; ?>">
                    <div class="input-group input-group-sm">
                        <select name="inscripcion_equipoid" class="form-select" required>
                            <option value="">Inscribir equipo…</option>
                            <?php foreach ($disponibles as $q): ?>
                                <option value="<?php echo (int)$q['equipo_id']; ?>">
                                    <?php echo $h($q['equipo_nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php /* En Bootstrap 5 el botón va suelto dentro del
                                 input-group: el div envolvente que llevaba en
                                 la versión 4 ya no existe. (Escrito sin la
                                 etiqueta literal a propósito: con ella, el
                                 script que retira esos envoltorios la trataba
                                 como código y se comía un cierre de verdad.) */ ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==================== Clasificación ==================== -->
    <div class="col-lg-7 mb-3">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0"><i class="fas fa-list-ol me-2"></i>Clasificación</h3>
                <span class="text-muted" style="font-size:.82rem;">
                    <?php echo (int)$categoria['categoria_ptsvictoria']; ?> victoria ·
                    <?php echo (int)$categoria['categoria_ptsderrota']; ?> derrota
                </span>
            </div>
            <div class="card-body p-0">
                <?php if (!$tabla): ?>
                    <p class="text-center text-muted py-4 mb-0">
                        Sin datos todavía. Habilite equipos y genere el calendario.
                    </p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-variant-numeric:tabular-nums;">
                        <thead>
                            <tr>
                                <th style="width:2rem;">#</th>
                                <th>Equipo</th>
                                <th class="text-end">PJ</th>
                                <th class="text-end">PG</th>
                                <th class="text-end">PP</th>
                                <th class="text-end">PF</th>
                                <th class="text-end">PC</th>
                                <th class="text-end">DIF</th>
                                <th class="text-end">PTS</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($tabla as $f): ?>
                            <tr>
                                <td class="text-muted"><?php echo (int)$f['posicion']; ?></td>
                                <td>
                                    <?php echo $h($f['equipo']); ?>
                                    <?php if ($f['desempate'] !== ''): ?>
                                        <i class="fas fa-info-circle text-muted ms-1"
                                           title="Posición resuelta por <?php echo $h($f['desempate']); ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?php echo (int)$f['pj']; ?></td>
                                <td class="text-end"><?php echo (int)$f['pg']; ?></td>
                                <td class="text-end"><?php echo (int)$f['pp']; ?></td>
                                <td class="text-end"><?php echo (int)$f['pf']; ?></td>
                                <td class="text-end"><?php echo (int)$f['pc']; ?></td>
                                <td class="text-end"><?php echo ($f['dif'] > 0 ? '+' : '') . (int)$f['dif']; ?></td>
                                <td class="text-end"><strong><?php echo (int)$f['pts']; ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-muted" style="font-size:.85rem;">
                Se calcula a partir de los resultados; no se almacena. Sólo cuentan los
                partidos finalizados y los walkover: un cancelado sale del cómputo.
            </div>
        </div>
    </div>
</div>

<!-- ==================== Calendario ==================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><i class="fas fa-calendar-day me-2"></i>Calendario</h3>
        <?php if ($faseId > 0 && usuario_tiene_permiso('sorteoPanel')): ?>
            <a href="<?php echo APP_URL; ?>sorteoPanel/<?php echo $faseId; ?>/"
               class="ds-link me-auto ms-3"><i class="fas fa-random me-1"></i>Sorteo de grupos</a>
            <a href="<?php echo APP_URL; ?>playoffPanel/<?php echo $categoriaId; ?>/"
               class="ds-link me-auto"><i class="fas fa-sitemap me-1"></i>Eliminatorias</a>
            <a href="<?php echo APP_URL; ?>rankingPanel/<?php echo $categoriaId; ?>/"
               class="ds-link me-auto"><i class="fas fa-medal me-1"></i>Líderes</a>
        <?php endif; ?>
        <?php if ($faseId > 0 && !$partidos && puede_crear('categoriaPanel')): ?>
        <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/leagueAjax.php"
              class="mb-0">
            <input type="hidden" name="modulo_league" value="generarFixture">
            <input type="hidden" name="fase_id" value="<?php echo $faseId; ?>">
            <div class="d-flex align-items-center" style="gap:.6rem;">
                <label class="mb-0 text-muted" style="font-size:.85rem;">
                    <input type="checkbox" name="ida_vuelta" value="S"> ida y vuelta
                </label>
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-magic me-1"></i>Generar calendario
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if ($faseId === 0): ?>
            <p class="text-center text-muted py-4 mb-0">
                Esta categoría no tiene ninguna fase creada.
            </p>
        <?php elseif (!$partidos): ?>
            <p class="text-center text-muted py-4 mb-0">
                Sin calendario. Hacen falta al menos dos equipos <strong>habilitados</strong>.
            </p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th style="width:5rem;">Jornada</th>
                        <th>Local</th>
                        <th class="text-center" style="width:9rem;">Resultado</th>
                        <th>Visitante</th>
                        <th style="width:13rem;">Cuándo y dónde</th>
                        <th style="width:8rem;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($partidos as $p):
                    $cerrado = $p['estado_efectivo'] === 'S';
                ?>
                    <tr>
                        <td class="text-muted"><?php echo (int)$p['jornada_numero']; ?></td>
                        <td><?php echo $h($p['local']); ?></td>
                        <td class="text-center">
                            <?php if ($cerrado): ?>
                                <strong><?php echo (int)$p['partido_puntoslocal']; ?>
                                        – <?php echo (int)$p['partido_puntosvisitante']; ?></strong>
                            <?php elseif (puede_editar('categoriaPanel')): ?>
                                <form class="FormularioAjax d-inline-flex" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/leagueAjax.php"
                                      style="gap:.25rem;">
                                    <input type="hidden" name="modulo_league" value="guardarResultado">
                                    <input type="hidden" name="partido_id" value="<?php echo (int)$p['partido_id']; ?>">
                                    <input type="number" name="puntos_local" class="form-control form-control-sm"
                                           style="width:3.6rem;" min="0" max="300" required>
                                    <input type="number" name="puntos_visitante" class="form-control form-control-sm"
                                           style="width:3.6rem;" min="0" max="300" required>
                                    <button type="submit" class="btn btn-sm btn-primary" title="Guardar resultado">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $h($p['visitante']); ?></td>

                        <!-- Programación: la cancha sale de Arena, no de aquí -->
                        <td>
                            <?php if ($p['partido_fecha']): ?>
                                <?php echo $h($p['partido_fecha']); ?>
                                <?php echo $h(substr((string)$p['partido_hora'], 0, 5)); ?>
                                <?php if (!empty($p['cancha'])): ?>
                                    <br><small class="text-muted"><?php echo $h($p['cancha']); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if (!$cerrado && $canchas && puede_editar('categoriaPanel')): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary js-programar"
                                        data-id="<?php echo (int)$p['partido_id']; ?>"
                                        data-rotulo="<?php echo $h($p['local'] . ' vs ' . $p['visitante']); ?>"
                                        data-fecha="<?php echo $h((string)$p['partido_fecha']); ?>"
                                        data-hora="<?php echo $h(substr((string)$p['partido_hora'], 0, 5)); ?>"
                                        data-inst="<?php echo (int)$p['partido_instalacionid']; ?>">
                                    <i class="fas fa-calendar-check me-1"></i><?php echo $p['partido_fecha'] ? 'Cambiar' : 'Programar'; ?>
                                </button>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="badge text-bg-<?php echo $tono[$p['estado_tono']] ?? 'secondary'; ?>">
                                <?php echo $h($p['estado_nombre']); ?>
                            </span>
                            <a href="<?php echo APP_URL; ?>actaPartido/<?php echo (int)$p['partido_id']; ?>/"
                               class="btn btn-sm btn-ver ms-1" title="Acta del partido">
                                <i class="fas fa-clipboard-list"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
/* Programación de un partido.
   El desplegable de canchas se construye con lo que Arena declara activo;
   quien decide si la franja está libre es Arena, no esta pantalla. Aquí
   sólo se recoge la propuesta y se muestra su respuesta. */
(function () {
    var canchas = <?php echo json_encode(array_map(static fn($c) => [
        'id'     => (int)$c['instalacion_id'],
        'nombre' => $c['instalacion_codigo'] . ' · ' . $c['instalacion_nombre'],
    ], $canchas), JSON_UNESCAPED_UNICODE); ?>;

    if (!canchas.length) { return; }

    document.querySelectorAll('.js-programar').forEach(function (b) {
        b.addEventListener('click', function () {
            var opciones = canchas.map(function (c) {
                var sel = String(c.id) === b.getAttribute('data-inst') ? ' selected' : '';
                return '<option value="' + c.id + '"' + sel + '>' + c.nombre + '</option>';
            }).join('');

            Swal.fire({
                title: b.getAttribute('data-rotulo'),
                html:
                    '<div style="text-align:left">' +
                    '<label style="font-size:.85rem">Cancha</label>' +
                    '<select id="sw-inst" class="swal2-input" style="width:100%;margin:0 0 .6rem">' + opciones + '</select>' +
                    '<label style="font-size:.85rem">Fecha</label>' +
                    '<input id="sw-fecha" type="date" class="swal2-input" style="width:100%;margin:0 0 .6rem" value="' + b.getAttribute('data-fecha') + '">' +
                    '<label style="font-size:.85rem">Hora de inicio</label>' +
                    '<input id="sw-hora" type="time" class="swal2-input" style="width:100%;margin:0 0 .6rem" value="' + (b.getAttribute('data-hora') || '19:00') + '">' +
                    '<label style="font-size:.85rem">Duración (minutos)</label>' +
                    '<input id="sw-dur" type="number" min="15" max="300" value="90" class="swal2-input" style="width:100%;margin:0">' +
                    '</div>',
                showCancelButton: true,
                confirmButtonText: 'Programar',
                cancelButtonText: 'Cancelar',
                preConfirm: function () {
                    var f = document.getElementById('sw-fecha').value;
                    var h = document.getElementById('sw-hora').value;
                    if (!f || !h) { Swal.showValidationMessage('Indique fecha y hora.'); return false; }
                    return {
                        instalacion_id: document.getElementById('sw-inst').value,
                        fecha: f, hora: h,
                        duracion: document.getElementById('sw-dur').value || 90
                    };
                }
            }).then(function (r) {
                if (!r.isConfirmed) { return; }

                var fd = new FormData();
                fd.append('modulo_league', 'programarPartido');
                fd.append('partido_id', b.getAttribute('data-id'));
                for (var k in r.value) { fd.append(k, r.value[k]); }

                fetch('<?php echo APP_URL; ?>ajax/leagueAjax.php', { method: 'POST', body: fd })
                    .then(function (x) { return x.json(); })
                    .then(function (j) {
                        Swal.fire({ icon: j.icono, title: j.titulo, text: j.texto })
                            .then(function () { if (j.tipo === 'recargar') { location.reload(); } });
                    })
                    .catch(function () {
                        Swal.fire({ icon: 'error', title: 'Sin respuesta',
                                    text: 'No se pudo contactar con el servidor.' });
                    });
            });
        });
    });
})();

/* Los cambios de estado de una inscripción se envían desde aquí. El
   botón ya sabe si su transición exige motivo, porque el servidor se lo
   dijo al pintarlo: la regla vive en dsl_estado_transicion y esta pantalla
   sólo la refleja. */
(function () {
    document.querySelectorAll('.js-mover').forEach(function (b) {
        b.addEventListener('click', function () {
            var exige = b.getAttribute('data-motivo') === 'S';

            var enviar = function (motivo) {
                var fd = new FormData();
                fd.append('modulo_league', 'estadoInscripcion');
                fd.append('inscripcion_id', b.getAttribute('data-id'));
                fd.append('hacia', b.getAttribute('data-hacia'));
                fd.append('motivo', motivo || '');

                fetch('<?php echo APP_URL; ?>ajax/leagueAjax.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        Swal.fire({ icon: j.icono, title: j.titulo, text: j.texto })
                            .then(function () { if (j.tipo === 'recargar') { location.reload(); } });
                    })
                    .catch(function () {
                        Swal.fire({ icon: 'error', title: 'Sin respuesta',
                                    text: 'No se pudo contactar con el servidor.' });
                    });
            };

            if (!exige) { enviar(''); return; }

            Swal.fire({
                title: b.getAttribute('data-accion'),
                input: 'textarea',
                inputLabel: 'Motivo',
                inputPlaceholder: 'Por qué se hace este cambio',
                showCancelButton: true,
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Cancelar',
                inputValidator: function (v) {
                    if (!v || !v.trim()) { return 'Este cambio necesita una justificación escrita.'; }
                }
            }).then(function (r) { if (r.isConfirmed) { enviar(r.value); } });
        });
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
