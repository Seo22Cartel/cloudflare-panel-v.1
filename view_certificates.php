<?php
$pageTitle = 'SSL Сертификаты';
require_once 'header.php';

$userId = $_SESSION['user_id'];

// Get groups
$groupStmt = $pdo->prepare("SELECT * FROM groups WHERE user_id = ?");
$groupStmt->execute([$userId]);
$groups = $groupStmt->fetchAll();

// Get accounts
$accountStmt = $pdo->prepare("SELECT * FROM cloudflare_credentials WHERE user_id = ?");
$accountStmt->execute([$userId]);
$accounts = $accountStmt->fetchAll();

// Get all domains with certificates
$stmt = $pdo->prepare("
    SELECT ca.id, ca.domain, ca.ssl_cert_id, ca.ssl_certificate, ca.ssl_private_key, ca.ssl_cert_created,
           ca.ssl_certificates_count, ca.ssl_has_active, ca.ssl_expires_soon, ca.ssl_nearest_expiry,
           cc.email, g.name AS group_name
    FROM cloudflare_accounts ca 
    JOIN cloudflare_credentials cc ON ca.account_id = cc.id 
    LEFT JOIN groups g ON ca.group_id = g.id 
    WHERE ca.user_id = ? 
    ORDER BY CASE WHEN ca.ssl_cert_id IS NOT NULL THEN 0 ELSE 1 END, ca.domain ASC
");
$stmt->execute([$userId]);
$allDomains = $stmt->fetchAll();

// Separate domains by certificate status
$domainsWithCerts = [];
$domainsWithoutCerts = [];

foreach ($allDomains as $domain) {
    if ($domain['ssl_cert_id'] && $domain['ssl_certificate'] && $domain['ssl_private_key']) {
        $domainsWithCerts[] = $domain;
    } else {
        $domainsWithoutCerts[] = $domain;
    }
}

include 'sidebar.php';
?>

<style>
.cert-container {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    margin-bottom: 15px;
    overflow: hidden;
    transition: var(--transition);
}

.cert-container:hover {
    box-shadow: var(--shadow-md);
    border-color: var(--primary);
}

.cert-header {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    padding: 15px 20px;
    cursor: pointer;
}

.cert-content {
    display: none;
    padding: 20px;
}

.cert-content.show {
    display: block;
}

.cert-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.cert-section {
    background: var(--gray-50);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.cert-section-header {
    background: var(--gray-200);
    padding: 10px 15px;
    font-weight: 600;
    font-size: 0.9rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.cert-code {
    background: var(--gray-800);
    color: #e2e8f0;
    font-family: 'Courier New', monospace;
    font-size: 0.75rem;
    line-height: 1.3;
    padding: 15px;
    margin: 0;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-all;
}

.no-cert-item {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.3);
    border-radius: var(--border-radius);
    padding: 15px 20px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

@media (max-width: 768px) {
    .cert-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-certificate me-2"></i>SSL Сертификаты</h1>
                <p class="text-muted mb-0">Управление Origin CA сертификатами</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success btn-sm" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-plus me-1"></i>Создать
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="location.reload()">
                    <i class="fas fa-sync me-1"></i>Обновить
                </button>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="quick-stats">
        <div class="card stat-card bg-gradient-success">
            <div class="icon"><i class="fas fa-shield-alt"></i></div>
            <div class="info">
                <h3><?php echo count($domainsWithCerts); ?></h3>
                <p>С сертификатами</p>
            </div>
        </div>
        <div class="card stat-card bg-gradient-warning">
            <div class="icon"><i class="fas fa-exclamation"></i></div>
            <div class="info">
                <h3><?php echo count($domainsWithoutCerts); ?></h3>
                <p>Без сертификатов</p>
            </div>
        </div>
        <div class="card stat-card bg-gradient-primary">
            <div class="icon"><i class="fas fa-globe"></i></div>
            <div class="info">
                <h3><?php echo count($allDomains); ?></h3>
                <p>Всего доменов</p>
            </div>
        </div>
        <div class="card stat-card bg-gradient-info">
            <div class="icon"><i class="fas fa-percentage"></i></div>
            <div class="info">
                <h3><?php echo count($allDomains) > 0 ? round((count($domainsWithCerts) / count($allDomains)) * 100) : 0; ?>%</h3>
                <p>Покрытие</p>
            </div>
        </div>
    </div>
    
    <!-- Filters & Controls -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" id="searchDomains" class="form-control" placeholder="Поиск доменов...">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="filter" id="filter-all" value="all" checked>
                        <label class="btn btn-outline-primary" for="filter-all">Все</label>
                        <input type="radio" class="btn-check" name="filter" id="filter-with" value="with">
                        <label class="btn btn-outline-success" for="filter-with">С сертификатами</label>
                        <input type="radio" class="btn-check" name="filter" id="filter-without" value="without">
                        <label class="btn btn-outline-warning" for="filter-without">Без сертификатов</label>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <?php if (!empty($domainsWithCerts)): ?>
                    <div class="btn-group">
                        <button class="btn btn-outline-primary btn-sm" onclick="expandAll()">
                            <i class="fas fa-expand-alt me-1"></i>Развернуть
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="collapseAll()">
                            <i class="fas fa-compress-alt me-1"></i>Свернуть
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="copyAllCertificates()">
                            <i class="fas fa-copy me-1"></i>CRT
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="copyAllKeys()">
                            <i class="fas fa-key me-1"></i>KEY
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Domains with Certificates -->
    <?php if (!empty($domainsWithCerts)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-shield-alt text-success me-2"></i>
            Домены с сертификатами <span class="badge bg-success ms-2"><?php echo count($domainsWithCerts); ?></span>
        </div>
        <div class="card-body">
            <?php foreach ($domainsWithCerts as $domain): ?>
                <div class="cert-container domain-item" data-domain="<?php echo htmlspecialchars($domain['domain']); ?>" data-has-cert="true">
                    <div class="cert-header" onclick="toggleCertificate('<?php echo $domain['id']; ?>')">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-globe me-2"></i>
                                <strong><?php echo htmlspecialchars($domain['domain']); ?></strong>
                                <?php if ($domain['ssl_expires_soon']): ?>
                                    <span class="badge bg-warning ms-2">⚠️ Истекает</span>
                                <?php endif; ?>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <small class="opacity-75">
                                    ID: <?php echo htmlspecialchars($domain['ssl_cert_id']); ?>
                                    <?php if ($domain['ssl_cert_created']): ?>
                                        | <?php echo date('d.m.Y', strtotime($domain['ssl_cert_created'])); ?>
                                    <?php endif; ?>
                                </small>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-light btn-sm" onclick="event.stopPropagation(); copyCertificate('<?php echo $domain['id']; ?>')" title="Копировать CRT">
                                        <i class="fas fa-certificate"></i>
                                    </button>
                                    <button type="button" class="btn btn-light btn-sm" onclick="event.stopPropagation(); copyPrivateKey('<?php echo $domain['id']; ?>')" title="Копировать KEY">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </div>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="cert-content" id="cert-content-<?php echo $domain['id']; ?>">
                        <div class="cert-grid">
                            <div class="cert-section">
                                <div class="cert-section-header">
                                    <div><i class="fas fa-key text-warning me-2"></i>Приватный ключ (KEY)</div>
                                    <div class="btn-group">
                                        <button class="btn btn-warning btn-xs" onclick="copyPrivateKey('<?php echo $domain['id']; ?>')"><i class="fas fa-copy"></i></button>
                                        <button class="btn btn-success btn-xs" onclick="downloadFile('<?php echo $domain['id']; ?>', 'key')"><i class="fas fa-download"></i></button>
                                    </div>
                                </div>
                                <pre class="cert-code" id="key-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['ssl_private_key']); ?></pre>
                            </div>
                            
                            <div class="cert-section">
                                <div class="cert-section-header">
                                    <div><i class="fas fa-certificate text-primary me-2"></i>Сертификат (CRT/PEM)</div>
                                    <div class="btn-group">
                                        <button class="btn btn-primary btn-xs" onclick="copyCertificate('<?php echo $domain['id']; ?>')"><i class="fas fa-copy"></i></button>
                                        <button class="btn btn-success btn-xs" onclick="downloadFile('<?php echo $domain['id']; ?>', 'cert')"><i class="fas fa-download"></i></button>
                                    </div>
                                </div>
                                <pre class="cert-code" id="cert-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['ssl_certificate']); ?></pre>
                            </div>
                        </div>
                        
                        <div class="mt-3 p-3 bg-light rounded">
                            <div class="row">
                                <div class="col-md-6 small">
                                    <strong>Группа:</strong> <?php echo htmlspecialchars($domain['group_name'] ?? 'Без группы'); ?><br>
                                    <strong>Email:</strong> <?php echo htmlspecialchars($domain['email']); ?>
                                </div>
                                <div class="col-md-6 small">
                                    <strong>Сертификатов:</strong> <?php echo $domain['ssl_certificates_count'] ?? 1; ?><br>
                                    <strong>Статус:</strong> <?php echo $domain['ssl_has_active'] ? '<span class="text-success">Активен</span>' : '<span class="text-muted">Неактивен</span>'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Domains without Certificates -->
    <?php if (!empty($domainsWithoutCerts)): ?>
    <div class="card">
        <div class="card-header">
            <i class="fas fa-exclamation-triangle text-warning me-2"></i>
            Домены без сертификатов <span class="badge bg-warning ms-2"><?php echo count($domainsWithoutCerts); ?></span>
        </div>
        <div class="card-body">
            <?php foreach ($domainsWithoutCerts as $domain): ?>
                <div class="no-cert-item domain-item" data-domain="<?php echo htmlspecialchars($domain['domain']); ?>" data-has-cert="false">
                    <div>
                        <i class="fas fa-globe text-muted me-2"></i>
                        <strong><?php echo htmlspecialchars($domain['domain']); ?></strong>
                        <small class="text-muted ms-2"><?php echo htmlspecialchars($domain['group_name'] ?? 'Без группы'); ?></small>
                    </div>
                    <button class="btn btn-warning btn-sm" onclick="createCertificateForDomain('<?php echo $domain['id']; ?>', '<?php echo htmlspecialchars($domain['domain']); ?>')">
                        <i class="fas fa-plus me-1"></i>Создать
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (empty($allDomains)): ?>
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <i class="fas fa-certificate"></i>
                <h5>Нет доменов</h5>
                <p>Добавьте домены в дашборде для управления сертификатами</p>
                <a href="dashboard.php" class="btn btn-primary">Перейти к дашборду</a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Hidden elements for certificate data -->
<?php foreach ($domainsWithCerts as $domain): ?>
    <textarea style="display: none;" id="hidden-cert-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['ssl_certificate']); ?></textarea>
    <textarea style="display: none;" id="hidden-key-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['ssl_private_key']); ?></textarea>
    <span style="display: none;" id="hidden-domain-<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['domain']); ?></span>
<?php endforeach; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search
    document.getElementById('searchDomains').addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.domain-item').forEach(item => {
            const domain = item.dataset.domain.toLowerCase();
            item.style.display = domain.includes(term) ? 'block' : 'none';
        });
    });
    
    // Filters
    document.querySelectorAll('input[name="filter"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const filter = this.value;
            document.querySelectorAll('.domain-item').forEach(item => {
                const hasCert = item.dataset.hasCert === 'true';
                let show = filter === 'all' || (filter === 'with' && hasCert) || (filter === 'without' && !hasCert);
                item.style.display = show ? 'block' : 'none';
            });
        });
    });
});

function toggleCertificate(domainId) {
    const content = document.getElementById('cert-content-' + domainId);
    content.classList.toggle('show');
}

function expandAll() {
    document.querySelectorAll('.cert-content').forEach(c => c.classList.add('show'));
    showToast('Все сертификаты развернуты', 'info');
}

function collapseAll() {
    document.querySelectorAll('.cert-content').forEach(c => c.classList.remove('show'));
    showToast('Все сертификаты свернуты', 'info');
}

function copyCertificate(domainId) {
    const cert = document.getElementById('hidden-cert-' + domainId).value;
    copyToClipboard(cert, 'Сертификат скопирован');
}

function copyPrivateKey(domainId) {
    const key = document.getElementById('hidden-key-' + domainId).value;
    copyToClipboard(key, 'Приватный ключ скопирован');
}

function downloadFile(domainId, type) {
    const domainName = document.getElementById('hidden-domain-' + domainId).textContent;
    const content = type === 'cert' 
        ? document.getElementById('hidden-cert-' + domainId).value
        : document.getElementById('hidden-key-' + domainId).value;
    const ext = type === 'cert' ? '.crt' : '.key';
    
    const blob = new Blob([content], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = domainName + ext;
    a.click();
    URL.revokeObjectURL(url);
    showToast('Файл загружен', 'success');
}

function copyAllCertificates() {
    const certs = [];
    document.querySelectorAll('[id^="hidden-cert-"]').forEach(el => {
        const domainId = el.id.replace('hidden-cert-', '');
        const domain = document.getElementById('hidden-domain-' + domainId).textContent;
        certs.push(`# ${domain}\n${el.value}\n`);
    });
    copyToClipboard(certs.join('\n'), `Скопировано ${certs.length} сертификатов`);
}

function copyAllKeys() {
    const keys = [];
    document.querySelectorAll('[id^="hidden-key-"]').forEach(el => {
        const domainId = el.id.replace('hidden-key-', '');
        const domain = document.getElementById('hidden-domain-' + domainId).textContent;
        keys.push(`# ${domain}\n${el.value}\n`);
    });
    copyToClipboard(keys.join('\n'), `Скопировано ${keys.length} ключей`);
}

function createCertificateForDomain(domainId, domainName) {
    if (!confirm(`Создать Origin CA сертификат для ${domainName}?`)) return;
    showToast('Перенаправление...', 'info');
    window.location.href = `dashboard.php?highlight=${domainId}#certificates`;
}
</script>

<?php include 'modals.php'; ?>
<?php include 'footer.php'; ?>