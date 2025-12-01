<?php
$pageTitle = 'Управление прокси';
require_once 'header.php';

$userId = $_SESSION['user_id'];

// Get groups for modals
$groupStmt = $pdo->prepare("SELECT * FROM groups WHERE user_id = ?");
$groupStmt->execute([$userId]);
$groups = $groupStmt->fetchAll();

// Get accounts for modals
$accountStmt = $pdo->prepare("SELECT * FROM cloudflare_credentials WHERE user_id = ?");
$accountStmt->execute([$userId]);
$accounts = $accountStmt->fetchAll();

$notification = '';
$error = '';

// Add proxies
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_proxies'])) {
    $proxiesList = explode("\n", trim($_POST['proxies']));
    $addedCount = 0;
    $errorCount = 0;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO proxies (user_id, proxy) VALUES (?, ?)");
        
        foreach ($proxiesList as $proxy) {
            $proxy = trim($proxy);
            if (empty($proxy)) continue;
            
            if (preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}):(\d+)@([^:@]+):(.+)$/', $proxy, $matches)) {
                $ip = $matches[1];
                $port = $matches[2];
                
                if (filter_var($ip, FILTER_VALIDATE_IP) && $port > 0 && $port <= 65535) {
                    try {
                        $stmt->execute([$userId, $proxy]);
                        $proxyId = $pdo->lastInsertId();
                        checkProxy($pdo, $proxy, $proxyId, $userId);
                        $addedCount++;
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            logAction($pdo, $userId, "Proxy duplicate", "Proxy: $proxy");
                        } else {
                            throw $e;
                        }
                        $errorCount++;
                    }
                } else {
                    logAction($pdo, $userId, "Invalid proxy format", "Proxy: $proxy");
                    $errorCount++;
                }
            } else {
                logAction($pdo, $userId, "Invalid proxy format", "Proxy: $proxy");
                $errorCount++;
            }
        }
        
        if ($addedCount > 0) {
            $notification = "Добавлено прокси: $addedCount";
        }
        if ($errorCount > 0) {
            $error = "Ошибок при добавлении: $errorCount";
        }
    } catch (PDOException $e) {
        $error = "Ошибка при добавлении прокси";
        logAction($pdo, $userId, "Proxy add error", "Error: " . $e->getMessage());
    }
}

// Delete proxy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_proxy'])) {
    $proxyId = (int)($_POST['proxy_id'] ?? 0);
    if ($proxyId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM proxies WHERE id = ? AND user_id = ?");
            $stmt->execute([$proxyId, $userId]);
            if ($stmt->rowCount() > 0) {
                $notification = "Прокси удален";
                logAction($pdo, $userId, "Proxy deleted", "Proxy ID: $proxyId");
            } else {
                $error = "Прокси не найден";
            }
        } catch (PDOException $e) {
            $error = "Ошибка при удалении прокси";
            logAction($pdo, $userId, "Proxy delete error", "Error: " . $e->getMessage());
        }
    }
}

// Check all proxies
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_all_proxies'])) {
    $stmt = $pdo->prepare("SELECT id, proxy FROM proxies WHERE user_id = ?");
    $stmt->execute([$userId]);
    $allProxies = $stmt->fetchAll();
    
    $checkedCount = 0;
    foreach ($allProxies as $proxyData) {
        checkProxy($pdo, $proxyData['proxy'], $proxyData['id'], $userId);
        $checkedCount++;
    }
    
    $notification = "Проверено прокси: $checkedCount";
}

// Get proxies
$stmt = $pdo->prepare("SELECT * FROM proxies WHERE user_id = ? ORDER BY status DESC, id DESC");
$stmt->execute([$userId]);
$proxies = $stmt->fetchAll();

// Count stats
$workingCount = count(array_filter($proxies, fn($p) => $p['status'] == 1));
$failedCount = count(array_filter($proxies, fn($p) => $p['status'] == 2));
$uncheckedCount = count(array_filter($proxies, fn($p) => $p['status'] == 0));

include 'sidebar.php';
?>

<div class="content">
    <div class="content-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1><i class="fas fa-server me-2"></i>Управление прокси</h1>
                <p class="text-muted mb-0">Настройка прокси-серверов для API запросов</p>
            </div>
        </div>
    </div>
    
    <?php if ($notification): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($notification); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="quick-stats">
        <div class="card stat-card bg-gradient-primary">
            <div class="icon"><i class="fas fa-server"></i></div>
            <div class="info">
                <h3><?php echo count($proxies); ?></h3>
                <p>Всего прокси</p>
            </div>
        </div>
        <div class="card stat-card bg-gradient-success">
            <div class="icon"><i class="fas fa-check-circle"></i></div>
            <div class="info">
                <h3><?php echo $workingCount; ?></h3>
                <p>Рабочих</p>
            </div>
        </div>
        <div class="card stat-card bg-gradient-danger">
            <div class="icon"><i class="fas fa-times-circle"></i></div>
            <div class="info">
                <h3><?php echo $failedCount; ?></h3>
                <p>Нерабочих</p>
            </div>
        </div>
        <div class="card stat-card bg-gradient-secondary">
            <div class="icon"><i class="fas fa-question-circle"></i></div>
            <div class="info">
                <h3><?php echo $uncheckedCount; ?></h3>
                <p>Не проверено</p>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Add Proxy Form -->
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle me-2"></i>Добавить прокси
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="proxies" class="form-label">Список прокси</label>
                            <textarea name="proxies" id="proxies" class="form-control" rows="6" placeholder="192.168.1.1:8080@username:password&#10;192.168.1.2:8080@username:password" required></textarea>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Формат: IP:PORT@LOGIN:PASSWORD (каждый прокси с новой строки)
                            </div>
                        </div>
                        <button type="submit" name="add_proxies" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i>Добавить прокси
                        </button>
                    </form>
                    
                    <hr>
                    
                    <form method="POST">
                        <button type="submit" name="check_all_proxies" class="btn btn-info w-100">
                            <i class="fas fa-sync me-1"></i>Проверить все прокси
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle me-2"></i>Информация
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <h6 class="alert-heading"><i class="fas fa-lightbulb me-1"></i>Подсказка</h6>
                        <p class="mb-2 small">Прокси используются для распределения нагрузки API запросов к Cloudflare и обхода rate-limiting.</p>
                        <p class="mb-0 small">Рекомендуется использовать надежные HTTP/HTTPS прокси с базовой авторизацией.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Proxy List -->
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <span><i class="fas fa-list me-2"></i>Список прокси</span>
                    <span class="badge bg-primary"><?php echo count($proxies); ?></span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($proxies)): ?>
                        <div class="empty-state">
                            <i class="fas fa-server"></i>
                            <h5>Нет прокси</h5>
                            <p>Добавьте прокси для начала работы</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                            <table class="table table-hover mb-0">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th>Прокси</th>
                                        <th style="width: 120px;">Статус</th>
                                        <th style="width: 80px;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($proxies as $proxy): ?>
                                        <tr>
                                            <td>
                                                <code class="text-truncate d-inline-block" style="max-width: 300px;" title="<?php echo htmlspecialchars($proxy['proxy']); ?>">
                                                    <?php 
                                                    // Mask password for display
                                                    $displayProxy = preg_replace('/:([^:@]+)@/', ':****@', $proxy['proxy']);
                                                    echo htmlspecialchars($displayProxy); 
                                                    ?>
                                                </code>
                                            </td>
                                            <td>
                                                <?php if ($proxy['status'] == 1): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-check me-1"></i>Работает
                                                    </span>
                                                <?php elseif ($proxy['status'] == 2): ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times me-1"></i>Не работает
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="fas fa-question me-1"></i>Не проверен
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="proxy_id" value="<?php echo $proxy['id']; ?>">
                                                    <button type="submit" name="delete_proxy" class="btn btn-sm btn-outline-danger" onclick="return confirm('Удалить этот прокси?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'modals.php'; ?>
<?php include 'footer.php'; ?>