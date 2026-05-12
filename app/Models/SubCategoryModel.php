<?php
class SubCategoryModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $schoolId, int $categoryId, string $name): int
    {
        if ($this->exists($schoolId, $categoryId, $name)) {
            throw new Exception("Subcategory already exists");
        }

        $stmt = $this->db->prepare("
            INSERT INTO sub_categories (school_id, category_id, name)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([$schoolId, $categoryId, $name]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $schoolId, int $categoryId, string $name): bool
    {
        if ($this->exists($schoolId, $categoryId, $name, $id)) {
            throw new Exception("Duplicate subcategory");
        }

        $stmt = $this->db->prepare("
            UPDATE sub_categories
            SET category_id=?, name=?
            WHERE id=? AND school_id=?
        ");

        $stmt->execute([$categoryId, $name, $id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE sub_categories 
        SET is_active = 0 
        WHERE id = ? AND school_id = ?
    ");

        $stmt->execute([$id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function all(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT sc.id, sc.name, sc.category_id, c.name AS category_name
            FROM sub_categories sc
            JOIN categories c ON c.id = sc.category_id
            WHERE sc.school_id=? AND sc.is_active=1 AND c.is_active=1
            ORDER BY sc.id DESC
        ");

        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function exists(int $schoolId, int $categoryId, string $name, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT id FROM sub_categories
            WHERE school_id=? AND category_id=? AND name=? AND is_active=1
        ";

        if ($excludeId) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->db->prepare($sql);

        $params = [$schoolId, $categoryId, $name];
        if ($excludeId) {
            $params[] = $excludeId;
        }

        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}