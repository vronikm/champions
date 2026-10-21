<?php

/**
 * TokenHelper — Validación de tokens HMAC de los enlaces de inscripción.
 *
 * Los tokens los emite el sistema principal Aquí solo se
 * verifican: firma HMAC-SHA256 y fecha de expiración.
 *
 * Formato del token:  base64url(payload).base64url(signature)
 * Payload JSON:       { "sede_id": int, "exp": unix_timestamp }
 */

namespace app\models;

class TokenHelper
{
    /**
     * Valida un token y devuelve el payload decodificado, o null si es
     * inválido o está expirado.
     */
    public static function validar(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadB64, $signatureB64] = $parts;

        // Verificar firma HMAC (hash_equals evita ataques de temporización)
        $expectedSignature = hash_hmac('sha256', $payloadB64, TOKEN_SECRET);
        $receivedSignature = self::base64urlDecode($signatureB64);

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            return null;
        }

        // Decodificar payload
        $payload = json_decode(self::base64urlDecode($payloadB64), true);
        if (!$payload || !isset($payload['sede_id'], $payload['exp'])) {
            return null;
        }

        // Verificar expiración
        if (time() > $payload['exp']) {
            return null;
        }

        return $payload;
    }

    /**
     * Verifica si un token con firma válida ya expiró, para poder mostrar
     * el mensaje "enlace expirado" en lugar de "enlace inválido".
     *
     * Un token con firma inválida nunca se reporta como expirado: eso
     * revelaría que el payload es legible sin conocer la clave.
     */
    public static function estaExpirado(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }

        [$payloadB64, $signatureB64] = $parts;

        $expectedSignature = hash_hmac('sha256', $payloadB64, TOKEN_SECRET);
        $receivedSignature = self::base64urlDecode($signatureB64);

        if (!hash_equals($expectedSignature, $receivedSignature)) {
            return false;
        }

        $payload = json_decode(self::base64urlDecode($payloadB64), true);
        if (!$payload || !isset($payload['exp'])) {
            return false;
        }

        return time() > $payload['exp'];
    }

    private static function base64urlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
