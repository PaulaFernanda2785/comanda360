-- Evolucao do modulo Estoque - ficha tecnica, baixa automatica, inventario e compras
-- Aplicar no banco de producao antes de usar a baixa automatica por pedido pago/finalizado.

CREATE TABLE IF NOT EXISTS stock_recipe_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    stock_item_id BIGINT UNSIGNED NOT NULL,
    quantity_per_unit DECIMAL(10,3) NOT NULL,
    waste_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(20) NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_recipe_items_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_recipe_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_recipe_items_stock_item
        FOREIGN KEY (stock_item_id) REFERENCES stock_items(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_stock_recipe_product_item UNIQUE (company_id, product_id, stock_item_id),
    CONSTRAINT chk_stock_recipe_quantity CHECK (quantity_per_unit > 0),
    CONSTRAINT chk_stock_recipe_waste CHECK (waste_percent >= 0 AND waste_percent <= 100),
    CONSTRAINT chk_stock_recipe_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_stock_recipe_items_company_product ON stock_recipe_items(company_id, product_id);
CREATE INDEX idx_stock_recipe_items_stock_item ON stock_recipe_items(stock_item_id);

CREATE TABLE IF NOT EXISTS stock_consumptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    stock_item_id BIGINT UNSIGNED NOT NULL,
    stock_movement_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_consumptions_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_consumptions_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_consumptions_order_item
        FOREIGN KEY (order_item_id) REFERENCES order_items(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_consumptions_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_stock_consumptions_stock_item
        FOREIGN KEY (stock_item_id) REFERENCES stock_items(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_stock_consumptions_movement
        FOREIGN KEY (stock_movement_id) REFERENCES stock_movements(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT uq_stock_consumption_order_item_stock UNIQUE (company_id, order_item_id, stock_item_id),
    CONSTRAINT chk_stock_consumptions_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_stock_consumptions_company_order ON stock_consumptions(company_id, order_id);
CREATE INDEX idx_stock_consumptions_stock_item ON stock_consumptions(stock_item_id);

CREATE TABLE IF NOT EXISTS stock_inventory_counts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    counted_by_user_id BIGINT UNSIGNED NULL,
    applied_by_user_id BIGINT UNSIGNED NULL,
    counted_at DATETIME NULL,
    applied_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_inventory_counts_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_inventory_counts_counted_by
        FOREIGN KEY (counted_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_stock_inventory_counts_applied_by
        FOREIGN KEY (applied_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_stock_inventory_counts_status CHECK (status IN ('draft', 'applied', 'canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_inventory_count_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    inventory_count_id BIGINT UNSIGNED NOT NULL,
    stock_item_id BIGINT UNSIGNED NOT NULL,
    system_quantity DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    counted_quantity DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    difference_quantity DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    stock_movement_id BIGINT UNSIGNED NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_inventory_count_items_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_inventory_count_items_count
        FOREIGN KEY (inventory_count_id) REFERENCES stock_inventory_counts(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_inventory_count_items_stock_item
        FOREIGN KEY (stock_item_id) REFERENCES stock_items(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_stock_inventory_count_items_movement
        FOREIGN KEY (stock_movement_id) REFERENCES stock_movements(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT uq_stock_inventory_item UNIQUE (inventory_count_id, stock_item_id),
    CONSTRAINT chk_stock_inventory_counted_quantity CHECK (counted_quantity >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    document VARCHAR(30) NULL,
    phone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'ativo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_suppliers_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_suppliers_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_suppliers_company_status ON suppliers(company_id, status);

CREATE TABLE IF NOT EXISTS stock_purchase_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    received_by_user_id BIGINT UNSIGNED NULL,
    received_at DATETIME NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_purchase_orders_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_purchase_orders_supplier
        FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_stock_purchase_orders_received_by
        FOREIGN KEY (received_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_stock_purchase_orders_status CHECK (status IN ('draft', 'received', 'canceled')),
    CONSTRAINT chk_stock_purchase_orders_total CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_purchase_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    stock_item_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(10,3) NOT NULL,
    unit_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock_movement_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_purchase_order_items_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_purchase_order_items_order
        FOREIGN KEY (purchase_order_id) REFERENCES stock_purchase_orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_purchase_order_items_stock_item
        FOREIGN KEY (stock_item_id) REFERENCES stock_items(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_stock_purchase_order_items_movement
        FOREIGN KEY (stock_movement_id) REFERENCES stock_movements(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_stock_purchase_order_items_quantity CHECK (quantity > 0),
    CONSTRAINT chk_stock_purchase_order_items_cost CHECK (unit_cost >= 0),
    CONSTRAINT chk_stock_purchase_order_items_total CHECK (line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
