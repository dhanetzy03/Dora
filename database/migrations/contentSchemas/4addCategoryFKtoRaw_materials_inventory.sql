
ALTER TABLE `raw_materials` ADD COLUMN `category_id` INT(11) DEFAULT NULL AFTER `material_code`;
ALTER TABLE `inventory` ADD COLUMN `category_id` INT(11) DEFAULT NULL AFTER `category`;


UPDATE raw_materials rm
JOIN categories c ON LOWER(rm.category) = LOWER(c.category_name)
SET rm.category_id = c.category_id;

UPDATE inventory i
JOIN categories c ON LOWER(i.category) = LOWER(c.category_name)
SET i.category_id = c.category_id;

FK constraints
ALTER TABLE `raw_materials` ADD CONSTRAINT `fk_rawmaterials_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL;
ALTER TABLE `inventory` ADD CONSTRAINT `fk_inventory_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL;

