<?php
// CRON: Close opportunities past their deadline
// Place this file in backend/api/admin/ and set up a cron job to run it daily

require_once __DIR__ . '/../../../backend/api/admin/config/config.php';

try {
    $pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $today = date('Y-m-d');
    $sql = "UPDATE opportunities SET status = 'expired' WHERE status != 'expired' AND deadline IS NOT NULL AND deadline < ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$today]);

    echo "Opportunities past deadline have been closed (expired).\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
