-- =========================================================
-- ARQUIVO: schema_menu_interativo_refinado.sql
-- SISTEMA: SaaS Menu Interativo
-- BANCO: MySQL 8.4+
-- PADRÃO: InnoDB / utf8mb4 / arquitetura SaaS multiempresa
-- FINALIDADE:
--   - Criar a estrutura base do banco
--   - Documentar as tabelas e colunas com comentários
--   - Inserir dados iniciais de demonstração
-- AMBIENTE-ALVO:
--   - Desenvolvimento local: WampServer / MySQL 8.4.x
--   - Produção: Hostinger / PHP 8.4 / phpMyAdmin
-- =========================================================

-- =========================================================
-- 1. CRIAÇÃO DO BANCO
-- =========================================================

CREATE DATABASE IF NOT EXISTS comanda360
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE comanda360;

-- =========================================================
-- 2. CONFIGURAÇÕES INICIAIS
-- =========================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;

-- =========================================================
-- 3. BLOCO SAAS INSTITUCIONAL
-- =========================================================

-- ---------------------------------------------------------
-- TABELA: plans
-- FINALIDADE:
--   Armazena os planos comerciais do SaaS.
-- ---------------------------------------------------------
CREATE TABLE plans (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Planos comerciais do SaaS';

-- ---------------------------------------------------------
-- TABELA: companies
-- FINALIDADE:
--   Armazena os estabelecimentos assinantes da plataforma.
-- ---------------------------------------------------------
CREATE TABLE companies (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Empresas assinantes do SaaS';

CREATE INDEX idx_companies_status ON companies(status);
CREATE INDEX idx_companies_plan_id ON companies(plan_id);
CREATE INDEX idx_companies_subscription_status ON companies(subscription_status);

-- ---------------------------------------------------------
-- TABELA: subscriptions
-- FINALIDADE:
--   Histórico de assinaturas por empresa.
-- ---------------------------------------------------------
CREATE TABLE subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da assinatura',
    company_id BIGINT UNSIGNED NOT NULL COMMENT 'Empresa assinante',
    plan_id BIGINT UNSIGNED NOT NULL COMMENT 'Plano vinculado à assinatura',
    status VARCHAR(20) NOT NULL DEFAULT 'trial' COMMENT 'Situação da assinatura',
    billing_cycle VARCHAR(20) NOT NULL COMMENT 'Periodicidade de cobrança',
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor contratado',
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
    CONSTRAINT chk_subscriptions_amount CHECK (amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de assinaturas por empresa';

CREATE INDEX idx_subscriptions_company_id ON subscriptions(company_id);
CREATE INDEX idx_subscriptions_plan_id ON subscriptions(plan_id);
CREATE INDEX idx_subscriptions_status ON subscriptions(status);
CREATE INDEX idx_subscriptions_company_status ON subscriptions(company_id, status);

-- ---------------------------------------------------------
-- TABELA: subscription_payments
-- FINALIDADE:
--   Cobranças e pagamentos ligados às assinaturas do SaaS.
-- ---------------------------------------------------------
CREATE TABLE subscription_payments (
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
    CONSTRAINT chk_subscription_payments_reference_month CHECK (
        reference_month BETWEEN 1 AND 12
    ),
    CONSTRAINT chk_subscription_payments_amount CHECK (amount >= 0),
    CONSTRAINT chk_subscription_payments_status CHECK (
        status IN ('pendente', 'pago', 'vencido', 'cancelado')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pagamentos e cobranças de assinaturas';

CREATE INDEX idx_subscription_payments_subscription_id ON subscription_payments(subscription_id);
CREATE INDEX idx_subscription_payments_company_id ON subscription_payments(company_id);
CREATE INDEX idx_subscription_payments_status ON subscription_payments(status);
CREATE INDEX idx_subscription_payments_due_date ON subscription_payments(due_date);

-- =========================================================
-- 4. BLOCO DE USUÁRIOS, PERFIS E PERMISSÕES
-- =========================================================

CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador do perfil',
    name VARCHAR(100) NOT NULL COMMENT 'Nome do perfil de acesso',
    slug VARCHAR(100) NOT NULL COMMENT 'Chave única do perfil',
    context VARCHAR(30) NOT NULL COMMENT 'Contexto do perfil: company, saas ou public',
    description TEXT NULL COMMENT 'Descrição do perfil',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Data da última atualização',

    CONSTRAINT uq_roles_slug UNIQUE (slug),
    CONSTRAINT chk_roles_context CHECK (context IN ('company', 'saas', 'public'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Perfis de acesso do sistema';

CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Identificador da permissão',
    module VARCHAR(80) NOT NULL COMMENT 'Módulo funcional da permissão',
    action VARCHAR(80) NOT NULL COMMENT 'Ação autorizada',
    slug VARCHAR(150) NOT NULL COMMENT 'Chave única da permissão',
    description TEXT NULL COMMENT 'Descrição da permissão',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação',

    CONSTRAINT uq_permissions_slug UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Permissões de acesso do sistema';

CREATE TABLE role_permissions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Relação entre perfis e permissões';

CREATE TABLE users (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Usuários internos da empresa e usuários globais do SaaS';

CREATE INDEX idx_users_company_id ON users(company_id);
CREATE INDEX idx_users_role_id ON users(role_id);
CREATE INDEX idx_users_company_status ON users(company_id, status);

-- =========================================================
-- 5. BLOCO DE CONFIGURAÇÕES E PERSONALIZAÇÃO
-- =========================================================

CREATE TABLE company_settings (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Configurações operacionais e comerciais por empresa';

CREATE TABLE company_themes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tema visual e identidade pública por empresa';

-- =========================================================
-- 6. BLOCO DE CATÁLOGO E CARDÁPIO
-- =========================================================

CREATE TABLE categories (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Categorias do cardápio por empresa';

CREATE INDEX idx_categories_company_id ON categories(company_id);
CREATE INDEX idx_categories_company_status ON categories(company_id, status);
CREATE INDEX idx_categories_company_display_order ON categories(company_id, display_order);

CREATE TABLE products (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Produtos do cardápio por empresa';

CREATE INDEX idx_products_company_id ON products(company_id);
CREATE INDEX idx_products_category_id ON products(category_id);
CREATE INDEX idx_products_company_status ON products(company_id, is_active, is_paused);
CREATE INDEX idx_products_company_display_order ON products(company_id, display_order);
CREATE INDEX idx_products_company_category_status ON products(company_id, category_id, is_active, is_paused);

CREATE TABLE additional_groups (
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
    CONSTRAINT chk_additional_groups_min_selection CHECK (
        min_selection IS NULL OR min_selection >= 0
    ),
    CONSTRAINT chk_additional_groups_max_selection CHECK (
        max_selection IS NULL OR max_selection >= 0
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Grupos de adicionais configuráveis por empresa';

CREATE INDEX idx_additional_groups_company_id ON additional_groups(company_id);
CREATE INDEX idx_additional_groups_status ON additional_groups(status);

CREATE TABLE additional_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Itens adicionais vinculados a grupos';

CREATE INDEX idx_additional_items_company_id ON additional_items(company_id);
CREATE INDEX idx_additional_items_group_id ON additional_items(additional_group_id);

CREATE TABLE product_additional_groups (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Vínculo entre produtos e grupos de adicionais';

CREATE INDEX idx_product_additional_groups_company_id ON product_additional_groups(company_id);

-- =========================================================
-- 7. BLOCO DE MESAS, CLIENTES E COMANDAS
-- =========================================================

CREATE TABLE tables (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Mesas do estabelecimento';

CREATE INDEX idx_tables_company_id ON tables(company_id);

CREATE TABLE customers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Clientes cadastrados por empresa';

CREATE INDEX idx_customers_company_id ON customers(company_id);
CREATE INDEX idx_customers_phone ON customers(phone);
CREATE INDEX idx_customers_company_phone ON customers(company_id, phone);

CREATE TABLE commands (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Comandas abertas por mesa e/ou cliente';

CREATE INDEX idx_commands_company_id ON commands(company_id);
CREATE INDEX idx_commands_table_id ON commands(table_id);
CREATE INDEX idx_commands_customer_id ON commands(customer_id);
CREATE INDEX idx_commands_company_status ON commands(company_id, status);
CREATE INDEX idx_commands_table_status ON commands(table_id, status);

-- =========================================================
-- 8. BLOCO DE PEDIDOS E ITENS
-- =========================================================

CREATE TABLE orders (
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
    CONSTRAINT chk_orders_channel CHECK (
        channel IN ('table', 'delivery', 'pickup', 'counter')
    ),
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
    CONSTRAINT chk_orders_placed_by CHECK (
        placed_by IN ('customer', 'waiter', 'cashier')
    ),
    CONSTRAINT uq_orders_company_order_number UNIQUE (company_id, order_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pedidos operacionais do estabelecimento';

CREATE INDEX idx_orders_company_id ON orders(company_id);
CREATE INDEX idx_orders_command_id ON orders(command_id);
CREATE INDEX idx_orders_table_id ON orders(table_id);
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_company_status ON orders(company_id, status);
CREATE INDEX idx_orders_company_payment_status ON orders(company_id, payment_status);
CREATE INDEX idx_orders_company_created_at ON orders(company_id, created_at);
CREATE INDEX idx_orders_company_status_created_at ON orders(company_id, status, created_at);

CREATE TABLE order_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Itens dos pedidos';

CREATE INDEX idx_order_items_company_id ON order_items(company_id);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_order_items_product_id ON order_items(product_id);

CREATE TABLE order_item_additionals (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Adicionais escolhidos em cada item do pedido';

CREATE INDEX idx_order_item_additionals_company_id ON order_item_additionals(company_id);
CREATE INDEX idx_order_item_additionals_order_item_id ON order_item_additionals(order_item_id);
CREATE INDEX idx_order_item_additionals_additional_item_id ON order_item_additionals(additional_item_id);

-- =========================================================
-- 9. BLOCO DE PRODUÇÃO E HISTÓRICO OPERACIONAL
-- =========================================================

CREATE TABLE order_status_history (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de mudanças de status dos pedidos';

CREATE INDEX idx_order_status_history_company_id ON order_status_history(company_id);
CREATE INDEX idx_order_status_history_order_id ON order_status_history(order_id);
CREATE INDEX idx_order_status_history_changed_at ON order_status_history(changed_at);

CREATE TABLE kitchen_print_logs (
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
    CONSTRAINT chk_kitchen_print_logs_status CHECK (
        status IN ('success', 'failed')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs de impressão operacional';

CREATE INDEX idx_kitchen_print_logs_company_id ON kitchen_print_logs(company_id);
CREATE INDEX idx_kitchen_print_logs_order_id ON kitchen_print_logs(order_id);

-- =========================================================
-- 10. BLOCO DE PAGAMENTOS E CAIXA
-- =========================================================

CREATE TABLE payment_methods (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Métodos de pagamento por empresa';

CREATE INDEX idx_payment_methods_company_id ON payment_methods(company_id);

CREATE TABLE payments (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Pagamentos de pedidos ou comandas';

CREATE INDEX idx_payments_company_id ON payments(company_id);
CREATE INDEX idx_payments_order_id ON payments(order_id);
CREATE INDEX idx_payments_command_id ON payments(command_id);
CREATE INDEX idx_payments_payment_method_id ON payments(payment_method_id);
CREATE INDEX idx_payments_company_status ON payments(company_id, status);
CREATE INDEX idx_payments_company_paid_at ON payments(company_id, paid_at);

CREATE TABLE cash_registers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Controle de abertura e fechamento de caixa';

CREATE INDEX idx_cash_registers_company_id ON cash_registers(company_id);
CREATE INDEX idx_cash_registers_status ON cash_registers(status);
CREATE INDEX idx_cash_registers_company_opened_at ON cash_registers(company_id, opened_at);

CREATE TABLE cash_movements (
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
    CONSTRAINT chk_cash_movements_type CHECK (
        type IN ('income', 'expense', 'adjustment')
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Movimentações registradas no caixa';

CREATE INDEX idx_cash_movements_company_id ON cash_movements(company_id);
CREATE INDEX idx_cash_movements_cash_register_id ON cash_movements(cash_register_id);
CREATE INDEX idx_cash_movements_payment_id ON cash_movements(payment_id);
CREATE INDEX idx_cash_movements_movement_at ON cash_movements(movement_at);

-- =========================================================
-- 11. BLOCO DE ESTOQUE
-- =========================================================

CREATE TABLE stock_items (
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
    CONSTRAINT chk_stock_items_minimum_quantity CHECK (
        minimum_quantity IS NULL OR minimum_quantity >= 0
    ),
    CONSTRAINT chk_stock_items_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Itens controlados em estoque';

CREATE INDEX idx_stock_items_company_id ON stock_items(company_id);
CREATE INDEX idx_stock_items_product_id ON stock_items(product_id);
CREATE INDEX idx_stock_items_company_status ON stock_items(company_id, status);

CREATE TABLE stock_item_products (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Produtos relacionados a cada item de estoque';

CREATE INDEX idx_stock_item_products_company_id ON stock_item_products(company_id);
CREATE INDEX idx_stock_item_products_stock_item_id ON stock_item_products(stock_item_id);
CREATE INDEX idx_stock_item_products_product_id ON stock_item_products(product_id);

CREATE TABLE stock_movements (
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
    CONSTRAINT chk_stock_movements_type CHECK (
        type IN ('entry', 'exit', 'adjustment')
    ),
    CONSTRAINT chk_stock_movements_quantity CHECK (quantity > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Movimentações de entrada, saída e ajuste de estoque';

CREATE INDEX idx_stock_movements_company_id ON stock_movements(company_id);
CREATE INDEX idx_stock_movements_stock_item_id ON stock_movements(stock_item_id);
CREATE INDEX idx_stock_movements_reference_type ON stock_movements(reference_type);
CREATE INDEX idx_stock_movements_reference_id ON stock_movements(reference_id);

-- =========================================================
-- 12. BLOCO DE ENTREGA
-- =========================================================

CREATE TABLE delivery_zones (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Zonas e taxas de entrega por empresa';

CREATE INDEX idx_delivery_zones_company_id ON delivery_zones(company_id);
CREATE INDEX idx_delivery_zones_status ON delivery_zones(status);

CREATE TABLE delivery_addresses (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Endereços de entrega dos clientes';

CREATE INDEX idx_delivery_addresses_company_id ON delivery_addresses(company_id);
CREATE INDEX idx_delivery_addresses_customer_id ON delivery_addresses(customer_id);
CREATE INDEX idx_delivery_addresses_zone_id ON delivery_addresses(delivery_zone_id);

CREATE TABLE deliveries (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Entregas vinculadas aos pedidos';

CREATE INDEX idx_deliveries_company_id ON deliveries(company_id);
CREATE INDEX idx_deliveries_order_id ON deliveries(order_id);
CREATE INDEX idx_deliveries_delivery_user_id ON deliveries(delivery_user_id);
CREATE INDEX idx_deliveries_company_status ON deliveries(company_id, status);

-- =========================================================
-- 13. BLOCO DE PROMOÇÕES E CUPONS
-- =========================================================

CREATE TABLE promotions (
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
    CONSTRAINT chk_promotions_discount_type CHECK (
        discount_type IN ('fixed', 'percent')
    ),
    CONSTRAINT chk_promotions_discount_value CHECK (discount_value >= 0),
    CONSTRAINT chk_promotions_minimum_order_amount CHECK (
        minimum_order_amount IS NULL OR minimum_order_amount >= 0
    ),
    CONSTRAINT chk_promotions_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Promoções configuradas por empresa';

CREATE INDEX idx_promotions_company_id ON promotions(company_id);
CREATE INDEX idx_promotions_status ON promotions(status);
CREATE INDEX idx_promotions_company_period ON promotions(company_id, starts_at, ends_at);

CREATE TABLE promotion_products (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Produtos participantes de promoções';

CREATE TABLE coupons (
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
    CONSTRAINT chk_coupons_discount_type CHECK (
        discount_type IN ('fixed', 'percent')
    ),
    CONSTRAINT chk_coupons_discount_value CHECK (discount_value >= 0),
    CONSTRAINT chk_coupons_minimum_order_amount CHECK (
        minimum_order_amount IS NULL OR minimum_order_amount >= 0
    ),
    CONSTRAINT chk_coupons_status CHECK (status IN ('ativo', 'inativo'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Cupons promocionais por empresa';

CREATE INDEX idx_coupons_company_id ON coupons(company_id);
CREATE INDEX idx_coupons_status ON coupons(status);

CREATE TABLE coupon_usages (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Histórico de utilização de cupons';

CREATE INDEX idx_coupon_usages_company_id ON coupon_usages(company_id);
CREATE INDEX idx_coupon_usages_coupon_id ON coupon_usages(coupon_id);
CREATE INDEX idx_coupon_usages_order_id ON coupon_usages(order_id);
CREATE INDEX idx_coupon_usages_customer_id ON coupon_usages(customer_id);

-- =========================================================
-- 14. BLOCO DE AUDITORIA E SUPORTE
-- =========================================================

CREATE TABLE audit_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Logs de auditoria e rastreabilidade';

CREATE INDEX idx_audit_logs_company_id ON audit_logs(company_id);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_module ON audit_logs(module);
CREATE INDEX idx_audit_logs_entity_type ON audit_logs(entity_type);
CREATE INDEX idx_audit_logs_entity_id ON audit_logs(entity_id);

CREATE TABLE support_tickets (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Chamados de suporte da plataforma';

CREATE INDEX idx_support_tickets_company_id ON support_tickets(company_id);
CREATE INDEX idx_support_tickets_status ON support_tickets(status);
CREATE INDEX idx_support_tickets_priority ON support_tickets(priority);

CREATE TABLE support_ticket_messages (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Mensagens em thread dos chamados de suporte';

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Anexos multiplos vinculados a cada mensagem do suporte';

CREATE INDEX idx_support_ticket_message_attachments_message_id ON support_ticket_message_attachments(message_id);
CREATE INDEX idx_support_ticket_message_attachments_created_at ON support_ticket_message_attachments(created_at);

-- =========================================================
-- 15. DADOS INICIAIS: PLANOS
-- =========================================================

INSERT INTO plans (
    id, name, slug, description, price_monthly, price_yearly, max_users, max_products, max_tables, features_json, status
) VALUES
(
    1,
    'Básico',
    'basico',
    'Plano inicial para pequenos estabelecimentos.',
    79.90,
    799.00,
    5,
    80,
    15,
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
    2,
    'Profissional',
    'profissional',
    'Plano intermediário com operação ampliada e recursos gerenciais.',
    149.90,
    1499.00,
    15,
    300,
    50,
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
    3,
    'Premium',
    'premium',
    'Plano completo para operações mais robustas.',
    249.90,
    2499.00,
    50,
    1000,
    200,
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
);

-- =========================================================
-- 16. DADOS INICIAIS: EMPRESA DEMO
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
);

INSERT INTO subscriptions (
    id, company_id, plan_id, status, billing_cycle, amount, starts_at, ends_at
) VALUES (
    1,
    1,
    2,
    'ativa',
    'mensal',
    149.90,
    '2026-01-01 00:00:00',
    '2026-12-31 23:59:59'
);

INSERT INTO subscription_payments (
    id, subscription_id, company_id, reference_month, reference_year, amount, status, payment_method, paid_at, due_date, transaction_reference
) VALUES
(1, 1, 1, 1, 2026, 149.90, 'pago', 'pix', '2026-01-05 10:30:00', '2026-01-10', 'SUB-2026-01-0001'),
(2, 1, 1, 2, 2026, 149.90, 'pago', 'pix', '2026-02-05 10:45:00', '2026-02-10', 'SUB-2026-02-0001'),
(3, 1, 1, 3, 2026, 149.90, 'pago', 'pix', '2026-03-05 11:00:00', '2026-03-10', 'SUB-2026-03-0001');

-- =========================================================
-- 17. DADOS INICIAIS: PERFIS E PERMISSÕES
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
(9, 'Financeiro SaaS', 'saas_financial', 'saas', 'Cobranças e assinaturas da plataforma');

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
(40, 'stock', 'manage', 'stock.manage', 'Gerenciar estoque');

-- Admin establishment: recebe todas as permissões
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

-- Manager
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions
WHERE slug IN (
    'dashboard.view',
    'products.view',
    'products.edit',
    'products.pause',
    'categories.view',
    'additionals.view',
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
    'reports.view',
    'stock.view',
    'stock.manage'
);

-- Cashier
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions
WHERE slug IN (
    'dashboard.view',
    'commands.view',
    'orders.view',
    'payments.view',
    'payments.create',
    'cash_registers.open',
    'cash_registers.close'
);

-- Waiter
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions
WHERE slug IN (
    'tables.view',
    'tables.manage',
    'commands.view',
    'commands.create',
    'commands.edit',
    'orders.view',
    'orders.create'
);

-- Kitchen
INSERT INTO role_permissions (role_id, permission_id)
SELECT 5, id FROM permissions
WHERE slug IN (
    'orders.view',
    'orders.status'
);

-- Delivery
INSERT INTO role_permissions (role_id, permission_id)
SELECT 6, id FROM permissions
WHERE slug IN (
    'orders.view',
    'orders.status'
);

-- SaaS Admin
INSERT INTO role_permissions (role_id, permission_id)
SELECT 7, id FROM permissions
WHERE slug IN (
    'companies.view',
    'companies.manage',
    'plans.view',
    'plans.manage',
    'subscriptions.view',
    'subscriptions.manage',
    'support.view',
    'support.manage',
    'users.view',
    'users.create',
    'users.edit',
    'dashboard.view'
);

-- SaaS Support
INSERT INTO role_permissions (role_id, permission_id)
SELECT 8, id FROM permissions
WHERE slug IN (
    'companies.view',
    'subscriptions.view',
    'support.view',
    'support.manage',
    'dashboard.view'
);

-- SaaS Financial
INSERT INTO role_permissions (role_id, permission_id)
SELECT 9, id FROM permissions
WHERE slug IN (
    'companies.view',
    'plans.view',
    'subscriptions.view',
    'subscriptions.manage',
    'dashboard.view'
);

-- =========================================================
-- 18. DADOS INICIAIS: USUÁRIOS DEMO
-- OBS:
--   As senhas abaixo usam hashes de exemplo para ambiente inicial.
--   Idealmente, em implantação real, recriar os usuários com hash gerado
--   pela própria aplicação usando password_hash().
-- SENHA PLANA SUGERIDA DE TESTE:
--   123456
-- =========================================================

INSERT INTO users (
    id, company_id, role_id, name, email, phone, password_hash, status, is_saas_user, last_login_at
) VALUES
(
    1, 1, 1, 'Administrador Demo', 'admin@saboremesa.local', '91911110001',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 0, NULL
),
(
    2, 1, 2, 'Gerente Demo', 'gerente@saboremesa.local', '91911110002',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 0, NULL
),
(
    3, 1, 3, 'Caixa Demo', 'caixa@saboremesa.local', '91911110003',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 0, NULL
),
(
    4, 1, 4, 'Garçom Demo', 'garcom@saboremesa.local', '91911110004',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 0, NULL
),
(
    5, 1, 5, 'Cozinha Demo', 'cozinha@saboremesa.local', '91911110005',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 0, NULL
),
(
    6, 1, 6, 'Motoboy Demo', 'motoboy@saboremesa.local', '91911110006',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 0, NULL
),
(
    7, NULL, 7, 'Admin SaaS Demo', 'saas.admin@menu.local', '91922220001',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 1, NULL
),
(
    8, NULL, 8, 'Suporte SaaS Demo', 'saas.suporte@menu.local', '91922220002',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 1, NULL
),
(
    9, NULL, 9, 'Financeiro SaaS Demo', 'saas.financeiro@menu.local', '91922220003',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9lrjZs5SX9xM/eGfD5sJGa',
    'ativo', 1, NULL
);

-- =========================================================
-- 19. DADOS INICIAIS: CONFIGURAÇÕES E TEMA DA EMPRESA DEMO
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
);

INSERT INTO company_themes (
    id, company_id, primary_color, secondary_color, accent_color, main_card_color, logo_path, banner_path, title, description, footer_text
) VALUES (
    1, 1, '#D62828', '#1D3557', '#F4A261', '#1D3557',
    'uploads/logos/sabor-mesa-logo.png',
    'uploads/banners/sabor-mesa-banner.png',
    'Restaurante Sabor & Mesa',
    'Experiência digital para pedidos por mesa, balcão e delivery.',
    'Restaurante Sabor & Mesa - Atendimento digital'
);

-- =========================================================
-- 20. DADOS INICIAIS: CATEGORIAS DEMO
-- =========================================================

INSERT INTO categories (
    id, company_id, name, slug, description, display_order, status
) VALUES
(1, 1, 'Entradas', 'entradas', 'Petiscos e entradas iniciais.', 1, 'ativo'),
(2, 1, 'Hambúrgueres', 'hamburgueres', 'Linha principal de hambúrgueres artesanais.', 2, 'ativo'),
(3, 1, 'Pizzas', 'pizzas', 'Pizzas salgadas e doces.', 3, 'ativo'),
(4, 1, 'Bebidas', 'bebidas', 'Sucos, refrigerantes e bebidas diversas.', 4, 'ativo'),
(5, 1, 'Sobremesas', 'sobremesas', 'Sobremesas e doces.', 5, 'ativo');

-- =========================================================
-- 21. DADOS INICIAIS: PRODUTOS DEMO
-- =========================================================

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
(7, 1, 5, 'Brownie com Sorvete', 'brownie-com-sorvete', 'Brownie artesanal servido com sorvete.', 'SOB-001', 'uploads/products/brownie-sorvete.png', 16.00, NULL, 1, 1, 0, 1, 0, 1);

-- =========================================================
-- 22. DADOS INICIAIS: GRUPOS E ITENS DE ADICIONAIS DEMO
-- =========================================================

INSERT INTO additional_groups (
    id, company_id, name, description, is_required, min_selection, max_selection, status
) VALUES
(1, 1, 'Adicionais de Hambúrguer', 'Complementos opcionais para hambúrgueres.', 0, 0, 5, 'ativo'),
(2, 1, 'Borda Recheada', 'Escolha de borda para pizzas.', 0, 0, 1, 'ativo');

INSERT INTO additional_items (
    id, company_id, additional_group_id, name, description, price, status, display_order
) VALUES
(1, 1, 1, 'Queijo Extra', 'Porção adicional de queijo.', 3.50, 'ativo', 1),
(2, 1, 1, 'Bacon Extra', 'Porção adicional de bacon.', 4.50, 'ativo', 2),
(3, 1, 1, 'Ovo', 'Adicionar ovo ao hambúrguer.', 2.50, 'ativo', 3),
(4, 1, 2, 'Borda de Catupiry', 'Borda recheada com catupiry.', 8.00, 'ativo', 1),
(5, 1, 2, 'Borda de Cheddar', 'Borda recheada com cheddar.', 8.00, 'ativo', 2);

INSERT INTO product_additional_groups (
    id, company_id, product_id, additional_group_id
) VALUES
(1, 1, 2, 1),
(2, 1, 3, 1),
(3, 1, 4, 2);

-- =========================================================
-- 23. DADOS INICIAIS: MESAS DEMO
-- =========================================================

INSERT INTO tables (
    id, company_id, name, number, capacity, qr_code_token, status
) VALUES
(1, 1, 'Mesa 01', 1, 4, 'mesa-01-token-demo', 'livre'),
(2, 1, 'Mesa 02', 2, 4, 'mesa-02-token-demo', 'livre'),
(3, 1, 'Mesa 03', 3, 6, 'mesa-03-token-demo', 'livre'),
(4, 1, 'Mesa 04', 4, 2, 'mesa-04-token-demo', 'livre'),
(5, 1, 'Varanda 01', 5, 4, 'mesa-05-token-demo', 'livre');

-- =========================================================
-- 24. DADOS INICIAIS: MÉTODOS DE PAGAMENTO DEMO
-- =========================================================

INSERT INTO payment_methods (
    id, company_id, name, code, status
) VALUES
(1, 1, 'Pix', 'pix', 'ativo'),
(2, 1, 'Dinheiro', 'cash', 'ativo'),
(3, 1, 'Cartão de Crédito', 'credit_card', 'ativo'),
(4, 1, 'Cartão de Débito', 'debit_card', 'ativo'),
(5, 1, 'Pagamento Online', 'online', 'ativo');

-- =========================================================
-- 25. DADOS INICIAIS: ZONAS DE ENTREGA DEMO
-- =========================================================

INSERT INTO delivery_zones (
    id, company_id, name, description, fee_amount, minimum_order_amount, status
) VALUES
(1, 1, 'Centro', 'Região central com entrega rápida.', 5.00, 20.00, 'ativo'),
(2, 1, 'Bairro Próximo', 'Bairros de raio curto.', 7.00, 25.00, 'ativo'),
(3, 1, 'Bairro Distante', 'Regiões mais afastadas.', 10.00, 35.00, 'ativo');

-- =========================================================
-- 26. DADOS INICIAIS: CLIENTE DEMO E ENDEREÇO DEMO
-- =========================================================

INSERT INTO customers (
    id, company_id, name, phone, email, document_number, birth_date, notes, status
) VALUES (
    1, 1, 'Cliente Demo', '91988887777', 'cliente.demo@local', NULL, NULL,
    'Cliente de teste para simulações do sistema.',
    'ativo'
);

INSERT INTO delivery_addresses (
    id, company_id, customer_id, label, street, number, complement, neighborhood, city, state, zip_code, reference, delivery_zone_id
) VALUES (
    1, 1, 1, 'Casa', 'Av. Principal', '100', 'Ap 101', 'Centro', 'Belém', 'PA', '66000-000', 'Próximo à praça central', 1
);

-- =========================================================
-- 27. DADOS INICIAIS: PROMOÇÃO E CUPOM DEMO
-- =========================================================

INSERT INTO promotions (
    id, company_id, name, description, discount_type, discount_value, starts_at, ends_at, minimum_order_amount, status
) VALUES (
    1, 1, 'Promo Hambúrguer da Semana', 'Desconto especial em hambúrgueres selecionados.',
    'percent', 10.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 20.00, 'ativo'
);

INSERT INTO promotion_products (
    id, promotion_id, product_id
) VALUES
(1, 1, 2),
(2, 1, 3);

INSERT INTO coupons (
    id, company_id, code, description, discount_type, discount_value, minimum_order_amount, usage_limit, used_count, starts_at, ends_at, status
) VALUES (
    1, 1, 'BEMVINDO10', 'Cupom de boas-vindas para novos clientes.',
    'percent', 10.00, 30.00, 100, 0, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'ativo'
);

-- =========================================================
-- 28. RESTAURA CONFIGURAÇÃO FINAL
-- =========================================================

SET foreign_key_checks = 1;
