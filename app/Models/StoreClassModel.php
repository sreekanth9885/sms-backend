<?php

class StoreClassModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $schoolId, string $name): int
    {
        if ($this->exists($schoolId, $name)) {
            throw new Exception("Class already exists");
        }

        $stmt = $this->db->prepare("
            INSERT INTO store_classes (school_id, name)
            VALUES (?, ?)
        ");

        $stmt->execute([$schoolId, $name]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $schoolId, string $name): bool
    {
        if ($this->exists($schoolId, $name, $id)) {
            throw new Exception("Duplicate class name");
        }

        $stmt = $this->db->prepare("
            UPDATE store_classes
            SET name = ?
            WHERE id = ? AND school_id = ? AND is_active = 1
        ");

        $stmt->execute([$name, $id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE store_classes
            SET is_active = 0
            WHERE id = ? AND school_id = ? AND is_active = 1
        ");

        $stmt->execute([$id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name
FROM store_classes
WHERE school_id = ? AND is_active = 1
ORDER BY CAST(SUBSTRING_INDEX(name, ' ', 1) AS UNSIGNED);
        ");

        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function exists(int $schoolId, string $name, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT id FROM store_classes
            WHERE school_id = ?
            AND name = ?
            AND is_active = 1
        ";

        if ($excludeId) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->db->prepare($sql);

        $params = [$schoolId, $name];
        if ($excludeId) {
            $params[] = $excludeId;
        }

        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}