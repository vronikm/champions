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
|   enlace, vence   enlace público firmado y su caducidad (d/m/Y)
|   pdf        URL del PDF con sesión, para «Compartir PDF»
|
| La redacción la decide cada vista porque cada módulo habla de cosas
| distintas (alumnos y pensiones, reservas y canchas); esta pieza sólo
| sabe montar el mensaje y entregarlo.
|
| El botón que abre el modal lo pone cada vista en su barra de acciones:
|   data-bs-toggle="modal" data-bs-target="#modalWhatsapp"
|
| POR QUE DOS VIAS PARA EL PDF
|
| Un enlace wa.me sólo transporta texto. «Compartir PDF» adjunta el archivo
| de verdad donde el navegador lo permite (casi todos los teléfonos y
| Chrome/Edge de escritorio); en el resto, el mensaje lleva un enlace que
| descarga sólo este recibo y caduca solo.
*/
if (!isset($waDatos) || !is_array($waDatos)) {
    http_response_code(404);
    exit;
}

$waEsc = static fn ($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
?>
		<!-- ==================== Enviar por WhatsApp ==================== -->
		<div class="modal fade" id="modalWhatsapp" tabindex="-1" aria-labelledby="modalWhatsappTitulo" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="modalWhatsappTitulo">
							<i class="fab fa-whatsapp me-2 wa-color"></i>Enviar recibo por WhatsApp
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="wa-destino mb-3">
							<div class="wa-avatar" aria-hidden="true"><i class="fas fa-user"></i></div>
							<div class="flex-grow-1 min-w-0">
								<div class="fw-semibold text-truncate"><?php echo $waEsc($waDatos['repre'] ?: 'Destinatario'); ?></div>
								<?php if (!empty($waDatos['subtitulo'])): ?>
									<div class="small text-body-secondary"><?php echo $waEsc($waDatos['subtitulo']); ?></div>
								<?php endif; ?>
							</div>
						</div>

						<label for="waCelular" class="form-label">Número de WhatsApp <span class="text-danger">*</span></label>
						<div class="input-group">
							<span class="input-group-text"><i class="fas fa-mobile-alt"></i></span>
							<input type="tel" class="form-control" id="waCelular" inputmode="tel" autocomplete="off"
							       placeholder="09XXXXXXXX" value="<?php echo $waEsc($waDatos['celular']); ?>">
						</div>
						<div class="form-text" id="waCelularAyuda"></div>

						<label for="waMensaje" class="form-label mt-3">Mensaje</label>
						<textarea class="form-control" id="waMensaje" rows="9"></textarea>

						<div class="form-check form-switch mt-2">
							<input class="form-check-input" type="checkbox" role="switch" id="waEnlace"
							       <?php echo $waDatos['enlace'] !== '' ? 'checked' : 'disabled'; ?>>
							<label class="form-check-label" for="waEnlace">
								Incluir enlace para descargar el recibo
								<?php if ($waDatos['vence'] !== ''): ?>
									<span class="text-body-secondary small">(válido hasta el <?php echo $waEsc($waDatos['vence']); ?>)</span>
								<?php endif; ?>
							</label>
						</div>

						<div class="wa-aviso mt-3" id="waAvisoPdf" hidden>
							<i class="fas fa-paperclip me-1"></i>
							Este dispositivo puede adjuntar el PDF directamente. Use <strong>Compartir PDF</strong> y elija WhatsApp.
						</div>
					</div>
					<div class="modal-footer flex-wrap gap-2">
						<button type="button" class="btn btn-outline-secondary btn-sm me-auto" id="waCopiar">
							<i class="far fa-copy me-1"></i><span>Copiar mensaje</span>
						</button>
						<button type="button" class="btn btn-outline-success btn-sm" id="waCompartir" hidden>
							<i class="fas fa-share-alt me-1"></i>Compartir PDF
						</button>
						<button type="button" class="btn btn-whatsapp btn-sm" id="waAbrir">
							<i class="fab fa-whatsapp me-1"></i>Abrir WhatsApp
						</button>
					</div>
				</div>
			</div>
		</div>

	<script>
	(function () {
		var D = <?php echo json_encode($waDatos, JSON_UNESCAPED_UNICODE); ?>;

		var modal    = document.getElementById('modalWhatsapp');
		var celular  = document.getElementById('waCelular');
		var ayuda    = document.getElementById('waCelularAyuda');
		var mensaje  = document.getElementById('waMensaje');
		var conEnl   = document.getElementById('waEnlace');
		var abrir    = document.getElementById('waAbrir');
		var copiar   = document.getElementById('waCopiar');
		var compart  = document.getElementById('waCompartir');
		var avisoPdf = document.getElementById('waAvisoPdf');

		/* Fútbol avisa con alertas_ajax (ajax.js); Arena, con SweetAlert. */
		function avisar(a) {
			if (typeof window.alertas_ajax === 'function') { window.alertas_ajax(a); return; }
			if (window.Swal) { window.Swal.fire({ icon: a.icono, title: a.titulo, text: a.texto }); return; }
			window.alert(a.titulo + '\n' + a.texto);
		}

		function plantilla() {
			return 'Hola ' + (D.repre || '') + ' 👋\n' + D.intro + '\n\n' +
				(D.lineas || []).join('\n') + '\n\n¡Gracias por su confianza!';
		}

		/* El número en formato internacional, sin «+». Ecuador por defecto:
		   0991234567 → 593991234567. Con prefijo de otro país, se respeta. */
		function numero() {
			var crudo = celular.value.trim(), n = crudo.replace(/\D/g, '');
			if (/^\+/.test(crudo))            { return n.length >= 8 && n.length <= 15 ? n : ''; }
			if (/^09\d{8}$/.test(n))          { return '593' + n.slice(1); }
			if (/^9\d{8}$/.test(n))           { return '593' + n; }
			if (/^5939\d{8}$/.test(n))        { return n; }
			return '';
		}

		function validar() {
			var n = numero();
			if (!celular.value.trim()) {
				ayuda.innerHTML = '<span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>No hay celular registrado. Escríbalo para continuar.</span>';
			} else if (!n) {
				ayuda.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Número no válido. Use un celular de 10 dígitos (09…) o el formato internacional (+57…).</span>';
			} else {
				ayuda.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Se enviará a +' +
					n.replace(/^(593)(\d{2})(\d{3})(\d{4})$/, '$1 $2 $3 $4') + '</span>';
			}
			celular.classList.toggle('is-invalid', !!celular.value.trim() && !n);
			abrir.disabled = !n;
			return n;
		}

		function textoFinal() {
			var t = mensaje.value.trim();
			if (conEnl.checked && D.enlace) {
				t += '\n\n📄 Descargue su recibo aquí' + (D.vence ? ' (válido hasta el ' + D.vence + ')' : '') + ':\n' + D.enlace;
			}
			return t;
		}

		/* El mensaje se prepara al abrir, sin pisar lo que el usuario ya
		   haya corregido en esta misma visita. */
		var preparado = false;
		modal.addEventListener('show.bs.modal', function () {
			if (!preparado) { mensaje.value = plantilla(); preparado = true; }
			validar();
		});
		modal.addEventListener('shown.bs.modal', function () {
			if (!numero()) { celular.focus(); return; }
			/* El cursor al principio: con él al final, el área se desplazaba
			   y el saludo quedaba cortado por arriba. */
			mensaje.focus();
			mensaje.setSelectionRange(0, 0);
			mensaje.scrollTop = 0;
		});
		celular.addEventListener('input', validar);

		abrir.addEventListener('click', function () {
			var n = validar();
			if (!n) { celular.focus(); return; }
			window.open('https://wa.me/' + n + '?text=' + encodeURIComponent(textoFinal()), '_blank', 'noopener');
		});

		copiar.addEventListener('click', function () {
			var etiqueta = copiar.querySelector('span');
			var listo = function () {
				etiqueta.textContent = '¡Copiado!';
				setTimeout(function () { etiqueta.textContent = 'Copiar mensaje'; }, 1800);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(textoFinal()).then(listo);
			} else {
				var a = document.createElement('textarea');
				a.value = textoFinal(); document.body.appendChild(a); a.select();
				try { document.execCommand('copy'); listo(); } catch (e) {}
				a.remove();
			}
		});

		/* Compartir el PDF de verdad, donde el navegador lo permita. */
		var prueba = (typeof File === 'function') ? new File(['x'], 'r.pdf', { type: 'application/pdf' }) : null;
		if (prueba && navigator.canShare && navigator.canShare({ files: [prueba] })) {
			compart.hidden = false;
			avisoPdf.hidden = false;
			compart.addEventListener('click', function () {
				compart.disabled = true;
				fetch(D.pdf, { credentials: 'same-origin' })
					.then(function (r) { if (!r.ok) { throw new Error('HTTP ' + r.status); } return r.blob(); })
					.then(function (b) {
						var archivo = new File([b], 'Recibo-' + D.recibo + '.pdf', { type: 'application/pdf' });
						return navigator.share({ files: [archivo], text: mensaje.value.trim(), title: 'Recibo ' + D.recibo });
					})
					.catch(function (e) {
						if (e && e.name === 'AbortError') { return; }   /* el usuario cerró el diálogo */
						avisar({ tipo: 'simple', icono: 'error', titulo: 'No se pudo compartir',
							texto: 'Use «Abrir WhatsApp»: el mensaje incluye el enlace para descargar el recibo.' });
					})
					.finally(function () { compart.disabled = false; });
			});
		}
	})();
	</script>

	<style>
		.btn-whatsapp { background: #25d366; border-color: #25d366; color: #0b1f14; font-weight: 600; }
		.btn-whatsapp:hover, .btn-whatsapp:focus-visible { background: #1ebe5b; border-color: #1ebe5b; color: #0b1f14; }
		.btn-whatsapp:disabled { background: #25d366; border-color: #25d366; color: #0b1f14; opacity: .5; }
		.wa-color { color: #25d366; }
		.wa-destino {
			display: flex; align-items: center; gap: 12px; padding: 10px 12px;
			border: 1px solid var(--bs-border-color); border-radius: var(--ds-radius-sm, 10px);
			background: var(--bs-tertiary-bg);
		}
		.wa-avatar {
			width: 40px; height: 40px; flex: 0 0 auto; display: grid; place-items: center;
			border-radius: 50%; background: #25d366; color: #0b1f14;
		}
		.min-w-0 { min-width: 0; }
		#waMensaje { font-size: .92rem; line-height: 1.45; }
		.wa-aviso {
			padding: 8px 12px; border-radius: var(--ds-radius-sm, 10px); font-size: .85rem;
			background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis);
			border: 1px solid var(--bs-success-border-subtle);
		}
		.wa-aviso[hidden] { display: none; }
	</style>
