<?php

class School
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function create(array $data, int $createdBy): int
    {
        if ($this->emailExist($data['contact_email'])) {
            Response::json(["message" => "This email is already registered. Please use a different email address."], 422);
        }
        $stmt = $this->db->prepare("
            INSERT INTO schools (
              name, tagline, code, address, latitude, longitude,
              contact_name, contact_designation, contact_email,
              contact_phone_primary, contact_phone_secondary,
              board, established_date, website, logo_url, created_by
            )
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");

        $stmt->execute([
            $data['name'],
            $data['tagline'] ?? null,
            $data['code'],
            $data['address'],
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['contact_name'],
            $data['contact_designation'],
            $data['contact_email'],
            $data['contact_phone_primary'],
            $data['contact_phone_secondary'] ?? null,
            $data['board'],
            $data['established_date'],
            $data['website'] ?? null,
            $data['logo_url'] ?? null,
            $createdBy
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createAdmin(
        int $schoolId,
        string $name,
        string $email,
        string $hashedPassword
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO users (
                name,
                email,
                password,
                role,
                school_id,
                must_reset_password
            )
            VALUES (?, ?, ?, 'ADMIN', ?, 1)
        ");

        $stmt->execute([
            $name,
            $email,
            $hashedPassword,
            $schoolId
        ]);
    }
    public function all(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM schools");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to fetch schools",
                "error" => $e->getMessage()
            ], 500);
        }
    }
    private function emailExist(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM schools WHERE contact_email = ?");
        $stmt->execute([$email]);
        return $stmt->rowCount() > 0;
    }
}
