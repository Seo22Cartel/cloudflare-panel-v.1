/**
 * Cloudflare Worker: Advanced Protection
 * Полная защита: боты + IP + геоблокировка + referrer проверка
 * 
 * Создано CloudPanel Security Manager
 */

// Список плохих ботов (топ 100 из nginx-ultimate-bad-bot-blocker)
const BAD_BOTS = {{BAD_BOTS_LIST}};

// Список вредоносных IP (пример)
const BLOCKED_IPS = {{BLOCKED_IPS_LIST}};

// Разрешенные страны (whitelist) или заблокированные (blacklist)
const GEO_MODE = '{{GEO_MODE}}'; // 'whitelist' или 'blacklist'
const ALLOWED_COUNTRIES = {{ALLOWED_COUNTRIES_LIST}}; // Измените на свои

// Разрешенные реферреры
const ALLOWED_REFERRERS = {{ALLOWED_REFERRERS_LIST}};

// Исключения (URLs которые всегда доступны)
const URL_EXCEPTIONS = {{URL_EXCEPTIONS_LIST}};

// Rate limiting настройки
const RATE_LIMIT = {
    requests: 100,     // Максимум запросов
    window: 60,        // За период (секунд)
    enabled: true
};

addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    try {
        const url = new URL(request.url);
        const ip = request.headers.get('CF-Connecting-IP');
        const userAgent = request.headers.get('User-Agent') || '';
        const referer = request.headers.get('Referer') || '';
        const country = request.cf?.country || 'XX';
        
        // Проверка исключений
        if (isExcludedUrl(url.pathname)) {
            return fetch(request);
        }
        
        // 1. Проверка bad bots
        if (isBadBot(userAgent)) {
            return blockRequest('Bad Bot Detected', 403);
        }
        
        // 2. Проверка IP
        if (isBlockedIP(ip)) {
            return blockRequest('IP Blocked', 403);
        }
        
        // 3. Геоблокировка
        if (!isAllowedCountry(country)) {
            return blockRequest('Access Denied: Geographic Restriction', 403);
        }
        
        // 4. Проверка referrer
        if (!isValidReferrer(referer)) {
            return blockRequest('Access Denied: Direct Access Not Allowed', 403);
        }
        
        // 5. Rate Limiting
        if (RATE_LIMIT.enabled) {
            const rateLimitCheck = await checkRateLimit(ip);
            if (!rateLimitCheck.allowed) {
                return blockRequest('Rate Limit Exceeded', 429);
            }
        }
        
        // Все проверки пройдены, пропускаем запрос
        return fetch(request);
        
    } catch (error) {
        // В случае ошибки пропускаем запрос
        return fetch(request);
    }
}

function isBadBot(userAgent) {
    const ua = userAgent.toLowerCase();
    return BAD_BOTS.some(bot => ua.includes(bot.toLowerCase()));
}

function isBlockedIP(ip) {
    return BLOCKED_IPS.includes(ip);
}

function isAllowedCountry(country) {
    if (GEO_MODE === 'whitelist') {
        return ALLOWED_COUNTRIES.includes(country);
    } else {
        return !ALLOWED_COUNTRIES.includes(country);
    }
}

function isValidReferrer(referer) {
    // Пустой referrer разрешен (закладки, прямой ввод)
    if (!referer) {
        return true;
    }
    
    // Проверяем разрешенные реферреры
    return ALLOWED_REFERRERS.some(allowed => referer.toLowerCase().includes(allowed.toLowerCase()));
}

function isExcludedUrl(pathname) {
    return URL_EXCEPTIONS.some(exception => {
        if (exception.endsWith('*')) {
            return pathname.startsWith(exception.slice(0, -1));
        }
        return pathname.startsWith(exception);
    });
}

async function checkRateLimit(ip) {
    // Простая реализация rate limiting с использованием Workers KV
    // В production используйте Workers KV или Durable Objects
    
    try {
        const key = `ratelimit:${ip}`;
        const now = Date.now();
        const windowMs = RATE_LIMIT.window * 1000;
        
        // Получаем данные из KV (если доступно)
        // const data = await RATE_LIMIT_KV.get(key, 'json');
        
        // Для демо версии без KV просто разрешаем
        return { allowed: true };
        
        /*
        if (!data || now - data.timestamp > windowMs) {
            // Новое окно
            await RATE_LIMIT_KV.put(key, JSON.stringify({
                count: 1,
                timestamp: now
            }), { expirationTtl: RATE_LIMIT.window });
            return { allowed: true };
        }
        
        if (data.count >= RATE_LIMIT.requests) {
            return { allowed: false, resetAt: data.timestamp + windowMs };
        }
        
        // Увеличиваем счетчик
        await RATE_LIMIT_KV.put(key, JSON.stringify({
            count: data.count + 1,
            timestamp: data.timestamp
        }), { expirationTtl: RATE_LIMIT.window });
        
        return { allowed: true };
        */
    } catch (error) {
        // В случае ошибки разрешаем запрос
        return { allowed: true };
    }
}

function blockRequest(reason, status = 403) {
    return new Response(`
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    margin: 0;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                }
                .container {
                    text-align: center;
                    padding: 40px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    max-width: 500px;
                }
                h1 {
                    color: #e74c3c;
                    margin-bottom: 20px;
                }
                p {
                    color: #7f8c8d;
                    margin-bottom: 30px;
                }
                .icon {
                    font-size: 64px;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">🛡️</div>
                <h1>Access Denied</h1>
                <p>${reason}</p>
                <small style="color: #95a5a6;">Protected by CloudPanel Security</small>
            </div>
        </body>
        </html>
    `, {
        status: status,
        headers: {
            'Content-Type': 'text/html; charset=utf-8',
            'X-Robots-Tag': 'noindex',
            'X-Protected-By': 'CloudPanel-Security'
        }
    });
}

