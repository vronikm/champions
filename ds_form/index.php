<?php

/**
 * Formulario público de inscripción.
 *
 * Punto de entrada único. Valida el token HMAC del enlace compartido por
 * WhatsApp, extrae la sede y solo entonces renderiza el formulario.
 * Sin un token válido y vigente no se muestra ningún dato ni formulario.
 */

    require_once "./config/app.php";
    require_once "./autoload.php";

    use app\models\TokenHelper;
    use app\controllers\registroController;

    /*----------  Sesión del formulario público  ----------*/
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    require_once "./app/views/inc/session_start.php";

    /*----------  Cabeceras de seguridad  ----------*/
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: same-origin");
    /*
    | La politica ya no admite jsdelivr: no queda nada que traer de fuera.
    |
    | Ojo con font-src. Antes decia SOLO «https://cdn.jsdelivr.net», sin
    | 'self'. Al pasar los recursos a servidor propio, esa linea habria
    | BLOQUEADO las tipografias locales —Source Sans Pro y los iconos de
    | FontAwesome— y la pagina habria salido con la fuente del sistema y
    | cuadros vacios donde van los iconos, sin un solo error visible salvo
    | en la consola. Es el fallo clasico al dejar de usar un CDN.
    */
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "font-src 'self' data:; "
         . "img-src 'self' data:; "
         . "connect-src 'self'; "
         . "object-src 'none'; base-uri 'self'; "
         . "form-action 'self'; frame-ancestors 'none'");

    /*----------  Validación del token del enlace  ----------*/
    $token  = $_GET['t'] ?? '';
    $estado = 'invalido';   // invalido | expirado | valido

    if ($token !== '') {
        $payload = TokenHelper::validar($token);

        if ($payload) {
            $estado = 'valido';

            // La sede queda fijada por el token, no por lo que envíe el navegador
            $_SESSION['inscripcion_sedeid'] = intval($payload['sede_id']);
            $_SESSION['inscripcion_exp']    = intval($payload['exp']);
            $_SESSION['inscripcion_valida'] = true;
        } elseif (TokenHelper::estaExpirado($token)) {
            $estado = 'expirado';
        }
    }

    if ($estado !== 'valido') {
        // Cortar cualquier sesión previa: un enlace vencido no debe heredar permisos
        unset(
            $_SESSION['inscripcion_sedeid'],
            $_SESSION['inscripcion_exp'],
            $_SESSION['inscripcion_valida']
        );

        http_response_code($estado === 'expirado' ? 410 : 403);
        require_once "./app/views/content/enlaceInvalido-view.php";
        exit();
    }

    /*----------  Token válido  ----------*/
    $sedeId       = intval($_SESSION['inscripcion_sedeid']);
    $expTimestamp = intval($_SESSION['inscripcion_exp']);

    $insRegistro   = new registroController();
    $sedeNombre    = $insRegistro->nombreSede($sedeId);
    $tiposDocumento = $insRegistro->listarCatalogo(1);   // tipo_documento
    $nacionalidades = $insRegistro->listarCatalogo(2);   // nacionalidad
    $parentescos    = $insRegistro->listarCatalogo(4);   // parentesco

    /* Los textos salen del mismo archivo cuyo hash se guarda al aceptar,
       para que lo firmado sea exactamente lo que se mostró en pantalla. */
    $consentimientos = $insRegistro->textosConsentimiento();

    /*----------  CSRF  ----------*/
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    $csrfToken = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars(ESCUELA_NOMBRE); ?> | Inscripción</title>

    <link rel="icon" type="image/png" href="<?php echo DS_HUB_URL; ?>ds_core/assets/img/icon_cz98px.png">

    <?php
    /*
    | TODO SE SIRVE DESDE AQUÍ, NADA DESDE UN CDN
    |
    | Esta página pedía cinco archivos a cdn.jsdelivr.net. Dos problemas, y
    | ninguno teórico:
    |
    |   1. Si el CDN no responde —conexión mala, red que lo bloquea, el
    |      servicio caído— el formulario sale SIN ESTILOS y sin los avisos
    |      de SweetAlert. Es la primera pantalla que ve una familia de la
    |      escuela, y llega por WhatsApp a un teléfono con la red que sea.
    |
    |   2. Cada visita envía la IP del representante a un tercero antes de
    |      que haya aceptado nada. El ecosistema ya tomó esta decisión con
    |      las tipografías —ver ds_core/assets/css/fuentes.css— y por el
    |      mismo motivo.
    |
    | Bootstrap CSS y JS son la MISMA versión (5.3.8). Mezclar versiones
    | entre la hoja y el script produce fallos sutiles en los componentes.
    */
    ?>
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css" rel="stylesheet">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/css/fuentes.css" rel="stylesheet">
    <?php /* SweetAlert2 vive en Basketball, que es donde el ecosistema
             guarda este complemento; DS_VENDOR_URL no está definida en el
             arranque de ds_form, así que se ancla a DS_FUTBOL_URL igual
             que hace media_url() con los medios protegidos. */ ?>
    <link href="<?php echo DS_FUTBOL_URL; ?>app/views/dist/css/sweetalert2.min.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>assets/css/style.css?v=<?php
        echo @filemtime(__DIR__ . '/assets/css/style.css') ?: time(); ?>" rel="stylesheet">
</head>
<body>

<!-- ═══════════════ CABECERA ═══════════════ -->
<?php
/*
| El logo sale de la organización configurada en Core, no de un archivo
| fijo del módulo. Es el mismo criterio que el nombre: quien cambia el
| escudo en Core lo cambia aquí, y no hay que acordarse de subirlo dos
| veces. Si no hay ninguno configurado, se usa el del sistema.
*/
$logoOrg = trim((string) ds_organizacion()['escuela_logo']);
$logoUrl = ($logoOrg !== '' && is_file(DS_HUB_PATH . 'ds_core/assets/img/marca/' . basename($logoOrg)))
    ? DS_HUB_URL . 'ds_core/assets/img/marca/' . rawurlencode(basename($logoOrg))
    : DS_HUB_URL . 'ds_core/assets/img/icon_cz98px.png';
?>
<header class="cz-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="" height="52" class="cz-logo">
                <div>
                    <h1><?php echo htmlspecialchars(ESCUELA_NOMBRE); ?></h1>
                    <span class="cz-sede">
                        <i class="fas fa-location-dot" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($sedeNombre); ?>
                    </span>
                </div>
            </div>
            <?php
            /*
            | La cuenta atrás lleva role="status" y aria-live="off".
            |
            | «off» y no «polite» a propósito: el contenido cambia cada
            | segundo, y un lector de pantalla que lo anunciara haría
            | imposible rellenar el formulario. form.js lo pasa a «assertive»
            | sólo cuando quedan menos de cinco minutos, que es cuando de
            | verdad hay que enterarse.
            */
            ?>
            <?php
            /*
            | El valor inicial lo pinta PHP, no el JavaScript.
            |
            | Antes decía «Cargando...» y esperaba a que form.js lo
            | sustituyera. Cuando ese archivo no llegaba a ejecutarse —una
            | caché sirviendo la versión anterior, por ejemplo— el reloj se
            | quedaba en «Cargando...» para siempre, y quien lo miraba no
            | tenía forma de saber cuánto le quedaba.
            |
            | El servidor ya sabe la respuesta: la trae el propio token. Se
            | pinta aquí y form.js sólo se encarga de que siga corriendo. Si
            | el JavaScript falla, se ve un tiempo CIERTO aunque congelado,
            | que es infinitamente mejor que una palabra que no dice nada.
            */
            $restan = max(0, $expTimestamp - time());
            $reloj  = $restan > 0
                ? ($restan >= 86400 ? intdiv($restan, 86400) . 'd ' : '')
                  . sprintf('%02d:%02d:%02d', intdiv($restan % 86400, 3600),
                            intdiv($restan % 3600, 60), $restan % 60)
                : 'Enlace expirado';
            ?>
            <div class="cz-timer<?php echo $restan > 0 && $restan < 900 ? ' cz-timer--pronto' : ''; ?>"
                 id="cz_timer" role="status" aria-live="off">
                <i class="fas fa-clock" aria-hidden="true"></i>
                <?php echo htmlspecialchars($reloj, ENT_QUOTES, 'UTF-8'); ?>
                <span class="visually-hidden"> restantes para completar el formulario</span>
            </div>
        </div>
    </div>
</header>

<main class="container py-4">

    <div id="formContainer">
        <form id="formRegistro"
              action="<?php echo APP_URL; ?>app/ajax/registroAjax.php"
              method="POST"
              enctype="multipart/form-data"
              autocomplete="off"
              novalidate>

            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

            <!-- ─── PASOS ───
            | El marcado sigue siendo nav-pills con botones, porque form.js
            | los mueve con bootstrap.Tab y no se toca ni un id. Lo que
            | cambia es que ahora se ven como un progreso numerado: tres
            | pastillas iguales no decían cuántos pasos había ni cuál era
            | el que faltaba.
            -->
            <ul class="nav cz-pasos" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-repre" data-bs-toggle="pill"
                            data-bs-target="#seccion-repre" type="button" role="tab">
                        <span class="cz-num" aria-hidden="true">1</span>
                        <span class="cz-paso-texto">Representante</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-alumno" data-bs-toggle="pill"
                            data-bs-target="#seccion-alumno" type="button" role="tab">
                        <span class="cz-num" aria-hidden="true">2</span>
                        <span class="cz-paso-texto">Alumno</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-autorizacion" data-bs-toggle="pill"
                            data-bs-target="#seccion-autorizacion" type="button" role="tab">
                        <span class="cz-num" aria-hidden="true">3</span>
                        <span class="cz-paso-texto">Autorización</span>
                    </button>
                </li>
            </ul>

            <p class="cz-leyenda">
                Los campos marcados con <span class="cz-req">*</span> son obligatorios.
                Sus datos se usan sólo para la inscripción del alumno.
            </p>

            <div class="tab-content" id="formTabsContent">

                <!-- ═══ SECCIÓN REPRESENTANTE ═══ -->
                <div class="tab-pane fade show active" id="seccion-repre" role="tabpanel">
                    <div class="cz-card card mb-4">
                        <div class="card-header">
                            <i class="fas fa-id-badge"></i> Datos del Representante
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-4">
                                    <label for="repre_tipoidentificacion" class="form-label">
                                        Tipo de identificación <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <select class="form-select" id="repre_tipoidentificacion" name="repre_tipoidentificacion" required>
                                        <?php foreach ($tiposDocumento as $t): ?>
                                            <option value="<?php echo htmlspecialchars($t['catalogo_valor']); ?>"
                                                <?php echo $t['catalogo_valor'] === 'CED' ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($t['catalogo_descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="repre_identificacion" class="form-label">
                                        Identificación <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="repre_identificacion" name="repre_identificacion"
                                           maxlength="13" placeholder="Ej: 1104XXXXXX" required>
                                    <div class="invalid-feedback">Ingrese una identificación válida</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="repre_sexo" class="form-label">Sexo</label>
                                    <select class="form-select" id="repre_sexo" name="repre_sexo">
                                        <option value="">Seleccione</option>
                                        <option value="M">Masculino</option>
                                        <option value="F">Femenino</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="repre_primernombre" class="form-label">
                                        Primer nombre <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="repre_primernombre" name="repre_primernombre"
                                           maxlength="50" placeholder="Primer nombre" required>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>

                                <div class="col-md-3">
                                    <label for="repre_segundonombre" class="form-label">Segundo nombre</label>
                                    <input type="text" class="form-control" id="repre_segundonombre" name="repre_segundonombre"
                                           maxlength="50" placeholder="Segundo nombre">
                                </div>

                                <div class="col-md-3">
                                    <label for="repre_apellidopaterno" class="form-label">
                                        Apellido paterno <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="repre_apellidopaterno" name="repre_apellidopaterno"
                                           maxlength="50" placeholder="Apellido paterno" required>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>

                                <div class="col-md-3">
                                    <label for="repre_apellidomaterno" class="form-label">Apellido materno</label>
                                    <input type="text" class="form-control" id="repre_apellidomaterno" name="repre_apellidomaterno"
                                           maxlength="50" placeholder="Apellido materno">
                                </div>

                                <div class="col-12">
                                    <label for="repre_direccion" class="form-label">
                                        Dirección <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="repre_direccion" name="repre_direccion"
                                           maxlength="400" placeholder="Barrio, calle principal, #casa, calle secundaria" required>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="repre_correo" class="form-label">
                                        Correo electrónico <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="repre_correo" name="repre_correo"
                                               maxlength="50" placeholder="correo@ejemplo.com" required>
                                        <div class="invalid-feedback">Ingrese un correo válido</div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label for="repre_celular" class="form-label">
                                        Celular <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="text" class="form-control" id="repre_celular" name="repre_celular"
                                               maxlength="10" placeholder="09XXXXXXXX" required>
                                        <div class="invalid-feedback">Debe iniciar con 09 y tener 10 dígitos</div>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label for="repre_parentesco" class="form-label">
                                        Parentesco <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <select class="form-select" id="repre_parentesco" name="repre_parentesco" required>
                                        <option value="">Seleccione</option>
                                        <?php foreach ($parentescos as $p): ?>
                                            <option value="<?php echo htmlspecialchars($p['catalogo_valor']); ?>">
                                                <?php echo htmlspecialchars($p['catalogo_descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Seleccione el parentesco</div>
                                </div>

                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="button" class="btn btn-cz" id="btn_to_alumno">
                                    Siguiente: Datos del Alumno <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECCIÓN ALUMNO ═══ -->
                <div class="tab-pane fade" id="seccion-alumno" role="tabpanel">
                    <div class="cz-card card mb-4">
                        <div class="card-header">
                            <i class="fas fa-address-card"></i> Datos del Alumno
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-12 text-center mb-3">
                                    <label class="form-label d-block">Foto del alumno</label>
                                    <div class="foto-preview-container mb-2" id="foto_container">
                                        <i class="fas fa-camera placeholder-icon" id="foto_placeholder"></i>
                                        <img id="foto_preview" src="" alt="Vista previa" style="display:none;">
                                    </div>
                                    <input type="file" class="form-control mx-auto" id="alumno_foto" name="alumno_foto"
                                           accept="image/jpeg,image/png" style="max-width: 300px;">
                                    <small class="text-muted">JPG o PNG, máximo 4 MB</small>
                                </div>

                                <div class="col-md-4">
                                    <label for="alumno_tipoidentificacion" class="form-label">
                                        Tipo de identificación <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <select class="form-select" id="alumno_tipoidentificacion" name="alumno_tipoidentificacion" required>
                                        <?php foreach ($tiposDocumento as $t): ?>
                                            <option value="<?php echo htmlspecialchars($t['catalogo_valor']); ?>"
                                                <?php echo $t['catalogo_valor'] === 'CED' ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($t['catalogo_descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="alumno_identificacion" class="form-label">
                                        Identificación <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="alumno_identificacion" name="alumno_identificacion"
                                           maxlength="20" placeholder="Ej: 1104XXXXXX" required>
                                    <div class="invalid-feedback">Ingrese una identificación válida</div>
                                </div>

                                <div class="col-md-4">
                                    <label for="alumno_fechanacimiento" class="form-label">
                                        Fecha de nacimiento <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="alumno_fechanacimiento" name="alumno_fechanacimiento"
                                           max="<?php echo date('Y-m-d'); ?>" required>
                                    <div class="invalid-feedback">Seleccione la fecha de nacimiento</div>
                                </div>

                                <div class="col-md-3">
                                    <label for="alumno_primernombre" class="form-label">
                                        Primer nombre <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="alumno_primernombre" name="alumno_primernombre"
                                           maxlength="150" placeholder="Primer nombre" required>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>

                                <div class="col-md-3">
                                    <label for="alumno_segundonombre" class="form-label">Segundo nombre</label>
                                    <input type="text" class="form-control" id="alumno_segundonombre" name="alumno_segundonombre"
                                           maxlength="150" placeholder="Segundo nombre">
                                </div>

                                <div class="col-md-3">
                                    <label for="alumno_apellidopaterno" class="form-label">
                                        Apellido paterno <span class="cz-req" title="Campo obligatorio">*</span>
                                    </label>
                                    <input type="text" class="form-control" id="alumno_apellidopaterno" name="alumno_apellidopaterno"
                                           maxlength="150" placeholder="Apellido paterno" required>
                                    <div class="invalid-feedback">Este campo es obligatorio</div>
                                </div>

                                <div class="col-md-3">
                                    <label for="alumno_apellidomaterno" class="form-label">Apellido materno</label>
                                    <input type="text" class="form-control" id="alumno_apellidomaterno" name="alumno_apellidomaterno"
                                           maxlength="150" placeholder="Apellido materno">
                                </div>

                                <div class="col-md-4">
                                    <label for="alumno_nacionalidadid" class="form-label">Nacionalidad</label>
                                    <select class="form-select" id="alumno_nacionalidadid" name="alumno_nacionalidadid">
                                        <?php foreach ($nacionalidades as $n): ?>
                                            <option value="<?php echo htmlspecialchars($n['catalogo_valor']); ?>"
                                                <?php echo $n['catalogo_valor'] === 'ECU' ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($n['catalogo_descripcion']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-8">
                                    <label for="alumno_direccion" class="form-label">Dirección</label>
                                    <input type="text" class="form-control" id="alumno_direccion" name="alumno_direccion"
                                           maxlength="400" placeholder="Dirección del alumno (si es distinta a la del representante)">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Género <span class="cz-req" title="Campo obligatorio">*</span></label>
                                    <div class="d-flex gap-4 mt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="alumno_genero" id="genero_m" value="M">
                                            <label class="form-check-label" for="genero_m">Masculino</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="alumno_genero" id="genero_f" value="F">
                                            <label class="form-check-label" for="genero_f">Femenino</label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">¿Tiene hermanos en la escuela?</label>
                                    <div class="d-flex gap-4 mt-1">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="alumno_hermanos" id="hermanos_s" value="S">
                                            <label class="form-check-label" for="hermanos_s">Sí</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="alumno_hermanos" id="hermanos_n" value="N" checked>
                                            <label class="form-check-label" for="hermanos_n">No</label>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" id="btn_to_repre">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </button>
                                <button type="button" class="btn btn-cz" id="btn_to_autorizacion">
                                    Siguiente: Autorización <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ SECCIÓN AUTORIZACIÓN LOPDP ═══ -->
                <div class="tab-pane fade" id="seccion-autorizacion" role="tabpanel">
                    <div class="cz-card card mb-4">
                        <div class="card-header">
                            <i class="fas fa-shield-halved"></i> Autorización y Consentimiento
                        </div>
                        <div class="card-body">

                            <?php
                                /* Ambos consentimientos son obligatorios: sin los dos
                                   no hay inscripción. El texto sale de config/consentimientos.php,
                                   que es el mismo cuyo hash se guarda al aceptar. */
                                /*
                                | Los dos consentimientos se distinguen por el ICONO, no
                                | por un borde rojo y otro verde.
                                |
                                | Rojo y verde aquí decían algo que no es cierto: los dos
                                | son obligatorios y ninguno es un error ni una
                                | confirmación. Además chocaban con la paleta del escudo.
                                | Ahora los dos llevan el mismo filete azul y se
                                | diferencian por lo que son.
                                */
                                $estilos = [
                                    'DATOS'  => ['icono' => 'fa-file-shield'],
                                    'IMAGEN' => ['icono' => 'fa-camera'],
                                ];
                            ?>

                            <?php foreach ($consentimientos as $tipo => $bloque): ?>
                                <?php $e = $estilos[$tipo] ?? ['icono' => 'fa-file-lines']; ?>
                                <div class="card border-start border-4 mb-4"
                                     style="border-left-color: var(--cz-azul) !important;">
                                    <div class="card-body">
                                        <h6 class="fw-bold mb-3" style="color: var(--cz-azul);">
                                            <i class="fas <?php echo $e['icono']; ?> me-1"
                                               style="color: var(--cz-naranja);" aria-hidden="true"></i>
                                            <?php echo htmlspecialchars($bloque['titulo']); ?>
                                        </h6>

                                        <div class="bg-light p-3 rounded mb-3" style="max-height: 200px; overflow-y: auto; font-size: 0.875rem; line-height: 1.6;">
                                            <?php echo $bloque['texto']; ?>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input consentimiento-obligatorio" type="checkbox"
                                                   id="acepta_<?php echo strtolower($tipo); ?>"
                                                   name="acepta_<?php echo strtolower($tipo); ?>" value="S" required>
                                            <label class="form-check-label fw-semibold" for="acepta_<?php echo strtolower($tipo); ?>">
                                                <?php echo htmlspecialchars($bloque['casilla']); ?>
                                                <span class="cz-req" title="Campo obligatorio">*</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="alert alert-info d-flex align-items-start gap-2" role="alert">
                                <i class="fas fa-circle-info mt-1"></i>
                                <div style="font-size: 0.875rem;">
                                    Al marcar las casillas anteriores y enviar este formulario, declaro que soy el representante
                                    legal del menor y que la información proporcionada es verídica. Entiendo que puedo ejercer
                                    mis derechos conforme a la LOPDP comunicándome con la escuela.
                                </div>
                            </div>

                            <div class="alert alert-warning d-flex align-items-start gap-2 py-2" id="aviso_consentimientos" role="alert">
                                <i class="fas fa-triangle-exclamation mt-1"></i>
                                <div style="font-size: 0.875rem;">
                                    Debe aceptar <strong>las dos autorizaciones</strong> para poder completar la inscripción.
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <button type="button" class="btn btn-outline-secondary" id="btn_to_alumno_back">
                                    <i class="fas fa-arrow-left"></i> Volver
                                </button>

                                <div>
                                    <button type="submit" class="btn btn-cz btn-cz-enviar" id="btn_submit" disabled>
                                        <i class="fas fa-circle-check"></i> Registrar Alumno
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

    <!-- Pantalla de éxito -->
    <div id="successContainer" style="display:none;">
        <div class="cz-card card">
            <div class="card-body cz-exito">
                <i class="fas fa-circle-check"></i>
                <h2>Registro completado</h2>
                <p class="text-muted mb-4">
                    El alumno ha sido registrado exitosamente en la sede
                    <strong><?php echo htmlspecialchars($sedeNombre); ?></strong>.
                </p>
                <p class="text-muted">La escuela se pondrá en contacto con usted. Puede cerrar esta ventana.</p>
            </div>
        </div>
    </div>

</main>

<footer class="cz-footer">
    <div class="container">
        &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars(ESCUELA_NOMBRE); ?> &mdash; Todos los derechos reservados
    </div>
</footer>

<script>var TOKEN_EXPIRY_TIMESTAMP = <?php echo intval($expTimestamp); ?>;</script>

<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo DS_FUTBOL_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
<?php
/*
| El ?v= NO es opcional aquí.
|
| Sin él, el navegador del representante sigue ejecutando el form.js que se
| descargó la vez anterior. Y eso ya rompió la pantalla: al renombrar el
| identificador del reloj de «afpl_timer» a «cz_timer», los navegadores que
| tenían el archivo viejo en caché buscaban un elemento que ya no existe, el
| temporizador no arrancaba nunca y se quedaba en «Cargando...» para siempre.
|
| El síntoma engaña: en un navegador limpio —el de las pruebas, sin caché—
| funcionaba perfectamente. Sólo fallaba en el de quien ya había abierto la
| página antes, que es justamente todo el mundo menos quien lo prueba.
|
| La marca es la fecha del archivo: cambia sola al editarlo y no hay que
| acordarse de subir ningún número.
*/
?>
<script src="<?php echo APP_URL; ?>assets/js/form.js?v=<?php
    echo @filemtime(__DIR__ . '/assets/js/form.js') ?: time(); ?>"></script>

</body>
</html>
