<?php

require_once __DIR__ . '/../Models/User.php';

class AuthController
{
    private User $user;

    public function __construct(PDO $db)
    {
        $this->user = new User($db);
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

        Response::json([
            "message" => "Login successful",
            "user" => [
                "id" => $user['id'],
                "role" => $user['role']
            ]
        ]);
    }
}
