<?php
	use app\controllers\representanteController;
	$insRepre = new representanteController();

	/* La cabecera es común a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Nuevo representante';
	$extras      = ['swal'];
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
						<h4 class="m-0">Nuevo representante</h4>
						<p class="small text-body-secondary mb-0">Al guardar pasará a registrar a su representado.</p>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>representanteList/">Representantes</a></li>
							<li class="breadcrumb-item active" aria-current="page">Nuevo</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
			<?php /* Antes, dos pestañas: el formulario se abría dentro de la
			         primera y se cerraba fuera de la segunda. Ahora secciones
			         a la vista y la del cónyuge, que es opcional, plegada. */ ?>
			<form id="formRepre" class="FormularioAjax ds-editar" action="<?php echo APP_URL; ?>app/ajax/representanteAjax.php" method="POST" autocomplete="off" novalidate data-base="<?php echo APP_URL; ?>">
				<input type="hidden" name="modulo_repre" value="registrar">

				<?php $valores = []; require "app/views/inc/representante-campos.php"; ?>

				<div class="ds-editar__barra">
					<a href="<?php echo APP_URL; ?>representanteList/" class="btn btn-secondary"><i class="fas fa-xmark me-1" aria-hidden="true"></i>Cancelar</a>
					<button type="submit" class="btn btn-success"><i class="fas fa-floppy-disk me-1" aria-hidden="true"></i>Guardar representante</button>
				</div>
			</form>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/form-secciones.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/representante-form.js'); ?>"></script>
  </body>
</html>
