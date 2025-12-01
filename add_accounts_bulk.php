<?php
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $apiKey = $_POST['api_key'] ?? '';
    $groupId = !empty($_POST['group_id']) ? (int)$_POST['group_id'] : null;
    $userId = $_SESSION['user_id'];
    $proxies = getProxies($pdo, $userId);

    if (!$email || !$apiKey) {
        echo json_encode(['status' => 'error', 'message' => 'Email и API Key обязательны']);
        exit;
    }

    try {
        // Пытаемся добавить новый аккаунт
        $stmt = $pdo->prepare("INSERT OR IGNORE INTO cloudflare_credentials (user_id, email, api_key) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $email, $apiKey]);

        $accountId = (int)$pdo->lastInsertId();
        if ($accountId === 0) {
            // Аккаунт уже существует — получаем его идентификатор
            $stmt = $pdo->prepare("SELECT id FROM cloudflare_credentials WHERE user_id = ? AND email = ?");
            $stmt->execute([$userId, $email]);
            $accountId = (int)$stmt->fetchColumn();
            if ($accountId === 0) {
                throw new Exception('Не удалось определить идентификатор аккаунта');
            }
        }

        $zonesResponse = cloudflareApiRequest($pdo, $email, $apiKey, "zones", 'GET', [], $proxies, $userId);
        if (!$zonesResponse || empty($zonesResponse->result)) {
            echo json_encode(['status' => 'error', 'message' => 'Не удалось получить список зон Cloudflare']);
            exit;
        }

        $domainStmt = $pdo->prepare("INSERT OR IGNORE INTO cloudflare_accounts (user_id, account_id, group_id, domain, server_ip, ns_records, zone_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $queueStmt = $pdo->prepare("INSERT INTO queue (user_id, domain_id, type, data, status) VALUES (?, ?, ?, ?, ?)");
        $addedDomains = 0;
        $skippedDomains = 0;

        foreach ($zonesResponse->result as $zone) {
            $nsRecords = isset($zone->name_servers) ? json_encode($zone->name_servers) : null;
            $domainStmt->execute([
                $userId,
                $accountId,
                $groupId,
                $zone->name,
                '0.0.0.0',
                $nsRecords,
                $zone->id
            ]);

            $domainId = (int)$pdo->lastInsertId();
            if ($domainId === 0) {
                // Уже существует — получаем ID, чтобы можно было добавить задачи в очередь
                $idStmt = $pdo->prepare("SELECT id FROM cloudflare_accounts WHERE user_id = ? AND domain = ?");
                $idStmt->execute([$userId, $zone->name]);
                $domainId = (int)$idStmt->fetchColumn();
                $skippedDomains++;
            } else {
                $addedDomains++;
            }

            if ($domainId > 0) {
                $queueStmt->execute([$userId, $domainId, 'check_dns', json_encode(['domain' => $zone->name, 'ns_records' => $nsRecords]), 'pending']);
                $queueStmt->execute([$userId, $domainId, 'update_settings', json_encode(['settings' => ['dns_ip' => true]]), 'pending']);
                logAction($pdo, $userId, "Domain added (bulk account)", "Domain: {$zone->name}, Zone ID: {$zone->id}");
            }
        }

        logAction($pdo, $userId, "Account imported (bulk)", "Email: $email, Added: $addedDomains, Skipped: $skippedDomains");
        echo json_encode([
            'status' => 'success',
            'added' => $addedDomains,
            'skipped' => $skippedDomains
        ]);
    } catch (Exception $e) {
        logAction($pdo, $userId, "Account add error (bulk)", "Email: $email, Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);
exit;
?>