<?php

require_once __DIR__ . '/../Models/User.php';

class UserController
{
    private User $user;

    public function __construct(PDO $db)
    {
        $this->user = new User($db);
    }

    public function index()
    {
        $users = $this->user->all();

        Response::json([
            "status" => "success",
            "data" => $users
        ]);
    }
}
