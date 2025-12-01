<?php
/**
 * Скрипт для очистки файлов сброса пароля
 * Удаляет все файлы, связанные с операциями сброса
 */

require_once 'config.php';

$cleanup_files = [
    'reset_credentials.php',
    'password_reset_advanced.php',
    'cleanup_reset_files.php', // этот файл удалится последним
    'debug_login.php',
    'test_login_fix.php',
    'test_proxy.php',
    'test_mass_operations.php',
    'test_domain_fixes.php',
    'test_sync_comprehensive.php',
    'debug_zone_creation.php',
    'debug_ssl_status.php',
    'test_ssl_fix.php',
    'fix_ssl_sync_now.php',
    'sync_ssl_data.php',
    'NS_SERVERS_GUIDE.md',
    'dashboard.php.backup',
    'add_ns_column.php',
    'NS_STATUS_FIX.md',
    'PROXY_TROUBLESHOOTING.md',
    'MASS_OPERATIONS_FIX.md',
    'DOMAIN_FIXES.md',
    'SYNC_COMPREHENSIVE_GUIDE.md',
    'QUICK_SYNC_INSTRUCTIONS.md',
    'reset_log.txt',
    'check_db_structure.php',
    'fix_db_and_status.php',
    'STATUS_NS_FIX_GUIDE.md',
    'debug_ns_problem.php',
    'fix_ns_display.php',
    'NS_FINAL_FIX_GUIDE.md'
];

echo "<!DOCTYPE html>
<html>
<head>
    <title>Очистка файлов сброса</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: red; background: #ffebee; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #1976d2; background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
        button { background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin: 5px; }
        button:hover { background: #c82333; }
        .btn-safe { background: #28a745; }
        .btn-safe:hover { background: #218838; }
        .file-list { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🧹 Очистка файлов сброса пароля</h1>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cleanup_all') {
        echo "<h3>Результаты очистки:</h3>";
        
        // Удаляем файлы с учетными данными
        $credentials_pattern = ROOT_PATH . 'credentials*.txt';
        $credentials_files = glob($credentials_pattern);
        
        foreach ($credentials_files as $file) {
            if (file_exists($file)) {
                if (unlink($file)) {
                    echo "<div class='success'>✓ Удален: " . basename($file) . "</div>";
                } else {
                    echo "<div class='error'>✗ Ошибка удаления: " . basename($file) . "</div>";
                }
            }
        }
        
        // Удаляем системные файлы
        foreach ($cleanup_files as $file) {
            $filepath = ROOT_PATH . $file;
            if (file_exists($filepath)) {
                if ($file === 'cleanup_reset_files.php') {
                    // Этот файл удаляем последним
                    continue;
                }
                if (unlink($filepath)) {
                    echo "<div class='success'>✓ Удален: $file</div>";
                } else {
                    echo "<div class='error'>✗ Ошибка удаления: $file</div>";
                }
            } else {
                echo "<div class='info'>ℹ Файл не найден: $file</div>";
            }
        }
        
        echo "<div class='success'>
                <h4>Очистка завершена!</h4>
                <p>Все файлы сброса пароля удалены.</p>
                <p><strong>Примечание:</strong> Этот файл будет удален автоматически через 10 секунд.</p>
              </div>";
              
        // Самоуничтожение через JavaScript
        echo "<script>
                setTimeout(function() {
                    fetch('?selfdelete=1', {method: 'POST'})
                    .then(() => {
                        document.body.innerHTML = '<div style=\"text-align:center;padding:50px;\"><h2>✅ Очистка полностью завершена!</h2><p>Все файлы удалены.</p><a href=\"login.php\">Перейти к входу</a></div>';
                    });
                }, 10000);
              </script>";
        
    } elseif ($action === 'cleanup_credentials') {
        echo "<h3>Удаление файлов с учетными данными:</h3>";
        
        $credentials_pattern = ROOT_PATH . 'credentials*.txt';
        $credentials_files = glob($credentials_pattern);
        
        if (empty($credentials_files)) {
            echo "<div class='info'>ℹ Файлы с учетными данными не найдены.</div>";
        } else {
            foreach ($credentials_files as $file) {
                if (unlink($file)) {
                    echo "<div class='success'>✓ Удален: " . basename($file) . "</div>";
                } else {
                    echo "<div class='error'>✗ Ошибка удаления: " . basename($file) . "</div>";
                }
            }
        }
    }
    
} elseif (isset($_GET['selfdelete'])) {
    // Самоуничтожение
    $filepath = ROOT_PATH . 'cleanup_reset_files.php';
    if (file_exists($filepath)) {
        unlink($filepath);
    }
    exit('OK');
    
} else {
    // Показываем форму
    echo "<p>Этот скрипт поможет очистить все файлы, связанные со сбросом пароля.</p>";
    
    // Проверяем, какие файлы существуют
    echo "<div class='file-list'>
            <h4>Найденные файлы для удаления:</h4>";
    
    $found_files = false;
    
    // Проверяем системные файлы
    foreach ($cleanup_files as $file) {
        $filepath = ROOT_PATH . $file;
        if (file_exists($filepath)) {
            echo "<div>📄 $file</div>";
            $found_files = true;
        }
    }
    
    // Проверяем файлы с учетными данными
    $credentials_pattern = ROOT_PATH . 'credentials*.txt';
    $credentials_files = glob($credentials_pattern);
    foreach ($credentials_files as $file) {
        echo "<div>🔑 " . basename($file) . "</div>";
        $found_files = true;
    }
    
    if (!$found_files) {
        echo "<div>✅ Файлы для очистки не найдены</div>";
    }
    
    echo "</div>";
    
    if ($found_files) {
        echo "<h3>Выберите действие:</h3>
              
              <form method='POST' style='display: inline;'>
                  <input type='hidden' name='action' value='cleanup_credentials'>
                  <button type='submit' class='btn-safe'>
                      Удалить только файлы с учетными данными
                  </button>
              </form>
              
              <form method='POST' style='display: inline;'>
                  <input type='hidden' name='action' value='cleanup_all'>
                  <button type='submit' onclick='return confirm(\"Удалить ВСЕ файлы сброса, включая этот скрипт?\")'>
                      Удалить все файлы сброса
                  </button>
              </form>";
    }
    
    echo "<div class='info'>
            <h4>ℹ Информация:</h4>
            <ul>
                <li><strong>Безопасное удаление:</strong> Удаляет только файлы credentials*.txt</li>
                <li><strong>Полное удаление:</strong> Удаляет все скрипты сброса и файлы данных</li>
                <li>После полного удаления этот файл также будет удален</li>
            </ul>
          </div>";
}

echo "  </div>
</body>
</html>";
?> 