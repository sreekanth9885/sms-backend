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

        if (empty($data['name'])) {
            Response::json(["message" => "Class name required"], 422);
        }

        $classId = $this->classModel->create(
            (int)$user['school_id'],
            $data['name']
        );

        Response::json([
            "message" => "Class created",
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

        if ($user['role'] !== 'ADMIN') {
            Response::json(["message" => "Forbidden"], 403);
        }
        if (!$id) {
            Response::json(["message" => "Class ID required"], 422);
        }
        $deleted = $this->classModel->delete((int)$id);
        if (!$deleted) {
            Response::json(["message" => "Class not found"], 404);
        }

        Response::json(["message" => "Class deleted"]);
    }
}
