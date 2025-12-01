<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'security_rules_api.php'; // Используем функции из существующего API

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'apply_smart_rules') {
        $scope = $_POST['scope'] ?? [];
        $rules = $_POST['rules'] ?? [];
        
        $result = applySmartWafRules($pdo, $userId, $scope, $rules);
        echo json_encode($result);
    } else {
        throw new Exception('Неизвестное действие');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function applySmartWafRules($pdo, $userId, $scope, $rules) {
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds)) {
        return ['success' => false, 'error' => 'Нет доменов для применения'];
    }
    
    $proxies = getProxies($pdo, $userId);
    $results = [];
    $appliedCount = 0;
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) {
            $results[] = ['domain' => 'Unknown', 'success' => false, 'message' => 'Домен не найден или нет Zone ID'];
            continue;
        }
        
        $domainApplied = 0;
        $errors = [];
        
        // 1. Block Bad ASNs
        if (!empty($rules['bad_asn'])) {
            // Список плохих ASN (пример)
            $badAsns = [
                14061, // DigitalOcean
                24940, // Hetzner
                16509, // Amazon
                16276, // OVH
                12876, // Online SAS
                45102, // Alibaba
                20473, // Choopa
                60781, // LeaseWeb
                51167, // Contabo
                43754  // Vultr
            ];
            
            $expression = '(ip.geoip.asnum in {' . implode(' ', $badAsns) . '})';
            $ruleData = [
                'action' => 'block',
                'description' => 'Smart WAF - Block Bad ASNs',
                'filter' => ['expression' => $expression, 'paused' => false]
            ];
            
            $res = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/{$domain['zone_id']}/firewall/rules", 'POST', [$ruleData], $proxies, $userId);
            if ($res['success']) $domainApplied++; else $errors[] = "Bad ASN: " . ($res['api_errors'][0]['message'] ?? 'Error');
        }
        
        // 2. Block High Risk Countries
        if (!empty($rules['high_risk_countries'])) {
            $countries = ['CN', 'BR', 'IN', 'VN', 'ID', 'RU', 'UA', 'TR', 'PK', 'MX'];
            $expression = '(ip.geoip.country in {' . implode(' ', array_map(fn($c) => '"'.$c.'"', $countries)) . '})';
            $ruleData = [
                'action' => 'challenge', // Используем Challenge вместо Block для безопасности
                'description' => 'Smart WAF - Challenge High Risk Countries',
                'filter' => ['expression' => $expression, 'paused' => false]
            ];
            
            $res = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/{$domain['zone_id']}/firewall/rules", 'POST', [$ruleData], $proxies, $userId);
            if ($res['success']) $domainApplied++; else $errors[] = "High Risk Countries: " . ($res['api_errors'][0]['message'] ?? 'Error');
        }
        
        // 3. Challenge Unknown Bots
        if (!empty($rules['challenge_bots'])) {
            // Challenge всех, кто не является известным ботом и не браузер
            $expression = '(not cf.client.bot and not http.user_agent contains "Mozilla")';
            $ruleData = [
                'action' => 'js_challenge',
                'description' => 'Smart WAF - Challenge Unknown Bots',
                'filter' => ['expression' => $expression, 'paused' => false]
            ];
            
            $res = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/{$domain['zone_id']}/firewall/rules", 'POST', [$ruleData], $proxies, $userId);
            if ($res['success']) $domainApplied++; else $errors[] = "Challenge Bots: " . ($res['api_errors'][0]['message'] ?? 'Error');
        }
        
        // 4. Block WordPress Attacks
        if (!empty($rules['wordpress'])) {
            $expression = '((http.request.uri.path contains "/xmlrpc.php") or (http.request.uri.path contains "/wp-login.php" and not http.request.method eq "GET"))';
            $ruleData = [
                'action' => 'block',
                'description' => 'Smart WAF - Protect WordPress',
                'filter' => ['expression' => $expression, 'paused' => false]
            ];
            
            $res = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/{$domain['zone_id']}/firewall/rules", 'POST', [$ruleData], $proxies, $userId);
            if ($res['success']) $domainApplied++; else $errors[] = "WordPress: " . ($res['api_errors'][0]['message'] ?? 'Error');
        }
        
        // 5. Rate Limiting (через WAF rules, так как Rate Limiting API платный или ограничен)
        // Эмуляция rate limiting через блокировку частых запросов к динамике (не идеально, но работает на Free)
        // На самом деле, настоящий Rate Limiting требует отдельного API endpoint.
        // Здесь мы просто включим "Under Attack" режим если выбрано, или пропустим, так как настоящий RL сложен для Free.
        // Вместо этого применим правило для блокировки агрессивных сканеров
        if (!empty($rules['rate_limit'])) {
            $expression = '(http.user_agent contains "python" or http.user_agent contains "curl" or http.user_agent contains "wget" or http.user_agent contains "java")';
            $ruleData = [
                'action' => 'block',
                'description' => 'Smart WAF - Block Scripting Tools',
                'filter' => ['expression' => $expression, 'paused' => false]
            ];
            
            $res = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/{$domain['zone_id']}/firewall/rules", 'POST', [$ruleData], $proxies, $userId);
            if ($res['success']) $domainApplied++; else $errors[] = "Rate Limit (Tools): " . ($res['api_errors'][0]['message'] ?? 'Error');
        }
        
        if ($domainApplied > 0) {
            $appliedCount += $domainApplied;
            $results[] = ['domain' => $domain['domain'], 'success' => true, 'message' => "Применено правил: $domainApplied"];
        } else {
            $results[] = ['domain' => $domain['domain'], 'success' => false, 'message' => empty($errors) ? 'Нет выбранных правил' : implode(', ', $errors)];
        }
        
        // Задержка чтобы не превысить лимиты API
        usleep(500000);
    }
    
    return [
        'success' => true,
        'total' => count($domainIds),
        'applied' => $appliedCount,
        'details' => $results
    ];
}
?>