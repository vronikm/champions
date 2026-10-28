<?php
/*
|--------------------------------------------------------------------------
| Redes sociales (migración 073)
|--------------------------------------------------------------------------
| Configuración (Core › Redes sociales) y publicación de la tarjeta de
| cumpleaños (Fútbol › Cumpleaños) en la página de Facebook y la cuenta
| profesional de Instagram de la escuela, con la Graph API de Meta.
|
| QUÉ SE PUEDE Y QUÉ NO
|   · Facebook: la imagen se SUBE (multipart), así que funciona desde
|     cualquier servidor, también en desarrollo.
|   · Instagram: la API no acepta subidas; descarga la imagen de una
|     dirección PÚBLICA. Sólo funciona con el sistema publicado en internet.
|   · WhatsApp, TikTok y X no tienen API para esto: la pantalla ofrece
|     «Compartir» (la hoja del teléfono) y «Descargar».
|
| EL TOKEN
| El token de página da acceso a publicar en nombre de la escuela. Se
| guarda cifrado (AES-256-GCM) con una llave derivada de TOKEN_SECRET, que
| vive fuera de la base. Nunca vuelve a la pantalla ni a los registros.
| Rotar TOKEN_SECRET obliga a volver a escribirlo.
|
| LOPDP
| Sólo se publica a quien tiene vigente el consentimiento de tipo IMAGEN
| (redes_autorizado_imagen): lo comprueba el servidor, no la pantalla.
*/

if (!function_exists('redes_config')) {

    /** Valores por defecto de la configuración (antes de la 073 o sin fila). */
    function redes_config_defecto(): array
    {
        return [
            'url_facebook' => '', 'url_instagram' => '', 'url_tiktok' => '', 'url_youtube' => '', 'whatsapp' => '',
            'fb_activo' => 'N', 'fb_pagina_id' => '', 'fb_pagina_nombre' => '', 'fb_token_cifrado' => '',
            'ig_activo' => 'N', 'ig_cuenta_id' => '', 'graph_version' => 'v21.0',
            'cumple_mensaje' => '¡Feliz cumpleaños, {nombre}! 🎉⚽ Toda la familia de {escuela} te desea un gran día por tus {edad} años.',
            'cumple_hashtags' => '#FelizCumpleaños', 'updated_by' => '', 'updated_at' => '',
        ];
    }

    /** La configuración guardada. 'token_guardado' dice si hay token; el token no sale. */
    function redes_config(): array
    {
        $c = redes_config_defecto();
        $con = seguridad_conexion();
        if ($con) {
            try {
                $f = $con->query("SELECT * FROM redes_config WHERE config_lock = 'X' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
                if ($f) { $c = array_merge($c, array_map(static fn($v) => $v ?? '', $f)); }
            } catch (\Throwable $e) {
                /* Sin la migración 073: los valores por defecto, nada activo. */
            }
        }
        $c['token_guardado'] = trim((string)$c['fb_token_cifrado']) !== '';
        unset($c['fb_token_cifrado']);
        return $c;
    }

    /*----------  Cifrado del token  ----------*/

    function redes_llave(): string
    {
        if (!defined('TOKEN_SECRET') || TOKEN_SECRET === '') {
            throw new \RuntimeException('Falta TOKEN_SECRET en la configuración del servidor.');
        }
        return hash('sha256', 'ds-redes-sociales|' . TOKEN_SECRET, true);
    }

    function redes_cifrar(string $texto): string
    {
        $iv = random_bytes(12);
        $cifrado = openssl_encrypt($texto, 'aes-256-gcm', redes_llave(), OPENSSL_RAW_DATA, $iv, $etiqueta);
        if ($cifrado === false) { throw new \RuntimeException('No se pudo cifrar el token.'); }
        return base64_encode($iv . $etiqueta . $cifrado);
    }

    function redes_descifrar(string $blob): string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 29) { return ''; }
        $texto = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', redes_llave(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $texto === false ? '' : $texto;
    }

    /** El token de página, descifrado ('' si no hay o no se puede leer). */
    function redes_token(): string
    {
        $con = seguridad_conexion();
        if (!$con) { return ''; }
        try {
            $blob = (string)$con->query("SELECT fb_token_cifrado FROM redes_config WHERE config_lock = 'X'")->fetchColumn();
            return $blob !== '' ? redes_descifrar($blob) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /*----------  Graph API  ----------*/

    /**
     * Llamada a la Graph API. Devuelve ['ok' => bool, 'datos' => array, 'error' => string].
     * $campos va como POST (multipart si lleva un CURLFile) o como consulta en GET.
     */
    function redes_graph(string $metodo, string $ruta, array $campos, string $version = 'v21.0'): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'datos' => [], 'error' => 'El servidor no tiene la extensión cURL de PHP.'];
        }
        $version = preg_match('/^v\d{1,2}\.\d$/', $version) ? $version : 'v21.0';
        /* DS_GRAPH_BASE sólo lo define una prueba (pruebas/qa_redes_publicar.php)
           para hablar con un Meta falso local; en el sistema no existe. */
        $base = defined('DS_GRAPH_BASE') ? DS_GRAPH_BASE : 'https://graph.facebook.com/';
        $url = $base . $version . '/' . ltrim($ruta, '/');
        $ch = curl_init();
        $opciones = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 60];
        if ($metodo === 'GET') {
            $opciones[CURLOPT_URL] = $url . '?' . http_build_query($campos);
        } else {
            $opciones[CURLOPT_URL] = $url;
            $opciones[CURLOPT_POST] = true;
            $opciones[CURLOPT_POSTFIELDS] = $campos;
        }
        curl_setopt_array($ch, $opciones);
        $cuerpo = curl_exec($ch);
        $errorRed = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false || $cuerpo === '') {
            return ['ok' => false, 'datos' => [], 'error' => 'No se pudo conectar con Meta' . ($errorRed !== '' ? ': ' . $errorRed : '.')];
        }
        $datos = json_decode((string)$cuerpo, true) ?: [];
        if (isset($datos['error'])) {
            $e = $datos['error'];
            $msg = (int)($e['code'] ?? 0) === 190
                ? 'El token de la página no es válido o caducó. Genere uno nuevo y guárdelo en Core › Redes sociales.'
                : 'Meta respondió: ' . mb_substr((string)($e['message'] ?? 'error desconocido'), 0, 300);
            return ['ok' => false, 'datos' => $datos, 'error' => $msg];
        }
        return ['ok' => true, 'datos' => $datos, 'error' => ''];
    }

    /*----------  El mensaje  ----------*/

    /**
     * El texto de la publicación a partir de la plantilla. Por privacidad de
     * menores, {nombre} es sólo el primer nombre y {apellido} sólo el primer
     * apellido: el nombre completo no tiene marcador.
     */
    function redes_mensaje_cumple(array $alumno, ?array $cfg = null): string
    {
        $cfg ??= redes_config();
        $limpio = static fn($v) => trim(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $nombre = mb_convert_case(mb_strtolower($limpio($alumno['alumno_primernombre'] ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $apellido = mb_convert_case(mb_strtolower($limpio($alumno['alumno_apellidopaterno'] ?? ''), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $texto = strtr((string)$cfg['cumple_mensaje'], [
            '{nombre}' => $nombre, '{apellido}' => $apellido,
            '{edad}' => (string)(int)($alumno['edad'] ?? 0),
            '{sede}' => $limpio($alumno['sede_nombre'] ?? ''),
            '{escuela}' => function_exists('ds_nombre_organizacion') ? ds_nombre_organizacion() : '',
        ]);
        $hashtags = trim((string)$cfg['cumple_hashtags']);
        return trim($texto . ($hashtags !== '' ? "\n\n" . $hashtags : ''));
    }

    /*----------  Publicar  ----------*/

    /** Sube la imagen a la página de Facebook. ['ok', 'id', 'error']. */
    function redes_publicar_facebook(string $archivoJpeg, string $mensaje, array $cfg): array
    {
        $token = redes_token();
        if ($token === '' || $cfg['fb_pagina_id'] === '') {
            return ['ok' => false, 'id' => '', 'error' => 'Facebook no está configurado (página y token en Core › Redes sociales).'];
        }
        $r = redes_graph('POST', rawurlencode($cfg['fb_pagina_id']) . '/photos', [
            'source' => new \CURLFile($archivoJpeg, 'image/jpeg', 'cumpleanos.jpg'),
            'message' => $mensaje,
            'access_token' => $token,
        ], $cfg['graph_version']);
        return ['ok' => $r['ok'], 'id' => (string)($r['datos']['post_id'] ?? $r['datos']['id'] ?? ''), 'error' => $r['error']];
    }

    /**
     * Publica en Instagram: crea el contenedor con la imagen (que Meta
     * descarga de $urlPublica) y lo publica. ['ok', 'id', 'error'].
     */
    function redes_publicar_instagram(string $urlPublica, string $mensaje, array $cfg): array
    {
        $token = redes_token();
        if ($token === '' || $cfg['ig_cuenta_id'] === '') {
            return ['ok' => false, 'id' => '', 'error' => 'Instagram no está configurado (cuenta y token en Core › Redes sociales).'];
        }
        $host = strtolower((string)parse_url($urlPublica, PHP_URL_HOST));
        if (!str_starts_with($urlPublica, 'https://') || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            return ['ok' => false, 'id' => '', 'error' => 'Instagram descarga la imagen desde internet: sólo funciona con el sistema publicado (https), no en este servidor local.'];
        }
        $cuenta = rawurlencode($cfg['ig_cuenta_id']);
        $c = redes_graph('POST', $cuenta . '/media', ['image_url' => $urlPublica, 'caption' => $mensaje, 'access_token' => $token], $cfg['graph_version']);
        if (!$c['ok'] || empty($c['datos']['id'])) {
            return ['ok' => false, 'id' => '', 'error' => $c['error'] ?: 'Instagram no aceptó la imagen.'];
        }
        /* Meta puede tardar unos segundos en tener lista la imagen. */
        for ($intento = 0; $intento < 4; $intento++) {
            $p = redes_graph('POST', $cuenta . '/media_publish', ['creation_id' => $c['datos']['id'], 'access_token' => $token], $cfg['graph_version']);
            if ($p['ok']) { return ['ok' => true, 'id' => (string)($p['datos']['id'] ?? ''), 'error' => '']; }
            if ((int)($p['datos']['error']['code'] ?? 0) !== 9007) { return ['ok' => false, 'id' => '', 'error' => $p['error']]; }
            sleep(3);
        }
        return ['ok' => false, 'id' => '', 'error' => 'Instagram no terminó de procesar la imagen. Inténtelo de nuevo en un momento.'];
    }
}
