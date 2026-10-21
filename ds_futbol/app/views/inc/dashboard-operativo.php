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
foreach ($horarios as $h) {
    foreach (explode(',', (string)$h['dias']) as $d) {
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

$totalAlumnos = 0;
foreach ($horarios as $h) { $totalAlumnos += (int)$h['alumnos']; }

$nombreDia   = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];
$nombreMes   = [1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo',
                6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre',
                10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre'];

$puedeRegistrar = puede('ver', 'asistencia');
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>

<style>
.op-dia {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    width:38px; height:44px; border-radius:.35rem; font-size:.75rem; line-height:1.1;
    border:1px solid #dee2e6; background:#fff; color:#6c757d;
}
.op-dia .op-num { font-weight:700; font-size:.9rem; }
.op-dia.registrada { background:#28a745; border-color:#28a745; color:#fff; }
.op-dia.pendiente  { background:#ffc107; border-color:#ffc107; color:#212529; }
.op-dia.proxima    { background:#fff; border-color:#adb5bd; color:#495057; }
.op-dia.sin-clase  { background:#f8f9fa; border-color:#e9ecef; color:#ced4da; }
.op-dia.hoy        { box-shadow:0 0 0 2px #007bff; }
.op-leyenda span   { display:inline-flex; align-items:center; margin-right:1rem; font-size:.8rem; }
.op-leyenda i      { width:12px; height:12px; border-radius:3px; margin-right:.35rem; display:inline-block; }
.op-chip-dia {
    display:inline-block; width:20px; height:20px; line-height:20px; text-align:center;
    border-radius:50%; font-size:.7rem; font-weight:700; margin-right:2px;
    background:#e9ecef; color:#adb5bd;
}
.op-chip-dia.activo { background:#17a2b8; color:#fff; }
</style>

<div class="card card-outline card-primary">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-clipboard-list me-2"></i>
            <?php echo $vistaPropia ? 'Mis horarios y asistencia' : 'Horarios de mis sedes'; ?>
        </h3>
        <div class="card-tools text-muted" style="font-size:.85rem;">
            <?php echo $nombreMes[$mesActual] . ' ' . $anioActual; ?>
        </div>
    </div>

    <div class="card-body">

        <?php if (!$horarios): ?>

            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-1"></i>
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

            <div class="row">
                <div class="col-md-3 col-6 mb-3">
                    <div class="info-box">
                        <span class="info-box-icon text-bg-info shadow-sm"><i class="fas fa-calendar-alt"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Horarios</span>
                            <span class="info-box-number"><?php echo count($horarios); ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="info-box">
                        <span class="info-box-icon text-bg-primary shadow-sm"><i class="fas fa-users"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Alumnos a cargo</span>
                            <span class="info-box-number"><?php echo $totalAlumnos; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="info-box">
                        <span class="info-box-icon text-bg-success shadow-sm"><i class="fas fa-check"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Días con asistencia</span>
                            <span class="info-box-number"><?php echo $conRegistro; ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-3">
                    <div class="info-box">
                        <span class="info-box-icon shadow-sm <?php echo $pendientes ? 'text-bg-warning' : 'text-bg-secondary'; ?>">
                            <i class="fas fa-exclamation"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">Días sin registrar</span>
                            <span class="info-box-number"><?php echo $pendientes; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Calendario del mes: un vistazo a qué días quedó registrada la lista -->
            <h6 class="text-muted mb-2">
                Asistencia de <?php echo $nombreMes[$mesActual]; ?>
            </h6>
            <div class="d-flex flex-wrap" style="gap:4px;">
                <?php foreach ($calendario as $dia => $info): ?>
                    <div class="op-dia <?php echo $info['estado']; ?><?php echo $info['hoy'] ? ' hoy' : ''; ?>"
                         title="<?php
                            echo $dia . ' · ' . [
                                'registrada' => 'asistencia registrada',
                                'pendiente'  => 'con clase, sin registrar',
                                'proxima'    => 'clase próxima',
                                'sin-clase'  => 'sin clase',
                            ][$info['estado']];
                         ?>">
                        <span class="op-num"><?php echo $dia; ?></span>
                        <span><?php echo $nombreDia[$info['semana']]; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="op-leyenda mt-2 text-muted">
                <span><i style="background:#28a745;"></i> Registrada</span>
                <span><i style="background:#ffc107;"></i> Pendiente</span>
                <span><i style="background:#fff;border:1px solid #adb5bd;"></i> Próxima</span>
                <span><i style="background:#f8f9fa;border:1px solid #e9ecef;"></i> Sin clase</span>
            </div>

            <hr>

            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Sede</th>
                            <th>Horario</th>
                            <th style="width:130px;">Días</th>
                            <th style="width:110px;">Hora</th>
                            <th>Lugar</th>
                            <?php if (!$vistaPropia): ?><th>Profesor</th><?php endif; ?>
                            <th class="text-center" style="width:80px;">Alumnos</th>
                            <th class="text-center" style="width:110px;">Último registro</th>
                            <th style="width:150px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($horarios as $fila):
                        $id     = (int)$fila['horario_id'];
                        $suyos  = $asistencia[$id] ?? [];
                        $ultimo = $suyos ? max(array_keys($suyos)) : 0;
                        $dias   = array_map('intval', array_filter(explode(',', (string)$fila['dias']), 'strlen'));
                    ?>
                        <tr>
                            <td><?php echo $h($fila['sede_nombre']); ?></td>
                            <td>
                                <?php echo $h($fila['horario_nombre']); ?>
                                <?php if ($fila['horario_detalle']): ?>
                                    <br><small class="text-muted"><?php echo $h($fila['horario_detalle']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php for ($d = 1; $d <= 5; $d++): ?>
                                    <span class="op-chip-dia <?php echo in_array($d, $dias, true) ? 'activo' : ''; ?>">
                                        <?php echo $nombreDia[$d]; ?>
                                    </span>
                                <?php endfor; ?>
                            </td>
                            <td>
                                <?php echo substr((string)$fila['hora_inicio'], 0, 5); ?>
                                – <?php echo substr((string)$fila['hora_fin'], 0, 5); ?>
                            </td>
                            <td><small><?php echo $h($fila['lugares']); ?></small></td>
                            <?php if (!$vistaPropia): ?>
                                <td><small><?php echo $h($fila['profesores'] ?? ''); ?></small></td>
                            <?php endif; ?>
                            <td class="text-center">
                                <span class="badge text-bg-<?php echo (int)$fila['alumnos'] ? 'primary' : 'secondary'; ?>">
                                    <?php echo (int)$fila['alumnos']; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if ($ultimo): ?>
                                    <span class="text-success"><?php echo $ultimo; ?> de <?php echo $nombreMes[$mesActual]; ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?php echo APP_URL; ?>asistenciaVerHorario/<?php echo $id; ?>/"
                                   class="btn btn-sm btn-outline-secondary" target="_blank">
                                    <i class="fas fa-eye"></i> Ver
                                </a>
                                <?php if ($puedeRegistrar): ?>
                                    <a href="<?php echo APP_URL; ?>asistencia/" class="btn btn-sm btn-primary">
                                        <i class="fas fa-clipboard-check"></i> Registrar
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</div>
