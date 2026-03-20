<?php
// controllers/SubjectDefaultsController.php

require_once __DIR__ . '/../Models/SubjectDefaultsModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Core/Response.php';

class SubjectDefaultsController
{
    private SubjectDefaultsModel $subjectDefaultsModel;

    public function __construct(PDO $db)
    {
        $this->subjectDefaultsModel = new SubjectDefaultsModel($db);
    }

    /**
     * Get all default subjects
     * GET /api/subject-defaults
     */
    public function getAll()
    {
        try {
            // Verify authentication (optional - remove if you want public access)
            $user = JwtHelper::getUserFromToken();
            
            // Get search parameter if provided
            $search = $_GET['search'] ?? null;
            
            if ($search) {
                $subjects = $this->subjectDefaultsModel->search($search);
            } else {
                $subjects = $this->subjectDefaultsModel->getAll();
            }
            
            Response::json([
                "success" => true,
                "total" => count($subjects),
                "subjects" => $subjects
            ]);
            
        } catch (Exception $e) {
            Response::json([
                "success" => false,
                "message" => "Failed to fetch default subjects",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get a specific default subject by ID
     * GET /api/subject-defaults/{id}
     */
    public function get($id)
    {
        try {
            // Verify authentication (optional - remove if you want public access)
            $user = JwtHelper::getUserFromToken();
            
            $subject = $this->subjectDefaultsModel->findById($id);
            
            if (!$subject) {
                Response::json([
                    "success" => false,
                    "message" => "Default subject not found"
                ], 404);
            }
            
            Response::json([
                "success" => true,
                "subject" => $subject
            ]);
            
        } catch (Exception $e) {
            Response::json([
                "success" => false,
                "message" => "Failed to fetch default subject",
                "error" => $e->getMessage()
            ], 400);
        }
    }
}