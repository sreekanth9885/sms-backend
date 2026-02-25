<?php
require_once __DIR__ . '/../Models/StudentFeeModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class StudentFeeController
{
    private StudentFeeModel $studentFeeModel;

    public function __construct(PDO $db)
    {
        $this->studentFeeModel = new StudentFeeModel($db);
    }

    /**
     * Create bulk fee assignments
     * POST /student-fees/bulk
     */
    public function createBulk()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['assignments']) || !is_array($data['assignments'])) {
            Response::json(["message" => "Assignments data is required"], 422);
        }

        // Validate each assignment
        foreach ($data['assignments'] as $assignment) {
            if (empty($assignment['student_id'])) {
                Response::json(["message" => "Student ID is required for all assignments"], 422);
            }
            if (empty($assignment['fee_type_id'])) {
                Response::json(["message" => "Fee type ID is required for all assignments"], 422);
            }
            if (empty($assignment['amount']) || $assignment['amount'] <= 0) {
                Response::json(["message" => "Valid amount is required for all assignments"], 422);
            }
        }

        try {
            $assignments = array_map(function ($assignment) use ($user) {
                return [
                    'student_id' => (int)$assignment['student_id'],
                    'fee_type_id' => (int)$assignment['fee_type_id'],
                    'fee_type_name' => $assignment['fee_type_name'] ?? '',
                    'amount' => (float)$assignment['amount'],
                    'discount_amount' => (float)($assignment['discount_amount'] ?? 0),
                    'discount_reason' => $assignment['discount_reason'] ?? null,
                    'notes' => $assignment['notes'] ?? null,
                    'final_amount' => (float)($assignment['final_amount'] ?? $assignment['amount']),
                    'due_date' => $assignment['due_date'] ?? null,
                    'academic_year' => $assignment['academic_year'] ?? date('Y') . '-' . (date('Y') + 1),
                    'status' => 'pending',
                    'created_by' => (int)$user['id'],
                    'created_by_name' => $user['name'] ?? null,
                ];
            }, $data['assignments']);

            $result = $this->studentFeeModel->createBulk($assignments);

            Response::json([
                "message" => "Fee assignments created successfully",
                "count" => count($assignments)
            ], 201);

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to create fee assignments",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all student fees with filters
     * GET /student-fees
     */
    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        $filters = [
            'student_id' => $_GET['student_id'] ?? null,
            'class_id' => $_GET['class_id'] ?? null,
            'section_id' => $_GET['section_id'] ?? null,
            'fee_type_id' => $_GET['fee_type_id'] ?? null,
            'academic_year' => $_GET['academic_year'] ?? null,
            'status' => $_GET['status'] ?? null,
        ];

        $fees = $this->studentFeeModel->getAll($filters);

        Response::json($fees);
    }

    /**
     * Get student fee by ID
     * GET /student-fees/{id}
     */
    public function show($id)
    {
        $fee = $this->studentFeeModel->getById((int)$id);

        if (!$fee) {
            Response::json(["message" => "Fee record not found"], 404);
        }

        Response::json($fee);
    }

    /**
     * Get fees by student ID
     * GET /students/{studentId}/fees
     */
    public function getByStudent($studentId)
    {
        $academicYear = $_GET['academic_year'] ?? null;
        
        $fees = $this->studentFeeModel->getByStudentId((int)$studentId, $academicYear);

        Response::json($fees);
    }

    /**
     * Update fee record
     * PUT /student-fees/{id}
     */
    public function update($id)
    {
        $user = JwtHelper::getUserFromToken();

        $data = json_decode(file_get_contents("php://input"), true);

        // Check if fee exists
        $existingFee = $this->studentFeeModel->getById((int)$id);
        if (!$existingFee) {
            Response::json(["message" => "Fee record not found"], 404);
        }

        $allowedFields = ['discount_amount', 'discount_reason', 'notes', 'due_date'];
        $updateData = [];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if ($field === 'discount_amount') {
                    $updateData[$field] = (float)$data[$field];
                    // Recalculate final amount
                    $updateData['final_amount'] = $existingFee['amount'] - (float)$data[$field];
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }

        if (empty($updateData)) {
            Response::json(["message" => "No valid fields to update"], 400);
        }

        $updated = $this->studentFeeModel->update((int)$id, $updateData);

        if (!$updated) {
            Response::json(["message" => "Update failed"], 400);
        }

        Response::json(["message" => "Fee record updated successfully"]);
    }

    /**
     * Update payment status
     * PATCH /student-fees/{id}/payment
     */
    public function updatePayment($id)
    {
        $user = JwtHelper::getUserFromToken();

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['status'])) {
            Response::json(["message" => "Status is required"], 422);
        }

        $allowedStatuses = ['pending', 'paid', 'partial', 'overdue', 'cancelled'];
        if (!in_array($data['status'], $allowedStatuses)) {
            Response::json(["message" => "Invalid status"], 422);
        }

        $paymentData = [
            'status' => $data['status'],
            'paid_amount' => $data['paid_amount'] ?? 0,
            'paid_date' => $data['paid_date'] ?? date('Y-m-d'),
            'payment_method' => $data['payment_method'] ?? null,
            'transaction_id' => $data['transaction_id'] ?? null,
        ];

        $updated = $this->studentFeeModel->updatePaymentStatus((int)$id, $paymentData);

        if (!$updated) {
            Response::json(["message" => "Payment update failed"], 400);
        }

        Response::json(["message" => "Payment status updated successfully"]);
    }

    /**
     * Delete fee record
     * DELETE /student-fees/{id}
     */
    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'ADMIN' && $user['role'] !== 'SUPER_ADMIN') {
            Response::json(["message" => "Forbidden - Admin access required"], 403);
        }

        $deleted = $this->studentFeeModel->delete((int)$id);

        if (!$deleted) {
            Response::json(["message" => "Fee record not found"], 404);
        }

        Response::json(["message" => "Fee record deleted successfully"]);
    }

    /**
     * Get fee summary
     * GET /student-fees/summary
     */
    public function summary()
    {
        $user = JwtHelper::getUserFromToken();

        $filters = [
            'class_id' => $_GET['class_id'] ?? null,
            'section_id' => $_GET['section_id'] ?? null,
            'academic_year' => $_GET['academic_year'] ?? null,
        ];

        $summary = $this->studentFeeModel->getSummary($filters);

        Response::json($summary);
    }

    /**
     * Get overdue fees
     * GET /student-fees/overdue
     */
    public function overdue()
    {
        $user = JwtHelper::getUserFromToken();

        $currentDate = date('Y-m-d');
        $overdueFees = $this->studentFeeModel->getOverdueFees($currentDate);

        Response::json($overdueFees);
    }
}