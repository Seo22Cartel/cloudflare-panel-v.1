<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
    exit;
}

$userId = $_SESSION['user_id'];

$presets = [
    'block_ai_bots' => [
        'expression' => '(cf.bot_management.score <= 10 and http.user_agent contains "AI") or (http.user_agent contains "ChatGPT" or http.user_agent contains "GPTBot")',
        'description' => 'Блокировка AI ботов'
    ],
    'block_known_bots' => [
        'expression' => '(cf.bot_management.score <= 5) or (cf.threat_score > 25)',
        'description' => 'Блокировка подозрительных ботов'
    ],
    'block_country_list' => [
        'expression' => 'ip.geoip.country in {"CN" "RU" "KP" "IR"}',
        'description' => 'Блокировка запрещённых стран'
    ]
];

try {
    $domainIdsRaw = $_POST['domain_ids'] ?? '[]';
    $domainIds = is_array($domainIdsRaw) ? $domainIdsRaw : json_decode($domainIdsRaw, true);
    $applyToAll = isset($_POST['apply_all']) ? (bool)$_POST['apply_all'] : false;
    $preset = $_POST['preset'] ?? null;
    $expression = trim($_POST['expression'] ?? '');
    $action = $_POST['action'] ?? 'block';
    $description = trim($_POST['description'] ?? 'Custom security rule');
    $paused = isset($_POST['paused']) ? (bool)$_POST['paused'] : false;

    if ($applyToAll) {
        $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE user_id = ?");
        $stmt->execute([$userId]);
        $domainIds = array_column($stmt->fetchAll(), 'id');
    }

    if (empty($domainIds)) {
        throw new Exception('Не выбраны домены для применения правила');
    }

    if ($preset && isset($presets[$preset])) {
        if ($expression === '') {
            $expression = $presets[$preset]['expression'];
        }
        if (empty($_POST['description'])) {
            $description = $presets[$preset]['description'];
        }
    }

    if ($expression === '') {
        throw new Exception('Не задано условие (expression) для правила');
    }

    $allowedActions = ['block', 'challenge', 'js_challenge', 'managed_challenge', 'allow'];
    if (!in_array($action, $allowedActions, true)) {
        throw new Exception('Недопустимое действие правила');
    }

    $results = [];
    $summary = [
        'processed' => 0,
        'success' => 0,
        'failed' => 0
    ];

    foreach ($domainIds as $domainId) {
        $summary['processed']++;

        $stmt = $pdo->prepare("SELECT ca.domain, ca.zone_id, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
        $stmt->execute([(int)$domainId, $userId]);
        $domain = $stmt->fetch();

        if (!$domain) {
            $results[] = [
                'domain_id' => $domainId,
                'success' => false,
                'error' => 'Домен не найден'
            ];
            $summary['failed']++;
            continue;
        }

        $zoneId = $domain['zone_id'];
        $proxies = getProxies($pdo, $userId);

        if (!$zoneId) {
            $zoneLookup = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones?name={$domain['domain']}", 'GET', [], $proxies, $userId);
            if ($zoneLookup['success'] && !empty($zoneLookup['data'])) {
                $zoneData = is_array($zoneLookup['data']) ? reset($zoneLookup['data']) : $zoneLookup['data'];
                $zoneId = $zoneData->id ?? null;
                if ($zoneId) {
                    $update = $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?");
                    $update->execute([$zoneId, $domainId]);
                }
            }
        }

        if (!$zoneId) {
            $results[] = [
                'domain_id' => $domainId,
                'success' => false,
                'error' => 'Zone ID не найден'
            ];
            $summary['failed']++;
            continue;
        }

        $payload = [[
            'description' => $description,
            'action' => $action,
            'paused' => $paused,
            'filter' => [
                'expression' => $expression,
                'paused' => $paused
            ]
        ]];

        $response = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/$zoneId/firewall/rules",
            'POST',
            $payload,
            $proxies,
            $userId
        );

        if ($response['success']) {
            $results[] = [
                'domain_id' => $domainId,
                'domain' => $domain['domain'],
                'success' => true,
                'rule_id' => isset($response['data'][0]->id) ? $response['data'][0]->id : null
            ];
            $summary['success']++;
            logAction($pdo, $userId, 'Security Rule Applied', "Domain: {$domain['domain']}, Action: $action");
        } else {
            $summary['failed']++;
            $errorMessage = 'API ошибка';
            if (!empty($response['api_errors'])) {
                $errorMessage = $response['api_errors'][0]['message'] ?? $errorMessage;
            }
            $results[] = [
                'domain_id' => $domainId,
                'domain' => $domain['domain'],
                'success' => false,
                'error' => $errorMessage
            ];
            logAction($pdo, $userId, 'Security Rule Failed', "Domain: {$domain['domain']}, Error: $errorMessage");
        }
    }

    echo json_encode([
        'success' => true,
        'summary' => $summary,
        'results' => $results
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


