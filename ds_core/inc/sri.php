<?php
/*
|--------------------------------------------------------------------------
| Puente al servicio de facturación electrónica
|--------------------------------------------------------------------------
| La firma, la clave de acceso y el XML del SRI son del CONTRIBUYENTE, no
| de un módulo. La implementación vive hoy en ds_futbol/app/services/SRI
| porque allí nació; este archivo es el único punto del ecosistema que
| conoce esa ruta, de modo que el día que el servicio se mueva al núcleo
| haya un sitio que cambiar y no cinco.
|
| Es el mismo patrón que arenaPuente: una frontera explícita en vez de que
| cada módulo alcance por dentro a otro.
|
| LA CONFIGURACIÓN SE LEE DE LA BASE, NO SE COPIA
|
| facturas_electronicas_config es la fuente de verdad del emisor —RUC,
| razón social, ambiente, certificado—. Aquí se lee de ahí y se completan
| sólo los valores estructurales (catálogo de IVA, rutas de almacenamiento,
| endpoints del SRI) que no son configurables por el usuario.
|
| Lo que NO se toma de esa tabla es el punto de emisión: sus campos
| codigo_establecimiento y punto_emision son los de Basketball. Cada módulo
| usa el suyo, que se pide a facturacion_punto_de(). Confundirlos haría que
| dos módulos emitieran sobre la misma numeración.
*/

if (!function_exists('sri_disponible')) {

    /** Carpeta donde viven las clases del servicio. */
    function sri_ruta_servicios(): string
    {
        return dirname(__DIR__, 2) . '/ds_futbol/app/services/SRI/';
    }

    /** Raíz del almacenamiento de comprobantes del contribuyente. */
    function sri_ruta_storage(): string
    {
        return dirname(__DIR__, 2) . '/ds_futbol/storage/';
    }

    /**
     * ¿Está el servicio instalado y el emisor configurado?
     *
     * Se responde antes de intentar emitir para poder dar un mensaje que
     * diga qué falta, en vez de una excepción a mitad de una transacción.
     */
    function sri_disponible(): array
    {
        $faltan = [];

        if (!is_file(sri_ruta_servicios() . 'FacturaElectronicaService.php')) {
            $faltan[] = 'el servicio de facturación no está instalado';
        }

        $cfg = sri_config();

        if (strlen(preg_replace('/\D+/', '', (string)($cfg['emisor']['ruc'] ?? ''))) !== 13) {
            $faltan[] = 'falta el RUC del emisor (Core → Configuración SRI)';
        }
        if (trim((string)($cfg['emisor']['razon_social'] ?? '')) === '') {
            $faltan[] = 'falta la razón social del emisor';
        }

        return ['ok' => !$faltan, 'faltan' => $faltan];
    }

    /**
     * Configuración del emisor, mezclando la base con los valores
     * estructurales.
     */
    function sri_config(): array
    {
        static $cache = null;
        if ($cache !== null) { return $cache; }

        $raiz = rtrim(sri_ruta_storage(), '/\\');

        /* Lo estructural: catálogos del SRI y rutas. No es configurable
           por el usuario porque no depende de él. */
        $cfg = [
            'ambiente'             => '1',
            'tipo_emision'         => '1',
            'iva_tarifa_default'   => 0.0,
            'forma_pago_default'   => '20',
            'valores_incluyen_iva' => true,
            'emisor'               => [],
            'impuestos' => [
                'IVA' => [
                    'codigo'  => '2',
                    'tarifas' => [
                        '0'          => ['codigo' => '0', 'porcentaje' => 0],
                        '12'         => ['codigo' => '2', 'porcentaje' => 12],
                        '14'         => ['codigo' => '3', 'porcentaje' => 14],
                        '15'         => ['codigo' => '4', 'porcentaje' => 15],
                        'NO_OBJETO'  => ['codigo' => '6', 'porcentaje' => 0],
                        'EXENTO'     => ['codigo' => '7', 'porcentaje' => 0],
                    ],
                ],
            ],
            'storage' => [
                'xml_generados'   => $raiz . '/sri/xml/generados/',
                'xml_firmados'    => $raiz . '/sri/xml/firmados/',
                'xml_autorizados' => $raiz . '/sri/xml/autorizados/',
                'ride'            => $raiz . '/sri/ride/',
                'logs'            => $raiz . '/sri/logs/',
                'certificados'    => $raiz . '/certificados/',
            ],
            'formas_pago' => [
                '01' => 'SIN UTILIZACION DEL SISTEMA FINANCIERO',
                '15' => 'COMPENSACION DE DEUDAS',
                '16' => 'TARJETA DE DEBITO',
                '17' => 'DINERO ELECTRONICO',
                '18' => 'TARJETA PREPAGO',
                '19' => 'TARJETA DE CREDITO',
                '20' => 'OTROS CON UTILIZACION DEL SISTEMA FINANCIERO',
                '21' => 'ENDOSO DE TITULOS',
            ],
        ];

        $con = seguridad_conexion();
        if ($con === null) { return $cache = $cfg; }

        try {
            $f = $con->query("SELECT * FROM facturas_electronicas_config
                               WHERE config_lock = 'X' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

            if ($f && trim((string)($f['ruc'] ?? '')) !== '') {
                $cfg['ambiente']             = (string)$f['ambiente'];
                $cfg['tipo_emision']         = (string)$f['tipo_emision'];
                $cfg['iva_tarifa_default']   = (float)$f['iva_tarifa_default'];
                $cfg['forma_pago_default']   = (string)$f['forma_pago_default'];
                $cfg['valores_incluyen_iva'] = (bool)(int)$f['valores_incluyen_iva'];
                $cfg['emisor'] = [
                    'ruc'                       => (string)$f['ruc'],
                    'razon_social'              => (string)$f['razon_social'],
                    'nombre_comercial'          => (string)$f['nombre_comercial'],
                    'direccion_matriz'          => (string)$f['direccion_matriz'],
                    'direccion_establecimiento' => (string)$f['direccion_establecimiento'],
                    'obligado_contabilidad'     => (string)$f['obligado_contabilidad'],
                    'contribuyente_especial'    => (string)$f['contribuyente_especial'],
                    'agente_retencion'          => (string)$f['agente_retencion'],
                    'contribuyente_rimpe'       => (string)$f['contribuyente_rimpe'],
                ];
            }

            $formas = $con->query("SELECT codigo, nombre FROM facturas_electronicas_forma_pago
                                    WHERE activo = 1 ORDER BY orden, codigo")
                          ->fetchAll(PDO::FETCH_KEY_PAIR);
            if ($formas) { $cfg['formas_pago'] = $formas; }

        } catch (\Throwable $e) {
            /* Sin fila configurada se devuelve lo estructural; quien vaya a
               emitir lo detecta con sri_disponible() y avisa. */
        }

        return $cache = $cfg;
    }

    /**
     * Instancia del servicio, ya configurado.
     *
     * @throws RuntimeException si el servicio no está instalado.
     */
    function sri_servicio(): object
    {
        $archivo = sri_ruta_servicios() . 'FacturaElectronicaService.php';

        if (!is_file($archivo)) {
            throw new \RuntimeException(
                'El servicio de facturación electrónica no está instalado en '
                . sri_ruta_servicios() . '.');
        }

        require_once $archivo;

        $clase = '\\app\\services\\SRI\\FacturaElectronicaService';
        return new $clase(sri_config());
    }

    /**
     * Tarifa de IVA vigente, en la forma que espera el XML.
     *
     * Se resuelve desde el catálogo por la tarifa configurada: el
     * porcentaje cambia por ley y el código con el que viaja al SRI no es
     * el porcentaje, así que uno no se deduce del otro sin la tabla.
     */
    function sri_tarifa_iva(?float $tarifa = null): array
    {
        $cfg  = sri_config();
        $tar  = $tarifa ?? (float)($cfg['iva_tarifa_default'] ?? 0);
        $tabla = $cfg['impuestos']['IVA']['tarifas'] ?? [];

        foreach ($tabla as $fila) {
            if ((float)$fila['porcentaje'] === $tar) {
                return ['codigo' => $cfg['impuestos']['IVA']['codigo'] ?? '2',
                        'codigo_porcentaje' => $fila['codigo'],
                        'porcentaje' => (float)$fila['porcentaje']];
            }
        }

        return ['codigo' => '2', 'codigo_porcentaje' => '0', 'porcentaje' => 0.0];
    }

    /**
     * Tarifa a partir del CÓDIGO de porcentaje del SRI.
     *
     * Es el camino inverso al anterior y hace falta porque los catálogos
     * del sistema guardan el código, no el porcentaje: la tarifa cambia
     * por ley y el código sobrevive al cambio.
     */
    function sri_tarifa_por_codigo(string $codigo): array
    {
        $cfg = sri_config();

        foreach ($cfg['impuestos']['IVA']['tarifas'] ?? [] as $fila) {
            if ((string)$fila['codigo'] === $codigo) {
                return ['codigo' => $cfg['impuestos']['IVA']['codigo'] ?? '2',
                        'codigo_porcentaje' => (string)$fila['codigo'],
                        'porcentaje' => (float)$fila['porcentaje']];
            }
        }

        /* Código desconocido: 0 %. No se inventa una tarifa, porque
           equivocarla cobra de más al cliente o deja de declarar impuesto. */
        return ['codigo' => '2', 'codigo_porcentaje' => '0', 'porcentaje' => 0.0];
    }

    /**
     * Codigo SRI del tipo de identificacion.
     *
     * 04 RUC · 05 cedula · 06 pasaporte · 07 consumidor final.
     * Si el tipo declarado y la longitud del numero no concuerdan, manda la
     * longitud: es el dato que el SRI valida.
     */
    function sri_tipo_identificacion(string $tipo, string $identificacion): string
    {
        $tipo = strtoupper(trim($tipo));
        $num  = preg_replace('/\D+/', '', $identificacion);

        if ($tipo === '04' || $tipo === 'RUC' || strlen($num) === 13)          { return '04'; }
        if ($tipo === '05' || $tipo === 'CED' || strlen($num) === 10)          { return '05'; }
        if ($tipo === '06' || $tipo === 'PAS' || $tipo === 'PASAPORTE')        { return '06'; }
        if ($tipo === '07' || $tipo === 'CONSUMIDOR_FINAL')                    { return '07'; }

        return '08';
    }

    /** ¿La identificacion es valida para el codigo que le corresponde? */
    function sri_identificacion_valida(string $identificacion, string $tipo = '04'): bool
    {
        $codigo = sri_tipo_identificacion($tipo, $identificacion);
        $num    = preg_replace('/\D+/', '', $identificacion);

        if ($codigo === '05') { return strlen($num) === 10 && sri_cedula_valida($num); }
        /* Un RUC son 13 dígitos cuya cédula base debe ser válida y cuyo
           establecimiento no puede ser 000. */
        if ($codigo === '04') {
            return strlen($num) === 13
                && substr($num, -3) !== '000'
                && sri_cedula_valida(substr($num, 0, 10));
        }
        if ($codigo === '07') { return $num === '9999999999999'; }

        $t = trim($identificacion);
        return strlen($t) >= 3 && strlen($t) <= 20;
    }

    /**
     * Digito verificador de la cedula ecuatoriana (modulo 10).
     *
     * Se valida aqui y no sólo la longitud porque un digito cambiado
     * produce un comprobante que el SRI devuelve, y devolverlo significa
     * anular y reemitir con otro secuencial.
     */
    function sri_cedula_valida(string $cedula): bool
    {
        if (!preg_match('/^\d{10}$/', $cedula)) { return false; }

        $provincia = (int)substr($cedula, 0, 2);
        if ($provincia < 1 || ($provincia > 24 && $provincia !== 30)) { return false; }

        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $v = (int)$cedula[$i] * (($i % 2 === 0) ? 2 : 1);
            $suma += ($v > 9) ? $v - 9 : $v;
        }

        $verificador = (10 - ($suma % 10)) % 10;

        return $verificador === (int)$cedula[9];
    }

    /**
     * Texto apto para el XML: sin etiquetas, sin saltos y recortado.
     *
     * El SRI rechaza el comprobante si un campo excede su longitud, y un
     * salto de linea dentro de una razon social rompe el XML.
     */
    function sri_texto(string $valor, int $limite = 300): string
    {
        $v = html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $v = strip_tags($v);
        $v = preg_replace('/\s+/u', ' ', $v);

        return mb_substr(trim($v), 0, $limite, 'UTF-8');
    }
}
