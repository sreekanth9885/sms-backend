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

    public function allBySchool(int $schoolId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                st.id,
                st.admission_number,
                st.roll_number,
                CONCAT(st.first_name,' ',st.last_name) AS student_name,
                st.gender,
                c.name AS class_name,
                s.name AS section_name,
                st.is_active
            FROM students st
            JOIN classes c ON c.id = st.class_id
            JOIN sections s ON s.id = st.section_id
            WHERE st.school_id = ?
            ORDER BY st.id DESC
        ");

        $stmt->execute([$schoolId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
