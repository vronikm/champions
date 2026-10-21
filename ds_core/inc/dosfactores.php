<?php
/*
|--------------------------------------------------------------------------
| Segundo factor de autenticación (TOTP)
|--------------------------------------------------------------------------
| Implementa TOTP tal como lo define el RFC 6238, que es lo que hablan
| Google Authenticator, Microsoft Authenticator, Authy, 1Password y
| cualquier otra aplicación de códigos. No hay servicio externo: el
| secreto no sale de este servidor y el código se calcula aquí.
|
| POR QUÉ TOTP Y NO UN CÓDIGO POR SMS O CORREO
|
| El SMS se intercepta con un cambio de tarjeta —el fraude más común en la
| región— y el correo suele estar protegido por la misma contraseña que se
| intenta reforzar: si alguien la tiene, tiene también el segundo factor.
| TOTP no viaja por ningún canal: se calcula a la vez en el teléfono y en
| el servidor a partir de un secreto compartido una sola vez.
|
| LOS 30 SEGUNDOS Y LA VENTANA
|
| El código cambia cada 30 segundos. Se aceptan también el anterior y el
| siguiente porque los relojes se desfasan y porque el usuario tarda en
| teclear; sin esa holgura, una parte de los intentos legítimos falla y la
| gente acaba pidiendo que se desactive. Con ventana de 1 el margen real
| es de metro y medio: 90 segundos.
|
| EL SECRETO SE GUARDA TAL CUAL
|
| El servidor NECESITA el secreto para calcular el código, así que no
| puede guardarse como un hash igual que una contraseña. Queda protegido
| por el acceso a la base de datos. Es la limitación conocida de TOTP y la
| razón de que el segundo factor complemente a la contraseña en lugar de
| sustituirla.
*/

if (!function_exists('totp_secreto_nuevo')) {

    /** Duracion de cada codigo, en segundos. */
    function totp_periodo(): int { return 30; }

    /** Digitos del codigo. */
    function totp_digitos(): int { return 6; }

    /**
     * Pasos de tolerancia hacia atras y hacia adelante.
     *
     * 1 = se aceptan el codigo anterior, el actual y el siguiente.
     */
    function totp_ventana(): int { return 1; }

    /*==================================================================
      Base32

      El secreto se comparte en base32 y no en hexadecimal porque es lo
      que esperan las aplicaciones de codigos y lo que cabe teclear a mano
      cuando la camara no lee el QR: sin minusculas ni digitos que se
      confundan con letras.
      ==================================================================*/

    function base32_alfabeto(): string
    {
        return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    }

    function base32_codificar(string $bytes): string
    {
        $alfabeto = base32_alfabeto();
        $bits = '';

        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }

        $salida = '';
        foreach (str_split($bits, 5) as $trozo) {
            /* El ultimo trozo puede quedar corto: se rellena por la
               derecha, que es como lo define el RFC 4648. */
            $salida .= $alfabeto[bindec(str_pad($trozo, 5, '0', STR_PAD_RIGHT))];
        }

        return $salida;
    }

    function base32_decodificar(string $texto): string
    {
        $alfabeto = base32_alfabeto();
        /* Se toleran minusculas, espacios y el relleno '=': es como la
           gente copia y pega el secreto. */
        $texto = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $texto));

        $bits = '';
        for ($i = 0, $n = strlen($texto); $i < $n; $i++) {
            $pos = strpos($alfabeto, $texto[$i]);
            if ($pos === false) { continue; }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $trozo) {
            /* Sólo los grupos completos de 8 bits son un byte; lo que
               sobra es relleno del codificador, no informacion. */
            if (strlen($trozo) === 8) { $bytes .= chr(bindec($trozo)); }
        }

        return $bytes;
    }

    /*==================================================================
      Secreto y codigos
      ==================================================================*/

    /**
     * Secreto nuevo de 160 bits.
     *
     * Es el tamano que recomienda el RFC 4226 para HMAC-SHA1 y el que
     * generan las aplicaciones conocidas. random_bytes y no mt_rand: esto
     * es material criptografico, no un sorteo.
     */
    function totp_secreto_nuevo(): string
    {
        return base32_codificar(random_bytes(20));
    }

    /**
     * Codigo correspondiente a un instante.
     *
     * @param string   $secreto  en base32
     * @param int|null $momento  marca de tiempo; null = ahora
     * @param int      $desfase  pasos de 30 s a sumar o restar
     */
    function totp_codigo(string $secreto, ?int $momento = null, int $desfase = 0): string
    {
        $clave = base32_decodificar($secreto);
        if ($clave === '') { return ''; }

        $paso = (int)floor((($momento ?? time()) / totp_periodo())) + $desfase;

        /* El contador viaja como entero de 64 bits en orden de red. pack
           'J' lo da directamente; hacerlo a mano con dos enteros de 32 es
           donde suelen aparecer los fallos por encima de 2038. */
        $contador = pack('J', $paso);

        $hash = hash_hmac('sha1', $contador, $clave, true);

        /* Truncamiento dinamico del RFC 4226: el ultimo nibble dice desde
           que byte leer los cuatro que forman el numero. */
        $inicio = ord($hash[19]) & 0x0F;
        $numero = ((ord($hash[$inicio])     & 0x7F) << 24)
                | ((ord($hash[$inicio + 1]) & 0xFF) << 16)
                | ((ord($hash[$inicio + 2]) & 0xFF) << 8)
                |  (ord($hash[$inicio + 3]) & 0xFF);

        $modulo = 10 ** totp_digitos();

        return str_pad((string)($numero % $modulo), totp_digitos(), '0', STR_PAD_LEFT);
    }

    /**
     * ¿Es valido este codigo?
     *
     * Recorre la ventana de tolerancia y compara SIEMPRE las mismas veces:
     * salir en cuanto acierta filtraria por tiempo cual de los pasos
     * coincidio, y con eso se puede deducir el desfase del reloj del
     * servidor. hash_equals ademas evita la fuga byte a byte.
     */
    function totp_valido(string $secreto, string $codigo, ?int $momento = null): bool
    {
        $codigo = preg_replace('/\D+/', '', $codigo);
        if (strlen($codigo) !== totp_digitos()) { return false; }

        $ventana = totp_ventana();
        $acierta = false;

        for ($d = -$ventana; $d <= $ventana; $d++) {
            if (hash_equals(totp_codigo($secreto, $momento, $d), $codigo)) {
                $acierta = true;
            }
        }

        return $acierta;
    }

    /**
     * URI otpauth:// para el codigo QR.
     *
     * El emisor aparece dos veces —en la etiqueta y como parametro— a
     * proposito: hay aplicaciones que leen uno y aplicaciones que leen el
     * otro, y sin ambos la cuenta se guarda sin nombre y el usuario no
     * sabe cual es cual cuando tiene varias.
     */
    function totp_uri(string $secreto, string $usuario, string $emisor): string
    {
        $etiqueta = rawurlencode($emisor) . ':' . rawurlencode($usuario);

        return 'otpauth://totp/' . $etiqueta . '?' . http_build_query([
            'secret'    => $secreto,
            'issuer'    => $emisor,
            'algorithm' => 'SHA1',
            'digits'    => totp_digitos(),
            'period'    => totp_periodo(),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** El secreto en grupos de cuatro, para teclearlo sin perderse. */
    function totp_secreto_legible(string $secreto): string
    {
        return trim(chunk_split($secreto, 4, ' '));
    }

    /*==================================================================
      Codigos de recuperacion

      Si se pierde el telefono, sin esto la cuenta queda cerrada para
      siempre y alguien acaba desactivando el segundo factor de todo el
      sistema para poder entrar. Con ellos, el usuario tiene una salida
      que no obliga a bajar la seguridad de los demas.
      ==================================================================*/

    /** Diez codigos de un solo uso, en el formato XXXX-XXXX. */
    function recuperacion_generar(int $cuantos = 10): array
    {
        /* Sin I, O, 0 ni 1: se confunden al leerlos de un papel, y estos
           codigos se apuntan en papel por definicion. */
        $alfabeto = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $codigos  = [];

        for ($i = 0; $i < $cuantos; $i++) {
            $c = '';
            for ($j = 0; $j < 8; $j++) {
                if ($j === 4) { $c .= '-'; }
                $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
            }
            $codigos[] = $c;
        }

        return $codigos;
    }

    /**
     * Huella de un codigo de recuperacion, para guardarla.
     *
     * Se guarda el hash y no el codigo: quien lea la tabla no debe poder
     * entrar con lo que encuentre. password_hash y no sha1, porque un
     * codigo de 8 caracteres se recorre entero con un diccionario si el
     * hash es rapido.
     */
    function recuperacion_hash(string $codigo): string
    {
        return password_hash(recuperacion_normalizar($codigo), PASSWORD_BCRYPT);
    }

    /** Mayusculas y sin separadores: el usuario lo teclea como quiere. */
    function recuperacion_normalizar(string $codigo): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo));
    }

    function recuperacion_verificar(string $codigo, string $hash): bool
    {
        return password_verify(recuperacion_normalizar($codigo), $hash);
    }
}
