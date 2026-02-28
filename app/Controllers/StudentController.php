<?php
require_once __DIR__ . '/../Models/StudentModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class StudentController
{
    private StudentModel $studentModel;

    public function __construct(PDO $db)
    {
        $this->studentModel = new StudentModel($db);
    }

    public function register()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = $_POST;

        /* ---------- FILE UPLOAD ---------- */

        $photoUrl = null;

        if (!empty($_FILES['pic']['name'])) {

            $file = $_FILES['pic'];

            // validate size (2MB max)
            if ($file['size'] > 2 * 1024 * 1024) {
                Response::json(["message" => "Image must be less than 2MB"], 422);
            }

            // validate type
            $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            if (!in_array($file['type'], $allowed)) {
                Response::json(["message" => "Invalid image type"], 422);
            }

            // create folder if not exists
            $uploadDir = __DIR__ . "/../../app/uploads/students/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid("student_") . "." . $ext;

            $destination = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                Response::json(["message" => "Failed to upload image"], 500);
            }

            // public URL (adjust domain if needed)
            $photoUrl = "app/uploads/students/" . $fileName;
        }
        /* ---------- REQUIRED CHECK ---------- */

        $required = ['class_id', 'admission_number', 'first_name'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::json(["message" => "$field is required"], 422);
            }
        }

        $studentId = $this->studentModel->create([
            'school_id'               => (int)$user['school_id'],
            'class_id'                => (int)$data['class_id'],
            'section_id'              => (int)$data['section_id'],
            'admission_number'        => $data['admission_number'],
            'roll_number'             => $data['roll_number'] ?? null,
            'date_of_admission'       => $data['date_of_admission'] ?? null,
            'first_name'              => $data['first_name'],
            'last_name'               => $data['last_name'] ?? null,
            'gender'                  => $data['gender'] ?? null,
            'dob'                     => $data['dob'] ?? null,
            'aadhaar'                 => $data['aadhaar'] ?? null,
            'blood_group'             => $data['blood_group'] ?? null,
            'religion'                => $data['religion'] ?? null,
            'caste'                   => $data['caste'] ?? null,
            'sub_caste'               => $data['sub_caste'] ?? null,
            'pen'                     => $data['pen'] ?? null,
            'father_name'             => $data['father_name'] ?? null,
            'mother_name'             => $data['mother_name'] ?? null,
            'parent_phone'            => $data['parent_phone'] ?? null,
            'alternate_phone'         => $data['alternate_phone'] ?? null,
            'parent_email'            => $data['parent_email'] ?? null,
            'village'                 => $data['village'] ?? null,
            'district'                => $data['district'] ?? null,
            'state'                   => $data['state'] ?? null,
            'country'                 => $data['country'] ?? null,
            'pincode'                 => $data['pincode'] ?? null,
            'complete_address'        => $data['complete_address'] ?? null,
            'identification_mark_1'   => $data['identification_mark_1'] ?? null,
            'identification_mark_2'   => $data['identification_mark_2'] ?? null,
            'photo_url'               => $photoUrl,
            'created_by'              => (int)$user['id']
        ]);

        Response::json([
            "message" => "Student registered successfully",
            "student_id" => $studentId
        ], 201);
    }

    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        $filters = [
            'class_id' => $_GET['class_id'] ?? null,
            'section_id' => $_GET['section_id'] ?? null,
            'search' => $_GET['search'] ?? null,
        ];

        $students = $this->studentModel->allBySchool(
            (int)$user['school_id'],
            $filters
        );

        Response::json($students);
    }

    public function show($id)
    {
        $user = JwtHelper::getUserFromToken();

        $student = $this->studentModel->findById(
            (int)$id,
            (int)$user['school_id']
        );

        if (!$student) {
            Response::json(["message" => "Student not found"], 404);
        }

        Response::json($student);
    }

    public function update($id)
    {
        $user = JwtHelper::getUserFromToken();

        // Debug log
        error_log('Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'none'));

        // Parse the multipart data - merge with $_POST as fallback
        $parsedData = $this->parseMultipartData();
        $data = array_merge($_POST, $parsedData);

        // Debug log parsed data
        error_log('Parsed data: ' . print_r($parsedData, true));
        error_log('$_POST data: ' . print_r($_POST, true));
        error_log('Merged data: ' . print_r($data, true));
        error_log('Files: ' . print_r($_FILES, true));

        /* ---------- FILE UPLOAD ---------- */
        // Only update photo if a new one is uploaded
        if (!empty($_FILES['pic']['name'])) {
            $file = $_FILES['pic'];

            if ($file['size'] > 2 * 1024 * 1024) {
                Response::json(["message" => "Image must be less than 2MB"], 422);
            }

            $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];

            if (!in_array($file['type'], $allowed)) {
                Response::json(["message" => "Invalid image type"], 422);
            }

            $uploadDir = __DIR__ . "/../../app/uploads/students/";

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fileName = uniqid("student_") . "." . $ext;

            $destination = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                Response::json(["message" => "Failed to upload image"], 500);
            }

            // Update photo_url with new file
            $data['photo_url'] = "app/uploads/students/" . $fileName;
        }
        // If no new photo and photo_url is not in data, keep existing (don't set to null)
        else if (!isset($data['photo_url'])) {
            // Fetch existing student to keep current photo
            $existingStudent = $this->studentModel->findById((int)$id, (int)$user['school_id']);
            if ($existingStudent && !empty($existingStudent['photo_url'])) {
                $data['photo_url'] = $existingStudent['photo_url'];
            }
        }

        /* ---------- REQUIRED CHECK ---------- */
        $required = ['class_id', 'section_id', 'admission_number', 'first_name'];

        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                error_log("Missing required field: $field");
                Response::json([
                    "message" => "$field is required",
                    "debug" => [
                        "field" => $field,
                        "received_data" => $data
                    ]
                ], 422);
            }
        }

        /* ---------- UPDATE ---------- */
        // Cast numeric fields to integers
        $data['class_id'] = (int)$data['class_id'];
        $data['section_id'] = (int)$data['section_id'];
        $data['school_id'] = (int)$user['school_id'];

        // Remove any fields that shouldn't be updated
        unset($data['id']); // Don't try to update the ID
        unset($data['created_at']); // Keep original creation date
        unset($data['created_by']); // Keep original creator

        $updated = $this->studentModel->update(
            (int)$id,
            (int)$user['school_id'],
            $data
        );

        if (!$updated) {
            Response::json(["message" => "Update failed"], 400);
        }

        Response::json(["message" => "Student updated successfully"]);
    }

    public function delete($id)
    {
        $user = JwtHelper::getUserFromToken();

        $deleted = $this->studentModel->delete(
            (int)$id,
            (int)$user['school_id']
        );

        if (!$deleted) {
            Response::json(["message" => "Delete failed"], 400);
        }

        Response::json(["message" => "Student deleted successfully"]);
    }

    /**
     * Parse multipart/form-data from php://input
     */
    private function parseMultipartData(): array
    {
        $data = [];
        $rawInput = file_get_contents('php://input');

        // Get the boundary
        if (preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'] ?? '', $matches)) {
            $boundary = $matches[1];
        } else {
            return $_POST; // Fallback to $_POST if no boundary found
        }

        // Split by boundary
        $parts = explode('--' . $boundary, $rawInput);

        foreach ($parts as $part) {
            // Skip empty parts and the closing boundary
            if (trim($part) === '' || trim($part) === '--') {
                continue;
            }

            // Check if this part contains a name
            if (preg_match('/name="([^"]+)"/', $part, $nameMatch)) {
                $fieldName = $nameMatch[1];

                // Skip file uploads in text parsing (they are handled by $_FILES)
                if (strpos($part, 'filename="') !== false) {
                    continue;
                }

                // Extract the value (after the blank line)
                if (preg_match('/\r\n\r\n(.*)\r\n$/', $part, $valueMatch)) {
                    $data[$fieldName] = trim($valueMatch[1]);
                } else {
                    // Try alternative pattern for last part without trailing newline
                    if (preg_match('/\r\n\r\n(.*)$/', $part, $valueMatch)) {
                        $data[$fieldName] = trim($valueMatch[1]);
                    }
                }
            }
        }

        return $data;
    }
}