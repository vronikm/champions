<?php

/**
 * Autorizaciones LOPDP para un representante YA registrado.
 *
 * La escuela envía este enlace por WhatsApp o correo desde Fútbol ›
 * Consentimientos LOPDP a quien todavía no ha autorizado. Muestra SÓLO las
 * dos autorizaciones de la inscripción (mismos textos, mismo hash) y graba
 * la respuesta para cada alumno activo del representante.
 *
 * Funciona sin JavaScript: el formulario se envía a esta misma página, que
 * graba y redirige (POST → GET) a la pantalla de agradecimiento.
 *
 * El enlace vale una sola vez, hasta su vencimiento, y sólo si su código
 * está en consentimiento_enlace (autorizacionController::estadoEnlace).
 */

    require_once __DIR__ . "/../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
    require_once "./config/app.php";
    require_once "./autoload.php";

    use app\models\TokenHelper;
    use app\controllers\autorizacionController;
    use app\controllers\registroController;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    require_once "./app/views/inc/session_start.php";

    /* Las mismas cabeceras que index.php. */
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: same-origin");
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "font-src 'self' data:; "
         . "img-src 'self' data:; "
         . "connect-src 'self'; "
         . "object-src 'none'; base-uri 'self'; "
         . "form-action 'self'; frame-ancestors 'none'");

    $t      = (string)($_POST['t'] ?? $_GET['t'] ?? '');
    $token  = $t !== '' ? TokenHelper::validarAutorizacion($t) : null;
    $ctrl   = new autorizacionController();
    $estado = $token ? $ctrl->estadoEnlace($token) : ['estado' => 'invalido', 'enlace' => null];
    $error  = '';

    /*----------  Respuesta enviada  ----------*/
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $estado['estado'] === 'valido') {
        if (!hash_equals((string)($_SESSION['autorizacion_csrf'] ?? ''), (string)($_POST['csrf'] ?? ''))) {
            $error = 'La página estuvo abierta demasiado tiempo. Revise sus respuestas y vuelva a enviarlas.';
        } else {
            $r = $ctrl->responder($token, $estado['enlace'], (string)($_POST['acepta_datos'] ?? ''), (string)($_POST['imagen'] ?? ''));
            if ($r['ok']) {
                $_SESSION['autorizacion_gracias'] = ['codigo' => $token['c'], 'imagen' => $r['imagen']];
                unset($_SESSION['autorizacion_csrf']);
                header('Location: ' . APP_URL . 'autorizacion.php?t=' . rawurlencode($t), true, 303);
                exit();
            }
            $error = $r['error'];
            $estado = $ctrl->estadoEnlace($token);   // pudo gastarse entre tanto
        }
    }

    /*----------  Qué pantalla toca  ----------*/
    $gracias = $_SESSION['autorizacion_gracias'] ?? null;
    if ($estado['estado'] === 'usado' && $gracias && $token && hash_equals($gracias['codigo'], $token['c'])) {
        $pantalla = 'gracias';
    } else {
        $pantalla = $estado['estado'];   // valido | usado | expirado | invalido
    }

    if ($pantalla === 'valido') {
        $datos = $ctrl->datosRepresentante($token['r']);
        if (!$datos['alumnos']) {
            $pantalla = 'sin_alumnos';
        } else {
            $textos = (new registroController())->textosConsentimiento();
            $_SESSION['autorizacion_csrf'] ??= bin2hex(random_bytes(32));
            $imagenActual = count(array_unique(array_column($datos['alumnos'], 'imagen'))) === 1 ? $datos['alumnos'][0]['imagen'] : null;
            $imagenElegida = $_POST['imagen'] ?? $imagenActual;
        }
    } elseif ($pantalla !== 'gracias') {
        http_response_code($pantalla === 'invalido' ? 403 : 410);
    }

    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $nombre = static fn($v) => mb_convert_case(mb_strtolower(trim(html_entity_decode((string)$v, ENT_QUOTES, 'UTF-8')), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

    $logoOrg = trim((string) ds_organizacion()['escuela_logo']);
    $logoUrl = ($logoOrg !== '' && is_file(DS_HUB_PATH . 'ds_core/assets/img/marca/' . basename($logoOrg)))
        ? DS_HUB_URL . 'ds_core/assets/img/marca/' . rawurlencode(basename($logoOrg))
        : DS_HUB_URL . 'ds_core/assets/img/icon_cz98px.png';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $h(ESCUELA_NOMBRE); ?> | Autorización de datos e imagen</title>
    <link rel="icon" type="image/png" href="<?php echo DS_HUB_URL; ?>ds_core/assets/img/icon_cz98px.png">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css" rel="stylesheet">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/css/fuentes.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>assets/css/style.css?v=<?php
        echo @filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
    <style>
        .cz-aut { max-width: 760px; }
        .cz-aut__hijos { list-style: none; padding: 0; margin: .75rem 0 0; display: grid; gap: .5rem; }
        .cz-aut__hijos li {
            display: flex; align-items: center; gap: .6rem;
            padding: .6rem .8rem; border: 1px solid var(--cz-borde-suave);
            border-radius: 10px; background: var(--cz-fondo); font-weight: 600;
        }
        .cz-aut__hijos i { color: var(--cz-azul-3); }
        .cz-aut__texto {
            max-height: 260px; overflow-y: auto; font-size: .9rem; line-height: 1.6;
            background: var(--cz-fondo); border-radius: 10px; padding: 1rem;
        }
        .cz-aut__texto p:last-child { margin-bottom: 0; }
        /* Opciones grandes: se tocan con el pulgar, y la elegida se ve. */
        .cz-aut__opcion {
            display: flex; align-items: flex-start; gap: .75rem; cursor: pointer;
            padding: .9rem 1rem; border: 2px solid var(--cz-borde); border-radius: 12px;
            background: var(--cz-superficie); font-weight: 600; margin: 0;
        }
        .cz-aut__opcion .form-check-input { margin: .15rem 0 0; flex: none; width: 1.35rem; height: 1.35rem; }
        .cz-aut__opcion:has(.form-check-input:checked) { border-color: var(--cz-azul); background: #f2f5fc; }
        .cz-aut__opcion:has(.form-check-input:focus-visible) { outline: 3px solid var(--cz-naranja); outline-offset: 2px; }
        .cz-aut__opcion small { display: block; font-weight: 400; color: var(--cz-texto-suave); }
        .cz-aut__enviar { width: 100%; padding: .9rem 1rem; font-size: 1.05rem; }
        @media (min-width: 576px) { .cz-aut__enviar { width: auto; } }
    </style>
</head>
<body>

<header class="cz-header">
    <div class="container cz-aut">
        <div class="d-flex align-items-center gap-3">
            <img src="<?php echo $h($logoUrl); ?>" alt="" height="52" class="cz-logo">
            <div>
                <h1><?php echo $h(ESCUELA_NOMBRE); ?></h1>
                <span class="cz-sede"><i class="fas fa-shield-halved" aria-hidden="true"></i> Autorización de datos e imagen</span>
            </div>
        </div>
    </div>
</header>

<main class="container cz-aut py-4">

<?php if ($pantalla === 'valido'): ?>

    <form method="POST" action="<?php echo APP_URL; ?>autorizacion.php" id="formAutorizacion">
        <input type="hidden" name="t" value="<?php echo $h($t); ?>">
        <input type="hidden" name="csrf" value="<?php echo $h($_SESSION['autorizacion_csrf']); ?>">

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger d-flex gap-2" role="alert">
                <i class="fas fa-triangle-exclamation mt-1" aria-hidden="true"></i><div><?php echo $h($error); ?></div>
            </div>
        <?php endif; ?>

        <div class="cz-card card mb-4">
            <div class="card-body">
                <h2 class="h5 fw-bold mb-1" style="color: var(--cz-azul);">Hola, <?php echo $h($nombre($datos['nombre'])); ?></h2>
                <p class="mb-0 text-body-secondary">
                    Como representante, necesitamos su autorización para
                    <?php echo count($datos['alumnos']) > 1 ? 'sus representados' : 'su representado/a'; ?>:
                </p>
                <ul class="cz-aut__hijos">
                    <?php foreach ($datos['alumnos'] as $a): ?>
                        <li><i class="fas fa-child-reaching" aria-hidden="true"></i><?php echo $h($nombre($a['nombre'])); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <!-- 1 · Tratamiento de datos -->
        <div class="cz-card card mb-4">
            <div class="card-header"><i class="fas fa-file-shield" aria-hidden="true"></i> 1. <?php echo $h($textos['DATOS']['titulo']); ?></div>
            <div class="card-body">
                <div class="cz-aut__texto mb-3" tabindex="0" aria-label="Texto del consentimiento"><?php echo $textos['DATOS']['texto']; ?></div>
                <label class="cz-aut__opcion" for="acepta_datos">
                    <input class="form-check-input" type="checkbox" id="acepta_datos" name="acepta_datos" value="S" required
                        <?php echo ($_POST['acepta_datos'] ?? '') === 'S' ? 'checked' : ''; ?>>
                    <span><?php echo $h($textos['DATOS']['casilla']); ?> <span class="cz-req" title="Obligatorio">*</span></span>
                </label>
            </div>
        </div>

        <!-- 2 · Uso de imagen: es libre, se puede decir que no -->
        <div class="cz-card card mb-4">
            <div class="card-header"><i class="fas fa-camera" aria-hidden="true"></i> 2. <?php echo $h($textos['IMAGEN']['titulo']); ?></div>
            <div class="card-body">
                <div class="cz-aut__texto mb-3" tabindex="0" aria-label="Texto de la autorización"><?php echo $textos['IMAGEN']['texto']; ?></div>
                <fieldset>
                    <legend class="fs-6 fw-semibold mb-2">¿Autoriza el uso de la imagen? <span class="cz-req" title="Obligatorio">*</span></legend>
                    <div class="d-grid gap-2">
                        <label class="cz-aut__opcion" for="imagen_si">
                            <input class="form-check-input" type="radio" id="imagen_si" name="imagen" value="S" required <?php echo $imagenElegida === 'S' ? 'checked' : ''; ?>>
                            <span>Sí, autorizo<small><?php echo $h($textos['IMAGEN']['casilla']); ?></small></span>
                        </label>
                        <label class="cz-aut__opcion" for="imagen_no">
                            <input class="form-check-input" type="radio" id="imagen_no" name="imagen" value="N" <?php echo $imagenElegida === 'N' ? 'checked' : ''; ?>>
                            <span>No autorizo<small>No se publicarán fotos ni videos en los que aparezca.</small></span>
                        </label>
                    </div>
                </fieldset>
            </div>
        </div>

        <div class="alert alert-info d-flex gap-2" role="note" style="font-size: .9rem;">
            <i class="fas fa-circle-info mt-1" aria-hidden="true"></i>
            <div>
                Al enviar, declaro que soy el representante legal y que la respuesta vale para
                <?php echo count($datos['alumnos']) > 1 ? 'todos los alumnos de la lista' : 'el alumno/a indicado'; ?>.
                Puedo cambiarla o revocarla cuando quiera comunicándome con la escuela.
                Este enlace vence el <?php echo $h(date('d/m/Y', strtotime($estado['enlace']['enlace_expira']))); ?> y sirve una sola vez.
            </div>
        </div>

        <button type="submit" class="btn btn-cz btn-cz-enviar cz-aut__enviar" id="btnEnviar">
            <i class="fas fa-circle-check" aria-hidden="true"></i> Enviar mi respuesta
        </button>
    </form>

    <script>
    /* Sin doble envío: el botón se desactiva al mandar (el servidor, además,
       sólo acepta una respuesta por enlace). */
    document.getElementById('formAutorizacion').addEventListener('submit', function () {
        var b = document.getElementById('btnEnviar');
        setTimeout(function () { b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Enviando…'; }, 0);
    });
    </script>

<?php else: ?>

    <div class="cz-card card">
        <?php if ($pantalla === 'gracias'): ?>
            <div class="card-body cz-exito" role="status">
                <i class="fas fa-circle-check" aria-hidden="true"></i>
                <h2>¡Gracias! Registramos su respuesta</h2>
                <p class="mb-1">Tratamiento de datos personales: <strong>autorizado</strong>.</p>
                <p>Uso de imagen: <strong><?php echo $gracias['imagen'] === 'S' ? 'autorizado' : 'no autorizado'; ?></strong>.</p>
                <p class="small">Si necesita cambiarla, comuníquese con la escuela. Ya puede cerrar esta ventana.</p>
            </div>
        <?php else: ?>
            <div class="card-body cz-expirado">
                <?php if ($pantalla === 'usado'): ?>
                    <i class="fas fa-circle-check text-success" aria-hidden="true"></i>
                    <h2>Este enlace ya se respondió</h2>
                    <p>Su respuesta quedó registrada. Si necesita cambiarla, comuníquese con la escuela.</p>
                <?php elseif ($pantalla === 'expirado'): ?>
                    <i class="fas fa-clock-rotate-left text-warning" aria-hidden="true"></i>
                    <h2>El enlace venció</h2>
                    <p>Pida a la escuela que le envíe uno nuevo.</p>
                <?php elseif ($pantalla === 'sin_alumnos'): ?>
                    <i class="fas fa-user-slash text-secondary" aria-hidden="true"></i>
                    <h2>No hay alumnos activos</h2>
                    <p>No encontramos alumnos activos asociados a este enlace. Comuníquese con la escuela.</p>
                <?php else: ?>
                    <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    <h2>Enlace no válido</h2>
                    <p>Use el enlace exacto que le envió la escuela, o pida uno nuevo.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

</main>

<footer class="cz-footer">
    <div class="container">
        &copy; <?php echo date('Y'); ?> <?php echo $h(ESCUELA_NOMBRE); ?> &mdash; Ley Orgánica de Protección de Datos Personales
    </div>
</footer>

</body>
</html>
