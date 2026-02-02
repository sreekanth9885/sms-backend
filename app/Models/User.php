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
            "SELECT * FROM users WHERE email = ? AND is_active = 1"
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
