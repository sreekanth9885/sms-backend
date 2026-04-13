<?php

require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/../Helpers/JwtHelper.php';
require_once __DIR__ . '/../Core/Response.php';
require_once __DIR__ . '/../Helpers/MailHelper.php';

class AuthController
{
    private User $user;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->user = new User($db);
        $this->db = $db;
    }

    /**
     * Format school data from user record
     */
    private function formatSchoolData(array $userData): ?array
    {
        if (!$userData['school_id']) {
            return null;
        }

        return [
            "id" => $userData['school_id'],
            "name" => $userData['school_name'],
            "tagline" => $userData['school_tagline'] ?? null,
            "code" => $userData['school_code'],
            "address" => $userData['school_address'] ?? null,
            "latitude" => $userData['school_latitude'] ? (float)$userData['school_latitude'] : null,
            "longitude" => $userData['school_longitude'] ? (float)$userData['school_longitude'] : null,
            "contact_name" => $userData['school_contact_name'] ?? null,
            "contact_designation" => $userData['school_contact_designation'] ?? null,
            "contact_email" => $userData['school_contact_email'] ?? null,
            "contact_phone_primary" => $userData['school_contact_phone_primary'] ?? null,
            "contact_phone_secondary" => $userData['school_contact_phone_secondary'] ?? null,
            "board" => $userData['school_board'] ?? null,
            "established_date" => $userData['school_established_date'] ?? null,
            "website" => $userData['school_website'] ?? null,
            "logo_url" => $userData['school_logo_url'] ?? null,
            "is_active" => (bool)$userData['school_is_active'],
            "created_by" => $userData['school_created_by'] ? (int)$userData['school_created_by'] : null,
            "created_at" => $userData['school_created_at'] ?? null,
            "updated_at" => $userData['school_updated_at'] ?? null
        ];
    }

    /* =========================
       LOGIN
    ========================== */
    public function login()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email']) || empty($data['password'])) {
            Response::json(["message" => "Email and password required"], 400);
        }

        $user = $this->user->findByEmail($data['email']);
        $teacherId = null;
        if (!$user || !password_verify($data['password'], $user['password'])) {
            Response::json(["message" => "Invalid credentials"], 401);
        }
        if ($user['role'] === 'TEACHER') {
            $stmt = $this->db->prepare("
        SELECT id FROM teachers 
        WHERE user_id = ? AND school_id = ?
        LIMIT 1
    ");
            $stmt->execute([$user['id'], $user['school_id']]);
            $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($teacher) {
                $teacherId = (int)$teacher['id'];
            }
        }
        // Generate tokens via JwtHelper
        $accessToken = JwtHelper::generateAccessToken([
            "id" => $user['id'],
            "name" => $user['name'],
            "role" => $user['role'],
            "school_id" => $user['school_id']
        ]);

        $refreshToken = JwtHelper::generateRefreshToken([
            "id" => $user['id'],
            "name" => $user['name'],
            "role" => $user['role'],
            "school_id" => $user['school_id']
        ]);

        // Refresh token cookie
        setcookie(
            "refresh_token",
            $refreshToken,
            [
                "expires"  => time() + 604800, // 7 days
                "path"     => "/",
                "httponly" => true,
                "secure"   => isset($_SERVER['HTTPS']),
                "samesite" => "Lax"
            ]
        );

        // Format complete school data
        $schoolData = $this->formatSchoolData($user);

        Response::json([
            "message" => "Login successful",
            "access_token" => $accessToken,
            "user" => [
                "id" => (int)$user['id'],
                "name" => $user['name'],
                "email" => $user['email'],
                "role" => $user['role'],
                "must_reset_password" => (bool)$user['must_reset_password'],
                "school" => $schoolData,
                "teacher_id" => $teacherId
            ]
        ]);
    }

    /* =========================
       GET CURRENT USER
    ========================== */
    public function me()
    {
        try {
            $user = JwtHelper::getUserFromToken();

            // Fetch fresh user data with school context
            $userData = $this->user->findById($user['id']);

            if (!$userData) {
                Response::json(["message" => "User not found"], 404);
            }

            // Format complete school data
            $schoolData = $this->formatSchoolData($userData);

            Response::json([
                "id" => (int)$userData['id'],
                "name" => $userData['name'],
                "email" => $userData['email'],
                "role" => $userData['role'],
                "must_reset_password" => (bool)$userData['must_reset_password'],
                "school" => $schoolData
            ]);
        } catch (Exception $e) {
            Response::json(["message" => "Unauthorized"], 401);
        }
    }

    /* =========================
       FORGOT PASSWORD
    ========================== */
    public function forgotPassword()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email'])) {
            Response::json(["message" => "Email is required"], 400);
        }

        $email = $data['email'];
        $user = $this->user->findByEmail($email);

        // Always return success even if user doesn't exist (security through obscurity)
        if (!$user) {
            Response::json(["message" => "If your email exists in our system, you will receive a reset link"], 200);
        }

        // Generate a secure reset token
        $resetToken = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Store token in database
        $stmt = $this->db->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
            token = VALUES(token),
            expires_at = VALUES(expires_at),
            created_at = NOW()
        ");

        $stmt->execute([$user['id'], $resetToken, $expiresAt]);

        // Send email with reset link
        $resetLink = "https://sms.academicprojects.org/reset-password?token=" . $resetToken;

        // You'll need to implement MailHelper
        $mailSent = MailHelper::sendPasswordResetEmail($email, $resetLink);

        if (!$mailSent) {
            // Log error but don't tell user
            error_log("Failed to send password reset email to: " . $email);
        }

        Response::json(["message" => "If your email exists in our system, you will receive a reset link"], 200);
    }

    /* =========================
       VERIFY RESET TOKEN
    ========================== */
    public function verifyResetToken()
    {
        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            Response::json(["message" => "Token is required"], 400);
        }

        // Check if token exists and is not expired
        $stmt = $this->db->prepare("
            SELECT pr.*, u.email 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            Response::json(["message" => "Invalid or expired token"], 400);
        }

        Response::json([
            "message" => "Token is valid",
            "email" => $reset['email']
        ]);
    }

    /* =========================
       RESET PASSWORD
    ========================== */
    public function resetPassword()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['token']) || empty($data['newPassword'])) {
            Response::json(["message" => "Token and new password are required"], 400);
        }

        // Validate password strength
        if (strlen($data['newPassword']) < 8) {
            Response::json(["message" => "Password must be at least 8 characters long"], 400);
        }

        // Get valid token
        $stmt = $this->db->prepare("
            SELECT pr.user_id, u.email 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$data['token']]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            Response::json(["message" => "Invalid or expired token"], 400);
        }

        // Update password
        $hashedPassword = password_hash($data['newPassword'], PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("
            UPDATE users 
            SET password = ?, must_reset_password = 0, password_changed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$hashedPassword, $reset['user_id']]);

        // Delete used token
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE token = ?");
        $stmt->execute([$data['token']]);

        Response::json(["message" => "Password reset successful"]);
    }

    /* =========================
       FORGOT USERNAME
    ========================== */
    public function forgotUsername()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email'])) {
            Response::json(["message" => "Email is required"], 400);
        }

        $email = $data['email'];
        $user = $this->user->findByEmail($email);

        if (!$user) {
            Response::json(["message" => "If your email exists in our system, you will receive your username"], 200);
        }

        // Send email with username
        $mailSent = MailHelper::sendUsernameReminderEmail($email, $user['name']);

        if (!$mailSent) {
            error_log("Failed to send username reminder email to: " . $email);
        }

        Response::json(["message" => "If your email exists in our system, you will receive your username"], 200);
    }

    /* =========================
       LOGOUT
    ========================== */
    public function logout()
    {
        setcookie(
            "refresh_token",
            "",
            [
                "expires"  => time() - 3600,
                "path"     => "/",
                "httponly" => true,
                "secure"   => isset($_SERVER['HTTPS']),
                "samesite" => "Lax"
            ]
        );

        Response::json(["message" => "Logged out successfully"]);
    }

    /* =========================
       FORCE RESET PASSWORD
    ========================== */
    public function forceResetPassword()
    {
        $user = JwtHelper::getUserFromToken();

        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['password'])) {
            Response::json(["message" => "Password required"], 400);
        }

        $hashed = password_hash($data['password'], PASSWORD_BCRYPT);

        $stmt = $this->db->prepare("
            UPDATE users
            SET password = ?, must_reset_password = 0, password_changed_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([$hashed, $user['id']]);

        Response::json(["message" => "Password updated successfully"]);
    }

    /* =========================
       REFRESH TOKEN
    ========================== */
    public function refresh()
    {
        if (!isset($_COOKIE['refresh_token'])) {
            Response::json(["message" => "Refresh token missing"], 401);
        }

        $refreshToken = $_COOKIE['refresh_token'];

        $payload = JwtHelper::verify($refreshToken);

        if (($payload['type'] ?? '') !== 'refresh') {
            Response::json(["message" => "Invalid refresh token"], 401);
        }

        // Issue NEW access token
        $accessToken = JwtHelper::generateAccessToken([
            "id" => $payload['id'],
            "role" => $payload['role'],
            "school_id" => $payload['school_id']
        ]);

        Response::json([
            "access_token" => $accessToken
        ]);
    }
}