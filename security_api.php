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

    $domainId = (int)($_POST['domain_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($domainId <= 0 || !$action) {
        throw new Exception('Неверные параметры');
    }

    $stmt = $pdo->prepare("SELECT ca.*, cc.email, cc.api_key FROM cloudflare_accounts ca JOIN cloudflare_credentials cc ON ca.account_id = cc.id WHERE ca.id = ? AND ca.user_id = ?");
    $stmt->execute([$domainId, $_SESSION['user_id']]);
    $domain = $stmt->fetch();

    if (!$domain) throw new Exception('Домен не найден');

    $zoneId = $domain['zone_id'];
    $proxies = getProxies($pdo, $_SESSION['user_id']);

    if (!$zoneId) {
        $z = cloudflareApiRequest($pdo, $domain['email'], $domain['api_key'], "zones?name={$domain['domain']}", 'GET', [], $proxies, $_SESSION['user_id']);
        if (!$z || empty($z->result)) throw new Exception('Zone ID не найден');
        $zoneId = $z->result[0]->id;
        $pdo->prepare("UPDATE cloudflare_accounts SET zone_id = ? WHERE id = ?")->execute([$zoneId, $domainId]);
    }

    $result = null;

    switch ($action) {
        case 'under_attack_on':
            $result = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/security_level", 'PATCH', ['value' => 'under_attack'], $proxies, $_SESSION['user_id']);
            break;
        case 'under_attack_off':
            $result = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/security_level", 'PATCH', ['value' => 'medium'], $proxies, $_SESSION['user_id']);
            break;
        case 'bot_fight_on':
            $result = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/bot_management/configuration", 'PATCH', ['sbfm_definitely_bot_fight' => ['value' => true]], $proxies, $_SESSION['user_id']);
            break;
        case 'bot_fight_off':
            $result = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/bot_management/configuration", 'PATCH', ['sbfm_definitely_bot_fight' => ['value' => false]], $proxies, $_SESSION['user_id']);
            break;
        case 'block_ai_bots_on':
            $result = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/super_bot_fight_mode", 'PATCH', ['value' => 'challenge'], $proxies, $_SESSION['user_id']);
            break;
        case 'block_ai_bots_off':
            $result = cloudflareApiRequestDetailed($pdo, $domain['email'], $domain['api_key'], "zones/$zoneId/settings/super_bot_fight_mode", 'PATCH', ['value' => 'off'], $proxies, $_SESSION['user_id']);
            break;
        default:
            throw new Exception('Неизвестное действие');
    }

    if (!$result || !$result['success']) {
        throw new Exception('API ошибка изменения настроек');
    }

    echo json_encode(['success' => true, 'message' => 'Настройки обновлены']);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 