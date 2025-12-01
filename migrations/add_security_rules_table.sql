-- Миграция: Добавление таблицы security_rules
-- Дата: 06.11.2025
-- Описание: Таблица для хранения правил безопасности

-- Создание таблицы security_rules
CREATE TABLE IF NOT EXISTS security_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    domain_id INTEGER NOT NULL,
    rule_type TEXT NOT NULL, -- 'bad_bot', 'ip_block', 'geo_block', 'referrer_only', 'worker'
    rule_data TEXT, -- JSON с данными правила
    status TEXT DEFAULT 'active', -- 'active', 'paused', 'deleted'
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts(id)
);

-- Индексы для оптимизации
CREATE INDEX IF NOT EXISTS idx_security_rules_user ON security_rules(user_id);
CREATE INDEX IF NOT EXISTS idx_security_rules_domain ON security_rules(domain_id);
CREATE INDEX IF NOT EXISTS idx_security_rules_type ON security_rules(rule_type);
CREATE INDEX IF NOT EXISTS idx_security_rules_status ON security_rules(status);

-- Таблица для логирования блокировок (опционально)
CREATE TABLE IF NOT EXISTS security_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    domain_id INTEGER NOT NULL,
    rule_id INTEGER,
    blocked_ip TEXT,
    blocked_ua TEXT,
    blocked_country TEXT,
    blocked_reason TEXT,
    request_url TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts(id),
    FOREIGN KEY (rule_id) REFERENCES security_rules(id)
);

CREATE INDEX IF NOT EXISTS idx_security_logs_domain ON security_logs(domain_id);
CREATE INDEX IF NOT EXISTS idx_security_logs_created ON security_logs(created_at);

