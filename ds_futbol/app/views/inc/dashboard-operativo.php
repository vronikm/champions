<?php
/*
|--------------------------------------------------------------------------
| Panel operativo del dashboard
|--------------------------------------------------------------------------
| Lo que necesita quien está en la cancha, no quien mira la caja: qué
| horarios lleva, cuántos alumnos hay en cada uno y en qué días del mes ya
| pasó lista.
|
| El alcance sale de los datos, no del número de rol:
|   · Si el empleado tiene horarios asignados, ve los suyos.
|   · Si no tiene ninguno, ve los de las sedes que le hayan asignado. Es el
|     caso de quien acompaña la operación de una sede sin dar clase.
|
| Espera recibir $insDashboard ya instanciado.
|
| Desde el 2026-10-23 los colores salen del tema (futbol.css, «PANEL
| OPERATIVO»): antes eran fijos en un <style> propio y en el tema claro
| los días «sin clase» casi no se veían; «pendiente» usaba el amarillo de
| la marca y el mes entero parecía un botón. El calendario es una
| cuadrícula de semanas (L a D) y la tabla, fichas en el teléfono.
*/

$empleadoSesion = empleado_actual();
$usuarioSesion  = usuario_actual_id();

$horarios     = $empleadoSesion > 0 ? $insDashboard->horariosDelEmpleado($empleadoSesion) : [];
$vistaPropia  = !empty($horarios);
$sedesUsuario = [];

if (!$vistaPropia) {
    $sedesUsuario = $insDashboard->sedesDelUsuario($usuarioSesion);
    $ids = array_map('intval', array_column($sedesUsuario, 'sede_id'));
    $horarios = $ids ? $insDashboard->horariosDeSedes($ids) : [];
}

$idsHorario = array_map('intval', array_column($horarios, 'horario_id'));

/* Mes en curso. La asistencia se guarda por AAAAMM en una fila por alumno. */
$hoy      = (int)date('j');
$mesActual = (int)date('n');
$anioActual = (int)date('Y');
$aniomes  = (int)date('Ym');
$diasMes  = (int)date('t');

$asistencia = $idsHorario ? $insDashboard->diasConAsistencia($idsHorario, $aniomes) : [];

/* Días de la semana en los que hay clase, según las franjas del horario. */
$diasProgramados = [];
foreach ($horarios as $fila) {
    foreach (explode(',', (string)$fila['dias']) as $d) {
        $d = (int)trim($d);
        if ($d >= 1 && $d <= 7) { $diasProgramados[$d] = true; }
    }
}

/* Estado de cada día del mes. */
$registrados = [];
foreach ($asistencia as $porHorario) {
    foreach (array_keys($porHorario) as $dia) { $registrados[$dia] = true; }
}

$calendario = [];
$conRegistro = 0;
$pendientes  = 0;

for ($d = 1; $d <= $diasMes; $d++) {
    $semana    = (int)date('N', mktime(0, 0, 0, $mesActual, $d, $anioActual));
    $hayClase  = isset($diasProgramados[$semana]);
    $registrado = isset($registrados[$d]);

    if ($registrado)          { $estado = 'registrada'; $conRegistro++; }
    elseif (!$hayClase)       { $estado = 'sin-clase'; }
    elseif ($d > $hoy)        { $estado = 'proxima'; }
    else                      { $estado = 'pendiente'; $pendientes++; }

    $calendario[$d] = ['estado' => $estado, 'semana' => $semana, 'hoy' => ($d === $hoy)];
}
/* Huecos antes del día 1, para que cada día caiga bajo su columna (L a D). */
$huecos = $calendario ? $calendario[1]['semana'] - 1 : 0;

$totalAlumnos = 0;
foreach ($horarios as $fila) { $totalAlumnos += (int)$fila['alumnos']; }

$nombreDia   = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
$nombreDiaLargo = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
$nombreMes   = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo',
                6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
                10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];
$ESTADOS = [
    'registrada' => 'asistencia registrada',
    'pendiente'  => 'con clase, sin registrar',
    'proxima'    => 'clase próxima',
    'sin-clase'  => 'sin clase',
];

$puedeRegistrar = puede('ver', 'asistencia');
/* Se decodifica antes de escapar: limpiarHtml() guarda lo escrito ya escapado. */
$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
?>

<div class="card card-outline card-primary ds-op">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h3 class="card-title me-auto">
            <i class="fas fa-clipboard-list me-2" aria-hidden="true"></i>
            <?php echo $vistaPropia ? 'Mis horarios y asistencia' : 'Horarios de mis sedes'; ?>
        </h3>
        <span class="text-body-secondary small"><?php echo ucfirst($nombreMes[$mesActual]) . ' ' . $anioActual; ?></span>
    </div>

    <div class="card-body">

        <?php if (!$horarios): ?>

            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
                <?php if ($empleadoSesion === 0): ?>
                    Su usuario no está vinculado a una ficha de empleado, así que no se pueden
                    determinar sus horarios. Solicite al administrador que haga la vinculación.
                <?php elseif (!$sedesUsuario): ?>
                    Todavía no tiene horarios asignados ni sedes a su cargo.
                <?php else: ?>
                    Las sedes a su cargo no tienen horarios activos.
                <?php endif; ?>
            </div>

        <?php else: ?>

            <!-- =================== Cifras del mes =================== -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3">
                    <div class="info-box mb-0">
                        <span class="info-box-icon text-bg-info shadow-sm"><i class="fas fa-calendar-alt" aria-hidden="true"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Horarios</span>
                            <span class="info-box-number"><?php echo count($horarios); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="info-box mb-0">
                        <span class="info-box-icon text-bg-primary shadow-sm"><i class="fas fa-users" aria-hidden="true"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Alumnos a cargo</span>
                            <span class="info-box-number"><?php echo $totalAlumnos; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="info-box mb-0">
                        <span class="info-box-icon text-bg-success shadow-sm"><i class="fas fa-check" aria-hidden="true"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Días con asistencia</span>
                            <span class="info-box-number"><?php echo $conRegistro; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="info-box mb-0">
                        <span class="info-box-icon shadow-sm <?php echo $pendientes ? 'text-bg-danger' : 'text-bg-secondary'; ?>">
                            <i class="fas fa-exclamation" aria-hidden="true"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Días sin registrar</span>
                            <span class="info-box-number"><?php echo $pendientes; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- =================== Calendario del mes =================== -->
            <div class="ds-op__mes">
                <div class="ds-op__calendario">
                    <h4 class="ds-op__titulo">Asistencia de <?php echo $nombreMes[$mesActual]; ?></h4>
                    <div class="ds-op__semana" aria-hidden="true">
                        <?php foreach ($nombreDia as $letra): ?><span><?php echo $letra; ?></span><?php endforeach; ?>
                    </div>
                    <ol class="ds-op__dias" aria-label="Días de <?php echo $nombreMes[$mesActual]; ?>">
                        <?php for ($i = 0; $i < $huecos; $i++): ?><li class="ds-op__hueco" aria-hidden="true"></li><?php endfor; ?>
                        <?php foreach ($calendario as $dia => $info): ?>
                            <li class="ds-op__dia ds-op__dia--<?php echo $info['estado']; ?><?php echo $info['hoy'] ? ' ds-op__dia--hoy' : ''; ?>"
                                title="<?php echo $nombreDiaLargo[$info['semana']] . ' ' . $dia . ' · ' . $ESTADOS[$info['estado']]; ?>"
                                <?php echo $info['hoy'] ? 'aria-current="date"' : ''; ?>>
                                <span class="visually-hidden"><?php echo $nombreDiaLargo[$info['semana']]; ?></span>
                                <span class="ds-op__num"><?php echo $dia; ?></span>
                                <span class="visually-hidden">: <?php echo $ESTADOS[$info['estado']]; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <ul class="ds-op__leyenda list-unstyled">
                    <li><span class="ds-op__muestra ds-op__dia--registrada"></span>Registrada</li>
                    <li><span class="ds-op__muestra ds-op__dia--pendiente"></span>Sin registrar</li>
                    <li><span class="ds-op__muestra ds-op__dia--proxima"></span>Próxima clase</li>
                    <li><span class="ds-op__muestra ds-op__dia--sin-clase"></span>Sin clase</li>
                    <li><span class="ds-op__muestra ds-op__dia--proxima ds-op__dia--hoy"></span>Hoy</li>
                </ul>
            </div>

            <!-- =================== Horarios =================== -->
            <h4 class="ds-op__titulo mt-4">Horarios</h4>
            <table class="table table-hover align-middle ds-crud__tabla mb-0">
                <thead>
                    <tr>
                        <th>Horario</th>
                        <th>Días y hora</th>
                        <?php if (!$vistaPropia): ?><th class="ds-col-opcional">Profesor</th><?php endif; ?>
                        <th class="text-center">Alumnos</th>
                        <th>Último registro</th>
                        <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($horarios as $fila):
                    $id     = (int)$fila['horario_id'];
                    $suyos  = $asistencia[$id] ?? [];
                    $ultimo = $suyos ? max(array_keys($suyos)) : 0;
                    $dias   = array_map('intval', array_filter(explode(',', (string)$fila['dias']), 'strlen'));
                    $sitio  = implode(' · ', array_filter([$h($fila['sede_nombre']), $h($fila['lugares'])], 'strlen'));
                ?>
                    <tr>
                        <td class="ds-ficha__principal">
                            <div class="ds-ficha__nombre"><?php echo $h($fila['horario_nombre']); ?>
                                <?php if ($fila['horario_detalle']): ?><span class="fw-normal text-body-secondary">· <?php echo $h($fila['horario_detalle']); ?></span><?php endif; ?>
                            </div>
                            <?php if ($sitio !== ''): ?>
                                <div class="ds-ficha__meta"><i class="fas fa-location-dot" aria-hidden="true"></i> <?php echo $sitio; ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="ds-ficha__dato" data-etiqueta="Días y hora">
                            <span class="ds-op__chips">
                                <?php foreach ($dias as $d): if (!isset($nombreDia[$d])) { continue; } ?>
                                    <abbr title="<?php echo $nombreDiaLargo[$d]; ?>" class="ds-op__chip"><?php echo $nombreDia[$d]; ?></abbr>
                                <?php endforeach; ?>
                            </span>
                            <span class="d-block small text-body-secondary">
                                <?php echo substr((string)$fila['hora_inicio'], 0, 5); ?> – <?php echo substr((string)$fila['hora_fin'], 0, 5); ?>
                            </span>
                        </td>
                        <?php if (!$vistaPropia): ?>
                            <td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Profesor"><?php echo $h($fila['profesores'] ?? ''); ?></td>
                        <?php endif; ?>
                        <td class="ds-ficha__dato text-center" data-etiqueta="Alumnos">
                            <span class="badge rounded-pill <?php echo (int)$fila['alumnos'] ? 'text-bg-primary' : 'text-bg-secondary'; ?>"><?php echo (int)$fila['alumnos']; ?></span>
                        </td>
                        <td class="ds-ficha__dato" data-etiqueta="Último registro">
                            <?php if ($ultimo): ?>
                                <span class="text-success-emphasis"><i class="fas fa-check me-1" aria-hidden="true"></i><?php echo $ultimo . ' de ' . $nombreMes[$mesActual]; ?></span>
                            <?php else: ?>
                                <span class="text-body-secondary">Sin registro este mes</span>
                            <?php endif; ?>
                        </td>
                        <td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">
                            <?php if ($puedeRegistrar): ?>
                                <a href="<?php echo APP_URL; ?>asistenciaAlumno/<?php echo $id; ?>/" class="btn btn-primary btn-sm ds-ficha__primario">
                                    <i class="fas fa-clipboard-check me-1" aria-hidden="true"></i>Registrar
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo APP_URL; ?>asistenciaVerHorario/<?php echo $id; ?>/" class="btn btn-secondary btn-sm" title="Ver el horario" aria-label="Ver el horario <?php echo $h($fila['horario_nombre']); ?>">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </a>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
    </div>
</div>
