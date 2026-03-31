<?php
require_once __DIR__ . '/../Models/ClassModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class ClassController
{
    private ClassModel $classModel;

    public function __construct(PDO $db)
    {
        $this->classModel = new ClassModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);
        if (empty($data['name']) || empty($data['master_class_id'])) {
            Response::json(["message" => "Name and master class required"], 422);
        }
        if (empty($data['name'])) {
            Response::json(["message" => "Class name required"], 422);
        }

        $classId = $this->classModel->create(
            (int)$user['school_id'],
            $data['name'],
            (int)$data['master_class_id']
        );

        Response::json([
            "message" => "Class created successfully",
            "class_id" => $classId
        ], 201);
    }

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $classes = $this->classModel->allBySchool(
            (int)$user['school_id']
        );

        Response::json($classes);
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        if ($user['role'] !== 'ADMIN' && $user['role'] !== 'SUPER_ADMIN') {
            Response::json(["message" => "Forbidden - Admin access required"], 403);
        }

        if (!$id) {
            Response::json(["message" => "Class ID required"], 422);
        }

        // You need to modify your ClassModel delete method to include school_id check
        $deleted = $this->classModel->delete((int)$id, (int)$user['school_id']);

        if (!$deleted) {
            Response::json(["message" => "Class not found or doesn't belong to your school"], 404);
        }

        Response::json(["message" => "Class deleted successfully"]);
    }
}