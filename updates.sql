-- Add province column to users table
ALTER TABLE users ADD COLUMN province ENUM('North', 'South', 'East', 'West', 'Central', 'None') DEFAULT 'None' AFTER role;

-- Add province column to distribution_centers table
ALTER TABLE distribution_centers ADD COLUMN province ENUM('North', 'South', 'East', 'West', 'Central', 'None') DEFAULT 'None' AFTER location;

-- Clear previous dummy centers and inventory for a clean slate, except HQ if needed, but let's just clear and seed properly
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE distribution_centers;
TRUNCATE TABLE inventory;
SET FOREIGN_KEY_CHECKS = 1;

-- Seed the 5 Province RDCs + HQ
INSERT INTO distribution_centers (id, name, location, province, manager_name, contact_phone, contact_email) VALUES
(1, 'HQ Warehouse', 'Colombo, Sri Lanka', 'None', 'Kamal Perera', '+94 11 234 5671', 'hq@isdn.com'),
(2, 'Northern RDC', 'Jaffna, Sri Lanka', 'North', 'Siva Kumar', '+94 21 234 5672', 'north@isdn.com'),
(3, 'Southern RDC', 'Galle, Sri Lanka', 'South', 'Priya Silva', '+94 91 234 5673', 'south@isdn.com'),
(4, 'Eastern RDC', 'Trincomalee, Sri Lanka', 'East', 'Ravi Fernando', '+94 26 234 5674', 'east@isdn.com'),
(5, 'Western RDC', 'Gampaha, Sri Lanka', 'West', 'Nimal Perera', '+94 33 234 5675', 'west@isdn.com'),
(6, 'Central RDC', 'Kandy, Sri Lanka', 'Central', 'Kasun Bandara', '+94 81 234 5676', 'central@isdn.com');

-- Re-seed basic inventory for testing across all centers (Products 1-5, Centers 1-6)
INSERT INTO inventory (product_id, center_id, quantity_on_hand, quantity_reserved) VALUES
(1, 1, 500, 0), (2, 1, 300, 0), (3, 1, 1000, 0), (4, 1, 800, 0), (5, 1, 400, 0),
(1, 2, 200, 0), (2, 2, 150, 0), (3, 2, 400, 0),  (4, 2, 300, 0), (5, 2, 180, 0),
(1, 3, 180, 0), (2, 3, 120, 0), (3, 3, 350, 0),  (4, 3, 250, 0), (5, 3, 150, 0),
(1, 4, 220, 0), (2, 4, 160, 0), (3, 4, 420, 0),  (4, 4, 310, 0), (5, 4, 190, 0),
(1, 5, 250, 0), (2, 5, 200, 0), (3, 5, 500, 0),  (4, 5, 400, 0), (5, 5, 250, 0),
(1, 6, 210, 0), (2, 6, 170, 0), (3, 6, 380, 0),  (4, 6, 280, 0), (5, 6, 160, 0);
