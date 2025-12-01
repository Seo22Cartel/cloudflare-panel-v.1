<?php
/**
 * Security Rules Manager
 * Управление правилами безопасности и блокировками
 */

require_once 'header.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Получаем группы и домены
$groupsStmt = $pdo->prepare("SELECT * FROM groups WHERE user_id = ? ORDER BY name");
$groupsStmt->execute([$userId]);
$groups = $groupsStmt->fetchAll();

$domainsStmt = $pdo->prepare("
    SELECT ca.*, g.name as group_name 
    FROM cloudflare_accounts ca 
    LEFT JOIN groups g ON ca.group_id = g.id 
    WHERE ca.user_id = ? 
    ORDER BY ca.domain
");
$domainsStmt->execute([$userId]);
$domains = $domainsStmt->fetchAll();

// Получаем статистику блокировок
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT domain_id) as protected_domains,
        SUM(CASE WHEN rule_type = 'bad_bot' THEN 1 ELSE 0 END) as bot_rules,
        SUM(CASE WHEN rule_type = 'ip_block' THEN 1 ELSE 0 END) as ip_rules,
        SUM(CASE WHEN rule_type = 'geo_block' THEN 1 ELSE 0 END) as geo_rules,
        SUM(CASE WHEN rule_type = 'referrer_only' THEN 1 ELSE 0 END) as referrer_rules
    FROM security_rules 
    WHERE user_id = ?
");
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch();
?>

<?php include 'sidebar.php'; ?>

<div class="content">
    <!-- Заголовок -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Security Manager</h2>
            <p class="text-muted mb-0">Управление правилами безопасности и блокировками</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Назад
        </a>
    </div>

    <!-- Статистика -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card bg-gradient-primary">
                <div class="icon"><i class="fas fa-shield-alt"></i></div>
                <div class="info">
                    <h3><?php echo $stats['protected_domains'] ?? 0; ?></h3>
                    <p>Защищено доменов</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-gradient-warning">
                <div class="icon"><i class="fas fa-robot"></i></div>
                <div class="info">
                    <h3><?php echo $stats['bot_rules'] ?? 0; ?></h3>
                    <p>Правил ботов</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-gradient-danger">
                <div class="icon"><i class="fas fa-ban"></i></div>
                <div class="info">
                    <h3><?php echo $stats['ip_rules'] ?? 0; ?></h3>
                    <p>IP блокировок</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-gradient-info">
                <div class="icon"><i class="fas fa-globe"></i></div>
                <div class="info">
                    <h3><?php echo $stats['geo_rules'] ?? 0; ?></h3>
                    <p>Гео правил</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Навигация по табам -->
    <div class="card mb-4">
        <div class="card-header p-0 border-bottom-0">
            <ul class="nav nav-tabs card-header-tabs m-0" id="securityTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-3 px-4 border-top-0 border-start-0" id="bot-blocker-tab" data-bs-toggle="tab" data-bs-target="#bot-blocker" type="button">
                        <i class="fas fa-robot me-2"></i>Блокировка ботов
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-4 border-top-0" id="ip-blocker-tab" data-bs-toggle="tab" data-bs-target="#ip-blocker" type="button">
                        <i class="fas fa-ban me-2"></i>Блокировка IP
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-4 border-top-0" id="geo-blocker-tab" data-bs-toggle="tab" data-bs-target="#geo-blocker" type="button">
                        <i class="fas fa-globe me-2"></i>Геоблокировка
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-4 border-top-0" id="referrer-only-tab" data-bs-toggle="tab" data-bs-target="#referrer-only" type="button">
                        <i class="fas fa-search me-2"></i>Только поисковики
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-3 px-4 border-top-0 border-end-0" id="worker-manager-tab" data-bs-toggle="tab" data-bs-target="#worker-manager" type="button">
                        <i class="fas fa-code me-2"></i>Cloudflare Workers
                    </button>
                </li>
            </ul>
        </div>
        
        <div class="card-body p-4">
            <div class="tab-content" id="securityTabsContent">
                <!-- Блокировка ботов -->
                <div class="tab-pane fade show active" id="bot-blocker" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3 text-primary">Настройки блокировки</h5>
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="blockAllBots">
                                        <label class="form-check-label fw-bold" for="blockAllBots">
                                            Блокировать все известные плохие боты
                                        </label>
                                        <small class="text-muted d-block">Рекомендуется для большинства сайтов</small>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="blockSpamReferrers">
                                        <label class="form-check-label" for="blockSpamReferrers">Блокировать спам-реферреры</label>
                                    </div>
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="blockVulnScanners">
                                        <label class="form-check-label" for="blockVulnScanners">Блокировать сканеры уязвимостей</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="blockMalware">
                                        <label class="form-check-label" for="blockMalware">Блокировать malware/adware</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3 text-primary">Область применения</h5>
                            <div class="mb-3">
                                <label class="form-label">Применить к:</label>
                                <select class="form-select" id="botBlockerScope">
                                    <option value="all">Все домены</option>
                                    <option value="group">Выбранная группа</option>
                                    <option value="selected">Выбранные домены</option>
                                </select>
                            </div>
                            
                            <div id="botBlockerGroup" style="display: none;" class="mb-3">
                                <select class="form-select">
                                    <option value="">Выберите группу</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="botBlockerDomains" style="display: none; max-height: 200px; overflow-y: auto;" class="border rounded p-2 mb-3 bg-white">
                                <?php foreach ($domains as $domain): ?>
                                    <div class="form-check">
                                        <input class="form-check-input domain-checkbox" type="checkbox" value="<?php echo $domain['id']; ?>">
                                        <label class="form-check-label"><?php echo htmlspecialchars($domain['domain']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button class="btn btn-primary w-100" onclick="applyBotBlocker()">
                                <i class="fas fa-shield-alt me-2"></i>Применить защиту
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Блокировка IP -->
                <div class="tab-pane fade" id="ip-blocker" role="tabpanel">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3 text-danger">Список IP для блокировки</h5>
                            <textarea class="form-control mb-3" rows="8" id="ipBlockList" placeholder="192.168.1.1&#10;10.0.0.0/8&#10;Один IP или CIDR диапазон на строку"></textarea>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="importKnownBadIps">
                                <label class="form-check-label" for="importKnownBadIps">
                                    Импортировать известные вредоносные IP
                                </label>
                            </div>
                            <small class="text-muted">Поддерживается формат: IP (192.168.1.1) или CIDR (10.0.0.0/8)</small>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3 text-primary">Область применения</h5>
                            <div class="mb-3">
                                <label class="form-label">Применить к:</label>
                                <select class="form-select" id="ipBlockerScope">
                                    <option value="all">Все домены (<?php echo count($domains); ?>)</option>
                                    <option value="group">Выбранная группа</option>
                                    <option value="selected">Выбранные домены</option>
                                </select>
                            </div>
                            
                            <div id="ipBlockerGroup" style="display: none;" class="mb-3">
                                <label class="form-label">Выберите группу:</label>
                                <select class="form-select">
                                    <option value="">-- Выберите группу --</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="ipBlockerDomains" style="display: none; max-height: 200px; overflow-y: auto;" class="border rounded p-2 mb-3 bg-white">
                                <?php foreach ($domains as $domain): ?>
                                    <div class="form-check">
                                        <input class="form-check-input domain-checkbox" type="checkbox" value="<?php echo $domain['id']; ?>" data-group="<?php echo $domain['group_id']; ?>">
                                        <label class="form-check-label"><?php echo htmlspecialchars($domain['domain']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button class="btn btn-danger w-100" onclick="applyIPBlocker()">
                                <i class="fas fa-ban me-2"></i>Заблокировать IP
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Геоблокировка -->
                <div class="tab-pane fade" id="geo-blocker" role="tabpanel">
                    <div class="row">
                        <!-- Список всех стран -->
                        <div class="col-md-4">
                            <h5 class="mb-3 text-info"><i class="fas fa-globe me-2"></i>Все страны</h5>
                            <input type="text" class="form-control mb-2" id="countrySearch" placeholder="🔍 Поиск страны...">
                            <div class="border rounded p-2 bg-white" id="countryList" style="max-height: 350px; overflow-y: auto;">
                                <!-- JS заполнит список стран -->
                            </div>
                            <small class="text-muted mt-1 d-block">Нажмите на страну, затем выберите куда добавить</small>
                        </div>
                        
                        <!-- Whitelist - Разрешенные страны -->
                        <div class="col-md-4">
                            <div class="card border-success h-100">
                                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-check-circle me-2"></i>Whitelist (Разрешить)</h6>
                                    <span id="whitelistCount" class="badge bg-light text-success">0</span>
                                </div>
                                <div class="card-body p-2">
                                    <div id="whitelistCountries" class="country-drop-zone" style="min-height: 200px; max-height: 300px; overflow-y: auto;">
                                        <p class="text-muted text-center small mb-0 empty-msg">Перетащите страны сюда или нажмите кнопку ➕</p>
                                    </div>
                                </div>
                                <div class="card-footer bg-light p-2">
                                    <button class="btn btn-sm btn-outline-success w-100" onclick="addSelectedToWhitelist()">
                                        <i class="fas fa-plus me-1"></i>Добавить выбранные в Whitelist
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Blacklist - Заблокированные страны -->
                        <div class="col-md-4">
                            <div class="card border-danger h-100">
                                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><i class="fas fa-ban me-2"></i>Blacklist (Запретить)</h6>
                                    <span id="blacklistCount" class="badge bg-light text-danger">0</span>
                                </div>
                                <div class="card-body p-2">
                                    <div id="blacklistCountries" class="country-drop-zone" style="min-height: 200px; max-height: 300px; overflow-y: auto;">
                                        <p class="text-muted text-center small mb-0 empty-msg">Перетащите страны сюда или нажмите кнопку ➕</p>
                                    </div>
                                </div>
                                <div class="card-footer bg-light p-2">
                                    <button class="btn btn-sm btn-outline-danger w-100" onclick="addSelectedToBlacklist()">
                                        <i class="fas fa-plus me-1"></i>Добавить выбранные в Blacklist
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Область применения и кнопка -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Настройки применения</h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Режим работы:</label>
                                        <div class="btn-group w-100">
                                            <input type="radio" class="btn-check" name="geoApplyMode" id="geoApplyWhitelist" value="whitelist" checked>
                                            <label class="btn btn-outline-success" for="geoApplyWhitelist">
                                                <i class="fas fa-check-circle me-1"></i>Применить Whitelist
                                            </label>
                                            <input type="radio" class="btn-check" name="geoApplyMode" id="geoApplyBlacklist" value="blacklist">
                                            <label class="btn btn-outline-danger" for="geoApplyBlacklist">
                                                <i class="fas fa-ban me-1"></i>Применить Blacklist
                                            </label>
                                            <input type="radio" class="btn-check" name="geoApplyMode" id="geoApplyBoth" value="both">
                                            <label class="btn btn-outline-primary" for="geoApplyBoth">
                                                <i class="fas fa-list me-1"></i>Оба правила
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-1">
                                            <strong>Whitelist:</strong> разрешить ТОЛЬКО из этих стран<br>
                                            <strong>Blacklist:</strong> запретить из этих стран<br>
                                            <strong>Оба:</strong> создать 2 отдельных правила
                                        </small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Применить к доменам:</label>
                                        <select class="form-select" id="geoBlockerScope">
                                            <option value="all">Все домены (<?php echo count($domains); ?>)</option>
                                            <option value="group">Выбранная группа</option>
                                            <option value="selected">Выбранные домены</option>
                                        </select>
                                    </div>
                                    
                                    <div id="geoBlockerGroup" style="display: none;" class="mb-3">
                                        <select class="form-select">
                                            <option value="">-- Выберите группу --</option>
                                            <?php foreach ($groups as $group): ?>
                                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div id="geoBlockerDomains" style="display: none; max-height: 150px; overflow-y: auto;" class="border rounded p-2 bg-white">
                                        <?php foreach ($domains as $domain): ?>
                                            <div class="form-check">
                                                <input class="form-check-input domain-checkbox" type="checkbox" value="<?php echo $domain['id']; ?>" data-group="<?php echo $domain['group_id']; ?>">
                                                <label class="form-check-label"><?php echo htmlspecialchars($domain['domain']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Предпросмотр правил</h6>
                                </div>
                                <div class="card-body">
                                    <div id="geoRulesPreview" class="bg-dark text-success p-3 rounded font-monospace small" style="max-height: 180px; overflow-y: auto;">
                                        <div>// Выберите страны для просмотра правил</div>
                                    </div>
                                </div>
                                <div class="card-footer">
                                    <button class="btn btn-info w-100 text-white" onclick="applyGeoBlocker()">
                                        <i class="fas fa-globe me-2"></i>Применить гео-правила в Cloudflare
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Только поисковики -->
                <div class="tab-pane fade" id="referrer-only" role="tabpanel">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Внимание!</strong> Эта настройка заблокирует прямой доступ к сайту. Посетители смогут заходить только с поисковых систем.
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Разрешенные источники</h5>
                            <div class="card bg-light border-0 mb-3">
                                <div class="card-body">
                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="allowGoogle" checked><label class="form-check-label" for="allowGoogle">Google</label></div>
                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="allowYandex" checked><label class="form-check-label" for="allowYandex">Yandex</label></div>
                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="allowBing" checked><label class="form-check-label" for="allowBing">Bing</label></div>
                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="allowDuckDuckGo" checked><label class="form-check-label" for="allowDuckDuckGo">DuckDuckGo</label></div>
                                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="allowBaidu"><label class="form-check-label" for="allowBaidu">Baidu</label></div>
                                </div>
                            </div>
                            <label class="form-label">Дополнительные домены (по одному на строку)</label>
                            <textarea class="form-control mb-2" id="customReferrers" rows="3" placeholder="facebook.com&#10;twitter.com"></textarea>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="allowEmpty">
                                <label class="form-check-label" for="allowEmpty">Разрешить пустой Referer</label>
                            </div>
                            <label class="form-label">Исключения по URL (по одному на строку)</label>
                            <textarea class="form-control" id="referrerExceptions" rows="2" placeholder="/api/*&#10;/robots.txt"></textarea>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3">Действие при блокировке</h5>
                            <select class="form-select mb-3" id="referrerAction">
                                <option value="block">Блокировать (403)</option>
                                <option value="challenge">Challenge (Проверка)</option>
                                <option value="redirect">Редирект на страницу</option>
                            </select>
                            
                            <div id="customPageDiv" style="display: none;" class="mb-3">
                                <label class="form-label">URL для редиректа</label>
                                <input type="text" class="form-control" id="customPageUrl" placeholder="https://example.com/blocked">
                            </div>
                            
                            <h5 class="mb-3 text-primary">Область применения</h5>
                            <div class="mb-3">
                                <select class="form-select" id="referrerScope">
                                    <option value="all">Все домены (<?php echo count($domains); ?>)</option>
                                    <option value="group">Выбранная группа</option>
                                    <option value="selected">Выбранные домены</option>
                                </select>
                            </div>
                            
                            <div id="referrerGroup" style="display: none;" class="mb-3">
                                <select class="form-select">
                                    <option value="">-- Выберите группу --</option>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="referrerDomains" style="display: none; max-height: 150px; overflow-y: auto;" class="border rounded p-2 mb-3 bg-white">
                                <?php foreach ($domains as $domain): ?>
                                    <div class="form-check">
                                        <input class="form-check-input domain-checkbox" type="checkbox" value="<?php echo $domain['id']; ?>" data-group="<?php echo $domain['group_id']; ?>">
                                        <label class="form-check-label"><?php echo htmlspecialchars($domain['domain']); ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <button class="btn btn-warning w-100" onclick="applyReferrerOnly()">
                                <i class="fas fa-lock me-2"></i>Применить защиту
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Workers -->
                <div class="tab-pane fade" id="worker-manager" role="tabpanel">
                    <div class="row">
                        <!-- Выбор шаблона -->
                        <div class="col-md-3">
                            <h5 class="mb-3"><i class="fas fa-file-code me-2"></i>Шаблоны</h5>
                            <div class="list-group" id="workerTemplateList">
                                <button class="list-group-item list-group-item-action active" onclick="loadWorkerTemplateWithConfig('advanced-protection')">
                                    <h6 class="mb-1"><i class="fas fa-shield-alt me-2 text-primary"></i>Advanced Protection</h6>
                                    <small class="text-muted">Полная защита</small>
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplateWithConfig('bot-only')">
                                    <h6 class="mb-1"><i class="fas fa-robot me-2 text-warning"></i>Bot Blocker</h6>
                                    <small class="text-muted">Блокировка ботов</small>
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplateWithConfig('geo-only')">
                                    <h6 class="mb-1"><i class="fas fa-globe me-2 text-info"></i>Geo Blocker</h6>
                                    <small class="text-muted">Геоблокировка</small>
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplateWithConfig('rate-limit')">
                                    <h6 class="mb-1"><i class="fas fa-tachometer-alt me-2 text-danger"></i>Rate Limiting</h6>
                                    <small class="text-muted">Ограничение запросов</small>
                                </button>
                                <button class="list-group-item list-group-item-action" onclick="loadWorkerTemplateWithConfig('referrer-only')">
                                    <h6 class="mb-1"><i class="fas fa-search me-2 text-success"></i>Referrer Only</h6>
                                    <small class="text-muted">Только поисковики</small>
                                </button>
                            </div>
                            
                            <!-- Быстрые действия -->
                            <div class="card mt-3 border-info">
                                <div class="card-header bg-info text-white py-2">
                                    <small class="fw-bold"><i class="fas fa-bolt me-1"></i>Быстрые Пресеты</small>
                                </div>
                                <div class="card-body p-2">
                                    <button class="btn btn-sm btn-outline-success w-100 mb-1" onclick="applyPreset('rus-only')">
                                        🇷🇺 Только РФ + СНГ
                                    </button>
                                    <button class="btn btn-sm btn-outline-warning w-100 mb-1" onclick="applyPreset('block-bots')">
                                        🤖 Блокировать ботов
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger w-100" onclick="applyPreset('strict')">
                                        🔒 Строгая защита
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Редактор параметров -->
                        <div class="col-md-5">
                            <h5 class="mb-3"><i class="fas fa-sliders-h me-2"></i>Настройка параметров</h5>
                            <div class="card border-primary" id="workerConfigPanel">
                                <div class="card-header bg-primary text-white py-2">
                                    <span id="configPanelTitle">Выберите шаблон для настройки</span>
                                </div>
                                <div class="card-body p-3" style="max-height: 450px; overflow-y: auto;">
                                    <!-- Конфигурация будет динамически загружаться -->
                                    <div id="workerConfigContent">
                                        <p class="text-muted text-center">← Выберите шаблон Worker слева</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Превью и развёртывание -->
                        <div class="col-md-4">
                            <h5 class="mb-3"><i class="fas fa-eye me-2"></i>Превью кода</h5>
                            <div class="card bg-dark mb-3">
                                <div class="card-body p-2">
                                    <pre id="workerPreview" class="m-0 text-success" style="max-height: 180px; overflow: auto; font-size: 0.7rem;">// Выберите шаблон</pre>
                                </div>
                            </div>
                            
                            <!-- Область применения -->
                            <div class="card border-success">
                                <div class="card-header bg-success text-white py-2">
                                    <i class="fas fa-bullseye me-1"></i> Область применения
                                </div>
                                <div class="card-body p-3">
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Применить к:</label>
                                        <select class="form-select form-select-sm" id="workerScope">
                                            <option value="all">Все домены (<?php echo count($domains); ?>)</option>
                                            <option value="group">Выбранная группа</option>
                                            <option value="selected">Выбранные домены</option>
                                        </select>
                                    </div>
                                    
                                    <div id="workerGroup" style="display: none;" class="mb-3">
                                        <label class="form-label small fw-bold">Группа:</label>
                                        <select class="form-select form-select-sm" id="workerGroupSelect">
                                            <option value="">-- Выберите группу --</option>
                                            <?php foreach ($groups as $group): ?>
                                                <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?> (<?php echo count(array_filter($domains, fn($d) => $d['group_id'] == $group['id'])); ?>)</option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div id="workerDomains" style="display: none; max-height: 100px; overflow-y: auto;" class="border rounded p-2 mb-3 bg-white">
                                        <?php foreach ($domains as $domain): ?>
                                            <div class="form-check">
                                                <input class="form-check-input domain-checkbox" type="checkbox" value="<?php echo $domain['id']; ?>" data-group="<?php echo $domain['group_id']; ?>">
                                                <label class="form-check-label small"><?php echo htmlspecialchars($domain['domain']); ?></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Route Pattern:</label>
                                        <input type="text" class="form-control form-control-sm" id="workerRoute" placeholder="*example.com/*" value="*">
                                        <small class="text-muted">* = имя домена</small>
                                    </div>
                                    
                                    <button class="btn btn-success w-100" onclick="deployWorkerWithConfig()">
                                        <i class="fas fa-rocket me-2"></i>Развернуть Worker
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery должен быть до security_rules.js -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="security_rules.js?v=<?php echo time(); ?>"></script>

<?php include 'footer.php'; ?>
