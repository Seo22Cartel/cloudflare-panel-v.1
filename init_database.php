<?php
// Принудительная инициализация базы данных
header('Content-Type: text/plain; charset=utf-8');

echo "=== Инициализация базы данных ===\n\n";

// Определение путей
define('ROOT_PATH', dirname(__FILE__) . '/');
define('DB_PATH', ROOT_PATH . 'cloudflare_panel.db');

echo "Database path: " . DB_PATH . "\n";
echo "File exists before: " . (file_exists(DB_PATH) ? 'Yes' : 'No') . "\n";

if (file_exists(DB_PATH)) {
    echo "Current file size: " . filesize(DB_PATH) . " bytes\n";
}

try {
    // Удаляем старый файл если он пустой
    if (file_exists(DB_PATH) && filesize(DB_PATH) == 0) {
        unlink(DB_PATH);
        echo "Removed empty database file\n";
    }
    
    // Создаем новое соединение
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "Database connection: SUCCESS\n";
    
    // Создаем таблицы
    echo "\nСоздание таблиц...\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL
        );
    ");
    echo "Table 'users' created\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            name TEXT NOT NULL,
            UNIQUE(user_id, name),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
    echo "Table 'groups' created\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cloudflare_credentials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            email TEXT NOT NULL,
            api_key TEXT NOT NULL,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, email),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
    echo "Table 'cloudflare_credentials' created\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cloudflare_accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            group_id INTEGER,
            domain TEXT NOT NULL,
            server_ip TEXT NOT NULL,
            always_use_https INTEGER DEFAULT 0,
            min_tls_version TEXT DEFAULT '1.0',
            ssl_mode TEXT DEFAULT 'flexible',
            dns_ip TEXT,
            zone_id TEXT,
            domain_status TEXT DEFAULT 'unknown',
            last_check DATETIME,
            response_time REAL,
            ns_records TEXT,
            http_code INTEGER DEFAULT 0,
            https_status INTEGER DEFAULT 0,
            ssl_certificates_count INTEGER DEFAULT 0,
            ssl_status_check DATETIME,
            ssl_has_active INTEGER DEFAULT 0,
            ssl_expires_soon INTEGER DEFAULT 0,
            ssl_nearest_expiry DATETIME,
            ssl_types TEXT,
            ssl_certificate TEXT,
            ssl_private_key TEXT,
            ssl_cert_id TEXT,
            ssl_cert_created DATETIME,
            tls_1_3_enabled INTEGER DEFAULT 0,
            automatic_https_rewrites INTEGER DEFAULT 0,
            authenticated_origin_pulls INTEGER DEFAULT 0,
            ssl_last_check DATETIME,
            has_redirect INTEGER DEFAULT 0,
            redirect_url TEXT,
            redirect_code INTEGER DEFAULT 0,
            final_url TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (account_id) REFERENCES cloudflare_credentials(id),
            FOREIGN KEY (group_id) REFERENCES groups(id),
            UNIQUE(user_id, domain)
        );
    ");
    echo "Table 'cloudflare_accounts' created\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
    echo "Table 'logs' created\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS proxies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            proxy TEXT NOT NULL,
            status INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
    ");
    echo "Table 'proxies' created\n";
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS queue (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            domain_id INTEGER NOT NULL,
            type TEXT NOT NULL,
            data TEXT,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME,
            completed_at DATETIME,
            result TEXT,
            ssl_last_check DATETIME,
            FOREIGN KEY (user_id) REFERENCES users (id),
            FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts (id)
        );
    ");
    echo "Table 'queue' created\n";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS security_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            domain_id INTEGER NOT NULL,
            rule_type TEXT NOT NULL,
            rule_data TEXT,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts(id)
        );
    ");
    echo "Table 'security_rules' created\n";

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_security_rules_user ON security_rules(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_security_rules_domain ON security_rules(domain_id)");
    
    // Создаем индексы для оптимизации
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_group_id ON cloudflare_accounts(group_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON cloudflare_accounts(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_domain_status ON cloudflare_accounts(domain_status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_last_check ON cloudflare_accounts(last_check)");
    echo "Indexes created\n";
    
    // Создаем тестовые данные
    echo "\nСоздание тестовых данных...\n";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $username = 'admin';
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$username, $password]);
        echo "Test user created: $username / admin123\n";
        
        $userId = $pdo->lastInsertId();
        
        // Создаем группу по умолчанию
        $stmt = $pdo->prepare("INSERT INTO groups (user_id, name) VALUES (?, ?)");
        $stmt->execute([$userId, 'Test Group']);
        echo "Test group created\n";
        $groupId = $pdo->lastInsertId();
        
        // Создаем тестовые учетные данные Cloudflare с инструкциями
        $stmt = $pdo->prepare("INSERT INTO cloudflare_credentials (user_id, email, api_key) VALUES (?, ?, ?)");
        $stmt->execute([$userId, 'your-email@example.com', 'YOUR_CLOUDFLARE_API_KEY_HERE']);
        echo "Test Cloudflare credentials created (нужно заменить на реальные)\n";
        
        $accountId = $pdo->lastInsertId();
        
        // Создаем несколько тестовых доменов с правильной структурой
        $testDomains = [
            ['domain' => 'example1.com', 'ip' => '192.168.1.100'],
            ['domain' => 'example2.com', 'ip' => '192.168.1.101'], 
            ['domain' => 'test.com', 'ip' => '192.168.1.102'],
            ['domain' => 'demo.org', 'ip' => '192.168.1.103'],
            ['domain' => 'sample.net', 'ip' => '192.168.1.104']
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO cloudflare_accounts 
            (user_id, account_id, group_id, domain, server_ip, dns_ip, ns_records, domain_status, always_use_https, min_tls_version, ssl_mode) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($testDomains as $domain) {
            // Добавляем демонстрационные NS серверы для показа как должно работать
            $testNSServers = ['ns1.example-hosting.com', 'ns2.example-hosting.com'];
            $nsRecordsJson = json_encode($testNSServers);
            
            $stmt->execute([
                $userId, 
                $accountId, 
                $groupId, 
                $domain['domain'], 
                $domain['ip'],
                $domain['ip'], // dns_ip = server_ip для демо
                $nsRecordsJson, // NS серверы в правильном формате
                'unknown', // domain_status
                0, // always_use_https
                '1.2', // min_tls_version  
                'flexible' // ssl_mode
            ]);
        }
        
        echo "Test domains created with demo NS servers: " . implode(', ', array_column($testDomains, 'domain')) . "\n";
        
        // Добавляем домен с NULL NS для демонстрации работы обновления
        $stmt->execute([
            $userId, 
            $accountId, 
            $groupId, 
            'update-me.com', 
            '192.168.1.105',
            null, // dns_ip пустой
            null, // ns_records пустые - для демонстрации обновления
            'unknown',
            0,
            '1.2',
            'flexible'
        ]);
        echo "Demo domain for NS update testing created: update-me.com\n";
        
    } else {
        echo "Users already exist, skipping test data creation\n";
    }
    
    // Проверяем результат
    echo "\nПроверка результата...\n";
    $fileSize = filesize(DB_PATH);
    echo "Final file size: $fileSize bytes\n";
    
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "Users in database: $userCount\n";
    
    $domainCount = $pdo->query("SELECT COUNT(*) FROM cloudflare_accounts")->fetchColumn();
    echo "Domains in database: $domainCount\n";
    
    $domainsWithNS = $pdo->query("
        SELECT COUNT(*) FROM cloudflare_accounts 
        WHERE ns_records IS NOT NULL AND ns_records != '' AND ns_records != '[]'
    ")->fetchColumn();
    echo "Domains with working NS records: $domainsWithNS\n";
    
    $domainsNeedingUpdate = $pdo->query("
        SELECT COUNT(*) FROM cloudflare_accounts 
        WHERE ns_records IS NULL OR ns_records = '' OR ns_records = '[]'
    ")->fetchColumn();
    echo "Domains needing NS updates: $domainsNeedingUpdate\n";
    
    echo "\n✅ База данных успешно инициализирована!\n";
    echo "\nДля входа используйте:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    
    echo "\n🔧 Следующие шаги:\n";
    echo "1. Замените тестовые API ключи на реальные в разделе 'Аккаунты Cloudflare'\n";
    echo "2. Добавьте реальные домены или обновите существующие\n";
    echo "3. Запустите обновление NS серверов для получения актуальных данных\n";
    
    echo "\n📊 Для проверки работы NS серверов:\n";
    echo "- Откройте: dashboard.php (должны показываться демо NS серверы)\n";
    echo "- Тест диагностики: simple_ns_check.php\n";
    echo "- Полная диагностика: fix_ns_display.php\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    if (isset($pdo)) {
        echo "PDO Error Info: " . print_r($pdo->errorInfo(), true) . "\n";
    }
}

echo "\n=== Инициализация завершена ===\n";
?> 