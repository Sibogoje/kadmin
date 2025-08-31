<?php
// CRON: Close opportunities past their deadline
// Place this file in backend/api/admin/ and set up a cron job to run it daily


// Use the shared backend config
require_once '../../config/config.php';

try {
    // Use the global $pdo from config.php
    global $pdo;

    $today = date('Y-m-d');
    $sql = "UPDATE opportunities SET status = 'expired' WHERE status != 'expired' AND deadline IS NOT NULL AND deadline < ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today]);

    echo "Opportunities past deadline have been closed (expired).\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
