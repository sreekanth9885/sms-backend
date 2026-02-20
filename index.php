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
require_once __DIR__ . '/app/Controllers/StudentController.php';
require_once __DIR__ . '/app/Controllers/TeacherController.php';
require_once __DIR__ . '/app/Controllers/FeeTypeController.php';
require_once __DIR__ . '/app/Controllers/FeeStructureController.php';

$router = new Router();

$authController = new AuthController($pdo);
$userController = new UserController($pdo);
$statusController = new StatusController();
$schoolController = new SchoolController($pdo);
$classController = new ClassController($pdo);
$sectionController = new SectionController($pdo);
$studentController = new StudentController($pdo);
$teacherController = new TeacherController($pdo);
$feeTypeController = new FeeTypeController($pdo);
$feeStructureController = new FeeStructureController($pdo);

$router->post('/auth/login', [$authController, 'login']);
$router->get('/auth/me', [$authController, 'me']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->post('/auth/force-reset-password', [$authController, 'forceResetPassword']);
$router->post('/auth/refresh', [$authController, 'refresh']);

$router->get('/users', [$userController, 'index']);

$router->get('/status', [$statusController, 'index']);

$router->post('/schools', [$schoolController, 'create']);
$router->get('/schools', [$schoolController, 'index']);
$router->delete('/schools/{id}', [$schoolController, 'delete']);

$router->get('/classes', [$classController, 'index']);
$router->post('/classes', [$classController, 'create']);
$router->delete('/classes/{id}', [$classController, 'delete']);

$router->get('/sections', [$sectionController, 'index']);
$router->post('/sections', [$sectionController, 'create']);
$router->delete('/sections/{id}', [$sectionController, 'delete']);

$router->get('/students', [$studentController, 'index']);
$router->post('/students', [$studentController, 'register']);
$router->get('/students/{id}', [$studentController, 'show']);
$router->put('/students/{id}', [$studentController, 'update']);
$router->delete('/students/{id}', [$studentController, 'delete']);

$router->get('/teachers', [$teacherController, 'index']);
$router->post('/teachers', [$teacherController, 'create']);
$router->get('/teachers/{id}', [$teacherController, 'show']);
$router->put('/teachers/{id}', [$teacherController, 'update']);
$router->delete('/teachers/{id}', [$teacherController, 'delete']);

/* Fee Types */
$router->post('/fee-types', [$feeTypeController, 'create']);
$router->get('/fee-types', [$feeTypeController, 'index']);
$router->delete('/fee-types/{id}', [$feeTypeController, 'delete']);

/* Fee Structures */
$router->post('/fee-structures', [$feeStructureController, 'create']);
$router->get('/fee-structures', [$feeStructureController, 'index']);
$router->delete('/fee-structures/{id}', [$feeStructureController, 'delete']);


$router->dispatch();
