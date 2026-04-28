SET @stock_recipe_units_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'stock_recipe_items'
      AND COLUMN_NAME = 'consumption_unit'
);

SET @stock_recipe_units_sql := IF(
    @stock_recipe_units_column_exists = 0,
    'ALTER TABLE stock_recipe_items ADD COLUMN consumption_unit VARCHAR(20) NOT NULL DEFAULT ''un'' AFTER quantity_per_unit',
    'SELECT 1'
);

PREPARE stock_recipe_units_stmt FROM @stock_recipe_units_sql;
EXECUTE stock_recipe_units_stmt;
DEALLOCATE PREPARE stock_recipe_units_stmt;

UPDATE stock_recipe_items sri
INNER JOIN stock_items si
    ON si.id = sri.stock_item_id
   AND si.company_id = sri.company_id
SET sri.consumption_unit = si.unit_of_measure
WHERE sri.consumption_unit = 'un'
  AND si.unit_of_measure <> 'un';
