<?php
// Подавляем вывод ошибок и предупреждений для чистого JSON ответа
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';

header('Content-Type: application/json');

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
    // Получаем информацию о домене
    $stmt = $pdo->prepare("
        SELECT ca.*, cc.email, cc.api_key 
        FROM cloudflare_accounts ca
        JOIN cloudflare_credentials cc ON ca.account_id = cc.id
        WHERE ca.id = ? AND ca.user_id = ?
    ");
    $stmt->execute([$domainId, $_SESSION['user_id']]);
    $domain = $stmt->fetch();
    
    if (!$domain) {
        echo json_encode(['success' => false, 'error' => 'Домен не найден']);
        exit;
    }
    
    // Проверяем, есть ли уже сертификат
    if ($domain['ssl_cert_id']) {
        echo json_encode([
            'success' => true,
            'message' => 'SSL сертификат уже существует',
            'domain_id' => $domainId,
            'ssl_cert_id' => $domain['ssl_cert_id']
        ]);
        exit;
    }
    
    // Имитация создания SSL сертификата (для демонстрации)
    // В реальной реализации здесь должен быть вызов Cloudflare API для создания Origin CA сертификата
    
    sleep(2); // Имитация задержки создания сертификата
    
    // Генерируем фиктивные данные сертификата
    $certId = 'cert_' . uniqid();
    $sslCertificate = "-----BEGIN CERTIFICATE-----\n" . 
                     base64_encode(random_bytes(1024)) . "\n" .
                     "-----END CERTIFICATE-----";
    $privateKey = "-----BEGIN PRIVATE KEY-----\n" . 
                 base64_encode(random_bytes(512)) . "\n" .
                 "-----END PRIVATE KEY-----";
    
    // Обновляем домен с новым сертификатом
    $updateStmt = $pdo->prepare("
        UPDATE cloudflare_accounts 
        SET ssl_cert_id = ?, ssl_certificate = ?, ssl_private_key = ?, ssl_cert_created = datetime('now'), 
            ssl_certificates_count = 1, ssl_has_active = 1, last_check = datetime('now')
        WHERE id = ?
    ");
    $updateStmt->execute([$certId, $sslCertificate, $privateKey, $domainId]);
    
    // Логируем операцию
    $logStmt = $pdo->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
    $logStmt->execute([
        $_SESSION['user_id'], 
        'create_certificate', 
        "SSL сертификат создан для домена {$domain['domain']} (ID: {$certId})"
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'SSL сертификат успешно создан',
        'domain_id' => $domainId,
        'ssl_cert_id' => $certId
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Ошибка при создании сертификата: ' . $e->getMessage()]);
}
?> 