<?php
require_once '../../config/config.php';

enableCORS();

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // Generate correct hash for 'admin123'
    $newHash = Auth::hashPassword('admin123');
    
    // Update the admin password
    $stmt = $pdo->prepare("UPDATE admins SET password_hash = ? WHERE email = ?");
    $result = $stmt->execute([$newHash, 'admin@khuluma.app']);
    
    if ($result) {
        // Verify the update worked
        $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE email = ?");
        $stmt->execute(['admin@khuluma.app']);
        $admin = $stmt->fetch();
        
        $isValid = Auth::verifyPassword('admin123', $admin['password_hash']);
        
        Response::success('Password updated successfully', [
            'new_hash' => $newHash,
            'verification_test' => $isValid
        ]);
    } else {
        Response::error('Failed to update password');
    }
    
} catch (Exception $e) {
    Response::error('Error: ' . $e->getMessage(), 500);
}
?>
