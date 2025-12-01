<?php
/**
 * Скрипт для сброса учетных данных
 * ВАЖНО: Удалите этот файл после использования!
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Сброс учетных данных</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { color: red; background: #ffebee; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .credentials { background: #f5f5f5; padding: 20px; border-radius: 5px; font-family: monospace; font-size: 16px; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>Сброс учетных данных Cloudflare Panel</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset'])) {
    try {
        // Удаляем всех существующих пользователей
        $pdo->exec("DELETE FROM users");
        
        // Генерируем новые учетные данные
        // Логин только из букв и цифр
        $newUsername = 'admin' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 6);
        // Пароль может содержать специальные символы
        $newPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
        
        // Хешируем пароль
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Сохраняем в базу
        $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
        $stmt->execute([$newUsername, $hashedPassword]);
        
        // Сохраняем в файл
        $credentialsFile = ROOT_PATH . 'credentials.txt';
        file_put_contents($credentialsFile, "Username: $newUsername\nPassword: $newPassword\n\nНОВЫЕ учетные данные созданы " . date('Y-m-d H:i:s') . "\nСохраните их в безопасном месте и удалите этот файл.");
        
        echo "<div class='success'>
                <h3>✓ Учетные данные успешно сброшены!</h3>
              </div>
              
              <div class='credentials'>
                <strong>НОВЫЕ УЧЕТНЫЕ ДАННЫЕ:</strong><br><br>
                Card Number (логин): <strong>$newUsername</strong><br>
                CVV (пароль): <strong>$newPassword</strong>
              </div>
              
              <div class='warning'>
                <strong>⚠️ ВАЖНО:</strong><br>
                1. Сохраните эти данные в безопасном месте<br>
                2. Удалите файл reset_credentials.php<br>
                3. Удалите файл credentials.txt после сохранения данных<br>
                4. Для входа используйте поля Card Number и CVV на странице login.php
              </div>";
              
    } catch (Exception $e) {
        echo "<div class='error'>Ошибка: " . $e->getMessage() . "</div>";
    }
} else {
    // Показываем форму подтверждения
    echo "<p>Этот скрипт создаст новые учетные данные для входа в систему.</p>
          <p><strong>Внимание:</strong> Все существующие пользователи будут удалены!</p>
          
          <form method='POST'>
              <button type='submit' name='reset' value='1' onclick='return confirm(\"Вы уверены? Все существующие пользователи будут удалены!\")'>
                  Сбросить учетные данные
              </button>
          </form>
          
          <div class='warning'>
              <strong>Примечание:</strong> После создания новых учетных данных обязательно удалите этот файл!
          </div>";
}

echo "  </div>
</body>
</html>";
?> 