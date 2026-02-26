<?php
class StudentFeeModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create multiple student fee assignments
     */
    public function createBulk(array $assignments): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO student_fees (
            student_id, fee_type_id, fee_type_name, amount, 
            discount_amount, discount_reason, notes, final_amount,
            due_date, academic_year, status, paid_amount, created_by, created_by_name
        ) VALUES (
            :student_id, :fee_type_id, :fee_type_name, :amount,
            :discount_amount, :discount_reason, :notes, :final_amount,
            :due_date, :academic_year, :status, :paid_amount, :created_by, :created_by_name
        )";

            $stmt = $this->db->prepare($sql);

            foreach ($assignments as $assignment) {
                $stmt->execute([
                    ':student_id' => $assignment['student_id'],
                    ':fee_type_id' => $assignment['fee_type_id'],
                    ':fee_type_name' => $assignment['fee_type_name'],
                    ':amount' => $assignment['amount'],
                    ':discount_amount' => $assignment['discount_amount'] ?? 0,
                    ':discount_reason' => $assignment['discount_reason'] ?? null,
                    ':notes' => $assignment['notes'] ?? null,
                    ':final_amount' => $assignment['final_amount'],
                    ':due_date' => $assignment['due_date'] ?? null,
                    ':academic_year' => $assignment['academic_year'],
                    ':status' => $assignment['status'] ?? 'pending',
                    ':paid_amount' => 0.00,
                    ':created_by' => $assignment['created_by'],
                    ':created_by_name' => $assignment['created_by_name'] ?? null,
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in createBulk: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get student fees with filters
     */
    public function getAll(array $filters = []): array
{
    $sql = "SELECT 
                sf.*,
                s.first_name,
                s.last_name,
                s.admission_number,
                s.class_id,
                s.section_id,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                c.name as class_name,
                sec.name as section_name
            FROM student_fees sf
            JOIN students s ON sf.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN sections sec ON s.section_id = sec.id
            WHERE 1=1";
    
    $params = [];

    if (!empty($filters['student_id'])) {
        $sql .= " AND sf.student_id = :student_id";
        $params[':student_id'] = $filters['student_id'];
    }

    if (!empty($filters['class_id'])) {
        $sql .= " AND s.class_id = :class_id";
        $params[':class_id'] = $filters['class_id'];
    }

    if (!empty($filters['section_id'])) {
        $sql .= " AND s.section_id = :section_id";
        $params[':section_id'] = $filters['section_id'];
    }

    if (!empty($filters['fee_type_id'])) {
        $sql .= " AND sf.fee_type_id = :fee_type_id";
        $params[':fee_type_id'] = $filters['fee_type_id'];
    }

    if (!empty($filters['academic_year'])) {
        $sql .= " AND sf.academic_year = :academic_year";
        $params[':academic_year'] = $filters['academic_year'];
    }

    if (!empty($filters['status'])) {
        $sql .= " AND sf.status = :status";
        $params[':status'] = $filters['status'];
    }

    $sql .= " ORDER BY sf.created_at DESC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Get student fee by ID
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT sf.*, 
                s.first_name, s.last_name, s.admission_number,
                s.class_id, s.section_id,
                c.name as class_name, sec.name as section_name
                FROM student_fees sf
                JOIN students s ON sf.student_id = s.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                WHERE sf.id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get fees by student ID
     */
    public function getByStudentId(int $studentId, ?string $academicYear = null): array
    {
        $sql = "SELECT sf.*, ft.name as fee_type_name
                FROM student_fees sf
                JOIN fee_types ft ON sf.fee_type_id = ft.id
                WHERE sf.student_id = :student_id";
        
        $params = [':student_id' => $studentId];

        if ($academicYear) {
            $sql .= " AND sf.academic_year = :academic_year";
            $params[':academic_year'] = $academicYear;
        }

        $sql .= " ORDER BY sf.due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update fee payment status
     */
    public function updatePaymentStatus(int $id, array $data): bool
    {
        $sql = "UPDATE student_fees SET
                status = :status,
                paid_amount = :paid_amount,
                paid_date = :paid_date,
                payment_method = :payment_method,
                transaction_id = :transaction_id
                WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':id' => $id,
            ':status' => $data['status'],
            ':paid_amount' => $data['paid_amount'] ?? 0,
            ':paid_date' => $data['paid_date'] ?? null,
            ':payment_method' => $data['payment_method'] ?? null,
            ':transaction_id' => $data['transaction_id'] ?? null,
        ]);
    }

    /**
     * Update a single fee record
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        $allowedFields = [
            'discount_amount', 'discount_reason', 'notes', 
            'final_amount', 'due_date', 'status'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE student_fees SET " . implode(', ', $fields) . " WHERE id = :id";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete a fee record
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM student_fees WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get fee summary statistics
     */
    public function getSummary(array $filters = []): array
    {
        $sql = "SELECT 
                COUNT(*) as total_fees,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue_count,
                SUM(final_amount) as total_amount,
                SUM(paid_amount) as total_paid,
                SUM(final_amount - paid_amount) as total_due
                FROM student_fees sf
                JOIN students s ON sf.student_id = s.id
                WHERE 1=1";
        
        $params = [];

        if (!empty($filters['class_id'])) {
            $sql .= " AND s.class_id = :class_id";
            $params[':class_id'] = $filters['class_id'];
        }

        if (!empty($filters['section_id'])) {
            $sql .= " AND s.section_id = :section_id";
            $params[':section_id'] = $filters['section_id'];
        }

        if (!empty($filters['academic_year'])) {
            $sql .= " AND sf.academic_year = :academic_year";
            $params[':academic_year'] = $filters['academic_year'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get overdue fees
     */
    public function getOverdueFees(string $currentDate): array
    {
        $sql = "SELECT sf.*, 
                s.first_name, s.last_name, s.admission_number,
                s.class_id, s.section_id,
                c.name as class_name, sec.name as section_name
                FROM student_fees sf
                JOIN students s ON sf.student_id = s.id
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                WHERE sf.due_date < :current_date 
                AND sf.status IN ('pending', 'partial')
                ORDER BY sf.due_date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':current_date' => $currentDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    //     * Check if assignments already exist
    //  * 
    //  * @param array $assignments Array of assignments to check
    //  * @return array Array of existing assignments with details
    //  */
    public function checkExistingAssignments(array $assignments): array
    {
        if (empty($assignments)) {
            return [];
        }

        try {
            $conditions = [];
            $params = [];

            foreach ($assignments as $index => $assignment) {
                $academic_year = $assignment['academic_year'] ?? date('Y') . '-' . (date('Y') + 1);

                $conditions[] = "(student_id = :student_id_{$index} AND fee_type_id = :fee_type_id_{$index} AND academic_year = :academic_year_{$index})";
                $params[":student_id_{$index}"] = (int)$assignment['student_id'];
                $params[":fee_type_id_{$index}"] = (int)$assignment['fee_type_id'];
                $params[":academic_year_{$index}"] = $academic_year;
            }

            $whereClause = implode(' OR ', $conditions);

            $sql = "SELECT 
                    sf.student_id,
                    sf.fee_type_id,
                    ft.name as fee_type_name,
                    sf.academic_year,
                    sf.status,
                    s.first_name,
                    s.last_name,
                    s.admission_number
                FROM student_fees sf
                LEFT JOIN fee_types ft ON ft.id = sf.fee_type_id
                LEFT JOIN students s ON s.id = sf.student_id
                WHERE {$whereClause} 
                AND sf.status != 'cancelled'"; // Exclude cancelled fees if you want to allow re-assignment

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in checkExistingAssignments: " . $e->getMessage());
            throw $e;
        }
    }
    public function createBulkWithDuplicateCheck(array $assignments): array
    {
        try {
            $this->db->beginTransaction();

            $created = [];
            $duplicates = [];
            $skipped = [];

            // First, get all existing combinations
            $existingMap = $this->getExistingAssignmentsMap($assignments);

            foreach ($assignments as $index => $assignment) {
                $key = $assignment['student_id'] . '_' . $assignment['fee_type_id'] . '_' . $assignment['academic_year'];

                // Check if this combination already exists and is not cancelled
                if (isset($existingMap[$key]) && $existingMap[$key]['status'] != 'cancelled') {
                    $duplicates[] = [
                        'student_id' => $assignment['student_id'],
                        'fee_type_id' => $assignment['fee_type_id'],
                        'fee_type_name' => $assignment['fee_type_name'],
                        'academic_year' => $assignment['academic_year'],
                        'existing_status' => $existingMap[$key]['status']
                    ];
                    continue;
                }

                // If it exists but is cancelled, we can update it instead of inserting
                if (isset($existingMap[$key]) && $existingMap[$key]['status'] == 'cancelled') {
                    // Update the existing cancelled record
                    $updated = $this->updateCancelledFee($existingMap[$key]['id'], $assignment);
                    if ($updated) {
                        $created[] = [
                            'student_id' => $assignment['student_id'],
                            'fee_type_id' => $assignment['fee_type_id'],
                            'id' => $existingMap[$key]['id'],
                            'was_cancelled' => true
                        ];
                    } else {
                        $skipped[] = $assignment;
                    }
                    continue;
                }

                // Insert new record
                $id = $this->insertFeeAssignment($assignment);
                $created[] = [
                    'student_id' => $assignment['student_id'],
                    'fee_type_id' => $assignment['fee_type_id'],
                    'id' => $id,
                    'was_cancelled' => false
                ];
            }

            $this->db->commit();

            return [
                'created' => $created,
                'duplicates' => $duplicates,
                'skipped' => $skipped,
                'created_count' => count($created),
                'duplicate_count' => count($duplicates)
            ];
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in createBulkWithDuplicateCheck: " . $e->getMessage());
            throw $e;
        }
    }
    private function getExistingAssignmentsMap(array $assignments): array
    {
        if (empty($assignments)) {
            return [];
        }

        $studentIds = array_column($assignments, 'student_id');
        $feeTypeIds = array_column($assignments, 'fee_type_id');
        $academicYears = array_column($assignments, 'academic_year');

        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

        $sql = "SELECT id, student_id, fee_type_id, academic_year, status 
            FROM student_fees 
            WHERE student_id IN ($placeholders) 
            AND fee_type_id IN ($placeholders) 
            AND academic_year IN ($placeholders)";

        $params = array_merge($studentIds, $feeTypeIds, $academicYears);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $map = [];
        foreach ($results as $row) {
            $key = $row['student_id'] . '_' . $row['fee_type_id'] . '_' . $row['academic_year'];
            $map[$key] = $row;
        }

        return $map;
    }

    /**
     * Insert single fee assignment
     */
    private function insertFeeAssignment(array $assignment): int
    {
        $sql = "INSERT INTO student_fees (
        student_id, fee_type_id, fee_type_name, amount, 
        discount_amount, discount_reason, notes, final_amount,
        due_date, academic_year, status, paid_amount, created_by, created_by_name
    ) VALUES (
        :student_id, :fee_type_id, :fee_type_name, :amount,
        :discount_amount, :discount_reason, :notes, :final_amount,
        :due_date, :academic_year, :status, :paid_amount, :created_by, :created_by_name
    )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':student_id' => $assignment['student_id'],
            ':fee_type_id' => $assignment['fee_type_id'],
            ':fee_type_name' => $assignment['fee_type_name'],
            ':amount' => $assignment['amount'],
            ':discount_amount' => $assignment['discount_amount'] ?? 0,
            ':discount_reason' => $assignment['discount_reason'] ?? null,
            ':notes' => $assignment['notes'] ?? null,
            ':final_amount' => $assignment['final_amount'],
            ':due_date' => $assignment['due_date'] ?? null,
            ':academic_year' => $assignment['academic_year'],
            ':status' => $assignment['status'] ?? 'pending',
            ':paid_amount' => 0.00,
            ':created_by' => $assignment['created_by'],
            ':created_by_name' => $assignment['created_by_name'] ?? null,
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Update cancelled fee
     */
    private function updateCancelledFee(int $feeId, array $newData): bool
    {
        $sql = "UPDATE student_fees SET 
                amount = :amount,
                discount_amount = :discount_amount,
                discount_reason = :discount_reason,
                notes = :notes,
                final_amount = :final_amount,
                due_date = :due_date,
                status = 'pending',
                paid_amount = 0,
                paid_date = NULL,
                payment_method = NULL,
                transaction_id = NULL,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':id' => $feeId,
            ':amount' => $newData['amount'],
            ':discount_amount' => $newData['discount_amount'] ?? 0,
            ':discount_reason' => $newData['discount_reason'] ?? null,
            ':notes' => $newData['notes'] ?? null,
            ':final_amount' => $newData['final_amount'],
            ':due_date' => $newData['due_date'] ?? null
        ]);
    }
}