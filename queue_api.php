<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Неверный JSON');

    $action = $input['action'] ?? '';
    $taskType = null;

    switch ($action) {
        case 'add_task':
            $taskType = mapOperationToTaskType($input['task_type'] ?? '');
            $domainId = (int)($input['domain_id'] ?? 0);
            $data = $input['data'] ?? [];
            if (!$taskType || $domainId <= 0) throw new Exception('Неверные параметры');
            $res = addTask($pdo, $_SESSION['user_id'], $taskType, $domainId, $data);
            echo json_encode($res);
            break;
        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function mapOperationToTaskType($operation) {
    return match($operation) {
        'update_dns_ip' => 'check_dns',
        'check_ssl_status' => 'check_ssl_status',
        'check_domain_status' => 'update_ns_records', // повторно получит статус и NS
        'create_origin_certificate' => 'create_origin_certificate',
        default => null
    };
}

function addTask($pdo, $userId, $type, $domainId, $data) {
    try {
        $stmt = $pdo->prepare("INSERT INTO queue (user_id, domain_id, type, data, status, created_at) VALUES (?, ?, ?, ?, 'pending', datetime('now'))");
        $stmt->execute([$userId, $domainId, $type, json_encode($data)]);
        return [ 'success' => true, 'task_id' => $pdo->lastInsertId() ];
    } catch (Exception $e) {
        return [ 'success' => false, 'error' => $e->getMessage() ];
    }
} 