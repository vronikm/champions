<?php
/*
|--------------------------------------------------------------------------
| PWA de la toma de asistencia — lo que va en el <head>
|--------------------------------------------------------------------------
| Lo incluye cabecera.php cuando la vista define $pwaAsistencia = true
| (asistencia y asistenciaAlumno). El manifiesto y el service worker son
| archivos estáticos de ds_futbol/ (manifest-asistencia.json,
| sw-asistencia.js); aquí sólo se enlazan y se registra el worker con su
| alcance, que se limita a las rutas que empiezan por «asistencia».
|
| La librería de la cola (asistencia-offline.js) va también aquí, en el
| <head>, para que la vista la tenga lista al dibujar la lista.
*/
?>
    <link rel="manifest" href="<?php echo APP_URL; ?>manifest-asistencia.json">
    <meta name="theme-color" content="#a2cc38">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Asistencia">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="<?php echo APP_URL; ?>app/views/dist/img/pwa/apple-touch-icon.png">
    <script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/asistencia-offline.js'); ?>"></script>
    <script>
    (function () {
        if (window.DSAsistencia) {
            DSAsistencia.configurar({ url: <?php echo json_encode(APP_URL . 'app/ajax/asistenciaOfflineAjax.php'); ?> });
        }
        if ('serviceWorker' in navigator) {
            window.dsAsistenciaSW = navigator.serviceWorker.register(<?php echo json_encode(APP_URL . 'sw-asistencia.js'); ?>,
                { scope: <?php echo json_encode(parse_url(APP_URL, PHP_URL_PATH) . 'asistencia'); ?> })
                .catch(function () { return null; });   /* sin HTTPS o bloqueado: la toma sigue funcionando con conexión */
        }
    })();
    </script>
