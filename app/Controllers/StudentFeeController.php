<?php
require_once __DIR__ . '/../Models/StudentFeeModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Models/StudentFeePaymentModel.php';

class StudentFeeController
{
    private StudentFeeModel $studentFeeModel;
    private StudentFeePaymentModel $paymentModel;
    private PDO $db;
    public function __construct(PDO $db)
    {
        $this->studentFeeModel = new StudentFeeModel($db);
        $this->paymentModel = new StudentFeePaymentModel($db);
        $this->db = $db;
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
        foreach ($data['assignments'] as $index => $assignment) {
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
            // Check for existing assignments
            $existingAssignments = $this->studentFeeModel->checkExistingAssignments($data['assignments']);

            if (!empty($existingAssignments)) {
                $duplicateDetails = array_map(function ($item) {
                    return [
                        'student_id' => $item['student_id'],
                        'fee_type_id' => $item['fee_type_id'],
                        'fee_type_name' => $item['fee_type_name'] ?? 'Unknown',
                        'academic_year' => $item['academic_year'],
                        'status' => $item['status']
                    ];
                }, $existingAssignments);

                Response::json([
                    "success" => false,
                    "message" => "Some fee assignments already exist",
                    "duplicates" => $duplicateDetails,
                    "total_duplicates" => count($duplicateDetails)
                ], 409); // 409 Conflict
            }

            // Prepare assignments for insertion
            $assignments = array_map(function ($assignment) use ($user) {
                $academic_year = $assignment['academic_year'] ?? date('Y') . '-' . (date('Y') + 1);

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
                    'academic_year' => $academic_year,
                    'status' => 'pending',
                    'paid_amount' => 0.00,
                    'created_by' => (int)$user['id'],
                    'created_by_name' => $user['name'] ?? null,
                ];
            }, $data['assignments']);

            $result = $this->studentFeeModel->createBulk($assignments);

            Response::json([
                "success" => true,
                "message" => "Fee assignments created successfully",
                "count" => count($assignments)
            ], 201);
        } catch (Exception $e) {
            // Check for duplicate entry error
            if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate entry') !== false) {
            Response::json([
                    "success" => false,
                    "message" => "Duplicate fee assignment detected. This student already has this fee type for the academic year.",
                    "error" => "duplicate_entry"
                ], 409);
            }

            Response::json([
                "success" => false,
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
    public function collectPayment($id)
    {
        $input = json_decode(file_get_contents("php://input"), true);

        $amount = (float) ($input['amount'] ?? 0);
        $paymentMethod = $input['payment_method'] ?? null;
        $transactionId = $input['transaction_id'] ?? null;
        $remarks = $input['remarks'] ?? null;

        if ($amount <= 0) {
            Response::json(['error' => 'Invalid payment amount'], 422);
            return;
        }

        $fee = $this->studentFeeModel->getById($id);

        if (!$fee) {
            Response::json(['error' => 'Fee record not found'], 404);
            return;
        }

        $remaining = $fee['final_amount'] - $fee['paid_amount'];

        if ($amount > $remaining) {
            Response::json(['error' => 'Payment exceeds remaining balance'], 422);
            return;
        }

        try {
            $this->db->beginTransaction();

            // 1️⃣ Insert into payment ledger
            $this->paymentModel->createPayment([
                'student_fee_id' => $fee['id'],
                'student_id' => $fee['student_id'],
                'paid_amount' => $amount,
                'payment_method' => $paymentMethod,
                'transaction_id' => $transactionId,
                'remarks' => $remarks,
                'collected_by' => 1, // replace with logged-in user ID
                'collected_by_name' => 'Admin' // replace dynamically
            ]);

            // 2️⃣ Update master invoice
            $newPaidAmount = $fee['paid_amount'] + $amount;

            $status = 'pending';
            if ($newPaidAmount >= $fee['final_amount']) {
                $status = 'paid';
            } elseif ($newPaidAmount > 0) {
                $status = 'partial';
            }

            $this->studentFeeModel->updatePaymentDetails($fee['id'], [
                'paid_amount' => $newPaidAmount,
                'status' => $status,
                'paid_date' => date('Y-m-d')
            ]);

            $this->db->commit();

            Response::json([
                'message' => 'Payment collected successfully'
            ]);
        } catch (Exception $e) {
            $this->db->rollBack();
            Response::json(['error' => 'Payment failed'], 500);
        }
    }
    public function getPaymentHistory($id)
    {
        $payments = $this->paymentModel->getPaymentsByStudentFeeId($id);

        Response::json($payments);
    }
}