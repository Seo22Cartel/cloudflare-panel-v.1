<?php
// Подключаем файлы без вывода ошибок
@require_once 'config.php';
@require_once 'functions.php';

// Проверяем, инициализирована ли сессия
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Логируем выход (только если функция существует и пользователь авторизован)
if (isset($_SESSION['user_id']) && function_exists('logAction') && isset($pdo)) {
    try {
        logAction($pdo, $_SESSION['user_id'], "Logout", "User logged out");
    } catch (Exception $e) {
        // Игнорируем ошибки логирования при выходе
    }
}

// Полностью уничтожаем сессию
$_SESSION = array();

// Удаляем сессионную cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Уничтожаем сессию
session_destroy();

// Определяем базовый путь для перенаправления
$scriptPath = dirname($_SERVER['SCRIPT_NAME']);
$basePath = rtrim($scriptPath, '/') . '/';

// Перенаправляем на страницу входа
header('Location: ' . $basePath . 'login.php');
exit;
?>