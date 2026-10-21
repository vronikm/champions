<?php
	use app\controllers\carnetController;

	$insCarnet = new carnetController();
    
    include 'app/lib/barcode.php';
	
	$generator = new barcode_generator();
	$optionsQR=array('sx'=>4,'sy'=>4,'p'=>-12);
    
    $alumnoid = ds_id_de_url($url, 1, APP_URL . 'carnetList/');
    $datos=$insCarnet->infoAlumnoCarnet($alumnoid);

    if(is_string($datos)){
        $alerta = json_decode($datos, true);
        die('<div style="font-family:sans-serif; padding:30px; color:#c0392b;">
                <h2>⚠️ ' . htmlspecialchars($alerta['titulo']) . '</h2>
                <p>' . htmlspecialchars($alerta['texto']) . '</p>
                <a href="javascript:history.back()">← Volver</a>
            </div>');
    }

	if($datos->rowCount()==1){
		$datos=$datos->fetch();
        
		if ($datos['alumno_imagen']!=""){
			$foto = media_url('alumno', $datos['alumno_imagen']);
		}else{
			$foto = media_url('alumno', 'alumno.png');
		}
    }

    // Obtener mes actual y el estado de pensión del alumno
    $estadoalumno=$insCarnet->EstadoAlumno($alumnoid);
    if($estadoalumno->rowCount()==1){
        $estadoalumno=$estadoalumno->fetch();
        $fechapension = $estadoalumno['FechaUltPension'];
        $mesActual = (int)date('n', strtotime($fechapension));
    }else{
        $mesActual = (int)date('n');
    }    
    
    // Obtener color asignado al mes actual
    $colorMes = $insCarnet->BuscarColorPorMes($mesActual);
    $colorHex = '#0000FF'; // Color por defecto (azul)
    $colorNombre = 'Azul';
    
    if($colorMes && $colorMes->rowCount() == 1) {
        $colorMes = $colorMes->fetch();
        $colorHex = $colorMes['color_hex'];
        $colorNombre = $colorMes['color_nombre'];
    }

    $lugar_sedeid = $datos['alumno_sedeid'];
	$sede=$insCarnet->informacionSede($lugar_sedeid);
    if($sede->rowCount()==1){
		$sede=$sede->fetch();
        
        $carnet_verticalprincipal = APP_URL.'app/views/imagenes/carnet/'.$sede['escuela_verticalprincipal'];
        $carnet_verticalfondo = APP_URL.'app/views/imagenes/carnet/'.$sede['escuela_verticalfondo'];        
        $carnet_verticalcolor = APP_URL.'app/views/imagenes/carnet/'.$sede['escuela_verticalcolor'];

        // El respaldo sede -> escuela ya lo resuelve ds_logo_url().
        $sede_foto = ds_logo_url((int)$sede["sede_id"]);
    }else{
        $carnet_verticalprincipal = APP_URL.'app/views/imagenes/carnet/vertical_principal.png';
        $carnet_verticalfondo = APP_URL.'app/views/imagenes/carnet/vertical_fondo.png';        
        $carnet_verticalcolor = APP_URL.'app/views/imagenes/carnet/vertical_azul.png';
    }    
    
    // Obtener nombre del mes en español
    $nombresMeses = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
    ];
    $nombreMesActual = $nombresMeses[$mesActual];
?>

<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Carnet de Alumno</title>
        <?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
        <link rel="icon" type="image/png" href="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>">
        <?php /* La version la pone ds_recurso() con la fecha del archivo, no una
                 cifra escrita a mano. Estaba fija en 1.1.5: una version que hay
                 que acordarse de subir funciona hasta el dia que se olvida, y
                 ese dia el cambio de estilos no llega al navegador y no se
                 distingue de un cambio que no se hizo. */ ?>
        <link rel="stylesheet" href="<?php echo ds_recurso('ds_futbol/app/views/dist/css/carnet_style.css'); ?>">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <style>
            /* Aplicar el color del mes a la máscara de color */
            .decorativo-color {
                filter: <?php 
                    // Convertir color hex a filtro CSS
                    // Esto aplica un tinte del color del mes sobre la imagen
                    echo "hue-rotate(0deg) saturate(100%) brightness(100%)"; 
                ?>;
                /* Aplicamos el color como overlay */
                background-blend-mode: multiply;
                background-color: <?php echo $colorHex; ?>;
            }
            
            /* Método alternativo: Usar mix-blend-mode para colorear la imagen */
            .capa-color-overlay {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: <?php echo $colorHex; ?>;
                mix-blend-mode: multiply;
                z-index: 2;
                pointer-events: none;
            }
        </style>
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
    </head>
    <body>
        <div class="carnet" id="carnet">

            <!-- LADO IZQUIERDO: Camiseta del jugador con color del mes -->
            <div class="decorativo-izquierda">
                <!-- Capa 1: Camiseta base del jugador (fondo) -->
                <img src="<?php echo $carnet_verticalfondo ?>" class="capa-camiseta" alt="Camiseta jugador">
                
                <!-- Capa 2: Máscara de color del mes (vertical_azul.png coloreada) -->
                <!-- <img src="<?php echo $carnet_verticalcolor ?>" class="capa-color" alt="Color del mes"> -->
                
                <!-- Overlay de color para teñir la imagen -->
                <div class="capa-color-overlay"></div>
            </div>

            <!-- LADO DERECHO: Imagen decorativa principal -->
            <img src="<?php echo $carnet_verticalprincipal ?>" class="decorativo-derecha" alt="Decorativo derecha">

            <div class="header">
                <!-- Logo centrado -->
                <img src="<?php echo $sede_foto; ?>" class="logo" alt="Logo Academia">

                <!-- Código QR alineado a la derecha -->
                <div class="qr">
                    <?php
                        ('Content-Type: image/svg+xml');
                        $svg = $generator->render_svg("qr","Estado pension: ".$estadoalumno["Condicion"]. "\n"."Fecha ultimo pago:".$estadoalumno["FechaUltPension"]. "\n". "Sede: ".$sede["sede_nombre"]."\n".$sede["sede_telefono"]."\n".$sede["sede_email"], $optionsQR); 
                                                                           
                        echo $svg;  
                    ?>
                </div>
            </div>

            <div class="content">                
                <div class="info">
                    <h3>DEPORTISTA</h3>
                    <h4><?= $datos['Nombres'].' '.$datos['Apellidos'] ?></h4>
                    <p><strong>C.I.:</strong> <?= $datos['alumno_identificacion'] ?></p>
                    <p><strong>Horario:</strong> <?= $datos['horario_nombre'] ?></p>
                    <p><strong>Edad:</strong> <?= date_diff(date_create($datos['alumno_fechanacimiento']), date_create('today'))->y ?> años</p>
                    
                    <!-- Badge con el mes y su color -->
                    <p>
                        <strong>Mes vigencia:</strong>
                        <span class="badge-mes" style="background-color: <?php echo $colorHex; ?>">
                            <?= $nombreMesActual ?>
                        </span>
                    </p>
                    <p><strong>Sede:</strong> <?= $sede['sede_nombre'] ?></p>
                    <p style="font-size: 11px;"><?= ds_email_organizacion((int)$sede['sede_id']) ?></br> <?= ds_telefono_organizacion((int)$sede['sede_id']) ?></p>
                </div>
                <div class="foto">
                    <img src="<?php echo $foto; ?>" alt="Foto alumno">
                </div>             
            </div>    
        </div>

        <button id="descargar">Descargar PDF</button>

        <script src="<?php echo APP_URL; ?>app/views/dist/js/carnet_pdf.js"></script>
        <script>
            var datosPersona = {
                nombres: '<?= addslashes($datos["Nombres"]) ?>',
                apellidos: '<?= addslashes($datos["Apellidos"]) ?>'
            };
        </script>
    </body>
</html>