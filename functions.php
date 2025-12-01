<?php
require_once 'config.php';

// Основные функции логирования и работы с прокси
function logAction($pdo, $userId, $action, $details = '') {
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $action, $details]);
        return true;
    } catch (Exception $e) {
        error_log("Log Action Failed: " . $e->getMessage());
        return false;
    }
}

function getRandomProxy($proxies) {
    return $proxies ? $proxies[array_rand($proxies)] : null;
}

function getProxies($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT proxy FROM proxies WHERE user_id = ? AND status = 1");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        return [];
    }
}

// Функция проверки прокси
function checkProxy($pdo, $proxyString, $proxyId, $userId) {
    try {
        // Парсим прокси в формате IP:PORT@LOGIN:PASSWORD
        if (!preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxyString, $matches)) {
            logAction($pdo, $userId, "Proxy Check Failed", "Invalid format: $proxyString");
            $stmt = $pdo->prepare("UPDATE proxies SET status = 2 WHERE id = ?");
            $stmt->execute([$proxyId]);
            return false;
        }
        
        $proxyIp = $matches[1];
        $proxyPort = $matches[2];
        $proxyLogin = $matches[3];
        $proxyPass = $matches[4];
        
        // Тестируем прокси
        $ch = curl_init('http://httpbin.org/ip');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_PROXY => "$proxyIp:$proxyPort",
            CURLOPT_PROXYUSERPWD => "$proxyLogin:$proxyPass",
            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,
            CURLOPT_FOLLOWLOCATION => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($response !== false && $httpCode === 200 && empty($curlError)) {
            // Прокси работает
            $stmt = $pdo->prepare("UPDATE proxies SET status = 1 WHERE id = ?");
            $stmt->execute([$proxyId]);
            logAction($pdo, $userId, "Proxy Check Success", "Proxy: $proxyString");
            return true;
        } else {
            // Прокси не работает
            $stmt = $pdo->prepare("UPDATE proxies SET status = 2 WHERE id = ?");
            $stmt->execute([$proxyId]);
            logAction($pdo, $userId, "Proxy Check Failed", "Proxy: $proxyString, Error: $curlError, HTTP: $httpCode");
            return false;
        }
        
    } catch (Exception $e) {
        // Ошибка проверки
        $stmt = $pdo->prepare("UPDATE proxies SET status = 2 WHERE id = ?");
        $stmt->execute([$proxyId]);
        logAction($pdo, $userId, "Proxy Check Error", "Proxy: $proxyString, Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Улучшенная функция для API запросов к Cloudflare с детальным логированием ошибок
 * Поддерживает как legacy аутентификацию (Email + API Key), так и современные Bearer токены
 * 
 * @param PDO $pdo Соединение с базой данных
 * @param string|null $email Email для legacy аутентификации (или null для Bearer token)
 * @param string $apiKey API Key для legacy аутентификации или Bearer token
 * @param string $endpoint Эндпоинт API (без префикса /client/v4/)
 * @param string $method HTTP метод (GET, POST, PATCH, PUT, DELETE)
 * @param array $data Данные для отправки
 * @param array $proxies Список прокси серверов
 * @param int|null $userId ID пользователя для логирования
 * @return array Детальный результат запроса
 */
function cloudflareApiRequestDetailed($pdo, $email, $apiKey, $endpoint, $method = 'GET', $data = [], $proxies = [], $userId = null) {
    $result = [
        'success' => false,
        'data' => null,
        'http_code' => 0,
        'curl_error' => null,
        'api_errors' => [],
        'api_messages' => [],
        'raw_response' => null,
        'auth_method' => null
    ];
    
    $ch = curl_init("https://api.cloudflare.com/client/v4/$endpoint");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    // Определяем метод аутентификации
    $headers = ["Content-Type: application/json"];
    
    if ($email === null || empty($email) || strpos($apiKey, 'Bearer ') === 0 || strlen($apiKey) > 40) {
        // Используем Bearer token (современный способ)
        $token = strpos($apiKey, 'Bearer ') === 0 ? $apiKey : "Bearer $apiKey";
        $headers[] = "Authorization: $token";
        $result['auth_method'] = 'bearer';
    } else {
        // Используем legacy аутентификацию (Email + API Key)
        $headers[] = "X-Auth-Email: $email";
        $headers[] = "X-Auth-Key: $apiKey";
        $result['auth_method'] = 'legacy';
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    // Настройка прокси - ИСПРАВЛЕННЫЙ КОД
    if ($proxies) {
        $proxy = getRandomProxy($proxies);
        if ($proxy && preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
            $proxyIp = $matches[1];
            $proxyPort = $matches[2];
            $proxyLogin = $matches[3];
            $proxyPass = $matches[4];
            
            curl_setopt($ch, CURLOPT_PROXY, "$proxyIp:$proxyPort");
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyLogin:$proxyPass");
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            
            if ($userId) {
                logAction($pdo, $userId, "Using Proxy (Detailed)", "Proxy: $proxyIp:$proxyPort, Endpoint: $endpoint");
            }
        }
    }
    
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $result['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result['curl_error'] = curl_error($ch);
    $result['raw_response'] = $response;
    curl_close($ch);

    if ($response === false || !empty($result['curl_error'])) {
        if ($userId) logAction($pdo, $userId, "API Request Failed (Detailed)", "Endpoint: $endpoint, cURL Error: {$result['curl_error']}");
        return $result;
    }

    if ($result['http_code'] !== 200) {
        if ($userId) logAction($pdo, $userId, "API Request Failed (Detailed)", "Endpoint: $endpoint, HTTP Code: {$result['http_code']}, Response: " . substr($response, 0, 500));
        return $result;
    }

    $decodedResponse = json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if ($userId) logAction($pdo, $userId, "API Request Failed (Detailed)", "Endpoint: $endpoint, JSON Error: " . json_last_error_msg());
        return $result;
    }

    // Парсим ответ API
    if (isset($decodedResponse->success)) {
        $result['success'] = $decodedResponse->success;
        $result['data'] = $decodedResponse->result ?? null;
        
        // Извлекаем ошибки API
        if (isset($decodedResponse->errors) && is_array($decodedResponse->errors)) {
            foreach ($decodedResponse->errors as $error) {
                $result['api_errors'][] = [
                    'code' => $error->code ?? 'unknown',
                    'message' => $error->message ?? 'Unknown error',
                    'error_chain' => $error->error_chain ?? null
                ];
            }
        }
        
        // Извлекаем сообщения API
        if (isset($decodedResponse->messages) && is_array($decodedResponse->messages)) {
            foreach ($decodedResponse->messages as $message) {
                $result['api_messages'][] = $message->message ?? 'Unknown message';
            }
        }
    }

    if ($userId) {
        $status = $result['success'] ? 'Success' : 'Failed';
        $errorCount = count($result['api_errors']);
        logAction($pdo, $userId, "API Request $status (Detailed)", "Endpoint: $endpoint, Errors: $errorCount");
    }
    
    return $result;
}

// Получение DNS IP из Cloudflare
function getDNSIPFromCloudflare($pdo, $domainId, $userId) {
    try {
        // Логируем начало операции
        logAction($pdo, $userId, "getDNSIP Started", "Domain ID: $domainId, User ID: $userId");
        
        // Получаем информацию о домене
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain) {
            logAction($pdo, $userId, "getDNSIP Error", "Domain not found for ID: $domainId");
            return ['success' => false, 'error' => 'Домен не найден'];
        }
        
        logAction($pdo, $userId, "getDNSIP Domain Found", "Domain: {$domain['domain']}, Email: {$domain['email']}, Current DNS IP: {$domain['dns_ip']}, Zone ID: {$domain['zone_id']}");
        
        $proxies = getProxies($pdo, $userId);
        
        // Получаем зону
        $zoneResponse = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones?name={$domain['domain']}", 'GET', [], $proxies, $userId);
        if (!$zoneResponse || empty($zoneResponse->result)) {
            logAction($pdo, $userId, "getDNSIP Error", "Zone not found in Cloudflare for domain: {$domain['domain']}");
            return ['success' => false, 'error' => 'Зона не найдена в Cloudflare'];
        }
        
        $zoneId = $zoneResponse->result[0]->id;
        logAction($pdo, $userId, "getDNSIP Zone Found", "Domain: {$domain['domain']}, Zone ID: $zoneId");
        
        // Обновляем zone_id в базе если его нет
        if (empty($domain['zone_id'])) {
            logAction($pdo, $userId, "getDNSIP Updating Zone ID", "Domain: {$domain['domain']}, Old Zone ID: {$domain['zone_id']}, New Zone ID: $zoneId");
            
            $updateZoneStmt = $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?");
            $zoneUpdateResult = $updateZoneStmt->execute([$zoneId, $domainId]);
            $zoneRowsAffected = $updateZoneStmt->rowCount();
            
            logAction($pdo, $userId, "getDNSIP Zone Update Result", "Domain: {$domain['domain']}, Update Success: " . ($zoneUpdateResult ? 'Yes' : 'No') . ", Rows Affected: $zoneRowsAffected");
        }
        
        // Получаем DNS записи типа A
        $dnsResponse = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/dns_records?type=A", 'GET', [], $proxies, $userId);
        if (!$dnsResponse || empty($dnsResponse->result)) {
            logAction($pdo, $userId, "getDNSIP Error", "A records not found for domain: {$domain['domain']}");
            return ['success' => false, 'error' => 'A записи не найдены'];
        }
        
        $dnsIp = $dnsResponse->result[0]->content;
        $allIPs = array_map(function($record) { return $record->content; }, $dnsResponse->result);
        
        logAction($pdo, $userId, "getDNSIP Records Found", "Domain: {$domain['domain']}, Primary IP: $dnsIp, All IPs: " . implode(', ', $allIPs));
        
        // Получаем NS серверы для домена
        $nsServers = [];
        $nsResponse = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/dns_records?type=NS", 'GET', [], $proxies, $userId);
        if ($nsResponse && !empty($nsResponse->result)) {
            $nsServers = array_map(function($record) { 
                return $record->content; 
            }, $nsResponse->result);
            logAction($pdo, $userId, "getDNSIP NS Records Found", "Domain: {$domain['domain']}, NS Servers: " . implode(', ', $nsServers));
        } else {
            logAction($pdo, $userId, "getDNSIP NS Records", "Domain: {$domain['domain']}, No NS records found or API error");
        }
        
        // Обновляем DNS IP и NS серверы в базе
        $nsRecordsJson = json_encode($nsServers);
        $updateStmt = $pdo->prepare("UPDATE cloudflare_accounts SET dns_ip = ?, zone_id = ?, ns_records = ? WHERE id = ?");
        $updateResult = $updateStmt->execute([$dnsIp, $zoneId, $nsRecordsJson, $domainId]);
        $rowsAffected = $updateStmt->rowCount();
        
        logAction($pdo, $userId, "getDNSIP Database Update", "Domain: {$domain['domain']}, Update Success: " . ($updateResult ? 'Yes' : 'No') . ", Rows Affected: $rowsAffected, New DNS IP: $dnsIp, Zone ID: $zoneId");
        
        // Проверяем результат обновления
        if (!$updateResult) {
            logAction($pdo, $userId, "getDNSIP Database Update Failed", "Domain: {$domain['domain']}, Failed to execute UPDATE statement");
            return ['success' => false, 'error' => 'Не удалось обновить DNS IP в базе данных'];
        }
        
        if ($rowsAffected === 0) {
            logAction($pdo, $userId, "getDNSIP Database Update Warning", "Domain: {$domain['domain']}, No rows were affected by UPDATE statement");
        }
        
        // Проверяем что данные действительно обновились
        $verifyStmt = $pdo->prepare("SELECT dns_ip, zone_id FROM cloudflare_accounts WHERE id = ?");
        $verifyStmt->execute([$domainId]);
        $verifyData = $verifyStmt->fetch();
        
        logAction($pdo, $userId, "getDNSIP Verification", "Domain: {$domain['domain']}, Stored DNS IP: {$verifyData['dns_ip']}, Stored Zone ID: {$verifyData['zone_id']}, Expected DNS IP: $dnsIp, Expected Zone ID: $zoneId");
        
        // Убеждаемся что транзакция зафиксирована
        if ($pdo->inTransaction()) {
            $pdo->commit();
            logAction($pdo, $userId, "getDNSIP Transaction Committed", "Domain: {$domain['domain']}");
        }
        
        logAction($pdo, $userId, "DNS IP Updated", "Domain: {$domain['domain']}, IP: $dnsIp");
        
        return [
            'success' => true,
            'dns_ip' => $dnsIp,
            'all_ips' => $allIPs,
            'zone_id' => $zoneId,
            'name_servers' => $nsServers
        ];
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "DNS IP Update Error", "Domain ID: $domainId, Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Получение SSL статуса из Cloudflare (ИСПРАВЛЕННАЯ ВЕРСИЯ)
function getSSLStatusFromCloudflare($pdo, $domainId, $userId) {
    try {
        // Логируем начало операции
        logAction($pdo, $userId, "getSSLStatus Started", "Domain ID: $domainId, User ID: $userId");
        
        // Получаем информацию о домене
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain || !$domain['zone_id']) {
            logAction($pdo, $userId, "getSSLStatus Error", "Domain not found or missing Zone ID for ID: $domainId");
            return ['success' => false, 'error' => 'Домен не найден или отсутствует Zone ID'];
        }
        
        logAction($pdo, $userId, "getSSLStatus Domain Found", "Domain: {$domain['domain']}, Zone ID: {$domain['zone_id']}, Current SSL Mode: {$domain['ssl_mode']}, HTTPS: {$domain['always_use_https']}, TLS: {$domain['min_tls_version']}");
        
        $proxies = getProxies($pdo, $userId);
        $zoneId = $domain['zone_id'];
        
        // ИСПРАВЛЕНО: Используем cloudflareApiRequestDetailed для получения точных данных
        
        // Получаем SSL режим
        $sslResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/ssl", 'GET', [], $proxies, $userId);
        if (!$sslResponse['success']) {
            logAction($pdo, $userId, "getSSLStatus Error", "Failed to get SSL settings for domain: {$domain['domain']}, HTTP: {$sslResponse['http_code']}, Errors: " . json_encode($sslResponse['api_errors']));
            return ['success' => false, 'error' => 'Не удалось получить SSL настройки'];
        }
        
        $sslData = $sslResponse['data'] ?? null;
        $sslMode = 'unknown';
        if (is_object($sslData)) {
            $sslVars = get_object_vars($sslData);
            if (isset($sslVars['value'])) {
                $sslMode = $sslVars['value'] ?? 'unknown';
            }
        }
        logAction($pdo, $userId, "getSSLStatus SSL Mode", "Domain: {$domain['domain']}, SSL Mode: $sslMode");
        
        // Получаем дополнительные SSL настройки
        $httpsResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/always_use_https", 'GET', [], $proxies, $userId);
        $alwaysHttps = 0;
        if ($httpsResponse['success'] && isset($httpsResponse['data']) && is_object($httpsResponse['data'])) {
            $httpsVars = get_object_vars($httpsResponse['data']);
            if (isset($httpsVars['value'])) {
                $alwaysHttps = ($httpsVars['value'] === 'on') ? 1 : 0;
            }
        }
        logAction($pdo, $userId, "getSSLStatus HTTPS Setting", "Domain: {$domain['domain']}, Always HTTPS: " . ($alwaysHttps ? 'On' : 'Off'));
        
        $tlsResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/min_tls_version", 'GET', [], $proxies, $userId);
        $minTls = '1.0';
        if ($tlsResponse['success'] && isset($tlsResponse['data']) && is_object($tlsResponse['data'])) {
            $tlsVars = get_object_vars($tlsResponse['data']);
            if (isset($tlsVars['value'])) {
                $minTls = $tlsVars['value'] ?? '1.0';
            }
        }
        logAction($pdo, $userId, "getSSLStatus TLS Version", "Domain: {$domain['domain']}, Min TLS: $minTls");
        
        // Обновляем SSL информацию в базе
        $updateStmt = $pdo->prepare("
            UPDATE cloudflare_accounts 
            SET ssl_mode = ?, always_use_https = ?, min_tls_version = ?, ssl_last_check = datetime('now')
            WHERE id = ?
        ");
        $updateResult = $updateStmt->execute([$sslMode, $alwaysHttps, $minTls, $domainId]);
        $rowsAffected = $updateStmt->rowCount();
        
        logAction($pdo, $userId, "getSSLStatus Database Update", "Domain: {$domain['domain']}, Update Success: " . ($updateResult ? 'Yes' : 'No') . ", Rows Affected: $rowsAffected, SSL Mode: $sslMode, HTTPS: $alwaysHttps, TLS: $minTls");
        
        // Проверяем результат обновления
        if (!$updateResult) {
            logAction($pdo, $userId, "getSSLStatus Database Update Failed", "Domain: {$domain['domain']}, Failed to execute UPDATE statement");
            return ['success' => false, 'error' => 'Не удалось обновить SSL статус в базе данных'];
        }
        
        if ($rowsAffected === 0) {
            logAction($pdo, $userId, "getSSLStatus Database Update Warning", "Domain: {$domain['domain']}, No rows were affected by UPDATE statement");
        }
        
        // Проверяем что данные действительно обновились
        $verifyStmt = $pdo->prepare("SELECT ssl_mode, always_use_https, min_tls_version, ssl_last_check FROM cloudflare_accounts WHERE id = ?");
        $verifyStmt->execute([$domainId]);
        $verifyData = $verifyStmt->fetch();
        
        logAction($pdo, $userId, "getSSLStatus Verification", "Domain: {$domain['domain']}, Stored SSL Mode: {$verifyData['ssl_mode']}, Stored HTTPS: {$verifyData['always_use_https']}, Stored TLS: {$verifyData['min_tls_version']}, Last Check: {$verifyData['ssl_last_check']}");
        
        // Убеждаемся что транзакция зафиксирована
        if ($pdo->inTransaction()) {
            $pdo->commit();
            logAction($pdo, $userId, "getSSLStatus Transaction Committed", "Domain: {$domain['domain']}");
        }
        
        logAction($pdo, $userId, "SSL Status Updated", "Domain: {$domain['domain']}, Mode: $sslMode");
        
        return [
            'success' => true,
            'ssl_mode' => $sslMode,
            'always_use_https' => $alwaysHttps,
            'min_tls_version' => $minTls
        ];
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "SSL Status Update Error", "Domain ID: $domainId, Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Проверяет статус домена (HTTP/HTTPS доступность)
 */
function checkDomainStatus($domain, $serverIp = null, $proxies = []) {
    $results = [
        'http' => ['status' => false, 'code' => 0, 'time' => 0],
        'https' => ['status' => false, 'code' => 0, 'time' => 0],
        'overall_status' => 'offline',
        'checked_at' => date('Y-m-d H:i:s')
    ];
    
    $urls = [
        'http' => "http://$domain",
        'https' => "https://$domain"
    ];
    
    foreach ($urls as $protocol => $url) {
        $startTime = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            CURLOPT_NOBODY => true, // HEAD запрос
            CURLOPT_HEADER => true
        ]);
        
        // Используем прокси если доступны - ИСПРАВЛЕННЫЙ КОД
        if (!empty($proxies)) {
            $proxy = getRandomProxy($proxies);
            if ($proxy && preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
                $proxyIp = $matches[1];
                $proxyPort = $matches[2];
                $proxyLogin = $matches[3];
                $proxyPass = $matches[4];
                
                curl_setopt($ch, CURLOPT_PROXY, "$proxyIp:$proxyPort");
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyLogin:$proxyPass");
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $endTime = microtime(true);
        $responseTime = ($endTime - $startTime) * 1000; // в миллисекундах
        
        $results[$protocol] = [
            'status' => ($httpCode >= 200 && $httpCode < 400),
            'code' => $httpCode,
            'time' => round($responseTime, 2),
            'error' => $error
        ];
    }
    
    // Определяем общий статус
    if ($results['https']['status']) {
        $results['overall_status'] = 'online_https';
    } elseif ($results['http']['status']) {
        $results['overall_status'] = 'online_http';
    } else {
        $results['overall_status'] = 'offline';
    }
    
    return $results;
}

// Обновление настроек Cloudflare
function updateCloudflareSettings($pdo, $domainId, $email, $apiKey, $domain, $settings, $proxies) {
    try {
        $stmt = $pdo->prepare("SELECT user_id, zone_id FROM cloudflare_accounts WHERE id = ?");
        $stmt->execute([$domainId]);
        $domainInfo = $stmt->fetch();
        
        if (!$domainInfo) {
            return false;
        }
        
        $userId = $domainInfo['user_id'];
        $zoneId = $domainInfo['zone_id'];
        
        // Если zone_id отсутствует, получаем его
        if (!$zoneId) {
            $zoneResponse = cloudflareApiRequest($pdo, $email, $apiKey, "zones?name=$domain", 'GET', [], $proxies, $userId);
            if (!$zoneResponse || empty($zoneResponse->result)) {
                return false;
            }
            $zoneId = $zoneResponse->result[0]->id;
            
            // Обновляем zone_id в базе
            $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?");
            $stmt->execute([$zoneId, $domainId]);
        }
        
        $updateData = [];
        
        // Обновление Always Use HTTPS
        if (isset($settings['always_use_https'])) {
            $value = $settings['always_use_https'] ? 'on' : 'off';
            $result = cloudflareApiRequest($pdo, $email, $apiKey, "zones/$zoneId/settings/always_use_https", 'PATCH', ['value' => $value], $proxies, $userId);
            if ($result) {
                $updateData['always_use_https'] = $settings['always_use_https'] ? 1 : 0;
            }
        }
        
        // Обновление минимальной версии TLS
        if (isset($settings['min_tls_version'])) {
            $result = cloudflareApiRequest($pdo, $email, $apiKey, "zones/$zoneId/settings/min_tls_version", 'PATCH', ['value' => $settings['min_tls_version']], $proxies, $userId);
            if ($result) {
                $updateData['min_tls_version'] = $settings['min_tls_version'];
            }
        }
        
        // Обновление режима SSL
        if (isset($settings['ssl_mode'])) {
            $result = cloudflareApiRequest($pdo, $email, $apiKey, "zones/$zoneId/settings/ssl", 'PATCH', ['value' => $settings['ssl_mode']], $proxies, $userId);
            if ($result) {
                $updateData['ssl_mode'] = $settings['ssl_mode'];
            }
        }
        
        // Обновление DNS IP
        if (isset($settings['new_ip'])) {
            $newIp = $settings['new_ip'];
            $dnsResponse = cloudflareApiRequest($pdo, $email, $apiKey, "zones/$zoneId/dns_records?type=A", 'GET', [], $proxies, $userId);
            
            if ($dnsResponse && !empty($dnsResponse->result)) {
                $updatedCount = 0;
                foreach ($dnsResponse->result as $record) {
                    if ($record->type === 'A' && $record->content !== $newIp) {
                        $updateResult = cloudflareApiRequest($pdo, $email, $apiKey, "zones/$zoneId/dns_records/{$record->id}", 'PATCH', [
                            'content' => $newIp,
                            'name' => $record->name,
                            'type' => 'A',
                            'ttl' => $record->ttl ?? 1,
                            'proxied' => $record->proxied ?? false
                        ], $proxies, $userId);
                        
                        if ($updateResult) {
                            $updatedCount++;
                        }
                    }
                }
                
                if ($updatedCount > 0) {
                    $updateData['dns_ip'] = $newIp;
                }
            }
        }
        
        // Обновляем данные в базе
        if ($updateData) {
            $columns = implode(', ', array_map(fn($k) => "$k = ?", array_keys($updateData)));
            $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET $columns WHERE id = ?");
            $stmt->execute([...array_values($updateData), $domainId]);
            
            logAction($pdo, $userId, "Settings Updated", "Domain: $domain, Fields: " . json_encode($updateData));
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        logAction($pdo, $userId ?? null, "Settings Update Error", "Domain: $domain, Error: " . $e->getMessage());
        return false;
    }
}

// Массовое обновление DNS IP
function bulkUpdateDNSIP($pdo, $userId, $limit = 10) {
    try {
        // Добавляем логирование начала операции
        logAction($pdo, $userId, "Bulk DNS IP Update Started", "Limit: $limit, User ID: $userId");
        
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.domain, ca.dns_ip, ca.zone_id
            FROM cloudflare_accounts ca 
            WHERE ca.user_id = ? 
            AND (ca.dns_ip IS NULL OR ca.dns_ip = '' OR ca.zone_id IS NULL OR ca.zone_id = '')
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $domains = $stmt->fetchAll();
        
        // Логируем найденные домены
        logAction($pdo, $userId, "Bulk DNS IP Update Domains Found", "Count: " . count($domains) . ", Domains: " . implode(', ', array_column($domains, 'domain')));
        
        $results = [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($domains as $domain) {
            $results['checked']++;
            
            logAction($pdo, $userId, "Processing Domain DNS", "Domain: {$domain['domain']}, ID: {$domain['id']}, Current DNS IP: {$domain['dns_ip']}, Zone ID: {$domain['zone_id']}");
            
            $dnsResult = getDNSIPFromCloudflare($pdo, $domain['id'], $userId);
            
            // Детальное логирование результата
            logAction($pdo, $userId, "DNS IP Result", "Domain: {$domain['domain']}, Success: " . ($dnsResult['success'] ? 'Yes' : 'No') . ", Result: " . json_encode($dnsResult));
            
            if ($dnsResult['success']) {
                $results['updated']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'dns_ip' => $dnsResult['dns_ip'],
                    'status' => 'updated'
                ];
                
                // Проверяем что данные действительно обновились в базе
                $checkStmt = $pdo->prepare("SELECT dns_ip, zone_id FROM cloudflare_accounts WHERE id = ?");
                $checkStmt->execute([$domain['id']]);
                $updatedData = $checkStmt->fetch();
                
                logAction($pdo, $userId, "DNS IP Database Verification", "Domain: {$domain['domain']}, ID: {$domain['id']}, Updated DNS IP: {$updatedData['dns_ip']}, Updated Zone ID: {$updatedData['zone_id']}");
                
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'status' => 'failed',
                    'error' => $dnsResult['error']
                ];
            }
            
            usleep(500000); // 500ms задержка
        }
        
        logAction($pdo, $userId, "Bulk DNS IP Update", "Checked: {$results['checked']}, Updated: {$results['updated']}, Failed: {$results['failed']}");
        
        $results['success'] = true;
        return $results;
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Bulk DNS IP Update Error", "Error: " . $e->getMessage());
        return [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => [],
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Массовая проверка SSL статуса
 */
function bulkCheckSSLStatus($pdo, $userId, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.domain 
            FROM cloudflare_accounts ca 
            WHERE ca.user_id = ? 
            AND (ca.last_check IS NULL OR ca.last_check < datetime('now', '-2 hours'))
            ORDER BY ca.last_check ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $domains = $stmt->fetchAll();
        
        $results = [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($domains as $domain) {
            $results['checked']++;
            
            $sslResult = getSSLStatusFromCloudflare($pdo, $domain['id'], $userId);
            
            if ($sslResult['success']) {
                $results['updated']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'ssl_status' => $sslResult['ssl_mode'],
                    'result' => 'updated'
                ];
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'result' => 'failed',
                    'error' => $sslResult['error']
                ];
            }
            
            usleep(500000); // 500ms задержка
        }
        
        logAction($pdo, $userId, "Bulk SSL Status Check", "Checked: {$results['checked']}, Updated: {$results['updated']}, Failed: {$results['failed']}");
        
        $results['success'] = true;
        return $results;
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Bulk SSL Status Check Error", "Error: " . $e->getMessage());
        return [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => [],
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Получение информации о сертификатах домена из Cloudflare
 */
function getCertificatesFromCloudflare($pdo, $domainId, $userId) {
    try {
        // Получаем информацию о домене
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain) {
            return ['success' => false, 'error' => 'Домен не найден'];
        }
        
        if (!$domain['zone_id']) {
            return ['success' => false, 'error' => 'Zone ID не найден для домена'];
        }
        
        $proxies = getProxies($pdo, $userId);
        $zoneId = $domain['zone_id'];
        
        // Получаем все сертификаты для зоны
        $certResponse = cloudflareApiRequest(
            $pdo, 
            $domain['email'], 
            $domain['api_key'], 
            "zones/$zoneId/ssl/certificate_packs?status=all", 
            'GET', 
            [], 
            $proxies, 
            $userId
        );
        
        if (!$certResponse) {
            return ['success' => false, 'error' => 'Не удалось получить сертификаты'];
        }
        
        $certificates = [];
        if (isset($certResponse->result) && is_array($certResponse->result)) {
            foreach ($certResponse->result as $cert) {
                $certificates[] = [
                    'id' => $cert->id,
                    'type' => $cert->type ?? 'unknown',
                    'hosts' => $cert->hosts ?? [],
                    'status' => $cert->status ?? 'unknown',
                    'validation_method' => $cert->validation_method ?? 'unknown',
                    'validity_days' => $cert->validity_days ?? 0,
                    'created_on' => $cert->created_on ?? null,
                    'expires_on' => $cert->expires_on ?? null,
                    'certificate_authority' => $cert->certificate_authority ?? 'unknown'
                ];
            }
        }
        
        // Получаем пользовательские сертификаты
        $customCertResponse = cloudflareApiRequest(
            $pdo, 
            $domain['email'], 
            $domain['api_key'], 
            "zones/$zoneId/custom_certificates", 
            'GET', 
            [], 
            $proxies, 
            $userId
        );
        
        $customCertificates = [];
        if ($customCertResponse && isset($customCertResponse->result) && is_array($customCertResponse->result)) {
            foreach ($customCertResponse->result as $cert) {
                $customCertificates[] = [
                    'id' => $cert->id,
                    'hosts' => $cert->hosts ?? [],
                    'status' => $cert->status ?? 'unknown',
                    'expires_on' => $cert->expires_on ?? null,
                    'uploaded_on' => $cert->uploaded_on ?? null,
                    'issuer' => $cert->issuer ?? 'unknown'
                ];
            }
        }
        
        // НОВАЯ ЛОГИКА: Анализируем и сохраняем результаты в базу данных
        $totalCount = count($certificates) + count($customCertificates);
        $hasActive = false;
        $expiresSoon = false;
        $nearestExpiry = null;
        $types = [];
        
        // Анализируем Certificate Packs
        foreach ($certificates as $cert) {
            if ($cert['status'] === 'active') {
                $hasActive = true;
            }
            
            // Собираем типы сертификатов
            $types[] = $cert['type'];
            
            // Проверяем срок действия (если есть)
            if ($cert['expires_on']) {
                $expiryTime = strtotime($cert['expires_on']);
                if ($expiryTime) {
                    // Проверяем истекает ли в ближайшие 30 дней
                    $thirtyDaysFromNow = time() + (30 * 24 * 60 * 60);
                    if ($expiryTime <= $thirtyDaysFromNow) {
                        $expiresSoon = true;
                    }
                    
                    // Находим ближайшую дату истечения
                    if (!$nearestExpiry || $expiryTime < strtotime($nearestExpiry)) {
                        $nearestExpiry = $cert['expires_on'];
                    }
                }
            }
        }
        
        // Анализируем Custom Certificates
        foreach ($customCertificates as $cert) {
            if ($cert['status'] === 'active') {
                $hasActive = true;
            }
            
            $types[] = 'custom';
            
            if ($cert['expires_on']) {
                $expiryTime = strtotime($cert['expires_on']);
                if ($expiryTime) {
                    $thirtyDaysFromNow = time() + (30 * 24 * 60 * 60);
                    if ($expiryTime <= $thirtyDaysFromNow) {
                        $expiresSoon = true;
                    }
                    
                    if (!$nearestExpiry || $expiryTime < strtotime($nearestExpiry)) {
                        $nearestExpiry = $cert['expires_on'];
                    }
                }
            }
        }
        
        // Создаем строку типов сертификатов
        $typesString = !empty($types) ? implode(',', array_unique($types)) : null;
        
        // СОХРАНЯЕМ РЕЗУЛЬТАТЫ В БАЗУ ДАННЫХ
        $updateStmt = $pdo->prepare("
            UPDATE cloudflare_accounts 
            SET ssl_certificates_count = ?,
                ssl_has_active = ?,
                ssl_expires_soon = ?,
                ssl_nearest_expiry = ?,
                ssl_types = ?,
                ssl_status_check = datetime('now')
            WHERE id = ?
        ");
        
        $updateResult = $updateStmt->execute([
            $totalCount,
            $hasActive ? 1 : 0,
            $expiresSoon ? 1 : 0,
            $nearestExpiry,
            $typesString,
            $domainId
        ]);
        
        if (!$updateResult) {
            logAction($pdo, $userId, "Certificates Save Failed", "Domain: {$domain['domain']}, Error: Database update failed");
        } else {
            logAction($pdo, $userId, "Certificates Saved", 
                "Domain: {$domain['domain']}, Count: $totalCount, Active: " . ($hasActive ? 'Yes' : 'No') . 
                ", Expires Soon: " . ($expiresSoon ? 'Yes' : 'No') . ", Types: " . ($typesString ?? 'None'));
        }
        
        logAction($pdo, $userId, "Certificates Retrieved", "Domain: {$domain['domain']}, Count: " . $totalCount);
        
        return [
            'success' => true,
            'domain' => $domain['domain'],
            'certificates' => $certificates,
            'custom_certificates' => $customCertificates,
            'total_count' => $totalCount,
            'has_active' => $hasActive,
            'expires_soon' => $expiresSoon,
            'nearest_expiry' => $nearestExpiry,
            'types' => $typesString
        ];
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Get Certificates Error", "Domain ID: $domainId, Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Создание Origin CA сертификата через Cloudflare API
 */
function createOriginCertificate($pdo, $domainId, $userId, $validity = 365) {
    try {
        // Получаем информацию о домене
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain) {
            return ['success' => false, 'error' => 'Домен не найден'];
        }
        
        logAction($pdo, $userId, "Starting Certificate Creation", "Domain: {$domain['domain']}, Validity: {$validity} days");
        
        $proxies = getProxies($pdo, $userId);
        
        // Создаем Origin CA сертификат через правильный API эндпоинт
        // Cloudflare требует CSR для данного типа аккаунта
        
        // Генерируем приватный ключ и CSR
        $privateKeyConfig = [
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];
        
        $privateKeyResource = openssl_pkey_new($privateKeyConfig);
        if (!$privateKeyResource) {
            $errorMsg = 'Не удалось создать приватный ключ: ' . openssl_error_string();
            logAction($pdo, $userId, "Private Key Generation Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Создаем CSR
        $csrConfig = [
            "commonName" => $domain['domain'],
            "countryName" => "US",
            "stateOrProvinceName" => "CA", 
            "localityName" => "San Francisco",
            "organizationName" => "Example Corp",
            "organizationalUnitName" => "IT Department",
            "emailAddress" => $domain['email']
        ];
        
        // Добавляем SAN (Subject Alternative Names) для wildcard
        $altNames = "DNS:" . $domain['domain'] . ",DNS:*." . $domain['domain'];
        
        $csrResource = openssl_csr_new($csrConfig, $privateKeyResource, [
            "digest_alg" => "sha256",
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA
        ]);
        
        if (!$csrResource) {
            $errorMsg = 'Не удалось создать CSR: ' . openssl_error_string();
            logAction($pdo, $userId, "CSR Generation Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Экспортируем CSR в PEM формат
        $csrPem = '';
        if (!openssl_csr_export($csrResource, $csrPem)) {
            $errorMsg = 'Не удалось экспортировать CSR: ' . openssl_error_string();
            logAction($pdo, $userId, "CSR Export Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Экспортируем приватный ключ в PEM формат
        $privateKeyPem = '';
        if (!openssl_pkey_export($privateKeyResource, $privateKeyPem)) {
            $errorMsg = 'Не удалось экспортировать приватный ключ: ' . openssl_error_string();
            logAction($pdo, $userId, "Private Key Export Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        logAction($pdo, $userId, "CSR and Private Key Generated", 
            "Domain: {$domain['domain']}, CSR Length: " . strlen($csrPem) . " chars, Key Length: " . strlen($privateKeyPem) . " chars");
        
        $certData = [
            'hostnames' => [$domain['domain'], "*." . $domain['domain']],
            'requested_validity' => $validity,
            'request_type' => 'origin-rsa',
            'csr' => $csrPem  // Добавляем сгенерированный CSR
        ];
        
        logAction($pdo, $userId, "Sending Certificate Request to Cloudflare", 
            "Domain: {$domain['domain']}, Hostnames: " . implode(', ', $certData['hostnames']) . ", API Endpoint: certificates");
        
        // Для Origin CA API нужен специальный запрос с прямой авторизацией
        $endpointUrl = "https://api.cloudflare.com/client/v4/certificates";
        $ch = curl_init($endpointUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Auth-Email: {$domain['email']}",
            "X-Auth-Key: {$domain['api_key']}",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($certData));
        
        // Настройка прокси если доступны
        if ($proxies) {
            $proxy = getRandomProxy($proxies);
            if ($proxy && preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
                $proxyIp = $matches[1];
                $proxyPort = $matches[2];
                $proxyLogin = $matches[3];
                $proxyPass = $matches[4];
                
                curl_setopt($ch, CURLOPT_PROXY, "$proxyIp:$proxyPort");
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyLogin:$proxyPass");
                curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
                
                if ($userId) {
                    logAction($pdo, $userId, "Using Proxy", "Proxy: $proxyIp:$proxyPort, Endpoint: $endpointUrl");
                }
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($curlError)) {
            $errorMsg = "cURL Error: $curlError";
            logAction($pdo, $userId, "Certificate API Request Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }

        logAction($pdo, $userId, "Cloudflare API Response", 
            "Domain: {$domain['domain']}, HTTP Code: $httpCode, Response length: " . strlen($response) . " chars");

        if ($httpCode !== 200) {
            logAction($pdo, $userId, "Certificate API Request Failed", "Domain: {$domain['domain']}, HTTP Code: $httpCode, Response: " . substr($response, 0, 500));
            return ['success' => false, 'error' => "HTTP Error $httpCode: " . substr($response, 0, 200)];
        }

        $certResponse = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errorMsg = 'JSON Error: ' . json_last_error_msg();
            logAction($pdo, $userId, "Certificate JSON Parse Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Логируем полный ответ для отладки
        if ($certResponse) {
            logAction($pdo, $userId, "Cloudflare API Response Received", 
                "Domain: {$domain['domain']}, Success: " . ($certResponse->success ?? 'unknown') . ", Response keys: " . implode(', ', array_keys((array)$certResponse)));
            
            if (isset($certResponse->result)) {
                $resultKeys = array_keys((array)$certResponse->result);
                logAction($pdo, $userId, "Certificate Result Keys", 
                    "Domain: {$domain['domain']}, Result keys: " . implode(', ', $resultKeys));
            }
        }
        
        if (!isset($certResponse->success) || !$certResponse->success) {
            $errorMsg = 'Cloudflare API returned error';
            if (isset($certResponse->errors) && is_array($certResponse->errors)) {
                $errors = array_map(function($err) { 
                    return $err->message ?? 'Unknown error'; 
                }, $certResponse->errors);
                $errorMsg .= ': ' . implode(', ', $errors);
            }
            if (isset($certResponse->messages) && is_array($certResponse->messages)) {
                $messages = array_map(function($msg) { 
                    return $msg->message ?? 'Unknown message'; 
                }, $certResponse->messages);
                if (!empty($messages)) {
                    $errorMsg .= ' Messages: ' . implode(', ', $messages);
                }
            }
            logAction($pdo, $userId, "Certificate Creation Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        if (!isset($certResponse->result)) {
            $errorMsg = 'No result in API response';
            logAction($pdo, $userId, "Certificate Creation Failed", "Domain: {$domain['domain']}, Error: $errorMsg");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        $result = $certResponse->result;
        $certificate = $result->certificate ?? null;
        $certId = $result->id ?? null;
        $expiresOn = $result->expires_on ?? null;
        
        // Для Origin CA с CSR используем наш сгенерированный приватный ключ
        // а не тот что приходит от API (так как мы создаем CSR локально)
        $privateKeyToSave = $privateKeyPem;
        
        // Логируем все поля результата для отладки
        $resultFields = [];
        foreach ((array)$result as $key => $value) {
            if (is_string($value)) {
                $resultFields[] = "$key: " . (strlen($value) > 50 ? substr($value, 0, 50) . '... (' . strlen($value) . ' chars)' : $value);
            } else {
                $resultFields[] = "$key: " . gettype($value);
            }
        }
        logAction($pdo, $userId, "Certificate Result Fields", 
            "Domain: {$domain['domain']}, Fields: " . implode(', ', $resultFields));
        
        // Проверяем что все данные получены
        if (!$certificate || !$privateKeyToSave || !$certId) {
            $errorMsg = 'Неполные данные сертификата от Cloudflare API или локальной генерации';
            logAction($pdo, $userId, "Incomplete Certificate Data", 
                "Domain: {$domain['domain']}, Has Cert: " . ($certificate ? 'Yes (' . strlen($certificate) . ' chars)' : 'No') . 
                ", Has Key: " . ($privateKeyToSave ? 'Yes (' . strlen($privateKeyToSave) . ' chars)' : 'No') . 
                ", Has ID: " . ($certId ? 'Yes (' . $certId . ')' : 'No'));
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Проверяем формат сертификата и ключа
        $certValid = strpos($certificate, '-----BEGIN CERTIFICATE-----') !== false;
        $keyValid = (strpos($privateKeyToSave, '-----BEGIN PRIVATE KEY-----') !== false || 
                     strpos($privateKeyToSave, '-----BEGIN RSA PRIVATE KEY-----') !== false);
        
        if (!$certValid || !$keyValid) {
            $errorMsg = 'Неверный формат сертификата или ключа';
            logAction($pdo, $userId, "Invalid Certificate Format", 
                "Domain: {$domain['domain']}, Cert Valid: " . ($certValid ? 'Yes' : 'No') . 
                ", Key Valid: " . ($keyValid ? 'Yes' : 'No'));
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Логируем размеры полученных данных
        $certLength = strlen($certificate);
        $keyLength = strlen($privateKeyToSave);
        logAction($pdo, $userId, "Certificate Data Received", 
            "Domain: {$domain['domain']}, Cert ID: $certId, Certificate Length: {$certLength} chars, Private Key Length: {$keyLength} chars");
        
        // Сохраняем сертификат в базе данных
        $stmt = $pdo->prepare("
            UPDATE cloudflare_accounts 
            SET ssl_certificate = ?, ssl_private_key = ?, ssl_cert_id = ?, ssl_cert_created = datetime('now')
            WHERE id = ?
        ");
        $saveResult = $stmt->execute([$certificate, $privateKeyToSave, $certId, $domainId]);
        
        if (!$saveResult) {
            $errorMsg = 'Не удалось сохранить сертификат в базу данных';
            logAction($pdo, $userId, "Certificate Save Failed", "Domain: {$domain['domain']}, Error: Database update failed");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        $affectedRows = $stmt->rowCount();
        if ($affectedRows === 0) {
            $errorMsg = 'Не удалось обновить запись домена с сертификатом';
            logAction($pdo, $userId, "Certificate Save Warning", "Domain: {$domain['domain']}, Warning: No rows affected");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        // Проверяем что сертификат действительно сохранен
        $checkStmt = $pdo->prepare("
            SELECT ssl_certificate, ssl_private_key, ssl_cert_id 
            FROM cloudflare_accounts 
            WHERE id = ? AND ssl_cert_id = ?
        ");
        $checkStmt->execute([$domainId, $certId]);
        $savedData = $checkStmt->fetch();
        
        if (!$savedData || !$savedData['ssl_certificate'] || !$savedData['ssl_private_key']) {
            $errorMsg = 'Сертификат не найден в базе данных после сохранения';
            logAction($pdo, $userId, "Certificate Verification Failed", "Domain: {$domain['domain']}, Error: Not found after save");
            return ['success' => false, 'error' => $errorMsg];
        }
        
        logAction($pdo, $userId, "Origin Certificate Created Successfully", 
            "Domain: {$domain['domain']}, Cert ID: $certId, Saved: {$affectedRows} rows, Expires: $expiresOn, Cert Length: {$certLength}, Key Length: {$keyLength}");
        
        return [
            'success' => true,
            'domain' => $domain['domain'],
            'certificate_id' => $certId,
            'certificate' => $certificate,
            'private_key' => $privateKeyToSave,
            'expires_on' => $expiresOn,
            'certificate_length' => $certLength,
            'private_key_length' => $keyLength
        ];
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        logAction($pdo, $userId, "Create Certificate Exception", "Domain ID: $domainId, Error: $errorMsg");
        return ['success' => false, 'error' => $errorMsg];
    }
}

/**
 * Заказ Universal SSL сертификата через Cloudflare API
 */
function orderUniversalSSL($pdo, $domainId, $userId) {
    try {
        // Получаем информацию о домене
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain) {
            return ['success' => false, 'error' => 'Домен не найден'];
        }
        
        if (!$domain['zone_id']) {
            return ['success' => false, 'error' => 'Zone ID не найден для домена'];
        }
        
        $proxies = getProxies($pdo, $userId);
        $zoneId = $domain['zone_id'];
        
        // Заказываем Universal SSL сертификат
        $certData = [
            'type' => 'universal',
            'hosts' => [$domain['domain'], "*." . $domain['domain']],
            'validation_method' => 'txt',
            'validity_days' => 90
        ];
        
        $certResponse = cloudflareApiRequestDetailed(
            $pdo,
            $domain['email'],
            $domain['api_key'],
            "zones/$zoneId/ssl/certificate_packs/order",
            'POST',
            $certData,
            $proxies,
            $userId
        );

        if (!$certResponse || empty($certResponse['success'])) {
            $errorMessage = 'Не удалось заказать Universal SSL сертификат';
            if (!empty($certResponse['api_errors'])) {
                $firstError = $certResponse['api_errors'][0];
                $errorMessage .= ': ' . ($firstError['message'] ?? 'Неизвестная ошибка');
            }
            return ['success' => false, 'error' => $errorMessage];
        }

        $result = $certResponse['data'] ?? null;
        if (is_array($result)) {
            $result = reset($result);
        }

        if (!is_object($result)) {
            return ['success' => false, 'error' => 'Ответ Cloudflare не содержит данных о сертификате'];
        }

        $certId = $result->id ?? null;
        $status = $result->status ?? 'unknown';
        $validationRecords = $result->validation_records ?? [];

        if (!$certId) {
            return ['success' => false, 'error' => 'Cloudflare не вернул идентификатор сертификата'];
        }
        
        logAction($pdo, $userId, "Universal SSL Ordered", "Domain: {$domain['domain']}, Cert ID: $certId, Status: $status");
        
        return [
            'success' => true,
            'domain' => $domain['domain'],
            'certificate_id' => $certId,
            'status' => $status,
            'validation_records' => $validationRecords,
            'type' => 'universal'
        ];
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Order Universal SSL Error", "Domain ID: $domainId, Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Массовое создание сертификатов для всех доменов пользователя
 */
function bulkCreateCertificates($pdo, $userId, $certificateType = 'origin', $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.domain 
            FROM cloudflare_accounts ca 
            WHERE ca.user_id = ? 
            AND (ca.ssl_cert_id IS NULL OR ca.ssl_cert_id = '')
            ORDER BY ca.domain ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $domains = $stmt->fetchAll();
        
        $results = [
            'processed' => 0,
            'created' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($domains as $domain) {
            $results['processed']++;
            
            if ($certificateType === 'origin') {
                $certResult = createOriginCertificate($pdo, $domain['id'], $userId);
            } else {
                $certResult = orderUniversalSSL($pdo, $domain['id'], $userId);
            }
            
            if ($certResult['success']) {
                $results['created']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'certificate_id' => $certResult['certificate_id'],
                    'status' => 'created',
                    'type' => $certificateType
                ];
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'status' => 'failed',
                    'error' => $certResult['error']
                ];
            }
            
            // Задержка между запросами
            usleep(1000000); // 1 секунда
        }
        
        logAction($pdo, $userId, "Bulk Certificate Creation", "Type: $certificateType, Processed: {$results['processed']}, Created: {$results['created']}, Failed: {$results['failed']}");
        
        return $results;
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Bulk Certificate Creation Error", "Error: " . $e->getMessage());
        return [
            'processed' => 0,
            'created' => 0,
            'failed' => 0,
            'details' => [],
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Получение статуса всех сертификатов пользователя
 */
function getAllCertificatesStatus($pdo, $userId, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.domain, ca.ssl_cert_id, ca.ssl_cert_created
            FROM cloudflare_accounts ca 
            WHERE ca.user_id = ? 
            AND ca.zone_id IS NOT NULL
            ORDER BY ca.ssl_cert_created DESC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $domains = $stmt->fetchAll();
        
        $results = [
            'checked' => 0,
            'updated' => 0,
            'certificates' => []
        ];
        
        foreach ($domains as $domain) {
            $results['checked']++;
            
            $certInfo = getCertificatesFromCloudflare($pdo, $domain['id'], $userId);
            
            if ($certInfo['success']) {
                $certificates = $certInfo['certificates'] ?? [];
                $customCerts = $certInfo['custom_certificates'] ?? [];
                $totalCount = count($certificates) + count($customCerts);
                
                // Обновляем информацию о сертификатах в главной таблице
                $hasActiveCerts = false;
                $nearestExpiry = null;
                $certTypes = [];
                
                // Анализируем обычные сертификаты
                foreach ($certificates as $cert) {
                    if (isset($cert['status']) && $cert['status'] === 'active') {
                        $hasActiveCerts = true;
                        if (isset($cert['expires_on'])) {
                            $expiryTime = strtotime($cert['expires_on']);
                            if (!$nearestExpiry || $expiryTime < $nearestExpiry) {
                                $nearestExpiry = $expiryTime;
                            }
                        }
                    }
                    if (isset($cert['type'])) {
                        $certTypes[] = $cert['type'];
                    }
                }
                
                // Анализируем пользовательские сертификаты (включая Origin CA)
                foreach ($customCerts as $cert) {
                    if (isset($cert['status']) && $cert['status'] === 'active') {
                        $hasActiveCerts = true;
                        if (isset($cert['expires_on'])) {
                            $expiryTime = strtotime($cert['expires_on']);
                            if (!$nearestExpiry || $expiryTime < $nearestExpiry) {
                                $nearestExpiry = $expiryTime;
                            }
                        }
                    }
                    if (isset($cert['type'])) {
                        $certTypes[] = $cert['type'];
                    }
                }
                
                // Проверяем истекают ли сертификаты в ближайшие 30 дней
                $expiringSoon = false;
                if ($nearestExpiry) {
                    $daysToExpiry = ($nearestExpiry - time()) / (24 * 3600);
                    $expiringSoon = $daysToExpiry <= 30;
                }
                
                // Обновляем данные в базе
                $updateStmt = $pdo->prepare("
                    UPDATE cloudflare_accounts 
                    SET 
                        ssl_certificates_count = ?,
                        ssl_status_check = datetime('now'),
                        ssl_has_active = ?,
                        ssl_expires_soon = ?,
                        ssl_nearest_expiry = ?,
                        ssl_types = ?
                    WHERE id = ? AND user_id = ?
                ");
                
                $updateResult = $updateStmt->execute([
                    $totalCount,
                    $hasActiveCerts ? 1 : 0,
                    $expiringSoon ? 1 : 0,
                    $nearestExpiry ? date('Y-m-d H:i:s', $nearestExpiry) : null,
                    implode(',', array_unique($certTypes)),
                    $domain['id'],
                    $userId
                ]);
                
                if ($updateResult && $updateStmt->rowCount() > 0) {
                    $results['updated']++;
                }
                
                $results['certificates'][] = [
                    'domain' => $domain['domain'],
                    'certificates' => $certificates,
                    'custom_certificates' => $customCerts,
                    'total_count' => $totalCount,
                    'has_active' => $hasActiveCerts,
                    'expires_soon' => $expiringSoon,
                    'nearest_expiry' => $nearestExpiry ? date('Y-m-d H:i:s', $nearestExpiry) : null
                ];
            } else {
                $results['certificates'][] = [
                    'domain' => $domain['domain'],
                    'error' => $certInfo['error'] ?? 'Неизвестная ошибка',
                    'total_count' => 0
                ];
            }
            
            // Задержка между запросами
            usleep(500000); // 500ms
        }
        
        logAction($pdo, $userId, "All Certificates Status Check", "Checked: {$results['checked']} domains, Updated: {$results['updated']} records");
        
        return [
            'success' => true,
            'checked' => $results['checked'],
            'updated' => $results['updated'],
            'certificates' => $results['certificates']
        ];
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "All Certificates Status Error", "Error: " . $e->getMessage());
        return [
            'success' => false,
            'checked' => 0,
            'updated' => 0,
            'certificates' => [],
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Получает NS серверы и статус домена из Cloudflare (улучшенная версия)
 */
function getDomainStatusAndNameservers($pdo, $domainId, $userId) {
    try {
        // Получаем информацию о домене
        $stmt = $pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain) {
            return ['success' => false, 'error' => 'Домен не найден'];
        }
        
        logAction($pdo, $userId, "Getting Domain Status and NS", "Domain: {$domain['domain']}, Current NS: {$domain['ns_records']}");
        
        $proxies = getProxies($pdo, $userId);
        
        // Получаем зону и её статус
        $zoneResponse = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones?name={$domain['domain']}", 'GET', [], $proxies, $userId);
        if (!$zoneResponse || empty($zoneResponse->result)) {
            logAction($pdo, $userId, "Zone not found", "Domain: {$domain['domain']}");
            return ['success' => false, 'error' => 'Зона не найдена в Cloudflare'];
        }
        
        $zone = $zoneResponse->result[0];
        $zoneId = $zone->id;
        $nameServers = $zone->name_servers ?? [];
        $status = $zone->status ?? 'unknown';
        
        logAction($pdo, $userId, "Zone Found", "Domain: {$domain['domain']}, Zone ID: $zoneId, NS Count: " . count($nameServers) . ", Status: $status");
        
        // Проверяем статус домена (HTTP/HTTPS)
        $domainStatus = checkDomainStatus($domain['domain'], $domain['server_ip'], $proxies);
        
        // Обновляем информацию в базе
        $stmt = $pdo->prepare("
            UPDATE cloudflare_accounts 
            SET zone_id = ?, ns_records = ?, domain_status = ?, last_check = ?, response_time = ?
            WHERE id = ?
        ");
        
        $nsRecordsJson = json_encode($nameServers);
        $avgTime = ($domainStatus['http']['time'] + $domainStatus['https']['time']) / 2;
        
        $updateResult = $stmt->execute([
            $zoneId,
            $nsRecordsJson,
            $domainStatus['overall_status'],
            $domainStatus['checked_at'],
            $avgTime,
            $domainId
        ]);
        
        logAction($pdo, $userId, "Domain Status Updated", "Domain: {$domain['domain']}, Status: {$domainStatus['overall_status']}, Zone Status: $status, NS Updated: " . ($updateResult ? 'Yes' : 'No') . ", NS JSON: $nsRecordsJson");
        
        return [
            'success' => true,
            'zone_id' => $zoneId,
            'zone_status' => $status,
            'name_servers' => $nameServers,
            'domain_status' => $domainStatus['overall_status'],
            'http_status' => $domainStatus['http'],
            'https_status' => $domainStatus['https'],
            'response_time' => $avgTime
        ];
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Domain Status Update Error", "Domain ID: $domainId, Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Массовое обновление статуса доменов и NS серверов
 */
function bulkUpdateDomainStatus($pdo, $userId, $limit = 10) {
    try {
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.domain 
            FROM cloudflare_accounts ca 
            WHERE ca.user_id = ? 
            AND (ca.last_check IS NULL OR ca.last_check < datetime('now', '-1 hour'))
            ORDER BY ca.last_check ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $domains = $stmt->fetchAll();
        
        $results = [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => []
        ];
    
        foreach ($domains as $domain) {
            $results['checked']++;
            
            $statusResult = getDomainStatusAndNameservers($pdo, $domain['id'], $userId);
            
            if ($statusResult['success']) {
                $results['updated']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'status' => $statusResult['domain_status'],
                    'zone_status' => $statusResult['zone_status'],
                    'nameservers_count' => count($statusResult['name_servers']),
                    'result' => 'updated'
                ];
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'result' => 'failed',
                    'error' => $statusResult['error']
                ];
            }
            
            usleep(1000000); // 1 секунда задержка
        }
        
        logAction($pdo, $userId, "Bulk Domain Status Update", "Checked: {$results['checked']}, Updated: {$results['updated']}, Failed: {$results['failed']}");
        
        $results['success'] = true;
        return $results;
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Bulk Domain Status Update Error", "Error: " . $e->getMessage());
        return [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => [],
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Массовое обновление NS серверов для всех доменов
 */
function updateAllNameservers($pdo, $userId, $limit = 50) {
    try {
        // Получаем домены без NS записей или с пустыми NS записями
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.domain, ca.ns_records
            FROM cloudflare_accounts ca 
            WHERE ca.user_id = ? 
            ORDER BY ca.last_check ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $domains = $stmt->fetchAll();
        
        logAction($pdo, $userId, "NS Update Started", "Found " . count($domains) . " domains to process");
        
        $results = [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => []
        ];
    
        foreach ($domains as $domain) {
            $results['checked']++;
            
            logAction($pdo, $userId, "Processing Domain NS", "Domain: {$domain['domain']}, Current NS: {$domain['ns_records']}");
            
            $statusResult = getDomainStatusAndNameservers($pdo, $domain['id'], $userId);
            
            if ($statusResult['success']) {
                $results['updated']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'status' => $statusResult['domain_status'],
                    'zone_status' => $statusResult['zone_status'],
                    'nameservers_count' => count($statusResult['name_servers']),
                    'nameservers' => $statusResult['name_servers'],
                    'result' => 'updated'
                ];
                
                logAction($pdo, $userId, "NS Updated Successfully", "Domain: {$domain['domain']}, NS Count: " . count($statusResult['name_servers']));
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'domain' => $domain['domain'],
                    'result' => 'failed',
                    'error' => $statusResult['error']
                ];
                
                logAction($pdo, $userId, "NS Update Failed", "Domain: {$domain['domain']}, Error: {$statusResult['error']}");
            }
            
            usleep(500000); // 0.5 секунды задержка
        }
        
        logAction($pdo, $userId, "NS Update Completed", "Checked: {$results['checked']}, Updated: {$results['updated']}, Failed: {$results['failed']}");
        
        $results['success'] = true;
        return $results;
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "NS Update Error", "Error: " . $e->getMessage());
        return [
            'success' => false,
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * МЕГА-ФУНКЦИЯ: Обновляет ВСЁ для всех доменов пользователя
 * Выполняет все возможные массовые операции подряд
 */
function bulkUpdateAll($pdo, $userId, $limit = 20) {
    try {
        logAction($pdo, $userId, "BULK UPDATE ALL STARTED", "Limit: $limit, User: $userId, Starting mega-update of everything!");
        
        $startTime = microtime(true);
        $allResults = [
            'total_processed' => 0,
            'total_updated' => 0,
            'total_failed' => 0,
            'operations' => []
        ];
        
        // 1️⃣ ОБНОВЛЕНИЕ DNS IP
        logAction($pdo, $userId, "BULK ALL: Starting DNS IP Update", "Step 1/4");
        $dnsResult = bulkUpdateDNSIP($pdo, $userId, $limit);
        $allResults['operations']['dns_ip_update'] = $dnsResult;
        $allResults['total_processed'] += ($dnsResult['checked'] ?? 0);
        $allResults['total_updated'] += ($dnsResult['updated'] ?? 0);
        $allResults['total_failed'] += ($dnsResult['failed'] ?? 0);
        
        // Задержка между операциями
        sleep(2);
        
        // 2️⃣ ПРОВЕРКА SSL СТАТУСА
        logAction($pdo, $userId, "BULK ALL: Starting SSL Status Check", "Step 2/4");
        $sslResult = bulkCheckSSLStatus($pdo, $userId, $limit);
        $allResults['operations']['ssl_status_check'] = $sslResult;
        $allResults['total_processed'] += ($sslResult['checked'] ?? 0);
        $allResults['total_updated'] += ($sslResult['updated'] ?? 0);
        $allResults['total_failed'] += ($sslResult['failed'] ?? 0);
        
        // Задержка между операциями
        sleep(2);
        
        // 3️⃣ ОБНОВЛЕНИЕ СТАТУСА ДОМЕНОВ И NS СЕРВЕРОВ
        logAction($pdo, $userId, "BULK ALL: Starting Domain Status Update", "Step 3/4");
        $domainStatusResult = bulkUpdateDomainStatus($pdo, $userId, $limit);
        $allResults['operations']['domain_status_update'] = $domainStatusResult;
        $allResults['total_processed'] += ($domainStatusResult['checked'] ?? 0);
        $allResults['total_updated'] += ($domainStatusResult['updated'] ?? 0);
        $allResults['total_failed'] += ($domainStatusResult['failed'] ?? 0);
        
        // Задержка между операциями
        sleep(2);
        
        // 4️⃣ ПОЛУЧЕНИЕ СТАТУСА СЕРТИФИКАТОВ
        logAction($pdo, $userId, "BULK ALL: Starting Certificates Status Check", "Step 4/4");
        $certificatesResult = getAllCertificatesStatus($pdo, $userId, $limit);
        $allResults['operations']['certificates_status'] = $certificatesResult;
        $allResults['total_processed'] += ($certificatesResult['checked'] ?? 0);
        $allResults['total_updated'] += ($certificatesResult['updated'] ?? 0);
        
        $endTime = microtime(true);
        $totalTime = round($endTime - $startTime, 2);
        
        // Финальная статистика
        $allResults['execution_time'] = $totalTime;
        $allResults['success'] = true;
        $allResults['summary'] = [
            'dns_ip_updated' => ($dnsResult['updated'] ?? 0),
            'ssl_status_checked' => ($sslResult['updated'] ?? 0),
            'domain_status_updated' => ($domainStatusResult['updated'] ?? 0),
            'certificates_checked' => ($certificatesResult['updated'] ?? 0),
            'total_time_seconds' => $totalTime
        ];
        
        logAction($pdo, $userId, "BULK UPDATE ALL COMPLETED", 
            "Total Time: {$totalTime}s, Processed: {$allResults['total_processed']}, " .
            "Updated: {$allResults['total_updated']}, Failed: {$allResults['total_failed']}, " .
            "DNS: {$dnsResult['updated']}, SSL: {$sslResult['updated']}, " .
            "Domains: {$domainStatusResult['updated']}, Certs: {$certificatesResult['updated']}");
        
        return $allResults;
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "BULK UPDATE ALL ERROR", "Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'total_processed' => 0,
            'total_updated' => 0,
            'total_failed' => 0,
            'operations' => []
        ];
    }
}

/**
 * Быстрая синхронизация SSL данных для выбранных доменов
 */
function quickSyncSSLData($pdo, $domainIds, $userId) {
    try {
        if (empty($domainIds)) {
            return ['success' => false, 'error' => 'Не указаны домены для синхронизации'];
        }
        
        logAction($pdo, $userId, "Quick SSL Sync Started", "Domain IDs: " . implode(', ', $domainIds) . ", Count: " . count($domainIds));
        
        $results = [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($domainIds as $domainId) {
            $results['checked']++;
            
            // Получаем информацию о домене
            $stmt = $pdo->prepare("
                SELECT ca.*, cc.email, cc.api_key 
                FROM cloudflare_accounts ca 
                JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
                WHERE ca.id = ? AND ca.user_id = ?
            ");
            $stmt->execute([$domainId, $userId]);
            $domain = $stmt->fetch();
            
            if (!$domain || !$domain['zone_id']) {
                $results['failed']++;
                $results['details'][] = [
                    'domain_id' => $domainId,
                    'domain' => $domain['domain'] ?? 'Unknown',
                    'status' => 'failed',
                    'error' => 'Домен не найден или отсутствует Zone ID'
                ];
                continue;
            }
            
            try {
                $proxies = getProxies($pdo, $userId);
                $zoneId = $domain['zone_id'];
                
                // ИСПРАВЛЕНО: Получаем актуальные SSL настройки из Cloudflare с детальной проверкой
                $sslResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/ssl", 'GET', [], $proxies, $userId);
                $httpsResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/always_use_https", 'GET', [], $proxies, $userId);
                $tlsResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/min_tls_version", 'GET', [], $proxies, $userId);
                
                $sslData = $sslResponse['data'] ?? null;
                $sslMode = $domain['ssl_mode'];
                if ($sslResponse['success'] && is_object($sslData)) {
                    $sslVars = get_object_vars($sslData);
                    if (isset($sslVars['value'])) {
                        $sslMode = $sslVars['value'] ?? $domain['ssl_mode'];
                    }
                }
                $alwaysHttps = 0;
                if ($httpsResponse['success'] && isset($httpsResponse['data']) && is_object($httpsResponse['data'])) {
                    $httpsVars = get_object_vars($httpsResponse['data']);
                    if (isset($httpsVars['value'])) {
                        $alwaysHttps = ($httpsVars['value'] === 'on') ? 1 : 0;
                    }
                } else {
                    $alwaysHttps = $domain['always_use_https'];
                }
                
                $minTls = '1.0';
                if ($tlsResponse['success'] && isset($tlsResponse['data']) && is_object($tlsResponse['data'])) {
                    $tlsVars = get_object_vars($tlsResponse['data']);
                    if (isset($tlsVars['value'])) {
                        $minTls = $tlsVars['value'] ?? '1.0';
                    }
                } else {
                    $minTls = $domain['min_tls_version'];
                }
                
                // Обновляем данные в базе только если они изменились
                $needsUpdate = false;
                $changes = [];
                
                if ($domain['ssl_mode'] !== $sslMode) {
                    $changes[] = "SSL: {$domain['ssl_mode']} → $sslMode";
                    $needsUpdate = true;
                }
                
                if ((int)$domain['always_use_https'] !== $alwaysHttps) {
                    $changes[] = "HTTPS: " . ($domain['always_use_https'] ? 'on' : 'off') . " → " . ($alwaysHttps ? 'on' : 'off');
                    $needsUpdate = true;
                }
                
                if ($domain['min_tls_version'] !== $minTls) {
                    $changes[] = "TLS: {$domain['min_tls_version']} → $minTls";
                    $needsUpdate = true;
                }
                
                if ($needsUpdate) {
                    $updateStmt = $pdo->prepare("
                        UPDATE cloudflare_accounts 
                        SET ssl_mode = ?, always_use_https = ?, min_tls_version = ?, ssl_last_check = datetime('now')
                        WHERE id = ?
                    ");
                    $updateResult = $updateStmt->execute([$sslMode, $alwaysHttps, $minTls, $domainId]);
                    
                    if ($updateResult) {
                        $results['updated']++;
                        $results['details'][] = [
                            'domain_id' => $domainId,
                            'domain' => $domain['domain'],
                            'status' => 'updated',
                            'changes' => implode(', ', $changes),
                            'ssl_mode' => $sslMode,
                            'always_use_https' => $alwaysHttps,
                            'min_tls_version' => $minTls
                        ];
                        
                        logAction($pdo, $userId, "Quick SSL Sync Updated", "Domain: {$domain['domain']}, Changes: " . implode(', ', $changes));
                    } else {
                        $results['failed']++;
                        $results['details'][] = [
                            'domain_id' => $domainId,
                            'domain' => $domain['domain'],
                            'status' => 'failed',
                            'error' => 'Не удалось обновить базу данных'
                        ];
                    }
                } else {
                    $results['details'][] = [
                        'domain_id' => $domainId,
                        'domain' => $domain['domain'],
                        'status' => 'no_changes',
                        'ssl_mode' => $sslMode,
                        'always_use_https' => $alwaysHttps,
                        'min_tls_version' => $minTls
                    ];
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'domain_id' => $domainId,
                    'domain' => $domain['domain'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                
                logAction($pdo, $userId, "Quick SSL Sync Error", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
            }
            
            // Небольшая задержка между запросами
            usleep(300000); // 300ms
        }
        
        logAction($pdo, $userId, "Quick SSL Sync Completed", "Checked: {$results['checked']}, Updated: {$results['updated']}, Failed: {$results['failed']}");
        
        return [
            'success' => true,
            'checked' => $results['checked'],
            'updated' => $results['updated'],
            'failed' => $results['failed'],
            'details' => $results['details']
        ];
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Quick SSL Sync Exception", "Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Улучшенная синхронизация всех данных для выбранных доменов (включая DNS IP)
 */
function quickSyncAllData($pdo, $domainIds, $userId) {
    try {
        if (empty($domainIds)) {
            return ['success' => false, 'error' => 'Не указаны домены для синхронизации'];
        }
        
        logAction($pdo, $userId, "Quick All Data Sync Started", "Domain IDs: " . implode(', ', $domainIds) . ", Count: " . count($domainIds));
        
        $results = [
            'checked' => 0,
            'updated' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        foreach ($domainIds as $domainId) {
            $results['checked']++;
            
            // Получаем информацию о домене
            $stmt = $pdo->prepare("
                SELECT ca.*, cc.email, cc.api_key 
                FROM cloudflare_accounts ca 
                JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
                WHERE ca.id = ? AND ca.user_id = ?
            ");
            $stmt->execute([$domainId, $userId]);
            $domain = $stmt->fetch();
            
            if (!$domain || !$domain['zone_id']) {
                $results['failed']++;
                $results['details'][] = [
                    'domain_id' => $domainId,
                    'domain' => $domain['domain'] ?? 'Unknown',
                    'status' => 'failed',
                    'error' => 'Домен не найден или отсутствует Zone ID'
                ];
                continue;
            }
            
            try {
                $proxies = getProxies($pdo, $userId);
                $zoneId = $domain['zone_id'];
                
                // ИСПРАВЛЕНО: Получаем все актуальные данные из Cloudflare с детальной проверкой
                $sslResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/ssl", 'GET', [], $proxies, $userId);
                $httpsResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/always_use_https", 'GET', [], $proxies, $userId);
                $tlsResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/min_tls_version", 'GET', [], $proxies, $userId);
                $dnsResponse = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/dns_records?type=A", 'GET', [], $proxies, $userId);
                
                // Парсим данные с проверкой успешности запросов
                $sslData = $sslResponse['data'] ?? null;
                $sslMode = $domain['ssl_mode'];
                if ($sslResponse['success'] && is_object($sslData)) {
                    $sslVars = get_object_vars($sslData);
                    if (isset($sslVars['value'])) {
                        $sslMode = $sslVars['value'] ?? $domain['ssl_mode'];
                    }
                }
                
                $alwaysHttps = 0;
                if ($httpsResponse['success'] && isset($httpsResponse['data']) && is_object($httpsResponse['data'])) {
                    $httpsVars = get_object_vars($httpsResponse['data']);
                    if (isset($httpsVars['value'])) {
                        $alwaysHttps = ($httpsVars['value'] === 'on') ? 1 : 0;
                    }
                } else {
                    $alwaysHttps = $domain['always_use_https'];
                }
                
                $minTls = '1.0';
                if ($tlsResponse['success'] && isset($tlsResponse['data']) && is_object($tlsResponse['data'])) {
                    $tlsVars = get_object_vars($tlsResponse['data']);
                    if (isset($tlsVars['value'])) {
                        $minTls = $tlsVars['value'] ?? '1.0';
                    }
                } else {
                    $minTls = $domain['min_tls_version'];
                }
                
                // Получаем DNS IP
                $dnsIp = $domain['dns_ip'];
                if ($dnsResponse['success'] && !empty($dnsResponse['data'])) {
                    $dnsIp = $dnsResponse['data'][0]->content ?? $domain['dns_ip'];
                }
                
                // Обновляем данные в базе только если они изменились
                $needsUpdate = false;
                $changes = [];
                
                if ($domain['ssl_mode'] !== $sslMode) {
                    $changes[] = "SSL: {$domain['ssl_mode']} → $sslMode";
                    $needsUpdate = true;
                }
                
                if ((int)$domain['always_use_https'] !== $alwaysHttps) {
                    $changes[] = "HTTPS: " . ($domain['always_use_https'] ? 'on' : 'off') . " → " . ($alwaysHttps ? 'on' : 'off');
                    $needsUpdate = true;
                }
                
                if ($domain['min_tls_version'] !== $minTls) {
                    $changes[] = "TLS: {$domain['min_tls_version']} → $minTls";
                    $needsUpdate = true;
                }
                
                if ($domain['dns_ip'] !== $dnsIp) {
                    $changes[] = "DNS IP: {$domain['dns_ip']} → $dnsIp";
                    $needsUpdate = true;
                }
                
                if ($needsUpdate) {
                    $updateStmt = $pdo->prepare("
                        UPDATE cloudflare_accounts 
                        SET ssl_mode = ?, always_use_https = ?, min_tls_version = ?, dns_ip = ?, ssl_last_check = datetime('now')
                        WHERE id = ?
                    ");
                    $updateResult = $updateStmt->execute([$sslMode, $alwaysHttps, $minTls, $dnsIp, $domainId]);
                    
                    if ($updateResult) {
                        $results['updated']++;
                        $results['details'][] = [
                            'domain_id' => $domainId,
                            'domain' => $domain['domain'],
                            'status' => 'updated',
                            'changes' => implode(', ', $changes),
                            'ssl_mode' => $sslMode,
                            'always_use_https' => $alwaysHttps,
                            'min_tls_version' => $minTls,
                            'dns_ip' => $dnsIp
                        ];
                        
                        logAction($pdo, $userId, "Quick All Data Sync Updated", "Domain: {$domain['domain']}, Changes: " . implode(', ', $changes));
                    } else {
                        $results['failed']++;
                        $results['details'][] = [
                            'domain_id' => $domainId,
                            'domain' => $domain['domain'],
                            'status' => 'failed',
                            'error' => 'Не удалось обновить базу данных'
                        ];
                    }
                } else {
                    $results['details'][] = [
                        'domain_id' => $domainId,
                        'domain' => $domain['domain'],
                        'status' => 'no_changes',
                        'ssl_mode' => $sslMode,
                        'always_use_https' => $alwaysHttps,
                        'min_tls_version' => $minTls,
                        'dns_ip' => $dnsIp
                    ];
                }
                
            } catch (Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'domain_id' => $domainId,
                    'domain' => $domain['domain'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
                
                logAction($pdo, $userId, "Quick All Data Sync Error", "Domain: {$domain['domain']}, Error: " . $e->getMessage());
            }
            
            // Небольшая задержка между запросами
            usleep(300000); // 300ms
        }
        
        logAction($pdo, $userId, "Quick All Data Sync Completed", "Checked: {$results['checked']}, Updated: {$results['updated']}, Failed: {$results['failed']}");
        
        return [
            'success' => true,
            'checked' => $results['checked'],
            'updated' => $results['updated'],
            'failed' => $results['failed'],
            'details' => $results['details']
        ];
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Quick All Data Sync Exception", "Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Автоматическая синхронизация после изменения настроек
 */
function autoSyncAfterChange($pdo, $domainId, $userId, $changeType = 'unknown') {
    try {
        logAction($pdo, $userId, "Auto Sync After Change", "Domain ID: $domainId, Change Type: $changeType");
        
        // Ждем 2 секунды для применения изменений в Cloudflare
        sleep(2);
        
        // Выполняем синхронизацию
        $syncResult = quickSyncAllData($pdo, [$domainId], $userId);
        
        if ($syncResult['success'] && $syncResult['updated'] > 0) {
            logAction($pdo, $userId, "Auto Sync Success", "Domain ID: $domainId, Updated: {$syncResult['updated']}, Changes: " . 
                      (isset($syncResult['details'][0]['changes']) ? $syncResult['details'][0]['changes'] : 'None'));
            return ['success' => true, 'synced' => true, 'details' => $syncResult['details']];
        } else {
            logAction($pdo, $userId, "Auto Sync No Changes", "Domain ID: $domainId, Status: " . 
                      (isset($syncResult['details'][0]['status']) ? $syncResult['details'][0]['status'] : 'Unknown'));
            return ['success' => true, 'synced' => false, 'details' => $syncResult['details']];
        }
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Auto Sync Error", "Domain ID: $domainId, Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Массовая проверка синхронизации всех доменов пользователя
 */
function massiveSyncCheck($pdo, $userId, $limit = 20) {
    try {
        logAction($pdo, $userId, "Massive Sync Check Started", "User: $userId, Limit: $limit");
        
        $stmt = $pdo->prepare("
            SELECT ca.id, ca.domain, ca.zone_id
            FROM cloudflare_accounts ca 
            WHERE ca.user_id = ? 
            AND ca.zone_id IS NOT NULL AND ca.zone_id != ''
            ORDER BY ca.ssl_last_check ASC, ca.last_check ASC
            LIMIT ?
        ");
        $stmt->execute([$userId, $limit]);
        $domains = $stmt->fetchAll();
        
        if (empty($domains)) {
            return ['success' => true, 'message' => 'Нет доменов для проверки', 'checked' => 0, 'synced' => 0];
        }
        
        $domainIds = array_column($domains, 'id');
        
        // Выполняем массовую синхронизацию
        $syncResult = quickSyncAllData($pdo, $domainIds, $userId);
        
        $summary = [
            'success' => true,
            'domains_found' => count($domains),
            'domains_checked' => $syncResult['checked'] ?? 0,
            'domains_updated' => $syncResult['updated'] ?? 0,
            'domains_failed' => $syncResult['failed'] ?? 0,
            'sync_details' => $syncResult['details'] ?? []
        ];
        
        // Подсчитываем статистику изменений
        $changeTypes = [];
        foreach ($syncResult['details'] ?? [] as $detail) {
            if (isset($detail['changes']) && $detail['status'] === 'updated') {
                $changes = explode(', ', $detail['changes']);
                foreach ($changes as $change) {
                    $changeType = explode(':', $change)[0];
                    $changeTypes[$changeType] = ($changeTypes[$changeType] ?? 0) + 1;
                }
            }
        }
        
        $summary['change_statistics'] = $changeTypes;
        
        logAction($pdo, $userId, "Massive Sync Check Completed", 
                  "Found: {$summary['domains_found']}, Checked: {$summary['domains_checked']}, " .
                  "Updated: {$summary['domains_updated']}, Failed: {$summary['domains_failed']}, " .
                  "Changes: " . json_encode($changeTypes));
        
        return $summary;
        
    } catch (Exception $e) {
        logAction($pdo, $userId, "Massive Sync Check Error", "Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}


function getCloudflareAccountCredentials($pdo, $userId, $accountId) {
    $stmt = $pdo->prepare("SELECT * FROM cloudflare_credentials WHERE id = ? AND user_id = ?");
    $stmt->execute([$accountId, $userId]);
    return $stmt->fetch();
}

function ensureCloudflareZone($pdo, $credentials, $domainName, $proxies, $userId, $createIfMissing = true, $jumpStart = false) {
    $result = [
        'success' => false,
        'zone_id' => null,
        'created' => false,
        'error' => null,
        'raw' => null
    ];

    if (!$credentials) {
        $result['error'] = 'Учетные данные аккаунта не найдены';
        return $result;
    }

    $email = $credentials['email'];
    $apiKey = $credentials['api_key'];

    $lookup = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones?name=$domainName", 'GET', [], $proxies, $userId);
    if ($lookup && $lookup['success'] && !empty($lookup['data'])) {
        $zone = is_array($lookup['data']) ? reset($lookup['data']) : $lookup['data'];
        if ($zone && isset($zone->id)) {
            $result['success'] = true;
            $result['zone_id'] = $zone->id;
            $result['created'] = false;
            $result['raw'] = $zone;
            return $result;
        }
    }

    if (!$createIfMissing) {
        $result['error'] = 'Зона не найдена и автоматическое создание отключено';
        return $result;
    }

    $payload = [
        'name' => $domainName,
        'jump_start' => (bool)$jumpStart
    ];

    $create = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones", 'POST', $payload, $proxies, $userId);
    if ($create && $create['success'] && isset($create['data']->id)) {
        $result['success'] = true;
        $result['zone_id'] = $create['data']->id;
        $result['created'] = true;
        $result['raw'] = $create['data'];
        return $result;
    }

    $result['error'] = $create['api_errors'][0]['message'] ?? 'Не удалось создать зону';
    $result['raw'] = $create;
    return $result;
}

function createOrUpdateCloudflareDomain($pdo, $userId, $accountId, $domainName, $zoneId, array $options = []) {
    $defaults = [
        'server_ip' => null,
        'group_id' => null,
        'ssl_mode' => 'flexible',
        'min_tls_version' => '1.0',
        'always_use_https' => 0,
        'dns_ip' => null
    ];
    $opts = array_merge($defaults, $options);

    if (empty($opts['server_ip'])) {
        throw new Exception('Не указан server_ip для домена ' . $domainName);
    }

    $stmt = $pdo->prepare("SELECT id, zone_id FROM cloudflare_accounts WHERE user_id = ? AND domain = ?");
    $stmt->execute([$userId, $domainName]);
    $existing = $stmt->fetch();

    if ($existing) {
        $update = $pdo->prepare("UPDATE cloudflare_accounts SET account_id = ?, group_id = ?, server_ip = ?, ssl_mode = ?, min_tls_version = ?, always_use_https = ?, dns_ip = ?, zone_id = ?, updated_at = datetime('now') WHERE id = ?");
        $update->execute([
            $accountId,
            $opts['group_id'],
            $opts['server_ip'],
            $opts['ssl_mode'],
            $opts['min_tls_version'],
            $opts['always_use_https'],
            $opts['dns_ip'],
            $zoneId,
            $existing['id']
        ]);
        return $existing['id'];
    }

    $insert = $pdo->prepare("INSERT INTO cloudflare_accounts (user_id, account_id, group_id, domain, server_ip, always_use_https, min_tls_version, ssl_mode, dns_ip, zone_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
    $insert->execute([
        $userId,
        $accountId,
        $opts['group_id'],
        $domainName,
        $opts['server_ip'],
        $opts['always_use_https'],
        $opts['min_tls_version'],
        $opts['ssl_mode'],
        $opts['dns_ip'],
        $zoneId
    ]);

    return $pdo->lastInsertId();
}

function cloudflareBulkAddDomains($pdo, $userId, $accountId, array $domains, array $options = []) {
    $credentials = getCloudflareAccountCredentials($pdo, $userId, $accountId);
    if (!$credentials) {
        return ['success' => false, 'error' => 'Учетные данные Cloudflare не найдены'];
    }

    $proxies = getProxies($pdo, $userId);
    $results = [];
    $success = 0;
    $failed = 0;

    $settings = $options['settings'] ?? [];
    $cacheSettings = $options['cache_settings'] ?? [];
    $securitySettings = $options['security_settings'] ?? [];
    $groupId = $options['group_id'] ?? null;
    $serverIp = $options['server_ip'] ?? null;
    $jumpStart = $options['jump_start'] ?? false;

    foreach ($domains as $rawDomain) {
        $domainName = strtolower(trim($rawDomain));
        if (!$domainName) {
            continue;
        }

        try {
            $zone = ensureCloudflareZone($pdo, $credentials, $domainName, $proxies, $userId, true, $jumpStart);
            if (!$zone['success']) {
                $failed++;
                $results[] = ['domain' => $domainName, 'success' => false, 'error' => $zone['error']];
                continue;
            }

            $domainId = createOrUpdateCloudflareDomain($pdo, $userId, $accountId, $domainName, $zone['zone_id'], [
                'server_ip' => $options['server_ip_per_domain'][$domainName] ?? $serverIp,
                'group_id' => $groupId,
                'ssl_mode' => $options['initial_ssl_mode'] ?? 'flexible',
                'min_tls_version' => $options['initial_min_tls'] ?? '1.0',
                'always_use_https' => !empty($options['initial_always_https']) ? 1 : 0
            ]);

            $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ?");
            $stmt->execute([$domainId]);
            $domainRow = $stmt->fetch();

            if (!$domainRow) {
                throw new Exception('Не удалось получить данные домена после создания');
            }

            $credentialsCurrent = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];

            if ($settings) {
                cloudflareApplySettings($pdo, $domainRow, $credentialsCurrent, $settings, $proxies, $userId);
            }

            if ($cacheSettings) {
                cloudflareUpdateCacheSettings($pdo, $domainRow, $credentialsCurrent, $cacheSettings, $proxies, $userId);
            }

            if ($securitySettings) {
                cloudflareUpdateSecuritySettings($pdo, $domainRow, $credentialsCurrent, $securitySettings, $proxies, $userId);
            }

            $success++;
            $results[] = [
                'domain' => $domainName,
                'success' => true,
                'zone_id' => $zone['zone_id'],
                'created_zone' => $zone['created'],
                'domain_id' => $domainId
            ];

            logAction($pdo, $userId, 'Bulk Domain Added', "Domain: $domainName, Zone: {$zone['zone_id']}, Created: " . ($zone['created'] ? 'yes' : 'no'));
            usleep(250000);
        } catch (Exception $e) {
            $failed++;
            $results[] = ['domain' => $domainName, 'success' => false, 'error' => $e->getMessage()];
            logAction($pdo, $userId, 'Bulk Domain Add Failed', "Domain: $domainName, Error: " . $e->getMessage());
        }
    }

    return [
        'success' => $failed === 0,
        'processed' => count($results),
        'added' => $success,
        'failed' => $failed,
        'details' => $results
    ];
}

function cloudflareApplySettings($pdo, $domainRow, $credentials, array $settings, $proxies, $userId) {
    if (!$domainRow || !$credentials) {
        return ['success' => false, 'error' => 'Домен или учетные данные не найдены'];
    }

    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        $zoneResult = ensureCloudflareZone($pdo, $credentials, $domainRow['domain'], $proxies, $userId, false);
        if (!$zoneResult['success']) {
            return ['success' => false, 'error' => 'Zone ID не найден'];
        }
        $zoneId = $zoneResult['zone_id'];
        $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?")->execute([$zoneId, $domainRow['id']]);
    }

    $email = $credentials['email'];
    $apiKey = $credentials['api_key'];

    $applied = [];
    $errors = [];

    if (isset($settings['ssl_mode'])) {
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/ssl", 'PATCH', ['value' => $settings['ssl_mode']], $proxies, $userId);
        if ($resp['success']) {
            $applied['ssl_mode'] = $settings['ssl_mode'];
            $pdo->prepare("UPDATE cloudflare_accounts SET ssl_mode = ?, ssl_last_check = datetime('now') WHERE id = ?")->execute([$settings['ssl_mode'], $domainRow['id']]);
        } else {
            $errors['ssl_mode'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
        }
    }

    if (isset($settings['always_use_https'])) {
        $value = $settings['always_use_https'] ? 'on' : 'off';
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/always_use_https", 'PATCH', ['value' => $value], $proxies, $userId);
        if ($resp['success']) {
            $applied['always_use_https'] = $settings['always_use_https'];
            $pdo->prepare("UPDATE cloudflare_accounts SET always_use_https = ?, ssl_last_check = datetime('now') WHERE id = ?")->execute([(int)$settings['always_use_https'], $domainRow['id']]);
        } else {
            $errors['always_use_https'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
        }
    }

    if (isset($settings['min_tls_version'])) {
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/min_tls_version", 'PATCH', ['value' => $settings['min_tls_version']], $proxies, $userId);
        if ($resp['success']) {
            $applied['min_tls_version'] = $settings['min_tls_version'];
            $pdo->prepare("UPDATE cloudflare_accounts SET min_tls_version = ?, ssl_last_check = datetime('now') WHERE id = ?")->execute([$settings['min_tls_version'], $domainRow['id']]);
        } else {
            $errors['min_tls_version'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
        }
    }

    if (isset($settings['development_mode'])) {
        $value = $settings['development_mode'] ? 'on' : 'off';
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/development_mode", 'PATCH', ['value' => $value], $proxies, $userId);
        if ($resp['success']) {
            $applied['development_mode'] = $settings['development_mode'];
        } else {
            $errors['development_mode'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
        }
    }

    if (isset($settings['automatic_https_rewrites'])) {
        $value = $settings['automatic_https_rewrites'] ? 'on' : 'off';
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/automatic_https_rewrites", 'PATCH', ['value' => $value], $proxies, $userId);
        if ($resp['success']) {
            $applied['automatic_https_rewrites'] = $settings['automatic_https_rewrites'];
        } else {
            $errors['automatic_https_rewrites'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
        }
    }

    if (!empty($settings['dns_ip_sync'])) {
        $dnsResp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/dns_records?type=A", 'GET', [], $proxies, $userId);
        if ($dnsResp['success'] && !empty($dnsResp['data'])) {
            $firstRecord = is_array($dnsResp['data']) ? reset($dnsResp['data']) : $dnsResp['data'];
            if ($firstRecord && isset($firstRecord->content)) {
                $applied['dns_ip'] = $firstRecord->content;
                $pdo->prepare("UPDATE cloudflare_accounts SET dns_ip = ? WHERE id = ?")->execute([$firstRecord->content, $domainRow['id']]);
            }
        } else {
            $errors['dns_ip_sync'] = $dnsResp['api_errors'] ?? $dnsResp['curl_error'] ?? 'unknown';
        }
    }

    // Mass IP Update Support
    if (isset($settings['new_ip']) && !empty($settings['new_ip'])) {
        $newIp = $settings['new_ip'];
        $dnsResp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/dns_records?type=A", 'GET', [], $proxies, $userId);
        
        if ($dnsResp['success'] && !empty($dnsResp['data'])) {
            $updatedCount = 0;
            $records = is_array($dnsResp['data']) ? $dnsResp['data'] : [$dnsResp['data']];
            
            foreach ($records as $record) {
                if ($record->type === 'A' && $record->content !== $newIp) {
                    $updateResult = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/dns_records/{$record->id}", 'PATCH', [
                        'content' => $newIp,
                        'name' => $record->name,
                        'type' => 'A',
                        'ttl' => $record->ttl ?? 1,
                        'proxied' => $record->proxied ?? false
                    ], $proxies, $userId);
                    
                    if ($updateResult['success']) {
                        $updatedCount++;
                    } else {
                        $errors['new_ip'][] = "Failed to update record {$record->name}: " . json_encode($updateResult['api_errors'] ?? $updateResult['curl_error']);
                    }
                }
            }
            
            if ($updatedCount > 0) {
                $applied['new_ip'] = $newIp;
                $pdo->prepare("UPDATE cloudflare_accounts SET dns_ip = ? WHERE id = ?")->execute([$newIp, $domainRow['id']]);
            }
        } else {
            $errors['new_ip'] = "Failed to fetch DNS records: " . json_encode($dnsResp['api_errors'] ?? $dnsResp['curl_error']);
        }
    }

    return [
        'success' => empty($errors),
        'applied' => $applied,
        'errors' => $errors,
        'domain' => $domainRow['domain']
    ];
}

function cloudflareBulkApplySettings($pdo, $userId, $domainIds, array $settings) {
    $results = [];
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
        $stmt->execute([$domainId, $userId]);
        $domainRow = $stmt->fetch();

        if (!$domainRow) {
            $results[] = ['domain_id' => $domainId, 'success' => false, 'error' => 'Домен не найден'];
            continue;
        }

        $proxies = getProxies($pdo, $userId);
        $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
        $applyResult = cloudflareApplySettings($pdo, $domainRow, $credentials, $settings, $proxies, $userId);
        $results[] = array_merge(['domain_id' => $domainId, 'domain' => $domainRow['domain']], $applyResult);
        usleep(250000);
    }

    return $results;
}

function cloudflareUpdateCacheSettings($pdo, $domainRow, $credentials, array $settings, $proxies, $userId) {
    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        return ['success' => false, 'error' => 'Zone ID не установлен'];
    }

    $email = $credentials['email'];
    $apiKey = $credentials['api_key'];
    $applied = [];
    $errors = [];

    if (isset($settings['cache_level'])) {
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/cache_level", 'PATCH', ['value' => $settings['cache_level']], $proxies, $userId);
        $resp['success'] ? $applied['cache_level'] = $settings['cache_level'] : $errors['cache_level'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
    }

    if (isset($settings['browser_cache_ttl'])) {
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/browser_cache_ttl", 'PATCH', ['value' => (int)$settings['browser_cache_ttl']], $proxies, $userId);
        $resp['success'] ? $applied['browser_cache_ttl'] = (int)$settings['browser_cache_ttl'] : $errors['browser_cache_ttl'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
    }

    if (isset($settings['always_online'])) {
        $value = $settings['always_online'] ? 'on' : 'off';
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/always_online", 'PATCH', ['value' => $value], $proxies, $userId);
        $resp['success'] ? $applied['always_online'] = $settings['always_online'] : $errors['always_online'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
    }

    if (isset($settings['argo_smart_routing'])) {
        $value = $settings['argo_smart_routing'] ? 'on' : 'off';
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/argo/smart_routing", 'PATCH', ['value' => $value], $proxies, $userId);
        $resp['success'] ? $applied['argo_smart_routing'] = $settings['argo_smart_routing'] : $errors['argo_smart_routing'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
    }

    return ['success' => empty($errors), 'applied' => $applied, 'errors' => $errors];
}

function cloudflareUpdateSecuritySettings($pdo, $domainRow, $credentials, array $settings, $proxies, $userId) {
    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        return ['success' => false, 'error' => 'Zone ID не установлен'];
    }

    $email = $credentials['email'];
    $apiKey = $credentials['api_key'];
    $applied = [];
    $errors = [];

    if (!empty($settings['block_countries'])) {
        $expressionParts = array_map(fn($code) => "(ip.geoip.country eq \"$code\")", $settings['block_countries']);
        $payload = [
            'action' => 'block',
            'description' => 'Auto Country Block',
            'filter' => [
                'expression' => implode(' or ', $expressionParts)
            ]
        ];
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/firewall/rules", 'POST', [$payload], $proxies, $userId);
        $resp['success'] ? $applied['block_countries'] = $settings['block_countries'] : $errors['block_countries'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
    }

    if (!empty($settings['block_ip_ranges'])) {
        foreach ($settings['block_ip_ranges'] as $range) {
            $payload = [
                'mode' => 'block',
                'configuration' => [
                    'target' => 'ip',
                    'value' => $range
                ],
                'notes' => 'Auto block subnet'
            ];
            $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/firewall/access_rules/rules", 'POST', $payload, $proxies, $userId);
            if (!$resp['success']) {
                $errors['block_ip_ranges'][] = $range;
            }
        }
        if (empty($errors['block_ip_ranges'])) {
            $applied['block_ip_ranges'] = $settings['block_ip_ranges'];
        }
    }

    if (isset($settings['under_attack'])) {
        $value = $settings['under_attack'] ? 'under_attack' : 'medium';
        $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones/$zoneId/settings/security_level", 'PATCH', ['value' => $value], $proxies, $userId);
        $resp['success'] ? $applied['under_attack'] = $settings['under_attack'] : $errors['under_attack'] = $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown';
    }

    return ['success' => empty($errors), 'applied' => $applied, 'errors' => $errors];
}

function cloudflareCreateFirewallRule($pdo, $userId, $domainRow, $credentials, array $ruleData, $proxies) {
    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        return ['success' => false, 'error' => 'Zone ID не установлен'];
    }

    $email = $credentials['email'];
    $apiKey = $credentials['api_key'];
    $payload = [
        'description' => $ruleData['description'] ?? $ruleData['name'] ?? 'Custom Rule',
        'action' => $ruleData['action'] ?? 'block',
        'expression' => $ruleData['expression'],
        'paused' => !empty($ruleData['paused'])
    ];

    if (!empty($ruleData['schedule'])) {
        $payload['schedule'] = $ruleData['schedule'];
    }

    $method = empty($ruleData['rule_id']) ? 'POST' : 'PUT';
    $endpoint = empty($ruleData['rule_id']) ? "zones/$zoneId/firewall/rules" : "zones/$zoneId/firewall/rules/{$ruleData['rule_id']}";

    $requestPayload = $method === 'POST' ? [$payload] : $payload;
    $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, $endpoint, $method, $requestPayload, $proxies, $userId);

    if (!$resp['success']) {
        return ['success' => false, 'error' => $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown'];
    }

    $rule = $method === 'POST' ? (is_array($resp['data']) ? reset($resp['data']) : $resp['data']) : $resp['data'];
    $ruleId = $rule->id ?? ($ruleData['rule_id'] ?? null);

    $stmt = $pdo->prepare("INSERT INTO cloudflare_firewall_rules (user_id, domain_id, rule_id, name, expression, action, paused, description, schedule, updated_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
    $stmt->execute([
        $userId,
        $domainRow['id'],
        $ruleId,
        $ruleData['name'] ?? ($rule->description ?? 'Custom Rule'),
        $ruleData['expression'],
        $ruleData['action'] ?? 'block',
        !empty($ruleData['paused']) ? 1 : 0,
        $ruleData['description'] ?? ($rule->description ?? null),
        is_array($ruleData['schedule'] ?? null) ? json_encode($ruleData['schedule']) : ($ruleData['schedule'] ?? null)
    ]);

    return ['success' => true, 'rule_id' => $ruleId];
}

function cloudflareBulkCreateFirewallRule($pdo, $userId, $domainIds, array $ruleData) {
    $results = [];
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
        $stmt->execute([$domainId, $userId]);
        $domainRow = $stmt->fetch();

        if (!$domainRow) {
            $results[] = ['domain_id' => $domainId, 'success' => false, 'error' => 'Домен не найден'];
            continue;
        }

        $proxies = getProxies($pdo, $userId);
        $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
        $result = cloudflareCreateFirewallRule($pdo, $userId, $domainRow, $credentials, $ruleData, $proxies);
        $result['domain_id'] = $domainId;
        $result['domain'] = $domainRow['domain'];
        $results[] = $result;
        usleep(250000);
    }

    return $results;
}

function cloudflareDeleteFirewallRule($pdo, $userId, $domainRow, $credentials, $ruleId, $proxies) {
    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        return ['success' => false, 'error' => 'Zone ID не установлен'];
    }

    $email = $credentials['email'];
    $apiKey = $credentials['api_key'];
    $endpoint = "zones/$zoneId/firewall/rules/$ruleId";

    $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, $endpoint, 'DELETE', [], $proxies, $userId);
    if (!$resp['success']) {
        return ['success' => false, 'error' => $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown'];
    }

    $stmt = $pdo->prepare("DELETE FROM cloudflare_firewall_rules WHERE rule_id = ? AND domain_id = ? AND user_id = ?");
    $stmt->execute([$ruleId, $domainRow['id'], $userId]);

    return ['success' => true];
}

function cloudflareListFirewallRules($pdo, $domainRow, $credentials, $proxies, $userId) {
    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        return ['success' => false, 'error' => 'Zone ID не установлен'];
    }

    $email = $credentials['email'];
    $apiKey = $credentials['api_key'];
    $endpoint = "zones/$zoneId/firewall/rules";

    $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, $endpoint, 'GET', [], $proxies, $userId);
    if (!$resp['success']) {
        return ['success' => false, 'error' => $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown'];
    }

    return ['success' => true, 'rules' => $resp['data']];
}

function cloudflareToggleAnalytics($pdo, $domainRow, $credentials, $enable, $proxies, $userId) {
    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        return ['success' => false, 'error' => 'Zone ID не установлен'];
    }

    $email = $credentials['email'];
    $apiKey = $credentials['api_key'];
    $endpoint = "zones/$zoneId/analytics/dashboard";
    $method = $enable ? 'POST' : 'DELETE';
    $payload = $enable ? ['kind' => 'web_analytics'] : [];

    $resp = cloudflareApiRequestDetailed($pdo, $email, $apiKey, $endpoint, $method, $payload, $proxies, $userId);
    if (!$resp['success'] && $enable) {
        return ['success' => false, 'error' => $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown'];
    }

    $stmt = $pdo->prepare("UPDATE cloudflare_accounts SET ssl_last_check = ssl_last_check WHERE id = ?");
    $stmt->execute([$domainRow['id']]);

    return ['success' => true, 'enabled' => (bool)$enable];
}

function cloudflarePagesCreateProject($pdo, $credentials, $projectData, $proxies, $userId) {
    return cloudflareApiRequestDetailed($pdo, $credentials['email'], $credentials['api_key'], "accounts/{$projectData['account_id']}/pages/projects", 'POST', $projectData, $proxies, $userId);
}

function cloudflarePagesTriggerDeploy($pdo, $credentials, $accountId, $projectName, $branch, $proxies, $userId) {
    $endpoint = "accounts/$accountId/pages/projects/$projectName/deployments";
    $payload = ['branch' => $branch];
    return cloudflareApiRequestDetailed($pdo, $credentials['email'], $credentials['api_key'], $endpoint, 'POST', $payload, $proxies, $userId);
}

function cloudflarePagesFetchStatus($pdo, $credentials, $accountId, $projectName, $proxies, $userId) {
    $endpoint = "accounts/$accountId/pages/projects/$projectName";
    return cloudflareApiRequestDetailed($pdo, $credentials['email'], $credentials['api_key'], $endpoint, 'GET', [], $proxies, $userId);
}

function listCloudflareWorkerTemplates($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT * FROM cloudflare_worker_scripts WHERE user_id = ? ORDER BY name ASC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getCloudflareWorkerTemplate($pdo, $userId, $templateId) {
    $stmt = $pdo->prepare("SELECT * FROM cloudflare_worker_scripts WHERE id = ? AND user_id = ?");
    $stmt->execute([$templateId, $userId]);
    return $stmt->fetch();
}

function saveCloudflareWorkerTemplate($pdo, $userId, $name, $script, $description = null, $templateId = null) {
    if (empty($name) || empty($script)) {
        throw new Exception('Название и содержимое скрипта обязательны');
    }

    if ($templateId) {
        $stmt = $pdo->prepare("UPDATE cloudflare_worker_scripts SET name = ?, description = ?, script = ?, updated_at = datetime('now') WHERE id = ? AND user_id = ?");
        $stmt->execute([$name, $description, $script, $templateId, $userId]);
        if ($stmt->rowCount() === 0) {
            throw new Exception('Шаблон не найден или нет прав на запись');
        }
        return $templateId;
    }

    $stmt = $pdo->prepare("INSERT INTO cloudflare_worker_scripts (user_id, name, description, script, usage_count, created_at, updated_at) VALUES (?, ?, ?, ?, 0, datetime('now'), datetime('now'))");
    $stmt->execute([$userId, $name, $description, $script]);
    return $pdo->lastInsertId();
}

function deleteCloudflareWorkerTemplate($pdo, $userId, $templateId) {
    $stmt = $pdo->prepare("DELETE FROM cloudflare_worker_scripts WHERE id = ? AND user_id = ?");
    $stmt->execute([$templateId, $userId]);
    return $stmt->rowCount() > 0;
}

function cloudflareUploadWorkerScript($pdo, $credentials, $zoneId, $scriptContent, $userId = null, $proxies = []) {
    $result = [
        'success' => false,
        'http_code' => 0,
        'curl_error' => null,
        'api_errors' => [],
        'data' => null,
        'raw_response' => null
    ];

    if (!$zoneId) {
        $result['api_errors'][] = ['message' => 'Zone ID не указан'];
        return $result;
    }

    $endpointUrl = "https://api.cloudflare.com/client/v4/zones/$zoneId/workers/script";
    $ch = curl_init($endpointUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $scriptContent);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

    $headers = [
        "X-Auth-Email: {$credentials['email']}",
        "X-Auth-Key: {$credentials['api_key']}",
        "Content-Type: application/javascript"
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if (!empty($proxies)) {
        $proxy = getRandomProxy($proxies);
        if ($proxy && preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
            curl_setopt($ch, CURLOPT_PROXY, "{$matches[1]}:{$matches[2]}");
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "{$matches[3]}:{$matches[4]}");
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
        }
    }

    $response = curl_exec($ch);
    $result['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $result['curl_error'] = curl_error($ch);
    $result['raw_response'] = $response;
    curl_close($ch);

    if ($response === false || !empty($result['curl_error'])) {
        if ($userId) {
            logAction($pdo, $userId, 'Worker Upload Failed', "Zone: $zoneId, Error: {$result['curl_error']}");
        }
        return $result;
    }

    $decoded = json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if ($userId) {
            logAction($pdo, $userId, 'Worker Upload Failed', "Zone: $zoneId, JSON Error: " . json_last_error_msg());
        }
        return $result;
    }

    if (!empty($decoded->success)) {
        $result['success'] = true;
        $result['data'] = $decoded->result ?? null;
        if ($userId) {
            logAction($pdo, $userId, 'Worker Script Uploaded', "Zone: $zoneId");
        }
    } else {
        $result['api_errors'] = isset($decoded->errors) ? array_map(function ($err) {
            return [
                'code' => $err->code ?? null,
                'message' => $err->message ?? 'Unknown error'
            ];
        }, $decoded->errors) : [];
        if ($userId) {
            logAction($pdo, $userId, 'Worker Upload Failed', "Zone: $zoneId, API Errors: " . json_encode($result['api_errors']));
        }
    }

    return $result;
}

function cloudflareListWorkerRoutes($pdo, $credentials, $zoneId, $proxies, $userId) {
    return cloudflareApiRequestDetailed($pdo, $credentials['email'], $credentials['api_key'], "zones/$zoneId/workers/routes", 'GET', [], $proxies, $userId);
}

function cloudflareEnsureWorkerRoute($pdo, $credentials, $zoneId, $routePattern, $userId, $proxies, $enabled = true) {
    $payload = [
        'pattern' => $routePattern,
        'script' => '',
        'enabled' => $enabled ? true : false
    ];

    $routesResponse = cloudflareListWorkerRoutes($pdo, $credentials, $zoneId, $proxies, $userId);
    $existingRoute = null;
    if ($routesResponse['success'] && !empty($routesResponse['data'])) {
        $routes = is_array($routesResponse['data']) ? $routesResponse['data'] : [$routesResponse['data']];
        foreach ($routes as $route) {
            if (isset($route->pattern) && $route->pattern === $routePattern) {
                $existingRoute = $route;
                break;
            }
        }
    }

    if ($existingRoute && isset($existingRoute->id)) {
        $endpoint = "zones/$zoneId/workers/routes/{$existingRoute->id}";
        $resp = cloudflareApiRequestDetailed($pdo, $credentials['email'], $credentials['api_key'], $endpoint, 'PUT', $payload, $proxies, $userId);
        $resp['route'] = $resp['success'] ? $resp['data'] : null;
        $resp['route_id'] = $existingRoute->id;
        return $resp;
    }

    $resp = cloudflareApiRequestDetailed($pdo, $credentials['email'], $credentials['api_key'], "zones/$zoneId/workers/routes", 'POST', $payload, $proxies, $userId);
    if ($resp['success']) {
        $route = $resp['data'] ?? null;
        $resp['route_id'] = $route->id ?? null;
        $resp['route'] = $route;
    }
    return $resp;
}

function recordWorkerRouteState($pdo, $userId, $domainId, $routeId, $routePattern, $templateId = null, $scriptName = null, $status = 'active', $error = null) {
    $stmt = $pdo->prepare("INSERT INTO cloudflare_worker_routes (user_id, domain_id, route_id, route_pattern, script_name, template_id, status, last_error, applied_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))
        ON CONFLICT(user_id, domain_id, route_pattern) DO UPDATE SET
            route_id = excluded.route_id,
            script_name = excluded.script_name,
            template_id = excluded.template_id,
            status = excluded.status,
            last_error = excluded.last_error,
            applied_at = excluded.applied_at,
            updated_at = excluded.updated_at");
    $stmt->execute([$userId, $domainId, $routeId, $routePattern, $scriptName, $templateId, $status, $error]);
}

/**
 * Генерирует скрипт воркера на основе шаблона и конфигурации
 */
function generateWorkerScript($templateContent, $config = []) {
    $script = $templateContent;
    
    // Стандартные списки для замены если не переданы в конфиге
    $defaultBadBots = [
        'semrush', 'ahrefs', 'majestic', 'mj12bot', 'dotbot', 'rogerbot',
        'linkdex', 'blexbot', 'baiduspider', 'yandexbot', 'sogou',
        'scrapy', 'python-requests', 'curl', 'wget', 'masscan', 'nmap',
        'nikto', 'sqlmap', 'havij', 'metasploit', 'nessus', 'acunetix',
        'grabber', 'morfeus', 'grendel', 'webinspector', 'jorgee',
        'brutus', 'hydra', 'zeus', 'w3af', 'gobuster', 'dirbuster',
        'wpscan', 'joomscan', 'vega', 'skipfish', 'webshag', 'burp',
        'zap', 'proxy', 'scanner', 'exploit', 'virus', 'malware',
        'trojan', 'backdoor', 'rootkit', 'worm', 'ransomware'
    ];
    
    $defaultAllowedReferrers = [
        'google.', 'yandex.', 'bing.com', 'duckduckgo.com', 't.co',
        'facebook.com', 'instagram.com', 'youtube.com', 'vk.com', 'ok.ru'
    ];
    
    $defaultUrlExceptions = [
        '/api/', '/webhook/', '/public/', '/robots.txt', '/sitemap.xml', '/.well-known/'
    ];

    // Замена BAD_BOTS_LIST
    $badBots = $config['bad_bots'] ?? $defaultBadBots;
    $script = str_replace('{{BAD_BOTS_LIST}}', json_encode($badBots), $script);
    
    // Замена BLOCKED_IPS_LIST
    $blockedIps = $config['blocked_ips'] ?? [];
    $script = str_replace('{{BLOCKED_IPS_LIST}}', json_encode($blockedIps), $script);
    
    // Замена GEO_MODE
    $geoMode = $config['geo_mode'] ?? 'whitelist';
    $script = str_replace('{{GEO_MODE}}', $geoMode, $script);
    
    // Замена ALLOWED_COUNTRIES_LIST
    $allowedCountries = $config['allowed_countries'] ?? ['RU', 'US', 'GB', 'DE', 'FR'];
    $script = str_replace('{{ALLOWED_COUNTRIES_LIST}}', json_encode($allowedCountries), $script);
    
    // Замена ALLOWED_REFERRERS_LIST
    $allowedReferrers = $config['allowed_referrers'] ?? $defaultAllowedReferrers;
    $script = str_replace('{{ALLOWED_REFERRERS_LIST}}', json_encode($allowedReferrers), $script);
    
    // Замена URL_EXCEPTIONS_LIST
    $urlExceptions = $config['url_exceptions'] ?? $defaultUrlExceptions;
    $script = str_replace('{{URL_EXCEPTIONS_LIST}}', json_encode($urlExceptions), $script);
    
    return $script;
}

function cloudflareApplyWorkerTemplate($pdo, $userId, $domainRow, $credentials, $templateRow, $routePattern, $proxies, $config = []) {
    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        $zoneResult = ensureCloudflareZone($pdo, $credentials, $domainRow['domain'], $proxies, $userId, false);
        if (!$zoneResult['success']) {
            return ['success' => false, 'error' => 'Zone ID не найден'];
        }
        $zoneId = $zoneResult['zone_id'];
        $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?")->execute([$zoneId, $domainRow['id']]);
    }

    $scriptContent = generateWorkerScript($templateRow['script'], $config);
    $upload = cloudflareUploadWorkerScript($pdo, $credentials, $zoneId, $scriptContent, $userId, $proxies);
    if (!$upload['success']) {
        recordWorkerRouteState($pdo, $userId, $domainRow['id'], null, $routePattern, $templateRow['id'], $templateRow['name'], 'failed', json_encode($upload['api_errors'] ?? $upload['curl_error']));
        return ['success' => false, 'error' => 'Не удалось загрузить Worker скрипт', 'details' => $upload];
    }

    $pattern = $routePattern;
    if (!$pattern) {
        $pattern = $domainRow['domain'] . '/*';
    }
    if (strpos($pattern, '{{domain}}') !== false) {
        $pattern = str_replace('{{domain}}', $domainRow['domain'], $pattern);
    }

    $routeResponse = cloudflareEnsureWorkerRoute($pdo, $credentials, $zoneId, $pattern, $userId, $proxies, true);
    if (!$routeResponse['success']) {
        recordWorkerRouteState($pdo, $userId, $domainRow['id'], null, $pattern, $templateRow['id'], $templateRow['name'], 'failed', json_encode($routeResponse['api_errors'] ?? $routeResponse['curl_error']));
        return ['success' => false, 'error' => 'Не удалось обновить маршрут', 'details' => $routeResponse];
    }

    recordWorkerRouteState(
        $pdo,
        $userId,
        $domainRow['id'],
        $routeResponse['route_id'] ?? null,
        $pattern,
        $templateRow['id'],
        $templateRow['name'],
        'active',
        null
    );

    $updateTemplateStats = $pdo->prepare("UPDATE cloudflare_worker_scripts SET usage_count = usage_count + 1, last_used = datetime('now'), updated_at = datetime('now') WHERE id = ? AND user_id = ?");
    $updateTemplateStats->execute([$templateRow['id'], $userId]);

    logAction($pdo, $userId, 'Worker Applied', "Domain: {$domainRow['domain']}, Pattern: $pattern, Template: {$templateRow['name']}");

    return ['success' => true, 'route_id' => $routeResponse['route_id'] ?? null, 'pattern' => $pattern];
}

function cloudflareBulkApplyWorkerTemplate($pdo, $userId, $domainIds, $templateRow, $routePattern, $proxies, $config = []) {
    $results = [];
    foreach ($domainIds as $domainId) {
        $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
        $stmt->execute([$domainId, $userId]);
        $domainRow = $stmt->fetch();

        if (!$domainRow) {
            $results[] = [
                'domain_id' => $domainId,
                'success' => false,
                'error' => 'Домен не найден'
            ];
            continue;
        }

        $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
        $apply = cloudflareApplyWorkerTemplate($pdo, $userId, $domainRow, $credentials, $templateRow, $routePattern, $proxies, $config);
        $apply['domain_id'] = $domainId;
        $apply['domain'] = $domainRow['domain'];
        $results[] = $apply;

        usleep(250000);
    }

    return $results;
}

function cloudflareRemoveWorkerRoute($pdo, $userId, $domainRow, $credentials, $routeId, $routePattern, $proxies) {
    $zoneId = $domainRow['zone_id'];
    if (!$zoneId) {
        return ['success' => false, 'error' => 'Zone ID не установлен'];
    }

    $endpoint = "zones/$zoneId/workers/routes/$routeId";
    $resp = cloudflareApiRequestDetailed($pdo, $credentials['email'], $credentials['api_key'], $endpoint, 'DELETE', [], $proxies, $userId);
    if (!$resp['success']) {
        return ['success' => false, 'error' => $resp['api_errors'] ?? $resp['curl_error'] ?? 'unknown'];
    }

    $stmt = $pdo->prepare("DELETE FROM cloudflare_worker_routes WHERE user_id = ? AND domain_id = ? AND (route_id = ? OR route_pattern = ?)");
    $stmt->execute([$userId, $domainRow['id'], $routeId, $routePattern]);

    logAction($pdo, $userId, 'Worker Route Removed', "Domain: {$domainRow['domain']}, Route: $routePattern");

    return ['success' => true];
}

function cloudflareFetchWorkerState($pdo, $userId, $domainId) {
    $stmt = $pdo->prepare("SELECT * FROM cloudflare_worker_routes WHERE user_id = ? AND domain_id = ? ORDER BY applied_at DESC");
    $stmt->execute([$userId, $domainId]);
    return $stmt->fetchAll();
}

function saveCloudflareApiToken($pdo, $userId, $accountId, $name, $token, $tag = null) {
    $stmt = $pdo->prepare("INSERT INTO cloudflare_api_tokens (user_id, account_id, name, token, tag, created_at, updated_at) VALUES (?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
    $stmt->execute([$userId, $accountId, $name, $token, $tag]);
    return $pdo->lastInsertId();
}

function listCloudflareApiTokens($pdo, $userId, $accountId = null) {
    if ($accountId) {
        $stmt = $pdo->prepare("SELECT * FROM cloudflare_api_tokens WHERE user_id = ? AND account_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId, $accountId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM cloudflare_api_tokens WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
    }
    return $stmt->fetchAll();
}

function deleteCloudflareApiToken($pdo, $userId, $tokenId) {
    $stmt = $pdo->prepare("DELETE FROM cloudflare_api_tokens WHERE id = ? AND user_id = ?");
    $stmt->execute([$tokenId, $userId]);
    return $stmt->rowCount() > 0;
}

function exportCloudflareTokensCsv($pdo, $userId, $accountId = null) {
    $tokens = listCloudflareApiTokens($pdo, $userId, $accountId);
    $lines = ["name,token,tag,account_id,created_at"];
    foreach ($tokens as $token) {
        $lines[] = sprintf('"%s","%s","%s",%d,"%s"',
            str_replace('"', '""', $token['name']),
            str_replace('"', '""', $token['token']),
            str_replace('"', '""', $token['tag']),
            $token['account_id'],
            $token['created_at']
        );
    }
    return implode("\n", $lines);
}

// Обратная совместимость: старая функция cloudflareApiRequest
function cloudflareApiRequest($pdo, $email, $apiKey, $endpoint, $method = 'GET', $data = [], $proxies = [], $userId = null) {
    $detailedResult = cloudflareApiRequestDetailed($pdo, $email, $apiKey, $endpoint, $method, $data, $proxies, $userId);
    
    if ($detailedResult['success']) {
        // Возвращаем объект в старом формате для совместимости
        return (object) [
            'success' => true,
            'result' => $detailedResult['data']
        ];
    } else {
        return false;
    }
}

// Конец файла без закрывающего тега PHP для предотвращения лишнего вывода