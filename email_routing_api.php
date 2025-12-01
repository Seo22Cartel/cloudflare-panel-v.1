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
    $source = trim($_POST['source'] ?? ''); // local-part
    $destination = trim($_POST['destination'] ?? ''); // full email

    if ($domainId <= 0 || !$source || !$destination) throw new Exception('Неверные параметры');

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

    // Включаем Email Routing для зоны (мягкая попытка)
    cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/email/routing/enable", 'POST', [], $proxies, $_SESSION['user_id']);

    // Создаем custom address
    $customResp = cloudflareApiRequestDetailed(
        $pdo, $domain['email'], $domain['api_key'],
        "zones/$zoneId/email/routing/addresses",
        'POST',
        ['email' => $source . '@' . $domain['domain']],
        $proxies, $_SESSION['user_id']
    );

    if (!$customResp['success']) throw new Exception('Не удалось создать адрес');

    // Создаем правило маршрутизации
    $ruleResp = cloudflareApiRequestDetailed(
        $pdo, $domain['email'], $domain['api_key'],
        "zones/$zoneId/email/routing/rules",
        'POST',
        [
            'actions' => [[ 'type' => 'forward', 'value' => [$destination] ]],
            'matchers' => [[ 'type' => 'literal', 'field' => 'to', 'value' => $source . '@' . $domain['domain'] ]],
            'enabled' => true,
            'name' => 'Auto forward ' . $source
        ],
        $proxies, $_SESSION['user_id']
    );

    if (!$ruleResp['success']) throw new Exception('Не удалось создать правило');

    echo json_encode(['success' => true, 'message' => 'Email routing настроен']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 