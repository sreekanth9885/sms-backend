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
                AND (start_time < ? AND end_time > ?)
                AND (start_date <= ? AND (end_date IS NULL OR end_date >= ?))
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

    public function checkConflictsForUpdate($schoolId, $id, $classId, $teacherId, $startDate, $endDate, $startTime, $endTime)
    {
        $sql = "
            SELECT * FROM teacher_timetables
            WHERE school_id = ?
            AND is_active = 1
            AND id != ?
            AND (
                (class_id = ? OR teacher_id = ?)
                AND (start_time < ? AND end_time > ?)
                AND (start_date <= ? AND (end_date IS NULL OR end_date >= ?))
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $schoolId,
            $id,
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

    public function update($id, $data)
    {
        $stmt = $this->db->prepare("
            UPDATE teacher_timetables
            SET class_id = ?, subject_id = ?, teacher_id = ?, academic_year = ?, start_date = ?, end_date = ?, start_time = ?, end_time = ?
            WHERE id = ? AND school_id = ?
        ");

        $stmt->execute([
            $data['class_id'],
            $data['subject_id'],
            $data['teacher_id'],
            $data['academic_year'],
            $data['start_date'],
            $data['end_date'],
            $data['start_time'],
            $data['end_time'],
            $id,
            $data['school_id']
        ]);

        return $stmt->rowCount() > 0;
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

    public function getTimetable($schoolId, $classId = null, $teacherId = null, $date = null)
    {
        $sql = "
        SELECT
            tt.id,
            tt.class_id,
            c.name AS class_name,
            tt.teacher_id,
            t.name AS teacher_name,
            t.phone AS teacher_phone,
            t.email AS teacher_email,
            t.subject AS teacher_subject,
            tt.subject_id,
            sm.subname AS subject_name,
            tt.start_date,
            tt.end_date,
            tt.start_time,
            tt.end_time,
            tt.academic_year,
            tt.is_active
        FROM teacher_timetables tt
        LEFT JOIN classes c
            ON c.id = tt.class_id
            AND c.school_id = tt.school_id
        LEFT JOIN teachers t
            ON t.id = tt.teacher_id
            AND t.school_id = tt.school_id
        LEFT JOIN submaster sm
            ON sm.sid = tt.subject_id
            AND sm.master_class_id = c.master_class_id
        WHERE tt.school_id = ?
        AND tt.is_active = 1
    ";

        $params = [$schoolId];

        if (!empty($classId)) {
            $sql .= " AND tt.class_id = ?";
            $params[] = $classId;
        }

        if (!empty($teacherId)) {
            $sql .= " AND tt.teacher_id = ?";
            $params[] = $teacherId;
        }

        if (!empty($date)) {
            $sql .= "
            AND tt.start_date <= ?
            AND (tt.end_date IS NULL OR tt.end_date >= ?)
        ";
            $params[] = $date;
            $params[] = $date;
        }

        $sql .= " ORDER BY tt.start_time ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
