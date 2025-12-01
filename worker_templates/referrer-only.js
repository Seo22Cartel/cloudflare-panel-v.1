/**
 * Cloudflare Worker: Referrer Only Protection
 * Доступ только через поисковые системы и разрешенные реферреры
 */

const ALLOWED_REFERRERS = [
    'google.',
    'yandex.',
    'bing.com',
    'duckduckgo.com',
    'baidu.com'
];

const URL_EXCEPTIONS = [
    '/api/',
    '/webhook/',
    '/robots.txt',
    '/sitemap.xml'
];

addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    const url = new URL(request.url);
    const referer = request.headers.get('Referer') || '';
    
    // Проверка исключений
    if (isExcludedUrl(url.pathname)) {
        return fetch(request);
    }
    
    // Проверка referrer
    if (!referer) {
        return blockRequest('Direct access not allowed. Please use search engines.');
    }
    
    const isAllowed = ALLOWED_REFERRERS.some(allowed => 
        referer.toLowerCase().includes(allowed.toLowerCase())
    );
    
    if (!isAllowed) {
        return blockRequest('Access only allowed from search engines.');
    }
    
    return fetch(request);
}

function isExcludedUrl(pathname) {
    return URL_EXCEPTIONS.some(exception => pathname.startsWith(exception));
}

function blockRequest(reason) {
    return new Response(reason, { status: 403 });
}

