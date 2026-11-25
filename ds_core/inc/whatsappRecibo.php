<?php
/*
|--------------------------------------------------------------------------
| Enviar un recibo por WhatsApp — modal, script y estilos del ecosistema
|--------------------------------------------------------------------------
| Lo usan Fútbol (recibo de un pago y de un abono a un pago pendiente) y
| Arena (recibo del pago de una reserva). Vive en el núcleo para que los
| dos módulos usen la MISMA pieza; ds_core/inc no se sirve por HTTP.
|
| Antes de incluirlo, la vista define $waDatos:
|
|   repre      nombre de quien recibe el mensaje
|   subtitulo  segunda línea bajo el nombre («Representante de …»)
|   celular    número tal como está en la ficha
|   intro      la frase tras el saludo («… le confirma el pago registrado:»)
|   lineas     [] líneas del detalle, ya redactadas por la vista
|   recibo     número del recibo, para nombrar el PDF
|   pdf        URL del PDF con sesión
|
| El botón que abre el modal lo pone cada vista en su barra de acciones:
|   data-bs-toggle="modal" data-bs-target="#modalWhatsapp"
|
|
| SIEMPRE EL PDF Y EL MENSAJE, NUNCA UN ENLACE
|
| El recibo viaja como archivo, con el mensaje, en un solo envío. Desde el
| 2026-11-18 no se comparte ningún enlace para descargarlo (antes había un
| interruptor «Incluir enlace…» y un botón «Abrir WhatsApp» que mandaba
| sólo texto): un enlace reenviado lo abre cualquiera mientras no caduque.
|
| Cómo llega el archivo a WhatsApp depende del dispositivo:
|
|   · Con «compartir archivos» (teléfonos y Chrome/Edge de escritorio):
|     navigator.share({files, text}). Se elige WhatsApp y el chat, y el PDF
|     sale con el mensaje.
|   · Sin él (Firefox de escritorio, por ejemplo): el mismo botón descarga
|     el PDF y abre el chat con el mensaje escrito; se indica que hay que
|     adjuntar el PDF con el clip. wa.me no puede llevar archivos.
|
| El PDF se pide AL ABRIR el modal y no al tocar «Enviar»: navigator.share
| exige que se llame dentro del toque, y esperar la descarga en medio hace
| que Safari y Chrome del teléfono lo rechacen.
*/
if (!isset($waDatos) || !is_array($waDatos)) {
    http_response_code(404);
    exit;
}

/* Al script sólo lo que usa: ni enlaces ni datos que no se muestran. */
$waJs = [
    'repre'   => (string)($waDatos['repre'] ?? ''),
    'intro'   => (string)($waDatos['intro'] ?? ''),
    'lineas'  => array_values((array)($waDatos['lineas'] ?? [])),
    'recibo'  => (string)($waDatos['recibo'] ?? ''),
    'pdf'     => (string)($waDatos['pdf'] ?? ''),
];
$waEsc = static fn ($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
$waArchivo = 'Recibo-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string)($waDatos['recibo'] ?? '')) . '.pdf';
?>
		<!-- ==================== Enviar por WhatsApp ==================== -->
		<div class="modal fade" id="modalWhatsapp" tabindex="-1" aria-labelledby="modalWhatsappTitulo" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="modalWhatsappTitulo">
							<i class="fab fa-whatsapp me-2 wa-color" aria-hidden="true"></i>Enviar recibo por WhatsApp
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<!-- Para quién -->
						<div class="wa-destino">
							<div class="wa-avatar" aria-hidden="true"><i class="fas fa-user"></i></div>
							<div class="flex-grow-1 min-w-0">
								<div class="fw-semibold text-truncate"><?php echo $waEsc($waDatos['repre'] ?: 'Destinatario'); ?></div>
								<?php if (!empty($waDatos['subtitulo'])): ?>
									<div class="small text-body-secondary text-truncate"><?php echo $waEsc($waDatos['subtitulo']); ?></div>
								<?php endif; ?>
								<?php if (trim((string)$waDatos['celular']) !== ''): ?>
									<div class="small wa-destino__cel"><i class="fas fa-mobile-alt me-1" aria-hidden="true"></i><?php echo $waEsc($waDatos['celular']); ?></div>
								<?php endif; ?>
							</div>
						</div>

						<!-- Número: sólo donde el chat se abre por wa.me (sin «compartir archivos»).
						     Con compartir, el chat se elige en WhatsApp. -->
						<div id="waNumeroCaja" class="mt-3" hidden>
							<label for="waCelular" class="form-label">Número de WhatsApp</label>
							<div class="input-group">
								<span class="input-group-text"><i class="fas fa-mobile-alt" aria-hidden="true"></i></span>
								<input type="tel" class="form-control" id="waCelular" inputmode="tel" autocomplete="off"
								       placeholder="09XXXXXXXX" value="<?php echo $waEsc($waDatos['celular']); ?>" aria-describedby="waCelularAyuda">
							</div>
							<div class="form-text" id="waCelularAyuda"></div>
						</div>

						<!-- Lo que se envía: el PDF, y debajo el mensaje que lo acompaña -->
						<div class="wa-envio mt-3">
							<div class="wa-envio__titulo">Se envía</div>
							<div class="wa-adjunto" id="waAdjunto" aria-live="polite">
								<span class="wa-adjunto__icono" aria-hidden="true"><i class="fas fa-file-pdf"></i></span>
								<span class="min-w-0 flex-grow-1">
									<span class="wa-adjunto__nombre d-block text-truncate"><?php echo $waEsc($waArchivo); ?></span>
									<span class="wa-adjunto__estado d-block small" id="waAdjuntoEstado">
										<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Preparando el PDF…
									</span>
								</span>
								<button type="button" class="btn btn-sm btn-link p-0 wa-adjunto__reintentar" id="waReintentar" hidden>Reintentar</button>
							</div>

							<label for="waMensaje" class="form-label mt-3 mb-1">Mensaje que acompaña al PDF</label>
							<textarea class="form-control" id="waMensaje" rows="8"></textarea>
						</div>

						<div class="wa-como mt-3" id="waComo"></div>
					</div>
					<div class="modal-footer wa-pie">
						<button type="button" class="btn btn-outline-secondary wa-pie__copiar" id="waCopiar">
							<i class="far fa-copy me-1" aria-hidden="true"></i><span>Copiar mensaje</span>
						</button>
						<button type="button" class="btn btn-whatsapp wa-pie__enviar" id="waEnviar" disabled>
							<i class="fab fa-whatsapp me-2" aria-hidden="true"></i>Enviar recibo
						</button>
					</div>
				</div>
			</div>
		</div>

	<script>
	(function () {
		var D = <?php echo json_encode($waJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
		var NOMBRE = <?php echo json_encode($waArchivo); ?>;

		var modal    = document.getElementById('modalWhatsapp');
		var cajaNum  = document.getElementById('waNumeroCaja');
		var celular  = document.getElementById('waCelular');
		var ayuda    = document.getElementById('waCelularAyuda');
		var mensaje  = document.getElementById('waMensaje');
		var enviar   = document.getElementById('waEnviar');
		var copiar   = document.getElementById('waCopiar');
		var estado   = document.getElementById('waAdjuntoEstado');
		var reint    = document.getElementById('waReintentar');
		var como     = document.getElementById('waComo');

		/* Fútbol avisa con alertas_ajax (ajax.js); Arena, con SweetAlert. */
		function avisar(a) {
			if (window.Swal) { return window.Swal.fire({ icon: a.icono, title: a.titulo, html: a.html || null, text: a.html ? null : a.texto, confirmButtonText: 'Entendido' }); }
			if (typeof window.alertas_ajax === 'function') { window.alertas_ajax({ tipo: 'simple', icono: a.icono, titulo: a.titulo, texto: a.texto }); return; }
			window.alert(a.titulo + '\n' + a.texto);
		}

		function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

		function plantilla() {
			/* Sin nombre (consumidor final en Arena › Punto de venta): «Hola 👋». */
			return 'Hola ' + (D.repre ? D.repre + ' ' : '') + '👋\n' + D.intro + '\n\n' +
				(D.lineas || []).join('\n') + '\n\n📎 Adjunto su recibo en PDF.\n\n¡Gracias por su confianza!';
		}

		/* ¿Este navegador puede pasarle un PDF a WhatsApp? */
		var prueba = (typeof File === 'function') ? new File(['x'], 'r.pdf', { type: 'application/pdf' }) : null;
		var conArchivo = !!(prueba && navigator.canShare && navigator.share && navigator.canShare({ files: [prueba] }));

		/* El número en formato internacional, sin «+». Ecuador por defecto:
		   0991234567 → 593991234567. Con prefijo de otro país, se respeta.
		   Vacío = WhatsApp pide elegir el chat. */
		function numero() {
			var crudo = celular.value.trim(), n = crudo.replace(/\D/g, '');
			if (crudo === '')                 { return ''; }
			if (/^\+/.test(crudo))            { return n.length >= 8 && n.length <= 15 ? n : null; }
			if (/^09\d{8}$/.test(n))          { return '593' + n.slice(1); }
			if (/^9\d{8}$/.test(n))           { return '593' + n; }
			if (/^5939\d{8}$/.test(n))        { return n; }
			return null;
		}
		function validar() {
			var n = numero();
			if (n === '') {
				ayuda.innerHTML = '<span class="text-body-secondary"><i class="fas fa-info-circle me-1" aria-hidden="true"></i>Sin número, WhatsApp le pedirá elegir el chat.</span>';
			} else if (n === null) {
				ayuda.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1" aria-hidden="true"></i>Número no válido. Use un celular de 10 dígitos (09…) o el formato internacional (+57…).</span>';
			} else {
				ayuda.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1" aria-hidden="true"></i>Se abrirá el chat con +' +
					n.replace(/^(593)(\d{2})(\d{3})(\d{4})$/, '$1 $2 $3 $4') + '</span>';
			}
			celular.classList.toggle('is-invalid', n === null);
			return n;
		}

		/* El PDF, pedido una vez por página y guardado para los envíos. */
		var pdf = null, pidiendo = false;
		function pedirPdf() {
			if (pdf || pidiendo) { return; }
			pidiendo = true; enviar.disabled = true; reint.hidden = true;
			estado.className = 'wa-adjunto__estado d-block small';
			estado.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Preparando el PDF…';
			fetch(D.pdf, { credentials: 'same-origin' })
				.then(function (r) {
					if (!r.ok) { throw new Error('HTTP ' + r.status); }
					return r.blob();
				})
				.then(function (b) {
					/* Una sesión caducada devuelve el login en HTML, no un PDF. */
					if (b.size < 100 || (b.type && b.type.indexOf('pdf') === -1)) { throw new Error('no es un PDF'); }
					pdf = new File([b], NOMBRE, { type: 'application/pdf' });
					estado.className = 'wa-adjunto__estado d-block small text-success';
					estado.innerHTML = '<i class="fas fa-check-circle me-1" aria-hidden="true"></i>Listo · ' +
						(b.size >= 1048576 ? (b.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b.size / 1024)) + ' KB');
					enviar.disabled = false;
				})
				.catch(function () {
					estado.className = 'wa-adjunto__estado d-block small text-danger';
					estado.innerHTML = '<i class="fas fa-exclamation-circle me-1" aria-hidden="true"></i>No se pudo preparar el PDF.';
					reint.hidden = false;
				})
				.finally(function () { pidiendo = false; });
		}
		reint.addEventListener('click', pedirPdf);

		/* Qué va a pasar al tocar «Enviar recibo», dicho antes de tocarlo. */
		if (conArchivo) {
			como.innerHTML = '<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Al tocar <strong>Enviar recibo</strong>, elija ' +
				'<strong>WhatsApp</strong> y el chat de ' + (D.repre ? '<strong>' + esc(D.repre) + '</strong>' : 'quien lo recibe') +
				': el PDF y el mensaje se envían juntos.';
		} else {
			cajaNum.hidden = false;
			como.innerHTML = '<i class="fas fa-circle-info me-1" aria-hidden="true"></i>Este navegador no puede pasarle archivos a WhatsApp. ' +
				'Al tocar <strong>Enviar recibo</strong> se descarga el PDF y se abre el chat con el mensaje: adjúntelo con el clip <i class="fas fa-paperclip" aria-hidden="true"></i> y envíe.';
		}

		/* El mensaje se prepara al abrir, sin pisar lo que el usuario ya
		   haya corregido en esta misma visita. */
		var preparado = false;
		modal.addEventListener('show.bs.modal', function () {
			if (!preparado) { mensaje.value = plantilla(); preparado = true; }
			if (!conArchivo) { validar(); }
			pedirPdf();
		});
		modal.addEventListener('shown.bs.modal', function () {
			/* El cursor al principio: con él al final, el área se desplazaba
			   y el saludo quedaba cortado por arriba. Sin foco en el teléfono:
			   abriría el teclado tapando el botón. */
			mensaje.setSelectionRange(0, 0);
			mensaje.scrollTop = 0;
		});
		celular.addEventListener('input', validar);

		enviar.addEventListener('click', function () {
			if (!pdf) { pedirPdf(); return; }
			var texto = mensaje.value.trim();

			if (conArchivo) {
				/* Dentro del toque y sin esperas: así lo exige navigator.share. */
				navigator.share({ files: [pdf], text: texto, title: 'Recibo ' + D.recibo })
					.then(function () {
						var m = window.bootstrap && window.bootstrap.Modal.getInstance(modal);
						if (m) { m.hide(); }
					})
					.catch(function (e) {
						if (e && e.name === 'AbortError') { return; }   /* cerró el menú de compartir */
						avisar({ icono: 'error', titulo: 'No se pudo enviar',
							texto: 'El dispositivo no dejó compartir el recibo. Vuelva a tocar «Enviar recibo»; si sigue fallando, descárguelo y adjúntelo desde WhatsApp.' });
					});
				return;
			}

			/* Sin compartir archivos: el chat con el mensaje y el PDF descargado. */
			var n = validar();
			if (n === null) { celular.focus(); return; }
			window.open('https://wa.me/' + n + '?text=' + encodeURIComponent(texto), '_blank', 'noopener');
			var url = URL.createObjectURL(pdf), a = document.createElement('a');
			a.href = url; a.download = NOMBRE; document.body.appendChild(a); a.click(); a.remove();
			setTimeout(function () { URL.revokeObjectURL(url); }, 60000);
			avisar({ icono: 'info', titulo: 'Adjunte el PDF en WhatsApp',
				texto: 'Se descargó ' + NOMBRE + ' y se abrió el chat con el mensaje. En WhatsApp, toque el clip, elija el PDF descargado y envíe.',
				html: 'Se descargó <strong>' + esc(NOMBRE) + '</strong> y se abrió el chat con el mensaje.<br>En WhatsApp, toque el clip <i class="fas fa-paperclip"></i>, elija el PDF descargado y envíe.' });
		});

		copiar.addEventListener('click', function () {
			var etiqueta = copiar.querySelector('span'), texto = mensaje.value.trim();
			var listo = function () {
				etiqueta.textContent = '¡Copiado!';
				setTimeout(function () { etiqueta.textContent = 'Copiar mensaje'; }, 1800);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(texto).then(listo);
			} else {
				var a = document.createElement('textarea');
				a.value = texto; document.body.appendChild(a); a.select();
				try { document.execCommand('copy'); listo(); } catch (e) {}
				a.remove();
			}
		});
	})();
	</script>

	<style>
		.btn-whatsapp { background: #25d366; border-color: #25d366; color: #0b1f14; font-weight: 600; }
		.btn-whatsapp:hover, .btn-whatsapp:focus-visible { background: #1ebe5b; border-color: #1ebe5b; color: #0b1f14; }
		.btn-whatsapp:disabled { background: #25d366; border-color: #25d366; color: #0b1f14; opacity: .55; }
		.wa-color { color: #25d366; }
		.min-w-0 { min-width: 0; }

		.wa-destino {
			display: flex; align-items: center; gap: 12px; padding: 10px 12px;
			border: 1px solid var(--bs-border-color); border-radius: var(--ds-radius-sm, 10px);
			background: var(--bs-tertiary-bg);
		}
		.wa-avatar {
			width: 42px; height: 42px; flex: 0 0 auto; display: grid; place-items: center;
			border-radius: 50%; background: #25d366; color: #0b1f14;
		}
		.wa-destino__cel { color: var(--bs-emphasis-color); font-variant-numeric: tabular-nums; }

		/* Lo que se envía: el PDF como en un chat, y el mensaje debajo. */
		.wa-envio__titulo { font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--bs-secondary-color); margin-bottom: .4rem; }
		.wa-adjunto {
			display: flex; align-items: center; gap: 12px; padding: 10px 12px;
			border: 1px solid var(--bs-border-color); border-radius: var(--ds-radius-sm, 10px);
			background: var(--bs-body-bg);
		}
		.wa-adjunto__icono {
			width: 40px; height: 40px; flex: 0 0 auto; display: grid; place-items: center;
			border-radius: 8px; font-size: 1.25rem;
			background: var(--bs-danger-bg-subtle); color: var(--bs-danger-text-emphasis);
		}
		.wa-adjunto__nombre { font-weight: 600; }
		.wa-adjunto__estado { color: var(--bs-secondary-color); }
		.wa-adjunto__reintentar { font-weight: 600; }
		#waMensaje { font-size: .92rem; line-height: 1.45; }
		.wa-como {
			padding: 9px 12px; border-radius: var(--ds-radius-sm, 10px); font-size: .85rem; line-height: 1.4;
			background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis);
			border: 1px solid var(--bs-success-border-subtle);
		}
		.wa-como:empty { display: none; }

		.wa-pie { gap: .5rem; }
		.wa-pie .btn { min-height: 2.75rem; }
		.wa-pie__copiar { margin-right: auto; }
		.wa-pie__enviar { padding-inline: 1.25rem; }

		/* Teléfono: el modal a pantalla completa (modal-fullscreen-sm-down) y
		   el botón de enviar grande, abajo, al alcance del pulgar. */
		@media (max-width: 575.98px) {
			.wa-pie { flex-direction: column-reverse; align-items: stretch; padding: .75rem; }
			.wa-pie > .btn { width: 100%; margin: 0; }
			.wa-pie .wa-pie__enviar { min-height: 3.25rem; font-size: 1.05rem; }
			.wa-pie .wa-pie__copiar { border-color: transparent; }
			#waMensaje { min-height: 12rem; }
		}
	</style>
