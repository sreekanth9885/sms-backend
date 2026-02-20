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

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                fs.id,
                fs.amount,
                fs.academic_year,
                c.name AS class_name,
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
}
