<?php
require_once __DIR__ . '/../Models/AttendanceModel.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';

class AttendanceController
{
    private AttendanceModel $attendanceModel;

    public function __construct(PDO $db)
    {
        $this->attendanceModel = new AttendanceModel($db);
    }

    /**
     * Save attendance for multiple students
     * POST /attendance/bulk
     */
    public function saveBulk()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['attendance']) || !is_array($data['attendance'])) {
            Response::json(["message" => "Attendance data is required"], 422);
        }

        // Validate required fields
        $required = ['student_id', 'class_id', 'section_id', 'date', 'status'];
        $validStatuses = ['Present', 'Absent', 'Late', 'Half-Day', 'Holiday'];

        foreach ($data['attendance'] as $record) {
            foreach ($required as $field) {
                if (!isset($record[$field])) {
                    Response::json(["message" => "Missing field: $field in attendance record"], 422);
                }
            }
            
            if (!in_array($record['status'], $validStatuses)) {
                Response::json(["message" => "Invalid status: {$record['status']}"], 422);
            }
        }

        try {
            $saved = $this->attendanceModel->saveBulk(
                $data['attendance'],
                (int)$user['id'],
                $user['name'] ?? null
            );

            if ($saved) {
                Response::json([
                    "message" => "Attendance saved successfully",
                    "count" => count($data['attendance'])
                ], 201);
            } else {
                Response::json(["message" => "Failed to save attendance"], 500);
            }

        } catch (Exception $e) {
            Response::json([
                "message" => "Failed to save attendance",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get attendance for a class/section on a specific date
     * GET /attendance
     */
    public function index()
    {
        $user = JwtHelper::getUserFromToken();

        if (!isset($user['school_id'])) {
            Response::json(["message" => "School context missing"], 403);
        }

        $classId = $_GET['class_id'] ?? null;
        $sectionId = $_GET['section_id'] ?? null;
        $date = $_GET['date'] ?? date('Y-m-d');

        if (!$classId || !$sectionId) {
            Response::json(["message" => "Class ID and Section ID are required"], 422);
        }

        $attendance = $this->attendanceModel->getByClassSection(
            (int)$classId,
            (int)$sectionId,
            $date
        );

        Response::json($attendance);
    }

    /**
     * Get attendance for a specific student
     * GET /attendance/student/{studentId}
     */
    public function getByStudent($studentId)
    {
        $user = JwtHelper::getUserFromToken();

        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;

        $attendance = $this->attendanceModel->getByStudent(
            (int)$studentId,
            $startDate,
            $endDate
        );

        Response::json($attendance);
    }

    /**
     * Get attendance summary
     * GET /attendance/summary
     */
    public function summary()
    {
        $user = JwtHelper::getUserFromToken();

        $classId = $_GET['class_id'] ?? null;
        $sectionId = $_GET['section_id'] ?? null;
        $startDate = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
        $endDate = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

        if (!$classId || !$sectionId) {
            Response::json(["message" => "Class ID and Section ID are required"], 422);
        }

        $summary = $this->attendanceModel->getSummary(
            (int)$classId,
            (int)$sectionId,
            $startDate,
            $endDate
        );

        Response::json($summary);
    }

    /**
     * Get today's attendance status for a student
     * GET /attendance/today/{studentId}
     */
    public function getTodayStatus($studentId)
    {
        $user = JwtHelper::getUserFromToken();

        $status = $this->attendanceModel->getTodayStatus((int)$studentId);

        Response::json($status ?: ["message" => "No attendance marked for today", "status" => null]);
    }

    /**
     * Get attendance percentage for a student
     * GET /attendance/percentage/{studentId}
     */
    public function getPercentage($studentId)
    {
        $user = JwtHelper::getUserFromToken();

        $startDate = $_GET['start_date'] ?? date('Y-m-01');
        $endDate = $_GET['end_date'] ?? date('Y-m-t');

        $percentage = $this->attendanceModel->getAttendancePercentage(
            (int)$studentId,
            $startDate,
            $endDate
        );

        Response::json([
            "student_id" => (int)$studentId,
            "percentage" => $percentage,
            "start_date" => $startDate,
            "end_date" => $endDate
        ]);
    }

    /**
     * Delete attendance for a specific date
     * DELETE /attendance
     */
    public function delete()
    {
        $user = JwtHelper::getUserFromToken();

        if ($user['role'] !== 'ADMIN' && $user['role'] !== 'SUPER_ADMIN') {
            Response::json(["message" => "Forbidden - Admin access required"], 403);
        }

        $classId = $_GET['class_id'] ?? null;
        $sectionId = $_GET['section_id'] ?? null;
        $date = $_GET['date'] ?? null;

        if (!$classId || !$sectionId || !$date) {
            Response::json(["message" => "Class ID, Section ID and Date are required"], 422);
        }

        $deleted = $this->attendanceModel->deleteByDate(
            (int)$classId,
            (int)$sectionId,
            $date
        );

        if ($deleted) {
            Response::json(["message" => "Attendance deleted successfully"]);
        } else {
            Response::json(["message" => "No attendance records found for the given criteria"], 404);
        }
    }
}