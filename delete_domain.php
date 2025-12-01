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
    // Получаем информацию о домене для проверки принадлежности пользователю
    $stmt = $pdo->prepare("
        SELECT ca.*, cc.email 
        FROM cloudflare_accounts ca
        JOIN cloudflare_credentials cc ON ca.account_id = cc.id
        WHERE ca.id = ? AND ca.user_id = ?
    ");
    $stmt->execute([$domainId, $_SESSION['user_id']]);
    $domain = $stmt->fetch();
    
    if (!$domain) {
        echo json_encode(['success' => false, 'error' => 'Домен не найден или не принадлежит пользователю']);
        exit;
    }
    
    // Начинаем транзакцию для безопасного удаления
    $pdo->beginTransaction();
    
    try {
        // Удаляем домен
        $deleteStmt = $pdo->prepare("DELETE FROM cloudflare_accounts WHERE id = ? AND user_id = ?");
        $deleteResult = $deleteStmt->execute([$domainId, $_SESSION['user_id']]);
        
        if (!$deleteResult || $deleteStmt->rowCount() === 0) {
            throw new Exception('Не удалось удалить домен из базы данных');
        }
        
        // Логируем операцию
        $logStmt = $pdo->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
        $logStmt->execute([
            $_SESSION['user_id'], 
            'delete_domain', 
            "Домен удален: {$domain['domain']} (Email: {$domain['email']})"
        ]);
        
        // Подтверждаем транзакцию
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Домен {$domain['domain']} успешно удален",
            'domain_id' => $domainId,
            'domain' => $domain['domain']
        ]);
        
    } catch (Exception $e) {
        // Откатываем транзакцию при ошибке
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка при удалении: ' . $e->getMessage()]);
}
?>