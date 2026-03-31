<?php

require_once __DIR__ . '/../Models/EafModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class EafController
{
    private EafModel $eafModel;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->eafModel = new EafModel($db);
    }

    public function generate()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        $classId = $data['class_id'] ?? null;
        $students = $data['students'] ?? [];
        $subjects = $data['subjects'] ?? [];

        if (!$classId || empty($students) || empty($subjects)) {
            Response::json(["message" => "Invalid data"], 422);
        }

        try {
            $this->db->beginTransaction();

            $this->eafModel->bulkInsert($classId, $students, $subjects);

            $this->db->commit();

            Response::json([
                "status" => true,
                "message" => "EAF generated successfully"
            ]);

        } catch (Exception $e) {
            $this->db->rollBack();

            Response::json([
                "status" => false,
                "message" => $e->getMessage()
            ], 500);
        }
    }
}