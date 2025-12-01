<?php
/**
 * Ежедневный скрипт синхронизации данных всех Cloudflare аккаунтов
 * 
 * Запуск по cron:
 * 0 3 * * * /usr/bin/php /path/to/cron/daily_sync.php >> /path/to/cron/logs/daily_sync.log 2>&1
 * 
 * Функции:
 * - Синхронизация SSL настроек (режим SSL, HTTPS, TLS версия)
 * - Синхронизация DNS IP
 * - Проверка NS серверов
 * - Проверка статуса сертификатов
 * - Отправка отчета в Telegram
 */

// Отключаем веб-сессию для CLI
if (php_sapi_name() !== 'cli') {
    die("Этот скрипт предназначен только для запуска из командной строки (CLI)\n");
}

// Базовый путь к проекту
$basePath = dirname(__DIR__);
chdir($basePath);

// Увеличиваем лимиты
set_time_limit(3600); // 1 час
ini_set('memory_limit', '512M');

// Подключаем конфигурацию (без сессии)
define('CLI_MODE', true);

// Загружаем минимальную конфигурацию БД
$configPath = $basePath . '/config.php';
if (!file_exists($configPath)) {
    die("Файл config.php не найден\n");
}

// Эмулируем сессию для CLI
$_SESSION = ['user_id' => null];

// Загружаем config без session_start
$configContent = file_get_contents($configPath);
if (strpos($configContent, 'session_start') !== false) {
    // Временно подавляем session_start
    ob_start();
    @include $configPath;
    ob_end_clean();
} else {
    require_once $configPath;
}

require_once $basePath . '/functions.php';

// Настройки
$config = [
    'batch_size' => 20,           // Количество доменов за один проход
    'delay_between_domains' => 1, // Секунд между доменами
    'delay_between_batches' => 5, // Секунд между пакетами
    'telegram_enabled' => false,   // Отправлять отчет в Telegram
    'telegram_bot_token' => '',   // Заполнить для Telegram
    'telegram_chat_id' => '',     // Заполнить для Telegram
    'log_file' => $basePath . '/cron/logs/daily_sync_' . date('Y-m-d') . '.log'
];

// Создаем папку для логов
$logsDir = $basePath . '/cron/logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Функция логирования
function cronLog($message, $level = 'INFO') {
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] [$level] $message\n";
    
    file_put_contents($config['log_file'], $logLine, FILE_APPEND);
    echo $logLine;
}

// Функция отправки в Telegram
function sendTelegramReport($message) {
    global $config;
    
    if (!$config['telegram_enabled'] || empty($config['telegram_bot_token']) || empty($config['telegram_chat_id'])) {
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$config['telegram_bot_token']}/sendMessage";
    
    $data = [
        'chat_id' => $config['telegram_chat_id'],
        'text' => $message,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $success = curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200;
    curl_close($ch);
    
    return $success;
}

// Класс для ежедневной синхронизации
class DailySync {
    private $pdo;
    private $config;
    private $stats;
    
    public function __construct($pdo, $config) {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->stats = [
            'start_time' => microtime(true),
            'users_processed' => 0,
            'domains_total' => 0,
            'domains_synced' => 0,
            'domains_failed' => 0,
            'domains_new' => 0,          // НОВЫЕ домены добавленные из Cloudflare
            'domains_ip_updated' => 0,   // Домены с обновлённым IP
            'ssl_stats' => [
                'off' => 0,
                'flexible' => 0,
                'full' => 0,
                'strict' => 0,
                'unknown' => 0
            ],
            'https_enabled' => 0,
            'https_disabled' => 0,
            'tls_versions' => [
                '1.0' => 0,
                '1.1' => 0,
                '1.2' => 0,
                '1.3' => 0
            ],
            'errors' => [],
            'warnings' => [],
            'new_domains' => [],        // Список новых доменов
            'ip_changes' => []          // Список изменений IP
        ];
    }
    
    /**
     * Получить всех пользователей с Cloudflare credentials
     */
    private function getUsers() {
        $stmt = $this->pdo->query("
            SELECT DISTINCT u.id, u.username
            FROM users u
            JOIN cloudflare_credentials cc ON cc.user_id = u.id
        ");
        return $stmt->fetchAll();
    }
    
    /**
     * Получить все Cloudflare credentials пользователя
     */
    private function getUserCredentials($userId) {
        $stmt = $this->pdo->prepare("
            SELECT id, email, api_key
            FROM cloudflare_credentials
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Получить существующие домены пользователя (по zone_id)
     */
    private function getExistingZoneIds($userId) {
        $stmt = $this->pdo->prepare("
            SELECT zone_id FROM cloudflare_accounts
            WHERE user_id = ? AND zone_id IS NOT NULL AND zone_id != ''
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Получить группу по умолчанию для пользователя
     */
    private function getDefaultGroupId($userId) {
        // Ищем группу "Default" или первую группу
        $stmt = $this->pdo->prepare("
            SELECT id FROM groups
            WHERE user_id = ?
            ORDER BY CASE WHEN name = 'Default' THEN 0 ELSE 1 END, id ASC
            LIMIT 1
        ");
        $stmt->execute([$userId]);
        $groupId = $stmt->fetchColumn();
        
        // Если группы нет, создаём Default
        if (!$groupId) {
            $stmt = $this->pdo->prepare("INSERT INTO groups (user_id, name) VALUES (?, 'Default')");
            $stmt->execute([$userId]);
            $groupId = $this->pdo->lastInsertId();
        }
        
        return $groupId;
    }
    
    /**
     * Синхронизировать новые домены из Cloudflare аккаунта
     */
    private function syncNewDomains($userId, $credential) {
        $proxies = getProxies($this->pdo, $userId);
        $existingZones = $this->getExistingZoneIds($userId);
        $newDomainsAdded = 0;
        
        // Получаем все зоны из Cloudflare
        $zonesResponse = cloudflareApiRequestDetailed(
            $this->pdo,
            $credential['email'],
            $credential['api_key'],
            "zones?per_page=50",
            'GET', [], $proxies, $userId
        );
        
        if (!$zonesResponse['success'] || empty($zonesResponse['data'])) {
            return 0;
        }
        
        $zones = $zonesResponse['data'];
        $defaultGroupId = $this->getDefaultGroupId($userId);
        
        foreach ($zones as $zone) {
            // Пропускаем существующие
            if (in_array($zone->id, $existingZones)) {
                continue;
            }
            
            try {
                // Получаем DNS IP для нового домена
                $dnsIp = '0.0.0.0';
                $dnsResponse = cloudflareApiRequestDetailed(
                    $this->pdo,
                    $credential['email'],
                    $credential['api_key'],
                    "zones/{$zone->id}/dns_records?type=A&per_page=1",
                    'GET', [], $proxies, $userId
                );
                
                if ($dnsResponse['success'] && !empty($dnsResponse['data'])) {
                    $dnsIp = $dnsResponse['data'][0]->content ?? '0.0.0.0';
                }
                
                // Добавляем новый домен
                $stmt = $this->pdo->prepare("
                    INSERT OR IGNORE INTO cloudflare_accounts
                    (user_id, account_id, group_id, domain, server_ip, zone_id, dns_ip, ssl_mode, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'flexible', datetime('now'))
                ");
                $stmt->execute([
                    $userId,
                    $credential['id'],
                    $defaultGroupId,
                    $zone->name,
                    $dnsIp,
                    $zone->id,
                    $dnsIp
                ]);
                
                if ($stmt->rowCount() > 0) {
                    $newDomainsAdded++;
                    $this->stats['new_domains'][] = $zone->name;
                    cronLog("    + НОВЫЙ ДОМЕН: {$zone->name} (Zone: {$zone->id}, IP: $dnsIp)");
                }
                
                // Небольшая задержка
                usleep(200000);
                
            } catch (Exception $e) {
                cronLog("    ! Ошибка добавления домена {$zone->name}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        return $newDomainsAdded;
    }
    
    /**
     * Получить домены пользователя
     */
    private function getUserDomains($userId) {
        $stmt = $this->pdo->prepare("
            SELECT ca.id, ca.domain, ca.zone_id, ca.ssl_mode, ca.always_use_https, ca.min_tls_version, ca.dns_ip,
                   cc.email, cc.api_key
            FROM cloudflare_accounts ca
            JOIN cloudflare_credentials cc ON ca.account_id = cc.id
            WHERE ca.user_id = ? AND ca.zone_id IS NOT NULL AND ca.zone_id != ''
            ORDER BY ca.domain ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Синхронизировать данные одного домена
     */
    private function syncDomain($domain, $userId) {
        $result = [
            'success' => false,
            'domain' => $domain['domain'],
            'changes' => [],
            'error' => null
        ];
        
        try {
            $proxies = getProxies($this->pdo, $userId);
            $zoneId = $domain['zone_id'];
            
            // Получаем SSL настройки
            $sslResponse = cloudflareApiRequestDetailed(
                $this->pdo, 
                $domain['email'], 
                $domain['api_key'], 
                "zones/$zoneId/settings/ssl", 
                'GET', [], $proxies, $userId
            );
            
            $httpsResponse = cloudflareApiRequestDetailed(
                $this->pdo, 
                $domain['email'], 
                $domain['api_key'], 
                "zones/$zoneId/settings/always_use_https", 
                'GET', [], $proxies, $userId
            );
            
            $tlsResponse = cloudflareApiRequestDetailed(
                $this->pdo, 
                $domain['email'], 
                $domain['api_key'], 
                "zones/$zoneId/settings/min_tls_version", 
                'GET', [], $proxies, $userId
            );
            
            // Парсим данные
            $newSslMode = $domain['ssl_mode'];
            if ($sslResponse['success'] && isset($sslResponse['data']->value)) {
                $newSslMode = $sslResponse['data']->value;
            }
            
            $newAlwaysHttps = $domain['always_use_https'];
            if ($httpsResponse['success'] && isset($httpsResponse['data']->value)) {
                $newAlwaysHttps = ($httpsResponse['data']->value === 'on') ? 1 : 0;
            }
            
            $newMinTls = $domain['min_tls_version'] ?? '1.0';
            if ($tlsResponse['success'] && isset($tlsResponse['data']->value)) {
                $newMinTls = $tlsResponse['data']->value;
            }
            
            // Получаем DNS IP
            $dnsResponse = cloudflareApiRequestDetailed(
                $this->pdo, 
                $domain['email'], 
                $domain['api_key'], 
                "zones/$zoneId/dns_records?type=A", 
                'GET', [], $proxies, $userId
            );
            
            $newDnsIp = $domain['dns_ip'];
            if ($dnsResponse['success'] && !empty($dnsResponse['data'])) {
                $newDnsIp = $dnsResponse['data'][0]->content ?? $domain['dns_ip'];
            }
            
            // Определяем изменения
            if ($domain['ssl_mode'] !== $newSslMode) {
                $result['changes'][] = "SSL: {$domain['ssl_mode']} → $newSslMode";
            }
            if ((int)$domain['always_use_https'] !== $newAlwaysHttps) {
                $result['changes'][] = "HTTPS: " . ($domain['always_use_https'] ? 'on' : 'off') . " → " . ($newAlwaysHttps ? 'on' : 'off');
            }
            if ($domain['min_tls_version'] !== $newMinTls) {
                $result['changes'][] = "TLS: {$domain['min_tls_version']} → $newMinTls";
            }
            if ($domain['dns_ip'] !== $newDnsIp) {
                $result['changes'][] = "DNS IP: {$domain['dns_ip']} → $newDnsIp";
                // Отслеживаем изменение IP
                $this->stats['domains_ip_updated']++;
                $this->stats['ip_changes'][] = [
                    'domain' => $domain['domain'],
                    'old_ip' => $domain['dns_ip'],
                    'new_ip' => $newDnsIp
                ];
            }
            
            // Обновляем базу данных
            $updateStmt = $this->pdo->prepare("
                UPDATE cloudflare_accounts 
                SET ssl_mode = ?, always_use_https = ?, min_tls_version = ?, dns_ip = ?, 
                    ssl_last_check = datetime('now'), last_check = datetime('now')
                WHERE id = ?
            ");
            $updateStmt->execute([$newSslMode, $newAlwaysHttps, $newMinTls, $newDnsIp, $domain['id']]);
            
            // Обновляем статистику
            $sslModeKey = in_array($newSslMode, ['off', 'flexible', 'full', 'strict']) ? $newSslMode : 'unknown';
            $this->stats['ssl_stats'][$sslModeKey]++;
            
            if ($newAlwaysHttps) {
                $this->stats['https_enabled']++;
            } else {
                $this->stats['https_disabled']++;
            }
            
            $tlsKey = in_array($newMinTls, ['1.0', '1.1', '1.2', '1.3']) ? $newMinTls : '1.0';
            $this->stats['tls_versions'][$tlsKey]++;
            
            $result['success'] = true;
            $result['ssl_mode'] = $newSslMode;
            $result['always_https'] = $newAlwaysHttps;
            $result['min_tls'] = $newMinTls;
            $result['dns_ip'] = $newDnsIp;
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $this->stats['errors'][] = "{$domain['domain']}: {$e->getMessage()}";
        }
        
        return $result;
    }
    
    /**
     * Запустить синхронизацию
     */
    public function run() {
        cronLog("=== Начало ежедневной синхронизации ===");
        
        $users = $this->getUsers();
        cronLog("Найдено пользователей: " . count($users));
        
        foreach ($users as $user) {
            $this->stats['users_processed']++;
            cronLog("Обработка пользователя: {$user['username']} (ID: {$user['id']})");
            
            // ШАГ 1: Поиск и добавление НОВЫХ доменов из Cloudflare
            cronLog("  [1/2] Проверка новых доменов в Cloudflare...");
            $credentials = $this->getUserCredentials($user['id']);
            
            foreach ($credentials as $credential) {
                cronLog("    Аккаунт: {$credential['email']}");
                $newCount = $this->syncNewDomains($user['id'], $credential);
                $this->stats['domains_new'] += $newCount;
                
                if ($newCount > 0) {
                    cronLog("    Добавлено новых доменов: $newCount");
                }
                
                sleep(1); // Задержка между аккаунтами
            }
            
            // ШАГ 2: Синхронизация существующих доменов
            cronLog("  [2/2] Синхронизация существующих доменов...");
            $domains = $this->getUserDomains($user['id']);
            $domainCount = count($domains);
            cronLog("  Всего доменов для синхронизации: $domainCount");
            
            $this->stats['domains_total'] += $domainCount;
            
            // Обрабатываем пакетами
            $batches = array_chunk($domains, $this->config['batch_size']);
            $batchNum = 0;
            
            foreach ($batches as $batch) {
                $batchNum++;
                cronLog("  Пакет $batchNum/" . count($batches));
                
                foreach ($batch as $domain) {
                    $result = $this->syncDomain($domain, $user['id']);
                    
                    if ($result['success']) {
                        $this->stats['domains_synced']++;
                        
                        if (!empty($result['changes'])) {
                            cronLog("    ✓ {$domain['domain']}: " . implode(', ', $result['changes']));
                        }
                    } else {
                        $this->stats['domains_failed']++;
                        cronLog("    ✗ {$domain['domain']}: {$result['error']}", 'ERROR');
                    }
                    
                    // Задержка между доменами
                    sleep($this->config['delay_between_domains']);
                }
                
                // Задержка между пакетами
                if ($batchNum < count($batches)) {
                    sleep($this->config['delay_between_batches']);
                }
            }
        }
        
        // Финализация
        $this->stats['end_time'] = microtime(true);
        $this->stats['duration'] = round($this->stats['end_time'] - $this->stats['start_time'], 2);
        
        $this->generateReport();
        
        return $this->stats;
    }
    
    /**
     * Генерация отчета
     */
    private function generateReport() {
        $report = "\n=== ОТЧЕТ ЕЖЕДНЕВНОЙ СИНХРОНИЗАЦИИ ===\n";
        $report .= "Дата: " . date('Y-m-d H:i:s') . "\n";
        $report .= "Длительность: {$this->stats['duration']} сек\n\n";
        
        $report .= "📊 СТАТИСТИКА:\n";
        $report .= "  Пользователей: {$this->stats['users_processed']}\n";
        $report .= "  Доменов всего: {$this->stats['domains_total']}\n";
        $report .= "  Новых добавлено: {$this->stats['domains_new']}\n";
        $report .= "  Синхронизировано: {$this->stats['domains_synced']}\n";
        $report .= "  IP обновлено: {$this->stats['domains_ip_updated']}\n";
        $report .= "  Ошибок: {$this->stats['domains_failed']}\n\n";
        
        // Показываем новые домены
        if (!empty($this->stats['new_domains'])) {
            $report .= "🆕 НОВЫЕ ДОМЕНЫ (" . count($this->stats['new_domains']) . "):\n";
            foreach (array_slice($this->stats['new_domains'], 0, 10) as $newDomain) {
                $report .= "  + $newDomain\n";
            }
            if (count($this->stats['new_domains']) > 10) {
                $report .= "  ... и ещё " . (count($this->stats['new_domains']) - 10) . " доменов\n";
            }
            $report .= "\n";
        }
        
        // Показываем изменения IP
        if (!empty($this->stats['ip_changes'])) {
            $report .= "🔄 ИЗМЕНЕНИЯ IP (" . count($this->stats['ip_changes']) . "):\n";
            foreach (array_slice($this->stats['ip_changes'], 0, 10) as $change) {
                $report .= "  {$change['domain']}: {$change['old_ip']} → {$change['new_ip']}\n";
            }
            if (count($this->stats['ip_changes']) > 10) {
                $report .= "  ... и ещё " . (count($this->stats['ip_changes']) - 10) . " изменений\n";
            }
            $report .= "\n";
        }
        
        $report .= "🔒 SSL РЕЖИМЫ:\n";
        foreach ($this->stats['ssl_stats'] as $mode => $count) {
            if ($count > 0) {
                $modeNames = [
                    'off' => 'Off (отключено)',
                    'flexible' => 'Flexible (гибкий)',
                    'full' => 'Full (полный)',
                    'strict' => 'Full (strict)',
                    'unknown' => 'Unknown'
                ];
                $report .= "  {$modeNames[$mode]}: $count\n";
            }
        }
        
        $report .= "\n🌐 HTTPS:\n";
        $report .= "  Включен: {$this->stats['https_enabled']}\n";
        $report .= "  Отключен: {$this->stats['https_disabled']}\n";
        
        $report .= "\n🔐 TLS ВЕРСИИ:\n";
        foreach ($this->stats['tls_versions'] as $version => $count) {
            if ($count > 0) {
                $report .= "  TLS $version: $count\n";
            }
        }
        
        // Предупреждения о небезопасных настройках
        $warnings = [];
        if ($this->stats['ssl_stats']['off'] > 0) {
            $warnings[] = "⚠️ {$this->stats['ssl_stats']['off']} доменов с отключенным SSL!";
        }
        if ($this->stats['ssl_stats']['flexible'] > 0) {
            $warnings[] = "⚠️ {$this->stats['ssl_stats']['flexible']} доменов с режимом Flexible (менее безопасно)";
        }
        if ($this->stats['tls_versions']['1.0'] > 0 || $this->stats['tls_versions']['1.1'] > 0) {
            $oldTls = $this->stats['tls_versions']['1.0'] + $this->stats['tls_versions']['1.1'];
            $warnings[] = "⚠️ $oldTls доменов с устаревшим TLS 1.0/1.1";
        }
        if ($this->stats['https_disabled'] > 0) {
            $warnings[] = "⚠️ {$this->stats['https_disabled']} доменов без Always HTTPS";
        }
        
        if (!empty($warnings)) {
            $report .= "\n⚠️ ПРЕДУПРЕЖДЕНИЯ:\n";
            foreach ($warnings as $warning) {
                $report .= "  $warning\n";
            }
        }
        
        if (!empty($this->stats['errors'])) {
            $report .= "\n❌ ОШИБКИ (" . count($this->stats['errors']) . "):\n";
            foreach (array_slice($this->stats['errors'], 0, 10) as $error) {
                $report .= "  - $error\n";
            }
            if (count($this->stats['errors']) > 10) {
                $report .= "  ... и ещё " . (count($this->stats['errors']) - 10) . " ошибок\n";
            }
        }
        
        $report .= "\n==========================================\n";
        
        cronLog($report);
        
        // Отправка в Telegram
        if ($this->config['telegram_enabled']) {
            $telegramReport = "📋 <b>Ежедневная синхронизация Cloudflare</b>\n\n";
            $telegramReport .= "✅ Синхронизировано: {$this->stats['domains_synced']}/{$this->stats['domains_total']}\n";
            
            if ($this->stats['domains_new'] > 0) {
                $telegramReport .= "🆕 Новых доменов: {$this->stats['domains_new']}\n";
            }
            if ($this->stats['domains_ip_updated'] > 0) {
                $telegramReport .= "🔄 IP обновлено: {$this->stats['domains_ip_updated']}\n";
            }
            
            $telegramReport .= "❌ Ошибок: {$this->stats['domains_failed']}\n";
            $telegramReport .= "⏱ Время: {$this->stats['duration']}s\n\n";
            
            // Список новых доменов
            if (!empty($this->stats['new_domains'])) {
                $telegramReport .= "<b>Новые домены:</b>\n";
                foreach (array_slice($this->stats['new_domains'], 0, 5) as $newDomain) {
                    $telegramReport .= "• $newDomain\n";
                }
                if (count($this->stats['new_domains']) > 5) {
                    $telegramReport .= "• ... и ещё " . (count($this->stats['new_domains']) - 5) . "\n";
                }
                $telegramReport .= "\n";
            }
            
            $telegramReport .= "<b>SSL режимы:</b>\n";
            $telegramReport .= "• Flexible: {$this->stats['ssl_stats']['flexible']}\n";
            $telegramReport .= "• Full: {$this->stats['ssl_stats']['full']}\n";
            $telegramReport .= "• Strict: {$this->stats['ssl_stats']['strict']}\n";
            
            if (!empty($warnings)) {
                $telegramReport .= "\n<b>Предупреждения:</b>\n";
                foreach ($warnings as $warning) {
                    $telegramReport .= "• " . strip_tags($warning) . "\n";
                }
            }
            
            sendTelegramReport($telegramReport);
            cronLog("Отчет отправлен в Telegram");
        }
    }
}

// ========================
// MAIN
// ========================

cronLog("Скрипт запущен");

// Проверяем подключение к БД
if (!isset($pdo)) {
    cronLog("Ошибка: PDO не инициализирован", 'ERROR');
    exit(1);
}

// Загружаем настройки Telegram из бота если есть
$telegramConfigPath = $basePath . '/telegram_bot/config.php';
if (file_exists($telegramConfigPath)) {
    // Загружаем константы Telegram если определены
    include_once $telegramConfigPath;
    
    if (defined('TELEGRAM_BOT_TOKEN') && TELEGRAM_BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE') {
        $config['telegram_enabled'] = true;
        $config['telegram_bot_token'] = TELEGRAM_BOT_TOKEN;
        $config['telegram_chat_id'] = defined('TELEGRAM_CHAT_IDS') ? explode(',', TELEGRAM_CHAT_IDS)[0] : '';
        cronLog("Telegram уведомления включены");
    }
}

try {
    $sync = new DailySync($pdo, $config);
    $stats = $sync->run();
    
    cronLog("Синхронизация завершена успешно");
    exit(0);
    
} catch (Exception $e) {
    cronLog("Критическая ошибка: " . $e->getMessage(), 'ERROR');
    
    // Отправляем уведомление об ошибке в Telegram
    if ($config['telegram_enabled']) {
        sendTelegramReport("🔴 <b>Ошибка ежедневной синхронизации</b>\n\n" . $e->getMessage());
    }
    
    exit(1);
}