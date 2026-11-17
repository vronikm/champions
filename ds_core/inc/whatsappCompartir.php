<?php
/*
|--------------------------------------------------------------------------
| Compartir un mensaje por WhatsApp, con imagen opcional — modal del ecosistema
|--------------------------------------------------------------------------
| Para mensajes que se envían a un CONTACTO o a un GRUPO: el enlace de
| inscripción, avisos… (el recibo tiene su propia pieza, whatsappRecibo.php,
| porque va a un solo representante y adjunta el PDF).
|
| Uso en la vista:
|     $waCompartir = ['titulo' => 'Compartir enlace de inscripción'];
|     require DS_HUB_PATH . "ds_core/inc/whatsappCompartir.php";
| y, en JavaScript, cuando ya se tenga el texto:
|     dsWhatsappCompartir.abrir({ mensaje: '…', enlace: 'https://…' });
| («enlace» es opcional: si se da, se avisa cuando el texto ya no lo lleva).
|
| UN SOLO ENVÍO: LA IMAGEN ARRIBA Y EL TEXTO DEBAJO
|
| Con imagen, se manda TODO junto como en WhatsApp se manda una foto con
| texto: la imagen y, debajo, el mensaje como pie de foto. La vista previa
| del modal lo muestra así antes de enviar.
|
| · Imagen + texto viajan con la función de compartir del dispositivo
|   (navigator.share con archivos y texto): casi todos los teléfonos y
|   Chrome/Edge de escritorio. Se elige WhatsApp y luego el chat o grupo.
| · WhatsApp admite hasta 1024 caracteres de pie de foto: el contador lo
|   vigila, porque si se pasa, WhatsApp puede mandar el texto aparte.
| · Donde no hay «compartir archivos», se abre el chat con el texto ya
|   escrito y la imagen copiada: al pegarla (Ctrl+V), el texto escrito pasa
|   a ser su pie. Si tampoco se puede copiar, la imagen se descarga.
| · Sin imagen, un enlace wa.me con el texto. wa.me sólo apunta a un
|   número, no a un grupo: sin número, WhatsApp abre su lista de chats y
|   ahí se elige el grupo. Por eso «Grupo» no pide número.
|
| La imagen no sale nunca hacia el servidor: se lee en el navegador.
*/
$waC    = is_array($waCompartir ?? null) ? $waCompartir : [];
$waCEsc = static fn ($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
?>
		<!-- ==================== Compartir por WhatsApp ==================== -->
		<div class="modal fade" id="modalWaCompartir" tabindex="-1" aria-labelledby="modalWaCompartirTitulo" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title" id="modalWaCompartirTitulo">
							<i class="fab fa-whatsapp me-2 wac-color" aria-hidden="true"></i><?php echo $waCEsc($waC['titulo'] ?? 'Compartir por WhatsApp'); ?>
						</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>
					<div class="modal-body">
						<div class="wac-cuerpo">

							<!-- ===== Lo que se escribe ===== -->
							<div class="wac-editar">
								<fieldset class="wac-fieldset mb-3">
									<legend class="form-label fs-6 mb-2">¿A quién se lo envía?</legend>
									<div class="wac-destinos" role="radiogroup">
										<input type="radio" class="btn-check" name="wacDestino" id="wacDestinoContacto" value="contacto" checked>
										<label class="btn wac-destino" for="wacDestinoContacto">
											<i class="fas fa-user" aria-hidden="true"></i><span>Un contacto</span>
										</label>
										<input type="radio" class="btn-check" name="wacDestino" id="wacDestinoGrupo" value="grupo">
										<label class="btn wac-destino" for="wacDestinoGrupo">
											<i class="fas fa-users" aria-hidden="true"></i><span>Un grupo</span>
										</label>
									</div>
								</fieldset>

								<div id="wacBloqueContacto" class="mb-3">
									<label for="wacCelular" class="form-label">Número de WhatsApp <span class="text-body-secondary fw-normal">(opcional)</span></label>
									<div class="input-group">
										<span class="input-group-text"><i class="fas fa-mobile-alt" aria-hidden="true"></i></span>
										<input type="tel" class="form-control" id="wacCelular" inputmode="tel" autocomplete="off" placeholder="09XXXXXXXX">
									</div>
									<div class="form-text" id="wacCelularAyuda"></div>
								</div>
								<div class="wac-nota mb-3" id="wacNotaDestino" hidden></div>

								<span class="form-label d-block" id="wacImagenRotulo">1 · Imagen <span class="text-body-secondary fw-normal">(opcional, va arriba)</span></span>
								<div class="wac-imagen mb-3">
									<label class="wac-imagen__elegir" for="wacImagen" id="wacImagenElegir">
										<i class="far fa-image" aria-hidden="true"></i>
										<span>Elegir una imagen<small>JPG, PNG o WebP · hasta 10 MB</small></span>
									</label>
									<input type="file" id="wacImagen" accept="image/jpeg,image/png,image/webp" class="visually-hidden" aria-labelledby="wacImagenRotulo">
									<div class="wac-imagen__vista" id="wacImagenVista" hidden>
										<img id="wacImagenMini" alt="">
										<div class="min-w-0 flex-grow-1">
											<div class="text-truncate small fw-semibold" id="wacImagenNombre"></div>
											<div class="small text-body-secondary" id="wacImagenPeso"></div>
										</div>
										<label class="btn btn-sm btn-outline-secondary mb-0" for="wacImagen" title="Cambiar la imagen" aria-label="Cambiar la imagen">
											<i class="fas fa-sync-alt" aria-hidden="true"></i>
										</label>
										<button type="button" class="btn btn-sm btn-outline-danger" id="wacImagenQuitar" title="Quitar la imagen" aria-label="Quitar la imagen">
											<i class="fas fa-times" aria-hidden="true"></i>
										</button>
									</div>
								</div>

								<div class="d-flex align-items-baseline justify-content-between gap-2">
									<label for="wacMensaje" class="form-label mb-1">2 · Texto <span class="text-body-secondary fw-normal" id="wacMensajeRol">(debajo de la imagen)</span></label>
									<span class="small text-body-secondary" id="wacContador" aria-live="polite"></span>
								</div>
								<textarea class="form-control" id="wacMensaje" rows="9"></textarea>
								<div class="form-text text-warning" id="wacSinEnlace" hidden>
									<i class="fas fa-exclamation-triangle me-1" aria-hidden="true"></i>El texto ya no incluye el enlace.
									<button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="wacRestaurar">Restaurar texto</button>
								</div>
								<div class="form-text text-danger" id="wacLargo" hidden>
									<i class="fas fa-exclamation-circle me-1" aria-hidden="true"></i>Como pie de imagen WhatsApp admite 1024 caracteres: acorte el texto o WhatsApp podría enviarlo aparte.
								</div>
							</div>

							<!-- ===== Cómo llegará ===== -->
							<div class="wac-previa">
								<div class="wac-previa__rotulo"><i class="far fa-eye me-1" aria-hidden="true"></i>Así llegará</div>
								<div class="wac-chat">
									<div class="wac-burbuja">
										<img id="wacPreviaImagen" alt="Imagen del mensaje" hidden>
										<div class="wac-burbuja__texto" id="wacPreviaTexto"></div>
										<div class="wac-burbuja__hora" aria-hidden="true"><span id="wacPreviaHora"></span> <i class="fas fa-check-double"></i></div>
									</div>
								</div>
								<p class="small text-body-secondary mt-2 mb-0" id="wacComo"></p>
							</div>
						</div>
					</div>
					<div class="modal-footer gap-2">
						<button type="button" class="btn btn-outline-secondary" id="wacCopiar">
							<i class="far fa-copy me-1" aria-hidden="true"></i><span>Copiar texto</span>
						</button>
						<button type="button" class="btn btn-whatsapp" id="wacEnviar">
							<i class="fab fa-whatsapp me-1" aria-hidden="true"></i><span>Enviar por WhatsApp</span>
						</button>
					</div>
				</div>
			</div>
		</div>

	<script>
	(function () {
		var $ = function (id) { return document.getElementById(id); };
		var modal = $('modalWaCompartir');
		if (!modal) { return; }

		var LIMITE_PIE = 1024;
		var celular = $('wacCelular'), ayuda = $('wacCelularAyuda'), nota = $('wacNotaDestino'),
		    mensaje = $('wacMensaje'), contador = $('wacContador'), archivo = $('wacImagen'),
		    elegir = $('wacImagenElegir'), vista = $('wacImagenVista'), enviar = $('wacEnviar'),
		    copiarBtn = $('wacCopiar'), previaImg = $('wacPreviaImagen'), previaTxt = $('wacPreviaTexto');
		var estado = { original: '', enlace: '', imagen: null, url: '' };

		function avisar(icono, titulo, texto) {
			if (window.Swal) { return window.Swal.fire({ icon: icono, title: titulo, text: texto }); }
			window.alert(titulo + '\n' + texto); return Promise.resolve();
		}
		function destino() { return document.querySelector('input[name="wacDestino"]:checked').value; }
		function largo(t) { return Array.from(t).length; }   /* un emoji cuenta uno, como en WhatsApp */
		function esc(t) { return t.replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }

		/* Número internacional sin «+» (Ecuador por defecto). null: vacío; '': no válido. */
		function numero() {
			var crudo = celular.value.trim(), n = crudo.replace(/\D/g, '');
			if (crudo === '') { return null; }
			if (/^\+/.test(crudo))     { return n.length >= 8 && n.length <= 15 ? n : ''; }
			if (/^09\d{8}$/.test(n))   { return '593' + n.slice(1); }
			if (/^9\d{8}$/.test(n))    { return '593' + n; }
			if (/^5939\d{8}$/.test(n)) { return n; }
			return '';
		}
		function compartible(f) {
			try { return !!(f && navigator.canShare && navigator.canShare({ files: [f], text: 'x' })); } catch (e) { return false; }
		}

		/* El formato de WhatsApp en la vista previa: *negrita*, _cursiva_, ~tachado~ y enlaces. */
		function formatoWhatsapp(t) {
			return esc(t)
				.replace(/(https?:\/\/[^\s<]+)/g, '<span class="wac-enlace">$1</span>')
				.replace(/(^|[\s(])\*([^*\n]+)\*(?=[\s).,!?:;]|$)/g, '$1<strong>$2</strong>')
				.replace(/(^|[\s(])_([^_\n]+)_(?=[\s).,!?:;]|$)/g, '$1<em>$2</em>')
				.replace(/(^|[\s(])~([^~\n]+)~(?=[\s).,!?:;]|$)/g, '$1<s>$2</s>');
		}

		function refrescar() {
			var grupo = destino() === 'grupo', conImagen = !!estado.imagen, comparte = compartible(estado.imagen);
			var t = mensaje.value.trim(), n = numero();

			/* Destino. Con imagen y «compartir», el chat se elige dentro de WhatsApp. */
			var eligeEnWa = grupo || (conImagen && comparte);
			$('wacBloqueContacto').hidden = eligeEnWa;
			nota.hidden = !eligeEnWa;
			if (eligeEnWa) {
				nota.innerHTML = '<i class="fas fa-' + (grupo ? 'users' : 'address-book') + ' me-1"></i>'
					+ 'Al enviar, WhatsApp le pedirá elegir ' + (grupo ? 'el <strong>grupo</strong>' : 'el <strong>contacto</strong>') + '.';
			} else {
				celular.classList.toggle('is-invalid', n === '');
				ayuda.innerHTML = n === '' ? '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Número no válido: 09… (10 dígitos) o +código de país.</span>'
					: n ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Se abrirá el chat con +' + n.replace(/^(593)(\d{2})(\d{3})(\d{4})$/, '$1 $2 $3 $4') + '</span>'
					: 'Vacío: elegirá el chat en WhatsApp.';
			}

			/* Texto: contador y avisos. */
			var c = largo(t), pasado = conImagen && c > LIMITE_PIE;
			contador.textContent = conImagen ? c + ' / ' + LIMITE_PIE : c + ' caracteres';
			contador.classList.toggle('text-danger', pasado);
			$('wacLargo').hidden = !pasado;
			$('wacMensajeRol').textContent = conImagen ? '(pie de la imagen)' : '';
			$('wacSinEnlace').hidden = !estado.enlace || t.indexOf(estado.enlace) !== -1;

			/* Vista previa: la imagen arriba y el texto debajo, como llega. */
			previaImg.hidden = !conImagen;
			if (conImagen) { previaImg.src = estado.url; }
			previaTxt.innerHTML = t ? formatoWhatsapp(t) : '<span class="text-body-secondary">(sin texto)</span>';

			$('wacComo').innerHTML = !conImagen
				? 'Se abrirá WhatsApp con el texto ya escrito.'
				: comparte
					? 'Se envía <strong>todo junto</strong>: en el menú de compartir elija WhatsApp y el chat.'
					: 'Este navegador no adjunta imágenes: se abrirá el chat con el texto escrito y la imagen copiada. Pegue con <kbd>Ctrl</kbd>+<kbd>V</kbd> y el texto quedará como pie.';

			enviar.disabled = !t || (!eligeEnWa && n === '');
			enviar.querySelector('span').textContent = conImagen ? 'Enviar imagen y texto' : 'Enviar por WhatsApp';
		}

		function ponerImagen(f) {
			if (!f) { return; }
			if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { avisar('error', 'Formato no válido', 'Use una imagen JPG, PNG o WebP.'); return; }
			if (f.size > 10 * 1024 * 1024) { avisar('error', 'Imagen muy grande', 'La imagen no debe superar los 10 MB.'); return; }
			if (estado.url) { URL.revokeObjectURL(estado.url); }
			estado.imagen = f; estado.url = URL.createObjectURL(f);
			$('wacImagenMini').src = estado.url;
			$('wacImagenNombre').textContent = f.name;
			$('wacImagenPeso').textContent = f.size >= 1048576 ? (f.size / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(f.size / 1024)) + ' KB';
			vista.hidden = false; elegir.hidden = true;
			refrescar();
		}
		function quitarImagen() {
			if (estado.url) { URL.revokeObjectURL(estado.url); }
			estado.imagen = null; estado.url = ''; archivo.value = '';
			vista.hidden = true; elegir.hidden = false;
			refrescar();
		}

		archivo.addEventListener('change', function () { ponerImagen(archivo.files[0]); archivo.value = ''; });
		$('wacImagenQuitar').addEventListener('click', quitarImagen);
		/* También se puede soltar una imagen sobre la zona, o pegarla (Ctrl+V) con el modal abierto. */
		['dragover', 'drop'].forEach(function (ev) {
			elegir.addEventListener(ev, function (e) {
				e.preventDefault();
				if (ev === 'drop' && e.dataTransfer.files[0]) { ponerImagen(e.dataTransfer.files[0]); }
			});
		});
		modal.addEventListener('paste', function (e) {
			var it = Array.from((e.clipboardData || {}).items || []).find(function (i) { return i.kind === 'file' && /^image\//.test(i.type); });
			if (it) { e.preventDefault(); ponerImagen(it.getAsFile()); }
		});
		document.querySelectorAll('input[name="wacDestino"]').forEach(function (r) { r.addEventListener('change', refrescar); });
		celular.addEventListener('input', refrescar);
		mensaje.addEventListener('input', refrescar);
		$('wacRestaurar').addEventListener('click', function () { mensaje.value = estado.original; refrescar(); });

		function copiarTexto(t) {
			if (navigator.clipboard && window.isSecureContext) { return navigator.clipboard.writeText(t); }
			return new Promise(function (ok, mal) {
				var a = document.createElement('textarea'); a.value = t; document.body.appendChild(a); a.select();
				try { document.execCommand('copy') ? ok() : mal(); } catch (e) { mal(e); } finally { a.remove(); }
			});
		}
		/* El portapapeles de imágenes sólo admite PNG: se convierte en el navegador. */
		function copiarImagen() {
			if (!(window.ClipboardItem && navigator.clipboard && navigator.clipboard.write && window.isSecureContext)) {
				return Promise.reject(new Error('sin portapapeles de imágenes'));
			}
			var f = estado.imagen;
			var png = f.type === 'image/png' ? Promise.resolve(f) : new Promise(function (ok, mal) {
				var img = new Image();
				img.onload = function () {
					var c = document.createElement('canvas'); c.width = img.naturalWidth; c.height = img.naturalHeight;
					c.getContext('2d').drawImage(img, 0, 0);
					c.toBlob(function (b) { b ? ok(b) : mal(new Error('png')); }, 'image/png');
				};
				img.onerror = mal; img.src = estado.url;
			});
			return png.then(function (b) { return navigator.clipboard.write([new ClipboardItem({ 'image/png': b })]); });
		}
		function descargarImagen() {
			var a = document.createElement('a'); a.href = estado.url; a.download = estado.imagen.name || 'imagen';
			document.body.appendChild(a); a.click(); a.remove();
		}

		copiarBtn.addEventListener('click', function () {
			var et = copiarBtn.querySelector('span');
			copiarTexto(mensaje.value.trim()).then(function () {
				et.textContent = '¡Copiado!'; setTimeout(function () { et.textContent = 'Copiar texto'; }, 1800);
			});
		});

		enviar.addEventListener('click', function () {
			var t = mensaje.value.trim(), grupo = destino() === 'grupo';
			var n = grupo ? null : numero();
			var waUrl = 'https://wa.me/' + (n || '') + '?text=' + encodeURIComponent(t);

			if (!estado.imagen) { window.open(waUrl, '_blank', 'noopener'); return; }

			/* Imagen + texto en UN envío: el texto como pie, debajo de la imagen. */
			if (compartible(estado.imagen)) {
				enviar.disabled = true;
				navigator.share({ files: [estado.imagen], text: t })
					.catch(function (e) {
						if (e && e.name === 'AbortError') { return; }   /* cerró el menú */
						avisar('error', 'No se pudo compartir', 'Inténtelo de nuevo. Si persiste, copie el texto y adjunte la imagen en el chat.');
					})
					.finally(function () { enviar.disabled = false; });
				return;
			}

			/* Sin «compartir archivos»: el chat se abre en el mismo gesto (si no,
			   el navegador bloquea la ventana) con el texto escrito, y la imagen
			   queda copiada: al pegarla, el texto pasa a ser su pie. */
			window.open(waUrl, '_blank', 'noopener');
			copiarImagen().then(function () {
				avisar('success', 'Imagen copiada', 'En el chat, pegue la imagen con Ctrl+V: el texto ya escrito quedará como su pie. Revise y envíe.');
			}).catch(function () {
				descargarImagen();
				avisar('info', 'Imagen descargada', 'Este navegador no permite copiar imágenes. Adjúntela en el chat con el clip 📎; escriba o pegue el texto en el pie.');
			});
		});

		window.dsWhatsappCompartir = {
			abrir: function (opciones) {
				opciones = opciones || {};
				estado.original = opciones.mensaje || '';
				estado.enlace = opciones.enlace || '';
				mensaje.value = estado.original;
				var ahora = new Date();
				$('wacPreviaHora').textContent = String(ahora.getHours()).padStart(2, '0') + ':' + String(ahora.getMinutes()).padStart(2, '0');
				refrescar();
				bootstrap.Modal.getOrCreateInstance(modal).show();
			}
		};
		modal.addEventListener('shown.bs.modal', function () {
			mensaje.setSelectionRange(0, 0); mensaje.scrollTop = 0;
		});
	})();
	</script>

	<style>
		.btn-whatsapp { background: #25d366; border-color: #25d366; color: #0b1f14; font-weight: 600; }
		.btn-whatsapp:hover, .btn-whatsapp:focus-visible { background: #1ebe5b; border-color: #1ebe5b; color: #0b1f14; }
		.btn-whatsapp:disabled { background: #25d366; border-color: #25d366; color: #0b1f14; opacity: .5; }
		.wac-color { color: #25d366; }
		#modalWaCompartir .wac-fieldset { border: 0; padding: 0; margin-left: 0; margin-right: 0; min-width: 0; }
		#modalWaCompartir [hidden] { display: none !important; }

		/* Dos columnas en pantallas anchas: lo que se escribe y cómo llegará. */
		.wac-cuerpo { display: grid; gap: 1.25rem; }
		@media (min-width: 992px) { .wac-cuerpo { grid-template-columns: minmax(0, 1fr) minmax(0, 20rem); align-items: start; } }

		.wac-destinos { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
		.wac-destino {
			display: flex; align-items: center; justify-content: center; gap: .5rem; min-height: 2.75rem;
			border: 1px solid var(--bs-border-color); background: var(--bs-body-bg); color: var(--bs-body-color);
		}
		.btn-check:checked + .wac-destino {
			border-color: #25d366; background: rgba(37, 211, 102, .12); color: var(--bs-emphasis-color); font-weight: 600;
			box-shadow: inset 0 0 0 1px #25d366;
		}
		.btn-check:focus-visible + .wac-destino { outline: 2px solid #25d366; outline-offset: 2px; }
		.wac-nota {
			padding: 8px 12px; border-radius: var(--ds-radius-sm, 10px); font-size: .85rem;
			background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis);
			border: 1px solid var(--bs-success-border-subtle);
		}
		#wacMensaje { font-size: .92rem; line-height: 1.45; }

		.wac-imagen__elegir {
			display: flex; align-items: center; gap: .75rem; padding: .75rem 1rem; margin: 0; cursor: pointer;
			border: 1px dashed var(--bs-border-color); border-radius: var(--ds-radius-sm, 10px);
			background: var(--bs-tertiary-bg);
		}
		.wac-imagen__elegir:hover { border-color: #25d366; }
		.wac-imagen__elegir i { font-size: 1.5rem; color: var(--bs-secondary-color); }
		.wac-imagen__elegir span { display: flex; flex-direction: column; font-weight: 600; }
		.wac-imagen__elegir small { font-weight: 400; color: var(--bs-secondary-color); }
		#wacImagen:focus-visible ~ .wac-imagen__elegir, #wacImagen:focus-visible ~ .wac-imagen__vista { outline: 2px solid #25d366; outline-offset: 2px; }
		.wac-imagen__vista {
			display: flex; align-items: center; gap: .6rem; padding: .5rem;
			border: 1px solid var(--bs-border-color); border-radius: var(--ds-radius-sm, 10px);
		}
		.wac-imagen__vista img { width: 56px; height: 56px; object-fit: cover; border-radius: 8px; flex: none; background: var(--bs-tertiary-bg); }
		.wac-imagen__vista .btn { min-width: 2.25rem; min-height: 2.25rem; display: inline-flex; align-items: center; justify-content: center; }

		/* Vista previa con el aspecto de un chat de WhatsApp. */
		.wac-previa__rotulo { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); margin-bottom: .4rem; }
		.wac-chat {
			padding: .75rem; border-radius: var(--ds-radius-sm, 10px);
			background: #e5ddd5; max-height: 26rem; overflow-y: auto;
		}
		.wac-burbuja {
			margin-left: auto; max-width: 18rem; padding: 4px 4px 6px;
			background: #d9fdd3; color: #111b21; border-radius: 8px 0 8px 8px;
			box-shadow: 0 1px .5px rgba(11, 20, 26, .13); font-size: .86rem; line-height: 1.38;
		}
		.wac-burbuja img { display: block; width: 100%; max-height: 16rem; object-fit: cover; border-radius: 6px; }
		.wac-burbuja__texto { padding: 6px 6px 0; white-space: pre-wrap; overflow-wrap: anywhere; }
		.wac-burbuja__texto .wac-enlace { color: #027eb5; text-decoration: underline; }
		.wac-burbuja__hora { text-align: right; font-size: .68rem; color: #667781; padding: 0 6px; }
		.wac-burbuja__hora i { color: #53bdeb; }
		[data-bs-theme="dark"] .wac-chat { background: #0b141a; }
		[data-bs-theme="dark"] .wac-burbuja { background: #005c4b; color: #e9edef; }
		[data-bs-theme="dark"] .wac-burbuja__texto .wac-enlace { color: #53bdeb; }
		[data-bs-theme="dark"] .wac-burbuja__hora { color: #8696a0; }

		/* Teléfono: botones a todo el ancho, enviar primero. */
		@media (max-width: 575.98px) {
			#modalWaCompartir .modal-footer { flex-direction: column-reverse; align-items: stretch; }
			#modalWaCompartir .modal-footer > .btn { margin: 0; min-height: 2.75rem; }
			.wac-chat { max-height: 18rem; }
		}
		.min-w-0 { min-width: 0; }
	</style>
