<?php
// Simple test to debug the documents API
require_once 'config/cors.php';
require_once 'config/config.php';

echo "Testing document API dependencies...\n";

// Check if classes exist
echo "ConnectionManager exists: " . (class_exists('ConnectionManager') ? 'YES' : 'NO') . "\n";
echo "Auth exists: " . (class_exists('Auth') ? 'YES' : 'NO') . "\n";
echo "Response exists: " . (class_exists('Response') ? 'YES' : 'NO') . "\n";
echo "Logger exists: " . (class_exists('Logger') ? 'YES' : 'NO') . "\n";

// Test connection
try {
    $pdo = ConnectionManager::getConnection();
    echo "Database connection: SUCCESS\n";
    
    // Test if documents table exists
    $stmt = $pdo->query("DESCRIBE documents");
    echo "Documents table exists: YES\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}

// Test the specific URL structure
echo "\nTesting request simulation:\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['action'] = 'upload';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

echo "Method: $method\n";
echo "Action: $action\n";
echo "Combined: $method:$action\n";

switch ("$method:$action") {
    case 'POST:upload':
        echo "Route matches POST:upload - SUCCESS\n";
        break;
    default:
        echo "Route does NOT match - this would cause 'Endpoint not found'\n";
}
?>
