-- 10 Add discount tracking fields for PWD / Senior Citizen in sales
ALTER TABLE `sales`
  ADD COLUMN `discount_type` ENUM('none','pwd','senior_citizen') NOT NULL DEFAULT 'none' AFTER `payment_method`,
  ADD COLUMN `discount_rate` DECIMAL(5,4) NOT NULL DEFAULT 0.0000 AFTER `discount_type`,
  ADD COLUMN `discount_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `discount_rate`,
  ADD COLUMN `discount_reference` VARCHAR(100) NULL AFTER `discount_amount`;
