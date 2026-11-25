<?php
/*
|--------------------------------------------------------------------------
| DsXlsx — una hoja de Excel (.xlsx) con estilos, celdas combinadas e imagen
|--------------------------------------------------------------------------
| Lo justo para entregar un reporte con la cara del Excel de la escuela
| (2026-11-25): colores, bordes, números de verdad (no texto), fechas,
| celdas combinadas, el logo y la hoja lista para imprimir.
|
| NO USA NINGUNA EXTENSIÓN OPCIONAL. El proyecto no tiene PhpSpreadsheet, y
| el PHP del servidor no siempre trae zip ni zlib: el .xlsx (que es un ZIP
| de XML) se arma aquí a mano. Si hay zlib se comprime (deflate); si no, va
| sin comprimir (método «stored»), que Excel abre igual. crc32() y pack()
| son del núcleo de PHP.
|
| Uso:
|   $x = new DsXlsx('Octubre 2026');
|   $x->anchos([12, 14, 40]);
|   $x->celda(0, 0, 'Hola', ['b' => true, 'fondo' => 'E2EFDA']);
|   $x->combinar(0, 0, 0, 2, [...estilo...]);
|   $x->imagen('/ruta/logo.png', 0, 0, 60, 45);
|   echo $x->binario();
| Filas y columnas empiezan en 0.
|
| Estilo: b, i (negrita, cursiva), tam (pt), color y fondo ('RRGGBB'),
| borde (true), h ('left'|'center'|'right'), v ('center'…), ajustar (true:
| texto en varias líneas), fmt ('dinero' #,##0.00 | 'usd' $#,##0.00 |
| 'fecha' dd/mm/aaaa | 'entero').
*/

if (!class_exists('DsXlsx')) {
    final class DsXlsx
    {
        private string $hoja;
        private array $celdas = [];      /* [fila][col] = [valor, xf] */
        private array $altos = [];
        private array $anchos = [];
        private array $combinadas = [];
        private ?array $img = null;
        private array $config = ['horizontal' => true, 'titulo' => '', 'pie' => ''];

        /* Registro de estilos: cada combinación distinta, una sola vez. */
        private array $fuentes = ['<font><sz val="10"/><name val="Calibri"/><family val="2"/></font>'];
        private array $rellenos = ['<fill><patternFill patternType="none"/></fill>', '<fill><patternFill patternType="gray125"/></fill>'];
        private array $bordes = ['<border><left/><right/><top/><bottom/><diagonal/></border>'];
        private array $xfs = ['<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'];
        private array $indice = [];
        private const FORMATOS = ['dinero' => 164, 'usd' => 165, 'fecha' => 166, 'entero' => 3];

        public function __construct(string $hoja)
        {
            /* Excel no admite en el nombre de la hoja : \ / ? * [ ] ni más de 31 caracteres. */
            $this->hoja = mb_substr(trim(preg_replace('~[:\\\\/?*\[\]]~u', ' ', $hoja)) ?: 'Hoja1', 0, 31, 'UTF-8');
        }

        /** Anchos de columna, en caracteres. */
        public function anchos(array $anchos): void { $this->anchos = $anchos; }

        public function alto(int $fila, float $pt): void { $this->altos[$fila] = $pt; }

        /** Impresión: apaisada, ajustada al ancho, y el pie «Página X de N». */
        public function impresion(bool $horizontal, string $titulo = '', string $pie = ''): void
        {
            $this->config = ['horizontal' => $horizontal, 'titulo' => $titulo, 'pie' => $pie];
        }

        /** Una celda. $valor: string, int/float (número) o null (sólo estilo). */
        public function celda(int $fila, int $col, $valor, array $estilo = []): void
        {
            $this->celdas[$fila][$col] = [$valor, $this->estilo($estilo)];
        }

        /** Combina un rango; el estilo se aplica a todas sus celdas (si no, los bordes salen a medias). */
        public function combinar(int $f1, int $c1, int $f2, int $c2, array $estilo = []): void
        {
            $xf = $this->estilo($estilo);
            for ($f = $f1; $f <= $f2; $f++) {
                for ($c = $c1; $c <= $c2; $c++) {
                    if (!isset($this->celdas[$f][$c])) { $this->celdas[$f][$c] = [null, $xf]; }
                    else { $this->celdas[$f][$c][1] = $xf; }
                }
            }
            if ($f1 !== $f2 || $c1 !== $c2) {
                $this->combinadas[] = self::ref($f1, $c1) . ':' . self::ref($f2, $c2);
            }
        }

        /** El logo: anclado en la celda, del tamaño dado en puntos (se respeta la proporción). */
        public function imagen(string $archivo, int $fila, int $col, float $anchoPt, float $altoPt): bool
        {
            if ($archivo === '' || !is_file($archivo)) { return false; }
            $info = @getimagesize($archivo);
            $tipos = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpeg', IMAGETYPE_GIF => 'gif'];
            if (!$info || !isset($tipos[$info[2]]) || $info[0] <= 0 || $info[1] <= 0) { return false; }
            $escala = min($anchoPt / $info[0], $altoPt / $info[1]);
            $this->img = ['datos' => (string) file_get_contents($archivo), 'ext' => $tipos[$info[2]],
                          'fila' => $fila, 'col' => $col,
                          'cx' => (int) round($info[0] * $escala * 12700), 'cy' => (int) round($info[1] * $escala * 12700)];
            return true;
        }

        /** Fecha «AAAA-MM-DD» → número de serie de Excel (para el formato 'fecha'). */
        public static function serie(string $fecha): ?int
        {
            if (!preg_match('~^(\d{4})-(\d{2})-(\d{2})~', $fecha, $m) || $m[1] === '0000') { return null; }
            return (int) (gmmktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]) / 86400) + 25569;
        }

        public static function ref(int $fila, int $col): string
        {
            $l = '';
            for ($c = $col + 1; $c > 0; $c = intdiv($c - 1, 26)) { $l = chr(65 + ($c - 1) % 26) . $l; }
            return $l . ($fila + 1);
        }

        private static function x(string $s): string
        {
            /* Fuera los caracteres de control que XML no admite. */
            $s = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~', '', $s);
            return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        private function estilo(array $e): int
        {
            ksort($e);
            $clave = json_encode($e);
            if (isset($this->indice[$clave])) { return $this->indice[$clave]; }

            $f = '<font>' . (!empty($e['b']) ? '<b/>' : '') . (!empty($e['i']) ? '<i/>' : '')
               . '<sz val="' . (float) ($e['tam'] ?? 10) . '"/>'
               . (isset($e['color']) ? '<color rgb="FF' . $e['color'] . '"/>' : '')
               . '<name val="Calibri"/><family val="2"/></font>';
            $fontId = array_search($f, $this->fuentes, true);
            if ($fontId === false) { $this->fuentes[] = $f; $fontId = count($this->fuentes) - 1; }

            $fillId = 0;
            if (isset($e['fondo'])) {
                $r = '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $e['fondo'] . '"/><bgColor indexed="64"/></patternFill></fill>';
                $fillId = array_search($r, $this->rellenos, true);
                if ($fillId === false) { $this->rellenos[] = $r; $fillId = count($this->rellenos) - 1; }
            }

            $borderId = 0;
            if (!empty($e['borde'])) {
                $l = '<%1$s style="thin"><color rgb="FF8C8C8C"/></%1$s>';
                $b = '<border>' . sprintf($l, 'left') . sprintf($l, 'right') . sprintf($l, 'top') . sprintf($l, 'bottom') . '<diagonal/></border>';
                $borderId = array_search($b, $this->bordes, true);
                if ($borderId === false) { $this->bordes[] = $b; $borderId = count($this->bordes) - 1; }
            }

            $fmt = self::FORMATOS[$e['fmt'] ?? ''] ?? 0;
            $alin = '';
            if (isset($e['h']) || isset($e['v']) || !empty($e['ajustar'])) {
                $alin = '<alignment' . (isset($e['h']) ? ' horizontal="' . $e['h'] . '"' : '')
                      . ' vertical="' . ($e['v'] ?? 'center') . '"' . (!empty($e['ajustar']) ? ' wrapText="1"' : '') . '/>';
            }
            $this->xfs[] = '<xf numFmtId="' . $fmt . '" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="' . $borderId . '" xfId="0"'
                         . ($fmt ? ' applyNumberFormat="1"' : '') . ($fontId ? ' applyFont="1"' : '') . ($fillId ? ' applyFill="1"' : '')
                         . ($borderId ? ' applyBorder="1"' : '') . ($alin !== '' ? ' applyAlignment="1">' . $alin . '</xf>' : '/>');
            return $this->indice[$clave] = count($this->xfs) - 1;
        }

        private function estilos(): string
        {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<numFmts count="3"><numFmt numFmtId="164" formatCode="#,##0.00"/>'
                . '<numFmt numFmtId="165" formatCode="&quot;$&quot;#,##0.00;[Red]\-&quot;$&quot;#,##0.00"/>'
                . '<numFmt numFmtId="166" formatCode="dd/mm/yyyy"/></numFmts>'
                . '<fonts count="' . count($this->fuentes) . '">' . implode('', $this->fuentes) . '</fonts>'
                . '<fills count="' . count($this->rellenos) . '">' . implode('', $this->rellenos) . '</fills>'
                . '<borders count="' . count($this->bordes) . '">' . implode('', $this->bordes) . '</borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="' . count($this->xfs) . '">' . implode('', $this->xfs) . '</cellXfs>'
                . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                . '</styleSheet>';
        }

        private function hojaXml(): string
        {
            foreach ($this->altos as $f => $pt) { $this->celdas[$f] ??= []; }   /* una fila vacía con alto propio */
            ksort($this->celdas);
            $filas = '';
            $maxF = 0; $maxC = 0;
            foreach ($this->celdas as $f => $cols) {
                ksort($cols);
                $attr = isset($this->altos[$f]) ? ' ht="' . $this->altos[$f] . '" customHeight="1"' : '';
                $filas .= '<row r="' . ($f + 1) . '"' . $attr . '>';
                foreach ($cols as $c => [$v, $xf]) {
                    $r = self::ref($f, $c);
                    $s = $xf ? ' s="' . $xf . '"' : '';
                    if ($v === null || $v === '') {
                        $filas .= '<c r="' . $r . '"' . $s . '/>';
                    } elseif (is_int($v) || is_float($v)) {
                        $filas .= '<c r="' . $r . '"' . $s . '><v>' . (is_float($v) ? rtrim(rtrim(sprintf('%.4F', $v), '0'), '.') : $v) . '</v></c>';
                    } else {
                        $filas .= '<c r="' . $r . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . self::x((string) $v) . '</t></is></c>';
                    }
                    $maxC = max($maxC, $c);
                }
                $filas .= '</row>';
                $maxF = max($maxF, $f);
            }
            $cols = '';
            foreach ($this->anchos as $i => $w) {
                $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
            }
            $merge = $this->combinadas
                ? '<mergeCells count="' . count($this->combinadas) . '">' . implode('', array_map(static fn($m) => '<mergeCell ref="' . $m . '"/>', $this->combinadas)) . '</mergeCells>'
                : '';
            /* En el pie, «&» es un código de Excel (&P = página): el texto no lo lleva. */
            $pie = '&amp;L' . self::x(str_replace('&', 'y', $this->config['pie'])) . '&amp;R' . self::x('Página ') . '&amp;P de &amp;N';

            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
                . '<dimension ref="A1:' . self::ref($maxF, $maxC) . '"/>'
                . '<sheetViews><sheetView workbookViewId="0" showGridLines="0" zoomScale="100"/></sheetViews>'
                . '<sheetFormatPr defaultRowHeight="15"/>'
                . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
                . '<sheetData>' . $filas . '</sheetData>'
                . $merge
                . '<printOptions horizontalCentered="1"/>'
                . '<pageMargins left="0.3" right="0.3" top="0.4" bottom="0.5" header="0.2" footer="0.25"/>'
                . '<pageSetup paperSize="9" orientation="' . ($this->config['horizontal'] ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>'
                . '<headerFooter><oddFooter>' . $pie . '</oddFooter></headerFooter>'
                . ($this->img ? '<drawing r:id="rId1"/>' : '')
                . '</worksheet>';
        }

        private function dibujo(): string
        {
            $i = $this->img;
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
                . '<xdr:oneCellAnchor><xdr:from><xdr:col>' . $i['col'] . '</xdr:col><xdr:colOff>38100</xdr:colOff><xdr:row>' . $i['fila'] . '</xdr:row><xdr:rowOff>38100</xdr:rowOff></xdr:from>'
                . '<xdr:ext cx="' . $i['cx'] . '" cy="' . $i['cy'] . '"/>'
                . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="2" name="Logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $i['cx'] . '" cy="' . $i['cy'] . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
                . '</xdr:pic><xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>';
        }

        /** El archivo .xlsx entero. */
        public function binario(): string
        {
            $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
            $pkg = 'http://schemas.openxmlformats.org/package/2006/relationships';
            $ext = $this->img['ext'] ?? '';
            $archivos = [
                '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                    . '<Default Extension="xml" ContentType="application/xml"/>'
                    . ($ext !== '' ? '<Default Extension="' . $ext . '" ContentType="image/' . $ext . '"/>' : '')
                    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                    . ($this->img ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
                    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                    . '</Types>',
                '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<Relationships xmlns="' . $pkg . '">'
                    . '<Relationship Id="rId1" Type="' . $rel . '/officeDocument" Target="xl/workbook.xml"/>'
                    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                    . '</Relationships>',
                'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                    . '<dc:title>' . self::x($this->config['titulo']) . '</dc:title><dc:creator>DigiSports Insights</dc:creator>'
                    . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
                    . '</cp:coreProperties>',
                'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="' . $rel . '">'
                    . '<bookViews><workbookView/></bookViews>'
                    . '<sheets><sheet name="' . self::x($this->hoja) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
                'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<Relationships xmlns="' . $pkg . '">'
                    . '<Relationship Id="rId1" Type="' . $rel . '/worksheet" Target="worksheets/sheet1.xml"/>'
                    . '<Relationship Id="rId2" Type="' . $rel . '/styles" Target="styles.xml"/>'
                    . '</Relationships>',
                'xl/worksheets/sheet1.xml' => $this->hojaXml(),
                'xl/styles.xml' => $this->estilos(),
            ];
            if ($this->img) {
                $archivos['xl/worksheets/_rels/sheet1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<Relationships xmlns="' . $pkg . '"><Relationship Id="rId1" Type="' . $rel . '/drawing" Target="../drawings/drawing1.xml"/></Relationships>';
                $archivos['xl/drawings/drawing1.xml'] = $this->dibujo();
                $archivos['xl/drawings/_rels/drawing1.xml.rels'] = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                    . '<Relationships xmlns="' . $pkg . '"><Relationship Id="rId1" Type="' . $rel . '/image" Target="../media/image1.' . $ext . '"/></Relationships>';
                $archivos['xl/media/image1.' . $ext] = $this->img['datos'];
            }
            return self::zip($archivos);
        }

        /** Un ZIP mínimo: deflate si hay zlib, «stored» si no. */
        private static function zip(array $archivos): string
        {
            $comprimir = function_exists('gzdeflate');
            $t = getdate();
            $hora  = ($t['hours'] << 11) | ($t['minutes'] << 5) | intdiv($t['seconds'], 2);
            $fecha = (($t['year'] - 1980) << 9) | ($t['mon'] << 5) | $t['mday'];
            $cuerpo = ''; $central = '';
            foreach ($archivos as $nombre => $datos) {
                $crc = crc32($datos);
                $z = $comprimir ? gzdeflate($datos, 6) : false;
                $metodo = $z !== false ? 8 : 0;
                $z = $z !== false ? $z : $datos;
                $offset = strlen($cuerpo);
                $cuerpo .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, $metodo, $hora, $fecha, $crc, strlen($z), strlen($datos), strlen($nombre), 0)
                         . $nombre . $z;
                $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, $metodo, $hora, $fecha, $crc, strlen($z), strlen($datos),
                                 strlen($nombre), 0, 0, 0, 0, 0, $offset) . $nombre;
            }
            return $cuerpo . $central
                 . pack('VvvvvVVv', 0x06054b50, 0, 0, count($archivos), count($archivos), strlen($central), strlen($cuerpo), 0);
        }
    }
}
