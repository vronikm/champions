<?php
/* Alta y edición de un usuario. */

use admin\controllers\coreController;

$insCore = new coreController();

$id      = (int)($_GET['id'] ?? 0);
$usuario = $id > 0 ? $insCore->usuario($id) : null;
$esAlta  = ($usuario === null);

/* La vista se gobierna con los permisos de usuarioList, que es su menú. */
if ($esAlta && !puede_crear('usuarioList')) {
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}
if (!$esAlta && !puede_editar('usuarioList')) {
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}

/* Poder editar a un Super Administrador es poder entrar como él: bastaría
   con ponerle otra contraseña. Sólo otro Super Administrador pasa. */
$editaSuper = !$esAlta && (int)$usuario['usuario_rolid'] === self_rol_superadmin();
if ($editaSuper && !es_superadministrador()) {
    http_response_code(403);
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}

$tituloVista = $esAlta ? 'Nuevo usuario' : 'Editar usuario';
$vistaActual = 'usuarioList';

$roles     = $insCore->roles();
$empleados = $insCore->empleadosSinUsuario($id);

/* Permite llegar desde el listado de empleados de Basketball con la
   persona ya elegida: .../usuarioForm/?empleado=12 */
$empleadoPre = (int)($_GET['empleado'] ?? 0);
$empleadoSel = (int)($usuario['usuario_empleadoid'] ?? 0) ?: $empleadoPre;

$rolActual = (int)($usuario['usuario_rolid'] ?? 0);
$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row justify-content-center">
    <div class="col-lg-9">

        <?php if ($editaSuper): ?>
            <div class="aviso-superadmin mb-3">
                <i class="fas fa-shield-alt fa-lg mt-1"></i>
                <div>
                    <strong>Cuenta con acceso total.</strong><br>
                    Es la que sostiene el acceso al sistema: no se puede dar de baja y sólo
                    otro Super Administrador puede modificarla.
                </div>
            </div>
        <?php endif; ?>

        <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php">
            <input type="hidden" name="modulo_core" value="guardarUsuario">
            <input type="hidden" name="usuario_id" value="<?php echo $id; ?>">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><?php echo $tituloVista; ?></h3>
                </div>

                <div class="card-body">

                    <!-- Identidad -->
                    <h6 class="text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.05em;">
                        Identidad
                    </h6>

                    <div class="row g-2">
                        <div class="mb-3 col-md-6">
                            <label for="usuario_usuario">Usuario <span class="text-danger">*</span></label>
                            <div class="input-group">
                                                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                            
                                <input type="text" class="form-control" id="usuario_usuario" name="usuario_usuario"
                                       value="<?php echo $h($usuario['usuario_usuario'] ?? ''); ?>"
                                       pattern="[a-zA-Z0-9]{4,20}" maxlength="20" required autocomplete="off">
                            </div>
                            <small class="text-muted">4 a 20 caracteres, sólo letras y números.</small>
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="usuario_empleadoid">Persona vinculada</label>
                            <div class="input-group">
                                                                <span class="input-group-text"><i class="fas fa-id-badge"></i></span>
                            
                                <select class="form-select" id="usuario_empleadoid" name="usuario_empleadoid">
                                    <option value="0">Sin vincular</option>
                                    <?php foreach ($empleados as $e): ?>
                                        <option value="<?php echo (int)$e['empleado_id']; ?>"
                                            <?php echo $empleadoSel === (int)$e['empleado_id'] ? 'selected' : ''; ?>>
                                            <?php echo $h($e['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <small class="text-muted">
                                De ella salen la foto, la sede y los horarios que verá en su panel.
                            </small>
                        </div>
                    </div>

                    <hr>

                    <!-- Acceso -->
                    <h6 class="text-muted text-uppercase mb-3" style="font-size:.75rem;letter-spacing:.05em;">
                        Acceso
                    </h6>

                    <div class="row g-2">
                        <div class="mb-3 col-md-6">
                            <label for="usuario_rolid">Rol <span class="text-danger">*</span></label>
                            <?php
                            /* El rol que ya tiene el usuario se ofrece siempre, aunque quien
                               edita no pueda otorgarlo: si se omitía, el select quedaba sin
                               selección y al guardar el servidor respondía «Seleccione un
                               rol», sin que se hubiera tocado el campo. */
                            $puedeOtorgarSuper = es_superadministrador();
                            ?>
                            <select class="form-select" id="usuario_rolid" name="usuario_rolid" required>
                                <?php if ($esAlta): ?>
                                    <option value="">Seleccione…</option>
                                <?php endif; ?>
                                <?php foreach ($roles as $r):
                                    $rid     = (int)$r['rol_id'];
                                    $esSuper = $rid === self_rol_superadmin();
                                    $esSuyo  = $rid === $rolActual;

                                    if ($esSuper && !$puedeOtorgarSuper && !$esSuyo) { continue; }
                                ?>
                                    <option value="<?php echo $rid; ?>" <?php echo $esSuyo ? 'selected' : ''; ?>>
                                        <?php echo $h($r['rol_nombre']); ?><?php echo $esSuper ? ' — acceso total' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$puedeOtorgarSuper): ?>
                                <small class="text-muted">
                                    Sólo un Super Administrador puede otorgar el acceso total.
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="usuario_clave">
                                Contraseña <?php echo $esAlta ? '<span class="text-danger">*</span>' : ''; ?>
                            </label>
                            <div class="input-group">
                                                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                            
                                <input type="password" class="form-control" id="usuario_clave" name="usuario_clave"
                                       minlength="<?php echo clave_longitud_minima(); ?>"
                                       maxlength="<?php echo clave_longitud_maxima(); ?>"
                                       autocomplete="new-password"
                                       placeholder="<?php echo $esAlta
                                           ? 'Mínimo ' . clave_longitud_minima() . ' caracteres'
                                           : 'Dejar vacía para no cambiarla'; ?>"
                                       <?php echo $esAlta ? 'required' : ''; ?>>
                                                                <button type="button" class="btn btn-outline-secondary" id="btnVerClave"
                                        title="Mostrar u ocultar"><i class="fas fa-eye"></i></button>
                            
                            </div>
                            <small class="text-muted">
                                <?php echo clave_regla_texto(); ?>
                                <?php if (!$esAlta): ?>
                                    Si la deja vacía, la actual no se toca.
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-6 mb-0">
                            <label for="usuario_estado">Estado</label>
                            <select class="form-select" id="usuario_estado" name="usuario_estado">
                                <option value="A" <?php echo ($usuario['usuario_estado'] ?? 'A') === 'A' ? 'selected' : ''; ?>>Activo</option>
                                <option value="I" <?php echo ($usuario['usuario_estado'] ?? '') === 'I' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                            <small class="text-muted">Un usuario inactivo no puede iniciar sesión.</small>
                        </div>

                        <div class="mb-3 col-md-6 mb-0">
                            <label for="usuario_tienebloqueo">Bloqueado</label>
                            <select class="form-select" id="usuario_tienebloqueo" name="usuario_tienebloqueo">
                                <option value="N" <?php echo ($usuario['usuario_tienebloqueo'] ?? 'N') === 'N' ? 'selected' : ''; ?>>No</option>
                                <option value="S" <?php echo ($usuario['usuario_tienebloqueo'] ?? '') === 'S' ? 'selected' : ''; ?>>Sí</option>
                            </select>
                            <small class="text-muted">El bloqueo es temporal; la baja se hace desde el listado.</small>
                        </div>
                    </div>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'usuarioList/'); ?>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var boton = document.getElementById('btnVerClave');
    var campo = document.getElementById('usuario_clave');
    if (!boton || !campo) { return; }

    boton.addEventListener('click', function () {
        var oculta = campo.type === 'password';
        campo.type = oculta ? 'text' : 'password';
        this.innerHTML = oculta ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
