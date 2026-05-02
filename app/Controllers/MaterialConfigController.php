<?php

require_once __DIR__ . '/../Models/MaterialConfigModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class MaterialConfigController
{
    private MaterialConfigModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new MaterialConfigModel($db);
    }

    /**
     * Save Materials
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
            empty($data['materials']) ||
            !is_array($data['materials'])
        ) {
            Response::json(["message" => "Invalid input"], 422);
        }

        try {
            $this->model->saveConfig(
                (int)$user['school_id'],
                (int)$data['branch_id'],
                (int)$data['program_type_id'],
                (int)$data['class_id'],
                $data['materials']
            );

            Response::json(["message" => "Material config saved"]);

        } catch (Exception $e) {
            Response::json(["message" => $e->getMessage()], 500);
        }
    }

    /**
     * Get Materials
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
            Response::json(["message" => "Missing parameters"], 422);
        }

        $data = $this->model->getConfig(
            (int)$user['school_id'],
            (int)$branchId,
            (int)$programTypeId,
            (int)$classId
        );

        Response::json(["data" => $data]);
    }
}