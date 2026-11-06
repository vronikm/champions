<?php
/*
| Bloqueos: periodos en los que una instalación no se alquila, aunque caigan
| dentro de su horario habitual (mantenimiento, eventos).
|
| Dos formas (ver arenaController, sección Bloqueos):
|   · Días y franjas   «del 6 al 11 nov, lun a vie, 18:00–19:00».
|   · Periodo continuo «del lunes 10:00 al miércoles 18:00».
|
| Cada bloqueo es una tarjeta que dice cuándo en palabras, si está en curso,
| próximo o terminado, y cuántas reservas vigentes caen dentro. El mismo
| modal sirve para crear y para editar: el botón que lo abre trae los datos.
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Mantenimiento';
$vistaActual = 'bloqueoList';

$instalaciones = $insArena->instalaciones();

$instSel   = (int)($_GET['instalacion'] ?? 0);
$historico = isset($_GET['historico']);

$bloqueos = $insArena->bloqueos($instSel, !$historico);
$franjasInst = $insArena->franjasParaBloqueo();

$tipos = [
    'M' => ['nombre' => 'Mantenimiento', 'color' => 'warning',   'icono' => 'fa-tools'],
    'E' => ['nombre' => 'Evento propio', 'color' => 'info',      'icono' => 'fa-flag'],
    'O' => ['nombre' => 'Otro',          'color' => 'secondary', 'icono' => 'fa-ban'],
];
$momentos = [
    1 => ['nombre' => 'En curso',   'color' => 'danger'],
    2 => ['nombre' => 'Próximo',    'color' => 'primary'],
    3 => ['nombre' => 'Terminado',  'color' => 'secondary'],
];

$puedeCrear    = puede_crear('bloqueoList');
$puedeEditar   = puede_editar('bloqueoList');
$puedeEliminar = puede_eliminar('bloqueoList');

$e = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

/* Fechas en palabras: «vie 6 nov» y, si no es este año, con el año. */
$diasCortos  = [1 => 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
$diasLargos  = [1 => 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$mesesCortos = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$hoy    = date('Y-m-d');
$manana = date('Y-m-d', strtotime('+1 day'));
$diaRelativo = static function (int $t) use ($hoy, $manana): string {
    $f = date('Y-m-d', $t);
    return $f === $hoy ? 'hoy' : ($f === $manana ? 'mañana' : '');
};
$fechaCorta  = static function (int $t) use ($diasCortos, $mesesCortos, $diaRelativo): string {
    $rel = $diaRelativo($t);
    $txt = ($rel ?: $diasCortos[(int)date('N', $t)]) . ' ' . date('j', $t) . ' ' . $mesesCortos[(int)date('n', $t)];
    return date('Y', $t) === date('Y') ? $txt : $txt . ' ' . date('Y', $t);
};
$hora24 = static fn (int $t, int $base): string => ($t - $base >= 86400) ? '24:00' : date('H:i', $t);
$duracion = static function (int $ini, int $fin): string {
    $min   = intdiv($fin - $ini, 60);
    $dias  = intdiv($min, 1440);
    $horas = intdiv($min % 1440, 60);
    $resto = $min % 60;
    $partes = [];
    if ($dias)  { $partes[] = $dias . ($dias === 1 ? ' día' : ' días'); }
    if ($horas) { $partes[] = $horas . ' h'; }
    if ($resto) { $partes[] = $resto . ' min'; }
    return $partes ? implode(' ', $partes) : '0 min';
};

/* «Lun a vie», «Sáb y dom», «Todos los días», «Lun, mié, vie». */
$textoDias = static function (array $dias) use ($diasCortos): string {
    sort($dias);
    if ($dias === [1, 2, 3, 4, 5, 6, 7]) { return 'Todos los días'; }
    if ($dias === [6, 7])                { return 'Sáb y dom'; }
    $seguidos = count($dias) >= 3 && $dias === range($dias[0], end($dias));
    if ($seguidos) { return ucfirst($diasCortos[$dias[0]]) . ' a ' . $diasCortos[end($dias)]; }
    $n = array_map(static fn ($d) => $diasCortos[$d], $dias);
    return ucfirst(implode(', ', $n));
};

/* Une las franjas seguidas para leerlas: 18–19 y 19–20 → 18:00–20:00. */
$tramosTexto = static function (array $franjas): array {
    sort($franjas);
    $t = [];
    foreach ($franjas as $f) {
        [$a, $b] = explode('-', $f);
        $u = count($t) - 1;
        if ($u >= 0 && $a <= $t[$u][1]) { $t[$u][1] = max($t[$u][1], $b); } else { $t[] = [$a, $b]; }
    }
    return array_map(static fn ($x) => $x[0] . '–' . $x[1], $t);
};

$enCurso = count(array_filter($bloqueos, static fn ($b) => (int)$b['momento'] === 1));

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if (!$instalaciones): ?>

    <div class="aviso-superadmin">
        <i class="fas fa-info-circle fa-lg mt-1"></i>
        <div>
            <strong>Todavía no hay instalaciones registradas.</strong><br>
            <a href="<?php echo APP_URL; ?>instalacionList/">Ir a Instalaciones →</a>
        </div>
    </div>

<?php else: ?>

    <div class="card ar-bloq">
        <div class="card-header ar-bloq__cabecera">
            <div>
                <h3 class="card-title mb-0">
                    <?php echo count($bloqueos); ?> bloqueo<?php echo count($bloqueos) === 1 ? '' : 's'; ?>
                    <?php echo $historico ? '' : (count($bloqueos) === 1 ? 'vigente' : 'vigentes'); ?>
                </h3>
                <?php if ($enCurso > 0): ?>
                    <small class="text-danger d-block mt-1">
                        <i class="fas fa-circle ar-bloq__pulso me-1"></i>
                        <?php echo $enCurso === 1 ? '1 en curso ahora' : $enCurso . ' en curso ahora'; ?>
                    </small>
                <?php endif; ?>
            </div>

            <?php if ($puedeCrear): ?>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalBloqueo">
                    <i class="fas fa-plus me-1"></i> Nuevo bloqueo
                </button>
            <?php endif; ?>
        </div>

        <form method="GET" action="<?php echo APP_URL; ?>bloqueoList/" class="ar-bloq__filtros">
            <select name="instalacion" class="form-select form-select-sm" aria-label="Instalación"
                    onchange="this.form.submit()">
                <option value="0">Todas las instalaciones</option>
                <?php foreach ($instalaciones as $i): ?>
                    <option value="<?php echo (int)$i['instalacion_id']; ?>"
                        <?php echo $instSel === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                        <?php echo $e($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php
            $base = APP_URL . 'bloqueoList/' . ($instSel > 0 ? '?instalacion=' . $instSel : '');
            $sep  = $instSel > 0 ? '&' : '?';
            ?>
            <div class="btn-group btn-group-sm" role="group" aria-label="Periodo">
                <a href="<?php echo $base; ?>"
                   class="btn btn-outline-secondary<?php echo $historico ? '' : ' active'; ?>"
                   <?php echo $historico ? '' : 'aria-current="true"'; ?>>Vigentes</a>
                <a href="<?php echo $base . $sep; ?>historico=1"
                   class="btn btn-outline-secondary<?php echo $historico ? ' active' : ''; ?>"
                   <?php echo $historico ? 'aria-current="true"' : ''; ?>>Todos</a>
            </div>
        </form>

        <div class="card-body">
            <?php if (!$bloqueos): ?>
                <div class="ar-bloq__vacio">
                    <i class="fas fa-calendar-check fa-2x mb-2"></i>
                    <strong><?php echo $historico ? 'No hay bloqueos registrados.' : 'No hay bloqueos vigentes.'; ?></strong>
                    <span>Las instalaciones se pueden reservar en todo su horario.</span>
                    <?php if ($puedeCrear): ?>
                        <button type="button" class="btn btn-outline-primary btn-sm mt-2"
                                data-bs-toggle="modal" data-bs-target="#modalBloqueo">
                            <i class="fas fa-plus me-1"></i> Registrar un bloqueo
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="ar-bloq__lista">
                <?php foreach ($bloqueos as $b):
                    $esRegla = (int)$b['grupo_id'] > 0;
                    $tipo    = $tipos[$b['bloqueo_tipo']] ?? $tipos['O'];
                    $mom     = $momentos[(int)$b['momento']] ?? $momentos[2];
                    $afect   = (int)$b['afectadas'];

                    if ($esRegla) {
                        $tDesde = strtotime($b['fechadesde']);
                        $tHasta = strtotime($b['fechahasta']);
                        $todo   = !$b['franjas'];
                        /* El recuadro marca la próxima vez que se aplica. */
                        $tChip  = $b['proxima'] ? strtotime($b['proxima']['inicio']) : $tDesde;
                        $urlAfectadas = APP_URL . 'reservaList/?instalacion=' . (int)$b['bloqueo_instalacionid']
                                      . '&desde=' . $b['fechadesde'] . '&hasta=' . $b['fechahasta'];
                    } else {
                        $tIni = strtotime($b['bloqueo_inicio']);
                        $tFin = strtotime($b['bloqueo_fin']);
                        $tChip = $tIni;
                        $baseDia  = strtotime(date('Y-m-d', $tIni));
                        $mismoDia = $tFin - $baseDia <= 86400;
                        $urlAfectadas = APP_URL . 'reservaList/?instalacion=' . (int)$b['bloqueo_instalacionid']
                                      . '&desde=' . date('Y-m-d', $tIni) . '&hasta=' . date('Y-m-d', $tFin);
                    }
                ?>
                    <article class="ar-bloq__item ar-bloq__item--<?php echo (int)$b['momento']; ?>">
                        <div class="ar-bloq__fecha text-bg-<?php echo $tipo['color']; ?>" aria-hidden="true">
                            <span class="ar-bloq__dsem"><?php echo $diaRelativo($tChip) ?: $diasCortos[(int)date('N', $tChip)]; ?></span>
                            <span class="ar-bloq__dnum"><?php echo date('j', $tChip); ?></span>
                            <span class="ar-bloq__mes"><?php echo $mesesCortos[(int)date('n', $tChip)]; ?></span>
                        </div>

                        <div class="ar-bloq__datos">
                            <div class="ar-bloq__marcas">
                                <span class="badge text-bg-<?php echo $tipo['color']; ?>">
                                    <i class="fas <?php echo $tipo['icono']; ?> me-1"></i><?php echo $tipo['nombre']; ?>
                                </span>
                                <span class="badge rounded-pill bg-<?php echo $mom['color']; ?>-subtle text-<?php echo $mom['color']; ?>-emphasis">
                                    <?php echo $mom['nombre']; ?>
                                </span>
                                <?php if ($b['liga']): ?>
                                    <span class="badge rounded-pill bg-secondary-subtle text-secondary-emphasis">
                                        <i class="fas fa-trophy me-1"></i>League
                                    </span>
                                <?php endif; ?>
                            </div>

                            <strong class="ar-bloq__motivo">
                                <?php echo $e($b['bloqueo_motivo'] ?: $tipo['nombre']); ?>
                            </strong>

                            <?php if ($esRegla): ?>
                                <span class="ar-bloq__cuando">
                                    <i class="far fa-calendar me-1"></i>
                                    <?php if ($tDesde === $tHasta): ?>
                                        <?php echo $fechaCorta($tDesde); ?>
                                    <?php else: ?>
                                        <span class="text-nowrap"><?php echo $fechaCorta($tDesde); ?></span>
                                        <span class="text-muted">→</span>
                                        <span class="text-nowrap"><?php echo $fechaCorta($tHasta); ?></span>
                                        <span class="ar-bloq__dur"><?php echo $e($textoDias($b['dias'])); ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="ar-bloq__franjas">
                                    <i class="far fa-clock me-1"></i>
                                    <?php if ($todo): ?>
                                        <span class="ar-bloq__franja">Todo el día</span>
                                    <?php else: foreach ($tramosTexto($b['franjas']) as $t): ?>
                                        <span class="ar-bloq__franja"><?php echo $e($t); ?></span>
                                    <?php endforeach; endif; ?>
                                </span>
                                <?php if ($b['proxima'] && (int)$b['momento'] !== 1): ?>
                                    <span class="ar-bloq__proxima">
                                        Próxima: <?php echo $fechaCorta(strtotime($b['proxima']['inicio'])); ?>,
                                        <?php echo date('H:i', strtotime($b['proxima']['inicio'])); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="ar-bloq__cuando">
                                    <i class="far fa-clock me-1"></i>
                                    <?php if ($mismoDia): ?>
                                        <?php echo $fechaCorta($tIni); ?> ·
                                        <span class="text-nowrap"><?php echo date('H:i', $tIni); ?>–<?php echo $hora24($tFin, $baseDia); ?></span>
                                    <?php else: ?>
                                        <span class="text-nowrap"><?php echo $fechaCorta($tIni) . ', ' . date('H:i', $tIni); ?></span>
                                        <span class="text-muted">→</span>
                                        <span class="text-nowrap"><?php echo $fechaCorta($tFin) . ', ' . date('H:i', $tFin); ?></span>
                                    <?php endif; ?>
                                    <span class="ar-bloq__dur"><?php echo $duracion($tIni, $tFin); ?> seguidas</span>
                                </span>
                            <?php endif; ?>

                            <span class="ar-bloq__lugar">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo $e($b['instalacion_codigo'] . ' · ' . $b['instalacion_nombre']); ?>
                                <?php if ($b['sede_nombre']): ?>
                                    <span class="text-muted">· <?php echo $e($b['sede_nombre']); ?></span>
                                <?php endif; ?>
                            </span>

                            <?php if ($afect > 0): ?>
                                <a href="<?php echo $urlAfectadas; ?>" class="ar-bloq__aviso">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <?php echo $afect === 1 ? '1 reserva vigente dentro: reubíquela'
                                                            : $afect . ' reservas vigentes dentro: reubíquelas'; ?>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if ($puedeEditar || $puedeEliminar): ?>
                            <div class="ar-bloq__acciones">
                                <?php if ($b['liga']): ?>
                                    <small class="text-muted me-auto align-self-center">Se cambia desde el partido en League.</small>
                                <?php elseif ($puedeEditar): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#modalBloqueo"
                                            data-editar="1"
                                            data-instalacion="<?php echo (int)$b['bloqueo_instalacionid']; ?>"
                                            data-tipo="<?php echo $e($b['bloqueo_tipo']); ?>"
                                            data-motivo="<?php echo $e($b['bloqueo_motivo']); ?>"
                                            <?php if ($esRegla): ?>
                                                data-modo="F"
                                                data-grupo="<?php echo (int)$b['grupo_id']; ?>"
                                                data-desde="<?php echo $e($b['fechadesde']); ?>"
                                                data-hasta="<?php echo $e($b['fechahasta']); ?>"
                                                data-dias="<?php echo $e(implode(',', $b['dias'])); ?>"
                                                data-franjas="<?php echo $e(implode(',', $b['franjas'])); ?>"
                                            <?php else: ?>
                                                data-modo="C"
                                                data-id="<?php echo (int)$b['bloqueo_id']; ?>"
                                                data-inicio="<?php echo date('Y-m-d\TH:i', $tIni); ?>"
                                                data-fin="<?php echo date('Y-m-d\TH:i', $tFin); ?>"
                                            <?php endif; ?>
                                            aria-label="Editar el bloqueo <?php echo $e($b['bloqueo_motivo'] ?: $tipo['nombre']); ?>">
                                        <i class="fas fa-pen me-1"></i> Editar
                                    </button>
                                <?php endif; ?>

                                <?php if ($puedeEliminar): ?>
                                    <form class="FormularioAjax d-inline" method="POST"
                                          action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                          data-confirmar="<?php echo $esRegla
                                              ? 'Se eliminará el bloqueo de todos sus días y franjas; la instalación volverá a estar disponible.'
                                              : 'Se eliminará el bloqueo y la instalación volverá a estar disponible en ese periodo.'; ?>">
                                        <input type="hidden" name="modulo_arena" value="eliminarBloqueo">
                                        <input type="hidden" name="bloqueo_id" value="<?php echo (int)$b['bloqueo_id']; ?>">
                                        <input type="hidden" name="bloqueo_grupoid" value="<?php echo (int)$b['grupo_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"
                                                aria-label="Eliminar el bloqueo">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-footer text-muted small">
            Mientras dura un bloqueo la instalación no se puede reservar, aunque esté dentro de su
            <a href="<?php echo APP_URL; ?>horarioList/">horario</a>.
        </div>
    </div>

    <?php if ($puedeCrear || $puedeEditar): ?>
        <div class="modal fade" id="modalBloqueo" tabindex="-1" aria-labelledby="modalBloqueoTitulo">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <form class="FormularioAjax ar-bloq" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" id="formBloqueo">
                        <input type="hidden" name="modulo_arena" value="guardarBloqueo">
                        <input type="hidden" name="bloqueo_id" id="bloqueo_id" value="0">
                        <input type="hidden" name="bloqueo_grupoid" id="bloqueo_grupoid" value="0">

                        <div class="modal-header">
                            <h5 class="modal-title" id="modalBloqueoTitulo">Nuevo bloqueo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-6">
                                    <label for="bloqueo_instalacionid" class="form-label">Instalación <span class="text-danger">*</span></label>
                                    <select class="form-select" id="bloqueo_instalacionid" name="bloqueo_instalacionid" required
                                            data-defecto="<?php echo $instSel > 0 ? $instSel : (count($instalaciones) === 1 ? (int)$instalaciones[0]['instalacion_id'] : ''); ?>">
                                        <option value="">Seleccione…</option>
                                        <?php foreach ($instalaciones as $i): ?>
                                            <option value="<?php echo (int)$i['instalacion_id']; ?>">
                                                <?php echo $e($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="bloqueo_motivo" class="form-label">Motivo</label>
                                    <input type="text" class="form-control" id="bloqueo_motivo" name="bloqueo_motivo" maxlength="150"
                                           placeholder="Ej.: cambio de césped, torneo interno">
                                </div>
                            </div>

                            <fieldset class="mb-3 border-0 p-0">
                                <legend class="form-label fs-6 mb-2">Tipo</legend>
                                <div class="ar-bloq__tipos">
                                    <?php foreach ($tipos as $k => $t): ?>
                                        <input type="radio" class="btn-check" name="bloqueo_tipo"
                                               id="bloqueo_tipo_<?php echo $k; ?>" value="<?php echo $k; ?>"
                                               <?php echo $k === 'M' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-secondary" for="bloqueo_tipo_<?php echo $k; ?>">
                                            <i class="fas <?php echo $t['icono']; ?>"></i>
                                            <span><?php echo $t['nombre']; ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>

                            <fieldset class="mb-3 border-0 p-0">
                                <legend class="form-label fs-6 mb-2">¿Cómo se bloquea?</legend>
                                <div class="ar-bloq__modos">
                                    <input type="radio" class="btn-check" name="bloqueo_modo" id="bloqueo_modo_F" value="F" checked>
                                    <label class="btn btn-outline-primary" for="bloqueo_modo_F">
                                        <i class="fas fa-th"></i>
                                        <span><strong>Días y franjas</strong><small>Ciertos días, a ciertas horas</small></span>
                                    </label>
                                    <input type="radio" class="btn-check" name="bloqueo_modo" id="bloqueo_modo_C" value="C">
                                    <label class="btn btn-outline-primary" for="bloqueo_modo_C">
                                        <i class="fas fa-arrows-alt-h"></i>
                                        <span><strong>Periodo continuo</strong><small>De una fecha y hora a otra</small></span>
                                    </label>
                                </div>
                            </fieldset>

                            <!-- ---------- Días y franjas ---------- -->
                            <div id="seccionFranjas" class="ar-bloq__seccion">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label for="bloqueo_fechadesde" class="form-label">Desde el <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="bloqueo_fechadesde" name="bloqueo_fechadesde" required>
                                    </div>
                                    <div class="col-6">
                                        <label for="bloqueo_fechahasta" class="form-label">Hasta el <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="bloqueo_fechahasta" name="bloqueo_fechahasta" required>
                                    </div>
                                </div>

                                <fieldset class="mb-3 border-0 p-0">
                                    <div class="ar-bloq__rotulo">
                                        <legend class="form-label fs-6 mb-0">Días <span class="text-danger">*</span></legend>
                                        <span class="ar-bloq__atajos" data-atajo="dias">
                                            <button type="button" class="btn btn-link btn-sm p-0" data-dias="1,2,3,4,5,6,7">Todos</button>
                                            <button type="button" class="btn btn-link btn-sm p-0" data-dias="1,2,3,4,5">Lun–vie</button>
                                            <button type="button" class="btn btn-link btn-sm p-0" data-dias="6,7">Sáb–dom</button>
                                        </span>
                                    </div>
                                    <div class="ar-bloq__dias">
                                        <?php foreach ($diasLargos as $n => $nombre): ?>
                                            <input type="checkbox" class="btn-check" name="bloqueo_dias[]"
                                                   id="bloqueo_dia_<?php echo $n; ?>" value="<?php echo $n; ?>">
                                            <label class="btn btn-outline-secondary" for="bloqueo_dia_<?php echo $n; ?>"
                                                   title="<?php echo $nombre; ?>">
                                                <?php echo mb_substr($nombre, 0, 3, 'UTF-8'); ?>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </fieldset>

                                <fieldset class="mb-2 border-0 p-0">
                                    <div class="ar-bloq__rotulo">
                                        <legend class="form-label fs-6 mb-0">Franjas <span class="text-danger">*</span></legend>
                                        <span class="ar-bloq__atajos">
                                            <button type="button" class="btn btn-link btn-sm p-0" id="franjasTodas">Todas</button>
                                            <button type="button" class="btn btn-link btn-sm p-0" id="franjasNinguna">Ninguna</button>
                                        </span>
                                    </div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" role="switch"
                                               id="bloqueo_todoeldia" name="bloqueo_todoeldia" value="S">
                                        <label class="form-check-label" for="bloqueo_todoeldia">Todo el día (00:00 a 24:00)</label>
                                    </div>
                                    <div id="franjasLista" class="ar-bloq__franjas-lista" aria-live="polite"></div>
                                    <small class="text-muted d-block mt-1">
                                        Las franjas salen del <a href="<?php echo APP_URL; ?>horarioList/">horario</a> de la instalación.
                                    </small>
                                </fieldset>
                            </div>

                            <!-- ---------- Periodo continuo ---------- -->
                            <div id="seccionContinuo" class="ar-bloq__seccion" hidden>
                                <div class="row g-2">
                                    <div class="mb-2 col-12 col-sm-6">
                                        <label for="bloqueo_inicio" class="form-label">Desde <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" id="bloqueo_inicio" name="bloqueo_inicio" required>
                                    </div>
                                    <div class="mb-2 col-12 col-sm-6">
                                        <label for="bloqueo_fin" class="form-label">Hasta <span class="text-danger">*</span></label>
                                        <input type="datetime-local" class="form-control" id="bloqueo_fin" name="bloqueo_fin" required>
                                    </div>
                                </div>
                                <div class="ar-bloq__rapidos mb-2" role="group" aria-label="Duración rápida">
                                    <span class="small text-muted me-1">Duración:</span>
                                    <button type="button" class="btn btn-sm btn-light" data-horas="1">1 h</button>
                                    <button type="button" class="btn btn-sm btn-light" data-horas="2">2 h</button>
                                    <button type="button" class="btn btn-sm btn-light" data-horas="4">4 h</button>
                                    <button type="button" class="btn btn-sm btn-light" data-dia="1">Todo el día</button>
                                </div>
                            </div>

                            <div id="bloqueoResumen" class="ar-bloq__resumen" aria-live="polite"></div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary" id="bloqueoGuardar">
                                <i class="fas fa-save me-1"></i> <span>Guardar</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var FRANJAS = <?php echo json_encode((object)$franjasInst, JSON_UNESCAPED_UNICODE); ?>;
            var DIAS = ['', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
            var MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

            var modal = document.getElementById('modalBloqueo');
            var form  = document.getElementById('formBloqueo');
            if (!modal || !form) { return; }
            var $ = function (id) { return document.getElementById(id); };

            var id = $('bloqueo_id'), grupo = $('bloqueo_grupoid'), inst = $('bloqueo_instalacionid');
            var motivo = $('bloqueo_motivo'), titulo = $('modalBloqueoTitulo'), guardar = $('bloqueoGuardar');
            var resumen = $('bloqueoResumen');
            var secF = $('seccionFranjas'), secC = $('seccionContinuo');
            var fDesde = $('bloqueo_fechadesde'), fHasta = $('bloqueo_fechahasta');
            var todo = $('bloqueo_todoeldia'), lista = $('franjasLista');
            var ini = $('bloqueo_inicio'), fin = $('bloqueo_fin');
            var dias = form.querySelectorAll('input[name="bloqueo_dias[]"]');
            var extra = [];   /* franjas de una regla que ya no están en el horario */

            function dos(n) { return (n < 10 ? '0' : '') + n; }
            function iso(d) { return d.getFullYear() + '-' + dos(d.getMonth() + 1) + '-' + dos(d.getDate()); }
            function aCampo(d) { return iso(d) + 'T' + dos(d.getHours()) + ':' + dos(d.getMinutes()); }
            function deCampo(v) { return v ? new Date(v) : null; }
            function deFecha(v) { var p = (v || '').split('-'); return p.length === 3 ? new Date(+p[0], p[1] - 1, +p[2]) : null; }
            function corta(d) { return DIAS[d.getDay() || 7] + ' ' + d.getDate() + ' ' + MESES[d.getMonth()]; }
            function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
            function modo() { return form.querySelector('input[name="bloqueo_modo"]:checked').value; }
            function marcarRadio(nombre, v, defecto) {
                var r = form.querySelector('input[name="' + nombre + '"][value="' + v + '"]') ||
                        form.querySelector('input[name="' + nombre + '"][value="' + defecto + '"]');
                r.checked = true;
            }
            function franjasMarcadas() {
                return Array.prototype.map.call(lista.querySelectorAll('input:checked'), function (c) { return c.value; });
            }
            function duracion(min) {
                var d = Math.floor(min / 1440), h = Math.floor((min % 1440) / 60), m = min % 60, p = [];
                if (d) { p.push(d + (d === 1 ? ' día' : ' días')); }
                if (h) { p.push(h + ' h'); }
                if (m) { p.push(m + ' min'); }
                return p.join(' ');
            }
            function aviso(clase, html) { resumen.className = 'ar-bloq__resumen ' + clase; resumen.innerHTML = html; }

            /* Los chips de franjas, según la instalación elegida. */
            function pintarFranjas(marcadas) {
                var propias = (FRANJAS[inst.value] || []).map(function (f) { return f[0] + '-' + f[1]; });
                var todas = propias.concat(extra.filter(function (f) { return propias.indexOf(f) < 0; })).sort();
                if (!inst.value) {
                    lista.innerHTML = '<small class="text-muted">Elija primero la instalación.</small>';
                    return;
                }
                if (!todas.length) {
                    lista.innerHTML = '<small class="text-warning">Esta instalación no tiene horario: use «Todo el día» o un periodo continuo.</small>';
                    return;
                }
                lista.innerHTML = todas.map(function (f, k) {
                    var p = f.split('-');
                    return '<input type="checkbox" class="btn-check" name="bloqueo_franjas[]" id="franja_' + k + '" value="' + esc(f) + '"' +
                           (marcadas.indexOf(f) >= 0 ? ' checked' : '') + '>' +
                           '<label class="btn btn-outline-secondary" for="franja_' + k + '">' + esc(p[0]) + '<span>–' + esc(p[1]) + '</span></label>';
                }).join('');
            }

            function aplicarModo() {
                var f = modo() === 'F';
                secF.hidden = !f; secC.hidden = f;
                /* Lo oculto no se envía ni se valida. */
                secF.querySelectorAll('input').forEach(function (x) { x.disabled = !f; });
                secC.querySelectorAll('input, button').forEach(function (x) { x.disabled = f; });
                if (f) { lista.querySelectorAll('input').forEach(function (x) { x.disabled = todo.checked; }); }
                lista.classList.toggle('ar-bloq--apagado', todo.checked);
                actualizar();
            }

            function actualizar() {
                fin.setCustomValidity(''); fHasta.setCustomValidity('');
                dias[0].setCustomValidity('');

                if (modo() === 'C') {
                    var a = deCampo(ini.value), b = deCampo(fin.value);
                    fin.min = ini.value || '';
                    if (!a || !b) { aviso('', ''); return; }
                    var min = Math.round((b - a) / 60000);
                    if (min <= 0) {
                        fin.setCustomValidity('El fin debe ser posterior al inicio.');
                        aviso('text-danger', '<i class="fas fa-exclamation-circle me-1"></i>El fin debe ser posterior al inicio.');
                        return;
                    }
                    aviso('text-muted', '<i class="fas fa-hourglass-half me-1"></i>Bloqueará ' + duracion(min) + ' seguidas.');
                    return;
                }

                var d1 = deFecha(fDesde.value), d2 = deFecha(fHasta.value);
                fHasta.min = fDesde.value || '';
                var elegidos = Array.prototype.filter.call(dias, function (c) { return c.checked; })
                                    .map(function (c) { return +c.value; });

                /* Días que no caen en el rango: se apagan (no se borran). */
                var enRango = {};
                if (d1 && d2 && d2 >= d1) {
                    for (var d = new Date(d1), n = 0; d <= d2 && n < 7; d.setDate(d.getDate() + 1), n++) { enRango[d.getDay() || 7] = true; }
                }
                dias.forEach(function (c) { c.nextElementSibling.classList.toggle('ar-bloq__fuera', !!(d1 && d2) && !enRango[+c.value]); });

                if (!d1 || !d2) { aviso('', ''); return; }
                if (d2 < d1) {
                    fHasta.setCustomValidity('La fecha final debe ser igual o posterior a la inicial.');
                    aviso('text-danger', '<i class="fas fa-exclamation-circle me-1"></i>La fecha final debe ser igual o posterior a la inicial.');
                    return;
                }
                if (!elegidos.length) {
                    dias[0].setCustomValidity('Marque al menos un día.');
                    aviso('text-danger', '<i class="fas fa-exclamation-circle me-1"></i>Marque al menos un día de la semana.');
                    return;
                }
                var cuantos = 0;
                for (var x = new Date(d1); x <= d2; x.setDate(x.getDate() + 1)) {
                    if (elegidos.indexOf(x.getDay() || 7) >= 0) { cuantos++; }
                }
                if (!cuantos) {
                    dias[0].setCustomValidity('Ningún día marcado cae entre esas fechas.');
                    aviso('text-danger', '<i class="fas fa-exclamation-circle me-1"></i>Entre esas fechas no cae ninguno de los días marcados.');
                    return;
                }
                var fr = franjasMarcadas();
                if (!todo.checked && !fr.length) {
                    aviso('text-warning', '<i class="fas fa-hand-pointer me-1"></i>Elija las franjas que se bloquean, o «Todo el día».');
                    return;
                }
                /* Une las franjas seguidas para el resumen. */
                var tramos = [];
                fr.slice().sort().forEach(function (f) {
                    var p = f.split('-'), u = tramos[tramos.length - 1];
                    if (u && p[0] <= u[1]) { u[1] = p[1] > u[1] ? p[1] : u[1]; } else { tramos.push([p[0], p[1]]); }
                });
                var horas = todo.checked ? 'todo el día' : tramos.map(function (t) { return t[0] + '–' + t[1]; }).join(', ');
                var fechas = +d1 === +d2 ? corta(d1) : corta(d1) + ' → ' + corta(d2);
                aviso('text-body', '<i class="fas fa-lock me-1"></i>Se bloquearán <strong>' + cuantos + (cuantos === 1 ? ' día' : ' días') +
                      '</strong> (' + esc(fechas) + '), ' + esc(horas) + '.');
            }

            /* El botón que abre el modal decide si es alta o edición. */
            modal.addEventListener('show.bs.modal', function (ev) {
                var btn = ev.relatedTarget, ds = (btn && btn.dataset) || {};
                var editar = !!ds.editar;
                var hoy = new Date();

                form.classList.remove('was-validated');
                id.value = ds.id || '0';
                grupo.value = ds.grupo || '0';
                inst.value = editar ? ds.instalacion : (inst.dataset.defecto || '');
                motivo.value = editar ? (ds.motivo || '') : '';
                marcarRadio('bloqueo_tipo', ds.tipo || 'M', 'M');
                marcarRadio('bloqueo_modo', ds.modo || 'F', 'F');

                /* Días y franjas: por defecto hoy, todos los días, sin franjas. */
                fDesde.value = ds.desde || iso(hoy);
                fHasta.value = ds.hasta || iso(hoy);
                var dSel = (ds.dias || '1,2,3,4,5,6,7').split(',');
                dias.forEach(function (c) { c.checked = dSel.indexOf(c.value) >= 0; });
                var fSel = ds.modo === 'F' ? (ds.franjas || '').split(',').filter(Boolean) : [];
                todo.checked = ds.modo === 'F' && !fSel.length;
                extra = fSel;
                pintarFranjas(fSel);

                /* Continuo: la próxima hora en punto, 2 h. */
                if (ds.modo === 'C') {
                    ini.value = ds.inicio; fin.value = ds.fin;
                } else {
                    var a = new Date(); a.setMinutes(0, 0, 0); a.setHours(a.getHours() + 1);
                    ini.value = aCampo(a); fin.value = aCampo(new Date(a.getTime() + 7200000));
                }

                titulo.textContent = editar ? 'Editar bloqueo' : 'Nuevo bloqueo';
                guardar.querySelector('span').textContent = editar ? 'Guardar cambios' : 'Guardar';
                aplicarModo();
            });

            form.addEventListener('change', function (ev) {
                var t = ev.target;
                if (t === inst) { pintarFranjas(franjasMarcadas()); }
                if (t.name === 'bloqueo_modo' || t === todo || t === inst) { aplicarModo(); return; }
                if (t === ini) {
                    /* Al mover el inicio se conserva la duración elegida. */
                    var a = deCampo(ini.value), b = deCampo(fin.value), previo = deCampo(ini.dataset.previo || '');
                    if (a && b && previo) { fin.value = aCampo(new Date(a.getTime() + (b - previo))); }
                    ini.dataset.previo = ini.value;
                }
                if (t === fDesde && fHasta.value && fHasta.value < fDesde.value) { fHasta.value = fDesde.value; }
                actualizar();
            });
            ini.addEventListener('focus', function () { ini.dataset.previo = ini.value; });
            fin.addEventListener('input', actualizar);

            form.addEventListener('click', function (ev) {
                var b = ev.target.closest('button');
                if (!b) { return; }
                if (b.dataset.dias) {
                    var sel = b.dataset.dias.split(',');
                    dias.forEach(function (c) { c.checked = sel.indexOf(c.value) >= 0; });
                    actualizar();
                } else if (b.id === 'franjasTodas' || b.id === 'franjasNinguna') {
                    todo.checked = false;
                    lista.querySelectorAll('input').forEach(function (c) { c.checked = b.id === 'franjasTodas'; });
                    aplicarModo();
                } else if (b.dataset.horas || b.dataset.dia) {
                    var a = deCampo(ini.value) || new Date();
                    if (b.dataset.dia) {
                        a.setHours(0, 0, 0, 0);
                        ini.value = aCampo(a);
                        fin.value = aCampo(new Date(a.getTime() + 86400000));
                    } else {
                        fin.value = aCampo(new Date(a.getTime() + Number(b.dataset.horas) * 3600000));
                    }
                    actualizar();
                }
            });
        })();
        </script>
    <?php endif; ?>

<?php endif; ?>

<style>
.ar-bloq__cabecera { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; }
.ar-bloq__cabecera::before, .ar-bloq__cabecera::after { content: none; display: none; }
.ar-bloq__filtros {
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    padding: .6rem 1rem; border-bottom: 1px solid var(--bs-border-color);
}
.ar-bloq__filtros .form-select { width: auto; max-width: 100%; }
.ar-bloq__pulso { font-size: .55rem; vertical-align: middle; animation: arBloqPulso 1.6s ease-in-out infinite; }
@keyframes arBloqPulso { 50% { opacity: .25; } }
@media (prefers-reduced-motion: reduce) { .ar-bloq__pulso { animation: none; } }

.ar-bloq__lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 380px), 1fr)); gap: .75rem; }
.ar-bloq__item {
    display: grid; grid-template-columns: auto 1fr; gap: .35rem .85rem; align-items: start;
    padding: .8rem; border: 1px solid var(--bs-border-color); border-radius: .5rem;
    border-left-width: 4px;
}
.ar-bloq__item--1 { border-left-color: var(--bs-danger); }
.ar-bloq__item--2 { border-left-color: var(--bs-primary); }
.ar-bloq__item--3 { border-left-color: var(--bs-secondary-border-subtle); opacity: .72; }
.ar-bloq__fecha {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    width: 3.6rem; padding: .35rem 0; border-radius: .45rem; line-height: 1.1; text-transform: uppercase;
}
.ar-bloq__dsem, .ar-bloq__mes { font-size: .68rem; font-weight: 600; letter-spacing: .03em; }
.ar-bloq__dnum { font-size: 1.35rem; font-weight: 700; }
.ar-bloq__datos { display: flex; flex-direction: column; gap: .2rem; min-width: 0; }
.ar-bloq__marcas { display: flex; flex-wrap: wrap; gap: .35rem; }
.ar-bloq__motivo { font-size: .95rem; overflow-wrap: anywhere; }
.ar-bloq__cuando, .ar-bloq__lugar, .ar-bloq__franjas, .ar-bloq__proxima { font-size: .85rem; color: var(--bs-secondary-color); }
.ar-bloq__franjas { display: flex; flex-wrap: wrap; align-items: center; gap: .25rem; }
.ar-bloq__franja {
    padding: 0 .45rem; border-radius: .3rem; font-size: .78rem; font-weight: 600; white-space: nowrap;
    background: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis);
}
.ar-bloq__proxima { font-size: .8rem; }
.ar-bloq__dur {
    display: inline-block; margin-left: .35rem; padding: 0 .4rem; border-radius: 1rem;
    background: var(--bs-tertiary-bg); font-size: .75rem; white-space: nowrap;
}
.ar-bloq__aviso { font-size: .82rem; font-weight: 600; color: var(--bs-warning-text-emphasis); text-decoration: none; }
.ar-bloq__aviso:hover { text-decoration: underline; }
.ar-bloq__acciones { grid-column: 1 / -1; display: flex; justify-content: flex-end; gap: .5rem; margin-top: .2rem; }
.ar-bloq__acciones form { margin: 0; }
/* El gris de outline-secondary, en oscuro, parecía un botón deshabilitado. */
.ar-bloq__acciones .btn-outline-secondary { --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-secondary-color); }
.ar-bloq legend { padding: 0; float: none; width: auto; }

.ar-bloq__vacio {
    display: flex; flex-direction: column; align-items: center; text-align: center; gap: .2rem;
    padding: 2rem 1rem; color: var(--bs-secondary-color);
}
.ar-bloq__vacio > i { color: var(--bs-success); }
.ar-bloq__vacio strong { color: var(--bs-body-color); }

/* ---- Modal ---- */
.ar-bloq__tipos { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .5rem; }
.ar-bloq__tipos .btn { display: flex; align-items: center; justify-content: center; gap: .4rem; font-size: .85rem; }
.ar-bloq__modos { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; }
.ar-bloq__modos .btn { display: flex; align-items: center; gap: .6rem; text-align: left; padding: .55rem .75rem; }
.ar-bloq__modos .btn > i { font-size: 1.1rem; width: 1.2rem; text-align: center; }
.ar-bloq__modos .btn span { display: flex; flex-direction: column; line-height: 1.2; }
.ar-bloq__modos .btn small { font-size: .75rem; opacity: .8; }
.ar-bloq__seccion { padding: .85rem; margin-bottom: .75rem; border-radius: .5rem; background: var(--bs-tertiary-bg); }
.ar-bloq__rotulo { display: flex; justify-content: space-between; align-items: baseline; gap: .5rem; margin-bottom: .4rem; }
.ar-bloq__atajos { display: flex; gap: .75rem; }
.ar-bloq__atajos .btn { font-size: .8rem; text-decoration: none; }
.ar-bloq__dias { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: .35rem; }
.ar-bloq__dias .btn { padding: .4rem 0; font-size: .82rem; font-weight: 600; }
.ar-bloq__dias .btn.ar-bloq__fuera { opacity: .4; text-decoration: line-through; }
.ar-bloq__franjas-lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(5.6rem, 1fr)); gap: .35rem; }
.ar-bloq__franjas-lista .btn { padding: .35rem .25rem; font-size: .82rem; font-variant-numeric: tabular-nums; }
.ar-bloq__franjas-lista .btn span { opacity: .75; }
.ar-bloq__franjas-lista > small { grid-column: 1 / -1; }
.ar-bloq--apagado { opacity: .45; }
/* Elegido = azul y con marca; sin elegir = blanco. El gris se leía como
   «deshabilitado». */
.ar-bloq__dias .btn, .ar-bloq__franjas-lista .btn, .ar-bloq__tipos .btn {
    --bs-btn-color: var(--bs-body-color); --bs-btn-hover-color: var(--bs-body-color);
    --bs-btn-hover-bg: var(--bs-tertiary-bg); --bs-btn-hover-border-color: var(--bs-primary);
    background: var(--bs-body-bg); border-color: var(--bs-border-color);
}
.ar-bloq__dias .btn-check:checked + .btn, .ar-bloq__franjas-lista .btn-check:checked + .btn, .ar-bloq__tipos .btn-check:checked + .btn {
    background: var(--bs-primary); border-color: var(--bs-primary); color: #fff;
}
.ar-bloq__franjas-lista .btn-check:checked + .btn span { opacity: 1; }
.ar-bloq__franjas-lista .btn-check:checked + .btn::before { content: "\2713\00a0"; font-weight: 700; }
.ar-bloq__franjas-lista .btn-check:disabled + .btn { pointer-events: none; }

/* El formulario está entre .modal-content y .modal-body: sin esto el
   desplazamiento interno del modal no funciona y el pie queda cortado. */
#formBloqueo { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
#formBloqueo .modal-body { overflow-y: auto; }
.ar-bloq__rapidos { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem; }
.ar-bloq__rapidos .btn { border: 1px solid var(--bs-border-color); }
.ar-bloq__resumen { min-height: 1.25rem; font-size: .875rem; }

@media (max-width: 575.98px) {
    .ar-bloq__tipos .btn { flex-direction: column; gap: .2rem; padding: .45rem .25rem; font-size: .78rem; }
    .ar-bloq__modos .btn { flex-direction: column; text-align: center; gap: .25rem; padding: .5rem .35rem; }
    .ar-bloq__modos .btn span { align-items: center; }
    .ar-bloq__modos .btn small { display: none; }
    .ar-bloq__filtros .form-select { flex: 1 1 100%; }
    .ar-bloq__acciones .btn { min-height: 40px; }
    .ar-bloq__seccion { padding: .65rem; }
    .ar-bloq__dias .btn { font-size: .74rem; }
    .ar-bloq__franjas-lista { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
</style>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
