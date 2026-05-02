<?php

class MaterialModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $schoolId, string $name, string $type): int
    {
        if ($this->exists($schoolId, $name, $type)) {
            throw new Exception("Material already exists");
        }

        $stmt = $this->db->prepare("
            INSERT INTO store_materials (school_id, name, type)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([$schoolId, $name, $type]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $schoolId, string $name, string $type): bool
    {
        if ($this->exists($schoolId, $name, $type, $id)) {
            throw new Exception("Duplicate material");
        }

        $stmt = $this->db->prepare("
            UPDATE store_materials
            SET name = ?, type = ?
            WHERE id = ? AND school_id = ?
        ");

        $stmt->execute([$name, $type, $id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE store_materials
            SET is_active = 0
            WHERE id = ? AND school_id = ?
        ");

        $stmt->execute([$id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, type
            FROM store_materials
            WHERE school_id = ? AND is_active = 1
            ORDER BY id DESC
        ");

        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function exists(
        int $schoolId,
        string $name,
        string $type,
        ?int $excludeId = null
    ): bool {

        $sql = "
            SELECT id FROM store_materials
            WHERE school_id = ?
            AND name = ?
            AND type = ?
            AND is_active = 1
        ";

        if ($excludeId) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->db->prepare($sql);

        $params = [$schoolId, $name, $type];
        if ($excludeId) {
            $params[] = $excludeId;
        }

        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}