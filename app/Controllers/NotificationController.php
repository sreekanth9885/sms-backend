<?php
// controllers/NotificationController.php

require_once __DIR__ . '/../services/FirebaseNotificationService.php';
require_once __DIR__ . '/../Core/Response.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class NotificationController
{
    private $db;
    private $firebaseService;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->firebaseService = new FirebaseNotificationService();
    }
    
    /**
     * Send notification to specific students
     * POST /api/notifications/send
     */
    public function sendNotification()
    {
        // Verify admin/school permissions
        $user = JwtHelper::getUserFromToken();

        $data = json_decode(file_get_contents("php://input"), true);

        $recipientType = $data['recipient_type'] ?? 'single';
        $studentIds = $data['student_ids'] ?? [];
        $classId = $data['class_id'] ?? null;
        $sectionId = $data['section_id'] ?? null;
        $title = $data['title'] ?? null;
        $body = $data['body'] ?? null;
        $dataPayload = $data['data'] ?? [];

        if (!$title || !$body) {
            Response::json(["message" => "Title and body are required"], 400);
        }

        // Add school context for security
        $schoolId = $user['school_id'] ?? null;

        $result = ['success' => false, 'sent' => 0, 'failed' => 0];

        switch ($recipientType) {
            case 'single':
                if (empty($studentIds)) {
                    Response::json(["message" => "Student IDs required"], 400);
                }
                // Handle single student (first ID)
                $result = $this->sendToStudents([$studentIds[0]], $title, $body, $dataPayload);
                break;

            case 'multiple': // Add this case
                if (empty($studentIds)) {
                    Response::json(["message" => "Student IDs required"], 400);
                }
                $result = $this->sendToStudents($studentIds, $title, $body, $dataPayload);
                break;

            case 'class':
                if (!$classId) {
                    Response::json(["message" => "Class ID required"], 400);
                }
                $result = $this->sendToClass($classId, $title, $body, $dataPayload);
                break;

            case 'section':
                if (!$classId || !$sectionId) {
                    Response::json(["message" => "Class ID and Section ID required"], 400);
                }
                $result = $this->sendToSection($classId, $sectionId, $title, $body, $dataPayload);
                break;

            case 'all':
                $result = $this->sendToAllSchoolStudents($schoolId, $title, $body, $dataPayload);
                break;

            default:
                Response::json(["message" => "Invalid recipient type"], 400);
        }

        // Log notification for history
        $this->logNotification([
            'school_id' => $schoolId,
            'created_by' => $user['id'],
            'title' => $title,
            'body' => $body,
            'recipient_type' => $recipientType,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'sent_count' => $result['sent'] ?? 0,
            'failed_count' => $result['failed'] ?? 0,
            'data' => json_encode($dataPayload)
        ]);

        Response::json([
            'success' => true,
            'message' => "Notification sent to {$result['sent']} devices",
            'details' => $result
        ]);
    }
    
    /**
     * Send to specific students by IDs
     */
    private function sendToStudents($studentIds, $title, $body, $dataPayload)
    {
        // Get tokens for these students - USING device_tokens TABLE
        $placeholders = implode(',', array_fill(0, count($studentIds), '?'));
        $stmt = $this->db->prepare("
            SELECT token 
            FROM device_tokens 
            WHERE student_id IN ($placeholders) 
            AND last_used_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute($studentIds);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0, 'message' => 'No active devices'];
        }
        
        $response = $this->firebaseService->sendToMultipleDevices($tokens, $title, $body, $dataPayload);
        
        return [
            'sent' => $response['success'] ?? 0,
            'failed' => ($response['failed'] ?? 0) + ($response['error'] ?? 0)
        ];
    }
    
    /**
     * Send to entire class
     */
    private function sendToClass($classId, $title, $body, $dataPayload)
    {
        // USING device_tokens TABLE
        $stmt = $this->db->prepare("
            SELECT dt.token 
            FROM device_tokens dt
            JOIN students s ON dt.student_id = s.id
            WHERE s.class_id = ? 
            AND dt.last_used_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$classId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0, 'message' => 'No active devices in this class'];
        }
        
        $response = $this->firebaseService->sendToMultipleDevices($tokens, $title, $body, $dataPayload);
        
        return [
            'sent' => $response['success'] ?? 0,
            'failed' => ($response['failed'] ?? 0) + ($response['error'] ?? 0)
        ];
    }
    
    /**
     * Send to section
     */
    private function sendToSection($classId, $sectionId, $title, $body, $dataPayload)
    {
        // USING device_tokens TABLE
        $stmt = $this->db->prepare("
            SELECT dt.token 
            FROM device_tokens dt
            JOIN students s ON dt.student_id = s.id
            WHERE s.class_id = ? AND s.section_id = ? 
            AND dt.last_used_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$classId, $sectionId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0, 'message' => 'No active devices in this section'];
        }
        
        $response = $this->firebaseService->sendToMultipleDevices($tokens, $title, $body, $dataPayload);
        
        return [
            'sent' => $response['success'] ?? 0,
            'failed' => ($response['failed'] ?? 0) + ($response['error'] ?? 0)
        ];
    }
    
    /**
     * Send to all students in school
     */
    private function sendToAllSchoolStudents($schoolId, $title, $body, $dataPayload)
    {
        // For large schools, you might want to batch this
        // USING device_tokens TABLE
        $stmt = $this->db->prepare("
            SELECT dt.token 
            FROM device_tokens dt
            JOIN students s ON dt.student_id = s.id
            WHERE s.school_id = ? 
            AND dt.last_used_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$schoolId]);
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0, 'message' => 'No active devices in school'];
        }
        
        // FCM limit is 1000 per request, so chunk if needed
        $chunks = array_chunk($tokens, 900);
        $totalSent = 0;
        $totalFailed = 0;
        
        foreach ($chunks as $chunk) {
            $response = $this->firebaseService->sendToMultipleDevices($chunk, $title, $body, $dataPayload);
            $totalSent += $response['success'] ?? 0;
            $totalFailed += ($response['failed'] ?? 0) + ($response['error'] ?? 0);
        }
        
        return ['sent' => $totalSent, 'failed' => $totalFailed];
    }
    
    /**
     * Get notification history
     * GET /api/notifications/history
     */
    public function getNotificationHistory()
    {
        $user = JwtHelper::getUserFromToken();
        $schoolId = $user['school_id'] ?? null;
        
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 20;
        $offset = ($page - 1) * $limit;
        
        // Check if table exists first
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM notification_history 
                WHERE school_id = ? 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$schoolId, (int)$limit, (int)$offset]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM notification_history WHERE school_id = ?");
            $countStmt->execute([$schoolId]);
            $total = $countStmt->fetchColumn();
            
            Response::json([
                'history' => $history,
                'total' => (int)$total,
                'page' => (int)$page,
                'pages' => ceil($total / $limit)
            ]);
        } catch (PDOException $e) {
            // Table might not exist yet
            Response::json([
                'history' => [],
                'total' => 0,
                'page' => (int)$page,
                'pages' => 0
            ]);
        }
    }
    
    /**
     * Log notification to history
     */
    private function logNotification($data)
    {
        try {
            // Check if table exists, create if not
            $this->ensureNotificationTableExists();
            
            $stmt = $this->db->prepare("
                INSERT INTO notification_history (
                    school_id, created_by, title, body, 
                    recipient_type, class_id, section_id, 
                    sent_count, failed_count, data, created_at
                ) VALUES (
                    :school_id, :created_by, :title, :body,
                    :recipient_type, :class_id, :section_id,
                    :sent_count, :failed_count, :data, NOW()
                )
            ");
            
            $stmt->execute($data);
        } catch (Exception $e) {
            // Just log error but don't stop notification
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }
    
    /**
     * Ensure notification_history table exists
     */
    private function ensureNotificationTableExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS notification_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            school_id BIGINT NOT NULL,
            created_by BIGINT NOT NULL,
            title VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            recipient_type ENUM('single', 'class', 'section', 'all') NOT NULL,
            class_id BIGINT NULL,
            section_id BIGINT NULL,
            sent_count INT DEFAULT 0,
            failed_count INT DEFAULT 0,
            data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_school (school_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->db->exec($sql);
    }
}