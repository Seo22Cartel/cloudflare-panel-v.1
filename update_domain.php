<?php
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_domain'])) {
    $proxies = getProxies($pdo, $_SESSION['user_id']);
    
    // Валидация входных данных
    $serverIp = trim($_POST['server_ip'] ?? '');
    if (!filter_var($serverIp, FILTER_VALIDATE_IP)) {
        header('Location: ' . BASE_PATH . 'dashboard.php?error=Неверный IP адрес');
        exit;
    }
    
    $domainId = (int)($_POST['id'] ?? 0);
    if ($domainId <= 0) {
        header('Location: ' . BASE_PATH . 'dashboard.php?error=Неверный идентификатор домена');
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
    $stmt->execute([$domainId, $_SESSION['user_id']]);
    $domain = $stmt->fetch();
    
    if ($domain) {
        // Получаем все настройки из формы
        $sslMode = $_POST['ssl_mode'] ?? 'flexible';
        $minTlsVersion = $_POST['min_tls_version'] ?? '1.2';
        $alwaysUseHttps = isset($_POST['always_use_https']);
        $tls13Enabled = isset($_POST['tls_1_3_enabled']);
        $automaticHttpsRewrites = isset($_POST['automatic_https_rewrites']);
        $authenticatedOriginPulls = isset($_POST['authenticated_origin_pulls']);
        
        $settings = [
            'ssl_mode' => $sslMode,
            'min_tls_version' => $minTlsVersion,
            'always_use_https' => $alwaysUseHttps,
            'tls_1_3_enabled' => $tls13Enabled,
            'automatic_https_rewrites' => $automaticHttpsRewrites,
            'authenticated_origin_pulls' => $authenticatedOriginPulls,
            'dns_ip' => true
        ];
        
        try {
            // Обновляем данные в базе
            $stmt = $pdo->prepare("
                UPDATE cloudflare_accounts 
                SET server_ip = ?, ssl_mode = ?, min_tls_version = ?, always_use_https = ?, 
                    tls_1_3_enabled = ?, automatic_https_rewrites = ?, authenticated_origin_pulls = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $serverIp,
                $sslMode,
                $minTlsVersion,
                $alwaysUseHttps ? 1 : 0,
                $tls13Enabled ? 1 : 0,
                $automaticHttpsRewrites ? 1 : 0,
                $authenticatedOriginPulls ? 1 : 0,
                $domainId,
                $_SESSION['user_id']
            ]);
            
            // Добавляем задачу на обновление настроек в Cloudflare
            $queueStmt = $pdo->prepare("INSERT INTO queue (domain_id, type, data, status) VALUES (?, ?, ?, ?)");
            $queueStmt->execute([$domainId, 'update_settings', json_encode(['settings' => $settings]), 'pending']);
            
            // Логируем действие
            logAction($pdo, $_SESSION['user_id'], "Domain updated", 
                "Domain: {$domain['domain']}, IP: $serverIp, SSL Mode: $sslMode, " .
                "TLS: $minTlsVersion, HTTPS: " . ($alwaysUseHttps ? 'on' : 'off') . 
                ", TLS 1.3: " . ($tls13Enabled ? 'on' : 'off') .
                ", Auto HTTPS: " . ($automaticHttpsRewrites ? 'on' : 'off') .
                ", Origin Pulls: " . ($authenticatedOriginPulls ? 'on' : 'off')
            );
            
            header('Location: ' . BASE_PATH . 'dashboard.php?notification=Домен обновлен и поставлен в очередь на обработку');
        } catch (PDOException $e) {
            logAction($pdo, $_SESSION['user_id'], "Domain update failed", "Domain ID: $domainId, Error: " . $e->getMessage());
            header('Location: ' . BASE_PATH . 'dashboard.php?error=Ошибка при обновлении домена');
        }
    } else {
        header('Location: ' . BASE_PATH . 'dashboard.php?error=Домен не найден');
    }
    exit;
}

header('Location: ' . BASE_PATH . 'dashboard.php');
exit;
?>