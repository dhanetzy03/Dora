-- 6 Enforce enums and sensible defaults across core tables
-- sales.status -> default 'pending'
ALTER TABLE `sales`
  MODIFY COLUMN `status` ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending';

-- inventory.status -> ensure expected values and default
ALTER TABLE `inventory`
  MODIFY COLUMN `status` ENUM('Sufficient','Low Stock','Out of Stock') NOT NULL DEFAULT 'Sufficient';

-- products.status -> ensure default active
ALTER TABLE `products`
  MODIFY COLUMN `status` ENUM('active','inactive') NOT NULL DEFAULT 'active';

-- sale_items pricing precision and defaults
ALTER TABLE `sale_items`
  MODIFY COLUMN `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  MODIFY COLUMN `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00;

-- stock.quantity precision and default
ALTER TABLE `stock`
  MODIFY COLUMN `quantity` DECIMAL(12,2) NOT NULL DEFAULT 0.00;

-- stock_movements movement_type enum enforcement
ALTER TABLE `stock_movements`
  MODIFY COLUMN `movement_type` ENUM('in','out','adjustment') NOT NULL;

-- stock_movements unit_cost_at_movement precision
ALTER TABLE `stock_movements`
  MODIFY COLUMN `unit_cost_at_movement` DECIMAL(12,2) DEFAULT NULL;

-- inventory.unit default enforcement
ALTER TABLE `inventory`
  MODIFY COLUMN `unit` VARCHAR(20) NOT NULL DEFAULT 'pcs';


