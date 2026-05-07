<?php

class FirmModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Create a new firm
     */
    public function create(int $schoolId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO firms (school_id, name, address, contact_person, contact_phone, gst_no)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $schoolId,
            $data['name'],
            $data['address'] ?? null,
            $data['contact_person'],
            $data['contact_phone'],
            $data['gst_no'] ?? null
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Get all firms for a school
     */
    public function getAll(int $schoolId, bool $onlyActive = true): array
    {
        $sql = "SELECT * FROM firms WHERE school_id = ?";
        
        if ($onlyActive) {
            $sql .= " AND is_active = 1";
        }
        
        $sql .= " ORDER BY name ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$schoolId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get firm by ID
     */
    public function getById(int $id, int $schoolId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM firms 
            WHERE id = ? AND school_id = ? AND is_active = 1
        ");
        
        $stmt->execute([$id, $schoolId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Update firm
     */
    public function update(int $id, int $schoolId, array $data): bool
    {
        $fields = [];
        $params = [];

        if (isset($data['name'])) {
            $fields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['address'])) {
            $fields[] = "address = ?";
            $params[] = $data['address'];
        }
        if (isset($data['contact_person'])) {
            $fields[] = "contact_person = ?";
            $params[] = $data['contact_person'];
        }
        if (isset($data['contact_phone'])) {
            $fields[] = "contact_phone = ?";
            $params[] = $data['contact_phone'];
        }
        if (isset($data['gst_no'])) {
            $fields[] = "gst_no = ?";
            $params[] = $data['gst_no'];
        }

        if (empty($fields)) {
            return false;
        }

        $params[] = $id;
        $params[] = $schoolId;

        $stmt = $this->db->prepare("
            UPDATE firms 
            SET " . implode(", ", $fields) . "
            WHERE id = ? AND school_id = ? AND is_active = 1
        ");

        return $stmt->execute($params);
    }

    /**
     * Delete (soft delete) firm
     */
    public function delete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE firms 
            SET is_active = 0 
            WHERE id = ? AND school_id = ? AND is_active = 1
        ");
        
        return $stmt->execute([$id, $schoolId]);
    }

    /**
     * Hard delete firm (permanently)
     */
    public function hardDelete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM firms 
            WHERE id = ? AND school_id = ?
        ");
        
        return $stmt->execute([$id, $schoolId]);
    }

    /**
     * Check if firm name exists
     */
    public function isNameExists(int $schoolId, string $name, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM firms WHERE school_id = ? AND name = ? AND is_active = 1";
        $params = [$schoolId, $name];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() > 0;
    }
}