<?php
require_once '../../config/config.php';

enableCORS();

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // Get the admin user
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
    $stmt->execute(['admin@khuluma.app']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        // Test password verification
        $testPassword = 'admin123';
        $isValid = password_verify($testPassword, $admin['password_hash']);
        
        // Also test with Auth class method
        $isValidAuth = Auth::verifyPassword($testPassword, $admin['password_hash']);
        
        // Generate new hash for comparison
        $newHash = Auth::hashPassword($testPassword);
        
        Response::success('Password test results', [
            'admin_found' => true,
            'stored_hash' => $admin['password_hash'],
            'test_password' => $testPassword,
            'password_verify_result' => $isValid,
            'auth_verify_result' => $isValidAuth,
            'new_hash_sample' => $newHash,
            'hash_info' => password_get_info($admin['password_hash'])
        ]);
    } else {
        Response::error('Admin user not found');
    }
    
} catch (Exception $e) {
    Response::error('Error: ' . $e->getMessage(), 500);
}
?>
