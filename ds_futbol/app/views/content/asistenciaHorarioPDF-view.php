<?php	

    use app\controllers\asistenciaController;

    include 'app/lib/barcode.php';
    include 'app/lib/fpdf.php';

    $generator = new barcode_generator();
    $symbology="qr";
    $optionsQR=array('sx'=>4,'sy'=>4,'p'=>-12);
    
    $insHorario = new asistenciaController();	
	$horario_id = ds_id_de_url($url, 1, APP_URL . 'asistenciaListHorario/');
    $filename = "app/views/dist/img/temp/".$horario_id.".jpeg";

	$datoshorario=$insHorario->seleccionarDatos("Unico","asistencia_horario","horario_id",$horario_id);
	if($datoshorario->rowCount()==1){
		$datoshorario=$datoshorario->fetch();
		$lugar_sedeid 		= $datoshorario['horario_sedeid'];
		$horario_nombre 	= $datoshorario['horario_nombre'];
		$horario_detalle	= $datoshorario['horario_detalle'];
		$horario_estado		= $datoshorario['horario_estado'];
         
	}else{
		$lugar_sedeid = isset($_POST['horario_sedeid']) ? $insHorario->limpiarCadena($_POST['horario_sedeid']) : 0;
		$horario_nombre 	= "";
		$horario_detalle	= "";
		$horario_estado		= "";
	}

	$sede=$insHorario->informacionSede($lugar_sedeid);
	if($sede->rowCount()==1){
		$sede=$sede->fetch(); 
    } else {
			/* Sin registro, $sede seguiria siendo el statement y la
			   vista lo usaria como array: error fatal en pantalla, con la
			   ruta del servidor dentro. Se vuelve a donde ya vuelve esta
			   misma vista cuando el identificador no sirve. */
			header("Location: " . APP_URL . 'asistenciaListHorario/');
			exit();
		}

    $HorarioPDF = $insHorario->HorarioPDF($horario_id);
    if($HorarioPDF->rowCount()==1){
	    $HorarioPDF=$HorarioPDF->fetch(); 
    }

    $CanchaPDF = $insHorario->CanchaPDF($horario_id);
    if($CanchaPDF->rowCount()==1){
	    $CanchaPDF=$CanchaPDF->fetch(); 
    }

    $ProfesorPDF = $insHorario->ProfesorPDF($horario_id);
    if($ProfesorPDF->rowCount()==1){
	    $ProfesorPDF=$ProfesorPDF->fetch(); 
    }
   											
    $data="Horario ".$horario_nombre. " | "."\n".ds_nombre_organizacion($sede["sede_id"])."\n".$sede["sede_telefono"]."\n".$sede["sede_email"];

    $image = $generator->render_image($symbology, $data, $optionsQR);
    imagejpeg($image, $filename);
    imagedestroy($image);

    //$pdf = new FPDF( 'P', 'mm', 'A4' );	
    $pdf = new FPDF('L', 'mm', array(130,210));

    // on sup les 2 cm en bas
    $pdf->SetAutoPagebreak(False);
    $pdf->SetMargins(0,0,0);	    
 	   
    $pdf->AddPage();

    // logo : 80 de largo por 55 de alto
    //,,ancho,

    $__img = ds_logo_ruta((int)$sede["sede_id"]);
    if (is_file($__img)) $pdf->Image($__img, 54, 10, 26, 26);

    $pdf->SetLineWidth(0.1); $pdf->Rect(10, 10, 190, 40, "D"); $x=15; $y=13;
  
    $pdf->SetXY( $x, $y ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 260, 8, ds_nombre_organizacion_may($sede["sede_id"]), 0, 0, 'C'); $y+=5;
    $pdf->SetXY( $x, $y ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 260, 8, $sede['sede_nombre'], 0, 0, 'C'); $y+=5;

    $pdf->SetXY( $x+8, $y+10); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell(5, 10, mb_convert_encoding("Dirección:", 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->SetXY( $x, $y+10); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell( 123, 10, mb_convert_encoding($sede["sede_direccion"], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C'); $y+=5;
    $pdf->SetXY( $x, $y+10); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell(85, 10, mb_convert_encoding("Celular:", 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->SetXY( $x+10, $y+10); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell( 95, 10, mb_convert_encoding($sede["sede_telefono"], 'ISO-8859-1', 'UTF-8'), 0, 0,'C');

    $pdf->SetLineWidth(0.1); $pdf->Rect(125, 35, 60, 10, "D");
    $pdf->Line(125, 38, 185, 38);
    $pdf->SetXY( 120, 32.5); $pdf->SetFont( "Arial", "", 7 ); $pdf->Cell( 19, 2, mb_convert_encoding("Fecha ", 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->SetXY( 125, 32.5); $pdf->SetFont( "Arial", "", 5 ); $pdf->Cell( 20, 8, "DIA", 0, 0, 'C');
    $pdf->SetXY( 145, 32.5); $pdf->SetFont( "Arial", "", 5 ); $pdf->Cell( 20, 8, "MES", 0, 0, 'C');
    $pdf->SetXY( 165, 32.5); $pdf->SetFont( "Arial", "", 5 ); $pdf->Cell( 20, 8, mb_convert_encoding("AÑO", 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    //FECHA VARIABLE
    $pdf->SetXY( 125, 38); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell( 20, 8, date('d', strtotime(date('Y-m-d'))), 0, 0, 'C');
    $pdf->SetXY( 145, 38); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell( 20, 8,date('m', strtotime(date('Y-m-d'))), 0, 0, 'C');
    $pdf->SetXY( 165, 38); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell( 20, 8, date('Y', strtotime(date('Y-m-d'))), 0, 0, 'C');
  
    $pdf->Line(140, 35, 140, 45);
    $pdf->Line(160, 35, 160, 45);

    $pdf->SetLineWidth(0.1); $pdf->Rect(10, 51, 190, 70, "D");
    //margen, alto izquierdo de separación de línea, ancho de la fila de lado a lado, alto derecho de separación de línea

    $pdf->Line(10, 60, 200, 60);
    $pdf->Line(10, 67, 200, 67);
    $pdf->Line(10, 74, 200, 74);
    $pdf->Line(10, 81, 200, 81);
    $pdf->Line(10, 88, 200, 88);   

    $pdf->SetXY( 15, 52 ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 20, 8, mb_convert_encoding("Horario ".$horario_nombre.". ".$horario_detalle, 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $C = 27;
    $CC = 35;
    $pdf->Line($C, 60, $C, 88); $C+=$CC;
    $pdf->Line($C, 60, $C, 88); $C+=$CC;
    $pdf->Line($C, 60, $C, 88); $C+=$CC;
    $pdf->Line($C, 60, $C, 88); $C+=$CC;
    $pdf->Line($C, 60, $C, 88);

    $pdf->SetXY( 38, 63.5 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "LUNES", 0, 0, 'L');    
    $pdf->SetXY( 70, 63.5 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "MARTES", 0, 0, 'L');
    $pdf->SetXY( 103, 63.5 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "MIERCOLES", 0, 0, 'L');
    $pdf->SetXY( 140, 63.5 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "JUEVES", 0, 0, 'L');
    $pdf->SetXY( 175, 63.5 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "VIERNES", 0, 0, 'L');

    $pdf->SetXY( 10, 71 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "Horario", 0, 0, 'L');
    $pdf->SetXY( 10, 78); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "Cancha", 0, 0, 'L');
    $pdf->SetXY( 10, 85); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "Profesor", 0, 0, 'L');

    $pdf->SetXY( 30, 71 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, $HorarioPDF['Lunes'], 0, 0, 'L');
    $pdf->SetXY( 65, 71 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0,$HorarioPDF['Martes'], 0, 0, 'L');
    $pdf->SetXY( 101, 71 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, $HorarioPDF['Miercoles'], 0, 0, 'L');
    $pdf->SetXY( 136, 71 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, $HorarioPDF['Jueves'], 0, 0, 'L');
    $pdf->SetXY( 170, 71 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, $HorarioPDF['Viernes'], 0, 0, 'L');

    $pdf->SetXY( 27, 78 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($CanchaPDF['Lunes']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->SetXY( 62, 78 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($CanchaPDF['Martes']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->SetXY( 97, 78 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($CanchaPDF['Miercoles']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->SetXY( 132, 78 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($CanchaPDF['Jueves']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->SetXY( 167, 78 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($CanchaPDF['Viernes']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
   
    $pdf->SetXY( 27, 85 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($ProfesorPDF['Lunes']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->SetXY( 62, 85 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($ProfesorPDF['Martes']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->SetXY( 97, 85 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($ProfesorPDF['Miercoles']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->SetXY( 132, 85 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($ProfesorPDF['Jueves']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
    $pdf->SetXY( 167, 85 ); $pdf->SetFont( "Arial", "", 8 ); $pdf->Cell( 20, 0, mb_convert_encoding($ProfesorPDF['Viernes']?? '', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
     
    $pdf->Image(APP_URL.$filename, 171, 93, 23, 23);
      
    unlink($filename);
   
    $pdf->Output("$horario_id.pdf","I","T");
    
  
