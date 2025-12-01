/**
 * Cloudflare Worker: Geo Blocker Only
 * Геоблокировка по странам
 */

const GEO_MODE = 'whitelist'; // 'whitelist' или 'blacklist'
const COUNTRIES = ['RU', 'US', 'GB', 'DE', 'FR']; // Измените на свои

addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    const country = request.cf?.country || 'XX';
    
    const isAllowed = GEO_MODE === 'whitelist' 
        ? COUNTRIES.includes(country)
        : !COUNTRIES.includes(country);
    
    if (!isAllowed) {
        return new Response('Access Denied: Geographic Restriction', { status: 403 });
    }
    
    return fetch(request);
}

