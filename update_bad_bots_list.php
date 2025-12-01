<?php
/**
 * Auto Update Bad Bots List
 * Автоматическое обновление списка плохих ботов
 * 
 * Запускать через cron раз в неделю:
 * 0 3 * * 0 php /path/to/cloudpanel/update_bad_bots_list.php
 */

require_once 'config.php';

echo "=== Обновление списков безопасности ===\n\n";

// 1. Обновление списка bad bots
echo "1. Загрузка списка bad bots...\n";
$badBotsUrl = 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-user-agents.list';
$badBots = @file_get_contents($badBotsUrl);

if ($badBots) {
    file_put_contents(__DIR__ . '/cache/bad-bots.list', $badBots);
    $count = count(explode("\n", $badBots));
    echo "   ✅ Загружено: $count ботов\n";
} else {
    echo "   ❌ Ошибка загрузки списка bad bots\n";
}

// 2. Обновление списка spam referrers
echo "\n2. Загрузка списка spam referrers...\n";
$spamReferrersUrl = 'https://raw.githubusercontent.com/mitchellkrogza/nginx-ultimate-bad-bot-blocker/master/_generator_lists/bad-referrers.list';
$spamReferrers = @file_get_contents($spamReferrersUrl);

if ($spamReferrers) {
    file_put_contents(__DIR__ . '/cache/spam-referrers.list', $spamReferrers);
    $count = count(explode("\n", $spamReferrers));
    echo "   ✅ Загружено: $count реферреров\n";
} else {
    echo "   ❌ Ошибка загрузки списка spam referrers\n";
}

// 3. Обновление списка вредоносных IP
echo "\n3. Загрузка списка вредоносных IP...\n";
$badIPsUrl = 'https://raw.githubusercontent.com/mitchellkrogza/Suspicious.Snooping.Sniffing.Hacking.IP.Addresses/master/ips.list';
$badIPs = @file_get_contents($badIPsUrl);

if ($badIPs) {
    file_put_contents(__DIR__ . '/cache/bad-ips.list', $badIPs);
    $count = count(explode("\n", $badIPs));
    echo "   ✅ Загружено: $count IP адресов\n";
} else {
    echo "   ❌ Ошибка загрузки списка bad IPs\n";
}

// 4. Обновление списка вредоносных доменов
echo "\n4. Загрузка списка вредоносных доменов...\n";
$malwareDomainsUrl = 'https://raw.githubusercontent.com/mitchellkrogza/The-Big-List-of-Hacked-Malware-Web-Sites/master/hacked-domains.list';
$malwareDomains = @file_get_contents($malwareDomainsUrl);

if ($malwareDomains) {
    file_put_contents(__DIR__ . '/cache/malware-domains.list', $malwareDomains);
    $count = count(explode("\n", $malwareDomains));
    echo "   ✅ Загружено: $count доменов\n";
} else {
    echo "   ❌ Ошибка загрузки списка malware domains\n";
}

// 5. Логирование в базу
try {
    $stmt = $pdo->prepare("
        INSERT INTO logs (user_id, action, details, timestamp) 
        VALUES (1, 'Security Lists Updated', ?, datetime('now'))
    ");
    $details = json_encode([
        'bad_bots' => isset($count) ? $count : 0,
        'spam_referrers' => isset($count) ? $count : 0,
        'bad_ips' => isset($count) ? $count : 0,
        'malware_domains' => isset($count) ? $count : 0,
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    $stmt->execute([$details]);
    echo "\n✅ Логирование выполнено\n";
} catch (Exception $e) {
    echo "\n❌ Ошибка логирования: " . $e->getMessage() . "\n";
}

echo "\n=== Обновление завершено ===\n";
echo "Время: " . date('Y-m-d H:i:s') . "\n";

