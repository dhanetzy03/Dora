-- Seed updates: set cost_per_unit for common seeded inventory items
-- This seed can be run after the migration above.

UPDATE inventory SET cost_per_unit = 500.00 WHERE item_name LIKE '%Coffee Beans%';
UPDATE inventory SET cost_per_unit = 60.00 WHERE item_name LIKE '%Sugar%';

-- Add any other manual cost adjustments below as needed.
