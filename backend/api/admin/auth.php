<?php
require_once '../../config/cors.php';
require_once '../../config/config.php';

class AdminAuth {
    private $pdo;
    
    public function __construct() {
        $this->pdo = ConnectionManager::getConnection();
    }
    
    public function __destruct() {
        ConnectionManager::releaseConnection();
    }
    
    public function login() {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['email']) || !isset($input['password'])) {
                Response::error('Email and password are required', 400);
            }
            
            $email = trim($input['email']);
            $password = $input['password'];
            
            if (!Validator::email($email)) {
                Response::error('Invalid email format', 400);
            }
            
            $sql = "SELECT * FROM admins WHERE email = ? AND status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if (!$admin || !Auth::verifyPassword($password, $admin['password_hash'])) {
                Response::error('Invalid credentials', 401);
            }
            
            $payload = [
                'id' => $admin['id'],
                'email' => $admin['email'],
                'username' => $admin['username'],
                'role' => $admin['role'],
                'exp' => time() + JWTConfig::$expiration_time
            ];
            
            $token = Auth::generateToken($payload);
            
            // Log login activity
            Logger::logActivity($this->pdo, $admin['id'], 'admin_login');
            
            Response::success('Login successful', [
                'token' => $token,
                'admin' => [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'email' => $admin['email'],
                    'full_name' => $admin['full_name'],
                    'role' => $admin['role'],
                    'status' => $admin['status'] ?? 'active',
                    'created_at' => $admin['created_at']
                ]
            ]);
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            Response::error('Login failed', 500);
        }
    }
    
    public function register() {
        $auth = Auth::requireAuth();
        
        // Only super_admin can create new admins
        if ($auth['role'] !== 'super_admin') {
            Response::error('Insufficient permissions', 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['username', 'email', 'password', 'full_name'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || !Validator::required($input[$field])) {
                Response::error("$field is required", 400);
            }
        }
        
        $username = trim($input['username']);
        $email = trim($input['email']);
        $password = $input['password'];
        $fullName = trim($input['full_name']);
        $role = $input['role'] ?? 'admin';
        
        if (!Validator::email($email)) {
            Response::error('Invalid email format', 400);
        }
        
        if (!Validator::minLength($password, 6)) {
            Response::error('Password must be at least 6 characters', 400);
        }
        
        if (!in_array($role, ['admin', 'moderator'])) {
            Response::error('Invalid role', 400);
        }
        
        // Check if username or email already exists
        $sql = "SELECT id FROM admins WHERE username = ? OR email = ?";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([$username, $email]);
        
        if ($stmt->fetch()) {
            Response::error('Username or email already exists', 409);
        }
        
        // Create new admin
        $passwordHash = Auth::hashPassword($password);
        $sql = "INSERT INTO admins (username, email, password_hash, full_name, role) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([$username, $email, $passwordHash, $fullName, $role]);
        
        $adminId = $this->db->getPdo()->lastInsertId();
        
        // Log activity
        Logger::logActivity($this->db, $auth['id'], 'admin_created', 'admins', $adminId, null, [
            'username' => $username,
            'email' => $email,
            'role' => $role
        ]);
        
        Response::success('Admin created successfully', ['id' => $adminId]);
    }
    
    public function profile() {
        $auth = Auth::requireAuth();
        
        $sql = "SELECT id, username, email, full_name, role, created_at FROM admins WHERE id = ?";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([$auth['id']]);
        $admin = $stmt->fetch();
        
        if (!$admin) {
            Response::error('Admin not found', 404);
        }
        
        Response::success('Profile retrieved', $admin);
    }
    
    public function updateProfile() {
        $auth = Auth::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        
        $allowedFields = ['full_name', 'email'];
        $updates = [];
        $values = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field]) && Validator::required($input[$field])) {
                if ($field === 'email' && !Validator::email($input[$field])) {
                    Response::error('Invalid email format', 400);
                }
                $updates[] = "$field = ?";
                $values[] = trim($input[$field]);
            }
        }
        
        if (empty($updates)) {
            Response::error('No valid fields to update', 400);
        }
        
        $values[] = $auth['id'];
        $sql = "UPDATE admins SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($values);
        
        Logger::logActivity($this->db, $auth['id'], 'profile_updated', 'admins', $auth['id']);
        
        Response::success('Profile updated successfully');
    }
    
    public function changePassword() {
        $auth = Auth::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['current_password']) || !isset($input['new_password'])) {
            Response::error('Current password and new password are required', 400);
        }
        
        if (!Validator::minLength($input['new_password'], 6)) {
            Response::error('New password must be at least 6 characters', 400);
        }
        
        // Verify current password
        $sql = "SELECT password_hash FROM admins WHERE id = ?";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([$auth['id']]);
        $admin = $stmt->fetch();
        
        if (!Auth::verifyPassword($input['current_password'], $admin['password_hash'])) {
            Response::error('Current password is incorrect', 400);
        }
        
        // Update password
        $newPasswordHash = Auth::hashPassword($input['new_password']);
        $sql = "UPDATE admins SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute([$newPasswordHash, $auth['id']]);
        
        Logger::logActivity($this->db, $auth['id'], 'password_changed', 'admins', $auth['id']);
        
        Response::success('Password changed successfully');
    }
}

// Route handling
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

$adminAuth = new AdminAuth();

switch ("$method:$path") {
    case 'POST:login':
        $adminAuth->login();
        break;
    case 'POST:register':
        $adminAuth->register();
        break;
    case 'GET:profile':
        $adminAuth->profile();
        break;
    case 'PUT:profile':
        $adminAuth->updateProfile();
        break;
    case 'PUT:change-password':
        $adminAuth->changePassword();
        break;
    default:
        Response::error('Endpoint not found', 404);
}
?>
