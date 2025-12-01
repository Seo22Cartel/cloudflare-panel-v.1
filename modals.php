<!-- ===================================
     GROUP MODALS
     =================================== -->

<!-- Add Group Modal -->
<div class="modal fade" id="addGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-folder-plus me-2"></i>Добавить группу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="handle_forms.php" id="addGroupForm">
                    <div class="mb-3">
                        <label class="form-label">Название группы</label>
                        <input type="text" name="group_name" class="form-control" placeholder="Введите название" required>
                    </div>
                    <button type="submit" name="add_group" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-1"></i>Добавить
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Delete Group Modal -->
<div class="modal fade" id="deleteGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-folder-minus me-2 text-danger"></i>Удалить группу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Все домены этой группы будут перемещены в "Без группы"
                </div>
                <form method="POST" action="handle_forms.php">
                    <div class="mb-3">
                        <label class="form-label">Выберите группу</label>
                        <select name="group_id" class="form-select" required>
                            <option value="">-- Выберите группу --</option>
                            <?php if (isset($groups)): ?>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button type="submit" name="delete_group" class="btn btn-danger w-100">
                        <i class="fas fa-trash me-1"></i>Удалить группу
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===================================
     DOMAIN MODALS
     =================================== -->

<!-- Add Domain Modal -->
<div class="modal fade" id="addDomainModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-globe me-2"></i>Добавить домен</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="handle_forms.php">
                    <div class="mb-3">
                        <label class="form-label">Аккаунт Cloudflare</label>
                        <select name="account_id" class="form-select" required>
                            <option value="">-- Выберите аккаунт --</option>
                            <?php if (isset($accounts)): ?>
                                <?php foreach ($accounts as $account): ?>
                                    <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['email']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Группа</label>
                        <select name="group_id" class="form-select" required>
                            <option value="">-- Выберите группу --</option>
                            <?php if (isset($groups)): ?>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Домен</label>
                        <input type="text" name="domain" class="form-control" placeholder="example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">IP сервера</label>
                        <input type="text" name="server_ip" class="form-control" placeholder="192.168.1.1" required>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" name="enable_https" class="form-check-input" id="enableHttpsSingle">
                            <label class="form-check-label" for="enableHttpsSingle">Always Use HTTPS</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="enable_tls13" class="form-check-input" id="enableTlsSingle">
                            <label class="form-check-label" for="enableTlsSingle">TLS 1.3</label>
                        </div>
                    </div>
                    <button type="submit" name="add_domain" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-1"></i>Добавить домен
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Add Domains Modal -->
<div class="modal fade" id="addDomainsBulkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Массовое добавление доменов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="handle_forms.php">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Аккаунт Cloudflare</label>
                            <select name="account_id" class="form-select" required>
                                <option value="">-- Выберите аккаунт --</option>
                                <?php if (isset($accounts)): ?>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['email']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Группа</label>
                            <select name="group_id" class="form-select" required>
                                <option value="">-- Выберите группу --</option>
                                <?php if (isset($groups)): ?>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Список доменов</label>
                        <textarea name="domains_list" class="form-control" rows="6" placeholder="example.com;192.168.1.1&#10;example2.com;192.168.1.2" required></textarea>
                        <div class="form-text">Формат: домен;IP (каждая пара с новой строки)</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="enable_https" class="form-check-input" id="enableHttpsBulk">
                            <label class="form-check-label" for="enableHttpsBulk">Always Use HTTPS</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input type="checkbox" name="enable_tls13" class="form-check-input" id="enableTlsBulk">
                            <label class="form-check-label" for="enableTlsBulk">TLS 1.3</label>
                        </div>
                    </div>
                    <button type="submit" name="add_domains_bulk" class="btn btn-primary w-100">
                        <i class="fas fa-upload me-1"></i>Добавить списком
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ===================================
     ACCOUNT MODALS
     =================================== -->

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Добавить аккаунт</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="POST" action="handle_forms.php">
                    <div class="mb-3">
                        <label class="form-label">Группа</label>
                        <select name="group_id" class="form-select" required>
                            <option value="">-- Выберите группу --</option>
                            <?php if (isset($groups)): ?>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <input type="text" name="api_key" class="form-control" placeholder="Cloudflare Global API Key" required>
                    </div>
                    <button type="submit" name="add_account" class="btn btn-primary w-100">
                        <i class="fas fa-plus me-1"></i>Добавить аккаунт
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Add Accounts Modal (Progressive Loading) -->
<div class="modal fade" id="addAccountsBulkModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-users me-2"></i>Массовое добавление аккаунтов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="bulkAccountsCloseBtn"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Input accounts -->
                <div id="bulkAccountsStep1">
                    <div class="mb-3">
                        <label class="form-label">Группа</label>
                        <select id="bulkAccountsGroupId" class="form-select" required>
                            <option value="">-- Выберите группу --</option>
                            <?php if (isset($groups)): ?>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Список аккаунтов</label>
                        <textarea id="bulkAccountsList" class="form-control" rows="8" placeholder="email1@example.com;api_key1&#10;email2@example.com;api_key2" required></textarea>
                    </div>
                    <div class="alert alert-info">
                        <small>
                            <strong>Формат:</strong> email;api_key (каждый аккаунт с новой строки)<br>
                            <strong>Рекомендация:</strong> для 100+ аккаунтов загрузка происходит последовательно с задержкой для стабильности
                        </small>
                    </div>
                    
                    <!-- Speed settings -->
                    <div class="mb-3">
                        <label class="form-label">Скорость загрузки</label>
                        <select id="bulkAccountsSpeed" class="form-select">
                            <option value="2000">Медленная (2 сек между аккаунтами) - безопасно для 1000+ аккаунтов</option>
                            <option value="1000" selected>Нормальная (1 сек между аккаунтами)</option>
                            <option value="500">Быстрая (0.5 сек) - только для <100 аккаунтов</option>
                            <option value="200">Очень быстрая (0.2 сек) - только для <50 аккаунтов</option>
                        </select>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" onclick="validateBulkAccounts()">
                            <i class="fas fa-check-circle me-1"></i>Проверить список
                        </button>
                        <button type="button" class="btn btn-primary flex-grow-1" onclick="startBulkAccountsImport()">
                            <i class="fas fa-upload me-1"></i>Начать загрузку
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Validation results -->
                <div id="bulkAccountsStep2" class="d-none">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-clipboard-check me-2"></i>Результаты проверки</h6>
                        <div id="validationResults"></div>
                    </div>
                    <div id="validationErrors" class="mb-3"></div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="backToStep1()">
                            <i class="fas fa-arrow-left me-1"></i>Назад
                        </button>
                        <button type="button" class="btn btn-primary flex-grow-1" onclick="startBulkAccountsImport(true)">
                            <i class="fas fa-upload me-1"></i>Продолжить загрузку
                        </button>
                    </div>
                </div>
                
                <!-- Step 3: Progress -->
                <div id="bulkAccountsStep3" class="d-none">
                    <div class="text-center mb-3">
                        <h5><i class="fas fa-sync fa-spin me-2"></i>Загрузка аккаунтов...</h5>
                        <p class="text-muted">Не закрывайте это окно до завершения</p>
                    </div>
                    
                    <div class="progress mb-3" style="height: 25px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="bulkAccountsProgress" role="progressbar" style="width: 0%">0%</div>
                    </div>
                    
                    <div class="row text-center mb-3">
                        <div class="col-3">
                            <div class="border rounded p-2 bg-light">
                                <div class="fs-4 fw-bold text-primary" id="bulkStatTotal">0</div>
                                <small class="text-muted">Всего</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border rounded p-2 bg-light">
                                <div class="fs-4 fw-bold text-success" id="bulkStatSuccess">0</div>
                                <small class="text-muted">Добавлено</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border rounded p-2 bg-light">
                                <div class="fs-4 fw-bold text-warning" id="bulkStatDuplicate">0</div>
                                <small class="text-muted">Дубликаты</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border rounded p-2 bg-light">
                                <div class="fs-4 fw-bold text-danger" id="bulkStatError">0</div>
                                <small class="text-muted">Ошибки</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Текущий аккаунт:</label>
                        <div class="border rounded p-2 bg-light" id="currentAccountStatus">
                            <span class="text-muted">Подготовка...</span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label d-flex justify-content-between">
                            <span>Лог операций:</span>
                            <span id="bulkDomainsTotal" class="badge bg-info">Доменов: 0</span>
                        </label>
                        <div id="bulkAccountsLog" class="border rounded p-2 bg-dark text-light font-monospace" style="height: 200px; overflow-y: auto; font-size: 12px;"></div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-danger" id="bulkAccountsStopBtn" onclick="stopBulkAccountsImport()">
                            <i class="fas fa-stop me-1"></i>Остановить
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="bulkAccountsBackBtn" onclick="backToStep1()">
                            <i class="fas fa-arrow-left me-1"></i>Назад
                        </button>
                    </div>
                </div>
                
                <!-- Step 4: Complete -->
                <div id="bulkAccountsStep4" class="d-none">
                    <div class="text-center mb-4">
                        <div class="display-1 text-success mb-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4>Загрузка завершена!</h4>
                    </div>
                    
                    <div class="row text-center mb-4">
                        <div class="col-3">
                            <div class="border rounded p-3">
                                <div class="fs-3 fw-bold text-primary" id="finalStatTotal">0</div>
                                <small class="text-muted">Всего</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border rounded p-3">
                                <div class="fs-3 fw-bold text-success" id="finalStatSuccess">0</div>
                                <small class="text-muted">Добавлено</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border rounded p-3">
                                <div class="fs-3 fw-bold text-warning" id="finalStatDuplicate">0</div>
                                <small class="text-muted">Дубликаты</small>
                            </div>
                        </div>
                        <div class="col-3">
                            <div class="border rounded p-3">
                                <div class="fs-3 fw-bold text-danger" id="finalStatError">0</div>
                                <small class="text-muted">Ошибки</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Добавлено доменов: <strong id="finalDomainsTotal">0</strong>
                    </div>
                    
                    <div id="bulkAccountsErrorsList" class="mb-3"></div>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="resetBulkAccountsModal()">
                            <i class="fas fa-redo me-1"></i>Загрузить ещё
                        </button>
                        <button type="button" class="btn btn-primary flex-grow-1" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Обновить страницу
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Глобальные переменные для массовой загрузки аккаунтов
let bulkAccountsQueue = [];
let bulkAccountsRunning = false;
let bulkAccountsStats = { total: 0, success: 0, duplicate: 0, error: 0, domains: 0 };
let bulkAccountsErrors = [];
let bulkAccountsDelay = 1000;
let validatedAccounts = null;

// Валидация списка аккаунтов
async function validateBulkAccounts() {
    const accountsList = document.getElementById('bulkAccountsList').value.trim();
    
    if (!accountsList) {
        showNotification('Введите список аккаунтов', 'warning');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'validate_accounts');
        formData.append('accounts_list', accountsList);
        
        const response = await fetch('add_accounts_bulk_api.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            validatedAccounts = data.valid;
            
            document.getElementById('validationResults').innerHTML = `
                <div class="d-flex justify-content-around">
                    <span class="text-success"><i class="fas fa-check me-1"></i>Валидных: ${data.valid_count}</span>
                    <span class="text-danger"><i class="fas fa-times me-1"></i>С ошибками: ${data.invalid_count}</span>
                </div>
            `;
            
            let errorsHtml = '';
            if (data.invalid.length > 0) {
                errorsHtml = '<div class="alert alert-danger"><h6>Ошибки:</h6><ul class="mb-0">';
                data.invalid.forEach(err => {
                    errorsHtml += `<li>Строка ${err.line}: ${err.error}</li>`;
                });
                if (data.has_more_errors) {
                    errorsHtml += '<li>... и другие ошибки</li>';
                }
                errorsHtml += '</ul></div>';
            }
            document.getElementById('validationErrors').innerHTML = errorsHtml;
            
            // Показываем шаг 2
            document.getElementById('bulkAccountsStep1').classList.add('d-none');
            document.getElementById('bulkAccountsStep2').classList.remove('d-none');
        } else {
            showNotification('Ошибка валидации: ' + data.error, 'error');
        }
    } catch (error) {
        showNotification('Ошибка сети: ' + error.message, 'error');
    }
}

// Вернуться к шагу 1
function backToStep1() {
    document.getElementById('bulkAccountsStep1').classList.remove('d-none');
    document.getElementById('bulkAccountsStep2').classList.add('d-none');
    document.getElementById('bulkAccountsStep3').classList.add('d-none');
    document.getElementById('bulkAccountsStep4').classList.add('d-none');
    document.getElementById('bulkAccountsCloseBtn').disabled = false;
}

// Начать импорт аккаунтов
async function startBulkAccountsImport(useValidated = false) {
    const groupId = document.getElementById('bulkAccountsGroupId').value;
    
    if (!groupId) {
        showNotification('Выберите группу', 'warning');
        return;
    }
    
    let accounts;
    
    if (useValidated && validatedAccounts) {
        accounts = validatedAccounts;
    } else {
        // Парсим список аккаунтов
        const accountsList = document.getElementById('bulkAccountsList').value.trim();
        if (!accountsList) {
            showNotification('Введите список аккаунтов', 'warning');
            return;
        }
        
        accounts = [];
        const lines = accountsList.split('\n');
        for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed || trimmed.indexOf(';') === -1) continue;
            
            const [email, apiKey] = trimmed.split(';', 2);
            if (email && apiKey) {
                accounts.push({ email: email.trim(), api_key: apiKey.trim() });
            }
        }
    }
    
    if (accounts.length === 0) {
        showNotification('Не найдено валидных аккаунтов для импорта', 'warning');
        return;
    }
    
    // Получаем задержку
    bulkAccountsDelay = parseInt(document.getElementById('bulkAccountsSpeed').value) || 1000;
    
    // Инициализация
    bulkAccountsQueue = accounts.map((acc, idx) => ({ ...acc, index: idx, groupId }));
    bulkAccountsStats = { total: accounts.length, success: 0, duplicate: 0, error: 0, domains: 0 };
    bulkAccountsErrors = [];
    bulkAccountsRunning = true;
    
    // Показываем шаг 3 (прогресс)
    document.getElementById('bulkAccountsStep1').classList.add('d-none');
    document.getElementById('bulkAccountsStep2').classList.add('d-none');
    document.getElementById('bulkAccountsStep3').classList.remove('d-none');
    document.getElementById('bulkAccountsCloseBtn').disabled = true;
    
    // Обновляем статистику
    updateBulkStats();
    
    // Очищаем лог
    document.getElementById('bulkAccountsLog').innerHTML = '';
    addBulkLog('info', `Начинаем загрузку ${accounts.length} аккаунтов (задержка: ${bulkAccountsDelay}ms)`);
    
    // Запускаем последовательную обработку
    await processBulkAccountsQueue();
}

// Обработка очереди аккаунтов
async function processBulkAccountsQueue() {
    while (bulkAccountsQueue.length > 0 && bulkAccountsRunning) {
        const account = bulkAccountsQueue.shift();
        
        // Обновляем текущий аккаунт
        document.getElementById('currentAccountStatus').innerHTML = `
            <i class="fas fa-spinner fa-spin me-2"></i>
            <strong>${account.email}</strong>
            <span class="text-muted">(${account.index + 1} из ${bulkAccountsStats.total})</span>
        `;
        
        try {
            const formData = new FormData();
            formData.append('action', 'add_single_account');
            formData.append('email', account.email);
            formData.append('api_key', account.api_key);
            formData.append('group_id', account.groupId);
            
            const response = await fetch('add_accounts_bulk_api.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                if (data.status === 'duplicate') {
                    bulkAccountsStats.duplicate++;
                    addBulkLog('warning', `${account.email} - дубликат`);
                } else {
                    bulkAccountsStats.success++;
                    bulkAccountsStats.domains += data.domains_count || 0;
                    addBulkLog('success', `${account.email} - добавлен (${data.domains_count} доменов)`);
                }
            } else {
                bulkAccountsStats.error++;
                bulkAccountsErrors.push({ email: account.email, error: data.error });
                addBulkLog('error', `${account.email} - ошибка: ${data.error}`);
            }
        } catch (error) {
            bulkAccountsStats.error++;
            bulkAccountsErrors.push({ email: account.email, error: error.message });
            addBulkLog('error', `${account.email} - сетевая ошибка: ${error.message}`);
        }
        
        // Обновляем статистику
        updateBulkStats();
        
        // Задержка перед следующим аккаунтом
        if (bulkAccountsQueue.length > 0 && bulkAccountsRunning) {
            await new Promise(resolve => setTimeout(resolve, bulkAccountsDelay));
        }
    }
    
    // Завершение
    completeButlkAccountsImport();
}

// Обновление статистики
function updateBulkStats() {
    const processed = bulkAccountsStats.success + bulkAccountsStats.duplicate + bulkAccountsStats.error;
    const percent = bulkAccountsStats.total > 0 ? Math.round((processed / bulkAccountsStats.total) * 100) : 0;
    
    document.getElementById('bulkAccountsProgress').style.width = percent + '%';
    document.getElementById('bulkAccountsProgress').textContent = percent + '%';
    
    document.getElementById('bulkStatTotal').textContent = bulkAccountsStats.total;
    document.getElementById('bulkStatSuccess').textContent = bulkAccountsStats.success;
    document.getElementById('bulkStatDuplicate').textContent = bulkAccountsStats.duplicate;
    document.getElementById('bulkStatError').textContent = bulkAccountsStats.error;
    document.getElementById('bulkDomainsTotal').textContent = 'Доменов: ' + bulkAccountsStats.domains;
}

// Добавление записи в лог
function addBulkLog(type, message) {
    const log = document.getElementById('bulkAccountsLog');
    const time = new Date().toLocaleTimeString();
    const colors = { info: '#17a2b8', success: '#28a745', warning: '#ffc107', error: '#dc3545' };
    const icons = { info: 'info-circle', success: 'check-circle', warning: 'exclamation-triangle', error: 'times-circle' };
    
    log.innerHTML += `<div style="color: ${colors[type]}"><i class="fas fa-${icons[type]} me-1"></i>[${time}] ${message}</div>`;
    log.scrollTop = log.scrollHeight;
}

// Остановка импорта
function stopBulkAccountsImport() {
    if (confirm('Остановить загрузку? Уже добавленные аккаунты сохранятся.')) {
        bulkAccountsRunning = false;
        addBulkLog('warning', 'Загрузка остановлена пользователем');
        document.getElementById('bulkAccountsStopBtn').disabled = true;
    }
}

// Завершение импорта
function completeButlkAccountsImport() {
    bulkAccountsRunning = false;
    
    // Обновляем финальную статистику
    document.getElementById('finalStatTotal').textContent = bulkAccountsStats.total;
    document.getElementById('finalStatSuccess').textContent = bulkAccountsStats.success;
    document.getElementById('finalStatDuplicate').textContent = bulkAccountsStats.duplicate;
    document.getElementById('finalStatError').textContent = bulkAccountsStats.error;
    document.getElementById('finalDomainsTotal').textContent = bulkAccountsStats.domains;
    
    // Показываем ошибки если есть
    if (bulkAccountsErrors.length > 0) {
        let errorsHtml = '<div class="alert alert-danger"><h6><i class="fas fa-exclamation-triangle me-2"></i>Ошибки при импорте:</h6><ul class="mb-0" style="max-height: 150px; overflow-y: auto;">';
        bulkAccountsErrors.slice(0, 20).forEach(err => {
            errorsHtml += `<li><strong>${err.email}</strong>: ${err.error}</li>`;
        });
        if (bulkAccountsErrors.length > 20) {
            errorsHtml += `<li>... и ещё ${bulkAccountsErrors.length - 20} ошибок</li>`;
        }
        errorsHtml += '</ul></div>';
        document.getElementById('bulkAccountsErrorsList').innerHTML = errorsHtml;
    }
    
    // Показываем шаг 4 (завершение)
    document.getElementById('bulkAccountsStep3').classList.add('d-none');
    document.getElementById('bulkAccountsStep4').classList.remove('d-none');
    document.getElementById('bulkAccountsCloseBtn').disabled = false;
    
    addBulkLog('info', `Импорт завершен: ${bulkAccountsStats.success} добавлено, ${bulkAccountsStats.duplicate} дубликатов, ${bulkAccountsStats.error} ошибок`);
}

// Сброс модального окна
function resetBulkAccountsModal() {
    bulkAccountsQueue = [];
    bulkAccountsRunning = false;
    bulkAccountsStats = { total: 0, success: 0, duplicate: 0, error: 0, domains: 0 };
    bulkAccountsErrors = [];
    validatedAccounts = null;
    
    document.getElementById('bulkAccountsList').value = '';
    document.getElementById('bulkAccountsLog').innerHTML = '';
    
    backToStep1();
}

// Обработка закрытия модального окна
document.getElementById('addAccountsBulkModal').addEventListener('hidden.bs.modal', function() {
    if (bulkAccountsRunning) {
        bulkAccountsRunning = false;
    }
});
</script>

<!-- Add Account via Queue Modal -->
<div class="modal fade" id="addAccountQueueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Добавить аккаунт (через очередь)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Домены будут автоматически получены из Cloudflare
                </div>
                <form id="addAccountQueueForm">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="accountEmail" placeholder="user@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <input type="text" class="form-control" id="accountApiKey" placeholder="Global API Key или Token" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Группа</label>
                        <select class="form-select" id="accountGroupId" required>
                            <option value="">-- Выберите группу --</option>
                            <?php if (isset($groups)): ?>
                                <?php foreach ($groups as $group): ?>
                                    <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processAccountAdd()">
                    <i class="fas fa-tasks me-1"></i>Добавить в очередь
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===================================
     NS SERVERS MODAL
     =================================== -->

<div class="modal fade" id="nsServersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-server me-2"></i>NS Серверы</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="nsServersContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status"></div>
                        <p class="mt-2 text-muted">Загрузка...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- ===================================
     SETTINGS MODALS
     =================================== -->

<!-- Change IP Modal -->
<div class="modal fade" id="changeIPModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-network-wired me-2"></i>Смена IP адресов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="changeIPForm">
                    <div class="mb-3">
                        <label class="form-label">Новый IP адрес</label>
                        <input type="text" class="form-control" id="newIPAddress" placeholder="192.168.1.1" required pattern="^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$">
                        <div class="form-text">Введите корректный IPv4 адрес</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForIP" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processIPChange()">
                    <i class="fas fa-tasks me-1"></i>Добавить в очередь
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Change SSL Mode Modal -->
<div class="modal fade" id="changeSSLModeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i>Смена SSL режима</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="changeSSLModeForm">
                    <div class="mb-3">
                        <label class="form-label">SSL режим</label>
                        <select class="form-select" id="newSSLMode" required>
                            <option value="">-- Выберите режим --</option>
                            <option value="off">Off - SSL отключен</option>
                            <option value="flexible">Flexible - Частичное шифрование</option>
                            <option value="full">Full - Полное шифрование</option>
                            <option value="strict">Full (strict) - С проверкой сертификата</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForSSL" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processSSLModeChange()">
                    <i class="fas fa-tasks me-1"></i>Применить
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Change TLS Modal -->
<div class="modal fade" id="changeTLSModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-lock me-2"></i>Смена версии TLS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="changeTLSForm">
                    <div class="mb-3">
                        <label class="form-label">Версия TLS</label>
                        <select class="form-select" id="newTLSVersion" required>
                            <option value="">-- Выберите версию --</option>
                            <option value="1.0">TLS 1.0 (не рекомендуется)</option>
                            <option value="1.1">TLS 1.1 (устарело)</option>
                            <option value="1.2">TLS 1.2 (рекомендуется)</option>
                            <option value="1.3">TLS 1.3 (современный)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForTLS" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processTLSChange()">
                    <i class="fas fa-tasks me-1"></i>Применить
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Change HTTPS Modal -->
<div class="modal fade" id="changeHTTPSModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-globe me-2"></i>Always Use HTTPS</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="changeHTTPSForm">
                    <div class="mb-3">
                        <label class="form-label">Настройка</label>
                        <select class="form-select" id="newHTTPSSetting" required>
                            <option value="">-- Выберите --</option>
                            <option value="1">Включить - Принудительное HTTPS</option>
                            <option value="0">Выключить - Разрешить HTTP</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForHTTPS" class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" onclick="processHTTPSChange()">
                    <i class="fas fa-tasks me-1"></i>Применить
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===================================
     WORKERS MODALS
     =================================== -->

<!-- Manage Worker Modal -->
<div class="modal fade" id="manageWorkerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-code me-2"></i>Cloudflare Workers 
                    <span id="workerModalDomainName" class="text-primary"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="workerModalLoader" class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>

                <div id="workerModalContent" class="d-none">
                    <!-- Active Routes -->
                    <div class="mb-4">
                        <h6 class="fw-bold"><i class="fas fa-route me-2"></i>Активные маршруты</h6>
                        <div id="workerRoutesContainer" class="border rounded p-3 bg-light"></div>
                    </div>

                    <!-- Apply Template -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <i class="fas fa-wrench me-2"></i>Применить шаблон
                        </div>
                        <div class="card-body">
                            <form id="workerApplyForm">
                                <input type="hidden" id="workerDomainId">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Шаблон</label>
                                        <select id="workerTemplateSelect" class="form-select">
                                            <option value="">-- Выберите шаблон --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Маршрут</label>
                                        <input type="text" id="workerRoutePattern" class="form-control" placeholder="{{domain}}/*">
                                        <div class="form-text">{{domain}} заменится на домен</div>
                                    </div>
                                </div>
                            </form>
                            <div class="mt-3">
                                <button type="button" class="btn btn-primary" onclick="applyWorkerTemplate()">
                                    <i class="fas fa-play me-1"></i>Применить
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="reloadWorkerModalData()">
                                    <i class="fas fa-sync me-1"></i>Обновить
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Custom Script -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-file-code me-2"></i>Пользовательский скрипт
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <textarea id="workerCustomScript" class="form-control font-monospace" rows="10" placeholder="// Ваш JavaScript код"></textarea>
                            </div>
                            <div class="row align-items-center">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="workerSaveTemplate">
                                        <label class="form-check-label" for="workerSaveTemplate">Сохранить как шаблон</label>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <input type="text" id="workerTemplateName" class="form-control d-none" placeholder="Название шаблона">
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="button" class="btn btn-success" onclick="applyWorkerCustomScript()">
                                    <i class="fas fa-cloud-upload-alt me-1"></i>Загрузить скрипт
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <span id="workerModalStatus" class="me-auto text-muted"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Worker Modal -->
<div class="modal fade" id="bulkWorkerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Массовое применение Workers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" id="bulkWorkerSelectionInfo"></div>
                <form id="bulkWorkerForm">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Шаблон</label>
                            <select id="bulkWorkerTemplate" class="form-select" required>
                                <option value="">-- Выберите шаблон --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Маршрут</label>
                            <input type="text" id="bulkWorkerRoutePattern" class="form-control" placeholder="{{domain}}/*">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Область применения</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="bulkWorkerScope" id="bulkWorkerScopeSelected" value="selected" checked>
                            <label class="form-check-label" for="bulkWorkerScopeSelected">Только выбранные домены</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="bulkWorkerScope" id="bulkWorkerScopeGroup" value="group">
                            <label class="form-check-label" for="bulkWorkerScopeGroup">Вся группа</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="bulkWorkerScope" id="bulkWorkerScopeAll" value="all">
                            <label class="form-check-label" for="bulkWorkerScopeAll">Все домены</label>
                        </div>
                        <div class="mt-2 d-none" id="bulkWorkerGroupWrapper">
                            <select id="bulkWorkerGroup" class="form-select">
                                <option value="">-- Выберите группу --</option>
                                <?php if (isset($groups)): ?>
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </form>
                <div id="bulkWorkerResult" class="mt-3 d-none"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" onclick="bulkApplyWorkers()">
                    <i class="fas fa-cloud-upload-alt me-1"></i>Применить
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===================================
     CERTIFICATES MODAL
     =================================== -->

<div class="modal fade" id="createCertificateModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-certificate me-2"></i>Создание SSL сертификатов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Внимание!</strong> Будут созданы Origin CA сертификаты Cloudflare (срок действия 1 год).
                </div>
                <form id="createCertificateForm">
                    <div class="mb-3">
                        <label class="form-label">Выбранные домены:</label>
                        <div id="selectedDomainsForCert" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto;">
                            <span class="text-muted">Домены не выбраны</span>
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="includeCertificateData">
                        <label class="form-check-label" for="includeCertificateData">
                            Показать сертификаты в логах
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-warning" onclick="processCertificateCreation()">
                    <i class="fas fa-tasks me-1"></i>Создать сертификаты
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===================================
     WORKER TEMPLATE CHECKBOX LOGIC
     =================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle save template input
    const saveTemplateCheck = document.getElementById('workerSaveTemplate');
    const templateNameInput = document.getElementById('workerTemplateName');
    
    if (saveTemplateCheck && templateNameInput) {
        saveTemplateCheck.addEventListener('change', function() {
            templateNameInput.classList.toggle('d-none', !this.checked);
        });
    }
    
    // Toggle group selector
    const scopeRadios = document.querySelectorAll('input[name="bulkWorkerScope"]');
    const groupWrapper = document.getElementById('bulkWorkerGroupWrapper');
    
    scopeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (groupWrapper) {
                groupWrapper.classList.toggle('d-none', this.value !== 'group');
            }
        });
    });
});
</script>