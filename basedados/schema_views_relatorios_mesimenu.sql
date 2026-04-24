-- =========================================================
-- ARQUIVO: schema_views_relatorios.sql
-- SISTEMA: SaaS Menu Interativo
-- FINALIDADE:
--   Views SQL auxiliares para dashboard, relatórios e operação.
--
-- PRÉ-REQUISITO:
--   Executar antes:
--     1) schema_producao_implantacao.sql
--   Opcional:
--     2) seed_demo.sql
--
-- OBSERVAÇÃO:
--   Estas views foram construídas para MySQL 8.4+.
--   Elas partem da estrutura já definida no schema principal.
-- =========================================================

USE mesimenu;

-- =========================================================
-- 1. LIMPEZA CONTROLADA DAS VIEWS
-- =========================================================

DROP VIEW IF EXISTS vw_dashboard_resumo_empresa;
DROP VIEW IF EXISTS vw_dashboard_resumo_diario;
DROP VIEW IF EXISTS vw_relatorio_vendas_pedidos;
DROP VIEW IF EXISTS vw_relatorio_vendas_pagamentos;
DROP VIEW IF EXISTS vw_produtos_mais_vendidos;
DROP VIEW IF EXISTS vw_pedidos_por_status;
DROP VIEW IF EXISTS vw_fechamento_caixa_resumo;
DROP VIEW IF EXISTS vw_fechamento_caixa_movimentos;
DROP VIEW IF EXISTS vw_ticket_medio_por_empresa;
DROP VIEW IF EXISTS vw_vendas_por_categoria;

-- =========================================================
-- 2. DASHBOARD - RESUMO GERAL POR EMPRESA
-- =========================================================
-- FINALIDADE:
--   Consolidar visão geral da operação por empresa.
-- =========================================================

CREATE VIEW vw_dashboard_resumo_empresa AS
SELECT
    c.id AS company_id,
    c.name AS company_name,

    (
        SELECT COUNT(*)
        FROM products p
        WHERE p.company_id = c.id
          AND p.deleted_at IS NULL
          AND p.is_active = 1
    ) AS total_produtos_ativos,

    (
        SELECT COUNT(*)
        FROM tables t
        WHERE t.company_id = c.id
    ) AS total_mesas,

    (
        SELECT COUNT(*)
        FROM tables t
        WHERE t.company_id = c.id
          AND t.status = 'ocupada'
    ) AS mesas_ocupadas,

    (
        SELECT COUNT(*)
        FROM commands cmd
        WHERE cmd.company_id = c.id
          AND cmd.status = 'aberta'
    ) AS comandas_abertas,

    (
        SELECT COUNT(*)
        FROM orders o
        WHERE o.company_id = c.id
          AND o.status NOT IN ('finished', 'canceled')
    ) AS pedidos_em_aberto,

    (
        SELECT COUNT(*)
        FROM orders o
        WHERE o.company_id = c.id
          AND o.status = 'preparing'
    ) AS pedidos_em_preparo,

    (
        SELECT COUNT(*)
        FROM orders o
        WHERE o.company_id = c.id
          AND o.status = 'ready'
    ) AS pedidos_prontos,

    (
        SELECT COUNT(*)
        FROM customers cu
        WHERE cu.company_id = c.id
          AND cu.status = 'ativo'
    ) AS total_clientes_ativos

FROM companies c;

-- =========================================================
-- 3. DASHBOARD - RESUMO DIÁRIO POR EMPRESA
-- FINALIDADE:
--   Métricas diárias operacionais e financeiras.
-- =========================================================

CREATE VIEW vw_dashboard_resumo_diario AS
SELECT
    o.company_id,
    DATE(o.created_at) AS data_referencia,
    COUNT(DISTINCT o.id) AS total_pedidos,
    SUM(o.subtotal_amount) AS subtotal_vendido,
    SUM(o.discount_amount) AS total_descontos,
    SUM(o.delivery_fee) AS total_taxas_entrega,
    SUM(o.total_amount) AS total_vendido,
    SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) AS total_pago,
    SUM(CASE WHEN o.status = 'canceled' THEN 1 ELSE 0 END) AS total_pedidos_cancelados,
    AVG(o.total_amount) AS ticket_medio_bruto
FROM orders o
GROUP BY o.company_id, DATE(o.created_at);

-- =========================================================
-- 4. RELATÓRIO DE VENDAS - PEDIDOS
-- FINALIDADE:
--   Base detalhada por pedido para filtros e relatórios.
-- =========================================================

CREATE VIEW vw_relatorio_vendas_pedidos AS
SELECT
    o.id AS order_id,
    o.company_id,
    c.name AS company_name,
    o.order_number,
    o.channel,
    o.status,
    o.payment_status,
    o.command_id,
    o.table_id,
    t.number AS table_number,
    o.customer_id,
    COALESCE(o.customer_name, cu.name) AS customer_name,
    o.subtotal_amount,
    o.discount_amount,
    o.delivery_fee,
    o.total_amount,
    o.placed_by,
    o.placed_by_user_id,
    u.name AS placed_by_user_name,
    o.created_at,
    o.updated_at,
    o.canceled_at
FROM orders o
INNER JOIN companies c
    ON c.id = o.company_id
LEFT JOIN tables t
    ON t.id = o.table_id
LEFT JOIN customers cu
    ON cu.id = o.customer_id
LEFT JOIN users u
    ON u.id = o.placed_by_user_id;

-- =========================================================
-- 5. RELATÓRIO DE VENDAS - PAGAMENTOS
-- FINALIDADE:
--   Consolidar pagamentos por pedido/comanda para análise financeira.
-- =========================================================

CREATE VIEW vw_relatorio_vendas_pagamentos AS
SELECT
    p.id AS payment_id,
    p.company_id,
    c.name AS company_name,
    p.order_id,
    o.order_number,
    p.command_id,
    p.payment_method_id,
    pm.name AS payment_method_name,
    pm.code AS payment_method_code,
    p.amount,
    p.status AS payment_status,
    p.transaction_reference,
    p.paid_at,
    p.received_by_user_id,
    u.name AS received_by_user_name,
    p.created_at
FROM payments p
INNER JOIN companies c
    ON c.id = p.company_id
LEFT JOIN orders o
    ON o.id = p.order_id
LEFT JOIN payment_methods pm
    ON pm.id = p.payment_method_id
LEFT JOIN users u
    ON u.id = p.received_by_user_id;

-- =========================================================
-- 6. PRODUTOS MAIS VENDIDOS
-- FINALIDADE:
--   Ranking consolidado de produtos por empresa.
-- OBS:
--   Considera itens de pedidos não cancelados.
-- =========================================================

CREATE VIEW vw_produtos_mais_vendidos AS
SELECT
    oi.company_id,
    p.id AS product_id,
    p.name AS product_name,
    cat.id AS category_id,
    cat.name AS category_name,
    COUNT(oi.id) AS total_linhas_vendidas,
    SUM(oi.quantity) AS total_quantidade_vendida,
    SUM(oi.line_subtotal) AS valor_total_vendido,
    AVG(oi.unit_price) AS preco_medio_vendido
FROM order_items oi
INNER JOIN products p
    ON p.id = oi.product_id
LEFT JOIN categories cat
    ON cat.id = p.category_id
INNER JOIN orders o
    ON o.id = oi.order_id
WHERE oi.status = 'active'
  AND o.status <> 'canceled'
GROUP BY
    oi.company_id,
    p.id,
    p.name,
    cat.id,
    cat.name;

-- =========================================================
-- 7. PEDIDOS POR STATUS
-- FINALIDADE:
--   Resumo de quantidade e valores agrupados por status do pedido.
-- =========================================================

CREATE VIEW vw_pedidos_por_status AS
SELECT
    o.company_id,
    o.status,
    COUNT(*) AS total_pedidos,
    SUM(o.total_amount) AS valor_total_pedidos,
    AVG(o.total_amount) AS ticket_medio_status,
    MIN(o.created_at) AS primeiro_pedido_status,
    MAX(o.created_at) AS ultimo_pedido_status
FROM orders o
GROUP BY o.company_id, o.status;

-- =========================================================
-- 8. FECHAMENTO DE CAIXA - RESUMO
-- FINALIDADE:
--   Resumo consolidado por caixa aberto/fechado.
-- =========================================================

CREATE VIEW vw_fechamento_caixa_resumo AS
SELECT
    cr.id AS cash_register_id,
    cr.company_id,
    c.name AS company_name,
    cr.opened_by_user_id,
    u_open.name AS opened_by_user_name,
    cr.closed_by_user_id,
    u_close.name AS closed_by_user_name,
    cr.opened_at,
    cr.closed_at,
    cr.opening_amount,
    cr.closing_amount_reported,
    cr.closing_amount_calculated,
    cr.status AS cash_register_status,

    COALESCE((
        SELECT SUM(cm.amount)
        FROM cash_movements cm
        WHERE cm.cash_register_id = cr.id
          AND cm.type = 'income'
    ), 0) AS total_entradas,

    COALESCE((
        SELECT SUM(cm.amount)
        FROM cash_movements cm
        WHERE cm.cash_register_id = cr.id
          AND cm.type = 'expense'
    ), 0) AS total_saidas,

    COALESCE((
        SELECT SUM(cm.amount)
        FROM cash_movements cm
        WHERE cm.cash_register_id = cr.id
          AND cm.type = 'adjustment'
    ), 0) AS total_ajustes,

    (
        cr.opening_amount
        + COALESCE((
            SELECT SUM(cm.amount)
            FROM cash_movements cm
            WHERE cm.cash_register_id = cr.id
              AND cm.type = 'income'
        ), 0)
        - COALESCE((
            SELECT SUM(cm.amount)
            FROM cash_movements cm
            WHERE cm.cash_register_id = cr.id
              AND cm.type = 'expense'
        ), 0)
    ) AS saldo_teorico_sem_ajustes

FROM cash_registers cr
INNER JOIN companies c
    ON c.id = cr.company_id
INNER JOIN users u_open
    ON u_open.id = cr.opened_by_user_id
LEFT JOIN users u_close
    ON u_close.id = cr.closed_by_user_id;

-- =========================================================
-- 9. FECHAMENTO DE CAIXA - MOVIMENTOS DETALHADOS
-- FINALIDADE:
--   Base detalhada de movimentos associados ao caixa.
-- =========================================================

CREATE VIEW vw_fechamento_caixa_movimentos AS
SELECT
    cm.id AS cash_movement_id,
    cm.company_id,
    cm.cash_register_id,
    cm.payment_id,
    cm.type,
    cm.description,
    cm.amount,
    cm.movement_at,
    cm.created_by_user_id,
    u.name AS created_by_user_name,
    p.payment_method_id,
    pm.name AS payment_method_name,
    pm.code AS payment_method_code,
    p.order_id,
    o.order_number
FROM cash_movements cm
LEFT JOIN users u
    ON u.id = cm.created_by_user_id
LEFT JOIN payments p
    ON p.id = cm.payment_id
LEFT JOIN payment_methods pm
    ON pm.id = p.payment_method_id
LEFT JOIN orders o
    ON o.id = p.order_id;

-- =========================================================
-- 10. TICKET MÉDIO POR EMPRESA
-- FINALIDADE:
--   Apoio gerencial para indicadores do dashboard.
-- =========================================================

CREATE VIEW vw_ticket_medio_por_empresa AS
SELECT
    o.company_id,
    COUNT(*) AS total_pedidos,
    SUM(o.total_amount) AS total_vendido,
    AVG(o.total_amount) AS ticket_medio
FROM orders o
WHERE o.status <> 'canceled'
GROUP BY o.company_id;

-- =========================================================
-- 11. VENDAS POR CATEGORIA
-- FINALIDADE:
--   Consolidar vendas por categoria de produto.
-- =========================================================

CREATE VIEW vw_vendas_por_categoria AS
SELECT
    oi.company_id,
    cat.id AS category_id,
    cat.name AS category_name,
    SUM(oi.quantity) AS total_quantidade_vendida,
    SUM(oi.line_subtotal) AS valor_total_vendido,
    COUNT(DISTINCT oi.order_id) AS total_pedidos_com_categoria
FROM order_items oi
INNER JOIN products p
    ON p.id = oi.product_id
INNER JOIN categories cat
    ON cat.id = p.category_id
INNER JOIN orders o
    ON o.id = oi.order_id
WHERE oi.status = 'active'
  AND o.status <> 'canceled'
GROUP BY
    oi.company_id,
    cat.id,
    cat.name;
