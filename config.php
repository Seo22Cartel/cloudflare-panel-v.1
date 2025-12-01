<?php
session_start();

// Определяем, это API запрос или нет
$_isApiEndpoint = (
    isset($_SERVER['REQUEST_URI']) &&
    (strpos($_SERVER['REQUEST_URI'], '_api.php') !== false ||
     strpos($_SERVER['REQUEST_URI'], 'queue_processor.php') !== false)
);

// Bot and AI Crawler Protection - НЕ применяем для API endpoints
if (!$_isApiEndpoint) {
    require_once 'bot_protection.php';
}

// Автоматическое определение базового пути
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$basePath = rtrim($scriptPath, '/') . '/';
define('BASE_PATH', $basePath);

// Определение абсолютного пути к файлам
define('ROOT_PATH', dirname(__FILE__) . '/');
define('DB_PATH', ROOT_PATH . 'cloudflare_panel.db');

// Перенаправление на HTTPS, если соединение не защищено (исключая localhost, CLI и API файлы)
if (php_sapi_name() !== 'cli') {
    $isLocalhost = in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1', '::1']);
    $isApiFile = (
        isset($_SERVER['REQUEST_URI']) && 
        (strpos($_SERVER['REQUEST_URI'], 'queue_processor.php') !== false ||
         strpos($_SERVER['REQUEST_URI'], 'get_debug_logs.php') !== false ||
         strpos($_SERVER['REQUEST_URI'], 'test_') !== false ||
         strpos($_SERVER['REQUEST_URI'], '.json') !== false)
    );
    
    if (!$isLocalhost && !$isApiFile && (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')) {
        header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
        exit;
    }
}

/**
 * Инициализация соединения с базой данных SQLite
 */
try {
    // Проверяем права доступа к файлу базы данных
    if (!file_exists(DB_PATH)) {
        touch(DB_PATH);
        chmod(DB_PATH, 0600); // Устанавливаем права 600 (только владелец может читать/писать) для безопасности
    } elseif (!is_writable(DB_PATH)) {
        die("Database file is not writable: " . DB_PATH);
    }
    
    // Проверяем и исправляем права доступа, если они слишком широкие
    $currentPerms = fileperms(DB_PATH) & 0777;
    if ($currentPerms > 0600) {
        chmod(DB_PATH, 0600);
    }

    // Создаем соединение с базой данных
    $pdo = new PDO("sqlite:" . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Создание таблиц, если они еще не существуют
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL
        );
        
        CREATE TABLE IF NOT EXISTS groups (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            name TEXT NOT NULL,
            UNIQUE(user_id, name),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (account_id) REFERENCES cloudflare_credentials(id),
            FOREIGN KEY (group_id) REFERENCES groups(id),
            UNIQUE(user_id, domain)
        );
        
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
        
        CREATE TABLE IF NOT EXISTS logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        
        CREATE TABLE IF NOT EXISTS proxies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            proxy TEXT NOT NULL,
            status INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        
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
            FOREIGN KEY (user_id) REFERENCES users (id),
            FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts (id)
        );

        CREATE TABLE IF NOT EXISTS cloudflare_api_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            token TEXT NOT NULL,
            tag TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (account_id) REFERENCES cloudflare_credentials(id)
        );

        CREATE TABLE IF NOT EXISTS cloudflare_firewall_rules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            domain_id INTEGER NOT NULL,
            rule_id TEXT,
            name TEXT NOT NULL,
            expression TEXT NOT NULL,
            action TEXT NOT NULL,
            paused INTEGER DEFAULT 0,
            description TEXT,
            schedule TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts(id)
        );

        CREATE TABLE IF NOT EXISTS cloudflare_pages_projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            account_id INTEGER NOT NULL,
            domain_id INTEGER,
            project_id TEXT,
            name TEXT NOT NULL,
            production_branch TEXT,
            build_config TEXT,
            latest_deploy TEXT,
            status TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (account_id) REFERENCES cloudflare_credentials(id),
            FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts(id)
        );

        CREATE TABLE IF NOT EXISTS cloudflare_bulk_operations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            account_id INTEGER,
            operation_type TEXT NOT NULL,
            payload TEXT,
            total_items INTEGER DEFAULT 0,
            processed_items INTEGER DEFAULT 0,
            success_items INTEGER DEFAULT 0,
            failed_items INTEGER DEFAULT 0,
            status TEXT DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME,
            completed_at DATETIME,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (account_id) REFERENCES cloudflare_credentials(id)
        );

        CREATE TABLE IF NOT EXISTS cloudflare_worker_scripts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            script TEXT NOT NULL,
            usage_count INTEGER DEFAULT 0,
            last_used DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(user_id, name),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS cloudflare_worker_routes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            domain_id INTEGER NOT NULL,
            route_id TEXT,
            route_pattern TEXT NOT NULL,
            script_name TEXT,
            template_id INTEGER,
            status TEXT,
            last_error TEXT,
            applied_at DATETIME,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (domain_id) REFERENCES cloudflare_accounts(id),
            FOREIGN KEY (template_id) REFERENCES cloudflare_worker_scripts(id),
            UNIQUE(user_id, domain_id, route_pattern)
        );

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

    // Добавляем индекс для оптимизации фильтра по group_id
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_group_id ON cloudflare_accounts(group_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_security_rules_user ON security_rules(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_security_rules_domain ON security_rules(domain_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_user_id ON cloudflare_accounts(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_domain_status ON cloudflare_accounts(domain_status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_last_check ON cloudflare_accounts(last_check)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_tokens_user ON cloudflare_api_tokens(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_firewall_rules_domain ON cloudflare_firewall_rules(domain_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_pages_projects_account ON cloudflare_pages_projects(account_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_bulk_operations_user ON cloudflare_bulk_operations(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_worker_scripts_user ON cloudflare_worker_scripts(user_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_worker_routes_domain ON cloudflare_worker_routes(domain_id)");

    try {
        $pdo->exec("ALTER TABLE cloudflare_accounts ADD COLUMN updated_at DATETIME");
    } catch (Exception $e) {
        // Колонка уже существует
    }

    try {
        $pdo->exec("UPDATE cloudflare_accounts SET updated_at = COALESCE(updated_at, created_at)");
    } catch (Exception $e) {
        // Игнорируем ошибки обновления при отсутствии таблицы
    }

    // Проверяем, есть ли пользователи, и создаем пользователя по умолчанию, если их нет
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        // Генерируем случайные учетные данные
        // Используем только буквы и цифры для логина (без специальных символов)
        $randomUsername = 'admin' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
        // Пароль может содержать специальные символы
        $randomPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 12);
        
        // Сохраняем учетные данные в файл
        $credentialsFile = ROOT_PATH . 'credentials.txt';
        file_put_contents($credentialsFile, "Username: $randomUsername\nPassword: $randomPassword\n\nЭти данные были сгенерированы автоматически при первом запуске.\nСохраните их в безопасном месте и удалите этот файл после прочтения.");
        chmod($credentialsFile, 0600); // Только владелец может читать
        
        // Хешируем пароль и сохраняем в БД
        $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$randomUsername, $hashedPassword]);
        
        // Выводим информацию о созданных учетных данных только в CLI режиме И только если это не API запрос
        if (php_sapi_name() === 'cli' && !isset($_SERVER['REQUEST_URI'])) {
            echo "\n=== УЧЕТНЫЕ ДАННЫЕ СОЗДАНЫ ===\n";
            echo "Username: $randomUsername\n";
            echo "Password: $randomPassword\n";
            echo "Файл credentials.txt создан в корневой папке\n";
            echo "================================\n\n";
        }
    }

    // Проверяем, есть ли группы, и создаем группу по умолчанию, если их нет
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM groups WHERE user_id = ?");
    $stmt->execute([1]); // Предполагаем user_id = 1 для пользователя по умолчанию
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO groups (user_id, name) VALUES (?, ?)");
        $stmt->execute([1, 'Default Group']);
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Настройки отладки (отключаем для API запросов чтобы не мешать JSON)
$isApiRequest = (
    isset($_SERVER['REQUEST_URI']) &&
    (strpos($_SERVER['REQUEST_URI'], '_api.php') !== false ||
     strpos($_SERVER['REQUEST_URI'], 'get_debug_logs.php') !== false ||
     strpos($_SERVER['REQUEST_URI'], 'queue_processor.php') !== false ||
     strpos($_SERVER['REQUEST_URI'], '.json') !== false ||
     (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
     (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false))
);

if (!$isApiRequest) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Для API запросов отключаем вывод ошибок в HTML
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL); // Ошибки логируем, но не выводим
}

// Удаляем автоматическую авторизацию - она мешает нормальной работе
// if (!isset($_SESSION['user_id'])) {
//     $_SESSION['user_id'] = 1; // Временная заглушка для тестов, замените на реальную авторизацию
// }

/**
 * Парсит учетные данные Cloudflare в различных форматах
 * Поддерживает форматы: 
 * - email;api_key (новый формат)
 * - email;password;api_key (устаревший формат)
 */
function parseCloudflareCredentials($credentialString) {
    $credentialString = trim($credentialString);
    
    // Проверяем формат с разделителем ;
    if (strpos($credentialString, ';') !== false) {
        $parts = explode(';', $credentialString);
        
        if (count($parts) === 2) {
            // Новый формат: email;api_key
            return [
                'email' => trim($parts[0]),
                'api_key' => trim($parts[1]),
                'format' => 'modern_legacy'
            ];
        } elseif (count($parts) >= 3) {
            // Устаревший формат: email;password;api_key
            return [
                'email' => trim($parts[0]),
                'password' => trim($parts[1]), // Сохраняем пароль для совместимости
                'api_key' => trim($parts[2]),
                'format' => 'legacy'
            ];
        } else {
            throw new Exception('Неверный формат учетных данных. Ожидается: email;api_key или email;password;api_key');
        }
    }
    
    // Если это Bearer token
    if (strpos($credentialString, 'Bearer ') === 0 || strlen($credentialString) > 30) {
        return [
            'api_token' => $credentialString,
            'format' => 'bearer'
        ];
    }
    
    throw new Exception('Неподдерживаемый формат учетных данных');
}

/**
 * Создает SSL менеджер из строки учетных данных
 */
function createSSLManagerFromCredentials($credentialString, $pdo, $userId, $proxies = []) {
    $credentials = parseCloudflareCredentials($credentialString);
    // В текущей версии не используется объектный менеджер SSL, возвращаем разобранные креды
    if ($credentials['format'] === 'legacy') {
        return [
            'email' => $credentials['email'],
            'api_key' => $credentials['api_key'],
            'format' => $credentials['format']
        ];
    }
    return [
        'api_token' => $credentials['api_token'] ?? null,
        'format' => $credentials['format']
    ];
}

/**
 * Массовый импорт учетных данных в различных форматах
 * Поддерживает: email;api_key и email;password;api_key
 */
function importLegacyCredentials($credentialsList, $pdo, $userId, $testConnections = true) {
    $results = [
        'total_processed' => 0,
        'success_count' => 0,
        'error_count' => 0,
        'duplicate_count' => 0,
        'successful' => [],
        'failed' => [],
        'duplicates' => []
    ];
    
    foreach ($credentialsList as $index => $credentialString) {
        $results['total_processed']++;
        
        try {
            $credentials = parseCloudflareCredentials($credentialString);
            
            if (!in_array($credentials['format'], ['legacy', 'modern_legacy'])) {
                $results['error_count']++;
                $results['failed'][] = [
                    'line' => $index + 1,
                    'credential' => $credentialString,
                    'error' => 'Поддерживается только формат email;api_key или email;password;api_key'
                ];
                continue;
            }
            
            // Проверяем, существует ли уже такой email
            $stmt = $pdo->prepare("SELECT id FROM cloudflare_credentials WHERE user_id = ? AND email = ?");
            $stmt->execute([$userId, $credentials['email']]);
            
            if ($stmt->fetch()) {
                $results['duplicate_count']++;
                $results['duplicates'][] = [
                    'line' => $index + 1,
                    'email' => $credentials['email'],
                    'message' => 'Email уже существует в базе данных'
                ];
                continue;
            }
            
            $connectionTest = null;
            
            // Тестируем соединение с Cloudflare если требуется
            if ($testConnections) {
                $connectionTest = testCloudflareConnection($credentials['email'], $credentials['api_key']);
                
                if (!$connectionTest['success']) {
                    $results['error_count']++;
                    $results['failed'][] = [
                        'line' => $index + 1,
                        'email' => $credentials['email'],
                        'error' => 'Ошибка подключения: ' . $connectionTest['error']
                    ];
                    continue;
                }
            }
            
            // Сохраняем разобранный API ключ
            $stmt = $pdo->prepare("
                INSERT INTO cloudflare_credentials (user_id, email, api_key, status, created_at) 
                VALUES (?, ?, ?, 'active', datetime('now'))
            ");
            $stmt->execute([
                $userId,
                $credentials['email'],
                $credentials['api_key']
            ]);
            
            $results['success_count']++;
            $successData = [
                'line' => $index + 1,
                'email' => $credentials['email'],
                'format' => $credentials['format']
            ];
            
            if ($connectionTest) {
                $successData['connection_test'] = true;
                $successData['zones_count'] = $connectionTest['zones_count'] ?? 0;
            }
            
            $results['successful'][] = $successData;
            
        } catch (Exception $e) {
            $results['error_count']++;
            $results['failed'][] = [
                'line' => $index + 1,
                'credential' => $credentialString,
                'error' => $e->getMessage()
            ];
        }
    }
    
    return $results;
}

/**
 * Тестирует соединение с Cloudflare API
 */
function testCloudflareConnection($email, $apiKey) {
    try {
        $ch = curl_init("https://api.cloudflare.com/client/v4/zones?per_page=1");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Auth-Email: $email",
            "X-Auth-Key: $apiKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            return [
                'success' => false,
                'error' => 'cURL error: ' . $curlError
            ];
        }

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'HTTP error ' . $httpCode
            ];
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'JSON decode error: ' . json_last_error_msg()
            ];
        }

        $zones = $decoded['result'] ?? [];
        return [
            'success' => true,
            'zones_count' => is_array($zones) ? count($zones) : 0,
            'message' => 'Соединение успешно установлено'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Валидирует формат учетных данных Cloudflare
 * Поддерживает: email;api_key и email;password;api_key
 */
function validateCredentialFormat($credentialString) {
    $parts = explode(';', trim($credentialString));
    
    if (count($parts) === 2) {
        // Новый формат: email;api_key
        $email = trim($parts[0]);
        $apiKey = trim($parts[1]);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'error' => 'Неверный формат email'
            ];
        }
        
        if (strlen($apiKey) < 30) {
            return [
                'valid' => false,
                'error' => 'API ключ слишком короткий'
            ];
        }
        
        return [
            'valid' => true,
            'email' => $email,
            'api_key' => $apiKey,
            'format' => 'modern_legacy'
        ];
        
    } elseif (count($parts) === 3) {
        // Устаревший формат: email;password;api_key
        $email = trim($parts[0]);
        $password = trim($parts[1]);
        $apiKey = trim($parts[2]);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'error' => 'Неверный формат email'
            ];
        }
        
        if (empty($password)) {
            return [
                'valid' => false,
                'error' => 'Пароль не может быть пустым'
            ];
        }
        
        if (strlen($apiKey) < 30) {
            return [
                'valid' => false,
                'error' => 'API ключ слишком короткий'
            ];
        }
        
        return [
            'valid' => true,
            'email' => $email,
            'password' => $password,
            'api_key' => $apiKey,
            'format' => 'legacy'
        ];
        
    } else {
        return [
            'valid' => false,
            'error' => 'Неверное количество частей. Ожидается: email;api_key или email;password;api_key'
        ];
    }
}

/**
 * Валидирует формат email;password;api_key (для обратной совместимости)
 */
function validateLegacyCredentialFormat($credentialString) {
    return validateCredentialFormat($credentialString);
}

/**
 * Массовая валидация учетных данных
 */
function validateBulkCredentials($credentialsList) {
    $results = [
        'valid' => [],
        'invalid' => [],
        'duplicates' => [],
        'total_checked' => 0,
        'valid_count' => 0,
        'invalid_count' => 0,
        'duplicate_count' => 0
    ];
    
    $emails = [];
    
    foreach ($credentialsList as $index => $credential) {
        $results['total_checked']++;
        
        $validation = validateCredentialFormat($credential);
        
        if (!$validation['valid']) {
            $results['invalid_count']++;
            $results['invalid'][] = [
                'line' => $index + 1,
                'credential' => $credential,
                'error' => $validation['error']
            ];
            continue;
        }
        
        // Проверяем дубликаты email в текущем списке
        if (in_array($validation['email'], $emails)) {
            $results['duplicate_count']++;
            $results['duplicates'][] = [
                'line' => $index + 1,
                'email' => $validation['email'],
                'error' => 'Дублирующийся email в списке'
            ];
            continue;
        }
        
        $emails[] = $validation['email'];
        $results['valid_count']++;
        $results['valid'][] = [
            'line' => $index + 1,
            'email' => $validation['email'],
            'credential' => $credential,
            'format' => $validation['format']
        ];
    }
    
    return $results;
}
?>