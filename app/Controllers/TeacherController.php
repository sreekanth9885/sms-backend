<?php

require_once __DIR__ . '/../Models/TeacherModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class TeacherController
{
    private TeacherModel $teacherModel;

    public function __construct(PDO $db)
    {
        $this->teacherModel = new TeacherModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        // Get data from $_POST (for multipart/form-data)
        $data = $_POST;

        // Debug log
        error_log('=== TEACHER CREATE DEBUG ===');
        error_log('$_POST: ' . print_r($_POST, true));
        error_log('$_FILES: ' . print_r($_FILES, true));

        /* ---------- FILE UPLOAD ---------- */
        $photoUrl = null;

        if (!empty($_FILES['photo']['name'])) {
            $photoUrl = $this->uploadPhoto($_FILES['photo']);
        }

        /* ---------- REQUIRED CHECK ---------- */
        $required = ['name', 'gender', 'dob', 'id_number', 'phone', 'subject'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::json(["message" => "$field is required"], 422);
            }
        }

        $teacherId = $this->teacherModel->create(
            (int)$user['school_id'],
            $data,
            $photoUrl
        );

        Response::json([
            "message" => "Teacher created successfully",
            "teacher_id" => $teacherId,
            "photo_url" => $photoUrl
        ], 201);
    }

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $teachers = $this->teacherModel->allBySchool(
            (int)$user['school_id']
        );

        Response::json($teachers);
    }

    public function show($id)
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $teacher = $this->teacherModel->find((int)$id, (int)$user['school_id']);

        if (!$teacher) {
            Response::json(["message" => "Teacher not found"], 404);
        }

        Response::json($teacher);
    }

    public function update($id)
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        // Debug log
        error_log('=== TEACHER UPDATE DEBUG ===');
        error_log('ID: ' . $id);
        error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'none'));
        error_log('$_POST: ' . print_r($_POST, true));
        error_log('$_FILES: ' . print_r($_FILES, true));

        // Get data from $_POST (PHP automatically parses FormData)
        $data = $_POST;

        // Check if teacher exists and belongs to this school
        $existingTeacher = $this->teacherModel->find((int)$id, (int)$user['school_id']);
        if (!$existingTeacher) {
            Response::json(["message" => "Teacher not found"], 404);
        }

        /* ---------- FILE UPLOAD ---------- */
        // Check if a new photo is being uploaded
        if (!empty($_FILES['photo']['name'])) {
            error_log('New photo uploaded: ' . $_FILES['photo']['name']);

            // Upload new photo
            $photoUrl = $this->uploadPhoto($_FILES['photo']);
            $data['photo'] = $photoUrl;

            // Delete old photo if exists
            if (!empty($existingTeacher['photo'])) {
                $this->deleteOldPhoto($existingTeacher['photo']);
            }
        }
        // If no new photo, keep existing (don't include photo in data)
        // The model will not update photo if it's not in the data array

        /* ---------- REQUIRED CHECK ---------- */
        $required = ['name', 'gender', 'dob', 'id_number', 'phone', 'subject'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                error_log("Missing required field: $field");
                Response::json(["message" => "$field is required"], 422);
            }
        }

        /* ---------- UPDATE ---------- */
        // Remove fields that shouldn't be updated
        unset($data['id']);
        unset($data['school_id']); // Don't allow school_id to be changed
        unset($data['created_at']);

        error_log('Data to update: ' . print_r($data, true));

        $updated = $this->teacherModel->update(
            (int)$id,
            (int)$user['school_id'],
            $data
        );

        if (!$updated) {
            Response::json(["message" => "Update failed - no changes made"], 400);
        }

        Response::json([
            "message" => "Teacher updated successfully",
            "photo_url" => $data['photo'] ?? $existingTeacher['photo']
        ]);
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        if ($user['role'] !== 'ADMIN' && $user['role'] !== 'SUPER_ADMIN') {
            Response::json(["message" => "Forbidden - Admin access required"], 403);
        }

        // Get teacher to delete photo
        $teacher = $this->teacherModel->find((int)$id, (int)$user['school_id']);

        if (!$teacher) {
            Response::json(["message" => "Teacher not found"], 404);
        }

        $deleted = $this->teacherModel->delete((int)$id, (int)$user['school_id']);

        if (!$deleted) {
            Response::json(["message" => "Delete failed"], 400);
        }

        // Delete photo file if exists
        if ($teacher && !empty($teacher['photo'])) {
            $this->deleteOldPhoto($teacher['photo']);
        }

        Response::json(["message" => "Teacher deleted successfully"]);
    }

    /**
     * Upload photo and return the URL
     */
    private function uploadPhoto($file): string
    {
        // Validate size (2MB max)
        if ($file['size'] > 2 * 1024 * 1024) {
            Response::json(["message" => "Image must be less than 2MB"], 422);
        }

        // Validate type using exif_imagetype or getimagesize as fallback
        $allowedTypes = [
            1 => 'image/gif',
            2 => 'image/jpeg',
            3 => 'image/png',
            18 => 'image/webp'
        ];

        // Try using exif_imagetype first (faster)
        if (function_exists('exif_imagetype')) {
            $imageType = exif_imagetype($file['tmp_name']);
            if (!isset($allowedTypes[$imageType])) {
                Response::json(["message" => "Invalid image type. Allowed: JPG, PNG, GIF, WEBP"], 422);
            }
        }
        // Fallback to getimagesize
        else if (function_exists('getimagesize')) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                Response::json(["message" => "Invalid image file"], 422);
            }
            $imageType = $imageInfo[2];
            if (!isset($allowedTypes[$imageType])) {
                Response::json(["message" => "Invalid image type. Allowed: JPG, PNG, GIF, WEBP"], 422);
            }
        }
        // Last resort: check file extension (less secure but works)
        else {
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                Response::json(["message" => "Invalid file extension. Allowed: JPG, PNG, GIF, WEBP"], 422);
            }
        }

        // Create folder if not exists
        $uploadDir = __DIR__ . "/../../app/uploads/teachers/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid("teacher_") . "." . $ext;

        $destination = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            Response::json(["message" => "Failed to upload image"], 500);
        }

        error_log('Photo uploaded successfully: ' . $fileName);

        // Return relative path
        return "app/uploads/teachers/" . $fileName;
    }

    /**
     * Delete old photo file
     */
    private function deleteOldPhoto(string $photoUrl): void
    {
        $filePath = __DIR__ . "/../../" . $photoUrl;
        if (file_exists($filePath)) {
            unlink($filePath);
            error_log('Deleted old photo: ' . $photoUrl);
        }
    }
}