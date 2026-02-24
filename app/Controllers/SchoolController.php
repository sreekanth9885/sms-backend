<?php

require_once __DIR__ . '/../Models/School.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Core/Response.php';

class SchoolController
{
    private $db;
    private $school;
    private $uploadDir = __DIR__ . '/../../app/uploads/schools/';

    public function __construct($db)
    {
        $this->db = $db;
        $this->school = new School($db);

        // Create upload directory if it doesn't exist
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'SUPER_ADMIN') {
            Response::json(["message" => "Forbidden"], 403);
        }

        // Handle multipart form data for file upload
        $data = $_POST;
        $logoUrl = null;

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $logoUrl = $this->uploadLogo($_FILES['logo']);
        }

        $required = [
            'name',
            'address',
            'contact_name',
            'contact_designation',
            'contact_email',
            'contact_phone_primary',
            'board',
            'established_date'
        ];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::json(["message" => "$field is required"], 422);
            }
        }

        // Generate school code
        $data['code'] = strtoupper(substr(preg_replace('/\s+/', '', $data['name']), 0, 4)) . rand(1000, 9999);
        $data['logo_url'] = $logoUrl;

        try {
            $this->db->beginTransaction();

            // Create school
            $schoolId = $this->school->create($data, $user['id']);

            // Generate ADMIN password
            $tempPassword = bin2hex(random_bytes(4));
            $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

            // Create ADMIN
            $this->school->createAdmin(
                $schoolId,
                $data['contact_name'],
                $data['contact_email'],
                $hashedPassword
            );

            $this->db->commit();

            Response::json([
                "message" => "School and admin created successfully",
                "school_code" => $data['code'],
                "admin_credentials" => [
                    "email" => $data['contact_email'],
                    "temp_password" => $tempPassword
                ],
                "logo_url" => $logoUrl
            ], 201);
        } catch (Exception $e) {
            $this->db->rollBack();

            // Delete uploaded logo if school creation failed
            if ($logoUrl && file_exists($this->uploadDir . basename($logoUrl))) {
                unlink($this->uploadDir . basename($logoUrl));
            }

            Response::json([
                "message" => "School creation failed",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function update($id)
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'SUPER_ADMIN') {
            Response::json(["message" => "Forbidden"], 403);
        }

        if (!$id) {
            Response::json(["message" => "School ID required"], 422);
        }

        // Get existing school data
        $existingSchool = $this->school->find((int)$id);
        if (!$existingSchool) {
            Response::json(["message" => "School not found"], 404);
        }

        // Handle multipart form data
        $data = $_POST;
        $logoUrl = $existingSchool['logo_url']; // Keep existing logo by default

        // Handle logo upload
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            // Upload new logo
            $logoUrl = $this->uploadLogo($_FILES['logo']);

            // Delete old logo if exists
            if ($existingSchool['logo_url']) {
                $oldLogoPath = $this->uploadDir . basename($existingSchool['logo_url']);
                if (file_exists($oldLogoPath)) {
                    unlink($oldLogoPath);
                }
            }
        }

        // Check if email is being updated and already exists
        if (
            isset($data['contact_email']) &&
            $data['contact_email'] !== $existingSchool['contact_email'] &&
            $this->school->emailExistsForOther($data['contact_email'], (int)$id)
        ) {
            Response::json(["message" => "Email already registered with another school"], 422);
        }

        $data['logo_url'] = $logoUrl;

        try {
            $updated = $this->school->update((int)$id, $data);

            if (!$updated) {
                Response::json(["message" => "No changes made or school not found"], 400);
            }

            Response::json([
                "message" => "School updated successfully",
                "logo_url" => $logoUrl
            ]);
        } catch (Exception $e) {
            Response::json([
                "message" => "School update failed",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    private function uploadLogo($file): string
    {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        // Validate file type
        if (!in_array($file['type'], $allowedTypes)) {
            Response::json(["message" => "Invalid file type. Only JPG, PNG, GIF and WEBP are allowed"], 422);
        }

        // Validate file size
        if ($file['size'] > $maxSize) {
            Response::json(["message" => "File size too large. Maximum 2MB allowed"], 422);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            Response::json(["message" => "Failed to upload logo"], 500);
        }

        // Return relative path for database
        return 'app/uploads/schools/' . $filename;
    }

    public function index()
    {
        $schools = $this->school->all();
        Response::json($schools);
    }

    public function get($id)
    {
        $school = $this->school->find((int)$id);

        if (!$school) {
            Response::json(["message" => "School not found"], 404);
        }

        Response::json($school);
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'SUPER_ADMIN') {
            Response::json(["message" => "Forbidden"], 403);
        }

        if (!$id) {
            Response::json(["message" => "School ID required"], 422);
        }

        // Get school to delete logo
        $school = $this->school->find((int)$id);

        $deleted = $this->school->delete((int)$id);

        if (!$deleted) {
            Response::json(["message" => "School not found"], 404);
        }

        // Delete logo file if exists
        if ($school && $school['logo_url']) {
            $logoPath = $this->uploadDir . basename($school['logo_url']);
            if (file_exists($logoPath)) {
                unlink($logoPath);
            }
        }

        Response::json(["message" => "School deleted successfully"]);
    }
}