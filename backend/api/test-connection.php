<?php
require_once '../config/cors.php';
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    // Test the new connection manager
    $pdo = ConnectionManager::getConnection();
    
    // Simple test query
    $stmt = $pdo->prepare("SELECT 1 as test");
    $stmt->execute();
    $result = $stmt->fetch();
    
    // Release the connection
    ConnectionManager::releaseConnection();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful with new connection manager',
        'test_result' => $result,
        'active_connections' => ConnectionManager::getActiveConnectionCount(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
