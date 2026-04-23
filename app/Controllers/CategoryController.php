<?php
require_once __DIR__ . '/../Models/CategoryModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
class CategoryController
{
    private CategoryModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new CategoryModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['name'])) {
            Response::json(["message" => "Category name required"], 422);
        }

        try {
            $id = $this->model->create((int)$user['school_id'], trim($data['name']));

            Response::json([
                "message" => "Category created",
                "id" => $id
            ], 201);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 409);
        }
    }

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        $data = $this->model->all((int)$user['school_id']);

        Response::json(["data" => $data]);
    }

    public function update($id)
    {
        $user = JwtHelper::getUserFromToken();
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['name'])) {
            Response::json(["message" => "Name required"], 422);
        }

        try {
            $updated = $this->model->update(
                (int)$id,
                (int)$user['school_id'],
                trim($data['name'])
            );

            if (!$updated) {
                Response::json(["message" => "Not found"], 404);
            }

            Response::json(["message" => "Category updated"]);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 409);
        }
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        $deleted = $this->model->delete((int)$id, (int)$user['school_id']);

        if (!$deleted) {
            Response::json(["message" => "Not found"], 404);
        }

        Response::json(["message" => "Category deleted"]);
    }
}