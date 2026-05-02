<?php

class ProgramConfigModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Replace subjects for given branch + program + class
     */
    public function replaceConfig(
        int $schoolId,
        int $branchId,
        int $programTypeId,
        int $classId,
        array $subjectIds
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

            // Step 2: insert new records
            $stmt = $this->db->prepare("
                INSERT INTO store_program_config
                (school_id, branch_id, program_type_id, class_id, subject_id)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($subjectIds as $subjectId) {
                $stmt->execute([
                    $schoolId,
                    $branchId,
                    $programTypeId,
                    $classId,
                    $subjectId
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
     * Get subjects for given config
     */
    public function getSubjects(
        int $schoolId,
        int $branchId,
        int $programTypeId,
        int $classId
    ): array {

        $stmt = $this->db->prepare("
            SELECT s.id, s.name
            FROM store_program_config pc
            JOIN store_subjects s ON s.id = pc.subject_id
            WHERE pc.school_id = ?
            AND pc.branch_id = ?
            AND pc.program_type_id = ?
            AND pc.class_id = ?
            AND pc.is_active = 1
            AND s.is_active = 1
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