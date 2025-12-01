<?php
/**
 * Процессор очередей задач Cloudflare Panel
 * Обрабатывает задачи из таблицы queue
 */

// Подключаем конфигурацию без HTML вывода
require_once 'config.php';
require_once 'functions.php';

// Отключаем HTML вывод для API
header('Content-Type: application/json; charset=utf-8');

// Проверяем права доступа
$isAuthorized = false;

// Авторизация через параметры или сессию
if (isset($_GET['auth_token']) && $_GET['auth_token'] === 'cloudflare_queue_processor_2024') {
    $isAuthorized = true;
} elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $isAuthorized = true;
} elseif (php_sapi_name() === 'cli') {
    $isAuthorized = true; // CLI всегда разрешен
}

if (!$isAuthorized) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Основной класс процессора
class QueueProcessor {
    private $pdo;
    private $maxTasks;
    private $taskTimeout;
    
    public function __construct($pdo, $maxTasks = 10, $taskTimeout = 300) {
        $this->pdo = $pdo;
        $this->maxTasks = $maxTasks;
        $this->taskTimeout = $taskTimeout;
    }
    
    /**
     * Основной метод обработки очереди
     */
    public function processQueue() {
        $startTime = microtime(true);
        $processed = 0;
        $results = [];
        
        try {
            // Получаем pending задачи
            $stmt = $this->pdo->prepare("
                SELECT * FROM queue 
                WHERE status = 'pending' 
                ORDER BY created_at ASC 
                LIMIT ?
            ");
            $stmt->execute([$this->maxTasks]);
            $tasks = $stmt->fetchAll();
            
            foreach ($tasks as $task) {
                $result = $this->processTask($task);
                $results[] = $result;
                $processed++;
                
                // Небольшая задержка между задачами
                usleep(100000); // 0.1 секунды
            }
            
            $executionTime = round(microtime(true) - $startTime, 2);
            
            return [
                'success' => true,
                'processed' => $processed,
                'execution_time' => $executionTime,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processed' => $processed
            ];
        }
    }
    
    /**
     * Обработка отдельной задачи
     */
    private function processTask($task) {
        $taskId = $task['id'];
        $taskType = $task['type'];
        $userId = $task['user_id'];
        $domainId = $task['domain_id'];
        $taskData = json_decode($task['data'], true) ?: [];
        
        try {
            // Отмечаем задачу как выполняющуюся
            $this->updateTaskStatus($taskId, 'processing', null, date('Y-m-d H:i:s'));
            
            $result = null;
            
            switch ($taskType) {
                case 'update_ns_records':
                    $result = $this->processUpdateNSRecords($userId, $domainId, $taskData);
                    break;
                    
                case 'bulk_update_ns_records':
                    $result = $this->processBulkUpdateNSRecords($userId, $taskData);
                    break;
                    
                case 'get_certificates_info':
                    $result = $this->processGetCertificatesInfo($userId, $domainId, $taskData);
                    break;
                    
                case 'create_origin_certificate':
                    $result = $this->processCreateOriginCertificate($userId, $domainId, $taskData);
                    break;
                    
                case 'order_universal_ssl':
                    $result = $this->processOrderUniversalSSL($userId, $domainId, $taskData);
                    break;
                    
                case 'bulk_create_certificates':
                    $result = $this->processBulkCreateCertificates($userId, $taskData);
                    break;
                    
                case 'bulk_get_certificates_status':
                    $result = $this->processBulkGetCertificatesStatus($userId, $taskData);
                    break;
                    
                case 'check_dns':
                    $result = $this->processCheckDNS($userId, $domainId, $taskData);
                    break;
                    
                case 'update_settings':
                    $result = $this->processUpdateSettings($userId, $domainId, $taskData);
                    break;
                
                case 'check_ssl_status':
                    $result = $this->processCheckSSLStatus($userId, $domainId);
                    break;
                    
                default:
                    throw new Exception("Неизвестный тип задачи: $taskType");
            }
            
            // Сохраняем результат
            $this->updateTaskStatus($taskId, 'completed', json_encode($result), null, date('Y-m-d H:i:s'));
            
            return [
                'task_id' => $taskId,
                'type' => $taskType,
                'status' => 'completed',
                'result' => $result
            ];
            
        } catch (Exception $e) {
            // Сохраняем ошибку
            $errorResult = ['success' => false, 'error' => $e->getMessage()];
            $this->updateTaskStatus($taskId, 'failed', json_encode($errorResult), null, date('Y-m-d H:i:s'));
            
            return [
                'task_id' => $taskId,
                'type' => $taskType,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Обновление статуса задачи
     */
    private function updateTaskStatus($taskId, $status, $result = null, $startedAt = null, $completedAt = null) {
        $sql = "UPDATE queue SET status = ?";
        $params = [$status];
        
        if ($result !== null) {
            $sql .= ", result = ?";
            $params[] = $result;
        }
        
        if ($startedAt !== null) {
            $sql .= ", started_at = ?";
            $params[] = $startedAt;
        }
        
        if ($completedAt !== null) {
            $sql .= ", completed_at = ?";
            $params[] = $completedAt;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $taskId;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
    
    /**
     * Обновление NS записей для отдельного домена
     */
    private function processUpdateNSRecords($userId, $domainId, $taskData) {
        // Получаем информацию о домене
        $stmt = $this->pdo->prepare("
            SELECT ca.*, cc.email, cc.api_key 
            FROM cloudflare_accounts ca 
            LEFT JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
            WHERE ca.id = ? AND ca.user_id = ?
        ");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain) {
            throw new Exception("Домен не найден");
        }
        
        // Используем существующую функцию для получения NS серверов
        $result = getDomainStatusAndNameservers($this->pdo, $domainId, $userId);
        
        if ($result['success']) {
            logAction($this->pdo, $userId, "Queue: NS Updated", 
                "Domain: {$domain['domain']}, NS Count: " . count($result['name_servers']));
        }
        
        return $result;
    }
    
    /**
     * Массовое обновление NS записей
     */
    private function processBulkUpdateNSRecords($userId, $taskData) {
        $limit = $taskData['limit'] ?? 10;
        
        // Используем существующую функцию
        $result = updateAllNameservers($this->pdo, $userId, $limit);
        
        logAction($this->pdo, $userId, "Queue: Bulk NS Update", 
            "Limit: $limit, Updated: " . ($result['updated'] ?? 0));
        
        return $result;
    }
    
    /**
     * Получение информации о сертификатах
     */
    private function processGetCertificatesInfo($userId, $domainId, $taskData) {
        // Используем существующую функцию получения сертификатов
        return getCertificatesFromCloudflare($this->pdo, $domainId, $userId);
    }
    
    /**
     * Создание Origin CA сертификата
     */
    private function processCreateOriginCertificate($userId, $domainId, $taskData) {
        $validity = $taskData['validity'] ?? 365;
        return createOriginCertificate($this->pdo, $domainId, $userId, $validity);
    }
    
    /**
     * Заказ Universal SSL
     */
    private function processOrderUniversalSSL($userId, $domainId, $taskData) {
        return orderUniversalSSL($this->pdo, $domainId, $userId);
    }
    
    /**
     * Массовое создание сертификатов
     */
    private function processBulkCreateCertificates($userId, $taskData) {
        $certificateType = $taskData['certificate_type'] ?? 'origin';
        $limit = $taskData['limit'] ?? 5;
        
        return bulkCreateCertificates($this->pdo, $userId, $certificateType, $limit);
    }
    
    /**
     * Массовая проверка статуса сертификатов
     */
    private function processBulkGetCertificatesStatus($userId, $taskData) {
        $limit = $taskData['limit'] ?? 10;
        return getAllCertificatesStatus($this->pdo, $userId, $limit);
    }
    
    /**
     * Проверка DNS
     */
    private function processCheckDNS($userId, $domainId, $taskData) {
        return getDNSIPFromCloudflare($this->pdo, $domainId, $userId);
    }
    
    /**
     * Обновление настроек
     */
    private function processUpdateSettings($userId, $domainId, $taskData) {
        // Заглушка для обновления настроек
        // Здесь можно добавить логику обновления настроек в Cloudflare
        return ['success' => true, 'message' => 'Settings update queued'];
    }

    /**
     * Проверка SSL статуса для одного домена
     */
    private function processCheckSSLStatus($userId, $domainId) {
        return getSSLStatusFromCloudflare($this->pdo, $domainId, $userId);
    }
}

// Точка входа
try {
    $processor = new QueueProcessor($pdo);
    
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'process':
                $result = $processor->processQueue();
                echo json_encode($result);
                break;
                
            case 'status':
                // Получаем статистику очереди
                $stats = getQueueStats($pdo);
                echo json_encode($stats);
                break;
                
            case 'clear_completed':
                // Очищаем завершенные задачи старше 24 часов
                $cleared = clearCompletedTasks($pdo);
                echo json_encode(['success' => true, 'cleared' => $cleared]);
                break;
                
            default:
                echo json_encode(['error' => 'Unknown action']);
        }
    } else {
        // По умолчанию обрабатываем очередь
        $result = $processor->processQueue();
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Получение статистики очереди
 */
function getQueueStats($pdo) {
    try {
        $stats = [];
        
        // Общая статистика
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM queue GROUP BY status");
        $statusCounts = $stmt->fetchAll();
        
        foreach ($statusCounts as $row) {
            $stats[$row['status']] = $row['count'];
        }
        
        // Статистика по типам задач
        $stmt = $pdo->query("SELECT type, COUNT(*) as count FROM queue WHERE status = 'pending' GROUP BY type");
        $typeCounts = $stmt->fetchAll();
        $stats['pending_by_type'] = [];
        
        foreach ($typeCounts as $row) {
            $stats['pending_by_type'][$row['type']] = $row['count'];
        }
        
        // Последние выполненные задачи
        $stmt = $pdo->query("
            SELECT id, type, status, created_at, completed_at 
            FROM queue 
            WHERE status IN ('completed', 'failed') 
            ORDER BY completed_at DESC 
            LIMIT 10
        ");
        $stats['recent_tasks'] = $stmt->fetchAll();
        
        return [
            'success' => true,
            'stats' => $stats
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Очистка завершенных задач
 */
function clearCompletedTasks($pdo) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM queue 
            WHERE status IN ('completed', 'failed') 
            AND completed_at < datetime('now', '-24 hours')
        ");
        $stmt->execute();
        
        return $stmt->rowCount();
        
    } catch (Exception $e) {
        return 0;
    }
}
?> 