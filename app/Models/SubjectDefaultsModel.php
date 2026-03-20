<?php
// models/SubjectDefaultsModel.php

class SubjectDefaultsModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get all default subjects
     */
    public function getAll(): array
    {
        $sql = "SELECT id, name FROM subjects ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a default subject by ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT id, name FROM subjects WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Search default subjects by name
     */
    public function search(string $keyword): array
    {
        $sql = "SELECT id, name FROM subjects WHERE name LIKE :keyword ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':keyword' => '%' . $keyword . '%']);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}