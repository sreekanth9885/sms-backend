<?php
require_once __DIR__ . '/../Models/SubjectModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class SubjectController
{
    private SubjectModel $subjectModel;

    public function __construct(PDO $db)
    {
        $this->subjectModel = new SubjectModel($db);
    }

    public function index()
{
    $user = JwtHelper::getUserFromToken();

    if (!isset($user['school_id'])) {
        Response::json(["message" => "School context missing"], 403);
    }

    // ✅ get class_id from frontend
    $classId = $_GET['class_id'] ?? null;

    $subjects = $this->subjectModel->getByClassId($classId);

    Response::json([
        "status" => true,
        "subjects" => $subjects
    ]);
}
}