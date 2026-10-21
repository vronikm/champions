document.getElementById('descargar').addEventListener('click', function () {
    // Abrir el PDF generado por PHP en una nueva pestaña
    // La URL sigue el mismo patrón de rutas del sistema: carnetFotoPDF/{id}/
    var alumnoid = window.location.pathname.split('/').filter(Boolean).pop();
    window.open(APP_URL + 'carnetFotoPDF/' + alumnoid + '/', '_blank');
});