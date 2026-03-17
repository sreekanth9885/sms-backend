<?php
require_once __DIR__ . '/../Core/Response.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Models/StudentModel.php';
require_once __DIR__ . '/../Models/DeviceToken.php';

class StudentAuthController
{
    private PDO $db;
    private StudentModel $student;
    private DeviceToken $deviceToken;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->student = new StudentModel($db);
        $this->deviceToken = new DeviceToken($db);
    }
    
    /**
     * Student Login with Mobile Number
     * POST /auth/student-login
     */
    public function studentLogin()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['mobile_number'])) {
            Response::json(["message" => "Mobile number is required"], 400);
        }

        $mobileNumber = $data['mobile_number'];
        $schoolId = $data['school_id'] ?? null; // Optional school_id filter

        // Find ALL students by mobile number, optionally filtered by school
        $students = $this->findAllStudentsByMobile($mobileNumber, $schoolId);

        if (empty($students)) {
            Response::json(["message" => "No account found with this mobile number"], 404);
        }

        // If multiple students found, return the list for selection
        if (count($students) > 1) {
            $studentList = [];
            foreach ($students as $student) {
                $studentList[] = [
                    "id" => $student['id'],
                    "name" => trim($student['first_name'] . " " . ($student['last_name'] ?? "")),
                    "class_name" => $student['class_name'] ?? null,
                    "section_name" => $student['section_name'] ?? null,
                    "admission_number" => $student['admission_number'],
                    "roll_number" => $student['roll_number'],
                    "photo_url" => $student['photo_url'],
                    "school_id" => $student['school_id'] // Include school_id in response
                ];
            }

            Response::json([
                "success" => true,
                "multiple_accounts" => true,
                "message" => "Multiple accounts found. Please select one.",
                "students" => $studentList
            ]);
        }

        // Single student found - proceed with login
        $student = $students[0];

        // Generate JWT token for student
        $token = JwtHelper::generateAccessToken([
            "id" => $student['id'],
            "type" => "student",
            "school_id" => $student['school_id'],
            "class_id" => $student['class_id'],
            "section_id" => $student['section_id']
        ]);

        // Format student data for response
        $studentData = [
            "id" => $student['id'],
            "name" => trim($student['first_name'] . " " . ($student['last_name'] ?? "")),
            "first_name" => $student['first_name'],
            "last_name" => $student['last_name'],
            "admission_number" => $student['admission_number'],
            "roll_number" => $student['roll_number'],
            "class_id" => $student['class_id'],
            "class_name" => $student['class_name'],
            "section_id" => $student['section_id'],
            "section_name" => $student['section_name'],
            "school_id" => $student['school_id'],
            "father_name" => $student['father_name'],
            "mother_name" => $student['mother_name'],
            "parent_phone" => $student['parent_phone'],
            "alternate_phone" => $student['alternate_phone'],
            "parent_email" => $student['parent_email'],
            "photo_url" => $student['photo_url'],
            "is_active" => (bool)$student['is_active']
        ];

        Response::json([
            "success" => true,
            "message" => "Login successful",
            "token" => $token,
            "user" => $studentData
        ]);
    }
    public function selectStudent()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['mobile_number'])) {
            Response::json(["message" => "Mobile number is required"], 400);
        }

        if (empty($data['student_id'])) {
            Response::json(["message" => "Student ID is required"], 400);
        }

        // if (empty($data['school_id'])) {
        //     Response::json(["message" => "School ID is required"], 400);
        // }

        $mobileNumber = $data['mobile_number'];
        $studentId = $data['student_id'];
        $schoolId = $data['school_id'];

        // Find the specific student by ID and verify it belongs to this mobile number and school
        $student = $this->findStudentByIdAndMobile($studentId, $mobileNumber, $schoolId);

        if (!$student) {
            Response::json(["message" => "Student not found or does not belong to this mobile number/school"], 404);
        }

        // Generate JWT token for the selected student
        $token = JwtHelper::generateAccessToken([
            "id" => $student['id'],
            "type" => "student",
            "school_id" => $student['school_id'],
            "class_id" => $student['class_id'],
            "section_id" => $student['section_id']
        ]);

        // Format student data for response
        $studentData = [
            "id" => $student['id'],
            "name" => trim($student['first_name'] . " " . ($student['last_name'] ?? "")),
            "first_name" => $student['first_name'],
            "last_name" => $student['last_name'],
            "admission_number" => $student['admission_number'],
            "roll_number" => $student['roll_number'],
            "class_id" => $student['class_id'],
            "class_name" => $student['class_name'],
            "section_id" => $student['section_id'],
            "section_name" => $student['section_name'],
            "school_id" => $student['school_id'],
            "father_name" => $student['father_name'],
            "mother_name" => $student['mother_name'],
            "parent_phone" => $student['parent_phone'],
            "alternate_phone" => $student['alternate_phone'],
            "parent_email" => $student['parent_email'],
            "photo_url" => $student['photo_url'],
            "is_active" => (bool)$student['is_active']
        ];

        Response::json([
            "success" => true,
            "message" => "Student selected successfully",
            "token" => $token,
            "user" => $studentData
        ]);
    }

    /**
     * Find ALL students by mobile number
     */
    private function findAllStudentsByMobile($mobileNumber, $schoolId = null)
    {
        $sql = "SELECT s.*, 
               c.name as class_name, 
               sec.name as section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE (s.parent_phone = :mobile OR s.alternate_phone = :mobile)
        AND s.is_active = 1";

        // Add school filter if provided
        $params = [':mobile' => $mobileNumber];

        if ($schoolId) {
            $sql .= " AND s.school_id = :school_id";
            $params[':school_id'] = $schoolId;
        }

        $sql .= " ORDER BY s.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Student Logout
     * POST /auth/student-logout
     */
    public function studentLogout()
    {
        $user = JwtHelper::getUserFromTokenMobile();
        
        if (!$user || $user['type'] !== 'student') {
            Response::json(["message" => "Unauthorized"], 401);
        }
        
        $data = json_decode(file_get_contents("php://input"), true);
        $deviceToken = $data['device_token'] ?? null;
        
        if ($deviceToken) {
            // Remove specific device token
            $this->deviceToken->deleteToken($user['id'], $deviceToken);
        } else {
            // Remove all tokens for this student
            $this->deviceToken->deleteAllStudentTokens($user['id']);
        }
        
        Response::json([
            "success" => true,
            "message" => "Logged out successfully"
        ]);
    }
    
    /**
     * Register Device Token for Push Notifications
     * POST /register-device
     */
    public function registerDeviceToken()
{
    $data = json_decode(file_get_contents("php://input"), true);
    
    $userId = $data['user_id'] ?? null;
    $token = $data['token'] ?? null;
    $platform = $data['platform'] ?? 'android';
    $schoolId = $data['school_id'] ?? null; // Add this
    
    if (!$userId || !$token) {
        Response::json(["message" => "User ID and token are required"], 400);
    }
    
    if (!$schoolId) {
        Response::json(["message" => "School ID is required"], 400);
    }
    
    // Verify the student exists with both id and school_id
    $student = $this->student->findById($userId, $schoolId);
    
    if (!$student) {
        Response::json(["message" => "Student not found"], 404);
    }
    
    // Store or update device token
    $this->deviceToken->storeToken($userId, $token, $platform);
    
    Response::json([
        "success" => true,
        "message" => "Device token registered successfully"
    ]);
}

    /**
     * Find student by mobile number (parent_phone or alternate_phone)
     */
    private function findStudentByMobile($mobileNumber)
    {
        $sql = "SELECT s.*, 
                       c.name as class_name, 
                       sec.name as section_name
                FROM students s
                LEFT JOIN classes c ON s.class_id = c.id
                LEFT JOIN sections sec ON s.section_id = sec.id
                WHERE (s.parent_phone = :mobile OR s.alternate_phone = :mobile)
                AND s.is_active = 1
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':mobile' => $mobileNumber]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get class and section details
     */
    private function getClassSectionDetails($classId, $sectionId)
    {
        $sql = "SELECT 
                    c.id as class_id, 
                    c.name as class_name,
                    s.id as section_id,
                    s.name as section_name
                FROM classes c
                LEFT JOIN sections s ON s.id = :section_id AND s.class_id = c.id
                WHERE c.id = :class_id";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':class_id' => $classId,
            ':section_id' => $sectionId
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'class' => $result ? ['id' => $result['class_id'], 'name' => $result['class_name']] : null,
            'section' => ($result && $result['section_id']) ? ['id' => $result['section_id'], 'name' => $result['section_name']] : null
        ];
    }
    /**
     * Find student by ID and verify it belongs to the mobile number
     */
    private function findStudentByIdAndMobile($studentId, $mobileNumber, $schoolId)
    {
        $sql = "SELECT s.*, 
               c.name as class_name, 
               sec.name as section_name
        FROM students s
        LEFT JOIN classes c ON s.class_id = c.id
        LEFT JOIN sections sec ON s.section_id = sec.id
        WHERE s.id = :student_id 
        AND (s.parent_phone = :mobile OR s.alternate_phone = :mobile)
        AND s.school_id = :school_id
        AND s.is_active = 1
        LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':student_id' => $studentId,
            ':mobile' => $mobileNumber,
            ':school_id' => $schoolId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}