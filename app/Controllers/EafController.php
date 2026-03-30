
<?php
require_once __DIR__ . '/../Models/EafModel.php';
require_once __DIR__ . '/../Core/Response.php';

class EafController
{
    private EafModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new EafModel($db);
    }

    public function getByClass()
    {
        try {
            $classId = $_GET['class_id'] ?? null;
            $sectionId = $_GET['section_id'] ?? null;

            if (!$classId) {
                Response::json(["message" => "Class ID is required"], 422);
            }

            $data = $this->model->getByClass(
                (int)$classId,
                $sectionId ? (int)$sectionId : null
            );

            Response::json([
                "total" => count($data),
                "data" => $data
            ]);

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to fetch EAF data",
                "error" => $e->getMessage()
            ], 400);
        }
    }
}