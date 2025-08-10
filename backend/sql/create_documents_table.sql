-- Create documents table for client document management
CREATE TABLE IF NOT EXISTS documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    document_type ENUM('id_document', 'proof_of_address', 'bank_statement', 'income_proof', 'contract', 'medical_record', 'other') NOT NULL DEFAULT 'other',
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    description TEXT,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES admins(id),
    
    INDEX idx_client_id (client_id),
    INDEX idx_document_type (document_type),
    INDEX idx_created_at (created_at)
);

-- Insert some sample document types for reference
-- This helps ensure consistent document categorization
