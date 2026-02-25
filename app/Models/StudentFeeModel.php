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
                due_date, academic_year, status, created_by, created_by_name
            ) VALUES (
                :student_id, :fee_type_id, :fee_type_name, :amount,
                :discount_amount, :discount_reason, :notes, :final_amount,
                :due_date, :academic_year, :status, :created_by, :created_by_name
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
                CONCAT(s.first_name, ' ', s.last_name) as student_name
            FROM student_fees sf
            JOIN students s ON sf.student_id = s.id
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
}