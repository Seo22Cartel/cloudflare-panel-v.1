<?php
$pageTitle = 'Очистка кеша';
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
                <h1><i class="fas fa-broom me-2"></i>Очистка кеша</h1>
                <p class="text-muted mb-0">Управление кешем Cloudflare для ваших доменов</p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Single Domain Cache -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-globe me-2"></i>Очистка кеша домена
                </div>
                <div class="card-body">
                    <form id="singleCacheForm">
                        <div class="mb-3">
                            <label for="domainSelect" class="form-label">Выберите домен</label>
                            <select id="domainSelect" class="form-select" required>
                                <option value="">-- Выберите домен --</option>
                                <?php foreach ($domains as $domain): ?>
                                    <option value="<?php echo $domain['id']; ?>">
                                        <?php echo htmlspecialchars($domain['domain']); ?>
                                        <?php echo $domain['group_name'] ? " ({$domain['group_name']})" : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-danger" onclick="purgeEverything()">
                                <i class="fas fa-trash-alt me-1"></i>Очистить весь кеш
                            </button>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <h6 class="mb-3">Очистка по URL</h6>
                    <form id="urlCacheForm">
                        <div class="mb-3">
                            <label for="purgeUrls" class="form-label">URL для очистки</label>
                            <textarea id="purgeUrls" class="form-control" rows="4" placeholder="https://example.com/page1&#10;https://example.com/page2"></textarea>
                            <div class="form-text">Введите URL по одному на строку (максимум 30)</div>
                        </div>
                        <button type="button" class="btn btn-warning w-100" onclick="purgeByUrls()">
                            <i class="fas fa-link me-1"></i>Очистить по URL
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Bulk Cache Operations -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-layer-group me-2"></i>Массовая очистка
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Внимание!</strong> Массовая очистка кеша создаст нагрузку на API.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Выберите домены</label>
                        <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="selectAllDomains" onchange="toggleAllDomains(this)">
                                <label class="form-check-label fw-bold" for="selectAllDomains">
                                    Выбрать все (<?php echo count($domains); ?>)
                                </label>
                            </div>
                            <hr class="my-2">
                            <?php foreach ($domains as $domain): ?>
                                <div class="form-check">
                                    <input class="form-check-input domain-checkbox" type="checkbox" value="<?php echo $domain['id']; ?>" id="domain_<?php echo $domain['id']; ?>">
                                    <label class="form-check-label" for="domain_<?php echo $domain['id']; ?>">
                                        <?php echo htmlspecialchars($domain['domain']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-danger w-100" onclick="bulkPurgeCache()">
                        <i class="fas fa-broom me-1"></i>Очистить кеш выбранных доменов
                    </button>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>Информация о кеше
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="h4 text-primary"><?php echo count($domains); ?></div>
                            <small class="text-muted">Доменов</small>
                        </div>
                        <div class="col-4">
                            <div class="h4 text-success">30</div>
                            <small class="text-muted">URL за раз</small>
                        </div>
                        <div class="col-4">
                            <div class="h4 text-warning">1000</div>
                            <small class="text-muted">Запросов/день</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Results Log -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-terminal me-2"></i>Результаты операций</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="clearLog()">
                <i class="fas fa-eraser me-1"></i>Очистить
            </button>
        </div>
        <div class="card-body p-0">
            <div id="operationLog" class="bg-dark text-light p-3" style="min-height: 150px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.85rem;">
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

async function purgeEverything() {
    const domainId = document.getElementById('domainSelect').value;
    if (!domainId) {
        showToast('Выберите домен', 'warning');
        return;
    }
    
    logMessage('Очистка всего кеша...', 'info');
    
    try {
        const form = new URLSearchParams();
        form.append('domain_id', domainId);
        form.append('purge_everything', '1');
        
        const response = await fetch('cache_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: form.toString()
        });
        
        const data = await response.json();
        
        if (data.success) {
            logMessage('✓ Кеш успешно очищен', 'success');
            showToast('Кеш успешно очищен', 'success');
        } else {
            logMessage('✗ Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            showToast('Ошибка: ' + (data.error || 'unknown'), 'error');
        }
    } catch (err) {
        logMessage('✗ Ошибка сети: ' + err.message, 'error');
        showToast('Ошибка сети', 'error');
    }
}

async function purgeByUrls() {
    const domainId = document.getElementById('domainSelect').value;
    const urlsText = document.getElementById('purgeUrls').value.trim();
    
    if (!domainId) {
        showToast('Выберите домен', 'warning');
        return;
    }
    
    if (!urlsText) {
        showToast('Введите URL для очистки', 'warning');
        return;
    }
    
    const urls = urlsText.split('\n').filter(u => u.trim());
    if (urls.length > 30) {
        showToast('Максимум 30 URL за раз', 'warning');
        return;
    }
    
    logMessage(`Очистка ${urls.length} URL...`, 'info');
    
    try {
        const form = new URLSearchParams();
        form.append('domain_id', domainId);
        form.append('purge_urls', JSON.stringify(urls));
        
        const response = await fetch('cache_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: form.toString()
        });
        
        const data = await response.json();
        
        if (data.success) {
            logMessage(`✓ Очищено ${urls.length} URL`, 'success');
            showToast('URL успешно очищены', 'success');
        } else {
            logMessage('✗ Ошибка: ' + (data.error || 'Неизвестная ошибка'), 'error');
            showToast('Ошибка: ' + (data.error || 'unknown'), 'error');
        }
    } catch (err) {
        logMessage('✗ Ошибка сети: ' + err.message, 'error');
        showToast('Ошибка сети', 'error');
    }
}

function toggleAllDomains(checkbox) {
    document.querySelectorAll('.domain-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
}

async function bulkPurgeCache() {
    const selectedDomains = Array.from(document.querySelectorAll('.domain-checkbox:checked')).map(cb => cb.value);
    
    if (selectedDomains.length === 0) {
        showToast('Выберите хотя бы один домен', 'warning');
        return;
    }
    
    if (!confirm(`Очистить кеш для ${selectedDomains.length} доменов?`)) {
        return;
    }
    
    logMessage(`Начинаем очистку кеша для ${selectedDomains.length} доменов...`, 'info');
    
    let successCount = 0;
    let errorCount = 0;
    
    for (const domainId of selectedDomains) {
        try {
            const form = new URLSearchParams();
            form.append('domain_id', domainId);
            form.append('purge_everything', '1');
            
            const response = await fetch('cache_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: form.toString()
            });
            
            const data = await response.json();
            
            if (data.success) {
                successCount++;
                logMessage(`✓ Домен ID ${domainId} - кеш очищен`, 'success');
            } else {
                errorCount++;
                logMessage(`✗ Домен ID ${domainId} - ${data.error || 'ошибка'}`, 'error');
            }
        } catch (err) {
            errorCount++;
            logMessage(`✗ Домен ID ${domainId} - ошибка сети`, 'error');
        }
        
        // Small delay to avoid rate limiting
        await new Promise(r => setTimeout(r, 200));
    }
    
    logMessage(`Завершено: ${successCount} успешно, ${errorCount} ошибок`, successCount > 0 ? 'success' : 'error');
    showToast(`Очищено: ${successCount} из ${selectedDomains.length}`, successCount > 0 ? 'success' : 'error');
}
</script>

<?php include 'footer.php'; ?>