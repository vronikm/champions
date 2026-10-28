<?php
	use app\controllers\jugadorController;
	$insJugador = new jugadorController();

	/* Ambos son obligatorios: sin equipo no hay a quién inscribir. */
	$equipo_torneoid = ds_id_de_url($url, 1, APP_URL . 'torneoList/');
	$equipo_id       = ds_id_de_url($url, 2, APP_URL . 'torneoList/');

	$equipo = $insJugador->BuscarEquipo($equipo_id)->fetch(PDO::FETCH_ASSOC);
	if (!$equipo) {
		header("Location: " . APP_URL . "equipoList/" . (int)$equipo_torneoid . "/");
		exit();
	}
	/* El torneo de la dirección no es el del equipo: a la dirección buena
	   (el pago se liga al torneo del equipo, nunca al de la URL). */
	if ((int)$equipo['equipo_torneoid'] !== (int)$equipo_torneoid) {
		header("Location: " . APP_URL . "jugadorNew/" . (int)$equipo['equipo_torneoid'] . "/" . (int)$equipo_id . "/");
		exit();
	}

	$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
	$sedes = $insJugador->sedesDeLaBusqueda();

	/*----------  Búsqueda (GET: sobrevive a recargar y a volver)  ----------
	   Al abrir, ya llena con lo del equipo: su sede (si es una de las de la
	   sesión) y los años que diga su categoría («2014-2015» → 2014, 2015). */
	$buscado = isset($_GET['buscar']);
	$campo   = static fn(string $n) => trim((string)($_GET[$n] ?? ''));
	if ($buscado) {
		$filtro = ['anios_texto' => $campo('anios'), 'sede' => (int)$campo('sede')];
	} else {
		preg_match_all('/\b(19|20)\d{2}\b/', html_entity_decode((string)$equipo['equipo_categoria']), $m);
		$aniosCategoria = $m[0] ? jugadorController::aniosDelFiltro(count($m[0]) === 2 ? $m[0][0] . '-' . $m[0][1] : implode(',', $m[0])) : [];
		$filtro = ['anios_texto' => implode(', ', $aniosCategoria),
		           'sede' => isset($sedes[(int)$equipo['equipo_sedeid']]) ? (int)$equipo['equipo_sedeid'] : 0];
	}
	if (!isset($sedes[$filtro['sede']])) { $filtro['sede'] = 0; }
	$filtro['anios'] = jugadorController::aniosDelFiltro($filtro['anios_texto']);
	$aniosMal = $filtro['anios_texto'] !== '' && !$filtro['anios'];

	$alumnos = $insJugador->buscarAlumnosParaEquipo($filtro, $equipo);

	$puedeAgregar = puede('crear', 'torneoList');
	$puedePagar   = $puedeAgregar && puede('crear', 'pagosList');
	$valorEquipo  = $equipo['equipo_valorinscripcion'] !== null ? number_format((float)$equipo['equipo_valorinscripcion'], 2, '.', '') : '';
	$posiciones   = $insJugador->listarCatalogoPosicion();
	$tipos        = $insJugador->listarCatalogoTipo();
	$formas       = $puedePagar ? $insJugador->opcionesFormaPagoCobro() : [];
	$urlEquipos   = APP_URL . 'equipoList/' . (int)$equipo_torneoid . '/';
	$urlJugadores = APP_URL . 'jugadorLista/' . (int)$equipo_torneoid . '/' . (int)$equipo_id . '/';
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Asignar jugadores';
	$extras      = array (0 => 'datatables', 1 => 'swal',);
	$cabeceraExtra = <<<'CSS'
	<style>
		.ds-jug__equipo { display: flex; flex-wrap: wrap; align-items: center; gap: .35rem .6rem; }
		.ds-jug__selects { display: grid; grid-template-columns: 1fr 1fr; gap: .4rem; min-width: 15rem; }
		.ds-jug__selects .form-select { font-size: .85rem; }
		.ds-jug__pago-resumen { display: grid; gap: .45rem; margin: 0; padding: 0; list-style: none; }
		.ds-jug__pago-resumen li { display: flex; gap: .55rem; align-items: flex-start; }
		.ds-jug__pago-resumen .fas { margin-top: .2rem; width: 1rem; text-align: center; }
		@media (max-width: 991.98px) {
			.ds-jug__selects { min-width: 0; }
		}
	</style>
CSS;
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
						<h4 class="m-0">Asignar jugadores</h4>
						<div class="ds-jug__equipo small text-body-secondary mt-1">
							<span><i class="fas fa-shield-halved me-1" aria-hidden="true"></i><strong><?php echo $h($equipo['equipo_nombre']); ?></strong> · <?php echo $h($equipo['equipo_categoria']); ?></span>
							<span><i class="fas fa-location-dot me-1" aria-hidden="true"></i><?php echo $h($equipo['sede_nombre']); ?></span>
							<span class="badge <?php echo $valorEquipo !== '' ? 'text-bg-primary' : 'text-bg-secondary'; ?>">
								Inscripción <?php echo $valorEquipo !== '' ? '$' . $valorEquipo : 'sin definir'; ?>
							</span>
						</div>
					</div>
					<div class="col-sm-5 d-none d-sm-block">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>torneoList/">Torneos</a></li>
							<li class="breadcrumb-item"><a href="<?php echo $urlEquipos; ?>">Equipos</a></li>
							<li class="breadcrumb-item active" aria-current="page">Asignar jugadores</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<!-- =================== Búsqueda =================== -->
				<form class="card ds-crud__form" method="GET" action="<?php echo APP_URL . 'jugadorNew/' . (int)$equipo_torneoid . '/' . (int)$equipo_id . '/'; ?>" autocomplete="off" id="filtroJugadores">
					<input type="hidden" name="buscar" value="1">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-magnifying-glass me-2" aria-hidden="true"></i>Búsqueda de alumnos</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-6 col-md-5 col-lg">
								<label for="f_anios" class="form-label">Año</label>
								<input type="text" class="form-control<?php echo $aniosMal ? ' is-invalid' : ''; ?>" id="f_anios" name="anios" inputmode="numeric"
								       placeholder="2014, 2015" value="<?php echo $h($filtro['anios_texto']); ?>" aria-describedby="f_anios_ayuda">
								<div class="form-text" id="f_anios_ayuda"><?php echo $aniosMal ? 'No se entendió el año: use 2014, 2015 o 2014-2016.' : 'Varios, separados por coma; o un rango: 2014-2016.'; ?></div>
							</div>
							<div class="col-6 col-md-4 col-lg">
								<label for="f_sede" class="form-label">Sede</label>
								<select class="form-select" id="f_sede" name="sede" <?php echo count($sedes) <= 1 ? 'disabled' : ''; ?>>
									<?php if (count($sedes) > 1): ?><option value="0" <?php echo $filtro['sede'] === 0 ? 'selected' : ''; ?>><?php echo sedes_de_la_sesion() === null ? 'Todas las sedes' : 'Todas mis sedes'; ?></option><?php endif; ?>
									<?php foreach ($sedes as $id => $nombre): ?>
										<option value="<?php echo (int)$id; ?>" <?php echo $filtro['sede'] === (int)$id ? 'selected' : ''; ?>><?php echo $h($nombre); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-12 col-md-3 col-lg-auto d-flex align-items-end">
								<button type="submit" class="btn btn-primary w-100"><i class="fas fa-magnifying-glass me-1" aria-hidden="true"></i>Buscar</button>
							</div>
						</div>
						<p class="small text-body-secondary mt-2 mb-0">Sólo alumnos <strong>activos</strong> que no juegan ya en otro equipo de esta categoría del torneo.</p>
					</div>
				</form>

				<!-- =================== Resultado =================== -->
				<div class="card ds-crud__lista" id="resultado">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-children me-2" aria-hidden="true"></i>Alumnos
							<span class="badge text-bg-secondary ms-1" id="totalAlumnos"><?php echo count($alumnos); ?></span></h3>
					</div>
					<div class="card-body">
						<table id="tablaAlumnos" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Alumno</th>
									<th>Inscripción al torneo</th>
									<th>Posición y tipo</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($alumnos as $a):
								$anio = substr((string)$a['alumno_fechanacimiento'], 0, 4); ?>
								<tr data-alumno="<?php echo (int)$a['alumno_id']; ?>" data-nombre="<?php echo $h($a['nombre']); ?>"
								    data-repre="<?php echo $h($a['representante']); ?>" data-correo="<?php echo $h(filter_var(trim((string)$a['repre_correo']), FILTER_VALIDATE_EMAIL) ?: ''); ?>">
									<td class="ds-ficha__principal" data-search="<?php echo $h($a['nombre'] . ' ' . $a['alumno_identificacion'] . ' ' . $anio . ' ' . $a['sede_nombre']); ?>">
										<div class="ds-ficha__nombre"><?php echo $h($a['nombre']); ?></div>
										<div class="ds-ficha__meta"><?php echo $h($a['alumno_identificacion']); ?> · <?php echo $h($anio); ?> · <?php echo $h($a['sede_nombre']); ?></div>
									</td>
									<td class="ds-ficha__estado" data-p="pago">
										<?php if ($a['pago_id']): ?>
											<a href="<?php echo APP_URL . 'pagosRecibo/' . (int)$a['pago_id'] . '/'; ?>" class="badge text-bg-success text-decoration-none" title="Ver el recibo">
												<i class="fas fa-check me-1" aria-hidden="true"></i>Pagado · <?php echo $h($a['pago_recibo']); ?>
											</a>
										<?php else: ?>
											<span class="badge text-bg-light border">Sin pagar</span>
										<?php endif; ?>
									</td>
									<td class="ds-ficha__dato">
										<div class="ds-jug__selects">
											<?php /* «Sin definir» primero: si no se elige, se guarda vacío y así se muestra. */ ?>
											<select class="form-select form-select-sm" data-c="posicion" aria-label="Posición de juego"><option value="" selected>Sin definir</option><?php echo $posiciones; ?></select>
											<select class="form-select form-select-sm" data-c="tipo" aria-label="Tipo"><option value="" selected>Sin definir</option><?php echo $tipos; ?></select>
										</div>
									</td>
									<td class="ds-ficha__acciones">
										<?php if ($puedeAgregar): ?>
										<div class="ds-ficha__botones">
											<?php if ($puedePagar && !$a['pago_id']): ?>
												<button type="button" class="btn btn-sm btn-success ds-ficha__primario" data-accion="pagar">
													<i class="fas fa-cash-register me-1" aria-hidden="true"></i>Pagar y agregar
												</button>
												<button type="button" class="btn btn-sm btn-outline-primary" data-accion="agregar" title="Agregar sin registrar el pago" aria-label="Agregar sin registrar el pago">
													<i class="fas fa-user-plus" aria-hidden="true"></i>
												</button>
											<?php else: ?>
												<button type="button" class="btn btn-sm btn-primary ds-ficha__primario" data-accion="agregar">
													<i class="fas fa-user-plus me-1" aria-hidden="true"></i>Agregar
												</button>
											<?php endif; ?>
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
					<a href="<?php echo $urlJugadores; ?>" class="btn btn-secondary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Regresar</a>
				</div>
			</div>
		</section>
	</div>

	<?php require_once "app/views/inc/footer.php"; ?>
</div>

<?php if ($puedePagar): ?>
<!-- =================== Pagar y agregar =================== -->
<div class="modal fade" id="modalPagar" tabindex="-1" aria-labelledby="pagarTitulo" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
		<form class="modal-content" id="formPagar" novalidate>
			<div class="modal-header">
				<h5 class="modal-title" id="pagarTitulo"><i class="fas fa-cash-register me-2 text-success" aria-hidden="true"></i>Pagar inscripción</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<div data-p="formulario">
					<p class="mb-1"><strong data-p="alumno"></strong></p>
					<p class="small text-body-secondary mb-3">
						<?php echo $h($equipo['equipo_nombre']); ?> · <?php echo $h($equipo['equipo_categoria']); ?><br>
						<span data-p="destino"></span>
					</p>
					<div class="row g-3">
						<div class="col-6">
							<label for="p_valor" class="form-label">Valor</label>
							<div class="input-group">
								<span class="input-group-text">$</span>
								<input type="text" class="form-control" id="p_valor" name="valor" inputmode="decimal" required value="<?php echo $h($valorEquipo); ?>" placeholder="0.00">
							</div>
						</div>
						<div class="col-6">
							<label for="p_fecha" class="form-label">Fecha</label>
							<input type="date" class="form-control" id="p_fecha" name="fecha" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
						</div>
						<div class="col-12">
							<label for="p_forma" class="form-label">Forma de pago</label>
							<select class="form-select" id="p_forma" name="formapago" required>
								<option value="">Seleccione</option>
								<?php foreach ($formas as $valor => $texto): ?>
									<option value="<?php echo $h($valor); ?>"<?php echo $valor === 'FEF' ? ' selected' : ''; ?>><?php echo $h($texto); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<p class="small text-body-secondary mt-3 mb-0">
						Se registra en Pagos como <strong>Campeonato</strong> del torneo, se agrega al equipo y el recibo se envía por correo al representante.
					</p>
					<div class="alert alert-warning mt-3 mb-0 py-2" data-p="aviso" role="alert" hidden></div>
				</div>
				<?php /* El resultado de cada paso: pago, equipo y correo. */ ?>
				<ul class="ds-jug__pago-resumen" data-p="resumen" hidden></ul>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-p="cerrar">Cancelar</button>
				<a href="#" class="btn btn-outline-primary" data-p="recibo" target="_blank" rel="noopener" hidden><i class="fas fa-receipt me-1" aria-hidden="true"></i>Ver recibo</a>
				<button type="submit" class="btn btn-success" data-p="pagar"><i class="fas fa-cash-register me-1" aria-hidden="true"></i>Pagar y agregar</button>
			</div>
		</form>
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
	var BASE = '<?php echo APP_URL; ?>';
	var EQUIPO = '<?php echo (int)$equipo_id; ?>';

	/* Tabla en escritorio, fichas en el teléfono (futbol.css, .ds-crud). */
	var tabla = new DataTable('#tablaAlumnos', {
		autoWidth: false, pageLength: 25, lengthChange: false, order: [],
		columnDefs: [{ targets: [2, 3], orderable: false, searchable: false }],
		layout: { topStart: null, topEnd: 'search', bottomStart: 'info', bottomEnd: { paging: { firstLast: false } } },
		language: {
			emptyTable: 'Ningún alumno activo coincide con la búsqueda',
			info: '_START_ a _END_ de _TOTAL_ alumnos', infoEmpty: 'Sin alumnos', infoFiltered: '(de _MAX_)',
			search: '', searchPlaceholder: 'Filtrar por nombre, cédula, año o sede…',
			zeroRecords: 'Ninguno coincide', paginate: { next: 'Siguiente', previous: 'Anterior' }
		}
	});

	function aviso(icono, titulo, texto) {
		Swal.fire({ toast: true, position: 'top-end', timer: 3500, showConfirmButton: false, icon: icono, title: titulo, text: texto || '' });
	}
	function enviar(datos) {
		var d = new FormData();
		Object.keys(datos).forEach(function (k) { d.append(k, datos[k]); });
		return fetch(AJAX, { method: 'POST', body: d, credentials: 'same-origin' }).then(function (r) { return r.json(); });
	}
	function quitarFila(tr) {
		tabla.row(tr).remove().draw(false);
		document.getElementById('totalAlumnos').textContent = tabla.rows().count();
	}
	function seleccion(tr, c) { return tr.querySelector('[data-c="' + c + '"]').value; }

	/* Agregar sin registrar el pago. */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-accion="agregar"]');
		if (!b) { return; }
		var tr = b.closest('tr');
		b.disabled = true;
		enviar({ modulo_jugador: 'agregar', equipo_id: EQUIPO, alumno_id: tr.dataset.alumno, posicion: seleccion(tr, 'posicion'), tipo: seleccion(tr, 'tipo') })
			.then(function (r) {
				if (r.tipo === 'Toast_Success') { aviso('success', 'Agregado al equipo', tr.dataset.nombre); quitarFila(tr); }
				else { b.disabled = false; Swal.fire({ icon: 'error', title: r.titulo || 'No se agregó', text: r.texto || '' }); }
			})
			.catch(function () { b.disabled = false; Swal.fire({ icon: 'error', title: 'Sin conexión', text: 'No fue posible comunicarse con el servidor.' }); });
	});

	/*----------  Pagar y agregar  ----------*/
	var modalEl = document.getElementById('modalPagar');
	if (!modalEl) { return; }
	var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
	var form = document.getElementById('formPagar');
	var $p = function (n) { return modalEl.querySelector('[data-p="' + n + '"]'); };
	var actual = null;

	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-accion="pagar"]');
		if (!b) { return; }
		actual = b.closest('tr');
		$p('alumno').textContent = actual.dataset.nombre;
		$p('destino').textContent = actual.dataset.correo
			? 'El recibo se enviará a ' + actual.dataset.repre + ' (' + actual.dataset.correo + ').'
			: 'El representante no tiene correo: el recibo se podrá imprimir o compartir desde Pagos.';
		$p('formulario').hidden = false; $p('resumen').hidden = true; $p('aviso').hidden = true;
		$p('recibo').hidden = true; $p('pagar').hidden = false; $p('pagar').disabled = false;
		$p('cerrar').textContent = 'Cancelar';
		modal.show();
	});
	modalEl.addEventListener('shown.bs.modal', function () { if (!$p('formulario').hidden) { document.getElementById('p_valor').focus(); } });

	function paso(icono, color, html) {
		var li = document.createElement('li');
		li.innerHTML = '<i class="fas ' + icono + ' text-' + color + '" aria-hidden="true"></i><span>' + html + '</span>';
		$p('resumen').appendChild(li);
		return li;
	}
	function esc(t) { var s = document.createElement('span'); s.textContent = t; return s.innerHTML; }

	form.addEventListener('submit', function (e) {
		e.preventDefault();
		var valor = document.getElementById('p_valor').value.trim().replace(',', '.');
		var forma = document.getElementById('p_forma').value, fecha = document.getElementById('p_fecha').value;
		var falta = !/^\d{1,6}(\.\d{1,2})?$/.test(valor) || parseFloat(valor) <= 0 ? 'Escriba el valor (por ejemplo 25 o 25.50).'
			: (!forma ? 'Elija la forma de pago.' : (!fecha ? 'Elija la fecha.' : ''));
		if (falta) { $p('aviso').textContent = falta; $p('aviso').hidden = false; return; }
		var boton = $p('pagar'), antes = boton.innerHTML, tr = actual;
		boton.disabled = true;
		boton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Registrando…';
		enviar({ modulo_jugador: 'registrar_pago_inscripcion', equipo_id: EQUIPO, alumno_id: tr.dataset.alumno,
		         posicion: seleccion(tr, 'posicion'), tipo: seleccion(tr, 'tipo'), valor: valor, formapago: forma, fecha: fecha })
			.then(function (r) {
				if (r.tipo !== 'pago_inscripcion') {
					boton.disabled = false; boton.innerHTML = antes;
					$p('aviso').textContent = r.texto || 'No se pudo registrar.'; $p('aviso').hidden = false;
					return;
				}
				/* Hecho: el pago y el equipo. Queda el correo. */
				$p('formulario').hidden = true; $p('resumen').innerHTML = ''; $p('resumen').hidden = false;
				boton.hidden = true; boton.innerHTML = antes; $p('cerrar').textContent = 'Listo';
				$p('recibo').href = BASE + 'pagosRecibo/' + r.pago_id + '/'; $p('recibo').hidden = false;
				paso('fa-circle-check', 'success', 'Pago registrado: <strong>$' + esc(r.valor) + '</strong>, recibo <strong>' + esc(r.recibo) + '</strong>.');
				paso('fa-circle-check', 'success', esc(r.alumno) + ' quedó en el equipo.');
				quitarFila(tr);
				if (!tr.dataset.correo) { paso('fa-circle-info', 'secondary', 'Sin correo del representante: el recibo no se envió.'); return; }
				var correo = paso('fa-spinner fa-spin', 'secondary', 'Enviando el recibo a ' + esc(tr.dataset.correo) + '…');
				/* El mismo envío que «Enviar por correo» del recibo: termina en
				   pagosRecibo/{pago}/1/ si salió, /0/ si no. */
				fetch(BASE + 'pagosReciboEnvio/' + r.pago_id + '/', { credentials: 'same-origin' })
					.then(function (resp) { return /\/1\/?$/.test(resp.url); }, function () { return false; })
					.then(function (ok) {
						correo.innerHTML = ok
							? '<i class="fas fa-envelope-circle-check text-success" aria-hidden="true"></i><span>Recibo enviado a ' + esc(tr.dataset.correo) + '.</span>'
							: '<i class="fas fa-triangle-exclamation text-warning" aria-hidden="true"></i><span>No se pudo enviar el recibo por correo. Ábralo con «Ver recibo» para reenviarlo o compartirlo.</span>';
					});
			})
			.catch(function () {
				boton.disabled = false; boton.innerHTML = antes;
				$p('aviso').textContent = 'No fue posible comunicarse con el servidor.'; $p('aviso').hidden = false;
			});
	});
});
</script>

</body>
</html>
