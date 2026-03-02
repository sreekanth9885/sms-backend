<?php
class FeeStructureModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(
        int $schoolId,
        ?int $classId,
        int $feeTypeId,
        float $amount,
        string $year
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO fee_structures 
            (school_id, class_id, fee_type_id, amount, academic_year)
            VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([$schoolId, $classId, $feeTypeId, $amount, $year]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Check if a fee structure already exists
     */
    public function findByCombination(
        int $schoolId,
        ?int $classId,
        int $feeTypeId,
        string $year
    ): ?array {
        $sql = "SELECT * FROM fee_structures 
                WHERE school_id = ? 
                AND fee_type_id = ? 
                AND academic_year = ?";

        $params = [$schoolId, $feeTypeId, $year];

        // Handle class_id (can be null for global fee structures)
        if ($classId !== null) {
            $sql .= " AND class_id = ?";
            $params[] = $classId;
        } else {
            $sql .= " AND class_id IS NULL";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                fs.id,
                fs.amount,
                fs.academic_year,
                COALESCE(c.name, 'All Classes') AS class_name,
                ft.name AS fee_name
            FROM fee_structures fs
            LEFT JOIN classes c ON c.id = fs.class_id
            JOIN fee_types ft ON ft.id = fs.fee_type_id
            WHERE fs.school_id = ?
            ORDER BY fs.id DESC
        ");

        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM fee_structures WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Create multiple fee structures at once (for multi-class selection)
     */
    public function createBulk(
        int $schoolId,
        array $classIds,
        int $feeTypeId,
        float $amount,
        string $year
    ): array {
        $results = [
            'created' => [],
            'skipped' => []
        ];

        $this->db->beginTransaction();

        try {
            foreach ($classIds as $classId) {
                // Check if exists
                $existing = $this->findByCombination($schoolId, $classId, $feeTypeId, $year);

                if ($existing) {
                    $results['skipped'][] = [
                        'class_id' => $classId,
                        'existing_id' => $existing['id']
                    ];
                    continue;
                }

                // Create new
                $id = $this->create($schoolId, $classId, $feeTypeId, $amount, $year);
                $results['created'][] = [
                    'class_id' => $classId,
                    'new_id' => $id
                ];
            }

            $this->db->commit();
            return $results;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}