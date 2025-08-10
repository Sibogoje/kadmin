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
                getClient($_GET['id']);
            } else {
                getClients();
            }
            break;
            
        case 'POST':
            if ($action === 'create') {
                createClient($input);
            } else {
                throw new Exception('Invalid action for POST request');
            }
            break;
            
        case 'PUT':
            if ($action === 'update') {
                updateClient($input);
            } else {
                throw new Exception('Invalid action for PUT request');
            }
            break;
            
        case 'DELETE':
            if ($action === 'delete') {
                deleteClient($input);
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

function getClients() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, surname, email, phone, date_of_birth, address, status, profile_image, created_at, updated_at, last_login FROM clients ORDER BY created_at DESC");
        $stmt->execute();
        $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'clients' => $clients
        ]);
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch clients: ' . $e->getMessage());
    }
}

function getClient($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT id, name, surname, email, phone, date_of_birth, address, status, profile_image, created_at, updated_at, last_login FROM clients WHERE id = ?");
        $stmt->execute([$id]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($client) {
            echo json_encode([
                'success' => true,
                'client' => $client
            ]);
        } else {
            throw new Exception('Client not found');
        }
    } catch (PDOException $e) {
        throw new Exception('Failed to fetch client: ' . $e->getMessage());
    }
}

function createClient($data) {
    global $pdo;
    
    // Validate required fields
    $requiredFields = ['name', 'surname', 'email'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }
    
    try {
        $password_hash = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : password_hash('defaultPassword123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO clients (name, surname, email, password_hash, phone, date_of_birth, address, status, profile_image, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $data['name'],
            $data['surname'],
            $data['email'],
            $password_hash,
            $data['phone'] ?? null,
            $data['date_of_birth'] ?? null,
            $data['address'] ?? null,
            $data['status'] ?? 'active',
            $data['profile_image'] ?? null
        ]);
        
        $clientId = $pdo->lastInsertId();
        
        // Fetch the created client
        $stmt = $pdo->prepare("SELECT id, name, surname, email, phone, date_of_birth, address, status, profile_image, created_at, updated_at, last_login FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'message' => 'Client created successfully',
            'client' => $client
        ]);
    } catch (PDOException $e) {
        throw new Exception('Failed to create client: ' . $e->getMessage());
    }
}

function updateClient($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        throw new Exception('Client ID is required');
    }
    
    // Check if client exists
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Client not found');
    }
    
    try {
        $pdo->beginTransaction();
        
        // Build update query dynamically
        $updateFields = [];
        $updateValues = [];
        
        if (!empty($data['name'])) {
            $updateFields[] = "name = ?";
            $updateValues[] = $data['name'];
        }
        
        if (!empty($data['surname'])) {
            $updateFields[] = "surname = ?";
            $updateValues[] = $data['surname'];
        }
        
        if (!empty($data['email'])) {
            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            // Check if email is already taken by another client
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $data['id']]);
            if ($stmt->fetch()) {
                throw new Exception('Email already exists');
            }
            $updateFields[] = "email = ?";
            $updateValues[] = $data['email'];
        }
        
        if (isset($data['phone'])) {
            $updateFields[] = "phone = ?";
            $updateValues[] = $data['phone'];
        }
        
        if (isset($data['date_of_birth'])) {
            $updateFields[] = "date_of_birth = ?";
            $updateValues[] = $data['date_of_birth'];
        }
        
        if (isset($data['address'])) {
            $updateFields[] = "address = ?";
            $updateValues[] = $data['address'];
        }
        
        if (!empty($data['status'])) {
            $updateFields[] = "status = ?";
            $updateValues[] = $data['status'];
        }
        
        if (isset($data['profile_image'])) {
            $updateFields[] = "profile_image = ?";
            $updateValues[] = $data['profile_image'];
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
        $sql = "UPDATE clients SET " . implode(', ', $updateFields) . " WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Fetch the updated client
        $stmt = $pdo->prepare("SELECT id, name, surname, email, phone, date_of_birth, address, status, profile_image, created_at, updated_at, last_login FROM clients WHERE id = ?");
        $stmt->execute([$data['id']]);
        $client = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Client updated successfully',
            'client' => $client
        ]);
    } catch (Exception $e) {
        $pdo->rollback();
        throw $e;
    }
}

function deleteClient($data) {
    global $pdo;
    
    if (empty($data['id'])) {
        throw new Exception('Client ID is required');
    }
    
    // Check if client exists
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        throw new Exception('Client not found');
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$data['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Client deleted successfully'
        ]);
    } catch (PDOException $e) {
        throw new Exception('Failed to delete client: ' . $e->getMessage());
    }
}
?>
