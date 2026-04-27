-- Patch: preferencias de exibicao publica do menu digital por empresa.
-- Pode ser executado mais de uma vez.

SET @has_show_public_totals := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'company_themes'
      AND column_name = 'show_public_totals'
);

SET @sql := IF(
    @has_show_public_totals = 0,
    'ALTER TABLE company_themes ADD COLUMN show_public_totals TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''Exibe valores totais na area publica do menu digital'' AFTER footer_text',
    'SELECT ''show_public_totals ja existe'' AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_show_public_tickets := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'company_themes'
      AND column_name = 'show_public_tickets'
);

SET @sql := IF(
    @has_show_public_tickets = 0,
    'ALTER TABLE company_themes ADD COLUMN show_public_tickets TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''Permite consulta de tickets na area publica do menu digital'' AFTER show_public_totals',
    'SELECT ''show_public_tickets ja existe'' AS status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
