<?php	
    setlocale(LC_TIME, 'es_ES.UTF-8', 'es_ES', 'esp');
    use app\controllers\representanteController;

    include 'app/lib/fpdf.php';

    $insfrmLPD = new representanteController();	
	$repreid = ds_id_de_url($url, 1, APP_URL . 'representanteList/');

	$datos=$insfrmLPD->datosRepresentante($repreid);
	
	if($datos->rowCount()==1){
		$datos=$datos->fetch();
		$repre_sedeid = $datos['SEDE'];	
	} else {
		/* El registro no existe: se vuelve al listado. Antes se
		   incluía el aviso pero la vista seguía ejecutando con
		   $datos aún como PDOStatement y moría más abajo. */
		header("Location: " . APP_URL . "representanteList/");
		exit();
	}

	$sede=$insfrmLPD->informacionSede($repre_sedeid);
	if($sede->rowCount()==1){
		$sede=$sede->fetch(); 
		$sede_nombre  = $sede['sede_nombre'];
		$sede_email   = $sede['sede_email'];
        $sede_logo    = $sede['sede_foto'];
	}
    /*
    | EL LOGO SE LEE DEL DISCO, NO POR HTTP
    |
    | Aquí se usaba ds_logo_url(), que devuelve una URL, y se le pasaba a
    | FPDF::Image(). Funcionaba de casualidad: obliga al servidor a pedirse
    | su propia imagen por la red para estampar un PDF, y FPDF deduce el
    | formato de la extensión del nombre, así que cualquier cosa detrás del
    | «.png» —una marca de versión, por ejemplo— dejaba el formulario sin
    | generarse. Los otros diez PDF del sistema ya usaban ds_logo_ruta().
    |
    | is_file() antes de asignar: FPDF lanza excepción si el archivo no
    | está, y este formulario es el consentimiento de datos personales. Sin
    | logo se emite igual; sin PDF, no.
    */
    $logo = ds_logo_ruta((int)$repre_sedeid);
    if ($logo !== '' && !is_file($logo)) {
        $logo = '';
    }

    // Configura el idioma español
    $formatter = new IntlDateFormatter(
        'es_ES', // Idioma y región
        IntlDateFormatter::LONG, // Formato de fecha
        IntlDateFormatter::SHORT // Formato de hora
    );

    // Obtén la fecha actual
    $fechaHora = $formatter->format(new DateTime());

    // Crear una clase personalizada para el PDF
    class PDF extends FPDF {
        public $title;
        public $subtitle;
        public $logoPath;

        // Método para establecer datos dinámicos
        public function __construct($title = '', $logoPath = '') {
            parent::__construct(); // Llamar al constructor de la clase FPDF
            $this->title    = $title;
            $this->logoPath = $logoPath;            
        }

        // Agregar un encabezado
        function Header() {
            /* Por el ayudante común: sin zlib, un logo PNG con transparencia
               rompía el PDF entero (ds_pdf_imagen_encajada lo aplana). */
            if (!empty($this->logoPath)) {
                ds_pdf_imagen_encajada($this, $this->logoPath, 10, 8, 25, 25, 'L');
            }
          
            /* El título es editable (Core › Textos de autorizaciones): se
               parte en las líneas que haga falta, a la derecha del logo. */
            $this->SetFont('Arial', 'B', 10);
            $this->SetX(40);
            $this->MultiCell(0, 5, $this->subtitle, 0, 'R');
            $this->Cell(0, 5, $this->title, 0, 1,'R');
            $this->Ln(5);
        }

        // Agregar un pie de página
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo(), 0, 0, 'C');
        }
        
        // Método para agregar viñetas
        function AddBullet($text, $indent = 5) {
            $this->Cell($indent); // Indentación
            $this->Cell(5, 5, chr(149), 0, 0, 'L'); // Símbolo de viñeta
            $this->MultiCell(0, 4, $text, 0, 'L');
        }
    }

    $titulo = mb_convert_encoding(ds_nombre_organizacion_may($repre_sedeid), 'ISO-8859-1', 'UTF-8');

    /* El texto vigente del formulario en papel (Core › Textos de
       autorizaciones); sin versiones, el de siempre. */
    $form = consent_formulario(ds_nombre_organizacion() ?: DS_HUB_NAME, (string)($sede_nombre ?? ''), (string)($sede_email ?? ds_email_organizacion()));

    $pdf = new PDF($titulo, $logo);
    $pdf->subtitle = mb_convert_encoding($form['titulo'], 'Windows-1252', 'UTF-8');
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();

    $pdf->SetFont('Arial', 'B', 10);

    // Título de la sección
    $pdf->Cell(0, 15, '1. Datos del Titular y Representante del alumno', 0, 2, 'L');

    $pdf->SetFont('Arial', '', 9);
    // Lista de viñetas
    $pdf->AddBullet(mb_convert_encoding('Nombre del representante: '.$datos["REPRESENTANTE"], 'ISO-8859-1', 'UTF-8'),5);
    $pdf->AddBullet(mb_convert_encoding('Identificación del representante: '.$datos["repre_identificacion"], 'ISO-8859-1', 'UTF-8'),5);

    /* Secciones 2 a 6 y las autorizaciones del pie: el texto vigente de
       Core › Textos de autorizaciones (FORMULARIO); sin versiones, el de
       siempre (ds_core/inc/consentimientos.php). */
    $pdf->Ln(2);
    consent_pdf_html($pdf, $form['texto']);

    $pdf->SetFont('Arial', '', 9);
    $textl ="__________________________________________________________________________________________________________";
    $pdf->MultiCell(0,0, $textl);

    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(0, 5, '', 0, 2, 'L');
    $pdf->MultiCell(0,5, mb_convert_encoding(implode("\n", $form['pie']), 'Windows-1252', 'UTF-8'));

    $pdf->SetFont('Arial', '', 9);
    $texti ="__________________________________________________________________________________________________________";
    $pdf->MultiCell(0,1, $texti);

    $pdf->SetFont('Arial', '', 9);
    $textf ="Firma del representante legal: ____________________________                     Fecha:".$fechaHora;
    /* La firma con su fecha, juntas: si no caben, pasan las dos a la hoja siguiente. */
    $pdf->Ln(12);
    $pdf->Cell(0, 5, $textf, 0, 1);

    // Salida del PDF
    $pdf->Output('I', 'Formulario_Proteccion_Datos.pdf');