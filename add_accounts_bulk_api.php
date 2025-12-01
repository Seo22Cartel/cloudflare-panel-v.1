<?php
/**
 * API для массового добавления аккаунтов Cloudflare с прогрессом и rate-limiting
 * 
 * Обрабатывает аккаунты по одному для предотвращения падения сервера
 * при загрузке 1000+ аккаунтов
 */

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Увеличиваем лимиты для больших операций
ini_set('max_execution_time', 60);
ini_set('memory_limit', '256M');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'add_single_account':
        addSingleAccount();
        break;
    
    case 'validate_accounts':
        validateAccountsList();
        break;
    
    case 'get_import_status':
        getImportStatus();
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
}

/**
 * Добавляет один аккаунт и получает его домены
 * Используется для последовательной загрузки с фронтенда
 */
function addSingleAccount() {
    global $pdo;
    
    $email = trim($_POST['email'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    
    // Валидация
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Неверный email: ' . $email,
            'email' => $email
        ]);
        return;
    }
    
    if (strlen($apiKey) < 30) {
        echo json_encode([
            'success' => false, 
            'error' => 'API ключ слишком короткий',
            'email' => $email
        ]);
        return;
    }
    
    if (!$groupId) {
        echo json_encode([
            'success' => false, 
            'error' => 'Не указана группа',
            'email' => $email
        ]);
        return;
    }
    
    try {
        // Проверяем, существует ли уже такой аккаунт
        $checkStmt = $pdo->prepare("SELECT id FROM cloudflare_credentials WHERE user_id = ? AND email = ?");
        $checkStmt->execute([$_SESSION['user_id'], $email]);
        
        if ($existingAccount = $checkStmt->fetch()) {
            echo json_encode([
                'success' => true, 
                'status' => 'duplicate',
                'message' => 'Аккаунт уже существует',
                'email' => $email,
                'domains_count' => 0
            ]);
            return;
        }
        
        // Проверяем валидность API ключа через запрос к Cloudflare
        $proxies = getProxies($pdo, $_SESSION['user_id']);
        
        // Получаем зоны - это также проверит валидность ключа
        $zones = cloudflareApiRequestDetailed($pdo, $email, $apiKey, "zones?per_page=50", 'GET', [], $proxies, $_SESSION['user_id']);
        
        if (!$zones['success']) {
            $errorMsg = 'Ошибка API Cloudflare';
            if (!empty($zones['api_errors'])) {
                $errorMsg .= ': ' . implode(', ', array_map(function($e) { 
                    return $e['message'] ?? 'Unknown'; 
                }, $zones['api_errors']));
            } elseif (!empty($zones['curl_error'])) {
                $errorMsg .= ': ' . $zones['curl_error'];
            }
            
            echo json_encode([
                'success' => false, 
                'error' => $errorMsg,
                'email' => $email
            ]);
            return;
        }
        
        // Добавляем аккаунт в базу
        $stmt = $pdo->prepare("INSERT INTO cloudflare_credentials (user_id, email, api_key) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $email, $apiKey]);
        $accountId = $pdo->lastInsertId();
        
        if (!$accountId) {
            echo json_encode([
                'success' => false, 
                'error' => 'Не удалось добавить аккаунт в базу',
                'email' => $email
            ]);
            return;
        }
        
        // Добавляем домены из Cloudflare
        $domainsAdded = 0;
        
        if (!empty($zones['data'])) {
            $domainStmt = $pdo->prepare("
                INSERT OR IGNORE INTO cloudflare_accounts 
                (user_id, account_id, group_id, domain, server_ip, ssl_mode, zone_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($zones['data'] as $zone) {
                try {
                    $domainStmt->execute([
                        $_SESSION['user_id'], 
                        $accountId, 
                        $groupId, 
                        $zone->name, 
                        '0.0.0.0', 
                        'flexible', 
                        $zone->id
                    ]);
                    
                    if ($domainStmt->rowCount() > 0) {
                        $domainsAdded++;
                    }
                } catch (Exception $e) {
                    // Продолжаем даже если отдельный домен не добавился
                    logAction($pdo, $_SESSION['user_id'], "Domain Add Error", "Domain: {$zone->name}, Error: " . $e->getMessage());
                }
            }
        }
        
        logAction($pdo, $_SESSION['user_id'], "Account Added (Bulk Progressive)", "Email: $email, Domains: $domainsAdded");
        
        echo json_encode([
            'success' => true,
            'status' => 'added',
            'message' => 'Аккаунт добавлен',
            'email' => $email,
            'domains_count' => $domainsAdded,
            'account_id' => $accountId
        ]);
        
    } catch (Exception $e) {
        logAction($pdo, $_SESSION['user_id'], "Account Add Exception", "Email: $email, Error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false, 
            'error' => 'Исключение: ' . $e->getMessage(),
            'email' => $email
        ]);
    }
}

/**
 * Валидирует список аккаунтов перед импортом
 * Возвращает количество валидных/невалидных записей
 */
function validateAccountsList() {
    $accountsList = $_POST['accounts_list'] ?? '';
    $lines = explode("\n", trim($accountsList));
    
    $valid = [];
    $invalid = [];
    
    foreach ($lines as $index => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $lineNum = $index + 1;
        
        if (strpos($line, ';') === false) {
            $invalid[] = [
                'line' => $lineNum,
                'data' => $line,
                'error' => 'Неверный формат (ожидается email;api_key)'
            ];
            continue;
        }
        
        list($email, $apiKey) = explode(';', $line, 2);
        $email = trim($email);
        $apiKey = trim($apiKey);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $invalid[] = [
                'line' => $lineNum,
                'data' => $line,
                'error' => "Неверный email: $email"
            ];
            continue;
        }
        
        if (strlen($apiKey) < 30) {
            $invalid[] = [
                'line' => $lineNum,
                'data' => $line,
                'error' => "API ключ слишком короткий для $email"
            ];
            continue;
        }
        
        $valid[] = [
            'email' => $email,
            'api_key' => $apiKey
        ];
    }
    
    echo json_encode([
        'success' => true,
        'valid_count' => count($valid),
        'invalid_count' => count($invalid),
        'valid' => $valid,
        'invalid' => array_slice($invalid, 0, 10), // Показываем первые 10 ошибок
        'has_more_errors' => count($invalid) > 10
    ]);
}

/**
 * Получает статус текущего импорта из сессии
 */
function getImportStatus() {
    $status = $_SESSION['bulk_import_status'] ?? null;
    
    if (!$status) {
        echo json_encode([
            'success' => false,
            'error' => 'Нет активного импорта'
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'status' => $status
    ]);
}
?>