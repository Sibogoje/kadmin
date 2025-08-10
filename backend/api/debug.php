<?php
require_once '../config/config.php';

enableCORS();

// Debug endpoint to help troubleshoot CORS issues
header('Content-Type: application/json');

$debug_info = [
    'success' => true,
    'message' => 'Debug endpoint working',
    'request_info' => [
        'method' => $_SERVER['REQUEST_METHOD'],
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'No origin',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'No user agent',
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'No content type',
        'headers' => getallheaders()
    ],
    'post_data' => $_POST,
    'input_data' => file_get_contents('php://input'),
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
