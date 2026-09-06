/**
 * Converta Cookie Banner – Banner Design Customizer
 */
(function ($) {
    'use strict';

    var design   = $.extend({}, pccDesign.current);
    var AJAX_URL = pccDesign.ajaxUrl;
    var NONCE    = pccDesign.nonce;

    // ── Initialize WP Color Pickers ──

    $('.pcc-color-picker').each(function () {
        var $input = $(this);
        var key = $input.data('key');

        $input.wpColorPicker({
            change: function (event, ui) {
                design[key] = ui.color.toString();
                updatePreview();
            },
            clear: function () {
                design[key] = pccDesign.defaults[key];
                updatePreview();
            }
        });
    });

    // ── All sliders (radius, font sizes) ──

    document.querySelectorAll('.pcc-slider').forEach(function (slider) {
        var key = slider.dataset.key;
        var valSpan = document.querySelector('.pcc-slider-val[data-for="' + key + '"]');

        slider.addEventListener('input', function () {
            design[key] = this.value;
            if (valSpan) valSpan.textContent = this.value + 'px';
            updatePreview();
        });
    });

    // ── Reopen method radios ──

    document.querySelectorAll('input[name="pcc_reopen_method"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            design.reopen_method = this.value;
            document.querySelectorAll('input[name="pcc_reopen_method"]').forEach(function (r) {
                var m = r.closest('.pcc-trans-method');
                if (m) m.classList.toggle('pcc-method-active', r.checked);
            });
        });
    });

    // ── Live Preview ──

    function updatePreview() {
        var banner = document.getElementById('pcc-preview-banner');
        if (!banner) return;

        var r = design.banner_radius + 'px';

        banner.style.background   = design.banner_bg;
        banner.style.borderColor  = design.banner_border;
        banner.style.borderRadius = r;

        var heading = document.getElementById('pv-heading');
        if (heading) {
            heading.style.color    = design.heading_color;
            heading.style.fontSize = design.heading_font_size + 'px';
        }

        var text = document.getElementById('pv-text');
        if (text) {
            text.style.color    = design.text_color;
            text.style.fontSize = design.text_font_size + 'px';
        }

        var tag = document.getElementById('pv-tag');
        if (tag) {
            tag.style.background  = design.tag_bg;
            tag.style.borderColor = design.tag_border;
            tag.style.color       = design.tag_text;
        }

        var accept = document.getElementById('pv-accept');
        if (accept) {
            accept.style.background = design.btn_accept_bg;
            accept.style.color      = design.btn_accept_text;
            accept.onmouseenter = function() { this.style.background = design.btn_accept_hover; };
            accept.onmouseleave = function() { this.style.background = design.btn_accept_bg; };
        }

        var reject = document.getElementById('pv-reject');
        if (reject) {
            reject.style.background = design.btn_reject_bg;
            reject.style.color      = design.btn_reject_text;
            reject.onmouseenter = function() { this.style.background = design.btn_reject_hover; };
            reject.onmouseleave = function() { this.style.background = design.btn_reject_bg; };
        }

        var prefs = document.getElementById('pv-prefs');
        if (prefs) {
            prefs.style.background = design.btn_prefs_bg;
            prefs.style.color      = design.btn_prefs_text;
            prefs.onmouseenter = function() { this.style.background = design.btn_prefs_hover; };
            prefs.onmouseleave = function() { this.style.background = design.btn_prefs_bg; };
        }
    }

    // Initial preview
    updatePreview();

    // ── Save ──

    $('#pcc-save-design').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');

        $.post(AJAX_URL, {
            action: 'pcc_save_design',
            nonce: NONCE,
            design: JSON.stringify(design)
        }, function (res) {
            if (res.success) {
                $btn.text('Saved!');
                setTimeout(function () {
                    $btn.prop('disabled', false).text('Save Design');
                }, 1500);
            } else {
                alert('Save failed: ' + (res.data || 'Unknown error'));
                $btn.prop('disabled', false).text('Save Design');
            }
        }).fail(function () {
            alert('Save error');
            $btn.prop('disabled', false).text('Save Design');
        });
    });

    // ── Reset ──

    $('#pcc-reset-design').on('click', function () {
        if (!confirm('Reset all banner design to defaults?')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('Resetting...');

        $.post(AJAX_URL, {
            action: 'pcc_reset_design',
            nonce: NONCE
        }, function (res) {
            if (res.success) {
                design = res.data;
                // Update all color pickers
                $('.pcc-color-picker').each(function () {
                    var key = $(this).data('key');
                    if (design[key] !== undefined) {
                        $(this).wpColorPicker('color', design[key]);
                    }
                });
                // Update reopen method radios
                document.querySelectorAll('input[name="pcc_reopen_method"]').forEach(function (r) {
                    r.checked = (r.value === design.reopen_method);
                    var m = r.closest('.pcc-trans-method');
                    if (m) m.classList.toggle('pcc-method-active', r.checked);
                });
                // Update all sliders
                document.querySelectorAll('.pcc-slider').forEach(function (sl) {
                    var key = sl.dataset.key;
                    if (design[key] !== undefined) {
                        sl.value = design[key];
                        var valSpan = document.querySelector('.pcc-slider-val[data-for="' + key + '"]');
                        if (valSpan) valSpan.textContent = design[key] + 'px';
                    }
                });
                updatePreview();
                $btn.text('Reset!');
                setTimeout(function () {
                    $btn.prop('disabled', false).text('Reset to Defaults');
                }, 1200);
            }
        });
    });

})(jQuery);
