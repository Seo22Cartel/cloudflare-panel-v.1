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
    $accountId = (int)($_POST['account_id'] ?? 0);
    $groupId = isset($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $domainListRaw = trim($_POST['domains'] ?? '');
    $serverIp = trim($_POST['server_ip'] ?? '');
    $sslMode = $_POST['ssl_mode'] ?? null;
    $minTlsVersion = $_POST['min_tls_version'] ?? null;
    $alwaysHttps = isset($_POST['always_use_https']) ? (bool)$_POST['always_use_https'] : null;
    $applyDefaults = ($sslMode || $minTlsVersion || $alwaysHttps !== null || $serverIp !== '');

    if ($accountId <= 0) {
        throw new Exception('Не указан аккаунт Cloudflare');
    }

    if ($domainListRaw === '') {
        throw new Exception('Список доменов пуст');
    }

    $domains = array_filter(array_map('trim', preg_split("/[\r\n,;]+/", $domainListRaw)));
    if (empty($domains)) {
        throw new Exception('Не найдено ни одного домена для обработки');
    }

    // Получаем учетные данные
    $accountStmt = $pdo->prepare("SELECT email, api_key FROM cloudflare_credentials WHERE id = ? AND user_id = ?");
    $accountStmt->execute([$accountId, $userId]);
    $account = $accountStmt->fetch();

    if (!$account) {
        throw new Exception('Аккаунт Cloudflare не найден');
    }

    $proxies = getProxies($pdo, $userId);

    $results = [];
    $summary = [
        'processed' => 0,
        'created' => 0,
        'imported' => 0,
        'failed' => 0
    ];

    foreach ($domains as $inputDomain) {
        $summary['processed']++;
        $originalDomain = strtolower($inputDomain);

        if (!filter_var($originalDomain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $results[] = [
                'domain' => $originalDomain,
                'success' => false,
                'error' => 'Неверный формат домена'
            ];
            $summary['failed']++;
            continue;
        }

        $asciiDomain = function_exists('idn_to_ascii') ? idn_to_ascii($originalDomain, 0, INTL_IDNA_VARIANT_UTS46) : $originalDomain;
        if ($asciiDomain === false) {
            $results[] = [
                'domain' => $originalDomain,
                'success' => false,
                'error' => 'Не удалось преобразовать домен (IDNA)'
            ];
            $summary['failed']++;
            continue;
        }

        try {
            $pdo->beginTransaction();

            // Проверяем, есть ли домен в базе
            $existingStmt = $pdo->prepare("SELECT id, zone_id, server_ip, dns_ip, ssl_mode, min_tls_version, always_use_https FROM cloudflare_accounts WHERE user_id = ? AND domain = ?");
            $existingStmt->execute([$userId, $originalDomain]);
            $existingDomain = $existingStmt->fetch();

            $zoneId = null;
            $nsRecords = [];

            // Проверяем наличие зоны в Cloudflare
            $zoneLookup = cloudflareApiRequestDetailed(
                $pdo,
                $account['email'],
                $account['api_key'],
                "zones?name=$asciiDomain",
                'GET',
                [],
                $proxies,
                $userId
            );

            if ($zoneLookup['success'] && !empty($zoneLookup['data'])) {
                $zoneResult = is_array($zoneLookup['data']) ? reset($zoneLookup['data']) : $zoneLookup['data'];
                $zoneId = $zoneResult->id ?? null;
                $nsRecords = $zoneResult->name_servers ?? [];
            }

            if (!$zoneId) {
                $zoneCreate = cloudflareApiRequestDetailed(
                    $pdo,
                    $account['email'],
                    $account['api_key'],
                    'zones',
                    'POST',
                    [
                        'name' => $asciiDomain,
                        'jump_start' => false
                    ],
                    $proxies,
                    $userId
                );

                if (!$zoneCreate['success'] || empty($zoneCreate['data'])) {
                    $errorMessage = 'Cloudflare отказал в создании зоны';
                    if (!empty($zoneCreate['api_errors'])) {
                        $errorMessage .= ': ' . ($zoneCreate['api_errors'][0]['message'] ?? 'unknown');
                    }
                    throw new Exception($errorMessage);
                }

                $zoneData = is_array($zoneCreate['data']) ? $zoneCreate['data'] : [$zoneCreate['data']];
                $zoneObject = reset($zoneData);
                $zoneId = $zoneObject->id ?? null;
                $nsRecords = $zoneObject->name_servers ?? [];

                if (!$zoneId) {
                    throw new Exception('Cloudflare не вернул идентификатор зоны');
                }

                logAction($pdo, $userId, 'Zone Created (bulk)', "Domain: $originalDomain, Zone: $zoneId");
                $summary['created']++;
            } else {
                $summary['imported']++;
            }

            // Применяем настройки, если требуются
            if ($applyDefaults) {
                if ($sslMode) {
                    cloudflareApiRequestDetailed(
                        $pdo,
                        $account['email'],
                        $account['api_key'],
                        "zones/$zoneId/settings/ssl",
                        'PATCH',
                        ['value' => $sslMode],
                        $proxies,
                        $userId
                    );
                }

                if ($alwaysHttps !== null) {
                    cloudflareApiRequestDetailed(
                        $pdo,
                        $account['email'],
                        $account['api_key'],
                        "zones/$zoneId/settings/always_use_https",
                        'PATCH',
                        ['value' => $alwaysHttps ? 'on' : 'off'],
                        $proxies,
                        $userId
                    );
                }

                if ($minTlsVersion) {
                    cloudflareApiRequestDetailed(
                        $pdo,
                        $account['email'],
                        $account['api_key'],
                        "zones/$zoneId/settings/min_tls_version",
                        'PATCH',
                        ['value' => $minTlsVersion],
                        $proxies,
                        $userId
                    );
                }
            }

            $dnsIp = null;
            $shouldCreateDns = ($serverIp !== '' && filter_var($serverIp, FILTER_VALIDATE_IP));
            if ($shouldCreateDns) {
                $dnsCreate = cloudflareApiRequestDetailed(
                    $pdo,
                    $account['email'],
                    $account['api_key'],
                    "zones/$zoneId/dns_records",
                    'POST',
                    [
                        'type' => 'A',
                        'name' => $asciiDomain,
                        'content' => $serverIp,
                        'ttl' => 1,
                        'proxied' => false
                    ],
                    $proxies,
                    $userId
                );

                if ($dnsCreate['success']) {
                    $dnsIp = $serverIp;
                }
            }

            if (empty($nsRecords)) {
                // Получаем актуальные NS записи
                $zoneDetails = cloudflareApiRequestDetailed(
                    $pdo,
                    $account['email'],
                    $account['api_key'],
                    "zones/$zoneId",
                    'GET',
                    [],
                    $proxies,
                    $userId
                );
                if ($zoneDetails['success'] && isset($zoneDetails['data']->name_servers)) {
                    $nsRecords = $zoneDetails['data']->name_servers;
                }
            }

            $finalServerIp = $serverIp !== '' ? $serverIp : ($existingDomain['server_ip'] ?? null);
            $finalDnsIp = $dnsIp ?? ($existingDomain['dns_ip'] ?? null);
            $finalSslMode = $sslMode ?? ($existingDomain['ssl_mode'] ?? 'flexible');
            $finalTls = $minTlsVersion ?? ($existingDomain['min_tls_version'] ?? '1.0');
            $finalHttps = $alwaysHttps !== null ? (int)$alwaysHttps : ($existingDomain['always_use_https'] ?? 0);

            // Сохраняем или обновляем домен в БД
            if ($existingDomain) {
                $updateStmt = $pdo->prepare("UPDATE cloudflare_accounts SET account_id = ?, group_id = ?, zone_id = ?, ns_records = ?, server_ip = ?, dns_ip = ?, ssl_mode = ?, min_tls_version = ?, always_use_https = ?, updated_at = datetime('now') WHERE id = ?");
                $updateStmt->execute([
                    $accountId,
                    $groupId,
                    $zoneId,
                    json_encode($nsRecords),
                    $finalServerIp,
                    $finalDnsIp,
                    $finalSslMode,
                    $finalTls,
                    $finalHttps,
                    $existingDomain['id']
                ]);
                $domainId = (int)$existingDomain['id'];
            } else {
                $insertStmt = $pdo->prepare("INSERT INTO cloudflare_accounts (user_id, account_id, group_id, domain, server_ip, dns_ip, zone_id, ns_records, ssl_mode, min_tls_version, always_use_https, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
                $insertStmt->execute([
                    $userId,
                    $accountId,
                    $groupId,
                    $originalDomain,
                    $finalServerIp,
                    $finalDnsIp,
                    $zoneId,
                    json_encode($nsRecords),
                    $finalSslMode,
                    $finalTls,
                    $finalHttps
                ]);
                $domainId = (int)$pdo->lastInsertId();
            }

            if ($domainId > 0) {
                $queueStmt = $pdo->prepare("INSERT INTO queue (user_id, domain_id, type, data, status) VALUES (?, ?, ?, ?, ?)");
                $queueStmt->execute([$userId, $domainId, 'sync_all', json_encode(['created_via_bulk' => true]), 'pending']);
            }

            $pdo->commit();

            $results[] = [
                'domain' => $originalDomain,
                'success' => true,
                'zone_id' => $zoneId,
                'ns_records' => $nsRecords,
                'server_ip' => $finalServerIp
            ];
        } catch (Exception $domainException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $results[] = [
                'domain' => $originalDomain,
                'success' => false,
                'error' => $domainException->getMessage()
            ];
            $summary['failed']++;
            logAction($pdo, $userId, 'Bulk domain error', "Domain: $originalDomain, Error: " . $domainException->getMessage());
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


