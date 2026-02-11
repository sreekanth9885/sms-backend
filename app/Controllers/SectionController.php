<?php
require_once __DIR__ . '/../Models/SectionModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
class SectionController
{
    private SectionModel $section;

    public function __construct(PDO $db)
    {
        $this->section = new SectionModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'ADMIN') {
            Response::json(["message" => "Forbidden"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['class_id']) || empty($data['name'])) {
            Response::json(["message" => "Invalid data"], 422);
        }

        $id = $this->section->create($data['class_id'], $data['name']);

        Response::json([
            "message" => "Section created",
            "section_id" => $id
        ], 201);
    }
    /* ---------- Delete ---------- */

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'ADMIN') {
            Response::json(["message" => "Forbidden"], 403);
        }

        if (!$id) {
            Response::json(["message" => "Section ID required"], 422);
        }

        $deleted = $this->section->delete((int)$id);

        if (!$deleted) {
            Response::json(["message" => "Section not found"], 404);
        }

        Response::json(["message" => "Section deleted"]);
    }
    public function index()
{
    $user = JwtHelper::getUserFromToken();

    if (!isset($user['school_id'])) {
        Response::json(["message" => "School context missing"], 403);
    }

    if (!isset($_GET['class_id'])) {
        Response::json(["message" => "class_id required"], 422);
    }

    $classId = (int)$_GET['class_id'];

    $sections = $this->section->allByClass($classId);

    Response::json($sections);
}

}
