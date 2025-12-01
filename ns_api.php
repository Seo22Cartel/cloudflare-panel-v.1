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
    $domainId = (int)($_GET['domain_id'] ?? ($_POST['domain_id'] ?? 0));
    if ($domainId <= 0) {
        throw new Exception('Не указан домен');
    }

    $stmt = $pdo->prepare("SELECT ca.domain, ca.ns_records, ca.zone_id, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
    $stmt->execute([$domainId, $_SESSION['user_id']]);
    $domain = $stmt->fetch();

    if (!$domain) {
        throw new Exception('Домен не найден');
    }

    $nsRecords = [];
    if (!empty($domain['ns_records'])) {
        $decoded = json_decode($domain['ns_records'], true);
        if (is_array($decoded)) {
            $nsRecords = $decoded;
        }
    }

    if (empty($nsRecords) && !empty($domain['zone_id'])) {
        $proxies = getProxies($pdo, $_SESSION['user_id']);
        $zoneDetails = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/{$domain['zone_id']}", 'GET', [], $proxies, $_SESSION['user_id']);
        if ($zoneDetails['success'] && isset($zoneDetails['data']->name_servers)) {
            $nsRecords = $zoneDetails['data']->name_servers;
            $update = $pdo->prepare("UPDATE cloudflare_accounts SET ns_records = ? WHERE id = ?");
            $update->execute([json_encode($nsRecords), $domainId]);
        }
    }

    if (empty($nsRecords)) {
        throw new Exception('NS записи не найдены. Попробуйте обновить статус домена.');
    }

    echo json_encode([
        'success' => true,
        'domain' => $domain['domain'],
        'ns_records' => $nsRecords,
        'clipboard' => implode("\n", $nsRecords)
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


