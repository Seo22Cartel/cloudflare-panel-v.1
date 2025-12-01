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

$payload = file_get_contents('php://input');
$data = json_decode($payload, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = $data['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'bulk_add_domains':
            $accountId = (int)($data['account_id'] ?? 0);
            $domains = $data['domains'] ?? [];
            if ($accountId <= 0 || empty($domains) || !is_array($domains)) {
                throw new Exception('Не указаны аккаунт или список доменов');
            }
            $options = $data['options'] ?? [];
            $result = cloudflareBulkAddDomains($pdo, $userId, $accountId, $domains, $options);
            echo json_encode($result);
            break;

        case 'bulk_apply_settings':
            $domainIds = $data['domain_ids'] ?? [];
            $settings = $data['settings'] ?? [];
            if (empty($domainIds) || !is_array($domainIds) || empty($settings)) {
                throw new Exception('Не указаны домены или настройки');
            }
            $result = cloudflareBulkApplySettings($pdo, $userId, $domainIds, $settings);
            echo json_encode(['success' => true, 'details' => $result]);
            break;

        case 'bulk_cache_settings':
            $domainIds = $data['domain_ids'] ?? [];
            $settings = $data['cache_settings'] ?? [];
            if (empty($domainIds) || !is_array($domainIds) || empty($settings)) {
                throw new Exception('Не указаны домены или настройки кеша');
            }
            $details = [];
            foreach ($domainIds as $domainId) {
                $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
                $stmt->execute([$domainId, $userId]);
                $domainRow = $stmt->fetch();
                if (!$domainRow) {
                    $details[] = ['domain_id' => $domainId, 'success' => false, 'error' => 'Домен не найден'];
                    continue;
                }
                $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
                $proxies = getProxies($pdo, $userId);
                $details[] = array_merge(
                    ['domain_id' => $domainId, 'domain' => $domainRow['domain']],
                    cloudflareUpdateCacheSettings($pdo, $domainRow, $credentials, $settings, $proxies, $userId)
                );
                usleep(250000);
            }
            echo json_encode(['success' => true, 'details' => $details]);
            break;

        case 'bulk_security_settings':
            $domainIds = $data['domain_ids'] ?? [];
            $settings = $data['security_settings'] ?? [];
            if (empty($domainIds) || !is_array($domainIds) || empty($settings)) {
                throw new Exception('Не указаны домены или параметры безопасности');
            }
            $details = [];
            foreach ($domainIds as $domainId) {
                $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
                $stmt->execute([$domainId, $userId]);
                $domainRow = $stmt->fetch();
                if (!$domainRow) {
                    $details[] = ['domain_id' => $domainId, 'success' => false, 'error' => 'Домен не найден'];
                    continue;
                }
                $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
                $proxies = getProxies($pdo, $userId);
                $details[] = array_merge(
                    ['domain_id' => $domainId, 'domain' => $domainRow['domain']],
                    cloudflareUpdateSecuritySettings($pdo, $domainRow, $credentials, $settings, $proxies, $userId)
                );
                usleep(250000);
            }
            echo json_encode(['success' => true, 'details' => $details]);
            break;

        case 'bulk_purge_cache':
            $domainIds = $data['domain_ids'] ?? [];
            if (empty($domainIds) || !is_array($domainIds)) {
                throw new Exception('Не выбраны домены');
            }
            $purgeEverything = !isset($data['files']);
            $files = $data['files'] ?? [];
            $details = [];
            foreach ($domainIds as $domainId) {
                $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
                $stmt->execute([$domainId, $userId]);
                $domainRow = $stmt->fetch();
                if (!$domainRow) {
                    $details[] = ['domain_id' => $domainId, 'success' => false, 'error' => 'Домен не найден'];
                    continue;
                }
                $proxies = getProxies($pdo, $userId);
                $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
                $zoneId = $domainRow['zone_id'];
                if (!$zoneId) {
                    $zone = ensureCloudflareZone($pdo, $credentials, $domainRow['domain'], $proxies, $userId, false);
                    if (!$zone['success']) {
                        $details[] = ['domain_id' => $domainId, 'success' => false, 'error' => 'Zone ID не найден'];
                        continue;
                    }
                    $zoneId = $zone['zone_id'];
                    $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?")->execute([$zoneId, $domainId]);
                }
                $payload = $purgeEverything ? ['purge_everything' => true] : ['files' => $files];
                $resp = cloudflareApiRequestDetailed($pdo, $credentials['email'], $credentials['api_key'], "zones/$zoneId/purge_cache", 'POST', $payload, $proxies, $userId);
                $details[] = [
                    'domain_id' => $domainId,
                    'domain' => $domainRow['domain'],
                    'success' => $resp['success'],
                    'error' => $resp['success'] ? null : ($resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown')
                ];
                usleep(250000);
            }
            echo json_encode(['success' => true, 'details' => $details]);
            break;

        case 'change_group':
            $domainId = (int)($data['domain_id'] ?? 0);
            $groupId = $data['group_id'] ?? null;
            
            if ($domainId <= 0) {
                throw new Exception('Не указан домен');
            }
            
            // Проверяем что домен принадлежит пользователю
            $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$domainId, $userId]);
            if (!$stmt->fetch()) {
                throw new Exception('Домен не найден или нет доступа');
            }
            
            // Если указана группа, проверяем что она принадлежит пользователю
            if ($groupId !== null && $groupId !== '') {
                $groupId = (int)$groupId;
                $stmt = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND user_id = ?");
                $stmt->execute([$groupId, $userId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Группа не найдена или нет доступа');
                }
            } else {
                $groupId = null;
            }
            
            // Обновляем группу домена
            $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET group_id = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$groupId, $domainId, $userId]);
            
            logAction($pdo, $userId, 'Group Changed', "Domain ID: $domainId, New Group ID: " . ($groupId ?? 'None'));
            
            echo json_encode(['success' => true, 'domain_id' => $domainId, 'group_id' => $groupId]);
            break;

        case 'bulk_change_group':
            $domainIds = $data['domain_ids'] ?? [];
            $groupId = $data['group_id'] ?? null;
            
            if (empty($domainIds) || !is_array($domainIds)) {
                throw new Exception('Не выбраны домены');
            }
            
            // Если указана группа, проверяем что она принадлежит пользователю
            if ($groupId !== null && $groupId !== '') {
                $groupId = (int)$groupId;
                $stmt = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND user_id = ?");
                $stmt->execute([$groupId, $userId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Группа не найдена или нет доступа');
                }
            } else {
                $groupId = null;
            }
            
            // Обновляем группу для всех доменов
            $placeholders = implode(',', array_fill(0, count($domainIds), '?'));
            $params = array_merge([$groupId], $domainIds, [$userId]);
            $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET group_id = ? WHERE id IN ($placeholders) AND user_id = ?");
            $stmt->execute($params);
            $affected = $stmt->rowCount();
            
            logAction($pdo, $userId, 'Bulk Group Changed', "Domain IDs: " . implode(',', $domainIds) . ", New Group ID: " . ($groupId ?? 'None') . ", Affected: $affected");
            
            echo json_encode(['success' => true, 'affected' => $affected, 'group_id' => $groupId]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


