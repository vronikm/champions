<?php
/*
|--------------------------------------------------------------------------
| DigiCheck › Escanear — la pantalla de la cancha
|--------------------------------------------------------------------------
| Escaneo continuo: al leer el QR del carnet reconoce al alumno, guarda su
| asistencia EN EL DISPOSITIVO y la confirma con color, sonido y vibración.
|
| SIN INTERNET. Antes de ir a la cancha se descarga la lista (los alumnos
| de sus horarios con el código de su QR, ajax/listaAjax.php). Esta página
| queda guardada por el service worker (sw-check.js) y se abre sin señal.
| Cada marca se guarda en IndexedDB con la cola de la asistencia sin
| conexión de Fútbol (asistencia-offline.js) y se envía sola al volver la
| señal, por el mismo camino y con las mismas reglas (3 días de plazo,
| conflictos, permisos).
|
| Toda la lógica está en assets/js/digicheck.js; aquí, la pantalla y los
| datos que sólo sabe el servidor (usuario, URLs).
*/

$tituloVista = 'Escanear';
$vistaActual = 'escanear';

$usuarioId = (int)($_SESSION['usuarioid'] ?? 0);
$cfg = [
    'usuario'     => $usuarioId,
    'urlLista'    => APP_URL . 'ajax/listaAjax.php',
    'urlEnvio'    => DS_FUTBOL_URL . 'app/ajax/asistenciaOfflineAjax.php',
    'urlJsQR'     => ds_recurso('ds_core/assets/vendor/jsqr/jsQR.js'),
    'urlSW'       => APP_URL . 'sw-check.js',
    'alcanceSW'   => parse_url(APP_URL, PHP_URL_PATH),
    'urlLogin'    => DS_FUTBOL_URL . 'login/',
    'diasPlazo'   => 3,     /* asistenciaController::DIAS_SIN_CONEXION */
];

/* El <head> de la PWA: manifiesto, colores, icono y la cola de marcas. */
$cabeceraExtra = '
    <link rel="manifest" href="' . APP_URL . 'manifest-check.json">
    <meta name="theme-color" content="' . DS_CHECK_ACENTO . '">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="DigiCheck">
    <link rel="apple-touch-icon" href="' . APP_URL . 'assets/img/pwa/apple-touch-icon.png">
    <link rel="stylesheet" href="' . ds_recurso('ds_check/assets/css/digicheck.css') . '">
    <script src="' . ds_recurso('ds_futbol/app/views/dist/js/asistencia-offline.js') . '"></script>';

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="dc" id="dc" data-estado="inicio">

    <!-- ===== Estado: la lista, la conexión y lo que falta enviar ===== -->
    <section class="dc-estado" aria-label="Estado de DigiCheck">
        <div class="dc-estado__fila">
            <span class="dc-pill" id="dcRed" data-red="si"><i class="fas fa-wifi" aria-hidden="true"></i><span>En línea</span></span>
            <span class="dc-pill" id="dcPendientes" hidden><i class="fas fa-cloud-arrow-up" aria-hidden="true"></i><span>0 por enviar</span></span>
            <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="dcEnviar" hidden>
                <i class="fas fa-paper-plane me-1" aria-hidden="true"></i>Enviar
            </button>
        </div>
        <div class="dc-estado__lista">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span id="dcListaTexto">Preparando…</span>
            <button type="button" class="btn btn-sm btn-primary ms-auto" id="dcDescargar">
                <i class="fas fa-download me-1" aria-hidden="true"></i><span>Descargar lista</span>
            </button>
        </div>
        <p class="dc-aviso" id="dcAviso" role="status" hidden></p>
    </section>

    <!-- ===== Sin lista: qué hacer antes de ir a la cancha ===== -->
    <section class="dc-vacio" id="dcSinLista" hidden>
        <i class="fas fa-cloud-arrow-down" aria-hidden="true"></i>
        <h2>Descargue la lista antes de ir a la cancha</h2>
        <p>Con Internet, toque <strong>Descargar lista</strong>. Quedan guardados en este dispositivo los alumnos de sus
           horarios y podrá escanear sin señal. Las asistencias se envían solas cuando vuelva la conexión.</p>
    </section>

    <!-- ===== El escáner ===== -->
    <section class="dc-escaner" id="dcEscaner" hidden>
        <div class="dc-modo" role="radiogroup" aria-label="Qué se registra">
            <button type="button" class="dc-modo__op" role="radio" aria-checked="true" data-modo="P">
                <i class="fas fa-circle-check" aria-hidden="true"></i>Presente
            </button>
            <button type="button" class="dc-modo__op" role="radio" aria-checked="false" data-modo="A">
                <i class="fas fa-clock" aria-hidden="true"></i>Atraso
            </button>
        </div>

        <div class="dc-camara" id="dcCamara">
            <video id="dcVideo" playsinline muted autoplay aria-label="Vista de la cámara"></video>
            <div class="dc-visor" aria-hidden="true"><span></span></div>

            <div class="dc-camara__inicio" id="dcIniciar">
                <button type="button" class="btn btn-primary btn-lg" id="dcBotonIniciar">
                    <i class="fas fa-camera me-2" aria-hidden="true"></i>Iniciar escaneo
                </button>
                <p>Apunte al código QR del carnet. Se registra solo, uno tras otro.</p>
            </div>

            <div class="dc-camara__herr" id="dcHerr" hidden>
                <button type="button" class="dc-redondo" id="dcLinterna" hidden aria-label="Linterna" title="Linterna"><i class="fas fa-lightbulb" aria-hidden="true"></i></button>
                <button type="button" class="dc-redondo" id="dcCambiar" hidden aria-label="Cambiar de cámara" title="Cambiar de cámara"><i class="fas fa-camera-rotate" aria-hidden="true"></i></button>
                <button type="button" class="dc-redondo" id="dcPausar" aria-label="Pausar" title="Pausar"><i class="fas fa-pause" aria-hidden="true"></i></button>
            </div>

            <!-- La confirmación: color, icono y nombre en grande -->
            <div class="dc-resultado" id="dcResultado" hidden aria-live="assertive">
                <i class="fas fa-circle-check dc-resultado__icono" id="dcResIcono" aria-hidden="true"></i>
                <strong class="dc-resultado__titulo" id="dcResTitulo"></strong>
                <span class="dc-resultado__nombre" id="dcResNombre"></span>
                <span class="dc-resultado__detalle" id="dcResDetalle"></span>
            </div>
        </div>
        <p class="dc-camara__error" id="dcCamaraError" role="alert" hidden></p>

        <!-- Sin carnet a mano: buscarlo por nombre o cédula -->
        <details class="dc-manual">
            <summary><i class="fas fa-keyboard me-1" aria-hidden="true"></i>Registrar sin QR (buscar por nombre o cédula)</summary>
            <input type="search" class="form-control" id="dcBuscar" placeholder="Nombre, apellido o cédula" autocomplete="off" inputmode="search">
            <ul class="dc-manual__lista" id="dcBuscarLista"></ul>
        </details>
    </section>

    <!-- ===== Lo registrado hoy en este dispositivo ===== -->
    <section class="dc-hoy" id="dcHoy" hidden>
        <h2 class="dc-hoy__titulo">Registrados hoy <span class="badge text-bg-secondary" id="dcHoyTotal">0</span></h2>
        <ul class="dc-hoy__lista" id="dcHoyLista"></ul>
    </section>

    <!-- ===== Instalar como aplicación ===== -->
    <section class="dc-instalar" id="dcInstalar" hidden>
        <i class="fas fa-mobile-screen" aria-hidden="true"></i>
        <div>
            <strong>Instale DigiCheck en este dispositivo</strong>
            <span id="dcInstalarTexto">Se abre como una aplicación, a pantalla completa y sin conexión.</span>
        </div>
        <button type="button" class="btn btn-sm btn-primary" id="dcInstalarBoton">Instalar</button>
    </section>
</div>

<script>window.DIGICHECK = <?php echo json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?php echo ds_recurso('ds_check/assets/js/digicheck.js'); ?>"></script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
