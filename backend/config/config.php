<?php
// Database Configuration with Connection Pool
class Database {
    private $host = 'srv1212.hstgr.io';
    private $database = 'u747325399_khulumaApp';
    private $username = 'u747325399_khulumaApp';
    private $password = '5is~l4oCm>N';
    private $charset = 'utf8mb4';
    private static $instance = null;
    private static $pdo = null;
    
    private function __construct() {
        // Private constructor to prevent multiple instances
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function connect() {
        if (self::$pdo === null) {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true, // Enable persistent connections
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];
            
            try {
                self::$pdo = new PDO($dsn, $this->username, $this->password, $options);
            } catch (PDOException $e) {
                throw new PDOException($e->getMessage(), (int)$e->getCode());
            }
        }
        return self::$pdo;
    }
    
    public function getPdo() {
        return $this->connect();
    }
    
    // Close connection (optional - mainly for cleanup)
    public static function closeConnection() {
        self::$pdo = null;
    }
}

// Global PDO instance for backward compatibility using singleton pattern
$db = Database::getInstance();
$pdo = $db->getPdo();

// Include connection manager for better connection handling
require_once __DIR__ . '/../utils/connection_manager.php';

// JWT Configuration
class JWTConfig {
    public static $secret_key = "your-secret-key-here-change-in-production";
    public static $issuer = "khuluma-app";
    public static $audience = "khuluma-app-users";
    public static $expiration_time = 3600; // 1 hour
}

// CORS Configuration
function enableCORS() {
    // Get the current request origin
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    // For development, allow all localhost and 127.0.0.1 origins
    if (strpos($origin, 'http://localhost:') === 0 || 
        strpos($origin, 'http://127.0.0.1:') === 0 || 
        strpos($origin, 'https://localhost:') === 0 || 
        strpos($origin, 'https://127.0.0.1:') === 0) {
        header("Access-Control-Allow-Origin: $origin");
    } else if ($origin === '') {
        // For direct browser access
        header("Access-Control-Allow-Origin: *");
    }
    
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400"); // 24 hours
    
    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit(0);
    }
}

// Response Helper
class Response {
    public static function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }
    
    public static function success($message = 'Success', $data = null) {
        self::json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }
    
    public static function error($message = 'Error', $status = 400, $errors = null) {
        self::json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ], $status);
    }
}

// Input Validation
class Validator {
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
    
    public static function required($value) {
        return !empty(trim($value));
    }
    
    public static function minLength($value, $length) {
        return strlen(trim($value)) >= $length;
    }
    
    public static function maxLength($value, $length) {
        return strlen(trim($value)) <= $length;
    }
}

// Authentication Helper
class Auth {
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateToken($payload) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $headerEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $payloadEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $headerEncoded . "." . $payloadEncoded, JWTConfig::$secret_key, true);
        $signatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $headerEncoded . "." . $payloadEncoded . "." . $signatureEncoded;
    }
    
    public static function verifyToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        $header = $parts[0];
        $payload = $parts[1];
        $signature = $parts[2];
        
        $expectedSignature = hash_hmac('sha256', $header . "." . $payload, JWTConfig::$secret_key, true);
        $expectedSignatureEncoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSignature));
        
        if ($signature !== $expectedSignatureEncoded) {
            return false;
        }
        
        $payloadData = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if (isset($payloadData['exp']) && $payloadData['exp'] < time()) {
            return false;
        }
        
        return $payloadData;
    }
    
    public static function requireAuth() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!$authHeader || !preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Response::error('Access token required', 401);
        }
        
        $token = $matches[1];
        $payload = self::verifyToken($token);
        
        if (!$payload) {
            Response::error('Invalid or expired token', 401);
        }
        
        return $payload;
    }
}

// File Upload Helper
class FileUpload {
    private static $uploadDir = '../uploads/';
    private static $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private static $maxFileSize = 5 * 1024 * 1024; // 5MB
    
    public static function uploadImage($file) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('No file uploaded');
        }
        
        if ($file['size'] > self::$maxFileSize) {
            throw new Exception('File too large. Maximum size is 5MB');
        }
        
        if (!in_array($file['type'], self::$allowedTypes)) {
            throw new Exception('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed');
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = self::$uploadDir . $filename;
        
        if (!file_exists(self::$uploadDir)) {
            mkdir(self::$uploadDir, 0755, true);
        }
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Failed to upload file');
        }
        
        return $filename;
    }
}

// Logging
class Logger {
    public static function logActivity($dbOrPdo, $adminId, $action, $tableName = null, $recordId = null, $oldValues = null, $newValues = null) {
        try {
            $sql = "INSERT INTO admin_activity_log (admin_id, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            // Handle both Database object and PDO object
            if ($dbOrPdo instanceof PDO) {
                $pdo = $dbOrPdo;
            } else {
                $pdo = $dbOrPdo->getPdo();
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $adminId,
                $action,
                $tableName,
                $recordId,
                $oldValues ? json_encode($oldValues) : null,
                $newValues ? json_encode($newValues) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Log the error but don't break the main functionality
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }
}
?>
