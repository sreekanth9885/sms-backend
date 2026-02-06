<?php

require_once __DIR__ . '/../Core/Response.php';

class JwtHelper
{
    private static function config()
    {
        return require __DIR__ . '/../../config/jwt.php';
    }

    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode($data)
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    /* =========================
       GENERATE ACCESS TOKEN
    ========================== */
    public static function generateAccessToken(array $payload)
    {
        $config = self::config();

        $header = self::base64UrlEncode(json_encode([
            "alg" => "HS256",
            "typ" => "JWT"
        ]));

        $payload['iss'] = $config['issuer'];
        $payload['exp'] = time() + $config['access_expiry'];

        $payloadEncoded = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac(
            "sha256",
            "$header.$payloadEncoded",
            $config['secret'],
            true
        );

        return "$header.$payloadEncoded." . self::base64UrlEncode($signature);
    }

    /* =========================
       GENERATE REFRESH TOKEN
    ========================== */
    public static function generateRefreshToken(array $payload)
    {
        $config = self::config();

        $payload['iss'] = $config['issuer'];
        $payload['exp'] = time() + $config['refresh_expiry'];
        $payload['type'] = 'refresh';

        return self::generateAccessToken($payload);
    }

    /* =========================
       VERIFY TOKEN
    ========================== */
    public static function verify($token)
    {
        $config = self::config();

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            Response::json(["message" => "Invalid token"], 401);
        }

        [$header, $payload, $signature] = $parts;

        $expected = self::base64UrlEncode(
            hash_hmac("sha256", "$header.$payload", $config['secret'], true)
        );

        if (!hash_equals($expected, $signature)) {
            Response::json(["message" => "Invalid token"], 401);
        }

        $payloadData = json_decode(self::base64UrlDecode($payload), true);

        if ($payloadData['iss'] !== $config['issuer']) {
            Response::json(["message" => "Invalid issuer"], 401);
        }

        if ($payloadData['exp'] < time()) {
            Response::json(["message" => "Token expired"], 401);
        }

        return $payloadData;
    }

    /* =========================
       EXTRACT USER FROM HEADER
    ========================== */
    public static function getUserFromToken()
    {
        $headers = getallheaders();

        $auth =
            $headers['Authorization']
            ?? $headers['authorization']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? null;

        if (!$auth) {
            Response::json(["message" => "Unauthorized"], 401);
        }

        if (!str_starts_with($auth, 'Bearer ')) {
            Response::json(["message" => "Invalid auth format"], 401);
        }

        $token = trim(str_replace('Bearer', '', $auth));

        return self::verify($token);
    }
}
