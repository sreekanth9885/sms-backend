<?php

require_once __DIR__ . '/../Models/School.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Core/Response.php';

class SchoolController
{
    private $db;
    private $school;

    public function __construct($db)
    {
        $this->db = $db;
        $this->school = new School($db);
    }

    public function create()
{
    $user = JwtHelper::getUserFromToken();

    if ($user['role'] !== 'SUPER_ADMIN') {
        Response::json(["message" => "Forbidden"], 403);
    }

    $data = json_decode(file_get_contents("php://input"), true);

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
    $data['code'] =
        strtoupper(substr(preg_replace('/\s+/', '', $data['name']), 0, 4))
        . rand(1000, 9999);

    try {
        $this->db->beginTransaction();

        // 1️⃣ Create school
        $schoolId = $this->school->create($data, $user['uid']);

        // 2️⃣ Generate ADMIN password ONCE
        $tempPassword = bin2hex(random_bytes(4));
        $hashedPassword = password_hash($tempPassword, PASSWORD_BCRYPT);

        // 3️⃣ Create ADMIN
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
            ]
        ], 201);

    } catch (Exception $e) {
        $this->db->rollBack();

        Response::json([
            "message" => "School creation failed",
            "error" => $e->getMessage()
        ], 500);
    }
}



}
