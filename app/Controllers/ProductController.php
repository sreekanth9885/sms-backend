<?php
require_once __DIR__ . '/../Models/ProductModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class ProductController
{
    private ProductModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new ProductModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (
            empty($data['name']) ||
            empty($data['category_id']) ||
            empty($data['sub_category_id']) ||
            empty($data['unit'])
        ) {
            Response::json(["message" => "Required fields missing"], 422);
        }

        try {
            $id = $this->model->create(
                (int)$user['school_id'],
                (int)$data['category_id'],
                (int)$data['sub_category_id'],
                trim($data['name']),
                (int)($data['quantity'] ?? 0),
                strtoupper($data['unit'])
            );

            Response::json([
                "message" => "Product created",
                "product_id" => $id
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

        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$id) {
            Response::json(["message" => "Product ID required"], 422);
        }

        try {
            $updated = $this->model->update(
                (int)$id,
                (int)$user['school_id'],
                (int)$data['category_id'],
                (int)$data['sub_category_id'],
                trim($data['name']),
                (int)$data['quantity'],
                strtoupper($data['unit'])
            );

            if (!$updated) {
                Response::json(["message" => "Not found"], 404);
            }

            Response::json(["message" => "Product updated"]);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 409);
        }
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        $deleted = $this->model->delete((int)$id, (int)$user['school_id']);

        if (!$deleted) {
            Response::json(["message" => "Not found"], 404);
        }

        Response::json(["message" => "Product deleted"]);
    }
}