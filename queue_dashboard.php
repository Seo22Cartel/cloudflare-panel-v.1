<?php
$pageTitle = 'Очередь задач';
require_once 'header.php';

$userId = $_SESSION['user_id'];

// Get overall queue stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM queue 
    WHERE user_id = ?
");
$stmt->execute([$userId]);
$queueStats = $stmt->fetch();

// Get NS tasks stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM queue 
    WHERE user_id = ? AND type LIKE '%ns%'
");
$stmt->execute([$userId]);
$nsStats = $stmt->fetch();

// Get domains needing NS
$stmt = $pdo->prepare("
    SELECT COUNT(*) as count 
    FROM cloudflare_accounts 
    WHERE user_id = ? AND (ns_records IS NULL OR ns_records = '' OR ns_records = '[]')
");
$stmt->execute([$userId]);
$domainsNeedingNS = $stmt->fetchColumn();

include 'sidebar.php';
?>

<div class="content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-tasks me-2"></i>Управление очередью задач</h1>
                <p class="text-muted mb-0">Мониторинг и управление фоновыми задачами</p>
            </div>
            <div class="d-flex gap-2">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="autoRefresh">
                    <label class="form-check-label" for="autoRefresh">
                        <i class="fas fa-sync-alt me-1"></i>Автообновление
                    </label>
                </div>
                <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>К дашборду
                </a>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card stat-card bg-gradient-primary">
                <div class="card-body">
                    <h6 class="mb-3"><i class="fas fa-list me-2"></i>Общая статистика очереди</h6>
                    <div class="row text-center">
                        <div class="col">
                            <div class="h3 mb-0"><?php echo $queueStats['total'] ?? 0; ?></div>
                            <small>Всего</small>
                        </div>
                        <div class="col">
                            <div class="h3 mb-0"><?php echo $queueStats['pending'] ?? 0; ?></div>
                            <small>В очереди</small>
                        </div>
                        <div class="col">
                            <div class="h3 mb-0"><?php echo $queueStats['processing'] ?? 0; ?></div>
                            <small>Выполняется</small>
                        </div>
                        <div class="col">
                            <div class="h3 mb-0"><?php echo $queueStats['completed'] ?? 0; ?></div>
                            <small>Завершено</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card stat-card bg-gradient-success">
                <div class="card-body">
                    <h6 class="mb-3"><i class="fas fa-server me-2"></i>NS задачи</h6>
                    <div class="row text-center">
                        <div class="col">
                            <div class="h3 mb-0"><?php echo $nsStats['total'] ?? 0; ?></div>
                            <small>NS задач</small>
                        </div>
                        <div class="col">
                            <div class="h3 mb-0"><?php echo $nsStats['pending'] ?? 0; ?></div>
                            <small>В очереди</small>
                        </div>
                        <div class="col">
                            <div class="h3 mb-0"><?php echo $domainsNeedingNS; ?></div>
                            <small>Нужно NS</small>
                        </div>
                        <div class="col">
                            <div class="h3 mb-0"><?php echo $nsStats['failed'] ?? 0; ?></div>
                            <small>Ошибки</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Control Panel -->
    <div class="card mb-4">
        <div class="card-header">
            <i class="fas fa-cogs me-2"></i>Управление задачами
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <button class="btn btn-success w-100" onclick="addBulkNSUpdate()">
                        <i class="fas fa-rocket me-1"></i>Массовое обновление NS
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" onclick="processQueue()">
                        <i class="fas fa-play me-1"></i>Запустить процессор
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-warning w-100" onclick="clearCompleted()">
                        <i class="fas fa-broom me-1"></i>Очистить завершённые
                    </button>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-info w-100" onclick="refreshStatus()">
                        <i class="fas fa-sync-alt me-1"></i>Обновить статус
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Tasks -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-clock me-2"></i>Активные задачи</span>
            <small class="text-muted">Обновляется автоматически</small>
        </div>
        <div class="card-body" id="activeTasks">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Task History -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-history me-2"></i>История задач
        </div>
        <div class="card-body" id="taskHistory">
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Загрузка...</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk NS Modal -->
<div class="modal fade" id="bulkNSModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-server me-2"></i>Массовое обновление NS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="bulkLimit" class="form-label">Количество доменов:</label>
                    <input type="number" class="form-control" id="bulkLimit" value="10" min="1" max="50">
                    <div class="form-text">Рекомендуется не более 20 доменов за раз</div>
                </div>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Будут обновлены NS серверы для доменов, у которых они отсутствуют или устарели.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-success" onclick="confirmBulkNSUpdate()">
                    <i class="fas fa-rocket me-1"></i>Добавить в очередь
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let autoRefreshInterval;
let bulkNSModal;

document.addEventListener('DOMContentLoaded', function() {
    bulkNSModal = new bootstrap.Modal(document.getElementById('bulkNSModal'));
    refreshStatus();
    
    document.getElementById('autoRefresh').addEventListener('change', function() {
        if (this.checked) {
            autoRefreshInterval = setInterval(refreshStatus, 5000);
        } else {
            clearInterval(autoRefreshInterval);
        }
    });
});

function refreshStatus() {
    fetch('ns_queue_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_queue_status' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateActiveTasks(data.pending_tasks || [], data.processing_tasks || []);
            updateTaskHistory(data.recent_tasks || []);
        } else {
            showToast('Ошибка получения статуса: ' + data.error, 'error');
        }
    })
    .catch(err => showToast('Ошибка сети: ' + err.message, 'error'));
}

function updateActiveTasks(pendingTasks, processingTasks) {
    const container = document.getElementById('activeTasks');
    let html = '';
    
    if (processingTasks.length === 0 && pendingTasks.length === 0) {
        html = '<div class="text-center text-muted py-3">Нет активных задач</div>';
    } else {
        if (processingTasks.length > 0) {
            html += '<h6 class="mb-3"><i class="fas fa-cog fa-spin me-2"></i>Выполняется:</h6>';
            processingTasks.forEach(task => html += renderTaskRow(task, 'processing'));
        }
        if (pendingTasks.length > 0) {
            html += '<h6 class="mb-3 mt-3"><i class="fas fa-clock me-2"></i>В очереди:</h6>';
            pendingTasks.forEach(task => html += renderTaskRow(task, 'pending'));
        }
    }
    container.innerHTML = html;
}

function updateTaskHistory(recentTasks) {
    const container = document.getElementById('taskHistory');
    let html = '';
    
    if (recentTasks.length === 0) {
        html = '<div class="text-center text-muted py-3">Нет задач в истории</div>';
    } else {
        recentTasks.forEach(task => html += renderTaskRow(task, task.status));
    }
    container.innerHTML = html;
}

function renderTaskRow(task, status) {
    const badges = {
        'pending': '<span class="badge bg-warning">В очереди</span>',
        'processing': '<span class="badge bg-info">Выполняется</span>',
        'completed': '<span class="badge bg-success">Завершено</span>',
        'failed': '<span class="badge bg-danger">Ошибка</span>',
        'cancelled': '<span class="badge bg-secondary">Отменено</span>'
    };
    
    const typeLabels = {
        'update_ns_records': 'Обновление NS',
        'bulk_update_ns_records': 'Массовое NS',
        'change_ip': 'Смена IP',
        'change_ssl': 'Смена SSL',
        'change_tls': 'Смена TLS'
    };
    
    let actions = status === 'pending' 
        ? `<button class="btn btn-sm btn-outline-danger" onclick="cancelTask(${task.id})"><i class="fas fa-times"></i></button>`
        : '';
    
    return `
        <div class="queue-item ${status} p-3 mb-2 bg-light rounded border">
            <div class="row align-items-center">
                <div class="col-md-2">${badges[status] || badges.pending}</div>
                <div class="col-md-3">
                    <strong>${typeLabels[task.type] || task.type}</strong>
                    ${task.domain ? '<br><small class="text-muted">' + task.domain + '</small>' : ''}
                </div>
                <div class="col-md-3">
                    <small class="text-muted">
                        Создано: ${new Date(task.created_at).toLocaleString('ru-RU')}
                        ${task.completed_at ? '<br>Завершено: ' + new Date(task.completed_at).toLocaleString('ru-RU') : ''}
                    </small>
                </div>
                <div class="col-md-2"><small class="text-muted">ID: ${task.id}</small></div>
                <div class="col-md-2 text-end">${actions}</div>
            </div>
        </div>
    `;
}

function addBulkNSUpdate() {
    bulkNSModal.show();
}

function confirmBulkNSUpdate() {
    const limit = document.getElementById('bulkLimit').value;
    
    fetch('ns_queue_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add_bulk_ns_update', limit: parseInt(limit) })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            bulkNSModal.hide();
            refreshStatus();
        } else {
            showToast('Ошибка: ' + data.error, 'error');
        }
    })
    .catch(err => showToast('Ошибка: ' + err.message, 'error'));
}

function processQueue() {
    showLoading();
    fetch('queue_processor.php?action=process&auth_token=cloudflare_queue_processor_2024')
    .then(r => r.json())
    .then(data => {
        hideLoading();
        if (data.success) {
            showToast(`Обработано: ${data.processed} задач за ${data.execution_time}с`, 'success');
            refreshStatus();
        } else {
            showToast('Ошибка: ' + data.error, 'error');
        }
    })
    .catch(err => {
        hideLoading();
        showToast('Ошибка: ' + err.message, 'error');
    });
}

function clearCompleted() {
    fetch('ns_queue_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'clear_completed_ns_tasks' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast('Ошибка: ' + data.error, 'error');
        }
    })
    .catch(err => showToast('Ошибка: ' + err.message, 'error'));
}

function cancelTask(taskId) {
    if (!confirm('Отменить эту задачу?')) return;
    
    fetch('ns_queue_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'cancel_pending_task', task_id: taskId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            refreshStatus();
        } else {
            showToast('Ошибка: ' + data.error, 'error');
        }
    })
    .catch(err => showToast('Ошибка: ' + err.message, 'error'));
}
</script>

<?php include 'footer.php'; ?>