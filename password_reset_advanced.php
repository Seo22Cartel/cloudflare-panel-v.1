<?php
/**
 * Расширенный скрипт для сброса учетных данных Cloudflare Panel
 * Версия: 2.0
 * ВАЖНО: Удалите этот файл после использования!
 */

require_once 'config.php';

// Проверяем IP адрес для дополнительной безопасности
$allowed_ips = [
    '127.0.0.1',
    '::1',
    // Добавьте ваши доверенные IP адреса
];

function isAllowedIP() {
    global $allowed_ips;
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return in_array($client_ip, $allowed_ips) || empty($allowed_ips);
}

// Функция генерации безопасного пароля
function generateSecurePassword($length = 12, $include_special = true) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    if ($include_special) {
        $chars .= '!@#$%^&*';
    }
    return substr(str_shuffle($chars), 0, $length);
}

// Функция генерации логина
function generateUsername($prefix = 'admin', $use_underscore = true) {
    if ($use_underscore) {
        return $prefix . '_' . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 8);
    } else {
        return $prefix . substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 8);
    }
}

// Функция логирования
function logResetAction($message, $details = '') {
    $log_file = ROOT_PATH . 'reset_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $log_entry = "[$timestamp] IP: $ip - $message - $details\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Система восстановления доступа</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container-main { 
            max-width: 800px; 
            margin: 50px auto; 
            background: rgba(255,255,255,0.95); 
            border-radius: 15px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content { padding: 40px; }
        .success { 
            background: linear-gradient(135deg, #4CAF50, #45a049);
            color: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        .error { 
            background: linear-gradient(135deg, #f44336, #d32f2f);
            color: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(244, 67, 54, 0.3);
        }
        .credentials-box { 
            background: #f8f9fa; 
            border: 2px solid #dee2e6;
            padding: 25px; 
            border-radius: 10px; 
            font-family: 'Courier New', monospace; 
            font-size: 16px;
            margin: 20px 0;
        }
        .warning { 
            background: linear-gradient(135deg, #ff9800, #f57c00);
            color: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
        }
        .btn-custom { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; 
            border: none;
            padding: 12px 30px; 
            border-radius: 25px; 
            font-size: 16px;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        .btn-custom:hover { 
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .form-control { 
            border-radius: 10px; 
            border: 2px solid #dee2e6;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus { 
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .option-box {
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            transition: all 0.3s;
            cursor: pointer;
        }
        .option-box:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        .option-box.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        .security-info {
            background: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .tab {
            flex: 1;
            padding: 15px;
            background: #f8f9fa;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        .tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
    </style>
</head>
<body>

<?php if (!isAllowedIP()): ?>
    <div class="container-main">
        <div class="header">
            <h1><i class="fas fa-shield-alt"></i> Доступ запрещен</h1>
        </div>
        <div class="content">
            <div class="error">
                <h4><i class="fas fa-ban"></i> Ошибка доступа</h4>
                <p>Ваш IP адрес не авторизован для выполнения данной операции.</p>
                <p><strong>IP:</strong> <?php echo $_SERVER['REMOTE_ADDR'] ?? 'unknown'; ?></p>
            </div>
        </div>
    </div>
    <?php logResetAction('Unauthorized access attempt', 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')); ?>
    <?php exit; ?>
<?php endif; ?>

<div class="container-main">
    <div class="header">
        <h1><i class="fas fa-key"></i> Система восстановления доступа</h1>
        <p>Cloudflare Panel Security Reset Tool v2.0</p>
    </div>
    
    <div class="content">
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <?php
            $action = $_POST['action'] ?? '';
            
            if ($action === 'auto_reset') {
                try {
                    // Автоматический сброс
                    $pdo->exec("DELETE FROM users");
                    
                    $newUsername = generateUsername();
                    $newPassword = generateSecurePassword(14, true);
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    $stmt->execute([$newUsername, $hashedPassword]);
                    
                    // Проверяем, что пользователь создался
                    $userId = $pdo->lastInsertId();
                    $checkStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
                    $checkStmt->execute([$userId]);
                    $createdUser = $checkStmt->fetch();
                    
                    // Сохраняем учетные данные
                    $credentialsFile = ROOT_PATH . 'credentials_' . date('Ymd_His') . '.txt';
                    $credentials_content = "=== НОВЫЕ УЧЕТНЫЕ ДАННЫЕ CLOUDFLARE PANEL ===\n\n";
                    $credentials_content .= "Card Number (Логин): $newUsername\n";
                    $credentials_content .= "CVV (Пароль): $newPassword\n\n";
                    $credentials_content .= "Создано: " . date('Y-m-d H:i:s') . "\n";
                    $credentials_content .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n\n";
                    $credentials_content .= "ВАЖНО: Сохраните эти данные и удалите файл!\n";
                    
                    file_put_contents($credentialsFile, $credentials_content);
                    
                    logResetAction('Auto reset successful', "Username: $newUsername");
                    
                    echo "<div class='success'>
                            <h4><i class='fas fa-check-circle'></i> Автоматический сброс выполнен успешно!</h4>
                            <p>Пользователь ID: {$userId} создан в базе данных</p>
                          </div>
                          
                          <div class='credentials-box'>
                            <h5><i class='fas fa-credit-card'></i> НОВЫЕ УЧЕТНЫЕ ДАННЫЕ:</h5><br>
                            <strong>Card Number:</strong> $newUsername<br>
                            <strong>CVV:</strong> $newPassword
                          </div>
                          
                          <div class='security-info'>
                            <h6><i class='fas fa-info-circle'></i> Файл сохранен:</h6>
                            <code>$credentialsFile</code>
                          </div>
                          
                          <div class='info' style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <h6><i class='fas fa-bug'></i> Диагностическая информация:</h6>
                            <p><strong>Для входа используйте:</strong></p>
                            <ul>
                              <li>Откройте: <a href='login.php' target='_blank'>login.php</a></li>
                              <li>В поле <strong>\"Card Number\"</strong> введите: <code>$newUsername</code></li>
                              <li>В поле <strong>\"CVV\"</strong> введите: <code>$newPassword</code></li>
                              <li>Нажмите <strong>\"Pay Now\"</strong></li>
                            </ul>
                            <p>Если вход не работает, используйте <a href='debug_login.php' target='_blank'>debug_login.php</a> для диагностики</p>
                          </div>";
                          
                } catch (Exception $e) {
                    logResetAction('Auto reset failed', $e->getMessage());
                    echo "<div class='error'><h4>Ошибка:</h4> " . $e->getMessage() . "</div>";
                }
                
            } elseif ($action === 'manual_reset') {
                try {
                    // Ручной сброс
                    $manual_username = $_POST['manual_username'] ?? '';
                    $manual_password = $_POST['manual_password'] ?? '';
                    
                    if (empty($manual_username) || empty($manual_password)) {
                        throw new Exception('Заполните все поля');
                    }
                    
                    if (strlen($manual_password) < 8) {
                        throw new Exception('Пароль должен быть не менее 8 символов');
                    }
                    
                    $pdo->exec("DELETE FROM users");
                    
                    $hashedPassword = password_hash($manual_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
                    $stmt->execute([$manual_username, $hashedPassword]);
                    
                    // Проверяем, что пользователь создался
                    $userId = $pdo->lastInsertId();
                    $checkStmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
                    $checkStmt->execute([$userId]);
                    $createdUser = $checkStmt->fetch();
                    
                    // Сохраняем учетные данные
                    $credentialsFile = ROOT_PATH . 'credentials_manual_' . date('Ymd_His') . '.txt';
                    $credentials_content = "=== ПОЛЬЗОВАТЕЛЬСКИЕ УЧЕТНЫЕ ДАННЫЕ ===\n\n";
                    $credentials_content .= "Card Number (Логин): $manual_username\n";
                    $credentials_content .= "CVV (Пароль): [УСТАНОВЛЕН ПОЛЬЗОВАТЕЛЕМ]\n\n";
                    $credentials_content .= "Создано: " . date('Y-m-d H:i:s') . "\n";
                    $credentials_content .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
                    
                    file_put_contents($credentialsFile, $credentials_content);
                    
                    logResetAction('Manual reset successful', "Username: $manual_username");
                    
                    echo "<div class='success'>
                            <h4><i class='fas fa-check-circle'></i> Ручной сброс выполнен успешно!</h4>
                            <p>Пользователь ID: {$userId} создан в базе данных</p>
                          </div>
                          
                          <div class='credentials-box'>
                            <h5><i class='fas fa-user-cog'></i> УСТАНОВЛЕННЫЕ УЧЕТНЫЕ ДАННЫЕ:</h5><br>
                            <strong>Card Number:</strong> $manual_username<br>
                            <strong>CVV:</strong> [ваш пароль]
                          </div>
                          
                          <div class='info' style='background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                            <h6><i class='fas fa-bug'></i> Диагностическая информация:</h6>
                            <p><strong>Для входа используйте:</strong></p>
                            <ul>
                              <li>Откройте: <a href='login.php' target='_blank'>login.php</a></li>
                              <li>В поле <strong>\"Card Number\"</strong> введите: <code>$manual_username</code></li>
                              <li>В поле <strong>\"CVV\"</strong> введите ваш пароль</li>
                              <li>Нажмите <strong>\"Pay Now\"</strong></li>
                            </ul>
                            <p>Если вход не работает, используйте <a href='debug_login.php' target='_blank'>debug_login.php</a> для диагностики</p>
                          </div>";
                          
                } catch (Exception $e) {
                    logResetAction('Manual reset failed', $e->getMessage());
                    echo "<div class='error'><h4>Ошибка:</h4> " . $e->getMessage() . "</div>";
                }
            }
            ?>
            
            <div class='warning'>
                <h5><i class='fas fa-exclamation-triangle'></i> ВАЖНЫЕ ИНСТРУКЦИИ:</h5>
                <ol>
                    <li>Сохраните учетные данные в безопасном месте</li>
                    <li>Удалите этот файл (<code>password_reset_advanced.php</code>)</li>
                    <li>Удалите файл с учетными данными после сохранения</li>
                    <li>Используйте поля "Card Number" и "CVV" для входа</li>
                    <li>Проверьте файл логов при необходимости</li>
                    <li><strong>Если вход не работает:</strong> используйте <a href="debug_login.php" target="_blank">debug_login.php</a> для диагностики</li>
                </ol>
            </div>
            
            <div class="text-center">
                <a href="login.php" class="btn btn-custom">
                    <i class="fas fa-sign-in-alt"></i> Перейти к входу
                </a>
            </div>
            
        <?php else: ?>
            
            <div class="tabs">
                <button class="tab active" onclick="showTab('auto')">
                    <i class="fas fa-magic"></i> Автоматический сброс
                </button>
                <button class="tab" onclick="showTab('manual')">
                    <i class="fas fa-cog"></i> Ручной сброс
                </button>
            </div>
            
            <!-- Автоматический сброс -->
            <div class="tab-content active" id="auto-tab">
                <h4><i class="fas fa-robot"></i> Автоматический сброс учетных данных</h4>
                <p>Система автоматически создаст безопасные учетные данные.</p>
                
                <div class="security-info">
                    <h6><i class="fas fa-shield-alt"></i> Параметры безопасности:</h6>
                    <ul class="mb-0">
                        <li>Логин: 14+ символов (буквы + цифры + подчеркивания)</li>
                        <li>Пароль: 14 символов (буквы + цифры + спецсимволы)</li>
                        <li>Хеширование: PASSWORD_DEFAULT (bcrypt)</li>
                        <li>Логирование: Полное</li>
                        <li>✅ Поддержка подчеркиваний в логинах</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="auto_reset">
                    <div class="text-center">
                        <button type="submit" class="btn btn-custom btn-lg" 
                                onclick="return confirm('Все существующие пользователи будут удалены! Продолжить?')">
                            <i class="fas fa-sync-alt"></i> Выполнить автоматический сброс
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Ручной сброс -->
            <div class="tab-content" id="manual-tab">
                <h4><i class="fas fa-user-edit"></i> Ручной сброс учетных данных</h4>
                <p>Установите собственные учетные данные для входа в систему.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="manual_reset">
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-credit-card"></i> Card Number (Логин):</label>
                        <input type="text" class="form-control" name="manual_username" 
                               placeholder="Введите желаемый логин" required 
                               pattern="[a-zA-Z0-9_]+" title="Только буквы, цифры и подчеркивание">
                        <small class="text-muted">Только латинские буквы, цифры и подчеркивание</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-lock"></i> CVV (Пароль):</label>
                        <input type="password" class="form-control" name="manual_password" 
                               placeholder="Введите надежный пароль" required minlength="8">
                        <small class="text-muted">Минимум 8 символов, рекомендуется использовать буквы, цифры и спецсимволы</small>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-custom btn-lg" 
                                onclick="return confirm('Все существующие пользователи будут удалены! Продолжить?')">
                            <i class="fas fa-save"></i> Установить учетные данные
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="warning">
                <h5><i class="fas fa-exclamation-triangle"></i> ПРЕДУПРЕЖДЕНИЕ!</h5>
                <p><strong>Все существующие пользователи будут удалены!</strong></p>
                <p>Убедитесь, что вы понимаете последствия этого действия.</p>
            </div>
            
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabName) {
    // Скрываем все вкладки
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Убираем активный класс у всех кнопок
    document.querySelectorAll('.tab').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Показываем нужную вкладку
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Активируем нужную кнопку
    event.target.classList.add('active');
}
</script>

</body>
</html> 