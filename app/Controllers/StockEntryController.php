<?php
require_once __DIR__ . '/../Models/StockEntryModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
class StockEntryController
{
    private $model;

    public function __construct(PDO $db)
    {
        $this->model = new StockEntryModel($db);
    }
    public function index()
{
    $user = JwtHelper::getUserFromToken();

    if (!isset($user['school_id'])) {
        Response::json(["message" => "School context missing"], 403);
    }

    $data = $this->model->allBySchool($user['school_id']);

    Response::json(["data" => $data]);
}
    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        if (!in_array($user['role'], ['ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['agency_id'])  || empty($data['items'])) {
            Response::json(["message" => "Required fields missing"], 422);
        }

        try {
            $id = $this->model->create(
                $user['school_id'],
                $data,
                $user['id']
            );

            Response::json([
                "message" => "Stock entry created",
                "id" => $id
            ], 201);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 409);
        }
    }
    public function delete($id)
{
    $user = JwtHelper::getUserFromToken();

    if (!in_array($user['role'], ['ADMIN', 'STORE_ADMIN'])) {
        Response::json(["message" => "Forbidden"], 403);
    }

    $deleted = $this->model->delete($id, $user['school_id']);

    if (!$deleted) {
        Response::json(["message" => "Not found"], 404);
    }

    Response::json(["message" => "Deleted successfully"]);
}
public function update($id)
{
    $user = JwtHelper::getUserFromToken();

    if (!in_array($user['role'], ['ADMIN', 'STORE_ADMIN'])) {
        Response::json(["message" => "Forbidden"], 403);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    try {
        $this->model->update($id, $user['school_id'], $data, $user['id']);

        Response::json(["message" => "Updated successfully"]);

    } catch (Exception $e) {
        Response::json(["message" => $e->getMessage()], 409);
    }
}
}