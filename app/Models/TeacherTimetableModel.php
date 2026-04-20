<?php
class TeacherTimetableModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function checkConflicts($schoolId, $classId, $teacherId, $startDate, $endDate, $startTime, $endTime)
    {
        $sql = "
            SELECT * FROM teacher_timetables
            WHERE school_id = ?
            AND is_active = 1
            AND (
                (class_id = ? OR teacher_id = ?)
                AND (
                    start_time < ? AND end_time > ?
                )
                AND (
                    start_date <= ? 
                    AND (end_date IS NULL OR end_date >= ?)
                )
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $schoolId,
            $classId,
            $teacherId,
            $endTime,
            $startTime,
            $endDate ?? $startDate,
            $startDate
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data)
    {
        $stmt = $this->db->prepare("
            INSERT INTO teacher_timetables 
            (school_id, class_id, subject_id, teacher_id, academic_year, start_date, end_date, start_time, end_time)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['school_id'],
            $data['class_id'],
            $data['subject_id'],
            $data['teacher_id'],
            $data['academic_year'],
            $data['start_date'],
            $data['end_date'],
            $data['start_time'],
            $data['end_time']
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function deactivateConflicts($schoolId, $classId, $teacherId)
    {
        $stmt = $this->db->prepare("
            UPDATE teacher_timetables 
            SET is_active = 0
            WHERE school_id = ?
            AND (class_id = ? OR teacher_id = ?)
        ");

        $stmt->execute([$schoolId, $classId, $teacherId]);
    }
}