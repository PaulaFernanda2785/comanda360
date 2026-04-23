CREATE TABLE IF NOT EXISTS public_contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    contact_name VARCHAR(120) NOT NULL,
    contact_email VARCHAR(160) NOT NULL,
    company_name VARCHAR(160) NULL,
    phone VARCHAR(40) NOT NULL,
    plan_interest VARCHAR(120) NULL,
    billing_cycle_interest VARCHAR(20) NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    response_channel VARCHAR(20) NULL,
    response_notes TEXT NULL,
    source_page VARCHAR(255) NULL,
    utm_source VARCHAR(160) NULL,
    utm_medium VARCHAR(160) NULL,
    utm_campaign VARCHAR(160) NULL,
    utm_term VARCHAR(160) NULL,
    utm_content VARCHAR(160) NULL,
    submitted_ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    responded_by_user_id BIGINT UNSIGNED NULL,
    responded_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_public_contact_messages_status ON public_contact_messages(status);
CREATE INDEX idx_public_contact_messages_channel ON public_contact_messages(response_channel);
CREATE INDEX idx_public_contact_messages_created_at ON public_contact_messages(created_at);
