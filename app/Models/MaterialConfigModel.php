<?php

class MaterialConfigModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Replace full material config (smart sync)
     */
    public function saveConfig(
        int $schoolId,
        int $branchId,
        int $programTypeId,
        int $classId,
        array $materials
    ): bool {

        $this->db->beginTransaction();

        try {
            // Step 1: get existing active records
            $stmt = $this->db->prepare("
                SELECT id, material_id
                FROM store_material_config
                WHERE school_id = ?
                AND branch_id = ?
                AND program_type_id = ?
                AND class_id = ?
                AND is_active = 1
            ");

            $stmt->execute([$schoolId, $branchId, $programTypeId, $classId]);
            $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $existingMap = [];
            foreach ($existing as $row) {
                $existingMap[$row['material_id']] = $row['id'];
            }

            $incomingMaterialIds = [];

            // Step 2: insert/update
            foreach ($materials as $item) {

                $materialId = (int)$item['material_id'];
                $qty = (int)$item['quantity'];
                $price = (float)$item['price'];

                $incomingMaterialIds[] = $materialId;

                if (isset($existingMap[$materialId])) {
                    // UPDATE
                    $stmt = $this->db->prepare("
                        UPDATE store_material_config
                        SET quantity = ?, price = ?
                        WHERE id = ?
                    ");

                    $stmt->execute([
                        $qty,
                        $price,
                        $existingMap[$materialId]
                    ]);

                } else {
                    // INSERT
                    $stmt = $this->db->prepare("
                        INSERT INTO store_material_config
                        (school_id, branch_id, program_type_id, class_id, material_id, quantity, price)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");

                    $stmt->execute([
                        $schoolId,
                        $branchId,
                        $programTypeId,
                        $classId,
                        $materialId,
                        $qty,
                        $price
                    ]);
                }
            }

            // Step 3: deactivate removed materials
            foreach ($existingMap as $materialId => $id) {
                if (!in_array($materialId, $incomingMaterialIds)) {
                    $stmt = $this->db->prepare("
                        UPDATE store_material_config
                        SET is_active = 0
                        WHERE id = ?
                    ");
                    $stmt->execute([$id]);
                }
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get material config
     */
    public function getConfig(
        int $schoolId,
        int $branchId,
        int $programTypeId,
        int $classId
    ): array {

        $stmt = $this->db->prepare("
            SELECT 
                m.id as material_id,
                m.name,
                mc.quantity,
                mc.price
            FROM store_material_config mc
            JOIN store_materials m ON m.id = mc.material_id
            WHERE mc.school_id = ?
            AND mc.branch_id = ?
            AND mc.program_type_id = ?
            AND mc.class_id = ?
            AND mc.is_active = 1
            AND m.is_active = 1
        ");

        $stmt->execute([
            $schoolId,
            $branchId,
            $programTypeId,
            $classId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}