<?php
/*
| Segundo paso del acceso: el código de verificación.
|
| EL POST SE PROCESA ANTES DE IMPRIMIR NADA, por lo mismo que en
| login-view: si la página empieza a salir, session_regenerate_id() falla
| con un aviso y la redirección se degrada a un <script>. Cualquier cambio
| debe mantener este bloque el primero.
|
| A esta pantalla sólo se llega con la marca que dejó el paso de la
| contraseña. Sin ella no hay nada que verificar y se vuelve al principio:
| es lo que impide entrar por aquí sin haber pasado por la primera puerta.
*/

if (isset($_POST['codigo_2fa'])) {
    $insLogin->verificar2faControlador();
}

$pendiente = dosf_pendiente();

if (!$pendiente) {
    $_SESSION['login_aviso'] = 'La verificación caducó. Vuelva a iniciar sesión.';
    header('Location: ' . APP_URL . 'login/');
    exit;
}

$aviso = (string)($_SESSION['login2fa_aviso'] ?? '');
unset($_SESSION['login2fa_aviso']);

$restantes = dosf_recuperacion_restantes((int)$pendiente['usuario_id']);

$logo   = APP_URL . 'app/views/dist/img/Logos/logo_bsc.png';
$vendor = APP_URL . 'app/views/dist/';
$h      = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo APP_NAME; ?> | Verificación</title>

    <link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">
    <link rel="stylesheet" href="<?php echo $vendor; ?>css/sweetalert2.min.css">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/login.css'); ?>">

    <style>
        /* El campo del código se lee de un vistazo: dígitos grandes,
           espaciados y en monoespaciada, que es como vienen en la
           aplicación del teléfono. */
        .dsl-codigo {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 1.9rem; letter-spacing: .55rem; text-align: center;
            padding-left: .55rem;   /* compensa el espaciado del último dígito */
        }
        .dsl-alterno {
            background: none; border: 0; padding: 0; cursor: pointer;
            color: inherit; font: inherit; text-decoration: underline;
        }
    </style>
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
</head>
<body>

<div class="dsl-marco">
    <section class="dsl-panel">
        <div class="dsl-card">

            <div class="dsl-card__emblema">
                <img src="<?php echo $logo; ?>" alt="<?php echo APP_NAME; ?>">
            </div>

            <h2 class="dsl-card__titulo">Verificación en dos pasos</h2>
            <div class="dsl-card__regla"></div>

            <p style="text-align:center;margin:0 0 1.2rem;opacity:.85;font-size:.95rem;">
                Hola, <strong><?php echo $h($pendiente['usuario']); ?></strong>.<br>
                Escriba el código que muestra su aplicación de verificación.
            </p>

            <?php /* Dos formularios, no uno con un interruptor: cada uno
                     envía su propio «modo» y así el servidor no tiene que
                     adivinar qué se le está mandando. */ ?>
            <form method="POST" action="" id="formCodigo">
                <?php echo csrf_campo('2fa'); ?>
                <input type="hidden" name="modo" value="totp">

                <div class="dsl-campo">
                    <label class="dsl-campo__etiqueta" for="codigo_2fa">Código de 6 dígitos</label>
                    <div class="dsl-campo__caja">
                        <input type="text" class="dsl-campo__input dsl-codigo"
                               id="codigo_2fa" name="codigo_2fa"
                               inputmode="numeric" autocomplete="one-time-code"
                               pattern="[0-9]{6}" maxlength="6" autofocus
                               placeholder="······" required>
                    </div>
                </div>

                <button type="submit" class="dsl-boton" id="botonVerificar">
                    Verificar
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <?php /* La salida cuando no está el teléfono. Se muestra
                     plegada para que no compita con el camino normal, pero
                     tiene que estar a la vista: si no se encuentra, la
                     gente llama para que le desactiven el factor. */ ?>
            <form method="POST" action="" id="formRecuperacion" style="display:none;">
                <?php echo csrf_campo('2fa'); ?>
                <input type="hidden" name="modo" value="recuperacion">

                <div class="dsl-campo">
                    <label class="dsl-campo__etiqueta" for="codigo_rec">Código de recuperación</label>
                    <div class="dsl-campo__caja">
                        <input type="text" class="dsl-campo__input"
                               id="codigo_rec" name="codigo_2fa"
                               maxlength="9" placeholder="XXXX-XXXX"
                               autocomplete="off" style="text-transform:uppercase;">
                    </div>
                    <small style="opacity:.75;">
                        <?php if ($restantes > 0): ?>
                            Le quedan <?php echo $restantes; ?> códigos sin usar. Cada uno sirve una vez.
                        <?php else: ?>
                            No le quedan códigos de recuperación. Contacte al administrador.
                        <?php endif; ?>
                    </small>
                </div>

                <button type="submit" class="dsl-boton">
                    Usar código de recuperación
                </button>
            </form>

            <div class="dsl-pie__sep"></div>

            <p class="dsl-pie">
                <button type="button" class="dsl-alterno" id="alternar">
                    No tengo el teléfono a mano
                </button><br>
                <a href="<?php echo APP_URL; ?>login/" style="opacity:.8;">Volver al inicio</a>
            </p>
        </div>
    </section>
</div>

<script src="<?php echo $vendor; ?>js/sweetalert2.all.min.js"></script>

<script>
/* Alternar entre el código del teléfono y el de recuperación. */
(function () {
    var enlace = document.getElementById('alternar');
    var normal = document.getElementById('formCodigo');
    var resc   = document.getElementById('formRecuperacion');
    if (!enlace || !normal || !resc) { return; }

    enlace.addEventListener('click', function () {
        var usandoRescate = resc.style.display !== 'none';
        normal.style.display = usandoRescate ? '' : 'none';
        resc.style.display   = usandoRescate ? 'none' : '';
        enlace.textContent   = usandoRescate
            ? 'No tengo el teléfono a mano'
            : 'Tengo el teléfono: usar el código de 6 dígitos';
        var foco = usandoRescate ? document.getElementById('codigo_2fa')
                                 : document.getElementById('codigo_rec');
        if (foco) { foco.focus(); }
    });
})();

/* Sólo dígitos, y envío automático al completar los seis: el código
   caduca en 30 segundos y cada pulsación de más cuenta. */
(function () {
    var campo = document.getElementById('codigo_2fa');
    var form  = document.getElementById('formCodigo');
    if (!campo || !form) { return; }

    campo.addEventListener('input', function () {
        campo.value = campo.value.replace(/\D+/g, '').slice(0, 6);
        if (campo.value.length === 6) { form.requestSubmit(); }
    });

    form.addEventListener('submit', function () {
        var b = document.getElementById('botonVerificar');
        if (b) { b.disabled = true; b.querySelector('i').className = 'fas fa-circle-notch fa-spin'; }
    });
})();
</script>

<?php if ($aviso !== ''): ?>
<script>
Swal.fire({
    icon:  'error',
    title: 'No se pudo verificar',
    text:  <?php echo json_encode($aviso, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    confirmButtonColor: '#ff7900'
}).then(function () {
    var c = document.getElementById('codigo_2fa');
    if (c) { c.value = ''; c.focus(); }
});
</script>
<?php endif; ?>

</body>
</html>
