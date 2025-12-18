-- Migration: 8_add_price_to_products.sql
-- Adds a price column to products so staff UI can read unit prices

ALTER TABLE `products`
  ADD COLUMN `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `unit`;

-- Optional: set example prices for seeded products (adjust as needed)
-- UPDATE products SET price = 120.00 WHERE product_name LIKE '%Coffee%';
-- UPDATE products SET price = 60.00 WHERE product_name LIKE '%Tea%';

-- End of migration
