-- Migration: 7add_cost_per_unit_to_inventory.sql
-- Adds a cost_per_unit column to the inventory table so the system can calculate inventory value

ALTER TABLE `inventory`
  ADD COLUMN `cost_per_unit` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `stock_qty`;

-- Optional: set a reasonable default cost for some seeded items (safe updates, only affect matching rows)
UPDATE inventory SET cost_per_unit = 500.00 WHERE item_name LIKE '%Coffee Beans%';
UPDATE inventory SET cost_per_unit = 60.00 WHERE item_name LIKE '%Sugar%';

-- End of migration
