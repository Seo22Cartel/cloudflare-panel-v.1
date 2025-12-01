<?php
$pageTitle = 'Логи';
require_once 'header.php';

$userId = $_SESSION['user_id'];

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get total count
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE user_id = ?");
$countStmt->execute([$userId]);
$totalLogs = $countStmt->fetchColumn();
$totalPages = ceil($totalLogs / $perPage);

// Get logs with pagination
$stmt = $pdo->prepare("
    SELECT l.*, ca.domain 
    FROM logs l 
    LEFT JOIN cloudflare_accounts ca ON l.user_id = ca.user_id AND l.details LIKE '%' || ca.domain || '%'
    WHERE l.user_id = ? 
    ORDER BY l.timestamp DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $perPage, $offset]);
$logs = $stmt->fetchAll();

// Action type colors
$actionColors = [
    'Login' => 'success',
    'Logout' => 'secondary',
    'Domain' => 'primary',
    'DNS' => 'info',
    'SSL' => 'warning',
    'Security' => 'danger',
    'Worker' => 'dark',
    'Cache' => 'info',
    'Error' => 'danger',
    'API' => 'primary'
];

function getActionColor($action) {
    global $actionColors;
    foreach ($actionColors as $key => $color) {
        if (stripos($action, $key) !== false) {
            return $color;
        }
    }
    return 'secondary';
}

include 'sidebar.php';
?>

<div class="content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-history me-2"></i>Логи действий</h1>
                <p class="text-muted mb-0">История всех операций в системе</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-danger btn-sm" onclick="clearLogs()">
                    <i class="fas fa-trash me-1"></i>Очистить логи
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="exportLogs()">
                    <i class="fas fa-download me-1"></i>Экспорт
                </button>
            </div>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="quick-stats">
        <div class="card stat-card bg-gradient-primary">
            <div class="icon"><i class="fas fa-list"></i></div>
            <div class="info">
                <h3><?php echo number_format($totalLogs); ?></h3>
                <p>Всего записей</p>
            </div>
        </div>
        <div class="card stat-card bg-gradient-success">
            <div class="icon"><i class="fas fa-calendar-day"></i></div>
            <div class="info">
                <?php
                $todayStmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE user_id = ? AND date(timestamp) = date('now')");
                $todayStmt->execute([$userId]);
                $todayCount = $todayStmt->fetchColumn();
                ?>
                <h3><?php echo number_format($todayCount); ?></h3>
                <p>За сегодня</p>
            </div>
        </div>
        <div class="card stat-card bg-gradient-warning">
            <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="info">
                <?php
                $errorsStmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE user_id = ? AND (action LIKE '%error%' OR action LIKE '%fail%')");
                $errorsStmt->execute([$userId]);
                $errorsCount = $errorsStmt->fetchColumn();
                ?>
                <h3><?php echo number_format($errorsCount); ?></h3>
                <p>Ошибок</p>
            </div>
        </div>
    </div>
    
    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <span><i class="fas fa-list me-2"></i>История действий</span>
            <span class="text-muted">Страница <?php echo $page; ?> из <?php echo max(1, $totalPages); ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h5>Нет записей</h5>
                    <p>Логи появятся после выполнения операций в системе</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 180px;">Время</th>
                                <th style="width: 200px;">Действие</th>
                                <th>Детали</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td>
                                        <span class="text-muted">
                                            <?php echo date('d.m.Y H:i:s', strtotime($log['timestamp'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getActionColor($log['action']); ?>">
                                            <?php echo htmlspecialchars($log['action']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-truncate d-inline-block" style="max-width: 500px;" title="<?php echo htmlspecialchars($log['details'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($log['details'] ?? '-'); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function clearLogs() {
    if (!confirm('Вы уверены, что хотите очистить все логи? Это действие необратимо.')) {
        return;
    }
    
    fetch('logs_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'clear_logs' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Логи успешно очищены', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.error || 'Ошибка очистки логов', 'error');
        }
    })
    .catch(err => showToast('Ошибка: ' + err.message, 'error'));
}

function exportLogs() {
    window.location.href = 'logs_api.php?action=export';
}
</script>

<?php include 'footer.php'; ?>