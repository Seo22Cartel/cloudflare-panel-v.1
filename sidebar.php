<?php
// Get current page for highlighting active link
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo BASE_PATH; ?>dashboard.php" class="sidebar-brand">
            <i class="fas fa-cloud"></i>
            <span>Cloudflare Panel</span>
        </a>
    </div>
    
    <div class="sidebar-menu">
        <!-- Main Navigation -->
        <div class="sidebar-heading">Главное</div>
        
        <a href="<?php echo BASE_PATH; ?>dashboard.php" class="sidebar-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i>
            <span>Дашборд</span>
        </a>
        
        <a href="<?php echo BASE_PATH; ?>mass_operations.php" class="sidebar-link <?php echo $currentPage == 'mass_operations.php' ? 'active' : ''; ?>">
            <i class="fas fa-layer-group"></i>
            <span>Массовые операции</span>
        </a>
        
        <a href="<?php echo BASE_PATH; ?>queue_dashboard.php" class="sidebar-link <?php echo $currentPage == 'queue_dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-tasks"></i>
            <span>Очередь задач</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <!-- Security -->
        <div class="sidebar-heading">Безопасность</div>
        
        <a href="<?php echo BASE_PATH; ?>security_rules_manager.php" class="sidebar-link <?php echo $currentPage == 'security_rules_manager.php' ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i>
            <span>Правила безопасности</span>
        </a>
        
        <a href="<?php echo BASE_PATH; ?>smart_waf.php" class="sidebar-link <?php echo $currentPage == 'smart_waf.php' ? 'active' : ''; ?>">
            <i class="fas fa-robot"></i>
            <span>Smart WAF</span>
        </a>
        
        <a href="<?php echo BASE_PATH; ?>page_rules.php" class="sidebar-link <?php echo $currentPage == 'page_rules.php' ? 'active' : ''; ?>">
            <i class="fas fa-scroll"></i>
            <span>Page Rules</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <!-- SSL & DNS -->
        <div class="sidebar-heading">SSL & DNS</div>
        
        <a href="<?php echo BASE_PATH; ?>view_certificates.php" class="sidebar-link <?php echo $currentPage == 'view_certificates.php' ? 'active' : ''; ?>">
            <i class="fas fa-certificate"></i>
            <span>SSL Сертификаты</span>
        </a>
        
        <a href="<?php echo BASE_PATH; ?>whois.php" class="sidebar-link <?php echo $currentPage == 'whois.php' ? 'active' : ''; ?>">
            <i class="fas fa-id-card"></i>
            <span>WHOIS Домены</span>
        </a>
        
        <a href="<?php echo BASE_PATH; ?>cache_tools.php" class="sidebar-link <?php echo $currentPage == 'cache_tools.php' ? 'active' : ''; ?>">
            <i class="fas fa-broom"></i>
            <span>Очистка кеша</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <!-- Settings -->
        <div class="sidebar-heading">Настройки</div>
        
        <a href="<?php echo BASE_PATH; ?>proxies.php" class="sidebar-link <?php echo $currentPage == 'proxies.php' ? 'active' : ''; ?>">
            <i class="fas fa-server"></i>
            <span>Прокси</span>
        </a>
        
        <a href="<?php echo BASE_PATH; ?>logs.php" class="sidebar-link <?php echo $currentPage == 'logs.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i>
            <span>Логи</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <!-- Help & Documentation -->
        <div class="sidebar-heading">Справка</div>
        
        <a href="<?php echo BASE_PATH; ?>documentation.php" class="sidebar-link <?php echo $currentPage == 'documentation.php' ? 'active' : ''; ?>">
            <i class="fas fa-book"></i>
            <span>Документация</span>
        </a>
        
        <div class="sidebar-divider"></div>
        
        <!-- Account -->
        <a href="<?php echo BASE_PATH; ?>logout.php" class="sidebar-link text-danger">
            <i class="fas fa-sign-out-alt"></i>
            <span>Выход</span>
        </a>
    </div>
</div>