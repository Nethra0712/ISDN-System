-- ============================================
-- ISDN Distribution Management System
-- Full Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS isdn_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE isdn_db;

-- ============================================
-- TABLE: users
-- Stores all system users with roles
-- ============================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,         -- bcrypt hashed
    role ENUM('admin','ho_manager','rdc_staff','logistics_staff','customer') NOT NULL DEFAULT 'customer',
    province ENUM('North', 'South', 'East', 'West', 'Central', 'None') DEFAULT 'None',
    status ENUM('active','inactive','pending') NOT NULL DEFAULT 'pending',
    wallet_balance DECIMAL(12,2) DEFAULT 0.00,
    permanent_address TEXT,                 -- customer address
    route_number INT,                       -- logistics/customer route (1-5)
    vehicle_plate_number VARCHAR(20),       -- for logistics staff
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: distribution_centers
-- Regional Distribution Centers (RDCs)
-- ============================================
CREATE TABLE distribution_centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    location VARCHAR(200) NOT NULL,
    province ENUM('North', 'South', 'East', 'West', 'Central', 'None') DEFAULT 'None',
    manager_name VARCHAR(100),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(150),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: products
-- Master product catalog
-- ============================================
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    discount_percentage INT DEFAULT 0,
    unit_of_measure VARCHAR(30) DEFAULT 'unit',
    reorder_level INT DEFAULT 10,
    status ENUM('active','discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================
-- TABLE: inventory
-- Stock levels per product per center
-- ============================================
CREATE TABLE inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    center_id INT NOT NULL,
    quantity_on_hand INT NOT NULL DEFAULT 0,
    quantity_reserved INT NOT NULL DEFAULT 0,  -- reserved for pending orders
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (center_id) REFERENCES distribution_centers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_center (product_id, center_id)
);

-- ============================================
-- TABLE: orders
-- Customer orders (sales orders)
-- ============================================
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    center_id INT,
    status ENUM('pending','approved','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    shipping_address TEXT,
    route_number INT,
    notes TEXT,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (center_id) REFERENCES distribution_centers(id)
);

-- ============================================
-- TABLE: order_items
-- Line items for each order
-- ============================================
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================
-- TABLE: purchase_orders
-- Orders placed to suppliers by RDC staff
-- ============================================
CREATE TABLE purchase_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(30) NOT NULL UNIQUE,
    center_id INT NOT NULL,
    created_by INT NOT NULL,               -- rdc_staff user id
    approved_by INT,                        -- ho_manager user id
    supplier_name VARCHAR(150) NOT NULL,
    status ENUM('draft','submitted','approved','rejected','received') DEFAULT 'draft',
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    expected_delivery DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (center_id) REFERENCES distribution_centers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ============================================
-- TABLE: purchase_order_items
-- Line items for each purchase order
-- ============================================
CREATE TABLE purchase_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    po_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_ordered INT NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL,
    quantity_received INT DEFAULT 0,
    FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================
-- TABLE: deliveries
-- Delivery tracking for orders
-- ============================================
CREATE TABLE deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_number VARCHAR(30) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    assigned_to INT,                        -- logistics_staff user id
    center_id INT,
    status ENUM('pending','out_for_delivery','delivered','delayed') DEFAULT 'pending',
    driver_name VARCHAR(100),
    vehicle_number VARCHAR(30),
    estimated_delivery DATE,
    actual_delivery DATETIME,
    delivery_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (center_id) REFERENCES distribution_centers(id)
);

-- ============================================
-- TABLE: invoices
-- Invoices generated for orders
-- ============================================
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(30) NOT NULL UNIQUE,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL,
    status ENUM('unpaid','partially_paid','paid','overdue') DEFAULT 'unpaid',
    due_date DATE,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id)
);

-- ============================================
-- TABLE: payments
-- Payments made against invoices
-- ============================================
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','bank_transfer','cheque','online') DEFAULT 'cash',
    reference_number VARCHAR(100),
    payment_date DATE NOT NULL,
    recorded_by INT,                        -- user who recorded the payment
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- ============================================
-- TABLE: return_requests
-- Lifecycle of a product return
-- ============================================
CREATE TABLE return_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    customer_id INT NOT NULL,
    center_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    total_refund_amount DECIMAL(12,2) DEFAULT 0.00,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (center_id) REFERENCES distribution_centers(id)
);

-- ============================================
-- TABLE: return_items
-- Specific products within a return request
-- ============================================
CREATE TABLE return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    return_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_requested INT NOT NULL,
    quantity_accepted INT DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL,
    refund_amount DECIMAL(12,2) DEFAULT 0.00,
    FOREIGN KEY (return_id) REFERENCES return_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- ============================================
-- DEFAULT ADMIN USER
-- Password: Admin@1234
-- ============================================
INSERT INTO users (name, email, password, role, status) VALUES (
    'System Administrator',
    'admin@isdn.com',
    '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@1234
    'admin',
    'active'
);

-- Sample Distribution Centers (Province Based)
INSERT INTO distribution_centers (id, name, location, province, manager_name, contact_phone, contact_email) VALUES
(1, 'HQ Warehouse', 'Colombo, Sri Lanka', 'None', 'Kamal Perera', '+94 11 234 5671', 'hq@isdn.com'),
(2, 'Northern RDC', 'Jaffna, Sri Lanka', 'North', 'Siva Kumar', '+94 21 234 5672', 'north@isdn.com'),
(3, 'Southern RDC', 'Galle, Sri Lanka', 'South', 'Priya Silva', '+94 91 234 5673', 'south@isdn.com'),
(4, 'Eastern RDC', 'Trincomalee, Sri Lanka', 'East', 'Ravi Fernando', '+94 26 234 5674', 'east@isdn.com'),
(5, 'Western RDC', 'Gampaha, Sri Lanka', 'West', 'Nimal Perera', '+94 33 234 5675', 'west@isdn.com'),
(6, 'Central RDC', 'Kandy, Sri Lanka', 'Central', 'Kasun Bandara', '+94 81 234 5676', 'central@isdn.com');

-- Sample Products
INSERT INTO products (sku, name, description, category, unit_price, unit_of_measure, reorder_level) VALUES
('PRD-001', 'Rice 25kg Bag', 'Premium white rice 25kg', 'Grains', 3500.00, 'bag', 50),
('PRD-002', 'Coconut Oil 1L', 'Pure coconut oil 1 liter bottle', 'Oils', 850.00, 'bottle', 100),
('PRD-003', 'Sugar 1kg', 'Refined white sugar 1kg pack', 'Sweeteners', 220.00, 'pack', 200),
('PRD-004', 'Wheat Flour 1kg', 'All-purpose wheat flour 1kg', 'Grains', 180.00, 'pack', 150),
('PRD-005', 'Dhal 500g', 'Red lentils 500g pack', 'Pulses', 310.00, 'pack', 100);

-- Sample Inventory
INSERT INTO inventory (product_id, center_id, quantity_on_hand, quantity_reserved) VALUES
(1, 1, 500, 0), (2, 1, 300, 0), (3, 1, 1000, 0), (4, 1, 800, 0), (5, 1, 400, 0),
(1, 2, 200, 0), (2, 2, 150, 0), (3, 2, 400, 0),  (4, 2, 300, 0), (5, 2, 180, 0),
(1, 3, 180, 0), (2, 3, 120, 0), (3, 3, 350, 0),  (4, 3, 250, 0), (5, 3, 150, 0),
(1, 4, 220, 0), (2, 4, 160, 0), (3, 4, 420, 0),  (4, 4, 310, 0), (5, 4, 190, 0),
(1, 5, 250, 0), (2, 5, 200, 0), (3, 5, 500, 0),  (4, 5, 400, 0), (5, 5, 250, 0),
(1, 6, 210, 0), (2, 6, 170, 0), (3, 6, 380, 0),  (4, 6, 280, 0), (5, 6, 160, 0);
