<?php
/*
|--------------------------------------------------------------------------
| DigiCheck — el código del QR de cada carnet
|--------------------------------------------------------------------------
| El QR lleva «DSC1:<alumno>:<firma>». La firma es un HMAC del número de
| alumno con TOKEN_SECRET (el secreto de cada instalación, secrets.php):
|
|   · no se puede fabricar un carnet válido escribiendo otro número: sin
|     el secreto, la firma no cuadra;
|   · un carnet de una escuela no vale en otra (otro secreto);
|   · es corto (unos 22 caracteres): un QR de pocos módulos, que lee bien
|     hasta una cámara modesta a un metro.
|
| El dispositivo de la cancha NO verifica la firma (no tiene el secreto):
| descarga la lista de alumnos CON su código y busca coincidencia exacta.
| Lo que no está en la lista no se reconoce.
|
| Si algún día hay que anular los carnets impresos (uno perdido, o se
| filtra el secreto), basta con cambiar la VERSIÓN: los códigos nuevos
| dejan de coincidir con los viejos.
*/

if (!function_exists('digicheck_codigo')) {

    /** Prefijo y versión del código. Cambiarla anula los carnets anteriores. */
    define('DIGICHECK_VERSION', 'DSC1');

    /** El código del QR del carnet de un alumno. */
    function digicheck_codigo(int $alumno): string
    {
        $firma = substr(hash_hmac('sha256', 'digicheck|' . DIGICHECK_VERSION . '|' . $alumno, TOKEN_SECRET), 0, 12);
        return DIGICHECK_VERSION . ':' . $alumno . ':' . $firma;
    }

    /** El alumno de un código, si la firma es válida; si no, null. */
    function digicheck_alumno(string $codigo): ?int
    {
        if (!preg_match('/^' . DIGICHECK_VERSION . ':(\d{1,10}):([0-9a-f]{12})$/', trim($codigo), $m)) {
            return null;
        }
        return hash_equals(digicheck_codigo((int)$m[1]), trim($codigo)) ? (int)$m[1] : null;
    }
}
