<?php
require_once 'config.php';
require_once 'functions.php';

// Send anti-bot HTTP headers
BotProtection::sendProtectionHeaders();

// Check if user is logged in (except for login and install pages)
$currentPage = basename($_SERVER['PHP_SELF']);
$publicPages = ['login.php', 'install.php', 'password_reset_advanced.php'];

if (!in_array($currentPage, $publicPages) && !isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Rate limit check after session is confirmed
if (isset($_SESSION['user_id'])) {
    BotProtection::protectWithRateLimit();
}

// Get current page for active menu highlighting
$activePage = $currentPage;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Anti-Bot/AI Crawler Meta Tags -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="googlebot-news" content="noindex, nofollow">
    <meta name="bingbot" content="noindex, nofollow">
    <meta name="msnbot" content="noindex, nofollow">
    <meta name="slurp" content="noindex, nofollow">
    <meta name="duckduckbot" content="noindex, nofollow">
    <meta name="baiduspider" content="noindex, nofollow">
    <meta name="yandex" content="noindex, nofollow">
    <meta name="google-extended" content="noindex, nofollow">
    <meta name="gptbot" content="noindex, nofollow">
    <meta name="ccbot" content="noindex, nofollow">
    <meta name="anthropic-ai" content="noindex, nofollow">
    <meta name="perplexitybot" content="noindex, nofollow">
    <meta name="facebookexternalhit" content="noindex">
    
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>Cloudflare Panel</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts - Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom Styles -->
    <link href="<?php echo BASE_PATH; ?>styles.css" rel="stylesheet">
    
    <!-- Page-specific styles -->
    <?php if (isset($pageStyles)): ?>
    <style><?php echo $pageStyles; ?></style>
    <?php endif; ?>
</head>
<body>
    <!-- Toast Container for Notifications -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;" id="toastContainer"></div>
    
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-dark d-md-none position-fixed" style="top: 10px; left: 10px; z-index: 1100;" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>