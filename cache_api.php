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
    $purgeEverything = ($_POST['purge_everything'] ?? '1') === '1';
    $files = isset($_POST['files']) ? json_decode($_POST['files'], true) : [];

    if ($domainId <= 0) {
        throw new Exception('Не указан домен');
    }

    // Получаем домен и креды
    $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
    $stmt->execute([$domainId, $_SESSION['user_id']]);
    $domain = $stmt->fetch();

    if (!$domain) {
        throw new Exception('Домен не найден');
    }

    $zoneId = $domain['zone_id'];
    $proxies = getProxies($pdo, $_SESSION['user_id']);

    if (!$zoneId) {
        // Попытаться найти зону
        $zoneResp = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones?name={$domain['domain']}", 'GET', [], $proxies, $_SESSION['user_id']);
        if (!$zoneResp || empty($zoneResp->result)) {
            throw new Exception('Zone ID не найден');
        }
        $zoneId = $zoneResp->result[0]->id;
        $upd = $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?");
        $upd->execute([$zoneId, $domainId]);
    }

    $payload = $purgeEverything ? ['purge_everything' => true] : ['files' => $files];

    $purgeResp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/purge_cache", 'POST', $payload, $proxies, $_SESSION['user_id']);

    if (!$purgeResp['success']) {
        $err = 'Не удалось очистить кеш';
        if (!empty($purgeResp['api_errors'])) {
            $err .= ': ' . implode(', ', array_map(fn($e) => ($e['code'] ?? '?') . ' ' . ($e['message'] ?? 'unknown'), $purgeResp['api_errors']));
        }
        throw new Exception($err);
    }

    logAction($pdo, $_SESSION['user_id'], 'Cache Purged', "Domain: {$domain['domain']}, Zone: $zoneId, All: " . ($purgeEverything ? 'yes' : 'no'));

    echo json_encode(['success' => true, 'message' => 'Кеш очищен']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 