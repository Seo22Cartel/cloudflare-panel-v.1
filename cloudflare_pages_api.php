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
        case 'create_project':
            $accountId = (int)($data['account_id'] ?? 0);
            $projectData = $data['project_data'] ?? [];
            if ($accountId <= 0 || empty($projectData['name'])) {
                throw new Exception('Не указаны учетные данные проекта');
            }
            $credentials = getCloudflareAccountCredentials($pdo, $userId, $accountId);
            if (!$credentials) {
                throw new Exception('Аккаунт Cloudflare не найден');
            }
            $projectData['account_id'] = $accountId;
            $proxies = getProxies($pdo, $userId);
            $result = cloudflarePagesCreateProject($pdo, $credentials, $projectData, $proxies, $userId);
            if (!$result['success']) {
                throw new Exception($result['api_errors'][0]['message'] ?? $result['curl_error'] ?? 'Не удалось создать проект');
            }
            $project = $result['data'];
            $stmt = $pdo->prepare("INSERT INTO cloudflare_pages_projects (user_id, account_id, domain_id, project_id, name, production_branch, build_config, latest_deploy, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
            $stmt->execute([
                $userId,
                $accountId,
                $data['domain_id'] ?? null,
                $project->id ?? null,
                $project->name ?? $projectData['name'],
                $project->production_branch ?? ($projectData['production_branch'] ?? null),
                isset($projectData['deployment_configs']) ? json_encode($projectData['deployment_configs']) : null,
                isset($project->latest_deployment) ? json_encode($project->latest_deployment) : null,
                $project->status ?? 'created'
            ]);
            echo json_encode(['success' => true, 'project' => $project]);
            break;

        case 'trigger_deploy':
            $accountId = (int)($data['account_id'] ?? 0);
            $projectName = $data['project_name'] ?? '';
            $branch = $data['branch'] ?? 'main';
            if ($accountId <= 0 || !$projectName) {
                throw new Exception('Не указаны проект или аккаунт');
            }
            $credentials = getCloudflareAccountCredentials($pdo, $userId, $accountId);
            if (!$credentials) {
                throw new Exception('Аккаунт Cloudflare не найден');
            }
            $proxies = getProxies($pdo, $userId);
            $result = cloudflarePagesTriggerDeploy($pdo, $credentials, $accountId, $projectName, $branch, $proxies, $userId);
            echo json_encode($result);
            break;

        case 'status':
            $accountId = (int)($data['account_id'] ?? 0);
            $projectName = $data['project_name'] ?? '';
            if ($accountId <= 0 || !$projectName) {
                throw new Exception('Не указаны проект или аккаунт');
            }
            $credentials = getCloudflareAccountCredentials($pdo, $userId, $accountId);
            if (!$credentials) {
                throw new Exception('Аккаунт Cloudflare не найден');
            }
            $proxies = getProxies($pdo, $userId);
            $result = cloudflarePagesFetchStatus($pdo, $credentials, $accountId, $projectName, $proxies, $userId);
            echo json_encode($result);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


