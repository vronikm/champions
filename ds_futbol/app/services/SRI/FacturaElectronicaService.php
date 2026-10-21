<?php

namespace app\services\SRI;

class FacturaElectronicaService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $rutaSri = dirname(__DIR__, 3) . '/config/sri.php';
        $this->config = $config ?? (is_file($rutaSri) ? require $rutaSri : []);
    }

    public function getConfig(): array
    {
        return $this->config;
    }


    private function esValorNoAplica($valor): bool
    {
        $valor = strtoupper(trim((string) $valor));
        return $valor === '' || in_array($valor, ['NO', 'N/A', 'NA', 'NINGUNO', 'NO APLICA', '0'], true);
    }

    private function soloDigitos($valor): string
    {
        return preg_replace('/\D+/', '', trim((string) $valor));
    }
    public function generarCodigoNumerico(): string
    {
        return str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    }

    public function generarClaveAcceso(
        string $fechaEmision,
        string $tipoComprobante,
        string $ruc,
        string $ambiente,
        string $serie,
        string $secuencial,
        string $codigoNumerico,
        string $tipoEmision
    ): string {
        $base = $fechaEmision . $tipoComprobante . $ruc . $ambiente . $serie . $secuencial . $codigoNumerico . $tipoEmision;
        return $base . $this->calcularModulo11($base);
    }

    private function calcularModulo11(string $cadena): int
    {
        $coeficientes = [2, 3, 4, 5, 6, 7];
        $suma = 0;
        $j = 0;

        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += (int) $cadena[$i] * $coeficientes[$j];
            $j = ($j + 1) % count($coeficientes);
        }

        $digito = 11 - ($suma % 11);
        if ($digito === 11) {
            return 0;
        }
        if ($digito === 10) {
            return 1;
        }

        return $digito;
    }

    public function generarXMLFactura(array $datosFactura): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        $factura = $xml->createElement('factura');
        $factura->setAttribute('id', 'comprobante');
        $factura->setAttribute('version', $this->config['version_factura'] ?? '2.1.0');
        $xml->appendChild($factura);

        $factura->appendChild($this->crearInfoTributaria($xml, $datosFactura));
        $factura->appendChild($this->crearInfoFactura($xml, $datosFactura));
        $factura->appendChild($this->crearDetalles($xml, $datosFactura['detalles'] ?? []));

        if (!empty($datosFactura['info_adicional'])) {
            $factura->appendChild($this->crearInfoAdicional($xml, $datosFactura['info_adicional']));
        }

        return $xml->saveXML();
    }

    private function crearInfoTributaria(\DOMDocument $xml, array $datosFactura): \DOMElement
    {
        $emisor = $this->config['emisor'];
        $info = $xml->createElement('infoTributaria');

        $elementos = [
            'ambiente' => $this->config['ambiente'],
            'tipoEmision' => $this->config['tipo_emision'],
            'razonSocial' => $emisor['razon_social'],
            'nombreComercial' => $emisor['nombre_comercial'] ?? '',
            'ruc' => $emisor['ruc'],
            'claveAcceso' => $datosFactura['clave_acceso'],
            'codDoc' => '01',
            'estab' => $datosFactura['establecimiento'],
            'ptoEmi' => $datosFactura['punto_emision'],
            'secuencial' => $datosFactura['secuencial'],
            'dirMatriz' => $emisor['direccion_matriz'],
        ];

        if (empty($elementos['nombreComercial'])) {
            unset($elementos['nombreComercial']);
        }

        $agenteRetencion = $this->soloDigitos($emisor['agente_retencion'] ?? '');
        if ($agenteRetencion !== '' && $agenteRetencion !== '0') {
            $elementos['agenteRetencion'] = $agenteRetencion;
        }

        $contribuyenteRimpe = trim((string) ($emisor['contribuyente_rimpe'] ?? ''));
        if (!$this->esValorNoAplica($contribuyenteRimpe)) {
            $elementos['contribuyenteRimpe'] = $contribuyenteRimpe;
        }

        foreach ($elementos as $nombre => $valor) {
            $info->appendChild($this->elementoTexto($xml, $nombre, (string) $valor));
        }

        return $info;
    }

    private function crearInfoFactura(\DOMDocument $xml, array $datosFactura): \DOMElement
    {
        $emisor = $this->config['emisor'];
        $cliente = $datosFactura['cliente'];
        $totales = $datosFactura['totales'];

        $info = $xml->createElement('infoFactura');
        $info->appendChild($this->elementoTexto($xml, 'fechaEmision', $datosFactura['fecha_emision']));
        $info->appendChild($this->elementoTexto($xml, 'dirEstablecimiento', $emisor['direccion_establecimiento']));

        $contribuyenteEspecial = $this->soloDigitos($emisor['contribuyente_especial'] ?? '');
        if ($contribuyenteEspecial !== '' && $contribuyenteEspecial !== '0') {
            $info->appendChild($this->elementoTexto($xml, 'contribuyenteEspecial', $contribuyenteEspecial));
        }

        if (in_array($emisor['obligado_contabilidad'] ?? '', ['SI', 'NO'], true)) {
            $info->appendChild($this->elementoTexto($xml, 'obligadoContabilidad', $emisor['obligado_contabilidad']));
        }

        $info->appendChild($this->elementoTexto($xml, 'tipoIdentificacionComprador', $cliente['tipo_identificacion']));
        $info->appendChild($this->elementoTexto($xml, 'razonSocialComprador', $cliente['razon_social']));
        $info->appendChild($this->elementoTexto($xml, 'identificacionComprador', $cliente['identificacion']));

        if (!empty($cliente['direccion'])) {
            $info->appendChild($this->elementoTexto($xml, 'direccionComprador', $cliente['direccion']));
        }

        $info->appendChild($this->elementoTexto($xml, 'totalSinImpuestos', $this->money($totales['subtotal'])));
        $info->appendChild($this->elementoTexto($xml, 'totalDescuento', $this->money($totales['descuento'] ?? 0)));

        $totalConImpuestos = $xml->createElement('totalConImpuestos');
        foreach ($totales['impuestos'] as $impuesto) {
            $totalImpuesto = $xml->createElement('totalImpuesto');
            $totalImpuesto->appendChild($this->elementoTexto($xml, 'codigo', $impuesto['codigo']));
            $totalImpuesto->appendChild($this->elementoTexto($xml, 'codigoPorcentaje', $impuesto['codigo_porcentaje']));
            $totalImpuesto->appendChild($this->elementoTexto($xml, 'baseImponible', $this->money($impuesto['base_imponible'])));
            if (isset($impuesto['tarifa'])) {
                $totalImpuesto->appendChild($this->elementoTexto($xml, 'tarifa', $this->money($impuesto['tarifa'])));
            }
            $totalImpuesto->appendChild($this->elementoTexto($xml, 'valor', $this->money($impuesto['valor'])));
            $totalConImpuestos->appendChild($totalImpuesto);
        }
        $info->appendChild($totalConImpuestos);

        $info->appendChild($this->elementoTexto($xml, 'propina', '0.00'));
        $info->appendChild($this->elementoTexto($xml, 'importeTotal', $this->money($totales['total'])));
        $info->appendChild($this->elementoTexto($xml, 'moneda', 'DOLAR'));

        $pagos = $xml->createElement('pagos');
        foreach ($datosFactura['pagos'] as $pago) {
            $pagoElement = $xml->createElement('pago');
            $pagoElement->appendChild($this->elementoTexto($xml, 'formaPago', $pago['forma_pago']));
            $pagoElement->appendChild($this->elementoTexto($xml, 'total', $this->money($pago['total'])));
            if (!empty($pago['plazo'])) {
                $pagoElement->appendChild($this->elementoTexto($xml, 'plazo', (string) $pago['plazo']));
                $pagoElement->appendChild($this->elementoTexto($xml, 'unidadTiempo', $pago['unidad_tiempo'] ?? 'dias'));
            }
            $pagos->appendChild($pagoElement);
        }
        $info->appendChild($pagos);

        return $info;
    }

    private function crearDetalles(\DOMDocument $xml, array $detalles): \DOMElement
    {
        $detallesElement = $xml->createElement('detalles');

        foreach ($detalles as $item) {
            $detalle = $xml->createElement('detalle');
            $detalle->appendChild($this->elementoTexto($xml, 'codigoPrincipal', $item['codigo']));
            if (!empty($item['codigo_auxiliar'])) {
                $detalle->appendChild($this->elementoTexto($xml, 'codigoAuxiliar', $item['codigo_auxiliar']));
            }
            $detalle->appendChild($this->elementoTexto($xml, 'descripcion', $item['descripcion']));
            $detalle->appendChild($this->elementoTexto($xml, 'cantidad', number_format((float) $item['cantidad'], 6, '.', '')));
            $detalle->appendChild($this->elementoTexto($xml, 'precioUnitario', number_format((float) $item['precio_unitario'], 6, '.', '')));
            $detalle->appendChild($this->elementoTexto($xml, 'descuento', $this->money($item['descuento'] ?? 0)));
            $detalle->appendChild($this->elementoTexto($xml, 'precioTotalSinImpuesto', $this->money($item['precio_total_sin_impuesto'])));

            $impuestos = $xml->createElement('impuestos');
            foreach ($item['impuestos'] as $impuesto) {
                $imp = $xml->createElement('impuesto');
                $imp->appendChild($this->elementoTexto($xml, 'codigo', $impuesto['codigo']));
                $imp->appendChild($this->elementoTexto($xml, 'codigoPorcentaje', $impuesto['codigo_porcentaje']));
                $imp->appendChild($this->elementoTexto($xml, 'tarifa', $this->money($impuesto['tarifa'])));
                $imp->appendChild($this->elementoTexto($xml, 'baseImponible', $this->money($impuesto['base_imponible'])));
                $imp->appendChild($this->elementoTexto($xml, 'valor', $this->money($impuesto['valor'])));
                $impuestos->appendChild($imp);
            }
            $detalle->appendChild($impuestos);
            $detallesElement->appendChild($detalle);
        }

        return $detallesElement;
    }

    private function crearInfoAdicional(\DOMDocument $xml, array $infoAdicional): \DOMElement
    {
        $info = $xml->createElement('infoAdicional');
        foreach ($infoAdicional as $nombre => $valor) {
            if ($valor === '' || $valor === null) {
                continue;
            }
            $campo = $this->elementoTexto($xml, 'campoAdicional', (string) $valor);
            $campo->setAttribute('nombre', substr((string) $nombre, 0, 300));
            $info->appendChild($campo);
        }
        return $info;
    }

    public function guardarXML(string $xml, string $claveAcceso, string $tipo = 'generados'): string
    {
        $key = 'xml_' . $tipo;
        $directorio = $this->config['storage'][$key] ?? $this->config['storage']['xml_generados'];
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $archivo = rtrim($directorio, '/\\') . DIRECTORY_SEPARATOR . $claveAcceso . '.xml';
        file_put_contents($archivo, $xml);
        return $archivo;
    }

    public function validarXML(string $xml): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $valido = $dom->loadXML($xml);
        $errores = [];
        foreach (libxml_get_errors() as $error) {
            $errores[] = trim($error->message) . ' linea ' . $error->line;
        }
        libxml_clear_errors();

        return ['valido' => $valido && empty($errores), 'errores' => $errores];
    }

    private function elementoTexto(\DOMDocument $xml, string $nombre, string $valor): \DOMElement
    {
        $elemento = $xml->createElement($nombre);
        $elemento->appendChild($xml->createTextNode($valor));
        return $elemento;
    }

    private function money($valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}
