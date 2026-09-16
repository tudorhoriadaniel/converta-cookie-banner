/**
 * Converta Cookie Banner - Banner Logic & Google Consent Mode v2 Update
 *
 * The consent default is fired inline in <head> by PHP (first script in the
 * document). The banner MARKUP is intentionally absent from the page HTML
 * (SEO: its texts would be duplicate content on every page) — this script
 * fetches it via AJAX and injects it into the DOM at runtime, then wires up
 * all UI behavior, fires consent 'update', and logs consent via AJAX.
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

    // ---- UI state (elements exist only after AJAX injection) ----

    var overlay = null, banner = null, prefsPanel = null, reopenBtn = null;
    var bannerLoaded = false, bannerLoading = false, pendingShow = false;

    // ---- Basic Consent Mode: re-activate hard-blocked GTM scripts ----
    // (Scripts are only neutralized server-side when the "Block GTM until
    // consent" option is enabled; otherwise this is a harmless no-op.)

    function activateBlockedTags() {
        document.querySelectorAll('script[data-pcc-gtm]').forEach(function (old) {
            if (old.getAttribute('data-pcc-activated')) return;
            old.setAttribute('data-pcc-activated', '1');
            var s = document.createElement('script');
            for (var i = 0; i < old.attributes.length; i++) {
                var a = old.attributes[i];
                if (a.name === 'type' || a.name === 'data-pcc-gtm' || a.name === 'data-pcc-activated') continue;
                s.setAttribute(a.name, a.value);
            }
            s.text = old.text;
            old.parentNode.insertBefore(s, old.nextSibling);
        });
    }

    // ---- Save & Apply ----

    function saveConsent(action, consent) {
        setCookie(COOKIE_NAME, consent, COOKIE_EXPIRY);
        updateConsentMode(consent);
        if (consent.statistics || consent.marketing) {
            activateBlockedTags();
        }
        pushConsentEvents(consent);
        logConsent(action, consent);
        hideBanner();
    }

    // ---- UI Controls ----

    function showBanner() {
        if (!bannerLoaded) {
            pendingShow = true;
            loadBanner();
            return;
        }
        overlay.style.display = 'flex';
        banner.style.display  = '';
        prefsPanel.style.display = 'none';
        document.body.style.overflow = 'hidden';
    }

    function hideBanner() {
        if (!bannerLoaded) return;
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

    // ---- Smart footer link placement ----
    // Try to move the consent link into the theme's own footer links area
    // so it inherits the theme's styling. The standalone bar stays only as
    // a last-resort fallback.

    function placeFooterLink() {
        var bar = document.querySelector('.pcc-footer-bar');
        if (!bar) return;
        var link = bar.querySelector('a.pcc-plink');
        if (!link) return;

        // If a consent link was already placed manually (menu item with the
        // pcc-plink class, shortcode, widget), don't add another one —
        // just remove the fallback bar.
        var existing = document.querySelectorAll('.pcc-plink');
        for (var k = 0; k < existing.length; k++) {
            if (!bar.contains(existing[k])) {
                bar.parentNode.removeChild(bar);
                return;
            }
        }

        // 1) Footer menus (bottom-bar menus usually come last in the DOM,
        //    so pick the LAST match to avoid widget-column menus).
        var menuSelectors = [
            'footer ul.footer-menu', 'footer .footer-menu ul',
            '.site-footer ul.footer-menu',
            'footer nav ul', '.site-footer nav ul', '#colophon nav ul',
            'footer ul.menu', '.site-footer ul.menu', '#colophon ul.menu',
            'footer ul.nav'
        ];
        for (var i = 0; i < menuSelectors.length; i++) {
            var lists = document.querySelectorAll(menuSelectors[i]);
            if (!lists.length) continue;
            var menu = lists[lists.length - 1];
            var li = document.createElement('li');
            li.className = 'pcc-menu-item';
            // Borrow the class of a sibling item so theme menu styles apply
            var sibling = menu.querySelector('li');
            if (sibling && sibling.className) {
                li.className = sibling.className
                    .replace(/\bcurrent[-_\w]*\b/g, '')
                    .replace(/\bactive\b/g, '')
                    .trim() + ' pcc-menu-item';
            }
            li.appendChild(link);
            menu.appendChild(li);
            bar.parentNode.removeChild(bar);
            return;
        }

        // 2) Copyright / site-info rows — append inline with a separator.
        var inlineSelectors = [
            'footer .site-info', '.site-footer .site-info',
            'footer .copyright', '.site-footer .copyright',
            '.hestia-bottom-footer-content', 'footer .footer-bottom',
            'footer .colophon-content'
        ];
        for (var j = 0; j < inlineSelectors.length; j++) {
            var row = document.querySelector(inlineSelectors[j]);
            if (!row) continue;
            var sep = document.createElement('span');
            sep.className = 'pcc-sep';
            sep.textContent = ' · ';
            row.appendChild(sep);
            row.appendChild(link);
            bar.parentNode.removeChild(bar);
            return;
        }

        // 3) Universal fallback for custom/hand-coded footers: insert right
        //    after the LAST link inside the page's last <footer> element, so
        //    it sits with the theme's own footer links and inherits their
        //    styling (works with flex/gap rows, plain divs, anything).
        var footers = document.querySelectorAll('footer');
        if (footers.length) {
            var foot = footers[footers.length - 1];
            var anchors = foot.querySelectorAll('a[href]');
            if (anchors.length) {
                var lastA = anchors[anchors.length - 1];
                if (lastA.parentNode && !bar.contains(lastA)) {
                    lastA.parentNode.insertBefore(link, lastA.nextSibling);
                    bar.parentNode.removeChild(bar);
                    return;
                }
            }
        }

        // 4) No suitable spot found — keep the standalone bar as-is.
    }

    // ---- Wire up the injected markup ----

    function initUI() {
        overlay    = document.getElementById('pcc-ui-overlay');
        banner     = document.getElementById('pcc-ui-card');
        prefsPanel = document.getElementById('pcc-preferences-panel');
        reopenBtn  = document.getElementById('pcc-reopen');

        if (!overlay || !banner || !prefsPanel) return;

        var acceptAll = document.getElementById('pcc-accept-all');
        if (acceptAll) acceptAll.addEventListener('click', function () {
            saveConsent('accept_all', { necessary: true, statistics: true, marketing: true, timestamp: Date.now() });
        });

        var rejectAll = document.getElementById('pcc-reject-all');
        if (rejectAll) rejectAll.addEventListener('click', function () {
            saveConsent('reject_all', { necessary: true, statistics: false, marketing: false, timestamp: Date.now() });
        });

        var showPrefs = document.getElementById('pcc-show-preferences');
        if (showPrefs) showPrefs.addEventListener('click', showPreferences);

        var savePrefs = document.getElementById('pcc-save-preferences');
        if (savePrefs) savePrefs.addEventListener('click', function () {
            var stats = document.getElementById('pcc-statistics-toggle').checked;
            var mkt   = document.getElementById('pcc-marketing-toggle').checked;
            saveConsent('save_preferences', { necessary: true, statistics: stats, marketing: mkt, timestamp: Date.now() });
        });

        var cancelPrefs = document.getElementById('pcc-cancel-preferences');
        if (cancelPrefs) cancelPrefs.addEventListener('click', hidePreferences);

        var closePrefs = document.getElementById('pcc-close-preferences');
        if (closePrefs) closePrefs.addEventListener('click', hidePreferences);

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

        // Expand/collapse cookie details
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

        placeFooterLink();

        var existing = getCookie(COOKIE_NAME);
        if (existing && existing.timestamp) {
            if (reopenBtn) reopenBtn.style.display = 'flex';
            if (pendingShow) { pendingShow = false; showBanner(); }
        } else {
            pendingShow = false;
            showBanner();
        }
    }

    // ---- Fetch the banner markup and inject it ----

    function loadBanner() {
        if (bannerLoaded || bannerLoading) return;
        bannerLoading = true;

        var lang = (document.documentElement.getAttribute('lang') || '').substring(0, 2).toLowerCase();
        var url = AJAX_URL +
            '?action=pcc_load_ui' +
            '&path=' + encodeURIComponent(window.location.pathname) +
            '&lang=' + encodeURIComponent(lang);

        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res || !res.success || !res.data || !res.data.html) {
                    bannerLoading = false;
                    return;
                }
                var wrap = document.createElement('div');
                wrap.innerHTML = res.data.html;
                while (wrap.firstChild) {
                    document.body.appendChild(wrap.firstChild);
                }
                bannerLoaded  = true;
                bannerLoading = false;
                initUI();
            })
            .catch(function () { bannerLoading = false; });
    }

    // ---- Init ----

    // Necessary cookies are always active — fire on every page load
    window.dataLayer.push({ 'event': 'cookie_necessary' });

    // Returning visitor — re-fire consent events immediately, and if GTM
    // hard-blocking is enabled, load GTM right away for visitors who
    // already granted statistics or marketing consent.
    var existing0 = getCookie(COOKIE_NAME);
    if (existing0 && existing0.timestamp) {
        pushConsentEvents(existing0);
        if (existing0.statistics || existing0.marketing) {
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', activateBlockedTags);
            } else {
                activateBlockedTags();
            }
        }
    }

    // Consent links placed manually (menu items, shortcode) work even
    // before the banner markup is loaded. The legacy class name from
    // pre-1.7.0 setups is also supported.
    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('.pcc-plink, .pcc-consent-link') : null;
        if (link) {
            e.preventDefault();
            showBanner();
        }
    });

    // Rename legacy manually-placed link classes to the neutral one, so
    // ad-block cosmetic filters (which hide [class*="consent"] patterns)
    // don't hide the user's own menu items.
    function migrateLegacyLinks() {
        document.querySelectorAll('.pcc-consent-link').forEach(function (el) {
            el.classList.remove('pcc-consent-link');
            el.classList.add('pcc-plink');
        });
    }

    function boot() {
        migrateLegacyLinks();
        loadBanner();
    }

    // Always load the banner container: new visitors need the banner itself,
    // returning visitors need the reopen icon / footer link.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})();
