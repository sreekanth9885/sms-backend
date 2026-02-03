<?php
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Core/Response.php';
require_once __DIR__ . '/app/Core/Router.php';
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/UserController.php';
require_once __DIR__ . '/app/Controllers/StatusController.php';

$router = new Router();

$authController = new AuthController($pdo);
$userController = new UserController($pdo);
$statusController = new StatusController();


$router->post('/auth/login', [$authController, 'login']);
$router->get('/users', [$userController, 'index']);
$router->get('/status', [$statusController, 'index']);


$router->dispatch();
