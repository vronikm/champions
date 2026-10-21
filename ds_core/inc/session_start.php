<?php
    /*
    |----------------------------------------------------------------------
    | Inicio de sesion endurecido
    |----------------------------------------------------------------------
    | SameSite=Lax evita que el navegador adjunte la cookie de sesion en
    | peticiones POST originadas en otro sitio: es la primera barrera
    | contra CSRF. HttpOnly impide que JavaScript (o un XSS) lea el
    | identificador de sesion. Secure se activa solo bajo HTTPS para no
    | romper el entorno local por HTTP.
    */

    if (session_status() === PHP_SESSION_NONE) {

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        /*
        | Es una maquina local si el nombre es localhost o la IP pertenece
        | a un rango privado. Ahi se trabaja por HTTP y no se fuerza nada;
        | fuera de ahi, el login sin cifrar entrega la contrasena a
        | cualquiera que este en el camino.
        */
        $anfitrion = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $anfitrion = explode(':', $anfitrion)[0];
        $ipServidor = (string)($_SERVER['SERVER_ADDR'] ?? '');

        $esLocal = in_array($anfitrion, ['localhost', '127.0.0.1', '::1'], true)
                || str_ends_with($anfitrion, '.local')
                || str_ends_with($anfitrion, '.test')
                || ($ipServidor !== '' && filter_var(
                        $ipServidor, FILTER_VALIDATE_IP,
                        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false);

        if (defined('DS_FORZAR_HTTPS') && DS_FORZAR_HTTPS && !$https && !$esLocal) {
            $destino = 'https://' . ($_SERVER['HTTP_HOST'] ?? $anfitrion)
                     . ($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $destino, true, 301);
            exit();
        }

        /*
        | Cabeceras de seguridad, comunes a todo el ecosistema.
        */
        if (!headers_sent()) {
            // Impide incrustar el login en un iframe ajeno para superponerle
            // una capa y robar las pulsaciones (clickjacking).
            header('X-Frame-Options: SAMEORIGIN');

            /*
            | Content-Security-Policy
            |
            | QUE ESTO NO ES UNA CSP ESTRICTA, Y POR QUE
            |
            | La aplicacion imprime <script> en linea por todas partes: los
            | avisos de SweetAlert2 se generan desde PHP, y cada vista lleva
            | su propio bloque de comportamiento. Una CSP estricta —con
            | nonce por peticion— exige tocar esos bloques uno a uno en mas
            | de cien vistas. Mientras tanto, 'unsafe-inline' los mantiene
            | vivos, y eso significa que un XSS inyectado SIGUE
            | ejecutandose. Esa parte no esta cubierta.
            |
            | LO QUE SI CUBRE, QUE NO ES POCO
            |
            |   default-src 'self'   nada se carga de otro origen salvo lo
            |                        que se permita expresamente abajo.
            |   script-src           bloquea <script src="sitio-ajeno">. Un
            |                        XSS que intente traer su carga desde
            |                        fuera se queda sin ella.
            |   connect-src 'self'   ningun fetch ni XHR puede SACAR datos
            |                        hacia otro servidor. Contra el robo de
            |                        informacion es la linea mas util de
            |                        toda la politica.
            |   object-src 'none'    nada de <object>/<embed>: complementos
            |                        antiguos son una via de ejecucion.
            |   base-uri 'self'      impide que un <base> inyectado
            |                        redirija TODAS las rutas relativas de
            |                        la pagina a un servidor ajeno.
            |   form-action 'self'   impide reapuntar un formulario para que
            |                        las credenciales se envien a otro sitio.
            |   frame-ancestors      lo que ya habia contra el clickjacking.
            |
            | LOS CDN ESTAN LISTADOS UNO A UNO
            |
            | html2canvas, jsPDF, xlsx y chart.js se cargan de cdnjs y
            | jsdelivr, y los iconos de jsdelivr e ionicframework. Se
            | permiten esos origenes y ninguno mas.
            |
            | Es una dependencia que conviene quitar: esos archivos se
            | sirven SIN comprobacion de integridad, asi que quien controle
            | el CDN ejecuta lo que quiera dentro de una sesion con acceso a
            | datos de menores. Traerlos al servidor es la solucion; hasta
            | entonces, al menos ningun OTRO origen puede colar un script.
            */
            $cdnScript = 'https://cdnjs.cloudflare.com https://cdn.jsdelivr.net';
            $cdnEstilo = 'https://cdn.jsdelivr.net https://code.ionicframework.com';

            header('Content-Security-Policy: '
                . "default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$cdnScript}; "
                . "style-src 'self' 'unsafe-inline' {$cdnEstilo}; "
                /* data: para los QR y las miniaturas generadas en el
                   navegador; blob: para las descargas que arma jsPDF. */
                . "img-src 'self' data: blob:; "
                . "font-src 'self' data: {$cdnEstilo}; "
                . "connect-src 'self'; "
                . "object-src 'none'; "
                . "base-uri 'self'; "
                . "form-action 'self'; "
                . "frame-ancestors 'self'");

            // Que el navegador no adivine el tipo de un archivo servido.
            header('X-Content-Type-Options: nosniff');

            // No filtrar la URL interna al salir hacia otro sitio.
            header('Referrer-Policy: same-origin');

            // Nada de esto necesita la aplicacion.
            header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');

            // HSTS solo si ya se llego por HTTPS: anunciarlo por HTTP no
            // sirve de nada y el navegador lo ignora.
            if ($https && defined('DS_HSTS_MESES') && DS_HSTS_MESES > 0) {
                header('Strict-Transport-Security: max-age=' . (DS_HSTS_MESES * 2592000));
            }
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name(APP_SESSION_NAME);
        session_start();
    }

    require_once __DIR__ . "/seguridad.php";

    /* Nombre legal y logo de la organización: los consumen los PDF,
       recibos y reportes de todos los módulos. */
    require_once __DIR__ . "/organizacion.php";

    /* Botones, iconos y pies de formulario del ecosistema: el estándar de
       interfaz vive en funciones para que las vistas no puedan desviarse. */
    require_once __DIR__ . "/ui.php";

    /* Puntos de emisión y numeración de comprobantes. Va en el núcleo
       porque la numeración es del contribuyente y no de un módulo: si
       cada uno llevara su propia cuenta, dos podrían emitir el mismo
       número y el SRI lo rechazaría por duplicado. */
    require_once __DIR__ . "/facturacion.php";

    /* Puente al servicio SRI: clave de acceso, XML y validaciones de
       identificación. Es el único punto que conoce dónde vive hoy la
       implementación, para que moverla al núcleo sea un cambio de una
       línea y no de cinco módulos. */
    require_once __DIR__ . "/sri.php";

    /* Subida y normalizacion de imagenes. Vive en el nucleo porque la
       validacion de un archivo subido es identica en todos los modulos
       y es de los puntos mas atacados: repetirla es garantizar que una
       copia se quede corta. */
    require_once __DIR__ . "/imagenes.php";

    /* Segundo factor. El algoritmo va aparte del servicio a proposito:
       asi se puede comprobar contra los vectores del RFC 6238 sin tocar
       la base de datos, que es como se verifico que interopera con
       Google Authenticator y no solo consigo mismo. */
    require_once __DIR__ . "/dosfactores.php";
    require_once __DIR__ . "/dosfactores_servicio.php";

    /* Generacion de codigos QR. Puente, como sri.php: el secreto del
       segundo factor NO puede ir a un generador ajeno, ni siquiera como
       URL de imagen, porque eso es entregarle la llave a un tercero y
       dejarla escrita en sus registros. */
    require_once __DIR__ . "/qr.php";
