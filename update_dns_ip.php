<?php
// Подавляем вывод ошибок и предупреждений для чистого JSON ответа
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

// Проверяем авторизацию
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Неверный метод запроса']);
    exit;
}

$domainId = $_POST['domain_id'] ?? null;

if (!$domainId) {
    echo json_encode(['success' => false, 'error' => 'ID домена не указан']);
    exit;
}

try {
    // Используем функцию из functions.php
    $result = getDNSIPFromCloudflare($pdo, $domainId, $_SESSION['user_id']);
    
    if ($result['success']) {
        // Получаем информацию о домене для сообщения
        $stmt = $pdo->prepare("SELECT domain FROM cloudflare_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$domainId, $_SESSION['user_id']]);
        $domain = $stmt->fetch();
        
        echo json_encode([
            'success' => true,
            'message' => "DNS IP обновлен: {$result['dns_ip']}",
            'domain_id' => $domainId,
            'dns_ip' => $result['dns_ip'],
            'name_servers' => $result['name_servers'] ?? []
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => $result['error']
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка при обновлении: ' . $e->getMessage()]);
}
?> 