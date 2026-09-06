/**
 * Converta Cookie Banner - Banner Logic & Google Consent Mode v2 Update
 *
 * The default consent is fired inline in <head> by PHP (priority 1).
 * This script handles the UI, fires consent 'update', and logs to DB via AJAX.
 */
(function () {
    'use strict';

    var COOKIE_NAME   = pccConfig.cookieName;
    var COOKIE_EXPIRY = parseInt(pccConfig.cookieExpiry, 10);
    var COOKIE_DOMAIN = pccConfig.cookieDomain;
    var AJAX_URL      = pccConfig.ajaxUrl;
    var NONCE         = pccConfig.nonce;

    // ---- Cookie helpers ----

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 86400000);
        document.cookie = name + '=' + encodeURIComponent(JSON.stringify(value)) +
            ';expires=' + d.toUTCString() +
            ';path=/' +
            ';domain=' + COOKIE_DOMAIN +
            ';SameSite=Lax;Secure';
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        if (match) {
            try { return JSON.parse(decodeURIComponent(match[1])); }
            catch (e) { return null; }
        }
        return null;
    }

    // ---- dataLayer helpers ----

    window.dataLayer = window.dataLayer || [];

    function pushConsentEvents(consent) {
        if (consent.statistics) {
            window.dataLayer.push({ 'event': 'cookie_analytics' });
        }
        if (consent.marketing) {
            window.dataLayer.push({ 'event': 'cookie_marketing' });
        }
        // Functional consent is always granted in our setup
        window.dataLayer.push({ 'event': 'cookie_functional' });
    }

    // ---- Consent Mode v2 Update ----

    function updateConsentMode(consent) {
        if (typeof gtag !== 'function') return;

        gtag('consent', 'update', {
            'ad_storage':            consent.marketing  ? 'granted' : 'denied',
            'ad_user_data':          consent.marketing  ? 'granted' : 'denied',
            'ad_personalization':    consent.marketing  ? 'granted' : 'denied',
            'analytics_storage':     consent.statistics  ? 'granted' : 'denied',
            'functionality_storage': 'granted',
            'personalization_storage': consent.marketing ? 'granted' : 'denied',
            'security_storage':      'granted'
        });
    }

    // ---- Log consent to server ----

    function logConsent(action, consent) {
        var data = new FormData();
        data.append('action', 'pcc_log_consent');
        data.append('nonce', NONCE);
        data.append('consent_action', action);
        data.append('statistics', consent.statistics ? 1 : 0);
        data.append('marketing', consent.marketing ? 1 : 0);

        if (navigator.sendBeacon) {
            navigator.sendBeacon(AJAX_URL, data);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', AJAX_URL, true);
            xhr.send(data);
        }
    }

    // ---- Save & Apply ----

    function saveConsent(action, consent) {
        setCookie(COOKIE_NAME, consent, COOKIE_EXPIRY);
        updateConsentMode(consent);
        pushConsentEvents(consent);
        logConsent(action, consent);
        hideBanner();
    }

    // ---- UI Controls ----

    var overlay      = document.getElementById('pcc-cookie-overlay');
    var banner       = document.getElementById('pcc-cookie-banner');
    var prefsPanel   = document.getElementById('pcc-preferences-panel');
    var reopenBtn    = document.getElementById('pcc-reopen-banner');

    function showBanner() {
        overlay.style.display = 'flex';
        banner.style.display  = '';
        prefsPanel.style.display = 'none';
        document.body.style.overflow = 'hidden';
    }

    function hideBanner() {
        overlay.style.display = 'none';
        banner.style.display  = '';
        prefsPanel.style.display = 'none';
        document.body.style.overflow = '';
        if (reopenBtn) reopenBtn.style.display = 'flex';
    }

    function showPreferences() {
        banner.style.display     = 'none';
        prefsPanel.style.display = 'flex';

        var existing = getCookie(COOKIE_NAME);
        if (existing) {
            var statsToggle = document.getElementById('pcc-statistics-toggle');
            var mktToggle   = document.getElementById('pcc-marketing-toggle');
            if (statsToggle) statsToggle.checked = !!existing.statistics;
            if (mktToggle)   mktToggle.checked   = !!existing.marketing;
        }
    }

    function hidePreferences() {
        prefsPanel.style.display = 'none';
        banner.style.display     = '';
    }

    // ---- Button handlers ----

    document.getElementById('pcc-accept-all').addEventListener('click', function () {
        saveConsent('accept_all', { necessary: true, statistics: true, marketing: true, timestamp: Date.now() });
    });

    document.getElementById('pcc-reject-all').addEventListener('click', function () {
        saveConsent('reject_all', { necessary: true, statistics: false, marketing: false, timestamp: Date.now() });
    });

    document.getElementById('pcc-show-preferences').addEventListener('click', showPreferences);

    document.getElementById('pcc-save-preferences').addEventListener('click', function () {
        var stats = document.getElementById('pcc-statistics-toggle').checked;
        var mkt   = document.getElementById('pcc-marketing-toggle').checked;
        saveConsent('save_preferences', { necessary: true, statistics: stats, marketing: mkt, timestamp: Date.now() });
    });

    document.getElementById('pcc-cancel-preferences').addEventListener('click', hidePreferences);
    document.getElementById('pcc-close-preferences').addEventListener('click', hidePreferences);

    // Accept All from preferences panel
    var acceptAllPrefs = document.getElementById('pcc-accept-all-prefs');
    if (acceptAllPrefs) {
        acceptAllPrefs.addEventListener('click', function () {
            saveConsent('accept_all', { necessary: true, statistics: true, marketing: true, timestamp: Date.now() });
        });
    }

    if (reopenBtn) {
        reopenBtn.addEventListener('click', function () {
            reopenBtn.style.display = 'none';
            showBanner();
        });
    }

    // Footer link / shortcode / menu links with class .pcc-consent-link
    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('.pcc-consent-link') : null;
        if (link) {
            e.preventDefault();
            showBanner();
        }
    });

    // ---- Expand/collapse cookie details ----

    document.querySelectorAll('.pcc-expand-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = this.dataset.target;
            var target = document.getElementById(targetId);
            if (!target) return;

            var isOpen = target.style.display !== 'none';
            target.style.display = isOpen ? 'none' : 'block';
            this.classList.toggle('pcc-expanded', !isOpen);
        });
    });

    // ---- Init ----

    // Necessary cookies are always active — fire on every page load
    window.dataLayer.push({ 'event': 'cookie_necessary' });

    var existing = getCookie(COOKIE_NAME);
    if (existing && existing.timestamp) {
        // Returning visitor — re-fire consent events based on stored preferences
        pushConsentEvents(existing);
        if (reopenBtn) reopenBtn.style.display = 'flex';
    } else {
        showBanner();
    }

})();
