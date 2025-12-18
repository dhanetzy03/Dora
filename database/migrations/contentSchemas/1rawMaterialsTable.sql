CREATE TABLE IF NOT EXISTS raw_materials (
  material_id INT NOT NULL AUTO_INCREMENT,
  material_code VARCHAR(50) NOT NULL UNIQUE,
  material_name VARCHAR(150) NOT NULL,
  category VARCHAR(100) DEFAULT 'Raw',
  unit VARCHAR(30) NOT NULL DEFAULT 'pcs',
  quantity INT NOT NULL DEFAULT 0,
  cost_per_unit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  reorder_level INT NOT NULL DEFAULT 0,
  supplier_id INT DEFAULT NULL,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (material_id),
  INDEX idx_category (category),
  INDEX idx_material_code (material_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;