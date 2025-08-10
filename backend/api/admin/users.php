<?php
require_once '../../config/cors.php';
require_once '../../config/config.php';

header('Content-Type: application/json');

// Get the request method
$method = $_SERVER['REQUEST_METHOD'];

// Get the raw POST data for PUT and DELETE requests
$input = json_decode(file_get_contents('php://input'), true);

// Get action from input or URL parameter
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getAdmin($_GET['id']);
            } else {
                getAdmins();
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createAdmin($input);
            } else {
                throw new Exception('Invalid action for POST request');
            }
            break;
            
        case 'PUT':
            if ($action === 'update') {
                updateAdmin($input);
            } else {
                throw new Exception('Invalid action for PUT request');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                deleteAdmin($input);
            } else {
                throw new Exception('Invalid action for DELETE request');
            }
            break;
            
        default:
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getAdmins() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, phone, full_name, role, status, last_login, created_at, updated_at FROM admins ORDER BY created_at DESC");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'admins' => $admins
        ]);
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch admins: ' . $e->getMessage());
    }
}

function getAdmin($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, username, email, phone, full_name, role, status, last_login, created_at, updated_at FROM admins WHERE id = ?");
        $stmt->execute([$id]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo json_encode([
                'success' => true,
                'admin' => $admin
            ]);
        } else {
            throw new Exception('Admin not found');
        }
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch admin: ' . $e->getMessage());
    }
}

function createAdmin($data) {
    global $pdo;
    
    // Validate required fields
    $requiredFields = ['full_name', 'username', 'email', 'role'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check if username or email already exists
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
    $stmt->execute([$data['username'], $data['email']]);
    if ($stmt->fetch()) {
        throw new Exception('Username or email already exists');
    }
    
    try {
        $password = $data['password'] ?? 'defaultPassword123';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $status = $data['status'] ?? 'active';
        
        $stmt = $pdo->prepare("
            INSERT INTO admins (username, email, phone, full_name, role, status, password_hash, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['username'],
            $data['email'],
            $data['phone'] ?? null,
            $data['full_name'],
            $data['role'],
            $status,
            $hashedPassword
        ]);
        
        $adminId = $pdo->lastInsertId();
        
        // Fetch the created admin
        $stmt = $pdo->prepare("SELECT id, username, email, phone, full_name, role, status, last_login, created_at, updated_at FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin created successfully',
            'admin' => $admin
        ]);
    } catch (PDOException $e) {
        throw new Exception('Failed to create admin: ' . $e->getMessage());
    }
}

function updateAdmin($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        throw new Exception('Admin ID is required');
    }
    
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id FROM admins WHERE id = ?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Admin not found');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Build update query dynamically
        $updateFields = [];
        $updateValues = [];
        
        if (!empty($data['full_name'])) {
            $updateFields[] = "full_name = ?";
            $updateValues[] = $data['full_name'];
        }
        
        if (!empty($data['username'])) {
            // Check if username is already taken by another admin
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
            $stmt->execute([$data['username'], $data['id']]);
            if ($stmt->fetch()) {
                throw new Exception('Username already exists');
            }
            $updateFields[] = "username = ?";
            $updateValues[] = $data['username'];
        }
        
        if (!empty($data['email'])) {
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            // Check if email is already taken by another admin
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $data['id']]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }
            $updateFields[] = "email = ?";
            $updateValues[] = $data['email'];
        }
        
        if (!empty($data['phone'])) {
            $updateFields[] = "phone = ?";
            $updateValues[] = $data['phone'];
        }
        
        if (!empty($data['role'])) {
            $updateFields[] = "role = ?";
            $updateValues[] = $data['role'];
        }
        
        if (!empty($data['status'])) {
            $updateFields[] = "status = ?";
            $updateValues[] = $data['status'];
        }
        
        if (!empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $updateFields[] = "password_hash = ?";
            $updateValues[] = $hashedPassword;
        }
        
        if (empty($updateFields)) {
            throw new Exception('No fields to update');
        }
        
        $updateValues[] = $data['id'];
        $sql = "UPDATE admins SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Fetch the updated admin
        $stmt = $pdo->prepare("SELECT id, username, email, phone, full_name, role, status, last_login, created_at, updated_at FROM admins WHERE id = ?");
        $stmt->execute([$data['id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin updated successfully',
            'admin' => $admin
        ]);
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function deleteAdmin($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        throw new Exception('Admin ID is required');
    }
    
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id, role FROM admins WHERE id = ?");
    $stmt->execute([$data['id']]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        throw new Exception('Admin not found');
    }
    
    // Prevent deletion of the last superadmin
    if ($admin['role'] === 'superadmin') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admins WHERE role = 'superadmin'");
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count <= 1) {
            throw new Exception('Cannot delete the last superadmin');
        }
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Admin deleted successfully'
        ]);
    } catch (PDOException $e) {
        throw new Exception('Failed to delete admin: ' . $e->getMessage());
    }
}
?>
