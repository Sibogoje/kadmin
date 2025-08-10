<?php
require_once '../../config/config.php';

enableCORS();

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // Test database connection and check if admin table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'admins'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        // Check admin users
        $stmt = $pdo->query("SELECT id, email, full_name, role, created_at FROM admins");
        $admins = $stmt->fetchAll();
        
        Response::success('Database connection successful', [
            'admins_table_exists' => true,
            'admin_users' => $admins,
            'server_time' => date('Y-m-d H:i:s')
        ]);
    } else {
        Response::error('Admins table does not exist', 500);
    }
    
} catch (Exception $e) {
    Response::error('Database error: ' . $e->getMessage(), 500);
}
?>
