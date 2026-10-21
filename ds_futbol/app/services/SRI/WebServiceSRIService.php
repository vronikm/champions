<?php

namespace app\services\SRI;

class WebServiceSRIService
{
    private array $config;
    private string $urlRecepcion;
    private string $urlAutorizacion;

    private function normalizarEndpointSoap(string $url): string
    {
        return preg_replace('/\\?wsdl$/i', '', trim($url));
    }

    public function __construct(?array $config = null)
    {
        $rutaSri = dirname(__DIR__, 3).'/config/sri.php';
        $this->config = $config ?? (is_file($rutaSri) ? require $rutaSri : []);
        $ambiente = ((string)($this->config['ambiente'] ?? '1') === '2') ? 'produccion' : 'pruebas';
        $this->urlRecepcion = $this->config['webservices'][$ambiente]['recepcion'] ?? '';
        $this->urlAutorizacion = $this->config['webservices'][$ambiente]['autorizacion'] ?? '';
    }

    public function enviarComprobante(string $xmlFirmado): array
    {
        try {
            $xmlBase64 = base64_encode($xmlFirmado);
            $soapRequest = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://ec.gob.sri.ws.recepcion">'
                .'<soapenv:Header/>'
                .'<soapenv:Body>'
                .'<ec:validarComprobante><xml>'.$xmlBase64.'</xml></ec:validarComprobante>'
                .'</soapenv:Body>'
                .'</soapenv:Envelope>';

            $response = $this->ejecutarSoapRequest($this->urlRecepcion, $soapRequest);
            return $this->parsearRespuestaRecepcion($response);
        } catch (\Throwable $e) {
            return [
                'exito' => false,
                'estado' => 'ERROR',
                'mensaje' => 'Error al enviar comprobante: '.$e->getMessage(),
                'comprobantes' => [],
            ];
        }
    }

    public function consultarAutorizacion(string $claveAcceso): array
    {
        try {
            $soapRequest = '<?xml version="1.0" encoding="UTF-8"?>'
                .'<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://ec.gob.sri.ws.autorizacion">'
                .'<soapenv:Header/>'
                .'<soapenv:Body>'
                .'<ec:autorizacionComprobante><claveAccesoComprobante>'.$claveAcceso.'</claveAccesoComprobante></ec:autorizacionComprobante>'
                .'</soapenv:Body>'
                .'</soapenv:Envelope>';

            $response = $this->ejecutarSoapRequest($this->urlAutorizacion, $soapRequest);
            return $this->parsearRespuestaAutorizacion($response);
        } catch (\Throwable $e) {
            return [
                'exito' => false,
                'estado' => 'ERROR',
                'mensaje' => 'Error al consultar autorizacion: '.$e->getMessage(),
                'autorizaciones' => [],
            ];
        }
    }

    public function procesarComprobante(string $xmlFirmado, string $claveAcceso, int $intentos = 4, int $espera = 3): array
    {
        $recepcion = $this->enviarComprobante($xmlFirmado);
        $this->registrarLog('recepcion', $claveAcceso, $recepcion);

        if (empty($recepcion['exito'])) {
            $enProcesamiento = false;
            foreach ($recepcion['comprobantes'] ?? [] as $comp) {
                foreach ($comp['mensajes'] ?? [] as $msg) {
                    $texto = ($msg['mensaje'] ?? '').' '.($msg['informacion_adicional'] ?? '');
                    if (stripos($texto, 'PROCESAMIENTO') !== false || stripos($texto, 'CLAVE ACCESO REGISTRADA') !== false) {
                        $enProcesamiento = true;
                        break 2;
                    }
                }
            }

            if (!$enProcesamiento) {
                return [
                    'exito' => false,
                    'etapa' => 'recepcion',
                    'resultado' => $recepcion,
                ];
            }
        }

        $ultimaAutorizacion = null;
        $ultimoEstado = null;

        for ($i = 0; $i < $intentos; $i++) {
            if ($espera > 0) {
                sleep($espera);
            }

            $autorizacion = $this->consultarAutorizacion($claveAcceso);
            $this->registrarLog('autorizacion', $claveAcceso, $autorizacion);

            if (!empty($autorizacion['exito'])) {
                return [
                    'exito' => true,
                    'etapa' => 'autorizado',
                    'recepcion' => $recepcion,
                    'resultado' => $autorizacion,
                ];
            }

            $ultimaAutorizacion = $autorizacion;
            if (!empty($autorizacion['autorizaciones'][0]['estado'])) {
                $ultimoEstado = $autorizacion['autorizaciones'][0]['estado'];
                if ($ultimoEstado === 'NO AUTORIZADO') {
                    return [
                        'exito' => false,
                        'etapa' => 'no_autorizado',
                        'recepcion' => $recepcion,
                        'resultado' => $autorizacion,
                    ];
                }
            }
        }

        return [
            'exito' => false,
            'etapa' => 'en_procesamiento',
            'mensaje' => 'La factura fue enviada al SRI y aun esta en procesamiento. Consulte nuevamente en unos minutos.',
            'recepcion' => $recepcion,
            'resultado' => $ultimaAutorizacion,
            'ultimo_estado' => $ultimoEstado,
        ];
    }

    public function verificarConectividad(): array
    {
        $resultado = ['recepcion' => false, 'autorizacion' => false];
        foreach (['recepcion' => $this->urlRecepcion, 'autorizacion' => $this->urlAutorizacion] as $key => $url) {
            try {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_HTTPGET => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                ]);
                curl_exec($ch);
                $resultado[$key] = curl_getinfo($ch, CURLINFO_HTTP_CODE) > 0;
                curl_close($ch);
            } catch (\Throwable $e) {
                $resultado[$key] = false;
            }
        }
        return $resultado;
    }

    private function ejecutarSoapRequest(string $url, string $soapRequest): string
    {
        if ($url === '') {
            throw new \RuntimeException('URL de web service SRI no configurada.');
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('La extension CURL de PHP no esta disponible.');
        }

        $url = $this->normalizarEndpointSoap($url);
        $ch = curl_init();
        $caBundle = $this->resolverCaBundle();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $soapRequest,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
            CURLOPT_SSL_VERIFYPEER => $caBundle !== null,
            CURLOPT_SSL_VERIFYHOST => $caBundle !== null ? 2 : 0,
            CURLOPT_CAINFO => $caBundle ?? '',
            CURLOPT_HTTPHEADER => [
                'Content-Type: text/xml; charset=utf-8',
                'SOAPAction: ""',
                'Content-Length: '.strlen($soapRequest),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('Error CURL: '.$error);
        }
        if ($httpCode !== 200) {
            $detalleHttp = 'HTTP Error: '.$httpCode;
            if (!empty($effectiveUrl)) { $detalleHttp .= '. URL efectiva: '.$effectiveUrl; }
            if (!empty($redirectUrl)) { $detalleHttp .= '. Redireccion: '.$redirectUrl; }
            throw new \RuntimeException($detalleHttp);
        }
        if ($response === false || $response === '') {
            throw new \RuntimeException('Respuesta vacia del SRI.');
        }

        return $response;
    }

    private function parsearRespuestaRecepcion(string $response): array
    {
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            return ['exito' => false, 'estado' => 'ERROR', 'mensaje' => 'No se pudo leer la respuesta del SRI.', 'comprobantes' => []];
        }

        $estadoNodes = $xml->xpath('//*[local-name()="estado"]');
        $estado = isset($estadoNodes[0]) ? (string)$estadoNodes[0] : 'DESCONOCIDO';
        $resultado = ['exito' => $estado === 'RECIBIDA', 'estado' => $estado, 'comprobantes' => []];

        foreach ($xml->xpath('//*[local-name()="comprobante"]') ?: [] as $comprobante) {
            $comp = ['clave_acceso' => (string)($comprobante->claveAcceso ?? ''), 'mensajes' => []];
            foreach ($comprobante->xpath('.//*[local-name()="mensaje"]') ?: [] as $mensaje) {
                $comp['mensajes'][] = [
                    'identificador' => (string)($mensaje->identificador ?? ''),
                    'mensaje' => (string)($mensaje->mensaje ?? ''),
                    'informacion_adicional' => (string)($mensaje->informacionAdicional ?? ''),
                    'tipo' => (string)($mensaje->tipo ?? ''),
                ];
            }
            $resultado['comprobantes'][] = $comp;
        }

        return $resultado;
    }

    private function parsearRespuestaAutorizacion(string $response): array
    {
        $xml = simplexml_load_string($response);
        if ($xml === false) {
            return ['exito' => false, 'estado' => 'ERROR', 'mensaje' => 'No se pudo leer la respuesta de autorizacion.', 'autorizaciones' => []];
        }

        $resultado = ['exito' => false, 'autorizaciones' => []];
        foreach ($xml->xpath('//*[local-name()="autorizacion"]') ?: [] as $autorizacion) {
            $estado = (string)($autorizacion->estado ?? '');
            $auth = [
                'estado' => $estado,
                'numero_autorizacion' => (string)($autorizacion->numeroAutorizacion ?? ''),
                'fecha_autorizacion' => (string)($autorizacion->fechaAutorizacion ?? ''),
                'ambiente' => (string)($autorizacion->ambiente ?? ''),
                'comprobante' => (string)($autorizacion->comprobante ?? ''),
                'mensajes' => [],
            ];
            foreach ($autorizacion->xpath('.//*[local-name()="mensaje"]') ?: [] as $mensaje) {
                $auth['mensajes'][] = [
                    'identificador' => (string)($mensaje->identificador ?? ''),
                    'mensaje' => (string)($mensaje->mensaje ?? ''),
                    'informacion_adicional' => (string)($mensaje->informacionAdicional ?? ''),
                    'tipo' => (string)($mensaje->tipo ?? ''),
                ];
            }
            if ($estado === 'AUTORIZADO') {
                $resultado['exito'] = true;
            }
            $resultado['autorizaciones'][] = $auth;
        }

        return $resultado;
    }

    private function registrarLog(string $tipo, string $claveAcceso, array $datos): void
    {
        $directorio = $this->config['storage']['logs'] ?? dirname(__DIR__, 3).'/storage/sri/logs/';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $archivo = rtrim($directorio, '/\\').DIRECTORY_SEPARATOR.date('Y-m-d').'_'.$tipo.'.log';
        $linea = date('Y-m-d H:i:s').' | '.$claveAcceso.' | '.json_encode($datos, JSON_UNESCAPED_UNICODE).PHP_EOL;
        file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);
    }

    private function resolverCaBundle(): ?string
    {
        $candidates = [
            ini_get('curl.cainfo') ?: '',
            'C:/wamp64/apps/phpmyadmin5.2.3/vendor/composer/ca-bundle/res/cacert.pem',
            'C:/wamp64/apps/phpmyadmin5.2.2/vendor/composer/ca-bundle/res/cacert.pem',
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/pki/tls/certs/ca-bundle.crt',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
