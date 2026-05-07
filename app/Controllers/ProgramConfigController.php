<?php

require_once __DIR__ . '/../Models/ProgramConfigModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class ProgramConfigController
{
    private ProgramConfigModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new ProgramConfigModel($db);
    }

    /**
     * Save (Replace) Config with quantity and price
     */
    public function save()
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
            empty($data['branch_id']) ||
            empty($data['program_type_id']) ||
            empty($data['class_id']) ||
            empty($data['subjects']) ||
            !is_array($data['subjects'])
        ) {
            Response::json(["message" => "Invalid input. Required: branch_id, program_type_id, class_id, subjects array"], 422);
        }

        // Validate subjects array structure
        foreach ($data['subjects'] as $subject) {
            if (empty($subject['subject_id']) || !isset($subject['quantity']) || !isset($subject['price'])) {
                Response::json(["message" => "Each subject must have subject_id, quantity, and price"], 422);
            }

            if ($subject['quantity'] <= 0) {
                Response::json(["message" => "Quantity must be greater than 0"], 422);
            }

            if ($subject['price'] < 0) {
                Response::json(["message" => "Price cannot be negative"], 422);
            }
        }

        try {
            $this->model->replaceConfig(
                (int)$user['school_id'],
                (int)$data['branch_id'],
                (int)$data['program_type_id'],
                (int)$data['class_id'],
                isset($data['agency_id']) ? (int)$data['agency_id'] : null,
                $data['subjects']
            );

            Response::json(["message" => "Program config saved successfully"]);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 500);
        }
    }

    /**
     * Get Config with quantity and price
     */
    public function get()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $branchId = $_GET['branch_id'] ?? null;
        $programTypeId = $_GET['program_type_id'] ?? null;
        $classId = $_GET['class_id'] ?? null;

        if (!$branchId || !$programTypeId || !$classId) {
            Response::json(["message" => "Missing parameters: branch_id, program_type_id, class_id"], 422);
        }

        $data = $this->model->getSubjectsWithDetails(
            (int)$user['school_id'],
            (int)$branchId,
            (int)$programTypeId,
            (int)$classId
        );

        Response::json(["data" => $data]);
    }
}