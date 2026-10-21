<?php	

    use app\controllers\asistenciaController;

    include 'app/lib/barcode.php';
    include 'app/lib/fpdf.php';

    $generator = new barcode_generator();
    $symbology="qr";
    $optionsQR=array('sx'=>4,'sy'=>4,'p'=>-12);
    $filename = "app/views/dist/img/temp/";

    $insListaHorario = new asistenciaController();	
	$horario_id = ds_id_de_url($url, 1, APP_URL . 'asistenciaHorarioLista/');
    $datos=$insListaHorario->BuscarHorarioSede($horario_id);

    if($datos->rowCount()==1){
		$datos=$datos->fetch();
		$horario_nombre     = $datos['horario_nombre'];
        $horario_detalle    = $datos['horario_detalle'];
        $horario_sedeid     = $datos['horario_sedeid'];
        $horario_sede       = $datos['sede_nombre'];
        $filename .= "alumno.jpg";
	}else{
		$horario_nombre 	= "";
		$horario_detalle	= "";
        $horario_sedeid     = "";
        $horario_sede	    = "";
	}

	$sede=$insListaHorario->informacionSede($horario_sedeid);
	if($sede->rowCount()==1){
		$sede=$sede->fetch(); 
    } else {
			/* Sin registro, $sede seguiria siendo el statement y la
			   vista lo usaria como array: error fatal en pantalla, con la
			   ruta del servidor dentro. Se vuelve a donde ya vuelve esta
			   misma vista cuando el identificador no sirve. */
			header("Location: " . APP_URL . 'asistenciaHorarioLista/');
			exit();
		}

    $data="alumno.jpg";
    $image = $generator->render_image($symbology, $data, $optionsQR);
    imagejpeg($image, $filename);
    imagedestroy($image);

    $pdf = new FPDF( 'P', 'mm', 'A4' );	
    // on sup les 2 cm en bas
    $pdf->SetAutoPagebreak(False);
    $pdf->SetMargins(0,0,0);	    
 	   
    $pdf->AddPage();
    $__img = ds_logo_ruta((int)$sede["sede_id"]);
    if (is_file($__img)) $pdf->Image($__img, 24, 10, 47, 26);
    $pdf->SetLineWidth(0.1); $pdf->Rect(10, 10, 190, 40, "D"); $x=15; $y=13;  
    $pdf->SetXY( $x, $y ); $pdf->SetFont( "Arial", "B", 12 ); $pdf->Cell( 240, 10, ds_nombre_organizacion_may($sede["sede_id"]), 0, 0, 'C'); $y+=5;
    $pdf->SetXY( $x, $y); $pdf->SetFont( "Arial", "", 10 ); $pdf->Cell(240, 12, mb_convert_encoding("Dirección: ".$sede["sede_direccion"], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C'); $y+=5;
    $pdf->SetXY( $x, $y); $pdf->SetFont( "Arial", "", 10); $pdf->Cell(240, 12, mb_convert_encoding("Correo: ".$sede["sede_email"], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C'); $y+=5;
    $pdf->SetXY( $x, $y); $pdf->SetFont( "Arial", "", 10); $pdf->Cell(240, 12, mb_convert_encoding("Celular: ".$sede["sede_telefono"], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');    
    $pdf->SetXY( $x, $y ); $pdf->SetFont( "Arial", "B", 12 ); $pdf->Cell( 180, 35, mb_convert_encoding("HORARIO: ".$horario_nombre."   DETALLE: ".$horario_detalle, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

    $tabla="";
    $x=10; $y=60;    
    $pdf->SetMargins(10, 10, 10);

    // Función para crear los encabezados de la tabla
    function crearEncabezados($pdf) {
        $pdf->SetFont( "Arial", "B", 10 );
        $pdf->Ln(23);
        $pdf->Cell( 10, 8, "No.", 1, 0, 'C'); //alineación: 'L' alineación a la izquierda (predeterminado), 'C' para centrar o 'R' alineación a la derecha.       
        $pdf->Cell( 90, 8, "APELLIDOS Y NOMBRES", 1); //$pdf->Cell(ancho, alto, 'texto', borde, salto, 'alineacion');  
        $pdf->Cell( 30, 8, "CEDULA", 1, 0, 'C');
        $pdf->Cell( 30, 8, "CATEGORIA", 1, 0, 'C');  
        $pdf->Cell( 30, 8, "No. CAMISETA", 1, 0, 'C');
        $pdf->Ln(2);
    }

    // Crear los encabezados de la primera página
    crearEncabezados($pdf);

    $listaHorario=$insListaHorario->listaHorarioPDF($horario_id);
    if($listaHorario->rowCount()>0){
		$listaHorario=$listaHorario->fetchAll(); 
        $pdf->SetFont( "Arial", "", 10 );
        $lineNumber = 1; // Inicializa el contador de líneas
        foreach($listaHorario as $rows){
            // Verificar si hay suficiente espacio en la página
            if ($pdf->GetY() > 260) { // Aproximadamente el final de la página
                $pdf->AddPage(); // Agregar una nueva página
                crearEncabezados($pdf); // Crear los encabezados en la nueva página

                // Después de los encabezados, volver a configurar la fuente normal
                $pdf->SetFont('Arial', '', 10);
            }
            $pdf->Ln(6);
            $pdf->Cell( 10, 8, $lineNumber, 1); // Imprime el número de línea
            $pdf->Cell( 90, 8, mb_convert_encoding($rows['APELLIDOS'].' '.$rows['NOMBRES'], 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->Cell( 30, 8, $rows['CEDULA'], 1, 0, 'C');
            $pdf->Cell( 30, 8, $rows['CATEGORIA'], 1, 0, 'C');
            $pdf->Cell( 30, 8, $rows['NUMCAMISETA'], 1, 0, 'C');
            $lineNumber++; // Incrementa el número de línea
            $pdf->Ln(2);
        }   
    }    
    // Salto de línea antes de las firmas
    $pdf->Ln(15);

    // Fila para las firmas
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(90, 10, mb_convert_encoding(ds_nombre_organizacion((int)$sede["sede_id"]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->Cell(90, 10, 'Profesor a cargo', 0, 1, 'C');

    // Líneas para las firmas
    $pdf->Cell(90, 10, '_________________________', 0, 0, 'C');
    $pdf->Cell(90, 10, '_________________________', 0, 1, 'C');

    unlink($filename);
    $pdf->Output($datos['horario_nombre'].".pdf","I","T");