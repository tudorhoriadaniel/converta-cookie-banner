/**
 * Converta Cookie Banner – Translations Admin
 */
(function () {
    'use strict';

    var AJAX_URL = pccTrans.ajaxUrl;
    var NONCE    = pccTrans.nonce;
    var data     = JSON.parse(JSON.stringify(pccTrans.current));

    // ── Language tabs ──

    document.querySelectorAll('.pcc-lang-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.pcc-lang-tab').forEach(function (t) { t.classList.remove('pcc-lang-tab-active'); });
            document.querySelectorAll('.pcc-lang-panel').forEach(function (p) { p.style.display = 'none'; });
            this.classList.add('pcc-lang-tab-active');
            document.getElementById('pcc-lang-' + this.dataset.lang).style.display = '';
        });
    });

    // ── Detection method radio ──

    document.querySelectorAll('input[name="pcc_detection"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            data.detection_method = this.value;
            document.querySelectorAll('.pcc-trans-method').forEach(function (m) { m.classList.remove('pcc-method-active'); });
            this.closest('.pcc-trans-method').classList.add('pcc-method-active');
        });
    });

    // ── Default language ──

    var defaultSelect = document.getElementById('pcc-default-lang');
    if (defaultSelect) {
        defaultSelect.addEventListener('change', function () {
            data.default_lang = this.value;
        });
    }

    // ── Collect all string inputs on save ──

    function collectStrings() {
        document.querySelectorAll('.pcc-trans-input').forEach(function (el) {
            var lang = el.dataset.lang;
            var key  = el.dataset.key;
            if (!data.strings[lang]) data.strings[lang] = {};
            data.strings[lang][key] = el.value;
        });
    }

    // ── Save ──

    document.getElementById('pcc-save-translations').addEventListener('click', function () {
        var btn = this;
        btn.disabled = true;
        btn.textContent = 'Saving...';

        collectStrings();

        var fd = new FormData();
        fd.append('action', 'pcc_save_translations');
        fd.append('nonce', NONCE);
        fd.append('translations', JSON.stringify(data));

        fetch(AJAX_URL, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    btn.textContent = 'Saved!';
                    setTimeout(function () { btn.disabled = false; btn.textContent = 'Save Translations'; }, 1500);
                } else {
                    alert('Save failed: ' + (res.data || 'Unknown error'));
                    btn.disabled = false;
                    btn.textContent = 'Save Translations';
                }
            })
            .catch(function (err) {
                alert('Error: ' + err.message);
                btn.disabled = false;
                btn.textContent = 'Save Translations';
            });
    });

    // ── Reset ──

    document.getElementById('pcc-reset-translations').addEventListener('click', function () {
        if (!confirm('Reset all translations to defaults?')) return;

        var defaults = pccTrans.defaults;
        data = JSON.parse(JSON.stringify(defaults));

        // Update detection method radios
        document.querySelectorAll('input[name="pcc_detection"]').forEach(function (r) {
            r.checked = (r.value === defaults.detection_method);
            var m = r.closest('.pcc-trans-method');
            m.classList.toggle('pcc-method-active', r.checked);
        });

        // Update default lang
        if (defaultSelect) defaultSelect.value = defaults.default_lang;

        // Update all text fields
        document.querySelectorAll('.pcc-trans-input').forEach(function (el) {
            var lang = el.dataset.lang;
            var key  = el.dataset.key;
            var val  = defaults.strings[lang] && defaults.strings[lang][key] ? defaults.strings[lang][key] : '';
            if (el.tagName === 'TEXTAREA') {
                el.value = val;
            } else {
                el.value = val;
            }
        });
    });

})();
