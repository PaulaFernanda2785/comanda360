-- =========================================================
-- ARQUIVO: schema_producao_implantacao.sql
-- SISTEMA: SaaS Menu Interativo
-- FINALIDADE:
--   Estrutura de produção/implantação do banco de dados.
--   Este arquivo contém apenas DDL (estrutura), sem dados demo.
--
-- RECURSOS DESTA VERSÃO:
--   - Bloco opcional de DROP TABLE IF EXISTS
--   - Criação ordenada para reexecução segura
--   - Chaves estrangeiras e índices
--   - Comentários por tabela e coluna
--
-- USO RECOMENDADO:
--   1) Revisar o nome do banco
--   2) Executar este arquivo
--   3) Executar seed_demo.sql apenas em ambiente de teste
-- =========================================================

-- =========================================================
-- 1. CRIAÇÃO DO BANCO
-- =========================================================

CREATE DATABASE IF NOT EXISTS mesimenu
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE mesimenu;

-- =========================================================
-- 2. CONFIGURAÇÕES INICIAIS
-- =========================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- =========================================================
-- 3. BLOCO OPCIONAL DE LIMPEZA
-- DESCOMENTE SOMENTE SE QUISER RECRIAR TUDO DO ZERO
-- =========================================================
/*
SET foreign_key_checks = 0;

DROP TABLE IF EXISTS coupon_usages;
DROP TABLE IF EXISTS promotion_products;
DROP TABLE IF EXISTS promotions;
DROP TABLE IF EXISTS coupons;

DROP TABLE IF EXISTS deliveries;
DROP TABLE IF EXISTS delivery_addresses;
DROP TABLE IF EXISTS delivery_zones;

DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS stock_item_products;
DROP TABLE IF EXISTS stock_items;

DROP TABLE IF EXISTS cash_movements;
DROP TABLE IF EXISTS cash_registers;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS payment_methods;

DROP TABLE IF EXISTS kitchen_print_logs;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_item_additionals;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;

DROP TABLE IF EXISTS commands;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS tables;

DROP TABLE IF EXISTS product_additional_groups;
DROP TABLE IF EXISTS additional_items;
DROP TABLE IF EXISTS additional_groups;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;

DROP TABLE IF EXISTS company_themes;
DROP TABLE IF EXISTS company_settings;

DROP TABLE IF EXISTS public_interactions;
DROP TABLE IF EXISTS support_ticket_messages;
DROP TABLE IF EXISTS support_tickets;
DROP TABLE IF EXISTS audit_logs;

DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;

DROP TABLE IF EXISTS subscription_payments;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS plans;

SET foreign_key_checks = 1;
*/

SET foreign_key_checks = 0;

-- =========================================================
-- 4. BLOCO SAAS INSTITUCIONAL
-- =========================================================

CREATE TABLE IF NOT EXISTS plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único do plano',
    name VARCHAR(120) NOT NULL COMMENT 'Nome comercial do plano',
    slug VARCHAR(120) NOT NULL COMMENT 'Identificador textual único do plano',
    description TEXT NULL COMMENT 'Descrição comercial do plano',
    price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor mensal do plano',
    price_yearly DECIMAL(10,2) NULL COMMENT 'Valor anual do plano',
    max_users INT UNSIGNED NULL COMMENT 'Limite de usuários por empresa no plano',
    max_products INT UNSIGNED NULL COMMENT 'Limite de produtos por empresa no plano',
    max_tables INT UNSIGNED NULL COMMENT 'Limite de mesas por empresa no plano',
    features_json JSON NULL COMMENT 'Recursos habilitados no plano em formato JSON',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação do plano',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT uq_plans_slug UNIQUE (slug),
    CONSTRAINT chk_plans_price_monthly CHECK (price_monthly >= 0),
    CONSTRAINT chk_plans_price_yearly CHECK (price_yearly IS NULL OR price_yearly >= 0),
    CONSTRAINT chk_plans_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Planos comerciais do SaaS';

CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador único da empresa',
    name VARCHAR(150) NOT NULL COMMENT 'Nome fantasia do estabelecimento',
    legal_name VARCHAR(180) NULL COMMENT 'Razão social do estabelecimento',
    document_number VARCHAR(20) NULL COMMENT 'CPF ou CNPJ',
    email VARCHAR(150) NOT NULL COMMENT 'E-mail principal da empresa',
    phone VARCHAR(25) NULL COMMENT 'Telefone principal',
    whatsapp VARCHAR(25) NULL COMMENT 'WhatsApp comercial',
    slug VARCHAR(150) NOT NULL COMMENT 'Identificador amigável único da empresa',
    status VARCHAR(20) NOT NULL DEFAULT 'teste' COMMENT 'Situação operacional da empresa na plataforma',
    plan_id BIGINT UNSIGNED NULL COMMENT 'Plano atual vinculado à empresa',
    subscription_status VARCHAR(30) NOT NULL DEFAULT 'trial' COMMENT 'Estado da assinatura atual',
    trial_ends_at DATETIME NULL COMMENT 'Data de fim do período de teste',
    subscription_starts_at DATETIME NULL COMMENT 'Data de início da assinatura',
    subscription_ends_at DATETIME NULL COMMENT 'Data de fim da assinatura',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT uq_companies_slug UNIQUE (slug),
    CONSTRAINT fk_companies_plan
        FOREIGN KEY (plan_id) REFERENCES plans(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_companies_status CHECK (status IN ('ativa', 'teste', 'suspensa', 'cancelada')),
    CONSTRAINT chk_companies_subscription_status CHECK (
        subscription_status IN ('ativa', 'trial', 'inadimplente', 'suspensa', 'cancelada')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Empresas assinantes do SaaS';

CREATE INDEX idx_companies_status ON companies(status);
CREATE INDEX idx_companies_plan_id ON companies(plan_id);
CREATE INDEX idx_companies_subscription_status ON companies(subscription_status);

CREATE TABLE IF NOT EXISTS subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da assinatura',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa assinante',
    plan_id BIGINT UNSIGNED NOT NULL COMMENT 'Plano vinculado à assinatura',
    status VARCHAR(20) NOT NULL DEFAULT 'trial' COMMENT 'Situação da assinatura',
    billing_cycle VARCHAR(20) NOT NULL COMMENT 'Periodicidade de cobrança',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor contratado',
    billing_credit_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo de credito acumulado para abatimento nas proximas cobrancas',
    starts_at DATETIME NOT NULL COMMENT 'Data inicial da assinatura',
    ends_at DATETIME NULL COMMENT 'Data final prevista',
    canceled_at DATETIME NULL COMMENT 'Data de cancelamento',
    preferred_payment_method VARCHAR(20) NULL COMMENT 'Metodo preferencial da assinatura',
    auto_charge_enabled TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Recorrencia automatica por cartao habilitada',
    card_brand VARCHAR(30) NULL COMMENT 'Bandeira do cartao salvo para recorrencia',
    card_last_digits VARCHAR(4) NULL COMMENT 'Ultimos 4 digitos do cartao salvo',
    gateway_provider VARCHAR(30) NULL COMMENT 'Provedor externo de cobranca recorrente',
    gateway_subscription_id VARCHAR(120) NULL COMMENT 'Identificador da assinatura no gateway',
    gateway_checkout_url VARCHAR(255) NULL COMMENT 'Link externo de autorizacao da assinatura',
    gateway_status VARCHAR(40) NULL COMMENT 'Status retornado pelo gateway',
    gateway_webhook_payload_json JSON NULL COMMENT 'Ultimo payload bruto recebido do gateway',
    gateway_last_synced_at DATETIME NULL COMMENT 'Data da ultima sincronizacao com o gateway',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_subscriptions_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_subscriptions_plan
        FOREIGN KEY (plan_id) REFERENCES plans(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_subscriptions_status CHECK (
        status IN ('ativa', 'vencida', 'cancelada', 'trial')
    ),
    CONSTRAINT chk_subscriptions_billing_cycle CHECK (
        billing_cycle IN ('mensal', 'anual')
    ),
    CONSTRAINT chk_subscriptions_amount CHECK (amount >= 0),
    CONSTRAINT chk_subscriptions_billing_credit_balance CHECK (billing_credit_balance >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de assinaturas por empresa';

CREATE INDEX idx_subscriptions_company_id ON subscriptions(company_id);
CREATE INDEX idx_subscriptions_plan_id ON subscriptions(plan_id);
CREATE INDEX idx_subscriptions_status ON subscriptions(status);
CREATE INDEX idx_subscriptions_company_status ON subscriptions(company_id, status);

CREATE TABLE IF NOT EXISTS subscription_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do pagamento da assinatura',
    subscription_id BIGINT UNSIGNED NOT NULL COMMENT 'Assinatura relacionada',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa cobrada',
    reference_month TINYINT UNSIGNED NOT NULL COMMENT 'Mês de referência da cobrança',
    reference_year SMALLINT UNSIGNED NOT NULL COMMENT 'Ano de referência da cobrança',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor cobrado',
    status VARCHAR(20) NOT NULL DEFAULT 'pendente' COMMENT 'Situação da cobrança',
    payment_method VARCHAR(30) NULL COMMENT 'Forma de pagamento da assinatura',
    paid_at DATETIME NULL COMMENT 'Data/hora do pagamento',
    due_date DATE NOT NULL COMMENT 'Data de vencimento',
    transaction_reference VARCHAR(120) NULL COMMENT 'Código de transação externa',
    charge_origin VARCHAR(20) NOT NULL DEFAULT 'manual' COMMENT 'Origem da geracao da cobranca',
    pix_code VARCHAR(180) NULL COMMENT 'Codigo textual PIX vinculado a cobranca',
    pix_qr_payload TEXT NULL COMMENT 'Payload textual para representacao do QR PIX',
    pix_qr_image_base64 LONGTEXT NULL COMMENT 'Imagem do QR PIX em base64 vinda do gateway',
    pix_ticket_url VARCHAR(255) NULL COMMENT 'URL hospedada para pagamento PIX',
    payment_details_json JSON NULL COMMENT 'Metadados operacionais do pagamento',
    gateway_payment_id VARCHAR(120) NULL COMMENT 'Identificador do pagamento no gateway',
    gateway_payment_url VARCHAR(255) NULL COMMENT 'URL externa de pagamento no gateway',
    gateway_status VARCHAR(40) NULL COMMENT 'Status retornado pelo gateway para a cobranca',
    gateway_webhook_payload_json JSON NULL COMMENT 'Ultimo payload bruto recebido do gateway',
    gateway_last_synced_at DATETIME NULL COMMENT 'Data da ultima sincronizacao com o gateway',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_subscription_payments_subscription
        FOREIGN KEY (subscription_id) REFERENCES subscriptions(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_subscription_payments_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_subscription_payments_reference_month CHECK (reference_month BETWEEN 1 AND 12),
    CONSTRAINT chk_subscription_payments_amount CHECK (amount >= 0),
    CONSTRAINT chk_subscription_payments_status CHECK (
        status IN ('pendente', 'pago', 'vencido', 'cancelado')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pagamentos e cobranças de assinaturas';

CREATE INDEX idx_subscription_payments_subscription_id ON subscription_payments(subscription_id);
CREATE INDEX idx_subscription_payments_company_id ON subscription_payments(company_id);
CREATE INDEX idx_subscription_payments_status ON subscription_payments(status);
CREATE INDEX idx_subscription_payments_due_date ON subscription_payments(due_date);

-- =========================================================
-- 5. BLOCO DE USUÁRIOS, PERFIS E PERMISSÕES
-- =========================================================

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do perfil',
    name VARCHAR(100) NOT NULL COMMENT 'Nome do perfil de acesso',
    slug VARCHAR(100) NOT NULL COMMENT 'Chave única do perfil',
    context VARCHAR(30) NOT NULL COMMENT 'Contexto do perfil: company, saas ou public',
    description TEXT NULL COMMENT 'Descrição do perfil',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT uq_roles_slug UNIQUE (slug),
    CONSTRAINT chk_roles_context CHECK (context IN ('company', 'saas', 'public'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Perfis de acesso do sistema';

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da permissão',
    module VARCHAR(80) NOT NULL COMMENT 'Módulo funcional da permissão',
    action VARCHAR(80) NOT NULL COMMENT 'Ação autorizada',
    slug VARCHAR(150) NOT NULL COMMENT 'Chave única da permissão',
    description TEXT NULL COMMENT 'Descrição da permissão',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    CONSTRAINT uq_permissions_slug UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permissões de acesso do sistema';

CREATE TABLE IF NOT EXISTS role_permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da relação role x permission',
    role_id BIGINT UNSIGNED NOT NULL COMMENT 'Perfil vinculado',
    permission_id BIGINT UNSIGNED NOT NULL COMMENT 'Permissão vinculada',
    CONSTRAINT fk_role_permissions_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission
        FOREIGN KEY (permission_id) REFERENCES permissions(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_role_permissions UNIQUE (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Relação entre perfis e permissões';

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do usuário',
    company_id BIGINT UNSIGNED NULL COMMENT 'Empresa do usuário; nulo para usuários globais do SaaS',
    role_id BIGINT UNSIGNED NOT NULL COMMENT 'Perfil do usuário',
    name VARCHAR(150) NOT NULL COMMENT 'Nome completo do usuário',
    email VARCHAR(150) NOT NULL COMMENT 'E-mail de login',
    phone VARCHAR(25) NULL COMMENT 'Telefone do usuário',
    password_hash VARCHAR(255) NOT NULL COMMENT 'Senha armazenada em hash seguro',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação do usuário',
    is_saas_user TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica se é usuário institucional do SaaS',
    last_login_at DATETIME NULL COMMENT 'Data/hora do último login',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    deleted_at DATETIME NULL COMMENT 'Exclusão lógica do usuário',
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT fk_users_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_users_status CHECK (status IN ('ativo', 'inativo', 'bloqueado')),
    CONSTRAINT chk_users_is_saas_user CHECK (is_saas_user IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuários internos da empresa e usuários globais do SaaS';

CREATE INDEX idx_users_company_id ON users(company_id);
CREATE INDEX idx_users_role_id ON users(role_id);
CREATE INDEX idx_users_company_status ON users(company_id, status);

-- =========================================================
-- 6. BLOCO DE CONFIGURAÇÕES E PERSONALIZAÇÃO
-- =========================================================

CREATE TABLE IF NOT EXISTS company_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador das configurações da empresa',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária das configurações',
    opening_time TIME NULL COMMENT 'Hora padrão de abertura',
    closing_time TIME NULL COMMENT 'Hora padrão de fechamento',
    allow_orders_outside_business_hours TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Permite pedidos fora do horário de funcionamento',
    minimum_order_amount DECIMAL(10,2) NULL DEFAULT 0.00 COMMENT 'Valor mínimo do pedido',
    accept_pix TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Aceita pagamentos via Pix',
    accept_online_payment TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Aceita pagamento online integrado',
    accept_cash TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Aceita dinheiro',
    accept_credit_card TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Aceita cartão de crédito',
    accept_debit_card TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Aceita cartão de débito',
    allow_table_service TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Permite operação por mesa',
    allow_delivery TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Permite pedidos delivery',
    allow_pickup TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Permite retirada no local',
    allow_counter_order TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Permite pedidos no balcão',
    default_order_status VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'Status inicial padrão do pedido',
    auto_print_orders TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Ativa impressão automática dos pedidos',
    currency_code VARCHAR(10) NOT NULL DEFAULT 'BRL' COMMENT 'Código da moeda utilizada',
    timezone VARCHAR(60) NOT NULL DEFAULT 'America/Belem' COMMENT 'Fuso horário da empresa',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT uq_company_settings_company_id UNIQUE (company_id),
    CONSTRAINT fk_company_settings_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_company_settings_minimum_order_amount CHECK (
        minimum_order_amount IS NULL OR minimum_order_amount >= 0
    ),
    CONSTRAINT chk_company_settings_default_order_status CHECK (
        default_order_status IN ('pending', 'received', 'preparing', 'ready')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configurações operacionais e comerciais por empresa';

CREATE TABLE IF NOT EXISTS company_themes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do tema visual',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do tema',
    primary_color VARCHAR(20) NULL COMMENT 'Cor principal do sistema',
    secondary_color VARCHAR(20) NULL COMMENT 'Cor secundária',
    accent_color VARCHAR(20) NULL COMMENT 'Cor de destaque',
    main_card_color VARCHAR(20) NULL COMMENT 'Cor do card principal das páginas',
    logo_path VARCHAR(255) NULL COMMENT 'Caminho do arquivo da logo',
    banner_path VARCHAR(255) NULL COMMENT 'Caminho do arquivo do banner',
    title VARCHAR(150) NULL COMMENT 'Título exibido na área pública',
    description TEXT NULL COMMENT 'Descrição pública do estabelecimento',
    footer_text VARCHAR(255) NULL COMMENT 'Texto de rodapé do cardápio/site',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT uq_company_themes_company_id UNIQUE (company_id),
    CONSTRAINT fk_company_themes_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tema visual e identidade pública por empresa';

-- =========================================================
-- 7. BLOCO DE CATÁLOGO E CARDÁPIO
-- =========================================================

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da categoria',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária da categoria',
    name VARCHAR(120) NOT NULL COMMENT 'Nome da categoria',
    slug VARCHAR(120) NOT NULL COMMENT 'Identificador amigável da categoria',
    description TEXT NULL COMMENT 'Descrição da categoria',
    display_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição da categoria no cardápio',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação da categoria',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    deleted_at DATETIME NULL COMMENT 'Exclusão lógica',
    CONSTRAINT fk_categories_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_categories_company_slug UNIQUE (company_id, slug),
    CONSTRAINT chk_categories_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias do cardápio por empresa';

CREATE INDEX idx_categories_company_id ON categories(company_id);
CREATE INDEX idx_categories_company_status ON categories(company_id, status);
CREATE INDEX idx_categories_company_display_order ON categories(company_id, display_order);

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do produto',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do produto',
    category_id BIGINT UNSIGNED NOT NULL COMMENT 'Categoria do produto',
    name VARCHAR(150) NOT NULL COMMENT 'Nome do produto',
    slug VARCHAR(150) NOT NULL COMMENT 'Identificador amigável do produto',
    description TEXT NULL COMMENT 'Descrição do produto',
    sku VARCHAR(60) NULL COMMENT 'Código interno do produto',
    image_path VARCHAR(255) NULL COMMENT 'Caminho da imagem do produto',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço base do produto',
    promotional_price DECIMAL(10,2) NULL COMMENT 'Preço promocional do produto',
    is_featured TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Produto em destaque',
    is_active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Produto ativo para uso',
    is_paused TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Produto pausado temporariamente',
    allows_notes TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Permite observação no item',
    has_additionals TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Produto possui adicionais vinculados',
    display_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição do produto',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    deleted_at DATETIME NULL COMMENT 'Exclusão lógica',
    CONSTRAINT fk_products_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_products_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT uq_products_company_slug UNIQUE (company_id, slug),
    CONSTRAINT chk_products_price CHECK (price >= 0),
    CONSTRAINT chk_products_promotional_price CHECK (
        promotional_price IS NULL OR promotional_price >= 0
    ),
    CONSTRAINT chk_products_is_featured CHECK (is_featured IN (0,1)),
    CONSTRAINT chk_products_is_active CHECK (is_active IN (0,1)),
    CONSTRAINT chk_products_is_paused CHECK (is_paused IN (0,1)),
    CONSTRAINT chk_products_allows_notes CHECK (allows_notes IN (0,1)),
    CONSTRAINT chk_products_has_additionals CHECK (has_additionals IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Produtos do cardápio por empresa';

CREATE INDEX idx_products_company_id ON products(company_id);
CREATE INDEX idx_products_category_id ON products(category_id);
CREATE INDEX idx_products_company_status ON products(company_id, is_active, is_paused);
CREATE INDEX idx_products_company_display_order ON products(company_id, display_order);
CREATE INDEX idx_products_company_category_status ON products(company_id, category_id, is_active, is_paused);

CREATE TABLE IF NOT EXISTS additional_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do grupo de adicionais',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do grupo',
    name VARCHAR(120) NOT NULL COMMENT 'Nome do grupo de adicionais',
    description TEXT NULL COMMENT 'Descrição do grupo',
    is_required TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Indica se o grupo é obrigatório',
    min_selection SMALLINT UNSIGNED NULL COMMENT 'Quantidade mínima de escolhas',
    max_selection SMALLINT UNSIGNED NULL COMMENT 'Quantidade máxima de escolhas',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação do grupo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_additional_groups_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_additional_groups_is_required CHECK (is_required IN (0,1)),
    CONSTRAINT chk_additional_groups_status CHECK (status IN ('ativo', 'inativo')),
    CONSTRAINT chk_additional_groups_min_selection CHECK (min_selection IS NULL OR min_selection >= 0),
    CONSTRAINT chk_additional_groups_max_selection CHECK (max_selection IS NULL OR max_selection >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Grupos de adicionais configuráveis por empresa';

CREATE INDEX idx_additional_groups_company_id ON additional_groups(company_id);
CREATE INDEX idx_additional_groups_status ON additional_groups(status);

CREATE TABLE IF NOT EXISTS additional_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do item adicional',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do adicional',
    additional_group_id BIGINT UNSIGNED NOT NULL COMMENT 'Grupo ao qual o adicional pertence',
    name VARCHAR(120) NOT NULL COMMENT 'Nome do adicional',
    description TEXT NULL COMMENT 'Descrição do adicional',
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor do adicional',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação do adicional',
    display_order INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Ordem de exibição do adicional',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_additional_items_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_additional_items_group
        FOREIGN KEY (additional_group_id) REFERENCES additional_groups(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_additional_items_price CHECK (price >= 0),
    CONSTRAINT chk_additional_items_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens adicionais vinculados a grupos';

CREATE INDEX idx_additional_items_company_id ON additional_items(company_id);
CREATE INDEX idx_additional_items_group_id ON additional_items(additional_group_id);

CREATE TABLE IF NOT EXISTS product_additional_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do vínculo produto x grupo adicional',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa do vínculo',
    product_id BIGINT UNSIGNED NOT NULL COMMENT 'Produto vinculado',
    additional_group_id BIGINT UNSIGNED NOT NULL COMMENT 'Grupo adicional vinculado',
    CONSTRAINT fk_product_additional_groups_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_product_additional_groups_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_product_additional_groups_group
        FOREIGN KEY (additional_group_id) REFERENCES additional_groups(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_product_additional_groups UNIQUE (product_id, additional_group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vínculo entre produtos e grupos de adicionais';

CREATE INDEX idx_product_additional_groups_company_id ON product_additional_groups(company_id);

-- =========================================================
-- 8. BLOCO DE MESAS, CLIENTES E COMANDAS
-- =========================================================

CREATE TABLE IF NOT EXISTS tables (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da mesa',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária da mesa',
    name VARCHAR(100) NULL COMMENT 'Nome descritivo da mesa',
    number INT UNSIGNED NOT NULL COMMENT 'Número operacional da mesa',
    capacity SMALLINT UNSIGNED NULL COMMENT 'Capacidade de pessoas da mesa',
    qr_code_token VARCHAR(120) NOT NULL COMMENT 'Token único do QR Code da mesa',
    status VARCHAR(30) NOT NULL DEFAULT 'livre' COMMENT 'Situação atual da mesa',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_tables_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_tables_qr_code_token UNIQUE (qr_code_token),
    CONSTRAINT uq_tables_company_number UNIQUE (company_id, number),
    CONSTRAINT chk_tables_status CHECK (
        status IN ('livre', 'ocupada', 'aguardando_fechamento', 'bloqueada')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mesas do estabelecimento';

CREATE INDEX idx_tables_company_id ON tables(company_id);

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do cliente',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa à qual o cliente pertence',
    name VARCHAR(150) NOT NULL COMMENT 'Nome do cliente',
    phone VARCHAR(25) NULL COMMENT 'Telefone do cliente',
    email VARCHAR(150) NULL COMMENT 'E-mail do cliente',
    document_number VARCHAR(20) NULL COMMENT 'Documento do cliente',
    birth_date DATE NULL COMMENT 'Data de nascimento do cliente',
    notes TEXT NULL COMMENT 'Observações internas sobre o cliente',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação do cliente',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_customers_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_customers_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clientes cadastrados por empresa';

CREATE INDEX idx_customers_company_id ON customers(company_id);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_company_phone ON customers(company_id, phone);

CREATE TABLE IF NOT EXISTS commands (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da comanda',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária da comanda',
    table_id BIGINT UNSIGNED NULL COMMENT 'Mesa vinculada à comanda',
    customer_id BIGINT UNSIGNED NULL COMMENT 'Cliente vinculado à comanda',
    opened_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuário que abriu a comanda',
    customer_name VARCHAR(150) NULL COMMENT 'Nome livre do cliente no momento da abertura',
    status VARCHAR(20) NOT NULL DEFAULT 'aberta' COMMENT 'Situação da comanda',
    opened_at DATETIME NOT NULL COMMENT 'Data/hora de abertura',
    closed_at DATETIME NULL COMMENT 'Data/hora de fechamento',
    notes TEXT NULL COMMENT 'Observações gerais da comanda',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_commands_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_commands_table
        FOREIGN KEY (table_id) REFERENCES tables(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_commands_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_commands_opened_by_user
        FOREIGN KEY (opened_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_commands_status CHECK (status IN ('aberta', 'fechada', 'cancelada'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Comandas abertas por mesa e/ou cliente';

CREATE INDEX idx_commands_company_id ON commands(company_id);
CREATE INDEX idx_commands_table_id ON commands(table_id);
CREATE INDEX idx_commands_customer_id ON commands(customer_id);
CREATE INDEX idx_commands_company_status ON commands(company_id, status);
CREATE INDEX idx_commands_table_status ON commands(table_id, status);

-- =========================================================
-- 9. BLOCO DE PEDIDOS E ITENS
-- =========================================================

CREATE TABLE IF NOT EXISTS orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do pedido',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do pedido',
    command_id BIGINT UNSIGNED NULL COMMENT 'Comanda relacionada',
    table_id BIGINT UNSIGNED NULL COMMENT 'Mesa relacionada',
    customer_id BIGINT UNSIGNED NULL COMMENT 'Cliente relacionado',
    order_number VARCHAR(40) NOT NULL COMMENT 'Número operacional do pedido',
    channel VARCHAR(20) NOT NULL COMMENT 'Canal de entrada do pedido',
    status VARCHAR(30) NOT NULL DEFAULT 'pending' COMMENT 'Status operacional do pedido',
    payment_status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'Status financeiro do pedido',
    customer_name VARCHAR(150) NULL COMMENT 'Nome do cliente no momento do pedido',
    subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal dos itens do pedido',
    discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor de desconto aplicado',
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de entrega aplicada',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor final do pedido',
    notes TEXT NULL COMMENT 'Observações gerais do pedido',
    placed_by VARCHAR(20) NOT NULL COMMENT 'Origem do lançamento do pedido',
    placed_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuário responsável pelo lançamento, quando houver',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora da criação do pedido',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da última atualização',
    canceled_at DATETIME NULL COMMENT 'Data/hora do cancelamento',
    CONSTRAINT fk_orders_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_orders_command
        FOREIGN KEY (command_id) REFERENCES commands(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_orders_table
        FOREIGN KEY (table_id) REFERENCES tables(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_orders_placed_by_user
        FOREIGN KEY (placed_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_orders_channel CHECK (channel IN ('table', 'delivery', 'pickup', 'counter')),
    CONSTRAINT chk_orders_status CHECK (
        status IN ('pending', 'received', 'preparing', 'ready', 'delivered', 'paid', 'finished', 'canceled')
    ),
    CONSTRAINT chk_orders_payment_status CHECK (
        payment_status IN ('pending', 'partial', 'paid', 'canceled')
    ),
    CONSTRAINT chk_orders_subtotal_amount CHECK (subtotal_amount >= 0),
    CONSTRAINT chk_orders_discount_amount CHECK (discount_amount >= 0),
    CONSTRAINT chk_orders_delivery_fee CHECK (delivery_fee >= 0),
    CONSTRAINT chk_orders_total_amount CHECK (total_amount >= 0),
    CONSTRAINT chk_orders_placed_by CHECK (placed_by IN ('customer', 'waiter', 'cashier')),
    CONSTRAINT uq_orders_company_order_number UNIQUE (company_id, order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pedidos operacionais do estabelecimento';

CREATE INDEX idx_orders_company_id ON orders(company_id);
CREATE INDEX idx_orders_command_id ON orders(command_id);
CREATE INDEX idx_orders_table_id ON orders(table_id);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_company_status ON orders(company_id, status);
CREATE INDEX idx_orders_company_payment_status ON orders(company_id, payment_status);
CREATE INDEX idx_orders_company_created_at ON orders(company_id, created_at);
CREATE INDEX idx_orders_company_status_created_at ON orders(company_id, status, created_at);

CREATE TABLE IF NOT EXISTS order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do item do pedido',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do item',
    order_id BIGINT UNSIGNED NOT NULL COMMENT 'Pedido pai do item',
    product_id BIGINT UNSIGNED NOT NULL COMMENT 'Produto original do item',
    product_name_snapshot VARCHAR(150) NOT NULL COMMENT 'Nome do produto no momento da venda',
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço unitário no momento da venda',
    quantity INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Quantidade do item',
    notes TEXT NULL COMMENT 'Observação específica do item',
    line_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal da linha',
    status VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'Situação do item',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_order_items_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_order_items_unit_price CHECK (unit_price >= 0),
    CONSTRAINT chk_order_items_quantity CHECK (quantity >= 1),
    CONSTRAINT chk_order_items_line_subtotal CHECK (line_subtotal >= 0),
    CONSTRAINT chk_order_items_status CHECK (status IN ('active', 'canceled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens dos pedidos';

CREATE INDEX idx_order_items_company_id ON order_items(company_id);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);

CREATE TABLE IF NOT EXISTS order_item_additionals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do adicional do item',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do adicional',
    order_item_id BIGINT UNSIGNED NOT NULL COMMENT 'Item de pedido ao qual o adicional pertence',
    additional_item_id BIGINT UNSIGNED NOT NULL COMMENT 'Adicional original selecionado',
    additional_name_snapshot VARCHAR(150) NOT NULL COMMENT 'Nome do adicional no momento da venda',
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Preço do adicional no momento da venda',
    quantity INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Quantidade do adicional',
    line_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Subtotal do adicional',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    CONSTRAINT fk_order_item_additionals_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_item_additionals_order_item
        FOREIGN KEY (order_item_id) REFERENCES order_items(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_item_additionals_additional_item
        FOREIGN KEY (additional_item_id) REFERENCES additional_items(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_order_item_additionals_unit_price CHECK (unit_price >= 0),
    CONSTRAINT chk_order_item_additionals_quantity CHECK (quantity >= 1),
    CONSTRAINT chk_order_item_additionals_line_subtotal CHECK (line_subtotal >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Adicionais escolhidos em cada item do pedido';

CREATE INDEX idx_order_item_additionals_company_id ON order_item_additionals(company_id);
CREATE INDEX idx_order_item_additionals_order_item_id ON order_item_additionals(order_item_id);
CREATE INDEX idx_order_item_additionals_additional_item_id ON order_item_additionals(additional_item_id);

-- =========================================================
-- 10. BLOCO DE PRODUÇÃO E HISTÓRICO OPERACIONAL
-- =========================================================

CREATE TABLE IF NOT EXISTS order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do histórico de status',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do histórico',
    order_id BIGINT UNSIGNED NOT NULL COMMENT 'Pedido relacionado',
    old_status VARCHAR(30) NULL COMMENT 'Status anterior do pedido',
    new_status VARCHAR(30) NOT NULL COMMENT 'Novo status do pedido',
    changed_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuário que alterou o status',
    changed_at DATETIME NOT NULL COMMENT 'Data/hora da alteração',
    notes TEXT NULL COMMENT 'Observações sobre a mudança de status',
    CONSTRAINT fk_order_status_history_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_status_history_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_order_status_history_user
        FOREIGN KEY (changed_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_order_status_history_new_status CHECK (
        new_status IN ('pending', 'received', 'preparing', 'ready', 'delivered', 'paid', 'finished', 'canceled')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de mudanças de status dos pedidos';

CREATE INDEX idx_order_status_history_company_id ON order_status_history(company_id);
CREATE INDEX idx_order_status_history_order_id ON order_status_history(order_id);
CREATE INDEX idx_order_status_history_changed_at ON order_status_history(changed_at);

CREATE TABLE IF NOT EXISTS kitchen_print_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do log de impressão',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do log',
    order_id BIGINT UNSIGNED NOT NULL COMMENT 'Pedido relacionado',
    print_type VARCHAR(30) NOT NULL COMMENT 'Tipo de impressão executada',
    printed_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuário que executou a impressão',
    printed_at DATETIME NOT NULL COMMENT 'Data/hora da impressão',
    status VARCHAR(20) NOT NULL DEFAULT 'success' COMMENT 'Resultado da impressão',
    notes TEXT NULL COMMENT 'Observações técnicas da impressão',
    CONSTRAINT fk_kitchen_print_logs_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_kitchen_print_logs_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_kitchen_print_logs_user
        FOREIGN KEY (printed_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_kitchen_print_logs_print_type CHECK (
        print_type IN ('kitchen_ticket', 'cashier_receipt', 'command_summary', 'cash_closure')
    ),
    CONSTRAINT chk_kitchen_print_logs_status CHECK (status IN ('success', 'failed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de impressão operacional';

CREATE INDEX idx_kitchen_print_logs_company_id ON kitchen_print_logs(company_id);
CREATE INDEX idx_kitchen_print_logs_order_id ON kitchen_print_logs(order_id);

-- =========================================================
-- 11. BLOCO DE PAGAMENTOS E CAIXA
-- =========================================================

CREATE TABLE IF NOT EXISTS payment_methods (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do método de pagamento',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do método',
    name VARCHAR(100) NOT NULL COMMENT 'Nome exibido do método de pagamento',
    code VARCHAR(40) NOT NULL COMMENT 'Código interno do método',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação do método',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_payment_methods_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_payment_methods_company_code UNIQUE (company_id, code),
    CONSTRAINT chk_payment_methods_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Métodos de pagamento por empresa';

CREATE INDEX idx_payment_methods_company_id ON payment_methods(company_id);

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do pagamento',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do pagamento',
    order_id BIGINT UNSIGNED NULL COMMENT 'Pedido vinculado ao pagamento',
    command_id BIGINT UNSIGNED NULL COMMENT 'Comanda vinculada ao pagamento',
    payment_method_id BIGINT UNSIGNED NOT NULL COMMENT 'Método de pagamento utilizado',
    amount DECIMAL(10,2) NOT NULL COMMENT 'Valor do pagamento',
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'Situação do pagamento',
    transaction_reference VARCHAR(120) NULL COMMENT 'Referência de transação externa',
    paid_at DATETIME NULL COMMENT 'Data/hora do pagamento confirmado',
    received_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuário que recebeu o pagamento',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_payments_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_payments_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_payments_command
        FOREIGN KEY (command_id) REFERENCES commands(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_payments_method
        FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_payments_received_by_user
        FOREIGN KEY (received_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_payments_amount CHECK (amount > 0),
    CONSTRAINT chk_payments_status CHECK (
        status IN ('pending', 'paid', 'failed', 'refunded', 'canceled')
    ),
    CONSTRAINT chk_payments_order_or_command CHECK (
        order_id IS NOT NULL OR command_id IS NOT NULL
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pagamentos de pedidos ou comandas';

CREATE INDEX idx_payments_company_id ON payments(company_id);
CREATE INDEX idx_payments_order_id ON payments(order_id);
CREATE INDEX idx_payments_command_id ON payments(command_id);
CREATE INDEX idx_payments_payment_method_id ON payments(payment_method_id);
CREATE INDEX idx_payments_company_status ON payments(company_id, status);
CREATE INDEX idx_payments_company_paid_at ON payments(company_id, paid_at);

CREATE TABLE IF NOT EXISTS cash_registers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do caixa',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do caixa',
    opened_by_user_id BIGINT UNSIGNED NOT NULL COMMENT 'Usuário que abriu o caixa',
    closed_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuário que fechou o caixa',
    opened_at DATETIME NOT NULL COMMENT 'Data/hora de abertura do caixa',
    closed_at DATETIME NULL COMMENT 'Data/hora de fechamento do caixa',
    opening_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor inicial em caixa',
    closing_amount_reported DECIMAL(10,2) NULL COMMENT 'Valor informado no fechamento',
    closing_amount_calculated DECIMAL(10,2) NULL COMMENT 'Valor calculado pelo sistema',
    status VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'Situação do caixa',
    notes TEXT NULL COMMENT 'Observações do caixa',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_cash_registers_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_cash_registers_opened_by_user
        FOREIGN KEY (opened_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_cash_registers_closed_by_user
        FOREIGN KEY (closed_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_cash_registers_opening_amount CHECK (opening_amount >= 0),
    CONSTRAINT chk_cash_registers_closing_amount_reported CHECK (
        closing_amount_reported IS NULL OR closing_amount_reported >= 0
    ),
    CONSTRAINT chk_cash_registers_closing_amount_calculated CHECK (
        closing_amount_calculated IS NULL OR closing_amount_calculated >= 0
    ),
    CONSTRAINT chk_cash_registers_status CHECK (status IN ('open', 'closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Controle de abertura e fechamento de caixa';

CREATE INDEX idx_cash_registers_company_id ON cash_registers(company_id);
CREATE INDEX idx_cash_registers_status ON cash_registers(status);
CREATE INDEX idx_cash_registers_company_opened_at ON cash_registers(company_id, opened_at);

CREATE TABLE IF NOT EXISTS cash_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do movimento de caixa',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do movimento',
    cash_register_id BIGINT UNSIGNED NOT NULL COMMENT 'Caixa relacionado',
    payment_id BIGINT UNSIGNED NULL COMMENT 'Pagamento relacionado ao movimento',
    type VARCHAR(20) NOT NULL COMMENT 'Tipo do movimento financeiro',
    description VARCHAR(255) NOT NULL COMMENT 'Descrição resumida do movimento',
    amount DECIMAL(10,2) NOT NULL COMMENT 'Valor do movimento',
    movement_at DATETIME NOT NULL COMMENT 'Data/hora do movimento',
    created_by_user_id BIGINT UNSIGNED NOT NULL COMMENT 'Usuário que registrou o movimento',
    CONSTRAINT fk_cash_movements_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_cash_movements_cash_register
        FOREIGN KEY (cash_register_id) REFERENCES cash_registers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_cash_movements_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_cash_movements_created_by_user
        FOREIGN KEY (created_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_cash_movements_type CHECK (type IN ('income', 'expense', 'adjustment'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Movimentações registradas no caixa';

CREATE INDEX idx_cash_movements_company_id ON cash_movements(company_id);
CREATE INDEX idx_cash_movements_cash_register_id ON cash_movements(cash_register_id);
CREATE INDEX idx_cash_movements_payment_id ON cash_movements(payment_id);
CREATE INDEX idx_cash_movements_movement_at ON cash_movements(movement_at);

-- =========================================================
-- 12. BLOCO DE ESTOQUE
-- =========================================================

CREATE TABLE IF NOT EXISTS stock_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do item de estoque',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do item de estoque',
    product_id BIGINT UNSIGNED NULL COMMENT 'Produto vinculado ao item de estoque',
    name VARCHAR(150) NOT NULL COMMENT 'Nome do item de estoque',
    sku VARCHAR(60) NULL COMMENT 'Código interno do item de estoque',
    current_quantity DECIMAL(10,3) NOT NULL DEFAULT 0.000 COMMENT 'Quantidade atual do item',
    minimum_quantity DECIMAL(10,3) NULL COMMENT 'Quantidade mínima ideal',
    unit_of_measure VARCHAR(20) NOT NULL COMMENT 'Unidade de medida',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação do item',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_stock_items_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_stock_items_current_quantity CHECK (current_quantity >= 0),
    CONSTRAINT chk_stock_items_minimum_quantity CHECK (minimum_quantity IS NULL OR minimum_quantity >= 0),
    CONSTRAINT chk_stock_items_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens controlados em estoque';

CREATE INDEX idx_stock_items_company_id ON stock_items(company_id);
CREATE INDEX idx_stock_items_product_id ON stock_items(product_id);
CREATE INDEX idx_stock_items_company_status ON stock_items(company_id, status);

CREATE TABLE IF NOT EXISTS stock_item_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Relacionamento entre item de estoque e produto',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietaria do vinculo',
    stock_item_id BIGINT UNSIGNED NOT NULL COMMENT 'Item de estoque vinculado',
    product_id BIGINT UNSIGNED NOT NULL COMMENT 'Produto que utiliza o item',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criacao do vinculo',
    CONSTRAINT fk_stock_item_products_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_item_products_stock_item
        FOREIGN KEY (stock_item_id) REFERENCES stock_items(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_item_products_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_stock_item_products UNIQUE (stock_item_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Produtos relacionados a cada item de estoque';

CREATE INDEX idx_stock_item_products_company_id ON stock_item_products(company_id);
CREATE INDEX idx_stock_item_products_stock_item_id ON stock_item_products(stock_item_id);
CREATE INDEX idx_stock_item_products_product_id ON stock_item_products(product_id);

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do movimento de estoque',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do movimento',
    stock_item_id BIGINT UNSIGNED NOT NULL COMMENT 'Item de estoque movimentado',
    type VARCHAR(20) NOT NULL COMMENT 'Tipo do movimento de estoque',
    quantity DECIMAL(10,3) NOT NULL COMMENT 'Quantidade movimentada',
    reason VARCHAR(255) NULL COMMENT 'Motivo do movimento',
    reference_type VARCHAR(40) NULL COMMENT 'Tipo de referência da origem do movimento',
    reference_id BIGINT UNSIGNED NULL COMMENT 'ID da referência de origem',
    moved_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuário responsável pelo movimento',
    moved_at DATETIME NOT NULL COMMENT 'Data/hora do movimento',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    CONSTRAINT fk_stock_movements_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_stock_item
        FOREIGN KEY (stock_item_id) REFERENCES stock_items(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_moved_by_user
        FOREIGN KEY (moved_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_stock_movements_type CHECK (type IN ('entry', 'exit', 'adjustment')),
    CONSTRAINT chk_stock_movements_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Movimentações de entrada, saída e ajuste de estoque';

CREATE INDEX idx_stock_movements_company_id ON stock_movements(company_id);
CREATE INDEX idx_stock_movements_stock_item_id ON stock_movements(stock_item_id);
CREATE INDEX idx_stock_movements_reference_type ON stock_movements(reference_type);
CREATE INDEX idx_stock_movements_reference_id ON stock_movements(reference_id);

-- =========================================================
-- 13. BLOCO DE ENTREGA
-- =========================================================

CREATE TABLE IF NOT EXISTS delivery_zones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da zona de entrega',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária da zona de entrega',
    name VARCHAR(120) NOT NULL COMMENT 'Nome da zona de entrega',
    description TEXT NULL COMMENT 'Descrição da zona',
    fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de entrega da zona',
    minimum_order_amount DECIMAL(10,2) NULL COMMENT 'Pedido mínimo da zona',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação da zona de entrega',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_delivery_zones_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_delivery_zones_fee_amount CHECK (fee_amount >= 0),
    CONSTRAINT chk_delivery_zones_minimum_order_amount CHECK (
        minimum_order_amount IS NULL OR minimum_order_amount >= 0
    ),
    CONSTRAINT chk_delivery_zones_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Zonas e taxas de entrega por empresa';

CREATE INDEX idx_delivery_zones_company_id ON delivery_zones(company_id);
CREATE INDEX idx_delivery_zones_status ON delivery_zones(status);

CREATE TABLE IF NOT EXISTS delivery_addresses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do endereço de entrega',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do endereço',
    customer_id BIGINT UNSIGNED NOT NULL COMMENT 'Cliente proprietário do endereço',
    label VARCHAR(60) NULL COMMENT 'Rótulo do endereço, ex.: casa, trabalho',
    street VARCHAR(150) NOT NULL COMMENT 'Logradouro',
    number VARCHAR(20) NOT NULL COMMENT 'Número',
    complement VARCHAR(120) NULL COMMENT 'Complemento',
    neighborhood VARCHAR(120) NOT NULL COMMENT 'Bairro',
    city VARCHAR(120) NOT NULL COMMENT 'Cidade',
    state CHAR(2) NOT NULL COMMENT 'UF',
    zip_code VARCHAR(15) NULL COMMENT 'CEP',
    reference VARCHAR(255) NULL COMMENT 'Ponto de referência',
    delivery_zone_id BIGINT UNSIGNED NULL COMMENT 'Zona de entrega vinculada',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_delivery_addresses_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_delivery_addresses_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_delivery_addresses_zone
        FOREIGN KEY (delivery_zone_id) REFERENCES delivery_zones(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Endereços de entrega dos clientes';

CREATE INDEX idx_delivery_addresses_company_id ON delivery_addresses(company_id);
CREATE INDEX idx_delivery_addresses_customer_id ON delivery_addresses(customer_id);
CREATE INDEX idx_delivery_addresses_zone_id ON delivery_addresses(delivery_zone_id);

CREATE TABLE IF NOT EXISTS deliveries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da entrega',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária da entrega',
    order_id BIGINT UNSIGNED NOT NULL COMMENT 'Pedido vinculado à entrega',
    delivery_address_id BIGINT UNSIGNED NOT NULL COMMENT 'Endereço da entrega',
    delivery_user_id BIGINT UNSIGNED NULL COMMENT 'Entregador responsável',
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'Situação da entrega',
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Taxa de entrega aplicada',
    assigned_at DATETIME NULL COMMENT 'Data/hora de atribuição ao entregador',
    left_at DATETIME NULL COMMENT 'Data/hora de saída para entrega',
    delivered_at DATETIME NULL COMMENT 'Data/hora de conclusão da entrega',
    notes TEXT NULL COMMENT 'Observações da entrega',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_deliveries_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_deliveries_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_deliveries_address
        FOREIGN KEY (delivery_address_id) REFERENCES delivery_addresses(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_deliveries_delivery_user
        FOREIGN KEY (delivery_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_deliveries_status CHECK (
        status IN ('pending', 'assigned', 'in_route', 'delivered', 'failed', 'canceled')
    ),
    CONSTRAINT chk_deliveries_delivery_fee CHECK (delivery_fee >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Entregas vinculadas aos pedidos';

CREATE INDEX idx_deliveries_company_id ON deliveries(company_id);
CREATE INDEX idx_deliveries_order_id ON deliveries(order_id);
CREATE INDEX idx_deliveries_delivery_user_id ON deliveries(delivery_user_id);
CREATE INDEX idx_deliveries_company_status ON deliveries(company_id, status);

-- =========================================================
-- 14. BLOCO DE PROMOÇÕES E CUPONS
-- =========================================================

CREATE TABLE IF NOT EXISTS promotions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da promoção',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária da promoção',
    name VARCHAR(150) NOT NULL COMMENT 'Nome da promoção',
    description TEXT NULL COMMENT 'Descrição da promoção',
    discount_type VARCHAR(20) NOT NULL COMMENT 'Tipo de desconto',
    discount_value DECIMAL(10,2) NOT NULL COMMENT 'Valor do desconto',
    starts_at DATETIME NOT NULL COMMENT 'Data/hora de início da promoção',
    ends_at DATETIME NULL COMMENT 'Data/hora de fim da promoção',
    minimum_order_amount DECIMAL(10,2) NULL COMMENT 'Pedido mínimo para aplicação',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação da promoção',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_promotions_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT chk_promotions_discount_type CHECK (discount_type IN ('fixed', 'percent')),
    CONSTRAINT chk_promotions_discount_value CHECK (discount_value >= 0),
    CONSTRAINT chk_promotions_minimum_order_amount CHECK (
        minimum_order_amount IS NULL OR minimum_order_amount >= 0
    ),
    CONSTRAINT chk_promotions_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Promoções configuradas por empresa';

CREATE INDEX idx_promotions_company_id ON promotions(company_id);
CREATE INDEX idx_promotions_status ON promotions(status);
CREATE INDEX idx_promotions_company_period ON promotions(company_id, starts_at, ends_at);

CREATE TABLE IF NOT EXISTS promotion_products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do vínculo promoção x produto',
    promotion_id BIGINT UNSIGNED NOT NULL COMMENT 'Promoção vinculada',
    product_id BIGINT UNSIGNED NOT NULL COMMENT 'Produto vinculado',
    CONSTRAINT fk_promotion_products_promotion
        FOREIGN KEY (promotion_id) REFERENCES promotions(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_promotion_products_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_promotion_products UNIQUE (promotion_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Produtos participantes de promoções';

CREATE TABLE IF NOT EXISTS coupons (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do cupom',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do cupom',
    code VARCHAR(60) NOT NULL COMMENT 'Código do cupom',
    description TEXT NULL COMMENT 'Descrição do cupom',
    discount_type VARCHAR(20) NOT NULL COMMENT 'Tipo de desconto',
    discount_value DECIMAL(10,2) NOT NULL COMMENT 'Valor do desconto',
    minimum_order_amount DECIMAL(10,2) NULL COMMENT 'Pedido mínimo exigido',
    usage_limit INT UNSIGNED NULL COMMENT 'Limite total de usos do cupom',
    used_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Quantidade de usos já realizados',
    starts_at DATETIME NOT NULL COMMENT 'Data/hora de início da vigência',
    ends_at DATETIME NULL COMMENT 'Data/hora de fim da vigência',
    status VARCHAR(20) NOT NULL DEFAULT 'ativo' COMMENT 'Situação do cupom',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',
    CONSTRAINT fk_coupons_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT uq_coupons_company_code UNIQUE (company_id, code),
    CONSTRAINT chk_coupons_discount_type CHECK (discount_type IN ('fixed', 'percent')),
    CONSTRAINT chk_coupons_discount_value CHECK (discount_value >= 0),
    CONSTRAINT chk_coupons_minimum_order_amount CHECK (
        minimum_order_amount IS NULL OR minimum_order_amount >= 0
    ),
    CONSTRAINT chk_coupons_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cupons promocionais por empresa';

CREATE INDEX idx_coupons_company_id ON coupons(company_id);
CREATE INDEX idx_coupons_status ON coupons(status);

CREATE TABLE IF NOT EXISTS coupon_usages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do uso do cupom',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa proprietária do uso do cupom',
    coupon_id BIGINT UNSIGNED NOT NULL COMMENT 'Cupom utilizado',
    customer_id BIGINT UNSIGNED NULL COMMENT 'Cliente que utilizou o cupom',
    order_id BIGINT UNSIGNED NOT NULL COMMENT 'Pedido em que o cupom foi usado',
    used_at DATETIME NOT NULL COMMENT 'Data/hora do uso do cupom',
    CONSTRAINT fk_coupon_usages_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_coupon_usages_coupon
        FOREIGN KEY (coupon_id) REFERENCES coupons(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_coupon_usages_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_coupon_usages_order
        FOREIGN KEY (order_id) REFERENCES orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de utilização de cupons';

CREATE INDEX idx_coupon_usages_company_id ON coupon_usages(company_id);
CREATE INDEX idx_coupon_usages_coupon_id ON coupon_usages(coupon_id);
CREATE INDEX idx_coupon_usages_order_id ON coupon_usages(order_id);
CREATE INDEX idx_coupon_usages_customer_id ON coupon_usages(customer_id);

-- =========================================================
-- 15. BLOCO DE AUDITORIA E SUPORTE
-- =========================================================

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do log de auditoria',
    company_id BIGINT UNSIGNED NULL COMMENT 'Empresa relacionada à ação',
    user_id BIGINT UNSIGNED NULL COMMENT 'Usuário responsável pela ação',
    module VARCHAR(80) NOT NULL COMMENT 'Módulo afetado',
    action VARCHAR(80) NOT NULL COMMENT 'Ação executada',
    entity_type VARCHAR(80) NOT NULL COMMENT 'Tipo da entidade afetada',
    entity_id BIGINT UNSIGNED NOT NULL COMMENT 'ID da entidade afetada',
    old_values_json JSON NULL COMMENT 'Valores anteriores em JSON',
    new_values_json JSON NULL COMMENT 'Novos valores em JSON',
    ip_address VARCHAR(45) NULL COMMENT 'Endereço IP de origem',
    user_agent VARCHAR(255) NULL COMMENT 'User agent da requisição',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora do evento',
    CONSTRAINT fk_audit_logs_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_audit_logs_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Logs de auditoria e rastreabilidade';

CREATE INDEX idx_audit_logs_company_id ON audit_logs(company_id);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_module ON audit_logs(module);
CREATE INDEX idx_audit_logs_entity_type ON audit_logs(entity_type);
CREATE INDEX idx_audit_logs_entity_id ON audit_logs(entity_id);

CREATE TABLE IF NOT EXISTS support_tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do chamado de suporte',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa solicitante do chamado',
    opened_by_user_id BIGINT UNSIGNED NOT NULL COMMENT 'Usuário que abriu o chamado',
    assigned_to_user_id BIGINT UNSIGNED NULL COMMENT 'Usuário institucional responsável pelo atendimento',
    subject VARCHAR(180) NOT NULL COMMENT 'Assunto do chamado',
    description TEXT NOT NULL COMMENT 'Descrição detalhada do problema',
    status VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT 'Situação do chamado',
    priority VARCHAR(20) NOT NULL DEFAULT 'medium' COMMENT 'Prioridade do chamado',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de abertura',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da última atualização',
    closed_at DATETIME NULL COMMENT 'Data/hora de encerramento',
    CONSTRAINT fk_support_tickets_company
        FOREIGN KEY (company_id) REFERENCES companies(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_support_tickets_opened_by_user
        FOREIGN KEY (opened_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_support_tickets_assigned_to_user
        FOREIGN KEY (assigned_to_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_support_tickets_status CHECK (
        status IN ('open', 'in_progress', 'resolved', 'closed')
    ),
    CONSTRAINT chk_support_tickets_priority CHECK (
        priority IN ('low', 'medium', 'high', 'urgent')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Chamados de suporte da plataforma';

CREATE INDEX idx_support_tickets_company_id ON support_tickets(company_id);
CREATE INDEX idx_support_tickets_status ON support_tickets(status);
CREATE INDEX idx_support_tickets_priority ON support_tickets(priority);

CREATE TABLE IF NOT EXISTS support_ticket_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da mensagem do chamado',
    ticket_id BIGINT UNSIGNED NOT NULL COMMENT 'Chamado vinculado a mensagem',
    sender_user_id BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que enviou a mensagem',
    sender_context VARCHAR(20) NOT NULL COMMENT 'Origem do remetente: empresa ou SaaS',
    message TEXT NOT NULL COMMENT 'Conteudo textual da mensagem',
    attachment_path VARCHAR(255) NULL COMMENT 'Caminho interno do anexo da mensagem',
    attachment_original_name VARCHAR(190) NULL COMMENT 'Nome original do arquivo anexado',
    attachment_mime_type VARCHAR(120) NULL COMMENT 'Tipo MIME validado do anexo',
    attachment_size_bytes INT UNSIGNED NULL COMMENT 'Tamanho do anexo em bytes',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criacao da mensagem',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da ultima alteracao da mensagem',
    CONSTRAINT fk_support_ticket_messages_ticket
        FOREIGN KEY (ticket_id) REFERENCES support_tickets(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_support_ticket_messages_sender
        FOREIGN KEY (sender_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT chk_support_ticket_messages_sender_context CHECK (
        sender_context IN ('company', 'saas')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mensagens em thread dos chamados de suporte';

CREATE INDEX idx_support_ticket_messages_ticket_id ON support_ticket_messages(ticket_id);
CREATE INDEX idx_support_ticket_messages_sender_user_id ON support_ticket_messages(sender_user_id);
CREATE INDEX idx_support_ticket_messages_created_at ON support_ticket_messages(created_at);

CREATE TABLE IF NOT EXISTS support_ticket_message_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do anexo da mensagem',
    message_id BIGINT UNSIGNED NOT NULL COMMENT 'Mensagem do chamado que recebeu o anexo',
    stored_path VARCHAR(255) NOT NULL COMMENT 'Caminho interno do arquivo salvo em storage',
    original_name VARCHAR(190) NOT NULL COMMENT 'Nome original enviado pelo usuario',
    mime_type VARCHAR(120) NOT NULL COMMENT 'Tipo MIME validado do arquivo',
    size_bytes INT UNSIGNED NOT NULL COMMENT 'Tamanho do arquivo em bytes',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora de criacao do anexo',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da ultima alteracao do anexo',
    CONSTRAINT fk_support_ticket_message_attachments_message
        FOREIGN KEY (message_id) REFERENCES support_ticket_messages(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Anexos multiplos vinculados a cada mensagem do suporte';

CREATE INDEX idx_support_ticket_message_attachments_message_id ON support_ticket_message_attachments(message_id);
CREATE INDEX idx_support_ticket_message_attachments_created_at ON support_ticket_message_attachments(created_at);

CREATE TABLE IF NOT EXISTS public_interactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da interacao publica enviada pelo visitante',
    visitor_name VARCHAR(120) NOT NULL COMMENT 'Nome informado no formulario publico',
    visitor_email VARCHAR(160) NOT NULL COMMENT 'E-mail informado no formulario publico',
    message TEXT NOT NULL COMMENT 'Mensagem enviada pelo visitante',
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'Status editorial da interacao',
    source_page VARCHAR(255) NULL COMMENT 'URL da pagina publica de origem',
    submitted_ip VARCHAR(45) NULL COMMENT 'IP de origem do envio',
    user_agent VARCHAR(255) NULL COMMENT 'User agent capturado no momento do envio',
    reviewed_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuario SaaS que revisou a interacao',
    reviewed_at DATETIME NULL DEFAULT NULL COMMENT 'Data/hora da ultima revisao editorial',
    published_at DATETIME NULL DEFAULT NULL COMMENT 'Data/hora de publicacao na landing',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora do envio',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da ultima atualizacao',
    CONSTRAINT fk_public_interactions_reviewed_by_user
        FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_public_interactions_status CHECK (
        status IN ('pending', 'published', 'rejected')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Interacoes publicas moderadas da pagina institucional';

CREATE INDEX idx_public_interactions_status ON public_interactions(status);
CREATE INDEX idx_public_interactions_published_at ON public_interactions(published_at);
CREATE INDEX idx_public_interactions_created_at ON public_interactions(created_at);

CREATE TABLE IF NOT EXISTS public_contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do contato comercial enviado pela pagina publica',
    contact_name VARCHAR(120) NOT NULL COMMENT 'Nome informado pelo visitante',
    contact_email VARCHAR(160) NOT NULL COMMENT 'E-mail comercial informado pelo visitante',
    company_name VARCHAR(160) NULL COMMENT 'Empresa informada no formulario publico',
    phone VARCHAR(40) NOT NULL COMMENT 'Telefone ou WhatsApp para retorno',
    plan_interest VARCHAR(120) NULL COMMENT 'Plano de interesse informado pelo visitante',
    billing_cycle_interest VARCHAR(20) NULL COMMENT 'Ciclo comercial de interesse',
    message TEXT NOT NULL COMMENT 'Mensagem comercial enviada pelo visitante',
    status VARCHAR(20) NOT NULL DEFAULT 'new' COMMENT 'Status comercial do lead',
    response_channel VARCHAR(20) NULL COMMENT 'Canal usado ou planejado para retorno',
    response_notes TEXT NULL COMMENT 'Observacoes da equipe comercial',
    source_page VARCHAR(255) NULL COMMENT 'URL da pagina publica de origem',
    utm_source VARCHAR(160) NULL COMMENT 'UTM source de origem',
    utm_medium VARCHAR(160) NULL COMMENT 'UTM medium de origem',
    utm_campaign VARCHAR(160) NULL COMMENT 'UTM campaign de origem',
    utm_term VARCHAR(160) NULL COMMENT 'UTM term de origem',
    utm_content VARCHAR(160) NULL COMMENT 'UTM content de origem',
    submitted_ip VARCHAR(45) NULL COMMENT 'IP de origem do envio',
    user_agent VARCHAR(255) NULL COMMENT 'User agent capturado no envio',
    responded_by_user_id BIGINT UNSIGNED NULL COMMENT 'Usuario SaaS que conduziu o retorno comercial',
    responded_at DATETIME NULL DEFAULT NULL COMMENT 'Data/hora do ultimo retorno comercial registrado',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data/hora do envio',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data/hora da ultima atualizacao',
    CONSTRAINT fk_public_contact_messages_responded_by_user
        FOREIGN KEY (responded_by_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT chk_public_contact_messages_status CHECK (
        status IN ('new', 'contacted', 'qualified', 'converted', 'archived')
    ),
    CONSTRAINT chk_public_contact_messages_channel CHECK (
        response_channel IS NULL OR response_channel IN ('email', 'phone', 'whatsapp')
    ),
    CONSTRAINT chk_public_contact_messages_cycle CHECK (
        billing_cycle_interest IS NULL OR billing_cycle_interest IN ('mensal', 'anual')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contatos comerciais enviados pela landing publica';

CREATE INDEX idx_public_contact_messages_status ON public_contact_messages(status);
CREATE INDEX idx_public_contact_messages_channel ON public_contact_messages(response_channel);
CREATE INDEX idx_public_contact_messages_created_at ON public_contact_messages(created_at);

SET foreign_key_checks = 1;
