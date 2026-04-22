<?php

class AgencyModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(
        int $schoolId,
        string $name,
        string $phone,
        ?string $gst,
        string $contactPerson,
        ?string $address
    ): int {

        // Duplicate check
        if ($this->exists($schoolId, $phone, $gst)) {
            throw new Exception("Agency with same phone or GST already exists");
        }

        $stmt = $this->db->prepare("
            INSERT INTO agencies (school_id, name, phone, gst_number, contact_person, address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $schoolId,
            $name,
            $phone,
            $gst,
            $contactPerson,
            $address
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function update(
        int $id,
        int $schoolId,
        string $name,
        string $phone,
        ?string $gst,
        string $contactPerson,
        ?string $address
    ): bool {

        if ($this->exists($schoolId, $phone, $gst, $id)) {
            throw new Exception("Duplicate phone or GST found");
        }

        $stmt = $this->db->prepare("
            UPDATE agencies
            SET name=?, phone=?, gst_number=?, contact_person=?, address=?
            WHERE id=? AND school_id=?
        ");

        $stmt->execute([
            $name,
            $phone,
            $gst,
            $contactPerson,
            $address,
            $id,
            $schoolId
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM agencies WHERE id=? AND school_id=?
        ");
        $stmt->execute([$id, $schoolId]);

        return $stmt->rowCount() > 0;
    }

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, phone, gst_number, contact_person, address
            FROM agencies
            WHERE school_id=? AND is_active=1
            ORDER BY id DESC
        ");

        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function exists(int $schoolId, string $phone, ?string $gst, ?int $excludeId = null): bool
    {
        $sql = "
            SELECT id FROM agencies 
            WHERE school_id = ? 
            AND (phone = ? OR gst_number = ?)
        ";

        if ($excludeId) {
            $sql .= " AND id != ?";
        }

        $stmt = $this->db->prepare($sql);

        $params = [$schoolId, $phone, $gst];
        if ($excludeId) {
            $params[] = $excludeId;
        }

        $stmt->execute($params);

        return (bool)$stmt->fetch();
    }
}