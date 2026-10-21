<?php
/*
|--------------------------------------------------------------------------
| El enlace de aviso por WhatsApp, armado en el navegador
|--------------------------------------------------------------------------
| Se incluye en las vistas de cobranza que pintan botones .js-wasap.
|
| POR QUE NO VA EL MENSAJE EN CADA FILA
|
| Iba: el texto completo dentro del href, unos 280 bytes por fila. Con 220
| morosos son 62 KB de la misma frase repetida, un tercio del cuerpo de la
| tabla. Aquí la plantilla está UNA vez y cada fila aporta sólo lo suyo:
| teléfono, valor y de qué es la deuda.
|
| SIGUE SIENDO UN ENLACE DE VERDAD
|
| El href se rellena al cargar y después de cada redibujado de DataTables,
| así que se puede abrir en otra pestaña, copiar o pulsar con el botón
| central, igual que antes. No se sustituye por un onclick.
|
| NO DEPENDE DE COMO SE LLAME EL CONTROLADOR EN LA VISTA
|
| La primera versión leía $insCobranza y las vistas lo llaman $insMora: el
| include habría fallado en las tres. Se instancia aquí uno propio, que no
| guarda estado y sólo se usa para leer la plantilla.
*/

$__wasap = new \app\controllers\cobranzaController();
?>
<script>
(function () {
	var PLANTILLA = <?php echo json_encode($__wasap->plantillaWhatsapp(), JSON_UNESCAPED_UNICODE); ?>;

	function armar() {
		document.querySelectorAll('a.js-wasap').forEach(function (a) {
			/* Ya armado: no se rehace en cada redibujado. */
			if (a.getAttribute('href')) { return; }

			var texto = PLANTILLA
				.replace('{CONCEPTO}', a.dataset.concepto || '')
				.replace('{VALOR}', a.dataset.valor || '');

			a.href = 'https://wa.me/593' + (a.dataset.tel || '')
			       + '?text=' + encodeURIComponent(texto);
		});
	}

	document.addEventListener('DOMContentLoaded', armar);

	/*
	| DataTables DESMONTA las filas que no se ven y las vuelve a montar al
	| cambiar de página: los enlaces de la página 2 nacen sin href.
	|
	| Se comprobó: con una escucha nativa de 'draw.dt' las páginas 2 y 3
	| salían con cero enlaces armados. draw.dt lo emite jQuery, y un
	| addEventListener nativo no lo oye. Hay que engancharse con jQuery,
	| que es quien lo dispara.
	|
	| El clic en captura queda como red de seguridad por si alguna tabla no
	| pasara por jQuery: arma el enlace antes de que el navegador lo siga.
	*/
	if (window.jQuery) {
		window.jQuery(document).on('draw.dt', armar);
	}

	document.addEventListener('click', function (e) {
		if (e.target.closest('a.js-wasap')) { armar(); }
	}, true);
})();
</script>
