<?php
/*
| Cambiar la contraseña de la propia cuenta, OBLIGATORIO.
|
| Aquí llega, desde cualquier pantalla, quien entró con una contraseña que
| le puso otra persona (alta o restablecida y enviada por correo o
| WhatsApp): clave_exigir_cambio_si_pendiente() no le deja ir a ningún otro
| sitio hasta cambiarla. Sólo actúa sobre quien tiene la sesión abierta:
| no hay id de usuario en el formulario.
|
| También se puede abrir sin tener el cambio pendiente (?p=clave): sirve
| como «Cambiar contraseña» desde el Hub.
*/

$pendiente     = clave_cambio_pendiente();
$nombreUsuario = ds_nombre_usuario();
$fotoUsuario   = !empty($_SESSION['foto'])
    ? media_url('empleado', $_SESSION['foto'])
    : DS_HUB_URL . 'ds_core/assets/img/avatar.png';

$vendorCss = DS_HUB_URL . 'ds_core/assets/vendor/fontawesome6/css/all.min.css';
$vendorJs  = DS_FUTBOL_URL . 'app/views/dist/js/sweetalert2.all.min.js';
$vendorSw  = DS_FUTBOL_URL . 'app/views/dist/css/sweetalert2.min.css';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo DS_HUB_NAME; ?> | Cambiar contraseña</title>

    <link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
    <?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo $vendorCss; ?>">
    <link rel="stylesheet" href="<?php echo $vendorSw; ?>">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">

    <style>
        .cc-caja { background: var(--ds-surface, #1c2333); border: 1px solid var(--ds-border, #2a3348);
                   border-radius: 14px; padding: 1.4rem; }
        .cc-titulo { display: flex; align-items: center; gap: .6rem; margin: 0 0 .6rem;
                     font-size: 1.15rem; font-weight: 600; }
        .cc-aviso { border-left: 4px solid #fbbf24; background: rgba(251,191,36,.10);
                    border-radius: 8px; padding: .75rem .9rem; margin-bottom: 1.2rem; font-size: .92rem; }
        .cc-campo { margin-bottom: 1rem; }
        .cc-campo label { display: block; font-weight: 600; font-size: .9rem; margin-bottom: .35rem; }
        .cc-entrada { display: flex; gap: .4rem; }
        .cc-entrada input { flex: 1; min-width: 0; min-height: 2.75rem; border-radius: 10px; padding: .55rem .75rem;
                            border: 1px solid var(--ds-border, #2a3348); background: rgba(148,163,184,.08);
                            color: inherit; font-size: 1rem; }
        .cc-entrada input:focus { outline: 2px solid var(--ds-accent, #6366f1); outline-offset: 1px; }
        .cc-ver { min-width: 2.75rem; border-radius: 10px; border: 1px solid var(--ds-border, #2a3348);
                  background: transparent; color: inherit; cursor: pointer; }
        .cc-regla { font-size: .82rem; opacity: .8; margin: .35rem 0 0; }
        .cc-botones { display: flex; gap: .6rem; flex-wrap: wrap; margin-top: 1.2rem; }
        .cc-boton { border: 0; border-radius: 10px; padding: .7rem 1.2rem; min-height: 2.75rem; cursor: pointer;
                    font-weight: 600; font-size: .95rem; text-decoration: none; display: inline-flex;
                    align-items: center; justify-content: center; gap: .4rem; }
        .cc-boton--pri { background: var(--ds-accent, #6366f1); color: #fff; flex: 1 1 12rem; }
        .cc-boton--sec { background: transparent; color: inherit; border: 1px solid var(--ds-border, #2a3348); }
        .cc-error { color: #f87171; font-size: .9rem; min-height: 1.2rem; margin: .5rem 0 0; }
    </style>
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
</head>
<body class="ds-body">

    <header class="ds-header">
        <div class="ds-container ds-header__inner">
            <span class="ds-brand">
                <img src="<?php echo DS_HUB_URL; ?>ds_core/assets/img/logo_ds.png" alt="<?php echo DS_HUB_NAME; ?>" class="ds-brand__logo">
                <span>
                    <p class="ds-brand__name"><?php echo DS_HUB_NAME; ?></p>
                    <p class="ds-brand__tagline">Cambiar contraseña</p>
                </span>
            </span>
            <div class="ds-header__actions">
                <div class="ds-user">
                    <img src="<?php echo $fotoUsuario; ?>" alt="" class="ds-user__avatar">
                    <span class="ds-user__name"><?php echo $h($nombreUsuario); ?></span>
                </div>
                <?php if (!$pendiente): ?>
                    <a href="<?php echo DS_HUB_URL; ?>" class="ds-iconbtn" title="Volver al Hub" aria-label="Volver al Hub"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
                <?php endif; ?>
                <a href="<?php echo DS_FUTBOL_URL; ?>logOut/" class="ds-iconbtn" title="Cerrar sesión" aria-label="Cerrar sesión"><i class="fas fa-sign-out-alt" aria-hidden="true"></i></a>
            </div>
        </div>
    </header>

    <main class="ds-container" style="max-width:520px;padding-top:1.6rem;padding-bottom:3rem;">
        <form class="cc-caja" id="ccForm" autocomplete="off" novalidate>
            <h1 class="cc-titulo"><i class="fas fa-key" aria-hidden="true"></i>
                <?php echo $pendiente ? 'Cambie su contraseña' : 'Cambiar contraseña'; ?></h1>

            <?php if ($pendiente): ?>
                <div class="cc-aviso" role="status">
                    La contraseña con la que entró se la asignó otra persona. Antes de seguir, elija una
                    que sólo usted conozca.
                </div>
            <?php endif; ?>

            <div class="cc-campo">
                <label for="ccActual"><?php echo $pendiente ? 'Contraseña que le enviaron' : 'Contraseña actual'; ?></label>
                <div class="cc-entrada">
                    <input type="password" id="ccActual" autocomplete="current-password" required>
                    <button type="button" class="cc-ver" data-ver="ccActual" aria-label="Mostrar u ocultar"><i class="fas fa-eye" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="cc-campo">
                <label for="ccNueva">Nueva contraseña</label>
                <div class="cc-entrada">
                    <input type="password" id="ccNueva" autocomplete="new-password" required
                           minlength="<?php echo clave_longitud_minima(); ?>" maxlength="<?php echo clave_longitud_maxima(); ?>">
                    <button type="button" class="cc-ver" data-ver="ccNueva" aria-label="Mostrar u ocultar"><i class="fas fa-eye" aria-hidden="true"></i></button>
                </div>
                <p class="cc-regla"><?php echo clave_regla_texto(); ?></p>
            </div>
            <div class="cc-campo">
                <label for="ccConfirmar">Repita la nueva contraseña</label>
                <div class="cc-entrada">
                    <input type="password" id="ccConfirmar" autocomplete="new-password" required>
                </div>
            </div>

            <p class="cc-error" id="ccError" role="alert"></p>

            <div class="cc-botones">
                <button type="submit" class="cc-boton cc-boton--pri" id="ccGuardar"><i class="fas fa-save" aria-hidden="true"></i>Guardar contraseña</button>
                <a href="<?php echo DS_FUTBOL_URL; ?>logOut/" class="cc-boton cc-boton--sec">Salir</a>
            </div>
        </form>
    </main>

<script src="<?php echo $vendorJs; ?>"></script>
<script>
(function () {
    var url = '<?php echo DS_HUB_URL; ?>?p=seguridadAjax';
    var form = document.getElementById('ccForm'), error = document.getElementById('ccError'), boton = document.getElementById('ccGuardar');

    document.querySelectorAll('[data-ver]').forEach(function (b) {
        b.addEventListener('click', function () {
            var c = document.getElementById(b.getAttribute('data-ver'));
            c.type = c.type === 'password' ? 'text' : 'password';
            b.innerHTML = c.type === 'password' ? '<i class="fas fa-eye" aria-hidden="true"></i>' : '<i class="fas fa-eye-slash" aria-hidden="true"></i>';
        });
    });

    form.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var actual = document.getElementById('ccActual').value, nueva = document.getElementById('ccNueva').value,
            confirmar = document.getElementById('ccConfirmar').value;
        error.textContent = '';
        if (!actual || !nueva || !confirmar) { error.textContent = 'Complete los tres campos.'; return; }
        if (nueva !== confirmar) { error.textContent = 'La nueva contraseña y su confirmación no coinciden.'; return; }

        var fd = new FormData();
        fd.append('modulo_hub', 'cambiarClave');
        fd.append('clave_actual', actual); fd.append('clave_nueva', nueva); fd.append('clave_confirmar', confirmar);
        boton.disabled = true;
        fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.ok) { boton.disabled = false; error.textContent = j.texto || 'No se pudo cambiar la contraseña.'; return; }
                Swal.fire({ icon: 'success', title: 'Contraseña cambiada', text: 'Ya puede usar el sistema con su nueva contraseña.', confirmButtonText: 'Continuar' })
                    .then(function () { location.href = '<?php echo DS_HUB_URL; ?>'; });
            })
            .catch(function () { boton.disabled = false; error.textContent = 'No se pudo contactar con el servidor. Inténtelo de nuevo.'; });
    });
})();
</script>
</body>
</html>
