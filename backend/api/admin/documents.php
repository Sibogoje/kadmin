<?php
require_once '../../config/cors.php';
require_once '../../config/config.php';

class DocumentManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = ConnectionManager::getConnection();
    }
    
    public function __destruct() {
        ConnectionManager::releaseConnection();
    }
    
    public function getClientDocuments($clientId) {
        $auth = Auth::requireAuth();
        
        $sql = "SELECT d.*, c.name as client_name 
                FROM documents d 
                JOIN clients c ON d.client_id = c.id 
                WHERE d.client_id = ? 
                ORDER BY d.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$clientId]);
        $documents = $stmt->fetchAll();
        
        Response::success('Documents retrieved', $documents);
    }
    
    public function getAllDocuments() {
        $auth = Auth::requireAuth();
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $clientId = $_GET['client_id'] ?? '';
        $type = $_GET['type'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $offset = ($page - 1) * $limit;
        
        // Build WHERE clause
        $whereConditions = [];
        $params = [];
        
        if ($clientId) {
            $whereConditions[] = "d.client_id = ?";
            $params[] = $clientId;
        }
        
        if ($type) {
            $whereConditions[] = "d.document_type = ?";
            $params[] = $type;
        }
        
        if ($search) {
            $whereConditions[] = "(d.document_name LIKE ? OR d.description LIKE ? OR c.name LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total 
                     FROM documents d 
                     JOIN clients c ON d.client_id = c.id 
                     $whereClause";
        
        $stmt = $this->pdo->prepare($countSql);
        $stmt->execute($params);
        $totalCount = $stmt->fetch()['total'];
        
        // Get documents
        $sql = "SELECT d.*, c.name as client_name 
                FROM documents d 
                JOIN clients c ON d.client_id = c.id 
                $whereClause 
                ORDER BY d.created_at DESC 
                LIMIT $limit OFFSET $offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $documents = $stmt->fetchAll();
        
        Response::success('Documents retrieved', [
            'documents' => $documents,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($totalCount / $limit),
                'total_items' => $totalCount,
                'items_per_page' => $limit
            ]
        ]);
    }
    
    public function uploadDocument() {
        error_log("Documents API - Starting uploadDocument method");
        
        try {
            $auth = Auth::requireAuth();
            error_log("Documents API - Auth successful: " . json_encode($auth));
        } catch (Exception $e) {
            error_log("Documents API - Auth failed: " . $e->getMessage());
            Response::error('Authentication failed: ' . $e->getMessage(), 401);
            return;
        }
        
        error_log("Documents API - Checking file upload and client_id");
        error_log("Documents API - FILES: " . json_encode($_FILES));
        error_log("Documents API - POST: " . json_encode($_POST));
        
        if (!isset($_FILES['document']) || !isset($_POST['client_id'])) {
            error_log("Documents API - Missing required fields");
            Response::error('Document file and client ID are required', 400);
            return;
        }
        
        $clientId = (int)$_POST['client_id'];
        $documentName = $_POST['document_name'] ?? $_FILES['document']['name'];
        $documentType = $_POST['document_type'] ?? 'other';
        $description = $_POST['description'] ?? '';
        
        error_log("Documents API - Processing upload for client $clientId");
        
        // Validate client exists
        try {
            $sql = "SELECT id FROM clients WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$clientId]);
            if (!$stmt->fetch()) {
                error_log("Documents API - Client $clientId not found");
                Response::error('Client not found', 404);
                return;
            }
            error_log("Documents API - Client $clientId exists");
        } catch (Exception $e) {
            error_log("Documents API - Error checking client: " . $e->getMessage());
            Response::error('Error checking client: ' . $e->getMessage(), 500);
            return;
        }
        
        try {
            error_log("Documents API - Starting file upload");
            // Upload file
            $filename = DocumentUpload::uploadDocument($_FILES['document']);
            error_log("Documents API - File uploaded successfully: $filename");
            
            // Save to database
            $sql = "INSERT INTO documents (client_id, document_name, document_type, file_path, file_size, description, uploaded_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                $clientId,
                $documentName,
                $documentType,
                $filename,
                $_FILES['document']['size'],
                $description,
                $auth['id']
            ]);
            
            if (!$result) {
                error_log("Documents API - Failed to insert into database");
                Response::error('Failed to save document to database', 500);
                return;
            }
            
            $documentId = $this->pdo->lastInsertId();
            error_log("Documents API - Document saved with ID: $documentId");
            
            // Log activity
            try {
                Logger::logActivity($this->pdo, $auth['id'], 'document_uploaded', 'documents', $documentId, null, [
                    'client_id' => $clientId,
                    'document_name' => $documentName,
                    'document_type' => $documentType,
                    'file_size' => $_FILES['document']['size']
                ]);
                error_log("Documents API - Activity logged successfully");
            } catch (Exception $e) {
                error_log("Documents API - Failed to log activity: " . $e->getMessage());
                // Don't fail the upload if logging fails
            }
            
            error_log("Documents API - Upload completed successfully");
            Response::success('Document uploaded successfully', [
                'document_id' => $documentId,
                'filename' => $filename
            ]);
            
        } catch (Exception $e) {
            error_log("Documents API - Exception in upload process: " . $e->getMessage());
            error_log("Documents API - Exception trace: " . $e->getTraceAsString());
            Response::error('Failed to upload document: ' . $e->getMessage(), 500);
        }
    }
    
    public function downloadDocument($documentId) {
        $auth = Auth::requireAuth();
        
        $sql = "SELECT d.*, c.name as client_name 
                FROM documents d 
                JOIN clients c ON d.client_id = c.id 
                WHERE d.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        
        if (!$document) {
            Response::error('Document not found', 404);
        }
        
        $filepath = DocumentUpload::getUploadDir() . $document['file_path'];
        
        if (!file_exists($filepath)) {
            Response::error('File not found on server', 404);
        }
        
        // Log download activity
        Logger::logActivity($this->pdo, $auth['id'], 'document_downloaded', 'documents', $documentId);
        
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $document['document_name'] . '"');
        header('Content-Length: ' . filesize($filepath));
        
        // Output file
        readfile($filepath);
        exit;
    }
    
    public function deleteDocument($documentId) {
        $auth = Auth::requireAuth();
        
        $sql = "SELECT * FROM documents WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documentId]);
        $document = $stmt->fetch();
        
        if (!$document) {
            Response::error('Document not found', 404);
        }
        
        // Delete file from disk
        $filepath = DocumentUpload::getUploadDir() . $document['file_path'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        // Delete from database
        $sql = "DELETE FROM documents WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documentId]);
        
        // Log activity
        Logger::logActivity($this->pdo, $auth['id'], 'document_deleted', 'documents', $documentId, $document);
        
        Response::success('Document deleted successfully');
    }
    
    public function updateDocument() {
        $auth = Auth::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['id', 'document_name', 'document_type'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || !Validator::required($input[$field])) {
                Response::error("$field is required", 400);
            }
        }
        
        $id = (int)$input['id'];
        $documentName = trim($input['document_name']);
        $documentType = trim($input['document_type']);
        $description = trim($input['description'] ?? '');
        
        // Get current document
        $sql = "SELECT * FROM documents WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $oldDocument = $stmt->fetch();
        
        if (!$oldDocument) {
            Response::error('Document not found', 404);
        }
        
        // Update document
        $sql = "UPDATE documents SET 
                document_name = ?, 
                document_type = ?, 
                description = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$documentName, $documentType, $description, $id]);
        
        // Log activity
        $newValues = [
            'document_name' => $documentName,
            'document_type' => $documentType,
            'description' => $description
        ];
        
        Logger::logActivity($this->pdo, $auth['id'], 'document_updated', 'documents', $id, $oldDocument, $newValues);
        
        Response::success('Document updated successfully');
    }
}

// Document Upload Helper (extends FileUpload for documents)
class DocumentUpload {
    private static $uploadDir = '../../uploads/documents/';
    private static $allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'image/jpeg',
        'image/png',
        'image/gif'
    ];
    private static $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    public static function uploadDocument($file) {
        error_log("DocumentUpload - Starting file upload validation");
        error_log("DocumentUpload - File details: " . json_encode($file));
        
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            error_log("DocumentUpload - No valid file uploaded");
            throw new Exception('No file uploaded');
        }
        
        if ($file['size'] > self::$maxFileSize) {
            error_log("DocumentUpload - File too large: " . $file['size'] . " bytes");
            throw new Exception('File too large. Maximum size is 10MB');
        }
        
        // More lenient MIME type checking - also check by extension
        $isValidMimeType = in_array($file['type'], self::$allowedTypes);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
        $isValidExtension = in_array($extension, $allowedExtensions);
        
        error_log("DocumentUpload - MIME type: " . $file['type'] . ", Extension: $extension");
        error_log("DocumentUpload - Valid MIME: " . ($isValidMimeType ? 'YES' : 'NO') . ", Valid Extension: " . ($isValidExtension ? 'YES' : 'NO'));
        
        if (!$isValidMimeType && !$isValidExtension) {
            error_log("DocumentUpload - Invalid file type and extension");
            throw new Exception('Invalid file type. Allowed types: PDF, Word, Excel, Text, Images');
        }
        
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $filepath = self::$uploadDir . $filename;
        
        error_log("DocumentUpload - Target filepath: $filepath");
        
        if (!file_exists(self::$uploadDir)) {
            error_log("DocumentUpload - Creating upload directory");
            mkdir(self::$uploadDir, 0755, true);
        }
        
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log("DocumentUpload - Failed to move uploaded file");
            throw new Exception('Failed to upload file');
        }
        
        error_log("DocumentUpload - File uploaded successfully: $filename");
        return $filename;
    }
    
    public static function getUploadDir() {
        return self::$uploadDir;
    }
}

// Route handling
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? '';

// Debug logging
error_log("Documents API - Method: $method, Action: $action, Combined: $method:$action");

try {
    $documentManager = new DocumentManager();
    error_log("Documents API - DocumentManager created successfully");
} catch (Exception $e) {
    error_log("Documents API - Failed to create DocumentManager: " . $e->getMessage());
    Response::error('Failed to initialize document manager', 500);
    exit;
}

try {
    switch ("$method:$action") {
        case 'GET:client':
            error_log("Documents API - Handling GET:client");
            if (!$id) {
                Response::error('Client ID is required', 400);
            }
            $documentManager->getClientDocuments($id);
            break;
            
        case 'GET:':
        case 'GET:list':
            error_log("Documents API - Handling GET:list");
            $documentManager->getAllDocuments();
            break;
            
        case 'POST:upload':
            error_log("Documents API - Handling POST:upload");
            $documentManager->uploadDocument();
            break;
            
        case 'GET:download':
            error_log("Documents API - Handling GET:download");
            if (!$id) {
                Response::error('Document ID is required', 400);
            }
            $documentManager->downloadDocument($id);
            break;
            
        case 'PUT:update':
            error_log("Documents API - Handling PUT:update");
            $documentManager->updateDocument();
            break;
            
        case 'DELETE:delete':
            error_log("Documents API - Handling DELETE:delete");
            if (!$id) {
                Response::error('Document ID is required', 400);
            }
            $documentManager->deleteDocument($id);
            break;
            
        default:
            error_log("Documents API - No matching route for: $method:$action");
            Response::error('Endpoint not found', 404);
    }
} catch (Exception $e) {
    error_log("Document API Error: " . $e->getMessage());
    Response::error('Internal server error', 500);
}
?>
