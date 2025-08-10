<?php
// CORS configuration for API endpoints

// Allow requests from any origin (for development)
// In production, replace '*' with your specific domain
header('Access-Control-Allow-Origin: *');

// Allow specific HTTP methods
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// Allow specific headers
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Accept, Origin');

// Allow credentials (if needed)
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Set maximum age for preflight cache
    header('Access-Control-Max-Age: 86400'); // 24 hours
    
    // Exit early for preflight requests
    http_response_code(200);
    exit(0);
}

// Set content type for JSON responses
header('Content-Type: application/json; charset=utf-8');
?>
