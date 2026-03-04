<?php
// Helpers/MailHelper.php

class MailHelper
{
    private static $fromEmail = 'no-reply@academicprojects.org';
    private static $fromName = 'School Management System';
    private static $apiUrl = 'https://academicprojects.org/api/send-email.php';

    public static function sendPasswordResetEmail($to, $resetLink)
    {
        $subject = "Password Reset Request - School Management System";
        $message = self::getPasswordResetTemplate($resetLink);
        
        return self::sendMail($to, $subject, $message);
    }

    public static function sendUsernameReminderEmail($to, $username)
    {
        $subject = "Username Reminder - School Management System";
        $message = self::getUsernameReminderTemplate($username);
        
        return self::sendMail($to, $subject, $message);
    }

    private static function sendMail($to, $subject, $message)
    {
        // Check if we're in local development
        $isLocalhost = ($_SERVER['HTTP_HOST'] ?? 'localhost') === 'localhost' || 
                       ($_SERVER['SERVER_NAME'] ?? 'localhost') === 'localhost' ||
                       !isset($_SERVER['SERVER_NAME']);
        
        if ($isLocalhost) {
            // LOCAL DEVELOPMENT - Just log emails
            return self::logEmailForDevelopment($to, $subject, $message);
        } else {
            // PRODUCTION - Use the API
            return self::sendMailViaApi($to, $subject, $message);
        }
    }

    private static function logEmailForDevelopment($to, $subject, $message)
    {
        // Create logs directory
        $logDir = __DIR__ . '/../../logs/emails';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        // Create a filename with timestamp
        $filename = $logDir . '/' . date('Y-m-d_H-i-s') . '_' . preg_replace('/[^a-z0-9]/i', '_', $to) . '.html';
        
        // Create the email content with headers
        $emailContent = "<!DOCTYPE html>
<html>
<head>
    <title>Email Log - Development Mode</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f0f8ff; }
        .dev-badge { 
            background: #ff6b6b; 
            color: white; 
            padding: 10px; 
            border-radius: 5px; 
            text-align: center;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .header { background: #f0f0f0; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .label { font-weight: bold; color: #2c3e50; width: 100px; display: inline-block; }
        .value { margin-bottom: 10px; }
        .content { border: 2px dashed #4CAF50; padding: 20px; border-radius: 5px; background: white; }
    </style>
</head>
<body>
    <div class='dev-badge'>
        🔧 DEVELOPMENT MODE - Email Logged (Not Sent)
    </div>
    <div class='header'>
        <div class='value'><span class='label'>To:</span> {$to}</div>
        <div class='value'><span class='label'>Subject:</span> {$subject}</div>
        <div class='value'><span class='label'>Date:</span> " . date('Y-m-d H:i:s') . "</div>
        <div class='value'><span class='label'>From:</span> " . self::$fromName . " &lt;" . self::$fromEmail . "&gt;</div>
        <div class='value'><span class='label'>Reset Link:</span> " . self::extractResetLink($message) . "</div>
    </div>
    <div class='content'>
        {$message}
    </div>
</body>
</html>";
        
        // Save to file
        file_put_contents($filename, $emailContent);
        
        // Also log to PHP error log with clickable path
        error_log("📧 DEV MODE: Email logged to file:///" . str_replace('\\', '/', $filename));
        error_log("📧 To: {$to} | Subject: {$subject}");
        
        return true; // Always return true in development
    }

    private static function sendMailViaApi($to, $subject, $message)
    {
        // Prepare the data for the API
        $data = [
            'order_id' => 'reset_' . time() . '_' . rand(1000, 9999),
            'project_id' => 0,
            'customer_email' => $to,
            'customer_name' => self::extractNameFromEmail($to),
            'email_type' => 'password_reset',
            'reset_link' => self::extractResetLink($message),
            'custom_message' => $message,
            'custom_subject' => $subject
        ];

        // Use file_get_contents with stream context (more reliable than cURL on some hosts)
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($data),
                'timeout' => 30,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ];
        
        $context = stream_context_create($options);
        
        try {
            $response = @file_get_contents(self::$apiUrl, false, $context);
            
            // Check if we got a response
            if ($response === false) {
                $error = error_get_last();
                error_log("❌ API request failed: " . ($error['message'] ?? 'Unknown error'));
                return self::productionFallback($to, $subject, $message);
            }
            
            $result = json_decode($response, true);
            $httpCode = self::getHttpCodeFromStreamResponse($http_response_header ?? []);
            
            // Log the attempt
            self::logApiAttempt($to, $subject, $httpCode, $response);
            
            // Check if successful
            if ($httpCode === 200 && isset($result['success']) && $result['success'] === true) {
                error_log("✅ Password reset email sent successfully via API to: " . $to);
                return true;
            } else {
                error_log("❌ API returned error: " . ($result['error'] ?? 'Unknown error'));
                return self::productionFallback($to, $subject, $message);
            }
            
        } catch (Exception $e) {
            error_log("❌ Exception in API call: " . $e->getMessage());
            return self::productionFallback($to, $subject, $message);
        }
    }

    private static function productionFallback($to, $subject, $message)
    {
        error_log("⚠️ Using production fallback for: " . $to);
        
        // For GoDaddy production, use mail() with proper settings
        $headers = "From: " . self::$fromName . " <" . self::$fromEmail . ">\r\n";
        $headers .= "Reply-To: " . self::$fromEmail . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        // Set GoDaddy SMTP settings
        ini_set('SMTP', 'localhost');
        ini_set('smtp_port', '25');
        ini_set('sendmail_from', self::$fromEmail);
        
        if (mail($to, $subject, $message, $headers)) {
            error_log("✅ Production fallback mail sent to: " . $to);
            return true;
        } else {
            $error = error_get_last();
            error_log("❌ Production fallback mail failed: " . ($error['message'] ?? 'Unknown error'));
            return false;
        }
    }

    private static function getHttpCodeFromStreamResponse($headers)
    {
        if (empty($headers)) {
            return 0;
        }
        
        preg_match('/HTTP\/[\d.]+ (\d+)/', $headers[0], $matches);
        return isset($matches[1]) ? (int)$matches[1] : 0;
    }

    private static function logApiAttempt($to, $subject, $httpCode, $response)
    {
        $logDir = __DIR__ . '/../../logs/emails';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/api_attempts.log';
        $logEntry = date('Y-m-d H:i:s') . " | To: $to | Subject: $subject | HTTP: $httpCode | Response: " . substr($response, 0, 200) . "\n";
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    private static function extractNameFromEmail($email)
    {
        $parts = explode('@', $email);
        $name = $parts[0];
        $name = str_replace(['.', '_'], ' ', $name);
        $name = ucwords($name);
        return $name;
    }

    private static function extractResetLink($message)
    {
        preg_match('/<a href="([^"]+)" class=\'button\'/', $message, $matches);
        return $matches[1] ?? '';
    }

    private static function getPasswordResetTemplate($resetLink)
    {
        return "
        <html>
        <head>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); padding: 30px; text-align: center; }
                .header h1 { color: white; margin: 0; font-size: 24px; }
                .content { padding: 40px 30px; }
                .button { 
                    display: inline-block; 
                    padding: 12px 30px; 
                    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%);
                    color: white; 
                    text-decoration: none; 
                    border-radius: 5px;
                    font-weight: bold;
                    margin: 20px 0;
                }
                .footer { 
                    background: #f8fafc; 
                    padding: 20px; 
                    text-align: center; 
                    color: #64748b; 
                    font-size: 14px;
                    border-top: 1px solid #e2e8f0;
                }
                .warning { 
                    background: #fff3cd; 
                    border: 1px solid #ffe69c; 
                    color: #856404; 
                    padding: 10px; 
                    border-radius: 5px; 
                    font-size: 14px;
                    margin-top: 20px;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>We received a request to reset your password for your School Management System account. Click the button below to proceed:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Reset Password</a>
                    </div>
                    
                    <div class='warning'>
                        ⚠️ This link will expire in <strong>1 hour</strong> for security reasons.
                    </div>
                    
                    <p>If you didn't request this password reset, please ignore this email or contact your system administrator if you have concerns.</p>
                    
                    <p>For security, please:</p>
                    <ul>
                        <li>Never share this link with anyone</li>
                        <li>Choose a strong password that you don't use elsewhere</li>
                        <li>Enable two-factor authentication if available</li>
                    </ul>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " School Management System. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }

    private static function getUsernameReminderTemplate($username)
    {
        return "
        <html>
        <head>
            <title>Username Reminder</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 30px; text-align: center; }
                .header h1 { color: white; margin: 0; font-size: 24px; }
                .content { padding: 40px 30px; }
                .username-box { 
                    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
                    padding: 20px; 
                    border-radius: 10px; 
                    font-size: 24px;
                    font-weight: bold;
                    text-align: center;
                    letter-spacing: 1px;
                    border: 2px dashed #10b981;
                    margin: 20px 0;
                }
                .footer { 
                    background: #f8fafc; 
                    padding: 20px; 
                    text-align: center; 
                    color: #64748b; 
                    font-size: 14px;
                    border-top: 1px solid #e2e8f0;
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>👤 Username Reminder</h1>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>Here's your username for the School Management System:</p>
                    
                    <div class='username-box'>
                        {$username}
                    </div>
                    
                    <p>You can now login using this username and your password.</p>
                    
                    <p>If you didn't request this information, please contact your system administrator immediately.</p>
                </div>
                <div class='footer'>
                    <p>© " . date('Y') . " School Management System. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}