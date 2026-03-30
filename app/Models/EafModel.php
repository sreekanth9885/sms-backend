
<?php
class EafModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function getByClass(int $classId, ?int $sectionId = null): array
    {
        $sql = "
            SELECT 
                e.*,
                s.first_name,
                s.last_name,
                s.admission_number,
                s.class_id,
                s.section_id,
                c.name as class_name
            FROM eaf e
            JOIN students s ON e.student_id = s.id
            JOIN classes c ON e.class_id = c.id
            WHERE e.class_id = :class_id
        ";

        $params = ['class_id' => $classId];

        if ($sectionId) {
            $sql .= " AND s.section_id = :section_id";
            $params['section_id'] = $sectionId;
        }

        $sql .= " ORDER BY s.id, e.subject_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}