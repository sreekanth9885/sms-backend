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

        $data = json_decode(file_get_contents("php://input"), true);

        // Required fields
        $required = [
            'class_id',
            'section_id',
            'admission_number',
            'first_name'
        ];

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
            'photo_url'               => $data['photo_url'] ?? null,
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
        $data = json_decode(file_get_contents("php://input"), true);

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
}
