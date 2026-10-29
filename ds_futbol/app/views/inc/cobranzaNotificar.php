<?php
/*
|--------------------------------------------------------------------------
| Modal «Notificar» de las cobranzas (pensiones y uniformes)
|--------------------------------------------------------------------------
| El mismo esquema que «Enviar autorización» de consentimientoList: a quién,
| por dónde (WhatsApp o correo) y el mensaje, que se puede corregir. Lo abren
| los botones [data-notificar] que pinta cobranzaController::botonNotificar.
|
| La vista define antes de incluirlo:
|
|   $cobroVista     'cobranzaPension' o 'cobranzaUniforme': viaja con el
|                   envío por correo y es la pantalla cuyo permiso «editar»
|                   comprueba cobranzaAjax.php
|   $cobroConcepto  'pensiones' o 'uniformes': de qué es la deuda en el
|                   mensaje («saldo pendiente de uniformes de Juan Pérez»)
|
| Va DESPUÉS de bootstrap.bundle y de sweetalert (lo usa al enviar).
*/
$__notificar = new \app\controllers\cobranzaController();
?>
<div class="modal fade ds-aut-modal" id="modalNotificar" tabindex="-1" aria-labelledby="notTitulo" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="notTitulo"><i class="fas fa-paper-plane me-2 text-primary" aria-hidden="true"></i>Notificar pago pendiente</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div class="ds-aut-destino mb-3">
					<div class="ds-aut-avatar" aria-hidden="true"><i class="fas fa-user"></i></div>
					<div class="flex-grow-1" style="min-width:0">
						<div class="fw-semibold text-truncate" id="notNombre"></div>
						<div class="small text-body-secondary" id="notDetalle"></div>
					</div>
				</div>

				<fieldset class="mb-3 border-0 p-0 m-0">
					<legend class="form-label fs-6 mb-2">Enviar por</legend>
					<div class="ds-aut-canales">
						<input type="radio" class="btn-check" name="notCanal" id="notCanalWa" value="WHATSAPP" autocomplete="off">
						<label class="btn ds-aut-canal" for="notCanalWa">
							<i class="fab fa-whatsapp ds-aut-canal__icono" aria-hidden="true"></i>
							<span>WhatsApp<small id="notResumenWa"></small></span>
						</label>
						<input type="radio" class="btn-check" name="notCanal" id="notCanalCorreo" value="CORREO" autocomplete="off">
						<label class="btn ds-aut-canal" for="notCanalCorreo">
							<i class="fas fa-envelope ds-aut-canal__icono" aria-hidden="true"></i>
							<span>Correo<small id="notResumenCorreo"></small></span>
						</label>
					</div>
				</fieldset>

				<div id="notGrupoWa">
					<label for="notCelular" class="form-label">Número de WhatsApp <span class="text-danger">*</span></label>
					<div class="input-group">
						<span class="input-group-text"><i class="fas fa-mobile-alt" aria-hidden="true"></i></span>
						<input type="tel" class="form-control" id="notCelular" inputmode="tel" autocomplete="off" placeholder="09XXXXXXXX">
					</div>
					<div class="form-text" id="notCelularAyuda"></div>
				</div>

				<div id="notGrupoCorreo" hidden>
					<label for="notCorreo" class="form-label">Correo del representante <span class="text-danger">*</span></label>
					<div class="input-group">
						<span class="input-group-text"><i class="fas fa-at" aria-hidden="true"></i></span>
						<input type="email" class="form-control" id="notCorreo" inputmode="email" autocomplete="off" placeholder="correo@ejemplo.com">
					</div>
					<div class="form-text" id="notCorreoAyuda"></div>
				</div>

				<label for="notCuerpo" class="form-label mt-3">Mensaje</label>
				<textarea class="form-control" id="notCuerpo" rows="6" maxlength="1500"></textarea>

				<div class="alert alert-danger mt-3 mb-0 py-2" id="notAviso" role="alert" hidden></div>
			</div>
			<div class="modal-footer flex-wrap gap-2">
				<button type="button" class="btn btn-outline-secondary me-auto" id="notCopiar">
					<i class="far fa-copy me-1" aria-hidden="true"></i><span>Copiar mensaje</span>
				</button>
				<button type="button" class="btn btn-whatsapp" id="notEnviar" disabled>
					<i class="fab fa-whatsapp me-1" aria-hidden="true"></i><span>Abrir WhatsApp</span>
				</button>
			</div>
		</div>
	</div>
</div>
<script>
(function () {
	var AJAX = '<?php echo APP_URL; ?>app/ajax/cobranzaAjax.php';
	var VISTA = <?php echo json_encode($cobroVista); ?>;
	var CONCEPTO = <?php echo json_encode($cobroConcepto, JSON_UNESCAPED_UNICODE); ?>;
	var PLANTILLA = <?php echo json_encode($__notificar->plantillaWhatsapp(), JSON_UNESCAPED_UNICODE); ?>;
	var $n = function (id) { return document.getElementById(id); };
	var modalEl = $n('modalNotificar'), modal = bootstrap.Modal.getOrCreateInstance(modalEl);
	var celular = $n('notCelular'), correo = $n('notCorreo'), cuerpo = $n('notCuerpo');
	var btnEnviar = $n('notEnviar'), btnCopiar = $n('notCopiar'), avisoModal = $n('notAviso');
	var actual = null;   /* los data-* del botón pulsado */

	function esc(t) { var d = document.createElement('i'); d.textContent = t; return d.innerHTML; }
	function canal() { return $n('notCanalCorreo').checked ? 'CORREO' : 'WHATSAPP'; }
	function avisar(texto) { avisoModal.textContent = texto; avisoModal.hidden = !texto; }
	function dinero(v) { return '$' + Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

	/* El número en formato internacional: 0991234567 → 593991234567; con
	   «+» y prefijo de otro país, se respeta. */
	function numero() {
		var crudo = celular.value.trim(), n = crudo.replace(/\D/g, '');
		if (/^\+/.test(crudo))     { return n.length >= 8 && n.length <= 15 ? n : ''; }
		if (/^09\d{8}$/.test(n))   { return '593' + n.slice(1); }
		if (/^9\d{8}$/.test(n))    { return '593' + n; }
		if (/^5939\d{8}$/.test(n)) { return n; }
		return '';
	}
	function correoValido() { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(correo.value.trim()); }

	/* Destino, ayuda y botón principal según el canal elegido. */
	function refrescar() {
		var wa = canal() === 'WHATSAPP', ok;
		$n('notGrupoWa').hidden = !wa;
		$n('notGrupoCorreo').hidden = wa;
		if (wa) {
			var n = numero();
			ok = !!n;
			$n('notCelularAyuda').innerHTML = !celular.value.trim()
				? '<span class="text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>No hay celular registrado. Escríbalo para continuar.</span>'
				: (n ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Se abrirá WhatsApp con +' + n.replace(/^(593)(\d{2})(\d{3})(\d{4})$/, '$1 $2 $3 $4') + '</span>'
				     : '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Use un celular de 10 dígitos (09…) o el formato internacional (+57…).</span>');
			celular.classList.toggle('is-invalid', !!celular.value.trim() && !n);
			btnEnviar.className = 'btn btn-whatsapp';
			btnEnviar.innerHTML = '<i class="fab fa-whatsapp me-1" aria-hidden="true"></i><span>Abrir WhatsApp</span>';
		} else {
			ok = correoValido();
			$n('notCorreoAyuda').innerHTML = !correo.value.trim()
				? '<span class="text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>No hay correo registrado. Escríbalo para continuar.</span>'
				: (ok ? '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Se enviará desde el sistema a ' + esc(correo.value.trim()) + '</span>'
				      : '<span class="text-danger"><i class="fas fa-times-circle me-1"></i>Correo no válido.</span>');
			correo.classList.toggle('is-invalid', !!correo.value.trim() && !ok);
			btnEnviar.className = 'btn btn-primary';
			btnEnviar.innerHTML = '<i class="fas fa-envelope me-1" aria-hidden="true"></i><span>Enviar correo</span>';
		}
		$n('notResumenWa').textContent = celular.value.trim() || 'sin celular';
		$n('notResumenCorreo').textContent = correo.value.trim() || 'sin correo';
		ok = ok && cuerpo.value.trim() !== '';
		btnEnviar.disabled = !ok;
		return ok;
	}

	/* Delegado en el documento: DataTables vuelve a montar las filas al
	   cambiar de página. */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-notificar]');
		if (!b) { return; }
		actual = b.dataset;
		$n('notNombre').textContent = actual.repre;
		$n('notDetalle').textContent = 'Representante de ' + actual.alumno + ' · ' + CONCEPTO + ' en mora ' + dinero(actual.valor);
		celular.value = actual.celular || '';
		correo.value = actual.correo || '';
		cuerpo.value = PLANTILLA.replace('{CONCEPTO}', CONCEPTO + ' de ' + actual.alumno)
		                        .replace('{VALOR}', Number(actual.valor).toFixed(2));
		/* Por dónde, de entrada: WhatsApp si hay celular; si no, correo si hay. */
		var porCorreo = !numero() && correoValido();
		$n('notCanalWa').checked = !porCorreo;
		$n('notCanalCorreo').checked = porCorreo;
		avisar('');
		refrescar();
		modal.show();
	});

	modalEl.addEventListener('shown.bs.modal', function () { btnEnviar.focus(); });
	[$n('notCanalWa'), $n('notCanalCorreo')].forEach(function (r) { r.addEventListener('change', function () { avisar(''); refrescar(); }); });
	[celular, correo, cuerpo].forEach(function (c) { c.addEventListener('input', refrescar); });

	btnEnviar.addEventListener('click', function () {
		if (!actual || !refrescar()) { return; }
		avisar('');

		if (canal() === 'WHATSAPP') {
			/* Se abre dentro del toque: el teléfono no lo bloquea. */
			window.open('https://wa.me/' + numero() + '?text=' + encodeURIComponent(cuerpo.value.trim()), '_blank', 'noopener');
			modal.hide();
			return;
		}

		var antes = btnEnviar.innerHTML, d = new FormData();
		d.append('modulo_cobranza', 'enviar_correo');
		d.append('vista', VISTA);
		d.append('alumno_id', actual.alumnoId);
		d.append('correo', correo.value.trim());
		d.append('cuerpo', cuerpo.value);
		btnEnviar.disabled = true;
		btnEnviar.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Enviando…';
		fetch(AJAX, { method: 'POST', body: d, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				if (r.icono === 'success') {
					modal.hide();
					Swal.fire({ toast: true, position: 'top-end', timer: 3500, showConfirmButton: false, icon: 'success', title: r.titulo, text: r.texto || '' });
				} else {
					avisar(r.texto || r.titulo);
				}
			})
			.catch(function () { avisar('No fue posible comunicarse con el servidor.'); })
			.finally(function () { btnEnviar.innerHTML = antes; refrescar(); });
	});

	btnCopiar.addEventListener('click', function () {
		var etiqueta = btnCopiar.querySelector('span'), texto = cuerpo.value.trim();
		var listo = function () {
			etiqueta.textContent = '¡Copiado!';
			setTimeout(function () { etiqueta.textContent = 'Copiar mensaje'; }, 1800);
		};
		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(texto).then(listo);
		} else {
			var t = document.createElement('textarea');
			t.value = texto; document.body.appendChild(t); t.select();
			try { document.execCommand('copy'); listo(); } catch (err) {}
			t.remove();
		}
	});
})();
</script>
