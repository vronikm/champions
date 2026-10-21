<?php
/* Pantalla mostrada cuando el rol no tiene acceso a League o a una vista. */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso denegado | <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
</head>
<body class="ds-body">
    <div class="ds-container d-flex align-items-center justify-content-center" style="min-height:100vh;">
        <div class="text-center" style="max-width:520px;">
            <img src="<?php echo DS_HUB_URL; ?>ds_core/assets/img/logo_ds.png"
                 alt="<?php echo DS_HUB_NAME; ?>" style="height:80px;opacity:.85;margin-bottom:28px;">
            <h3 style="margin-bottom:14px;">
                <i class="fas fa-lock" style="color:var(--ds-warning);margin-right:8px;"></i>Acceso denegado
            </h3>
            <p style="color:var(--ds-text-muted);margin-bottom:28px;">
                Su rol no tiene permiso para acceder a esta sección de League.
                Solicite a un administrador que revise sus permisos en el módulo Core.
            </p>
            <a href="<?php echo DS_HUB_URL; ?>" class="ds-chip" style="padding:10px 18px;">
                <i class="fas fa-arrow-left"></i> Volver al Hub
            </a>
        </div>
    </div>
</body>
</html>
