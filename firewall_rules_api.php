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
            $domainId = (int)($data['domain_id'] ?? 0);
            if ($domainId <= 0) {
                throw new Exception('Не указан домен');
            }
            $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
            $stmt->execute([$domainId, $userId]);
            $domainRow = $stmt->fetch();
            if (!$domainRow) {
                throw new Exception('Домен не найден');
            }
            $proxies = getProxies($pdo, $userId);
            $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
            $cloudflareRules = cloudflareListFirewallRules($pdo, $domainRow, $credentials, $proxies, $userId);
            $stmt = $pdo->prepare("SELECT * FROM cloudflare_firewall_rules WHERE domain_id = ? AND user_id = ? ORDER BY created_at DESC");
            $stmt->execute([$domainId, $userId]);
            $stored = $stmt->fetchAll();
            echo json_encode([
                'success' => true,
                'cloudflare_rules' => $cloudflareRules['rules'] ?? [],
                'stored_rules' => $stored
            ]);
            break;

        case 'create':
            $domainId = (int)($data['domain_id'] ?? 0);
            $ruleData = $data['rule'] ?? [];
            if ($domainId <= 0 || empty($ruleData['expression'])) {
                throw new Exception('Не указаны параметры правила');
            }
            $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
            $stmt->execute([$domainId, $userId]);
            $domainRow = $stmt->fetch();
            if (!$domainRow) {
                throw new Exception('Домен не найден');
            }
            $proxies = getProxies($pdo, $userId);
            $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
            $result = cloudflareCreateFirewallRule($pdo, $userId, $domainRow, $credentials, $ruleData, $proxies);
            echo json_encode($result);
            break;

        case 'bulk_create':
            $domainIds = $data['domain_ids'] ?? [];
            $ruleData = $data['rule'] ?? [];
            if (empty($domainIds) || empty($ruleData['expression'])) {
                throw new Exception('Не указаны домены или правило');
            }
            $result = cloudflareBulkCreateFirewallRule($pdo, $userId, $domainIds, $ruleData);
            echo json_encode(['success' => true, 'details' => $result]);
            break;

        case 'delete':
            $domainId = (int)($data['domain_id'] ?? 0);
            $ruleId = $data['rule_id'] ?? '';
            if ($domainId <= 0 || !$ruleId) {
                throw new Exception('Не указаны домен или правило');
            }
            $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
            $stmt->execute([$domainId, $userId]);
            $domainRow = $stmt->fetch();
            if (!$domainRow) {
                throw new Exception('Домен не найден');
            }
            $credentials = ['email' => $domainRow['email'], 'api_key' => $domainRow['api_key']];
            $proxies = getProxies($pdo, $userId);
            $result = cloudflareDeleteFirewallRule($pdo, $userId, $domainRow, $credentials, $ruleId, $proxies);
            echo json_encode($result);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


