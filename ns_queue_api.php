<?php
/**
 * API для управления NS серверами через систему очередей
 */

require_once 'config.php';
require_once 'functions.php';

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный формат JSON']);
    exit;
}

$action = $data['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'add_single_ns_update':
            $domainId = $data['domain_id'] ?? null;
            if (!$domainId) {
                throw new Exception('Не указан ID домена');
            }
            
            $result = addNSUpdateToQueue($pdo, $userId, $domainId);
            echo json_encode($result);
            break;
            
        case 'add_bulk_ns_update':
            $limit = $data['limit'] ?? 10;
            $result = addBulkNSUpdateToQueue($pdo, $userId, $limit);
            echo json_encode($result);
            break;
            
        case 'add_selected_ns_update':
            $domainIds = $data['domain_ids'] ?? [];
            if (empty($domainIds)) {
                throw new Exception('Не выбраны домены');
            }
            
            $result = addSelectedNSUpdateToQueue($pdo, $userId, $domainIds);
            echo json_encode($result);
            break;
            
        case 'get_queue_status':
            $result = getNSQueueStatus($pdo, $userId);
            echo json_encode($result);
            break;
            
        case 'clear_completed_ns_tasks':
            $result = clearCompletedNSTasks($pdo, $userId);
            echo json_encode($result);
            break;
            
        case 'cancel_pending_task':
            $taskId = $data['task_id'] ?? null;
            if (!$taskId) {
                throw new Exception('Не указан ID задачи');
            }
            
            $result = cancelPendingTask($pdo, $userId, $taskId);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Неизвестное действие: ' . $action);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Добавляет задачу обновления NS для одного домена
 */
function addNSUpdateToQueue($pdo, $userId, $domainId) {
    try {
        // Проверяем что домен принадлежит пользователю
        $stmt = $pdo->prepare("SELECT domain FROM cloudflare_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$domainId, $userId]);
        $domain = $stmt->fetch();
        
        if (!$domain) {
            throw new Exception('Домен не найден или не принадлежит пользователю');
        }
        
        // Проверяем нет ли уже pending задачи для этого домена
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM queue 
            WHERE user_id = ? AND domain_id = ? AND type = 'update_ns_records' AND status = 'pending'
        ");
        $stmt->execute([$userId, $domainId]);
        $existingTasks = $stmt->fetchColumn();
        
        if ($existingTasks > 0) {
            return [
                'success' => false,
                'error' => 'Задача обновления NS для этого домена уже в очереди'
            ];
        }
        
        // Добавляем задачу в очередь
        $stmt = $pdo->prepare("
            INSERT INTO queue (user_id, domain_id, type, data, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', datetime('now'))
        ");
        $stmt->execute([$userId, $domainId, 'update_ns_records', json_encode([])]);
        
        $taskId = $pdo->lastInsertId();
        
        logAction($pdo, $userId, "NS Queue: Single Domain Added", 
            "Domain: {$domain['domain']}, Task ID: $taskId");
        
        return [
            'success' => true,
            'message' => 'Задача обновления NS добавлена в очередь',
            'task_id' => $taskId,
            'domain' => $domain['domain']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Добавляет массовую задачу обновления NS
 */
function addBulkNSUpdateToQueue($pdo, $userId, $limit = 10) {
    try {
        // Проверяем нет ли уже активной массовой задачи
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM queue 
            WHERE user_id = ? AND type = 'bulk_update_ns_records' AND status IN ('pending', 'processing')
        ");
        $stmt->execute([$userId]);
        $existingTasks = $stmt->fetchColumn();
        
        if ($existingTasks > 0) {
            return [
                'success' => false,
                'error' => 'Массовая задача обновления NS уже выполняется'
            ];
        }
        
        // Добавляем массовую задачу
        $stmt = $pdo->prepare("
            INSERT INTO queue (user_id, domain_id, type, data, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', datetime('now'))
        ");
        $stmt->execute([$userId, 0, 'bulk_update_ns_records', json_encode(['limit' => $limit])]);
        
        $taskId = $pdo->lastInsertId();
        
        logAction($pdo, $userId, "NS Queue: Bulk Update Added", 
            "Limit: $limit, Task ID: $taskId");
        
        return [
            'success' => true,
            'message' => "Массовая задача обновления NS добавлена в очередь (лимит: $limit)",
            'task_id' => $taskId,
            'limit' => $limit
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Добавляет задачи обновления NS для выбранных доменов
 */
function addSelectedNSUpdateToQueue($pdo, $userId, $domainIds) {
    try {
        $addedTasks = 0;
        $skippedTasks = 0;
        $errors = [];
        
        foreach ($domainIds as $domainId) {
            $result = addNSUpdateToQueue($pdo, $userId, $domainId);
            
            if ($result['success']) {
                $addedTasks++;
            } else {
                $skippedTasks++;
                $errors[] = "Домен ID $domainId: " . $result['error'];
            }
        }
        
        logAction($pdo, $userId, "NS Queue: Selected Domains Added", 
            "Total: " . count($domainIds) . ", Added: $addedTasks, Skipped: $skippedTasks");
        
        return [
            'success' => true,
            'message' => "Обработано доменов: " . count($domainIds),
            'added' => $addedTasks,
            'skipped' => $skippedTasks,
            'errors' => $errors
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Получает статус NS задач в очереди
 */
function getNSQueueStatus($pdo, $userId) {
    try {
        // Статистика NS задач по статусам
        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count 
            FROM queue 
            WHERE user_id = ? AND type LIKE '%ns%' 
            GROUP BY status
        ");
        $stmt->execute([$userId]);
        $statusStats = $stmt->fetchAll();
        
        // Последние NS задачи
        $stmt = $pdo->prepare("
            SELECT q.*, ca.domain 
            FROM queue q 
            LEFT JOIN cloudflare_accounts ca ON q.domain_id = ca.id 
            WHERE q.user_id = ? AND q.type LIKE '%ns%' 
            ORDER BY q.created_at DESC 
            LIMIT 20
        ");
        $stmt->execute([$userId]);
        $recentTasks = $stmt->fetchAll();
        
        // Pending NS задачи
        $stmt = $pdo->prepare("
            SELECT q.*, ca.domain 
            FROM queue q 
            LEFT JOIN cloudflare_accounts ca ON q.domain_id = ca.id 
            WHERE q.user_id = ? AND q.type LIKE '%ns%' AND q.status = 'pending' 
            ORDER BY q.created_at ASC
        ");
        $stmt->execute([$userId]);
        $pendingTasks = $stmt->fetchAll();
        
        // Обрабатываемые задачи
        $stmt = $pdo->prepare("
            SELECT q.*, ca.domain 
            FROM queue q 
            LEFT JOIN cloudflare_accounts ca ON q.domain_id = ca.id 
            WHERE q.user_id = ? AND q.type LIKE '%ns%' AND q.status = 'processing' 
            ORDER BY q.started_at ASC
        ");
        $stmt->execute([$userId]);
        $processingTasks = $stmt->fetchAll();
        
        return [
            'success' => true,
            'status_stats' => $statusStats,
            'recent_tasks' => $recentTasks,
            'pending_tasks' => $pendingTasks,
            'processing_tasks' => $processingTasks
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Очищает завершенные NS задачи
 */
function clearCompletedNSTasks($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("
            DELETE FROM queue 
            WHERE user_id = ? 
            AND type LIKE '%ns%' 
            AND status IN ('completed', 'failed') 
            AND completed_at < datetime('now', '-1 hour')
        ");
        $stmt->execute([$userId]);
        
        $clearedCount = $stmt->rowCount();
        
        logAction($pdo, $userId, "NS Queue: Cleaned Completed Tasks", 
            "Cleared: $clearedCount tasks");
        
        return [
            'success' => true,
            'message' => "Очищено завершенных NS задач: $clearedCount",
            'cleared_count' => $clearedCount
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Отменяет pending задачу
 */
function cancelPendingTask($pdo, $userId, $taskId) {
    try {
        // Проверяем что задача принадлежит пользователю и ее можно отменить
        $stmt = $pdo->prepare("
            SELECT type, status FROM queue 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$taskId, $userId]);
        $task = $stmt->fetch();
        
        if (!$task) {
            throw new Exception('Задача не найдена или не принадлежит пользователю');
        }
        
        if ($task['status'] !== 'pending') {
            throw new Exception('Можно отменить только pending задачи');
        }
        
        // Отменяем задачу
        $stmt = $pdo->prepare("
            UPDATE queue 
            SET status = 'cancelled', completed_at = datetime('now') 
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$taskId, $userId]);
        
        logAction($pdo, $userId, "NS Queue: Task Cancelled", 
            "Task ID: $taskId, Type: {$task['type']}");
        
        return [
            'success' => true,
            'message' => 'Задача отменена',
            'task_id' => $taskId
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?> 