<?php
class CategoryModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $schoolId, string $name): int
    {
        if ($this->exists($schoolId, $name)) {
            throw new Exception("Category already exists");
        }

        $stmt = $this->db->prepare("
            INSERT INTO categories (school_id, name)
            VALUES (?, ?)
        ");

        $stmt->execute([$schoolId, $name]);

        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, int $schoolId, string $name): bool
    {
        if ($this->exists($schoolId, $name, $id)) {
            throw new Exception("Duplicate category");
        }

        $stmt = $this->db->prepare("
            UPDATE categories SET name=?
            WHERE id=? AND school_id=?
        ");

        $stmt->execute([$name, $id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $schoolId): bool
    {
        try {

            $this->db->beginTransaction();

            // =====================================
            // CHECK PRODUCTS EXIST
            // =====================================

            $productStmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM products
            WHERE category_id = ?
              AND school_id = ?
              AND is_active = 1
        ");

            $productStmt->execute([
                $id,
                $schoolId
            ]);

            $productCount = $productStmt->fetch(PDO::FETCH_ASSOC);

            if ($productCount['total'] > 0) {

                throw new Exception(
                    "Cannot delete category. Products are linked."
                );
            }

            // =====================================
            // SOFT DELETE CATEGORY
            // =====================================

            $categoryStmt = $this->db->prepare("
            UPDATE categories
            SET is_active = 0
            WHERE id = ?
              AND school_id = ?
        ");

            $categoryStmt->execute([
                $id,
                $schoolId
            ]);

            // =====================================
            // SOFT DELETE SUB CATEGORIES
            // =====================================

            $subStmt = $this->db->prepare("
            UPDATE sub_categories
            SET is_active = 0
            WHERE category_id = ?
              AND school_id = ?
        ");

            $subStmt->execute([
                $id,
                $schoolId
            ]);

            $this->db->commit();

            return $categoryStmt->rowCount() > 0;
        } catch (Exception $e) {

            $this->db->rollBack();

            throw $e;
        }
    }

    public function all(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name FROM categories
            WHERE school_id=? AND is_active=1
            ORDER BY id DESC
        ");

        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function exists(int $schoolId, string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM categories WHERE school_id=? AND name=? AND is_active=1";

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