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

/* Decodificado y escapado: lo escrito se guarda ya escapado (limpiarHtml). */
$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card ds-usuarios">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <h3 class="card-title mb-0 me-auto">
            <i class="fas fa-users-gear me-2" aria-hidden="true"></i>Usuarios
            <span class="badge text-bg-secondary ms-1"><?php echo count($usuarios); ?></span>
        </h3>
        <?php if ($puedeCrear): ?>
            <a href="<?php echo APP_URL; ?>usuarioForm/" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1" aria-hidden="true"></i> Nuevo usuario
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <?php /* Buscar y ordenar en una barra. En escritorio se ordena con los
                 encabezados; en el teléfono la tabla pasa a fichas y no hay
                 encabezados, así que el orden se elige en el selector. */ ?>
        <div class="ds-usuarios__barra mb-3">
            <?php if (count($usuarios) > 5): ?>
                <input type="search" class="form-control ds-usuarios__buscar" id="usuariosBuscar"
                       placeholder="Buscar usuario…" title="Busca por usuario, nombre, rol o sede"
                       aria-label="Buscar por usuario, nombre, rol o sede">
            <?php endif; ?>
            <?php if (count($usuarios) > 1): ?>
                <div class="ds-usuarios__ordenar">
                    <label class="visually-hidden" for="usuariosOrden">Ordenar por</label>
                    <select class="form-select" id="usuariosOrden">
                        <option value="usuario:asc">Usuario (A–Z)</option>
                        <option value="usuario:desc">Usuario (Z–A)</option>
                        <option value="persona:asc">Nombre (A–Z)</option>
                        <option value="rol:asc">Rol</option>
                        <option value="sedes:asc">Sedes</option>
                        <option value="estado:asc">Activos primero</option>
                        <option value="estado:desc">Inactivos primero</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <?php /* Un solo marcado: tabla en escritorio y una ficha por usuario
                 por debajo de 992 px (core.css, .ds-usuarios). */ ?>
        <table class="table align-middle mb-0 ds-usuarios__tabla">
            <thead>
                <tr>
                    <th aria-sort="ascending"><button type="button" class="ds-usuarios__orden" data-orden="usuario">Usuario<i class="fas fa-sort-up" aria-hidden="true"></i></button></th>
                    <th aria-sort="none"><button type="button" class="ds-usuarios__orden" data-orden="rol">Rol<i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th class="ds-usuarios__opcional" aria-sort="none"><button type="button" class="ds-usuarios__orden" data-orden="sedes">Sedes<i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th aria-sort="none"><button type="button" class="ds-usuarios__orden" data-orden="estado">Estado<i class="fas fa-sort" aria-hidden="true"></i></button></th>
                    <th class="text-end"><span class="visually-hidden">Acciones</span></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$usuarios): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No hay usuarios registrados.</td></tr>
            <?php endif; ?>

            <?php foreach ($usuarios as $u):
                $id      = (int)$u['usuario_id'];
                $esSuper = (int)$u['usuario_rolid'] === self_rol_superadmin();
                $esYo    = $id === (int)($_SESSION['usuarioid'] ?? 0);
                $persona = trim(html_entity_decode((string)$u['empleado'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $partes  = preg_split('/\s+/', $persona !== '' ? $persona : (string)$u['usuario_usuario']);
                $inicial = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1) . mb_substr($partes[1] ?? '', 0, 1));

                /* La cuenta con acceso total no se da de baja, y sólo otro
                   Super Administrador puede tocarla: cambiarle la clave
                   equivaldría a entrar como él. */
                $editable = $puedeEditar && (!$esSuper || es_superadministrador());
                $bajable  = $puedeEliminar && !$esYo && !$esSuper;
                /* Sedes que ve (migración 072): todas, las asignadas o ninguna. */
                $todasSedes = $esSuper || ($u['usuario_todassedes'] ?? 'N') === 'S';
                $sedesTexto = $todasSedes ? 'Todas las sedes' : trim((string)($u['sedes_nombres'] ?? ''));
            ?>
                <?php /* Claves de orden. «Todas» va antes que cualquier sede y
                         «sin sede» al final; el estado, activos primero. */ ?>
                <tr data-buscar="<?php echo $h(mb_strtolower($u['usuario_usuario'] . ' ' . $persona . ' ' . $u['rol_nombre'] . ' ' . $sedesTexto)); ?>"
                    data-o-usuario="<?php echo $h(mb_strtolower($u['usuario_usuario'])); ?>"
                    data-o-persona="<?php echo $h(mb_strtolower($persona !== '' ? $persona : '~')); ?>"
                    data-o-rol="<?php echo $h(mb_strtolower($u['rol_nombre'])); ?>"
                    data-o-sedes="<?php echo $h($todasSedes ? ' ' : ($sedesTexto === '' ? '~' : mb_strtolower($sedesTexto))); ?>"
                    data-o-estado="<?php echo $u['usuario_estado'] === 'A' ? '0' : '1'; ?>">
                    <td class="ds-usuarios__principal">
                        <div class="ds-usuarios__quien">
                            <span class="ds-usuarios__inicial" aria-hidden="true"><?php echo $h($inicial); ?></span>
                            <div class="min-w-0">
                                <div class="ds-usuarios__usuario">
                                    <?php echo $h($u['usuario_usuario']); ?>
                                    <?php if ($esYo): ?><span class="badge text-bg-info ms-1">Usted</span><?php endif; ?>
                                    <?php if ($u['usuario_tienebloqueo'] === 'S'): ?><span class="badge text-bg-danger ms-1">Bloqueado</span><?php endif; ?>
                                    <?php /* Ver quién tiene segundo factor importa para saber
                                             a qué cuentas les basta la contraseña robada. */ ?>
                                    <?php if (($u['usuario_2fa_estado'] ?? 'N') === 'A'): ?>
                                        <span class="badge text-bg-success ms-1" title="Verificación en dos pasos activa"><i class="fas fa-shield-alt" aria-hidden="true"></i><span class="visually-hidden">Verificación en dos pasos activa</span></span>
                                    <?php endif; ?>
                                </div>
                                <div class="ds-usuarios__persona"><?php echo $persona !== '' ? $h($persona) : 'Sin ficha de empleado'; ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="ds-usuarios__dato" data-rotulo="Rol">
                        <?php echo $h($u['rol_nombre']); ?>
                        <?php if ($esSuper): ?><span class="badge text-bg-warning ms-1">Acceso total</span><?php endif; ?>
                    </td>
                    <td class="ds-usuarios__dato ds-usuarios__opcional" data-rotulo="Sedes">
                        <?php if ($todasSedes): ?>
                            <span class="badge text-bg-primary"><i class="fas fa-globe-americas me-1" aria-hidden="true"></i>Todas</span>
                        <?php elseif ($sedesTexto === ''): ?>
                            <span class="badge text-bg-danger" title="Sin sede no ve ninguna información"><i class="fas fa-exclamation-triangle me-1" aria-hidden="true"></i>Sin sede</span>
                        <?php else: ?>
                            <?php echo $h($sedesTexto); ?>
                        <?php endif; ?>
                    </td>
                    <td class="ds-usuarios__estado">
                        <span class="badge text-bg-<?php echo $u['usuario_estado'] === 'A' ? 'success' : 'secondary'; ?>">
                            <?php echo $u['usuario_estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                        </span>
                    </td>
                    <td class="ds-tabla-acciones ds-usuarios__acciones"><div class="ds-usuarios__botones">
                        <?php if ($editable): ?>
                            <a href="<?php echo APP_URL; ?>usuarioForm/?id=<?php echo $id; ?>"
                               class="btn btn-sm btn-info" title="Editar" aria-label="Editar a <?php echo $h($u['usuario_usuario']); ?>"><i class="fas fa-pen" aria-hidden="true"></i></a>
                            <?php if (!$esYo): ?>
                                <button type="button" class="btn btn-sm btn-warning js-clave ds-usuarios__primario"
                                        title="Cambiar contraseña y enviarla"
                                        aria-label="Cambiar la contraseña de <?php echo $h($u['usuario_usuario']); ?>"
                                        data-id="<?php echo $id; ?>" data-usuario="<?php echo $h($u['usuario_usuario']); ?>"
                                        data-nombre="<?php echo $h($persona); ?>" data-correo="<?php echo $h($u['correo']); ?>"
                                        data-celular="<?php echo $h(preg_replace('/\D/', '', (string)$u['celular'])); ?>">
                                    <i class="fas fa-key" aria-hidden="true"></i><span class="ds-usuarios__texto-boton">Contraseña</span>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php /* Restablecer el segundo factor de otro. Es la salida
                                 cuando alguien pierde el teléfono y agota sus códigos
                                 de recuperación. Sólo el superadministrador, con motivo
                                 obligatorio, y queda escrito quién lo hizo. */ ?>
                        <?php if (es_superadministrador() && ($u['usuario_2fa_estado'] ?? 'N') !== 'N'): ?>
                            <button type="button" class="btn btn-sm btn-secondary js-reset2fa"
                                    title="Restablecer la verificación en dos pasos" aria-label="Restablecer la verificación en dos pasos de <?php echo $h($u['usuario_usuario']); ?>"
                                    data-id="<?php echo $id; ?>" data-usuario="<?php echo $h($u['usuario_usuario']); ?>">
                                <i class="fas fa-mobile-alt" aria-hidden="true"></i>
                            </button>
                        <?php endif; ?>

                        <?php if ($bajable): ?>
                            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php"
                                  data-confirmar="El usuario quedará dado de baja y no podrá iniciar sesión.">
                                <input type="hidden" name="modulo_core" value="eliminarUsuario">
                                <input type="hidden" name="usuario_id" value="<?php echo $id; ?>">
                                <?php echo ds_boton_eliminar('al usuario ' . $u['usuario_usuario'], ['title' => 'Dar de baja']); ?>
                            </form>
                        <?php elseif ($esSuper): ?>
                            <?php echo ds_hueco('La cuenta con acceso total no se puede dar de baja'); ?>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-body-secondary text-center d-none mt-3 mb-0" id="usuariosNinguno">Ningún usuario coincide con la búsqueda.</p>
    </div>
</div>

<?php if ($puedeEditar) { require __DIR__ . "/inc/modal-clave.php"; } ?>

<script>
/* Buscador: filtra las filas en el navegador (los usuarios son pocos). */
(function () {
    var campo = document.getElementById('usuariosBuscar');
    if (!campo) { return; }
    var quita = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    campo.addEventListener('input', function () {
        var q = quita(campo.value.trim()), vistos = 0;
        document.querySelectorAll('.ds-usuarios__tabla tbody tr[data-buscar]').forEach(function (tr) {
            var ver = q === '' || quita(tr.getAttribute('data-buscar')).indexOf(q) !== -1;
            tr.hidden = !ver; if (ver) { vistos++; }
        });
        document.getElementById('usuariosNinguno').classList.toggle('d-none', vistos > 0);
    });
})();

/* Orden por campo. Los encabezados (escritorio) y el selector (teléfono,
   donde la tabla es una lista de fichas sin encabezados) comparten el mismo
   estado. Un segundo clic en el mismo encabezado invierte el orden. Se
   recuerda en este navegador; sin almacenamiento, vale el orden por usuario. */
(function () {
    var tbody = document.querySelector('.ds-usuarios__tabla tbody');
    var selector = document.getElementById('usuariosOrden');
    var botones = document.querySelectorAll('.ds-usuarios__orden');
    if (!tbody || (!selector && !botones.length)) { return; }

    var CLAVE = 'ds-usuarios-orden';
    var comparar = new Intl.Collator('es', { sensitivity: 'base', numeric: true }).compare;
    var actual = { campo: 'usuario', dir: 'asc' };

    function ordenar(campo, dir) {
        actual = { campo: campo, dir: dir };
        var filas = Array.prototype.slice.call(tbody.querySelectorAll('tr[data-o-usuario]'));
        var signo = dir === 'desc' ? -1 : 1;
        filas.sort(function (a, b) {
            return signo * comparar(a.getAttribute('data-o-' + campo), b.getAttribute('data-o-' + campo))
                || comparar(a.getAttribute('data-o-usuario'), b.getAttribute('data-o-usuario'));
        });
        filas.forEach(function (tr) { tbody.appendChild(tr); });

        botones.forEach(function (b) {
            var th = b.closest('th'), activo = b.getAttribute('data-orden') === campo;
            th.setAttribute('aria-sort', activo ? (dir === 'asc' ? 'ascending' : 'descending') : 'none');
            b.querySelector('i').className = 'fas ' + (activo ? (dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort');
        });
        if (selector) {
            var valor = campo + ':' + dir;
            /* Si la combinación no está en el selector (p. ej. Rol Z–A desde el
               encabezado), se muestra la del mismo campo. */
            selector.value = selector.querySelector('option[value="' + valor + '"]') ? valor : campo + ':asc';
        }
        try { localStorage.setItem(CLAVE, campo + ':' + dir); } catch (e) {}
    }

    botones.forEach(function (b) {
        b.addEventListener('click', function () {
            var campo = b.getAttribute('data-orden');
            ordenar(campo, actual.campo === campo && actual.dir === 'asc' ? 'desc' : 'asc');
        });
    });
    if (selector) {
        selector.addEventListener('change', function () {
            var p = selector.value.split(':');
            ordenar(p[0], p[1]);
        });
    }

    try {
        var guardado = (localStorage.getItem(CLAVE) || '').split(':');
        if (/^(usuario|persona|rol|sedes|estado)$/.test(guardado[0]) && /^(asc|desc)$/.test(guardado[1])) {
            ordenar(guardado[0], guardado[1]);
        }
    } catch (e) {}
})();

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
