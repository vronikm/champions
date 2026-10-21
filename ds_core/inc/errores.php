<?php
/*
|--------------------------------------------------------------------------
| Politica de errores del ecosistema
|--------------------------------------------------------------------------
| Un error nunca se le cuenta al navegador de otra persona. Se registra en
| el servidor y al cliente se le da un mensaje neutro.
|
| POR QUE, CON LO QUE SE VIO
|
| Con display_errors activo y Xdebug cargado, una excepcion no capturada
| devuelve la traza COMPLETA, y Xdebug imprime los argumentos de cada
| llamada. Se comprobo con una sonda:
|
|     conectar( $usuario = 'root', $clave = 'CLAVE-SECRETA-DE-PRUEBA' )
|
| El modelo conecta con new PDO($dsn, $this->user, $this->pass), asi que la
| clave de la base viaja como argumento. Y en un sistema que mueve cedulas,
| telefonos y nombres de menores, cualquier funcion que reciba uno de esos
| datos lo imprimiria igual. No es «se ven las rutas del disco».
|
| Ademas la interfaz lo amplifica: cuando la respuesta no es JSON, ajax.js
| ofrece un desplegable «Ver detalle tecnico del servidor» con el texto
| crudo. La traza acababa dentro del dialogo.
|
| QUIEN VE LOS ERRORES
|
| Solo quien pide desde la propia maquina (127.0.0.1 o ::1) y la linea de
| comandos. Es exactamente donde trabaja quien desarrolla, asi que su forma
| de trabajar no cambia en nada.
|
| Se decide por el cliente y NO por una constante de entorno a proposito:
| una bandera hay que acordarse de cambiarla al desplegar, y el dia que se
| olvide el sistema queda contando sus secretos. Ademas cubre un caso que
| una bandera no ve: las tablets y telefonos que entran por la red local
| —que aqui es un uso previsto, como reconoce DS_FORZAR_HTTPS— son clientes
| remotos, y con la bandera en «desarrollo» habrian recibido la traza.
|
| LO QUE ESTE ARCHIVO NO HACE
|
| No convierte los avisos en excepciones. Seria mas limpio, y cambiaria el
| comportamiento de codigo que hoy funciona conviviendo con sus warnings:
| eso es otra tarea, con sus pruebas, no un efecto secundario de tapar una
| fuga.
*/

/**
 * Si quien pide es la propia maquina.
 *
 * Por consola no hay cliente: se trata como local, que es donde corren las
 * pruebas y las tareas programadas.
 */
function ds_cliente_es_local(): bool
{
    if (PHP_SAPI === 'cli') { return true; }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    /* Si hay proxy delante, la peticion no es local aunque REMOTE_ADDR lo
       parezca: el proxy si esta en la misma maquina, el cliente no. */
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) || isset($_SERVER['HTTP_X_REAL_IP'])) {
        return false;
    }

    return in_array($ip, ['127.0.0.1', '::1'], true);
}

/* El registro se escribe siempre, se muestre o no. */
ini_set('log_errors', '1');

if (ds_cliente_es_local()) {
    /* En la maquina de quien desarrolla no se toca nada: los errores se
       siguen viendo como hasta ahora. */
    return;
}

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

/**
 * Si la peticion espera JSON.
 *
 * Los endpoints de ajax.js responden JSON y la interfaz lo parsea. Si aqui
 * se devolviera HTML, el dialogo enseñaria el HTML crudo, que es justo lo
 * que se quiere evitar.
 */
function ds_peticion_espera_json(): bool
{
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') { return true; }
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) { return true; }
    /* Los endpoints del sistema viven bajo .../ajax/. */
    return str_contains($_SERVER['REQUEST_URI'] ?? '', '/ajax/');
}

/**
 * Lo unico que ve el cliente. Sin rutas, sin nombres de clase, sin traza.
 *
 * Lleva un identificador para que quien atienda la incidencia pueda buscar
 * el detalle en el registro sin pedirle al usuario que copie nada tecnico.
 */
function ds_responder_error_neutro(string $referencia): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: ' . (ds_peticion_espera_json()
            ? 'application/json; charset=utf-8'
            : 'text/html; charset=utf-8'));
    }

    if (ds_peticion_espera_json()) {
        /* La forma la manda alertas_ajax, no este archivo: entiende
           «simple», «recargar», «limpiar», «redireccionar» y los avisos
           emergentes, y NO entiende «error». Con un tipo que no conoce,
           el JSON se parsea bien y no se dibuja nada: el formulario se
           quedaria mudo, que es peor que un mensaje feo. */
        echo json_encode([
            'tipo'   => 'simple',
            'icono'  => 'error',
            'titulo' => 'Error del servidor',
            'texto'  => 'No se pudo completar la operacion. Referencia: ' . $referencia,
        ], JSON_UNESCAPED_UNICODE);
        return;
    }

    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">'
       . '<title>Error</title></head><body style="font-family:system-ui;padding:2rem">'
       . '<h1 style="font-size:1.2rem">No se pudo completar la operacion</h1>'
       . '<p>El problema ha quedado registrado. Referencia: <code>'
       . htmlspecialchars($referencia, ENT_QUOTES, 'UTF-8') . '</code></p>'
       . '</body></html>';
}

/** Un identificador corto para casar lo que ve el usuario con el registro. */
function ds_referencia_error(): string
{
    return strtoupper(bin2hex(random_bytes(4)));
}

set_exception_handler(function (\Throwable $e): void {
    $ref = ds_referencia_error();
    error_log(sprintf('[DS %s] %s: %s en %s:%d',
        $ref, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()));
    ds_responder_error_neutro($ref);
});

/*
| Los errores fatales no pasan por el manejador de excepciones. Sin esto, un
| «Allowed memory size exhausted» seguiria imprimiendose entero.
*/
register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e === null || !in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $ref = ds_referencia_error();
    error_log(sprintf('[DS %s] fatal: %s en %s:%d',
        $ref, $e['message'], $e['file'], $e['line']));

    /* Si ya se envio algo, lo unico que se puede hacer es no añadir mas. */
    if (!headers_sent()) { ds_responder_error_neutro($ref); }
});
