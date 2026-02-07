
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
            SELECT 
                c.id   AS class_id,
                c.name AS class_name,
                s.id   AS section_id,
                s.name AS section_name
            FROM classes c
            LEFT JOIN sections s ON s.class_id = c.id
            WHERE c.school_id = ?
            ORDER BY c.id, s.name
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$schoolId]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->mapClassesWithSections($rows);
    }
    private function mapClassesWithSections(array $rows): array
    {
        $classes = [];

        foreach ($rows as $row) {
            $classId = (int)$row['class_id'];

            if (!isset($classes[$classId])) {
                $classes[$classId] = [
                    "id" => $classId,
                    "name" => $row['class_name'],
                    "sections" => []
                ];
            }

            if ($row['section_id']) {
                $classes[$classId]['sections'][] = [
                    "id" => (int)$row['section_id'],
                    "name" => $row['section_name']
                ];
            }
        }

        return array_values($classes);
    }
}
?>