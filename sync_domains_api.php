<?php
/**
 * API для прогрессивной синхронизации доменов
 * Обновляет статус, SSL и DNS IP последовательно
 */

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'get_domains':
        getDomains($pdo, $userId);
        break;
    case 'sync_domain':
        syncDomain($pdo, $userId);
        break;
    case 'get_progress':
        getProgress($pdo, $userId);
        break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

/**
 * Получить список доменов для синхронизации
 */
function getDomains($pdo, $userId) {
    $groupId = $_POST['group_id'] ?? null;
    
    $sql = "
        SELECT ca.id, ca.domain, ca.zone_id, cc.email, cc.api_key
        FROM cloudflare_accounts ca
        JOIN cloudflare_credentials cc ON ca.account_id = cc.id
        WHERE ca.user_id = ? AND ca.zone_id IS NOT NULL AND ca.zone_id != ''
    ";
    $params = [$userId];
    
    if ($groupId && $groupId !== 'all') {
        $sql .= " AND ca.group_id = ?";
        $params[] = $groupId;
    }
    
    $sql .= " ORDER BY ca.domain ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $domains = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'domains' => $domains,
        'total' => count($domains)
    ]);
}

/**
 * Синхронизировать один домен
 */
function syncDomain($pdo, $userId) {
    $domainId = (int)($_POST['domain_id'] ?? 0);
    
    if (!$domainId) {
        echo json_encode(['success' => false, 'error' => 'Domain ID required']);
        return;
    }
    
    // Получаем данные домена
    $stmt = $pdo->prepare("
        SELECT ca.*, cc.email, cc.api_key
        FROM cloudflare_accounts ca
        JOIN cloudflare_credentials cc ON ca.account_id = cc.id
        WHERE ca.id = ? AND ca.user_id = ?
    ");
    $stmt->execute([$domainId, $userId]);
    $domain = $stmt->fetch();
    
    if (!$domain) {
        echo json_encode(['success' => false, 'error' => 'Domain not found']);
        return;
    }
    
    $result = [
        'success' => true,
        'domain_id' => $domainId,
        'domain' => $domain['domain'],
        'dns_ip' => null,
        'ssl_mode' => null,
        'ssl_status' => null,
        'http_code' => null,
        'domain_status' => null,
        'changes' => [],
        'errors' => []
    ];
    
    $proxies = getProxies($pdo, $userId);
    $zoneId = $domain['zone_id'];
    
    // 1. Получаем DNS IP (все A-записи)
    try {
        $dnsResponse = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$zoneId}/dns_records?type=A&per_page=100",
            'GET', [], $proxies, $userId
        );
        
        if ($dnsResponse['success'] && !empty($dnsResponse['data'])) {
            // Собираем все уникальные IP
            $ips = [];
            $records = is_array($dnsResponse['data']) ? $dnsResponse['data'] : [$dnsResponse['data']];
            foreach ($records as $record) {
                if (isset($record->content) && $record->content) {
                    $ips[] = $record->content;
                }
            }
            if (!empty($ips)) {
                $uniqueIps = array_unique($ips);
                $result['dns_ip'] = implode(', ', $uniqueIps);
                $result['a_records_count'] = count($records);
                
                if ($domain['dns_ip'] !== $result['dns_ip']) {
                    $result['changes'][] = "IP: {$domain['dns_ip']} → {$result['dns_ip']}";
                }
            }
        }
    } catch (Exception $e) {
        $result['errors'][] = 'DNS: ' . $e->getMessage();
    }
    
    // 2. Получаем SSL настройки
    try {
        $sslResponse = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$zoneId}/settings/ssl",
            'GET', [], $proxies, $userId
        );
        
        if ($sslResponse['success'] && isset($sslResponse['data'])) {
            $sslData = $sslResponse['data'];
            $sslMode = null;
            
            // Робастное извлечение значения SSL mode
            if (is_object($sslData)) {
                if (isset($sslData->value)) {
                    $sslMode = $sslData->value;
                } else {
                    // Попробуем через get_object_vars
                    $sslVars = get_object_vars($sslData);
                    if (isset($sslVars['value'])) {
                        $sslMode = $sslVars['value'];
                    }
                }
            } elseif (is_array($sslData) && isset($sslData['value'])) {
                $sslMode = $sslData['value'];
            }
            
            if ($sslMode) {
                $result['ssl_mode'] = $sslMode;
                
                if ($domain['ssl_mode'] !== $result['ssl_mode']) {
                    $result['changes'][] = "SSL: {$domain['ssl_mode']} → {$result['ssl_mode']}";
                }
            }
        }
    } catch (Exception $e) {
        $result['errors'][] = 'SSL: ' . $e->getMessage();
    }
    
    // 3. Проверяем статус SSL сертификата
    try {
        $certResponse = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/{$zoneId}/ssl/certificate_packs?status=active",
            'GET', [], $proxies, $userId
        );
        
        if ($certResponse['success']) {
            $result['ssl_status'] = !empty($certResponse['data']) ? 'active' : 'none';
            $result['ssl_has_active'] = !empty($certResponse['data']) ? 1 : 0;
        }
    } catch (Exception $e) {
        $result['errors'][] = 'Certificate: ' . $e->getMessage();
    }
    
    // 4. Проверяем HTTP статус домена
    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://{$domain['domain']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_NOBODY => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 CloudPanel/2.1'
        ]);
        
        // Добавляем прокси если есть (формат: IP:PORT@LOGIN:PASS)
        if (!empty($proxies)) {
            $proxyString = $proxies[array_rand($proxies)];
            if ($proxyString && preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxyString, $matches)) {
                $proxyIp = $matches[1];
                $proxyPort = $matches[2];
                $proxyLogin = $matches[3];
                $proxyPass = $matches[4];
                
                curl_setopt($ch, CURLOPT_PROXY, "$proxyIp:$proxyPort");
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyLogin:$proxyPass");
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            }
        }
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result['http_code'] = $httpCode;
        $result['domain_status'] = $httpCode >= 200 && $httpCode < 400 ? 'online' : 'offline';
        
    } catch (Exception $e) {
        $result['errors'][] = 'HTTP: ' . $e->getMessage();
        $result['http_code'] = 0;
        $result['domain_status'] = 'error';
    }
    
    // Обновляем БД - проверяем наличие колонки http_code
    try {
        $updateSql = "
            UPDATE cloudflare_accounts SET
                dns_ip = COALESCE(?, dns_ip),
                ssl_mode = COALESCE(?, ssl_mode),
                ssl_has_active = COALESCE(?, ssl_has_active),
                http_code = ?,
                domain_status = ?,
                last_check = datetime('now'),
                ssl_last_check = datetime('now')
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($updateSql);
        $stmt->execute([
            $result['dns_ip'],
            $result['ssl_mode'],
            $result['ssl_has_active'] ?? null,
            $result['http_code'],
            $result['domain_status'],
            $domainId
        ]);
    } catch (PDOException $e) {
        // Если нет колонки http_code, обновляем без неё
        if (strpos($e->getMessage(), 'http_code') !== false) {
            $updateSql = "
                UPDATE cloudflare_accounts SET
                    dns_ip = COALESCE(?, dns_ip),
                    ssl_mode = COALESCE(?, ssl_mode),
                    ssl_has_active = COALESCE(?, ssl_has_active),
                    domain_status = ?,
                    last_check = datetime('now'),
                    ssl_last_check = datetime('now')
                WHERE id = ?
            ";
            
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([
                $result['dns_ip'],
                $result['ssl_mode'],
                $result['ssl_has_active'] ?? null,
                $result['domain_status'],
                $domainId
            ]);
        } else {
            throw $e;
        }
    }
    
    echo json_encode($result);
}

/**
 * Получить текущий прогресс
 */
function getProgress($pdo, $userId) {
    // Подсчитываем статистику
    $stats = [];
    
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE user_id = $userId")->fetchColumn();
    $stats['online'] = $pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE user_id = $userId AND domain_status = 'online'")->fetchColumn();
    $stats['offline'] = $pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE user_id = $userId AND domain_status = 'offline'")->fetchColumn();
    $stats['active_ssl'] = $pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE user_id = $userId AND ssl_has_active = 1")->fetchColumn();
    $stats['checked_today'] = $pdo->query("SELECT COUNT(*) FROM cloudflare_accounts WHERE user_id = $userId AND date(last_check) = date('now')")->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
}