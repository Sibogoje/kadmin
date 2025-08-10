<?php
require_once '../config/config.php';

enableCORS();

// Simple test endpoint to verify CORS is working
header('Content-Type: application/json');

$response = [
    'success' => true,
    'message' => 'CORS is working correctly!',
    'timestamp' => date('Y-m-d H:i:s'),
    'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'No origin header',
    'method' => $_SERVER['REQUEST_METHOD']
];

echo json_encode($response);
?>
