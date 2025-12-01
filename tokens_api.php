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

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = $data['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'list':
            $accountId = isset($data['account_id']) ? (int)$data['account_id'] : null;
            $tokens = listCloudflareApiTokens($pdo, $userId, $accountId);
            echo json_encode(['success' => true, 'tokens' => $tokens]);
            break;

        case 'create':
            $accountId = (int)($data['account_id'] ?? 0);
            $name = trim($data['name'] ?? '');
            $token = trim($data['token'] ?? '');
            $tag = trim($data['tag'] ?? '');
            if ($accountId <= 0 || !$name || !$token) {
                throw new Exception('Не указаны обязательные параметры');
            }
            $id = saveCloudflareApiToken($pdo, $userId, $accountId, $name, $token, $tag ?: null);
            echo json_encode(['success' => true, 'token_id' => $id]);
            break;

        case 'delete':
            $tokenId = (int)($data['token_id'] ?? 0);
            if ($tokenId <= 0) {
                throw new Exception('Не указан token_id');
            }
            $deleted = deleteCloudflareApiToken($pdo, $userId, $tokenId);
            if (!$deleted) {
                throw new Exception('Токен не найден');
            }
            echo json_encode(['success' => true]);
            break;

        case 'export':
            $accountId = isset($data['account_id']) ? (int)$data['account_id'] : null;
            $csv = exportCloudflareTokensCsv($pdo, $userId, $accountId);
            echo json_encode(['success' => true, 'csv' => $csv]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


