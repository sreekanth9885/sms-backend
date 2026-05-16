<?php
class ProductModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(
        int $schoolId,
        int $categoryId,
        int $subCategoryId,
        string $name,
        int $quantity,
        string $unit
    ): int {

        if ($this->exists($schoolId, $subCategoryId, $name)) {
            throw new Exception("Product already exists");
        }

        $stmt = $this->db->prepare("
            INSERT INTO products (school_id, category_id, sub_category_id, name, quantity, unit)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $schoolId,
            $categoryId,
            $subCategoryId,
            $name,
            $quantity,
            $unit
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(
        int $id,
        int $schoolId,
        int $categoryId,
        int $subCategoryId,
        string $name,
        int $quantity,
        string $unit
    ): bool {

        if ($this->exists($schoolId, $subCategoryId, $name, $id)) {
            throw new Exception("Duplicate product");
        }

        $stmt = $this->db->prepare("
            UPDATE products
            SET category_id=?, sub_category_id=?, name=?, quantity=?, unit=?
            WHERE id=? AND school_id=?
        ");

        $stmt->execute([
            $categoryId,
            $subCategoryId,
            $name,
            $quantity,
            $unit,
            $id,
            $schoolId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE products 
        SET is_active = 0 
        WHERE id = ? AND school_id = ?
    ");

        $stmt->execute([$id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function all(
        int $schoolId,
        int $page = 1,
        int $limit = 20,
        string $search = '',
        ?int $categoryId = null,
        ?int $subCategoryId = null
    ): array {

        $offset = ($page - 1) * $limit;

        $where = "
        WHERE p.school_id = :school_id
        AND p.is_active = 1
        AND c.is_active = 1
        AND sc.is_active = 1
    ";

        $params = [
            ':school_id' => $schoolId
        ];

        // SEARCH
        if (!empty($search)) {
            $where .= "
            AND (
                p.name LIKE :search
                OR c.name LIKE :search
                OR sc.name LIKE :search
            )
        ";

            $params[':search'] = "%{$search}%";
        }

        // CATEGORY FILTER
        if (!empty($categoryId)) {
            $where .= " AND p.category_id = :category_id ";
            $params[':category_id'] = $categoryId;
        }

        // SUB CATEGORY FILTER
        if (!empty($subCategoryId)) {
            $where .= " AND p.sub_category_id = :sub_category_id ";
            $params[':sub_category_id'] = $subCategoryId;
        }

        // TOTAL COUNT QUERY
        $countSql = "
        SELECT COUNT(*) as total
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN sub_categories sc ON sc.id = p.sub_category_id
        $where
    ";

        $countStmt = $this->db->prepare($countSql);

        foreach ($params as $key => $value) {
            $countStmt->bindValue($key, $value);
        }

        $countStmt->execute();

        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        // DATA QUERY
        $sql = "
        SELECT 
            p.id,
            p.name,
            p.quantity,
            p.unit,
            p.category_id,
            p.sub_category_id,
            c.name AS category_name,
            sc.name AS sub_category_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN sub_categories sc ON sc.id = p.sub_category_id
        $where
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

        $stmt->execute();

        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            "data" => $data,
            "pagination" => [
                "total" => $total,
                "page" => $page,
                "limit" => $limit,
                "total_pages" => ceil($total / $limit)
            ]
        ];
    }

    private function exists(
        int $schoolId,
        int $subCategoryId,
        string $name,
        ?int $excludeId = null
    ): bool {

        $sql = "
        SELECT id FROM products
        WHERE school_id=? 
        AND sub_category_id=? 
        AND name=? 
        AND is_active=1
    ";

        if ($excludeId) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->db->prepare($sql);

        $params = [$schoolId, $subCategoryId, $name];
        if ($excludeId) {
            $params[] = $excludeId;
        }

        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
    public function searchProducts(
        int $schoolId,
        string $search
    ): array {

        $stmt = $this->db->prepare("
        SELECT
            p.id,
            p.name,
            p.unit,
            c.name AS category_name,
            sc.name AS sub_category_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        JOIN sub_categories sc ON sc.id = p.sub_category_id
        WHERE p.school_id = ?
        AND p.is_active = 1
        AND (
            p.name LIKE ?
            OR c.name LIKE ?
            OR sc.name LIKE ?
        )
        ORDER BY p.name ASC
        LIMIT 20
    ");

        $searchTerm = "%{$search}%";

        $stmt->execute([
            $schoolId,
            $searchTerm,
            $searchTerm,
            $searchTerm
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}