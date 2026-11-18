<?php

/**
 * Formulario público de inscripción de ALUMNOS ADULTOS (migración 086).
 *
 * Un mayor de edad se inscribe a sí mismo, sin representante: queda como
 * su propio titular y sus recibos y facturas salen a su nombre.
 *
 * Es un punto de entrada aparte de index.php —el formulario de menores, que
 * no cambia—: token con su propia llave (TokenHelper::validarAdulto), claves
 * de sesión propias (adulto_*) y su propio endpoint (registroAdultoAjax.php).
 * Sólo para sedes con «Admite alumnos adultos» en Core › Sedes.
 */

    require_once __DIR__ . "/../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
    require_once "./config/app.php";
    require_once "./autoload.php";

    use app\models\TokenHelper;
    use app\controllers\registroAdultoController;

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    require_once "./app/views/inc/session_start.php";

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

    /*----------  Token del enlace de adultos  ----------*/
    $token   = (string)($_GET['t'] ?? '');
    $estado  = 'invalido';   // invalido | expirado | sede_inactiva | valido
    $payload = $token !== '' ? TokenHelper::validarAdulto($token) : null;

    if ($payload) {
        $estado = $payload['expirado'] ? 'expirado' : 'valido';
    }

    $insRegistro = new registroAdultoController();
    $sedeNombre  = null;

    if ($estado === 'valido') {
        $sedeNombre = $insRegistro->sedeAdmite($payload['sede_id']);
        if ($sedeNombre === null) {
            $estado = 'sede_inactiva';
        }
    }

    if ($estado !== 'valido') {
        unset($_SESSION['adulto_sedeid'], $_SESSION['adulto_exp'], $_SESSION['adulto_valida']);
        http_response_code($estado === 'invalido' ? 403 : 410);
        require_once "./app/views/content/enlaceInvalido-view.php";
        exit();
    }

    $_SESSION['adulto_sedeid'] = $payload['sede_id'];
    $_SESSION['adulto_exp']    = $payload['exp'];
    $_SESSION['adulto_valida'] = true;

    $expTimestamp    = $payload['exp'];
    $nacionalidades  = $insRegistro->listarNacionalidades();
    $consentimientos = $insRegistro->textosConsentimiento();
    $maxNacimiento   = (new DateTime('today'))->modify('-18 years')->format('Y-m-d');

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['csrf_token'];

    $h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

    $logoOrg = trim((string) ds_organizacion()['escuela_logo']);
    $logoUrl = ($logoOrg !== '' && is_file(DS_HUB_PATH . 'ds_core/assets/img/marca/' . basename($logoOrg)))
        ? DS_HUB_URL . 'ds_core/assets/img/marca/' . rawurlencode(basename($logoOrg))
        : DS_HUB_URL . 'ds_core/assets/img/icon_cz98px.png';

    $restan = max(0, $expTimestamp - time());
    $reloj  = ($restan >= 86400 ? intdiv($restan, 86400) . 'd ' : '')
            . sprintf('%02d:%02d:%02d', intdiv($restan % 86400, 3600), intdiv($restan % 3600, 60), $restan % 60);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $h(ESCUELA_NOMBRE); ?> | Inscripción de adultos</title>

    <link rel="icon" type="image/png" href="<?php echo DS_HUB_URL; ?>ds_core/assets/img/icon_cz98px.png">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css" rel="stylesheet">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/css/fuentes.css" rel="stylesheet">
    <link href="<?php echo DS_FUTBOL_URL; ?>app/views/dist/css/sweetalert2.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>assets/css/style.css?v=<?php
        echo @filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
</head>
<body>

<header class="cz-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?php echo $h($logoUrl); ?>" alt="" height="52" class="cz-logo">
                <div>
                    <h1><?php echo $h(ESCUELA_NOMBRE); ?></h1>
                    <span class="cz-sede">
                        <i class="fas fa-location-dot" aria-hidden="true"></i>
                        <?php echo $h($sedeNombre); ?> · Alumnos adultos
                    </span>
                </div>
            </div>
            <div class="cz-timer<?php echo $restan < 900 ? ' cz-timer--pronto' : ''; ?>"
                 id="cz_timer" role="status" aria-live="off">
                <i class="fas fa-clock" aria-hidden="true"></i>
                <?php echo $h($reloj); ?>
                <span class="visually-hidden"> restantes para completar el formulario</span>
            </div>
        </div>
    </div>
</header>

<main class="container py-4">

    <div id="formContainer">
        <form id="formAdulto"
              action="<?php echo APP_URL; ?>app/ajax/registroAdultoAjax.php"
              method="POST" enctype="multipart/form-data" autocomplete="off" novalidate>

            <input type="hidden" name="csrf_token" value="<?php echo $h($csrfToken); ?>">

            <ul class="nav cz-pasos" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-datos" data-bs-toggle="pill"
                            data-bs-target="#seccion-datos" type="button" role="tab">
                        <span class="cz-num" aria-hidden="true">1</span>
                        <span class="cz-paso-texto">Mis datos</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-autorizacion" data-bs-toggle="pill"
                            data-bs-target="#seccion-autorizacion" type="button" role="tab">
                        <span class="cz-num" aria-hidden="true">2</span>
                        <span class="cz-paso-texto">Autorización</span>
                    </button>
                </li>
            </ul>

            <p class="cz-leyenda">
                Inscripción para personas <strong>de 18 años o más</strong>. Los campos marcados con
                <span class="cz-req">*</span> son obligatorios.
            </p>

            <div class="tab-content">

                <!-- ═══ MIS DATOS ═══ -->
                <div class="tab-pane fade show active" id="seccion-datos" role="tabpanel">
                    <div class="cz-card card mb-4">
                        <div class="card-header">
                            <i class="fas fa-address-card"></i> Mis datos
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-12 text-center mb-2">
                                    <label class="form-label d-block" for="foto">Mi foto</label>
                                    <div class="foto-preview-container mb-2">
                                        <i class="fas fa-camera placeholder-icon" id="foto_placeholder"></i>
                                        <img id="foto_preview" src="" alt="Vista previa" style="display:none;">
                                    </div>
                                    <input type="file" class="form-control mx-auto" id="foto" name="foto"
                                           accept="image/jpeg,image/png" style="max-width: 300px;">
                                    <small class="text-muted">Opcional. JPG o PNG, máximo 4 MB</small>
                                </div>

                                <div class="col-md-4">
                                    <label for="tipoidentificacion" class="form-label">
                                        Tipo de identificación <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <select class="form-select" id="tipoidentificacion" name="tipoidentificacion" required>
                                        <option value="CED" selected>Cédula</option>
                                        <option value="PAS">Pasaporte</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="identificacion" class="form-label">
                                        Identificación <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="identificacion" name="identificacion"
                                           maxlength="13" placeholder="10 dígitos" required>
                                    <div class="invalid-feedback">Ingrese una identificación válida</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="fechanacimiento" class="form-label">
                                        Fecha de nacimiento <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="date" class="form-control" id="fechanacimiento" name="fechanacimiento"
                                               max="<?php echo $maxNacimiento; ?>" required>
                                        <span class="input-group-text" id="edad_texto" aria-live="polite">--</span>
                                        <div class="invalid-feedback">Seleccione la fecha de nacimiento</div>
                                    </div>
                                </div>

                                <div class="col-md-3">
                                    <label for="primernombre" class="form-label">
                                        Primer nombre <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="primernombre" name="primernombre"
                                           maxlength="50" autocomplete="given-name" required>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>

                                <div class="col-md-3">
                                    <label for="segundonombre" class="form-label">Segundo nombre</label>
                                    <input type="text" class="form-control" id="segundonombre" name="segundonombre" maxlength="50">
                                </div>

                                <div class="col-md-3">
                                    <label for="apellidopaterno" class="form-label">
                                        Apellido paterno <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="apellidopaterno" name="apellidopaterno"
                                           maxlength="50" autocomplete="family-name" required>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>

                                <div class="col-md-3">
                                    <label for="apellidomaterno" class="form-label">Apellido materno</label>
                                    <input type="text" class="form-control" id="apellidomaterno" name="apellidomaterno" maxlength="50">
                                </div>

                                <div class="col-md-6">
                                    <span class="form-label d-block">Sexo <span class="cz-req" title="Campo obligatorio">*</span></span>
                                    <div class="d-flex gap-4 mt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="genero" id="genero_m" value="M">
                                            <label class="form-check-label" for="genero_m">Masculino</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="genero" id="genero_f" value="F">
                                            <label class="form-check-label" for="genero_f">Femenino</label>
                                        </div>
                                    </div>
                                    <small class="text-danger" id="aviso_genero" style="display:none;">Seleccione una opción</small>
                                </div>

                                <div class="col-md-6">
                                    <label for="nacionalidadid" class="form-label">Nacionalidad</label>
                                    <select class="form-select" id="nacionalidadid" name="nacionalidadid">
                                        <?php foreach ($nacionalidades as $n): ?>
                                            <option value="<?php echo $h($n['catalogo_valor']); ?>"
                                                <?php echo $n['catalogo_valor'] === 'ECU' ? 'selected' : ''; ?>>
                                                <?php echo $h($n['catalogo_descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="correo" class="form-label">
                                        Correo electrónico <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="correo" name="correo"
                                               maxlength="50" placeholder="correo@ejemplo.com" autocomplete="email" required>
                                        <div class="invalid-feedback">Ingrese un correo válido</div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="celular" class="form-label">
                                        Celular <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="celular" name="celular"
                                               maxlength="10" inputmode="numeric" placeholder="09XXXXXXXX" autocomplete="tel" required>
                                        <div class="invalid-feedback">Debe iniciar con 09 y tener 10 dígitos</div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label for="direccion" class="form-label">
                                        Dirección <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="direccion" name="direccion"
                                           maxlength="400" placeholder="Barrio, calle principal, #casa, calle secundaria"
                                           autocomplete="street-address" required>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>

                                <div class="col-12">
                                    <div class="alert alert-info d-flex align-items-start gap-2 mb-0" role="note">
                                        <i class="fas fa-file-invoice-dollar mt-1" aria-hidden="true"></i>
                                        <div style="font-size: 0.9rem;">
                                            Tus <strong>recibos y facturas</strong> se emitirán a tu nombre, con esta
                                            identificación y esta dirección, y se enviarán a este correo.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-cz" id="btn_to_autorizacion">
                                    Siguiente: Autorización <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ AUTORIZACIÓN ═══ -->
                <div class="tab-pane fade" id="seccion-autorizacion" role="tabpanel">
                    <div class="cz-card card mb-4">
                        <div class="card-header">
                            <i class="fas fa-shield-halved"></i> Autorización y Consentimiento
                        </div>
                        <div class="card-body">
                            <?php $iconos = ['DATOS' => 'fa-file-shield', 'IMAGEN' => 'fa-camera']; ?>
                            <?php foreach ($consentimientos as $tipo => $bloque): ?>
                                <div class="card border-start border-4 mb-4" style="border-left-color: var(--cz-azul) !important;">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3" style="color: var(--cz-azul);">
                                            <i class="fas <?php echo $iconos[$tipo] ?? 'fa-file-lines'; ?> me-1"
                                               style="color: var(--cz-acento-texto);" aria-hidden="true"></i>
                                            <?php echo $h($bloque['titulo']); ?>
                                        </h6>
                                        <div class="bg-light p-3 rounded mb-3" style="max-height: 200px; overflow-y: auto; font-size: 0.875rem; line-height: 1.6;">
                                            <?php echo $bloque['texto']; ?>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input consentimiento-obligatorio" type="checkbox"
                                                   id="acepta_<?php echo strtolower($tipo); ?>"
                                                   name="acepta_<?php echo strtolower($tipo); ?>" value="S" required>
                                            <label class="form-check-label fw-semibold" for="acepta_<?php echo strtolower($tipo); ?>">
                                                <?php echo $h($bloque['casilla']); ?>
                                                <span class="cz-req" title="Campo obligatorio">*</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                                <i class="fas fa-circle-info mt-1"></i>
                                <div style="font-size: 0.875rem;">
                                    Al marcar las casillas y enviar este formulario, declaro que soy mayor de edad y que la
                                    información proporcionada es verídica. Entiendo que puedo ejercer mis derechos conforme
                                    a la LOPDP comunicándome con la escuela.
                                </div>
                            </div>

                            <div class="alert alert-warning d-flex align-items-start gap-2 py-2" id="aviso_consentimientos" role="alert">
                                <i class="fas fa-triangle-exclamation mt-1"></i>
                                <div style="font-size: 0.875rem;">
                                    Debe aceptar <strong>las dos autorizaciones</strong> para completar la inscripción.
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" id="btn_to_datos">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </button>
                                <div>
                                    <button type="submit" class="btn btn-cz btn-cz-enviar" id="btn_submit" disabled>
                                        <i class="fas fa-circle-check"></i> Registrar mi inscripción
                                    </button>
                                    <div class="cz-cargando" id="btn_loading">
                                        <div class="spinner-border spinner-border-sm" role="status" style="color: var(--cz-azul);"></div>
                                        <span class="fw-semibold" style="color: var(--cz-azul);">Registrando...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <div id="successContainer" style="display:none;">
        <div class="cz-card card">
            <div class="card-body cz-exito">
                <i class="fas fa-circle-check"></i>
                <h2>Inscripción registrada</h2>
                <p class="text-muted mb-4">
                    Quedaste inscrito en la sede <strong><?php echo $h($sedeNombre); ?></strong>.
                </p>
                <p class="text-muted">La academia se pondrá en contacto contigo. Puedes cerrar esta ventana.</p>
            </div>
        </div>
    </div>

</main>

<footer class="cz-footer">
    <div class="container">
        &copy; <?php echo date('Y'); ?> <?php echo $h(ESCUELA_NOMBRE); ?> &mdash; Todos los derechos reservados
    </div>
</footer>

<script>var TOKEN_EXPIRY_TIMESTAMP = <?php echo (int)$expTimestamp; ?>;</script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo DS_FUTBOL_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
<script src="<?php echo APP_URL; ?>assets/js/adulto.js?v=<?php
    echo @filemtime(__DIR__ . '/assets/js/adulto.js') ?: time(); ?>"></script>

</body>
</html>
