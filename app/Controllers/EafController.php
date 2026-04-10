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
    public function getMarks()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $classId = $_GET['class_id'] ?? null;
        $subjectId = $_GET['subject_id'] ?? null;
        $examType = $_GET['exam_type'] ?? null;

        if (!$classId || !$subjectId || !$examType) {
            Response::json(["message" => "Missing params"], 422);
        }

        $data = $this->eafModel->getMarks($classId, $subjectId, $examType);

        Response::json([
            "status" => true,
            "data" => $data
        ]);
    }
    public function saveMarks()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        $examType = $data['exam_type'];
        $records = $data['records'];

        $result = $this->eafModel->updateMarks($examType, $records);

        Response::json([
            "status" => true,
            "message" => "Marks processed",
            "updated" => $result['updated'],
            "blocked" => $result['blocked']
        ]);
    }
    public function getStudentAllMarks()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $studentId = $_GET['student_id'] ?? null;

        if (!$studentId) {
            Response::json(["message" => "Student ID required"], 422);
        }

        $data = $this->eafModel->getStudentAllMarks($studentId);

        Response::json([
            "status" => true,
            "data" => $data
        ]);
    }
}