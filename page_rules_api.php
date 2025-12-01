<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }

    $domainId = (int)($_POST['domain_id'] ?? 0);
    $ruleType = $_POST['rule_type'] ?? '';

    if ($domainId <= 0 || !$ruleType) throw new Exception('Неверные параметры');

    $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
    $stmt->execute([$domainId, $_SESSION['user_id']]);
    $domain = $stmt->fetch();
    if (!$domain) throw new Exception('Домен не найден');

    $zoneId = $domain['zone_id'];
    $proxies = getProxies($pdo, $_SESSION['user_id']);

    if (!$zoneId) {
        $z = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones?name={$domain['domain']}", 'GET', [], $proxies, $_SESSION['user_id']);
        if (!$z || empty($z->result)) throw new Exception('Zone ID не найден');
        $zoneId = $z->result[0]->id;
        $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?")->execute([$zoneId, $domainId]);
    }

    // Формируем правило
    $rule = null;
    switch ($ruleType) {
        case 'cache_static':
            $rule = [
                'targets' => [[
                    'target' => 'url',
                    'constraint' => [
                        'operator' => 'matches',
                        'value' => "*{$domain['domain']}/*"
                    ]
                ]],
                'actions' => [[ 'id' => 'cache_level', 'value' => 'cache_everything' ]],
                'priority' => 1,
                'status' => 'active'
            ];
            break;
        case 'redirect_https':
            $rule = [
                'targets' => [[
                    'target' => 'url',
                    'constraint' => [ 'operator' => 'matches', 'value' => "http://{$domain['domain']}/*" ]
                ]],
                'actions' => [[ 'id' => 'forwarding_url', 'value' => ['url' => "https://{$domain['domain']}/$1", 'status_code' => 301] ]],
                'priority' => 2,
                'status' => 'active'
            ];
            break;
        default:
            throw new Exception('Неизвестный тип правила');
    }

    $resp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/pagerules", 'POST', $rule, $proxies, $_SESSION['user_id']);

    if (!$resp || !$resp['success']) {
        throw new Exception('Не удалось применить правило');
    }

    echo json_encode(['success' => true, 'message' => 'Правило применено']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 