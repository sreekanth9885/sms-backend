<?php

class SubjectModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    // ✅ Filter using master_class_id
    public function getAll(?int $classId = null): array
    {
        if ($classId) {
            $stmt = $this->db->prepare("
                SELECT 
                    sid,
                    subname,
                    pri,
                    fa,
                    sa
                FROM submaster
                WHERE master_class_id = ?
                ORDER BY pri ASC
            ");
            $stmt->execute([$classId]);
        } else {
            $stmt = $this->db->query("
                SELECT 
                    sid,
                    subname,
                    pri,
                    fa,
                    sa
                FROM submaster
                ORDER BY master_class_id, pri ASC
            ");
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function getByClassId(?int $classId = null): array
{
    if (!$classId) return [];

    $stmt = $this->db->prepare("
        SELECT 
            sm.sid,
            sm.subname,
            sm.pri,
            sm.fa,
            sm.sa
        FROM submaster sm
        INNER JOIN classes c 
            ON c.master_class_id = sm.master_class_id
        WHERE c.id = ?
        ORDER BY sm.pri ASC
    ");

    $stmt->execute([$classId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}