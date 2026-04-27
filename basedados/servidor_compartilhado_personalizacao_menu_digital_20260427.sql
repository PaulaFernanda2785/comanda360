-- MesiMenu - Patch para servidor compartilhado
-- Data: 2026-04-27
-- Objetivo:
--   Adicionar as preferencias da tela Personalizacao para controlar:
--   1) exibicao de valores totais no menu digital publico;
--   2) permissao de tickets publicos pelo QR Code.
--
-- Como usar no servidor compartilhado:
--   Execute este arquivo no banco do MesiMenu pelo phpMyAdmin ou pelo painel SQL
--   da hospedagem. O script pode ser executado mais de uma vez.
--
-- Observacao:
--   Nao apaga dados e nao altera as configuracoes antigas das empresas.
--   Por padrao, empresas existentes continuam exibindo valores e tickets.

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

UPDATE company_themes
SET
    show_public_totals = COALESCE(show_public_totals, 1),
    show_public_tickets = COALESCE(show_public_tickets, 1);

SELECT
    'Patch de personalizacao do menu digital aplicado com sucesso.' AS status,
    COUNT(*) AS empresas_com_tema
FROM company_themes;
