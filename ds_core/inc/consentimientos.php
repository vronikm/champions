<?php
/*
|--------------------------------------------------------------------------
| Textos de las autorizaciones LOPDP (migración 075)
|--------------------------------------------------------------------------
| Se editan en Core › Textos de autorizaciones:
|
|   DATOS, IMAGEN  los dos que acepta el representante en línea: en el
|                  formulario de inscripción (ds_form/index.php, el enlace de
|                  Fútbol › Generar enlaces) y en el enlace de autorización
|                  (ds_form/autorizacion.php, desde Consentimientos LOPDP).
|   FORMULARIO     el formulario en papel que firma el representante
|                  (Fútbol › Representantes › Formulario LPD y su PDF).
|
| Cada cambio es una VERSIÓN NUEVA (consentimiento_texto): nunca se
| reescribe una anterior. De cada aceptación en línea se guarda el SHA-256
| del texto que se mostró (alumno_consentimiento.consent_textohash), así que
| cada una sigue ligada a la redacción que vio el representante.
|
| Sin versiones en la base (o sin la migración) vale el texto de siempre:
| ds_form/config/consentimientos.php para los de línea —con el mismo hash
| que hasta ahora— y consent_formulario_original() para el de papel.
|
| Marcadores: {ESCUELA} en todos; en el de papel también {SEDE} y
| {CORREO_SEDE}, porque se imprime para un representante de una sede.
*/

if (!function_exists('consent_textos_vigentes')) {

    /* Los que se aceptan en línea (los usa ds_form). */
    define('CONSENT_TIPOS', ['DATOS' => 'Tratamiento de datos personales', 'IMAGEN' => 'Uso de imagen del menor']);
    /* Todos los que se editan en Core. */
    define('CONSENT_EDITABLES', CONSENT_TIPOS + ['FORMULARIO' => 'Formulario en papel']);

    /** El texto original de los de línea, el del archivo, sin sustituir {ESCUELA}. */
    function consent_textos_archivo(): array
    {
        static $archivo = null;
        $archivo ??= require dirname(__DIR__, 2) . '/ds_form/config/consentimientos.php';
        return $archivo;
    }

    /**
     * El formulario en papel tal como estaba escrito en la vista y el PDF
     * (representanteFLPD, formularioLPPDF). La sección 1 —nombre y cédula
     * del representante— la pone el sistema; esto es de la 2 en adelante.
     * 'casilla' son las autorizaciones del pie, una por línea.
     */
    function consent_formulario_original(): array
    {
        return [
            'titulo'  => 'FORMULARIO DE CONSENTIMIENTO INFORMADO DE RECOLECCIÓN Y TRATAMIENTO DE DATOS PERSONALES',
            'texto'   => <<<TEXTO
<p><strong>2. Finalidad del tratamiento de datos</strong></p>
<p>En {ESCUELA}, recolectamos y tratamos los datos personales de nuestros alumnos y sus representantes legales con las siguientes finalidades:</p>
<ul>
    <li>Gestión administrativa y operativa de la inscripción, pagos mensuales y participación del alumno en las actividades de {ESCUELA}.</li>
    <li>Comunicación de eventos, horarios de entrenamiento, fechas de torneos, actividades y todo tipo de información relevante.</li>
    <li>Atención de situaciones de emergencia, incluyendo acceso a información de contacto y datos médicos básicos proporcionados por el representante legal.</li>
    <li>Registro de contenido audiovisual (fotos y videos) para la promoción de actividades en redes sociales y material institucional, siempre con el consentimiento del representante del alumno.</li>
</ul>
<p><strong>3. Datos a ser recolectados</strong></p>
<p>Con el fin de llevar a cabo las finalidades descritas, recolectaremos los siguientes datos personales:</p>
<ul>
    <li>Datos del alumno: Tipo de identificación, número de identificación, apellido paterno, apellido materno, primer nombre, segundo nombre, nacionalidad, fecha de nacimiento, dirección, tiene hermanos, sexo, fotografías del documento de identificación (anverso y reverso).</li>
    <li>Contacto de emergencia del alumno: Celular, nombre, parentesco.</li>
    <li>Datos del representante: Tipo de identificación, número de identificación, apellido paterno, apellido materno, primer nombre, segundo nombre, parentesco, sexo, dirección, correo, celular y si requiere factura.</li>
    <li>Información médica: Tipo de sangre, peso, talla, enfermedad diagnosticada, medicamentos, alergia a medicamentos, alergia a objetos, cirugías, dispone de carnet de vacunación COVID y vacunación habitual.</li>
    <li>Contenido audiovisual (fotos y videos): Se podrán tomar y usar imágenes de los alumnos en actividades propias de {ESCUELA} con fines promocionales o informativos.</li>
</ul>
<p><strong>4. Derechos del Titular de los Datos</strong></p>
<p>El titular de los datos o su representante tiene derecho a acceder, rectificar, cancelar u oponerse al tratamiento de los datos personales en cualquier momento, de conformidad con la Ley de Protección de Datos Personales en Ecuador. Para ejercer estos derechos, puede contactarse a través del correo: {CORREO_SEDE}.</p>
<p><strong>5. Seguridad de los Datos</strong></p>
<p>{ESCUELA} adopta medidas de seguridad razonables y adecuadas para proteger los datos personales contra el acceso no autorizado, pérdida, destrucción, alteración o uso indebido.</p>
<p><strong>6. Consentimiento</strong></p>
<p>Declaro que he leído y comprendido los términos de este consentimiento y autorizo a {ESCUELA} a recolectar y tratar los datos personales mencionados, en los términos señalados en este documento.</p>
TEXTO,
            'casilla' => "Sí, autorizo el uso de imágenes del alumno (fotos y videos) en las redes sociales y material promocional de {ESCUELA}, sede {SEDE}.\n"
                       . 'Sí, consiento el tratamiento de los datos personales del alumno con las finalidades descritas.',
        ];
    }

    /** El texto original (sin versiones) de cada tipo editable. */
    function consent_originales(): array
    {
        return consent_textos_archivo() + ['FORMULARIO' => consent_formulario_original()];
    }

    /**
     * La versión vigente de cada tipo editable, sin sustituir marcadores:
     * ['DATOS' => ['titulo','texto','casilla','version','fecha','usuario','origen' => BD|ARCHIVO], ...]
     */
    function consent_vigentes_crudos(): array
    {
        $textos = [];
        foreach (consent_originales() as $tipo => $b) {
            $textos[$tipo] = $b + ['version' => 0, 'fecha' => '', 'usuario' => '', 'origen' => 'ARCHIVO'];
        }
        $con = function_exists('seguridad_conexion') ? seguridad_conexion() : null;
        if (!$con) { return $textos; }
        try {
            $filas = $con->query(
                "SELECT t.texto_tipo, t.texto_version, t.texto_titulo, t.texto_contenido, t.texto_casilla, t.texto_usuario, t.texto_fecha
                   FROM consentimiento_texto t
                  WHERE t.texto_version = (SELECT MAX(t2.texto_version) FROM consentimiento_texto t2 WHERE t2.texto_tipo = t.texto_tipo)"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return $textos;   // sin la migración 075: los originales
        }
        foreach ($filas as $f) {
            if (!isset($textos[$f['texto_tipo']])) { continue; }
            $textos[$f['texto_tipo']] = [
                'titulo'  => $f['texto_titulo'],
                'texto'   => $f['texto_contenido'],
                'casilla' => $f['texto_casilla'],
                'version' => (int)$f['texto_version'],
                'fecha'   => $f['texto_fecha'],
                'usuario' => $f['texto_usuario'],
                'origen'  => 'BD',
            ];
        }
        return $textos;
    }

    /** Los vigentes de los que se aceptan en línea (DATOS, IMAGEN), sin sustituir {ESCUELA}. */
    function consent_textos_crudos(): array
    {
        return array_intersect_key(consent_vigentes_crudos(), CONSENT_TIPOS);
    }

    /** Los textos en línea como se muestran: {ESCUELA} ya sustituido. */
    function consent_textos_vigentes(string $escuela): array
    {
        $textos = consent_textos_crudos();
        foreach ($textos as $tipo => $b) {
            $textos[$tipo]['texto'] = str_replace('{ESCUELA}', $escuela, $b['texto']);
        }
        return $textos;
    }

    /**
     * El formulario en papel vigente, listo para una sede: marcadores
     * sustituidos. 'texto' es HTML (los valores van escapados) y 'pie' las
     * autorizaciones del pie, una por elemento, en texto plano.
     */
    function consent_formulario(string $escuela, string $sede, string $correo): array
    {
        $f = consent_vigentes_crudos()['FORMULARIO'];
        $plano = ['{ESCUELA}' => $escuela, '{SEDE}' => $sede, '{CORREO_SEDE}' => $correo];
        $html = array_map(static fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $plano);
        return [
            'titulo'  => strtr($f['titulo'], $plano),
            'texto'   => strtr($f['texto'], $html),
            'pie'     => array_values(array_filter(array_map('trim', explode("\n", strtr($f['casilla'], $plano))), 'strlen')),
            'version' => $f['version'],
        ];
    }

    /** La huella que se guarda con cada aceptación (ver registroController::hashTexto). */
    function consent_hash(string $contenido, string $escuela): string
    {
        return hash('sha256', str_replace('{ESCUELA}', $escuela, $contenido));
    }

    /**
     * Deja sólo el formato que admite un texto legal: párrafos, saltos,
     * negrita, cursiva, subrayado y listas. Sin atributos (ni estilos, ni
     * enlaces, ni eventos): el texto se pinta tal cual en una página pública.
     * Las etiquetas no admitidas se quitan y se queda su contenido; script y
     * style, con su contenido.
     */
    function consent_limpiar_html(string $html): string
    {
        $html = trim(str_replace("\r", '', $html));
        if ($html === '') { return ''; }
        $permitidas = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'];

        $doc = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="raiz">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        $limpiar = static function (DOMNode $nodo) use (&$limpiar, $permitidas) {
            foreach (iterator_to_array($nodo->childNodes) as $hijo) {
                if ($hijo instanceof DOMElement) {
                    $etiqueta = strtolower($hijo->nodeName);
                    if (in_array($etiqueta, ['script', 'style', 'iframe', 'object', 'embed', 'template'], true)) {
                        $nodo->removeChild($hijo);
                        continue;
                    }
                    $limpiar($hijo);
                    if (!in_array($etiqueta, $permitidas, true)) {
                        while ($hijo->firstChild) { $nodo->insertBefore($hijo->firstChild, $hijo); }
                        $nodo->removeChild($hijo);
                        continue;
                    }
                    while ($hijo->attributes->length) { $hijo->removeAttribute($hijo->attributes->item(0)->nodeName); }
                } elseif ($hijo->nodeType === XML_COMMENT_NODE || $hijo->nodeType === XML_PI_NODE) {
                    $nodo->removeChild($hijo);
                }
            }
        };
        $raiz = $doc->getElementById('raiz');
        if (!$raiz) { return ''; }
        $limpiar($raiz);

        $salida = '';
        foreach ($raiz->childNodes as $hijo) { $salida .= $doc->saveHTML($hijo); }
        return trim($salida);
    }

    /**
     * Dibuja en un FPDF el HTML limpio de un texto (el del formulario en
     * papel): un párrafo que es todo negrita sale como título de sección
     * (10 pt), el resto a $cuerpo pt; negrita, cursiva y subrayado dentro de
     * la línea, y listas con viñeta y sangría. FPDF usa Windows-1252.
     */
    function consent_pdf_html($pdf, string $html, float $alto = 5, int $cuerpo = 9): void
    {
        $doc = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="raiz">' . consent_limpiar_html($html) . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        $raiz = $doc->getElementById('raiz');
        if (!$raiz) { return; }

        $cp = static fn($t) => mb_convert_encoding($t, 'Windows-1252', 'UTF-8');
        $margen = $pdf->GetX();
        /* El texto de un nodo en línea, con el estilo que le toca. */
        $enLinea = static function (DOMNode $n, string $estilo, int $tam) use (&$enLinea, $pdf, $alto, $cp) {
            foreach ($n->childNodes as $h) {
                if ($h->nodeType === XML_TEXT_NODE) {
                    $t = preg_replace('/\s+/u', ' ', $h->nodeValue);
                    if ($t === '') { continue; }
                    $pdf->SetFont('Arial', $estilo, $tam);
                    $pdf->Write($alto, $cp($t));
                } elseif ($h->nodeName === 'br') {
                    $pdf->Ln($alto);
                } else {
                    $mas = ['strong' => 'B', 'b' => 'B', 'em' => 'I', 'i' => 'I', 'u' => 'U'][$h->nodeName] ?? '';
                    $enLinea($h, count_chars($estilo . $mas, 3), $tam);
                }
            }
        };
        foreach ($raiz->childNodes as $b) {
            if ($b->nodeType === XML_TEXT_NODE) {
                if (trim($b->nodeValue) === '') { continue; }
                $p = $doc->createElement('p');
                $p->appendChild($doc->createTextNode($b->nodeValue));
                $enLinea($p, '', $cuerpo);
                $pdf->Ln($alto + 1);
                continue;
            }
            if (in_array($b->nodeName, ['ul', 'ol'], true)) {
                $i = 0;
                foreach ($b->childNodes as $li) {
                    if ($li->nodeName !== 'li') { continue; }
                    $i++;
                    $pdf->SetX($margen + 5);
                    $pdf->SetFont('Arial', '', $cuerpo);
                    $pdf->Cell(5, $alto, $b->nodeName === 'ol' ? $i . '.' : chr(149));
                    /* La sangría de la viñeta también en las líneas que se parten. */
                    $pdf->SetLeftMargin($margen + 10);
                    $enLinea($li, '', $cuerpo);
                    $pdf->SetLeftMargin($margen);
                    $pdf->Ln($alto);
                }
                $pdf->Ln(1);
                continue;
            }
            /* Un párrafo que es todo negrita: el título de una sección. */
            $titulo = $b->nodeName === 'p' && $b->childNodes->length === 1 && in_array($b->firstChild->nodeName, ['strong', 'b'], true);
            if ($titulo) { $pdf->Ln(2); }
            $enLinea($b, $titulo ? 'B' : '', $titulo ? 10 : $cuerpo);
            $pdf->Ln($titulo ? $alto + 2 : $alto + 1);
        }
        $pdf->SetFont('Arial', '', $cuerpo);
    }
}
