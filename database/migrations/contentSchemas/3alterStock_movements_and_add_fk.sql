
ALTER TABLE `stock_movements`
  ADD COLUMN `unit_cost_at_movement` DECIMAL(12,2) DEFAULT NULL AFTER `quantity`,
  ADD COLUMN `reference_number` VARCHAR(100) DEFAULT NULL AFTER `reference_id`;


ALTER TABLE `raw_materials`
  ADD CONSTRAINT `fk_rawmaterials_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`supplier_id`) ON DELETE SET NULL;
