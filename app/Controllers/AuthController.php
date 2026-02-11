<?php

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Core/Response.php';

class AuthController
{
    private User $user;
    private PDO $db;
    public function __construct(PDO $db)
    {
        $this->user = new User($db);
        $this->db = $db;
    }

    /* =========================
       LOGIN
    ========================== */
    public function login()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email']) || empty($data['password'])) {
            Response::json(["message" => "Email and password required"], 400);
        }

        $user = $this->user->findByEmail($data['email']);

        if (!$user || !password_verify($data['password'], $user['password'])) {
            Response::json(["message" => "Invalid credentials"], 401);
        }

        // ✅ Generate tokens via JwtHelper (config-driven)
        $accessToken = JwtHelper::generateAccessToken([
            "id" => $user['id'],
            "role" => $user['role'],
            "school_id" => $user['school_id'] // Add school context to token
        ]);

        $refreshToken = JwtHelper::generateRefreshToken([
            "id" => $user['id'],
            "role" => $user['role'],
            "school_id" => $user['school_id']
        ]);

        // ✅ Refresh token cookie (env-aware)
        setcookie(
            "refresh_token",
            $refreshToken,
            [
                "expires"  => time() + 604800, // 7 days
                "path"     => "/",
                "httponly" => true,
                "secure"   => isset($_SERVER['HTTPS']), // localhost-safe
                "samesite" => "Lax"
            ]
        );

        Response::json([
            "message" => "Login successful",
            "access_token" => $accessToken,
            "user" => [
                "id"    => $user['id'],
                "name"  => $user['name'],
                "email" => $user['email'],
                "role"  => $user['role'],
                "must_reset_password" => (bool)$user['must_reset_password'],
                // ✅ SCHOOL CONTEXT (NULL for SUPER_ADMIN)
                "school" => $user['school_id'] ? [
                    "id"   => $user['school_id'],
                    "name" => $user['school_name'],
                    "code" => $user['school_code']
                ] : null
            ]
        ]);
    }

    /* =========================
       LOGOUT
    ========================== */
    public function logout()
    {
        setcookie(
            "refresh_token",
            "",
            [
                "expires"  => time() - 3600,
                "path"     => "/",
                "httponly" => true,
                "secure"   => isset($_SERVER['HTTPS']),
                "samesite" => "Lax"
            ]
        );

        Response::json(["message" => "Logged out successfully"]);
    }
    public function forceResetPassword()
    {
        $user = JwtHelper::getUserFromToken();

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['password'])) {
            Response::json(["message" => "Password required"], 400);
        }

        $hashed = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("
        UPDATE users
        SET password = ?, must_reset_password = 0, password_changed_at = NOW()
        WHERE id = ?
    ");

        $stmt->execute([$hashed, $user['id']]);

        Response::json(["message" => "Password updated successfully"]);
    }
    public function refresh()
    {
        if (!isset($_COOKIE['refresh_token'])) {
            Response::json(["message" => "Refresh token missing"], 401);
        }

        $refreshToken = $_COOKIE['refresh_token'];

        $payload = JwtHelper::verify($refreshToken);

        if (($payload['type'] ?? '') !== 'refresh') {
            Response::json(["message" => "Invalid refresh token"], 401);
        }

        // Issue NEW access token
        $accessToken = JwtHelper::generateAccessToken([
            "id" => $payload['id'],
            "role" => $payload['role'],
            "school_id" => $payload['school_id']
        ]);

        Response::json([
            "access_token" => $accessToken
        ]);
    }
    public function me()
    {
        try {
            $user = JwtHelper::getUserFromToken();

            // Fetch fresh user data (including school context)
            $stmt = $this->db->prepare("
                SELECT u.id, u.name, u.email, u.role, u.must_reset_password,
                       s.id AS school_id, s.name AS school_name, s.code AS school_code
                FROM users u
                LEFT JOIN schools s ON u.school_id = s.id
                WHERE u.id = ?
            ");
            $stmt->execute([$user['id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userData) {
                Response::json(["message" => "User not found"], 404);
            }

            Response::json([
                "id"    => $userData['id'],
                "name"  => $userData['name'],
                "email" => $userData['email'],
                "role"  => $userData['role'],
                "must_reset_password" => (bool)$userData['must_reset_password'],
                "school" => $userData['school_id'] ? [
                    "id"   => $userData['school_id'],
                    "name" => $userData['school_name'],
                    "code" => $userData['school_code']
                ] : null
            ]);
        } catch (Exception $e) {
            Response::json(["message" => "Unauthorized"], 401);
        }
    }
}
