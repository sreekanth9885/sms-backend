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
                section_id = VALUES(section_id),
                updated_at = CURRENT_TIMESTAMP";

            $stmt = $this->db->prepare($sql);

            foreach ($attendanceData as $data) {
                // Handle section_id - can be null
                $sectionId = isset($data['section_id']) && $data['section_id'] !== ''
                    ? $data['section_id']
                    : null;

                $stmt->execute([
                    ':student_id' => $data['student_id'],
                    ':class_id' => $data['class_id'],
                    ':section_id' => $sectionId,
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
    /**
     * Get attendance for a specific class and section on a date
     */
    public function getByClassSection(int $classId, ?int $sectionId, string $date): array
{
        // First, get all students in the class (and section if provided)
        $studentSql = "SELECT 
                    s.id as student_id,
                    s.first_name,
                    s.last_name,
                    s.admission_number,
                    s.roll_number,
                    CONCAT(s.first_name, ' ', s.last_name) as student_name
                FROM students s
                WHERE s.class_id = :class_id";

        $studentParams = [':class_id' => $classId];

        if ($sectionId !== null) {
            $studentSql .= " AND s.section_id = :section_id";
            $studentParams[':section_id'] = $sectionId;
        }

        $studentSql .= " ORDER BY s.roll_number ASC";

        $studentStmt = $this->db->prepare($studentSql);
        $studentStmt->execute($studentParams);
        $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($students)) {
            return [];
        }

        // Get student IDs
        $studentIds = array_column($students, 'student_id');
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));

        // Get attendance records for these students on this date
        $attendanceSql = "SELECT * FROM attendance 
                      WHERE student_id IN ({$placeholders}) 
                      AND date = ?";

        $attendanceParams = array_merge($studentIds, [$date]);

        $attendanceStmt = $this->db->prepare($attendanceSql);
        $attendanceStmt->execute($attendanceParams);
        $attendanceRecords = $attendanceStmt->fetchAll(PDO::FETCH_ASSOC);

        // Map attendance records by student_id
        $attendanceMap = [];
        foreach ($attendanceRecords as $record) {
            $attendanceMap[$record['student_id']] = $record;
        }

        // Combine students with their attendance
        $result = [];
        foreach ($students as $student) {
            $studentId = $student['student_id'];
            if (isset($attendanceMap[$studentId])) {
                // Student has attendance record
                $result[] = array_merge($student, $attendanceMap[$studentId]);
            } else {
                // Student has no attendance record for this date
                $result[] = array_merge($student, [
                    'id' => null,
                    'student_id' => $studentId,
                    'class_id' => $classId,
                    'section_id' => $sectionId,
                    'date' => $date,
                    'status' => null,
                    'remarks' => null,
                    'marked_by' => null,
                    'marked_by_name' => null,
                    'created_at' => null,
                    'updated_at' => null
                ]);
            }
        }

        return $result;
}

    /**
     * Get attendance for a specific student
     */
    public function getByStudent(int $studentId, ?string $startDate = null, ?string $endDate = null): array
    {
        $sql = "SELECT a.*, 
                c.name as class_name, sec.name as section_name
                FROM attendance a
                LEFT JOIN classes c ON a.class_id = c.id
                LEFT JOIN sections sec ON a.section_id = sec.id
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