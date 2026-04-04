-- ================================================
-- Shukran Café Inventory Tracking System Database
-- Web-based Inventory Tracking System with Enhanced 
-- Stock Monitoring and Sales Validation
-- ================================================

CREATE DATABASE IF NOT EXISTS `shukran_cafe`;
USE `shukran_cafe`;

-- ================================================
-- Table: users
-- Purpose: Store user accounts (admin, staff)
-- ================================================
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: categories
-- Purpose: Product/Item categories
-- ================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `category_id` INT(11) NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: suppliers
-- Purpose: Store supplier information
-- ================================================
CREATE TABLE IF NOT EXISTS `suppliers` (
  `supplier_id` INT(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` VARCHAR(100) NOT NULL,
  `contact_person` VARCHAR(100),
  `phone` VARCHAR(20),
  `email` VARCHAR(100),
  `address` TEXT,
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`supplier_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: products
-- Purpose: Store product/item information (for future inventory)
-- ================================================
CREATE TABLE IF NOT EXISTS `products` (
  `product_id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_code` VARCHAR(50) NOT NULL UNIQUE,
  `product_name` VARCHAR(150) NOT NULL,
  `description` TEXT,
  `category_id` INT(11),
  `unit` VARCHAR(20) DEFAULT 'pcs',
  `reorder_level` INT(11) DEFAULT 0,
  `price` DECIMAL(10,2) DEFAULT 0.00,
  `shelf_life_days` INT(11) NULL COMMENT 'Shelf life in days',
  `expiry_alert_days` INT(11) DEFAULT 7 COMMENT 'Days before expiry to show alert',
  `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: stock
-- Purpose: Current stock levels (for future use)
-- ================================================
CREATE TABLE IF NOT EXISTS `stock` (
  `stock_id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `date_received` DATE NULL,
  `expiry_date` DATE NULL,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`stock_id`),
  UNIQUE KEY `unique_product` (`product_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: stock_movements
-- Purpose: Track all stock movements (in/out)
-- Enhanced Stock Monitoring
-- ================================================
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `movement_id` INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `movement_type` ENUM('in', 'out', 'adjustment', 'spoilage') NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `unit_cost_at_movement` DECIMAL(12,2) DEFAULT NULL,
  `previous_quantity` DECIMAL(10,2) NOT NULL,
  `new_quantity` DECIMAL(10,2) NOT NULL,
  `reference_type` ENUM('purchase', 'sale', 'adjustment', 'return', 'spoilage') NOT NULL,
  `reference_id` INT(11),
  `reference_number` VARCHAR(100) DEFAULT NULL,
  `sale_ref_id` INT(11) DEFAULT NULL COMMENT 'Reference to sales table',
  `remarks` TEXT,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`movement_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: sales
-- Purpose: Sales transactions with validation
-- Sales Validation feature
-- ================================================
CREATE TABLE IF NOT EXISTS `sales` (
  `sale_id` INT(11) NOT NULL AUTO_INCREMENT,
  `sale_number` VARCHAR(50) NOT NULL UNIQUE,
  `sale_date` DATETIME NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `payment_method` ENUM('cash', 'card', 'gcash', 'other') NOT NULL DEFAULT 'cash',
  `customer_name` VARCHAR(100),
  `status` ENUM('pending', 'completed', 'cancelled') NOT NULL DEFAULT 'completed',
  `validated_by` INT(11),
  `validated_at` TIMESTAMP NULL,
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`sale_id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
  FOREIGN KEY (`validated_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: sale_items
-- Purpose: Individual items in a sale

CREATE TABLE IF NOT EXISTS `sale_items` (
  `sale_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `sale_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `unit_cost_at_sale` DECIMAL(10,2) DEFAULT 0.00,
  `markup_rate` DECIMAL(5,4) DEFAULT 0.00 COMMENT 'Markup rate as decimal (e.g., 0.20 for 20%)',
  `subtotal` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`sale_item_id`),
  FOREIGN KEY (`sale_id`) REFERENCES `sales`(`sale_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: purchases
-- Purpose: Purchase orders from suppliers (for future use)
-- ================================================
CREATE TABLE IF NOT EXISTS `purchases` (
  `purchase_id` INT(11) NOT NULL AUTO_INCREMENT,
  `purchase_number` VARCHAR(50) NOT NULL UNIQUE,
  `supplier_id` INT(11),
  `purchase_date` DATETIME NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `status` ENUM('pending', 'received', 'cancelled') NOT NULL DEFAULT 'pending',
  `created_by` INT(11) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`purchase_id`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`supplier_id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: purchase_items
-- Purpose: Individual items in a purchase order
-- ================================================
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `purchase_item_id` INT(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` INT(11) NOT NULL,
  `product_id` INT(11) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `unit_cost` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`purchase_item_id`),
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`purchase_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: activity_logs
-- Purpose: Track all system activities for audit trail
-- ================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `log_id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11),
  `action` VARCHAR(100) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Insert Test Users (Admin & Staff)
-- ================================================
-- Admin User: Username: admintester | Password: test123
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`) 
VALUES ('admintester', 'admin@shukrancafe.com', 'test123', 'Admin Tester', 'admin', 'active');

-- Staff User: Username: staffuser | Password: test123
INSERT INTO `users` (`username`, `email`, `password`, `full_name`, `role`, `status`) 
VALUES ('staffuser', 'staff@shukrancafe.com', 'test123', 'Staff User', 'staff', 'active');

-- ================================================
-- Table: inventory
-- Purpose: Simple inventory tracking for admin dashboard
-- ================================================
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_code` VARCHAR(50) NULL UNIQUE,
  `item_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `stock_qty` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `reorder_level` INT(11) DEFAULT 0,
  `status` ENUM('Sufficient', 'Low Stock', 'Out of Stock') NOT NULL DEFAULT 'Sufficient',
  `unit` VARCHAR(20) DEFAULT 'pcs',
  `cost_per_unit` DECIMAL(10,2) DEFAULT 0.00,
  `stock_in` INT(11) DEFAULT 0,
  `stock_out` INT(11) DEFAULT 0,
  `shelf_life_days` INT(11) NULL COMMENT 'Shelf life in days',
  `date_received` DATE NULL COMMENT 'Date when item was received/added',
  `expiry_date` DATE NULL COMMENT 'Calculated or manual expiry date',
  `expiry_alert_days` INT(11) DEFAULT 7 COMMENT 'Days before expiry to show alert',
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Insert Sample Categories
-- ================================================
INSERT INTO `categories` (`category_name`, `description`) VALUES
('Beverages', 'Coffee, tea, and other drinks'),
('Food Items', 'Sandwiches, pastries, and meals'),
('Ingredients', 'Raw materials and ingredients'),
('Supplies', 'Paper cups, napkins, and other supplies');

-- ================================================
-- Table: raw_materials
-- Purpose: Track raw materials inventory separately
-- ================================================
CREATE TABLE IF NOT EXISTS `raw_materials` (
  `material_id` INT(11) NOT NULL AUTO_INCREMENT,
  `material_code` VARCHAR(50) NOT NULL UNIQUE,
  `material_name` VARCHAR(150) NOT NULL,
  `category` VARCHAR(100) DEFAULT 'Raw',
  `unit` VARCHAR(30) NOT NULL DEFAULT 'pcs',
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `cost_per_unit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `reorder_level` INT(11) NOT NULL DEFAULT 0,
  `supplier_id` INT(11) DEFAULT NULL,
  `last_updated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`material_id`),
  INDEX `idx_category` (`category`),
  INDEX `idx_material_code` (`material_code`),
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`supplier_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- Table: spoilage_records
-- Purpose: Track spoiled inventory items
-- ================================================
CREATE TABLE IF NOT EXISTS `spoilage_records` (
  `spoilage_id` INT(11) NOT NULL AUTO_INCREMENT,
  `item_type` ENUM('inventory', 'product') NOT NULL DEFAULT 'inventory',
  `item_id` INT(11) NOT NULL COMMENT 'ID from inventory or products table',
  `item_name` VARCHAR(150) NOT NULL,
  `quantity_spoiled` DECIMAL(10,2) NOT NULL,
  `unit` VARCHAR(20) DEFAULT 'pcs',
  `cost_per_unit` DECIMAL(10,2) DEFAULT 0.00,
  `total_loss` DECIMAL(10,2) GENERATED ALWAYS AS (quantity_spoiled * cost_per_unit) STORED,
  `spoilage_reason` ENUM('expired', 'damaged', 'contaminated', 'overstock', 'other') NOT NULL DEFAULT 'expired',
  `reason_details` TEXT NULL COMMENT 'Additional details about spoilage',
  `date_spoiled` DATE NOT NULL,
  `recorded_by` INT(11) NULL COMMENT 'User ID who recorded the spoilage',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`spoilage_id`),
  FOREIGN KEY (`recorded_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  INDEX `idx_date_spoiled` (`date_spoiled`),
  INDEX `idx_item_type_id` (`item_type`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Track spoiled inventory items';

-- ================================================
-- Table: inventory_snapshots
-- Purpose: Store inventory snapshots for beginning/ending tracking
-- ================================================
CREATE TABLE IF NOT EXISTS `inventory_snapshots` (
  `snapshot_id` INT(11) NOT NULL AUTO_INCREMENT,
  `snapshot_date` DATE NOT NULL,
  `snapshot_type` ENUM('beginning', 'ending', 'periodic') NOT NULL DEFAULT 'ending',
  `period_type` ENUM('daily', 'weekly', 'monthly') NOT NULL DEFAULT 'daily',
  `item_id` INT(11) NOT NULL COMMENT 'Inventory item ID',
  `item_name` VARCHAR(150) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `cost_per_unit` DECIMAL(10,2) DEFAULT 0.00,
  `total_value` DECIMAL(10,2) GENERATED ALWAYS AS (quantity * cost_per_unit) STORED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT(11) NULL,
  PRIMARY KEY (`snapshot_id`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_snapshot` (`snapshot_date`, `snapshot_type`, `item_id`),
  INDEX `idx_snapshot_date` (`snapshot_date`),
  INDEX `idx_period_type` (`period_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Store inventory snapshots for beginning/ending tracking';

-- ================================================
-- End of Database Schema
-- ================================================
