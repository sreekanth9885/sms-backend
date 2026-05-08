<?php

require_once __DIR__ . '/../Models/StoreSubjectModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class StoreSubjectController
{
    private StoreSubjectModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new StoreSubjectModel($db);
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
            Response::json(["message" => "Subject name required"], 422);
        }
        if (empty($data['agency_id'])) {
            Response::json(["message" => "Agency ID required"], 422);
        }
        try {
            $id = $this->model->create(
                (int)$user['school_id'],
                trim($data['name']),
                (int)$data['agency_id']
            );

            Response::json([
                "message" => "Subject created successfully",
                "subject_id" => $id
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

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }
        $data = json_decode(file_get_contents("php://input"), true);
        if (!$id) {
            Response::json(["message" => "Subject ID required"], 422);
        }
        if (empty($data['agency_id'])) {
            Response::json(["message" => "Agency ID required"], 422);
        }


        if (empty($data['name'])) {
            Response::json(["message" => "Subject name required"], 422);
        }

        try {
            $updated = $this->model->update(
                (int)$id,
                (int)$user['school_id'],
                trim($data['name']),
                (int)$data['agency_id']
            );

            if (!$updated) {
                Response::json(["message" => "Subject not found"], 404);
            }

            Response::json(["message" => "Subject updated"]);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 409);
        }
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        $deleted = $this->model->delete((int)$id, (int)$user['school_id']);

        if (!$deleted) {
            Response::json(["message" => "Subject not found"], 404);
        }

        Response::json(["message" => "Subject deleted"]);
    }
}