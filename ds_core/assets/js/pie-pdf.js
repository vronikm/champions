/*
| Pie de los PDF (Core › Organización) para los PDF que se arman en el
| navegador con jsPDF: estadísticas, ingresos por lugar de entrenamiento e
| ingresos de mora por mes. Los de FPDF lo reciben solos (ds_pdf_pie, en
| ds_core/inc/organizacion.php).
|
| El texto viaja en el propio <script>:
|     <script src=".../pie-pdf.js" data-pie="<?php echo ...ds_pie_pdf_texto()... ?>"></script>
| y se aplica con dsPiePdf(pdf) justo antes de pdf.save().
|
| Mismo aspecto que en FPDF: una línea centrada, pequeña y gris, al final de
| cada página. Si no cabe se achica hasta 5 pt y luego se recorta. Va sobre
| una franja blanca: la captura de la tabla puede llegar hasta el borde.
*/
(function () {
    var s = document.currentScript;
    var texto = ((s && s.dataset.pie) || '').replace(/\s+/g, ' ').trim();

    window.dsPiePdf = function (pdf) {
        if (!texto || !pdf) { return; }
        var w = pdf.internal.pageSize.getWidth();
        var h = pdf.internal.pageSize.getHeight();
        var ancho = w - 16;
        var paginas = pdf.getNumberOfPages();

        for (var i = 1; i <= paginas; i++) {
            pdf.setPage(i);
            pdf.setFont('helvetica', 'normal');
            var tam = 6.5;
            pdf.setFontSize(tam);
            while (tam > 5 && pdf.getTextWidth(texto) > ancho) {
                tam -= 0.5;
                pdf.setFontSize(tam);
            }
            var t = texto;
            if (pdf.getTextWidth(t) > ancho) {
                while (t && pdf.getTextWidth(t + '...') > ancho) { t = t.slice(0, -1); }
                t = t.replace(/\s+$/, '') + '...';
            }
            pdf.setFillColor(255, 255, 255);
            pdf.rect(0, h - 8, w, 8, 'F');
            pdf.setTextColor(120, 120, 120);
            pdf.text(t, w / 2, h - 4.5, { align: 'center' });
        }
    };
})();
