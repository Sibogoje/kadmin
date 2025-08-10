<?php
require_once '../config/config.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // Read and execute the SQL file
    $sql = file_get_contents('create_documents_table.sql');
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Documents table created successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to create documents table',
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
