<?php
/*
| Panel de entrada del módulo Core.
|
|
| QUÉ CONTABA ANTES Y QUÉ CUENTA AHORA
|
| Cuatro tarjetas de INVENTARIO: cuántos usuarios, roles, vistas y permisos
| hay. Cifras que no se mueven en semanas y que no cambian lo que nadie va a
| hacer hoy. En un módulo de seguridad la pregunta es otra: «¿hay algo mal
| configurado, o algo que vigilar?».
|
| Con los datos de hoy la respuesta era que sí, y la pantalla no lo decía:
| CINCO de cinco cuentas sin segundo factor, veintidós vistas registradas a
| las que ningún rol puede entrar y treinta y ocho accesos fallidos en una
| semana.
|
| Se usa el mismo componente que Basketball, League y Arena —small-box con
| pie que enlaza— para que los cinco paneles del producto se lean igual.
*/

use admin\controllers\coreController;

$insCore = new coreController();

$tituloVista = 'Panel';
$vistaActual = 'panel';

$atencion = $insCore->requiereAtencion();
$pulso    = $insCore->pulso();
$fallidos = $insCore->ultimosFallidos(6);
$roles    = $insCore->roles();
$modulos  = ds_modulos_conocidos();

$h = static fn ($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php
/*==================================================================
  1 · REQUIERE ATENCIÓN

  Sólo se pinta lo que está mal. Una tira que enseña cinco ceros enseña a
  no mirarla, y el día que uno deje de ser cero nadie lo verá.
  ==================================================================*/
$avisos = [];

if ($atencion['sin2fa'] > 0) {
    $avisos[] = ['n' => $atencion['sin2fa'], 'color' => 'danger',
                 'icono' => 'fa-shield-halved',
                 'texto' => 'cuentas activas sin segundo factor',
                 'accion' => 'Revisar usuarios',
                 'url' => APP_URL . 'usuarioList/'];
}
if ($atencion['bloqueados'] > 0) {
    $avisos[] = ['n' => $atencion['bloqueados'], 'color' => 'warning',
                 'icono' => 'fa-user-lock',
                 'texto' => 'cuentas bloqueadas',
                 'accion' => 'Desbloquear',
                 'url' => APP_URL . 'usuarioList/'];
}
if ($atencion['vistasHuerfanas'] > 0) {
    $avisos[] = ['n' => $atencion['vistasHuerfanas'], 'color' => 'warning',
                 'icono' => 'fa-eye-slash',
                 'texto' => 'vistas sin permiso para ningún rol',
                 'accion' => 'Revisar permisos',
                 'url' => APP_URL . 'permisoRol/'];
}
if ($atencion['fallidos'] > 0) {
    $avisos[] = ['n' => $atencion['fallidos'], 'color' => 'info',
                 'icono' => 'fa-triangle-exclamation',
                 'texto' => 'accesos fallidos en 7 días',
                 'accion' => 'Ver los últimos',
                 'url' => '#ultimosFallidos'];
}
if ($atencion['rolesVacios'] > 0) {
    $avisos[] = ['n' => $atencion['rolesVacios'], 'color' => 'secondary',
                 'icono' => 'fa-user-slash',
                 'texto' => 'roles activos sin ningún usuario',
                 'accion' => 'Revisar roles',
                 'url' => APP_URL . 'rolList/'];
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
                <a href="<?php echo $h($a['url']); ?>"
                   class="d-flex align-items-center gap-3 text-decoration-none p-3 rounded border h-100">
                    <i class="fas <?php echo $a['icono']; ?> fa-2x text-<?php echo $a['color']; ?>"
                       aria-hidden="true"></i>
                    <span class="min-w-0">
                        <span class="d-block fw-bold fs-4 lh-1 text-body">
                            <?php echo number_format($a['n'], 0, ',', '.'); ?>
                        </span>
                        <span class="d-block small text-body-secondary"><?php echo $h($a['texto']); ?></span>
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
    <span>Nada pendiente: todas las cuentas con segundo factor y ninguna vista sin permiso.</span>
</div>
<?php endif; ?>

<?php
/*==================================================================
  2 · EL PULSO

  col-6 en teléfono y no col-12: cuatro tarjetas a pantalla completa son
  ochocientos píxeles de arrastre antes de llegar al contenido.
  ==================================================================*/
$metricas = [
    ['valor' => $pulso['cobertura2fa'] . '%', 'etiqueta' => 'Cobertura 2FA',
     'detalle' => $pulso['con2fa'] . ' de ' . $pulso['usuarios'] . ' cuentas',
     'clase' => $pulso['cobertura2fa'] >= 100 ? 'text-bg-success'
                : ($pulso['cobertura2fa'] > 0 ? 'text-bg-warning' : 'text-bg-danger'),
     'icono' => 'fa-shield-halved', 'url' => APP_URL . 'usuarioList/', 'pie' => 'Ver usuarios'],

    ['valor' => $pulso['usuarios'], 'etiqueta' => 'Usuarios activos',
     'detalle' => $pulso['roles'] . ' rol' . ($pulso['roles'] === 1 ? '' : 'es') . ' en uso',
     'clase' => 'text-bg-primary', 'icono' => 'fa-users',
     'url' => APP_URL . 'usuarioList/', 'pie' => 'Administrar'],

    ['valor' => $pulso['vistas'], 'etiqueta' => 'Vistas registradas',
     'detalle' => $atencion['vistasHuerfanas'] > 0
                  ? $atencion['vistasHuerfanas'] . ' sin permiso a nadie' : 'todas alcanzables',
     'clase' => $atencion['vistasHuerfanas'] > 0 ? 'text-bg-warning' : 'text-bg-info',
     'icono' => 'fa-bars', 'url' => APP_URL . 'menuList/', 'pie' => 'Ver menús'],

    ['valor' => $pulso['permisos'], 'etiqueta' => 'Permisos otorgados',
     'detalle' => 'usuario → rol → permiso',
     'clase' => 'text-bg-success', 'icono' => 'fa-key',
     'url' => APP_URL . 'permisoRol/', 'pie' => 'Administrar'],

    ['valor' => $pulso['sedes'], 'etiqueta' => 'Sedes',
     'detalle' => 'de la organización',
     'clase' => 'text-bg-secondary', 'icono' => 'fa-location-dot',
     'url' => APP_URL . 'sedeList/', 'pie' => 'Ver sedes'],
];
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

<?php
/*==================================================================
  3 · LOS ÚLTIMOS ACCESOS FALLIDOS

  El aviso de arriba dice cuántos hay; esto dice de dónde vienen, que es
  lo que permite distinguir un usuario que olvidó su clave de alguien
  probando cuentas.

  No va en <table>: tres columnas caben, pero en 390 px la IP y la fecha
  se pisan. Fila flexible con la hora fija a la derecha.
  ==================================================================*/
?>
<?php if ($fallidos): ?>
<div class="card mb-3" id="ultimosFallidos">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0">Últimos accesos fallidos</h3>
        <span class="badge text-bg-secondary"><?php echo (int)$atencion['fallidos']; ?> en 7 días</span>
    </div>
    <div class="card-body p-0">
        <ul class="list-group list-group-flush">
            <?php foreach ($fallidos as $f): ?>
            <li class="list-group-item d-flex align-items-center gap-3">
                <i class="fas fa-user-xmark text-body-secondary flex-shrink-0" aria-hidden="true"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="text-truncate fw-semibold"><?php echo $h($f['intento_usuario']); ?></div>
                    <div class="small text-body-secondary font-monospace text-truncate">
                        <?php echo $h($f['ip']); ?>
                    </div>
                </div>
                <div class="small text-body-secondary flex-shrink-0 text-end font-monospace">
                    <?php echo $h(substr((string)$f['intento_fecha'], 0, 16)); ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">Roles del ecosistema</h3>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Rol</th>
                        <th>Usuarios</th>
                        <th>Vistas con permiso</th>
                        <th>Módulos</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $r): $esSuper = (int)$r['rol_id'] === self_rol_superadmin(); ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($r['rol_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($esSuper): ?>
                                <span class="badge text-bg-warning ms-1">Acceso total</span>
                            <?php endif; ?>
                            <?php if ($r['rol_detalle']): ?>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($r['rol_detalle'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int)$r['usuarios']; ?></td>
                        <td><?php echo $esSuper ? '<span class="text-muted">todas</span>' : (int)$r['permisos']; ?></td>
                        <td>
                            <?php
                            $asignados = $esSuper ? array_keys($modulos) : array_filter(explode(',', (string)$r['modulos']));
                            if (!$asignados) {
                                echo '<span class="text-muted small">ninguno</span>';
                            }
                            foreach ($asignados as $mod):
                                if (!isset($modulos[$mod])) continue;
                            ?>
                                <span class="badge-modulo badge-modulo--<?php echo $mod; ?>"><?php echo $modulos[$mod]; ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="text-end">
                            <a href="<?php echo APP_URL; ?>permisoRol/?rol=<?php echo (int)$r['rol_id']; ?>"
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-key me-1"></i> Permisos
                            </a>
                            <a href="<?php echo APP_URL; ?>moduloRol/?rol=<?php echo (int)$r['rol_id']; ?>"
                               class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-th-large me-1"></i> Módulos
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
