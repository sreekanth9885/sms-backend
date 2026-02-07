<?php
// CORS MUST BE FIRST
require_once __DIR__ . '/config/cors.php';

// Config & DB
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';

// Core
require_once __DIR__ . '/app/Core/Response.php';
require_once __DIR__ . '/app/Core/Router.php';

// Controllers
require_once __DIR__ . '/app/Controllers/AuthController.php';
require_once __DIR__ . '/app/Controllers/UserController.php';
require_once __DIR__ . '/app/Controllers/StatusController.php';
require_once __DIR__ . '/app/Controllers/SchoolController.php';
require_once __DIR__ . '/app/Controllers/ClassController.php';
require_once __DIR__ . '/app/Controllers/SectionController.php';

$router = new Router();

$authController = new AuthController($pdo);
$userController = new UserController($pdo);
$statusController = new StatusController();
$schoolController = new SchoolController($pdo);
$classController = new ClassController($pdo);
$sectionController = new SectionController($pdo);

$router->post('/auth/login', [$authController, 'login']);
$router->get('/users', [$userController, 'index']);
$router->get('/status', [$statusController, 'index']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->post('/auth/force-reset-password', [$authController, 'forceResetPassword']);
$router->post('/schools', [$schoolController, 'create']);
$router->post('/classes', [$classController, 'create']);
$router->get('/classes', [$classController, 'index']);
$router->post('/sections', [$sectionController, 'create']);

$router->dispatch();
