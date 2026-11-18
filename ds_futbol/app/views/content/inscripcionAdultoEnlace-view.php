<?php
    use app\controllers\inscripcionController;
    $insInscripcion = new inscripcionController();
    /* Sólo las sedes marcadas en Core › Sedes como «Admite alumnos adultos» (migración 086). */
    $sedes = $insInscripcion->listarSedesAdultos();
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Enlace entrenamiento ejecutivo';
	$extras      = array (0 => 'swal',);
	$cabeceraExtra = <<<'CSS'
    <style>
        .enlace-resultado { display: none; margin-top: 20px; }
        .enlace-url {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            word-break: break-all;
            font-family: monospace;
            font-size: 0.85rem;
            color: #495057;
        }
        .btn-whatsapp { background: #25D366; border-color: #25D366; color: #212529; font-weight: 600; }
        .btn-whatsapp:hover { background: #1ebe5c; border-color: #1ebe5c; color: #212529; }
        .btn-copiar { background: #6c757d; border-color: #6c757d; color: #fff; }
        .enlace-info {
            background: #e8f5e9;
            color: #14532d;
            border-left: 4px solid #4caf50;
            padding: 12px 15px;
            border-radius: 0 8px 8px 0;
            margin-top: 15px;
        }
        .ds-adulto__pasos { margin: 0; padding-left: 1.2rem; }
        .ds-adulto__pasos li + li { margin-top: .25rem; }
    </style>
CSS;
	require_once "app/views/inc/cabecera.php";
?>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">
        <?php require_once "app/views/inc/navbar.php"; ?>
        <?php require_once "app/views/inc/main-sidebar.php"; ?>

        <div class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h4 class="m-0">Enlace entrenamiento ejecutivo</h4>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="<?php echo APP_URL . 'dashboard/'; ?>">Inicio</a></li>
                                <li class="breadcrumb-item">Inscripción Online</li>
                                <li class="breadcrumb-item active" aria-current="page">Alumnos adultos</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <section class="app-content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-md-8 offset-md-2">

                            <div class="card card-primary">
                                <div class="card-header">
                                    <h3 class="card-title">
                                        <i class="fas fa-user-tie"></i> Inscripción de mayores de edad, sin representante
                                    </h3>
                                </div>
                                <div class="card-body">
                                    <ul class="text-muted mb-4 ds-adulto__pasos">
                                        <li>El alumno llena <strong>sus propios datos</strong>: identificación, contacto y dirección.</li>
                                        <li>Queda como su propio titular: los <strong>recibos y facturas</strong> salen a su nombre y llegan a su correo.</li>
                                        <li>Sólo acepta personas de 18 años o más. Para un menor use <a href="<?php echo APP_URL; ?>inscripcionEnlace/">Generar enlaces</a>.</li>
                                    </ul>

                                    <?php if (!$sedes): ?>
                                        <div class="alert alert-warning d-flex align-items-start gap-2 mb-0" role="note">
                                            <i class="fas fa-triangle-exclamation mt-1" aria-hidden="true"></i>
                                            <div>
                                                Ninguna de sus sedes admite alumnos adultos. Se habilita en
                                                <strong>Core › Sedes</strong>, editando la sede y marcando
                                                «Admite alumnos adultos».
                                            </div>
                                        </div>
                                    <?php else: ?>
                                    <form id="formGenerarEnlaceAdulto" autocomplete="off" onsubmit="return false;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="sede_id">
                                                        <i class="fas fa-map-marker-alt text-primary"></i> Sede <span class="text-danger">*</span>
                                                    </label>
                                                    <select class="form-select" id="sede_id" name="sede_id" required>
                                                        <?php if (count($sedes) > 1): ?><option value="">Seleccione una sede</option><?php endif; ?>
                                                        <?php foreach ($sedes as $sede): ?>
                                                            <option value="<?php echo (int)$sede['sede_id']; ?>">
                                                                <?php echo htmlspecialchars($sede['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="horas_vigencia">
                                                        <i class="fas fa-clock text-primary"></i> Vigencia del enlace
                                                    </label>
                                                    <select class="form-select" id="horas_vigencia" name="horas_vigencia">
                                                        <option value="24">24 horas (1 día)</option>
                                                        <option value="48">48 horas (2 días)</option>
                                                        <option value="72" selected>72 horas (3 días)</option>
                                                        <option value="168">168 horas (7 días)</option>
                                                        <option value="336">336 horas (14 días)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="button" id="btnGenerar" class="btn btn-primary w-100">
                                            <i class="fas fa-magic"></i> Generar enlace entrenamiento ejecutivo
                                        </button>
                                    </form>

                                    <div id="enlaceResultado" class="enlace-resultado">
                                        <hr>
                                        <h5><i class="fas fa-check-circle text-success"></i> Enlace entrenamiento ejecutivo: <strong id="enlaceSede"></strong></h5>

                                        <div class="enlace-info">
                                            <small><i class="fas fa-info-circle"></i> Este enlace expira en <strong id="enlaceVigencia"></strong> horas</small>
                                        </div>

                                        <label class="mt-3"><i class="fas fa-link"></i> URL del formulario:</label>
                                        <div class="enlace-url" id="enlaceURL"></div>

                                        <div class="row mt-3">
                                            <div class="col-md-6 mb-2">
                                                <button type="button" id="btnCopiar" class="btn btn-copiar w-100">
                                                    <i class="fas fa-copy"></i> Copiar enlace
                                                </button>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <button type="button" id="btnWhatsApp" class="btn btn-whatsapp w-100">
                                                    <i class="fab fa-whatsapp"></i> Compartir por WhatsApp
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </section>
        </div>

        <?php require_once "app/views/inc/footer.php"; ?>
    </div>

    <script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
    <script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

    <?php
    /* Compartir: modal del núcleo con destino (contacto o grupo), mensaje
       editable e imagen opcional. */
    $waCompartir = ['titulo' => 'Compartir enlace de inscripción'];
    require DS_HUB_PATH . "ds_core/inc/whatsappCompartir.php";
    ?>

    <script>
    $(document).ready(function () {
        var textoBoton = '<i class="fas fa-magic"></i> Generar enlace entrenamiento ejecutivo';
        var ultimo = null;   /* el último enlace generado: { url, mensaje } */

        $('#btnWhatsApp').click(function () {
            if (!ultimo) { return; }
            window.dsWhatsappCompartir.abrir({ mensaje: ultimo.mensaje, enlace: ultimo.url });
        });

        $('#btnGenerar').click(function () {
            var sedeId = $('#sede_id').val();
            if (!sedeId) {
                Swal.fire('Campo requerido', 'Seleccione una sede para generar el enlace.', 'warning');
                return;
            }

            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generando...');

            $.ajax({
                url: '<?php echo APP_URL; ?>app/ajax/inscripcionAdultoAjax.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    modulo_inscripcion_adulto: 'generar_enlace',
                    sede_id: sedeId,
                    horas_vigencia: $('#horas_vigencia').val()
                },
                success: function (data) {
                    btn.prop('disabled', false).html(textoBoton);

                    if (data.tipo === 'enlace') {
                        $('#enlaceSede').text(data.sede_nombre);
                        $('#enlaceVigencia').text(data.vigencia_horas);
                        $('#enlaceURL').text(data.url_formulario);
                        ultimo = { url: data.url_formulario, mensaje: data.mensaje_whatsapp || data.url_formulario };
                        $('#enlaceResultado').slideDown();
                        Swal.fire({ title: data.titulo, text: data.texto, icon: data.icono });
                    } else {
                        Swal.fire(data.titulo, data.texto, data.icono);
                    }
                },
                error: function () {
                    btn.prop('disabled', false).html(textoBoton);
                    Swal.fire('Error', 'No se pudo generar el enlace. Intente nuevamente.', 'error');
                }
            });
        });

        $('#btnCopiar').click(function () {
            navigator.clipboard.writeText($('#enlaceURL').text()).then(function () {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success',
                            title: 'Enlace copiado al portapapeles', showConfirmButton: false, timer: 2000 });
            });
        });
    });
    </script>
</body>
</html>
