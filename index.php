<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Response.php';
require_once __DIR__ . '/app/Core/Router.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/UserController.php';
require_once __DIR__ . '/app/Controllers/StatusController.php';
require_once __DIR__ . '/config/cors.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET,POST, OPTIONS");

$router = new Router();

$authController = new AuthController($pdo);
$userController = new UserController($pdo);
$statusController = new StatusController();


$router->post('/auth/login', [$authController, 'login']);
$router->get('/users', [$userController, 'index']);
$router->get('/status', [$statusController, 'index']);


$router->dispatch();
