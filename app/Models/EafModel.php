<?php

class EafModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function bulkInsert(int $classId, array $students, array $subjects): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO eaf (
                student_id,
                class_id,
                subject_id,
                roll_no,
                subject_name,
                fa1max,
                sa1max
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                subject_name = VALUES(subject_name),
                fa1max = VALUES(fa1max),
                sa1max = VALUES(sa1max)
        ");

        foreach ($students as $student) {
            foreach ($subjects as $subject) {

                $stmt->execute([
                    $student['id'],
                    $classId,
                    $subject['sid'],
                    $student['roll_number'] ?? null,
                    $subject['subname'],
                    $subject['fa'],
                    $subject['sa']
                ]);
            }
        }

        return true;
    }
}