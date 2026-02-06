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
            "uid" => $user['id'],
            "role" => $user['role']
        ]);

        $refreshToken = JwtHelper::generateRefreshToken([
            "uid" => $user['id']
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
                "must_reset_password" => (bool)$user['must_reset_password']
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

    $stmt->execute([$hashed, $user['uid']]);

    Response::json(["message" => "Password updated successfully"]);
}

}
