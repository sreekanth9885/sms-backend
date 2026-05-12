<?php
require_once __DIR__ . '/../Models/SubCategoryModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
class SubCategoryController
{
    private SubCategoryModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new SubCategoryModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['name']) || empty($data['category_id'])) {
            Response::json(["message" => "Required fields missing"], 422);
        }

        try {
            $id = $this->model->create(
                (int)$user['school_id'],
                (int)$data['category_id'],
                trim($data['name'])
            );

            Response::json([
                "message" => "Subcategory created",
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

        if (empty($data['name']) || empty($data['category_id'])) {
            Response::json(["message" => "Required fields missing"], 422);
        }

        try {
            $updated = $this->model->update(
                (int)$id,
                (int)$user['school_id'],
                (int)$data['category_id'],
                trim($data['name'])
            );

            if (!$updated) {
                Response::json(["message" => "Not found"], 404);
            }

            Response::json(["message" => "Subcategory updated"]);

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

        Response::json(["message" => "Subcategory deleted"]);
    }
}