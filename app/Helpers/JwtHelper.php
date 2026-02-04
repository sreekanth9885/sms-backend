<?php

class JwtHelper
{
    public static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function generate(array $payload, string $secret, int $expiry)
    {
        $header = self::base64UrlEncode(json_encode([
            "alg" => "HS256",
            "typ" => "JWT"
        ]));

        $payload["exp"] = time() + $expiry;
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(
            "sha256",
            "$header.$payloadEncoded",
            $secret,
            true
        );

        return "$header.$payloadEncoded." . self::base64UrlEncode($signature);
    }
}
