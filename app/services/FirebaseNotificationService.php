<?php
// services/FirebaseNotificationService.php

class FirebaseNotificationService
{
    private $fcmUrl = 'https://fcm.googleapis.com/v1/projects/school-management-app-9dc59/messages:send';
    private $serviceAccountPath;
    private $projectId = 'school-management-app-9dc59';
    
    public function __construct()
    {
        // Fix the path - use correct directory separator
        $this->serviceAccountPath = __DIR__ . '/service-account.json';
        
        // Log the path for debugging
        error_log("Looking for service account at: " . $this->serviceAccountPath);
    }
    
    /**
     * Get OAuth 2.0 access token using service account
     */
    private function getAccessToken()
    {
        // Check if file exists
        if (!file_exists($this->serviceAccountPath)) {
            error_log("❌ Service account file not found at: " . $this->serviceAccountPath);
            return null;
        }
        
        // Load the service account JSON
        $fileContent = file_get_contents($this->serviceAccountPath);
        if (!$fileContent) {
            error_log("❌ Failed to read service account file");
            return null;
        }
        
        $serviceAccount = json_decode($fileContent, true);
        if (!$serviceAccount) {
            error_log("❌ Failed to parse service account JSON");
            return null;
        }
        
        // Create JWT header
        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        
        // Create JWT claim set
        $now = time();
        $claimSet = json_encode([
            'iss' => $serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600,
            'iat' => $now
        ]);
        
        // Encode header and claim
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlClaim = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($claimSet));
        
        // Create signature
        $signature = '';
        $privateKey = $serviceAccount['private_key'];
        
        // Ensure private key is properly formatted
        if (!openssl_sign($base64UrlHeader . '.' . $base64UrlClaim, $signature, $privateKey, 'SHA256')) {
            error_log("❌ Failed to create signature with private key");
            return null;
        }
        
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        // Create JWT
        $jwt = $base64UrlHeader . '.' . $base64UrlClaim . '.' . $base64UrlSignature;
        
        // Exchange JWT for access token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            // curl_close($ch);
            error_log("❌ CURL error: " . $error);
            return null;
        }
        
        // curl_close($ch);
        
        $tokenData = json_decode($response, true);
        
        if (isset($tokenData['error'])) {
            error_log("❌ Token error: " . json_encode($tokenData['error']));
            return null;
        }
        
        if (!isset($tokenData['access_token'])) {
            error_log("❌ No access token in response: " . $response);
            return null;
        }
        
        error_log("✅ Access token obtained successfully");
        return $tokenData['access_token'];
    }
    
    /**
     * Send notification to a device using FCM v1 API
     */
    public function sendToDevice($deviceToken, $title, $body, $data = [])
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            error_log("❌ Failed to get access token");
            return [
                'success' => false,
                'error' => 'Failed to get access token',
                'http_code' => 0
            ];
        }
        
        $message = [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body
                ],
                'data' => array_merge($data, [
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                    'screen' => $data['screen'] ?? 'home',
                ]),
                'android' => [
                    'priority' => 'high'
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10'
                    ]
                ]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local development
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            // curl_close($ch);
            error_log("❌ CURL error sending notification: " . $error);
            return [
                'success' => false,
                'error' => $error,
                'http_code' => 0
            ];
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200) {
            error_log("✅ Notification sent successfully");
        } else {
            error_log("❌ FCM error (HTTP $httpCode): " . $response);
        }
        
        return [
            'success' => $httpCode === 200,
            'response' => $responseData,
            'http_code' => $httpCode
        ];
    }
    
    /**
     * Send to multiple devices (batch)
     */
    public function sendToMultipleDevices($deviceTokens, $title, $body, $data = [])
    {
        if (empty($deviceTokens)) {
            return [
                'success' => 0,
                'failed' => 0,
                'details' => []
            ];
        }
        
        $results = [];
        $successCount = 0;
        $failedCount = 0;
        
        foreach ($deviceTokens as $token) {
            $result = $this->sendToDevice($token, $title, $body, $data);
            $results[] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
            }
            
            usleep(100000); // 100ms delay to avoid rate limiting
        }
        
        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'details' => $results
        ];
    }
}