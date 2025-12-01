<?php
/**
 * Документация системы Cloudflare Panel
 * Полное описание всех функций и возможностей
 */

require_once 'header.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}
?>

<?php include 'sidebar.php'; ?>

<div class="content">
    <!-- Заголовок -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><i class="fas fa-book me-2 text-primary"></i>Документация</h2>
            <p class="text-muted mb-0">Полное руководство по использованию Cloudflare Panel</p>
        </div>
        <a href="dashboard.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Назад
        </a>
    </div>

    <!-- Навигация по документации -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="#overview" class="btn btn-outline-primary btn-sm"><i class="fas fa-home me-1"></i>Обзор</a>
                        <a href="#dashboard" class="btn btn-outline-primary btn-sm"><i class="fas fa-tachometer-alt me-1"></i>Панель управления</a>
                        <a href="#mass-operations" class="btn btn-outline-primary btn-sm"><i class="fas fa-tasks me-1"></i>Массовые операции</a>
                        <a href="#security" class="btn btn-outline-primary btn-sm"><i class="fas fa-shield-alt me-1"></i>Безопасность</a>
                        <a href="#workers" class="btn btn-outline-primary btn-sm"><i class="fas fa-code me-1"></i>Workers</a>
                        <a href="#ssl" class="btn btn-outline-primary btn-sm"><i class="fas fa-lock me-1"></i>SSL/TLS</a>
                        <a href="#dns" class="btn btn-outline-primary btn-sm"><i class="fas fa-server me-1"></i>DNS</a>
                        <a href="#api" class="btn btn-outline-primary btn-sm"><i class="fas fa-plug me-1"></i>API</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Обзор системы -->
    <div class="card mb-4" id="overview">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-home me-2"></i>Обзор системы</h5>
        </div>
        <div class="card-body">
            <p>Cloudflare Panel — это мощная панель управления для массового управления доменами в Cloudflare. Система позволяет:</p>
            <ul class="mb-4">
                <li><strong>Управлять множеством доменов</strong> — добавляйте и настраивайте десятки и сотни доменов одновременно</li>
                <li><strong>Массово изменять настройки</strong> — SSL режим, DNS записи, IP адреса для всех доменов сразу</li>
                <li><strong>Защищать сайты</strong> — настройка правил безопасности, блокировка ботов и геоблокировка</li>
                <li><strong>Развертывать Workers</strong> — установка защитных скриптов на уровне edge-серверов Cloudflare</li>
                <li><strong>Мониторить состояние</strong> — отслеживание SSL сертификатов, доступности и производительности</li>
            </ul>
            
            <div class="alert alert-info">
                <i class="fas fa-lightbulb me-2"></i>
                <strong>Совет:</strong> Для начала работы добавьте учетные данные Cloudflare (email + API ключ) и домены через Dashboard.
            </div>
        </div>
    </div>

    <!-- Панель управления -->
    <div class="card mb-4" id="dashboard">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-tachometer-alt me-2"></i>Панель управления (Dashboard)</h5>
        </div>
        <div class="card-body">
            <p>Главная страница системы отображает все ваши домены с их текущим статусом.</p>
            
            <h6 class="fw-bold mt-4">Кнопки действий:</h6>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Кнопка</th>
                        <th>Описание</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge bg-primary"><i class="fas fa-plus"></i> Добавить домен</span></td>
                        <td>Добавление нового домена в систему. Требуется выбрать аккаунт Cloudflare и указать домен.</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success"><i class="fas fa-upload"></i> Добавить пачкой</span></td>
                        <td>Массовое добавление доменов. Введите список доменов (по одному на строку) и они будут добавлены автоматически.</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-info"><i class="fas fa-sync"></i> Обновить всё</span></td>
                        <td>Синхронизация данных всех доменов с Cloudflare: DNS IP, SSL режим, NS серверы.</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-warning"><i class="fas fa-folder"></i> Группы</span></td>
                        <td>Управление группами для организации доменов. Можно создавать, редактировать и удалять группы.</td>
                    </tr>
                </tbody>
            </table>

            <h6 class="fw-bold mt-4">Фильтрация и поиск:</h6>
            <ul>
                <li><strong>Поиск</strong> — быстрый поиск по имени домена</li>
                <li><strong>Группа</strong> — фильтр по группам доменов</li>
                <li><strong>SSL режим</strong> — фильтр по текущему SSL режиму (Off, Flexible, Full, Strict)</li>
            </ul>
        </div>
    </div>

    <!-- Массовые операции -->
    <div class="card mb-4" id="mass-operations">
        <div class="card-header bg-warning text-dark">
            <h5 class="mb-0"><i class="fas fa-tasks me-2"></i>Массовые операции</h5>
        </div>
        <div class="card-body">
            <p>Страница для выполнения операций над множеством доменов одновременно.</p>
            
            <h6 class="fw-bold mt-4">Доступные операции:</h6>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Операция</th>
                        <th>Описание</th>
                        <th>Что меняется</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Смена IP адресов</strong></td>
                        <td>Изменяет IP адрес во всех A-записях DNS выбранных доменов</td>
                        <td>DNS A-записи в Cloudflare</td>
                    </tr>
                    <tr>
                        <td><strong>Режим SSL</strong></td>
                        <td>Изменяет режим шифрования SSL/TLS</td>
                        <td>
                            <ul class="mb-0">
                                <li><code>Off</code> — без шифрования</li>
                                <li><code>Flexible</code> — шифрование до Cloudflare</li>
                                <li><code>Full</code> — шифрование до сервера</li>
                                <li><code>Full (Strict)</code> — строгое шифрование с проверкой сертификата</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Always Use HTTPS</strong></td>
                        <td>Включает/выключает автоматическое перенаправление HTTP на HTTPS</td>
                        <td>Все HTTP запросы будут редиректиться на HTTPS</td>
                    </tr>
                    <tr>
                        <td><strong>Минимальная версия TLS</strong></td>
                        <td>Устанавливает минимальную версию TLS для соединений</td>
                        <td>
                            <ul class="mb-0">
                                <li><code>TLS 1.0</code> — минимальная безопасность (не рекомендуется)</li>
                                <li><code>TLS 1.2</code> — хороший баланс совместимости и безопасности</li>
                                <li><code>TLS 1.3</code> — максимальная безопасность</li>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td><strong class="text-danger">Удаление доменов</strong></td>
                        <td>Удаляет выбранные домены из панели</td>
                        <td>Домены удаляются только из панели, НЕ из Cloudflare</td>
                    </tr>
                </tbody>
            </table>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Внимание:</strong> Операции выполняются последовательно с задержкой 0.5 секунды между доменами для избежания rate limiting Cloudflare API.
            </div>
        </div>
    </div>

    <!-- Безопасность -->
    <div class="card mb-4" id="security">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Security Rules Manager</h5>
        </div>
        <div class="card-body">
            <p>Мощный инструмент для настройки правил безопасности через Cloudflare Firewall Rules. Все правила создаются через официальное API Cloudflare и работают на уровне edge-серверов Cloudflare, обеспечивая защиту до того, как запрос достигнет вашего сервера.</p>
            
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Как это работает:</strong> Система отправляет запросы к Cloudflare API для создания Firewall Rules в выбранных зонах (доменах). Каждое правило содержит условие (expression) и действие (action).
            </div>
            
            <h6 class="fw-bold mt-4">Подробное описание правил:</h6>
            
            <div class="accordion" id="securityAccordion">
                <!-- Блокировка ботов -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#botBlocker">
                            <i class="fas fa-robot me-2 text-warning"></i>Блокировка ботов (Bad Bot Blocker)
                        </button>
                    </h2>
                    <div id="botBlocker" class="accordion-collapse collapse show" data-bs-parent="#securityAccordion">
                        <div class="accordion-body">
                            <h6 class="text-primary fw-bold">Описание:</h6>
                            <p>Создает правила Cloudflare Firewall для блокировки запросов с вредоносными User-Agent. Боты идентифицируются по подстрокам в заголовке User-Agent HTTP запроса.</p>
                            
                            <h6 class="text-primary fw-bold mt-3">Как работает технически:</h6>
                            <ol>
                                <li>Система загружает список плохих ботов (из nginx-ultimate-bad-bot-blocker или встроенный)</li>
                                <li>Формирует выражение Cloudflare: <code>(lower(http.user_agent) contains "semrush") or (lower(http.user_agent) contains "ahrefs") or ...</code></li>
                                <li>Создает правило с действием <strong>Block</strong> через API: <code>POST zones/{zone_id}/firewall/rules</code></li>
                            </ol>
                            
                            <h6 class="text-primary fw-bold mt-3">Категории блокировки:</h6>
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr><th>Категория</th><th>Примеры ботов</th><th>Описание</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>SEO парсеры</strong></td>
                                        <td>semrush, ahrefs, majestic, mj12bot, dotbot</td>
                                        <td>Сервисы анализа сайтов конкурентов</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Сканеры уязвимостей</strong></td>
                                        <td>nikto, nmap, sqlmap, acunetix, metasploit</td>
                                        <td>Инструменты поиска уязвимостей</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Скрейперы</strong></td>
                                        <td>scrapy, python-requests, wget, curl</td>
                                        <td>Автоматизированные инструменты сбора данных</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Спам/Malware</strong></td>
                                        <td>morfeus, grendel, webinspector, jorgee</td>
                                        <td>Известные вредоносные боты</td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Ограничение:</strong> Cloudflare имеет лимит на длину выражения. Система автоматически ограничивает список до 100 ботов.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Блокировка IP -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#ipBlocker">
                            <i class="fas fa-ban me-2 text-danger"></i>Блокировка IP (IP Access Rules)
                        </button>
                    </h2>
                    <div id="ipBlocker" class="accordion-collapse collapse" data-bs-parent="#securityAccordion">
                        <div class="accordion-body">
                            <h6 class="text-danger fw-bold">Описание:</h6>
                            <p>Создает правила доступа Cloudflare для блокировки конкретных IP адресов или диапазонов (CIDR). Использует Access Rules API.</p>
                            
                            <h6 class="text-danger fw-bold mt-3">Как работает технически:</h6>
                            <ol>
                                <li>Для каждого IP/CIDR создается отдельное правило доступа</li>
                                <li>API запрос: <code>POST zones/{zone_id}/firewall/access_rules/rules</code></li>
                                <li>Тело запроса:
                                    <pre class="bg-light p-2 rounded mt-2"><code>{
  "mode": "block",
  "configuration": {
    "target": "ip" или "ip_range",
    "value": "192.168.1.1" или "10.0.0.0/8"
  },
  "notes": "Auto IP Block - CloudPanel"
}</code></pre>
                                </li>
                            </ol>
                            
                            <h6 class="text-danger fw-bold mt-3">Поддерживаемые форматы:</h6>
                            <ul>
                                <li><code>192.168.1.1</code> — одиночный IPv4</li>
                                <li><code>10.0.0.0/8</code> — CIDR нотация (диапазон)</li>
                                <li><code>2001:db8::1</code> — одиночный IPv6</li>
                                <li><code>2001:db8::/32</code> — CIDR IPv6</li>
                            </ul>
                            
                            <h6 class="text-danger fw-bold mt-3">Импорт известных IP:</h6>
                            <p>При включении опции "Импортировать известные вредоносные IP" система загружает списки из:</p>
                            <ul>
                                <li>mitchellkrogza/Suspicious.Snooping.Sniffing.Hacking.IP.Addresses (GitHub)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Геоблокировка -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#geoBlocker">
                            <i class="fas fa-globe me-2 text-info"></i>Геоблокировка (Geo Blocking)
                        </button>
                    </h2>
                    <div id="geoBlocker" class="accordion-collapse collapse" data-bs-parent="#securityAccordion">
                        <div class="accordion-body">
                            <h6 class="text-info fw-bold">Описание:</h6>
                            <p>Создает правила на основе географического положения посетителя (по IP). Cloudflare определяет страну по базе MaxMind GeoIP2.</p>
                            
                            <h6 class="text-info fw-bold mt-3">Режимы работы:</h6>
                            <table class="table table-bordered">
                                <thead class="table-light">
                                    <tr><th>Режим</th><th>Выражение Cloudflare</th><th>Логика</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="badge bg-success">Whitelist</span></td>
                                        <td><code>(not ip.geoip.country in {"RU" "US" "DE"})</code></td>
                                        <td>Блокировать всех, кроме указанных стран</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-danger">Blacklist</span></td>
                                        <td><code>(ip.geoip.country in {"CN" "KP" "IR"})</code></td>
                                        <td>Блокировать только указанные страны</td>
                                    </tr>
                                    <tr>
                                        <td><span class="badge bg-primary">Оба</span></td>
                                        <td>Создаются 2 отдельных правила</td>
                                        <td>Whitelist И Blacklist одновременно</td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <h6 class="text-info fw-bold mt-3">Как работает технически:</h6>
                            <ol>
                                <li>Выбранные страны группируются в выражение с оператором <code>in</code></li>
                                <li>Для Whitelist добавляется NOT (блокировать всех НЕ из списка)</li>
                                <li>API запрос: <code>POST zones/{zone_id}/firewall/rules</code></li>
                            </ol>
                            
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <div class="card border-success">
                                        <div class="card-header bg-success text-white">Пример Whitelist</div>
                                        <div class="card-body">
                                            <p class="small mb-1">Разрешить только RU, US, DE:</p>
                                            <pre class="bg-light p-2 rounded small"><code>(not ip.geoip.country in {"RU" "US" "DE"})
→ Action: BLOCK</code></pre>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-danger">
                                        <div class="card-header bg-danger text-white">Пример Blacklist</div>
                                        <div class="card-body">
                                            <p class="small mb-1">Заблокировать CN, KP:</p>
                                            <pre class="bg-light p-2 rounded small"><code>(ip.geoip.country in {"CN" "KP"})
→ Action: BLOCK</code></pre>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="text-info fw-bold mt-4">Популярные коды стран:</h6>
                            <p class="small">
                                <code>RU</code>=Россия, <code>US</code>=США, <code>UA</code>=Украина, <code>BY</code>=Беларусь, <code>KZ</code>=Казахстан,
                                <code>DE</code>=Германия, <code>GB</code>=Великобритания, <code>FR</code>=Франция, <code>CN</code>=Китай, <code>JP</code>=Япония
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Только поисковики -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#referrerOnly">
                            <i class="fas fa-search me-2 text-success"></i>Только поисковики (Referrer Protection)
                        </button>
                    </h2>
                    <div id="referrerOnly" class="accordion-collapse collapse" data-bs-parent="#securityAccordion">
                        <div class="accordion-body">
                            <h6 class="text-success fw-bold">Описание:</h6>
                            <p>Блокирует прямой доступ к сайту. Разрешает только переходы с поисковых систем и указанных доменов. Проверяет HTTP заголовок <code>Referer</code>.</p>
                            
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>ВНИМАНИЕ!</strong> Эта настройка заблокирует:
                                <ul class="mb-0">
                                    <li>Прямой ввод URL в браузере</li>
                                    <li>Переходы из закладок</li>
                                    <li>Ссылки из мессенджеров (Telegram, WhatsApp)</li>
                                    <li>Email рассылки</li>
                                </ul>
                            </div>
                            
                            <h6 class="text-success fw-bold mt-3">Как работает технически:</h6>
                            <ol>
                                <li>Формируется выражение проверки заголовка <code>http.referer</code></li>
                                <li>Разрешенные источники объединяются через OR</li>
                                <li>Конечное выражение:
                                    <pre class="bg-light p-2 rounded mt-2"><code>(not (
  (http.referer contains "google.") or
  (http.referer contains "yandex.") or
  (http.referer contains "bing.com")
))</code></pre>
                                </li>
                            </ol>
                            
                            <h6 class="text-success fw-bold mt-3">Доступные действия:</h6>
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr><th>Действие</th><th>Код</th><th>Описание</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Block</strong></td>
                                        <td>403</td>
                                        <td>Показать страницу ошибки 403 Forbidden</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Challenge</strong></td>
                                        <td>CAPTCHA</td>
                                        <td>Показать JavaScript challenge или CAPTCHA</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Redirect</strong></td>
                                        <td>302</td>
                                        <td>Перенаправить на указанную страницу</td>
                                    </tr>
                                </tbody>
                            </table>
                            
                            <h6 class="text-success fw-bold mt-3">Исключения URL:</h6>
                            <p>Можно указать пути, которые должны быть доступны всегда:</p>
                            <ul>
                                <li><code>/api/*</code> — для API запросов</li>
                                <li><code>/robots.txt</code> — для поисковых роботов</li>
                                <li><code>/sitemap.xml</code> — для индексации</li>
                                <li><code>/.well-known/*</code> — для верификации</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cloudflare Workers -->
    <div class="card mb-4" id="workers">
        <div class="card-header bg-dark text-white">
            <h5 class="mb-0"><i class="fas fa-code me-2"></i>Cloudflare Workers</h5>
        </div>
        <div class="card-body">
            <p>Workers — это скрипты JavaScript, выполняющиеся на edge-серверах Cloudflare. Они перехватывают и обрабатывают запросы до того, как они достигнут вашего сервера. В отличие от Firewall Rules, Workers позволяют выполнять сложную логику и модифицировать запросы/ответы.</p>
            
            <div class="alert alert-info mb-4">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Преимущества Workers над Firewall Rules:</strong>
                <ul class="mb-0 mt-2">
                    <li>Полный контроль над запросом и ответом</li>
                    <li>Возможность использовать KV Storage для хранения данных</li>
                    <li>Сложная логика (циклы, условия, регулярные выражения)</li>
                    <li>Rate limiting на уровне edge без нагрузки на сервер</li>
                </ul>
            </div>
            
            <h6 class="fw-bold mt-4">Подробное описание шаблонов:</h6>
            
            <div class="accordion" id="workersAccordion">
                <!-- Advanced Protection -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#advancedProtection">
                            <i class="fas fa-shield-virus me-2 text-primary"></i>Advanced Protection (Полная защита)
                        </button>
                    </h2>
                    <div id="advancedProtection" class="accordion-collapse collapse show" data-bs-parent="#workersAccordion">
                        <div class="accordion-body">
                            <h6 class="text-primary fw-bold">Описание:</h6>
                            <p>Комплексный скрипт защиты, объединяющий все механизмы: блокировку ботов, геоблокировку, защиту от прямого доступа и rate limiting. Рекомендуется для продакшена.</p>
                            
                            <h6 class="text-primary fw-bold mt-3">Функции:</h6>
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr><th>Функция</th><th>Описание</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td><strong>Bot Blocking</strong></td><td>Блокировка по User-Agent (список BAD_BOTS)</td></tr>
                                    <tr><td><strong>Geo Blocking</strong></td><td>Whitelist/Blacklist стран по CF-IPCountry</td></tr>
                                    <tr><td><strong>Referrer Check</strong></td><td>Проверка источника перехода</td></tr>
                                    <tr><td><strong>Rate Limiting</strong></td><td>Ограничение запросов с IP (требует KV)</td></tr>
                                </tbody>
                            </table>
                            
                            <h6 class="text-primary fw-bold mt-3">Конфигурационные переменные:</h6>
                            <pre class="bg-light p-2 rounded"><code>const CONFIG = {
  ALLOWED_COUNTRIES: ['RU', 'US', 'DE'],  // Whitelist стран
  BLOCKED_COUNTRIES: ['CN', 'KP'],         // Blacklist стран
  CHECK_REFERRER: true,                    // Включить проверку реферера
  ALLOWED_REFERRERS: ['google.', 'yandex.'], // Разрешенные рефереры
  RATE_LIMIT: 100,                         // Запросов в минуту
  RATE_LIMIT_WINDOW: 60                    // Окно в секундах
};</code></pre>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Требует KV namespace</strong> для rate limiting. Без KV функция rate limiting будет отключена.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bot Only -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#botOnly">
                            <i class="fas fa-robot me-2 text-warning"></i>Bot Blocker (Только блокировка ботов)
                        </button>
                    </h2>
                    <div id="botOnly" class="accordion-collapse collapse" data-bs-parent="#workersAccordion">
                        <div class="accordion-body">
                            <h6 class="text-warning fw-bold">Описание:</h6>
                            <p>Легковесный скрипт для блокировки нежелательных ботов по User-Agent. Не требует KV storage и имеет минимальную задержку.</p>
                            
                            <h6 class="text-warning fw-bold mt-3">Как работает:</h6>
                            <ol>
                                <li>Worker перехватывает входящий запрос</li>
                                <li>Извлекает заголовок <code>User-Agent</code></li>
                                <li>Сравнивает с массивом <code>BAD_BOTS</code> (регистронезависимо)</li>
                                <li>При совпадении возвращает 403 Forbidden</li>
                                <li>Иначе пропускает запрос к origin</li>
                            </ol>
                            
                            <h6 class="text-warning fw-bold mt-3">Пример кода:</h6>
                            <pre class="bg-light p-2 rounded small"><code>const BAD_BOTS = [
  'semrush', 'ahrefs', 'mj12bot', 'dotbot',
  'rogerbot', 'gigabot', 'blexbot', 'linkfluence'
];

addEventListener('fetch', event => {
  const ua = event.request.headers.get('User-Agent') || '';
  const isBot = BAD_BOTS.some(bot =>
    ua.toLowerCase().includes(bot.toLowerCase())
  );
  
  if (isBot) {
    return event.respondWith(
      new Response('Forbidden', { status: 403 })
    );
  }
  return event.respondWith(fetch(event.request));
});</code></pre>
                            
                            <h6 class="text-warning fw-bold mt-3">Список ботов по умолчанию:</h6>
                            <p class="small">semrush, ahrefs, mj12bot, dotbot, rogerbot, gigabot, blexbot, linkfluence, sogou, baiduspider, yandexbot (опционально), seznambot, exabot</p>
                        </div>
                    </div>
                </div>
                
                <!-- Geo Only -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#geoOnly">
                            <i class="fas fa-globe me-2 text-info"></i>Geo Blocker (Только геоблокировка)
                        </button>
                    </h2>
                    <div id="geoOnly" class="accordion-collapse collapse" data-bs-parent="#workersAccordion">
                        <div class="accordion-body">
                            <h6 class="text-info fw-bold">Описание:</h6>
                            <p>Геоблокировка на уровне Worker с использованием заголовка <code>CF-IPCountry</code>, который Cloudflare автоматически добавляет к каждому запросу.</p>
                            
                            <h6 class="text-info fw-bold mt-3">Режимы работы:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card border-success mb-2">
                                        <div class="card-body p-2">
                                            <strong class="text-success">Whitelist режим:</strong><br>
                                            <small>Разрешить только указанные страны, все остальные заблокировать</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-danger mb-2">
                                        <div class="card-body p-2">
                                            <strong class="text-danger">Blacklist режим:</strong><br>
                                            <small>Заблокировать только указанные страны, все остальные пропустить</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <h6 class="text-info fw-bold mt-3">Пример кода (Whitelist):</h6>
                            <pre class="bg-light p-2 rounded small"><code>const ALLOWED_COUNTRIES = ['RU', 'US', 'DE', 'GB'];

addEventListener('fetch', event => {
  const country = event.request.headers.get('CF-IPCountry');
  
  if (!ALLOWED_COUNTRIES.includes(country)) {
    return event.respondWith(
      new Response('Access denied from your country', {
        status: 403,
        headers: { 'Content-Type': 'text/plain' }
      })
    );
  }
  return event.respondWith(fetch(event.request));
});</code></pre>
                            
                            <h6 class="text-info fw-bold mt-3">Специальные значения CF-IPCountry:</h6>
                            <ul class="small">
                                <li><code>XX</code> — страна не определена</li>
                                <li><code>T1</code> — Tor exit node</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Rate Limiting -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#rateLimiting">
                            <i class="fas fa-tachometer-alt me-2 text-danger"></i>Rate Limiting (Ограничение запросов)
                        </button>
                    </h2>
                    <div id="rateLimiting" class="accordion-collapse collapse" data-bs-parent="#workersAccordion">
                        <div class="accordion-body">
                            <h6 class="text-danger fw-bold">Описание:</h6>
                            <p>Защита от DDoS и брутфорса путем ограничения количества запросов с одного IP адреса. Использует Cloudflare KV для хранения счетчиков.</p>
                            
                            <h6 class="text-danger fw-bold mt-3">Как работает:</h6>
                            <ol>
                                <li>Worker получает IP клиента из <code>CF-Connecting-IP</code></li>
                                <li>Формирует ключ: <code>rate:{IP}:{минута}</code></li>
                                <li>Читает текущее значение из KV</li>
                                <li>Если превышен лимит — возвращает 429 Too Many Requests</li>
                                <li>Иначе инкрементирует счетчик и пропускает запрос</li>
                            </ol>
                            
                            <h6 class="text-danger fw-bold mt-3">Конфигурация:</h6>
                            <pre class="bg-light p-2 rounded"><code>const RATE_LIMIT = 100;        // Максимум запросов
const WINDOW_SIZE = 60;        // Окно в секундах
const KV_NAMESPACE = RATE_KV;  // Привязанный KV namespace</code></pre>
                            
                            <div class="alert alert-danger mt-3">
                                <i class="fas fa-database me-2"></i>
                                <strong>Требует KV namespace!</strong> Без привязки KV storage Worker не сможет хранить счетчики и rate limiting работать не будет.
                            </div>
                            
                            <h6 class="text-danger fw-bold mt-3">Настройка KV в Cloudflare:</h6>
                            <ol class="small">
                                <li>Перейдите в Workers > KV</li>
                                <li>Создайте новый namespace (например, RATE_LIMITER)</li>
                                <li>В настройках Worker привяжите namespace с именем RATE_KV</li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <!-- Referrer Only -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#referrerOnlyWorker">
                            <i class="fas fa-search me-2 text-success"></i>Referrer Only (Только поисковики)
                        </button>
                    </h2>
                    <div id="referrerOnlyWorker" class="accordion-collapse collapse" data-bs-parent="#workersAccordion">
                        <div class="accordion-body">
                            <h6 class="text-success fw-bold">Описание:</h6>
                            <p>Разрешает доступ только для переходов с поисковых систем. Блокирует прямой доступ по URL, из закладок и мессенджеров.</p>
                            
                            <h6 class="text-success fw-bold mt-3">Как работает:</h6>
                            <ol>
                                <li>Worker проверяет заголовок <code>Referer</code></li>
                                <li>Сравнивает с массивом разрешенных источников</li>
                                <li>При отсутствии Referer или неразрешенном источнике — блокирует</li>
                            </ol>
                            
                            <h6 class="text-success fw-bold mt-3">Разрешенные источники по умолчанию:</h6>
                            <pre class="bg-light p-2 rounded small"><code>const ALLOWED_REFERRERS = [
  'google.',      // Google (все домены)
  'yandex.',      // Яндекс
  'bing.com',     // Bing
  'yahoo.com',    // Yahoo
  'duckduckgo.com', // DuckDuckGo
  'baidu.com',    // Baidu
  'mail.ru',      // Mail.ru
  'rambler.ru'    // Rambler
];</code></pre>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Внимание!</strong> Заголовок Referer может отсутствовать:
                                <ul class="mb-0 mt-1 small">
                                    <li>При прямом вводе URL</li>
                                    <li>Из HTTPS → HTTP перехода</li>
                                    <li>При настройке Referrer-Policy: no-referrer</li>
                                    <li>В режиме инкогнито некоторых браузеров</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <h6 class="fw-bold mt-4">Как развернуть Worker:</h6>
            <div class="alert alert-info">
                <ol class="mb-0">
                    <li>Перейдите в Security Rules Manager → вкладка "Worker Manager"</li>
                    <li>Выберите шаблон из списка слева</li>
                    <li>Просмотрите и отредактируйте код при необходимости</li>
                    <li>Укажите Route Pattern (например, <code>example.com/*</code>)</li>
                    <li>Выберите домены для развертывания</li>
                    <li>Нажмите "Развернуть Worker"</li>
                </ol>
            </div>
            
            <h6 class="fw-bold mt-4">Route Patterns:</h6>
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>Pattern</th><th>Описание</th><th>Примеры URL</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>example.com/*</code></td>
                        <td>Все пути на домене</td>
                        <td>example.com/, example.com/page, example.com/api/v1</td>
                    </tr>
                    <tr>
                        <td><code>*.example.com/*</code></td>
                        <td>Все субдомены</td>
                        <td>www.example.com, api.example.com, blog.example.com</td>
                    </tr>
                    <tr>
                        <td><code>example.com/api/*</code></td>
                        <td>Только /api/ пути</td>
                        <td>example.com/api/users, example.com/api/data</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SSL/TLS -->
    <div class="card mb-4" id="ssl">
        <div class="card-header bg-success text-white">
            <h5 class="mb-0"><i class="fas fa-lock me-2"></i>SSL/TLS и Сертификаты</h5>
        </div>
        <div class="card-body">
            <h6 class="fw-bold">Режимы SSL:</h6>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Режим</th>
                        <th>Описание</th>
                        <th>Когда использовать</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="badge bg-secondary">Off</span></td>
                        <td>SSL отключен. Соединение не шифруется.</td>
                        <td>Не рекомендуется. Только для тестирования.</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-warning text-dark">Flexible</span></td>
                        <td>Шифрование между браузером и Cloudflare. Соединение Cloudflare → Сервер не шифруется.</td>
                        <td>Когда на сервере нет SSL сертификата.</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-primary">Full</span></td>
                        <td>Шифрование на всем пути. Cloudflare принимает любой сертификат на сервере.</td>
                        <td>Когда есть самоподписанный сертификат.</td>
                    </tr>
                    <tr>
                        <td><span class="badge bg-success">Full (Strict)</span></td>
                        <td>Полное шифрование с проверкой валидности сертификата на сервере.</td>
                        <td><strong>Рекомендуется.</strong> Требует валидный SSL на сервере (Origin CA или Let's Encrypt).</td>
                    </tr>
                </tbody>
            </table>

            <h6 class="fw-bold mt-4">Сертификаты:</h6>
            <ul>
                <li><strong>Universal SSL</strong> — бесплатный сертификат от Cloudflare (edge)</li>
                <li><strong>Origin CA</strong> — сертификат для установки на сервер (15 лет)</li>
                <li><strong>Custom Certificate</strong> — загрузка собственного сертификата</li>
            </ul>

            <div class="alert alert-success">
                <i class="fas fa-certificate me-2"></i>
                <strong>Рекомендация:</strong> Используйте Origin CA сертификат на сервере + Full (Strict) режим для максимальной безопасности.
            </div>
        </div>
    </div>

    <!-- DNS -->
    <div class="card mb-4" id="dns">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="fas fa-server me-2"></i>Управление DNS</h5>
        </div>
        <div class="card-body">
            <h6 class="fw-bold">Типы DNS записей:</h6>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Тип</th>
                        <th>Назначение</th>
                        <th>Пример</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>A</code></td>
                        <td>Привязка домена к IPv4 адресу</td>
                        <td>example.com → 1.2.3.4</td>
                    </tr>
                    <tr>
                        <td><code>AAAA</code></td>
                        <td>Привязка домена к IPv6 адресу</td>
                        <td>example.com → 2001:db8::1</td>
                    </tr>
                    <tr>
                        <td><code>CNAME</code></td>
                        <td>Алиас (псевдоним) на другой домен</td>
                        <td>www.example.com → example.com</td>
                    </tr>
                    <tr>
                        <td><code>MX</code></td>
                        <td>Почтовые серверы</td>
                        <td>mail.example.com (приоритет 10)</td>
                    </tr>
                    <tr>
                        <td><code>TXT</code></td>
                        <td>Текстовые записи (SPF, DKIM, верификация)</td>
                        <td>v=spf1 include:_spf.google.com ~all</td>
                    </tr>
                    <tr>
                        <td><code>NS</code></td>
                        <td>Серверы имен</td>
                        <td>ns1.cloudflare.com</td>
                    </tr>
                </tbody>
            </table>

            <h6 class="fw-bold mt-4">Массовая смена IP:</h6>
            <p>В разделе "Массовые операции" вы можете изменить IP адрес для всех A-записей выбранных доменов одновременно. Это полезно при:</p>
            <ul>
                <li>Переезде на новый сервер</li>
                <li>Смене хостинга</li>
                <li>Балансировке нагрузки</li>
            </ul>
        </div>
    </div>

    <!-- API -->
    <div class="card mb-4" id="api">
        <div class="card-header bg-purple text-white" style="background-color: #6f42c1;">
            <h5 class="mb-0"><i class="fas fa-plug me-2"></i>Cloudflare API</h5>
        </div>
        <div class="card-body">
            <h6 class="fw-bold">Настройка API доступа:</h6>
            <ol>
                <li>Войдите в <a href="https://dash.cloudflare.com" target="_blank">Cloudflare Dashboard</a></li>
                <li>Перейдите в My Profile → API Tokens</li>
                <li>Создайте токен или используйте Global API Key</li>
            </ol>

            <h6 class="fw-bold mt-4">Типы аутентификации:</h6>
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Метод</th>
                        <th>Требуется</th>
                        <th>Рекомендация</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Global API Key</strong></td>
                        <td>Email + API Key</td>
                        <td>Полный доступ ко всему аккаунту. Используйте осторожно.</td>
                    </tr>
                    <tr>
                        <td><strong>API Token</strong></td>
                        <td>Только токен</td>
                        <td><strong>Рекомендуется.</strong> Ограниченные права, можно отозвать.</td>
                    </tr>
                </tbody>
            </table>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Безопасность:</strong> Никогда не делитесь своими API ключами. Храните их в безопасном месте.
            </div>

            <h6 class="fw-bold mt-4">Rate Limiting API:</h6>
            <p>Cloudflare ограничивает количество запросов к API:</p>
            <ul>
                <li>1200 запросов в 5 минут (4 запроса в секунду)</li>
                <li>Панель автоматически добавляет задержки между операциями</li>
                <li>При большом количестве доменов используйте очередь задач</li>
            </ul>
        </div>
    </div>

    <!-- Очередь задач -->
    <div class="card mb-4">
        <div class="card-header bg-orange text-white" style="background-color: #fd7e14;">
            <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Очередь задач</h5>
        </div>
        <div class="card-body">
            <p>Система очередей позволяет выполнять длительные операции в фоновом режиме.</p>
            
            <h6 class="fw-bold">Возможности:</h6>
            <ul>
                <li>Добавление задач в очередь</li>
                <li>Автоматическая обработка по расписанию</li>
                <li>Повторное выполнение при ошибках</li>
                <li>Логирование результатов</li>
            </ul>

            <h6 class="fw-bold mt-4">Статусы задач:</h6>
            <ul>
                <li><span class="badge bg-secondary">pending</span> — ожидает выполнения</li>
                <li><span class="badge bg-primary">processing</span> — выполняется</li>
                <li><span class="badge bg-success">completed</span> — успешно завершена</li>
                <li><span class="badge bg-danger">failed</span> — ошибка выполнения</li>
            </ul>
        </div>
    </div>

    <!-- Прокси -->
    <div class="card mb-4">
        <div class="card-header bg-teal text-white" style="background-color: #20c997;">
            <h5 class="mb-0"><i class="fas fa-network-wired me-2"></i>Прокси серверы</h5>
        </div>
        <div class="card-body">
            <p>Система поддерживает работу через прокси серверы для обхода блокировок или балансировки запросов.</p>
            
            <h6 class="fw-bold">Формат прокси:</h6>
            <pre class="bg-light p-3 rounded"><code>IP:PORT@LOGIN:PASSWORD</code></pre>
            <p>Пример: <code>192.168.1.100:8080@user:pass123</code></p>

            <h6 class="fw-bold mt-4">Статусы прокси:</h6>
            <ul>
                <li><span class="badge bg-secondary">Не проверен</span> — прокси добавлен, но не протестирован</li>
                <li><span class="badge bg-success">Работает</span> — прокси успешно прошел проверку</li>
                <li><span class="badge bg-danger">Не работает</span> — прокси недоступен или не отвечает</li>
            </ul>
        </div>
    </div>

    <!-- Поддержка -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-question-circle me-2 text-primary"></i>Помощь и поддержка</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="fw-bold">Полезные ссылки:</h6>
                    <ul>
                        <li><a href="https://developers.cloudflare.com/api/" target="_blank">Cloudflare API Documentation</a></li>
                        <li><a href="https://developers.cloudflare.com/workers/" target="_blank">Cloudflare Workers Docs</a></li>
                        <li><a href="https://developers.cloudflare.com/ssl/" target="_blank">SSL/TLS Documentation</a></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="fw-bold">Частые проблемы:</h6>
                    <ul>
                        <li><strong>Ошибка API</strong> — проверьте правильность API ключа</li>
                        <li><strong>Zone не найдена</strong> — домен должен быть добавлен в Cloudflare</li>
                        <li><strong>Rate limit</strong> — подождите несколько минут и повторите</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>