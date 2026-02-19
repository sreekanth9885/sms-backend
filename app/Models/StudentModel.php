<?php

class StudentModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): int
    {
        $sql = "
            INSERT INTO students (
                school_id,
                class_id,
                section_id,
                admission_number,
                roll_number,
                date_of_admission,
                first_name,
                last_name,
                gender,
                dob,
                aadhaar,
                blood_group,
                religion,
                caste,
                sub_caste,
                pen,
                father_name,
                mother_name,
                parent_phone,
                alternate_phone,
                parent_email,
                village,
                district,
                state,
                country,
                pincode,
                complete_address,
                identification_mark_1,
                identification_mark_2,
                photo_url,
                created_by
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            $data['school_id'],
            $data['class_id'],
            $data['section_id'],
            $data['admission_number'],
            $data['roll_number'] ?? null,
            $data['date_of_admission'] ?? null,
            $data['first_name'],
            $data['last_name'] ?? null,
            $data['gender'] ?? null,
            $data['dob'] ?? null,
            $data['aadhaar'] ?? null,
            $data['blood_group'] ?? null,
            $data['religion'] ?? null,
            $data['caste'] ?? null,
            $data['sub_caste'] ?? null,
            $data['pen'] ?? null,
            $data['father_name'] ?? null,
            $data['mother_name'] ?? null,
            $data['parent_phone'] ?? null,
            $data['alternate_phone'] ?? null,
            $data['parent_email'] ?? null,
            $data['village'] ?? null,
            $data['district'] ?? null,
            $data['state'] ?? null,
            $data['country'] ?? null,
            $data['pincode'] ?? null,
            $data['complete_address'] ?? null,
            $data['identification_mark_1'] ?? null,
            $data['identification_mark_2'] ?? null,
            $data['photo_url'] ?? null,
            $data['created_by']
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function allBySchool(int $schoolId, array $filters = []): array
    {
        $sql = "
        SELECT
    st.*,
    CONCAT(st.first_name,' ',st.last_name) AS student_name,
    c.name AS class_name,
    s.name AS section_name
    FROM students st
    JOIN classes c ON c.id = st.class_id
    JOIN sections s ON s.id = st.section_id
    WHERE st.school_id = ?
    AND st.is_active = 1;

    ";

        $params = [$schoolId];

        if (!empty($filters['class_id'])) {
            $sql .= " AND st.class_id = ?";
            $params[] = $filters['class_id'];
        }

        if (!empty($filters['section_id'])) {
            $sql .= " AND st.section_id = ?";
            $params[] = $filters['section_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= " AND (
            st.first_name LIKE ?
            OR st.last_name LIKE ?
            OR st.admission_number LIKE ?
            OR st.roll_number LIKE ?
        )";
            $search = "%" . $filters['search'] . "%";
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }

        $sql .= " ORDER BY st.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function findById(int $id, int $schoolId): ?array
    {
        $stmt = $this->db->prepare("
        SELECT *
        FROM students
        WHERE id = ? AND school_id = ?
    ");

        $stmt->execute([$id, $schoolId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        return $student ?: null;
    }
    public function update(int $id, int $schoolId, array $data): bool
    {
        $sql = "
        UPDATE students SET
            class_id = ?,
            section_id = ?,
            admission_number = ?,
            roll_number = ?,
            date_of_admission = ?,
            first_name = ?,
            last_name = ?,
            gender = ?,
            dob = ?,
            aadhaar = ?,
            blood_group = ?,
            religion = ?,
            caste = ?,
            sub_caste = ?,
            pen = ?,
            father_name = ?,
            mother_name = ?,
            parent_phone = ?,
            alternate_phone = ?,
            parent_email = ?,
            village = ?,
            district = ?,
            state = ?,
            country = ?,
            pincode = ?,
            complete_address = ?,
            identification_mark_1 = ?,
            identification_mark_2 = ?,
            photo_url = ?
        WHERE id = ? AND school_id = ?
    ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $data['class_id'],
            $data['section_id'],
            $data['admission_number'],
            $data['roll_number'] ?? null,
            $data['date_of_admission'] ?? null,
            $data['first_name'],
            $data['last_name'] ?? null,
            $data['gender'] ?? null,
            $data['dob'] ?? null,
            $data['aadhaar'] ?? null,
            $data['blood_group'] ?? null,
            $data['religion'] ?? null,
            $data['caste'] ?? null,
            $data['sub_caste'] ?? null,
            $data['pen'] ?? null,
            $data['father_name'] ?? null,
            $data['mother_name'] ?? null,
            $data['parent_phone'] ?? null,
            $data['alternate_phone'] ?? null,
            $data['parent_email'] ?? null,
            $data['village'] ?? null,
            $data['district'] ?? null,
            $data['state'] ?? null,
            $data['country'] ?? null,
            $data['pincode'] ?? null,
            $data['complete_address'] ?? null,
            $data['identification_mark_1'] ?? null,
            $data['identification_mark_2'] ?? null,
            $data['photo_url'] ?? null,
            $id,
            $schoolId
        ]);
    }
    public function delete(int $id, int $schoolId): bool
    {
        $stmt = $this->db->prepare("
        UPDATE students
        SET is_active = 0
        WHERE id = ? AND school_id = ?
    ");

        return $stmt->execute([$id, $schoolId]);
    }
}
