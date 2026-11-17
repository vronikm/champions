<?php

/**
 * TokenHelper — Generación de tokens HMAC para enlaces de inscripción.
 *
 * Se usa en el proyecto principal para generar los enlaces que se comparten
 * por WhatsApp. La validación se hace en el proyecto senderodecampeones_form.
 *
 * Formato del token:  base64url(payload).base64url(signature)
 * Payload JSON:       { "sede_id": int, "exp": unix_timestamp }
 */

namespace app\models;

class TokenHelper
{
    /* Constantes que config/app.php debe definir para que el módulo funcione */
    const CONFIG_REQUERIDA = ['TOKEN_SECRET', 'TOKEN_EXPIRY', 'FORM_URL'];

    /**
     * Constantes de configuración que faltan.
     *
     * Existe porque al desplegar es fácil llevarse el código y dejar atrás el
     * config: antes eso reventaba con un "Undefined constant" y un 500 mudo.
     */
    public static function configFaltante(): array
    {
        return array_values(array_filter(
            self::CONFIG_REQUERIDA,
            fn($constante) => !defined($constante)
        ));
    }

    /**
     * Vigencia por defecto, resuelta en tiempo de ejecución y no como valor
     * por defecto del parámetro (que fallaría antes de poder diagnosticarlo).
     */
    private static function vigenciaPorDefecto(): int
    {
        return defined('TOKEN_EXPIRY') ? TOKEN_EXPIRY : 259200;
    }

    /**
     * Genera un token firmado con HMAC-SHA256.
     */
    public static function generar(int $sedeId, ?int $expiraEnSegundos = null): string
    {
        $expiraEnSegundos = $expiraEnSegundos ?? self::vigenciaPorDefecto();

        $payload = json_encode([
            'sede_id' => $sedeId,
            'exp'     => time() + $expiraEnSegundos
        ], JSON_UNESCAPED_UNICODE);

        $payloadB64   = self::base64urlEncode($payload);
        $signature    = hash_hmac('sha256', $payloadB64, TOKEN_SECRET);
        $signatureB64 = self::base64urlEncode($signature);

        return $payloadB64 . '.' . $signatureB64;
    }

    /**
     * Genera la URL completa del formulario con el token.
     */
    public static function generarURL(int $sedeId, ?int $expiraEnSegundos = null): string
    {
        $token = self::generar($sedeId, $expiraEnSegundos);
        return rtrim(FORM_URL, '/') . '/?t=' . $token;
    }

    /**
     * Genera el enlace de WhatsApp con mensaje predeterminado.
     */
    public static function generarEnlaceWhatsApp(int $sedeId, string $sedeNombre, ?int $expiraEnSegundos = null): string
    {
        $expiraEnSegundos = $expiraEnSegundos ?? self::vigenciaPorDefecto();

        $url = self::generarURL($sedeId, $expiraEnSegundos);
        $escuela = ds_organizacion()['escuela_nombre'];
        $mensaje = "Hola! Te comparto el enlace de inscripción para *{$escuela}* - Sede *{$sedeNombre}*.\n\n"
                 . "Completa el formulario en el siguiente enlace:\n{$url}\n\n"
                 . "Este enlace tiene una vigencia de " . round($expiraEnSegundos / 3600) . " horas.";

        return 'https://wa.me/?text=' . rawurlencode($mensaje);
    }

    /*----------  Enlace de autorización LOPDP (migración 074)  ----------*/

    /**
     * Token del enlace que pide las autorizaciones a un representante ya
     * registrado (ds_form/autorizacion.php).
     *
     * Se firma con una llave DERIVADA de TOKEN_SECRET, no con la de
     * inscripción: así ninguno de los dos enlaces sirve como el otro aunque
     * alguien junte los campos. El código lo guarda consentimiento_enlace;
     * sin esa fila, o ya usada, el enlace no vale.
     */
    public static function generarAutorizacion(int $repreId, string $codigo, int $expira): string
    {
        $payloadB64 = self::base64urlEncode(json_encode(['r' => $repreId, 'c' => $codigo, 'exp' => $expira]));
        $firma = hash_hmac('sha256', $payloadB64, self::llaveAutorizacion());

        return $payloadB64 . '.' . self::base64urlEncode($firma);
    }

    public static function urlAutorizacion(int $repreId, string $codigo, int $expira): string
    {
        return rtrim(FORM_URL, '/') . '/autorizacion.php?t=' . self::generarAutorizacion($repreId, $codigo, $expira);
    }

    /** La misma derivación que ds_form/app/models/TokenHelper::llaveAutorizacion(). */
    private static function llaveAutorizacion(): string
    {
        return hash('sha256', 'ds-autorizacion-lopdp|' . TOKEN_SECRET);
    }

    /*----------  Enlace de inscripción de alumnos adultos (migración 086)  ----------*/

    /**
     * Token del formulario de adultos (ds_form/adulto.php). Mismo contenido
     * que el de inscripción —sede y vencimiento— pero firmado con su propia
     * llave derivada: un enlace de menores no abre el de adultos ni al revés.
     */
    public static function generarAdulto(int $sedeId, ?int $expiraEnSegundos = null): string
    {
        $expiraEnSegundos = $expiraEnSegundos ?? self::vigenciaPorDefecto();

        $payloadB64 = self::base64urlEncode(json_encode([
            'sede_id' => $sedeId,
            'exp'     => time() + $expiraEnSegundos,
        ]));
        $firma = hash_hmac('sha256', $payloadB64, self::llaveAdulto());

        return $payloadB64 . '.' . self::base64urlEncode($firma);
    }

    public static function generarURLAdulto(int $sedeId, ?int $expiraEnSegundos = null): string
    {
        return rtrim(FORM_URL, '/') . '/adulto.php?t=' . self::generarAdulto($sedeId, $expiraEnSegundos);
    }

    /**
     * Mensaje para compartir el enlace de adultos. Lo usa el modal «Compartir
     * por WhatsApp», donde se puede editar antes de enviarlo.
     *
     * El texto es de cada escuela: si existe config/mensaje_inscripcion_adulto.txt
     * se usa ése; si no, el genérico de abajo. Marcadores:
     *   {ENLACE}  el enlace de inscripción
     *   {SEDE}    la sede en tipo título (Forest Ejecutivo)
     *   {SEDE_MAYUS} la sede en mayúsculas (FOREST EJECUTIVO)
     *   {ESCUELA} el nombre de la organización (Core › Organización)
     *   {VENCE}   fecha y hora en que vence el enlace
     */
    public static function mensajeWhatsAppAdulto(string $url, string $sedeNombre, int $expiraEnSegundos): string
    {
        $archivo   = dirname(__DIR__, 2) . '/config/mensaje_inscripcion_adulto.txt';
        $plantilla = is_file($archivo) ? trim(str_replace("\r", '', (string)file_get_contents($archivo))) : '';

        if ($plantilla === '') {
            $plantilla = "¡Hola! 👋 Te comparto el enlace de inscripción para *alumnos adultos* de *{ESCUELA}* - Sede *{SEDE}*.\n\n"
                       . "📝 Completa tus datos aquí (tu recibo y tu factura se emitirán a tu nombre):\n{ENLACE}\n\n"
                       . "⏳ El enlace vence el {VENCE}.";
        }

        $sede = html_entity_decode($sedeNombre, ENT_QUOTES, 'UTF-8');

        return strtr($plantilla, [
            '{ENLACE}'     => $url,
            '{SEDE_MAYUS}' => mb_strtoupper($sede, 'UTF-8'),
            '{SEDE}'       => mb_convert_case(mb_strtolower($sede, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
            '{ESCUELA}'    => str_contains($plantilla, '{ESCUELA}') ? (string)ds_organizacion()['escuela_nombre'] : '',
            '{VENCE}'      => date('d/m/Y \a \l\a\s H:i', time() + $expiraEnSegundos),
        ]);
    }

    /** Enlace de WhatsApp con el mensaje para un alumno adulto. */
    public static function generarEnlaceWhatsAppAdulto(string $url, string $sedeNombre, int $expiraEnSegundos): string
    {
        return 'https://wa.me/?text=' . rawurlencode(self::mensajeWhatsAppAdulto($url, $sedeNombre, $expiraEnSegundos));
    }

    /** La misma derivación que ds_form/app/models/TokenHelper::llaveAdulto(). */
    private static function llaveAdulto(): string
    {
        return hash('sha256', 'ds-inscripcion-adulto|' . TOKEN_SECRET);
    }

    private static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
