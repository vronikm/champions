<?php
	use app\controllers\pagosController;
	include 'app/lib/barcode.php';	
	include 'app/lib/fpdf.php';

	$generator = new barcode_generator();
    	$symbology="qr";
    	$optionsQR=array('sx'=>4,'sy'=>4,'p'=>-12);
    	$filename = "app/views/dist/img/temp/";
	
	$insAlumno = new pagosController();	
	$pagoid = ds_id_de_url($url, 1, APP_URL . 'pagosList/');

	$datos=$insAlumno->generarRecibo($pagoid);

	if($datos->rowCount()==1){
		$datos=$datos->fetch(); 

		$fecha_recibo = strrev($datos["pago_recibo"]);
		$first12Chars =  strrev(substr($datos["pago_recibo"], 0, 12));
		$nombre_sede  = $datos["sede_nombre"];

		$pairs = [];
		$length = strlen($first12Chars);

		for ($i = 0; $i < $length; $i += 2) {
			$pairs[] = substr($first12Chars, $i, 2);
		}
		$recibo_hora = $pairs[4].":".$pairs[2].":".$pairs[0];

		 $filename .= $datos["pago_recibo"].".jpeg";

	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "pagosList/");
		exit();
	}

	$sede=$insAlumno->informacionSede($datos["alumno_sedeid"]);
	if($sede->rowCount()==1){
		$sede=$sede->fetch(); 
    }

    $data="Recibo ".$datos["pago_recibo"]. "\n".$datos["pago_fecharegistro"]. " | ".$recibo_hora."\n".$sede['sede_nombre']."\n".$sede["sede_telefono"]."\n".$sede["sede_email"];

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
    ds_logo_dibujar($pdf, (int)$sede["sede_id"], 52, 10, 28, 28);   /* a su proporción, sin estirar */

        $pdf->SetLineWidth(0.1); $pdf->Rect(10, 10, 190, 40, "D"); $x=15; $y=13;       		

		$pdf->SetXY( $x, $y ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 290, 18, ds_nombre_organizacion_may($sede["sede_id"]), 0, 0, 'C'); $y+=5;
		$pdf->SetXY( $x, $y ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 260, 8, "", 0, 0, 'C'); $y+=17;

		$pdf->SetXY( $x, $y); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell(100, 8, mb_convert_encoding("Dirección: ".$sede["sede_direccion"], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C'); $y+=5;
		$pdf->SetXY( $x, $y); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell(100, 8, mb_convert_encoding("Celular: ".$sede["sede_telefono"], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

        $pdf->SetLineWidth(0.1); $pdf->Rect(130, 35, 60, 10, "D");
        $pdf->Line(130, 38, 190, 38);
        $pdf->SetXY( 130, 32.5); $pdf->SetFont( "Arial", "", 7 ); $pdf->Cell( 19, 2, mb_convert_encoding("Fecha de emisión", 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        $pdf->SetXY( 130, 32.5); $pdf->SetFont( "Arial", "", 5 ); $pdf->Cell( 20, 8, "DIA", 0, 0, 'C');
        $pdf->SetXY( 150, 32.5); $pdf->SetFont( "Arial", "", 5 ); $pdf->Cell( 20, 8, "MES", 0, 0, 'C');
        $pdf->SetXY( 170, 32.5); $pdf->SetFont( "Arial", "", 5 ); $pdf->Cell( 20, 8, mb_convert_encoding("AÑO", 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');

        //FECHA VARIABLE
        $pdf->SetXY( 130, 38); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell( 20, 8, date('d', strtotime($datos['pago_fecharegistro'])), 0, 0, 'C');
        $pdf->SetXY( 150, 38); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell( 20, 8,date('m', strtotime($datos['pago_fecharegistro'])), 0, 0, 'C');
        $pdf->SetXY( 170, 38); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell( 20, 8, date('Y', strtotime($datos['pago_fecharegistro'])), 0, 0, 'C');

        $pdf->Line(150, 35, 150, 45);
        $pdf->Line(170, 35, 170, 45);

        $pdf->SetLineWidth(0.1); $pdf->Rect(10, 51, 190, 70, "D");
        //margen, alto izquierdo de separación de línea, ancho de la fila de lado a lado, alto derecho de separación de línea
        $pdf->Line(10, 60, 200, 60);
        $pdf->Line(10, 67, 200, 67);
        $pdf->Line(10, 74, 200, 74);
        $pdf->Line(10, 81, 200, 81);
        $pdf->Line(10, 88, 200, 88);

        $pdf->SetXY( 15, 52 ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 20, 8, "POR ", 0, 0, 'L');
        $pdf->SetXY( 46, 52 ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 20, 8, "$".$datos['PAGO_INICIAL'], 0, 0, 'L');
	    $pdf->SetXY( 120, 52 ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 20, 8, "RECIBO", 0, 0, 'C');
        $pdf->SetXY( 175, 52 ); $pdf->SetFont( "Arial", "B", 11 ); $pdf->Cell( 20, 8, $datos['pago_recibo'], 0, 0, 'R');

        $pdf->SetXY( 15, 62 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 4, "Recibo de:", 0, 0, 'L');
        $pdf->SetXY( 46, 62 ); $pdf->SetFont( "Arial", "", 10 ); $pdf->Cell( 20, 4, mb_convert_encoding($datos['alumno_primernombre']." ".$datos['alumno_segundonombre']." ".$datos['alumno_apellidopaterno']." ".$datos['alumno_apellidomaterno']." (".date('Y', strtotime($datos['alumno_fechanacimiento'])).")", 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');

        $pdf->SetXY( 15, 71 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 0, "La cantidad de:", 0, 0, 'L');
        $pdf->SetXY( 46, 71 ); $pdf->SetFont( "Arial", "", 10 ); $pdf->Cell( 20, 0, mb_convert_encoding(ucfirst($insAlumno->textoLetras($datos['PAGO_INICIAL'])), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');

        $pdf->SetXY( 15, 80 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, -3, "Por concepto de:", 0, 0, 'L');
        /* «Por concepto de»: el rubro, el torneo y el período, y el detalle tras una coma,
           sin huecos si falta alguno; encajado en su renglón (74-81 mm) aunque sea largo. */
        $concepto = implode(' ', array_filter(array_map('trim', [(string)$datos['RUBRO'], (string)$datos['torneo_nombre'], (string)$datos['pago_periodo']]), 'strlen'));
        $detalle = trim((string)$datos['pago_concepto']);
        ds_pdf_texto_encajado($pdf, mb_convert_encoding($concepto . ($detalle !== '' ? ', ' . $detalle : ''), 'ISO-8859-1', 'UTF-8'), 46, 74.5, 152, 6.5);

        $pdf->SetXY( 15, 89 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, -7, "Forma de pago:", 0, 0, 'L');
        $pdf->SetXY( 46, 89 ); $pdf->SetFont( "Arial", "", 10 ); $pdf->Cell( 20, -7, mb_convert_encoding($datos['FORMAPAGO'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        
        //Sección de totales y firmas
        //ubicacion horizontal, ubicacion vertical, ancho, alto bajo
        $pdf->SetLineWidth(0.1); $pdf->Rect(100, 88, 100, 33, "D"); $x=5; $y=13;        


        //margen, alto izquierdo de separación de línea, ancho de la fila de lado a lado, alto derecho de separación de línea
        $pdf->Line(10, 97, 100, 97);
        $pdf->Line(10, 105, 100, 105);
        $pdf->Line(10, 113, 100, 113);

        $pdf->SetXY( 20, 87 ); $pdf->SetFont( "Arial", "", 12 ); $pdf->Cell( 12, 13, "MONTO", 0, 0, 'R');
        $pdf->SetXY( 20, 87 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 17, 29, "SUBTOTAL:", 0, 0, 'R');
        $pdf->SetXY( 20, 88 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 11, 43, "ABONO:", 0, 0, 'R');
        $pdf->SetXY( 20, 90 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 10, 56, "SALDO:", 0, 0, 'R');


        $pdf->SetXY( 41, 52 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 99, "$".number_format($datos['DEUDA_INICIAL'], 2), 0, 0, 'R');
        $pdf->SetXY( 41, 59 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 101, "$".$datos['PAGO_INICIAL'], 0, 0, 'R');
        $pdf->SetXY( 41, 66 ); $pdf->SetFont( "Arial", "B", 10 ); $pdf->Cell( 20, 104, "$".$datos['SALDO_INICIAL'], 0, 0, 'R');


        /* Sin firma ni «FIRMA AUTORIZADA»: el QR va centrado en el recuadro derecho (100–200 × 88–121). */
    /* El QR, por la ruta en disco y no por URL: con la URL el servidor se descargaba su propia imagen por HTTP, y en el hosting eso falla («Missing or incorrect image file»). */
    $pdf->Image($filename, 136.5, 91, 27, 27);
        
        unlink($filename);

	//echo "$fecha";		
	//$pdf->Output("recibos/recibo-".$num.".pdf","F","T");

        $pdf->Output("app/views/dist/pdf/".$datos['pago_recibo'].".pdf","F","T");
	//$path = "bookings/".$fecha.$file."booking.pdf";  

        //Envio de correo
	// Datos del correo
	$to = $datos['CORREO_REP'];
	$subject = $datos['RUBRO']." ".$datos['pago_periodo'];
	$subject = mb_convert_encoding(	$subject, 'ISO-8859-1', 'UTF-8');
	
	$message = "Estimad@ ".$datos['REPRESENTANTE'].",
    Adjunto al presente correo encontrará el recibo de pago de su representad@ ".$datos['alumno_primernombre']." ".$datos['alumno_segundonombre']." ".$datos['alumno_apellidopaterno']." ".$datos['alumno_apellidomaterno'].", por el rubro ".$datos['RUBRO']." ".$datos['pago_periodo'].".
    
    NOTA: Este correo electrónico servirá como recibo oficial de su pago.
    
    ".ds_nombre_organizacion_may((int)$sede["sede_id"]);
    
	$message = mb_convert_encoding($message, 'ISO-8859-1', 'UTF-8');

	$from = ds_email_organizacion();
	$headers = "From: " . $from;

	// Ruta del archivo adjunto
	$file_path = "app/views/dist/pdf/".$datos['pago_recibo'].".pdf";
	$file_name = basename($file_path);
	$file_size = filesize($file_path);
	$file_type = mime_content_type($file_path);
	
	// Leer el contenido del archivo y codificarlo en base64
	$file_content = chunk_split(base64_encode(file_get_contents($file_path)));

	// Generar un límite único para los encabezados MIME
	$uid = md5(uniqid(time()));

	// Encabezados MIME del correo
	$headers .= "\r\nMIME-Version: 1.0\r\n";
	$headers .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";

	// Cuerpo del correo en formato MIME
	$body = "--".$uid."\r\n";
	$body .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
	$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
	$body .= $message."\r\n\r\n";
	$body .= "--".$uid."\r\n";
	$body .= "Content-Type: ".$file_type."; name=\"".$file_name."\"\r\n";
	$body .= "Content-Transfer-Encoding: base64\r\n";
	$body .= "Content-Disposition: attachment; filename=\"".$file_name."\"\r\n\r\n";
	$body .= $file_content."\r\n\r\n";
	$body .= "--".$uid."--";
    
	/*
	| UNA SOLA REDIRECCIÓN, CON EL RESULTADO
	|
	| Antes se redirigía al recibo SIN resultado antes de enviar, y después
	| se intentaba otra vez con /1/ o /0/. Pero entre medias PHP ya había
	| escrito una advertencia —mail() sin servidor, o el segundo unlink() de
	| un archivo ya borrado— y con salida enviada la segunda cabecera no
	| se aplica: el recibo nunca decía si el correo salió. Ahora la
	| advertencia se captura, el PDF temporal se borra una vez y se vuelve
	| al recibo con el resultado: 1 enviado, 0 falló, 2 sin correo.
	*/
	@unlink($file_path);
	if (!filter_var(trim((string)$to), FILTER_VALIDATE_EMAIL)) {
		$envio = 2;
	} else {
		$avisoCorreo = '';
		set_error_handler(function ($n, $texto) use (&$avisoCorreo) { $avisoCorreo = trim((string)$texto); return true; });
		try {
			$envio = mail($to, $subject, $body, $headers) ? 1 : 0;
		} finally {
			restore_error_handler();
		}
		if ($envio === 0) { error_log('[recibo ' . $datos['pago_recibo'] . '] mail() no envió: ' . $avisoCorreo); }
	}
	header("Location: ".APP_URL."pagosRecibo/".$datos['pago_id']."/$envio/");
	exit();

     //header("Location: ../presupuestos_from.php?idprof=".$idprof."&id=".$cliente_id);
   	// Envio de correo -----------------------------------
