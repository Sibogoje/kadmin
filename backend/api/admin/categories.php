<?php
require_once '../../config/cors.php';
require_once '../../config/config.php';

class CategoryManager {
    private $pdo;
    
    public function __construct() {
        $this->pdo = ConnectionManager::getConnection();
    }
    
    public function __destruct() {
        ConnectionManager::releaseConnection();
    }
    
    public function getAll() {
        $auth = Auth::requireAuth();
        
        $sql = "SELECT c.*, COUNT(o.id) as opportunities_count 
                FROM categories c 
                LEFT JOIN opportunities o ON c.id = o.category_id 
                GROUP BY c.id 
                ORDER BY c.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $categories = $stmt->fetchAll();
        
        Response::success('Categories retrieved', $categories);
    }
    
    public function getById($id) {
        $auth = Auth::requireAuth();
        
        $sql = "SELECT c.*, COUNT(o.id) as opportunities_count 
                FROM categories c 
                LEFT JOIN opportunities o ON c.id = o.category_id 
                WHERE c.id = ? 
                GROUP BY c.id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            Response::error('Category not found', 404);
        }
        
        Response::success('Category retrieved', $category);
    }
    
    public function create() {
        $auth = Auth::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || !Validator::required($input['name'])) {
            Response::error('Category name is required', 400);
        }
        
        $name = trim($input['name']);
        $description = trim($input['description'] ?? '');
        $icon = trim($input['icon'] ?? '');
        $color = trim($input['color'] ?? '#007BFF');
        
        // Validate color format (hex)
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            Response::error('Invalid color format. Use hex format like #007BFF', 400);
        }
        
        // Check if category name already exists
        $sql = "SELECT id FROM categories WHERE name = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name]);
        
        if ($stmt->fetch()) {
            Response::error('Category name already exists', 409);
        }
        
        $sql = "INSERT INTO categories (name, description, icon, color) VALUES (?, ?, ?, ?)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$name, $description, $icon, $color]);
        
        $categoryId = $this->pdo->lastInsertId();
        
        Logger::logActivity($this->pdo, $auth['id'], 'category_created', 'categories', $categoryId, null, $input);
        
        Response::success('Category created successfully', ['id' => $categoryId]);
    }
    
    public function update($id) {
        $auth = Auth::requireAuth();
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Check if category exists
        $sql = "SELECT * FROM categories WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            Response::error('Category not found', 404);
        }
        
        $allowedFields = ['name', 'description', 'icon', 'color', 'status'];
        $updates = [];
        $values = [];
        $oldValues = [];
        $newValues = [];
        
        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $oldValues[$field] = $category[$field];
                $newValues[$field] = $input[$field];
                
                if ($field === 'name' && !Validator::required($input[$field])) {
                    Response::error('Category name cannot be empty', 400);
                }
                
                if ($field === 'color' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $input[$field])) {
                    Response::error('Invalid color format. Use hex format like #007BFF', 400);
                }
                
                if ($field === 'status' && !in_array($input[$field], ['active', 'inactive'])) {
                    Response::error('Invalid status. Use active or inactive', 400);
                }
                
                $updates[] = "$field = ?";
                $values[] = trim($input[$field]);
            }
        }
        
        if (empty($updates)) {
            Response::error('No valid fields to update', 400);
        }
        
        // Check for duplicate name (excluding current category)
        if (isset($input['name'])) {
            $sql = "SELECT id FROM categories WHERE name = ? AND id != ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([trim($input['name']), $id]);
            
            if ($stmt->fetch()) {
                Response::error('Category name already exists', 409);
            }
        }
        
        $values[] = $id;
        $sql = "UPDATE categories SET " . implode(', ', $updates) . " WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);
        
        Logger::logActivity($this->pdo, $auth['id'], 'category_updated', 'categories', $id, $oldValues, $newValues);
        
        Response::success('Category updated successfully');
    }
    
    public function delete($id) {
        $auth = Auth::requireAuth();
        
        // Check if category exists
        $sql = "SELECT * FROM categories WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $category = $stmt->fetch();
        
        if (!$category) {
            Response::error('Category not found', 404);
        }
        
        // Check if category has associated opportunities
        $sql = "SELECT COUNT(*) as count FROM opportunities WHERE category_id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $count = $stmt->fetch()['count'];
        
        if ($count > 0) {
            Response::error('Cannot delete category with associated opportunities. Please reassign or delete opportunities first.', 400);
        }
        
        // Delete the category
        $sql = "DELETE FROM categories WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        
        Logger::logActivity($this->pdo, $auth['id'], 'category_deleted', 'categories', $id, $category, null);
        
        Response::success('Category deleted successfully');
    }
}

// Route handling
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;

$manager = new CategoryManager();

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
    default:
        Response::error('Endpoint not found', 404);
}
?>
