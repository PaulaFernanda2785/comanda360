-- Popula metodos de pagamento padrao para todas as empresas existentes.
-- Seguro para executar mais de uma vez no phpMyAdmin.

INSERT IGNORE INTO payment_methods (company_id, name, code, status, created_at, updated_at)
SELECT id, 'Pix', 'pix', 'ativo', NOW(), NOW()
FROM companies;

INSERT IGNORE INTO payment_methods (company_id, name, code, status, created_at, updated_at)
SELECT id, 'Dinheiro', 'cash', 'ativo', NOW(), NOW()
FROM companies;

INSERT IGNORE INTO payment_methods (company_id, name, code, status, created_at, updated_at)
SELECT id, 'Cartao de Credito', 'credit_card', 'ativo', NOW(), NOW()
FROM companies;

INSERT IGNORE INTO payment_methods (company_id, name, code, status, created_at, updated_at)
SELECT id, 'Cartao de Debito', 'debit_card', 'ativo', NOW(), NOW()
FROM companies;

INSERT IGNORE INTO payment_methods (company_id, name, code, status, created_at, updated_at)
SELECT id, 'Pagamento Online', 'online', 'ativo', NOW(), NOW()
FROM companies;

UPDATE payment_methods
SET
    name = CASE code
        WHEN 'pix' THEN 'Pix'
        WHEN 'cash' THEN 'Dinheiro'
        WHEN 'credit_card' THEN 'Cartao de Credito'
        WHEN 'debit_card' THEN 'Cartao de Debito'
        WHEN 'online' THEN 'Pagamento Online'
        ELSE name
    END,
    status = 'ativo',
    updated_at = NOW()
WHERE code IN ('pix', 'cash', 'credit_card', 'debit_card', 'online');
