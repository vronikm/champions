<?php
/*
| Pagina mostrada cuando un usuario autenticado solicita una vista sobre la
| que su rol no tiene permiso. La renderiza index.php; no forma parte de la
| lista blanca de viewsModel, por lo que no es navegable directamente.
*/
if (!headers_sent() && http_response_code() === 200) { http_response_code(403); }   /* 403, salvo que quien la incluye ya haya puesto otro (el 404 de Insights) */
?>
<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = '';
	require_once "app/views/inc/cabecera.php";
?>
<body class="bg-body-tertiary">
    <div class="d-flex align-items-center justify-content-center" style="min-height:100vh;">
        <div class="text-center p-4" style="max-width:520px;">

            <img src="<?php echo APP_URL; ?>app/views/dist/img/Logos/logo_bsc.png"
                 alt="<?php echo APP_NAME; ?>" style="height:90px;opacity:.85;" class="mb-4">

            <h3 class="mb-3">
                <i class="fas fa-lock text-warning me-2"></i>Acceso denegado
            </h3>

            <p class="text-muted mb-4">
                Su rol no tiene permiso para acceder a esta sección.
                Si considera que se trata de un error, solicite al administrador
                que revise los permisos asignados.
            </p>

            <a href="<?php echo APP_URL; ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left me-1"></i> Volver al inicio
            </a>
            <a href="<?php echo APP_URL; ?>logOut/" class="btn btn-outline-secondary">
                Cerrar sesión
            </a>

        </div>
    </div>
</body>
</html>
