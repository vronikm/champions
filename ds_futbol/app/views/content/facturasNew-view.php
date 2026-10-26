<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\facturasController;

	include 'app/lib/barcode.php';

	$generator = new barcode_generator();
	$symbology = "code128"; // Cambiar tipo de código
	$options = array('sx'=>1,'sy'=>0.5,'p'=>1); // Ajustar tamaño y padding
	$insAlumno = new facturasController();
	$sriConfig = $insAlumno->obtenerConfiguracionSri();
	$sriEmisor = $sriConfig['emisor'] ?? [];
	$sriAmbiente = ((string)($sriConfig['ambiente'] ?? '1') === '2') ? 'Produccion' : 'Pruebas';
	$sriIvaDefault = (float)($sriConfig['iva_tarifa_default'] ?? 0);
	$sriFormaPago = (string)($sriConfig['forma_pago_default'] ?? '20');
	$sriFormaPagoTexto = $sriConfig['formas_pago'][$sriFormaPago] ?? $sriFormaPago;
	$h = static function($valor){ return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); };

	/* Subpantalla de facturasList: no tiene entrada de menú propia, así
	   que el control de acceso se hace aquí. Sin esto, al no estar
	   registrada en seguridad_menu quedaría sin restricción. */
	/* Servicio bajado por mantenimiento (Core › Facturación electrónica). */
	if (!$insAlumno->servicioFacturacionActivo()) { $insFactura = $insAlumno; require "app/views/inc/facturacion-mantenimiento.php"; }

	if (!puede_crear('facturasList')) {
		http_response_code(403);
		require_once "app/views/content/accesoDenegado-view.php";
		exit();
	}

	/* Exige el alumno en la URL. Sin él la consulta se armaba con un
	   WHERE vacío y la página moría con el SQL a la vista. */
	$alumno = isset($url[1]) ? $insLogin->limpiarCadena($url[1]) : '';

	if ($alumno === '' || !ctype_digit((string)$alumno)) {
		header("Location: " . APP_URL . "facturasList/");
		exit();
	}

	$fecha_inicio= date('Y-m-d');
	$fecha_fin= date('Y-m-d');

	$datos=$insAlumno->BuscarAlumnoFactura($alumno, $fecha_inicio,$fecha_fin);

	if($datos->rowCount()==1){
		$datos=$datos->fetch();

		/* validar correo */
		$error='N';
		$disabled='';

		if (!filter_var($datos['repre_correo'], FILTER_VALIDATE_EMAIL)) {
			$mail = '<p class="text-danger">'.$datos['repre_correo'].'</p>';
			$correo = '<strong class="text-danger"><i class="fas fa-envelope me-1"></i> Correo no válido</strong>';
			$error='S';
			$disabled='disabled';
		}else {
			$mail = '<p class="text-muted">'.$datos['repre_correo'].'</p>';
			$correo = '<strong><i class="fas fa-envelope me-1"></i> Correo</strong>';
		}

		/* validar identificacion SRI */
		if (!$insAlumno->validarIdentificacionSri($datos['repre_identificacion'], $datos['repre_tipoidentificacion'])) {
			$identificacion = '<p class="text-danger">'.$datos['repre_identificacion'].'</p>';
			$cedula = '<strong class="text-danger"><i class="fas fa-address-card me-1"></i> Identificacion no valida SRI</strong>';
			$error='S';
			$disabled='disabled';
		}else {
			$identificacion = '<p class="text-muted">'.$datos['repre_identificacion'].'</p>';
			$cedula = '<strong><i class="fas fa-address-card me-1"></i> Identificacion</strong>';
		}

	}else{
		/* El aviso no interrumpía el flujo y la página seguía renderizando
		   con $datos todavía como PDOStatement, así que reventaba unas
		   líneas más abajo. Sin representante no hay factura posible: se
		   vuelve al listado. */
		header("Location: " . APP_URL . "facturasList/");
		exit();
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Facturas';
	$extras      = array (0 => 'lightbox',1 => 'swal',);
	require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">

		<!-- Preloader -->
		<!--?php require_once "app/views/inc/preloader.php"; ?-->
		<!-- /.Preloader -->

		<!-- Navbar -->
		<?php require_once "app/views/inc/navbar.php"; ?>
		<!-- /.navbar -->

		<!-- Main Sidebar Container -->
		<?php require_once "app/views/inc/main-sidebar.php"; ?>
		<!-- /.Main Sidebar Container -->

		<!-- vista -->
		<div class="app-main">

			<!-- Content Header (Page header) -->
			<div class="app-content-header">
				<div class="container-fluid">
					<div class="row mb-2">
						<div class="col-sm-6">
							<h3 class="m-0">Envio de facturas</h3>
						</div><!-- /.col -->
						<div class="col-sm-6">
							<ol class="breadcrumb float-sm-end">
								<li class="breadcrumb-item"><a href="#">Inicio</a></li>
								<li class="breadcrumb-item active">Ficha Alumno</li>
							</ol>
						</div><!-- /.col -->
					</div><!-- /.row -->
				</div><!-- /.container-fluid -->
			</div>
			<!-- /.content-header -->

			<!-- Main content -->
			<section class="app-content">
				<!-- /.container-fluid información alumno -->
				<div class="container-fluid">

					<div class="row">
						<div class="col-md-3">
							<div class="card card-olive">
								<div class="card-header">
									<h3 class="card-title">Representante</h3>
								</div>

								<!-- Bloque Representante -->
								<div class="card-body">
									<strong><i class="fas fa-user me-1"></i> Nombres</strong>
									<p class="text-muted" id="representante_nombre"><?php echo $datos['representante']?></p>

									<hr>
									<div id="representante_identificacion">
										<?php echo $cedula.$identificacion?>
									</div>

									<hr>
									<strong><i class="fas fa-map-marker-alt me-1"></i> Dirección</strong>
									<p class="text-muted" id="representante_direccion"><?php echo $datos['repre_direccion']; ?></p>

									<hr>
									<div id="representante_correo">
										<?php echo $correo.$mail; ?>
									</div>

									<hr>
									<strong><i class="fas fa-phone me-1"></i> Teléfono</strong>
									<p class="text-muted" id="representante_celular"><?php echo $datos['repre_celular']; ?></p>

									<hr>
									<strong><i class="fas fa-print me-1"></i> Pagos receptados</strong>
									<p class="text-muted" id="representante_pagos"><?php echo $datos['pagos']; ?></p>

									<hr>
									<strong><i class="fas fa-print me-1"></i> Facturas generadas</strong>
									<p class="text-muted" id="representante_facturas"><?php echo $insAlumno->contarFacturasGeneradas($alumno,'',''); ?></p>
								</div>


								<div class="card-footer">
									<div class="text-end">
										<a href="#" class="btn btn-sm bg-olive" data-bs-target="#modal-representante" data-bs-toggle="modal">
											<i class="fas fa-pen"></i> Actualizar
										</a>
									</div>
								</div>

								<!-- /.card-body -->
							</div>
						</div>

						<div class="col-md-9">
							<div class="card">
								<div class="card-header p-2">
									<div class="row align-items-end">
										<!-- Fecha inicio -->
										<div class="col-md-4">
											<div class="mb-3 mb-0">
												<label for="fecha_inicio">Fecha inicio</label>
												<div class="input-group input-group-sm">
																							<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
									
													<input type="date" class="form-control form-control-sm" id="fecha_inicio" name="fecha_inicio" value="<?php echo $fecha_inicio; ?>">
												</div>
											</div>
										</div>

										<!-- Fecha fin -->
										<div class="col-md-4">
											<div class="mb-3 mb-0">
												<label for="fecha_fin">Fecha fin</label>
												<div class="input-group input-group-sm">
																							<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
									
													<input type="date" class="form-control form-control-sm" id="fecha_fin" name="fecha_fin" value="<?php echo $fecha_fin; ?>">
												</div>
											</div>
										</div>

										<!-- Botón -->
										<div class="col-md-4">
											<div class="mb-3 mb-0 d-flex justify-content-center">
												<a href="#" id="btn-generar-factura"
													class="btn btn-sm bg-lightblue btn-ctrl-sm <?php echo $disabled; ?>"
													data-bs-toggle="modal" data-bs-target="#modal-factura">
													<i class="fas fa-print"></i> Generar Factura
												</a>
											</div>
										</div>
									</div>
								</div><!-- /.card-header -->

								<div class="card-body">
									<div class="tab-content">
										<!-- /.tab-pane -->
										<div class="active tab-pane" id="pension">

											<p class="lead mb-0">Pagos receptados</p>

											<div class="tab-content" id="custom-content-above-tabContent">
												<div class="table-responsive">
												<table class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>
															<th>Fecha</th>
															<th>Pago</th>
															<th>Forma de pago</th>
															<th>Detalle</th>
															<th>Alumno</th>
															<th>Selección</th>
														</tr>
													</thead>
													<tbody id="tabla_pagos" >
														<?php
															echo $insAlumno->listarPagosFactura($alumno,$fecha_inicio, $fecha_fin);
														?>
													</tbody>
												</table>
												</div>
											</div>

											<div class="card-footer">
											</div>

											<div class="tab-custom-content">
												<p class="lead mb-0">Facturas generadas</p>
											</div>
											<div class="tab-content" id="custom-content-above-tabContent">
												<div class="table-responsive">
												<table class="table table-bordered table-striped table-sm">
													<thead>
														<tr>
															<th>No</th>
															<th>Fecha</th>
															<th>Pago</th>
															<th>Detalle</th>
															<th>Estado</th>
															<th style="width:280px;">Opciones</th>
														</tr>
													</thead>
													<tbody id="tabla_facturas" >
														<?php
															echo $insAlumno->listarFacturasGeneradas($alumno,'','');
														?>
													</tbody>
												</table>
												</div>
											</div>

										</div>
									</div>
									<!-- /.tab-content -->
								</div><!-- /.card-body -->
							</div>
							<!-- /.card -->
						</div>
					</div>
				</div>
			</section>
			<!-- /.content -->
		</div>
		<!-- /.vista -->

		<?php require_once "app/views/inc/footer.php"; ?>

		<!-- Control Sidebar -->
		<aside class="control-sidebar control-sidebar-dark">
		<!-- Control sidebar content goes here -->
		</aside>
      <!-- /.control-sidebar -->
    </div>
    <!-- ./wrapper -->

	<div class="modal fade" id="modal-representante">
		<div class="modal-dialog modal-sm">
			<div class="modal-content">
				<form class="FormularioAjax" id="quickForm" action="<?php echo APP_URL; ?>app/ajax/facturasAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
					<input type="hidden" name="modulo_facturas" value="ACTUALIZAR_REPRESENTANTE">
					<input type="hidden" name="usuario" value="<?php echo $_SESSION['usuario']; ?>">
					<input type="hidden" name="repre_id" value="<?php echo $datos['repre_id']; ?>">

					<div class="modal-header bg-olive py-2 px-3">
						<h6 class="modal-title mb-0"><?php echo $datos['representante']; ?></h6>
						<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
					</div>

					<div class="modal-body">

						<div class="mb-3 mb-3-sm">
							<label for="identificacion">Identificación</label>
							<input type="text" class="form-control form-control-sm" id="identificacion" name="identificacion" required utocomplete="off" value="<?php echo $datos['repre_identificacion']; ?>">
						</div>
						<div class="mb-3 mb-3-sm">
							<label for="direccion">Dirección</label>
							<input type="text" class="form-control form-control-sm" id="direccion" name="direccion" required utocomplete="off" value="<?php echo $datos['repre_direccion']; ?>">
						</div>
						<div class="mb-3 mb-3-sm">
							<label for="correo">Correo</label>
							<input type="email" class="form-control form-control-sm" id="correo" name="correo" required utocomplete="off" value="<?php echo $datos['repre_correo']; ?>">
						</div>
						<div class="mb-3 mb-3-sm">
							<label for="celular">Teléfono</label>
							<input type="text" class="form-control form-control-sm" id="celular" name="celular" required utocomplete="off" value="<?php echo $datos['repre_celular']; ?>">
						</div>
					</div>
					<div class="modal-footer justify-content-between py-2 px-3">
						<button type="button" class="btn bg-gray btn-sm" data-bs-dismiss="modal">Cerrar</button>
						<button type="submit" class="btn bg-olive btn-sm">Guardar</button>
					</div>
				</form>
			</div>
			<!-- /.modal-content -->
		</div>
	<!-- /.modal-dialog -->
	</div>

	<div class="modal fade" id="modal-factura" tabindex="-1">
		<div class="modal-dialog modal-xl">
			<div class="modal-content border">

			<!-- HEADER -->
			<div class="modal-header bg-lightblue py-2 px-3">
				<h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i> Factura Electrónica</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
			</div>

			<!-- BODY -->
			<div class="modal-body">
				<div class="alert alert-warning py-2">
					<i class="fas fa-exclamation-triangle me-1"></i>
					Vista previa. La validez tributaria requiere firma electronica y autorizacion del SRI.
				</div>

				<!-- LOGO Y DATOS EMISOR -->
				<div class="row mb-3">
				<div class="col-md-4 text-center">
					<img src="<?php echo APP_URL; ?>app/views/dist/img/Logos/logo_bsc.png" alt="<?php echo APP_NAME; ?>" class="img-fluid mb-2" style="max-height:80px;">
				</div>
				<div class="col-md-8">
					<h5 class="font-weight-bold mb-1"><?php echo $h($sriEmisor['razon_social'] ?? ''); ?></h5>
					<p class="mb-1"><strong>R.U.C.:</strong> <?php echo $h($sriEmisor['ruc'] ?? ''); ?></p>
					<p class="mb-1"><strong>Direccion Matriz:</strong> <?php echo $h($sriEmisor['direccion_matriz'] ?? ''); ?></p>
					<p class="mb-1"><strong>Direccion Sucursal:</strong> <?php echo $h($sriEmisor['direccion_establecimiento'] ?? ''); ?></p>
					<p class="mb-1"><strong>Teléfonos:</strong> <?php echo $h(ds_telefono_organizacion()); ?></p>
					<p class="mb-0"><strong>Obligado a llevar contabilidad:</strong> <?php echo $h($sriEmisor['obligado_contabilidad'] ?? 'NO'); ?></p>
				</div>
				</div>

				<!-- DATOS FACTURA Y CLIENTE -->
				<div class="row mb-3">
				<!-- FACTURA -->
				<div class="col-md-6 border p-2">
					<h6 class="font-weight-bold">Factura</h6>
					<p class="mb-1"><strong>No.:</strong> Se asigna al guardar</p>
					<div class="border rounded p-2 mb-2 text-muted small">La clave de acceso y el XML se generan al guardar la factura.</div>
					<p class="mb-1"><strong>Clave de Acceso:</strong> Pendiente</p>
					<p class="mb-1"><strong>Numero de Autorizacion:</strong> Pendiente de autorizacion SRI</p>
					<p class="mb-1"><strong>Fecha Autorizacion:</strong> Pendiente</p>
					<p class="mb-1"><strong>Ambiente:</strong> <?php echo $sriAmbiente; ?></p>
					<p class="mb-1"><strong>Emision:</strong> Normal</p>
					<p class="mb-0"><strong>Esquema:</strong> Offline</p>
				</div>
				<!-- CLIENTE -->
				<div class="col-md-6 border p-2">
					<h6 class="font-weight-bold">Datos del Cliente</h6>
					<p class="mb-1"><strong>Cliente:</strong> <?php echo $datos['representante']; ?></p>
					<p class="mb-1"><strong>Identificación:</strong> <?php echo $datos['repre_identificacion']; ?></p>
					<p class="mb-1"><strong>Dirección:</strong> <?php echo $datos['repre_direccion']; ?></p>
					<p class="mb-1"><strong>Teléfono:</strong> <?php echo $datos['repre_celular']; ?></p>
					<p class="mb-0"><strong>Email:</strong> <?php echo $datos['repre_correo']; ?></p>
				</div>
				</div>

				<!-- DETALLE FACTURA -->
				<div class="table-responsive mb-3">
				<table class="table table-sm table-bordered">
					<thead class="thead-light">
					<tr>
						<th>Código</th>
						<th class="text-end">Cantidad</th>
						<th>Detalle</th>
						<th class="text-end">Precio Unitario</th>
						<th class="text-end">Descuento</th>
						<th class="text-end">Precio Total</th>
					</tr>
					</thead>
					<tbody id="detalle-factura">
					<!-- Aquí se cargan los pagos seleccionados -->
					</tbody>
					<tfoot>
					<tr>
						<th colspan="5" class="text-end">Total</th>
						<th class="text-end" id="total-factura">0.00</th>
					</tr>
					</tfoot>
				</table>
				</div>

				<!-- INFORMACIÓN ADICIONAL Y TOTALES -->
				<div class="row">
					<div class="col-md-6">
						<h6 class="font-weight-bold">Información Adicional</h6>
						<p><strong>Usuario:</strong> <?php echo $h($_SESSION['usuario'] ?? 'Sistema'); ?></p>
						<div class="mb-3 mb-2">
							<label class="font-weight-bold mb-1" for="factura_forma_pago">Forma de Pago:</label>
							<select class="form-select form-select-sm" id="factura_forma_pago" name="forma_pago">
								<?php foreach(($sriConfig['formas_pago'] ?? []) as $codigo => $nombre){ ?>
									<option value="<?php echo $h($codigo); ?>" <?php echo ((string)$codigo === $sriFormaPago) ? 'selected' : ''; ?>><?php echo $h($codigo.' - '.$nombre); ?></option>
								<?php } ?>
							</select>
						</div>
					</div>
					<div class="col-md-6">
						<div class="table-responsive">
						<table class="table table-sm">
						<tr>
							<td class="text-end"><b>SUBTOTAL No objeto IVA</b>:</td>
							<td class="text-end">0.00</td>
						</tr>
						<tr>
							<td class="text-end"><b>SUBTOTAL Exento IVA</b>:</td>
							<td class="text-end">0.00</td>
						</tr>
						<tr>
							<td class="text-end"><b>SUBTOTAL 0%</b>:</td>
							<td class="text-end" id="subtotal0">0.00</td>
						</tr>
						<tr>
							<td class="text-end"><b>SUBTOTAL <?php echo number_format($sriIvaDefault, 0); ?>%</b>:</td>
							<td class="text-end" id="subtotalIva">0.00</td>
						</tr>
						<tr>
							<td class="text-end"><b>IVA <?php echo number_format($sriIvaDefault, 0); ?>%</b>:</td>
							<td class="text-end" id="ivaValor">0.00</td>
						</tr>
						<tr class="bg-light">
							<td class="text-end"><b>VALOR TOTAL</b>:</td>
							<td class="text-end font-weight-bold" id="total">0.00</td>
						</tr>
						</table>
						</div>
					</div>
				</div>
			</div>

			<!-- FOOTER -->
			<div class="modal-footer justify-content-between py-2 px-3">
				<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
				<i class="fas fa-times me-1"></i> Cerrar
				</button>
				<button type="button" id="btn-guardar-factura" class="btn bg-lightblue btn-sm">
				<i class="fas fa-paper-plane me-1"></i> Emitir Factura
				</button>
			</div>
			</div>
		</div>
	</div>










	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<!-- Bootstrap 4 -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<!-- Select2 -->
	<!-- Bootstrap4 Duallistbox -->
	<!-- InputMask -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/inputmask/jquery.inputmask.min.js"></script>
	<!-- date-range-picker -->
	<!-- bootstrap color picker -->
	<!-- Tempusdominus Bootstrap 4 -->
	<!-- Bootstrap Switch -->
	<!-- BS-Stepper -->
	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<!-- fileinput -->

	<script src="<?php echo ds_recurso('ds_core/assets/js/visor.js'); ?>"></script>

	<script>
		$(document).ready(function(){

		$("#fecha_inicio, #fecha_fin").on("change", function(){
			let fecha_inicio = $("#fecha_inicio").val();
			let fecha_fin = $("#fecha_fin").val();
			let alumno = "<?php echo $alumno; ?>";

			$.ajax({
				url: "<?php echo APP_URL; ?>app/ajax/facturasAjax.php",
				type: "POST",
				data: {
					modulo_facturas: "CONSULTAR_FACTURAS",
					alumno: alumno,
					fecha_inicio: fecha_inicio,
					fecha_fin: fecha_fin
				},
				beforeSend: function(){
					$("#tabla_pagos").html("<tr><td colspan='8'>Cargando...</td></tr>");
					$("#tabla_facturas").html("<tr><td colspan='8'>Cargando...</td></tr>");
				},
				success: function(respuesta){
					let datos = typeof respuesta === "string" ? JSON.parse(respuesta) : respuesta;

					// Actualizar tablas
					$("#tabla_pagos").html(datos.pagos);
					$("#tabla_facturas").html(datos.facturas);

					// Actualizar información representante
					if(datos.representante){
						$("#representante_nombre").text(datos.representante.nombre);
						$("#representante_identificacion").text(datos.representante.identificacion);
						$("#representante_direccion").text(datos.representante.direccion);
						$("#representante_correo").text(datos.representante.correo);
						$("#representante_celular").text(datos.representante.celular);
						$("#representante_pagos").text(datos.representante.pagos);
						$("#representante_facturas").text(datos.representante.facturas);
					}
				},
				error: function(xhr, status, error){
					console.log("Error AJAX:", error);
				}
			});
		});

	});
	</script>

	<script>
		const valoresIncluyenIva = <?php echo !empty($sriConfig['valores_incluyen_iva']) ? 'true' : 'false'; ?>;
		const alumnoFactura = "<?php echo $alumno; ?>";

		document.getElementById("btn-generar-factura").addEventListener("click", function(event) {
			let pagosSeleccionados = document.querySelectorAll(".chk-pago:checked");
			if(pagosSeleccionados.length === 0){
				event.preventDefault();
				event.stopPropagation();
				Swal.fire({title: "Seleccione pagos", text: "Debe seleccionar al menos un pago pendiente para generar la factura.", icon: "warning"});
				return false;
			}

			let tbody = document.getElementById("detalle-factura");
			let total = 0;
			let subtotal0 = 0;
			let subtotalIva = 0;
			let ivaValor = 0;

			tbody.innerHTML = "";

			pagosSeleccionados.forEach(pago => {
				let codigo = pago.getAttribute("data-codigo");
				let detalle = pago.getAttribute("data-detalle");
				let valor = parseFloat(pago.getAttribute("data-valor")) || 0;
				let tarifa = parseFloat(pago.getAttribute("data-tarifa")) || 0;
				let base = valor;
				let ivaLinea = 0;
				let totalLinea = valor;

				if(tarifa > 0){
					if(valoresIncluyenIva){
						base = valor / (1 + (tarifa / 100));
						ivaLinea = valor - base;
						totalLinea = valor;
					}else{
						ivaLinea = base * (tarifa / 100);
						totalLinea = base + ivaLinea;
					}
					subtotalIva += base;
				}else{
					subtotal0 += base;
				}

				total += totalLinea;
				ivaValor += ivaLinea;

				let row = `
					<tr>
						<td>${codigo}</td>
						<td class="text-end">1.00</td>
						<td>${detalle}</td>
						<td class="text-end">${base.toFixed(2)}</td>
						<td class="text-end">0.00</td>
						<td class="text-end">${base.toFixed(2)}</td>
					</tr>
				`;
				tbody.insertAdjacentHTML("beforeend", row);
			});

			document.getElementById("total-factura").innerText = (subtotal0 + subtotalIva).toFixed(2);
			document.getElementById("subtotal0").innerText = subtotal0.toFixed(2);
			document.getElementById("subtotalIva").innerText = subtotalIva.toFixed(2);
			document.getElementById("ivaValor").innerText = ivaValor.toFixed(2);
			document.getElementById("total").innerText = total.toFixed(2);
		});

		$("#btn-guardar-factura").on("click", function(){
			const pagos = $(".chk-pago:checked").map(function(){ return this.value; }).get();
			if(pagos.length === 0){
				Swal.fire({title: "Seleccione pagos", text: "Debe seleccionar al menos un pago pendiente para generar la factura.", icon: "warning"});
				return;
			}

			const formData = new FormData();
			formData.append("modulo_facturas", "GENERAR_FACTURA_ELECTRONICA");
			formData.append("alumno", alumnoFactura);
			formData.append("forma_pago", $("#factura_forma_pago").val() || "");
			pagos.forEach(id => formData.append("pagos[]", id));

			const boton = $(this);
			boton.prop("disabled", true);

			$.ajax({
				url: "<?php echo APP_URL; ?>app/ajax/facturasAjax.php",
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				dataType: "json",
				success: function(respuesta){
					if(["factura_generada", "factura_autorizada", "factura_enviada", "factura_error_sri"].includes(respuesta.tipo)){
						$("#modal-factura").modal("hide");
						Swal.fire({
							title: respuesta.titulo,
							html: `<p>${respuesta.texto}</p><p><strong>No.:</strong> ${respuesta.numero || ""}</p><p class="small"><strong>Clave:</strong> ${respuesta.clave_acceso || ""}</p><p><strong>Estado:</strong> ${respuesta.estado_sri || ""}</p><p><a href="${respuesta.ride_url}" target="_blank">Ver RIDE</a> | <a href="${respuesta.xml_url}">Descargar XML</a></p>`,
							icon: respuesta.icono
						});
						$("#fecha_inicio").trigger("change");
					}else{
						Swal.fire({title: respuesta.titulo || "Atencion", text: respuesta.texto || "No fue posible completar la accion.", icon: respuesta.icono || "warning"});
					}
				},
				error: function(){
					Swal.fire({title: "Error", text: "No fue posible comunicarse con el servidor.", icon: "error"});
				},
				complete: function(){
					boton.prop("disabled", false);
				}
			});
		});

		function ejecutarAccionSri(facturaId, modulo, cargando, textoEspera){
			const formData = new FormData();
			formData.append("modulo_facturas", modulo);
			formData.append("factura_id", facturaId);

			Swal.fire({
				title: cargando,
				text: textoEspera || "Espere un momento mientras se comunica con el SRI.",
				allowOutsideClick: false,
				didOpen: () => Swal.showLoading()
			});

			$.ajax({
				url: "<?php echo APP_URL; ?>app/ajax/facturasAjax.php",
				type: "POST",
				data: formData,
				processData: false,
				contentType: false,
				dataType: "json",
				success: function(respuesta){
					Swal.fire({
						title: respuesta.titulo || "Resultado SRI",
						text: respuesta.texto || "Proceso finalizado.",
						icon: respuesta.icono || "info"
					});
					$("#fecha_inicio").trigger("change");
				},
				error: function(){
					Swal.fire({title: "Error", text: "No fue posible comunicarse con el servidor.", icon: "error"});
				}
			});
		}

		$(document).on("click", ".btn-emitir-sri", function(){
			ejecutarAccionSri($(this).data("id"), "EMITIR_FACTURA_SRI", "Emitiendo factura");
		});

		$(document).on("click", ".btn-consultar-sri", function(){
			ejecutarAccionSri($(this).data("id"), "CONSULTAR_FACTURA_SRI", "Consultando autorizacion");
		});

		$(document).on("click", ".btn-regenerar-factura", function(){
			const facturaId = $(this).data("id");
			Swal.fire({
				title: "Generar nuevo secuencial",
				text: "Se creara una nueva factura para los mismos pagos. La factura devuelta no se reenviara al SRI.",
				icon: "warning",
				showCancelButton: true,
				confirmButtonText: "Generar nueva",
				cancelButtonText: "Cancelar"
			}).then((result) => {
				if(result.isConfirmed){
					ejecutarAccionSri(facturaId, "REGENERAR_FACTURA_DEVUELTA", "Generando nueva factura", "Se reservara el siguiente secuencial y se enviara al SRI.");
				}
			});
		});

		$(document).on("click", ".btn-enviar-factura", function(){
			const facturaId = $(this).data("id");
			const email = $(this).data("email") || "";
			Swal.fire({
				title: "Enviar factura",
				text: "Se enviara el RIDE y XML autorizado a " + email + ".",
				icon: "question",
				showCancelButton: true,
				confirmButtonText: "Enviar",
				cancelButtonText: "Cancelar"
			}).then((result) => {
				if(result.isConfirmed){
					ejecutarAccionSri(facturaId, "ENVIAR_FACTURA_CORREO", "Enviando factura", "Espere un momento mientras se prepara y envia el correo.");
				}
			});
		});
	</script>

  </body>
</html>
