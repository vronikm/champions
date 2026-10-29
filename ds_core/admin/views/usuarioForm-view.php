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

/* Quien ve sólo algunas sedes administra sólo a los usuarios de esas sedes. */
if (!$esAlta && !$insCore->usuarioEnMiAlcance($usuario)) {
    http_response_code(403);
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}

/* Sedes: las que puede asignar quien edita, y las del usuario. Las suyas
   que quien edita no administra no se muestran, pero se conservan. */
$sedesAsignables = $insCore->sedesAsignables($id);   /* activas, y las inactivas que ya tiene */
$sedesUsuario    = $insCore->sedesDeUsuario($id);
$puedeDarTodas   = sedes_de_la_sesion() === null;
$sedesFuera      = count(array_diff($sedesUsuario['ids'], array_map('intval', array_keys($sedesAsignables))));
if ($esAlta && count($sedesAsignables) === 1) { $sedesUsuario['ids'] = [(int)array_key_first($sedesAsignables)]; }

$tituloVista = $esAlta ? 'Nuevo usuario' : 'Editar usuario';
$vistaActual = 'usuarioList';

$roles     = $insCore->roles();
$empleados = $insCore->empleadosSinUsuario($id);

/* Permite llegar desde el listado de empleados de Basketball con la
   persona ya elegida: .../usuarioForm/?empleado=12 */
$empleadoPre = (int)($_GET['empleado'] ?? 0);
$empleadoSel = (int)($usuario['usuario_empleadoid'] ?? 0) ?: $empleadoPre;

$rolActual = (int)($usuario['usuario_rolid'] ?? 0);

/* Para el modal de contraseña: correo y celular de su ficha de empleado. */
$esYo = !$esAlta && $id === (int)($_SESSION['usuarioid'] ?? 0);
$contacto = ['empleado' => '', 'correo' => '', 'celular' => ''];
if (!$esAlta) {
    foreach ($insCore->usuarios() as $fila) {
        if ((int)$fila['usuario_id'] === $id) { $contacto = $fila; break; }
    }
}
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

        <?php /* El alta lo envía su propio script (abajo): responde en JSON con las
                 credenciales para mostrarlas y mandarlas. La edición sigue con
                 FormularioAjax. */ ?>
        <form class="<?php echo $esAlta ? '' : 'FormularioAjax'; ?>" id="<?php echo $esAlta ? 'usuarioAlta' : 'usuarioEditar'; ?>" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php">
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
                                            data-correo="<?php echo $h($e['correo']); ?>" data-celular="<?php echo $h(preg_replace('/\D/', '', (string)$e['celular'])); ?>" data-sede="<?php echo (int)$e['sede']; ?>"
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

                        <?php if ($esAlta): ?>
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
                                <button type="button" class="btn btn-secondary" id="btnGenerarClave" title="Generar una aleatoria" aria-label="Generar una contraseña aleatoria"><i class="fas fa-rotate" aria-hidden="true"></i></button>
                            
                            </div>
                            <small class="text-muted">
                                <?php echo clave_regla_texto(); ?>
                                <?php if (!$esAlta): ?>
                                    Si la deja vacía, la actual no se toca.
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php /* Credenciales al usuario nuevo: el correo sale del servidor;
                                 WhatsApp lo manda quien administra desde su teléfono. */ ?>
                        <fieldset class="col-12 mb-3 ds-clave__envio" id="altaEnvio">
                            <legend class="form-label mb-2">Enviarle usuario y contraseña por</legend>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="altaCorreoSi" name="enviar_correo" value="S">
                                        <label class="form-check-label" for="altaCorreoSi"><i class="fas fa-envelope me-1" aria-hidden="true"></i>Correo</label>
                                    </div>
                                    <input type="email" class="form-control mt-1" id="altaCorreo" name="correo" placeholder="correo@ejemplo.com" inputmode="email" aria-label="Correo del usuario" disabled>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="altaWaSi" name="enviar_whatsapp" value="S">
                                        <label class="form-check-label" for="altaWaSi"><i class="fab fa-whatsapp me-1" aria-hidden="true"></i>WhatsApp</label>
                                    </div>
                                    <input type="tel" class="form-control mt-1" id="altaCelular" name="celular" placeholder="09…" inputmode="numeric" maxlength="13" aria-label="Celular del usuario" disabled>
                                </div>
                            </div>
                            <div class="form-text">Se rellenan con los de la persona vinculada. Al ingresar por primera vez, el sistema le pedirá cambiar la contraseña.</div>
                        </fieldset>
                        <?php else: ?>
                        <?php /* Al editar, la contraseña se cambia en el modal: genera una
                                 aleatoria y la envía por correo o WhatsApp
                                 (inc/modal-clave.php). La propia, desde «Cambiar
                                 contraseña», que pide la actual. */ ?>
                        <div class="mb-3 col-md-6">
                            <span class="form-label d-block">Contraseña</span>
                            <?php if ($esYo): ?>
                                <p class="text-muted small mb-0">Para cambiar la suya use «Cambiar contraseña» en el menú de su usuario.</p>
                            <?php else: ?>
                                <button type="button" class="btn btn-warning js-clave"
                                        data-id="<?php echo $id; ?>" data-usuario="<?php echo $h($usuario['usuario_usuario']); ?>"
                                        data-nombre="<?php echo $h(html_entity_decode((string)$contacto['empleado'], ENT_QUOTES | ENT_HTML5, 'UTF-8')); ?>"
                                        data-correo="<?php echo $h($contacto['correo']); ?>"
                                        data-celular="<?php echo $h(preg_replace('/\D/', '', (string)$contacto['celular'])); ?>">
                                    <i class="fas fa-key me-1" aria-hidden="true"></i>Cambiar contraseña y enviarla
                                </button>
                                <small class="text-muted d-block mt-1">Se guarda al momento, aparte del resto del formulario.</small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr>

                    <!-- Sedes: qué información ve (sedes_de_la_sesion, migración 072) -->
                    <h6 class="text-muted text-uppercase mb-2" style="font-size:.75rem;letter-spacing:.05em;" id="sedesTitulo">
                        Sedes que ve
                    </h6>
                    <fieldset class="ds-sedes mb-3" aria-labelledby="sedesTitulo" id="sedesUsuario"
                              data-super="<?php echo self_rol_superadmin(); ?>">
                        <legend class="visually-hidden">Sedes que ve</legend>
                        <p class="text-muted small mb-2">
                            El usuario sólo verá alumnos, pagos, asistencia, reportes y facturas de estas sedes.
                        </p>
                        <p class="ds-sedes__super small mb-2" hidden>
                            <i class="fas fa-globe-americas me-1" aria-hidden="true"></i>El Super Administrador ve siempre todas las sedes.
                        </p>
                        <div class="ds-sedes__modos" role="radiogroup" aria-label="Alcance">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sedes_modo" id="sedesElegidas" value="elegidas"
                                       <?php echo !$sedesUsuario['todas'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="sedesElegidas">Sedes elegidas</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="sedes_modo" id="sedesTodas" value="todas"
                                       <?php echo $sedesUsuario['todas'] ? 'checked' : ''; ?>
                                       <?php echo $puedeDarTodas ? '' : 'disabled'; ?>>
                                <label class="form-check-label" for="sedesTodas">
                                    Todas las sedes <span class="text-muted small">(también las que se creen)</span>
                                </label>
                            </div>
                        </div>
                        <?php if (!$puedeDarTodas): ?>
                            <p class="small text-muted mb-2">Sólo quien ve todas las sedes puede dar «Todas las sedes». Usted puede asignar las suyas.</p>
                        <?php endif; ?>
                        <div class="ds-sedes__lista" id="sedesLista">
                            <?php foreach ($sedesAsignables as $sid => $snombre): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="sedes[]" value="<?php echo (int)$sid; ?>"
                                           id="sede<?php echo (int)$sid; ?>"
                                           <?php echo in_array((int)$sid, $sedesUsuario['ids'], true) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sede<?php echo (int)$sid; ?>"><?php echo $h($snombre); ?></label>
                                </div>
                            <?php endforeach; ?>
                            <?php if ($sedesFuera): ?>
                                <p class="small text-muted mb-0 w-100">
                                    Además tiene <?php echo $sedesFuera === 1 ? 'una sede' : $sedesFuera . ' sedes'; ?> que usted no administra: se conservan.
                                </p>
                            <?php endif; ?>
                        </div>
                    </fieldset>

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

<?php if ($esAlta || !$esYo) { require __DIR__ . "/inc/modal-clave.php"; } ?>

<?php if ($esAlta): ?>
<script>
/*
| Alta con envío de credenciales.
|
| Correo y celular se toman de la persona vinculada (data-* de cada
| opción) y los interruptores se marcan si los tiene. Al guardar, el
| servidor (guardarUsuario con respuesta=json) crea el usuario, envía el
| correo si se pidió y devuelve el mensaje: el modal de contraseña muestra
| el resultado y el botón de WhatsApp; al cerrarlo se vuelve al listado.
*/
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('usuarioAlta');
    if (!form) { return; }
    var $ = function (id) { return document.getElementById(id); };
    var persona = $('usuario_empleadoid'), clave = $('usuario_clave');

    function sincronizar() {
        $('altaCorreo').disabled = !$('altaCorreoSi').checked;
        $('altaCelular').disabled = !$('altaWaSi').checked;
    }
    function rellenar() {
        var o = persona.options[persona.selectedIndex] || {}, d = o.dataset || {};
        $('altaCorreo').value = d.correo || '';
        $('altaCelular').value = d.celular || '';
        $('altaCorreoSi').checked = !!d.correo;
        $('altaWaSi').checked = !!d.celular;
        sincronizar();
    }
    persona.addEventListener('change', rellenar);
    $('altaCorreoSi').addEventListener('change', sincronizar);
    $('altaWaSi').addEventListener('change', sincronizar);
    rellenar();

    /* Una aleatoria de partida, visible para poder anotarla. */
    function generar() {
        if (window.dsClaveGenerar) { clave.value = window.dsClaveGenerar(); clave.type = 'text'; }
    }
    $('btnGenerarClave').addEventListener('click', generar);
    if (!clave.value) { generar(); }

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        if (!form.reportValidity()) { return; }
        var fd = new FormData(form);
        fd.append('respuesta', 'json');
        var boton = form.querySelector('[type="submit"]'); if (boton) { boton.disabled = true; }
        fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (t) {
                var j = null; try { j = JSON.parse(t); } catch (e) {}
                if (j && j.ok) {
                    window.dsClaveResultado(j, function () { location.href = j.url; });
                    return;
                }
                if (boton) { boton.disabled = false; }
                Swal.fire({ icon: (j && j.icono) || 'error', title: (j && j.titulo) || 'No se pudo crear',
                            text: (j && j.texto) || 'Su sesión pudo caducar. Vuelva a entrar.' });
            })
            .catch(function () {
                if (boton) { boton.disabled = false; }
                Swal.fire({ icon: 'error', title: 'Sin conexión', text: 'No se pudo contactar con el servidor. No se creó nada.' });
            });
    });
});
</script>
<?php endif; ?>

<script>
/* Sedes: con «Todas las sedes» la lista no aplica; el Super Administrador
   ve siempre todas, así que el bloque se sustituye por la nota. */
(function () {
    var caja = document.getElementById('sedesUsuario'), rol = document.getElementById('usuario_rolid');
    if (!caja) { return; }
    var lista = document.getElementById('sedesLista'), todas = document.getElementById('sedesTodas');
    function aplicar() {
        var esSuper = rol && rol.value === caja.dataset.super;
        caja.querySelector('.ds-sedes__super').hidden = !esSuper;
        caja.querySelector('.ds-sedes__modos').hidden = esSuper;
        lista.hidden = esSuper;
        var bloqueada = esSuper || (todas && todas.checked);
        lista.classList.toggle('ds-sedes__lista--inactiva', bloqueada);
        lista.querySelectorAll('input').forEach(function (i) { i.disabled = bloqueada; });
    }
    caja.addEventListener('change', aplicar);
    if (rol) { rol.addEventListener('change', aplicar); }

    /* Al elegir la persona, su sede (la de su ficha) queda marcada, si aún
       no hay ninguna: es la que casi siempre corresponde. No pisa lo elegido. */
    var persona = document.getElementById('usuario_empleadoid');
    function sedeDeLaPersona() {
        var op = persona && persona.selectedOptions[0], sede = op && op.dataset.sede;
        if (!sede || (todas && todas.checked) || lista.querySelector('input:checked')) { return; }
        var casilla = document.getElementById('sede' + sede);
        if (casilla) { casilla.checked = true; }
    }
    if (persona) { persona.addEventListener('change', sedeDeLaPersona); }
    sedeDeLaPersona();
    aplicar();
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
