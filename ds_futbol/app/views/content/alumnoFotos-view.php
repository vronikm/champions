<?php
	use app\controllers\fotoAlumnoController;

	$insFoto = new fotoAlumnoController();

	$busqueda = isset($_GET['q'])      ? $insFoto->limpiarCadena($_GET['q'])      : '';
	$sedeSel  = isset($_GET['sede'])   ? $insFoto->limpiarCadena($_GET['sede'])   : '';
	$estadoF  = isset($_GET['estado']) ? $insFoto->limpiarCadena($_GET['estado']) : '';

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
	$todos   = $insFoto->listar($busqueda, $sedeSel, '');
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
				<div class="row">
					<div class="col-sm-6"><h3 class="mb-0">Fotos de alumnos</h3></div>
					<div class="col-sm-6">
						<ol class="breadcrumb float-sm-end">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active">Fotos de alumnos</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<div class="app-content">
			<div class="container-fluid">

				<!-- ==================== El diagnóstico ==================== -->
				<div class="row">
					<?php
					$tarjetas = [
						['Alumnos',          $resumen['TOTAL'],                              'primary',  'fa-users',              ''],
						['Con foto',         $resumen[fotoAlumnoController::OK],             'success',  'fa-circle-check',       fotoAlumnoController::OK],
						['Sin foto',         $resumen[fotoAlumnoController::SIN],            'secondary','fa-user',               fotoAlumnoController::SIN],
						['Archivo ausente',  $resumen[fotoAlumnoController::AUSENTE],         'danger',   'fa-triangle-exclamation', fotoAlumnoController::AUSENTE],
					];
					foreach ($tarjetas as [$titulo, $valor, $color, $icono, $filtro]):
						/* Cada tarjeta filtra la tabla: el número lleva a los
						   alumnos que lo componen, que es lo que se quiere
						   hacer justo después de leerlo. */
						$destino = $filtro === ''
							? '?' . http_build_query(array_filter(['q' => $busqueda, 'sede' => $sedeSel]))
							: '?' . http_build_query(array_filter(['q' => $busqueda, 'sede' => $sedeSel, 'estado' => $filtro]));
					?>
						<div class="col-12 col-sm-6 col-lg-3">
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

				<!-- ==================== Filtros ==================== -->
				<div class="card">
					<div class="card-body">
						<form method="GET" class="row g-2 align-items-end">
							<div class="col-12 col-md-4">
								<label for="q" class="form-label small mb-1">Buscar</label>
								<input type="search" class="form-control form-control-sm" id="q" name="q"
								       value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>"
								       placeholder="Cédula, nombre o apellido">
							</div>
							<div class="col-6 col-md-3">
								<label for="sede" class="form-label small mb-1">Sede</label>
								<select class="form-select form-select-sm" id="sede" name="sede">
									<option value="">Todas</option>
									<?php foreach ($sedes as $s): ?>
										<option value="<?php echo (int)$s['sede_id']; ?>"
											<?php echo ((string)$sedeSel === (string)$s['sede_id']) ? 'selected' : ''; ?>>
											<?php echo htmlspecialchars($s['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-md-3">
								<label for="estado" class="form-label small mb-1">Estado de la foto</label>
								<select class="form-select form-select-sm" id="estado" name="estado">
									<option value="">Todos</option>
									<?php foreach ($PINTA as $clave => $p): ?>
										<option value="<?php echo $clave; ?>"
											<?php echo $estadoF === $clave ? 'selected' : ''; ?>>
											<?php echo $p['etiqueta']; ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-12 col-md-2 d-grid">
								<button type="submit" class="btn btn-sm btn-primary">
									<i class="fas fa-filter me-1"></i>Filtrar
								</button>
							</div>
						</form>
					</div>
				</div>

				<!-- ==================== La tabla ==================== -->
				<div class="card">
					<div class="card-header">
						<h3 class="card-title mb-0">
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
							<?php echo $cuantos; ?> alumno<?php echo $cuantos === 1 ? '' : 's'; ?>
							<?php if ($estadoF !== ''): ?>
								<span class="badge text-bg-<?php echo $PINTA[$estadoF]['color']; ?> ms-1">
									<?php echo $PINTA[$estadoF]['etiqueta']; ?>
								</span>
							<?php endif; ?>
						</h3>
					</div>
					<div class="card-body">
						<div class="table-responsive">
							<table class="table table-hover table-sm align-middle" id="tablaFotos">
								<thead>
									<tr>
										<th style="width:64px;">Foto</th>
										<th>Identificación</th>
										<th>Alumno</th>
										<th class="d-none d-md-table-cell">Sede</th>
										<th class="d-none d-lg-table-cell text-end">Edad</th>
										<th>Estado</th>
										<?php if ($puedeEditar): ?><th class="text-end">Acción</th><?php endif; ?>
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
	| La tabla ordena y pagina en el navegador.
	|
	| Se pagina de 25 en 25 a propósito: con las cientos de filas visibles a la
	| vez, el navegador pediría todas las miniaturas de golpe y cada una entra
	| por app/media.php, que arranca la sesión para servirla.
	|
	| La columna de la foto y la de acciones no se ordenan: una imagen y un
	| botón no tienen orden que signifique nada.
	*/
	if (document.getElementById('tablaFotos')) {
		var columnas = document.querySelectorAll('#tablaFotos thead th').length;
		var sinOrden = [0];
		if (columnas > 6) { sinOrden.push(columnas - 1); }

		new DataTable('#tablaFotos', {
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
			| El orden va desactivado: las columnas son foto, insignias y
			| botones ya armados, y ordenarlas exigiria trasladar el criterio al
			| endpoint. El filtrado cubre lo que aqui se necesita.
			*/
			serverSide: true,
			processing: true,
			ajax: {
				url: '<?php echo APP_URL; ?>app/ajax/fotoAlumnoTablaAjax.php',
				type: 'GET',
				data: function (d) {
					d.q      = <?php echo json_encode($busqueda, JSON_UNESCAPED_UNICODE); ?>;
					d.sede   = <?php echo json_encode($sedeSel,  JSON_UNESCAPED_UNICODE); ?>;
					d.estado = <?php echo json_encode($estadoF,  JSON_UNESCAPED_UNICODE); ?>;
				}
			},
			ordering: false,
			pageLength: 25,
			autoWidth: false,
			columnDefs: [{ orderable: false, targets: sinOrden }],
			language: {
				emptyTable:  'No hay alumnos que mostrar',
				info:        'Mostrando _START_ a _END_ de _TOTAL_ alumnos',
				infoEmpty:   'Mostrando 0 a 0 de 0 alumnos',
				/* Faltaba, y no se notaba: sin serverSide la tabla nunca
				   decia «filtrado de N», asi que al buscar salia la frase en
				   ingles de fabrica. */
				infoFiltered: '(filtrado de _MAX_ alumnos en total)',
				lengthMenu:  'Mostrar _MENU_ alumnos',
				/* Decía «Buscar en la página» porque era verdad: DataTables
				   buscaba en lo ya cargado. Ahora la búsqueda la resuelve el
				   servidor sobre todos los alumnos, así que la advertencia
				   sobraba y además engañaba. */
				search:      'Buscar:',
				zeroRecords: 'Ningún alumno coincide',
				paginate: { first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior' }
			}
		});
	}

	var modalEl = document.getElementById('modalFoto');
	if (!modalEl) { return; }

	var modal = new bootstrap.Modal(modalEl);

	document.querySelectorAll('.btn-subir').forEach(function (b) {
		b.addEventListener('click', function () {
			document.getElementById('foto_alumno_id').value = this.dataset.id;
			document.getElementById('foto_alumno_nombre').textContent = this.dataset.nombre;
			document.getElementById('modalFotoTitulo').textContent = 'Foto de ' + this.dataset.nombre;

			/* El formulario se limpia al abrir: sin esto, abrir el modal de
			   otro alumno conserva el archivo elegido para el anterior y se
			   sube la foto equivocada sin que nada lo delate. */
			document.getElementById('alumno_foto').value = '';
			var prev = document.getElementById('vistaPrevia');
			prev.classList.add('d-none');
			prev.removeAttribute('src');

			modal.show();
		});
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
