/**
 * Converta Cookie Banner - Admin Scanner Page
 */
(function () {
    'use strict';

    var cookies   = (pccScanner.scanData && pccScanner.scanData.cookies) ? pccScanner.scanData.cookies : [];
    var AJAX_URL  = pccScanner.ajaxUrl;
    var NONCE     = pccScanner.nonce;

    // ======================================================================
    //  PREBUILT COOKIE LIBRARY
    // ======================================================================

    var PREBUILT = [
        {
            id: 'facebook',
            name: 'Facebook / Meta Pixel',
            icon: '📘',
            color: '#1877f2',
            cookies: [
                { name: '_fbp',  provider: 'Facebook / Meta', category: 'marketing', duration: '3 months',  description: 'Stores a unique browser ID used by Facebook to deliver and measure ads.' },
                { name: '_fbc',  provider: 'Facebook / Meta', category: 'marketing', duration: '2 years',   description: 'Stores the Facebook click identifier when a user arrives from a Facebook ad.' },
                { name: 'fr',    provider: 'Facebook / Meta', category: 'marketing', duration: '3 months',  description: 'Used by Facebook for ad delivery, measurement, and retargeting.' },
                { name: 'datr',  provider: 'Facebook / Meta', category: 'marketing', duration: '2 years',   description: 'Identifies the browser connecting to Facebook. Used for security and integrity.' },
                { name: 'sb',    provider: 'Facebook / Meta', category: 'marketing', duration: '2 years',   description: 'Browser identification cookie used for security by Meta platforms.' },
            ]
        },
        {
            id: 'google-ads',
            name: 'Google Ads',
            icon: '📢',
            color: '#4285f4',
            cookies: [
                { name: '_gcl_aw',     provider: 'Google Ads', category: 'marketing', duration: '90 days',    description: 'Stores Google Ads click information (GCLID) for conversion tracking.' },
                { name: '_gcl_au',     provider: 'Google Ads', category: 'marketing', duration: '90 days',    description: 'Used by Google Ads Conversion Linker to store ad click data.' },
                { name: '_gac_*',      provider: 'Google Ads', category: 'marketing', duration: '90 days',    description: 'Contains campaign information for Google Ads linked to Google Analytics.' },
                { name: 'IDE',         provider: 'Google DoubleClick', category: 'marketing', duration: '1 year', description: 'Used by Google DoubleClick to register and report ad clicks for retargeting.' },
                { name: 'test_cookie', provider: 'Google DoubleClick', category: 'marketing', duration: '15 minutes', description: 'Checks whether the browser accepts third-party cookies.' },
            ]
        },
        {
            id: 'google-analytics',
            name: 'Google Analytics (GA4)',
            icon: '📊',
            color: '#e37400',
            cookies: [
                { name: '_ga',   provider: 'Google Analytics', category: 'statistics', duration: '2 years',  description: 'Assigns a randomly generated ID to distinguish unique visitors across sessions.' },
                { name: '_ga_*', provider: 'Google Analytics', category: 'statistics', duration: '2 years',  description: 'Persists session state and campaign data across page loads in GA4.' },
                { name: '_gid',  provider: 'Google Analytics', category: 'statistics', duration: '24 hours', description: 'Distinguishes unique users within a 24-hour window for analytics.' },
                { name: '_gat',  provider: 'Google Analytics', category: 'statistics', duration: '1 minute', description: 'Throttles the request rate to Google Analytics to prevent overload.' },
            ]
        },
        {
            id: 'linkedin',
            name: 'LinkedIn Insight Tag',
            icon: '💼',
            color: '#0a66c2',
            cookies: [
                { name: 'li_sugr',            provider: 'LinkedIn', category: 'marketing', duration: '3 months', description: 'Used by LinkedIn for ad targeting and analytics on non-LinkedIn pages.' },
                { name: 'bcookie',             provider: 'LinkedIn', category: 'marketing', duration: '1 year',   description: 'LinkedIn browser ID cookie for identifying devices.' },
                { name: 'lidc',                provider: 'LinkedIn', category: 'marketing', duration: '24 hours', description: 'Used for routing and data center selection by LinkedIn services.' },
                { name: 'UserMatchHistory',    provider: 'LinkedIn', category: 'marketing', duration: '30 days',  description: 'Syncs LinkedIn ad targeting IDs with partner platforms.' },
                { name: 'AnalyticsSyncHistory',provider: 'LinkedIn', category: 'marketing', duration: '30 days',  description: 'Stores information about the time a LinkedIn cookie sync took place.' },
                { name: 'li_fat_id',           provider: 'LinkedIn', category: 'marketing', duration: '30 days',  description: 'First-party LinkedIn tracking cookie for conversion attribution.' },
                { name: 'ln_or',               provider: 'LinkedIn', category: 'marketing', duration: 'Session',  description: 'Used to determine if Oribi analytics can be carried out on LinkedIn.' },
            ]
        },
        {
            id: 'tiktok',
            name: 'TikTok Pixel',
            icon: '🎵',
            color: '#010101',
            cookies: [
                { name: '_ttp',         provider: 'TikTok', category: 'marketing', duration: '13 months', description: 'Assigns a unique ID to track visitors and measure TikTok ad performance.' },
                { name: 'tt_webid',     provider: 'TikTok', category: 'marketing', duration: '1 year',    description: 'TikTok tracking identifier used for ad delivery and targeting.' },
                { name: 'tt_webid_v2',  provider: 'TikTok', category: 'marketing', duration: '1 year',    description: 'Updated TikTok tracking ID for cross-site ad measurement.' },
                { name: 'tt_sessionId', provider: 'TikTok', category: 'marketing', duration: 'Session',   description: 'Tracks user sessions for TikTok ad attribution.' },
            ]
        },
        {
            id: 'twitter',
            name: 'Twitter / X Pixel',
            icon: '🐦',
            color: '#1da1f2',
            cookies: [
                { name: 'muc_ads',            provider: 'Twitter / X', category: 'marketing', duration: '2 years',  description: 'Used by Twitter to track ad engagement and measure campaign performance.' },
                { name: 'personalization_id',  provider: 'Twitter / X', category: 'marketing', duration: '2 years',  description: 'Allows Twitter to personalize content and ads based on browsing behavior.' },
                { name: 'guest_id',            provider: 'Twitter / X', category: 'marketing', duration: '2 years',  description: 'Assigns a unique guest ID for non-logged-in Twitter tracking.' },
                { name: 'guest_id_marketing',  provider: 'Twitter / X', category: 'marketing', duration: '2 years',  description: 'Twitter marketing cookie for guest users ad targeting.' },
                { name: 'guest_id_ads',        provider: 'Twitter / X', category: 'marketing', duration: '2 years',  description: 'Twitter ads cookie for guest users conversion tracking.' },
            ]
        },
        {
            id: 'pinterest',
            name: 'Pinterest Tag',
            icon: '📌',
            color: '#e60023',
            cookies: [
                { name: '_pinterest_ct_ua', provider: 'Pinterest', category: 'marketing', duration: '1 year',    description: 'Groups actions for users who cannot be identified by Pinterest cookie.' },
                { name: '_pin_unauth',      provider: 'Pinterest', category: 'marketing', duration: '1 year',    description: 'Tracks unauthenticated users interacting with Pinterest content.' },
                { name: '_derived_epik',    provider: 'Pinterest', category: 'marketing', duration: '1 year',    description: 'Identifies users for Pinterest ad targeting and measurement.' },
                { name: '_epik',            provider: 'Pinterest', category: 'marketing', duration: '1 year',    description: 'Pinterest enhanced match cookie for conversion attribution.' },
            ]
        },
        {
            id: 'snapchat',
            name: 'Snapchat Pixel',
            icon: '👻',
            color: '#fffc00',
            cookies: [
                { name: '_scid',   provider: 'Snapchat', category: 'marketing', duration: '13 months', description: 'Snapchat cookie identifier used for ad targeting and conversion tracking.' },
                { name: '_scid_r', provider: 'Snapchat', category: 'marketing', duration: '13 months', description: 'Snapchat cookie used for retargeting website visitors with ads.' },
                { name: 'sc_at',   provider: 'Snapchat', category: 'marketing', duration: '13 months', description: 'Snapchat advertising token for cross-site tracking.' },
            ]
        },
        {
            id: 'hotjar',
            name: 'Hotjar',
            icon: '🔥',
            color: '#ff3c00',
            cookies: [
                { name: '_hjSessionUser_*', provider: 'Hotjar', category: 'statistics', duration: '1 year',     description: 'Persists the Hotjar User ID unique to the site, used for heatmaps and recordings.' },
                { name: '_hjSession_*',     provider: 'Hotjar', category: 'statistics', duration: '30 minutes', description: 'Holds current session data so subsequent requests are attributed to the same session.' },
                { name: '_hjAbsoluteSessionInProgress', provider: 'Hotjar', category: 'statistics', duration: '30 minutes', description: 'Detects the first pageview session of a user, used by Hotjar for sampling.' },
                { name: '_hjIncludedInSessionSample_*', provider: 'Hotjar', category: 'statistics', duration: '2 minutes', description: 'Determines if a user is included in the current session recording sample.' },
            ]
        },
        {
            id: 'clarity',
            name: 'Microsoft Clarity',
            icon: '🔍',
            color: '#5c2d91',
            cookies: [
                { name: '_clck',  provider: 'Microsoft Clarity', category: 'statistics', duration: '1 year', description: 'Persists the Clarity User ID and preferences for heatmap and session replay.' },
                { name: '_clsk',  provider: 'Microsoft Clarity', category: 'statistics', duration: '1 day',  description: 'Connects multiple page views by a user into a single Clarity session.' },
                { name: 'CLID',   provider: 'Microsoft Clarity', category: 'statistics', duration: '1 year', description: 'Identifies the first-time Clarity saw this user on any Clarity-enabled site.' },
                { name: 'ANONCHK',provider: 'Microsoft Clarity', category: 'statistics', duration: 'Session', description: 'Used by Clarity to determine if MUID cookie can be synced.' },
                { name: 'MR',     provider: 'Microsoft Clarity', category: 'statistics', duration: '7 days',  description: 'Used by Microsoft to collect analytics data for statistical purposes.' },
                { name: 'SM',     provider: 'Microsoft Clarity', category: 'statistics', duration: 'Session',  description: 'Microsoft Clarity session marker cookie.' },
            ]
        },
        {
            id: 'hubspot',
            name: 'HubSpot',
            icon: '🧡',
            color: '#ff7a59',
            cookies: [
                { name: '__hstc',     provider: 'HubSpot', category: 'marketing', duration: '13 months',  description: 'Main HubSpot tracking cookie for visitor identification across sessions.' },
                { name: 'hubspotutk', provider: 'HubSpot', category: 'marketing', duration: '13 months',  description: 'Keeps track of visitor identity and is passed to HubSpot on form submission.' },
                { name: '__hssc',     provider: 'HubSpot', category: 'marketing', duration: '30 minutes', description: 'Keeps track of sessions so HubSpot can increment the session number.' },
                { name: '__hssrc',    provider: 'HubSpot', category: 'marketing', duration: 'Session',    description: 'Used to determine if the visitor has restarted their browser.' },
                { name: 'messagesUtk',provider: 'HubSpot', category: 'marketing', duration: '13 months',  description: 'Used by HubSpot chat to identify returning visitors for conversation history.' },
            ]
        },
        {
            id: 'matomo',
            name: 'Matomo (Piwik)',
            icon: '📈',
            color: '#3152a0',
            cookies: [
                { name: '_pk_id.*',  provider: 'Matomo', category: 'statistics', duration: '13 months',  description: 'Stores a unique visitor ID for Matomo analytics across sessions.' },
                { name: '_pk_ses.*', provider: 'Matomo', category: 'statistics', duration: '30 minutes', description: 'Short-lived session cookie used by Matomo to track page visits in a session.' },
                { name: '_pk_ref.*', provider: 'Matomo', category: 'statistics', duration: '6 months',   description: 'Stores the referrer URL initially used to visit the website.' },
            ]
        },
        {
            id: 'stripe',
            name: 'Stripe (Payments)',
            icon: '💳',
            color: '#635bff',
            cookies: [
                { name: '__stripe_mid', provider: 'Stripe', category: 'necessary', duration: '1 year',     description: 'Set by Stripe for fraud prevention and payment processing security.' },
                { name: '__stripe_sid', provider: 'Stripe', category: 'necessary', duration: '30 minutes', description: 'Set by Stripe to manage user sessions during payment flows.' },
                { name: 'm',           provider: 'Stripe', category: 'necessary', duration: '2 years',    description: 'Stripe device fingerprint for detecting fraudulent transactions.' },
            ]
        },
        {
            id: 'youtube',
            name: 'YouTube Embeds',
            icon: '▶️',
            color: '#ff0000',
            cookies: [
                { name: 'YSC',                provider: 'YouTube (Google)', category: 'marketing', duration: 'Session',  description: 'Registers a unique ID to keep statistics of what YouTube videos the user has seen.' },
                { name: 'VISITOR_INFO1_LIVE',  provider: 'YouTube (Google)', category: 'marketing', duration: '6 months', description: 'Tries to estimate user bandwidth on pages with integrated YouTube videos.' },
                { name: 'CONSENT',             provider: 'YouTube (Google)', category: 'necessary', duration: '2 years',  description: 'Stores the consent status of the user for YouTube cookies.' },
            ]
        },
        {
            id: 'intercom',
            name: 'Intercom',
            icon: '💬',
            color: '#286efa',
            cookies: [
                { name: 'intercom-id-*',      provider: 'Intercom', category: 'statistics', duration: '9 months', description: 'Allows visitors to see any conversations they have had on Intercom-powered sites.' },
                { name: 'intercom-session-*',  provider: 'Intercom', category: 'statistics', duration: '1 week',   description: 'Identifier for each unique browser session for Intercom messenger.' },
                { name: 'intercom-device-id-*',provider: 'Intercom', category: 'statistics', duration: '9 months', description: 'Device identifier for Intercom tracking across sessions.' },
            ]
        },
        {
            id: 'crisp',
            name: 'Crisp Chat',
            icon: '🗨️',
            color: '#4b61d1',
            cookies: [
                { name: 'crisp-client/*', provider: 'Crisp', category: 'necessary', duration: '6 months', description: 'Identifies the visitor for Crisp live chat, preserves conversation history.' },
            ]
        },
        {
            id: 'cloudflare',
            name: 'Cloudflare',
            icon: '☁️',
            color: '#f48120',
            cookies: [
                { name: '__cf_bm',     provider: 'Cloudflare', category: 'necessary', duration: '30 minutes', description: 'Bot management cookie that distinguishes humans from automated bots.' },
                { name: 'cf_clearance',provider: 'Cloudflare', category: 'necessary', duration: '30 minutes', description: 'Set after a visitor successfully completes a Cloudflare security challenge.' },
            ]
        },
        {
            id: 'recaptcha',
            name: 'Google reCAPTCHA',
            icon: '🤖',
            color: '#4285f4',
            cookies: [
                { name: '_GRECAPTCHA', provider: 'Google reCAPTCHA', category: 'necessary', duration: '6 months', description: 'Provides risk analysis and spam protection on forms using reCAPTCHA v3.' },
                { name: 'rc::a',       provider: 'Google reCAPTCHA', category: 'necessary', duration: 'Persistent', description: 'Used by reCAPTCHA to distinguish bots from humans.' },
                { name: 'rc::c',       provider: 'Google reCAPTCHA', category: 'necessary', duration: 'Session',    description: 'Used by reCAPTCHA for challenge-based bot detection.' },
            ]
        },
        {
            id: 'woocommerce',
            name: 'WooCommerce',
            icon: '🛒',
            color: '#96588a',
            cookies: [
                { name: 'woocommerce_cart_hash',      provider: 'WooCommerce', category: 'necessary', duration: 'Session', description: 'Stores a hash of the cart contents to detect when cart data changes.' },
                { name: 'woocommerce_items_in_cart',   provider: 'WooCommerce', category: 'necessary', duration: 'Session', description: 'Indicates whether there are items in the shopping cart.' },
                { name: 'wp_woocommerce_session_*',    provider: 'WooCommerce', category: 'necessary', duration: '2 days',  description: 'Contains a unique session identifier for the WooCommerce customer.' },
                { name: 'wc_cart_created',             provider: 'WooCommerce', category: 'necessary', duration: 'Session', description: 'Stores the timestamp of when the shopping cart was created.' },
            ]
        },
        {
            id: 'mailchimp',
            name: 'Mailchimp',
            icon: '📧',
            color: '#ffe01b',
            cookies: [
                { name: 'MCPopupClosed',     provider: 'Mailchimp', category: 'marketing', duration: '1 year',  description: 'Records whether the Mailchimp popup form has been closed by the visitor.' },
                { name: 'MCPopupSubscribed', provider: 'Mailchimp', category: 'marketing', duration: '1 year',  description: 'Records whether the visitor has subscribed via the Mailchimp popup form.' },
            ]
        },
        {
            id: 'criteo',
            name: 'Criteo',
            icon: '🎯',
            color: '#f27922',
            cookies: [
                { name: 'cto_bundle',  provider: 'Criteo', category: 'marketing', duration: '13 months', description: 'Criteo cookie bundle for retargeting and personalized advertising.' },
                { name: 'cto_tld_test',provider: 'Criteo', category: 'marketing', duration: 'Session',   description: 'Used by Criteo to check if cookies can be set on the top-level domain.' },
                { name: 'criteo_write_test', provider: 'Criteo', category: 'marketing', duration: '1 hour', description: 'Tests whether Criteo cookies can be written to the browser.' },
            ]
        },
    ];

    // ---- Render prebuilt grid ----

    function renderPrebuiltGrid() {
        var grid = document.getElementById('pcc-prebuilt-grid');
        if (!grid) return;

        grid.innerHTML = '';

        PREBUILT.forEach(function (platform) {
            var alreadyCount = 0;
            platform.cookies.forEach(function (c) {
                if (cookieExists(c.name)) alreadyCount++;
            });

            var allAdded = alreadyCount > 0 && alreadyCount === platform.cookies.length;
            var someAdded = alreadyCount > 0 && alreadyCount < platform.cookies.length;

            var card = document.createElement('div');
            card.className = 'pcc-prebuilt-card' + ((allAdded || someAdded) ? ' pcc-prebuilt-added' : '');
            card.innerHTML =
                '<div class="pcc-prebuilt-top">' +
                    '<span class="pcc-prebuilt-icon">' + platform.icon + '</span>' +
                    '<strong>' + platform.name + '</strong>' +
                '</div>' +
                '<span class="pcc-prebuilt-count">' + platform.cookies.length + ' cookies</span>' +
                (allAdded
                    ? '<span class="pcc-prebuilt-status">&#10003; Added</span>'
                    : someAdded
                        ? '<span class="pcc-prebuilt-status">&#10003; ' + alreadyCount + '/' + platform.cookies.length + ' Added</span>'
                        : '<button type="button" class="pcc-prebuilt-btn" data-platform="' + platform.id + '">+ Add All</button>'
                );
            grid.appendChild(card);
        });

        // Bind add buttons
        grid.querySelectorAll('.pcc-prebuilt-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var pid = this.dataset.platform;
                var platform = PREBUILT.find(function (p) { return p.id === pid; });
                if (!platform) return;

                var added = 0;
                platform.cookies.forEach(function (c) {
                    if (!cookieExists(c.name)) {
                        cookies.push({
                            name: c.name,
                            provider: c.provider,
                            category: c.category,
                            duration: c.duration,
                            description: c.description
                        });
                        added++;
                    }
                });

                renderTable();
                renderPrebuiltGrid();

                if (added > 0) {
                    this.textContent = '✓ ' + added + ' added!';
                    this.disabled = true;
                }
            });
        });
    }

    function cookieExists(name) {
        var lower = name.toLowerCase();
        return cookies.some(function (c) { return c.name.toLowerCase() === lower; });
    }

    // ---- Render cookie table ----

    function renderTable() {
        var tbody = document.getElementById('pcc-cookie-tbody');
        if (!tbody) return;

        tbody.innerHTML = '';

        if (!cookies.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999;padding:24px;">No cookies found yet. Click "Scan Website for Cookies" to start.</td></tr>';
            return;
        }

        cookies.forEach(function (c, i) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td><input type="text" class="pcc-input" data-idx="' + i + '" data-field="name" value="' + esc(c.name) + '"></td>' +
                '<td><input type="text" class="pcc-input" data-idx="' + i + '" data-field="provider" value="' + esc(c.provider) + '"></td>' +
                '<td><select class="pcc-input" data-idx="' + i + '" data-field="category">' +
                    '<option value="necessary"' + (c.category === 'necessary' ? ' selected' : '') + '>Necessary</option>' +
                    '<option value="statistics"' + (c.category === 'statistics' ? ' selected' : '') + '>Statistics</option>' +
                    '<option value="marketing"' + (c.category === 'marketing' ? ' selected' : '') + '>Marketing</option>' +
                '</select></td>' +
                '<td><input type="text" class="pcc-input" data-idx="' + i + '" data-field="duration" value="' + esc(c.duration) + '"></td>' +
                '<td><input type="text" class="pcc-input pcc-input-wide" data-idx="' + i + '" data-field="description" value="' + esc(c.description) + '"></td>' +
                '<td><button type="button" class="button pcc-delete-cookie" data-idx="' + i + '" title="Remove">&times;</button></td>';
            tbody.appendChild(tr);
        });

        // Bind inline edits
        tbody.querySelectorAll('.pcc-input').forEach(function (el) {
            el.addEventListener('change', function () {
                var idx = parseInt(this.dataset.idx, 10);
                var field = this.dataset.field;
                cookies[idx][field] = this.value;
            });
        });

        // Bind delete
        tbody.querySelectorAll('.pcc-delete-cookie').forEach(function (el) {
            el.addEventListener('click', function () {
                var idx = parseInt(this.dataset.idx, 10);
                cookies.splice(idx, 1);
                renderTable();
            });
        });
    }

    function esc(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    // ---- Initial render ----
    renderTable();
    renderPrebuiltGrid();

    // ---- Scan button ----
    var scanBtn    = document.getElementById('pcc-start-scan');
    var progressEl = document.getElementById('pcc-scan-progress');
    var statusEl   = document.getElementById('pcc-scan-status');
    var fillEl     = document.getElementById('pcc-progress-fill');

    if (scanBtn) {
        scanBtn.addEventListener('click', function () {
            scanBtn.disabled = true;
            scanBtn.textContent = 'Scanning...';
            progressEl.style.display = 'block';
            statusEl.textContent = 'Scanning up to 100 pages... This may take a minute.';
            fillEl.style.width = '0%';

            // Animate progress bar (fake progress since we can't track server-side)
            var progress = 0;
            var interval = setInterval(function () {
                progress += Math.random() * 8;
                if (progress > 90) progress = 90;
                fillEl.style.width = progress + '%';
            }, 500);

            var data = new FormData();
            data.append('action', 'pcc_run_scan');
            data.append('nonce', NONCE);

            fetch(AJAX_URL, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    clearInterval(interval);
                    fillEl.style.width = '100%';

                    if (res.success) {
                        cookies = res.data.cookies || [];
                        statusEl.textContent = 'Done! Found ' + cookies.length + ' cookies across ' + res.data.pages_scanned + ' pages.';
                        renderTable();
                        renderPrebuiltGrid();

                        // Update meta info
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        statusEl.textContent = 'Scan failed: ' + (res.data || 'Unknown error');
                    }

                    scanBtn.disabled = false;
                    scanBtn.innerHTML = '<span class="dashicons dashicons-search" style="margin-top:4px;margin-right:4px;"></span> Scan Website for Cookies';
                })
                .catch(function (err) {
                    clearInterval(interval);
                    statusEl.textContent = 'Scan error: ' + err.message;
                    scanBtn.disabled = false;
                    scanBtn.innerHTML = '<span class="dashicons dashicons-search" style="margin-top:4px;margin-right:4px;"></span> Scan Website for Cookies';
                });
        });
    }

    // ---- Add cookie ----
    var addBtn = document.getElementById('pcc-add-cookie');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            cookies.push({
                name: '',
                provider: '',
                category: 'necessary',
                duration: 'Session',
                description: ''
            });
            renderTable();

            // Scroll to bottom and focus
            var tbody = document.getElementById('pcc-cookie-tbody');
            var lastRow = tbody.lastElementChild;
            if (lastRow) {
                lastRow.scrollIntoView({ behavior: 'smooth' });
                var firstInput = lastRow.querySelector('input');
                if (firstInput) firstInput.focus();
            }
        });
    }

    // ---- Save cookie list ----
    var saveBtn = document.getElementById('pcc-save-cookies');
    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            // Filter out cookies with no name
            var clean = cookies.filter(function (c) { return c.name && c.name.trim(); });

            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            var data = new FormData();
            data.append('action', 'pcc_save_cookies');
            data.append('nonce', NONCE);
            data.append('cookies', JSON.stringify(clean));

            fetch(AJAX_URL, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        cookies = clean;
                        renderTable();
                        saveBtn.textContent = 'Saved!';
                        setTimeout(function () {
                            saveBtn.textContent = 'Save Cookie List';
                            saveBtn.disabled = false;
                        }, 1500);
                    } else {
                        alert('Save failed: ' + (res.data || 'Unknown error'));
                        saveBtn.textContent = 'Save Cookie List';
                        saveBtn.disabled = false;
                    }
                })
                .catch(function (err) {
                    alert('Save error: ' + err.message);
                    saveBtn.textContent = 'Save Cookie List';
                    saveBtn.disabled = false;
                });
        });
    }

})();
