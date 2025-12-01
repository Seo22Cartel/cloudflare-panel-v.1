<?php
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$userId = $_SESSION['user_id'];

// В текущей БД хранится email;api_key. Токены/теги как понятия не хранятся, поэтому экспортируем email и api_key.
// Если в будущем появится хранение API Token/Tag, тут можно расширить вывод.

$tokens = listCloudflareApiTokens($pdo, $userId);

if (!empty($tokens)) {
    echo "name,token,tag,account_id,created_at\n";
    foreach ($tokens as $token) {
        printf("\"%s\",\"%s\",\"%s\",%d,\"%s\"\n",
            str_replace('"', '""', $token['name']),
            str_replace('"', '""', $token['token']),
            str_replace('"', '""', $token['tag']),
            $token['account_id'],
            $token['created_at']
        );
    }
} else {
    $stmt = $pdo->prepare("SELECT id, email, api_key, created_at, status FROM cloudflare_credentials WHERE user_id = ? ORDER BY email ASC");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    echo "# Cloudflare Accounts (Token/Tag export)\n\n";
    foreach ($rows as $row) {
        echo "ID: {$row['id']}\n";
        echo "Email: {$row['email']}\n";
        echo "API: {$row['api_key']}\n";
        echo "Status: {$row['status']}\n";
        echo "Created: {$row['created_at']}\n";
        echo "---\n";
    }
}