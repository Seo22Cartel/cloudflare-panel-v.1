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

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$action = $data['action'] ?? '';
$userId = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'list_templates':
            $templates = listCloudflareWorkerTemplates($pdo, $userId);
            echo json_encode(['success' => true, 'templates' => $templates]);
            break;

        case 'save_template':
            $templateId = isset($data['template_id']) ? (int)$data['template_id'] : null;
            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '');
            $script = $data['script'] ?? '';

            $id = saveCloudflareWorkerTemplate($pdo, $userId, $name, $script, $description, $templateId);
            $template = getCloudflareWorkerTemplate($pdo, $userId, $id);
            echo json_encode(['success' => true, 'template' => $template]);
            break;

        case 'delete_template':
            $templateId = (int)($data['template_id'] ?? 0);
            if ($templateId <= 0) {
                throw new Exception('Не указан шаблон');
            }
            if (!deleteCloudflareWorkerTemplate($pdo, $userId, $templateId)) {
                throw new Exception('Шаблон не найден');
            }
            echo json_encode(['success' => true]);
            break;

        case 'get_domain':
            $domainId = (int)($data['domain_id'] ?? 0);
            if ($domainId <= 0) {
                throw new Exception('Не указан домен');
            }

            $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key, g.name AS group_name FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id LEFT JOIN groups g ON ca.group_id = g.id WHERE ca.id = ? AND ca.user_id = ?");
            $stmt->execute([$domainId, $userId]);
            $domain = $stmt->fetch();

            if (!$domain) {
                throw new Exception('Домен не найден');
            }

            $credentials = ['email' => $domain['email'], 'api_key' => $domain['api_key']];
            $proxies = getProxies($pdo, $userId);

            $zoneId = $domain['zone_id'];
            if (!$zoneId) {
                $zoneResult = ensureCloudflareZone($pdo, $credentials, $domain['domain'], $proxies, $userId, false);
                if ($zoneResult['success']) {
                    $zoneId = $zoneResult['zone_id'];
                }
            }

            $routesResponse = null;
            if ($zoneId) {
                $routesResponse = cloudflareListWorkerRoutes($pdo, $credentials, $zoneId, $proxies, $userId);
            }

            $storedRoutes = cloudflareFetchWorkerState($pdo, $userId, $domainId);
            $templates = listCloudflareWorkerTemplates($pdo, $userId);

            echo json_encode([
                'success' => true,
                'domain' => [
                    'id' => $domain['id'],
                    'name' => $domain['domain'],
                    'zone_id' => $zoneId,
                    'group' => $domain['group_name'] ?? null
                ],
                'routes' => $routesResponse,
                'stored_routes' => $storedRoutes,
                'templates' => $templates
            ]);
            break;

        case 'apply_template':
            $domainId = (int)($data['domain_id'] ?? 0);
            $templateId = (int)($data['template_id'] ?? 0);
            $routePattern = trim($data['route_pattern'] ?? '');
            $config = $data['config'] ?? []; // Получаем конфигурацию

            if ($domainId <= 0 || $templateId <= 0) {
                throw new Exception('Не указаны домен или шаблон');
            }

            $template = getCloudflareWorkerTemplate($pdo, $userId, $templateId);
            if (!$template) {
                throw new Exception('Шаблон не найден');
            }

            $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
            $stmt->execute([$domainId, $userId]);
            $domain = $stmt->fetch();
            if (!$domain) {
                throw new Exception('Домен не найден');
            }

            $credentials = ['email' => $domain['email'], 'api_key' => $domain['api_key']];
            $proxies = getProxies($pdo, $userId);
            $result = cloudflareApplyWorkerTemplate($pdo, $userId, $domain, $credentials, $template, $routePattern, $proxies, $config);

            echo json_encode($result);
            break;

        case 'apply_custom':
            $domainId = (int)($data['domain_id'] ?? 0);
            $routePattern = trim($data['route_pattern'] ?? '');
            $script = $data['script'] ?? '';
            $templateName = trim($data['template_name'] ?? 'Custom Worker');
            $saveTemplate = !empty($data['save_template']);

            if ($domainId <= 0 || !$script) {
                throw new Exception('Не указаны домен или скрипт');
            }

            $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
            $stmt->execute([$domainId, $userId]);
            $domain = $stmt->fetch();
            if (!$domain) {
                throw new Exception('Домен не найден');
            }

            $templateRow = ['id' => null, 'name' => $templateName, 'script' => $script];
            if ($saveTemplate) {
                $templateId = saveCloudflareWorkerTemplate($pdo, $userId, $templateName, $script);
                $templateRow = getCloudflareWorkerTemplate($pdo, $userId, $templateId);
            }

            $credentials = ['email' => $domain['email'], 'api_key' => $domain['api_key']];
            $proxies = getProxies($pdo, $userId);
            $result = cloudflareApplyWorkerTemplate($pdo, $userId, $domain, $credentials, $templateRow, $routePattern, $proxies);

            echo json_encode($result);
            break;

        case 'detach_route':
            $domainId = (int)($data['domain_id'] ?? 0);
            $routeId = $data['route_id'] ?? '';
            $routePattern = $data['route_pattern'] ?? '';

            if ($domainId <= 0) {
                throw new Exception('Не указан домен');
            }

            $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
            $stmt->execute([$domainId, $userId]);
            $domain = $stmt->fetch();
            if (!$domain) {
                throw new Exception('Домен не найден');
            }

            $credentials = ['email' => $domain['email'], 'api_key' => $domain['api_key']];
            $proxies = getProxies($pdo, $userId);
            $result = cloudflareRemoveWorkerRoute($pdo, $userId, $domain, $credentials, $routeId, $routePattern, $proxies);
            echo json_encode($result);
            break;

        case 'bulk_apply':
            $templateId = (int)($data['template_id'] ?? 0);
            $routePattern = trim($data['route_pattern'] ?? '');
            $scope = $data['scope'] ?? 'selected';
            $config = $data['config'] ?? []; // Получаем конфигурацию
            $domainIds = [];

            if ($templateId <= 0) {
                throw new Exception('Не указан шаблон для массового применения');
            }

            $template = getCloudflareWorkerTemplate($pdo, $userId, $templateId);
            if (!$template) {
                throw new Exception('Шаблон не найден');
            }

            if ($scope === 'selected') {
                $domainIds = array_map('intval', $data['domain_ids'] ?? []);
                if (empty($domainIds)) {
                    throw new Exception('Не выбраны домены');
                }
            } elseif ($scope === 'group') {
                $groupId = (int)($data['group_id'] ?? 0);
                if ($groupId <= 0) {
                    throw new Exception('Не указана группа');
                }
                $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE user_id = ? AND group_id = ?");
                $stmt->execute([$userId, $groupId]);
                $domainIds = array_column($stmt->fetchAll(), 'id');
            } elseif ($scope === 'all') {
                $stmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE user_id = ?");
                $stmt->execute([$userId]);
                $domainIds = array_column($stmt->fetchAll(), 'id');
            }

            $domainIds = array_values(array_filter($domainIds));
            if (empty($domainIds)) {
                throw new Exception('Список доменов пуст');
            }

            $proxies = getProxies($pdo, $userId);
            $results = cloudflareBulkApplyWorkerTemplate($pdo, $userId, $domainIds, $template, $routePattern, $proxies, $config);
            echo json_encode(['success' => true, 'results' => $results]);
            break;

        default:
            throw new Exception('Неизвестное действие');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


