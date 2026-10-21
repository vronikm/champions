<?php
/*
| Seguridad de la propia cuenta.
|
| Vive en el Hub y no en Core a propósito: sólo el rol 1 tiene concedido
| el módulo core, así que ahí los otros usuarios no habrían podido
| proteger su cuenta. El Hub es la única puerta por la que pasa todo el
| mundo.
|
| Todo lo que hace esta pantalla actúa sobre quien tiene la sesión
| abierta. No hay ningún id de usuario en el formulario ni en las
| peticiones: configurar el segundo factor de otra persona no tendría
| sentido —haría falta su teléfono— y sí sería una puerta trasera.
*/

use hub\hubController;

$insHub = new hubController();

$yo = usuario_actual_id();

$estado    = dosf_estado($yo);
$restantes = dosf_recuperacion_restantes($yo);
$historial = dosf_historial($yo, 10);

/* Si hay una configuración a medias, se pinta el QR para terminarla. */
$secreto = '';
$svgQr   = '';

if ($estado === 'P') {
    $secreto = dosf_secreto($yo);
    if ($secreto !== '') {
        $svgQr = qr_svg(totp_uri($secreto, ds_nombre_usuario(), dosf_emisor()), 5);
    }
}

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
    <title><?php echo DS_HUB_NAME; ?> | Mi seguridad</title>

    <link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
    <?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo $vendorCss; ?>">
    <link rel="stylesheet" href="<?php echo $vendorSw; ?>">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">

    <style>
        .sg-caja { background: var(--ds-surface, #1c2333); border: 1px solid var(--ds-border, #2a3348);
                   border-radius: 14px; padding: 1.4rem; margin-bottom: 1.2rem; }
        .sg-titulo { display: flex; align-items: center; gap: .6rem; margin: 0 0 1rem;
                     font-size: 1.05rem; font-weight: 600; }
        .sg-pastilla { margin-left: auto; font-size: .78rem; padding: .2rem .6rem;
                       border-radius: 999px; font-weight: 600; }
        .sg-pastilla--on  { background: rgba(52,211,153,.15); color: #34d399; }
        .sg-pastilla--med { background: rgba(251,191,36,.15); color: #fbbf24; }
        .sg-pastilla--off { background: rgba(148,163,184,.15); color: #94a3b8; }
        .sg-boton { border: 0; border-radius: 10px; padding: .6rem 1.1rem; cursor: pointer;
                    font-weight: 600; font-size: .92rem; }
        .sg-boton--pri { background: var(--ds-accent, #6366f1); color: #fff; }
        .sg-boton--ok  { background: #16a34a; color: #fff; }
        .sg-boton--sec { background: transparent; color: var(--ds-text-muted, #94a3b8);
                         border: 1px solid var(--ds-border, #2a3348); }
        .sg-boton--mal { background: transparent; color: #f87171; border: 1px solid #f87171; }
        .sg-clave { font-family: ui-monospace, Menlo, Consolas, monospace; letter-spacing: .12rem;
                    background: rgba(148,163,184,.10); border: 1px solid var(--ds-border, #2a3348);
                    border-radius: 8px; padding: .55rem .7rem; width: 100%; color: inherit; }
        .sg-codigo { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 1.5rem;
                     letter-spacing: .4rem; text-align: center; width: 190px; }
        .sg-fila { display: flex; gap: 1.4rem; flex-wrap: wrap; }
        .sg-qr { background: #fff; padding: .7rem; border-radius: 10px; line-height: 0; }
        .sg-mov { display: flex; gap: .7rem; padding: .55rem 0;
                  border-bottom: 1px solid var(--ds-border, #2a3348); font-size: .88rem; }
        .sg-mov:last-child { border-bottom: 0; }
        .sg-eti { font-size: .72rem; padding: .15rem .5rem; border-radius: 999px;
                  background: rgba(148,163,184,.15); white-space: nowrap; }
    </style>
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
</head>
<body class="ds-body">

    <header class="ds-header">
        <div class="ds-container ds-header__inner">
            <a href="<?php echo DS_HUB_URL; ?>" class="ds-brand">
                <img src="<?php echo DS_HUB_URL; ?>ds_core/assets/img/logo_ds.png"
                     alt="<?php echo DS_HUB_NAME; ?>" class="ds-brand__logo">
                <span>
                    <p class="ds-brand__name"><?php echo DS_HUB_NAME; ?></p>
                    <p class="ds-brand__tagline">Mi seguridad</p>
                </span>
            </a>

            <div class="ds-header__actions">
                <div class="ds-user">
                    <img src="<?php echo $fotoUsuario; ?>" alt="" class="ds-user__avatar">
                    <span>
                        <span class="ds-user__name"><?php echo $h($nombreUsuario); ?></span><br>
                        <span class="ds-user__role"><?php echo $h($_SESSION['sede'] ?: 'Todas las sedes'); ?></span>
                    </span>
                </div>
                <a href="<?php echo DS_HUB_URL; ?>" class="ds-iconbtn" title="Volver al Hub">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <a href="<?php echo DS_FUTBOL_URL; ?>logOut/" class="ds-iconbtn" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </header>

    <main class="ds-container" style="max-width:960px;padding-top:1.6rem;padding-bottom:3rem;">

        <div class="sg-caja">
            <h2 class="sg-titulo">
                <i class="fas fa-mobile-alt"></i> Verificación en dos pasos
                <span class="sg-pastilla sg-pastilla--<?php
                    echo $estado === 'A' ? 'on' : ($estado === 'P' ? 'med' : 'off'); ?>">
                    <?php echo $estado === 'A' ? 'Activa'
                             : ($estado === 'P' ? 'A medio configurar' : 'Desactivada'); ?>
                </span>
            </h2>

            <?php /*==========  Sin configurar  ==========*/ ?>
            <?php if ($estado === 'N'): ?>
                <p style="opacity:.85;margin-top:0;">
                    Una contraseña robada sirve para entrar, por larga que sea. Con la
                    verificación en dos pasos hace falta además un código que sólo genera
                    su teléfono y que cambia cada 30 segundos.
                </p>
                <p style="opacity:.7;font-size:.9rem;">
                    Necesitará una aplicación de verificación: Google Authenticator,
                    Microsoft Authenticator, Authy, 1Password o cualquier otra que lea
                    códigos QR.
                </p>
                <button type="button" class="sg-boton sg-boton--pri" id="btnPreparar">
                    <i class="fas fa-shield-alt"></i> Activar la verificación
                </button>

            <?php /*==========  A medio configurar  ==========*/ ?>
            <?php elseif ($estado === 'P'): ?>
                <div class="sg-fila">
                    <div>
                        <?php if ($svgQr !== ''): ?>
                            <div class="sg-qr"><?php echo $svgQr; ?></div>
                        <?php else: ?>
                            <p style="color:#fbbf24;font-size:.88rem;max-width:200px;">
                                No se pudo dibujar el código. Use la clave de al lado para
                                añadir la cuenta a mano.
                            </p>
                        <?php endif; ?>
                    </div>

                    <div style="flex:1;min-width:280px;">
                        <p style="margin-top:0;"><strong>1.</strong> Escanee el código con su aplicación.</p>

                        <?php /* La clave SIEMPRE a la vista, no sólo si el QR
                                 falla: hay cámaras que no enfocan una pantalla,
                                 y sin esta salida el usuario se queda atascado
                                 sin entender por qué. */ ?>
                        <p style="opacity:.75;font-size:.87rem;margin-bottom:.35rem;">
                            ¿No puede escanear? Añádala a mano con esta clave:
                        </p>
                        <div style="display:flex;gap:.4rem;margin-bottom:1.1rem;">
                            <input type="text" class="sg-clave" id="claveManual" readonly
                                   value="<?php echo $h(totp_secreto_legible($secreto)); ?>">
                            <button type="button" class="sg-boton sg-boton--sec" id="btnCopiar">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>

                        <p><strong>2.</strong> Escriba el código que aparece:</p>
                        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;">
                            <input type="text" class="sg-clave sg-codigo" id="codigoConfirma"
                                   inputmode="numeric" maxlength="6" placeholder="000000"
                                   autocomplete="off">
                            <button type="button" class="sg-boton sg-boton--ok" id="btnActivar">
                                Activar
                            </button>
                        </div>
                        <p style="opacity:.7;font-size:.85rem;margin-top:.8rem;">
                            Aún no está activa: mientras no confirme el código, su cuenta
                            entra sólo con la contraseña.
                        </p>

                        <button type="button" class="sg-boton sg-boton--sec" id="btnCancelar"
                                style="margin-top:.6rem;">
                            Cancelar la configuración
                        </button>
                    </div>
                </div>

            <?php /*==========  Activa  ==========*/ ?>
            <?php else: ?>
                <p style="color:#34d399;margin-top:0;">
                    <i class="fas fa-check-circle"></i>
                    Su cuenta está protegida. Al entrar se le pedirá el código de su
                    aplicación además de la contraseña.
                </p>

                <div style="display:flex;align-items:center;gap:.8rem;flex-wrap:wrap;margin:1.2rem 0;">
                    <span style="opacity:.85;font-size:.9rem;">
                        Códigos de recuperación sin usar:
                        <strong style="color:<?php echo $restantes <= 2 ? '#f87171' : 'inherit'; ?>">
                            <?php echo $restantes; ?></strong> de 10
                    </span>
                    <button type="button" class="sg-boton sg-boton--sec" id="btnRegenerar"
                            style="margin-left:auto;">
                        <i class="fas fa-sync-alt"></i> Generar códigos nuevos
                    </button>
                </div>

                <?php if ($restantes <= 2): ?>
                <p style="color:#fbbf24;font-size:.87rem;">
                    Le quedan pocos códigos. Si pierde el teléfono y se agotan, tendrá que
                    pedir al administrador que le restablezca la verificación.
                </p>
                <?php endif; ?>

                <button type="button" class="sg-boton sg-boton--mal" id="btnDesactivar">
                    <i class="fas fa-times"></i> Desactivar la verificación
                </button>
            <?php endif; ?>
        </div>

        <div class="sg-caja">
            <h2 class="sg-titulo"><i class="fas fa-history"></i> Movimientos</h2>

            <?php if (!$historial): ?>
                <p style="opacity:.6;margin:0;">Sin movimientos.</p>
            <?php else: foreach ($historial as $e): ?>
                <div class="sg-mov">
                    <span class="sg-eti"><?php echo $h($e['ev_accion']); ?></span>
                    <span style="flex:1;">
                        <?php echo $h($e['ev_nota']); ?>
                        <?php /* Cuando lo hizo otra persona, hay que verlo. */ ?>
                        <?php if ($e['ev_autor'] !== '' && $e['ev_autor'] !== $nombreUsuario): ?>
                            <br><span style="color:#f87171;font-size:.82rem;">
                                <i class="fas fa-user-shield"></i> por <?php echo $h($e['ev_autor']); ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span style="opacity:.6;white-space:nowrap;font-size:.82rem;">
                        <?php echo $h($e['ev_fecha']); ?>
                    </span>
                </div>
            <?php endforeach; endif; ?>

            <p style="opacity:.6;font-size:.84rem;margin:1rem 0 0;">
                Queda registrado quién toca su verificación. Si aparece un movimiento que
                no reconoce, cambie la contraseña y avise.
            </p>
        </div>

    </main>

<script src="<?php echo $vendorJs; ?>"></script>
<script>
(function () {
    var url = '<?php echo DS_HUB_URL; ?>?p=seguridadAjax';

    var enviar = function (campos, alTerminar) {
        var fd = new FormData();
        for (var k in campos) { fd.append(k, campos[k]); }
        fetch(url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (alTerminar) { alTerminar(j); return; }
                Swal.fire({ icon: j.icono, title: j.titulo, text: j.texto })
                    .then(function () { if (j.tipo === 'recargar') { location.reload(); } });
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Sin respuesta',
                            text: 'No se pudo contactar con el servidor.' });
            });
    };

    /* Los códigos de recuperación se muestran UNA sola vez. El cuadro no
       se cierra por fuera ni con Escape: es la única oportunidad de
       tenerlos, y cerrarlo sin querer deja al usuario sin salida el día
       que pierda el teléfono. */
    var mostrarCodigos = function (j) {
        if (!j.codigos) {
            Swal.fire({ icon: j.icono, title: j.titulo, text: j.texto })
                .then(function () { if (j.tipo === 'recargar') { location.reload(); } });
            return;
        }

        var lista = j.codigos.map(function (c) {
            return '<code style="font-size:1.05rem;">' + c + '</code>';
        }).join('<br>');

        Swal.fire({
            icon: 'success',
            title: 'Guarde estos códigos',
            html: '<div style="text-align:left">'
                + '<p style="font-size:.92rem;">Cada uno sirve <b>una sola vez</b> y le '
                + 'permite entrar si pierde el teléfono. <b>No se volverán a mostrar.</b></p>'
                + '<div style="background:#f4f6f9;border:1px solid #dee2e6;border-radius:6px;'
                + 'padding:.8rem;text-align:center;line-height:1.9;color:#111;">' + lista + '</div>'
                + '</div>',
            confirmButtonText: 'Ya los guardé',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showDenyButton: true,
            denyButtonText: 'Copiar'
        }).then(function (r) {
            if (r.isDenied) {
                navigator.clipboard.writeText(j.codigos.join('\n'));
                mostrarCodigos(j);      /* copiar no es cerrar */
                return;
            }
            location.reload();
        });
    };

    var pideClave = function (titulo, texto, cb) {
        Swal.fire({
            icon: 'warning', title: titulo, html: texto,
            input: 'password',
            inputPlaceholder: 'Su contraseña',
            inputAttributes: { autocomplete: 'current-password' },
            showCancelButton: true,
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            preConfirm: function (v) {
                if (!v) { Swal.showValidationMessage('Escriba su contraseña.'); return false; }
                return v;
            }
        }).then(function (r) { if (r.isConfirmed) { cb(r.value); } });
    };

    var el = function (id) { return document.getElementById(id); };

    if (el('btnPreparar')) {
        el('btnPreparar').addEventListener('click', function () {
            enviar({ modulo_hub: 'prepararSegundoFactor' });
        });
    }

    if (el('btnActivar')) {
        el('btnActivar').addEventListener('click', function () {
            var c = el('codigoConfirma').value.replace(/\D+/g, '');
            if (c.length !== 6) {
                Swal.fire({ icon: 'warning', title: 'Código incompleto',
                            text: 'Escriba los seis dígitos que muestra la aplicación.' });
                return;
            }
            enviar({ modulo_hub: 'activarSegundoFactor', codigo: c }, mostrarCodigos);
        });

        el('codigoConfirma').addEventListener('input', function (e) {
            e.target.value = e.target.value.replace(/\D+/g, '').slice(0, 6);
        });
    }

    if (el('btnCopiar')) {
        el('btnCopiar').addEventListener('click', function () {
            navigator.clipboard.writeText(el('claveManual').value.replace(/\s+/g, ''));
            el('btnCopiar').innerHTML = '<i class="fas fa-check"></i>';
        });
    }

    if (el('btnCancelar')) {
        el('btnCancelar').addEventListener('click', function () {
            Swal.fire({
                icon: 'question', title: '¿Cancelar la configuración?',
                text: 'El código que escaneó dejará de servir.',
                showCancelButton: true,
                confirmButtonText: 'Cancelar configuración',
                cancelButtonText: 'Seguir configurando'
            }).then(function (r) {
                /* Sin contraseña: lo que se cancela todavía no protege nada. */
                if (r.isConfirmed) { enviar({ modulo_hub: 'desactivarSegundoFactor' }); }
            });
        });
    }

    if (el('btnDesactivar')) {
        el('btnDesactivar').addEventListener('click', function () {
            pideClave('¿Desactivar la verificación?',
                'Su cuenta volverá a entrar sólo con la contraseña.<br>'
                + '<small>Confirme su contraseña para continuar.</small>',
                function (clave) {
                    enviar({ modulo_hub: 'desactivarSegundoFactor', clave: clave });
                });
        });
    }

    if (el('btnRegenerar')) {
        el('btnRegenerar').addEventListener('click', function () {
            pideClave('¿Generar códigos nuevos?',
                'Los códigos anteriores <b>dejarán de valer</b>.<br>'
                + '<small>Confirme su contraseña para continuar.</small>',
                function (clave) {
                    enviar({ modulo_hub: 'regenerarCodigos', clave: clave }, mostrarCodigos);
                });
        });
    }
})();
</script>

</body>
</html>
