<?php
/**
 * Скрипт мониторинга сервера с отправкой уведомлений в Telegram
 * 
 * Использование:
 * 1. Одиночная проверка: php monitor.php
 * 2. В режиме демона: php monitor.php --daemon
 * 3. Тест Telegram: php monitor.php --test
 * 4. Проверка конкретного URL: php monitor.php --url=https://example.com
 */

require_once __DIR__ . '/config.php';

// Проверяем конфигурацию
if (TELEGRAM_BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE' || TELEGRAM_CHAT_IDS === 'YOUR_CHAT_ID_HERE') {
    echo "❌ ОШИБКА: Настройте Telegram в config.php\n";
    echo "   1. Получите токен бота у @BotFather в Telegram\n";
    echo "   2. Получите свой chat_id у @userinfobot\n";
    echo "   3. Укажите их в config.php\n";
    exit(1);
}

/**
 * Класс для работы с Telegram API
 */
class TelegramNotifier {
    private $token;
    private $chatIds;
    
    public function __construct($token, $chatIds) {
        $this->token = $token;
        $this->chatIds = is_array($chatIds) ? $chatIds : explode(',', $chatIds);
    }
    
    /**
     * Отправить сообщение
     */
    public function send($message, $parseMode = 'HTML') {
        $results = [];
        
        foreach ($this->chatIds as $chatId) {
            $chatId = trim($chatId);
            if (empty($chatId)) continue;
            
            $url = "https://api.telegram.org/bot{$this->token}/sendMessage";
            
            $data = [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            $results[$chatId] = [
                'success' => $httpCode === 200,
                'http_code' => $httpCode,
                'error' => $error,
                'response' => $response
            ];
        }
        
        return $results;
    }
    
    /**
     * Отправить тестовое сообщение
     */
    public function sendTest() {
        $message = "🧪 <b>Тестовое сообщение</b>\n\n";
        $message .= "✅ Бот настроен правильно!\n";
        $message .= "📅 Время: " . date('Y-m-d H:i:s') . "\n";
        $message .= "🖥️ Сервер: " . gethostname();
        
        return $this->send($message);
    }
}

/**
 * Класс для мониторинга URL
 */
class ServerMonitor {
    private $telegram;
    private $state;
    private $stateFile;
    private $logFile;
    private $allowedCodes;
    
    public function __construct(TelegramNotifier $telegram) {
        $this->telegram = $telegram;
        $this->stateFile = STATE_FILE;
        $this->logFile = LOG_FILE;
        $this->allowedCodes = array_map('intval', explode(',', ALLOWED_HTTP_CODES));
        $this->loadState();
    }
    
    /**
     * Загрузить состояние
     */
    private function loadState() {
        if (file_exists($this->stateFile)) {
            $this->state = json_decode(file_get_contents($this->stateFile), true);
        }
        
        if (!is_array($this->state)) {
            $this->state = [
                'errors' => [],      // URL => количество последовательных ошибок
                'down_since' => [],  // URL => timestamp когда упал
                'notified' => []     // URL => timestamp последнего уведомления
            ];
        }
    }
    
    /**
     * Сохранить состояние
     */
    private function saveState() {
        file_put_contents($this->stateFile, json_encode($this->state, JSON_PRETTY_PRINT));
    }
    
    /**
     * Записать в лог
     */
    private function log($message, $level = 'INFO') {
        // Проверяем размер лога
        if (file_exists($this->logFile) && filesize($this->logFile) > MAX_LOG_SIZE) {
            // Ротация лога
            rename($this->logFile, $this->logFile . '.old');
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $logLine = "[$timestamp] [$level] $message\n";
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND);
        
        // Выводим в консоль если запущено интерактивно
        if (php_sapi_name() === 'cli') {
            echo $logLine;
        }
    }
    
    /**
     * Проверить URL
     */
    public function checkUrl($url) {
        $startTime = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => REQUEST_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => false, // Можно включить если есть валидный сертификат
            CURLOPT_USERAGENT => 'ServerMonitor/1.0',
            CURLOPT_NOBODY => false,
            CURLOPT_HEADER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        $result = [
            'url' => $url,
            'http_code' => $httpCode,
            'error' => $error,
            'errno' => $errno,
            'duration_ms' => $duration,
            'total_time' => $totalTime,
            'response_length' => strlen($response),
            'timestamp' => time()
        ];
        
        // Проверяем результат
        if ($errno > 0) {
            $result['status'] = 'error';
            $result['message'] = "cURL Error: $error";
        } elseif (!in_array($httpCode, $this->allowedCodes)) {
            $result['status'] = 'error';
            $result['message'] = "HTTP $httpCode (ожидалось: " . implode('/', $this->allowedCodes) . ")";
        } else {
            $result['status'] = 'ok';
            $result['message'] = "HTTP $httpCode, {$duration}ms";
        }
        
        return $result;
    }
    
    /**
     * Обработать результат проверки
     */
    public function processResult($result) {
        $url = $result['url'];
        
        if ($result['status'] === 'error') {
            // Увеличиваем счетчик ошибок
            if (!isset($this->state['errors'][$url])) {
                $this->state['errors'][$url] = 0;
            }
            $this->state['errors'][$url]++;
            
            // Запоминаем когда упал
            if (!isset($this->state['down_since'][$url])) {
                $this->state['down_since'][$url] = time();
            }
            
            $this->log("ERROR: {$url} - {$result['message']} (ошибка #{$this->state['errors'][$url]})", 'ERROR');
            
            // Отправляем уведомление если превышен порог
            if ($this->state['errors'][$url] >= ERROR_THRESHOLD) {
                // Проверяем, не отправляли ли мы недавно (в течение 5 минут)
                $lastNotified = $this->state['notified'][$url] ?? 0;
                if (time() - $lastNotified > 300) {
                    $this->sendDownNotification($url, $result);
                    $this->state['notified'][$url] = time();
                }
            }
            
        } else {
            // Сервер работает
            if (DEBUG_MODE) {
                $this->log("OK: {$url} - {$result['message']}", 'DEBUG');
            }
            
            // Если был down, отправляем уведомление о восстановлении
            if (isset($this->state['errors'][$url]) && $this->state['errors'][$url] >= ERROR_THRESHOLD) {
                if (NOTIFY_ON_RECOVERY) {
                    $this->sendRecoveryNotification($url, $result);
                }
            }
            
            // Сбрасываем состояние ошибок
            unset($this->state['errors'][$url]);
            unset($this->state['down_since'][$url]);
            unset($this->state['notified'][$url]);
        }
        
        $this->saveState();
    }
    
    /**
     * Отправить уведомление о падении
     */
    private function sendDownNotification($url, $result) {
        $downSince = $this->state['down_since'][$url] ?? time();
        $downDuration = time() - $downSince;
        
        $message = "🔴 <b>СЕРВЕР НЕДОСТУПЕН</b>\n\n";
        $message .= "🌐 <b>URL:</b> <code>{$url}</code>\n";
        $message .= "❌ <b>Ошибка:</b> {$result['message']}\n";
        $message .= "⏱️ <b>Время недоступности:</b> " . $this->formatDuration($downDuration) . "\n";
        $message .= "🔢 <b>Попыток:</b> {$this->state['errors'][$url]}\n";
        $message .= "📅 <b>Время проверки:</b> " . date('Y-m-d H:i:s') . "\n";
        $message .= "🖥️ <b>Монитор:</b> " . gethostname();
        
        $this->log("Отправка уведомления о падении: $url", 'ALERT');
        $this->telegram->send($message);
    }
    
    /**
     * Отправить уведомление о восстановлении
     */
    private function sendRecoveryNotification($url, $result) {
        $downSince = $this->state['down_since'][$url] ?? time();
        $downDuration = time() - $downSince;
        
        $message = "🟢 <b>СЕРВЕР ВОССТАНОВЛЕН</b>\n\n";
        $message .= "🌐 <b>URL:</b> <code>{$url}</code>\n";
        $message .= "✅ <b>Статус:</b> {$result['message']}\n";
        $message .= "⏱️ <b>Был недоступен:</b> " . $this->formatDuration($downDuration) . "\n";
        $message .= "📅 <b>Время восстановления:</b> " . date('Y-m-d H:i:s');
        
        $this->log("Отправка уведомления о восстановлении: $url", 'INFO');
        $this->telegram->send($message);
    }
    
    /**
     * Форматировать длительность
     */
    private function formatDuration($seconds) {
        if ($seconds < 60) return "{$seconds} сек";
        if ($seconds < 3600) return floor($seconds / 60) . " мин";
        return floor($seconds / 3600) . " ч " . floor(($seconds % 3600) / 60) . " мин";
    }
    
    /**
     * Запустить проверку всех URL
     */
    public function runCheck($urls = null) {
        if ($urls === null) {
            $urls = array_map('trim', explode(',', MONITOR_URLS));
        }
        
        $this->log("Начало проверки " . count($urls) . " URL(s)", 'INFO');
        
        $results = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if (empty($url)) continue;
            
            $result = $this->checkUrl($url);
            $this->processResult($result);
            $results[] = $result;
        }
        
        return $results;
    }
    
    /**
     * Запустить в режиме демона
     */
    public function runDaemon() {
        $this->log("Запуск в режиме демона (интервал: " . CHECK_INTERVAL . " сек)", 'INFO');
        
        // Отправляем уведомление о запуске
        $this->telegram->send("🚀 <b>Мониторинг запущен</b>\n\n" .
            "📊 Interval: " . CHECK_INTERVAL . " сек\n" .
            "🌐 URLs: " . MONITOR_URLS . "\n" .
            "📅 " . date('Y-m-d H:i:s'));
        
        while (true) {
            $this->runCheck();
            sleep(CHECK_INTERVAL);
        }
    }
}

// ========================
// MAIN
// ========================

// Обработка аргументов командной строки
$args = getopt('', ['test', 'daemon', 'url:', 'help']);

$telegram = new TelegramNotifier(TELEGRAM_BOT_TOKEN, TELEGRAM_CHAT_IDS);
$monitor = new ServerMonitor($telegram);

// Тест Telegram
if (isset($args['test'])) {
    echo "📤 Отправка тестового сообщения в Telegram...\n";
    $results = $telegram->sendTest();
    
    foreach ($results as $chatId => $result) {
        if ($result['success']) {
            echo "✅ Сообщение отправлено в чат $chatId\n";
        } else {
            echo "❌ Ошибка отправки в чат $chatId: {$result['error']}\n";
            if (DEBUG_MODE) {
                echo "Response: {$result['response']}\n";
            }
        }
    }
    exit(0);
}

// Справка
if (isset($args['help'])) {
    echo "Использование: php monitor.php [ОПЦИИ]\n\n";
    echo "Опции:\n";
    echo "  --test     Отправить тестовое сообщение в Telegram\n";
    echo "  --daemon   Запустить в режиме демона (бесконечный цикл)\n";
    echo "  --url=URL  Проверить конкретный URL\n";
    echo "  --help     Показать эту справку\n\n";
    echo "Примеры:\n";
    echo "  php monitor.php                        # Одиночная проверка настроенных URL\n";
    echo "  php monitor.php --test                 # Тест отправки в Telegram\n";
    echo "  php monitor.php --url=https://ex.com   # Проверить конкретный URL\n";
    echo "  php monitor.php --daemon               # Запуск как демон\n\n";
    echo "Для работы по cron добавьте в crontab:\n";
    echo "  * * * * * /usr/bin/php " . __FILE__ . " >> /dev/null 2>&1\n";
    exit(0);
}

// Режим демона
if (isset($args['daemon'])) {
    $monitor->runDaemon();
    exit(0);
}

// Проверка конкретного URL
if (isset($args['url'])) {
    $results = $monitor->runCheck([$args['url']]);
    foreach ($results as $result) {
        echo ($result['status'] === 'ok' ? '✅' : '❌') . " {$result['url']} - {$result['message']}\n";
    }
    exit($results[0]['status'] === 'ok' ? 0 : 1);
}

// По умолчанию - одиночная проверка
$results = $monitor->runCheck();
$hasErrors = false;
foreach ($results as $result) {
    if ($result['status'] !== 'ok') {
        $hasErrors = true;
    }
}
exit($hasErrors ? 1 : 0);