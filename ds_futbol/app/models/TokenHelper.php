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

    private static function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
