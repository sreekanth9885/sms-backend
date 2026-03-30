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
require_once __DIR__ . '/app/Controllers/StudentFeeController.php';
require_once __DIR__ . '/app/Controllers/AttendanceController.php';
require_once __DIR__ . '/app/Controllers/StudentAuthController.php';
require_once __DIR__ . '/app/Controllers/NotificationController.php';
require_once __DIR__ . '/app/Controllers/SubjectController.php';
require_once __DIR__ . '/app/Controllers/SubjectDefaultsController.php';
require_once __DIR__ . '/app/Controllers/ExamController.php';

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
$studentFeeController = new StudentFeeController($pdo);
$attendanceController = new AttendanceController($pdo);
$studentAuthController = new StudentAuthController($pdo);
$notificationController = new NotificationController($pdo);
$subjectController = new SubjectController($pdo);
$subjectDefaultsController = new SubjectDefaultsController($pdo);
$examController = new ExamController($pdo);

$router->post('/auth/login', [$authController, 'login']);
$router->get('/auth/me', [$authController, 'me']);
$router->post('/auth/logout', [$authController, 'logout']);
$router->post('/auth/force-reset-password', [$authController, 'forceResetPassword']);
$router->post('/auth/refresh', [$authController, 'refresh']);
$router->post('/auth/forgot-password', [$authController, 'forgotPassword']);
$router->post('/auth/reset-password', [$authController, 'resetPassword']);
$router->get('/auth/verify-reset-token', [$authController, 'verifyResetToken']);
$router->post('/auth/forgot-username', [$authController, 'forgotUsername']);
$router->get('/users', [$userController, 'index']);

$router->get('/status', [$statusController, 'index']);

$router->post('/schools', [$schoolController, 'create']);
$router->get('/schools', [$schoolController, 'index']);
$router->get('/schools/{id}', [$schoolController, 'get']);
$router->post('/schools/{id}', [$schoolController, 'update']);
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
$router->post('/students/{id}', [$studentController, 'update']);
$router->delete('/students/{id}', [$studentController, 'delete']);

$router->get('/teachers', [$teacherController, 'index']);
$router->post('/teachers', [$teacherController, 'create']);
$router->get('/teachers/{id}', [$teacherController, 'show']);
$router->post('/teachers/{id}', [$teacherController, 'update']);
$router->delete('/teachers/{id}', [$teacherController, 'delete']);

/* Fee Types */
$router->post('/fee-types', [$feeTypeController, 'create']);
$router->get('/fee-types', [$feeTypeController, 'index']);
$router->delete('/fee-types/{id}', [$feeTypeController, 'delete']);

/* Fee Structures */
$router->post('/fee-structures', [$feeStructureController, 'create']);
$router->get('/fee-structures', [$feeStructureController, 'index']);
$router->delete('/fee-structures/{id}', [$feeStructureController, 'delete']);

// Student Fee routes
$router->post('/student-fees/bulk', [$studentFeeController, 'createBulk']);
$router->get('/student-fees', [$studentFeeController, 'index']);
$router->get('/student-fees/{id}', [$studentFeeController, 'show']);
$router->put('/student-fees/{id}', [$studentFeeController, 'update']);
$router->patch('/student-fees/{id}/payment', [$studentFeeController, 'updatePayment']);
$router->delete('/student-fees/{id}', [$studentFeeController, 'delete']);
$router->get('/student-fees/summary', [$studentFeeController, 'summary']);
$router->get('/student-fees/overdue', [$studentFeeController, 'overdue']);
$router->get('/students/{studentId}/fees', [$studentFeeController, 'getByStudent']);
$router->post('/student-fees/{id}/collect', [$studentFeeController, 'collectPayment']);
$router->get('/student-fees/{id}/payments', [$studentFeeController, 'getPaymentHistory']);

// Attendance routes
$router->post('/attendance/bulk', [$attendanceController, 'saveBulk']);
$router->get('/attendance', [$attendanceController, 'index']);
$router->get('/attendance/summary', [$attendanceController, 'summary']);
$router->get('/attendance/student/{studentId}', [$attendanceController, 'getByStudent']);
$router->get('/attendance/today/{studentId}', [$attendanceController, 'getTodayStatus']);
$router->get('/attendance/percentage/{studentId}', [$attendanceController, 'getPercentage']);
$router->delete('/attendance', [$attendanceController, 'delete']);

$router->post('/auth/student-login', [$studentAuthController, 'studentLogin']);
$router->post('/auth/student-logout', [$studentAuthController, 'studentLogout']);
$router->post('/register-device', [$studentAuthController, 'registerDeviceToken']);
// $router->get('/student/profile', [$studentAuthController, 'getProfile']);
$router->post('/notifications/send', [$notificationController, 'sendNotification']);
$router->get('/notifications/history', [$notificationController, 'getNotificationHistory']);
$router->post('/auth/select-student', [$studentAuthController, 'selectStudent']);
// Subject routes
$router->post('/subjects', [$subjectController, 'create']);
$router->put('/subjects/(\d+)', [$subjectController, 'update']);
$router->delete('/subjects/(\d+)', [$subjectController, 'delete']);
$router->get('/subjects/(\d+)', [$subjectController, 'get']);
$router->get('/subjects/class/(\d+)', [$subjectController, 'getByClass']);
$router->get('/subjects', [$subjectController, 'getAll']);
$router->get('/subjects/summary', [$subjectController, 'getSummary']);
$router->post('/subjects/bulk', [$subjectController, 'bulkCreate']);
$router->post('/subjects/reorder', [$subjectController, 'reorderPriorities']);

$router->get('/subject-defaults', [$subjectDefaultsController, 'getAll']);
$router->get('/subject-defaults/{id}', [$subjectDefaultsController, 'get']);

$router->get('/exams', [$examController, 'getAll']);
$router->get('/exams/{id}', [$examController, 'get']);

$router->dispatch();
