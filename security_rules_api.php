<?php
/**
 * Security Rules API
 * API для управления правилами безопасности
 */

// Показываем все ошибки в JSON формате
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// JSON заголовок
header('Content-Type: application/json; charset=utf-8');

$debugLog = [];
$debugLog['request_time'] = date('Y-m-d H:i:s');

try {
    require_once 'config.php';
    require_once 'functions.php';
    
    $debugLog['session_id'] = session_id();
    $debugLog['user_id'] = $_SESSION['user_id'] ?? 'NOT SET';
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Ошибка инициализации: ' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
    exit;
}

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (!$action) {
        throw new Exception('Не указано действие');
    }
    
    switch ($action) {
        case 'apply_bot_blocker':
            $result = applyBotBlocker($pdo, $userId, $_POST);
            echo json_encode($result);
            break;
            
        case 'apply_ip_blocker':
            $result = applyIPBlocker($pdo, $userId, $_POST);
            echo json_encode($result);
            break;
            
        case 'apply_geo_blocker':
            $result = applyGeoBlocker($pdo, $userId, $_POST);
            echo json_encode($result);
            break;
            
        case 'apply_referrer_only':
            $result = applyReferrerOnly($pdo, $userId, $_POST);
            echo json_encode($result);
            break;
            
        case 'get_worker_template':
            $template = $_GET['template'] ?? '';
            $result = getWorkerTemplate($template);
            echo json_encode($result);
            break;
            
        case 'deploy_worker':
            $result = deployWorker($pdo, $userId, $_POST);
            echo json_encode($result);
            break;
            
        case 'debug_info':
            echo json_encode([
                'success' => true,
                'debug' => $debugLog,
                'php_version' => PHP_VERSION,
                'templates_dir' => __DIR__ . '/worker_templates',
                'templates_exist' => is_dir(__DIR__ . '/worker_templates')
            ]);
            break;
            
        default:
            throw new Exception('Неизвестное действие: ' . $action);
    }
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}

/**
 * Получить шаблон Worker
 */
function getWorkerTemplate($template) {
    if (empty($template)) {
        return [
            'success' => false,
            'error' => 'Шаблон не указан',
            'available' => ['advanced-protection', 'bot-only', 'geo-only', 'referrer-only', 'rate-limit']
        ];
    }
    
    $templateFiles = [
        'advanced-protection' => '/worker_templates/advanced-protection.js',
        'bot-only' => '/worker_templates/bot-only.js',
        'geo-only' => '/worker_templates/geo-only.js',
        'referrer-only' => '/worker_templates/referrer-only.js',
        'rate-limit' => '/worker_templates/rate-limit.js'
    ];
    
    if (!isset($templateFiles[$template])) {
        return [
            'success' => false,
            'error' => 'Шаблон не найден: ' . $template,
            'available' => array_keys($templateFiles)
        ];
    }
    
    $filePath = __DIR__ . $templateFiles[$template];
    
    if (!file_exists($filePath)) {
        return [
            'success' => false,
            'error' => 'Файл шаблона не найден: ' . basename($filePath)
        ];
    }
    
    $code = @file_get_contents($filePath);
    
    if ($code === false) {
        return ['success' => false, 'error' => 'Ошибка чтения файла шаблона'];
    }
    
    // Заменяем плейсхолдеры на примеры для превью
    $previewReplacements = [
        '{{BAD_BOTS_LIST}}' => '["semrush", "ahrefs", "mj12bot", "dotbot", "petalbot"]',
        '{{BLOCKED_IPS_LIST}}' => '["192.168.1.1", "10.0.0.1"]',
        '{{GEO_MODE}}' => 'whitelist',
        '{{ALLOWED_COUNTRIES_LIST}}' => '["RU", "US", "DE", "FR", "GB"]',
        '{{ALLOWED_REFERRERS_LIST}}' => '["google.", "yandex.", "bing.com"]',
        '{{URL_EXCEPTIONS_LIST}}' => '["/api/*", "/health", "/robots.txt"]',
        '{{BLOCKED_COUNTRIES_LIST}}' => '["CN", "KP"]',
    ];
    
    foreach ($previewReplacements as $placeholder => $value) {
        $code = str_replace($placeholder, $value, $code);
    }
    
    return [
        'success' => true,
        'code' => $code,
        'template' => $template,
        'description' => getTemplateDescription($template)
    ];
}

/**
 * Получить описание шаблона
 */
function getTemplateDescription($template) {
    $descriptions = [
        'advanced-protection' => 'Полная защита: блокировка ботов, IP, геоблокировка, проверка реферреров',
        'bot-only' => 'Только блокировка плохих ботов и сканеров',
        'geo-only' => 'Только геоблокировка по странам',
        'referrer-only' => 'Только проверка реферреров (защита от прямого доступа)',
        'rate-limit' => 'Ограничение частоты запросов (Rate Limiting)'
    ];
    
    return $descriptions[$template] ?? 'Без описания';
}

/**
 * Применить блокировку ботов
 */
function applyBotBlocker($pdo, $userId, $data) {
    $rules = $data['rules'] ?? [];
    $scope = $data['scope'] ?? [];
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds)) {
        return ['success' => false, 'error' => 'Нет доменов для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    $badBots = loadBadBotsList($rules);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        $expression = buildBotBlockExpression($badBots);
        
        $ruleData = [
            'action' => 'block',
            'description' => 'Auto Bot Blocker - CloudPanel',
            'filter' => [
                'expression' => $expression,
                'paused' => false
            ]
        ];
        
        $response = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$domain['zone_id']}/firewall/rules",
            'POST',
            [$ruleData],
            $proxies,
            $userId
        );
        
        if ($response['success']) {
            $applied++;
            saveSecurityRule($pdo, $userId, $domainId, 'bad_bot', $expression);
        }
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds)
    ];
}

/**
 * Применить блокировку IP
 */
function applyIPBlocker($pdo, $userId, $data) {
    $ips = $data['ips'] ?? [];
    $importKnown = $data['importKnown'] ?? false;
    $scope = $data['scope'] ?? [];
    
    if ($importKnown) {
        $ips = array_merge($ips, loadKnownBadIPs());
    }
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds) || empty($ips)) {
        return ['success' => false, 'error' => 'Нет данных для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (empty($ip)) continue;
            
            $accessRule = [
                'mode' => 'block',
                'configuration' => [
                    'target' => strpos($ip, '/') !== false ? 'ip_range' : 'ip',
                    'value' => $ip
                ],
                'notes' => 'Auto IP Block - CloudPanel'
            ];
            
            $response = cloudflareApiRequestDetailed(
                $pdo,
                $domain['email'],
                $domain['api_key'],
                "zones/{$domain['zone_id']}/firewall/access_rules/rules",
                'POST',
                $accessRule,
                $proxies,
                $userId
            );
            
            if ($response['success']) {
                saveSecurityRule($pdo, $userId, $domainId, 'ip_block', $ip);
            }
        }
        
        $applied++;
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds),
        'ips_blocked' => count($ips)
    ];
}

/**
 * Применить геоблокировку
 */
function applyGeoBlocker($pdo, $userId, $data) {
    $mode = $data['mode'] ?? 'whitelist';
    $whitelist = $data['whitelist'] ?? [];
    $blacklist = $data['blacklist'] ?? [];
    $scope = $data['scope'] ?? [];
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds)) {
        return ['success' => false, 'error' => 'Нет доменов для применения'];
    }
    
    if ($mode === 'whitelist' && empty($whitelist)) {
        return ['success' => false, 'error' => 'Whitelist пуст'];
    }
    if ($mode === 'blacklist' && empty($blacklist)) {
        return ['success' => false, 'error' => 'Blacklist пуст'];
    }
    
    $applied = 0;
    $rulesCreated = 0;
    $proxies = getProxies($pdo, $userId);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key
            FROM cloudflare_accounts ca
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        $domainRulesCreated = 0;
        
        // Whitelist rule
        if (($mode === 'whitelist' || $mode === 'both') && !empty($whitelist)) {
            $whitelistExpression = '(not ip.geoip.country in {' . implode(' ', array_map(function($c) { return '"' . $c . '"'; }, $whitelist)) . '})';
            
            $whitelistRule = [
                'action' => 'block',
                'description' => 'Geo Whitelist - CloudPanel',
                'filter' => [
                    'expression' => $whitelistExpression,
                    'paused' => false
                ]
            ];
            
            $response = cloudflareApiRequestDetailed(
                $pdo,
                $domain['email'],
                $domain['api_key'],
                "zones/{$domain['zone_id']}/firewall/rules",
                'POST',
                [$whitelistRule],
                $proxies,
                $userId
            );
            
            if ($response['success']) {
                $domainRulesCreated++;
                $rulesCreated++;
                saveSecurityRule($pdo, $userId, $domainId, 'geo_whitelist', json_encode(['countries' => $whitelist]));
            }
        }
        
        // Blacklist rule
        if (($mode === 'blacklist' || $mode === 'both') && !empty($blacklist)) {
            $blacklistExpression = '(ip.geoip.country in {' . implode(' ', array_map(function($c) { return '"' . $c . '"'; }, $blacklist)) . '})';
            
            $blacklistRule = [
                'action' => 'block',
                'description' => 'Geo Blacklist - CloudPanel',
                'filter' => [
                    'expression' => $blacklistExpression,
                    'paused' => false
                ]
            ];
            
            $response = cloudflareApiRequestDetailed(
                $pdo,
                $domain['email'],
                $domain['api_key'],
                "zones/{$domain['zone_id']}/firewall/rules",
                'POST',
                [$blacklistRule],
                $proxies,
                $userId
            );
            
            if ($response['success']) {
                $domainRulesCreated++;
                $rulesCreated++;
                saveSecurityRule($pdo, $userId, $domainId, 'geo_blacklist', json_encode(['countries' => $blacklist]));
            }
        }
        
        if ($domainRulesCreated > 0) {
            $applied++;
        }
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds),
        'rulesCreated' => $rulesCreated,
        'mode' => $mode
    ];
}

/**
 * Применить защиту "только реферреры"
 */
function applyReferrerOnly($pdo, $userId, $data) {
    $allowedReferrers = $data['allowedReferrers'] ?? [];
    $action = $data['action'] ?? 'block';
    $scope = $data['scope'] ?? [];
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds)) {
        return ['success' => false, 'error' => 'Нет доменов для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    $expression = buildReferrerExpression($allowedReferrers, []);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        $ruleAction = $action === 'challenge' ? 'challenge' : 'block';
        
        $ruleData = [
            'action' => $ruleAction,
            'description' => 'Auto Referrer Protection - CloudPanel',
            'filter' => [
                'expression' => $expression,
                'paused' => false
            ]
        ];
        
        $response = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$domain['zone_id']}/firewall/rules",
            'POST',
            [$ruleData],
            $proxies,
            $userId
        );
        
        if ($response['success']) {
            $applied++;
            saveSecurityRule($pdo, $userId, $domainId, 'referrer_only', json_encode($allowedReferrers));
        }
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds)
    ];
}

/**
 * Развернуть Worker
 */
function deployWorker($pdo, $userId, $data) {
    $template = $data['template'] ?? '';
    $route = $data['route'] ?? '*';
    $scope = $data['scope'] ?? [];
    $config = $data['config'] ?? [];
    
    $workerData = getWorkerTemplate($template);
    if (!$workerData['success']) {
        return $workerData;
    }
    
    $domainIds = getScopeDomains($pdo, $userId, $scope);
    
    if (empty($domainIds)) {
        return ['success' => false, 'error' => 'Нет доменов для применения'];
    }
    
    $applied = 0;
    $proxies = getProxies($pdo, $userId);
    
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key
            FROM cloudflare_accounts ca
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) continue;
        
        $scriptName = "security-{$template}-" . uniqid();
        $scriptCode = generateWorkerScript($workerData['code'], $config);
        
        $uploadResponse = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "accounts/{$domain['account_id']}/workers/scripts/{$scriptName}",
            'PUT',
            ['script' => $scriptCode],
            $proxies,
            $userId
        );
        
        if ($uploadResponse['success']) {
            $routePattern = str_replace('*', $domain['domain'], $route);
            
            $routeResponse = cloudflareApiRequestDetailed(
                $pdo,
                $domain['email'],
                $domain['api_key'],
                "zones/{$domain['zone_id']}/workers/routes",
                'POST',
                [
                    'pattern' => $routePattern,
                    'script' => $scriptName
                ],
                $proxies,
                $userId
            );
            
            if ($routeResponse['success']) {
                $applied++;
                saveSecurityRule($pdo, $userId, $domainId, 'worker', json_encode([
                    'template' => $template,
                    'script_name' => $scriptName,
                    'route' => $routePattern
                ]));
            }
        }
    }
    
    return [
        'success' => true,
        'applied' => $applied,
        'total' => count($domainIds),
        'template' => $template
    ];
}

/**
 * Вспомогательные функции
 */

function getScopeDomains($pdo, $userId, $scope) {
    $type = $scope['type'] ?? 'all';
    $domainIds = [];
    
    if ($type === 'all') {
        $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $domainIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($type === 'group') {
        $groupId = $scope['groupId'] ?? null;
        $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE user_id = ? AND group_id = ?");
        $stmt->execute([$userId, $groupId]);
        $domainIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($type === 'selected') {
        $domainIds = $scope['domainIds'] ?? [];
    }
    
    return $domainIds;
}

function saveSecurityRule($pdo, $userId, $domainId, $ruleType, $ruleData) {
    $stmt = $pdo->prepare("
        INSERT INTO security_rules (user_id, domain_id, rule_type, rule_data, created_at)
        VALUES (?, ?, ?, ?, datetime('now'))
    ");
    return $stmt->execute([$userId, $domainId, $ruleType, $ruleData]);
}

function loadBadBotsList($rules) {
    $badBots = [];
    
    if ($rules['blockAllBots'] ?? false) {
        $url = 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list';
        $content = @file_get_contents($url);
        if ($content) {
            $badBots = array_merge($badBots, explode("\n", $content));
        }
    }
    
    if ($rules['blockVulnScanners'] ?? false) {
        $badBots = array_merge($badBots, [
            'nikto', 'nmap', 'sqlmap', 'nessus', 'openvas', 'acunetix',
            'metasploit', 'w3af', 'burpsuite', 'owasp', 'skipfish'
        ]);
    }
    
    if ($rules['blockMalware'] ?? false) {
        $badBots = array_merge($badBots, [
            'malware', 'ransomware', 'trojan', 'adware', 'spyware'
        ]);
    }
    
    return array_filter(array_unique(array_map('trim', $badBots)));
}

function loadKnownBadIPs() {
    $urls = [
        'https://raw.githubusercontent.com/mitchellkrogza/Suspicious.Snooping.Sniffing.Hacking.IP.Addresses/master/ips.list'
    ];
    
    $badIPs = [];
    foreach ($urls as $url) {
        $content = @file_get_contents($url);
        if ($content) {
            $badIPs = array_merge($badIPs, explode("\n", $content));
        }
    }
    
    return array_filter(array_unique(array_map('trim', $badIPs)));
}

function buildBotBlockExpression($badBots) {
    $badBots = array_slice($badBots, 0, 100);
    
    $conditions = [];
    foreach ($badBots as $bot) {
        $bot = addslashes($bot);
        $conditions[] = "(lower(http.user_agent) contains \"$bot\")";
    }
    
    return implode(' or ', $conditions);
}

function buildReferrerExpression($allowedReferrers, $exceptions) {
    $conditions = [];
    $allowed = [];
    
    if ($allowedReferrers['google'] ?? false) {
        $allowed[] = '(http.referer contains "google.")';
    }
    if ($allowedReferrers['yandex'] ?? false) {
        $allowed[] = '(http.referer contains "yandex.")';
    }
    if ($allowedReferrers['bing'] ?? false) {
        $allowed[] = '(http.referer contains "bing.com")';
    }
    if ($allowedReferrers['duckduckgo'] ?? false) {
        $allowed[] = '(http.referer contains "duckduckgo.com")';
    }
    if ($allowedReferrers['baidu'] ?? false) {
        $allowed[] = '(http.referer contains "baidu.com")';
    }
    
    foreach ($allowedReferrers['custom'] ?? [] as $domain) {
        $allowed[] = "(http.referer contains \"$domain\")";
    }
    
    $allowedExpression = implode(' or ', $allowed);
    
    return "(not ($allowedExpression))";
}

function generateWorkerScript($templateCode, $config = []) {
    $badBots = $config['badBots'] ?? ['semrush', 'ahrefs', 'majestic', 'mj12bot', 'dotbot'];
    $blockedIps = $config['blockedIps'] ?? [];
    $geoMode = $config['geoMode'] ?? 'whitelist';
    $allowedCountries = $config['allowedCountries'] ?? ['RU', 'US', 'GB', 'DE', 'FR'];
    $blockedCountries = $config['blockedCountries'] ?? ['CN', 'KP'];
    $allowedReferrers = $config['allowedReferrers'] ?? ['google.', 'yandex.', 'bing.com'];
    $urlExceptions = $config['urlExceptions'] ?? ['/api/*', '/robots.txt'];
    
    $replacements = [
        '{{BAD_BOTS_LIST}}' => json_encode($badBots),
        '{{BLOCKED_IPS_LIST}}' => json_encode($blockedIps),
        '{{GEO_MODE}}' => $geoMode,
        '{{ALLOWED_COUNTRIES_LIST}}' => json_encode($allowedCountries),
        '{{BLOCKED_COUNTRIES_LIST}}' => json_encode($blockedCountries),
        '{{ALLOWED_REFERRERS_LIST}}' => json_encode($allowedReferrers),
        '{{URL_EXCEPTIONS_LIST}}' => json_encode($urlExceptions),
    ];
    
    foreach ($replacements as $placeholder => $value) {
        $templateCode = str_replace($placeholder, $value, $templateCode);
    }
    
    return $templateCode;
}
