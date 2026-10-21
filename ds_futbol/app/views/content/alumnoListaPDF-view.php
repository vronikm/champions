<?php	

    use app\controllers\alumnoController;

    include 'app/lib/barcode.php';
    include 'app/lib/fpdf.php';

    $insLista    = new alumnoController();

    $generator = new barcode_generator();
    $symbology="qr";
    $optionsQR=array('sx'=>4,'sy'=>4,'p'=>-12);
    $filename = "app/views/dist/img/temp/";

    /* Los dos son opcionales: 0 significa "todas". Pero había que leerlos
       con isset: al faltar el segmento en la URL, PHP emitía un aviso, ese
       aviso ya era salida enviada y FPDF abortaba con "Some data has
       already been output". El PDF moría por un warning, no por los datos. */
    $categoriaid = isset($url[1]) && $url[1] !== "" ? (int)$url[1] : 0;
    $sedeid      = isset($url[2]) && $url[2] !== "" ? (int)$url[2] : 0;

    if($sedeid == 0){
        $sedeid_Logo = 1;
    }else{
        $sedeid_Logo = $sedeid;
    }

    $sede=$insLista->informacionSede( $sedeid_Logo);
	if($sede->rowCount()==1){
		$sede=$sede->fetch(); 
	}
 
    $pdf = new FPDF( 'P', 'mm', 'A4' );	
    // on sup les 2 cm en bas
    $pdf->SetAutoPagebreak(False);
    $pdf->SetMargins(0,0,0);	    
 	   
    $pdf->AddPage();
    $__img = ds_logo_ruta((int)$sede["sede_id"]);
    if (is_file($__img)) $pdf->Image($__img, 24, 10, 47, 26);
    $pdf->SetLineWidth(0.1); $pdf->Rect(10, 10, 190, 35, "D"); $x=15; $y=13;  
    $pdf->SetXY( $x, $y ); $pdf->SetFont( "Arial", "B", 12 ); $pdf->Cell( 240, 10, ds_nombre_organizacion_may($sede["sede_id"]), 0, 0, 'C'); $y+=5;
    $pdf->SetXY( $x, $y); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell(240, 10, mb_convert_encoding("Dirección: ".$sede["sede_direccion"], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C'); $y+=5;
    $pdf->SetXY( $x, $y); $pdf->SetFont( "Arial", "", 9 ); $pdf->Cell(240, 10, mb_convert_encoding("Celular: ".$sede["sede_telefono"], 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
    $pdf->SetXY( $x, $y ); $pdf->SetFont( "Arial", "B", 12 ); $pdf->Cell( 190, 30, "REPORTE DE ALUMNOS", 0, 0, 'C'); $y+=5; 

    $tabla="";
    $x=10; $y=60;    
    $pdf->SetMargins(10, 10, 10);

    // Función para crear los encabezados de la tabla
    function crearEncabezados($pdf) {
        $pdf->SetFont( "Arial", "B", 10 );
        $pdf->Ln(23);
        $pdf->Cell( 10, 8, "No.", 1, 0, 'C'); //alineación: 'L' alineación a la izquierda (predeterminado), 'C' para centrar o 'R' alineación a la derecha.       
        $pdf->Cell( 25, 8, "SEDE", 1);
        $pdf->Cell( 30, 8, mb_convert_encoding("IDENTIFICACIÓN", 'ISO-8859-1', 'UTF-8'), 1, 0, 'C');
        $pdf->Cell( 45, 8, "NOMBRES", 1); //$pdf->Cell(ancho, alto, 'texto', borde, salto, 'alineacion');   
        $pdf->Cell( 50, 8, "APELLIDOS", 1); //$pdf->Cell(ancho, alto, 'texto', borde, salto, 'alineacion');  
        $pdf->Cell( 30, 8, "F. NACIMIENTO", 1, 0, 'C');
        $pdf->Ln(2);
    }

    // Crear los encabezados de la primera página
    crearEncabezados($pdf);

    $lista=$insLista->listarAlumnosPDF($categoriaid,$sedeid);

    if($lista->rowCount()>0){
		$lista=$lista->fetchAll(); 
        $pdf->SetFont( "Arial", "", 10 );
        $lineNumber = 1; // Inicializa el contador de líneas
        foreach($lista as $rows){
            // Verificar si hay suficiente espacio en la página
            if ($pdf->GetY() > 260) { // Aproximadamente el final de la página
                $pdf->AddPage(); // Agregar una nueva página
                crearEncabezados($pdf); // Crear los encabezados en la nueva página

                // Después de los encabezados, volver a configurar la fuente normal
                $pdf->SetFont('Arial', '', 10);
            }
            $pdf->Ln(6);
            $pdf->Cell( 10, 8, $lineNumber, 1); // Imprime el número de línea
            $pdf->Cell( 25, 8, $rows['sede_nombre'], 1, 0, 'C');
            $pdf->Cell( 30, 8, $rows['alumno_identificacion'], 1, 0, 'C');
            $pdf->Cell( 45, 8, mb_convert_encoding($rows['alumno_primernombre'].' '.$rows['alumno_segundonombre'], 'ISO-8859-1', 'UTF-8'), 1);            
            $pdf->Cell( 50, 8, mb_convert_encoding($rows['alumno_apellidopaterno'].' '.$rows['alumno_apellidomaterno'], 'ISO-8859-1', 'UTF-8'), 1);
            $pdf->Cell( 30, 8, $rows['alumno_fechanacimiento'], 1, 0, 'C');
            $lineNumber++; // Incrementa el número de línea
            $pdf->Ln(2);
        }   
    }    
    // Salto de línea antes de las firmas
    $pdf->Ln(15);
   
    $pdf->Output(ds_nombre_archivo("Alumnos").".pdf","I","T");

    