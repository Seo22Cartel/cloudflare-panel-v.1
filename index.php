<?php
// Проверяем, установлена ли система
if (!file_exists('cloudflare_panel.db')) {
    // Если нет, перенаправляем на установку
    header('Location: install.php');
} else {
    // Если да, перенаправляем на логин
    header('Location: login.php');
}
exit;
?> 