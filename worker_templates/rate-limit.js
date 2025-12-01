/**
 * Cloudflare Worker: Rate Limiter
 * Ограничение количества запросов
 */

const RATE_LIMIT = {
    requests: 100,     // Максимум запросов
    window: 60         // За период (секунд)
};

// Простое in-memory хранилище (сбросится при перезапуске Worker)
const rateLimitStore = new Map();

addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    const ip = request.headers.get('CF-Connecting-IP');
    const now = Date.now();
    const key = `ratelimit:${ip}`;
    
    // Очистка старых записей
    cleanupOldRecords(now);
    
    const record = rateLimitStore.get(key);
    
    if (!record || now - record.timestamp > RATE_LIMIT.window * 1000) {
        // Новое окно
        rateLimitStore.set(key, {
            count: 1,
            timestamp: now
        });
        return fetch(request);
    }
    
    if (record.count >= RATE_LIMIT.requests) {
        return new Response('Rate Limit Exceeded', {
            status: 429,
            headers: {
                'Retry-After': RATE_LIMIT.window.toString()
            }
        });
    }
    
    // Увеличиваем счетчик
    record.count++;
    rateLimitStore.set(key, record);
    
    return fetch(request);
}

function cleanupOldRecords(now) {
    const maxAge = RATE_LIMIT.window * 1000;
    for (const [key, record] of rateLimitStore.entries()) {
        if (now - record.timestamp > maxAge) {
            rateLimitStore.delete(key);
        }
    }
}

