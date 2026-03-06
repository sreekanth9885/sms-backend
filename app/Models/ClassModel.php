<?php
class ClassModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $schoolId, string $name): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO classes (school_id, name)
            VALUES (?, ?)
        ");
        $stmt->execute([$schoolId, $name]);

        return (int)$this->db->lastInsertId();
    }

    public function allBySchool(int $schoolId): array
    {
        $sql = "
        WITH class_student_counts AS (
            SELECT 
                class_id,
                COUNT(*) as total_students
            FROM students
            WHERE school_id = ? 
                AND is_active = 1
            GROUP BY class_id
        )
        SELECT 
            c.id   AS class_id,
            c.name AS class_name,
            s.id   AS section_id,
            s.name AS section_name,
            COALESCE(csc.total_students, 0) AS total_students,
            (
                SELECT COUNT(*) 
                FROM students st 
                WHERE st.class_id = c.id 
                AND st.section_id = s.id
                AND st.school_id = ? 
                AND st.is_active = 1
            ) AS section_students
        FROM classes c
        LEFT JOIN sections s ON s.class_id = c.id
        LEFT JOIN class_student_counts csc ON csc.class_id = c.id
        WHERE c.school_id = ?
        ORDER BY c.id, s.name
    ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$schoolId, $schoolId, $schoolId]); // school_id for CTE, section students, and WHERE clause

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->mapClassesWithSections($rows);
    }
    private function mapClassesWithSections(array $rows): array
    {
        $classes = [];

        foreach ($rows as $row) {
            $classId = $row['class_id'];

            if (!isset($classes[$classId])) {
                $classes[$classId] = [
                    'id' => $classId,
                    'name' => $row['class_name'],
                    'total_students' => (int)$row['total_students'],
                    'sections' => []
                ];
            }

            if ($row['section_id']) {
                $classes[$classId]['sections'][] = [
                    'id' => $row['section_id'],
                    'name' => $row['section_name'],
                    'total_students' => (int)$row['section_students']
                ];
            }
        }

        return array_values($classes);
    }

    public function delete(int $id, int $schoolId): bool
    {
        // Make sure the class belongs to the school before deleting
        $stmt = $this->db->prepare("DELETE FROM classes WHERE id = ? AND school_id = ?");
        $stmt->execute([$id, $schoolId]);
        return $stmt->rowCount() > 0;
    }
}