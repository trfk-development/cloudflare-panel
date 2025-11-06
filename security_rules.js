/**
 * Security Rules Manager - Frontend JavaScript
 */

// Список стран для геоблокировки
const countries = [
    {code: 'RU', name: 'Россия', flag: '🇷🇺'},
    {code: 'US', name: 'США', flag: '🇺🇸'},
    {code: 'GB', name: 'Великобритания', flag: '🇬🇧'},
    {code: 'DE', name: 'Германия', flag: '🇩🇪'},
    {code: 'FR', name: 'Франция', flag: '🇫🇷'},
    {code: 'IT', name: 'Италия', flag: '🇮🇹'},
    {code: 'ES', name: 'Испания', flag: '🇪🇸'},
    {code: 'PL', name: 'Польша', flag: '🇵🇱'},
    {code: 'UA', name: 'Украина', flag: '🇺🇦'},
    {code: 'TR', name: 'Турция', flag: '🇹🇷'},
    {code: 'CN', name: 'Китай', flag: '🇨🇳'},
    {code: 'JP', name: 'Япония', flag: '🇯🇵'},
    {code: 'KR', name: 'Южная Корея', flag: '🇰🇷'},
    {code: 'IN', name: 'Индия', flag: '🇮🇳'},
    {code: 'BR', name: 'Бразилия', flag: '🇧🇷'},
    {code: 'CA', name: 'Канада', flag: '🇨🇦'},
    {code: 'AU', name: 'Австралия', flag: '🇦🇺'},
    {code: 'MX', name: 'Мексика', flag: '🇲🇽'},
    {code: 'AR', name: 'Аргентина', flag: '🇦🇷'},
    {code: 'ZA', name: 'ЮАР', flag: '🇿🇦'}
];

let selectedCountries = [];
let currentWorkerTemplate = null;

// Инициализация при загрузке страницы
$(document).ready(function() {
    initializeCountryList();
    initializeScopeSelectors();
    initializeReferrerActionSelector();
});

// Инициализация списка стран
function initializeCountryList() {
    const countryList = $('#countryList');
    countryList.empty();
    
    countries.forEach(country => {
        countryList.append(`
            <div class="form-check">
                <input class="form-check-input country-checkbox" type="checkbox" value="${country.code}" id="country-${country.code}">
                <label class="form-check-label" for="country-${country.code}">
                    ${country.flag} ${country.name}
                </label>
            </div>
        `);
    });
    
    // Обработчик выбора стран
    $('.country-checkbox').on('change', function() {
        updateSelectedCountries();
    });
    
    // Поиск стран
    $('#countrySearch').on('input', function() {
        const search = $(this).val().toLowerCase();
        $('.country-checkbox').each(function() {
            const label = $(this).next('label').text().toLowerCase();
            $(this).parent().toggle(label.includes(search));
        });
    });
}

// Обновление выбранных стран
function updateSelectedCountries() {
    selectedCountries = [];
    $('.country-checkbox:checked').each(function() {
        const code = $(this).val();
        const country = countries.find(c => c.code === code);
        if (country) {
            selectedCountries.push(country);
        }
    });
    
    $('#selectedCountCount').text(selectedCountries.length);
    
    const selectedDiv = $('#selectedCountries');
    if (selectedCountries.length === 0) {
        selectedDiv.html('<p class="text-muted text-center">Выберите страны слева</p>');
    } else {
        selectedDiv.html(selectedCountries.map(c => `
            <span class="badge bg-primary me-1 mb-1">${c.flag} ${c.name}</span>
        `).join(''));
    }
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
    
    $.post('security_rules_api.php', {
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
    
    $.post('security_rules_api.php', {
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

// Применить геоблокировку
function applyGeoBlocker() {
    if (selectedCountries.length === 0) {
        showError('Выберите хотя бы одну страну');
        return;
    }
    
    const mode = $('input[name="geoMode"]:checked').val();
    const scope = getScope('geoBlocker');
    const countryCodes = selectedCountries.map(c => c.code);
    
    const modeText = mode === 'whitelist' ? 'разрешить доступ только из' : 'заблокировать доступ из';
    if (!confirm(`${modeText.charAt(0).toUpperCase() + modeText.slice(1)} ${selectedCountries.length} стран для ${scope.count} доменов?`)) {
        return;
    }
    
    showLoading('Применение геоблокировки...');
    
    $.post('security_rules_api.php', {
        action: 'apply_geo_blocker',
        mode: mode,
        countries: countryCodes,
        scope: scope
    })
    .done(function(response) {
        hideLoading();
        if (response.success) {
            showSuccess(`Геоблокировка применена к ${response.applied} доменам`);
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
    
    $.post('security_rules_api.php', {
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
    currentWorkerTemplate = template;
    
    showLoading('Загрузка шаблона...');
    
    $.get('security_rules_api.php', {
        action: 'get_worker_template',
        template: template
    })
    .done(function(response) {
        hideLoading();
        if (response.success) {
            $('#workerPreview').html(`<pre>${escapeHtml(response.code)}</pre>`);
        } else {
            showError(response.error || 'Ошибка загрузки шаблона');
        }
    })
    .fail(function() {
        hideLoading();
        showError('Ошибка соединения с сервером');
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
    
    $.post('security_rules_api.php', {
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

// Вспомогательные функции
function showLoading(message) {
    // TODO: Показать loading overlay
    console.log('Loading:', message);
}

function hideLoading() {
    // TODO: Скрыть loading overlay
    console.log('Loading hidden');
}

function showSuccess(message) {
    alert('✅ ' + message);
}

function showError(message) {
    alert('❌ ' + message);
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

