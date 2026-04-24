-- =========================================================
-- ARQUIVO: seed_demo.sql
-- SISTEMA: SaaS Menu Interativo
-- FINALIDADE:
--   Popular o banco com dados de demonstração e teste.
--
-- IMPORTANTE:
--   Execute este arquivo somente após schema_producao_implantacao.sql.
--   Não utilizar em produção real.
-- =========================================================

USE mesimenu;

SET foreign_key_checks = 0;

-- =========================================================
-- 1. PLANOS DEMO
-- =========================================================

INSERT INTO plans (
    id, name, slug, description, price_monthly, price_yearly, max_users, max_products, max_tables, features_json, status
) VALUES
(
    1, 'Básico', 'basico',
    'Plano inicial para pequenos estabelecimentos.',
    79.90, 799.00, 5, 80, 15,
    JSON_OBJECT(
        'cardapio_digital', true,
        'qrcode_mesa', true,
        'comandas', true,
        'cozinha', true,
        'pagamentos', true,
        'caixa', true,
        'delivery', false,
        'relatorios', true
    ),
    'ativo'
),
(
    2, 'Profissional', 'profissional',
    'Plano intermediário com operação ampliada e recursos gerenciais.',
    149.90, 1499.00, 15, 300, 50,
    JSON_OBJECT(
        'cardapio_digital', true,
        'qrcode_mesa', true,
        'comandas', true,
        'cozinha', true,
        'pagamentos', true,
        'caixa', true,
        'delivery', true,
        'relatorios', true,
        'promocoes', true,
        'cupons', true
    ),
    'ativo'
),
(
    3, 'Premium', 'premium',
    'Plano completo para operações mais robustas.',
    249.90, 2499.00, 50, 1000, 200,
    JSON_OBJECT(
        'cardapio_digital', true,
        'qrcode_mesa', true,
        'comandas', true,
        'cozinha', true,
        'pagamentos', true,
        'caixa', true,
        'delivery', true,
        'relatorios', true,
        'promocoes', true,
        'cupons', true,
        'multi_caixa', true,
        'estoque', true
    ),
    'ativo'
)
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
price_monthly = VALUES(price_monthly),
price_yearly = VALUES(price_yearly),
max_users = VALUES(max_users),
max_products = VALUES(max_products),
max_tables = VALUES(max_tables),
features_json = VALUES(features_json),
status = VALUES(status);

-- =========================================================
-- 2. EMPRESA DEMO
-- =========================================================

INSERT INTO companies (
    id, name, legal_name, document_number, email, phone, whatsapp, slug,
    status, plan_id, subscription_status, trial_ends_at, subscription_starts_at, subscription_ends_at
) VALUES (
    1,
    'Restaurante Sabor & Mesa',
    'Restaurante Sabor & Mesa LTDA',
    '12.345.678/0001-90',
    'contato@saboremesa.local',
    '91999990000',
    '91999990000',
    'restaurante-sabor-e-mesa',
    'ativa',
    2,
    'ativa',
    NULL,
    '2026-01-01 00:00:00',
    '2026-12-31 23:59:59'
)
ON DUPLICATE KEY UPDATE
name = VALUES(name),
legal_name = VALUES(legal_name),
document_number = VALUES(document_number),
email = VALUES(email),
phone = VALUES(phone),
whatsapp = VALUES(whatsapp),
status = VALUES(status),
plan_id = VALUES(plan_id),
subscription_status = VALUES(subscription_status),
subscription_starts_at = VALUES(subscription_starts_at),
subscription_ends_at = VALUES(subscription_ends_at);

INSERT INTO subscriptions (
    id, company_id, plan_id, status, billing_cycle, amount, starts_at, ends_at
) VALUES (
    1, 1, 2, 'ativa', 'mensal', 149.90, '2026-01-01 00:00:00', '2026-12-31 23:59:59'
)
ON DUPLICATE KEY UPDATE
company_id = VALUES(company_id),
plan_id = VALUES(plan_id),
status = VALUES(status),
billing_cycle = VALUES(billing_cycle),
amount = VALUES(amount),
starts_at = VALUES(starts_at),
ends_at = VALUES(ends_at);

INSERT INTO subscription_payments (
    id, subscription_id, company_id, reference_month, reference_year, amount, status, payment_method, paid_at, due_date, transaction_reference
) VALUES
(1, 1, 1, 1, 2026, 149.90, 'pago', 'pix', '2026-01-05 10:30:00', '2026-01-10', 'SUB-2026-01-0001'),
(2, 1, 1, 2, 2026, 149.90, 'pago', 'pix', '2026-02-05 10:45:00', '2026-02-10', 'SUB-2026-02-0001'),
(3, 1, 1, 3, 2026, 149.90, 'pago', 'pix', '2026-03-05 11:00:00', '2026-03-10', 'SUB-2026-03-0001')
ON DUPLICATE KEY UPDATE
status = VALUES(status),
payment_method = VALUES(payment_method),
paid_at = VALUES(paid_at),
due_date = VALUES(due_date),
transaction_reference = VALUES(transaction_reference);

-- =========================================================
-- 3. PERFIS E PERMISSÕES
-- =========================================================

INSERT INTO roles (id, name, slug, context, description) VALUES
(1, 'Administrador do Estabelecimento', 'admin_establishment', 'company', 'Controle administrativo completo da empresa'),
(2, 'Gerente Operacional', 'manager', 'company', 'Supervisão operacional e parcial administrativa'),
(3, 'Caixa', 'cashier', 'company', 'Operação de pagamentos e caixa'),
(4, 'Garçom', 'waiter', 'company', 'Atendimento e gestão de comandas'),
(5, 'Cozinha', 'kitchen', 'company', 'Produção e atualização de status de pedidos'),
(6, 'Motoboy', 'delivery', 'company', 'Operação de entrega'),
(7, 'Administrador SaaS', 'saas_admin', 'saas', 'Administração global da plataforma'),
(8, 'Suporte SaaS', 'saas_support', 'saas', 'Suporte institucional da plataforma'),
(9, 'Financeiro SaaS', 'saas_financial', 'saas', 'Cobranças e assinaturas da plataforma')
ON DUPLICATE KEY UPDATE
name = VALUES(name),
context = VALUES(context),
description = VALUES(description);

INSERT INTO permissions (id, module, action, slug, description) VALUES
(1, 'dashboard', 'view', 'dashboard.view', 'Visualizar dashboards'),
(2, 'products', 'view', 'products.view', 'Visualizar produtos'),
(3, 'products', 'create', 'products.create', 'Cadastrar produtos'),
(4, 'products', 'edit', 'products.edit', 'Editar produtos'),
(5, 'products', 'pause', 'products.pause', 'Pausar produtos'),
(6, 'categories', 'view', 'categories.view', 'Visualizar categorias'),
(7, 'categories', 'create', 'categories.create', 'Cadastrar categorias'),
(8, 'categories', 'edit', 'categories.edit', 'Editar categorias'),
(9, 'additionals', 'view', 'additionals.view', 'Visualizar adicionais'),
(10, 'additionals', 'create', 'additionals.create', 'Cadastrar adicionais'),
(11, 'additionals', 'edit', 'additionals.edit', 'Editar adicionais'),
(12, 'tables', 'view', 'tables.view', 'Visualizar mesas'),
(13, 'tables', 'manage', 'tables.manage', 'Gerenciar mesas'),
(14, 'commands', 'view', 'commands.view', 'Visualizar comandas'),
(15, 'commands', 'create', 'commands.create', 'Criar comandas'),
(16, 'commands', 'edit', 'commands.edit', 'Editar comandas'),
(17, 'orders', 'view', 'orders.view', 'Visualizar pedidos'),
(18, 'orders', 'create', 'orders.create', 'Criar pedidos'),
(19, 'orders', 'status', 'orders.status', 'Alterar status dos pedidos'),
(20, 'orders', 'cancel', 'orders.cancel', 'Cancelar pedidos'),
(21, 'payments', 'view', 'payments.view', 'Visualizar pagamentos'),
(22, 'payments', 'create', 'payments.create', 'Registrar pagamentos'),
(23, 'cash_registers', 'open', 'cash_registers.open', 'Abrir caixa'),
(24, 'cash_registers', 'close', 'cash_registers.close', 'Fechar caixa'),
(25, 'reports', 'view', 'reports.view', 'Visualizar relatórios'),
(26, 'users', 'view', 'users.view', 'Visualizar usuários'),
(27, 'users', 'create', 'users.create', 'Cadastrar usuários'),
(28, 'users', 'edit', 'users.edit', 'Editar usuários'),
(29, 'settings', 'edit', 'settings.edit', 'Editar configurações'),
(30, 'themes', 'edit', 'themes.edit', 'Editar tema visual'),
(31, 'companies', 'view', 'companies.view', 'Visualizar empresas'),
(32, 'companies', 'manage', 'companies.manage', 'Gerenciar empresas'),
(33, 'plans', 'view', 'plans.view', 'Visualizar planos'),
(34, 'plans', 'manage', 'plans.manage', 'Gerenciar planos'),
(35, 'subscriptions', 'view', 'subscriptions.view', 'Visualizar assinaturas'),
(36, 'subscriptions', 'manage', 'subscriptions.manage', 'Gerenciar assinaturas'),
(37, 'support', 'view', 'support.view', 'Visualizar chamados'),
(38, 'support', 'manage', 'support.manage', 'Gerenciar chamados'),
(39, 'stock', 'view', 'stock.view', 'Visualizar estoque'),
(40, 'stock', 'manage', 'stock.manage', 'Gerenciar estoque')
ON DUPLICATE KEY UPDATE
module = VALUES(module),
action = VALUES(action),
description = VALUES(description);

DELETE FROM role_permissions;

INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions
WHERE slug IN (
    'dashboard.view',
    'products.view',
    'products.create',
    'products.edit',
    'products.pause',
    'categories.view',
    'categories.create',
    'categories.edit',
    'additionals.view',
    'additionals.create',
    'additionals.edit',
    'tables.view',
    'tables.manage',
    'commands.view',
    'commands.create',
    'commands.edit',
    'orders.view',
    'orders.create',
    'orders.status',
    'orders.cancel',
    'payments.view',
    'payments.create',
    'stock.view',
    'stock.manage',
    'cash_registers.open',
    'cash_registers.close',
    'reports.view',
    'users.view',
    'users.create',
    'users.edit',
    'settings.edit',
    'themes.edit'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE slug IN (
    'dashboard.view','products.view','products.edit','products.pause','categories.view',
    'additionals.view','tables.view','tables.manage','commands.view','commands.create',
    'commands.edit','orders.view','orders.create','orders.status','orders.cancel',
    'payments.view','reports.view','stock.view','stock.manage'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE slug IN (
    'dashboard.view','commands.view','orders.view','payments.view','payments.create',
    'cash_registers.open','cash_registers.close'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE slug IN (
    'tables.view','tables.manage','commands.view','commands.create','commands.edit',
    'orders.view','orders.create'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions
WHERE slug IN ('orders.view','orders.status');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions
WHERE slug IN ('orders.view','orders.status');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions
WHERE slug IN (
    'companies.view','companies.manage','plans.view','plans.manage',
    'subscriptions.view','subscriptions.manage','support.view','support.manage',
    'users.view','users.create','users.edit','dashboard.view'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions
WHERE slug IN (
    'companies.view','subscriptions.view','support.view','support.manage','dashboard.view'
);

INSERT INTO role_permissions (role_id, permission_id)
SELECT 9, id FROM permissions
WHERE slug IN (
    'companies.view','plans.view','subscriptions.view','subscriptions.manage','dashboard.view'
);

-- =========================================================
-- 4. USUÁRIOS DEMO
-- SENHA DE TESTE SUGERIDA:
-- 123456
-- HASH DE EXEMPLO: recriar idealmente pela aplicação
-- =========================================================

INSERT INTO users (
    id, company_id, role_id, name, email, phone, password_hash, status, is_saas_user, last_login_at
) VALUES
(1, 1, 1, 'Administrador Demo', 'admin@saboremesa.local', '91911110001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 0, NULL),
(2, 1, 2, 'Gerente Demo', 'gerente@saboremesa.local', '91911110002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 0, NULL),
(3, 1, 3, 'Caixa Demo', 'caixa@saboremesa.local', '91911110003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 0, NULL),
(4, 1, 4, 'Garçom Demo', 'garcom@saboremesa.local', '91911110004', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 0, NULL),
(5, 1, 5, 'Cozinha Demo', 'cozinha@saboremesa.local', '91911110005', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 0, NULL),
(6, 1, 6, 'Motoboy Demo', 'motoboy@saboremesa.local', '91911110006', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 0, NULL),
(7, NULL, 7, 'Admin SaaS Demo', 'saas.admin@menu.local', '91922220001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 1, NULL),
(8, NULL, 8, 'Suporte SaaS Demo', 'saas.suporte@menu.local', '91922220002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 1, NULL),
(9, NULL, 9, 'Financeiro SaaS Demo', 'saas.financeiro@menu.local', '91922220003', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa', 'ativo', 1, NULL)
ON DUPLICATE KEY UPDATE
company_id = VALUES(company_id),
role_id = VALUES(role_id),
name = VALUES(name),
phone = VALUES(phone),
password_hash = VALUES(password_hash),
status = VALUES(status),
is_saas_user = VALUES(is_saas_user);

-- =========================================================
-- 5. CONFIGURAÇÕES E TEMA DEMO
-- =========================================================

INSERT INTO company_settings (
    id, company_id, opening_time, closing_time,
    allow_orders_outside_business_hours, minimum_order_amount,
    accept_pix, accept_online_payment, accept_cash, accept_credit_card, accept_debit_card,
    allow_table_service, allow_delivery, allow_pickup, allow_counter_order,
    default_order_status, auto_print_orders, currency_code, timezone
) VALUES (
    1, 1, '10:00:00', '23:00:00',
    0, 20.00,
    1, 1, 1, 1, 1,
    1, 1, 1, 1,
    'pending', 1, 'BRL', 'America/Belem'
)
ON DUPLICATE KEY UPDATE
opening_time = VALUES(opening_time),
closing_time = VALUES(closing_time),
allow_orders_outside_business_hours = VALUES(allow_orders_outside_business_hours),
minimum_order_amount = VALUES(minimum_order_amount),
accept_pix = VALUES(accept_pix),
accept_online_payment = VALUES(accept_online_payment),
accept_cash = VALUES(accept_cash),
accept_credit_card = VALUES(accept_credit_card),
accept_debit_card = VALUES(accept_debit_card),
allow_table_service = VALUES(allow_table_service),
allow_delivery = VALUES(allow_delivery),
allow_pickup = VALUES(allow_pickup),
allow_counter_order = VALUES(allow_counter_order),
default_order_status = VALUES(default_order_status),
auto_print_orders = VALUES(auto_print_orders),
currency_code = VALUES(currency_code),
timezone = VALUES(timezone);

INSERT INTO company_themes (
    id, company_id, primary_color, secondary_color, accent_color, main_card_color, logo_path, banner_path, title, description, footer_text
) VALUES (
    1, 1, '#D62828', '#1D3557', '#F4A261', '#1D3557',
    'uploads/logos/sabor-mesa-logo.png',
    'uploads/banners/sabor-mesa-banner.png',
    'Restaurante Sabor & Mesa',
    'Experiência digital para pedidos por mesa, balcão e delivery.',
    'Restaurante Sabor & Mesa - Atendimento digital'
)
ON DUPLICATE KEY UPDATE
primary_color = VALUES(primary_color),
secondary_color = VALUES(secondary_color),
accent_color = VALUES(accent_color),
main_card_color = VALUES(main_card_color),
logo_path = VALUES(logo_path),
banner_path = VALUES(banner_path),
title = VALUES(title),
description = VALUES(description),
footer_text = VALUES(footer_text);

-- =========================================================
-- 6. CATEGORIAS, PRODUTOS E ADICIONAIS DEMO
-- =========================================================

INSERT INTO categories (
    id, company_id, name, slug, description, display_order, status
) VALUES
(1, 1, 'Entradas', 'entradas', 'Petiscos e entradas iniciais.', 1, 'ativo'),
(2, 1, 'Hambúrgueres', 'hamburgueres', 'Linha principal de hambúrgueres artesanais.', 2, 'ativo'),
(3, 1, 'Pizzas', 'pizzas', 'Pizzas salgadas e doces.', 3, 'ativo'),
(4, 1, 'Bebidas', 'bebidas', 'Sucos, refrigerantes e bebidas diversas.', 4, 'ativo'),
(5, 1, 'Sobremesas', 'sobremesas', 'Sobremesas e doces.', 5, 'ativo')
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
display_order = VALUES(display_order),
status = VALUES(status);

INSERT INTO products (
    id, company_id, category_id, name, slug, description, sku, image_path,
    price, promotional_price, is_featured, is_active, is_paused, allows_notes, has_additionals, display_order
) VALUES
(1, 1, 1, 'Batata Frita Tradicional', 'batata-frita-tradicional', 'Porção média de batata frita crocante.', 'ENT-001', 'uploads/products/batata-frita.png', 18.00, NULL, 1, 1, 0, 1, 0, 1),
(2, 1, 2, 'Hambúrguer Clássico', 'hamburguer-classico', 'Pão brioche, carne artesanal, queijo e salada.', 'HAM-001', 'uploads/products/hamburguer-classico.png', 28.00, 25.90, 1, 1, 0, 1, 1, 1),
(3, 1, 2, 'Hambúrguer Bacon Especial', 'hamburguer-bacon-especial', 'Pão brioche, carne artesanal, bacon e cheddar.', 'HAM-002', 'uploads/products/hamburguer-bacon.png', 32.00, NULL, 1, 1, 0, 1, 1, 2),
(4, 1, 3, 'Pizza Calabresa Média', 'pizza-calabresa-media', 'Pizza média de calabresa com cebola.', 'PIZ-001', 'uploads/products/pizza-calabresa.png', 49.90, NULL, 1, 1, 0, 1, 1, 1),
(5, 1, 4, 'Refrigerante Lata', 'refrigerante-lata', 'Refrigerante lata 350ml.', 'BEB-001', 'uploads/products/refrigerante-lata.png', 6.50, NULL, 0, 1, 0, 0, 0, 1),
(6, 1, 4, 'Suco Natural', 'suco-natural', 'Suco natural 300ml.', 'BEB-002', 'uploads/products/suco-natural.png', 8.90, NULL, 0, 1, 0, 1, 0, 2),
(7, 1, 5, 'Brownie com Sorvete', 'brownie-com-sorvete', 'Brownie artesanal servido com sorvete.', 'SOB-001', 'uploads/products/brownie-sorvete.png', 16.00, NULL, 1, 1, 0, 1, 0, 1)
ON DUPLICATE KEY UPDATE
category_id = VALUES(category_id),
name = VALUES(name),
description = VALUES(description),
sku = VALUES(sku),
image_path = VALUES(image_path),
price = VALUES(price),
promotional_price = VALUES(promotional_price),
is_featured = VALUES(is_featured),
is_active = VALUES(is_active),
is_paused = VALUES(is_paused),
allows_notes = VALUES(allows_notes),
has_additionals = VALUES(has_additionals),
display_order = VALUES(display_order);

INSERT INTO additional_groups (
    id, company_id, name, description, is_required, min_selection, max_selection, status
) VALUES
(1, 1, 'Adicionais de Hambúrguer', 'Complementos opcionais para hambúrgueres.', 0, 0, 5, 'ativo'),
(2, 1, 'Borda Recheada', 'Escolha de borda para pizzas.', 0, 0, 1, 'ativo')
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
is_required = VALUES(is_required),
min_selection = VALUES(min_selection),
max_selection = VALUES(max_selection),
status = VALUES(status);

INSERT INTO additional_items (
    id, company_id, additional_group_id, name, description, price, status, display_order
) VALUES
(1, 1, 1, 'Queijo Extra', 'Porção adicional de queijo.', 3.50, 'ativo', 1),
(2, 1, 1, 'Bacon Extra', 'Porção adicional de bacon.', 4.50, 'ativo', 2),
(3, 1, 1, 'Ovo', 'Adicionar ovo ao hambúrguer.', 2.50, 'ativo', 3),
(4, 1, 2, 'Borda de Catupiry', 'Borda recheada com catupiry.', 8.00, 'ativo', 1),
(5, 1, 2, 'Borda de Cheddar', 'Borda recheada com cheddar.', 8.00, 'ativo', 2)
ON DUPLICATE KEY UPDATE
additional_group_id = VALUES(additional_group_id),
name = VALUES(name),
description = VALUES(description),
price = VALUES(price),
status = VALUES(status),
display_order = VALUES(display_order);

INSERT INTO product_additional_groups (
    id, company_id, product_id, additional_group_id
) VALUES
(1, 1, 2, 1),
(2, 1, 3, 1),
(3, 1, 4, 2)
ON DUPLICATE KEY UPDATE
company_id = VALUES(company_id),
product_id = VALUES(product_id),
additional_group_id = VALUES(additional_group_id);

-- =========================================================
-- 7. MESAS, MÉTODOS DE PAGAMENTO E DADOS OPERACIONAIS DEMO
-- =========================================================

INSERT INTO tables (
    id, company_id, name, number, capacity, qr_code_token, status
) VALUES
(1, 1, 'Mesa 01', 1, 4, 'mesa-01-token-demo', 'livre'),
(2, 1, 'Mesa 02', 2, 4, 'mesa-02-token-demo', 'livre'),
(3, 1, 'Mesa 03', 3, 6, 'mesa-03-token-demo', 'livre'),
(4, 1, 'Mesa 04', 4, 2, 'mesa-04-token-demo', 'livre'),
(5, 1, 'Varanda 01', 5, 4, 'mesa-05-token-demo', 'livre')
ON DUPLICATE KEY UPDATE
name = VALUES(name),
number = VALUES(number),
capacity = VALUES(capacity),
qr_code_token = VALUES(qr_code_token),
status = VALUES(status);

INSERT INTO payment_methods (
    id, company_id, name, code, status
) VALUES
(1, 1, 'Pix', 'pix', 'ativo'),
(2, 1, 'Dinheiro', 'cash', 'ativo'),
(3, 1, 'Cartão de Crédito', 'credit_card', 'ativo'),
(4, 1, 'Cartão de Débito', 'debit_card', 'ativo'),
(5, 1, 'Pagamento Online', 'online', 'ativo')
ON DUPLICATE KEY UPDATE
name = VALUES(name),
status = VALUES(status);

INSERT INTO delivery_zones (
    id, company_id, name, description, fee_amount, minimum_order_amount, status
) VALUES
(1, 1, 'Centro', 'Região central com entrega rápida.', 5.00, 20.00, 'ativo'),
(2, 1, 'Bairro Próximo', 'Bairros de raio curto.', 7.00, 25.00, 'ativo'),
(3, 1, 'Bairro Distante', 'Regiões mais afastadas.', 10.00, 35.00, 'ativo')
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
fee_amount = VALUES(fee_amount),
minimum_order_amount = VALUES(minimum_order_amount),
status = VALUES(status);

INSERT INTO customers (
    id, company_id, name, phone, email, document_number, birth_date, notes, status
) VALUES (
    1, 1, 'Cliente Demo', '91988887777', 'cliente.demo@local', NULL, NULL,
    'Cliente de teste para simulações do sistema.', 'ativo'
)
ON DUPLICATE KEY UPDATE
name = VALUES(name),
phone = VALUES(phone),
email = VALUES(email),
notes = VALUES(notes),
status = VALUES(status);

INSERT INTO delivery_addresses (
    id, company_id, customer_id, label, street, number, complement, neighborhood, city, state, zip_code, reference, delivery_zone_id
) VALUES (
    1, 1, 1, 'Casa', 'Av. Principal', '100', 'Ap 101', 'Centro', 'Belém', 'PA', '66000-000', 'Próximo à praça central', 1
)
ON DUPLICATE KEY UPDATE
label = VALUES(label),
street = VALUES(street),
number = VALUES(number),
complement = VALUES(complement),
neighborhood = VALUES(neighborhood),
city = VALUES(city),
state = VALUES(state),
zip_code = VALUES(zip_code),
reference = VALUES(reference),
delivery_zone_id = VALUES(delivery_zone_id);

INSERT INTO promotions (
    id, company_id, name, description, discount_type, discount_value, starts_at, ends_at, minimum_order_amount, status
) VALUES (
    1, 1, 'Promo Hambúrguer da Semana', 'Desconto especial em hambúrgueres selecionados.',
    'percent', 10.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 20.00, 'ativo'
)
ON DUPLICATE KEY UPDATE
name = VALUES(name),
description = VALUES(description),
discount_type = VALUES(discount_type),
discount_value = VALUES(discount_value),
starts_at = VALUES(starts_at),
ends_at = VALUES(ends_at),
minimum_order_amount = VALUES(minimum_order_amount),
status = VALUES(status);

INSERT INTO promotion_products (
    id, promotion_id, product_id
) VALUES
(1, 1, 2),
(2, 1, 3)
ON DUPLICATE KEY UPDATE
promotion_id = VALUES(promotion_id),
product_id = VALUES(product_id);

INSERT INTO coupons (
    id, company_id, code, description, discount_type, discount_value, minimum_order_amount, usage_limit, used_count, starts_at, ends_at, status
) VALUES (
    1, 1, 'BEMVINDO10', 'Cupom de boas-vindas para novos clientes.',
    'percent', 10.00, 30.00, 100, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'ativo'
)
ON DUPLICATE KEY UPDATE
description = VALUES(description),
discount_type = VALUES(discount_type),
discount_value = VALUES(discount_value),
minimum_order_amount = VALUES(minimum_order_amount),
usage_limit = VALUES(usage_limit),
used_count = VALUES(used_count),
starts_at = VALUES(starts_at),
ends_at = VALUES(ends_at),
status = VALUES(status);

SET foreign_key_checks = 1;
