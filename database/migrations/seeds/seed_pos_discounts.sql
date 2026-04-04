-- POS discount-focused seed data (PWD / Senior Citizen)
USE `shukran_cafe`;
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Ensure at least one staff user exists (fallback to first active user if not)
SET @staff_id = (SELECT user_id FROM users WHERE role = 'staff' LIMIT 1);
SET @staff_id = COALESCE(@staff_id, (SELECT user_id FROM users ORDER BY user_id LIMIT 1));

-- Ensure baseline products exist for POS
INSERT INTO `products` (`product_code`, `product_name`, `description`, `category_id`, `unit`, `reorder_level`, `price`, `status`, `created_at`, `updated_at`)
SELECT 'POS-AMER', 'Americano', 'POS Seed Product', NULL, 'cup', 10, 120.00, 'active', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'POS-AMER');

INSERT INTO `products` (`product_code`, `product_name`, `description`, `category_id`, `unit`, `reorder_level`, `price`, `status`, `created_at`, `updated_at`)
SELECT 'POS-LATT', 'Cafe Latte', 'POS Seed Product', NULL, 'cup', 10, 150.00, 'active', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM products WHERE product_code = 'POS-LATT');

SET @prod_amer = (SELECT product_id FROM products WHERE product_code = 'POS-AMER' LIMIT 1);
SET @prod_latt = (SELECT product_id FROM products WHERE product_code = 'POS-LATT' LIMIT 1);

-- Ensure stock rows exist
INSERT INTO `stock` (`product_id`, `quantity`, `last_updated`)
SELECT @prod_amer, 200, NOW()
WHERE NOT EXISTS (SELECT 1 FROM stock WHERE product_id = @prod_amer);

INSERT INTO `stock` (`product_id`, `quantity`, `last_updated`)
SELECT @prod_latt, 200, NOW()
WHERE NOT EXISTS (SELECT 1 FROM stock WHERE product_id = @prod_latt);

-- PWD discounted sale
INSERT INTO `sales` (`sale_number`, `sale_date`, `total_amount`, `payment_method`, `customer_name`, `discount_type`, `discount_rate`, `discount_amount`, `discount_reference`, `status`, `created_by`, `created_at`, `updated_at`)
VALUES (
    CONCAT('POS-PWD-', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')),
    NOW(),
    216.00,
    'cash',
    'PWD Walk-in',
    'pwd',
    0.2000,
    54.00,
    'PWD-ID-001',
    'completed',
    @staff_id,
    NOW(),
    NOW()
);
SET @sale_pwd = LAST_INSERT_ID();

INSERT INTO `sale_items` (`sale_id`, `product_id`, `quantity`, `unit_price`, `subtotal`)
VALUES
(@sale_pwd, @prod_amer, 1, 120.00, 120.00),
(@sale_pwd, @prod_latt, 1, 150.00, 150.00);

-- Senior Citizen discounted sale
INSERT INTO `sales` (`sale_number`, `sale_date`, `total_amount`, `payment_method`, `customer_name`, `discount_type`, `discount_rate`, `discount_amount`, `discount_reference`, `status`, `created_by`, `created_at`, `updated_at`)
VALUES (
    CONCAT('POS-SEN-', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')),
    NOW(),
    216.00,
    'gcash',
    'Senior Walk-in',
    'senior_citizen',
    0.2000,
    54.00,
    'SC-ID-001',
    'completed',
    @staff_id,
    NOW(),
    NOW()
);
SET @sale_sen = LAST_INSERT_ID();

INSERT INTO `sale_items` (`sale_id`, `product_id`, `quantity`, `unit_price`, `subtotal`)
VALUES
(@sale_sen, @prod_amer, 1, 120.00, 120.00),
(@sale_sen, @prod_latt, 1, 150.00, 150.00);

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
