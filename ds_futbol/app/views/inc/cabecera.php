<?php
/*
|--------------------------------------------------------------------------
| Cabecera comun de las vistas de Basketball
|--------------------------------------------------------------------------
| Setenta y tres vistas repetian el mismo bloque <head>: los mismos seis
| enlaces de estilo, el mismo icono, la misma etiqueta del tema. Cada vez que
| hubo que cambiar algo de la plantilla —y en esta migracion fueron muchas
| veces— hubo que repetirlo setenta y tres veces, con el riesgo de que
| alguna se quedara atras. Paso: tres vistas se quedaron con el CSS de
| DataTables para Bootstrap 4 durante toda la migracion porque el script que
| lo cambiaba solo miraba las que inicializaban una tabla.
|
| EL ORDEN DE LAS HOJAS IMPORTA, Y AQUI QUEDA FIJADO
|
| core.css reviste a AdminLTE, asi que tiene que cargarse DESPUES. Durante
| meses se cargo antes, y por eso ninguna de sus reglas de una sola clase
| llegaba a aplicarse: perdian contra el framework por orden de aparicion.
| Escrito una vez aqui, ese error no puede volver.
|
| USO
|
|     <?php
|         $tituloVista = 'Pagos';
|         $extras      = ['datatables'];      // opcional
|         require_once "app/views/inc/cabecera.php";
|     ?>
|     <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
|
| EXTRAS DISPONIBLES
|
|     datatables   la tabla, sus botones y el modo adaptable
|     dropzone     la zona de arrastrar archivos
|     lightbox     el visor de imagenes
|     select2      el desplegable con buscador
|
| Y $cabeceraExtra admite marcado suelto para lo que solo use una vista.
*/

$tituloVista   = $tituloVista   ?? '';
$extras        = $extras        ?? [];
$cabeceraExtra = $cabeceraExtra ?? '';

/* Cada extra, con sus hojas. Las rutas viven aqui y en ningun otro sitio. */
$hojasExtra = [
    'datatables' => [
        DS_HUB_URL . 'ds_core/assets/vendor/datatables2/css/dataTables.bootstrap5.min.css',
        DS_HUB_URL . 'ds_core/assets/vendor/datatables2/css/responsive.bootstrap5.min.css',
        DS_HUB_URL . 'ds_core/assets/vendor/datatables2/css/buttons.bootstrap5.min.css',
    ],
    'dropzone'   => [APP_URL . 'app/views/dist/plugins/dropzone/min/dropzone.min.css'],
    /* El visor ya no necesita hoja propia: usa el modal de Bootstrap y sus
       clases. La entrada se queda para no tocar el $extras de las siete
       vistas que la piden, pero no carga nada. */
    'lightbox'   => [],
    'select2'    => [
        APP_URL . 'app/views/dist/plugins/select2/css/select2.min.css',
        APP_URL . 'app/views/dist/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css',
    ],
];

/* SweetAlert2 lo cargaban en la CABECERA la mayoria de las vistas, no al
   final. No se cambia de sitio al unificar: mover un script de sitio es
   cambiar cuando se ejecuta, y eso no toca a una tarea que solo pretende
   dejar de repetir el mismo bloque setenta y tres veces. Se declara como
   extra para que cada vista conserve lo que tenia. */
$swalEnCabecera = in_array('swal', $extras, true);
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?><?php echo $tituloVista !== '' ? ' | ' . $tituloVista : ''; ?></title>
    <link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">

    <?php /* Tipografias e iconos. */ ?>
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">

<?php foreach ($extras as $extra): ?>
<?php     foreach ($hojasExtra[$extra] ?? [] as $hoja): ?>
    <link rel="stylesheet" href="<?php echo $hoja; ?>">
<?php     endforeach; ?>
<?php endforeach; ?>

    <?php /* El framework primero y la capa propia despues: al reves, las
             reglas de core.css pierden por orden de aparicion. */ ?>
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/css/overlayscrollbars.min.css">
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/css/adminlte.min.css">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/core.css'); ?>">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_futbol/app/views/dist/css/futbol.css'); ?>">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/dist/css/sweetalert2.min.css">
<?php if ($swalEnCabecera): ?>
    <script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js"></script>
<?php endif; ?>
<?php echo $cabeceraExtra; ?>

    <?php /* El testigo CSRF y el script que lo pega a cada peticion. */ ?>
    <?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>

    <?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
  </head>
