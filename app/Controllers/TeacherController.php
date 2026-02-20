<?php

require_once __DIR__ . '/../Models/TeacherModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class TeacherController
{
    private TeacherModel $teacherModel;

    public function __construct(PDO $db)
    {
        $this->teacherModel = new TeacherModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['name']) || empty($data['phone']) || empty($data['subject'])) {
            Response::json(["message" => "Required fields missing"], 422);
        }

        $teacherId = $this->teacherModel->create(
            (int)$user['school_id'],
            $data
        );

        Response::json([
            "message" => "Teacher created",
            "teacher_id" => $teacherId
        ], 201);
    }

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $teachers = $this->teacherModel->allBySchool(
            (int)$user['school_id']
        );

        Response::json($teachers);
    }

    public function show($id)
    {
        $teacher = $this->teacherModel->find((int)$id);

        if (!$teacher) {
            Response::json(["message" => "Teacher not found"], 404);
        }

        Response::json($teacher);
    }

    public function update($id)
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $updated = $this->teacherModel->update((int)$id, $data);

        if (!$updated) {
            Response::json(["message" => "Teacher not found or no changes"], 404);
        }

        Response::json(["message" => "Teacher updated"]);
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'ADMIN') {
            Response::json(["message" => "Forbidden"], 403);
        }

        $deleted = $this->teacherModel->delete((int)$id);

        if (!$deleted) {
            Response::json(["message" => "Teacher not found"], 404);
        }

        Response::json(["message" => "Teacher deleted"]);
    }
}
