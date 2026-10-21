<?php
    /**
     * Pantalla mostrada cuando el enlace de inscripción no es utilizable.
     * Espera la variable $estado con el valor 'expirado' o 'invalido'.
     */
    $estado = $estado ?? 'invalido';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars(ESCUELA_NOMBRE); ?> | Enlace de inscripción</title>

    <link rel="icon" type="image/png" href="<?php echo DS_HUB_URL; ?>ds_core/assets/img/icon_cz98px.png">

    <?php
    /*
    | Los mismos recursos propios que index.php, y por el mismo motivo.
    |
    | Esta pantalla se quedó atrás cuando el formulario dejó de usar el CDN:
    | la política de seguridad nueva bloqueó sus hojas de estilo y la página
    | salió sin formato. Que sea la pantalla de error no la exime — es la
    | que ve quien reenvía un enlace vencido, y salir rota ahí parece que el
    | sistema está caído.
    */
    ?>
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css" rel="stylesheet">
    <link href="<?php echo DS_HUB_URL; ?>ds_core/assets/css/fuentes.css" rel="stylesheet">
    <link href="<?php echo APP_URL; ?>assets/css/style.css?v=<?php
        echo @filemtime(__DIR__ . '/../../../assets/css/style.css') ?: time(); ?>" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh; padding:20px;">

    <div class="cz-card card" style="max-width:520px; width:100%;">
        <?php
        /* Una franja con el escudo: la pantalla de error también es la
           escuela, y sin ella parece de otro sitio. */
        ?>
        <div class="cz-header py-3">
            <div class="text-center">
                <img src="<?php echo DS_HUB_URL; ?>ds_core/assets/img/icon_cz98px.png"
                     alt="" height="48" class="cz-logo">
                <h1 class="mt-2"><?php echo htmlspecialchars(ESCUELA_NOMBRE); ?></h1>
            </div>
        </div>
        <div class="card-body cz-expirado">
            <?php if ($estado === 'expirado'): ?>
                <i class="fas fa-clock-rotate-left text-warning"></i>
                <h2>Enlace expirado</h2>
                <p>El tiempo para completar la inscripción ha finalizado.</p>
                <p>Por favor, solicite un nuevo enlace al administrador de la escuela.</p>
            <?php else: ?>
                <i class="fas fa-triangle-exclamation"></i>
                <h2>Enlace no válido</h2>
                <p>El enlace de inscripción no es válido o no fue proporcionado.</p>
                <p>Utilice el enlace exacto que le fue compartido por WhatsApp.</p>
            <?php endif; ?>

            <div class="cz-footer mt-4 pt-3 border-top">
                <?php echo htmlspecialchars(ESCUELA_NOMBRE); ?>
            </div>
        </div>
    </div>

</body>
</html>
