<?php
/* Listado de usuarios del ecosistema. */

use admin\controllers\coreController;

$insCore = new coreController();

$tituloVista = 'Usuarios';
$vistaActual = 'usuarioList';

$usuarios = $insCore->usuarios();

$puedeCrear    = puede_crear('usuarioList');
$puedeEditar   = puede_editar('usuarioList');
$puedeEliminar = puede_eliminar('usuarioList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0"><?php echo count($usuarios); ?> usuario<?php echo count($usuarios) === 1 ? '' : 's'; ?></h3>
        <?php if ($puedeCrear): ?>
            <a href="<?php echo APP_URL; ?>usuarioForm/" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Nuevo usuario
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Persona</th>
                        <th>Rol</th>
                        <th>Sede</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$usuarios): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>
                <?php endif; ?>

                <?php foreach ($usuarios as $u):
                    $esSuper = (int)$u['usuario_rolid'] === self_rol_superadmin();
                    $esYo    = (int)$u['usuario_id'] === (int)($_SESSION['usuarioid'] ?? 0);
                ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($u['usuario_usuario'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <?php if ($esYo): ?><span class="badge text-bg-info ms-1">Usted</span><?php endif; ?>
                            <?php if ($u['usuario_tienebloqueo'] === 'S'): ?>
                                <span class="badge text-bg-danger ms-1">Bloqueado</span>
                            <?php endif; ?>
                            <?php /* Ver quién tiene segundo factor importa para
                                     saber a qué cuentas les basta la contraseña
                                     robada para entrar. */ ?>
                            <?php if (($u['usuario_2fa_estado'] ?? 'N') === 'A'): ?>
                                <span class="badge text-bg-success ms-1"
                                      title="Verificación en dos pasos activa">
                                    <i class="fas fa-shield-alt"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars(trim((string)$u['empleado']) ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars((string)$u['rol_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($esSuper): ?><span class="badge text-bg-warning ms-1">Acceso total</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string)($u['sede_nombre'] ?? '') ?: '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <span class="badge text-bg-<?php echo $u['usuario_estado'] === 'A' ? 'success' : 'secondary'; ?>">
                                <?php echo $u['usuario_estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td class="ds-tabla-acciones">
                            <?php
                            /* La cuenta con acceso total no se da de baja, y
                               sólo otro Super Administrador puede tocarla:
                               cambiarle la clave equivaldría a entrar como él. */
                            $editable = $puedeEditar && (!$esSuper || es_superadministrador());
                            $bajable  = $puedeEliminar && !$esYo && !$esSuper;
                            ?>

                            <?php if ($editable): ?>
                                <a href="<?php echo APP_URL; ?>usuarioForm/?id=<?php echo (int)$u['usuario_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>

                            <?php if ($esSuper): ?>
                                <?php echo ds_hueco('La cuenta con acceso total no se puede dar de baja'); ?>
                            <?php endif; ?>

                            <?php /* Restablecer el segundo factor de otro.
                                     Es la salida cuando alguien pierde el
                                     teléfono y agota sus códigos de
                                     recuperación. Sólo el superadministrador,
                                     con motivo obligatorio, y queda escrito
                                     quién lo hizo: sin ese rastro esto sería
                                     una puerta trasera a cualquier cuenta. */ ?>
                            <?php if (es_superadministrador()
                                      && ($u['usuario_2fa_estado'] ?? 'N') !== 'N'): ?>
                                <button type="button" class="btn btn-sm btn-outline-warning js-reset2fa"
                                        title="Restablecer la verificación en dos pasos"
                                        data-id="<?php echo (int)$u['usuario_id']; ?>"
                                        data-usuario="<?php echo htmlspecialchars($u['usuario_usuario'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="fas fa-mobile-alt"></i>
                                </button>
                            <?php endif; ?>

                            <?php if ($bajable): ?>
                                <form class="FormularioAjax d-inline" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/coreAjax.php"
                                      data-confirmar="El usuario quedará dado de baja y no podrá iniciar sesión.">
                                    <input type="hidden" name="modulo_core" value="eliminarUsuario">
                                    <input type="hidden" name="usuario_id" value="<?php echo (int)$u['usuario_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Dar de baja">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
/* Restablecer el segundo factor de otra persona.
   Se pide motivo por escrito y se advierte de lo que implica: la cuenta
   queda protegida sólo por su contraseña hasta que su dueño vuelva a
   configurarlo. */
(function () {
    var url = '<?php echo APP_URL; ?>ajax/coreAjax.php';

    document.querySelectorAll('.js-reset2fa').forEach(function (b) {
        b.addEventListener('click', function () {
            var quien = b.getAttribute('data-usuario');

            Swal.fire({
                icon: 'warning',
                title: '¿Restablecer la verificación?',
                html: '<div style="text-align:left">'
                    + '<p>La cuenta <b>' + quien + '</b> dejará de pedir el código y '
                    + 'quedará protegida <b>sólo por su contraseña</b> hasta que su dueño '
                    + 'la configure de nuevo.</p>'
                    + '<p class="text-muted" style="font-size:.88rem;">Hágalo únicamente si '
                    + 'la persona perdió el teléfono y agotó sus códigos de recuperación, y '
                    + 'después de confirmar por otro medio que es realmente ella quien lo '
                    + 'pide. Queda registrado que usted lo hizo.</p></div>',
                input: 'text',
                inputPlaceholder: 'Motivo (obligatorio)',
                showCancelButton: true,
                confirmButtonText: 'Restablecer',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545',
                preConfirm: function (v) {
                    if (!v || !v.trim()) {
                        Swal.showValidationMessage('Escriba el motivo.');
                        return false;
                    }
                    return v.trim();
                }
            }).then(function (r) {
                if (!r.isConfirmed) { return; }

                var fd = new FormData();
                fd.append('modulo_core', 'restablecerSegundoFactor');
                fd.append('usuario_id', b.getAttribute('data-id'));
                fd.append('motivo', r.value);

                fetch(url, { method: 'POST', body: fd })
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
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
