<?php
	use app\controllers\representanteController;
	$insRepre = new representanteController();

	$repreid = ds_id_de_url($url, 1, APP_URL . 'representanteList/');

	$datos = $insRepre->seleccionarDatos("Unico", "alumno_representante", "repre_id", $repreid);
	if ($datos->rowCount() != 1) {
		/* No existe: al listado. Antes se pintaba el aviso de error dentro
		   de una página a medio montar. */
		header("Location: " . APP_URL . "representanteList/");
		exit();
	}
	$valores = $datos->fetch(\PDO::FETCH_ASSOC);

	/* El cónyuge, si lo tiene: sus campos se suman a los del formulario. */
	$conyuge = $insRepre->seleccionarDatos("Unico", "alumno_representanteconyuge", "conyuge_repid", $repreid);
	if ($conyuge->rowCount() >= 1) {
		$valores += $conyuge->fetch(\PDO::FETCH_ASSOC);
	}

	$nombreRepre = trim(html_entity_decode($valores['repre_primernombre'] . ' ' . $valores['repre_apellidopaterno'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

	/* La cabecera es común a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Editar a ' . $h($nombreRepre);
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
						<h4 class="m-0">Editar representante</h4>
						<p class="small text-body-secondary mb-0"><?php echo $h($nombreRepre); ?></p>
					</div>
					<div class="col-sm-5">
						<ol class="breadcrumb float-sm-end mb-0">
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>dashboard/">Inicio</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>representanteList/">Representantes</a></li>
							<li class="breadcrumb-item"><a href="<?php echo APP_URL; ?>representanteProfile/<?php echo (int)$repreid; ?>/">Ficha</a></li>
							<li class="breadcrumb-item active" aria-current="page">Editar</li>
						</ol>
					</div>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">
			<?php /* Los mismos campos que el alta (inc/representante-campos.php).
			         Antes eran dos pestañas, con el botón «Actualizar» al pie de
			         la que estuviera abierta. */ ?>
			<form id="formRepre" class="FormularioAjax ds-editar" action="<?php echo APP_URL; ?>app/ajax/representanteAjax.php" method="POST" autocomplete="off" novalidate
			      data-base="<?php echo APP_URL; ?>" data-repre-id="<?php echo (int)$repreid; ?>">
				<input type="hidden" name="modulo_repre" value="actualizar">
				<input type="hidden" name="repre_id" value="<?php echo (int)$repreid; ?>">

				<?php require "app/views/inc/representante-campos.php"; ?>

				<div class="ds-editar__barra">
					<a href="<?php echo APP_URL; ?>representanteProfile/<?php echo (int)$repreid; ?>/" class="btn btn-secondary"><i class="fas fa-xmark me-1" aria-hidden="true"></i>Cancelar</a>
					<button type="submit" class="btn btn-success"><i class="fas fa-floppy-disk me-1" aria-hidden="true"></i>Guardar cambios</button>
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
