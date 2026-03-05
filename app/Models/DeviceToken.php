<?php
// models/DeviceToken.php

class DeviceToken
{
    private PDO $db;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Store or update device token
     */
    public function storeToken(int $studentId, string $token, string $platform = 'android'): bool
    {
        // First, check if token already exists
        $checkSql = "SELECT id FROM device_tokens WHERE student_id = ? AND token = ?";
        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([$studentId, $token]);
        
        if ($checkStmt->fetch()) {
            // Update existing token
            $sql = "UPDATE device_tokens 
                    SET last_used_at = NOW(), platform = ? 
                    WHERE student_id = ? AND token = ?";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$platform, $studentId, $token]);
        } else {
            // Insert new token
            $sql = "INSERT INTO device_tokens (student_id, token, platform, created_at, last_used_at) 
                    VALUES (?, ?, ?, NOW(), NOW())";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$studentId, $token, $platform]);
        }
    }
    
    /**
     * Delete specific device token
     */
    public function deleteToken(int $studentId, string $token): bool
    {
        $sql = "DELETE FROM device_tokens WHERE student_id = ? AND token = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$studentId, $token]);
    }
    
    /**
     * Delete all tokens for a student
     */
    public function deleteAllStudentTokens(int $studentId): bool
    {
        $sql = "DELETE FROM device_tokens WHERE student_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$studentId]);
    }
    
    /**
     * Get all active tokens for a student
     */
    public function getStudentTokens(int $studentId): array
    {
        $sql = "SELECT token, platform FROM device_tokens 
                WHERE student_id = ? 
                AND last_used_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$studentId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all tokens for a class/section for broadcasting
     */
    public function getClassTokens(int $classId, ?int $sectionId = null): array
    {
        $sql = "SELECT dt.token, dt.platform 
                FROM device_tokens dt
                INNER JOIN students s ON dt.student_id = s.id
                WHERE s.class_id = ? 
                AND (s.section_id = ? OR ? IS NULL)
                AND s.is_active = 1
                AND dt.last_used_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$classId, $sectionId, $sectionId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Clean up old/unused tokens
     */
    public function cleanupOldTokens(int $daysOld = 60): int
    {
        $sql = "DELETE FROM device_tokens WHERE last_used_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$daysOld]);
        
        return $stmt->rowCount();
    }
}