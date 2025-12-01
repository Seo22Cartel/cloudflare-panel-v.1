/**
 * Security Rules Manager - Frontend JavaScript
 * Включает редактор конфигурации Worker шаблонов
 */

// Текущая конфигурация Worker
let workerConfig = {
    template: null,
    badBots: ['semrush', 'ahrefs', 'mj12bot', 'dotbot', 'petalbot', 'rogerbot', 'blexbot', 'linkdex'],
    blockedIps: [],
    geoMode: 'whitelist',
    allowedCountries: ['RU', 'US', 'DE', 'FR', 'GB'],
    blockedCountries: ['CN', 'KP', 'IR'],
    allowedReferrers: ['google.', 'yandex.', 'bing.com', 'duckduckgo.com'],
    urlExceptions: ['/api/*', '/robots.txt', '/favicon.ico', '/health'],
    rateLimit: { requests: 100, window: 60, enabled: true }
};

// Расширенный список стран для геоблокировки (ISO 3166-1 alpha-2)
const countries = [
    // СНГ и Восточная Европа
    {code: 'RU', name: 'Россия', flag: '🇷🇺', region: 'CIS'},
    {code: 'UA', name: 'Украина', flag: '🇺🇦', region: 'CIS'},
    {code: 'BY', name: 'Беларусь', flag: '🇧🇾', region: 'CIS'},
    {code: 'KZ', name: 'Казахстан', flag: '🇰🇿', region: 'CIS'},
    {code: 'UZ', name: 'Узбекистан', flag: '🇺🇿', region: 'CIS'},
    {code: 'GE', name: 'Грузия', flag: '🇬🇪', region: 'CIS'},
    {code: 'AM', name: 'Армения', flag: '🇦🇲', region: 'CIS'},
    {code: 'AZ', name: 'Азербайджан', flag: '🇦🇿', region: 'CIS'},
    {code: 'MD', name: 'Молдова', flag: '🇲🇩', region: 'CIS'},
    {code: 'KG', name: 'Кыргызстан', flag: '🇰🇬', region: 'CIS'},
    {code: 'TJ', name: 'Таджикистан', flag: '🇹🇯', region: 'CIS'},
    {code: 'TM', name: 'Туркменистан', flag: '🇹🇲', region: 'CIS'},
    
    // Западная Европа
    {code: 'GB', name: 'Великобритания', flag: '🇬🇧', region: 'Europe'},
    {code: 'DE', name: 'Германия', flag: '🇩🇪', region: 'Europe'},
    {code: 'FR', name: 'Франция', flag: '🇫🇷', region: 'Europe'},
    {code: 'IT', name: 'Италия', flag: '🇮🇹', region: 'Europe'},
    {code: 'ES', name: 'Испания', flag: '🇪🇸', region: 'Europe'},
    {code: 'PT', name: 'Португалия', flag: '🇵🇹', region: 'Europe'},
    {code: 'NL', name: 'Нидерланды', flag: '🇳🇱', region: 'Europe'},
    {code: 'BE', name: 'Бельгия', flag: '🇧🇪', region: 'Europe'},
    {code: 'AT', name: 'Австрия', flag: '🇦🇹', region: 'Europe'},
    {code: 'CH', name: 'Швейцария', flag: '🇨🇭', region: 'Europe'},
    {code: 'SE', name: 'Швеция', flag: '🇸🇪', region: 'Europe'},
    {code: 'NO', name: 'Норвегия', flag: '🇳🇴', region: 'Europe'},
    {code: 'DK', name: 'Дания', flag: '🇩🇰', region: 'Europe'},
    {code: 'FI', name: 'Финляндия', flag: '🇫🇮', region: 'Europe'},
    {code: 'IE', name: 'Ирландия', flag: '🇮🇪', region: 'Europe'},
    {code: 'GR', name: 'Греция', flag: '🇬🇷', region: 'Europe'},
    
    // Восточная Европа
    {code: 'PL', name: 'Польша', flag: '🇵🇱', region: 'Europe'},
    {code: 'CZ', name: 'Чехия', flag: '🇨🇿', region: 'Europe'},
    {code: 'RO', name: 'Румыния', flag: '🇷🇴', region: 'Europe'},
    {code: 'HU', name: 'Венгрия', flag: '🇭🇺', region: 'Europe'},
    {code: 'BG', name: 'Болгария', flag: '🇧🇬', region: 'Europe'},
    {code: 'SK', name: 'Словакия', flag: '🇸🇰', region: 'Europe'},
    {code: 'HR', name: 'Хорватия', flag: '🇭🇷', region: 'Europe'},
    {code: 'RS', name: 'Сербия', flag: '🇷🇸', region: 'Europe'},
    {code: 'SI', name: 'Словения', flag: '🇸🇮', region: 'Europe'},
    {code: 'LT', name: 'Литва', flag: '🇱🇹', region: 'Europe'},
    {code: 'LV', name: 'Латвия', flag: '🇱🇻', region: 'Europe'},
    {code: 'EE', name: 'Эстония', flag: '🇪🇪', region: 'Europe'},
    
    // Северная Америка
    {code: 'US', name: 'США', flag: '🇺🇸', region: 'Americas'},
    {code: 'CA', name: 'Канада', flag: '🇨🇦', region: 'Americas'},
    {code: 'MX', name: 'Мексика', flag: '🇲🇽', region: 'Americas'},
    
    // Южная Америка
    {code: 'BR', name: 'Бразилия', flag: '🇧🇷', region: 'Americas'},
    {code: 'AR', name: 'Аргентина', flag: '🇦🇷', region: 'Americas'},
    {code: 'CO', name: 'Колумбия', flag: '🇨🇴', region: 'Americas'},
    {code: 'CL', name: 'Чили', flag: '🇨🇱', region: 'Americas'},
    {code: 'PE', name: 'Перу', flag: '🇵🇪', region: 'Americas'},
    {code: 'VE', name: 'Венесуэла', flag: '🇻🇪', region: 'Americas'},
    
    // Азия
    {code: 'CN', name: 'Китай', flag: '🇨🇳', region: 'Asia'},
    {code: 'JP', name: 'Япония', flag: '🇯🇵', region: 'Asia'},
    {code: 'KR', name: 'Южная Корея', flag: '🇰🇷', region: 'Asia'},
    {code: 'IN', name: 'Индия', flag: '🇮🇳', region: 'Asia'},
    {code: 'ID', name: 'Индонезия', flag: '🇮🇩', region: 'Asia'},
    {code: 'TH', name: 'Таиланд', flag: '🇹🇭', region: 'Asia'},
    {code: 'VN', name: 'Вьетнам', flag: '🇻🇳', region: 'Asia'},
    {code: 'PH', name: 'Филиппины', flag: '🇵🇭', region: 'Asia'},
    {code: 'MY', name: 'Малайзия', flag: '🇲🇾', region: 'Asia'},
    {code: 'SG', name: 'Сингапур', flag: '🇸🇬', region: 'Asia'},
    {code: 'HK', name: 'Гонконг', flag: '🇭🇰', region: 'Asia'},
    {code: 'TW', name: 'Тайвань', flag: '🇹🇼', region: 'Asia'},
    {code: 'PK', name: 'Пакистан', flag: '🇵🇰', region: 'Asia'},
    {code: 'BD', name: 'Бангладеш', flag: '🇧🇩', region: 'Asia'},
    
    // Ближний Восток
    {code: 'TR', name: 'Турция', flag: '🇹🇷', region: 'MiddleEast'},
    {code: 'IL', name: 'Израиль', flag: '🇮🇱', region: 'MiddleEast'},
    {code: 'SA', name: 'Саудовская Аравия', flag: '🇸🇦', region: 'MiddleEast'},
    {code: 'AE', name: 'ОАЭ', flag: '🇦🇪', region: 'MiddleEast'},
    {code: 'IR', name: 'Иран', flag: '🇮🇷', region: 'MiddleEast'},
    {code: 'IQ', name: 'Ирак', flag: '🇮🇶', region: 'MiddleEast'},
    {code: 'EG', name: 'Египет', flag: '🇪🇬', region: 'MiddleEast'},
    
    // Африка
    {code: 'ZA', name: 'ЮАР', flag: '🇿🇦', region: 'Africa'},
    {code: 'NG', name: 'Нигерия', flag: '🇳🇬', region: 'Africa'},
    {code: 'KE', name: 'Кения', flag: '🇰🇪', region: 'Africa'},
    {code: 'MA', name: 'Марокко', flag: '🇲🇦', region: 'Africa'},
    
    // Океания
    {code: 'AU', name: 'Австралия', flag: '🇦🇺', region: 'Oceania'},
    {code: 'NZ', name: 'Новая Зеландия', flag: '🇳🇿', region: 'Oceania'}
];

// Раздельные списки для whitelist и blacklist
let whitelistCountries = [];
let blacklistCountries = [];
let currentWorkerTemplate = null;

// Инициализация при загрузке страницы
$(document).ready(function() {
    initializeCountryList();
    initializeScopeSelectors();
    initializeReferrerActionSelector();
    initializeGeoModeSelector();
    
    // Автозагрузка первого шаблона Worker при открытии вкладки Workers
    $('a[data-bs-toggle="tab"], button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
        if ($(e.target).attr('id') === 'worker-manager-tab' && !currentWorkerTemplate) {
            console.log('Worker tab opened, auto-loading first template...');
            loadWorkerTemplate('advanced-protection');
        }
    });
    
    // Если вкладка Workers уже активна при загрузке - загрузить первый шаблон
    setTimeout(function() {
        if ($('#worker-manager').hasClass('show') || $('#worker-manager').hasClass('active')) {
            if (!currentWorkerTemplate) {
                console.log('Worker tab already active on load, loading template...');
                loadWorkerTemplate('advanced-protection');
            }
        }
    }, 500);
});

// Инициализация списка стран с чекбоксами
function initializeCountryList() {
    const countryList = $('#countryList');
    if (!countryList.length) return;
    
    countryList.empty();
    
    // Группируем страны по регионам
    const regions = {
        'CIS': 'СНГ',
        'Europe': 'Европа',
        'Americas': 'Америка',
        'Asia': 'Азия',
        'MiddleEast': 'Ближний Восток',
        'Africa': 'Африка',
        'Oceania': 'Океания'
    };
    
    // Создаем список с группировкой по регионам
    Object.keys(regions).forEach(regionCode => {
        const regionCountries = countries.filter(c => c.region === regionCode);
        if (regionCountries.length === 0) return;
        
        countryList.append(`
            <div class="region-header bg-light px-2 py-1 mt-2 mb-1 rounded small fw-bold text-secondary">
                ${regions[regionCode]} (${regionCountries.length})
            </div>
        `);
        
        regionCountries.forEach(country => {
            countryList.append(`
                <div class="form-check country-item" data-code="${country.code}" data-name="${country.name.toLowerCase()}">
                    <input class="form-check-input country-checkbox" type="checkbox" value="${country.code}" id="country-${country.code}">
                    <label class="form-check-label w-100" for="country-${country.code}">
                        ${country.flag} ${country.name}
                    </label>
                </div>
            `);
        });
    });
    
    // Поиск стран
    $('#countrySearch').on('input', function() {
        const search = $(this).val().toLowerCase();
        $('.country-item').each(function() {
            const name = $(this).data('name');
            const code = $(this).data('code').toLowerCase();
            $(this).toggle(name.includes(search) || code.includes(search));
        });
        // Скрываем пустые заголовки регионов
        $('.region-header').each(function() {
            const hasVisibleCountries = $(this).nextUntil('.region-header').filter('.country-item:visible').length > 0;
            $(this).toggle(hasVisibleCountries);
        });
    });
}

// Инициализация переключателя режима geo
function initializeGeoModeSelector() {
    // Обновляем предпросмотр при изменении режима
    $('input[name="geoApplyMode"]').on('change', function() {
        updateGeoRulesPreview();
    });
}

// Добавить выбранные страны в Whitelist
function addSelectedToWhitelist() {
    const selectedCodes = [];
    $('.country-checkbox:checked').each(function() {
        selectedCodes.push($(this).val());
    });
    
    if (selectedCodes.length === 0) {
        showWarning('Выберите страны для добавления');
        return;
    }
    
    selectedCodes.forEach(code => {
        // Удаляем из blacklist если там есть
        blacklistCountries = blacklistCountries.filter(c => c.code !== code);
        // Добавляем в whitelist если ещё нет
        if (!whitelistCountries.find(c => c.code === code)) {
            const country = countries.find(c => c.code === code);
            if (country) {
                whitelistCountries.push(country);
            }
        }
    });
    
    // Снимаем выделение
    $('.country-checkbox:checked').prop('checked', false);
    
    updateWhitelistDisplay();
    updateBlacklistDisplay();
    updateGeoRulesPreview();
    
    showSuccess(`Добавлено ${selectedCodes.length} стран в Whitelist`);
}

// Добавить выбранные страны в Blacklist
function addSelectedToBlacklist() {
    const selectedCodes = [];
    $('.country-checkbox:checked').each(function() {
        selectedCodes.push($(this).val());
    });
    
    if (selectedCodes.length === 0) {
        showWarning('Выберите страны для добавления');
        return;
    }
    
    selectedCodes.forEach(code => {
        // Удаляем из whitelist если там есть
        whitelistCountries = whitelistCountries.filter(c => c.code !== code);
        // Добавляем в blacklist если ещё нет
        if (!blacklistCountries.find(c => c.code === code)) {
            const country = countries.find(c => c.code === code);
            if (country) {
                blacklistCountries.push(country);
            }
        }
    });
    
    // Снимаем выделение
    $('.country-checkbox:checked').prop('checked', false);
    
    updateWhitelistDisplay();
    updateBlacklistDisplay();
    updateGeoRulesPreview();
    
    showSuccess(`Добавлено ${selectedCodes.length} стран в Blacklist`);
}

// Удалить страну из Whitelist
function removeFromWhitelist(code) {
    whitelistCountries = whitelistCountries.filter(c => c.code !== code);
    updateWhitelistDisplay();
    updateGeoRulesPreview();
}

// Удалить страну из Blacklist
function removeFromBlacklist(code) {
    blacklistCountries = blacklistCountries.filter(c => c.code !== code);
    updateBlacklistDisplay();
    updateGeoRulesPreview();
}

// Обновить отображение Whitelist
function updateWhitelistDisplay() {
    $('#whitelistCount').text(whitelistCountries.length);
    
    const container = $('#whitelistCountries');
    if (whitelistCountries.length === 0) {
        container.html('<p class="text-muted text-center small mb-0 empty-msg">Перетащите страны сюда или нажмите кнопку ➕</p>');
    } else {
        container.html(whitelistCountries.map(c => `
            <div class="d-flex justify-content-between align-items-center p-1 border-bottom country-badge" data-code="${c.code}">
                <span>${c.flag} ${c.name}</span>
                <button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromWhitelist('${c.code}')" title="Удалить">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join(''));
    }
}

// Обновить отображение Blacklist
function updateBlacklistDisplay() {
    $('#blacklistCount').text(blacklistCountries.length);
    
    const container = $('#blacklistCountries');
    if (blacklistCountries.length === 0) {
        container.html('<p class="text-muted text-center small mb-0 empty-msg">Перетащите страны сюда или нажмите кнопку ➕</p>');
    } else {
        container.html(blacklistCountries.map(c => `
            <div class="d-flex justify-content-between align-items-center p-1 border-bottom country-badge" data-code="${c.code}">
                <span>${c.flag} ${c.name}</span>
                <button class="btn btn-sm btn-link text-danger p-0" onclick="removeFromBlacklist('${c.code}')" title="Удалить">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join(''));
    }
}

// Обновить предпросмотр правил Cloudflare
function updateGeoRulesPreview() {
    const previewDiv = $('#geoRulesPreview');
    if (!previewDiv.length) return;
    
    const mode = $('input[name="geoApplyMode"]:checked').val();
    let preview = '';
    
    if (mode === 'whitelist' || mode === 'both') {
        if (whitelistCountries.length > 0) {
            const codes = whitelistCountries.map(c => `"${c.code}"`).join(' ');
            preview += `<div class="mb-2"><span class="text-success">// Whitelist Rule (разрешить ТОЛЬКО из этих стран)</span></div>`;
            preview += `<div class="mb-2">(not ip.geoip.country in {${codes}})</div>`;
            preview += `<div class="mb-3 text-warning">→ Action: BLOCK</div>`;
        } else {
            preview += `<div class="text-muted mb-2">// Whitelist пуст</div>`;
        }
    }
    
    if (mode === 'blacklist' || mode === 'both') {
        if (blacklistCountries.length > 0) {
            const codes = blacklistCountries.map(c => `"${c.code}"`).join(' ');
            preview += `<div class="mb-2"><span class="text-danger">// Blacklist Rule (заблокировать из этих стран)</span></div>`;
            preview += `<div class="mb-2">(ip.geoip.country in {${codes}})</div>`;
            preview += `<div class="text-warning">→ Action: BLOCK</div>`;
        } else {
            preview += `<div class="text-muted">// Blacklist пуст</div>`;
        }
    }
    
    if (preview === '') {
        preview = '<div class="text-muted">// Выберите страны и режим для просмотра правил</div>';
    }
    
    previewDiv.html(preview);
}

// Очистить все списки
function clearGeoLists() {
    whitelistCountries = [];
    blacklistCountries = [];
    updateWhitelistDisplay();
    updateBlacklistDisplay();
    updateGeoRulesPreview();
}

// Инициализация селекторов области применения
function initializeScopeSelectors() {
    $('[id$="Scope"]').on('change', function() {
        const scope = $(this).val();
        const prefix = $(this).attr('id').replace('Scope', '');
        
        $(`#${prefix}Group`).toggle(scope === 'group');
        $(`#${prefix}Domains`).toggle(scope === 'selected');
    });
}

// Инициализация селектора действия реферрера
function initializeReferrerActionSelector() {
    $('#referrerAction').on('change', function() {
        $('#customPageDiv').toggle($(this).val() === 'custom');
    });
}

// Применить блокировку ботов
function applyBotBlocker() {
    const rules = {
        blockAllBots: $('#blockAllBots').is(':checked'),
        blockSpamReferrers: $('#blockSpamReferrers').is(':checked'),
        blockVulnScanners: $('#blockVulnScanners').is(':checked'),
        blockMalware: $('#blockMalware').is(':checked')
    };
    
    const scope = getScope('botBlocker');
    
    if (!confirm(`Применить блокировку ботов к ${scope.count} доменам?`)) {
        return;
    }
    
    showLoading('Применение правил блокировки ботов...');
    
    $.post('security_rules_api_minimal.php', {
        action: 'apply_bot_blocker',
        rules: rules,
        scope: scope
    })
    .done(function(response) {
        hideLoading();
        if (response.success) {
            showSuccess(`Правила применены к ${response.applied} доменам`);
            setTimeout(() => location.reload(), 2000);
        } else {
            showError(response.error || 'Ошибка применения правил');
        }
    })
    .fail(function() {
        hideLoading();
        showError('Ошибка соединения с сервером');
    });
}

// Применить блокировку IP
function applyIPBlocker() {
    const ips = $('#ipBlockList').val().split('\n').filter(ip => ip.trim());
    const importKnown = $('#importKnownBadIps').is(':checked');
    const scope = getScope('ipBlocker');
    
    if (ips.length === 0 && !importKnown) {
        showError('Укажите IP адреса для блокировки');
        return;
    }
    
    if (!confirm(`Заблокировать ${ips.length} IP адресов для ${scope.count} доменов?`)) {
        return;
    }
    
    showLoading('Применение блокировки IP...');
    
    $.post('security_rules_api_minimal.php', {
        action: 'apply_ip_blocker',
        ips: ips,
        importKnown: importKnown,
        scope: scope
    })
    .done(function(response) {
        hideLoading();
        if (response.success) {
            showSuccess(`IP блокировка применена к ${response.applied} доменам`);
            setTimeout(() => location.reload(), 2000);
        } else {
            showError(response.error || 'Ошибка применения блокировки');
        }
    })
    .fail(function() {
        hideLoading();
        showError('Ошибка соединения с сервером');
    });
}

// Применить геоблокировку с раздельными списками whitelist/blacklist
function applyGeoBlocker() {
    const mode = $('input[name="geoApplyMode"]:checked').val();
    const scope = getScope('geoBlocker');
    
    // Проверяем что есть страны для применения
    if (mode === 'whitelist' && whitelistCountries.length === 0) {
        showError('Добавьте страны в Whitelist');
        return;
    }
    if (mode === 'blacklist' && blacklistCountries.length === 0) {
        showError('Добавьте страны в Blacklist');
        return;
    }
    if (mode === 'both' && whitelistCountries.length === 0 && blacklistCountries.length === 0) {
        showError('Добавьте страны хотя бы в один список');
        return;
    }
    
    // Формируем текст подтверждения
    let confirmText = `Применить гео-правила к ${scope.count} доменам?\n\n`;
    if ((mode === 'whitelist' || mode === 'both') && whitelistCountries.length > 0) {
        confirmText += `✅ Whitelist (${whitelistCountries.length} стран): разрешить ТОЛЬКО из них\n`;
    }
    if ((mode === 'blacklist' || mode === 'both') && blacklistCountries.length > 0) {
        confirmText += `🚫 Blacklist (${blacklistCountries.length} стран): заблокировать из них\n`;
    }
    
    if (!confirm(confirmText)) {
        return;
    }
    
    showLoading('Применение геоблокировки...');
    
    // Подготавливаем данные
    const whitelistCodes = whitelistCountries.map(c => c.code);
    const blacklistCodes = blacklistCountries.map(c => c.code);
    
    $.post('security_rules_api_minimal.php', {
        action: 'apply_geo_blocker',
        mode: mode,
        whitelist: whitelistCodes,
        blacklist: blacklistCodes,
        scope: scope
    })
    .done(function(response) {
        hideLoading();
        if (response.success) {
            let message = `Гео-правила применены к ${response.applied} доменам`;
            if (response.rulesCreated) {
                message += ` (создано ${response.rulesCreated} правил)`;
            }
            showSuccess(message);
            setTimeout(() => location.reload(), 2000);
        } else {
            showError(response.error || 'Ошибка применения геоблокировки');
        }
    })
    .fail(function() {
        hideLoading();
        showError('Ошибка соединения с сервером');
    });
}

// Применить защиту "только реферреры"
function applyReferrerOnly() {
    const allowedReferrers = {
        google: $('#allowGoogle').is(':checked'),
        yandex: $('#allowYandex').is(':checked'),
        bing: $('#allowBing').is(':checked'),
        duckduckgo: $('#allowDuckDuckGo').is(':checked'),
        baidu: $('#allowBaidu').is(':checked'),
        custom: $('#customReferrers').val().split('\n').filter(r => r.trim()),
        allowEmpty: $('#allowEmpty').is(':checked')
    };
    
    const action = $('#referrerAction').val();
    const customPageUrl = $('#customPageUrl').val();
    const exceptions = $('#referrerExceptions').val().split('\n').filter(e => e.trim());
    const scope = getScope('referrer');
    
    if (!allowedReferrers.google && !allowedReferrers.yandex && !allowedReferrers.bing && 
        !allowedReferrers.duckduckgo && !allowedReferrers.baidu && 
        allowedReferrers.custom.length === 0 && !allowedReferrers.allowEmpty) {
        showError('Выберите хотя бы один разрешенный источник');
        return;
    }
    
    if (!confirm(`Применить защиту "только реферреры" к ${scope.count} доменам?\n\nВНИМАНИЕ: Это заблокирует прямой доступ к сайтам!`)) {
        return;
    }
    
    showLoading('Применение защиты...');
    
    $.post('security_rules_api_minimal.php', {
        action: 'apply_referrer_only',
        allowedReferrers: allowedReferrers,
        action: action,
        customPageUrl: customPageUrl,
        exceptions: exceptions,
        scope: scope
    })
    .done(function(response) {
        hideLoading();
        if (response.success) {
            showSuccess(`Защита применена к ${response.applied} доменам`);
            setTimeout(() => location.reload(), 2000);
        } else {
            showError(response.error || 'Ошибка применения защиты');
        }
    })
    .fail(function() {
        hideLoading();
        showError('Ошибка соединения с сервером');
    });
}

// Загрузить шаблон Worker
function loadWorkerTemplate(template) {
    if (!template) {
        showError('Не указан шаблон');
        return;
    }
    
    currentWorkerTemplate = template;
    
    // Визуально выделяем выбранный шаблон
    $('#workerTemplateList .list-group-item').removeClass('active');
    $('#workerTemplateList .list-group-item').each(function() {
        const onclick = $(this).attr('onclick') || '';
        if (onclick.includes(template)) {
            $(this).addClass('active');
        }
    });
    
    // Показываем индикатор загрузки в preview
    $('#workerPreview').html('<span class="text-warning">// Загрузка шаблона "' + template + '"...</span>');
    
    $.ajax({
        url: 'security_rules_api_minimal.php',
        type: 'GET',
        data: {
            action: 'get_worker_template',
            template: template
        },
        dataType: 'json',
        timeout: 10000 // 10 секунд таймаут
    })
    .done(function(response) {
        console.log('Template response:', response);
        if (response && response.success) {
            // Отображаем код шаблона
            const code = escapeHtml(response.code);
            $('#workerPreview').html(code);
            showInfo('Шаблон "' + template + '" загружен');
        } else {
            const errorMsg = response?.error || 'Неизвестная ошибка';
            showError('Ошибка: ' + errorMsg);
            $('#workerPreview').html('// Ошибка загрузки: ' + escapeHtml(errorMsg));
        }
    })
    .fail(function(xhr, status, error) {
        console.error('AJAX Error:', {status, error, response: xhr.responseText, statusCode: xhr.status});
        
        let errorMsg = 'Ошибка соединения';
        if (xhr.status === 401) {
            errorMsg = 'Требуется авторизация';
        } else if (xhr.status === 404) {
            errorMsg = 'API не найден';
        } else if (xhr.status === 500) {
            errorMsg = 'Ошибка сервера';
        } else if (status === 'timeout') {
            errorMsg = 'Таймаут запроса';
        } else if (status === 'parsererror') {
            errorMsg = 'Ошибка парсинга JSON: ' + xhr.responseText.substring(0, 100);
        }
        
        showError(errorMsg);
        $('#workerPreview').html('// ' + escapeHtml(errorMsg) + '\n// Status: ' + xhr.status);
    });
}

// Показать редактор кастомного Worker
function showCustomWorker() {
    // TODO: Реализовать модальное окно с редактором кода
    alert('Функция в разработке');
}

// Развернуть Worker
function deployWorker() {
    if (!currentWorkerTemplate) {
        showError('Выберите шаблон Worker');
        return;
    }
    
    const scope = getScope('worker');
    const route = $('#workerRoute').val().trim();
    
    if (!route) {
        showError('Укажите route pattern');
        return;
    }
    
    if (!confirm(`Развернуть Worker на ${scope.count} доменах?`)) {
        return;
    }
    
    showLoading('Развертывание Worker...');
    
    $.post('security_rules_api_minimal.php', {
        action: 'deploy_worker',
        template: currentWorkerTemplate,
        route: route,
        scope: scope
    })
    .done(function(response) {
        hideLoading();
        if (response.success) {
            showSuccess(`Worker развернут на ${response.applied} доменах`);
            setTimeout(() => location.reload(), 2000);
        } else {
            showError(response.error || 'Ошибка развертывания Worker');
        }
    })
    .fail(function() {
        hideLoading();
        showError('Ошибка соединения с сервером');
    });
}

// Получить область применения
function getScope(prefix) {
    const scopeValue = $(`#${prefix}Scope`).val();
    let result = {
        type: scopeValue,
        count: 0,
        groupId: null,
        domainIds: []
    };
    
    if (scopeValue === 'all') {
        result.count = $('.domain-checkbox').length;
    } else if (scopeValue === 'group') {
        result.groupId = $(`#${prefix}Group`).val();
        result.count = $(`.domain-checkbox[data-group="${result.groupId}"]`).length || 0;
    } else if (scopeValue === 'selected') {
        result.domainIds = $('.domain-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
        result.count = result.domainIds.length;
    }
    
    return result;
}

// Вспомогательные функции - интеграция с footer.php utilities
function showLoading(message) {
    // Используем функцию из footer.php если доступна
    if (typeof window.showLoading === 'function') {
        window.showLoading(message);
    } else {
        // Fallback: создаем overlay
        let overlay = document.getElementById('loadingOverlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.className = 'loading-overlay';
            overlay.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Загрузка...</span>
                    </div>
                    <p class="mt-3 loading-message">${message || 'Загрузка...'}</p>
                </div>
            `;
            document.body.appendChild(overlay);
        } else {
            overlay.querySelector('.loading-message').textContent = message || 'Загрузка...';
        }
        overlay.style.display = 'flex';
    }
}

function hideLoading() {
    // Используем функцию из footer.php если доступна
    if (typeof window.hideLoading === 'function') {
        window.hideLoading();
    } else {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = 'none';
        }
    }
}

function showSuccess(message) {
    // Используем функцию showToast из footer.php если доступна
    if (typeof showToast === 'function') {
        showToast(message, 'success');
    } else {
        // Fallback
        console.log('✅ ' + message);
        alert('✅ ' + message);
    }
}

function showError(message) {
    // Используем функцию showToast из footer.php если доступна
    if (typeof showToast === 'function') {
        showToast(message, 'danger');
    } else {
        // Fallback
        console.error('❌ ' + message);
        alert('❌ ' + message);
    }
}

function showWarning(message) {
    // Используем функцию showToast из footer.php если доступна
    if (typeof showToast === 'function') {
        showToast(message, 'warning');
    } else {
        console.warn('⚠️ ' + message);
        alert('⚠️ ' + message);
    }
}

function showInfo(message) {
    // Используем функцию showToast из footer.php если доступна
    if (typeof showToast === 'function') {
        showToast(message, 'info');
    } else {
        console.info('ℹ️ ' + message);
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Функция для загрузки шаблонов Worker
function loadWorkerTemplates() {
    $.get('workers_api.php', {
        action: 'list_templates'
    }, function(response) {
        if (response.success && response.templates) {
            const select = $('#workerTemplateSelect');
            select.empty().append('<option value="">-- Выберите шаблон --</option>');
            
            response.templates.forEach(template => {
                select.append(`<option value="${template.id}">${escapeHtml(template.name)}</option>`);
            });
        }
    }, 'json');
}

// Загрузить при инициализации
$(document).ready(function() {
    // Загружаем шаблоны Worker если на странице security_rules_manager
    if ($('#workerTemplateSelect').length) {
        loadWorkerTemplates();
    }
});

// =====================================================
// РАСШИРЕННЫЙ РЕДАКТОР WORKER ШАБЛОНОВ
// =====================================================

// Загрузить шаблон Worker с поддержкой конфигурации
function loadWorkerTemplateWithConfig(template) {
    if (!template) {
        showError('Не указан шаблон');
        return;
    }
    
    currentWorkerTemplate = template;
    workerConfig.template = template;
    
    // Визуально выделяем выбранный шаблон в списке
    $('#workerTemplateList .list-group-item').removeClass('active');
    $('#workerTemplateList .list-group-item').each(function() {
        const onclick = $(this).attr('onclick') || '';
        if (onclick.includes(template)) {
            $(this).addClass('active');
        }
    });
    
    // Показываем панель конфигурации для выбранного шаблона
    generateConfigPanel(template);
    
    // Показываем индикатор загрузки в preview
    $('#workerPreview').html('<span class="text-warning">// Загрузка шаблона "' + template + '"...</span>');
    
    $.ajax({
        url: 'security_rules_api_minimal.php',
        type: 'GET',
        data: {
            action: 'get_worker_template',
            template: template
        },
        dataType: 'json',
        timeout: 10000
    })
    .done(function(response) {
        if (response && response.success) {
            // Сохраняем оригинальный код
            workerConfig.originalCode = response.code;
            // Обновляем preview с подстановкой конфигурации
            updateWorkerPreview();
            showInfo('Шаблон "' + template + '" загружен. Настройте параметры.');
        } else {
            const errorMsg = response?.error || 'Неизвестная ошибка';
            showError('Ошибка: ' + errorMsg);
            $('#workerPreview').html('// Ошибка загрузки: ' + escapeHtml(errorMsg));
        }
    })
    .fail(function(xhr, status, error) {
        console.error('AJAX Error:', {status, error, response: xhr.responseText});
        showError('Ошибка соединения с сервером');
        $('#workerPreview').html('// Ошибка соединения');
    });
}

// Генерация панели конфигурации в зависимости от шаблона
function generateConfigPanel(template) {
    const panel = $('#workerConfigContent');
    if (!panel.length) {
        console.error('Element #workerConfigContent not found');
        return;
    }
    
    // Обновляем заголовок панели
    const templateNames = {
        'advanced-protection': 'Advanced Protection',
        'bot-only': 'Bot Blocker',
        'geo-only': 'Geo Blocker',
        'rate-limit': 'Rate Limiting',
        'referrer-only': 'Referrer Only'
    };
    $('#configPanelTitle').text('Настройка: ' + (templateNames[template] || template));
    
    let html = '';
    
    // Общие настройки для всех шаблонов
    html += `
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-cog"></i> Общие настройки</h6>
            <div class="mb-2">
                <label class="form-label small">URL исключения (по одному на строку)</label>
                <textarea class="form-control form-control-sm" id="configUrlExceptions" rows="2"
                    placeholder="/api/*&#10;/robots.txt&#10;/health">${workerConfig.urlExceptions.join('\n')}</textarea>
            </div>
        </div>
    `;
    
    // Настройки в зависимости от шаблона
    switch(template) {
        case 'advanced-protection':
            html += generateAdvancedProtectionConfig();
            break;
        case 'bot-only':
            html += generateBotOnlyConfig();
            break;
        case 'geo-only':
            html += generateGeoOnlyConfig();
            break;
        case 'rate-limit':
            html += generateRateLimitConfig();
            break;
        case 'referrer-only':
            html += generateReferrerOnlyConfig();
            break;
        default:
            html += '<p class="text-muted">Выберите шаблон для настройки</p>';
    }
    
    // Кнопка обновления preview
    html += `
        <div class="mt-3">
            <button class="btn btn-sm btn-outline-primary w-100" onclick="updateWorkerPreview()">
                <i class="fas fa-sync-alt"></i> Обновить предпросмотр
            </button>
        </div>
    `;
    
    panel.html(html);
    
    // Добавляем обработчики изменений для автообновления preview
    panel.find('input, textarea, select').on('change input', debounce(function() {
        updateConfigFromForm();
        updateWorkerPreview();
    }, 500));
}

// Конфигурация для Advanced Protection
function generateAdvancedProtectionConfig() {
    return `
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-robot"></i> Блокировка ботов</h6>
            <div class="mb-2">
                <label class="form-label small">Плохие боты (через запятую или по строкам)</label>
                <textarea class="form-control form-control-sm" id="configBadBots" rows="3"
                    placeholder="semrush, ahrefs, mj12bot...">${workerConfig.badBots.join(', ')}</textarea>
            </div>
        </div>
        
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-map-marker-alt"></i> Геоблокировка</h6>
            <div class="mb-2">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="configGeoMode" id="geoModeWhitelist" value="whitelist" ${workerConfig.geoMode === 'whitelist' ? 'checked' : ''}>
                    <label class="form-check-label" for="geoModeWhitelist">Whitelist</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="configGeoMode" id="geoModeBlacklist" value="blacklist" ${workerConfig.geoMode === 'blacklist' ? 'checked' : ''}>
                    <label class="form-check-label" for="geoModeBlacklist">Blacklist</label>
                </div>
            </div>
            <div class="mb-2" id="whitelistCountriesDiv" style="${workerConfig.geoMode === 'whitelist' ? '' : 'display:none'}">
                <label class="form-label small">Разрешенные страны (ISO коды)</label>
                <input type="text" class="form-control form-control-sm" id="configAllowedCountries"
                    value="${workerConfig.allowedCountries.join(', ')}" placeholder="RU, US, DE, FR">
            </div>
            <div class="mb-2" id="blacklistCountriesDiv" style="${workerConfig.geoMode === 'blacklist' ? '' : 'display:none'}">
                <label class="form-label small">Заблокированные страны (ISO коды)</label>
                <input type="text" class="form-control form-control-sm" id="configBlockedCountries"
                    value="${workerConfig.blockedCountries.join(', ')}" placeholder="CN, KP, IR">
            </div>
        </div>
        
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-tachometer-alt"></i> Rate Limiting</h6>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="configRateLimitEnabled" ${workerConfig.rateLimit.enabled ? 'checked' : ''}>
                <label class="form-check-label" for="configRateLimitEnabled">Включить ограничение запросов</label>
            </div>
            <div class="row">
                <div class="col-6">
                    <label class="form-label small">Запросов</label>
                    <input type="number" class="form-control form-control-sm" id="configRateLimitRequests"
                        value="${workerConfig.rateLimit.requests}" min="1" max="1000">
                </div>
                <div class="col-6">
                    <label class="form-label small">За секунд</label>
                    <input type="number" class="form-control form-control-sm" id="configRateLimitWindow"
                        value="${workerConfig.rateLimit.window}" min="1" max="3600">
                </div>
            </div>
        </div>
        
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-ban"></i> IP блокировка</h6>
            <div class="mb-2">
                <label class="form-label small">Заблокированные IP (по одному на строку)</label>
                <textarea class="form-control form-control-sm" id="configBlockedIps" rows="2"
                    placeholder="192.168.1.1&#10;10.0.0.0/8">${workerConfig.blockedIps.join('\n')}</textarea>
            </div>
        </div>
    `;
}

// Конфигурация для Bot Only
function generateBotOnlyConfig() {
    return `
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-robot"></i> Список ботов для блокировки</h6>
            <div class="mb-2">
                <label class="form-label small">Боты (через запятую)</label>
                <textarea class="form-control form-control-sm" id="configBadBots" rows="4"
                    placeholder="semrush, ahrefs, mj12bot, dotbot...">${workerConfig.badBots.join(', ')}</textarea>
            </div>
            <div class="mb-2">
                <button class="btn btn-sm btn-outline-secondary me-1" onclick="addBotPreset('seo')">+ SEO боты</button>
                <button class="btn btn-sm btn-outline-secondary me-1" onclick="addBotPreset('scrapers')">+ Парсеры</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="addBotPreset('all')">+ Все известные</button>
            </div>
        </div>
    `;
}

// Конфигурация для Geo Only
function generateGeoOnlyConfig() {
    return `
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-globe"></i> Режим геоблокировки</h6>
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="configGeoMode" id="geoModeWhitelist" value="whitelist" ${workerConfig.geoMode === 'whitelist' ? 'checked' : ''}>
                    <label class="form-check-label" for="geoModeWhitelist">
                        <strong>Whitelist</strong> - разрешить ТОЛЬКО из указанных стран
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="configGeoMode" id="geoModeBlacklist" value="blacklist" ${workerConfig.geoMode === 'blacklist' ? 'checked' : ''}>
                    <label class="form-check-label" for="geoModeBlacklist">
                        <strong>Blacklist</strong> - заблокировать указанные страны
                    </label>
                </div>
            </div>
        </div>
        
        <div class="config-section mb-3" id="whitelistSection" style="${workerConfig.geoMode === 'whitelist' ? '' : 'display:none'}">
            <h6 class="border-bottom pb-2 text-success"><i class="fas fa-check-circle"></i> Разрешенные страны</h6>
            <input type="text" class="form-control form-control-sm mb-2" id="configAllowedCountries"
                value="${workerConfig.allowedCountries.join(', ')}" placeholder="RU, US, DE, FR, GB">
            <div class="btn-group btn-group-sm flex-wrap">
                <button class="btn btn-outline-success" onclick="setGeoPreset('whitelist', ['RU'])">🇷🇺 Только РФ</button>
                <button class="btn btn-outline-success" onclick="setGeoPreset('whitelist', ['RU','BY','KZ'])">СНГ</button>
                <button class="btn btn-outline-success" onclick="setGeoPreset('whitelist', ['RU','US','DE','FR','GB'])">Топ-5</button>
            </div>
        </div>
        
        <div class="config-section mb-3" id="blacklistSection" style="${workerConfig.geoMode === 'blacklist' ? '' : 'display:none'}">
            <h6 class="border-bottom pb-2 text-danger"><i class="fas fa-ban"></i> Заблокированные страны</h6>
            <input type="text" class="form-control form-control-sm mb-2" id="configBlockedCountries"
                value="${workerConfig.blockedCountries.join(', ')}" placeholder="CN, KP, IR">
            <div class="btn-group btn-group-sm flex-wrap">
                <button class="btn btn-outline-danger" onclick="setGeoPreset('blacklist', ['CN'])">🇨🇳 Китай</button>
                <button class="btn btn-outline-danger" onclick="setGeoPreset('blacklist', ['CN','KP','IR'])">Санкционные</button>
                <button class="btn btn-outline-danger" onclick="setGeoPreset('blacklist', ['CN','IN','BD','PK'])">Азия (спам)</button>
            </div>
        </div>
    `;
}

// Конфигурация для Rate Limit
function generateRateLimitConfig() {
    return `
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-tachometer-alt"></i> Настройки Rate Limiting</h6>
            <div class="row mb-3">
                <div class="col-6">
                    <label class="form-label">Макс. запросов</label>
                    <input type="number" class="form-control" id="configRateLimitRequests"
                        value="${workerConfig.rateLimit.requests}" min="1" max="10000">
                </div>
                <div class="col-6">
                    <label class="form-label">За секунд</label>
                    <input type="number" class="form-control" id="configRateLimitWindow"
                        value="${workerConfig.rateLimit.window}" min="1" max="3600">
                </div>
            </div>
            <div class="btn-group btn-group-sm w-100 mb-2">
                <button class="btn btn-outline-primary" onclick="setRateLimitPreset(60, 60)">60/мин (мягкий)</button>
                <button class="btn btn-outline-warning" onclick="setRateLimitPreset(30, 60)">30/мин (средний)</button>
                <button class="btn btn-outline-danger" onclick="setRateLimitPreset(10, 60)">10/мин (строгий)</button>
            </div>
        </div>
        
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-reply"></i> Действие при превышении</h6>
            <select class="form-select form-select-sm" id="configRateLimitAction">
                <option value="block" selected>Заблокировать (403)</option>
                <option value="challenge">Challenge (капча)</option>
                <option value="slow">Замедлить ответ</option>
            </select>
        </div>
    `;
}

// Конфигурация для Referrer Only
function generateReferrerOnlyConfig() {
    return `
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-link"></i> Разрешенные реферреры</h6>
            <div class="mb-2">
                <label class="form-label small">Паттерны доменов (по одному на строку)</label>
                <textarea class="form-control form-control-sm" id="configAllowedReferrers" rows="4"
                    placeholder="google.&#10;yandex.&#10;bing.com">${workerConfig.allowedReferrers.join('\n')}</textarea>
            </div>
            <div class="btn-group btn-group-sm flex-wrap mb-2">
                <button class="btn btn-outline-primary" onclick="addReferrerPreset('search')">+ Поисковики</button>
                <button class="btn btn-outline-primary" onclick="addReferrerPreset('social')">+ Соцсети</button>
                <button class="btn btn-outline-primary" onclick="addReferrerPreset('all')">+ Все популярные</button>
            </div>
        </div>
        
        <div class="config-section mb-3">
            <h6 class="border-bottom pb-2"><i class="fas fa-question-circle"></i> Пустой реферрер</h6>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="configAllowEmptyReferrer" ${workerConfig.allowEmptyReferrer ? 'checked' : ''}>
                <label class="form-check-label" for="configAllowEmptyReferrer">
                    Разрешить прямой доступ (без реферрера)
                </label>
            </div>
        </div>
    `;
}

// Обновить конфигурацию из формы
function updateConfigFromForm() {
    // URL исключения
    const exceptions = $('#configUrlExceptions').val();
    if (exceptions !== undefined) {
        workerConfig.urlExceptions = exceptions.split('\n').map(s => s.trim()).filter(s => s);
    }
    
    // Боты
    const bots = $('#configBadBots').val();
    if (bots !== undefined) {
        workerConfig.badBots = bots.split(/[,\n]/).map(s => s.trim().toLowerCase()).filter(s => s);
    }
    
    // Гео режим
    const geoMode = $('input[name="configGeoMode"]:checked').val();
    if (geoMode) {
        workerConfig.geoMode = geoMode;
        // Показать/скрыть соответствующие секции
        $('#whitelistCountriesDiv, #whitelistSection').toggle(geoMode === 'whitelist');
        $('#blacklistCountriesDiv, #blacklistSection').toggle(geoMode === 'blacklist');
    }
    
    // Страны
    const allowed = $('#configAllowedCountries').val();
    if (allowed !== undefined) {
        workerConfig.allowedCountries = allowed.split(/[,\s]+/).map(s => s.trim().toUpperCase()).filter(s => s && s.length === 2);
    }
    
    const blocked = $('#configBlockedCountries').val();
    if (blocked !== undefined) {
        workerConfig.blockedCountries = blocked.split(/[,\s]+/).map(s => s.trim().toUpperCase()).filter(s => s && s.length === 2);
    }
    
    // Rate limit
    const rateLimitEnabled = $('#configRateLimitEnabled').is(':checked');
    const rateLimitRequests = parseInt($('#configRateLimitRequests').val()) || 100;
    const rateLimitWindow = parseInt($('#configRateLimitWindow').val()) || 60;
    workerConfig.rateLimit = {
        enabled: rateLimitEnabled !== undefined ? rateLimitEnabled : workerConfig.rateLimit.enabled,
        requests: rateLimitRequests,
        window: rateLimitWindow
    };
    
    // IP блокировка
    const blockedIps = $('#configBlockedIps').val();
    if (blockedIps !== undefined) {
        workerConfig.blockedIps = blockedIps.split('\n').map(s => s.trim()).filter(s => s);
    }
    
    // Реферреры
    const referrers = $('#configAllowedReferrers').val();
    if (referrers !== undefined) {
        workerConfig.allowedReferrers = referrers.split('\n').map(s => s.trim()).filter(s => s);
    }
    
    workerConfig.allowEmptyReferrer = $('#configAllowEmptyReferrer').is(':checked');
}

// Обновить предпросмотр кода Worker с подстановкой конфигурации
function updateWorkerPreview() {
    if (!workerConfig.originalCode) {
        $('#workerPreview').html('// Загрузите шаблон для предпросмотра');
        return;
    }
    
    updateConfigFromForm();
    
    let code = workerConfig.originalCode;
    
    // Замена плейсхолдеров на значения из конфигурации
    
    // Bad bots list
    const botsString = workerConfig.badBots.map(b => `'${b}'`).join(', ');
    code = code.replace(/\{\{BAD_BOTS_LIST\}\}/g, botsString);
    code = code.replace(/const\s+BAD_BOTS\s*=\s*\[([^\]]*)\]/g, `const BAD_BOTS = [${botsString}]`);
    
    // Blocked IPs
    const ipsString = workerConfig.blockedIps.map(ip => `'${ip}'`).join(', ');
    code = code.replace(/\{\{BLOCKED_IPS\}\}/g, ipsString);
    code = code.replace(/const\s+BLOCKED_IPS\s*=\s*\[([^\]]*)\]/g, `const BLOCKED_IPS = [${ipsString}]`);
    
    // Geo settings
    if (workerConfig.geoMode === 'whitelist') {
        const countriesString = workerConfig.allowedCountries.map(c => `'${c}'`).join(', ');
        code = code.replace(/\{\{ALLOWED_COUNTRIES\}\}/g, countriesString);
        code = code.replace(/const\s+ALLOWED_COUNTRIES\s*=\s*\[([^\]]*)\]/g, `const ALLOWED_COUNTRIES = [${countriesString}]`);
        code = code.replace(/\{\{GEO_MODE\}\}/g, 'whitelist');
    } else {
        const countriesString = workerConfig.blockedCountries.map(c => `'${c}'`).join(', ');
        code = code.replace(/\{\{BLOCKED_COUNTRIES\}\}/g, countriesString);
        code = code.replace(/const\s+BLOCKED_COUNTRIES\s*=\s*\[([^\]]*)\]/g, `const BLOCKED_COUNTRIES = [${countriesString}]`);
        code = code.replace(/\{\{GEO_MODE\}\}/g, 'blacklist');
    }
    
    // Rate limit
    code = code.replace(/\{\{RATE_LIMIT_REQUESTS\}\}/g, workerConfig.rateLimit.requests);
    code = code.replace(/\{\{RATE_LIMIT_WINDOW\}\}/g, workerConfig.rateLimit.window);
    code = code.replace(/const\s+RATE_LIMIT\s*=\s*\d+/g, `const RATE_LIMIT = ${workerConfig.rateLimit.requests}`);
    code = code.replace(/const\s+RATE_WINDOW\s*=\s*\d+/g, `const RATE_WINDOW = ${workerConfig.rateLimit.window}`);
    
    // Referrers
    const referrersString = workerConfig.allowedReferrers.map(r => `'${r}'`).join(', ');
    code = code.replace(/\{\{ALLOWED_REFERRERS\}\}/g, referrersString);
    code = code.replace(/const\s+ALLOWED_REFERRERS\s*=\s*\[([^\]]*)\]/g, `const ALLOWED_REFERRERS = [${referrersString}]`);
    
    // URL exceptions
    const exceptionsString = workerConfig.urlExceptions.map(e => `'${e}'`).join(', ');
    code = code.replace(/\{\{URL_EXCEPTIONS\}\}/g, exceptionsString);
    code = code.replace(/const\s+URL_EXCEPTIONS\s*=\s*\[([^\]]*)\]/g, `const URL_EXCEPTIONS = [${exceptionsString}]`);
    
    // Отображаем код (используем #workerPreview из HTML)
    $('#workerPreview').html(escapeHtml(code));
    
    // Сохраняем итоговый код
    workerConfig.generatedCode = code;
}

// Применить быстрый пресет
function applyPreset(preset) {
    switch(preset) {
        case 'russia':
            workerConfig.geoMode = 'whitelist';
            workerConfig.allowedCountries = ['RU'];
            workerConfig.blockedCountries = [];
            break;
        case 'cis':
            workerConfig.geoMode = 'whitelist';
            workerConfig.allowedCountries = ['RU', 'BY', 'KZ', 'UA', 'UZ', 'GE', 'AM', 'AZ', 'MD', 'KG', 'TJ', 'TM'];
            workerConfig.blockedCountries = [];
            break;
        case 'block-bots':
            workerConfig.badBots = ['semrush', 'ahrefs', 'mj12bot', 'dotbot', 'petalbot', 'rogerbot', 'blexbot',
                                    'linkdex', 'gigabot', 'exabot', 'sogou', 'yandexbot', 'baiduspider',
                                    'seznambot', 'duckduckbot'];
            break;
        case 'strict':
            workerConfig.geoMode = 'whitelist';
            workerConfig.allowedCountries = ['RU'];
            workerConfig.badBots = ['semrush', 'ahrefs', 'mj12bot', 'dotbot', 'petalbot', 'rogerbot',
                                    'blexbot', 'linkdex', 'gigabot', 'exabot'];
            workerConfig.rateLimit = { enabled: true, requests: 30, window: 60 };
            workerConfig.blockedCountries = ['CN', 'KP', 'IR'];
            break;
    }
    
    // Обновляем форму и preview
    generateConfigPanel(currentWorkerTemplate);
    updateWorkerPreview();
    showSuccess('Пресет "' + preset + '" применён');
}

// Добавить пресет ботов
function addBotPreset(type) {
    const presets = {
        seo: ['semrush', 'ahrefs', 'mj12bot', 'dotbot', 'rogerbot', 'blexbot', 'linkdex'],
        scrapers: ['scrapy', 'python-requests', 'curl', 'wget', 'httpclient', 'java', 'libwww'],
        all: ['semrush', 'ahrefs', 'mj12bot', 'dotbot', 'petalbot', 'rogerbot', 'blexbot', 'linkdex',
              'gigabot', 'exabot', 'sogou', 'baiduspider', 'yandexbot', 'seznambot', 'duckduckbot',
              'scrapy', 'python-requests', 'curl', 'wget', 'httpclient']
    };
    
    const newBots = presets[type] || [];
    const currentBots = workerConfig.badBots;
    
    // Объединяем без дубликатов
    const merged = [...new Set([...currentBots, ...newBots])];
    workerConfig.badBots = merged;
    
    // Обновляем textarea
    $('#configBadBots').val(merged.join(', '));
    updateWorkerPreview();
    showInfo(`Добавлено ${newBots.length} ботов`);
}

// Установить пресет геоблокировки
function setGeoPreset(mode, countries) {
    workerConfig.geoMode = mode;
    if (mode === 'whitelist') {
        workerConfig.allowedCountries = countries;
        $('#configAllowedCountries').val(countries.join(', '));
    } else {
        workerConfig.blockedCountries = countries;
        $('#configBlockedCountries').val(countries.join(', '));
    }
    updateWorkerPreview();
}

// Установить пресет rate limit
function setRateLimitPreset(requests, window) {
    workerConfig.rateLimit.requests = requests;
    workerConfig.rateLimit.window = window;
    $('#configRateLimitRequests').val(requests);
    $('#configRateLimitWindow').val(window);
    updateWorkerPreview();
}

// Добавить пресет реферреров
function addReferrerPreset(type) {
    const presets = {
        search: ['google.', 'yandex.', 'bing.com', 'duckduckgo.com', 'yahoo.com', 'baidu.com'],
        social: ['facebook.com', 'twitter.com', 'instagram.com', 'vk.com', 'ok.ru', 't.me', 'tiktok.com'],
        all: ['google.', 'yandex.', 'bing.com', 'duckduckgo.com', 'yahoo.com', 'baidu.com',
              'facebook.com', 'twitter.com', 'instagram.com', 'vk.com', 'ok.ru', 't.me', 'linkedin.com']
    };
    
    const newReferrers = presets[type] || [];
    const current = workerConfig.allowedReferrers;
    
    const merged = [...new Set([...current, ...newReferrers])];
    workerConfig.allowedReferrers = merged;
    
    $('#configAllowedReferrers').val(merged.join('\n'));
    updateWorkerPreview();
    showInfo(`Добавлено ${newReferrers.length} реферреров`);
}

// Развернуть Worker с пользовательской конфигурацией
function deployWorkerWithConfig() {
    if (!currentWorkerTemplate) {
        showError('Выберите шаблон Worker');
        return;
    }
    
    if (!workerConfig.generatedCode) {
        updateWorkerPreview();
    }
    
    const scope = getScope('worker');
    const route = $('#workerRoute').val()?.trim() || '/*';
    
    if (scope.count === 0) {
        showError('Выберите домены для развертывания');
        return;
    }
    
    if (!confirm(`Развернуть Worker "${currentWorkerTemplate}" с вашей конфигурацией на ${scope.count} доменах?`)) {
        return;
    }
    
    showLoading('Развертывание Worker с конфигурацией...');
    
    $.post('security_rules_api_minimal.php', {
        action: 'deploy_worker_with_config',
        template: currentWorkerTemplate,
        route: route,
        config: JSON.stringify(workerConfig),
        code: workerConfig.generatedCode,
        scope: scope
    })
    .done(function(response) {
        hideLoading();
        if (response.success) {
            showSuccess(`Worker развернут на ${response.applied} доменах`);
            if (response.errors && response.errors.length > 0) {
                console.warn('Некоторые домены с ошибками:', response.errors);
            }
            setTimeout(() => location.reload(), 2000);
        } else {
            showError(response.error || 'Ошибка развертывания Worker');
        }
    })
    .fail(function(xhr) {
        hideLoading();
        console.error('Deploy error:', xhr.responseText);
        showError('Ошибка соединения с сервером');
    });
}

// Debounce функция для оптимизации
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

