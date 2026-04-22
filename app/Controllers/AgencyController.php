<?php

require_once __DIR__ . '/../Models/AgencyModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class AgencyController
{
    private AgencyModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new AgencyModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        // Only Admin / Store Admin
        if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
            Response::json(["message" => "Forbidden"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // Validation
        if (empty($data['name']) || empty($data['phone']) || empty($data['contact_person'])) {
            Response::json(["message" => "Required fields missing"], 422);
        }

        try {
            $id = $this->model->create(
                (int)$user['school_id'],
                trim($data['name']),
                trim($data['phone']),
                $data['gst_number'] ?? null,
                trim($data['contact_person']),
                $data['address'] ?? null
            );

            Response::json([
                "message" => "Agency created successfully",
                "agency_id" => $id
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

        $data = json_decode(file_get_contents("php://input"), true);

        if (!$id) {
            Response::json(["message" => "Agency ID required"], 422);
        }

        try {
            $updated = $this->model->update(
                (int)$id,
                (int)$user['school_id'],
                trim($data['name']),
                trim($data['phone']),
                $data['gst_number'] ?? null,
                trim($data['contact_person']),
                $data['address'] ?? null
            );

            if (!$updated) {
                Response::json(["message" => "Agency not found"], 404);
            }

            Response::json(["message" => "Agency updated"]);

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
            Response::json(["message" => "Agency not found"], 404);
        }

        Response::json(["message" => "Agency deleted"]);
    }
}