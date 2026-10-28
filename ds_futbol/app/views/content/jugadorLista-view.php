<?php
	use app\controllers\jugadorController;
	$insJugador = new jugadorController();

	$equipo_torneoid = ds_id_de_url($url, 1, APP_URL . 'torneoList/');
	$equipo_id       = ds_id_de_url($url, 2, APP_URL . 'torneoList/');

	$equipo = $insJugador->BuscarEquipo($equipo_id)->fetch(PDO::FETCH_ASSOC);
	if (!$equipo) {
		header("Location: " . APP_URL . "equipoList/" . (int)$equipo_torneoid . "/");
		exit();
	}

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$jugadores    = $insJugador->jugadoresDelEquipo((int)$equipo_id);
	$puedeQuitar  = puede('eliminar', 'torneoList');
	$puedeAnular  = puede('eliminar', 'pagosList');
	$urlEquipos   = APP_URL . 'equipoList/' . (int)$equipo['equipo_torneoid'] . '/';

	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Jugadores';
	$extras      = array (0 => 'datatables', 1 => 'swal',);
	require_once "app/views/inc/cabecera.php";
?>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<div class="app-wrapper ds-core">

	<?php require_once "app/views/inc/navbar.php"; ?>
	<?php require_once "app/views/inc/main-sidebar.php"; ?>

	<div class="app-main">
		<div class="app-content-header">
			<div class="container-fluid">
				<div class="row mb-2 g-2 align-items-center">
					<div class="col-sm-7">
						<h4 class="m-0">Jugadores</h4>
						<p class="small text-body-secondary mb-0">
							<i class="fas fa-shield-halved me-1" aria-hidden="true"></i><strong><?php echo $h($equipo['equipo_nombre']); ?></strong>
							· <?php echo $h($equipo['equipo_categoria']); ?> · <?php echo $h($equipo['sede_nombre']); ?>
						</p>
					</div>
					<div class="col-sm-5 d-none d-sm-block">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>torneoList/">Torneos</a></li>
							<li class="breadcrumb-item"><a href="<?php echo $urlEquipos; ?>">Equipos</a></li>
							<li class="breadcrumb-item active" aria-current="page">Jugadores</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">
				<div class="card ds-crud__lista">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-users me-2" aria-hidden="true"></i>Jugadores del equipo
							<span class="badge text-bg-secondary ms-1" id="totalJugadores"><?php echo count($jugadores); ?></span></h3>
						<div class="card-tools d-flex flex-wrap gap-2">
							<?php if (puede_crear('torneoList')): ?>
								<a href="<?php echo APP_URL . 'jugadorNew/' . (int)$equipo['equipo_torneoid'] . '/' . (int)$equipo_id . '/'; ?>" class="btn btn-primary btn-sm"><i class="fas fa-user-plus me-1" aria-hidden="true"></i>Asignar</a>
							<?php endif; ?>
							<a href="<?php echo APP_URL . 'jugadorListaPDF/' . (int)$equipo_id . '/'; ?>" class="btn btn-success btn-sm" target="_blank" rel="noopener"><i class="fas fa-print me-1" aria-hidden="true"></i>Imprimir</a>
						</div>
					</div>
					<div class="card-body">
						<table id="tablaJugadores" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Jugador</th>
									<th>Posición</th>
									<th>Inscripción al torneo</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($jugadores as $j):
								$correo = filter_var(trim((string)$j['repre_correo']), FILTER_VALIDATE_EMAIL) ?: ''; ?>
								<tr data-alumno="<?php echo (int)$j['jugador_alumnoid']; ?>" data-nombre="<?php echo $h($j['nombre']); ?>"
								    data-recibo="<?php echo $h($j['pago_recibo']); ?>" data-valor="<?php echo $j['pago_valor'] !== null ? number_format((float)$j['pago_valor'], 2, '.', '') : ''; ?>"
								    data-repre="<?php echo $h($j['representante']); ?>" data-correo="<?php echo $h($correo); ?>">
									<td class="ds-ficha__principal" data-search="<?php echo $h($j['nombre'] . ' ' . $j['alumno_identificacion'] . ' ' . $j['anio']); ?>">
										<div class="ds-ficha__nombre"><?php echo $h($j['nombre']); ?></div>
										<div class="ds-ficha__meta"><?php echo $h($j['alumno_identificacion']); ?> · <?php echo (int)$j['anio']; ?></div>
									</td>
									<td class="ds-ficha__dato" data-etiqueta="Posición"><?php echo $h($j['posicion']); ?> · <?php echo $h($j['tipo']); ?></td>
									<td class="ds-ficha__estado">
										<?php if ($j['pago_id']): ?>
											<a href="<?php echo APP_URL . 'pagosRecibo/' . (int)$j['pago_id'] . '/'; ?>" class="badge text-bg-success text-decoration-none" title="Ver el recibo">
												<i class="fas fa-check me-1" aria-hidden="true"></i>Pagado · <?php echo $h($j['pago_recibo']); ?>
											</a>
										<?php else: ?>
											<span class="badge text-bg-light border">Sin pagar</span>
										<?php endif; ?>
									</td>
									<td class="ds-ficha__acciones">
										<?php if ($puedeQuitar): ?>
											<div class="ds-ficha__botones">
												<button type="button" class="btn btn-sm btn-danger ds-ficha__primario" data-accion="quitar">
													<i class="fas fa-user-minus me-1" aria-hidden="true"></i>Quitar
												</button>
											</div>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>

				<div class="ds-perfil__pie">
					<a href="<?php echo $urlEquipos; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Regresar</a>
				</div>
			</div>
		</section>
	</div>

	<?php require_once "app/views/inc/footer.php"; ?>
</div>

<?php if ($puedeQuitar): ?>
<!-- =================== Quitar del equipo =================== -->
<div class="modal fade" id="modalQuitar" tabindex="-1" aria-labelledby="quitarTitulo" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="quitarTitulo"><i class="fas fa-user-minus me-2 text-danger" aria-hidden="true"></i>Quitar del equipo</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div data-q="formulario">
					<p class="mb-2">Se quitará a <strong data-q="nombre"></strong> de <strong><?php echo $h($equipo['equipo_nombre']); ?></strong>.</p>
					<?php /* Sólo si tiene el pago de la inscripción del torneo. */ ?>
					<div data-q="pago" hidden>
						<div class="form-check p-3 border rounded bg-body-tertiary">
							<input class="form-check-input ms-0 me-2" type="checkbox" id="anularRecibo" <?php echo $puedeAnular ? 'checked' : 'disabled'; ?>>
							<label class="form-check-label" for="anularRecibo">
								<strong>Se agregó por error:</strong> anular también el recibo <strong data-q="recibo"></strong> (<span data-q="valor"></span>)
								y avisar por correo al representante.
								<span class="d-block small text-body-secondary mt-1" data-q="destino"></span>
							</label>
						</div>
						<p class="small text-body-secondary mt-2 mb-0">
							<?php echo $puedeAnular
								? 'Desmárquelo si sólo lo cambia de equipo: el pago del torneo se mantiene.'
								: 'Su rol no puede anular pagos: el recibo se mantiene. Pida a quien gestiona Pagos que lo anule.'; ?>
						</p>
					</div>
				</div>
				<ul class="ds-jug__pago-resumen list-unstyled mb-0" data-q="resumen" hidden></ul>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-q="cerrar">Cancelar</button>
				<button type="button" class="btn btn-danger" data-q="confirmar"><i class="fas fa-user-minus me-1" aria-hidden="true"></i>Quitar</button>
			</div>
		</div>
	</div>
</div>
<?php endif; ?>

<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
	var AJAX = '<?php echo APP_URL; ?>app/ajax/jugadorAjax.php';
	var EQUIPO = '<?php echo (int)$equipo_id; ?>';

	/* Tabla en escritorio, fichas en el teléfono (futbol.css, .ds-crud). */
	var tabla = new DataTable('#tablaJugadores', {
		autoWidth: false, pageLength: 25, lengthChange: false, order: [],
		columnDefs: [{ targets: 3, orderable: false, searchable: false }],
		layout: { topStart: null, topEnd: 'search', bottomStart: 'info', bottomEnd: { paging: { firstLast: false } } },
		language: {
			emptyTable: 'Este equipo aún no tiene jugadores', info: '_START_ a _END_ de _TOTAL_ jugadores', infoEmpty: 'Sin jugadores',
			infoFiltered: '(de _MAX_)', search: '', searchPlaceholder: 'Filtrar por nombre, cédula o año…',
			zeroRecords: 'Ninguno coincide', paginate: { next: 'Siguiente', previous: 'Anterior' }
		}
	});

	var modalEl = document.getElementById('modalQuitar');
	if (!modalEl) { return; }
	var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
	var $q = function (n) { return modalEl.querySelector('[data-q="' + n + '"]'); };
	var anular = document.getElementById('anularRecibo');
	var actual = null;
	function esc(t) { var s = document.createElement('span'); s.textContent = t; return s.innerHTML; }

	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-accion="quitar"]');
		if (!b) { return; }
		actual = b.closest('tr');
		$q('nombre').textContent = actual.dataset.nombre;
		var conPago = !!actual.dataset.recibo;
		$q('pago').hidden = !conPago;
		if (conPago) {
			$q('recibo').textContent = actual.dataset.recibo;
			$q('valor').textContent = '$' + actual.dataset.valor;
			$q('destino').textContent = actual.dataset.correo
				? 'Se avisará a ' + actual.dataset.repre + ' (' + actual.dataset.correo + ').'
				: 'El representante no tiene correo registrado: no se le podrá avisar por correo.';
		}
		$q('formulario').hidden = false; $q('resumen').hidden = true; $q('resumen').innerHTML = '';
		$q('confirmar').hidden = false; $q('confirmar').disabled = false; $q('cerrar').textContent = 'Cancelar';
		modal.show();
	});

	function paso(icono, color, html) {
		var li = document.createElement('li');
		li.className = 'd-flex gap-2 mb-2';
		li.innerHTML = '<i class="fas ' + icono + ' text-' + color + ' mt-1" aria-hidden="true"></i><span>' + html + '</span>';
		$q('resumen').appendChild(li);
	}

	$q('confirmar').addEventListener('click', function () {
		var boton = this, tr = actual, antes = boton.innerHTML;
		var d = new FormData();
		d.append('modulo_jugador', 'eliminar');
		d.append('equipo_id', EQUIPO);
		d.append('jugador_alumnoid', tr.dataset.alumno);
		d.append('anular_recibo', tr.dataset.recibo && anular && anular.checked && !anular.disabled ? 'S' : 'N');
		boton.disabled = true;
		boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Quitando…';
		fetch(AJAX, { method: 'POST', body: d, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (r) {
				boton.innerHTML = antes;
				if (r.tipo !== 'jugador_eliminado') {
					boton.disabled = false;
					Swal.fire({ icon: 'error', title: r.titulo || 'No se quitó', text: r.texto || '' });
					return;
				}
				tabla.row(tr).remove().draw(false);
				document.getElementById('totalJugadores').textContent = tabla.rows().count();
				$q('formulario').hidden = true; $q('resumen').hidden = false; boton.hidden = true; $q('cerrar').textContent = 'Listo';
				paso('fa-circle-check', 'success', esc(r.alumno) + ' ya no está en el equipo.');
				if (r.anulado) {
					paso('fa-circle-check', 'success', 'Recibo <strong>' + esc(r.recibo) + '</strong> anulado.');
					if (r.correo === 'enviado') { paso('fa-envelope-circle-check', 'success', 'Se avisó por correo a ' + esc(r.destino) + '.'); }
					else if (r.correo === 'sin_correo') { paso('fa-circle-info', 'secondary', 'El representante no tiene correo: avísele por otro medio.'); }
					else { paso('fa-triangle-exclamation', 'warning', 'No se pudo enviar el correo a ' + esc(r.destino) + ': avísele por otro medio.'); }
				}
			})
			.catch(function () {
				boton.disabled = false; boton.innerHTML = antes;
				Swal.fire({ icon: 'error', title: 'Sin conexión', text: 'No fue posible comunicarse con el servidor.' });
			});
	});
});
</script>

</body>
</html>
