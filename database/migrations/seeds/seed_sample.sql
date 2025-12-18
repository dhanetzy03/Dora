-- Seed file for Shukran Cafe (sample data based on migrations)
-- Includes sample "AS PACK" product and stock movements
USE `shukran_cafe`;
SET FOREIGN_KEY_CHECKS = 0;

START TRANSACTION;

-- Categories
INSERT INTO `categories` (`category_name`, `description`) VALUES
('Beverages','Coffee, tea, and other drinks'),
('Food Items','Sandwiches, pastries, and meals'),
('Ingredients','Raw materials and ingredients'),
('Supplies','Paper cups, napkins, and other supplies')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- Capture category ids
SET @cat_ingredients = (SELECT category_id FROM categories WHERE category_name = 'Ingredients' LIMIT 1);
SET @cat_beverages = (SELECT category_id FROM categories WHERE category_name = 'Beverages' LIMIT 1);
SET @cat_supplies = (SELECT category_id FROM categories WHERE category_name = 'Supplies' LIMIT 1);

-- Suppliers
INSERT INTO `suppliers` (`supplier_name`, `contact`, `email`, `address`) VALUES
('Main Supplier','Juan Dela Cruz','supplier@example.com','123 Market St'),
('Bakery Supplies Co.','Maria Santos','bakery@supplies.com','45 Baker St'),
('Dairy Supplier','Jose Ramos','dairy@supplies.com','88 Farm Rd'),
('Packaging Co.','Ana Lopez','packaging@example.com','10 Pack Ln')
ON DUPLICATE KEY UPDATE supplier_name = VALUES(supplier_name);
SET @supp_main = (SELECT supplier_id FROM suppliers WHERE supplier_name = 'Main Supplier' LIMIT 1);
SET @supp_bakery = (SELECT supplier_id FROM suppliers WHERE supplier_name = 'Bakery Supplies Co.' LIMIT 1);
SET @supp_dairy = (SELECT supplier_id FROM suppliers WHERE supplier_name = 'Dairy Supplier' LIMIT 1);
SET @supp_pack = (SELECT supplier_id FROM suppliers WHERE supplier_name = 'Packaging Co.' LIMIT 1);

-- Products (AS PACK sample)
INSERT INTO `products` (`product_code`, `product_name`, `description`, `category_id`, `unit`, `reorder_level`, `status`)
VALUES
('COF-PACK','Coffee Beans (AS PACK)','Roasted coffee beans sold per pack', @cat_ingredients, 'pack', 5, 'active'),
('SUGAR-1KG','Sugar 1kg','Sugar 1 kilogram pack', @cat_ingredients, 'pack', 10, 'active'),
('MILK-1L','Fresh Milk 1L','Pasteurized fresh milk', @cat_beverages, 'ltr', 10, 'active'),
('PAPER-CUP-12oz','Paper Cup 12oz','Disposable paper cup 12oz', @cat_supplies, 'pcs', 200, 'active')
ON DUPLICATE KEY UPDATE product_name = VALUES(product_name);

SET @prod_cof = (SELECT product_id FROM products WHERE product_code = 'COF-PACK' LIMIT 1);
SET @prod_sugar = (SELECT product_id FROM products WHERE product_code = 'SUGAR-1KG' LIMIT 1);
SET @prod_milk = (SELECT product_id FROM products WHERE product_code = 'MILK-1L' LIMIT 1);
SET @prod_cup = (SELECT product_id FROM products WHERE product_code = 'PAPER-CUP-12oz' LIMIT 1);

-- Inventory (admin-facing inventory table) with category mapping
-- Added `item_code` and `cost_per_unit` to improve seeded data
INSERT INTO `inventory` (`item_code`,`item_name`, `category`, `stock_qty`, `cost_per_unit`, `reorder_level`, `status`, `unit`, `stock_in`, `stock_out`, `category_id`)
VALUES
('COF-PACK','Coffee Beans (AS PACK)','Ingredients', 50, 500.00, 5, 'Sufficient', 'pack', 50, 0, @cat_ingredients),
('SUGAR-1KG','Sugar 1kg (pack)','Ingredients', 30, 60.00, 5, 'Sufficient', 'pack', 30, 0, @cat_ingredients),
('MILK-1L','Fresh Milk 1L','Beverages', 20, 80.00, 5, 'Sufficient', 'ltr', 20, 0, @cat_beverages),
('PAPER-CUP-12oz','Paper Cup 12oz','Supplies', 500, 2.50, 100, 'Sufficient', 'pcs', 500, 0, @cat_supplies),
('SUGAR-BULK','Sugar Bulk (kg)','Ingredients', 120, 40.00, 20, 'Sufficient', 'kg', 120, 0, @cat_ingredients)
;

SET @inv_cof = (SELECT id FROM inventory WHERE item_code = 'COF-PACK' LIMIT 1);
SET @inv_sugar = (SELECT id FROM inventory WHERE item_code = 'SUGAR-1KG' LIMIT 1);
SET @inv_milk = (SELECT id FROM inventory WHERE item_code = 'MILK-1L' LIMIT 1);
SET @inv_cup = (SELECT id FROM inventory WHERE item_code = 'PAPER-CUP-12oz' LIMIT 1);

-- Stock table (current stock per product)
INSERT INTO `stock` (`product_id`, `quantity`) VALUES
(@prod_cof, 20.00),
(@prod_sugar, 30.00),
(@prod_milk, 15.00),
(@prod_cup, 500)
ON DUPLICATE KEY UPDATE quantity = VALUES(quantity);

-- Initial stock movements (in) for AS PACK product
INSERT INTO `stock_movements` (`product_id`, `movement_type`, `quantity`, `previous_quantity`, `new_quantity`, `reference_type`, `reference_id`, `reference_number`, `unit_cost_at_movement`, `remarks`, `created_by`)
VALUES
(@prod_cof, 'in', 20.00, 0.00, 20.00, 'purchase', NULL, 'PO-0001', 500.00, 'Initial stock (AS PACK) received', 1),
(@prod_sugar, 'in', 30.00, 0.00, 30.00, 'purchase', NULL, 'PO-0002', 60.00, 'Initial stock (sugar packs) received', 1),
(@prod_milk, 'in', 15.00, 0.00, 15.00, 'purchase', NULL, 'PO-0003', 80.00, 'Initial stock (milk cartons) received', 1),
(@prod_cup, 'in', 500, 0.00, 500, 'purchase', NULL, 'PO-0004', 2.50, 'Initial stock (paper cups) received', 1)
;

-- Sample raw materials (if migrations added raw_materials table)
INSERT INTO `raw_materials` (`material_code`, `material_name`, `category`, `category_id`, `unit`, `quantity`, `cost_per_unit`, `reorder_level`, `supplier_id`)
VALUES
('RM-COFFEE','Green Coffee Beans','Ingredients', @cat_ingredients, 'kg', 100, 250.00, 10, @supp_main),
('RM-SUGAR','Sugar','Ingredients', @cat_ingredients, 'kg', 200, 40.00, 20, @supp_main)
;

-- Sample sale (sell 2 packs of COF-PACK)
INSERT INTO `sales` (`sale_number`, `sale_date`, `total_amount`, `payment_method`, `customer_name`, `status`, `created_by`)
VALUES ('S-0001', NOW(), 1000.00, 'cash', 'Walk-in Customer', 'completed', 2);
SET @sale1 = (SELECT sale_id FROM sales WHERE sale_number = 'S-0001' LIMIT 1);

-- Sale items referencing product and inventory
INSERT INTO `sale_items` (`sale_id`, `product_id`, `inventory_id`, `quantity`, `unit_price`, `subtotal`)
VALUES
(@sale1, @prod_cof, @inv_cof, 2.00, 500.00, 1000.00);

-- Deduct from stock and log a stock_movement (out) for the sale
UPDATE `stock` SET `quantity` = `quantity` - 2.00 WHERE `product_id` = @prod_cof;

INSERT INTO `stock_movements` (`product_id`, `movement_type`, `quantity`, `previous_quantity`, `new_quantity`, `reference_type`, `reference_id`, `sale_ref_id`, `reference_number`, `unit_cost_at_movement`, `remarks`, `created_by`)
VALUES
(@prod_cof, 'out', 2.00, 20.00, 18.00, 'sale', @sale1, @sale1, 'S-0001', 500.00, 'Sold 2 packs (AS PACK)', 2);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;

-- End of seed_sample.sql
