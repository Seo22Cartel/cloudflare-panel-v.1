/**
 * Cloudflare Worker: Bot Blocker Only
 * Блокировка только известных плохих ботов
 */

const BAD_BOTS = [
    'semrush', 'ahrefs', 'majestic', 'mj12bot', 'dotbot', 'rogerbot',
    'linkdex', 'blexbot', 'scrapy', 'python-requests', 'curl', 'wget',
    'masscan', 'nmap', 'nikto', 'sqlmap', 'havij', 'metasploit',
    'nessus', 'acunetix', 'grabber', 'brutus', 'hydra', 'wpscan'
];

addEventListener('fetch', event => {
    event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
    const userAgent = request.headers.get('User-Agent') || '';
    const ua = userAgent.toLowerCase();
    
    if (BAD_BOTS.some(bot => ua.includes(bot))) {
        return new Response('Bot Blocked', { status: 403 });
    }
    
    return fetch(request);
}

