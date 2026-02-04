<?php

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
class AuthController
{
    private User $user;
    private array $jwtConfig;

    public function __construct(PDO $db)
    {
        $this->user = new User($db);
        $this->jwtConfig = require __DIR__ . '/../../config/jwt.php';
    }

    public function login()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email']) || empty($data['password'])) {
            Response::json(["error" => "Email and password required"], 400);
        }

        $user = $this->user->findByEmail($data['email']);

        if (!$user || !password_verify($data['password'], $user['password'])) {
            Response::json(["error" => "Invalid credentials"], 401);
        }
        $accessToken=JwtHelper::generate(
            [
                "uid"=>$user['id'],
                "role"=>$user['role'],
                "iss"=>$this->jwtConfig['issuer']
            ],
            $this->jwtConfig['secret'],
            $this->jwtConfig['access_expiry']
        );
        $refreshToken=bin2hex(random_bytes(40));
        setcookie(
            "refresh_token",
            $refreshToken,
            [
                "expires"=>time()+$this->jwtConfig['refresh_expiry'],
                "path"=>"/",
                "httponly"=>true,
                "samesite"=>"Strict",
                "secure"=>true
            ]
        );
        Response::json([
            "message" => "Login successful",
            "access_token" =>$accessToken,
            "user" => [
                "id" => $user['id'],
                "role" => $user['role']
            ]
        ]);
    }
    public function logout()
    {
        setcookie("refresh_token", "", time() - 3600, "/");
        Response::json(["message" => "Logged out"]);
    }
}
