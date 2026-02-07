<?php

class User
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByEmail(string $email)
    {
        $stmt = $this->db->prepare(
            "SELECT
            u.*,
            s.id   AS school_id,
            s.name AS school_name,
            s.code AS school_code
        FROM users u
        LEFT JOIN schools s ON s.id = u.school_id
        WHERE u.email = ? AND u.is_active = 1
        LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT id, name, email, role, is_active, created_at 
         FROM users 
         ORDER BY id DESC"
        );
        return $stmt->fetchAll();
    }
}
