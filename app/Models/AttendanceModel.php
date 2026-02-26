<?php
class AttendanceModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Save or update attendance for multiple students
     */
    public function saveBulk(array $attendanceData, int $markedBy, ?string $markedByName): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO attendance 
                    (student_id, class_id, section_id, date, status, remarks, marked_by, marked_by_name) 
                    VALUES (:student_id, :class_id, :section_id, :date, :status, :remarks, :marked_by, :marked_by_name)
                    ON DUPLICATE KEY UPDATE 
                    status = VALUES(status),
                    remarks = VALUES(remarks),
                    updated_at = CURRENT_TIMESTAMP";

            $stmt = $this->db->prepare($sql);

            foreach ($attendanceData as $data) {
                $stmt->execute([
                    ':student_id' => $data['student_id'],
                    ':class_id' => $data['class_id'],
                    ':section_id' => $data['section_id'],
                    ':date' => $data['date'],
                    ':status' => $data['status'],
                    ':remarks' => $data['remarks'] ?? null,
                    ':marked_by' => $markedBy,
                    ':marked_by_name' => $markedByName,
                ]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error in saveBulk: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get attendance for a specific class and section on a date
     */
    public function getByClassSection(int $classId, int $sectionId, string $date): array
{
    $sql = "SELECT 
                a.id,
                a.student_id,
                a.class_id,
                a.section_id,
                a.date,
                a.status,
                a.remarks,
                a.marked_by,
                a.marked_by_name,
                a.created_at,
                a.updated_at,
                s.id as student_id,
                s.first_name,
                s.last_name,
                s.admission_number,
                s.roll_number,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                :class_id_param as class_id,
                :section_id_param as section_id,
                :date_param as date
            FROM students s
            LEFT JOIN attendance a ON a.student_id = s.id 
                AND a.date = :date_join
                AND a.class_id = :class_id_join 
                AND a.section_id = :section_id_join
            WHERE s.class_id = :class_id_where 
                AND s.section_id = :section_id_where
            ORDER BY s.roll_number ASC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':class_id_param' => $classId,
        ':section_id_param' => $sectionId,
        ':date_param' => $date,
        ':date_join' => $date,
        ':class_id_join' => $classId,
        ':section_id_join' => $sectionId,
        ':class_id_where' => $classId,
        ':section_id_where' => $sectionId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

    /**
     * Get attendance for a specific student
     */
    public function getByStudent(int $studentId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT a.*, 
                c.name as class_name, sec.name as section_name
                FROM attendance a
                JOIN classes c ON a.class_id = c.id
                JOIN sections sec ON a.section_id = sec.id
                WHERE a.student_id = :student_id";
        
        $params = [':student_id' => $studentId];

        if ($startDate && $endDate) {
            $sql .= " AND a.date BETWEEN :start_date AND :end_date";
            $params[':start_date'] = $startDate;
            $params[':end_date'] = $endDate;
        }

        $sql .= " ORDER BY a.date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance summary for a class/section
     */
    public function getSummary(int $classId, int $sectionId, string $startDate, string $endDate): array
    {
        $sql = "SELECT 
                    a.date,
                    COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
                    COUNT(CASE WHEN a.status = 'Late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN a.status = 'Half-Day' THEN 1 END) as halfday_count,
                    COUNT(CASE WHEN a.status = 'Holiday' THEN 1 END) as holiday_count,
                    COUNT(*) as total_count
                FROM attendance a
                WHERE a.class_id = :class_id 
                    AND a.section_id = :section_id
                    AND a.date BETWEEN :start_date AND :end_date
                GROUP BY a.date
                ORDER BY a.date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':class_id' => $classId,
            ':section_id' => $sectionId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get today's attendance status for a student
     */
    public function getTodayStatus(int $studentId): ?array
    {
        $today = date('Y-m-d');
        
        $sql = "SELECT * FROM attendance 
                WHERE student_id = :student_id AND date = :date";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':student_id' => $studentId,
            ':date' => $today
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get attendance percentage for a student
     */
    public function getAttendancePercentage(int $studentId, string $startDate, string $endDate): float
    {
        $sql = "SELECT 
                    COUNT(*) as total_days,
                    COUNT(CASE WHEN status IN ('Present', 'Late') THEN 1 END) as present_days
                FROM attendance
                WHERE student_id = :student_id 
                    AND date BETWEEN :start_date AND :end_date";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':student_id' => $studentId,
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['total_days'] > 0) {
            return round(($result['present_days'] / $result['total_days']) * 100, 2);
        }
        
        return 0;
    }

    /**
     * Delete attendance for a specific date
     */
    public function deleteByDate(int $classId, int $sectionId, string $date): bool
    {
        $sql = "DELETE FROM attendance 
                WHERE class_id = :class_id 
                    AND section_id = :section_id 
                    AND date = :date";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':class_id' => $classId,
            ':section_id' => $sectionId,
            ':date' => $date
        ]);
    }
}