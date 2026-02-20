<?php

class TeacherModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $schoolId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO teachers (
                school_id, name, gender, dob, id_number,
                blood_group, religion, email, phone, address,
                subject, qualification, photo
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
            $data['photo'] ?? null
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

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM teachers WHERE id = ?
        ");
        $stmt->execute([$id]);

        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

        return $teacher ?: null;
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE teachers SET
                name=?,
                gender=?,
                dob=?,
                id_number=?,
                blood_group=?,
                religion=?,
                email=?,
                phone=?,
                address=?,
                subject=?,
                qualification=?,
                photo=?
            WHERE id=?
        ");

        $stmt->execute([
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
            $data['photo'] ?? null,
            $id
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM teachers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
