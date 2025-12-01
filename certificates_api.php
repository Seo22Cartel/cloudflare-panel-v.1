<?php
require_once 'header.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Неверный формат JSON']);
    exit;
}

$action = $data['action'] ?? '';

switch ($action) {
    case 'get_certificates':
        $domainId = $data['domain_id'] ?? null;
        if (!$domainId) {
            echo json_encode(['success' => false, 'error' => 'Не указан ID домена']);
            exit;
        }
        
        // Добавляем задачу в очередь вместо прямого выполнения
        $stmt = $pdo->prepare("
            INSERT INTO queue (user_id, domain_id, type, data, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', datetime('now'))
        ");
        $stmt->execute([$_SESSION['user_id'], $domainId, 'get_certificates_info', json_encode([])]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Задача получения сертификатов добавлена в очередь',
            'queue_added' => true
        ]);
        break;
        
    case 'create_origin_certificate':
        $domainId = $data['domain_id'] ?? null;
        $validity = $data['validity'] ?? 365;
        
        if (!$domainId) {
            echo json_encode(['success' => false, 'error' => 'Не указан ID домена']);
            exit;
        }
        
        // Добавляем задачу в очередь вместо прямого выполнения
        $stmt = $pdo->prepare("
            INSERT INTO queue (user_id, domain_id, type, data, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', datetime('now'))
        ");
        $stmt->execute([
            $_SESSION['user_id'], 
            $domainId, 
            'create_origin_certificate', 
            json_encode(['validity' => $validity])
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Задача создания Origin CA сертификата добавлена в очередь',
            'queue_added' => true
        ]);
        break;
        
    case 'order_universal_ssl':
        $domainId = $data['domain_id'] ?? null;
        
        if (!$domainId) {
            echo json_encode(['success' => false, 'error' => 'Не указан ID домена']);
            exit;
        }
        
        // Добавляем задачу в очередь вместо прямого выполнения
        $stmt = $pdo->prepare("
            INSERT INTO queue (user_id, domain_id, type, data, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', datetime('now'))
        ");
        $stmt->execute([$_SESSION['user_id'], $domainId, 'order_universal_ssl', json_encode([])]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Задача заказа Universal SSL добавлена в очередь',
            'queue_added' => true
        ]);
        break;
        
    case 'bulk_create_certificates':
        $certificateType = $data['certificate_type'] ?? 'origin';
        $limit = $data['limit'] ?? 5;
        
        // Добавляем массовую задачу в очередь
        $stmt = $pdo->prepare("
            INSERT INTO queue (user_id, domain_id, type, data, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', datetime('now'))
        ");
        $stmt->execute([
            $_SESSION['user_id'], 
            0, // Для массовых задач используем 0
            'bulk_create_certificates', 
            json_encode(['certificate_type' => $certificateType, 'limit' => $limit])
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Задача массового создания сертификатов добавлена в очередь',
            'queue_added' => true
        ]);
        break;
        
    case 'bulk_get_certificates_status':
        $limit = $data['limit'] ?? 10;
        
        // Добавляем массовую задачу в очередь
        $stmt = $pdo->prepare("
            INSERT INTO queue (user_id, domain_id, type, data, status, created_at) 
            VALUES (?, ?, ?, ?, 'pending', datetime('now'))
        ");
        $stmt->execute([
            $_SESSION['user_id'], 
            0, // Для массовых задач используем 0
            'bulk_get_certificates_status', 
            json_encode(['limit' => $limit])
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Задача массовой проверки статуса сертификатов добавлена в очередь',
            'queue_added' => true
        ]);
        break;
        
    case 'get_certificates_status':
        // Эта функция остается для прямого выполнения, так как используется для быстрого обновления таблицы
        $domainIds = $data['domain_ids'] ?? [];
        
        if (empty($domainIds)) {
            echo json_encode(['success' => false, 'error' => 'Не указаны ID доменов']);
            exit;
        }
        
        $results = [];
        foreach ($domainIds as $domainId) {
            // Получаем информацию из базы данных (быстрый способ)
            $stmt = $pdo->prepare("
                SELECT ssl_cert_id, ssl_cert_created, domain 
                FROM cloudflare_accounts 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$domainId, $_SESSION['user_id']]);
            $domainData = $stmt->fetch();
            
            if ($domainData) {
                if ($domainData['ssl_cert_id']) {
                    $results[$domainId] = [
                        'total_count' => 1,
                        'active_count' => 1,
                        'expiring_soon' => false,
                        'nearest_expiry' => null,
                        'has_origin_ca' => true
                    ];
                } else {
                    $results[$domainId] = [
                        'total_count' => 0,
                        'active_count' => 0,
                        'expiring_soon' => false,
                        'nearest_expiry' => null,
                        'has_origin_ca' => false
                    ];
                }
            }
        }
        
        echo json_encode(['success' => true, 'certificates' => $results]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
        break;
}

// Логируем действие
logAction($pdo, $_SESSION['user_id'], "Certificates API Request", "Action: $action");
?> 