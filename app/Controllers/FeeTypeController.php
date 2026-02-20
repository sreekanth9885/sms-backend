<?php
require_once __DIR__ . '/../Models/FeeTypeModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class FeeTypeController
{
    private FeeTypeModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new FeeTypeModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['name'])) {
            Response::json(["message" => "Fee name required"], 422);
        }

        $id = $this->model->create(
            (int)$user['school_id'],
            $data['name'],
            $data['description'] ?? null,
            $data['is_optional'] ?? false
        );

        Response::json([
            "message" => "Fee type created",
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

        if (!$id) {
            Response::json(["message" => "ID required"], 422);
        }

        $deleted = $this->model->delete((int)$id);

        if (!$deleted) {
            Response::json(["message" => "Not found"], 404);
        }

        Response::json(["message" => "Deleted"]);
    }
}
