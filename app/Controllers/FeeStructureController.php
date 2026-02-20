<?php
require_once __DIR__ . '/../Models/FeeStructureModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class FeeStructureController
{
    private FeeStructureModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new FeeStructureModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();
    
        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['fee_type_id']) || empty($data['amount']) || empty($data['academic_year'])) {
            Response::json(["message" => "Missing required fields"], 422);
        }

        $id = $this->model->create(
            (int)$user['school_id'],
            $data['class_id'] ?? null,
            (int)$data['fee_type_id'],
            (float)$data['amount'],
            $data['academic_year']
        );

        Response::json([
            "message" => "Fee structure created",
            "id" => $id
        ], 201);
    }

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = $this->model->allBySchool((int)$user['school_id']);
        Response::json($data);
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'ADMIN') {
            Response::json(["message" => "Forbidden"], 403);
        }

        $deleted = $this->model->delete((int)$id);

        if (!$deleted) {
            Response::json(["message" => "Not found"], 404);
        }

        Response::json(["message" => "Deleted"]);
    }
}
