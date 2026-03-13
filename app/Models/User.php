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
                s.id AS school_id,
                s.name AS school_name,
                s.tagline AS school_tagline,
                s.code AS school_code,
                s.address AS school_address,
                s.latitude AS school_latitude,
                s.longitude AS school_longitude,
                s.contact_name AS school_contact_name,
                s.contact_designation AS school_contact_designation,
                s.contact_email AS school_contact_email,
                s.contact_phone_primary AS school_contact_phone_primary,
                s.contact_phone_secondary AS school_contact_phone_secondary,
                s.board AS school_board,
                s.established_date AS school_established_date,
                s.website AS school_website,
                s.logo_url AS school_logo_url,
                s.is_active AS school_is_active,
                s.created_by AS school_created_by,
                s.created_at AS school_created_at,
                s.updated_at AS school_updated_at
            FROM users u
            LEFT JOIN schools s ON s.id = u.school_id
            WHERE u.email = ? AND u.is_active = 1
            LIMIT 1"
        );
        $stmt->execute([$email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findById(int $id)
    {
        $stmt = $this->db->prepare(
            "SELECT
                u.*,
                s.id AS school_id,
                s.name AS school_name,
                s.tagline AS school_tagline,
                s.code AS school_code,
                s.address AS school_address,
                s.latitude AS school_latitude,
                s.longitude AS school_longitude,
                s.contact_name AS school_contact_name,
                s.contact_designation AS school_contact_designation,
                s.contact_email AS school_contact_email,
                s.contact_phone_primary AS school_contact_phone_primary,
                s.contact_phone_secondary AS school_contact_phone_secondary,
                s.board AS school_board,
                s.established_date AS school_established_date,
                s.website AS school_website,
                s.logo_url AS school_logo_url,
                s.is_active AS school_is_active,
                s.created_by AS school_created_by,
                s.created_at AS school_created_at,
                s.updated_at AS school_updated_at
            FROM users u
            LEFT JOIN schools s ON s.id = u.school_id
            WHERE u.id = ? AND u.is_active = 1
            LIMIT 1"
        );
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all(): array
    {
        $stmt = $this->db->query(
            "SELECT id, name, email, role, is_active, created_at 
         FROM users 
         ORDER BY id DESC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}