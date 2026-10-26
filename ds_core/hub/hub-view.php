<?php
/*
| Hub de aplicaciones DigiSports
|--------------------------------------------------------------------------
| Pantalla de entrada al ecosistema. Es una pagina autonoma: no carga el
| tema administrativo de ningun modulo, porque su proposito es presentar
| DigiSports como un lanzador de aplicaciones, no como un menu.
|
| Datos y catalogo de modulos: ds_core/hub/hubController.php y
| ds_core/modulos.php
*/

use hub\hubController;

$insHub = new hubController();

/*
| Lo que ve cada quien. La información gerencial (ingresos, alumnos y sedes
| de toda la escuela, pagos, cobranza) sólo con vistaGerencial(): el mismo
| criterio que el dashboard de Fútbol. Un profesor ve su día: sus horarios,
| sus alumnos y la toma de asistencia (miDia).
*/
$gerencial = $insHub->vistaGerencial();
$modulos   = $insHub->modulos();
$resumen   = $gerencial ? $insHub->resumenHoy() : [];
$actividad = $gerencial ? $insHub->actividadReciente() : [];
$avisos    = $gerencial ? $insHub->requiereAtencion() : [];
$miDia     = $gerencial ? null : $insHub->miDia();
$puedeTomar = usuario_tiene_permiso('asistencia', 'basketball');
$puedePanel = usuario_tiene_permiso('dashboard', 'basketball');

/* El nombre de la persona; si no tiene ficha, el nombre de usuario. */
$nombreUsuario = ds_nombre_usuario();
$primerNombre  = trim(explode(' ', trim((string)$nombreUsuario))[0] ?? '');

$fotoUsuario = !empty($_SESSION['foto'])
    ? media_url('empleado', $_SESSION['foto'])
    : DS_HUB_URL . 'ds_core/assets/img/avatar.png';

/* Vendor compartido: FontAwesome vive todavia en el modulo Basketball.
   Cuando haya un segundo modulo conviene subirlo a ds_core/assets/. */
$vendorCss = DS_HUB_URL . 'ds_core/assets/vendor/fontawesome6/css/all.min.css';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo DS_HUB_NAME; ?> | Hub</title>

    <link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
    <?php /* Tipografia servida desde el propio servidor. Ver el comentario
             de ds_core/assets/css/fuentes.css. */ ?>
    <?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo $vendorCss; ?>">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
</head>
<body class="ds-body">

    <!-- ============ Cabecera ============ -->
    <header class="ds-header">
        <div class="ds-container ds-header__inner">

            <a href="<?php echo DS_HUB_URL; ?>" class="ds-brand">
                <img src="<?php echo DS_HUB_URL; ?>ds_core/assets/img/icon_cz98px.png"
                     alt="<?php echo DS_HUB_NAME; ?>" class="ds-brand__logo">
                <span>
                    <p class="ds-brand__name"><?php echo DS_HUB_NAME; ?></p>
                    <p class="ds-brand__tagline"><?php echo DS_TAGLINE; ?></p>
                </span>
            </a>

            <div class="ds-header__actions">
                <?php if (!empty($avisos)): ?>
                    <span class="ds-iconbtn" title="<?php echo count($avisos); ?> avisos requieren tu atención">
                        <i class="fas fa-bell"></i>
                    </span>
                <?php endif; ?>

                <div class="ds-user">
                    <img src="<?php echo $fotoUsuario; ?>" alt="" class="ds-user__avatar">
                    <span>
                        <span class="ds-user__name"><?php echo htmlspecialchars((string)$nombreUsuario, ENT_QUOTES, 'UTF-8'); ?></span><br>
                        <span class="ds-user__role"><?php echo htmlspecialchars((string)($_SESSION['sede'] ?: ($gerencial ? 'Todas las sedes' : '')), ENT_QUOTES, 'UTF-8'); ?></span>
                    </span>
                </div>

                <?php /* Acceso a la seguridad de la propia cuenta. Va en la
                         cabecera del Hub porque es la unica pantalla por la
                         que pasan todos los usuarios, con independencia de
                         los modulos que tengan concedidos. */ ?>
                <a href="<?php echo DS_HUB_URL; ?>?p=seguridad" class="ds-iconbtn"
                   title="Mi seguridad<?php echo dosf_activo(usuario_actual_id())
                       ? ' · verificación en dos pasos activa'
                       : ' · sin verificación en dos pasos'; ?>">
                    <i class="fas fa-shield-alt"
                       <?php if (dosf_activo(usuario_actual_id())): ?>style="color:#34d399;"<?php endif; ?>></i>
                </a>

                <a href="<?php echo DS_FUTBOL_URL; ?>logOut/" class="ds-iconbtn" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>

        </div>
    </header>

    <main class="ds-container">

        <!-- ============ Saludo ============ -->
        <section class="ds-greeting">
            <h1 class="ds-greeting__title">
                <?php echo $insHub->saludo(); ?><?php echo $primerNombre ? ', ' . htmlspecialchars($primerNombre, ENT_QUOTES, 'UTF-8') : ''; ?> 👋
            </h1>
            <p class="ds-greeting__subtitle"><?php echo $gerencial ? '¿Qué quieres gestionar hoy?' : 'Tu día en la cancha'; ?></p>
        </section>

        <!-- ============ Aplicaciones ============ -->
        <section class="ds-apps">
            <?php foreach ($modulos as $m): ?>

                <div class="ds-app <?php echo $m['activo'] ? 'ds-app--activo' : 'ds-app--proximo'; ?>"
                     style="--acento: <?php echo $m['acento']; ?>;">

                    <?php if (!$m['activo']): ?>
                        <span class="ds-badge-proximo">Próximamente</span>
                    <?php endif; ?>

                    <span class="ds-app__icon"><i class="<?php echo $m['icono']; ?>"></i></span>

                    <h2 class="ds-app__name"><?php echo $m['nombre']; ?></h2>
                    <p class="ds-app__tagline"><?php echo $m['tagline']; ?></p>

                    <?php if (!empty($m['metricas'])): ?>
                        <div class="ds-app__metrics">
                            <?php foreach ($m['metricas'] as $met): ?>
                                <span>
                                    <span class="ds-metric__value"><?php echo $met['valor']; ?></span>
                                    <span class="ds-metric__label"><?php echo $met['etiqueta']; ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($m['activo']): ?>
                        <span class="ds-app__cta">Explorar <i class="fas fa-arrow-right"></i></span>

                        <?php /* Accesos rapidos: por encima del enlace superpuesto
                                 para que cada uno conserve su propio destino. */ ?>
                        <?php if (!empty($m['accesos'])): ?>
                            <div class="ds-app__quick">
                                <?php foreach ($m['accesos'] as $ac): ?>
                                    <a href="<?php echo ($m['base'] ?? $m['url']) . $ac['ruta']; ?>" class="ds-chip">
                                        <i class="<?php echo $ac['icono']; ?>"></i><?php echo $ac['texto']; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php /* Enlace que cubre toda la tarjeta, sin anidar <a>. */ ?>
                        <a href="<?php echo $m['url']; ?>"
                           class="ds-app__stretch"
                           aria-label="Abrir <?php echo DS_HUB_NAME . ' ' . $m['nombre']; ?>"></a>
                    <?php endif; ?>

                </div>

            <?php endforeach; ?>
        </section>

        <div class="ds-divider"></div>

        <?php if ($gerencial): ?>
        <!-- ============ Resumen ============ -->
        <div class="ds-section__head">
            <h2 class="ds-section__title">Resumen de hoy</h2>
            <a href="<?php echo DS_FUTBOL_URL; ?>estadisticas/" class="ds-link">Ver analítica →</a>
        </div>

        <section class="ds-stats">
            <?php foreach ($resumen as $r): ?>
                <div class="ds-stat">
                    <div class="ds-stat__value"><?php echo $r['valor']; ?></div>
                    <div class="ds-stat__label"><?php echo $r['etiqueta']; ?></div>
                </div>
            <?php endforeach; ?>
        </section>

        <!-- ============ Actividad y avisos ============ -->
        <section class="ds-columns">

            <div class="ds-panel">
                <div class="ds-section__head">
                    <h2 class="ds-section__title">Actividad reciente</h2>
                    <a href="<?php echo DS_FUTBOL_URL; ?>pagosList/" class="ds-link">Ver todo →</a>
                </div>

                <?php if (empty($actividad)): ?>
                    <p class="ds-vacio">Todavía no hay movimientos registrados.</p>
                <?php else: ?>
                    <ul class="ds-list">
                        <?php foreach ($actividad as $a): ?>
                            <li>
                                <span class="ds-dot ds-dot--<?php echo $a['estado']; ?>"></span>
                                <span>
                                    <span class="ds-item__title"><?php echo htmlspecialchars($a['titulo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="ds-item__meta"><?php echo htmlspecialchars($a['meta'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </span>
                                <span class="ds-item__value"><?php echo $a['valor']; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="ds-panel">
                <div class="ds-section__head">
                    <h2 class="ds-section__title">Requiere tu atención</h2>
                </div>

                <?php if (empty($avisos)): ?>
                    <p class="ds-vacio">Todo en orden. No hay pendientes.</p>
                <?php else: ?>
                    <?php foreach ($avisos as $av): ?>
                        <a href="<?php echo $av['url']; ?>" class="ds-alerta">
                            <span class="ds-dot ds-dot--<?php echo $av['estado']; ?>"></span>
                            <span>
                                <span class="ds-item__title"><?php echo htmlspecialchars($av['titulo'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="ds-item__meta"><?php echo htmlspecialchars($av['meta'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                            <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--ds-text-muted);"></i>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </section>

        <?php else: ?>
        <?php
        /* ============ El día de quien da clase ============
           Sólo sus horarios, sus alumnos y la toma de asistencia: nada de
           ingresos, alumnos o sedes de toda la escuela, ni cobranza. */
        $ESTADO_TOMA = [
            'tomada'      => ['success', 'Asistencia tomada'],
            'curso'       => ['warning', 'Asistencia a medias'],
            'pendiente'   => ['danger',  'Falta tomar asistencia'],
            'sin-alumnos' => ['warning', 'Sin alumnos asignados'],
        ];
        $clasesHoy = $miDia ? array_values(array_filter($miDia['horarios'], static fn($x) => $x['clase_hoy'])) : [];
        $hp = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
        ?>

        <?php if ($miDia === null || !$miDia['horarios']): ?>
            <section class="ds-panel">
                <p class="ds-vacio" style="margin:0;">
                    <?php echo $miDia === null
                        ? 'Su usuario no está vinculado a una ficha de empleado. Pida a administración que lo vincule para ver sus horarios.'
                        : 'Todavía no tiene horarios asignados. Cuando se los asignen, aquí verá sus clases y la toma de asistencia.'; ?>
                </p>
            </section>
        <?php else: ?>

        <!-- ============ Mi día ============ -->
        <div class="ds-section__head">
            <h2 class="ds-section__title">Mi día</h2>
            <?php if ($puedePanel): ?>
                <a href="<?php echo DS_FUTBOL_URL; ?>dashboard/" class="ds-link">Ver mi panel →</a>
            <?php endif; ?>
        </div>

        <section class="ds-stats">
            <div class="ds-stat"><div class="ds-stat__value"><?php echo count($miDia['horarios']); ?></div><div class="ds-stat__label">Mis horarios</div></div>
            <div class="ds-stat"><div class="ds-stat__value"><?php echo (int)$miDia['alumnos']; ?></div><div class="ds-stat__label">Mis alumnos</div></div>
            <div class="ds-stat"><div class="ds-stat__value"><?php echo (int)$miDia['clasesHoy']; ?></div><div class="ds-stat__label">Clases hoy</div></div>
            <div class="ds-stat"><div class="ds-stat__value"><?php echo (int)$miDia['sinRegistro']; ?></div><div class="ds-stat__label">Días sin asistencia este mes</div></div>
        </section>

        <section class="ds-columns">

            <!-- Mis clases de hoy, con su toma de asistencia -->
            <div class="ds-panel">
                <div class="ds-section__head">
                    <h2 class="ds-section__title">Mis clases de hoy</h2>
                    <?php if ($puedeTomar): ?>
                        <a href="<?php echo DS_FUTBOL_URL; ?>asistencia/" class="ds-link">Todos mis horarios →</a>
                    <?php endif; ?>
                </div>

                <?php if (!$clasesHoy):
                    /* Sin clases hoy: la próxima, que es lo que se quiere saber. */
                    $DIAS_LARGOS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];
                    $proxima = []; $cuando = '';
                    for ($k = 1; $k <= 7 && !$proxima; $k++) {
                        $t = strtotime("+$k day");
                        $proxima = array_values(array_filter($miDia['horarios'], static fn($x) => in_array((int)date('N', $t), $x['semana'], true)));
                        if ($proxima) { $cuando = ($k === 1 ? 'mañana, ' : '') . $DIAS_LARGOS[(int)date('N', $t)] . ' ' . (int)date('j', $t); }
                    }
                ?>
                    <p class="ds-vacio">Hoy no tiene clases.<?php if ($proxima): ?> La próxima es <?php echo $cuando; ?>:<?php endif; ?></p>
                    <?php foreach ($proxima as $c):
                        $rango = $c['inicio'] ? substr((string)$c['inicio'], 0, 5) . ' – ' . substr((string)$c['fin'], 0, 5) : ''; ?>
                        <div class="ds-alerta">
                            <span class="ds-dot" style="background:var(--ds-text-muted);"></span>
                            <span>
                                <span class="ds-item__title"><?php echo $rango !== '' ? $rango . ' · ' : ''; ?><?php echo $hp($c['horario_nombre']); ?></span>
                                <span class="ds-item__meta"><?php echo implode(' · ', array_filter([$hp($c['lugares']), $c['alumnos'] . ($c['alumnos'] === 1 ? ' alumno' : ' alumnos')], 'strlen')); ?></span>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($clasesHoy as $c):
                        [$color, $texto] = $ESTADO_TOMA[$c['estado']] ?? ['warning', ''];
                        $rango = $c['inicio'] ? substr((string)$c['inicio'], 0, 5) . ' – ' . substr((string)$c['fin'], 0, 5) : '';
                        $meta  = implode(' · ', array_filter([$hp($c['lugares']),
                                  $c['alumnos'] . ($c['alumnos'] === 1 ? ' alumno' : ' alumnos'),
                                  $c['marcados'] > 0 ? $c['marcados'] . ' de ' . $c['alumnos'] . ' marcados' : $texto], 'strlen'));
                    ?>
                        <a href="<?php echo $puedeTomar ? DS_FUTBOL_URL . 'asistenciaAlumno/' . (int)$c['horario_id'] . '/' : '#'; ?>" class="ds-alerta">
                            <span class="ds-dot ds-dot--<?php echo $color; ?>" title="<?php echo $texto; ?>"></span>
                            <span>
                                <span class="ds-item__title"><?php echo $rango !== '' ? $rango . ' · ' : ''; ?><?php echo $hp($c['horario_nombre']); ?></span>
                                <span class="ds-item__meta"><?php echo $meta; ?></span>
                            </span>
                            <?php if ($puedeTomar): ?>
                                <span class="ds-item__value" style="margin-left:auto;white-space:nowrap;">
                                    <?php echo $c['estado'] === 'tomada' ? 'Revisar' : 'Tomar asistencia'; ?> <i class="fas fa-chevron-right"></i>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Lo suyo que requiere atención -->
            <div class="ds-panel">
                <div class="ds-section__head">
                    <h2 class="ds-section__title">Requiere tu atención</h2>
                </div>
                <?php
                $pendientes = [];
                if ($miDia['pendientesHoy'] > 0) {
                    $pendientes[] = ['danger', $miDia['pendientesHoy'] . ($miDia['pendientesHoy'] === 1 ? ' clase de hoy' : ' clases de hoy') . ' sin terminar la asistencia',
                                     'Tómela desde la cancha', $puedeTomar ? DS_FUTBOL_URL . 'asistencia/' : ''];
                }
                if ($miDia['sinRegistro'] > 0) {
                    $pendientes[] = ['warning', $miDia['sinRegistro'] . ($miDia['sinRegistro'] === 1 ? ' día' : ' días') . ' de este mes sin asistencia registrada',
                                     'Días con clase en que no se marcó a nadie', $puedePanel ? DS_FUTBOL_URL . 'dashboard/' : ''];
                }
                foreach ($miDia['horarios'] as $hh) {
                    if ($hh['alumnos'] === 0) {
                        $pendientes[] = ['warning', $hp($hh['horario_nombre']) . ' no tiene alumnos asignados', 'Avise a administración', ''];
                    }
                }
                ?>
                <?php if (!$pendientes): ?>
                    <p class="ds-vacio">Todo al día. No hay pendientes.</p>
                <?php else: ?>
                    <?php foreach ($pendientes as [$color, $titulo, $meta, $url]): ?>
                        <<?php echo $url !== '' ? 'a href="' . $url . '"' : 'div'; ?> class="ds-alerta">
                            <span class="ds-dot ds-dot--<?php echo $color; ?>"></span>
                            <span>
                                <span class="ds-item__title"><?php echo $titulo; ?></span>
                                <span class="ds-item__meta"><?php echo $meta; ?></span>
                            </span>
                            <?php if ($url !== ''): ?><i class="fas fa-chevron-right" style="margin-left:auto;color:var(--ds-text-muted);"></i><?php endif; ?>
                        </<?php echo $url !== '' ? 'a' : 'div'; ?>>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </section>
        <?php endif; ?>
        <?php endif; ?>

    </main>

</body>
</html>
