<?php
/*
| Facturación en mantenimiento.
|
| Lo incluyen las pantallas de facturación (facturasList, facturasNew,
| facturasEmitidas) cuando el superadministrador bajó el servicio en
| Core › Facturación electrónica. Se pinta DENTRO de la plantilla —barra,
| menú y pie— para que quien llega no quede varado, y responde 503.
|
| Espera $insFactura (facturasController). Termina la petición.
*/
$estadoServicio = $insFactura->estadoServicioFacturacion();
http_response_code(503);
header('Retry-After: 3600');

$tituloVista = 'Facturación en mantenimiento';
require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">
      <?php require_once "app/views/inc/navbar.php"; ?>
      <?php require_once "app/views/inc/main-sidebar.php"; ?>

      <div class="app-main">
		<section class="app-content pt-4">
			<div class="container-fluid">
				<div class="card mx-auto ds-fact__mantenimiento" role="status">
					<div class="card-body text-center p-4 p-md-5">
						<span class="ds-fact__mantenimiento-icono" aria-hidden="true"><i class="fas fa-tools"></i></span>
						<h4 class="mt-3 mb-2">Facturación electrónica en mantenimiento</h4>
						<p class="mb-3"><?php echo htmlspecialchars($estadoServicio['mensaje'], ENT_QUOTES, 'UTF-8'); ?></p>
						<p class="text-body-secondary small mb-4">
							El registro de pagos sigue funcionando con normalidad: los pagos que se registren
							podrán facturarse cuando el servicio vuelva a estar disponible.
						</p>
						<div class="d-flex flex-wrap justify-content-center gap-2">
							<a href="<?php echo APP_URL; ?>dashboard/" class="btn btn-primary"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>Volver al inicio</a>
							<?php if (es_superadministrador()): ?>
								<a href="<?php echo DS_HUB_URL; ?>ds_core/admin/facturacionConfigSri/" class="btn btn-secondary"><i class="fas fa-power-off me-1" aria-hidden="true"></i>Levantar el servicio</a>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>
  </body>
</html>
<?php exit(); ?>
