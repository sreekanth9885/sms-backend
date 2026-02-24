<?php

class TeacherModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $schoolId, array $data, ?string $photoUrl = null): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO teachers (
                school_id, name, gender, dob, id_number,
                blood_group, religion, email, phone, address,
                subject, qualification, experience, photo
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $schoolId,
            $data['name'],
            $data['gender'],
            $data['dob'],
            $data['id_number'],
            $data['blood_group'] ?? null,
            $data['religion'] ?? null,
            $data['email'] ?? null,
            $data['phone'],
            $data['address'] ?? null,
            $data['subject'],
            $data['qualification'] ?? null,
            $data['experience'] ?? null,
            $photoUrl
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM teachers
            WHERE school_id = ?
            ORDER BY id DESC
        ");

        $stmt->execute([$schoolId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, int $schoolId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM teachers 
            WHERE id = ? AND school_id = ?
        ");
        $stmt->execute([$id, $schoolId]);

        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        return $teacher ?: null;
    }

    public function update(int $id, int $schoolId, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $fields = [];
        $values = [];

        // Build SET clause dynamically
        foreach ($data as $key => $value) {
            // Only include fields that exist in the table
            if ($value !== null && in_array($key, [
                'name',
                'gender',
                'dob',
                'id_number',
                'blood_group',
                'religion',
                'email',
                'phone',
                'address',
                'subject',
                'qualification',
                'experience',
                'photo'
            ])) {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        // Add WHERE conditions
        $values[] = $id;
        $values[] = $schoolId;

        $sql = "UPDATE teachers SET " . implode(', ', $fields) . " WHERE id = ? AND school_id = ?";

        error_log('Update SQL: ' . $sql);
        error_log('Update values: ' . print_r($values, true));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM teachers WHERE id = ? AND school_id = ?");
        $stmt->execute([$id, $schoolId]);
        return $stmt->rowCount() > 0;
    }
}