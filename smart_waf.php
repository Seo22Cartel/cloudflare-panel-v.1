<?php
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

$domainsStmt = $pdo->prepare("SELECT * FROM cloudflare_accounts WHERE user_id = ? ORDER BY domain");
$domainsStmt->execute([$userId]);
$domains = $domainsStmt->fetchAll();
?>

<?php include 'sidebar.php'; ?>

<div class="content">
    <!-- Заголовок -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Smart WAF Optimizer</h2>
            <p class="text-muted mb-0">Автоматическая настройка правил безопасности на основе лучших практик</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Назад
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h5 class="card-title mb-3 text-primary">Выберите цель применения</h5>
                    <div class="row align-items-end g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Область действия</label>
                            <select class="form-select" id="wafScope">
                                <option value="all">Все домены</option>
                                <option value="group">Группа доменов</option>
                                <option value="selected">Выбранные домены</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="groupSelectDiv" style="display: none;">
                            <label class="form-label fw-bold">Группа</label>
                            <select class="form-select" id="wafGroup">
                                <option value="">Выберите группу</option>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4" id="domainSelectDiv" style="display: none;">
                            <label class="form-label fw-bold">Домены</label>
                            <select class="form-select" id="wafDomains" multiple size="3">
                                <?php foreach ($domains as $domain): ?>
                                    <option value="<?php echo $domain['id']; ?>"><?php echo htmlspecialchars($domain['domain']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Rule 1: Block Bad ASNs -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                <div class="card-body text-center p-4">
                    <div class="mb-3 text-danger">
                        <i class="fas fa-network-wired fa-3x"></i>
                    </div>
                    <h5 class="fw-bold">Блокировка плохих ASN</h5>
                    <p class="text-muted small mb-4">Блокирует трафик от хостинг-провайдеров, часто используемых для атак (DigitalOcean, Hetzner, AWS и др.)</p>
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox" id="ruleBadASN" checked style="transform: scale(1.5);">
                    </div>
                </div>
            </div>
        </div>

        <!-- Rule 2: Block High Risk Countries -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                <div class="card-body text-center p-4">
                    <div class="mb-3 text-warning">
                        <i class="fas fa-globe-americas fa-3x"></i>
                    </div>
                    <h5 class="fw-bold">Блокировка рисковых стран</h5>
                    <p class="text-muted small mb-4">Блокирует или проверяет (Challenge) трафик из стран с высоким уровнем киберугроз (CN, BR, IN, VN и др.)</p>
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox" id="ruleHighRiskCountries" checked style="transform: scale(1.5);">
                    </div>
                </div>
            </div>
        </div>

        <!-- Rule 3: Challenge Unknown Bots -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                <div class="card-body text-center p-4">
                    <div class="mb-3 text-info">
                        <i class="fas fa-robot fa-3x"></i>
                    </div>
                    <h5 class="fw-bold">
                        Проверка неизвестных ботов 
                        <span class="badge bg-success ms-1">Rec</span>
                    </h5>
                    <p class="text-muted small mb-4">Применяет JS Challenge ко всем посетителям, которые не являются известными поисковыми ботами</p>
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox" id="ruleChallengeBots" style="transform: scale(1.5);">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Rule 4: Block WordPress Attacks -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                <div class="card-body text-center p-4">
                    <div class="mb-3 text-primary">
                        <i class="fab fa-wordpress fa-3x"></i>
                    </div>
                    <h5 class="fw-bold">Защита WordPress</h5>
                    <p class="text-muted small mb-4">Блокирует доступ к xmlrpc.php и wp-login.php для всех, кроме доверенных IP</p>
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox" id="ruleWordPress" style="transform: scale(1.5);">
                    </div>
                </div>
            </div>
        </div>

        <!-- Rule 5: Rate Limiting -->
        <div class="col-md-4">
            <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                <div class="card-body text-center p-4">
                    <div class="mb-3 text-secondary">
                        <i class="fas fa-tachometer-alt fa-3x"></i>
                    </div>
                    <h5 class="fw-bold">Базовый Rate Limiting</h5>
                    <p class="text-muted small mb-4">Ограничивает количество запросов с одного IP (защита от DDoS L7)</p>
                    <div class="form-check form-switch d-flex justify-content-center">
                        <input class="form-check-input" type="checkbox" id="ruleRateLimit" style="transform: scale(1.5);">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-12 text-center">
            <button class="btn btn-primary btn-lg px-5 py-3 rounded-pill shadow-lg" onclick="applySmartWaf()">
                <i class="fas fa-check-circle me-2"></i> Применить выбранные правила
            </button>
        </div>
    </div>
    
    <div class="row mt-4" id="resultsArea" style="display: none;">
        <div class="col-12">
            <div class="card border-0 shadow">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-terminal me-2"></i>Результаты применения</h5>
                </div>
                <div class="card-body bg-light">
                    <div id="resultsLog" style="max-height: 300px; overflow-y: auto; font-family: 'Courier New', monospace;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        $('#wafScope').change(function() {
            const scope = $(this).val();
            $('#groupSelectDiv').hide();
            $('#domainSelectDiv').hide();
            
            if (scope === 'group') {
                $('#groupSelectDiv').show();
            } else if (scope === 'selected') {
                $('#domainSelectDiv').show();
            }
        });
    });

    function applySmartWaf() {
        const scope = $('#wafScope').val();
        let targetData = {};
        
        if (scope === 'group') {
            targetData.groupId = $('#wafGroup').val();
            if (!targetData.groupId) {
                alert('Выберите группу');
                return;
            }
        } else if (scope === 'selected') {
            targetData.domainIds = $('#wafDomains').val();
            if (!targetData.domainIds || targetData.domainIds.length === 0) {
                alert('Выберите домены');
                return;
            }
        }
        
        const rules = {
            bad_asn: $('#ruleBadASN').is(':checked'),
            high_risk_countries: $('#ruleHighRiskCountries').is(':checked'),
            challenge_bots: $('#ruleChallengeBots').is(':checked'),
            wordpress: $('#ruleWordPress').is(':checked'),
            rate_limit: $('#ruleRateLimit').is(':checked')
        };
        
        if (!Object.values(rules).some(v => v)) {
            alert('Выберите хотя бы одно правило');
            return;
        }
        
        $('#resultsArea').show();
        $('#resultsLog').html('<div class="text-info"><i class="fas fa-spinner fa-spin me-2"></i>Запуск процесса...</div>');
        
        $.ajax({
            url: 'smart_waf_api.php',
            method: 'POST',
            data: {
                action: 'apply_smart_rules',
                scope: { type: scope, ...targetData },
                rules: rules
            },
            success: function(response) {
                try {
                    const res = typeof response === 'string' ? JSON.parse(response) : response;
                    if (res.success) {
                        let html = '<div class="text-success mb-2"><strong><i class="fas fa-check-circle me-2"></i>Успешно выполнено!</strong></div>';
                        html += `<div>Обработано доменов: <strong>${res.total}</strong></div>`;
                        html += `<div>Применено правил: <strong>${res.applied}</strong></div>`;
                        
                        if (res.details && res.details.length > 0) {
                            html += '<hr><h6 class="text-muted">Детали:</h6>';
                            res.details.forEach(detail => {
                                const color = detail.success ? 'text-success' : 'text-danger';
                                const icon = detail.success ? '<i class="fas fa-check"></i>' : '<i class="fas fa-times"></i>';
                                html += `<div class="${color} mb-1">${icon} <strong>${detail.domain}</strong>: ${detail.message}</div>`;
                            });
                        }
                        
                        $('#resultsLog').html(html);
                    } else {
                        $('#resultsLog').html(`<div class="text-danger"><i class="fas fa-exclamation-circle me-2"></i>Ошибка: ${res.error}</div>`);
                    }
                } catch (e) {
                    $('#resultsLog').html(`<div class="text-danger">Ошибка обработки ответа: ${e.message}</div>`);
                }
            },
            error: function(xhr, status, error) {
                $('#resultsLog').html(`<div class="text-danger">Системная ошибка: ${error}</div>`);
            }
        });
    }
</script>

<?php include 'footer.php'; ?>