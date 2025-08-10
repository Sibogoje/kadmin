<?php
// Connection Manager for handling database connections efficiently
class ConnectionManager {
    private static $activeConnections = 0;
    private static $maxConnections = 10; // Limit concurrent connections
    
    public static function getConnection() {
        if (self::$activeConnections >= self::$maxConnections) {
            // Wait a bit before trying again
            usleep(100000); // 100ms
        }
        
        try {
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            self::$activeConnections++;
            return $pdo;
        } catch (PDOException $e) {
            // If connection fails due to max connections, wait and retry
            if (strpos($e->getMessage(), 'max_connections_per_hour') !== false) {
                // Log the error
                error_log("Max connections per hour exceeded. Waiting before retry...");
                
                // Wait 5 seconds before retrying
                sleep(5);
                
                // Try once more
                try {
                    $db = Database::getInstance();
                    $pdo = $db->getPdo();
                    self::$activeConnections++;
                    return $pdo;
                } catch (PDOException $retryException) {
                    // If still failing, return error response
                    http_response_code(503);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Database service temporarily unavailable. Please try again in a few minutes.',
                        'error' => 'Connection limit exceeded'
                    ]);
                    exit;
                }
            }
            throw $e;
        }
    }
    
    public static function releaseConnection() {
        if (self::$activeConnections > 0) {
            self::$activeConnections--;
        }
    }
    
    public static function closeAllConnections() {
        Database::closeConnection();
        self::$activeConnections = 0;
    }
    
    public static function getActiveConnectionCount() {
        return self::$activeConnections;
    }
}

// Register shutdown function to clean up connections
register_shutdown_function(function() {
    ConnectionManager::closeAllConnections();
});
?>
