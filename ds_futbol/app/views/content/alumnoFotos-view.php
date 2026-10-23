<?php
	use app\controllers\fotoAlumnoController;

	$insFoto = new fotoAlumnoController();

	$busqueda = isset($_GET['q'])      ? $insFoto->limpiarCadena($_GET['q'])      : '';
	$sedeSel  = isset($_GET['sede'])   ? $insFoto->limpiarCadena($_GET['sede'])   : '';
	$estadoF  = isset($_GET['estado']) ? $insFoto->limpiarCadena($_GET['estado']) : '';

	/* Estado del ALUMNO (no de la foto): activos salvo que se pida otra cosa. */
	$alumnoEstado = in_array($_GET['alumno_estado'] ?? '', ['A', 'I', 'T'], true) ? $_GET['alumno_estado'] : 'A';
	$ESTADOS = ['A' => 'Activos', 'I' => 'Inactivos', 'T' => 'Todos'];

	/*
	| El resumen se calcula sobre TODOS los alumnos del alcance, no sobre lo
	| que queda tras filtrar por estado.
	|
	| Si se contara sobre la lista filtrada, elegir «Sin foto» dejaría los
	| contadores diciendo «156 sin foto, 0 con foto», que es cierto sobre lo
	| que se ve y falso sobre la escuela. Los contadores son el diagnóstico y
	| tienen que decir siempre lo mismo.
	*/
	/*
	| LAS FILAS YA NO SE PINTAN AQUI
	|
	| La tabla la sirve fotoAlumnoTablaAjax.php pagina a pagina. Antes se
	| pintaban las 308 filas y la pantalla pesaba 443 KB; DataTables paginaba
	| despues, en el navegador, asi que se pagaba el peso entero para enseñar
	| veinticinco.
	|
	| El RESUMEN si necesita todo el alcance —el estado sale de mirar el
	| disco, no de la base— y por eso listar() se sigue llamando aqui. Se
	| midio: 307 comprobaciones de disco cuestan 5,86 ms. Lo caro nunca fue
	| mirar el disco, sino mandar el HTML.
	*/
	$todos   = $insFoto->listar($busqueda, $sedeSel, '', $alumnoEstado);
	$resumen = $insFoto->resumen($todos);

	$sedes         = $insFoto->sedes();
	$puedeEditar   = puede_editar('alumnoFotos');

	/*
	| Un solo sitio donde un estado se convierte en algo que se ve.
	|
	| Repartir los textos y los colores por la plantilla es cómo se acaba
	| teniendo «Sin foto» en gris en un sitio y «No tiene» en rojo en otro
	| para la misma cosa. Ese sitio pasó a ser el controlador cuando las
	| filas empezaron a armarse también en el endpoint de la tabla: aquí
	| quedaba una copia, y una copia es exactamente el problema que este
	| comentario dice evitar.
	*/
	$PINTA = $insFoto->pinta();
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Fotos de alumnos';
	$extras      = array (0 => 'datatables',);
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
					<div class="col-sm-7"><h4 class="m-0">Fotos de alumnos</h4></div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Fotos de alumnos</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<div class="app-content">
			<div class="container-fluid ds-crud">

				<!-- ==================== El diagnóstico ==================== -->
				<div class="row">
					<?php
					$tarjetas = [
						['Alumnos' . ['A' => ' activos', 'I' => ' inactivos', 'T' => ''][$alumnoEstado], $resumen['TOTAL'],                             'primary',  'fa-users',              ''],
						['Con foto',         $resumen[fotoAlumnoController::OK],             'success',  'fa-circle-check',       fotoAlumnoController::OK],
						['Sin foto',         $resumen[fotoAlumnoController::SIN],            'secondary','fa-user',               fotoAlumnoController::SIN],
						['Archivo ausente',  $resumen[fotoAlumnoController::AUSENTE],         'danger',   'fa-triangle-exclamation', fotoAlumnoController::AUSENTE],
					];
					foreach ($tarjetas as [$titulo, $valor, $color, $icono, $filtro]):
						/* Cada tarjeta filtra la tabla: el número lleva a los
						   alumnos que lo componen, que es lo que se quiere
						   hacer justo después de leerlo. */
						$destino = $filtro === ''
							? '?' . http_build_query(array_filter(['q' => $busqueda, 'sede' => $sedeSel, 'alumno_estado' => $alumnoEstado]))
							: '?' . http_build_query(array_filter(['q' => $busqueda, 'sede' => $sedeSel, 'alumno_estado' => $alumnoEstado, 'estado' => $filtro]));
					?>
						<div class="col-6 col-lg-3">
							<a href="<?php echo $destino; ?>" class="text-decoration-none">
								<div class="small-box text-bg-<?php echo $color; ?>
								            <?php echo ($estadoF === $filtro && $filtro !== '') ? 'shadow-lg' : ''; ?>">
									<div class="inner">
										<h3><?php echo (int)$valor; ?></h3>
										<p class="mb-0"><?php echo $titulo; ?></p>
									</div>
									<i class="small-box-icon fas <?php echo $icono; ?>"></i>
								</div>
							</a>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ($resumen[fotoAlumnoController::AUSENTE] > 0): ?>
					<!--
					| Este aviso es la razón de ser de la pantalla.
					|
					| Un archivo ausente no se nota en ningún otro sitio: media_url()
					| devuelve la foto genérica y el alumno se ve igual que uno que
					| nunca tuvo foto. Se descubre al imprimir un carné.
					-->
					<div class="callout" style="border-left-color:var(--bs-danger);">
						<h5 class="mb-1">
							<i class="fas fa-triangle-exclamation text-danger me-1"></i>
							<?php echo (int)$resumen[fotoAlumnoController::AUSENTE]; ?>
							ficha<?php echo $resumen[fotoAlumnoController::AUSENTE] === 1 ? '' : 's'; ?>
							con foto registrada pero sin archivo
						</h5>
						<p class="mb-0 small">
							La base guarda el <strong>nombre</strong> de la foto, no la foto. Estas fichas
							apuntan a un archivo que ya no está en el servidor —un respaldo restaurado sin
							las imágenes, una migración a medias, una limpieza de la carpeta—. En el resto
							del sistema se ven como si no tuvieran foto, y el carné sale con la imagen
							genérica. Vuelva a subirlas.
						</p>
					</div>
				<?php endif; ?>

				<!-- ==================== Criterios de búsqueda ==================== -->
				<form method="GET" autocomplete="off" class="card ds-alumnos__filtros">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-12 col-lg-4 col-xl">
								<label for="q" class="form-label">Buscar</label>
								<input type="search" class="form-control" id="q" name="q"
								       value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>"
								       placeholder="Cédula, nombre o apellido">
							</div>
							<div class="col-6 col-lg-4 col-xl-2">
								<label for="alumno_estado" class="form-label">Estado</label>
								<select class="form-select" id="alumno_estado" name="alumno_estado">
									<?php foreach ($ESTADOS as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>" <?php echo $alumnoEstado === $valor ? 'selected' : ''; ?>><?php echo $texto; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-lg-4 col-xl-2">
								<label for="sede" class="form-label">Sede</label>
								<select class="form-select" id="sede" name="sede">
									<option value="">Todas</option>
									<?php foreach ($sedes as $s): ?>
										<option value="<?php echo (int)$s['sede_id']; ?>"
											<?php echo ((string)$sedeSel === (string)$s['sede_id']) ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars(html_entity_decode((string)$s['sede_nombre'], ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-12 col-sm-6 col-lg-4 col-xl-2">
								<label for="estado" class="form-label">Foto</label>
								<select class="form-select" id="estado" name="estado">
									<option value="">Todas</option>
									<?php foreach ($PINTA as $clave => $p): ?>
										<option value="<?php echo $clave; ?>"
											<?php echo $estadoF === $clave ? 'selected' : ''; ?>>
											<?php echo $p['etiqueta']; ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-12 col-sm-6 col-lg-4 col-xl-auto d-flex align-items-end">
								<div class="w-100">
									<?php echo ds_boton_buscar(); ?>
								</div>
							</div>
						</div>
					</div>
				</form>

				<!-- ==================== Resultado de la búsqueda ==================== -->
				<div class="card ds-crud__lista" id="fotos-resultado">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-camera me-2" aria-hidden="true"></i>Resultado de la búsqueda
							<?php
							/*
							| $filas ya no existe: la tabla la sirve el endpoint. El
							| recuento se deriva de $todos, que sigue aquí para el
							| resumen, aplicándole el filtro de estado igual que hace
							| listar(). Así este número y el de las tarjetas salen del
							| mismo conjunto y no pueden discrepar.
							*/
							$cuantos = $estadoF !== ''
								? count(array_filter($todos, static fn($f) => $f['foto_estado'] === $estadoF))
								: count($todos);
							?>
							<span class="badge text-bg-secondary ms-1" title="<?php echo $ESTADOS[$alumnoEstado]; ?>"><?php echo $cuantos; ?></span>
							<?php if ($estadoF !== ''): ?>
								<span class="badge text-bg-<?php echo $PINTA[$estadoF]['color']; ?> ms-1">
									<?php echo $PINTA[$estadoF]['etiqueta']; ?>
								</span>
							<?php endif; ?>
						</h3>
						<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
					</div>
					<div class="card-body">
							<table class="table table-hover align-middle ds-crud__tabla" id="tablaFotos" style="width:100%">
								<thead>
									<tr>
										<th>Alumno</th>
										<th class="ds-col-opcional">Sede</th>
										<th>Fecha de ingreso</th>
										<th>Foto</th>
										<?php if ($puedeEditar): ?><th class="text-end"><span class="visually-hidden">Acciones</span></th><?php endif; ?>
									</tr>
								</thead>
								<?php
								/*
								| El cuerpo lo rellena DataTables desde
								| fotoAlumnoTablaAjax.php, pagina a pagina. Antes se
								| pintaban aqui las 308 filas: 443 KB para enseñar
								| veinticinco.
								*/
								?>
								<tbody>
								</tbody>
							</table>
					</div>
				</div>

			</div>
		</div>
	</div>

	<?php require_once "app/views/inc/footer.php"; ?>
</div>

<?php if ($puedeEditar): ?>
<!-- ==================== Subir una foto ==================== -->
<div class="modal fade" id="modalFoto" tabindex="-1" aria-labelledby="modalFotoTitulo" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered">
		<form class="FormularioAjax modal-content" method="POST"
		      action="<?php echo APP_URL; ?>app/ajax/fotoAlumnoAjax.php"
		      enctype="multipart/form-data" autocomplete="off">
			<input type="hidden" name="modulo_fotoalumno" value="guardarFoto">
			<input type="hidden" name="alumno_id" id="foto_alumno_id" value="">
			<?php echo csrf_campo(); ?>

			<div class="modal-header">
				<h5 class="modal-title" id="modalFotoTitulo">Subir foto</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>
			<div class="modal-body">
				<p class="text-muted small" id="foto_alumno_nombre"></p>

				<label for="alumno_foto" class="form-label">Fotografía</label>
				<input type="file" class="form-control" id="alumno_foto" name="alumno_foto"
				       accept="image/jpeg,image/png" required>
				<div class="form-text">
					JPG o PNG, hasta 4 MB. La imagen se reduce a 800×600 al guardarla.
				</div>

				<img id="vistaPrevia" alt="" class="mt-3 rounded d-none"
				     style="max-height:220px; max-width:100%; object-fit:contain;">

				<!--
				| Recordatorio en el sitio donde se toma la decisión, no en un
				| manual que nadie abre. La foto de un menor sólo puede subirse
				| con el consentimiento de imagen de su representante.
				-->
				<div class="callout mt-3 mb-0 py-2" style="border-left-color:var(--bs-warning);">
					<span class="small">
						<i class="fas fa-shield-halved me-1"></i>
						Suba la foto sólo si el representante otorgó el consentimiento de
						<strong>uso de imagen</strong>. Puede revisarlo en
						<a href="<?php echo APP_URL; ?>consentimientoList/">Consentimientos LOPDP</a>.
					</span>
				</div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
				<button type="submit" class="btn btn-sm btn-primary">
					<i class="fas fa-upload me-1"></i>Guardar
				</button>
			</div>
		</form>
	</div>
</div>
<?php endif; ?>

<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
<?php /* ajax.js es quien envía los FormularioAjax; sin él el modal no guarda nada. */ ?>
<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

	/*
	| «Mostrar N» elige cuántos alumnos por página (10 a 100) y se recuerda.
	| No hay «Todos» a propósito: con cientos de filas a la vez el navegador
	| pediría todas las miniaturas de golpe, y cada una entra por
	| app/media.php, que arranca la sesión para servirla.
	|
	| Sin Responsive: en el teléfono cada alumno es una ficha (futbol.css,
	| .ds-crud); className y data-etiqueta dan a cada celda su papel.
	*/
	if (document.getElementById('tablaFotos')) {
		var CLAVE = 'ds-fotos-por-pagina', LARGOS = [10, 25, 50, 100], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}
		var etiqueta = function (texto) { return function (td) { td.setAttribute('data-etiqueta', texto); }; };
		var columnas = [
			{ targets: 0, className: 'ds-ficha__principal' },
			{ targets: 1, className: 'ds-ficha__dato ds-col-opcional', createdCell: etiqueta('Sede') },
			{ targets: 2, className: 'ds-ficha__dato', createdCell: etiqueta('Fecha de ingreso') },
			{ targets: 3, className: 'ds-ficha__dato', createdCell: etiqueta('Foto') }
		];
		if (document.querySelectorAll('#tablaFotos thead th').length > 4) {
			columnas.push({ targets: 4, className: 'ds-tabla-acciones ds-ficha__acciones', orderable: false });
		}

		var tabla = new DataTable('#tablaFotos', {
			/*
			| serverSide: la pagina llega armada del servidor.
			|
			| Antes se enviaban las 308 filas y DataTables paginaba aqui: 443 KB
			| para enseñar veinticinco. Ahora viajan veinticinco.
			|
			| La busqueda NO se queda corta por ello: la resuelve el endpoint
			| sobre todo el alcance, asi que sigue encontrando a cualquier
			| alumno y no solo a los de la pagina visible.
			|
			| Los filtros de la pantalla —buscador, sede y estado— se mandan en
			| cada peticion; si no, el resumen de arriba y la tabla dirian cosas
			| distintas sobre los mismos alumnos.
			|
			| El orden lo resuelve también el endpoint, sobre todo el alcance
			| (alumno, sede, fecha de ingreso y foto).
			*/
			serverSide: true,
			processing: true,
			ajax: {
				url: '<?php echo APP_URL; ?>app/ajax/fotoAlumnoTablaAjax.php',
				type: 'GET',
				data: function (d) {
					d.q             = <?php echo json_encode($busqueda, JSON_UNESCAPED_UNICODE); ?>;
					d.sede          = <?php echo json_encode($sedeSel,  JSON_UNESCAPED_UNICODE); ?>;
					d.estado        = <?php echo json_encode($estadoF,  JSON_UNESCAPED_UNICODE); ?>;
					d.alumno_estado = <?php echo json_encode($alumnoEstado); ?>;
				}
			},
			order: [[0, 'asc']],
			pageLength: largo,
			lengthMenu: LARGOS,
			autoWidth: false,
			columnDefs: columnas,
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable:   'No hay alumnos con estos criterios',
				info:         '_START_ a _END_ de _TOTAL_ alumnos',
				infoEmpty:    'Sin alumnos',
				infoFiltered: '(de _MAX_)',
				lengthMenu:   'Mostrar _MENU_',
				processing:   'Cargando…',
				/* La búsqueda la resuelve el servidor sobre todos los
				   alumnos, no sólo sobre la página que se ve. */
				search:       '',
				searchPlaceholder: 'Filtrar por nombre o cédula…',
				zeroRecords:  'Ningún alumno coincide con el filtro',
				paginate: { next: 'Siguiente', previous: 'Anterior' },
				aria: {
					sortAscending: ': ordenar de forma ascendente',
					sortDescending: ': ordenar de forma descendente'
				}
			}
		});
		tabla.on('length', function (e, s, nuevo) {
			try { localStorage.setItem(CLAVE, String(nuevo)); } catch (err) {}
		});
	}

	var modalEl = document.getElementById('modalFoto');
	if (!modalEl) { return; }

	var modal = new bootstrap.Modal(modalEl);

	/*
	| Delegado en la tabla: las filas llegan del endpoint DESPUÉS de este
	| código, en cada página. Antes se enlazaba con querySelectorAll al
	| cargar, cuando aún no había ningún botón, y «Subir» no abría nada.
	*/
	document.getElementById('tablaFotos').addEventListener('click', function (ev) {
		var b = ev.target.closest('.btn-subir');
		if (!b) { return; }
		document.getElementById('foto_alumno_id').value = b.dataset.id;
		document.getElementById('foto_alumno_nombre').textContent = b.dataset.nombre;
		document.getElementById('modalFotoTitulo').textContent = 'Foto de ' + b.dataset.nombre;

		/* El formulario se limpia al abrir: sin esto, abrir el modal de
		   otro alumno conserva el archivo elegido para el anterior y se
		   sube la foto equivocada sin que nada lo delate. */
		document.getElementById('alumno_foto').value = '';
		var prev = document.getElementById('vistaPrevia');
		prev.classList.add('d-none');
		prev.removeAttribute('src');

		modal.show();
	});

	/* Ver la foto antes de guardarla: es la única forma de darse cuenta de
	   que se eligió el archivo equivocado ANTES de que reemplace al bueno. */
	document.getElementById('alumno_foto').addEventListener('change', function () {
		var f = this.files && this.files[0];
		var prev = document.getElementById('vistaPrevia');
		if (!f) { prev.classList.add('d-none'); return; }

		var url = URL.createObjectURL(f);
		prev.src = url;
		prev.classList.remove('d-none');
		prev.addEventListener('load', function () { URL.revokeObjectURL(url); }, { once: true });
	});
});
</script>

</body>
</html>
