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
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    if (!$action) throw new Exception('Не указано действие');

    $userId = $_SESSION['user_id'];

    switch ($action) {
        case 'list':
            $domainId = (int)($_GET['domain_id'] ?? 0);
            if ($domainId <= 0) throw new Exception('Неверный домен');
            echo json_encode(listDnsRecords($pdo, $userId, $domainId));
            break;
        case 'create':
            if ($method !== 'POST') throw new Exception('Метод не поддерживается');
            echo json_encode(createDnsRecord($pdo, $userId, $_POST));
            break;
        case 'update':
            if ($method !== 'POST') throw new Exception('Метод не поддерживается');
            echo json_encode(updateDnsRecord($pdo, $userId, $_POST));
            break;
        case 'delete':
            if ($method !== 'POST') throw new Exception('Метод не поддерживается');
            echo json_encode(deleteDnsRecord($pdo, $userId, $_POST));
            break;
        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function ensureZone($pdo, $userId, $domainId) {
    $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
    $stmt->execute([$domainId, $userId]);
    $domain = $stmt->fetch();
    if (!$domain) throw new Exception('Домен не найден');
    $zoneId = $domain['zone_id'];
    $proxies = getProxies($pdo, $userId);
    if (!$zoneId) {
        $z = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones?name={$domain['domain']}", 'GET', [], $proxies, $userId);
        if (!$z || empty($z->result)) throw new Exception('Zone ID не найден');
        $zoneId = $z->result[0]->id;
        $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?")->execute([$zoneId, $domainId]);
    }
    return [$domain, $zoneId, $proxies];
}

function listDnsRecords($pdo, $userId, $domainId) {
    [$domain, $zoneId, $proxies] = ensureZone($pdo, $userId, $domainId);
    $resp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/dns_records", 'GET', [], $proxies, $userId);
    if (!$resp['success']) throw new Exception('Не удалось получить записи DNS');
    return ['success' => true, 'records' => $resp['data']];
}

function createDnsRecord($pdo, $userId, $data) {
    $domainId = (int)($data['domain_id'] ?? 0);
    $type = strtoupper(trim($data['type'] ?? ''));
    $name = trim($data['name'] ?? '');
    $content = trim($data['content'] ?? '');
    $ttl = (int)($data['ttl'] ?? 1);
    $proxied = isset($data['proxied']) ? (bool)$data['proxied'] : null;
    if ($domainId <= 0 || !$type || !$name || !$content) throw new Exception('Неверные параметры');
    [$domain, $zoneId, $proxies] = ensureZone($pdo, $userId, $domainId);
    $payload = [ 'type' => $type, 'name' => $name, 'content' => $content, 'ttl' => $ttl ];
    if (!is_null($proxied)) $payload['proxied'] = $proxied;
    $resp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/dns_records", 'POST', $payload, $proxies, $userId);
    if (!$resp['success']) throw new Exception('Не удалось создать запись');
    return ['success' => true, 'record' => $resp['data']];
}

function updateDnsRecord($pdo, $userId, $data) {
    $domainId = (int)($data['domain_id'] ?? 0);
    $recordId = trim($data['record_id'] ?? '');
    if ($domainId <= 0 || !$recordId) throw new Exception('Неверные параметры');
    [$domain, $zoneId, $proxies] = ensureZone($pdo, $userId, $domainId);
    $payload = [];
    foreach (['type','name','content','ttl','proxied'] as $k) if (isset($data[$k])) $payload[$k] = $data[$k];
    if (empty($payload)) throw new Exception('Нет данных для обновления');
    $resp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/dns_records/$recordId", 'PATCH', $payload, $proxies, $userId);
    if (!$resp['success']) throw new Exception('Не удалось обновить запись');
    return ['success' => true, 'record' => $resp['data']];
}

function deleteDnsRecord($pdo, $userId, $data) {
    $domainId = (int)($data['domain_id'] ?? 0);
    $recordId = trim($data['record_id'] ?? '');
    if ($domainId <= 0 || !$recordId) throw new Exception('Неверные параметры');
    [$domain, $zoneId, $proxies] = ensureZone($pdo, $userId, $domainId);
    $resp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/dns_records/$recordId", 'DELETE', [], $proxies, $userId);
    if (!$resp['success']) throw new Exception('Не удалось удалить запись');
    return ['success' => true];
} 