<?php

require_once __DIR__ . '/../Models/FirmModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class FirmController
{
    private FirmModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new FirmModel($db);
    }

    /**
     * Create a new firm
     * POST /api/firms
     */
    public function create()
    {
        try {
            $user = JwtHelper::getUserFromToken();

            if (!isset($user['school_id'])) {
                Response::json(["message" => "School context missing"], 403);
            }

            if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
                Response::json(["message" => "Forbidden"], 403);
            }

            $data = json_decode(file_get_contents("php://input"), true);

            // Validation
            if (empty($data['name'])) {
                Response::json(["message" => "Firm name is required"], 422);
            }

            if (empty($data['contact_person'])) {
                Response::json(["message" => "Contact person name is required"], 422);
            }

            if (empty($data['contact_phone'])) {
                Response::json(["message" => "Contact phone number is required"], 422);
            }

            // Check if firm name already exists
            if ($this->model->isNameExists((int)$user['school_id'], $data['name'])) {
                Response::json(["message" => "Firm name already exists"], 409);
            }

            $firmId = $this->model->create((int)$user['school_id'], $data);
            
            $firm = $this->model->getById($firmId, (int)$user['school_id']);

            Response::json([
                "message" => "Firm created successfully",
                "data" => $firm
            ], 201);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 500);
        }
    }

    /**
     * Get all firms
     * GET /api/firms
     */
    public function getAll()
    {
        try {
            $user = JwtHelper::getUserFromToken();

            if (!isset($user['school_id'])) {
                Response::json(["message" => "School context missing"], 403);
            }

            $onlyActive = !isset($_GET['include_inactive']) || $_GET['include_inactive'] !== 'true';
            
            $firms = $this->model->getAll((int)$user['school_id'], $onlyActive);

            Response::json([
                "data" => $firms,
                "total" => count($firms)
            ]);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 500);
        }
    }

    /**
     * Get firm by ID
     * GET /api/firms/{id}
     */
    public function getById($id)
    {
        try {
            $user = JwtHelper::getUserFromToken();

            if (!isset($user['school_id'])) {
                Response::json(["message" => "School context missing"], 403);
            }

            $firm = $this->model->getById((int)$id, (int)$user['school_id']);

            if (!$firm) {
                Response::json(["message" => "Firm not found"], 404);
            }

            Response::json(["data" => $firm]);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 500);
        }
    }

    /**
     * Update firm
     * PUT /api/firms/{id}
     */
    public function update($id)
    {
        try {
            $user = JwtHelper::getUserFromToken();

            if (!isset($user['school_id'])) {
                Response::json(["message" => "School context missing"], 403);
            }

            if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
                Response::json(["message" => "Forbidden"], 403);
            }

            $data = json_decode(file_get_contents("php://input"), true);

            // Check if firm exists
            $existingFirm = $this->model->getById((int)$id, (int)$user['school_id']);
            if (!$existingFirm) {
                Response::json(["message" => "Firm not found"], 404);
            }

            // Check if name already exists (excluding current firm)
            if (isset($data['name']) && $this->model->isNameExists((int)$user['school_id'], $data['name'], (int)$id)) {
                Response::json(["message" => "Firm name already exists"], 409);
            }

            $updated = $this->model->update((int)$id, (int)$user['school_id'], $data);

            if ($updated) {
                $firm = $this->model->getById((int)$id, (int)$user['school_id']);
                Response::json([
                    "message" => "Firm updated successfully",
                    "data" => $firm
                ]);
            } else {
                Response::json(["message" => "No changes made"], 200);
            }

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 500);
        }
    }

    /**
     * Delete firm (soft delete)
     * DELETE /api/firms/{id}
     */
    public function delete($id)
    {
        try {
            $user = JwtHelper::getUserFromToken();

            if (!isset($user['school_id'])) {
                Response::json(["message" => "School context missing"], 403);
            }

            if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN', 'STORE_ADMIN'])) {
                Response::json(["message" => "Forbidden"], 403);
            }

            // Check if firm exists
            $firm = $this->model->getById((int)$id, (int)$user['school_id']);
            if (!$firm) {
                Response::json(["message" => "Firm not found"], 404);
            }

            $deleted = $this->model->delete((int)$id, (int)$user['school_id']);

            if ($deleted) {
                Response::json(["message" => "Firm deleted successfully"]);
            } else {
                Response::json(["message" => "Failed to delete firm"], 500);
            }

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 500);
        }
    }
}