-- Products Database Schema
-- Create product categories table
CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    description TEXT,
    purchase_date DATE NOT NULL,
    order_number VARCHAR(100),
    units_purchased INT NOT NULL DEFAULT 0,
    cost_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    supplier_name VARCHAR(255),
    notes TEXT,
    office_id INT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (office_id) REFERENCES offices(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default product categories
INSERT INTO product_categories (name, description) VALUES 
('Engine Oil', 'Various types of engine oils and lubricants'),
('2T Oil', 'Two-stroke engine oils'),
('Transmission Oil', 'Transmission and gear oils'),
('Brake Fluid', 'Brake fluids and hydraulic fluids'),
('Coolant', 'Engine coolants and antifreeze'),
('Filters', 'Oil filters, air filters, fuel filters'),
('Spare Parts', 'General vehicle spare parts'),
('Tires', 'Vehicle tires and tubes'),
('Batteries', 'Vehicle batteries'),
('Other', 'Other automotive products')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Add products permissions to roles if they don't exist
INSERT IGNORE INTO role_permissions (role_id, permission) 
SELECT r.id, 'products_view' FROM roles r;

INSERT IGNORE INTO role_permissions (role_id, permission) 
SELECT r.id, 'products_edit' FROM roles r WHERE r.name IN ('Super Admin', 'Admin');

INSERT IGNORE INTO role_permissions (role_id, permission) 
SELECT r.id, 'products_delete' FROM roles r WHERE r.name = 'Super Admin';