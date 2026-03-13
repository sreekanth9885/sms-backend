<?php
require_once __DIR__ . '/../Models/SubjectModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Core/Response.php';

class SubjectController
{
    private SubjectModel $subjectModel;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->subjectModel = new SubjectModel($db);
        $this->db = $db;
    }

    /**
     * Create a new subject
     * POST /subjects
     */
    public function create()
    {
        try {
            $user = JwtHelper::getUserFromToken();
            
            // Check permission (only ADMIN and SUPER_ADMIN can create subjects)
            if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN'])) {
                Response::json(["message" => "Forbidden - Insufficient permissions"], 403);
            }

            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data)) {
                Response::json(["message" => "No data provided"], 400);
            }

            // Validate required fields
            $required = ['class_id', 'subject_name'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    Response::json(["message" => ucfirst(str_replace('_', ' ', $field)) . " is required"], 422);
                }
            }

            // Verify class belongs to user's school
            if (!$this->verifyClassAccess($data['class_id'], $user)) {
                Response::json(["message" => "Invalid class or access denied"], 403);
            }

            $subject = $this->subjectModel->create($data);

            Response::json([
                "message" => "Subject created successfully",
                "subject" => $subject
            ], 201);

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to create subject",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update an existing subject
     * PUT /subjects/{id}
     */
    public function update($id)
    {
        try {
            $user = JwtHelper::getUserFromToken();
            
            // Check permission
            if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN'])) {
                Response::json(["message" => "Forbidden - Insufficient permissions"], 403);
            }

            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data)) {
                Response::json(["message" => "No data provided"], 400);
            }

            // Get existing subject to verify access
            $existing = $this->subjectModel->findById($id);
            if (!$existing) {
                Response::json(["message" => "Subject not found"], 404);
            }

            // Verify class access
            if (!$this->verifyClassAccess($existing['class_id'], $user)) {
                Response::json(["message" => "Access denied"], 403);
            }

            $subject = $this->subjectModel->update($id, $data);

            Response::json([
                "message" => "Subject updated successfully",
                "subject" => $subject
            ]);

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to update subject",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete a subject
     * DELETE /subjects/{id}
     */
    public function delete($id)
    {
        try {
            $user = JwtHelper::getUserFromToken();
            
            // Check permission
            if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN'])) {
                Response::json(["message" => "Forbidden - Insufficient permissions"], 403);
            }

            // Get existing subject to verify access
            $existing = $this->subjectModel->findById($id);
            if (!$existing) {
                Response::json(["message" => "Subject not found"], 404);
            }

            // Verify class access
            if (!$this->verifyClassAccess($existing['class_id'], $user)) {
                Response::json(["message" => "Access denied"], 403);
            }

            $deleted = $this->subjectModel->delete($id);

            if ($deleted) {
                Response::json(["message" => "Subject deleted successfully"]);
            } else {
                Response::json(["message" => "Failed to delete subject"], 500);
            }

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to delete subject",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get subject by ID
     * GET /subjects/{id}
     */
    public function get($id)
    {
        try {
            $user = JwtHelper::getUserFromToken();

            $subject = $this->subjectModel->findById($id);

            if (!$subject) {
                Response::json(["message" => "Subject not found"], 404);
            }

            // Verify class access
            if (!$this->verifyClassAccess($subject['class_id'], $user)) {
                Response::json(["message" => "Access denied"], 403);
            }

            Response::json($subject);

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to fetch subject",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get subjects by class
     * GET /subjects/class/{classId}
     */
    public function getByClass($classId)
    {
        try {
            $user = JwtHelper::getUserFromToken();

            // Verify class access
            if (!$this->verifyClassAccess($classId, $user)) {
                Response::json(["message" => "Invalid class or access denied"], 403);
            }

            $subjects = $this->subjectModel->getByClass($classId);

            Response::json([
                "class_id" => (int)$classId,
                "total" => count($subjects),
                "subjects" => $subjects
            ]);

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to fetch subjects",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get all subjects (with optional filters)
     * GET /subjects
     */
    public function getAll()
    {
        try {
            $user = JwtHelper::getUserFromToken();

            // For SUPER_ADMIN, can see all subjects across schools
            // For ADMIN, only see their school's subjects
            $schoolId = $user['role'] === 'SUPER_ADMIN' 
                ? ($_GET['school_id'] ?? null) 
                : ($user['school_id'] ?? null);

            $search = $_GET['search'] ?? null;

            $subjects = $this->subjectModel->getAll($schoolId, $search);

            Response::json([
                "total" => count($subjects),
                "subjects" => $subjects
            ]);

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to fetch subjects",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get subjects summary
     * GET /subjects/summary
     */
    public function getSummary()
    {
        try {
            $user = JwtHelper::getUserFromToken();

            $classId = $_GET['class_id'] ?? null;

            // If class_id provided, verify access
            if ($classId && !$this->verifyClassAccess($classId, $user)) {
                Response::json(["message" => "Invalid class or access denied"], 403);
            }

            $summary = $this->subjectModel->getSummary($classId);

            Response::json($summary);

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to fetch summary",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Bulk create subjects for a class
     * POST /subjects/bulk
     */
    public function bulkCreate()
    {
        try {
            $user = JwtHelper::getUserFromToken();
            
            // Check permission
            if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN'])) {
                Response::json(["message" => "Forbidden - Insufficient permissions"], 403);
            }

            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data['class_id'])) {
                Response::json(["message" => "Class ID is required"], 422);
            }

            if (empty($data['subjects']) || !is_array($data['subjects'])) {
                Response::json(["message" => "Subjects array is required"], 422);
            }

            // Verify class access
            if (!$this->verifyClassAccess($data['class_id'], $user)) {
                Response::json(["message" => "Invalid class or access denied"], 403);
            }

            $result = $this->subjectModel->bulkCreate($data['class_id'], $data['subjects']);

            if ($result['success']) {
                Response::json([
                    "message" => "Subjects created successfully",
                    "count" => $result['count'],
                    "subjects" => $result['created']
                ], 201);
            } else {
                Response::json([
                    "message" => "Failed to create some subjects",
                    "errors" => $result['errors']
                ], 422);
            }

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to create subjects",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reorder subject priorities
     * POST /subjects/reorder
     */
    public function reorderPriorities()
    {
        try {
            $user = JwtHelper::getUserFromToken();
            
            // Check permission
            if (!in_array($user['role'], ['ADMIN', 'SUPER_ADMIN'])) {
                Response::json(["message" => "Forbidden - Insufficient permissions"], 403);
            }

            $data = json_decode(file_get_contents("php://input"), true);

            if (empty($data['class_id'])) {
                Response::json(["message" => "Class ID is required"], 422);
            }

            if (empty($data['priorities']) || !is_array($data['priorities'])) {
                Response::json(["message" => "Priorities array is required"], 422);
            }

            // Verify class access
            if (!$this->verifyClassAccess($data['class_id'], $user)) {
                Response::json(["message" => "Invalid class or access denied"], 403);
            }

            $reordered = $this->subjectModel->reorderPriorities($data['class_id'], $data['priorities']);

            if ($reordered) {
                Response::json([
                    "message" => "Priorities reordered successfully",
                    "class_id" => $data['class_id']
                ]);
            } else {
                Response::json(["message" => "Failed to reorder priorities"], 500);
            }

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to reorder priorities",
                "error" => $e->getMessage()
            ], 400);
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Verify if user has access to the class
     */
    private function verifyClassAccess(int $classId, array $user): bool
    {
        // SUPER_ADMIN can access any class
        if ($user['role'] === 'SUPER_ADMIN') {
            return true;
        }

        // For ADMIN, verify class belongs to their school
        if ($user['role'] === 'ADMIN' && isset($user['school_id'])) {
            $stmt = $this->db->prepare("SELECT id FROM classes WHERE id = ? AND school_id = ?");
            $stmt->execute([$classId, $user['school_id']]);
            return $stmt->fetch() !== false;
        }

        return false;
    }
}