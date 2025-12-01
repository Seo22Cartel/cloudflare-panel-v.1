<?php
/**
 * Cloudflare API Client
 * Современный клиент для работы с Cloudflare API v4
 * 
 * Поддерживает:
 * - Legacy аутентификацию (Email + API Key)
 * - Современные API Tokens (Bearer)
 * - Прокси серверы
 * - Детальное логирование
 * - Rate limiting
 * 
 * @version 2.0
 * @author CloudPanel Team
 * @license MIT
 */

class CloudflareApiClient {
    private $pdo;
    private $email;
    private $apiKey;
    private $authType;
    private $proxies = [];
    private $userId;
    private $timeout = 30;
    private $connectTimeout = 10;
    
    // Счетчик запросов для rate limiting
    private static $requestCount = 0;
    private static $lastRequestTime = 0;
    private const RATE_LIMIT_REQUESTS = 1200; // Cloudflare limit: 1200 req/5min
    private const RATE_LIMIT_WINDOW = 300; // 5 minutes
    
    /**
     * Создать новый Cloudflare API клиент
     * 
     * @param PDO $pdo Соединение с базой данных
     * @param string|null $email Email для legacy auth или null для Bearer token
     * @param string $apiKey API Key или Bearer token
     * @param array $proxies Массив прокси серверов
     * @param int|null $userId ID пользователя для логирования
     */
    public function __construct($pdo, $email, $apiKey, $proxies = [], $userId = null) {
        $this->pdo = $pdo;
        $this->email = $email;
        $this->apiKey = $apiKey;
        $this->proxies = $proxies;
        $this->userId = $userId;
        
        // Автоматически определяем тип аутентификации
        if ($email === null || empty($email) || strpos($apiKey, 'Bearer ') === 0 || strlen($apiKey) > 40) {
            $this->authType = 'bearer';
        } else {
            $this->authType = 'legacy';
        }
    }
    
    /**
     * Установить таймауты
     */
    public function setTimeouts($timeout = 30, $connectTimeout = 10) {
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }
    
    /**
     * Проверить rate limit
     */
    private function checkRateLimit() {
        $now = time();
        
        // Сброс счетчика если прошло больше 5 минут
        if ($now - self::$lastRequestTime > self::RATE_LIMIT_WINDOW) {
            self::$requestCount = 0;
            self::$lastRequestTime = $now;
        }
        
        self::$requestCount++;
        
        // Если превышен лимит, ждем
        if (self::$requestCount > self::RATE_LIMIT_REQUESTS) {
            $waitTime = self::RATE_LIMIT_WINDOW - ($now - self::$lastRequestTime);
            if ($waitTime > 0) {
                $this->log('Rate Limit Warning', "Waiting {$waitTime}s due to rate limit");
                sleep($waitTime);
                self::$requestCount = 0;
                self::$lastRequestTime = time();
            }
        }
    }
    
    /**
     * Выполнить API запрос
     * 
     * @param string $endpoint API endpoint (без префикса /client/v4/)
     * @param string $method HTTP метод
     * @param array $data Данные для отправки
     * @return array Результат запроса
     */
    public function request($endpoint, $method = 'GET', $data = []) {
        $this->checkRateLimit();
        
        $result = [
            'success' => false,
            'data' => null,
            'http_code' => 0,
            'curl_error' => null,
            'api_errors' => [],
            'api_messages' => [],
            'raw_response' => null,
            'auth_method' => $this->authType
        ];
        
        $url = "https://api.cloudflare.com/client/v4/$endpoint";
        $ch = curl_init($url);
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        // Устанавливаем заголовки аутентификации
        $headers = ["Content-Type: application/json"];
        
        if ($this->authType === 'bearer') {
            $token = strpos($this->apiKey, 'Bearer ') === 0 ? $this->apiKey : "Bearer {$this->apiKey}";
            $headers[] = "Authorization: $token";
        } else {
            $headers[] = "X-Auth-Email: {$this->email}";
            $headers[] = "X-Auth-Key: {$this->apiKey}";
        }
        
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Настройка метода и данных
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        // Настройка прокси
        if (!empty($this->proxies)) {
            $this->setupProxy($ch);
        }
        
        // Выполнение запроса
        $response = curl_exec($ch);
        $result['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $result['curl_error'] = curl_error($ch);
        $result['raw_response'] = $response;
        curl_close($ch);
        
        // Проверка на ошибки cURL
        if ($response === false || !empty($result['curl_error'])) {
            $this->log('API Request Failed', "Endpoint: $endpoint, cURL Error: {$result['curl_error']}");
            return $result;
        }
        
        // Проверка HTTP кода
        if ($result['http_code'] < 200 || $result['http_code'] >= 300) {
            $this->log('API Request Failed', "Endpoint: $endpoint, HTTP Code: {$result['http_code']}");
            return $result;
        }
        
        // Парсинг JSON ответа
        $decodedResponse = json_decode($response);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('API Request Failed', "Endpoint: $endpoint, JSON Error: " . json_last_error_msg());
            return $result;
        }
        
        // Обработка ответа API
        if (isset($decodedResponse->success)) {
            $result['success'] = $decodedResponse->success;
            $result['data'] = $decodedResponse->result ?? null;
            
            // Извлекаем ошибки
            if (isset($decodedResponse->errors) && is_array($decodedResponse->errors)) {
                foreach ($decodedResponse->errors as $error) {
                    $result['api_errors'][] = [
                        'code' => $error->code ?? 'unknown',
                        'message' => $error->message ?? 'Unknown error',
                        'error_chain' => $error->error_chain ?? null
                    ];
                }
            }
            
            // Извлекаем сообщения
            if (isset($decodedResponse->messages) && is_array($decodedResponse->messages)) {
                foreach ($decodedResponse->messages as $message) {
                    $result['api_messages'][] = is_string($message) ? $message : ($message->message ?? 'Unknown message');
                }
            }
        }
        
        $status = $result['success'] ? 'Success' : 'Failed';
        $this->log("API Request $status", "Endpoint: $endpoint, Method: $method, HTTP: {$result['http_code']}");
        
        return $result;
    }
    
    /**
     * Настройка прокси
     */
    private function setupProxy($ch) {
        if (empty($this->proxies)) {
            return;
        }
        
        $proxy = $this->proxies[array_rand($this->proxies)];
        
        if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
            $proxyIp = $matches[1];
            $proxyPort = $matches[2];
            $proxyLogin = $matches[3];
            $proxyPass = $matches[4];
            
            curl_setopt($ch, CURLOPT_PROXY, "$proxyIp:$proxyPort");
            curl_setopt($ch, CURLOPT_PROXYUSERPWD, "$proxyLogin:$proxyPass");
            curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP);
            
            $this->log('Using Proxy', "Proxy: $proxyIp:$proxyPort");
        }
    }
    
    /**
     * Логирование
     */
    private function log($action, $details) {
        if ($this->userId && $this->pdo) {
            try {
                $stmt = $this->pdo->prepare("INSERT INTO logs (user_id, action, details, timestamp) VALUES (?, ?, ?, datetime('now'))");
                $stmt->execute([$this->userId, $action, $details]);
            } catch (Exception $e) {
                error_log("Cloudflare API Client Log Error: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Вспомогательные методы для популярных операций
     */
    
    public function listZones($page = 1, $perPage = 50) {
        return $this->request("zones?page=$page&per_page=$perPage");
    }
    
    public function getZone($zoneId) {
        return $this->request("zones/$zoneId");
    }
    
    public function listDnsRecords($zoneId, $type = null) {
        $endpoint = "zones/$zoneId/dns_records";
        if ($type) {
            $endpoint .= "?type=$type";
        }
        return $this->request($endpoint);
    }
    
    public function createDnsRecord($zoneId, $type, $name, $content, $ttl = 1, $proxied = false) {
        return $this->request("zones/$zoneId/dns_records", 'POST', [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
            'proxied' => $proxied
        ]);
    }
    
    public function updateDnsRecord($zoneId, $recordId, $data) {
        return $this->request("zones/$zoneId/dns_records/$recordId", 'PATCH', $data);
    }
    
    public function deleteDnsRecord($zoneId, $recordId) {
        return $this->request("zones/$zoneId/dns_records/$recordId", 'DELETE');
    }
    
    public function getZoneSetting($zoneId, $setting) {
        return $this->request("zones/$zoneId/settings/$setting");
    }
    
    public function updateZoneSetting($zoneId, $setting, $value) {
        return $this->request("zones/$zoneId/settings/$setting", 'PATCH', ['value' => $value]);
    }
    
    public function getSslSettings($zoneId) {
        return $this->getZoneSetting($zoneId, 'ssl');
    }
    
    public function setSslMode($zoneId, $mode) {
        // Валидация SSL режима
        $validModes = ['off', 'flexible', 'full', 'strict'];
        if (!in_array($mode, $validModes)) {
            return [
                'success' => false,
                'error' => "Invalid SSL mode. Valid modes: " . implode(', ', $validModes)
            ];
        }
        return $this->updateZoneSetting($zoneId, 'ssl', $mode);
    }
    
    public function purgeCache($zoneId, $everything = true, $files = []) {
        $data = $everything ? ['purge_everything' => true] : ['files' => $files];
        return $this->request("zones/$zoneId/purge_cache", 'POST', $data);
    }
}

