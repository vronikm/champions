<?php
	use app\controllers\dashboardController;
	$insDashboard = new dashboardController();

	/*
	| El dashboard muestra dos cosas distintas según a quién sirve:
	|
	|   · Panel operativo: horarios a cargo y días con asistencia tomada.
	|     Se muestra a quien tiene una ficha de empleado detrás, que es
	|     quien está en la cancha.
	|   · Panel gerencial: alumnos, recaudación y mora por sede. Se reserva
	|     a quien puede ver el balance del mes; un profesor no tiene por qué
	|     conocer la caja de la escuela.
	|
	| La condición del bloque gerencial se apoya en un permiso ya existente
	| en lugar de mirar el número de rol, para que siga funcionando cuando
	| se creen roles nuevos.
	*/
	$verOperativo = empleado_actual() > 0;
	$verGerencial = es_superadministrador() || usuario_tiene_permiso('balanceResultados');

	$sedes = [];
	$totalRepresentantes = $totalAlumnosActivos = $totalAlumnosInactivos = 0;

	// Helper: extrae un valor escalar de un PDOStatement; 0 si no hay fila
	$valorEscalar = function($stmt, $col){
		if($stmt && $stmt->rowCount() > 0){
			$row = $stmt->fetch();
			return isset($row[$col]) ? $row[$col] : 0;
		}
		return 0;
	};

	$totalPendientes = 0;
	$filasSede       = [];

	if($verGerencial){
		// Sedes dinámicas (una tarjeta por cada registro de general_sede)
		$sedes = $insDashboard->obtenerSedes()->fetchAll();

		// Totales globales del consolidado
		$totalRepresentantes   = $valorEscalar($insDashboard->obtenerRepresentantes(),   "totalRepresentantes");
		$totalAlumnosActivos   = $valorEscalar($insDashboard->totalAlumnosActivos(),     "totalAlumnosActivos");
		$totalAlumnosInactivos = $valorEscalar($insDashboard->totalAlumnosInactivos(),   "totalAlumnosInactivos");
		$alumnosConMora        = $valorEscalar($insDashboard->alumnosConMora(),          "alumnosConMora");

		/*
		| PRIMERO SE RECOGE, DESPUES SE PINTA
		|
		| Antes las cifras de cada sede se consultaban dentro del mismo bucle
		| que dibujaba sus tarjetas, y la mora total se acumulaba ahí. Eso
		| obligaba a poner el CONSOLIDADO al final, porque hasta terminar el
		| recorrido valía cero. El resultado era que había que bajar por
		| veintiocho tarjetas para llegar al total, que es justo lo primero
		| que se busca al entrar.
		|
		| Separando las dos cosas el total ya está listo antes de dibujar
		| nada. Son las mismas consultas y el mismo número: sólo cambia
		| cuándo se hacen.
		*/
		foreach($sedes as $sede){
			$sedeId = $sede["sede_id"];

			$fila = [
				"id"         => $sedeId,
				"nombre"     => $sede["sede_nombre"],
				"activos"    => $valorEscalar($insDashboard->obtenerAlumnosActivos($sedeId),   "totalActivos"),
				"inactivos"  => $valorEscalar($insDashboard->obtenerAlumnosInactivos($sedeId), "totalInactivos"),
				"cancelado"  => $valorEscalar($insDashboard->obtenerPagosCancelados($sedeId),  "totalCancelados"),
				"pendiente"  => $valorEscalar($insDashboard->obtenerPagosPendientes($sedeId),  "totalPendientes"),
			];

			$totalPendientes += $fila["pendiente"];
			$filasSede[]      = $fila;
		}
	}

	/*
	| Las cuatro tarjetas de cada sede, en un solo sitio.
	|
	| Estaban escritas cuatro veces seguidas, con el mismo bloque copiado y
	| un color cambiado. Dos de ellas —pagos receptados y pagos pendientes—
	| compartían icono, asi que de un vistazo no se distinguían: había que
	| leer el rótulo para saber cuál era cuál.
	|
	| Cada métrica lleva ahora su propio icono, y el color de enlace del pie
	| va emparejado con el fondo: sobre amarillo y cian el texto que contrasta
	| es el oscuro, sobre verde y rojo el claro. Es lo que hace la propia
	| plantilla en su ejemplo.
	*/
	$tarjetasSede = function(array $s){
		return [
			["clase" => "text-bg-info",    "enlacePie" => "link-dark",
			 "valor" => $s["activos"],   "texto" => "Alumnos activos",
			 "icono" => "fa-users",             "url" => APP_URL . "alumnoList/"],

			["clase" => "text-bg-success", "enlacePie" => "link-light",
			 "valor" => $s["cancelado"], "texto" => "Pagos receptados mes",
			 "icono" => "fa-money-bill-wave",   "url" => APP_URL . "reportePagos/" . $s["id"]],

			["clase" => "text-bg-warning", "enlacePie" => "link-dark",
			 "valor" => $s["inactivos"], "texto" => "Alumnos inactivos",
			 "icono" => "fa-user-slash",        "url" => APP_URL . "alumnoList/"],

			["clase" => "text-bg-danger",  "enlacePie" => "link-light",
			 "valor" => $s["pendiente"], "texto" => "Pagos pendientes",
			 "icono" => "fa-file-invoice-dollar", "url" => APP_URL . "reportePendientes/" . $s["id"]],
		];
	};
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Dashboard';
	$extras      = array (0 => 'swal',);
	require_once "app/views/inc/cabecera.php";
?>
	<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
		<?php /* ds-core activa aqui la identidad DigiSports. Fase 1: solo
				 esta vista, para poder comparar antes de extenderlo. */ ?>
		<div class="app-wrapper ds-core">

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
					<div class="row mb-1">
						<div class="col-sm-6">
						<h1 class="m-0">Dashboard</h1>
						</div><!-- /.col -->
						<div class="col-sm-6">
						<ol class="breadcrumb float-sm-end">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item active" aria-current="page">Panel</li>
						</ol>
						</div><!-- /.col -->
					</div><!-- /.row -->
					</div><!-- /.container-fluid -->
				</div>
				<!-- /.content-header -->

				<!-- Main content -->
				<section class="app-content">
					
					<div class="container-fluid">

					<!-- Panel operativo: horarios a cargo y asistencia del mes -->
					<?php if($verOperativo): ?>
						<?php require "app/views/inc/dashboard-operativo.php"; ?>
					<?php endif; ?>

					<?php if(!$verOperativo && !$verGerencial): ?>
						<div class="alert alert-info">
							<i class="fas fa-info-circle me-1"></i>
							No hay indicadores que mostrar para su rol. Use el menú lateral
							para ir a las pantallas que tenga asignadas.
						</div>
					<?php endif; ?>

					<!-- Panel gerencial: alumnos, recaudación y mora por sede -->
					<?php if($verGerencial && empty($sedes)): ?>
						<div class="alert alert-info">No hay sedes registradas. Registre una sede para ver sus indicadores.</div>
					<?php endif; ?>

					<?php
					/*
					| LO QUE REQUIERE ACCION, ARRIBA DEL TODO
					|
					| Debajo hay treinta y dos tarjetas del mismo peso visual y
					| ninguna dice que hacer. Esta franja recoge lo accionable y
					| lleva a donde se resuelve, con un solo clic.
					|
					| Lo que esta a cero NO se pinta: una franja de ceros vuelve a
					| ser ruido, y el que este vacia es en si misma la buena noticia.
					*/
					if ($verGerencial):
						$at = $insDashboard->requiereAtencion()->fetch();

						$avisos = [];
						if (($alumnosConMora ?? 0) > 0) {
							$avisos[] = ['n' => (int)$alumnosConMora, 'texto' => 'con pagos en mora',
										 'accion' => 'Ver pendientes', 'icono' => 'fa-file-invoice-dollar',
										 'color' => 'danger', 'url' => APP_URL . 'reportePendientes/'];
						}
						if ((int)($at['sinConsentimiento'] ?? 0) > 0) {
							$avisos[] = ['n' => (int)$at['sinConsentimiento'], 'texto' => 'sin consentimiento LOPDP',
										 'accion' => 'Registrar', 'icono' => 'fa-file-signature',
										 'color' => 'warning', 'url' => APP_URL . 'consentimientoList/'];
						}
						if ((int)($at['sinFoto'] ?? 0) > 0) {
							$avisos[] = ['n' => (int)$at['sinFoto'], 'texto' => 'sin fotografía',
										 'accion' => 'Subir fotos', 'icono' => 'fa-camera',
										 'color' => 'secondary', 'url' => APP_URL . 'alumnoFotos/'];
						}
					?>
						<?php if ($avisos): ?>
							<div class="card card-default mb-4" style="border-left:4px solid var(--bs-danger);">
								<div class="card-header">
									<h3 class="card-title">
										<i class="fas fa-triangle-exclamation text-danger me-1"></i>
										REQUIERE ATENCIÓN
									</h3>
								</div>
								<div class="card-body">
									<div class="row g-3">
									<?php foreach ($avisos as $a): ?>
										<div class="col-12 col-md-4">
											<a href="<?php echo $a['url']; ?>"
											   class="d-flex align-items-center gap-3 text-decoration-none p-3 rounded border h-100">
												<i class="fas <?php echo $a['icono']; ?> fa-2x text-<?php echo $a['color']; ?>"
												   aria-hidden="true"></i>
												<span>
													<span class="d-block fw-bold fs-4 lh-1 text-body">
														<?php echo number_format($a['n'], 0, ',', '.'); ?>
													</span>
													<span class="d-block small text-body-secondary">
														alumnos <?php echo $a['texto']; ?>
													</span>
													<span class="d-block small fw-semibold text-<?php echo $a['color']; ?>">
														<?php echo $a['accion']; ?>
														<i class="fas fa-arrow-right ms-1" aria-hidden="true"></i>
													</span>
												</span>
											</a>
										</div>
									<?php endforeach; ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
					<?php endif; ?>

					<?php if($verGerencial && $filasSede): ?>
						<?php
							/* El resumen va antes que el detalle. Cada metrica lleva el
											 color y el icono que usa esa misma metrica en las
											 tarjetas de cada sede, para que el color signifique lo
											 mismo en toda la pantalla. */
							$consolidado = [
								["texto" => "Representantes",    "valor" => $totalRepresentantes,
								 "clase" => "text-bg-primary",   "icono" => "fa-user-friends",
								 "url"   => APP_URL . "representanteList/"],

								["texto" => "Alumnos activos",   "valor" => $totalAlumnosActivos,
								 "clase" => "text-bg-info",      "icono" => "fa-users",
								 "url"   => APP_URL . "alumnoList/"],

								["texto" => "Alumnos inactivos", "valor" => $totalAlumnosInactivos,
								 "clase" => "text-bg-warning",   "icono" => "fa-user-slash",
								 "url"   => APP_URL . "alumnoList/"],

								/*
								| Cuenta ALUMNOS, no partidas de pago.
								|
								| Antes usaba $totalPendientes, que es la suma de saldos y
								| pensiones sin pagar: bajo el rotulo «Alumnos con mora» daba
								| 433 con 266 alumnos activos. Mas morosos que alumnos.
								|
								| $totalPendientes sigue siendo correcto donde se llama
								| «Pagos pendientes», que es en las tarjetas por sede.
								*/
								["texto" => "Alumnos con mora",  "valor" => $alumnosConMora,
								 "clase" => "text-bg-danger",    "icono" => "fa-file-invoice-dollar",
								 "url"   => APP_URL . "reportePendientes/"],
							];
						?>
						<div class="card card-default mb-4">
							<div class="card-header">
								<h3 class="card-title">CONSOLIDADO</h3>
								<div class="card-tools">
									<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
								</div>
							</div>

							<div class="card-body">
								<div class="row">
									<?php foreach($consolidado as $c): ?>
										<div class="col-12 col-sm-6 col-lg-3">
											<?php /* text-reset conserva el color del texto: sin el, envolver
													la caja en un enlace se lo lleva todo al azul de Bootstrap. */ ?>
											<a href="<?php echo $c["url"]; ?>" class="text-decoration-none text-reset">
												<div class="info-box">
													<span class="info-box-icon <?php echo $c["clase"]; ?> shadow-sm">
														<i class="fas <?php echo $c["icono"]; ?>" aria-hidden="true"></i>
													</span>
													<div class="info-box-content">
														<span class="info-box-text"><?php echo $c["texto"]; ?></span>
														<span class="info-box-number"><?php echo htmlspecialchars((string)$c["valor"]); ?></span>
													</div>
												</div>
											</a>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					<?php endif; ?>
					<?php foreach($filasSede as $sede): ?>
						<div class="card card-default mb-4">
							<div class="card-header">
								<h3 class="card-title"><?php echo strtoupper(htmlspecialchars($sede["nombre"])); ?></h3>
								<div class="card-tools">
									<button type="button" class="btn btn-tool" data-lte-toggle="card-collapse" title="Plegar o desplegar" aria-label="Plegar o desplegar"><i data-lte-icon="expand" class="fas fa-plus"></i><i data-lte-icon="collapse" class="fas fa-minus"></i></button>
								</div>
							</div>

							<div class="card-body">
								<div class="row">
									<?php foreach($tarjetasSede($sede) as $t): ?>
										<div class="col-lg-3 col-6">
											<?php /* Marcado de AdminLTE 4. Tres cosas que la version 3
													hacia de otra manera: el color va con text-bg-, que
													ademas elige el texto que contrasta; el icono se
													coloca solo con small-box-icon; y el pie necesita
													las clases de enlace o Bootstrap lo subraya. */ ?>
											<div class="small-box <?php echo $t["clase"]; ?>">
												<div class="inner">
													<h3><?php echo htmlspecialchars((string)$t["valor"]); ?></h3>
													<p><?php echo $t["texto"]; ?></p>
												</div>
												<i class="fas <?php echo $t["icono"]; ?> small-box-icon" aria-hidden="true"></i>
												<a href="<?php echo $t["url"]; ?>"
												   class="small-box-footer <?php echo $t["enlacePie"]; ?> link-underline-opacity-0 link-underline-opacity-50-hover">
													Ver detalle <i class="fas fa-arrow-circle-right ms-1"></i>
												</a>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>

					<!-- /.row -->
					</div><!-- /.container-fluid -->
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
    
		<!-- jQuery -->
		<!-- jQuery UI 1.11.4 -->
		<!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->

		<!-- Bootstrap 4 -->
		<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
		<!-- ChartJS -->
		<!-- Sparkline -->
		<!-- JQVMap -->
		<!-- jQuery Knob Chart -->
		<!-- daterangepicker -->
		<!-- Tempusdominus Bootstrap 4 -->
		<!-- AdminLTE App -->
		<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>

		<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
		<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
	
	</body>
</html>