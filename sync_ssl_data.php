<?php
/**
 * API для быстрой синхронизации SSL данных доменов
 */

// Подавляем вывод ошибок для чистого JSON ответа
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
    exit;
}

try {
    // Получаем данные запроса
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Если данные не в JSON, пробуем POST
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    $domainIds = $input['domain_ids'] ?? [];
    
    // Валидация
    if (empty($action)) {
        echo json_encode(['success' => false, 'error' => 'Не указано действие']);
        exit;
    }
    
    if (empty($domainIds) || !is_array($domainIds)) {
        echo json_encode(['success' => false, 'error' => 'Не указаны домены для синхронизации']);
        exit;
    }
    
    // Ограничиваем количество доменов для безопасности
    if (count($domainIds) > 50) {
        echo json_encode(['success' => false, 'error' => 'Максимум 50 доменов за раз']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    
    switch ($action) {
        case 'sync_ssl_data':
            // Быстрая синхронизация SSL данных
            $result = quickSyncSSLData($pdo, $domainIds, $userId);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => "Синхронизация завершена: проверено {$result['checked']}, обновлено {$result['updated']}, ошибок {$result['failed']}",
                    'checked' => $result['checked'],
                    'updated' => $result['updated'], 
                    'failed' => $result['failed'],
                    'details' => $result['details']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ]);
            }
            break;
            
        case 'sync_all_data':
            // Синхронизация всех данных (SSL + DNS IP)
            $result = quickSyncAllData($pdo, $domainIds, $userId);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => "Полная синхронизация завершена: проверено {$result['checked']}, обновлено {$result['updated']}, ошибок {$result['failed']}",
                    'checked' => $result['checked'],
                    'updated' => $result['updated'], 
                    'failed' => $result['failed'],
                    'details' => $result['details']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ]);
            }
            break;
            
        case 'massive_sync_check':
            // Массовая проверка синхронизации (без указания конкретных доменов)
            $limit = $input['limit'] ?? 20;
            if ($limit > 100) $limit = 100; // Максимум 100 доменов за раз
            
            $result = massiveSyncCheck($pdo, $userId, $limit);
            
            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => "Массовая проверка завершена: найдено {$result['domains_found']}, проверено {$result['domains_checked']}, обновлено {$result['domains_updated']}",
                    'domains_found' => $result['domains_found'],
                    'domains_checked' => $result['domains_checked'],
                    'domains_updated' => $result['domains_updated'],
                    'domains_failed' => $result['domains_failed'],
                    'change_statistics' => $result['change_statistics'],
                    'sync_details' => $result['sync_details']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $result['error']
                ]);
            }
            break;
            
        case 'sync_single_domain':
            // Синхронизация одного домена
            if (count($domainIds) !== 1) {
                echo json_encode(['success' => false, 'error' => 'Для этого действия нужен один домен']);
                exit;
            }
            
            $domainId = $domainIds[0];
            $sslResult = getSSLStatusFromCloudflare($pdo, $domainId, $userId);
            
            if ($sslResult['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => 'SSL данные успешно синхронизированы',
                    'domain_id' => $domainId,
                    'ssl_mode' => $sslResult['ssl_mode'],
                    'always_use_https' => $sslResult['always_use_https'],
                    'min_tls_version' => $sslResult['min_tls_version']
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => $sslResult['error']
                ]);
            }
            break;
            
        case 'get_current_ssl_data':
            // Получение текущих SSL данных без синхронизации
            $placeholders = str_repeat('?,', count($domainIds) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT id, domain, ssl_mode, always_use_https, min_tls_version, ssl_last_check
                FROM cloudflare_accounts 
                WHERE id IN ($placeholders) AND user_id = ?
            ");
            $stmt->execute([...$domainIds, $userId]);
            $domains = $stmt->fetchAll();
            
            echo json_encode([
                'success' => true,
                'domains' => $domains
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
            break;
    }
    
} catch (Exception $e) {
    logAction($pdo, $_SESSION['user_id'], "SSL Sync API Error", "Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()]);
}
?> 