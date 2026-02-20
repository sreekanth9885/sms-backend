<?php
class FeeTypeModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $schoolId, string $name, ?string $description, bool $optional): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO fee_types (school_id, name, description, is_optional)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$schoolId, $name, $description, $optional]);

        return (int)$this->db->lastInsertId();
    }

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, is_optional
            FROM fee_types
            WHERE school_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM fee_types WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
