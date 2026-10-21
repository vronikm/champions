<?php

namespace app\services\SRI;

class FirmaElectronicaService
{
    private array $config;
    private $certificado = null;
    private $clavePrivada = null;

    public function __construct(?array $config = null)
    {
        $rutaSri = dirname(__DIR__, 3).'/config/sri.php';
        $this->config = $config ?? (is_file($rutaSri) ? require $rutaSri : []);
    }

    public function cargarCertificado(?string $rutaCertificado = null, ?string $clave = null): bool
    {
        $rutaCertificado = $rutaCertificado ?: ($this->config['firma']['archivo'] ?? '');
        $clave = $clave ?? ($this->config['firma']['clave'] ?? '');

        if ($rutaCertificado === '' || !is_file($rutaCertificado)) {
            throw new \RuntimeException('Certificado no encontrado: '.$rutaCertificado);
        }

        if ($clave === '') {
            throw new \RuntimeException('No se encontro la clave de la firma electronica.');
        }

        $certInfo = [];
        if (!openssl_pkcs12_read(file_get_contents($rutaCertificado), $certInfo, $clave)) {
            throw new \RuntimeException('No se pudo leer el certificado .p12. Verifique la clave.');
        }

        $this->certificado = $certInfo['cert'] ?? null;
        $this->clavePrivada = $certInfo['pkey'] ?? null;

        if (!$this->certificado || !$this->clavePrivada) {
            throw new \RuntimeException('El certificado no contiene certificado y clave privada validos.');
        }

        return true;
    }

    public function obtenerInfoCertificado(): array
    {
        if (!$this->certificado) {
            throw new \RuntimeException('Certificado no cargado.');
        }

        $info = openssl_x509_parse($this->certificado);
        if (!is_array($info)) {
            throw new \RuntimeException('No se pudo leer la informacion del certificado.');
        }

        return [
            'titular' => $info['subject']['CN'] ?? 'N/A',
            'emisor' => $info['issuer']['CN'] ?? 'N/A',
            'serial' => $info['serialNumber'] ?? 'N/A',
            'valido_desde' => date('Y-m-d H:i:s', $info['validFrom_time_t'] ?? time()),
            'valido_hasta' => date('Y-m-d H:i:s', $info['validTo_time_t'] ?? time()),
            'vigente' => time() < ($info['validTo_time_t'] ?? 0),
            'dias_restantes' => (int)floor((($info['validTo_time_t'] ?? time()) - time()) / 86400),
        ];
    }

    public function firmarXML(string $xml): string
    {
        if (!$this->certificado || !$this->clavePrivada) {
            $this->cargarCertificado();
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (!$dom->loadXML($xml)) {
            throw new \RuntimeException('El XML a firmar no es valido.');
        }

        if (!$dom->documentElement->hasAttribute('id')) {
            $dom->documentElement->setAttribute('id', 'comprobante');
        }

        $uid = $this->uid();
        $sigId = 'Signature-'.$uid;
        $sigPropId = 'SignedProperties-'.$sigId;
        $keyInfoId = 'KeyInfo-'.$sigId;
        $refDocId = 'Reference-'.$this->uid();

        [$certB64, $certDigest, $issuerName, $serialNumber] = $this->extraerDatosCert();
        [$modulus, $exponent] = $this->extraerClavePublica();

        $canonDoc = $dom->documentElement->C14N(false, false);
        $digestDoc = base64_encode(hash('sha1', $canonDoc, true));

        $signedPropsXML = $this->buildSignedProperties(
            $sigPropId,
            $refDocId,
            date('Y-m-d\TH:i:sP'),
            $certDigest,
            $issuerName,
            $serialNumber
        );

        $spDom = new \DOMDocument('1.0', 'UTF-8');
        $spDom->loadXML($signedPropsXML);
        $digestSP = base64_encode(hash('sha1', $spDom->documentElement->C14N(false, false), true));

        $signedInfoXML = $this->buildSignedInfo($refDocId, $digestDoc, $sigPropId, $digestSP);
        $siDom = new \DOMDocument('1.0', 'UTF-8');
        $siDom->loadXML($signedInfoXML);
        $canonSI = $siDom->documentElement->C14N(false, false);

        $sigRaw = '';
        if (!openssl_sign($canonSI, $sigRaw, $this->clavePrivada, OPENSSL_ALGO_SHA1)) {
            throw new \RuntimeException('No se pudo firmar el XML con la clave privada.');
        }

        $signatureXML = $this->buildSignature(
            $sigId,
            $signedInfoXML,
            base64_encode($sigRaw),
            $keyInfoId,
            $certB64,
            $modulus,
            $exponent,
            $sigId,
            $signedPropsXML
        );

        $fragment = $dom->createDocumentFragment();
        if (!$fragment->appendXML($signatureXML)) {
            throw new \RuntimeException('No se pudo insertar la firma en el XML.');
        }
        $dom->documentElement->appendChild($fragment);

        return $dom->saveXML();
    }

    public function verificarFirma(string $xmlFirmado): bool
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xmlFirmado)) {
            return false;
        }

        $ds = 'http://www.w3.org/2000/09/xmldsig#';
        return $dom->getElementsByTagNameNS($ds, 'Signature')->length > 0
            && $dom->getElementsByTagNameNS($ds, 'SignatureValue')->length > 0
            && $dom->getElementsByTagNameNS($ds, 'X509Certificate')->length > 0
            && $dom->getElementsByTagNameNS($ds, 'SignedInfo')->length > 0;
    }

    private function buildSignedProperties(
        string $sigPropId,
        string $refDocId,
        string $signingTime,
        string $certDigest,
        string $issuerName,
        string $serialNumber
    ): string {
        return '<etsi:SignedProperties'
            .' xmlns:etsi="http://uri.etsi.org/01903/v1.3.2#"'
            .' xmlns:ds="http://www.w3.org/2000/09/xmldsig#"'
            .' Id="'.$sigPropId.'">'
                .'<etsi:SignedSignatureProperties>'
                    .'<etsi:SigningTime>'.$signingTime.'</etsi:SigningTime>'
                    .'<etsi:SigningCertificate>'
                        .'<etsi:Cert>'
                            .'<etsi:CertDigest>'
                                .'<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>'
                                .'<ds:DigestValue>'.$certDigest.'</ds:DigestValue>'
                            .'</etsi:CertDigest>'
                            .'<etsi:IssuerSerial>'
                                .'<ds:X509IssuerName>'.htmlspecialchars($issuerName, ENT_XML1).'</ds:X509IssuerName>'
                                .'<ds:X509SerialNumber>'.$serialNumber.'</ds:X509SerialNumber>'
                            .'</etsi:IssuerSerial>'
                        .'</etsi:Cert>'
                    .'</etsi:SigningCertificate>'
                .'</etsi:SignedSignatureProperties>'
                .'<etsi:SignedDataObjectProperties>'
                    .'<etsi:DataObjectFormat ObjectReference="#'.$refDocId.'">'
                        .'<etsi:Description>contenido comprobante</etsi:Description>'
                        .'<etsi:MimeType>text/xml</etsi:MimeType>'
                    .'</etsi:DataObjectFormat>'
                .'</etsi:SignedDataObjectProperties>'
            .'</etsi:SignedProperties>';
    }

    private function buildSignedInfo(string $refDocId, string $digestDoc, string $sigPropId, string $digestSP): string
    {
        return '<ds:SignedInfo'
            .' xmlns:ds="http://www.w3.org/2000/09/xmldsig#"'
            .' xmlns:etsi="http://uri.etsi.org/01903/v1.3.2#">'
            .'<ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>'
            .'<ds:SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"/>'
            .'<ds:Reference Id="'.$refDocId.'" URI="#comprobante">'
                .'<ds:Transforms>'
                    .'<ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>'
                .'</ds:Transforms>'
                .'<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>'
                .'<ds:DigestValue>'.$digestDoc.'</ds:DigestValue>'
            .'</ds:Reference>'
            .'<ds:Reference URI="#'.$sigPropId.'" Type="http://uri.etsi.org/01903#SignedProperties">'
                .'<ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>'
                .'<ds:DigestValue>'.$digestSP.'</ds:DigestValue>'
            .'</ds:Reference>'
        .'</ds:SignedInfo>';
    }

    private function buildSignature(
        string $sigId,
        string $signedInfoXML,
        string $sigValue,
        string $keyInfoId,
        string $certB64,
        string $modulus,
        string $exponent,
        string $qualifyingTarget,
        string $signedPropsXML
    ): string {
        return '<ds:Signature'
                .' xmlns:ds="http://www.w3.org/2000/09/xmldsig#"'
                .' xmlns:etsi="http://uri.etsi.org/01903/v1.3.2#"'
                .' Id="'.$sigId.'">'
            .$signedInfoXML
            .'<ds:SignatureValue Id="SignatureValue-'.$sigId.'">'.$sigValue.'</ds:SignatureValue>'
            .'<ds:KeyInfo Id="'.$keyInfoId.'">'
                .'<ds:X509Data>'
                    .'<ds:X509Certificate>'.$certB64.'</ds:X509Certificate>'
                .'</ds:X509Data>'
                .'<ds:KeyValue>'
                    .'<ds:RSAKeyValue>'
                        .'<ds:Modulus>'.$modulus.'</ds:Modulus>'
                        .'<ds:Exponent>'.$exponent.'</ds:Exponent>'
                    .'</ds:RSAKeyValue>'
                .'</ds:KeyValue>'
            .'</ds:KeyInfo>'
            .'<ds:Object Id="XadesObjectId-'.$sigId.'">'
                .'<etsi:QualifyingProperties Target="#'.$qualifyingTarget.'">'
                    .$signedPropsXML
                .'</etsi:QualifyingProperties>'
            .'</ds:Object>'
        .'</ds:Signature>';
    }

    private function extraerDatosCert(): array
    {
        $certPEM = '';
        openssl_x509_export($this->certificado, $certPEM);

        $certB64 = str_replace(
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"],
            '',
            $certPEM
        );
        $certDigest = base64_encode(hash('sha1', base64_decode($certB64), true));

        $parsed = openssl_x509_parse($this->certificado);
        $issuerName = $this->formatearEmisor($parsed['issuer'] ?? [], base64_decode($certB64));
        $serial = (string)($parsed['serialNumber'] ?? '0');
        if (stripos($serial, '0x') === 0) {
            $serial = base_convert(substr($serial, 2), 16, 10);
        }

        return [$certB64, $certDigest, $issuerName, $serial];
    }

    private function extraerClavePublica(): array
    {
        $pubKeyRes = openssl_pkey_get_public($this->certificado);
        $keyDetails = openssl_pkey_get_details($pubKeyRes);

        if (empty($keyDetails['rsa'])) {
            throw new \RuntimeException('El certificado no contiene una clave RSA.');
        }

        return [
            base64_encode($keyDetails['rsa']['n']),
            base64_encode($keyDetails['rsa']['e']),
        ];
    }

    private function formatearEmisor($issuer, ?string $certDer = null): string
    {
        if (is_string($issuer)) {
            return $issuer;
        }

        // El X509IssuerName debe coincidir con lo que produce el validador del
        // SRI (Java X500Principal, formato RFC2253):
        //  - orden INVERSO al DER del certificado, con TODOS los componentes;
        //  - los atributos sin keyword RFC2253 (p.ej. organizationIdentifier de
        //    certificados UANATACA) se emiten como  OID=#<hexDER del valor>.
        // De lo contrario el SRI rechaza con error 39 ("el certificado firmante
        // no es valido" / "no se ajusta a XAdES").
        static $oids = [
            'organizationIdentifier' => ['oid' => '2.5.4.97', 'der' => '0603550461'],
        ];

        $partes = [];
        foreach (array_reverse($issuer, true) as $attr => $valor) {
            foreach ((array)$valor as $v) {
                if (isset($oids[$attr])) {
                    $hex = $certDer !== null
                        ? $this->derValorTrasOid($certDer, $oids[$attr]['der'], (string)$v)
                        : null;
                    $partes[] = $oids[$attr]['oid'].'=#'.($hex ?? $this->derUtf8String((string)$v));
                } else {
                    $partes[] = $attr.'='.$v;
                }
            }
        }

        return implode(',', $partes);
    }

    /**
     * Devuelve, en hex, la codificacion DER (tag+long+valor) del AttributeValue
     * que sigue al OID indicado dentro del certificado, validando que su
     * contenido coincida con $valorEsperado (desambigua issuer vs subject).
     */
    private function derValorTrasOid(string $certDer, string $oidHex, string $valorEsperado): ?string
    {
        $hex = bin2hex($certDer);
        $offset = 0;
        while (($pos = strpos($hex, $oidHex, $offset)) !== false) {
            $after = substr($hex, $pos + strlen($oidHex));
            $len = hexdec(substr($after, 2, 2));          // long en forma corta (<128)
            $tlv = substr($after, 0, 4 + $len * 2);       // tag + long + contenido
            $contenido = @hex2bin(substr($after, 4, $len * 2));
            if ($contenido === $valorEsperado) {
                return $tlv;
            }
            $offset = $pos + strlen($oidHex);
        }
        return null;
    }

    /** Codifica un valor como DER UTF8String (fallback). */
    private function derUtf8String(string $valor): string
    {
        return '0c'.sprintf('%02x', strlen($valor)).bin2hex($valor);
    }

    private function uid(): string
    {
        return bin2hex(random_bytes(4));
    }
}
