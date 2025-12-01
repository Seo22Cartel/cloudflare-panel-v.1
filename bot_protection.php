<?php
/**
 * Bot and AI Crawler Protection
 * Include this file at the start of your application to block bots
 */

class BotProtection {
    
    // Search engine bots
    private static $searchBots = [
        'googlebot', 'google', 'bingbot', 'bing', 'msn', 'msnbot', 
        'slurp', 'yahoo', 'duckduckbot', 'baiduspider', 'baidu',
        'yandex', 'yandexbot', 'sogou', 'exabot', 'facebot', 
        'ia_archiver', 'teoma', 'alexa', 'surveybot', 'gigabot',
        'askjeeves', 'ask jeeves', 'nutch', 'seznambot'
    ];
    
    // AI crawlers
    private static $aiBots = [
        'gptbot', 'chatgpt', 'chatgpt-user', 'openai',
        'ccbot', 'common crawl',
        'anthropic', 'claude', 'claude-web', 'claudebot',
        'google-extended', 'google extended',
        'perplexitybot', 'perplexity',
        'bytespider', 'bytedance',
        'amazonbot', 'amazon',
        'youbot', 'you.com',
        'cohere', 'cohere-ai',
        'applebot', 'applebot-extended',
        'facebook', 'facebookbot', 'facebookexternalhit',
        'meta-externalfetcher', 'meta-externalagent',
        'oai-searchbot', 'openai-searchbot',
        'webgptbot', 'webgpt',
        'omgili', 'omgilibot',
        'diffbot',
        'img2dataset',
        'twitterbot',
        'linkedinbot',
        'whatsapp',
        'telegrambot',
        'pinterestbot'
    ];
    
    // SEO tools
    private static $seoBots = [
        'ahrefsbot', 'ahrefs',
        'semrush', 'semrushbot',
        'mj12bot', 'majestic',
        'dotbot',
        'rogerbot', 'moz',
        'dataforseobot', 'dataforseo',
        'blexbot',
        'screaming frog', 'screamingfrog',
        'siteaudit', 'siteauditbot',
        'serpstatbot', 'serpstat',
        'petalbot',
        'sistrix',
        'seokicks',
        'linkdexbot',
        'spbot',
        'tweetmemebot',
        'paperlibot',
        'neilpatelbot',
        'seoreviewtools',
        'seoscanners'
    ];
    
    // Archive bots
    private static $archiveBots = [
        'archive.org', 'archive.org_bot',
        'wayback', 'wayback machine',
        'internetarchive'
    ];
    
    // Scraping tools
    private static $scrapingTools = [
        'httrack', 'wget', 'curl/',
        'scrapy', 'python-requests', 'python-urllib', 'python/',
        'java/', 'apache-httpclient',
        'libwww', 'lwp', 'perl',
        'mechanize', 'go-http-client',
        'phantom', 'phantomjs',
        'selenium', 'webdriver',
        'headless', 'headlesschrome',
        'puppet', 'puppeteer',
        'playwright',
        'axios', 'node-fetch',
        'okhttp', 'apache-http',
        'postman', 'insomnia'
    ];
    
    // Generic bot patterns
    private static $genericPatterns = [
        'spider', 'crawl', 'bot/', 'bot;', 
        'scrape', 'harvest', 'extract', 
        'gather', 'index', 'scan',
        'fetch', 'collector', 'parser'
    ];
    
    // Known bad IPs ranges (common VPS/Datacenter providers used for scraping)
    private static $badIpRanges = [
        // Add known bad IP ranges here if needed
    ];
    
    /**
     * Check if request is from a bot
     */
    public static function isBot(): bool {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Empty user agent is suspicious
        if (empty($userAgent) || $userAgent === '-') {
            return true;
        }
        
        // Check search engine bots
        foreach (self::$searchBots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return true;
            }
        }
        
        // Check AI crawlers
        foreach (self::$aiBots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return true;
            }
        }
        
        // Check SEO tools
        foreach (self::$seoBots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return true;
            }
        }
        
        // Check archive bots
        foreach (self::$archiveBots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return true;
            }
        }
        
        // Check scraping tools
        foreach (self::$scrapingTools as $tool) {
            if (strpos($userAgent, $tool) !== false) {
                return true;
            }
        }
        
        // Check generic bot patterns
        foreach (self::$genericPatterns as $pattern) {
            if (strpos($userAgent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for suspicious request characteristics
     */
    public static function isSuspiciousRequest(): bool {
        // Too many rapid requests (simple rate limiting)
        if (self::isRateLimitExceeded()) {
            return true;
        }
        
        // Suspicious headers
        if (self::hasSuspiciousHeaders()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Simple rate limiting check
     */
    private static function isRateLimitExceeded(): bool {
        if (!session_id()) {
            return false; // Session not started, can't check
        }
        
        $now = time();
        $window = 60; // 1 minute window
        $maxRequests = 100; // Max requests per window
        
        if (!isset($_SESSION['request_count'])) {
            $_SESSION['request_count'] = 1;
            $_SESSION['request_window_start'] = $now;
            return false;
        }
        
        if ($now - $_SESSION['request_window_start'] > $window) {
            // Reset window
            $_SESSION['request_count'] = 1;
            $_SESSION['request_window_start'] = $now;
            return false;
        }
        
        $_SESSION['request_count']++;
        
        return $_SESSION['request_count'] > $maxRequests;
    }
    
    /**
     * Check for suspicious headers
     */
    private static function hasSuspiciousHeaders(): bool {
        // Check for typical headless browser headers
        $suspiciousHeaders = [
            'HTTP_X_FORWARDED_FOR' => function($v) { return substr_count($v, ',') > 3; }, // Too many proxies
            'HTTP_VIA' => function($v) { return !empty($v); }, // Proxy header
        ];
        
        foreach ($suspiciousHeaders as $header => $check) {
            if (isset($_SERVER[$header]) && $check($_SERVER[$header])) {
                return true;
            }
        }
        
        // Check referrer only for POST requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            
            // POST without referer from same site is suspicious
            if (empty($referer) || strpos($referer, $host) === false) {
                // Allow API endpoints
                $uri = $_SERVER['REQUEST_URI'] ?? '';
                if (strpos($uri, '_api.php') === false && strpos($uri, 'handle_forms.php') === false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Block the request
     */
    public static function block($reason = 'Blocked'): void {
        // Log blocked request
        error_log("Bot blocked: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | UA: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'none') . " | Reason: $reason");
        
        // Return 403 Forbidden
        http_response_code(403);
        header('Content-Type: text/plain');
        header('X-Robots-Tag: noindex, nofollow');
        
        // Disguise as generic server error
        die('Access Denied');
    }
    
    /**
     * Main protection method
     */
    public static function protect(): void {
        // Get request URI
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
        
        // Allow robots.txt
        if ($uri === '/robots.txt' || strpos($uri, 'robots.txt') !== false) {
            return; // Allow access to robots.txt
        }
        
        // Allow API endpoints (these have their own authentication)
        if (strpos($scriptName, '_api.php') !== false || strpos($uri, '_api.php') !== false) {
            return; // API endpoints - handled by their own auth
        }
        
        // IMPORTANT: Allow authenticated users full access
        // Session is already started in config.php before this is called
        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            return; // Authenticated user - allow full access
        }
        
        // Allow public pages without bot check for browsers
        $publicPages = ['login.php', 'install.php', 'password_reset_advanced.php'];
        if (in_array($scriptName, $publicPages)) {
            // Only block known aggressive bots on public pages, allow regular browsers
            if (self::isAggressiveBot()) {
                self::block('Aggressive bot detected');
            }
            return;
        }
        
        // For protected pages (unauthenticated), check if bot
        if (self::isBot()) {
            self::block('Bot detected via User-Agent');
        }
        
        // Check for suspicious request
        // Note: Rate limiting requires session, so we check after session_start
    }
    
    /**
     * Check if request is from an aggressive bot (not a regular browser)
     * More lenient check for public pages
     */
    public static function isAggressiveBot(): bool {
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        
        // Empty user agent on public pages - might be legit old browser
        if (empty($userAgent)) {
            return false;
        }
        
        // Only block very obvious bots on public pages
        $aggressiveBots = [
            'bot', 'crawl', 'spider', 'scrape', 'curl/', 'wget', 'python',
            'scrapy', 'httpclient', 'java/', 'mechanize', 'phantom',
            'selenium', 'headless', 'puppeteer', 'playwright'
        ];
        
        foreach ($aggressiveBots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check after session is started (for rate limiting)
     */
    public static function protectWithRateLimit(): void {
        if (self::isSuspiciousRequest()) {
            self::block('Suspicious request pattern');
        }
    }
    
    /**
     * Output no-index meta tags
     */
    public static function getNoIndexMeta(): string {
        return '
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate">
    <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
    <meta name="bingbot" content="noindex, nofollow">
    <meta name="slurp" content="noindex, nofollow">
    <meta name="duckduckbot" content="noindex, nofollow">
    <meta name="baiduspider" content="noindex, nofollow">
    <meta name="yandex" content="noindex, nofollow">
    <meta name="google-extended" content="noindex, nofollow">
    <meta name="gptbot" content="noindex, nofollow">
    <meta name="ccbot" content="noindex, nofollow">
    <meta name="anthropic-ai" content="noindex, nofollow">';
    }
    
    /**
     * Get HTTP headers for bot protection
     */
    public static function sendProtectionHeaders(): void {
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: no-referrer');
        }
    }
}

// Run protection immediately when this file is included
BotProtection::protect();