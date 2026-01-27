-- ================================================
-- Migration: Add Shelf Life and Spoilage Tracking
-- Purpose: Track expiry dates, shelf life, and spoilage records
-- Date: January 27, 2026
-- ================================================

USE `shukran_cafe`;

-- ================================================
-- 1. Add shelf life columns to inventory table
-- ================================================
ALTER TABLE `inventory` 
ADD COLUMN IF NOT EXISTS `shelf_life_days` INT(11) NULL COMMENT 'Shelf life in days',
ADD COLUMN IF NOT EXISTS `date_received` DATE NULL COMMENT 'Date when item was received/added',
ADD COLUMN IF NOT EXISTS `expiry_date` DATE NULL COMMENT 'Calculated or manual expiry date',
ADD COLUMN IF NOT EXISTS `expiry_alert_days` INT(11) DEFAULT 7 COMMENT 'Days before expiry to show alert';

-- ================================================
-- 2. Add shelf life columns to products table
-- ================================================
ALTER TABLE `products` 
ADD COLUMN IF NOT EXISTS `shelf_life_days` INT(11) NULL COMMENT 'Shelf life in days',
ADD COLUMN IF NOT EXISTS `expiry_alert_days` INT(11) DEFAULT 7 COMMENT 'Days before expiry to show alert';

-- ================================================
-- 3. Create spoilage_records table
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
-- 4. Create inventory_snapshots table for beginning/ending
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
-- 5. Update stock_movements to include spoilage type
-- ================================================
ALTER TABLE `stock_movements` 
MODIFY COLUMN `movement_type` ENUM('in', 'out', 'adjustment', 'spoilage') NOT NULL;

-- ================================================
-- 6. Add expiry tracking to stock table
-- ================================================
ALTER TABLE `stock` 
ADD COLUMN IF NOT EXISTS `date_received` DATE NULL,
ADD COLUMN IF NOT EXISTS `expiry_date` DATE NULL;

-- ================================================
-- Sample data: Update some inventory items with shelf life
-- ================================================
UPDATE `inventory` 
SET 
  shelf_life_days = CASE 
    WHEN item_name LIKE '%milk%' THEN 7
    WHEN item_name LIKE '%bread%' THEN 3
    WHEN item_name LIKE '%egg%' THEN 14
    WHEN item_name LIKE '%cheese%' THEN 30
    WHEN item_name LIKE '%meat%' THEN 5
    WHEN item_name LIKE '%vegetable%' OR item_name LIKE '%lettuce%' THEN 5
    WHEN item_name LIKE '%fruit%' THEN 7
    ELSE 90
  END,
  expiry_alert_days = 3,
  date_received = CURDATE()
WHERE shelf_life_days IS NULL;

-- Calculate expiry dates based on shelf life
UPDATE `inventory` 
SET expiry_date = DATE_ADD(date_received, INTERVAL shelf_life_days DAY)
WHERE shelf_life_days IS NOT NULL AND date_received IS NOT NULL AND expiry_date IS NULL;

-- ================================================
-- Success message
-- ================================================
SELECT 'Migration 9: Shelf Life and Spoilage Tracking - Completed Successfully!' as Status;
