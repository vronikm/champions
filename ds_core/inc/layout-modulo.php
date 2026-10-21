<?php
/*
|--------------------------------------------------------------------------
| Armazón común de los módulos administrativos · AdminLTE 4
|--------------------------------------------------------------------------
| Cabecera, barra lateral y apertura del contenido. Lo cierra
| layout-modulo-pie.php.
|
| POR QUE UNO SOLO Y NO TRES
|
| Arena, League y Core tenían cada uno su copia del mismo armazón. Con
| tres copias, un arreglo se aplica a una y se olvida en las otras dos, y
| la diferencia no se nota hasta que alguien abre la pantalla concreta. Al
| migrar a AdminLTE 4 había que reescribirlas de todas formas: escribirlo
| una vez cuesta lo mismo y deja de haber tres verdades.
|
| Lo que cambia entre módulos es poco y va en variables:
|
|   $moduloNombre   texto de la marca            «League»
|   $moduloAcento   color del elemento activo    #7c3aed
|   $moduloMenu     HTML de los <li> del menú
|   $tituloVista    encabezado y migas
|   $moduloInicio   URL del panel del módulo
|
| ADMINLTE 3 -> 4, LOS NOMBRES QUE CAMBIAN
|
|   .wrapper                  .app-wrapper
|   .main-header              .app-header
|   .main-sidebar             .app-sidebar
|   .content-wrapper          <main class="app-main">
|   .content-header           .app-content-header
|   .content                  .app-content
|   .nav-sidebar              .sidebar-menu
|   .badge-success            .text-bg-success
|   data-widget=«pushmenu»    data-lte-toggle=«sidebar»
|   .ml-N .mr-N               .ms-N .me-N
|   .float-left .float-right  .float-start .float-end
|
| (La tabla usa comillas angulares y N en lugar de los valores literales a
|  propósito: escrita con los nombres exactos, el propio convertidor de
|  clases la reescribía y la dejaba diciendo «.float-end -> .float-end».)
|
| EL VENDOR VIENE DEL NUCLEO
|
| Antes se cargaba con DS_VENDOR_URL, que apunta dentro de ds_futbol.
| AdminLTE no es de Basketball: es del ecosistema. Ver DS_VENDOR_CORE_URL
| en ds_core/config/app.php.
|
| SE CONSERVA FONT AWESOME
|
| AdminLTE 4 usa Bootstrap Icons en sus ejemplos, pero funciona con
| cualquier tipografía de iconos. Los iconos de menú están guardados en
| seguridad_menu.menu_icono como clases de Font Awesome; cambiarlos
| obligaría a migrar esa tabla y otras ochenta referencias sin ganar nada.
*/

$moduloNombre = $moduloNombre ?? 'DigiSports';
$moduloAcento = $moduloAcento ?? '#4f46e5';
$moduloMenu   = $moduloMenu   ?? '';
$tituloVista  = $tituloVista  ?? $moduloNombre;
$moduloInicio = $moduloInicio ?? (defined('APP_URL') ? APP_URL . 'panel/' : DS_HUB_URL);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo APP_NAME; ?> | <?php echo $tituloVista; ?></title>

    <link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
    <?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
    <link rel="stylesheet" href="<?php echo DS_OVERLAYSCROLL_URL; ?>css/overlayscrollbars.min.css">
    <link rel="stylesheet" href="<?php echo DS_ADMINLTE4_URL; ?>css/adminlte.min.css">
    <link rel="stylesheet" href="<?php echo DS_VENDOR_URL; ?>css/sweetalert2.min.css">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/core.css'); ?>">

    <style>
        /*
        | EL ACENTO DEL ELEMENTO ACTIVO NO ES EL TOKEN DEL MODULO
        |
        | Los tokens --ds-league (#a78bfa) y --ds-arena (#22d3ee) son
        | colores CLAROS, pensados para el fondo oscuro del Hub. Con texto
        | blanco encima dan contrastes de 2.7 y 1.9, muy por debajo del 4.5
        | que hace falta para leerlos. Cada modulo pasa aqui un tono mas
        | oscuro de su misma familia, y el contraste se mide, no se estima.
        */
        /* El acento va por variable, que es donde core.css lo busca. Asi
           el color del menu lo sigue poniendo la plantilla y lo unico que
           cambia por sistema es el elemento activo. */
        .ds-core {
            --ds-acento: <?php echo $moduloAcento; ?>;
            --ds-acento-texto: #fff;
        }
        .btn-primary {
            --bs-btn-bg: <?php echo $moduloAcento; ?>;
            --bs-btn-border-color: <?php echo $moduloAcento; ?>;
            --bs-btn-hover-bg: <?php echo $moduloAcento; ?>;
            --bs-btn-hover-border-color: <?php echo $moduloAcento; ?>;
            --bs-btn-active-bg: <?php echo $moduloAcento; ?>;
            --bs-btn-active-border-color: <?php echo $moduloAcento; ?>;
        }
        .switch input:checked + span { background: <?php echo $moduloAcento; ?>; }

        /* El lanzador de aplicaciones se escribio para la barra oscura de
           la version 3. En la 4 la cabecera es clara, asi que sus iconos
           necesitan color propio o se pierden sobre el fondo. */
        .app-header .nav-link,
        .app-header .ds-launcher .nav-link { color: var(--bs-body-color); }
    </style>
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
<?php /* ds-core NO es decorativa: acota las reglas de core.css que dan a
               DigiSports su aspecto —tarjetas, tablas, interruptores y menu—.
               Sin ella, Arena, League y Core se quedaban con el AdminLTE de
               fabrica. Basketball si la llevaba, y por eso solo fallaban los
               otros tres. (El componente propio .ds-kpi que hubo aqui ya no
               existe: los cuatro modulos usan .info-box de la plantilla.) */ ?>
<div class="app-wrapper ds-core">

    <?php /* ds-core__navbar mantiene la barra en oscuro con el tema claro,
             igual que en Basketball. El armazon no sigue la eleccion: es
             la identidad de DigiSports. */ ?>
    <nav class="app-header navbar navbar-expand bg-body ds-core__navbar" data-bs-theme="dark">
        <div class="container-fluid">

            <ul class="navbar-nav">
                <li class="nav-item">
                    <?php /* data-lte-toggle sustituye a data-widget de v3. */ ?>
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"
                       aria-label="Mostrar u ocultar el menú">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
                <li class="nav-item d-none d-sm-inline-block">
                    <a href="<?php echo DS_HUB_URL; ?>" class="nav-link">
                        <i class="fas fa-arrow-left me-1"></i> Hub
                    </a>
                </li>
            </ul>

            <ul class="navbar-nav ms-auto">
                <?php require_once __DIR__ . "/app-launcher.php"; ?>
                <?php require_once __DIR__ . "/tema-control.php"; ?>

                <li class="nav-item">
                    <span class="nav-link">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars(ds_nombre_usuario(), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a href="<?php echo DS_FUTBOL_URL; ?>logOut/" class="nav-link"
                       title="Cerrar sesión">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </li>
            </ul>

        </div>
    </nav>

    <?php
    /* bg-body-secondary NO es decorativo, y quitarlo deja el menu ilegible.
       data-bs-theme="dark" cambia los COLORES DE TEXTO a los del tema
       oscuro, pero no pinta ningun fondo. Sin la clase, el aside hereda el
       fondo claro de la pagina y queda texto claro sobre claro. */
    ?>
    <aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
        <div class="sidebar-brand">
            <a href="<?php echo $moduloInicio; ?>" class="brand-link">
                <?php /* El mismo icono que la pestaña y que el menu de Basketball.
                         Sale de ds_core y no de cada modulo: una sola copia para
                         los cinco, que no se desincroniza el dia que se cambie. */ ?>
                <img src="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>"
                     alt="<?php echo htmlspecialchars($moduloNombre, ENT_QUOTES, 'UTF-8'); ?>"
                     class="brand-image opacity-75 shadow">
                <span class="brand-text fw-light">
                    <?php echo htmlspecialchars($moduloNombre, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </a>
        </div>

        <div class="sidebar-wrapper">
            <nav class="mt-2" aria-label="Menú principal">
                <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview"
                    role="menu" data-accordion="false" id="navigation">
                    <?php echo $moduloMenu; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h1 class="mb-0 fs-3"><?php echo $tituloVista; ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-end">
                            <li class="breadcrumb-item">
                                <a href="<?php echo DS_HUB_URL; ?>">DigiSports</a></li>
                            <li class="breadcrumb-item">
                                <a href="<?php echo $moduloInicio; ?>"><?php
                                    echo htmlspecialchars($moduloNombre, ENT_QUOTES, 'UTF-8'); ?></a></li>
                            <li class="breadcrumb-item active"><?php echo $tituloVista; ?></li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>

        <div class="app-content">
            <div class="container-fluid">
