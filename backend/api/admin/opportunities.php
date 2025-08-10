<?php
require_once '../../config/cors.php';
require_once '../../config/config.php';

class OpportunityManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = ConnectionManager::getConnection();
    }
    
    public function __destruct() {
        ConnectionManager::releaseConnection();
    }
    
    public function getAll() {
        $auth = Auth::requireAuth();
        
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 20);
        $status = $_GET['status'] ?? '';
        $category = $_GET['category'] ?? '';
        $type = $_GET['type'] ?? '';
        $search = $_GET['search'] ?? '';
        
        $offset = ($page - 1) * $limit;
        
        $whereConditions = [];
        $params = [];
        
        if ($status) {
            $whereConditions[] = "o.status = ?";
            $params[] = $status;
        }
        
        if ($category) {
            $whereConditions[] = "o.category_id = ?";
            $params[] = $category;
        }
        
        if ($type) {
            $whereConditions[] = "o.type = ?";
            $params[] = $type;
        }
        
        if ($search) {
            $whereConditions[] = "(o.title LIKE ? OR o.description LIKE ? OR o.company_name LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM opportunities o $whereClause";
        $countStmt = $this->pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Get opportunities
        $sql = "SELECT o.*, c.name as category_name, a.full_name as admin_name 
                FROM opportunities o 
                LEFT JOIN categories c ON o.category_id = c.id 
                LEFT JOIN admins a ON o.admin_id = a.id 
                $whereClause 
                ORDER BY o.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $opportunities = $stmt->fetchAll();
        
        Response::success('Opportunities retrieved', [
            'opportunities' => $opportunities,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => ceil($total / $limit),
                'total_items' => $total,
                'items_per_page' => $limit
            ]
        ]);
    }
    
    public function getById($id) {
        $auth = Auth::requireAuth();
        
        $sql = "SELECT o.*, c.name as category_name, a.full_name as admin_name 
                FROM opportunities o 
                LEFT JOIN categories c ON o.category_id = c.id 
                LEFT JOIN admins a ON o.admin_id = a.id 
                WHERE o.id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $opportunity = $stmt->fetch();
        
        if (!$opportunity) {
            Response::error('Opportunity not found', 404);
        }
        
        Response::success('Opportunity retrieved', $opportunity);
    }
    
    public function create() {
        $auth = Auth::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        
        $required = ['title', 'description', 'type'];
        foreach ($required as $field) {
            if (!isset($input[$field]) || !Validator::required($input[$field])) {
                Response::error("$field is required", 400);
            }
        }
        
        $validTypes = ['job', 'internship', 'traineeship', 'tender', 'training', 'announcement'];
        if (!in_array($input['type'], $validTypes)) {
            Response::error('Invalid opportunity type', 400);
        }
        
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $priority = $input['priority'] ?? 'medium';
        if (!in_array($priority, $validPriorities)) {
            $priority = 'medium';
        }
        
        // Handle image upload if present
        $imageUrl = null;
        if (isset($_FILES['image'])) {
            try {
                $imageUrl = FileUpload::uploadImage($_FILES['image']);
            } catch (Exception $e) {
                Response::error($e->getMessage(), 400);
            }
        }
        
        $sql = "INSERT INTO opportunities (title, description, category_id, type, company_name, location, 
                salary_range, deadline, application_link, contact_email, contact_phone, requirements, 
                benefits, image_url, priority, status, admin_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            trim($input['title']),
            trim($input['description']),
            $input['category_id'] ?? null,
            $input['type'],
            $input['company_name'] ?? null,
            $input['location'] ?? null,
            $input['salary_range'] ?? null,
            $input['deadline'] ?? null,
            $input['application_link'] ?? null,
            $input['contact_email'] ?? null,
            $input['contact_phone'] ?? null,
            $input['requirements'] ?? null,
            $input['benefits'] ?? null,
            $imageUrl,
            $priority,
            $input['status'] ?? 'draft',
            $auth['id']
        ];
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $opportunityId = $this->pdo->lastInsertId();
        
        // If published, set published_at timestamp
        if (($input['status'] ?? 'draft') === 'published') {
            $updateSql = "UPDATE opportunities SET published_at = CURRENT_TIMESTAMP WHERE id = ?";
            $updateStmt = $this->pdo->prepare($updateSql);
            $updateStmt->execute([$opportunityId]);
        }
        
        Logger::logActivity($this->pdo, $auth['id'], 'opportunity_created', 'opportunities', $opportunityId, null, $input);
        
        Response::success('Opportunity created successfully', ['id' => $opportunityId]);
    }
    
    public function update($id) {
        $auth = Auth::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if opportunity exists
        $sql = "SELECT * FROM opportunities WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $opportunity = $stmt->fetch();
        
        if (!$opportunity) {
            Response::error('Opportunity not found', 404);
        }
        
        $allowedFields = [
            'title', 'description', 'category_id', 'type', 'company_name', 'location',
            'salary_range', 'deadline', 'application_link', 'contact_email', 'contact_phone',
            'requirements', 'benefits', 'priority', 'status'
        ];
        
        $updates = [];
        $values = [];
        $oldValues = [];
        $newValues = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $oldValues[$field] = $opportunity[$field];
                $newValues[$field] = $input[$field];
                
                if ($field === 'type') {
                    $validTypes = ['job', 'internship', 'traineeship', 'tender', 'training', 'announcement'];
                    if (!in_array($input[$field], $validTypes)) {
                        Response::error('Invalid opportunity type', 400);
                    }
                }
                
                if ($field === 'priority') {
                    $validPriorities = ['low', 'medium', 'high', 'urgent'];
                    if (!in_array($input[$field], $validPriorities)) {
                        Response::error('Invalid priority', 400);
                    }
                }
                
                $updates[] = "$field = ?";
                $values[] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            Response::error('No valid fields to update', 400);
        }
        
        // Handle status change to published
        if (isset($input['status']) && $input['status'] === 'published' && $opportunity['status'] !== 'published') {
            $updates[] = "published_at = CURRENT_TIMESTAMP";
        }
        
        $values[] = $id;
        $sql = "UPDATE opportunities SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        
        Logger::logActivity($this->pdo, $auth['id'], 'opportunity_updated', 'opportunities', $id, $oldValues, $newValues);
        
        Response::success('Opportunity updated successfully');
    }
    
    public function delete($id) {
        $auth = Auth::requireAuth();
        
        // Check if opportunity exists
        $sql = "SELECT * FROM opportunities WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $opportunity = $stmt->fetch();
        
        if (!$opportunity) {
            Response::error('Opportunity not found', 404);
        }
        
        // Delete the opportunity
        $sql = "DELETE FROM opportunities WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        
        Logger::logActivity($this->pdo, $auth['id'], 'opportunity_deleted', 'opportunities', $id, $opportunity, null);
        
        Response::success('Opportunity deleted successfully');
    }
    
    public function getStats() {
        $auth = Auth::requireAuth();
        
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired,
                    SUM(views_count) as total_views,
                    SUM(applications_count) as total_applications
                FROM opportunities";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $stats = $stmt->fetch();
        
        // Get opportunities by type
        $sql = "SELECT type, COUNT(*) as count FROM opportunities GROUP BY type";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $byType = $stmt->fetchAll();
        
        // Get recent opportunities
        $sql = "SELECT id, title, type, status, created_at FROM opportunities ORDER BY created_at DESC LIMIT 5";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $recent = $stmt->fetchAll();
        
        Response::success('Statistics retrieved', [
            'overview' => $stats,
            'by_type' => $byType,
            'recent' => $recent
        ]);
    }
}

// Route handling
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

$manager = new OpportunityManager();

switch ("$method:$path") {
    case 'GET:list':
        $manager->getAll();
        break;
    case 'GET:show':
        if (!$id) Response::error('ID parameter required', 400);
        $manager->getById($id);
        break;
    case 'POST:create':
        $manager->create();
        break;
    case 'PUT:update':
        if (!$id) Response::error('ID parameter required', 400);
        $manager->update($id);
        break;
    case 'DELETE:delete':
        if (!$id) Response::error('ID parameter required', 400);
        $manager->delete($id);
        break;
    case 'GET:stats':
        $manager->getStats();
        break;
    default:
        Response::error('Endpoint not found', 404);
}
?>
