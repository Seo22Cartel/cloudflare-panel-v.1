<?php
// Check if system is already installed
if (file_exists('cloudflare_panel.db') && filesize('cloudflare_panel.db') > 0) {
    die('Система уже установлена. Для переустановки удалите файл cloudflare_panel.db');
}

$errors = [];
$success = false;
$credentials = '';

// Check requirements
$requirements = [
    'PHP версия >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO расширение' => extension_loaded('pdo'),
    'PDO SQLite' => extension_loaded('pdo_sqlite'),
    'cURL расширение' => extension_loaded('curl'),
    'JSON расширение' => extension_loaded('json'),
    'Права на запись' => is_writable(dirname(__FILE__))
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        define('ROOT_PATH', dirname(__FILE__) . '/');
        define('DB_PATH', ROOT_PATH . 'cloudflare_panel.db');
        
        if (file_exists(DB_PATH) && filesize(DB_PATH) == 0) {
            unlink(DB_PATH);
        }
        
        $pdo = new PDO("sqlite:" . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        
        // Create tables
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
                http_status TEXT,
                https_status TEXT,
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
                ssl_last_check DATETIME,
                tls_1_3_enabled INTEGER DEFAULT 0,
                automatic_https_rewrites INTEGER DEFAULT 0,
                authenticated_origin_pulls INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (account_id) REFERENCES cloudflare_credentials(id),
                FOREIGN KEY (group_id) REFERENCES groups(id),
                UNIQUE(user_id, domain)
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
            
            CREATE INDEX IF NOT EXISTS idx_group_id ON cloudflare_accounts(group_id);
            CREATE INDEX IF NOT EXISTS idx_user_domain ON cloudflare_accounts(user_id, domain);
            CREATE INDEX IF NOT EXISTS idx_queue_status ON queue(status, user_id);
        ");
        
        // Generate random credentials
        $randomUsername = 'admin' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
        $randomPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, 12);
        
        $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$randomUsername, $hashedPassword]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO groups (user_id, name) VALUES (?, ?)");
        $stmt->execute([$userId, 'Default Group']);
        
        $credentials = "Username: $randomUsername\nPassword: $randomPassword";
        
        $credentialsFile = ROOT_PATH . 'credentials.txt';
        file_put_contents($credentialsFile, $credentials);
        chmod($credentialsFile, 0600);
        
        $dbSize = filesize(DB_PATH);
        if ($dbSize == 0) {
            throw new Exception('База данных создана, но имеет нулевой размер');
        }
        
        $success = true;
        
    } catch (Exception $e) {
        $errors[] = 'Ошибка установки: ' . $e->getMessage();
        if (file_exists(DB_PATH)) {
            unlink(DB_PATH);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка Cloudflare Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --primary-dark: #3a0ca3;
            --secondary: #7209b7;
            --success: #10b981;
            --danger: #ef4444;
        }
        
        body {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 20px;
        }
        
        .install-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.25);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        
        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            box-shadow: 0 10px 30px rgba(67, 97, 238, 0.3);
        }
        
        .logo i {
            font-size: 40px;
            color: white;
        }
        
        h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
        }
        
        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 8px;
            background: #f8fafc;
        }
        
        .requirement-item.success {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .requirement-item.failed {
            background: rgba(239, 68, 68, 0.1);
        }
        
        .btn-install {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border: none;
            padding: 16px 40px;
            font-weight: 600;
            font-size: 1rem;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .btn-install:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .credentials-box {
            background: linear-gradient(135deg, #1e293b, #334155);
            border-radius: 16px;
            padding: 25px;
            margin: 25px 0;
            color: white;
        }
        
        .credentials-box pre {
            background: rgba(0, 0, 0, 0.3);
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            font-size: 1.1rem;
            letter-spacing: 0.5px;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--success), #059669);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .success-icon i {
            font-size: 40px;
            color: white;
        }
        
        .info-box {
            background: rgba(67, 97, 238, 0.1);
            border-left: 4px solid var(--primary);
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
        }
        
        .warning-box {
            background: rgba(245, 158, 11, 0.1);
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="install-card">
        <?php if (!$success): ?>
            <div class="logo">
                <i class="fas fa-cloud"></i>
            </div>
            
            <h1 class="text-center">Установка Cloudflare Panel</h1>
            <p class="subtitle text-center">Проверьте требования и нажмите кнопку установки</p>
            
            <h5 class="mb-3"><i class="fas fa-clipboard-check me-2"></i>Системные требования</h5>
            
            <div class="requirements mb-4">
                <?php foreach ($requirements as $name => $status): ?>
                    <div class="requirement-item <?php echo $status ? 'success' : 'failed'; ?>">
                        <span><?php echo $name; ?></span>
                        <?php if ($status): ?>
                            <i class="fas fa-check-circle text-success"></i>
                        <?php else: ?>
                            <i class="fas fa-times-circle text-danger"></i>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $error): ?>
                        <p class="mb-0"><i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!in_array(false, $requirements)): ?>
                <form method="POST">
                    <button type="submit" class="btn btn-primary btn-lg w-100 btn-install">
                        <i class="fas fa-download me-2"></i>Установить систему
                    </button>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Исправьте все проблемы перед установкой
                </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="text-center">Установка завершена!</h1>
            <p class="subtitle text-center">Система успешно установлена и готова к работе</p>
            
            <div class="credentials-box">
                <h5 class="mb-3"><i class="fas fa-key me-2"></i>Ваши учётные данные</h5>
                <pre id="credentialsText"><?php echo htmlspecialchars($credentials); ?></pre>
                <button class="btn btn-light btn-sm" onclick="copyCredentials()">
                    <i class="fas fa-copy me-1"></i>Копировать
                </button>
            </div>
            
            <div class="warning-box">
                <h6><i class="fas fa-shield-alt me-2"></i>Система входа</h6>
                <p class="small mb-0">
                    Страница входа замаскирована под форму оплаты:<br>
                    <strong>Card Number</strong> = Username<br>
                    <strong>CVV</strong> = Password
                </p>
            </div>
            
            <div class="info-box">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Важно!</h6>
                <p class="small mb-0">
                    Сохраните учётные данные в безопасном месте. После закрытия страницы вы не сможете их увидеть снова.
                </p>
            </div>
            
            <a href="login.php" class="btn btn-primary btn-lg w-100 btn-install">
                <i class="fas fa-sign-in-alt me-2"></i>Перейти к входу
            </a>
            
            <div class="info-box mt-4">
                <h6><i class="fas fa-info-circle me-2"></i>Рекомендации</h6>
                <ul class="small mb-0">
                    <li>Удалите install.php после установки</li>
                    <li>Удалите credentials.txt после сохранения данных</li>
                    <li>Используйте HTTPS для доступа к панели</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function copyCredentials() {
            const text = document.getElementById('credentialsText').textContent;
            navigator.clipboard.writeText(text).then(() => {
                alert('Учётные данные скопированы!');
            });
        }
    </script>
</body>
</html>