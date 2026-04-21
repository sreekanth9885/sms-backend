<?php
require_once __DIR__ . '/../Models/TeacherTimetableModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class TeacherTimetableController
{
    private TeacherTimetableModel $model;

    public function __construct(PDO $db)
    {
        $this->model = new TeacherTimetableModel($db);
    }

    public function create()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        // ✅ Validation
        $required = ['class_id', 'subject_id', 'teacher_id', 'academic_year', 'start_date', 'start_time', 'end_time'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                Response::json(["message" => "$field is required"], 422);
            }
        }

        if ($data['start_time'] >= $data['end_time']) {
            Response::json(["message" => "Invalid time range"], 422);
        }

        if (!empty($data['end_date']) && $data['start_date'] > $data['end_date']) {
            Response::json(["message" => "Invalid date range"], 422);
        }

        // 🔥 Check conflicts
        $conflicts = $this->model->checkConflicts(
            $user['school_id'],
            $data['class_id'],
            $data['teacher_id'],
            $data['start_date'],
            $data['end_date'] ?? $data['start_date'],
            $data['start_time'],
            $data['end_time']
        );

        // ⚠️ If conflict → ask frontend confirmation
        if (!empty($conflicts) && empty($data['force'])) {
            Response::json([
                "message" => "Conflict detected",
                "conflicts" => $conflicts,
                "requires_confirmation" => true
            ], 409);
        }

        // ✅ If user confirmed → deactivate old + insert new
        if (!empty($data['force'])) {
            $this->model->deactivateConflicts(
                $user['school_id'],
                $data['class_id'],
                $data['teacher_id']
            );
        }

        $id = $this->model->create([
            "school_id" => $user['school_id'],
            ...$data
        ]);

        Response::json([
            "message" => "Timetable created successfully",
            "id" => $id
        ], 201);
    }
    public function get()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $params = $_GET;

        $classId   = $params['class_id'] ?? null;
        $teacherId = $params['teacher_id'] ?? null;
        $date      = $params['date'] ?? null;

        $data = $this->model->getTimetable(
            $user['school_id'],
            $classId,
            $teacherId,
            $date
        );

        Response::json([
            "message" => "Timetable fetched successfully",
            "data" => $data
        ]);
    }
}