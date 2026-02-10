<?php
class SectionModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $classId, string $name): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO sections (class_id, name)
            VALUES (?, ?)
        ");
        $stmt->execute([$classId, $name]);

        return (int)$this->db->lastInsertId();
    }

    public function allByClass(int $classId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM sections
            WHERE class_id = ? AND is_active = 1
            ORDER BY name
        ");
        $stmt->execute([$classId]);
        return $stmt->fetchAll();
    }
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM sections WHERE id = ?"
        );
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }
}
?>