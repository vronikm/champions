<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Representantes';
	$extras      = array (0 => 'datatables',1 => 'swal',);
	require_once "app/views/inc/cabecera.php";

	/* Estado: el de sus alumnos (ver condicionEstadoRepresentante). */
	$ESTADOS = ['A' => 'Con alumnos activos', 'I' => 'Con alumnos inactivos', 'S' => 'Sin alumnos', 'T' => 'Todos'];
	/* Autorizaciones LOPDP (Consentimientos LOPDP): ver condicionAutorizacion. */
	$AUTORIZACION = ['T' => 'Todas', 'P' => 'Por autorizar', 'C' => 'Completas', 'E' => 'Por enlace', 'F' => 'Por formulario'];
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
						<h4 class="m-0">Representantes</h4>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Representantes</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid ds-crud">

				<?php
				/*
				| LOS CRITERIOS VIAJAN CON CADA PETICIÓN DE LA TABLA
				|
				| Antes este formulario se enviaba a la página, pero la tabla pide
				| sus filas por AJAX (representanteTablaAjax.php) y esos campos no
				| llegaban: buscar por aquí no filtraba nada. Ahora «Buscar» recarga
				| la tabla con los criterios, sin recargar la página.
				*/
				?>
				<form id="filtroRepre" class="card ds-alumnos__filtros" autocomplete="off">
					<div class="card-header">
						<h3 class="card-title"><i class="fas fa-filter me-2" aria-hidden="true"></i>Búsqueda de representantes</h3>
						<div class="card-tools">
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<div class="row g-3">
							<div class="col-12 col-sm-6 col-lg-3 col-xl">
								<label for="repre_identificacion" class="form-label">Identificación</label>
								<input type="text" class="form-control" id="repre_identificacion" name="identificacion" inputmode="numeric" placeholder="Cédula">
							</div>
							<div class="col-6 col-sm-6 col-lg-3 col-xl">
								<label for="repre_apellido1" class="form-label">Apellido</label>
								<input type="text" class="form-control" id="repre_apellido1" name="apellido" placeholder="Apellido">
							</div>
							<div class="col-6 col-sm-6 col-lg-3 col-xl">
								<label for="repre_nombre1" class="form-label">Nombre</label>
								<input type="text" class="form-control" id="repre_nombre1" name="nombre" placeholder="Nombre">
							</div>
							<div class="col-6 col-sm-6 col-lg-3 col-xl">
								<label for="repre_estado" class="form-label">Estado</label>
								<select class="form-select" id="repre_estado" name="estado">
									<?php foreach ($ESTADOS as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>"<?php echo $valor === 'A' ? ' selected' : ''; ?>><?php echo $texto; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-6 col-sm-6 col-lg-3 col-xl">
								<label for="repre_autorizacion" class="form-label">Autorización</label>
								<select class="form-select" id="repre_autorizacion" name="autorizacion">
									<?php foreach ($AUTORIZACION as $valor => $texto): ?>
										<option value="<?php echo $valor; ?>"<?php echo $valor === 'T' ? ' selected' : ''; ?>><?php echo $texto; ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-12 col-xl-auto d-flex align-items-end gap-2">
								<?php echo ds_boton_buscar(); ?>
								<button type="reset" class="btn btn-secondary" title="Quitar los criterios"><i class="fas fa-eraser me-1" aria-hidden="true"></i>Limpiar</button>
							</div>
						</div>
					</div>
				</form>

				<div class="card ds-crud__lista" id="representantes-resultado">
					<div class="card-header d-flex flex-wrap align-items-center gap-2">
						<h3 class="card-title me-auto">
							<i class="fas fa-user-group me-2" aria-hidden="true"></i>Resultado de la búsqueda
							<span class="badge text-bg-secondary ms-1" id="repreTotal" aria-live="polite">…</span>
						</h3>
						<div class="ds-alumnos__herramientas">
							<a href="<?php echo APP_URL; ?>representanteNew/" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo representante</a>
							<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
						</div>
					</div>
					<div class="card-body">
						<p class="small text-body-secondary ds-repres__leyenda">
							<strong>Autorizaciones LOPDP</strong> de sus alumnos (datos e imagen) y por dónde se dieron:
							<span class="badge text-bg-primary">Enlace</span> el que se envía desde
							<a href="<?php echo APP_URL; ?>consentimientoList/">Consentimientos LOPDP</a>,
							<span class="badge text-bg-info">Formulario</span> el de inscripción en línea, o
							<span class="badge text-bg-warning">Administrador</span> registrada en el sistema.
							<strong>Formulario LPD</strong> es el de papel, que se firma en la sede.
						</p>
						<?php
						/*
						| EL CUERPO LLEGA DEL SERVIDOR, PAGINA A PAGINA
						|
						| Antes se pintaban aqui las 265 filas y la pantalla pesaba
						| 410 KB. Ahora lo sirve representanteTablaAjax.php: la busqueda
						| y el orden alcanzan a TODAS las filas, porque los resuelve la
						| base. Las celdas traen el marcado de fichas (.ds-crud): tabla en
						| escritorio y una ficha por representante en el teléfono.
						*/
						?>
						<table id="example1" class="table table-hover align-middle ds-crud__tabla" style="width:100%">
							<thead>
								<tr>
									<th>Representante</th>
									<th class="text-center">Alumnos</th>
									<th>Protección de datos</th>
									<th class="text-end"><span class="visually-hidden">Acciones</span></th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

			</div>
		</section>

		<?php
		/*
		| UN SOLO FORMULARIO PARA TODAS LAS FILAS
		|
		| Las filas llevan un boton con data-accion y data-id, y este formulario
		| unico se rellena al pulsarlo. Al llamar a requestSubmit() pasa por el
		| MISMO manejador de ajax.js: la confirmacion, el envio y el tratamiento
		| de la respuesta son los de siempre.
		*/
		?>
		<form class="FormularioAjax d-none" id="formAccionRepre"
		      action="<?php echo APP_URL; ?>app/ajax/representanteAjax.php"
		      method="POST" autocomplete="off">
			<input type="hidden" name="modulo_repre" id="accionRepre" value="">
			<input type="hidden" name="repre_id"     id="idRepre"     value="">
		</form>

      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- DataTables: sin Responsive ni botones de exportar -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/datatables2/js/dataTables.bootstrap5.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>

	<script>
	document.addEventListener('DOMContentLoaded', function () {
		var filtro = document.getElementById('filtroRepre');
		var CLAVE = 'ds-representantes-por-pagina', LARGOS = [10, 25, 50, 100], largo = 25;
		try { var g = parseInt(localStorage.getItem(CLAVE), 10); if (LARGOS.indexOf(g) !== -1) { largo = g; } } catch (e) {}

		/*
		| serverSide: la base pagina, busca y ordena. Cada petición lleva los
		| criterios del formulario (estado, identificación, apellido, nombre).
		| Las celdas son fichas; className pone a cada <td> su papel en ellas.
		| «Mostrar N» hasta 100: el servidor no sirve más de 200 por página.
		*/
		var tabla = new DataTable("#example1", {
			serverSide: true,
			processing: true,
			ajax: {
				url: "<?php echo APP_URL; ?>app/ajax/representanteTablaAjax.php",
				type: "GET",
				data: function (d) {
					new FormData(filtro).forEach(function (v, k) { d[k] = v; });
				}
			},
			autoWidth: false,
			/* La caja de filtrar consulta al servidor: se espera a que se deje de
			   escribir, no una petición por tecla. */
			searchDelay: 350,
			pageLength: largo,
			lengthMenu: LARGOS,
			order: [[0, "asc"]],
			columnDefs: [
				{ targets: 0, className: "ds-ficha__principal" },
				{ targets: 1, className: "ds-ficha__dato text-center", createdCell: function (td) { td.setAttribute('data-etiqueta', 'Alumnos'); } },
				{ targets: 2, className: "ds-ficha__dato", createdCell: function (td) { td.setAttribute('data-etiqueta', 'Protección de datos'); } },
				{ targets: 3, className: "ds-tabla-acciones ds-ficha__acciones", orderable: false }
			],
			layout: {
				topStart: 'pageLength',
				topEnd: 'search',
				bottomStart: 'info',
				bottomEnd: { paging: { firstLast: false } }
			},
			language: {
				emptyTable: "No hay representantes con estos criterios",
				info: "_START_ a _END_ de _TOTAL_ representantes",
				infoEmpty: "Sin representantes",
				infoFiltered: "(de _MAX_)",
				lengthMenu: "Mostrar _MENU_",
				loadingRecords: "Cargando...",
				processing: "Buscando…",
				zeroRecords: "Ningún representante coincide con la búsqueda",
				search: "",
				searchPlaceholder: "Filtrar por nombre, cédula o sede…",
				paginate: { next: "Siguiente", previous: "Anterior" },
				aria: {
					sortAscending: ": ordenar de forma ascendente",
					sortDescending: ": ordenar de forma descendente"
				}
			}
		});

		/* El contador de la cabecera: cuántos trae la búsqueda. */
		tabla.on('xhr', function () {
			var j = tabla.ajax.json();
			if (j) { document.getElementById('repreTotal').textContent = j.recordsFiltered; }
		});
		tabla.on('length', function (e, s, nuevo) {
			try { localStorage.setItem(CLAVE, String(nuevo)); } catch (err) {}
		});

		/* Buscar y Limpiar recargan la tabla, no la página. */
		filtro.addEventListener('submit', function (e) { e.preventDefault(); tabla.ajax.reload(); });
		filtro.addEventListener('reset', function () { setTimeout(function () { tabla.ajax.reload(); }, 0); });
		document.getElementById('repre_estado').addEventListener('change', function () { tabla.ajax.reload(); });
		document.getElementById('repre_autorizacion').addEventListener('change', function () { tabla.ajax.reload(); });

		/* Delegado en el documento y no en cada boton: DataTables rehace las
		   filas al paginar y ordenar, y los oyentes puestos fila a fila se pierden. */
		document.addEventListener('click', function (e) {
			var b = e.target.closest('.js-repre');
			if (!b) { return; }
			document.getElementById('accionRepre').value = b.dataset.accion || '';
			document.getElementById('idRepre').value     = b.dataset.id     || '';
			document.getElementById('formAccionRepre').requestSubmit();
		});
	});
	</script>

  </body>
</html>
