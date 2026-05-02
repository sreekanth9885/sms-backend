<?php

class ProgramConfigModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Replace subjects for given branch + program + class with quantity and price
     */
    public function replaceConfig(
        int $schoolId,
        int $branchId,
        int $programTypeId,
        int $classId,
        array $subjects
    ): bool {

        $this->db->beginTransaction();

        try {
            // Step 1: deactivate old records
            $stmt = $this->db->prepare("
                UPDATE store_program_config
                SET is_active = 0
                WHERE school_id = ?
                AND branch_id = ?
                AND program_type_id = ?
                AND class_id = ?
                AND is_active = 1
            ");

            $stmt->execute([$schoolId, $branchId, $programTypeId, $classId]);

            // Step 2: insert new records with quantity and price
            $stmt = $this->db->prepare("
                INSERT INTO store_program_config
                (school_id, branch_id, program_type_id, class_id, subject_id, quantity, price, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                quantity = VALUES(quantity),
                price = VALUES(price),
                is_active = 1
            ");

            foreach ($subjects as $subject) {
                $stmt->execute([
                    $schoolId,
                    $branchId,
                    $programTypeId,
                    $classId,
                    $subject['subject_id'],
                    $subject['quantity'],
                    $subject['price']
                ]);
            }

            $this->db->commit();
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get subjects with quantity and price for given config
     */
    public function getSubjectsWithDetails(
        int $schoolId,
        int $branchId,
        int $programTypeId,
        int $classId
    ): array {

        $stmt = $this->db->prepare("
            SELECT 
                s.id,
                s.name,
                pc.quantity,
                pc.price,
                (pc.quantity * pc.price) as total
            FROM store_program_config pc
            JOIN store_subjects s ON s.id = pc.subject_id
            WHERE pc.school_id = ?
            AND pc.branch_id = ?
            AND pc.program_type_id = ?
            AND pc.class_id = ?
            AND pc.is_active = 1
            AND s.is_active = 1
            ORDER BY s.name ASC
        ");

        $stmt->execute([
            $schoolId,
            $branchId,
            $programTypeId,
            $classId
        ]);

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format the response
        $formattedResults = [];
        foreach ($results as $row) {
            $formattedResults[] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'quantity' => (int)$row['quantity'],
                'price' => (float)$row['price'],
                'total' => (float)$row['total']
            ];
        }

        return $formattedResults;
    }

    /**
     * Get single subject configuration by ID
     */
    public function getSubjectConfigById(int $configId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                pc.id,
                pc.branch_id,
                pc.program_type_id,
                pc.class_id,
                pc.subject_id,
                pc.quantity,
                pc.price,
                s.name as subject_name
            FROM store_program_config pc
            JOIN store_subjects s ON s.id = pc.subject_id
            WHERE pc.id = ? AND pc.is_active = 1
        ");

        $stmt->execute([$configId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    /**
     * Update quantity and price for a specific config
     */
    public function updateConfigDetails(
        int $configId,
        int $quantity,
        float $price
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE store_program_config
            SET quantity = ?, price = ?
            WHERE id = ? AND is_active = 1
        ");

        return $stmt->execute([$quantity, $price, $configId]);
    }

    /**
     * Get all configurations for a school with pagination
     */
    public function getAllConfigs(
        int $schoolId,
        ?int $branchId = null,
        ?int $programTypeId = null,
        ?int $classId = null,
        int $limit = 100,
        int $offset = 0
    ): array {
        $sql = "
            SELECT 
                pc.id,
                pc.branch_id,
                b.name as branch_name,
                pc.program_type_id,
                pt.name as program_type_name,
                pc.class_id,
                c.name as class_name,
                pc.subject_id,
                s.name as subject_name,
                pc.quantity,
                pc.price,
                (pc.quantity * pc.price) as total
            FROM store_program_config pc
            JOIN store_branches b ON b.id = pc.branch_id
            JOIN store_program_types pt ON pt.id = pc.program_type_id
            JOIN store_classes c ON c.id = pc.class_id
            JOIN store_subjects s ON s.id = pc.subject_id
            WHERE pc.school_id = ? 
            AND pc.is_active = 1
        ";

        $params = [$schoolId];

        if ($branchId) {
            $sql .= " AND pc.branch_id = ?";
            $params[] = $branchId;
        }

        if ($programTypeId) {
            $sql .= " AND pc.program_type_id = ?";
            $params[] = $programTypeId;
        }

        if ($classId) {
            $sql .= " AND pc.class_id = ?";
            $params[] = $classId;
        }

        $sql .= " ORDER BY b.name, pt.name, c.name, s.name LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Delete (soft delete) a configuration
     */
    public function deleteConfig(int $configId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE store_program_config
            SET is_active = 0
            WHERE id = ?
        ");

        return $stmt->execute([$configId]);
    }
}