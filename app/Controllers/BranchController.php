<?php

require_once __DIR__ . '/../Models/BranchModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class BranchController
{
    private BranchModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new BranchModel($db);
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

        if (empty($data['name'])) {
            Response::json(["message" => "Branch name required"], 422);
        }

        try {
            $id = $this->model->create(
                (int)$user['school_id'],
                trim($data['name'])
            );

            Response::json([
                "message" => "Branch created successfully",
                "branch_id" => $id
            ], 201);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 409);
        }
    }

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = $this->model->allBySchool((int)$user['school_id']);

        Response::json(["data" => $data]);
    }

    public function update($id)
    {
        $user = JwtHelper::getUserFromToken();

        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        if (!$id) {
            Response::json(["message" => "Branch ID required"], 422);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['name'])) {
            Response::json(["message" => "Branch name required"], 422);
        }

        try {
            $updated = $this->model->update(
                (int)$id,
                (int)$user['school_id'],
                trim($data['name'])
            );

            if (!$updated) {
                Response::json(["message" => "Branch not found"], 404);
            }

            Response::json(["message" => "Branch updated"]);

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
            Response::json(["message" => "Branch not found"], 404);
        }

        Response::json(["message" => "Branch deleted"]);
    }
}