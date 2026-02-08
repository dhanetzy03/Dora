ALTER TABLE `stock_movements`
  ADD COLUMN `sale_ref_id` INT(11) DEFAULT NULL AFTER `reference_id`,
  ADD COLUMN `purchase_ref_id` INT(11) DEFAULT NULL AFTER `sale_ref_id`;

ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_stockmov_sale` FOREIGN KEY (`sale_ref_id`) REFERENCES `sales`(`sale_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_stockmov_purchase` FOREIGN KEY (`purchase_ref_id`) REFERENCES `purchases`(`purchase_id`) ON DELETE SET NULL;


ALTER TABLE `sale_items`
  ADD COLUMN `inventory_id` INT(11) DEFAULT NULL AFTER `product_id`;

ALTER TABLE `sale_items`
  ADD CONSTRAINT `fk_saleitems_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory`(`id`) ON DELETE SET NULL;

