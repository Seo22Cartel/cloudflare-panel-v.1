<?php
$pageTitle = 'Page Rules';
require_once 'header.php';

$userId = $_SESSION['user_id'];

// Get domains for dropdown
$stmt = $pdo->prepare("
    SELECT ca.id, ca.domain, ca.zone_id, g.name as group_name 
    FROM cloudflare_accounts ca 
    LEFT JOIN groups g ON ca.group_id = g.id 
    WHERE ca.user_id = ? 
    ORDER BY ca.domain
");
$stmt->execute([$userId]);
$domains = $stmt->fetchAll();

include 'sidebar.php';
?>

<div class="content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-scroll me-2"></i>Page Rules</h1>
                <p class="text-muted mb-0">Быстрое применение типовых правил страниц</p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Quick Rules -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-bolt me-2"></i>Быстрые правила
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Выберите домен и нажмите на нужное правило для его применения.
                    </div>
                    
                    <div class="mb-4">
                        <label for="domainSelect" class="form-label">Выберите домен</label>
                        <select id="domainSelect" class="form-select">
                            <option value="">-- Выберите домен --</option>
                            <?php foreach ($domains as $domain): ?>
                                <option value="<?php echo $domain['id']; ?>" data-domain="<?php echo htmlspecialchars($domain['domain']); ?>">
                                    <?php echo htmlspecialchars($domain['domain']); ?>
                                    <?php echo $domain['group_name'] ? " ({$domain['group_name']})" : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-3">
                        <button class="btn btn-warning btn-lg" onclick="applyRule('cache_everything')">
                            <i class="fas fa-box me-2"></i>Cache Everything
                            <small class="d-block mt-1 opacity-75">Кешировать все содержимое</small>
                        </button>
                        
                        <button class="btn btn-primary btn-lg" onclick="applyRule('redirect_https')">
                            <i class="fas fa-lock me-2"></i>Always Use HTTPS
                            <small class="d-block mt-1 opacity-75">Перенаправление на HTTPS</small>
                        </button>
                        
                        <button class="btn btn-info btn-lg" onclick="applyRule('cache_static')">
                            <i class="fas fa-images me-2"></i>Cache Static Files
                            <small class="d-block mt-1 opacity-75">Кеш для статических файлов</small>
                        </button>
                        
                        <button class="btn btn-success btn-lg" onclick="applyRule('browser_cache')">
                            <i class="fas fa-clock me-2"></i>Browser Cache TTL
                            <small class="d-block mt-1 opacity-75">Установить время кеша браузера</small>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rule Templates Info -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>Описание правил
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6 class="fw-bold text-warning">
                            <i class="fas fa-box me-2"></i>Cache Everything
                        </h6>
                        <p class="text-muted small mb-0">
                            Кеширует все типы контента на edge-серверах Cloudflare, включая HTML.
                            Идеально для статических сайтов. Применяется к паттерну: <code>*domain.com/*</code>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="fw-bold text-primary">
                            <i class="fas fa-lock me-2"></i>Always Use HTTPS
                        </h6>
                        <p class="text-muted small mb-0">
                            Автоматически перенаправляет все HTTP запросы на HTTPS.
                            Повышает безопасность сайта. Применяется к: <code>http://*domain.com/*</code>
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="fw-bold text-info">
                            <i class="fas fa-images me-2"></i>Cache Static Files
                        </h6>
                        <p class="text-muted small mb-0">
                            Кеширует только статические файлы (изображения, CSS, JS).
                            Ускоряет загрузку без кеширования динамического контента.
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h6 class="fw-bold text-success">
                            <i class="fas fa-clock me-2"></i>Browser Cache TTL
                        </h6>
                        <p class="text-muted small mb-0">
                            Устанавливает время жизни кеша в браузере посетителя.
                            По умолчанию устанавливается на 1 месяц.
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Важно
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-0">
                        <ul class="mb-0 ps-3">
                            <li>Бесплатный план Cloudflare позволяет 3 Page Rules на домен</li>
                            <li>Правила применяются по порядку (первое совпадение)</li>
                            <li>Изменения могут вступить в силу через несколько минут</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Operation Log -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-terminal me-2"></i>Результаты операций</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="clearLog()">
                <i class="fas fa-eraser me-1"></i>Очистить
            </button>
        </div>
        <div class="card-body p-0">
            <div id="operationLog" class="bg-dark text-light p-3" style="min-height: 100px; max-height: 200px; overflow-y: auto; font-family: monospace; font-size: 0.85rem;">
                <div class="text-muted">Лог операций будет отображаться здесь...</div>
            </div>
        </div>
    </div>
</div>

<script>
function logMessage(message, type = 'info') {
    const log = document.getElementById('operationLog');
    const time = new Date().toLocaleTimeString();
    const colors = {
        info: '#60a5fa',
        success: '#34d399',
        error: '#f87171',
        warning: '#fbbf24'
    };
    log.innerHTML += `<div style="color: ${colors[type]}">[${time}] ${message}</div>`;
    log.scrollTop = log.scrollHeight;
}

function clearLog() {
    document.getElementById('operationLog').innerHTML = '<div class="text-muted">Лог очищен...</div>';
}

async function applyRule(ruleType) {
    const select = document.getElementById('domainSelect');
    const domainId = select.value;
    
    if (!domainId) {
        showToast('Выберите домен', 'warning');
        return;
    }
    
    const domainName = select.options[select.selectedIndex].dataset.domain;
    const ruleNames = {
        'cache_everything': 'Cache Everything',
        'redirect_https': 'Always Use HTTPS',
        'cache_static': 'Cache Static Files',
        'browser_cache': 'Browser Cache TTL'
    };
    
    logMessage(`Применяем правило "${ruleNames[ruleType]}" для ${domainName}...`, 'info');
    
    try {
        const form = new URLSearchParams();
        form.append('domain_id', domainId);
        form.append('rule_type', ruleType);
        
        const response = await fetch('page_rules_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: form.toString()
        });
        
        const data = await response.json();
        
        if (data.success) {
            logMessage(`✓ Правило "${ruleNames[ruleType]}" успешно применено`, 'success');
            showToast('Правило успешно применено', 'success');
        } else {
            logMessage(`✗ Ошибка: ${data.error || 'Неизвестная ошибка'}`, 'error');
            showToast('Ошибка: ' + (data.error || 'unknown'), 'error');
        }
    } catch (err) {
        logMessage(`✗ Ошибка сети: ${err.message}`, 'error');
        showToast('Ошибка сети', 'error');
    }
}
</script>

<?php include 'footer.php'; ?>