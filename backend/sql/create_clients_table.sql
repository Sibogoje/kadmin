-- Create clients table
CREATE TABLE IF NOT EXISTS `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL UNIQUE,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `type` enum('individual','company','organization') NOT NULL DEFAULT 'company',
  `status` enum('active','inactive','potential') NOT NULL DEFAULT 'active',
  `website` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample clients for testing
INSERT INTO `clients` (`name`, `email`, `phone`, `address`, `type`, `status`, `website`, `contact_person`, `notes`) VALUES
('TechCorp Solutions', 'contact@techcorp.com', '+1-555-0123', '123 Tech Street, Silicon Valley, CA 94000', 'company', 'active', 'https://techcorp.com', 'John Smith', 'Leading technology solutions provider'),
('Green Energy Ltd', 'info@greenenergy.com', '+1-555-0456', '456 Renewable Ave, Austin, TX 78701', 'company', 'active', 'https://greenenergy.com', 'Sarah Johnson', 'Renewable energy consulting firm'),
('Creative Designs Inc', 'hello@creativedesigns.com', '+1-555-0789', '789 Art District, Portland, OR 97201', 'company', 'potential', 'https://creativedesigns.com', 'Mike Wilson', 'Creative agency specializing in branding'),
('Local Nonprofit', 'contact@localnonprofit.org', '+1-555-0321', '321 Community Blvd, Denver, CO 80202', 'organization', 'active', 'https://localnonprofit.org', 'Lisa Brown', 'Community-focused nonprofit organization'),
('Jane Doe Consulting', 'jane@janedoe.com', '+1-555-0654', '654 Business Center, Miami, FL 33101', 'individual', 'active', 'https://janedoe.com', 'Jane Doe', 'Independent business consultant');
