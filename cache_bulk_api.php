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

try {
    $domainIdsRaw = $_POST['domain_ids'] ?? '[]';
    $domainIds = is_array($domainIdsRaw) ? $domainIdsRaw : json_decode($domainIdsRaw, true);
    $purgeEverything = ($_POST['purge_everything'] ?? '1') === '1';
    $files = isset($_POST['files']) ? (is_array($_POST['files']) ? $_POST['files'] : json_decode($_POST['files'], true)) : [];

    if (empty($domainIds)) {
        throw new Exception('Не выбраны домены для очистки кеша');
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
            $zoneResp = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones?name={$domain['domain']}", 'GET', [], $proxies, $userId);
            if ($zoneResp && !empty($zoneResp->result)) {
                $zoneId = $zoneResp->result[0]->id;
                $update = $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?");
                $update->execute([$zoneId, $domainId]);
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

        $payload = $purgeEverything ? ['purge_everything' => true] : ['files' => $files];

        $purgeResp = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/purge_cache", 'POST', $payload, $proxies, $userId);

        if ($purgeResp['success']) {
            $summary['success']++;
            $results[] = [
                'domain_id' => $domainId,
                'domain' => $domain['domain'],
                'success' => true
            ];
            logAction($pdo, $userId, 'Cache Purged (bulk)', "Domain: {$domain['domain']}, All: " . ($purgeEverything ? 'yes' : 'no'));
        } else {
            $summary['failed']++;
            $errorText = 'Не удалось очистить кеш';
            if (!empty($purgeResp['api_errors'])) {
                $errorText .= ': ' . ($purgeResp['api_errors'][0]['message'] ?? 'unknown');
            }
            $results[] = [
                'domain_id' => $domainId,
                'domain' => $domain['domain'],
                'success' => false,
                'error' => $errorText
            ];
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


