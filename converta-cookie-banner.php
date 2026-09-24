<?php
/**
 * Plugin Name: Converta Cookie Banner
 * Plugin URI: https://converta.ro
 * Description: GDPR/ePrivacy cookie consent banner with Google Consent Mode v2, cookie scanner, and admin stats dashboard.
 * Version: 2.4.0
 * Author: Converta
 * Author URI: https://converta.ro
 * License: GPL v2 or later
 * Text Domain: procab-cookie-consent
 * Update URI: https://github.com/tudorhoriadaniel/converta-cookie-banner
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PCC_VERSION', '2.4.0' );
define( 'PCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PCC_COOKIE_NAME', 'procab_cookie_consent' );
define( 'PCC_COOKIE_EXPIRY', 365 );
define( 'PCC_GITHUB_REPO', 'tudorhoriadaniel/converta-cookie-banner' );

require_once PCC_PLUGIN_DIR . 'includes/class-cookie-scanner.php';

// =========================================================================
//  DATABASE: Create table on activation
// =========================================================================

register_activation_hook( __FILE__, 'pcc_activate' );

function pcc_activate() {
    pcc_create_table();
    pcc_auto_detect_language();
}

/**
 * Auto-detect WP locale on activation and set default language + detection method.
 */
function pcc_auto_detect_language() {
    // Only set defaults if translations haven't been saved yet.
    $existing = get_option( 'pcc_translations' );
    if ( $existing ) {
        return;
    }

    $locale    = get_locale(); // e.g. 'ro_RO', 'fr_FR', 'de_DE'
    $lang_code = strtolower( substr( $locale, 0, 2 ) );
    $supported = array( 'en', 'fr', 'de', 'it', 'ro', 'es' );

    if ( in_array( $lang_code, $supported, true ) ) {
        $defaults = pcc_translations_defaults();
        $defaults['default_lang']     = $lang_code;
        $defaults['detection_method'] = 'html_lang';
        update_option( 'pcc_translations', $defaults );
    }
}

function pcc_create_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'pcc_consent_log';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        visitor_hash VARCHAR(64) NOT NULL,
        action VARCHAR(20) NOT NULL,
        necessary TINYINT(1) NOT NULL DEFAULT 1,
        statistics TINYINT(1) NOT NULL DEFAULT 0,
        marketing TINYINT(1) NOT NULL DEFAULT 0,
        ip_country VARCHAR(5) DEFAULT '',
        user_agent TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_action (action),
        KEY idx_created (created_at)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );

    update_option( 'pcc_db_version', '1.2' );
}

// =========================================================================
//  AJAX: Log consent from frontend
// =========================================================================

add_action( 'wp_ajax_pcc_log_consent', 'pcc_log_consent' );
add_action( 'wp_ajax_nopriv_pcc_log_consent', 'pcc_log_consent' );

function pcc_log_consent() {
    check_ajax_referer( 'pcc_nonce', 'nonce' );

    $action     = sanitize_text_field( wp_unslash( $_POST['consent_action'] ?? '' ) );
    $statistics = absint( $_POST['statistics'] ?? 0 );
    $marketing  = absint( $_POST['marketing'] ?? 0 );

    if ( ! in_array( $action, array( 'accept_all', 'reject_all', 'save_preferences' ), true ) ) {
        wp_send_json_error( 'Invalid action' );
    }

    $raw_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' ) );
    $ua     = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
    $hash   = hash( 'sha256', $raw_ip . '|' . $ua . '|' . wp_salt( 'auth' ) );

    global $wpdb;
    $table = $wpdb->prefix . 'pcc_consent_log';

    $wpdb->insert( $table, array(
        'visitor_hash' => $hash,
        'action'       => $action,
        'necessary'    => 1,
        'statistics'   => $statistics,
        'marketing'    => $marketing,
        'user_agent'   => substr( $ua, 0, 500 ),
        'created_at'   => current_time( 'mysql' ),
    ), array( '%s', '%s', '%d', '%d', '%d', '%s', '%s' ) );

    wp_send_json_success();
}

// =========================================================================
//  AJAX: Run cookie scanner (admin only)
// =========================================================================

add_action( 'wp_ajax_pcc_run_scan', 'pcc_ajax_run_scan' );

function pcc_ajax_run_scan() {
    check_ajax_referer( 'pcc_scanner_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $results = PCC_Cookie_Scanner::scan( 100 );
    wp_send_json_success( $results );
}

// =========================================================================
//  AJAX: Save manually edited cookie list
// =========================================================================

add_action( 'wp_ajax_pcc_save_cookies', 'pcc_ajax_save_cookies' );

function pcc_ajax_save_cookies() {
    check_ajax_referer( 'pcc_scanner_nonce', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $raw = wp_unslash( $_POST['cookies'] ?? '[]' );
    $cookies = json_decode( $raw, true );

    if ( ! is_array( $cookies ) ) {
        wp_send_json_error( 'Invalid data' );
    }

    // Sanitize each cookie
    $clean = array();
    foreach ( $cookies as $c ) {
        $clean[] = array(
            'name'        => sanitize_text_field( $c['name'] ?? '' ),
            'provider'    => sanitize_text_field( $c['provider'] ?? '' ),
            'category'    => sanitize_text_field( $c['category'] ?? 'necessary' ),
            'duration'    => sanitize_text_field( $c['duration'] ?? 'Session' ),
            'description' => sanitize_text_field( $c['description'] ?? '' ),
        );
    }

    $scan = get_option( 'pcc_scan_results', array() );
    $scan['cookies'] = $clean;
    update_option( 'pcc_scan_results', $scan );

    wp_send_json_success();
}

// =========================================================================
//  TRANSLATIONS: Defaults, detection, save
// =========================================================================

function pcc_translations_defaults() {
    return array(
        'detection_method' => 'subfolder', // 'subfolder' or 'html_lang'
        'default_lang'     => 'en',
        'strings' => array(
            'en' => array(
                'tag'            => 'Privacy & Cookies',
                'heading'        => 'We respect your privacy',
                'body'           => 'We use cookies to improve your experience on our site, analyze traffic, and personalize content. You can choose which cookies you accept.',
                'btn_accept'     => 'Accept All',
                'btn_reject'     => 'Reject All',
                'btn_customize'  => 'Customize',
                'pref_heading'   => 'Cookie Preferences',
                'pref_intro'     => 'This site uses cookies for a better experience. Choose the categories you accept. Necessary cookies cannot be disabled.',
                'cat_necessary'  => 'Necessary',
                'cat_necessary_desc' => 'Essential cookies for the correct functioning of the site. They include WordPress sessions and consent preferences. Cannot be deactivated.',
                'cat_analytics'  => 'Analytics',
                'cat_analytics_desc' => 'Help us understand how you interact with the site. Includes Google Analytics cookies (_ga, _gid) and similar analytics services.',
                'cat_marketing'  => 'Marketing',
                'cat_marketing_desc' => 'Cookies for personalized advertising. Includes Google Ads (_gcl_*), Facebook Ads (_fbp, _fbc), and LinkedIn Ads.',
                'always_active'  => 'Always active',
                'btn_save'       => 'Save Preferences',
                'btn_cancel'     => 'Cancel',
                'footer_link'    => 'Cookie Settings',
            ),
            'fr' => array(
                'tag'            => 'Confidentialit&eacute; & Cookies',
                'heading'        => 'Nous respectons votre vie priv&eacute;e',
                'body'           => 'Nous utilisons des cookies pour am&eacute;liorer votre exp&eacute;rience, analyser le trafic et personnaliser le contenu. Vous pouvez choisir les cookies que vous acceptez.',
                'btn_accept'     => 'Tout accepter',
                'btn_reject'     => 'Tout refuser',
                'btn_customize'  => 'Personnaliser',
                'pref_heading'   => 'Pr&eacute;f&eacute;rences de cookies',
                'pref_intro'     => 'Ce site utilise des cookies pour une meilleure exp&eacute;rience. Choisissez les cat&eacute;gories que vous acceptez. Les cookies n&eacute;cessaires ne peuvent pas &ecirc;tre d&eacute;sactiv&eacute;s.',
                'cat_necessary'  => 'N&eacute;cessaires',
                'cat_necessary_desc' => 'Cookies essentiels au bon fonctionnement du site. Ils incluent les sessions WordPress et les pr&eacute;f&eacute;rences de consentement.',
                'cat_analytics'  => 'Analytiques',
                'cat_analytics_desc' => 'Nous aident &agrave; comprendre comment vous interagissez avec le site. Incluent Google Analytics (_ga, _gid) et services similaires.',
                'cat_marketing'  => 'Marketing',
                'cat_marketing_desc' => 'Cookies pour la publicit&eacute; personnalis&eacute;e. Incluent Google Ads (_gcl_*), Facebook Ads (_fbp, _fbc) et LinkedIn Ads.',
                'always_active'  => 'Toujours actif',
                'btn_save'       => 'Enregistrer',
                'btn_cancel'     => 'Annuler',
                'footer_link'    => 'Param&egrave;tres des cookies',
            ),
            'de' => array(
                'tag'            => 'Datenschutz & Cookies',
                'heading'        => 'Wir respektieren Ihre Privatsph&auml;re',
                'body'           => 'Wir verwenden Cookies, um Ihre Erfahrung zu verbessern, den Datenverkehr zu analysieren und Inhalte zu personalisieren. Sie k&ouml;nnen w&auml;hlen, welche Cookies Sie akzeptieren.',
                'btn_accept'     => 'Alle akzeptieren',
                'btn_reject'     => 'Alle ablehnen',
                'btn_customize'  => 'Anpassen',
                'pref_heading'   => 'Cookie-Einstellungen',
                'pref_intro'     => 'Diese Website verwendet Cookies f&uuml;r ein besseres Erlebnis. W&auml;hlen Sie die Kategorien, die Sie akzeptieren. Notwendige Cookies k&ouml;nnen nicht deaktiviert werden.',
                'cat_necessary'  => 'Notwendig',
                'cat_necessary_desc' => 'Wesentliche Cookies f&uuml;r das korrekte Funktionieren der Website. Sie umfassen WordPress-Sitzungen und Einwilligungspr&auml;ferenzen.',
                'cat_analytics'  => 'Analytik',
                'cat_analytics_desc' => 'Helfen uns zu verstehen, wie Sie mit der Website interagieren. Beinhalten Google Analytics (_ga, _gid) und &auml;hnliche Dienste.',
                'cat_marketing'  => 'Marketing',
                'cat_marketing_desc' => 'Cookies f&uuml;r personalisierte Werbung. Beinhalten Google Ads (_gcl_*), Facebook Ads (_fbp, _fbc) und LinkedIn Ads.',
                'always_active'  => 'Immer aktiv',
                'btn_save'       => 'Einstellungen speichern',
                'btn_cancel'     => 'Abbrechen',
                'footer_link'    => 'Cookie-Einstellungen',
            ),
            'it' => array(
                'tag'            => 'Privacy & Cookie',
                'heading'        => 'Rispettiamo la tua privacy',
                'body'           => 'Utilizziamo i cookie per migliorare la tua esperienza, analizzare il traffico e personalizzare i contenuti. Puoi scegliere quali cookie accettare.',
                'btn_accept'     => 'Accetta tutti',
                'btn_reject'     => 'Rifiuta tutti',
                'btn_customize'  => 'Personalizza',
                'pref_heading'   => 'Preferenze Cookie',
                'pref_intro'     => 'Questo sito utilizza i cookie per una migliore esperienza. Scegli le categorie che accetti. I cookie necessari non possono essere disattivati.',
                'cat_necessary'  => 'Necessari',
                'cat_necessary_desc' => 'Cookie essenziali per il corretto funzionamento del sito. Includono sessioni WordPress e preferenze di consenso. Non possono essere disattivati.',
                'cat_analytics'  => 'Analitici',
                'cat_analytics_desc' => 'Ci aiutano a capire come interagisci con il sito. Includono Google Analytics (_ga, _gid) e servizi simili.',
                'cat_marketing'  => 'Marketing',
                'cat_marketing_desc' => 'Cookie per la pubblicit&agrave; personalizzata. Includono Google Ads (_gcl_*), Facebook Ads (_fbp, _fbc) e LinkedIn Ads.',
                'always_active'  => 'Sempre attivo',
                'btn_save'       => 'Salva preferenze',
                'btn_cancel'     => 'Annulla',
                'footer_link'    => 'Impostazioni cookie',
            ),
            'ro' => array(
                'tag'            => 'Confiden&#539;ialitate & Cookie-uri',
                'heading'        => 'Respect&#259;m confiden&#539;ialitatea ta',
                'body'           => 'Folosim cookie-uri pentru a-&#539;i &icirc;mbun&#259;t&#259;&#539;i experien&#539;a, a analiza traficul &#537;i a personaliza con&#539;inutul. Po&#539;i alege ce cookie-uri accep&#539;i.',
                'btn_accept'     => 'Accept&#259; toate',
                'btn_reject'     => 'Refuz&#259; toate',
                'btn_customize'  => 'Personalizeaz&#259;',
                'pref_heading'   => 'Set&#259;ri Cookie-uri',
                'pref_intro'     => 'Acest site folose&#537;te cookie-uri pentru o experien&#539;&#259; mai bun&#259;. Alege categoriile pe care le accep&#539;i. Cookie-urile necesare nu pot fi dezactivate.',
                'cat_necessary'  => 'Necesare',
                'cat_necessary_desc' => 'Cookie-uri esen&#539;iale pentru func&#539;ionarea corect&#259; a site-ului. Includ sesiuni WordPress &#537;i preferin&#539;ele de consim&#539;&#259;m&acirc;nt.',
                'cat_analytics'  => 'Analitice',
                'cat_analytics_desc' => 'Ne ajut&#259; s&#259; &icirc;n&#539;elegem cum interac&#539;ionezi cu site-ul. Includ Google Analytics (_ga, _gid) &#537;i servicii similare.',
                'cat_marketing'  => 'Marketing',
                'cat_marketing_desc' => 'Cookie-uri pentru publicitate personalizat&#259;. Includ Google Ads (_gcl_*), Facebook Ads (_fbp, _fbc) &#537;i LinkedIn Ads.',
                'always_active'  => '&Icirc;ntotdeauna activ',
                'btn_save'       => 'Salveaz&#259;',
                'btn_cancel'     => 'Anuleaz&#259;',
                'footer_link'    => 'Set&#259;ri cookie-uri',
            ),
            'es' => array(
                'tag'            => 'Privacidad & Cookies',
                'heading'        => 'Respetamos tu privacidad',
                'body'           => 'Utilizamos cookies para mejorar tu experiencia, analizar el tr&aacute;fico y personalizar el contenido. Puedes elegir qu&eacute; cookies aceptas.',
                'btn_accept'     => 'Aceptar todo',
                'btn_reject'     => 'Rechazar todo',
                'btn_customize'  => 'Personalizar',
                'pref_heading'   => 'Preferencias de Cookies',
                'pref_intro'     => 'Este sitio utiliza cookies para una mejor experiencia. Elige las categor&iacute;as que aceptas. Las cookies necesarias no se pueden desactivar.',
                'cat_necessary'  => 'Necesarias',
                'cat_necessary_desc' => 'Cookies esenciales para el correcto funcionamiento del sitio. Incluyen sesiones de WordPress y preferencias de consentimiento.',
                'cat_analytics'  => 'Anal&iacute;ticas',
                'cat_analytics_desc' => 'Nos ayudan a entender c&oacute;mo interact&uacute;as con el sitio. Incluyen Google Analytics (_ga, _gid) y servicios similares.',
                'cat_marketing'  => 'Marketing',
                'cat_marketing_desc' => 'Cookies para publicidad personalizada. Incluyen Google Ads (_gcl_*), Facebook Ads (_fbp, _fbc) y LinkedIn Ads.',
                'always_active'  => 'Siempre activo',
                'btn_save'       => 'Guardar preferencias',
                'btn_cancel'     => 'Cancelar',
                'footer_link'    => 'Configuraci&oacute;n de cookies',
            ),
        ),
    );
}

function pcc_get_translations() {
    $saved = get_option( 'pcc_translations', array() );
    $defaults = pcc_translations_defaults();
    $merged = wp_parse_args( $saved, $defaults );
    // Deep merge strings per language
    foreach ( array( 'en', 'fr', 'de', 'it', 'ro', 'es' ) as $lang ) {
        if ( ! isset( $merged['strings'][ $lang ] ) ) {
            $merged['strings'][ $lang ] = $defaults['strings'][ $lang ];
        } else {
            $merged['strings'][ $lang ] = wp_parse_args( $merged['strings'][ $lang ], $defaults['strings'][ $lang ] );
        }
    }
    return $merged;
}

/**
 * Detect current language based on configured method.
 */
/**
 * Detect current language.
 *
 * $path_override / $lang_override let the AJAX banner endpoint pass the
 * page's real path and <html lang> value, since admin-ajax.php requests
 * don't carry them.
 */
function pcc_detect_language( $path_override = null, $lang_override = null ) {
    $trans  = pcc_get_translations();
    $method = $trans['detection_method'];
    $default = $trans['default_lang'];

    if ( $method === 'subfolder' ) {
        $uri = null !== $path_override ? $path_override : ( $_SERVER['REQUEST_URI'] ?? '' );
        $path = trim( (string) parse_url( $uri, PHP_URL_PATH ), '/' );
        $segments = explode( '/', $path );
        $first = strtolower( $segments[0] ?? '' );
        if ( in_array( $first, array( 'en', 'fr', 'de', 'it', 'ro', 'es' ), true ) ) {
            return $first;
        }
    } elseif ( $method === 'html_lang' ) {
        $locale = null !== $lang_override ? $lang_override : get_bloginfo( 'language' );
        $short  = strtolower( substr( (string) $locale, 0, 2 ) );
        if ( in_array( $short, array( 'en', 'fr', 'de', 'it', 'ro', 'es' ), true ) ) {
            return $short;
        }
    }

    return $default;
}

/**
 * Get the translated strings for the current page.
 */
function pcc_get_current_strings( $path_override = null, $lang_override = null ) {
    $trans = pcc_get_translations();
    $lang  = pcc_detect_language( $path_override, $lang_override );
    return $trans['strings'][ $lang ] ?? $trans['strings']['en'];
}

// AJAX: Save translations
add_action( 'wp_ajax_pcc_save_translations', 'pcc_ajax_save_translations' );

function pcc_ajax_save_translations() {
    check_ajax_referer( 'pcc_translations_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $raw = wp_unslash( $_POST['translations'] ?? '{}' );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'Invalid data' );
    }

    $clean = array(
        'detection_method' => in_array( $data['detection_method'] ?? '', array( 'subfolder', 'html_lang' ) ) ? $data['detection_method'] : 'subfolder',
        'default_lang'     => in_array( $data['default_lang'] ?? '', array( 'en', 'fr', 'de', 'it', 'ro', 'es' ) ) ? $data['default_lang'] : 'en',
        'strings'          => array(),
    );

    foreach ( array( 'en', 'fr', 'de', 'it', 'ro', 'es' ) as $lang ) {
        $clean['strings'][ $lang ] = array();
        $defaults = pcc_translations_defaults()['strings'][ $lang ];
        foreach ( $defaults as $key => $default_val ) {
            $clean['strings'][ $lang ][ $key ] = isset( $data['strings'][ $lang ][ $key ] )
                ? sanitize_text_field( $data['strings'][ $lang ][ $key ] )
                : $default_val;
        }
    }

    update_option( 'pcc_translations', $clean );
    wp_send_json_success();
}

// =========================================================================
//  BANNER DESIGN: Defaults + save
// =========================================================================

function pcc_design_defaults() {
    return array(
        // Banner background & text
        'banner_bg'            => '#0b1426',
        'banner_border'        => 'rgba(56,130,200,0.2)',
        'banner_radius'        => '20',
        'heading_color'        => '#ffffff',
        'heading_font_size'    => '24',
        'text_color'           => '#7b8da6',
        'text_font_size'       => '15',
        // Preferences heading
        'prefs_heading_color'  => '#ffffff',
        'prefs_heading_size'   => '22',
        // Tag / badge
        'tag_bg'               => '#162950',
        'tag_border'           => 'rgba(59,184,160,0.3)',
        'tag_text'             => '#3bb8a0',
        // Accept button
        'btn_accept_bg'        => '#3bb8a0',
        'btn_accept_text'      => '#ffffff',
        'btn_accept_hover'     => '#2ea08a',
        // Reject button
        'btn_reject_bg'        => '#000000',
        'btn_reject_text'      => '#ffffff',
        'btn_reject_hover'     => '#999999',
        // Customize / Preferences button
        'btn_prefs_bg'         => '#000000',
        'btn_prefs_text'       => '#ffffff',
        'btn_prefs_hover'      => '#999999',
        // Save button
        'btn_save_bg'          => '#3bb8a0',
        'btn_save_text'        => '#ffffff',
        'btn_save_hover'       => '#2ea08a',
        // Cancel button
        'btn_cancel_bg'        => '#000000',
        'btn_cancel_text'      => '#ffffff',
        'btn_cancel_hover'     => '#1a1a1a',
        // Close button
        'close_btn_bg'         => 'rgba(255,255,255,0.04)',
        'close_btn_color'      => '#7b8da6',
        'close_btn_hover'      => '#e83e72',
        // Category cards
        'card_bg'              => '#111d38',
        'card_border'          => 'rgba(56,130,200,0.2)',
        // Toggle switch
        'toggle_on'            => '#3bb8a0',
        'toggle_off'           => 'rgba(255,255,255,0.1)',
        // Badge (always active / cookie count)
        'badge_bg'             => 'rgba(59,184,160,0.15)',
        'badge_text'           => '#3bb8a0',
        // Cookie table
        'table_code_bg'        => 'rgba(59,184,160,0.12)',
        'table_code_text'      => '#3bb8a0',
        // Expand / Details arrow button
        'expand_btn_bg'        => '#3bb8a0',
        'expand_btn_color'     => '#ffffff',
        // Reopen button
        'reopen_bg'            => '#3bb8a0',
        'reopen_text'          => '#ffffff',
        // Overlay
        'overlay_bg'           => 'rgba(5,10,25,0.65)',
        // How visitors reopen the banner: 'icon', 'footer_link', or 'both'
        'reopen_method'        => 'icon',
        // GTM handling: 'advanced' (default) = Google Tag Manager loads
        // immediately, Consent Mode signals control the tags inside it.
        // 'basic' = the GTM/gtag script itself is blocked until the visitor
        // grants statistics or marketing consent.
        'gtm_blocking'         => 'advanced',
    );
}

function pcc_get_design() {
    $saved = get_option( 'pcc_design', array() );
    return wp_parse_args( $saved, pcc_design_defaults() );
}

add_action( 'wp_ajax_pcc_save_design', 'pcc_ajax_save_design' );

function pcc_ajax_save_design() {
    check_ajax_referer( 'pcc_design_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $raw = wp_unslash( $_POST['design'] ?? '{}' );
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) {
        wp_send_json_error( 'Invalid data' );
    }

    $defaults = pcc_design_defaults();
    $clean = array();
    foreach ( $defaults as $key => $default ) {
        $clean[ $key ] = isset( $data[ $key ] ) ? sanitize_text_field( $data[ $key ] ) : $default;
    }

    if ( ! in_array( $clean['reopen_method'], array( 'icon', 'footer_link', 'both' ), true ) ) {
        $clean['reopen_method'] = 'icon';
    }

    if ( ! in_array( $clean['gtm_blocking'], array( 'advanced', 'basic' ), true ) ) {
        $clean['gtm_blocking'] = 'advanced';
    }

    update_option( 'pcc_design', $clean );
    wp_send_json_success();
}

add_action( 'wp_ajax_pcc_reset_design', 'pcc_ajax_reset_design' );

function pcc_ajax_reset_design() {
    check_ajax_referer( 'pcc_design_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    delete_option( 'pcc_design' );
    wp_send_json_success( pcc_design_defaults() );
}

// =========================================================================
//  CONSENT MODE v2: Consent default + update MUST run before GTM/gtag,
//  no matter how the container snippet was added (theme header.php,
//  another plugin, wp_head, wp_body_open, etc.).
//
//  Strategy:
//  1. Buffer the full page output (template_redirect, earliest priority)
//     and physically inject the consent script right after the opening
//     <head> tag, so it is the first script in the document.
//  2. Fallback: if output buffering could not start, print on wp_head at
//     the earliest possible priority.
//  A JS guard (window.__pccConsentInit) makes a double injection harmless.
// =========================================================================

/**
 * Build the consent default + update inline script.
 *
 * Built as a plain string (NOT via ob_start) because this also runs inside
 * an output-buffer callback, where buffering functions are not allowed.
 */
function pcc_get_consent_mode_script() {
    $cookie_name = esc_js( PCC_COOKIE_NAME );

    return <<<SCRIPT
<script data-pcc-consent="1">
(function(){
if (window.__pccConsentInit) { return; }
window.__pccConsentInit = true;

window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
window.gtag = window.gtag || gtag;

gtag('consent', 'default', {
    'ad_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied',
    'analytics_storage': 'denied',
    'functionality_storage': 'denied',
    'personalization_storage': 'denied',
    'security_storage': 'granted',
    'wait_for_update': 500
});

var name = '{$cookie_name}=';
var cookies = document.cookie.split(';');
for (var i = 0; i < cookies.length; i++) {
    var c = cookies[i].trim();
    if (c.indexOf(name) === 0) {
        try {
            var consent = JSON.parse(decodeURIComponent(c.substring(name.length)));
            gtag('consent', 'update', {
                'ad_storage': consent.marketing ? 'granted' : 'denied',
                'ad_user_data': consent.marketing ? 'granted' : 'denied',
                'ad_personalization': consent.marketing ? 'granted' : 'denied',
                'analytics_storage': consent.statistics ? 'granted' : 'denied',
                'functionality_storage': 'granted',
                'personalization_storage': consent.marketing ? 'granted' : 'denied',
                'security_storage': 'granted'
            });
        } catch(e) {}
        break;
    }
}
})();
</script>

SCRIPT;
}

/**
 * Whether the output buffer for head injection was successfully started.
 */
function pcc_consent_buffer_active( $set = null ) {
    static $active = false;
    if ( null !== $set ) {
        $active = (bool) $set;
    }
    return $active;
}

add_action( 'template_redirect', 'pcc_start_consent_buffer', -99999 );

function pcc_start_consent_buffer() {
    if ( is_admin() || is_feed() || is_robots() || is_embed() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }
    if ( ob_start( 'pcc_inject_consent_script' ) ) {
        pcc_consent_buffer_active( true );
    }
}

/**
 * Output-buffer callback: inject the consent script immediately after the
 * opening <head> tag so it executes before any GTM/gtag snippet, wherever
 * that snippet lives in the page source.
 */
function pcc_inject_consent_script( $html ) {
    // Not an HTML document (e.g. XML sitemap) — leave untouched.
    if ( false === stripos( $html, '<head' ) ) {
        return $html;
    }

    $script = pcc_get_consent_mode_script();

    // Already injected via the wp_head fallback? The JS guard would handle
    // it anyway, but avoid duplicating the markup.
    if ( false !== strpos( $html, 'data-pcc-consent="1"' ) ) {
        return $html;
    }

    if ( preg_match( '/<head\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE ) ) {
        $pos = $m[0][1] + strlen( $m[0][0] );
        $html = substr( $html, 0, $pos ) . "\n" . $script . substr( $html, $pos );
    }

    // Basic Consent Mode (opt-in, NOT default): neutralize every
    // GTM/gtag <script> so it does not execute until the visitor grants
    // statistics or marketing consent; banner.js re-activates them.
    $d = pcc_get_design();
    if ( ( $d['gtm_blocking'] ?? 'advanced' ) === 'basic' ) {
        $html = pcc_neutralize_gtm_scripts( $html );
    }

    return $html;
}

/**
 * Turn every script that loads or references googletagmanager.com into an
 * inert <script type="text/plain" data-pcc-gtm="1"> so the browser does not
 * execute it. banner.js re-activates these scripts once the visitor grants
 * statistics or marketing consent (or on load for returning visitors who
 * already granted).
 */
function pcc_neutralize_gtm_scripts( $html ) {
    return preg_replace_callback(
        '#<script\b([^>]*)>(.*?)</script>#is',
        function ( $m ) {
            $attrs = $m[1];
            $body  = $m[2];

            if ( false === stripos( $attrs, 'googletagmanager.com' ) && false === stripos( $body, 'googletagmanager.com' ) ) {
                return $m[0];
            }
            // Never touch our own consent script or already-processed tags.
            if ( false !== stripos( $attrs, 'data-pcc-consent' ) || false !== stripos( $attrs, 'data-pcc-gtm' ) || false !== stripos( $attrs, 'text/plain' ) ) {
                return $m[0];
            }

            $attrs = preg_replace( '#\stype=("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $attrs );

            return '<script type="text/plain" data-pcc-gtm="1"' . $attrs . '>' . $body . '</script>';
        },
        $html
    );
}

// Fallback for setups where output buffering could not start.
add_action( 'wp_head', 'pcc_consent_mode_default', -99999 );

function pcc_consent_mode_default() {
    if ( pcc_consent_buffer_active() ) {
        return; // The buffer callback will inject at the top of <head>.
    }
    echo pcc_get_consent_mode_script(); // phpcs:ignore WordPress.Security.EscapeOutput
}

// =========================================================================
//  FRONTEND: Enqueue assets & render banner
// =========================================================================

add_action( 'wp_enqueue_scripts', 'pcc_enqueue_assets' );

function pcc_enqueue_assets() {
    // Ad-blocker hardening (Brave, uBlock, AdGuard): the plugin folder URL
    // contains "cookie-banner", which matches ad-block network filters, so
    // any external CSS/JS file from this plugin can be blocked and the
    // banner never appears. Therefore the frontend CSS and JS are INLINED
    // into the page — there is no asset URL to block.
    $frontend_css = (string) file_get_contents( PCC_PLUGIN_DIR . 'assets/css/banner.css' );
    $frontend_js  = (string) file_get_contents( PCC_PLUGIN_DIR . 'assets/js/banner.js' );

    wp_register_style( 'pcc-ui', false, array(), PCC_VERSION );
    wp_enqueue_style( 'pcc-ui' );
    wp_add_inline_style( 'pcc-ui', $frontend_css );

    wp_register_script( 'pcc-ui', false, array(), PCC_VERSION, true );
    wp_enqueue_script( 'pcc-ui' );
    wp_localize_script( 'pcc-ui', 'pccConfig', array(
        'cookieName'   => PCC_COOKIE_NAME,
        'cookieExpiry' => PCC_COOKIE_EXPIRY,
        'cookieDomain' => parse_url( home_url(), PHP_URL_HOST ),
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'pcc_nonce' ),
    ));
    wp_add_inline_script( 'pcc-ui', $frontend_js );

    // Inject custom design colors as inline CSS
    $d = pcc_get_design();
    $radius = absint( $d['banner_radius'] ) . 'px';
    $hfs = absint( $d['heading_font_size'] ?? 24 );
    $tfs = absint( $d['text_font_size'] ?? 15 );
    $phs = absint( $d['prefs_heading_size'] ?? 22 );
    $css = ":root{
        --pcc-bg:{$d['banner_bg']};--pcc-card:{$d['card_bg']};--pcc-card-border:{$d['card_border']};
        --pcc-text:{$d['text_color']};--pcc-text-muted:{$d['text_color']};--pcc-heading:{$d['heading_color']};
        --pcc-teal:{$d['btn_accept_bg']};--pcc-pink:{$d['btn_prefs_bg']};
        --pcc-badge-bg:{$d['badge_bg']};--pcc-badge-text:{$d['badge_text']};
        --pcc-tag-bg:{$d['tag_bg']};--pcc-tag-border:{$d['tag_border']};
        --pcc-radius:{$radius};--pcc-radius-sm:" . max( absint( $d['banner_radius'] ) - 6, 6 ) . "px;
    }
    .pcc-card{border-color:{$d['banner_border']} !important}
    .pcc-card-header h3{color:{$d['heading_color']} !important;font-size:{$hfs}px !important}
    .pcc-card-text p{color:{$d['text_color']} !important;font-size:{$tfs}px !important}
    .pcc-preferences-header h3{color:{$d['prefs_heading_color']} !important;font-size:{$phs}px !important}
    .pcc-preferences-intro{color:{$d['text_color']} !important;font-size:{$tfs}px !important}
    .pcc-category-desc{color:{$d['text_color']} !important}
    .pcc-card-tag{background:{$d['tag_bg']} !important;border-color:{$d['tag_border']} !important;color:{$d['tag_text']} !important}
    .pcc-btn.pcc-btn-accept{background:{$d['btn_accept_bg']} !important;color:{$d['btn_accept_text']} !important}
    .pcc-btn.pcc-btn-accept:hover{background:{$d['btn_accept_hover']} !important}
    .pcc-btn.pcc-btn-reject{background:{$d['btn_reject_bg']} !important;color:{$d['btn_reject_text']} !important}
    .pcc-btn.pcc-btn-reject:hover{background:{$d['btn_reject_hover']} !important}
    .pcc-btn.pcc-btn-preferences{background:{$d['btn_prefs_bg']} !important;color:{$d['btn_prefs_text']} !important}
    .pcc-btn.pcc-btn-preferences:hover{background:{$d['btn_prefs_hover']} !important}
    .pcc-btn.pcc-btn-save{background:{$d['btn_save_bg']} !important;color:{$d['btn_save_text']} !important}
    .pcc-btn.pcc-btn-save:hover{background:{$d['btn_save_hover']} !important}
    .pcc-btn.pcc-btn-cancel{background:{$d['btn_cancel_bg']} !important;color:{$d['btn_cancel_text']} !important}
    .pcc-btn.pcc-btn-cancel:hover{background:{$d['btn_cancel_hover']} !important}
    .pcc-toggle input:checked+.pcc-toggle-slider,.pcc-always-on{background:{$d['toggle_on']} !important}
    .pcc-toggle-slider{background:{$d['toggle_off']} !important}
    .pcc-ui-table code{background:{$d['table_code_bg']} !important;color:{$d['table_code_text']} !important}
    .pcc-reopen-btn{background:{$d['reopen_bg']} !important;color:{$d['reopen_text']} !important}
    .pcc-overlay{background:{$d['overlay_bg']} !important}
    .pcc-always-active,.pcc-ui-count{background:{$d['badge_bg']} !important;color:{$d['badge_text']} !important}
    .pcc-pref-icon{background:{$d['btn_accept_bg']} !important}
    .pcc-close-btn{background:{$d['close_btn_bg']} !important;color:{$d['close_btn_color']} !important;border-color:rgba(255,255,255,0.1) !important}
    .pcc-close-btn:hover{color:{$d['close_btn_hover']} !important;border-color:{$d['close_btn_hover']} !important}
    .pcc-expand-btn{background:{$d['expand_btn_bg']} !important;color:{$d['expand_btn_color']} !important}";
    wp_add_inline_style( 'pcc-ui', $css );
}

// NOTE: The banner markup is intentionally NOT rendered into the page HTML
// (SEO: its texts would appear as duplicate content on every page). It is
// fetched at runtime by banner.js via the AJAX endpoint below and injected
// into the DOM client-side.

// Action name deliberately avoids the words "banner"/"cookie"/"consent"
// so ad-block network filters don't match the request URL.
add_action( 'wp_ajax_pcc_load_ui', 'pcc_ajax_get_banner' );
add_action( 'wp_ajax_nopriv_pcc_load_ui', 'pcc_ajax_get_banner' );

// Legacy alias (pages cached with the pre-1.7.0 script).
add_action( 'wp_ajax_pcc_get_banner', 'pcc_ajax_get_banner' );
add_action( 'wp_ajax_nopriv_pcc_get_banner', 'pcc_ajax_get_banner' );

function pcc_ajax_get_banner() {
    $path = sanitize_text_field( wp_unslash( $_GET['path'] ?? '' ) );
    $lang = sanitize_text_field( wp_unslash( $_GET['lang'] ?? '' ) );

    ob_start();
    pcc_render_banner( $path !== '' ? $path : null, $lang !== '' ? $lang : null );
    $html = ob_get_clean();

    wp_send_json_success( array( 'html' => $html ) );
}

// =========================================================================
//  UPDATES FROM GITHUB: native WordPress updates, no manual zip uploads.
//  Uses the core `Update URI` mechanism (WP 5.8+). Works out of the box
//  when the repo is public; for a private repo, paste a GitHub personal
//  access token (repo read scope) in the Banner Design page.
// =========================================================================

/**
 * Latest version on GitHub's main branch (cached 6 hours).
 */
function pcc_get_remote_version() {
    $cached = get_site_transient( 'pcc_github_version' );
    if ( $cached ) {
        return $cached;
    }

    $resp = wp_remote_get(
        'https://api.github.com/repos/' . PCC_GITHUB_REPO . '/contents/converta-cookie-banner.php?ref=main',
        array(
            'timeout' => 10,
            'headers' => array( 'Accept' => 'application/vnd.github.raw+json' ),
        )
    );

    if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
        return false;
    }

    $body = wp_remote_retrieve_body( $resp );
    if ( preg_match( '/^\s*\*\s*Version:\s*([0-9][0-9a-z.\-]*)/mi', $body, $m ) ) {
        set_site_transient( 'pcc_github_version', $m[1], 6 * HOUR_IN_SECONDS );
        return $m[1];
    }

    return false;
}

// Send the GitHub token (if configured) with requests to this repo only.
add_filter( 'http_request_args', 'pcc_github_request_args', 10, 2 );

function pcc_github_request_args( $args, $url ) {
    $token = get_option( 'pcc_github_token', '' );
    if ( ! $token ) {
        return $args;
    }
    if ( false !== strpos( $url, 'github.com/' . PCC_GITHUB_REPO )
        || false !== strpos( $url, 'api.github.com/repos/' . PCC_GITHUB_REPO )
        || false !== strpos( $url, 'raw.githubusercontent.com/' . PCC_GITHUB_REPO ) ) {
        if ( ! isset( $args['headers'] ) || ! is_array( $args['headers'] ) ) {
            $args['headers'] = array();
        }
        $args['headers']['Authorization'] = 'token ' . $token;
    }
    return $args;
}

// Core calls this for every plugin whose Update URI host is github.com.
add_filter( 'update_plugins_github.com', 'pcc_github_update_info', 10, 3 );

function pcc_github_update_info( $update, $plugin_data, $plugin_file ) {
    if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
        return $update;
    }

    // Pinned version (after a rollback): report the pinned version so the
    // site is not immediately offered the newest release again. Cleared
    // with the "resume updates" button.
    $pin = get_option( 'pcc_pin_version', '' );
    if ( $pin ) {
        return array(
            'id'      => 'https://github.com/' . PCC_GITHUB_REPO,
            'slug'    => dirname( plugin_basename( __FILE__ ) ),
            'plugin'  => $plugin_file,
            'version' => ltrim( $pin, 'vV' ),
            'url'     => 'https://github.com/' . PCC_GITHUB_REPO,
            'package' => 'https://api.github.com/repos/' . PCC_GITHUB_REPO . '/zipball/refs/tags/' . rawurlencode( $pin ),
        );
    }

    $remote = pcc_get_remote_version();
    if ( ! $remote ) {
        return $update;
    }

    // With a token, the API zipball follows an authenticated redirect;
    // without one (public repo) the plain archive URL is enough.
    $package = get_option( 'pcc_github_token', '' )
        ? 'https://api.github.com/repos/' . PCC_GITHUB_REPO . '/zipball/main'
        : 'https://github.com/' . PCC_GITHUB_REPO . '/archive/refs/heads/main.zip';

    return array(
        'id'      => 'https://github.com/' . PCC_GITHUB_REPO,
        'slug'    => dirname( plugin_basename( __FILE__ ) ),
        'plugin'  => $plugin_file,
        'version' => $remote,
        'url'     => 'https://github.com/' . PCC_GITHUB_REPO,
        'package' => $package,
        'icons'   => array(
            '1x' => pcc_wp_assets_url() . 'icon-128x128.png',
            '2x' => pcc_wp_assets_url() . 'icon-256x256.png',
        ),
        'banners' => array(
            'low'  => pcc_wp_assets_url() . 'banner-772x250.png',
            'high' => pcc_wp_assets_url() . 'banner-1544x500.png',
        ),
    );
}

/**
 * Version tags published on GitHub (v1.5.0, v1.5.1, …), newest first.
 * Cached 6 hours.
 */
function pcc_get_github_tags() {
    $cached = get_site_transient( 'pcc_github_tags' );
    if ( is_array( $cached ) ) {
        return $cached;
    }
    $resp = wp_remote_get(
        'https://api.github.com/repos/' . PCC_GITHUB_REPO . '/tags?per_page=100',
        array( 'timeout' => 10 )
    );
    if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
        return array();
    }
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    $tags = array();
    foreach ( (array) $data as $t ) {
        if ( ! empty( $t['name'] ) ) {
            $tags[] = $t['name'];
        }
    }
    usort( $tags, function ( $a, $b ) {
        return version_compare( ltrim( $b, 'vV' ), ltrim( $a, 'vV' ) );
    } );
    set_site_transient( 'pcc_github_tags', $tags, 6 * HOUR_IN_SECONDS );
    return $tags;
}

/**
 * README.md from GitHub main (cached 6 hours) — source of the changelog.
 */
function pcc_get_remote_readme() {
    $cached = get_site_transient( 'pcc_github_readme' );
    if ( false !== $cached ) {
        return $cached;
    }
    $resp = wp_remote_get(
        'https://api.github.com/repos/' . PCC_GITHUB_REPO . '/contents/README.md?ref=main',
        array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github.raw+json' ) )
    );
    if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
        return '';
    }
    $body = wp_remote_retrieve_body( $resp );
    set_site_transient( 'pcc_github_readme', $body, 6 * HOUR_IN_SECONDS );
    return $body;
}

/**
 * Changelog parsed from the README: version => array( 'summary' => first
 * bullet (plain text), 'html' => full section as simple HTML ).
 */
function pcc_get_changelog_map() {
    $md = pcc_get_remote_readme();
    if ( ! $md ) {
        return array();
    }
    $map = array();
    if ( preg_match_all( '/^### ([0-9][0-9a-z.\-]*)\s*\n(.*?)(?=^### |\z)/ms', $md, $m, PREG_SET_ORDER ) ) {
        foreach ( $m as $sec ) {
            $version = trim( $sec[1] );
            $body    = trim( $sec[2] );

            $summary = '';
            foreach ( explode( "\n", $body ) as $line ) {
                $line = trim( $line );
                if ( 0 === strpos( $line, '- ' ) ) {
                    $summary = substr( $line, 2 );
                    break;
                }
            }
            $summary = pcc_strip_markdown( $summary );

            $html  = '';
            $in_ul = false;
            foreach ( explode( "\n", $body ) as $line ) {
                $trimmed = trim( $line );
                if ( '' === $trimmed ) {
                    continue;
                }
                if ( 0 === strpos( $trimmed, '- ' ) ) {
                    if ( ! $in_ul ) {
                        $html .= '<ul>';
                        $in_ul = true;
                    }
                    $html .= '<li>' . pcc_md_inline( substr( $trimmed, 2 ) ) . '</li>';
                } else {
                    if ( $in_ul ) {
                        $html .= '</ul>';
                        $in_ul = false;
                    }
                    $html .= '<p>' . pcc_md_inline( $trimmed ) . '</p>';
                }
            }
            if ( $in_ul ) {
                $html .= '</ul>';
            }

            $map[ $version ] = array( 'summary' => $summary, 'html' => $html );
        }
    }
    return $map;
}

function pcc_strip_markdown( $text ) {
    $text = preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $text );
    $text = str_replace( array( '**', '`', '*' ), '', $text );
    return trim( wp_strip_all_tags( $text ) );
}

function pcc_md_inline( $text ) {
    $text = esc_html( pcc_strip_markdown( $text ) );
    return $text;
}

/**
 * "View details" popup on the Plugins page: serve real information and the
 * full changelog from GitHub instead of a "plugin not found" error.
 */
add_filter( 'plugins_api', 'pcc_plugins_api_info', 10, 3 );

function pcc_wp_assets_url() {
    return 'https://raw.githubusercontent.com/' . PCC_GITHUB_REPO . '/main/assets/wp/';
}

/**
 * Date of the latest commit on main (cached 6 hours).
 */
function pcc_get_last_updated() {
    $cached = get_site_transient( 'pcc_github_updated' );
    if ( $cached ) {
        return $cached;
    }
    $resp = wp_remote_get(
        'https://api.github.com/repos/' . PCC_GITHUB_REPO . '/commits/main',
        array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github+json' ) )
    );
    if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
        return '';
    }
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    $date = $data['commit']['committer']['date'] ?? '';
    if ( $date ) {
        $date = gmdate( 'Y-m-d', strtotime( $date ) );
        set_site_transient( 'pcc_github_updated', $date, 6 * HOUR_IN_SECONDS );
    }
    return $date;
}

function pcc_plugins_api_info( $res, $action, $args ) {
    if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( plugin_basename( __FILE__ ) ) !== $args->slug ) {
        return $res;
    }

    $assets = pcc_wp_assets_url();

    $changelog = '';
    foreach ( pcc_get_changelog_map() as $version => $entry ) {
        $changelog .= '<h4>' . esc_html( $version ) . '</h4>' . $entry['html'];
    }

    $description = '<p><strong>Converta Cookie Banner</strong> is a complete GDPR/ePrivacy consent solution built around Google Consent Mode v2 — engineered so that the consent default always fires before Google Tag Manager, no matter how GTM is installed.</p>'
        . '<h4>Highlights</h4><ul>'
        . '<li><strong>Google Consent Mode v2</strong> — consent default injected as the very first script in <code>&lt;head&gt;</code>; optional hard-block mode for GTM.</li>'
        . '<li><strong>Ad-blocker resistant</strong> — inline assets and neutral markup survive Brave/uBlock cookie-notice filters, so consent can always be given.</li>'
        . '<li><strong>SEO-clean</strong> — zero banner text in the page HTML (loaded via AJAX, <code>data-nosnippet</code> everywhere).</li>'
        . '<li><strong>Cookie scanner</strong> — crawls the site, detects cookies, one-click cookie library.</li>'
        . '<li><strong>Consent statistics</strong> — accept/reject rates, unique visitors, daily trends (privacy-safe, hashed).</li>'
        . '<li><strong>Legal Pages</strong> — detects existing Privacy/Terms pages (never duplicates) or generates them from company data.</li>'
        . '<li><strong>Design customizer &amp; 6 languages</strong> — every color and text, RO/EN/DE/FR/IT/ES with automatic detection.</li>'
        . '<li><strong>Self-updates from GitHub</strong> — native WordPress updates, one-click rollback to any version.</li>'
        . '</ul>';

    $installation = '<ol>'
        . '<li>Upload the plugin ZIP via <strong>Plugins &rarr; Add New &rarr; Upload Plugin</strong> and activate it.</li>'
        . '<li>The banner appears immediately for visitors without a consent cookie.</li>'
        . '<li>Configure everything under the <strong>Cookie Consent</strong> admin menu: Statistics, Cookie Scanner, Banner Design, Translations, Legal Pages.</li>'
        . '<li>From then on the plugin updates itself from GitHub — no more manual uploads.</li>'
        . '</ol>';

    $faq = '<h4>How do visitors change their consent later?</h4><p>Via the floating cookie icon, an automatic footer link, the <code>[pcc_consent_link]</code> shortcode, or any link with the <code>pcc-plink</code> CSS class — configurable in Banner Design.</p>'
        . '<h4>Does it block Google Tag Manager?</h4><p>No — by default it uses Advanced Consent Mode: GTM loads immediately and consent signals keep Google tags cookieless until consent. An optional Basic mode hard-blocks GTM until consent is granted.</p>'
        . '<h4>Will it duplicate my Privacy Policy / Terms pages?</h4><p>Never. The Legal Pages section detects existing pages and selects them; generation is only offered when no page exists.</p>'
        . '<h4>Do updates or rollbacks lose my settings and statistics?</h4><p>No — settings and consent logs live in the database and survive updates, rollbacks and deactivation. Only deleting the plugin removes them.</p>'
        . '<h4>How do I install an older version?</h4><p>Banner Design &rarr; Plugin Updates from GitHub &rarr; Version switch: pick any released version and install it with one click. Updates pause on that version until you resume them.</p>';

    $screenshots = '<ol>'
        . '<li><a href="' . esc_url( $assets . 'screenshot-1.png' ) . '"><img src="' . esc_url( $assets . 'screenshot-1.png' ) . '" alt="Consent banner"></a><p>The consent banner on a live site (dark theme, fully customizable).</p></li>'
        . '<li><a href="' . esc_url( $assets . 'screenshot-2.png' ) . '"><img src="' . esc_url( $assets . 'screenshot-2.png' ) . '" alt="Preferences panel"></a><p>The preferences panel with per-category toggles and cookie tables.</p></li>'
        . '</ol>';

    return (object) array(
        'name'          => 'Converta Cookie Banner',
        'slug'          => $args->slug,
        'version'       => pcc_get_remote_version() ?: PCC_VERSION,
        'author'        => '<a href="https://converta.ro">Converta</a>',
        'author_profile' => 'https://converta.ro',
        'homepage'      => 'https://github.com/' . PCC_GITHUB_REPO,
        'requires'      => '5.8',
        'tested'        => get_bloginfo( 'version' ),
        'requires_php'  => '7.4',
        'last_updated'  => pcc_get_last_updated(),
        'banners'       => array(
            'low'  => $assets . 'banner-772x250.png',
            'high' => $assets . 'banner-1544x500.png',
        ),
        'icons'         => array(
            '1x' => $assets . 'icon-128x128.png',
            '2x' => $assets . 'icon-256x256.png',
        ),
        'contributors'  => array(
            'converta' => array(
                'profile'      => 'https://converta.ro',
                'avatar'       => $assets . 'icon-128x128.png',
                'display_name' => 'Converta',
            ),
        ),
        'sections'      => array(
            'description'  => $description,
            'installation' => $installation,
            'faq'          => $faq,
            'screenshots'  => $screenshots,
            'changelog'    => $changelog ?: '<p>See the repository for details.</p>',
        ),
        'download_link' => 'https://github.com/' . PCC_GITHUB_REPO . '/archive/refs/heads/main.zip',
    );
}

// One-click rollback / version switch, driven from the Banner Design page.
add_action( 'admin_post_pcc_rollback', 'pcc_handle_rollback' );

function pcc_handle_rollback() {
    if ( ! current_user_can( 'update_plugins' ) ) {
        wp_die( 'Unauthorized' );
    }
    check_admin_referer( 'pcc_rollback' );

    $version    = sanitize_text_field( wp_unslash( $_GET['version'] ?? '' ) );
    $design_url = admin_url( 'admin.php?page=pcc-card-design' );

    // "latest" = unpin and resume normal updates.
    if ( 'latest' === $version ) {
        delete_option( 'pcc_pin_version' );
        delete_site_transient( 'pcc_github_version' );
        wp_safe_redirect( $design_url );
        exit;
    }

    $tags = pcc_get_github_tags();
    if ( ! in_array( $version, $tags, true ) ) {
        wp_die( 'Unknown version: ' . esc_html( $version ) );
    }

    $plugin_file = plugin_basename( __FILE__ );
    $was_active  = is_plugin_active( $plugin_file );
    $ver_number  = ltrim( $version, 'vV' );
    $package     = 'https://api.github.com/repos/' . PCC_GITHUB_REPO . '/zipball/refs/tags/' . rawurlencode( $version );

    // Pin BEFORE installing, so the updater doesn't fight the rollback.
    update_option( 'pcc_pin_version', $version, false );
    delete_site_transient( 'pcc_github_version' );

    // Feed the chosen package to the standard WordPress plugin upgrader.
    add_filter( 'site_transient_update_plugins', function ( $t ) use ( $plugin_file, $ver_number, $package ) {
        if ( ! is_object( $t ) ) {
            $t = new stdClass();
        }
        if ( ! isset( $t->response ) || ! is_array( $t->response ) ) {
            $t->response = array();
        }
        $t->response[ $plugin_file ] = (object) array(
            'slug'        => dirname( $plugin_file ),
            'plugin'      => $plugin_file,
            'new_version' => $ver_number,
            'url'         => 'https://github.com/' . PCC_GITHUB_REPO,
            'package'     => $package,
        );
        return $t;
    } );

    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/misc.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';

    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Version switch</title></head><body style="font-family:-apple-system,sans-serif;max-width:720px;margin:40px auto;line-height:1.6;">';
    echo '<h1 style="font-size:20px;">Installing Converta Cookie Banner ' . esc_html( $version ) . '&hellip;</h1>';

    $upgrader = new Plugin_Upgrader( new Plugin_Upgrader_Skin() );
    $result   = $upgrader->upgrade( $plugin_file );

    if ( $result && ! is_wp_error( $result ) ) {
        // The upgrader deactivates the plugin during replacement; restore
        // activation without re-including the (already loaded) plugin file.
        if ( $was_active ) {
            $active = get_option( 'active_plugins', array() );
            if ( ! in_array( $plugin_file, $active, true ) ) {
                $active[] = $plugin_file;
                sort( $active );
                update_option( 'active_plugins', $active );
            }
        }
        echo '<p><strong>Done.</strong> Version ' . esc_html( $version ) . ' is installed and updates are paused (pinned to this version). Use &ldquo;Resume updates&rdquo; on the Banner Design page when you want the latest version again.</p>';
    } else {
        delete_option( 'pcc_pin_version' );
        echo '<p><strong>The version switch failed.</strong> The previous files may still be in place; check the Plugins page.</p>';
    }

    echo '<p><a href="' . esc_url( $design_url ) . '">&larr; Back to Banner Design</a></p></body></html>';
    exit;
}

// GitHub zips extract to folders like "converta-cookie-banner-main" or
// "tudorhoriadaniel-converta-cookie-banner-<sha>"; rename the extracted
// folder to the currently installed plugin folder so the plugin stays
// activated and its path never changes.
add_filter( 'upgrader_source_selection', 'pcc_normalize_github_source', 10, 4 );

function pcc_normalize_github_source( $source, $remote_source, $upgrader, $hook_extra ) {
    if ( empty( $hook_extra['plugin'] ) || plugin_basename( __FILE__ ) !== $hook_extra['plugin'] ) {
        return $source;
    }
    global $wp_filesystem;
    $desired = trailingslashit( $remote_source ) . dirname( plugin_basename( __FILE__ ) );
    if ( untrailingslashit( $source ) === $desired ) {
        return $source;
    }
    if ( $wp_filesystem && $wp_filesystem->move( untrailingslashit( $source ), $desired ) ) {
        return trailingslashit( $desired );
    }
    return $source;
}

// After this plugin updates, forget the cached remote version.
// "Check again" on Dashboard → Updates must bypass our 6-hour cache too,
// so a just-pushed release shows up immediately on a forced check.
add_action( 'load-update-core.php', 'pcc_force_update_check' );

function pcc_force_update_check() {
    if ( isset( $_GET['force-check'] ) ) {
        delete_site_transient( 'pcc_github_version' );
        delete_site_transient( 'pcc_github_tags' );
        delete_site_transient( 'pcc_github_readme' );
    }
}

add_action( 'upgrader_process_complete', 'pcc_flush_update_cache', 10, 2 );

function pcc_flush_update_cache( $upgrader, $hook_extra ) {
    if ( isset( $hook_extra['type'] ) && 'plugin' === $hook_extra['type'] ) {
        delete_site_transient( 'pcc_github_version' );
    }
}

// AJAX: save the GitHub token (admins only). An empty value removes it.
add_action( 'wp_ajax_pcc_save_gh_token', 'pcc_ajax_save_gh_token' );

function pcc_ajax_save_gh_token() {
    check_ajax_referer( 'pcc_design_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }
    $token = sanitize_text_field( wp_unslash( $_POST['token'] ?? '' ) );
    if ( '' === $token ) {
        delete_option( 'pcc_github_token' );
    } else {
        update_option( 'pcc_github_token', $token, false );
    }
    delete_site_transient( 'pcc_github_version' );
    wp_send_json_success();
}

/**
 * Helper: get cookie list grouped by category for the frontend.
 */
function pcc_get_cookie_list() {
    $scan = get_option( 'pcc_scan_results', array() );
    $cookies = isset( $scan['cookies'] ) ? $scan['cookies'] : array();

    $grouped = array(
        'necessary'  => array(),
        'statistics' => array(),
        'marketing'  => array(),
    );

    foreach ( $cookies as $c ) {
        $cat = $c['category'] ?? 'necessary';
        if ( isset( $grouped[ $cat ] ) ) {
            $grouped[ $cat ][] = $c;
        }
    }

    return $grouped;
}

/**
 * Render a cookie table for a category inside preferences panel.
 */
function pcc_render_cookie_table( $cookies ) {
    if ( empty( $cookies ) ) {
        return;
    }
    ?>
    <div class="pcc-ui-table-wrap">
        <table class="pcc-ui-table">
            <thead>
                <tr>
                    <th>Cookie</th>
                    <th>Provider</th>
                    <th>Duration</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $cookies as $c ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $c['name'] ); ?></code></td>
                    <td><?php echo esc_html( $c['provider'] ); ?></td>
                    <td><?php echo esc_html( $c['duration'] ); ?></td>
                    <td><?php echo esc_html( $c['description'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/**
 * Current reopen method: 'icon' (floating button), 'footer_link', or 'both'.
 */
function pcc_get_reopen_method() {
    $d = pcc_get_design();
    $method = $d['reopen_method'] ?? 'icon';
    return in_array( $method, array( 'icon', 'footer_link', 'both' ), true ) ? $method : 'icon';
}

/**
 * Shortcode [pcc_consent_link] — place a "Cookie Settings" link anywhere
 * (footer menu, widget, privacy page). Works regardless of the chosen
 * reopen method. Optional attribute: [pcc_consent_link text="Cookies"].
 */
add_shortcode( 'pcc_consent_link', 'pcc_consent_link_shortcode' );

function pcc_consent_link_shortcode( $atts ) {
    $atts = shortcode_atts( array( 'text' => '' ), $atts, 'pcc_consent_link' );
    $s    = pcc_get_current_strings();
    $text = '' !== $atts['text'] ? esc_html( $atts['text'] ) : ( $s['footer_link'] ?? 'Cookie Settings' );
    return '<a href="#" class="pcc-plink" role="button" data-nosnippet>' . $text . '</a>';
}

function pcc_render_banner( $path_override = null, $lang_override = null ) {
    $cookie_list = pcc_get_cookie_list();
    $s = pcc_get_current_strings( $path_override, $lang_override );
    $reopen_method = pcc_get_reopen_method();
    ?>
<div id="pcc-ui-overlay" class="pcc-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="Privacy preferences" data-nosnippet>

    <!-- Main Banner -->
    <div id="pcc-ui-card" class="pcc-card">
        <div class="pcc-card-content">
            <div class="pcc-card-tag">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <?php echo $s['tag']; ?>
            </div>
            <div class="pcc-card-header">
                <h3><?php echo $s['heading']; ?></h3>
            </div>
            <div class="pcc-card-text">
                <p><?php echo $s['body']; ?></p>
            </div>
            <div class="pcc-card-actions">
                <button id="pcc-accept-all" class="pcc-btn pcc-btn-accept" type="button"><?php echo esc_html( $s['btn_accept'] ); ?></button>
                <button id="pcc-reject-all" class="pcc-btn pcc-btn-reject" type="button"><?php echo esc_html( $s['btn_reject'] ); ?></button>
                <button id="pcc-show-preferences" class="pcc-btn pcc-btn-preferences" type="button">
                    <?php echo esc_html( $s['btn_customize'] ); ?>
                    <svg class="pcc-btn-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Preferences Panel -->
    <div id="pcc-preferences-panel" class="pcc-preferences" style="display:none;">
        <div class="pcc-preferences-content">
            <div class="pcc-preferences-header">
                <h3>
                    <span class="pcc-pref-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <?php echo $s['pref_heading']; ?>
                </h3>
                <button id="pcc-close-preferences" class="pcc-close-btn" type="button" aria-label="Close">&times;</button>
            </div>
            <p class="pcc-preferences-intro"><?php echo $s['pref_intro']; ?></p>

            <!-- Necessary -->
            <div class="pcc-ui-category">
                <div class="pcc-category-header">
                    <div class="pcc-category-info">
                        <strong><?php echo $s['cat_necessary']; ?></strong>
                        <span class="pcc-always-active"><?php echo $s['always_active']; ?></span>
                    </div>
                    <label class="pcc-toggle"><input type="checkbox" checked disabled><span class="pcc-toggle-slider pcc-always-on"></span></label>
                    <?php if ( ! empty( $cookie_list['necessary'] ) ) : ?>
                        <button type="button" class="pcc-expand-btn" data-target="pcc-necessary-list" aria-label="Show details">
                            &#9660;
                        </button>
                    <?php endif; ?>
                </div>
                <p class="pcc-category-desc"><?php echo $s['cat_necessary_desc']; ?></p>
                <?php if ( ! empty( $cookie_list['necessary'] ) ) : ?>
                    <div id="pcc-necessary-list" class="pcc-ui-details" style="display:none;">
                        <?php pcc_render_cookie_table( $cookie_list['necessary'] ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistics -->
            <div class="pcc-ui-category">
                <div class="pcc-category-header">
                    <div class="pcc-category-info">
                        <strong><?php echo $s['cat_analytics']; ?></strong>
                        <?php if ( ! empty( $cookie_list['statistics'] ) ) : ?>
                            <span class="pcc-ui-count"><?php echo count( $cookie_list['statistics'] ); ?> cookies</span>
                        <?php endif; ?>
                    </div>
                    <label class="pcc-toggle"><input type="checkbox" id="pcc-statistics-toggle" data-category="statistics"><span class="pcc-toggle-slider"></span></label>
                    <?php if ( ! empty( $cookie_list['statistics'] ) ) : ?>
                        <button type="button" class="pcc-expand-btn" data-target="pcc-statistics-list" aria-label="Show details">
                            &#9660;
                        </button>
                    <?php endif; ?>
                </div>
                <p class="pcc-category-desc"><?php echo $s['cat_analytics_desc']; ?></p>
                <?php if ( ! empty( $cookie_list['statistics'] ) ) : ?>
                    <div id="pcc-statistics-list" class="pcc-ui-details" style="display:none;">
                        <?php pcc_render_cookie_table( $cookie_list['statistics'] ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Marketing -->
            <div class="pcc-ui-category">
                <div class="pcc-category-header">
                    <div class="pcc-category-info">
                        <strong><?php echo $s['cat_marketing']; ?></strong>
                        <?php if ( ! empty( $cookie_list['marketing'] ) ) : ?>
                            <span class="pcc-ui-count"><?php echo count( $cookie_list['marketing'] ); ?> cookies</span>
                        <?php endif; ?>
                    </div>
                    <label class="pcc-toggle"><input type="checkbox" id="pcc-marketing-toggle" data-category="marketing"><span class="pcc-toggle-slider"></span></label>
                    <?php if ( ! empty( $cookie_list['marketing'] ) ) : ?>
                        <button type="button" class="pcc-expand-btn" data-target="pcc-marketing-list" aria-label="Show details">
                            &#9660;
                        </button>
                    <?php endif; ?>
                </div>
                <p class="pcc-category-desc"><?php echo $s['cat_marketing_desc']; ?></p>
                <?php if ( ! empty( $cookie_list['marketing'] ) ) : ?>
                    <div id="pcc-marketing-list" class="pcc-ui-details" style="display:none;">
                        <?php pcc_render_cookie_table( $cookie_list['marketing'] ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="pcc-preferences-actions">
                <button id="pcc-save-preferences" class="pcc-btn pcc-btn-save" type="button"><?php echo esc_html( $s['btn_save'] ); ?></button>
                <button id="pcc-accept-all-prefs" class="pcc-btn pcc-btn-accept" type="button"><?php echo esc_html( $s['btn_accept'] ); ?></button>
                <button id="pcc-cancel-preferences" class="pcc-btn pcc-btn-cancel" type="button"><?php echo esc_html( $s['btn_cancel'] ); ?></button>
            </div>
        </div>
    </div>

</div>

<?php if ( 'icon' === $reopen_method || 'both' === $reopen_method ) : ?>
<button id="pcc-reopen" class="pcc-reopen-btn" type="button" aria-label="Privacy preferences" style="display:none;" title="Privacy preferences">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg>
</button>
<?php endif; ?>

<?php if ( 'footer_link' === $reopen_method || 'both' === $reopen_method ) : ?>
<?php
// Footer links for legal pages: ONLY pages generated by this plugin.
// Pre-existing pages are already linked by the theme — adding them again
// would create duplicate links, so the plugin never does that.
$pcc_legal       = pcc_get_legal_settings();
$pcc_legal_links = array();
if ( ! empty( $pcc_legal['footer_links'] ) ) {
    $pcc_cur_lang = pcc_detect_language( $path_override, $lang_override );
    foreach ( array( 'privacy', 'terms' ) as $pcc_lt ) {
        $pcc_lp = (int) ( $pcc_legal[ $pcc_lt ] ?? 0 );
        // On multilingual sites, link the version in the visitor's language.
        $pcc_tr = (int) ( $pcc_legal['i18n'][ $pcc_lt ][ $pcc_cur_lang ] ?? 0 );
        if ( ! $pcc_tr && $pcc_lp && function_exists( 'pll_get_post' ) ) {
            $pcc_tr = (int) pll_get_post( $pcc_lp, $pcc_cur_lang );
        }
        if ( $pcc_tr ) {
            $pcc_lp = $pcc_tr;
        }
        if ( $pcc_lp && 'publish' === get_post_status( $pcc_lp ) && get_post_meta( $pcc_lp, '_pcc_legal_type', true ) === $pcc_lt ) {
            $pcc_legal_links[] = array( get_permalink( $pcc_lp ), get_the_title( $pcc_lp ) );
        }
    }
}
?>
<div class="pcc-footer-bar" data-nosnippet>
    <?php foreach ( $pcc_legal_links as $pcc_ll ) : ?>
    <a href="<?php echo esc_url( $pcc_ll[0] ); ?>" class="pcc-legal-link"><?php echo esc_html( $pcc_ll[1] ); ?></a>
    <?php endforeach; ?>
    <a href="#" class="pcc-plink" role="button">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg>
        <?php echo $s['footer_link'] ?? 'Cookie Settings'; ?>
    </a>
</div>
<?php endif; ?>
    <?php
}

// =========================================================================
//  LEGAL PAGES: use existing Privacy Policy / Terms pages, or generate
//  them from company data — NEVER creating duplicates.
//
//  Rules:
//  - Nothing is ever created automatically (not on activation, not on
//    upgrade). Generation happens only when the admin clicks Generate.
//  - Before generating, existing pages are detected (WP core privacy
//    setting, then slug/title heuristics in all supported languages) and
//    auto-selected instead — generation is refused while a page exists.
//  - Generated pages carry the _pcc_legal_type meta; regenerating updates
//    (or restores from trash) that same page instead of creating another.
//  - Footer links are added ONLY for pages this plugin generated. Existing
//    pages are assumed to already be linked by the theme, so the plugin
//    adds only the Cookie Settings link.
// =========================================================================

function pcc_company_defaults() {
    return array(
        'company_name' => '',
        'cui'          => '',
        'reg_com'      => '',
        'address'      => '',
        'email'        => '',
        'phone'        => '',
        'website'      => home_url(),
    );
}

function pcc_get_company() {
    return wp_parse_args( get_option( 'pcc_company', array() ), pcc_company_defaults() );
}

function pcc_get_legal_settings() {
    return wp_parse_args( get_option( 'pcc_legal_pages', array() ), array(
        'privacy'      => 0,
        'terms'        => 0,
        'footer_links' => 1,
        // Per-language page ids on multilingual (Polylang) sites:
        // array( 'privacy' => array( 'en' => 12 ), 'terms' => array( ... ) )
        'i18n'         => array(),
    ) );
}

// ---- Multilingual (Polylang) support ----

function pcc_site_languages() {
    return function_exists( 'pll_languages_list' ) ? (array) pll_languages_list( array( 'fields' => 'slug' ) ) : array();
}

function pcc_site_default_language() {
    return function_exists( 'pll_default_language' ) ? (string) pll_default_language( 'slug' ) : '';
}

/**
 * A page this plugin generated for a given type AND language.
 */
function pcc_get_generated_legal_page_lang( $type, $lang, $include_trash = false ) {
    $statuses = array( 'publish', 'draft', 'private', 'pending' );
    if ( $include_trash ) {
        $statuses[] = 'trash';
    }
    $posts = get_posts( array(
        'post_type'   => 'page',
        'post_status' => $statuses,
        'meta_query'  => array(
            array( 'key' => '_pcc_legal_type', 'value' => $type ),
            array( 'key' => '_pcc_legal_lang', 'value' => $lang ),
        ),
        'numberposts' => 1,
        'orderby'     => 'ID',
        'order'       => 'ASC',
        'lang'        => '', // Polylang: do not filter by admin language
    ) );
    return $posts ? $posts[0] : null;
}

/**
 * The page to use for a type in a specific language, or 0.
 * Order: explicit per-language selection → Polylang translation of the
 * default-language page → a page this plugin generated for that language.
 */
function pcc_resolve_legal_page( $type, $lang ) {
    $legal = pcc_get_legal_settings();

    $sel = (int) ( $legal['i18n'][ $type ][ $lang ] ?? 0 );
    if ( $sel && get_post( $sel ) && 'trash' !== get_post_status( $sel ) ) {
        return $sel;
    }

    $default_id = (int) ( $legal[ $type ] ?? 0 );
    if ( $default_id && function_exists( 'pll_get_post' ) ) {
        $t = pll_get_post( $default_id, $lang );
        if ( $t && 'trash' !== get_post_status( $t ) ) {
            return (int) $t;
        }
    }

    $generated = pcc_get_generated_legal_page_lang( $type, $lang );
    return $generated ? $generated->ID : 0;
}

/**
 * Page (any status except trash) previously GENERATED by this plugin.
 */
function pcc_get_generated_legal_page( $type, $include_trash = false ) {
    $statuses = array( 'publish', 'draft', 'private', 'pending' );
    if ( $include_trash ) {
        $statuses[] = 'trash';
    }
    $posts = get_posts( array(
        'post_type'   => 'page',
        'post_status' => $statuses,
        'meta_key'    => '_pcc_legal_type',
        'meta_value'  => $type,
        'numberposts' => 1,
        'orderby'     => 'ID',
        'order'       => 'ASC',
    ) );
    return $posts ? $posts[0] : null;
}

/**
 * Detect an EXISTING legal page on the site (not necessarily ours).
 * Returns a page ID or 0.
 */
function pcc_detect_legal_page( $type ) {
    // Explicit selection first.
    $legal = pcc_get_legal_settings();
    $sel   = (int) ( $legal[ $type ] ?? 0 );
    if ( $sel && get_post( $sel ) && 'trash' !== get_post_status( $sel ) ) {
        return $sel;
    }

    // WordPress core privacy page setting.
    if ( 'privacy' === $type ) {
        $core = (int) get_option( 'wp_page_for_privacy_policy' );
        if ( $core && get_post( $core ) && 'trash' !== get_post_status( $core ) ) {
            return $core;
        }
    }

    // A page this plugin generated earlier.
    $generated = pcc_get_generated_legal_page( $type );
    if ( $generated ) {
        return $generated->ID;
    }

    // Common slugs across the supported languages.
    $slugs = 'privacy' === $type
        ? array( 'politica-de-confidentialitate', 'politica-confidentialitate', 'confidentialitate', 'privacy-policy', 'privacy', 'datenschutz', 'datenschutzerklaerung', 'politique-de-confidentialite', 'confidentialite', 'informativa-privacy', 'politica-de-privacidad' )
        : array( 'termeni-si-conditii', 'termeni', 'terms-and-conditions', 'terms-conditions', 'terms-of-service', 'terms', 'agb', 'conditions-generales', 'cgv', 'termini-e-condizioni', 'terminos-y-condiciones' );
    foreach ( $slugs as $slug ) {
        $page = get_page_by_path( $slug );
        if ( $page && 'page' === $page->post_type && 'trash' !== $page->post_status ) {
            return $page->ID;
        }
    }

    // Title fragments (catches translated titles with other slugs).
    global $wpdb;
    $fragments = 'privacy' === $type
        ? array( 'confiden%ialitate', 'privacy', 'datenschutz', 'privacidad', 'confidentialit%' )
        : array( 'termeni', 'terms', 'agb', 'termini e condizioni', 't%rminos', 'conditions g%n%rales' );
    foreach ( $fragments as $frag ) {
        $id = $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status IN ('publish','draft','private')
             AND post_title LIKE %s ORDER BY ID ASC LIMIT 1",
            '%' . $frag . '%'
        ) );
        if ( $id ) {
            return (int) $id;
        }
    }

    return 0;
}

/**
 * Build the page content from company data. Romanian and English
 * templates; other site languages fall back to English.
 */
function pcc_legal_template( $type, $c, $lang = '' ) {
    $lang    = $lang ?: strtolower( substr( get_locale(), 0, 2 ) );
    $name    = $c['company_name'] ?: get_bloginfo( 'name' );
    $cui     = $c['cui'];
    $regcom  = $c['reg_com'];
    $address = $c['address'];
    $email   = $c['email'];
    $phone   = $c['phone'];
    $site    = $c['website'] ?: home_url();
    $host    = wp_parse_url( $site, PHP_URL_HOST );
    $date    = date_i18n( get_option( 'date_format' ) );

    // Identity/contact lines are built ONLY from fields that have a value —
    // empty fields are omitted entirely (no "[CUI]"-style placeholders).
    $parts_ro = array( $name );
    $parts_en = array( $name );
    if ( $cui ) {
        $parts_ro[] = 'CUI ' . $cui;
        $parts_en[] = 'tax ID ' . $cui;
    }
    if ( $regcom ) {
        $parts_ro[] = 'Nr. Reg. Com. ' . $regcom;
        $parts_en[] = 'trade registry no. ' . $regcom;
    }
    $id_line_ro = implode( ', ', $parts_ro ) . ( $address ? ', cu sediul în ' . $address : '' );
    $id_line_en = implode( ', ', $parts_en ) . ( $address ? ', registered at ' . $address : '' );

    $cparts_ro = array();
    $cparts_en = array();
    if ( $email ) {
        $cparts_ro[] = 'Email: ' . $email;
        $cparts_en[] = 'Email: ' . $email;
    }
    if ( $phone ) {
        $cparts_ro[] = 'Telefon: ' . $phone;
        $cparts_en[] = 'Phone: ' . $phone;
    }
    $contact_ro = $cparts_ro ? ' ' . implode( ' · ', $cparts_ro ) . '.' : '';
    $contact_en = $cparts_en ? ' ' . implode( ' · ', $cparts_en ) . '.' : '';

    $rights_ro = $email ? "scrieți-ne la {$email}" : 'contactați-ne folosind datele publicate pe site';
    $rights_en = $email ? "write to {$email}" : 'contact us using the details published on this website';

    if ( 'ro' === $lang ) {
        if ( 'privacy' === $type ) {
            return "<h2>1. Cine suntem</h2>\n<p>Site-ul <strong>{$host}</strong> este operat de <strong>{$id_line_ro}</strong> (denumit în continuare „{$name}”).{$contact_ro}</p>\n<h2>2. Ce date colectăm</h2>\n<p>Colectăm datele pe care ni le furnizați direct (de exemplu prin formularele de contact: nume, adresă de email, număr de telefon, conținutul mesajului) și date colectate automat prin cookie-uri și tehnologii similare (adresă IP, tip de browser, pagini vizitate, durata vizitei), în funcție de consimțământul dumneavoastră.</p>\n<h2>3. Scopurile și temeiurile prelucrării</h2>\n<p>Prelucrăm datele pentru: (a) a răspunde solicitărilor dumneavoastră — temei: demersuri precontractuale sau interes legitim; (b) analiza traficului și îmbunătățirea site-ului — temei: consimțământ; (c) marketing și publicitate personalizată — temei: consimțământ; (d) îndeplinirea obligațiilor legale.</p>\n<h2>4. Cookie-uri</h2>\n<p>Site-ul folosește cookie-uri necesare (esențiale pentru funcționare), de statistică și de marketing. Cookie-urile de statistică și marketing se activează doar cu consimțământul dumneavoastră, exprimat prin bannerul de consimțământ. Puteți modifica oricând alegerea de aici: [pcc_consent_link]. Lista completă a cookie-urilor folosite este disponibilă în panoul de preferințe al bannerului.</p>\n<h2>5. Destinatarii datelor</h2>\n<p>Datele pot fi transmise către furnizori de servicii (găzduire web, servicii de analiză precum Google Analytics, platforme de publicitate) care acționează ca persoane împuternicite, precum și autorităților publice atunci când legea o impune. Unii furnizori (de exemplu Google) pot transfera date în afara SEE, cu garanții adecvate (clauze contractuale standard).</p>\n<h2>6. Durata stocării</h2>\n<p>Păstrăm datele doar cât este necesar scopurilor de mai sus: datele din formulare — până la soluționarea solicitării și maximum 3 ani; datele de consimțământ pentru cookie-uri — 12 luni; datele de analiză — conform setărilor serviciului de analiză.</p>\n<h2>7. Drepturile dumneavoastră</h2>\n<p>Conform GDPR, aveți dreptul de acces, rectificare, ștergere, restricționare, portabilitate, opoziție și dreptul de a vă retrage oricând consimțământul, fără a afecta legalitatea prelucrării anterioare. Pentru exercitarea drepturilor, {$rights_ro}. Aveți de asemenea dreptul de a depune o plângere la ANSPDCP (www.dataprotection.ro).</p>\n<h2>8. Securitate</h2>\n<p>Aplicăm măsuri tehnice și organizatorice adecvate pentru protejarea datelor (conexiuni criptate HTTPS, acces restricționat, actualizări de securitate).</p>\n<h2>9. Actualizări</h2>\n<p>Prezenta politică poate fi actualizată periodic; versiunea curentă este publicată pe această pagină. Ultima actualizare: {$date}.</p>\n<p><em>Acest document este un șablon generat automat pe baza datelor companiei și nu constituie consultanță juridică. Recomandăm revizuirea lui de către un specialist.</em></p>";
        }
        return "<h2>1. Informații generale</h2>\n<p>Site-ul <strong>{$host}</strong> este operat de <strong>{$id_line_ro}</strong> (denumit în continuare „{$name}”).{$contact_ro}</p>\n<h2>2. Acceptarea termenilor</h2>\n<p>Accesarea și utilizarea site-ului implică acceptarea prezentelor termeni și condiții. Dacă nu sunteți de acord, vă rugăm să nu utilizați site-ul.</p>\n<h2>3. Conținutul site-ului</h2>\n<p>Conținutul site-ului (texte, imagini, elemente grafice, logo-uri) este proprietatea {$name} sau a partenerilor săi și este protejat de legislația privind drepturile de autor. Reproducerea fără acord scris este interzisă.</p>\n<h2>4. Utilizarea site-ului</h2>\n<p>Vă obligați să utilizați site-ul în conformitate cu legea și cu bunele practici, fără a afecta funcționarea acestuia, fără a accesa neautorizat sisteme sau date și fără a transmite conținut ilegal sau dăunător.</p>\n<h2>5. Servicii și informații</h2>\n<p>Informațiile publicate au caracter informativ. {$name} depune eforturi rezonabile pentru acuratețea lor, însă nu garantează caracterul complet sau actual al acestora și își rezervă dreptul de a modifica conținutul fără notificare prealabilă.</p>\n<h2>6. Limitarea răspunderii</h2>\n<p>{$name} nu răspunde pentru daune directe sau indirecte rezultate din utilizarea site-ului, din imposibilitatea utilizării acestuia sau din acțiunile unor terți (inclusiv site-uri către care există linkuri).</p>\n<h2>7. Protecția datelor</h2>\n<p>Prelucrarea datelor cu caracter personal este descrisă în Politica de Confidențialitate, iar utilizarea cookie-urilor poate fi gestionată oricând de aici: [pcc_consent_link].</p>\n<h2>8. Legea aplicabilă și litigii</h2>\n<p>Prezentele termeni sunt guvernate de legea română. Litigiile se soluționează pe cale amiabilă, iar în caz contrar de instanțele competente. Consumatorii pot apela și la platforma europeană de soluționare online a litigiilor (ec.europa.eu/consumers/odr) sau la ANPC (anpc.ro).</p>\n<h2>9. Modificarea termenilor</h2>\n<p>{$name} poate modifica acești termeni; versiunea curentă este publicată pe această pagină. Ultima actualizare: {$date}.</p>\n<p><em>Acest document este un șablon generat automat pe baza datelor companiei și nu constituie consultanță juridică. Recomandăm revizuirea lui de către un specialist.</em></p>";
    }

    if ( 'privacy' === $type ) {
        return "<h2>1. Who we are</h2>\n<p><strong>{$host}</strong> is operated by <strong>{$id_line_en}</strong> (\"{$name}\").{$contact_en}</p>\n<h2>2. Data we collect</h2>\n<p>We collect data you provide directly (e.g. via contact forms: name, email address, phone number, message content) and data collected automatically through cookies and similar technologies (IP address, browser type, pages visited, visit duration), subject to your consent.</p>\n<h2>3. Purposes and legal bases</h2>\n<p>We process data to: (a) respond to your requests — legal basis: pre-contractual steps or legitimate interest; (b) analyze traffic and improve the website — legal basis: consent; (c) marketing and personalized advertising — legal basis: consent; (d) comply with legal obligations.</p>\n<h2>4. Cookies</h2>\n<p>This website uses necessary cookies (essential for operation), statistics cookies and marketing cookies. Statistics and marketing cookies are activated only with your consent, given through the consent banner. You can change your choice at any time here: [pcc_consent_link]. The full list of cookies is available in the banner's preferences panel.</p>\n<h2>5. Data recipients</h2>\n<p>Data may be shared with service providers (web hosting, analytics services such as Google Analytics, advertising platforms) acting as processors, and with public authorities where required by law. Some providers (e.g. Google) may transfer data outside the EEA with appropriate safeguards (standard contractual clauses).</p>\n<h2>6. Retention</h2>\n<p>We keep data only as long as necessary for the purposes above: form data — until the request is resolved and at most 3 years; cookie consent records — 12 months; analytics data — according to the analytics service settings.</p>\n<h2>7. Your rights</h2>\n<p>Under the GDPR you have the rights of access, rectification, erasure, restriction, portability, objection, and the right to withdraw consent at any time without affecting prior processing. To exercise your rights, {$rights_en}. You may also lodge a complaint with your supervisory authority.</p>\n<h2>8. Security</h2>\n<p>We apply appropriate technical and organizational measures to protect data (HTTPS encryption, restricted access, security updates).</p>\n<h2>9. Updates</h2>\n<p>This policy may be updated periodically; the current version is published on this page. Last updated: {$date}.</p>\n<p><em>This document is an automatically generated template based on company data and does not constitute legal advice. We recommend review by a qualified professional.</em></p>";
    }
    return "<h2>1. General information</h2>\n<p><strong>{$host}</strong> is operated by <strong>{$id_line_en}</strong> (\"{$name}\").{$contact_en}</p>\n<h2>2. Acceptance of terms</h2>\n<p>By accessing and using this website you accept these terms and conditions. If you do not agree, please do not use the website.</p>\n<h2>3. Website content</h2>\n<p>The content of this website (texts, images, graphics, logos) is the property of {$name} or its partners and is protected by copyright law. Reproduction without written consent is prohibited.</p>\n<h2>4. Use of the website</h2>\n<p>You agree to use the website lawfully and in good faith, without disrupting its operation, attempting unauthorized access, or transmitting unlawful or harmful content.</p>\n<h2>5. Services and information</h2>\n<p>Published information is for informational purposes. {$name} makes reasonable efforts to keep it accurate but does not guarantee completeness or timeliness, and may change content without prior notice.</p>\n<h2>6. Limitation of liability</h2>\n<p>{$name} is not liable for direct or indirect damages resulting from the use of, or inability to use, this website, or from third-party actions (including linked websites).</p>\n<h2>7. Data protection</h2>\n<p>The processing of personal data is described in the Privacy Policy, and cookie preferences can be managed at any time here: [pcc_consent_link].</p>\n<h2>8. Governing law</h2>\n<p>These terms are governed by applicable law. Disputes will be resolved amicably or, failing that, by the competent courts. Consumers may also use the EU online dispute resolution platform (ec.europa.eu/consumers/odr).</p>\n<h2>9. Changes</h2>\n<p>{$name} may amend these terms; the current version is published on this page. Last updated: {$date}.</p>\n<p><em>This document is an automatically generated template based on company data and does not constitute legal advice. We recommend review by a qualified professional.</em></p>";
}

function pcc_legal_page_title( $type, $lang = '' ) {
    $lang = $lang ?: strtolower( substr( get_locale(), 0, 2 ) );
    $titles = array(
        'privacy' => array( 'ro' => 'Politica de Confidențialitate', 'en' => 'Privacy Policy', 'de' => 'Datenschutzerklärung', 'fr' => 'Politique de Confidentialité', 'it' => 'Informativa sulla Privacy', 'es' => 'Política de Privacidad' ),
        'terms'   => array( 'ro' => 'Termeni și Condiții', 'en' => 'Terms and Conditions', 'de' => 'AGB', 'fr' => 'Conditions Générales', 'it' => 'Termini e Condizioni', 'es' => 'Términos y Condiciones' ),
    );
    return $titles[ $type ][ $lang ] ?? $titles[ $type ]['en'];
}

// AJAX: save company data + selections
add_action( 'wp_ajax_pcc_save_legal', 'pcc_ajax_save_legal' );

function pcc_ajax_save_legal() {
    check_ajax_referer( 'pcc_legal_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $company = array();
    foreach ( pcc_company_defaults() as $key => $default ) {
        $val = sanitize_text_field( wp_unslash( $_POST[ 'company_' . $key ] ?? '' ) );
        if ( 'email' === $key ) {
            $val = sanitize_email( $val );
        } elseif ( 'website' === $key ) {
            $val = esc_url_raw( $val );
        }
        $company[ $key ] = $val;
    }
    update_option( 'pcc_company', $company, false );

    $legal = pcc_get_legal_settings();
    $legal['privacy']      = absint( $_POST['page_privacy'] ?? 0 );
    $legal['terms']        = absint( $_POST['page_terms'] ?? 0 );
    $legal['footer_links'] = empty( $_POST['footer_links'] ) ? 0 : 1;
    update_option( 'pcc_legal_pages', $legal, false );

    // Keep WP core in sync when a privacy page is chosen and core has none.
    if ( $legal['privacy'] && ! get_option( 'wp_page_for_privacy_policy' ) ) {
        update_option( 'wp_page_for_privacy_policy', $legal['privacy'] );
    }

    wp_send_json_success();
}

// AJAX: generate one legal page (duplicate-proof)
add_action( 'wp_ajax_pcc_generate_legal', 'pcc_ajax_generate_legal' );

function pcc_ajax_generate_legal() {
    check_ajax_referer( 'pcc_legal_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $type = sanitize_text_field( wp_unslash( $_POST['type'] ?? '' ) );
    if ( ! in_array( $type, array( 'privacy', 'terms' ), true ) ) {
        wp_send_json_error( 'Invalid type' );
    }

    $legal = pcc_get_legal_settings();

    // Optional language (multilingual sites): validated against the
    // languages Polylang actually has.
    $lang      = sanitize_text_field( wp_unslash( $_POST['lang'] ?? '' ) );
    $site_lang = pcc_site_languages();
    if ( $lang && ! in_array( $lang, $site_lang, true ) ) {
        wp_send_json_error( 'Unknown language' );
    }
    $is_default_lang = ! $lang || $lang === pcc_site_default_language();

    // 1) A page we generated before (even trashed): update / restore it.
    $ours = ( $lang && ! $is_default_lang )
        ? pcc_get_generated_legal_page_lang( $type, $lang, true )
        : pcc_get_generated_legal_page( $type, true );

    // 2) Otherwise: if an existing page is found for this language, refuse
    //    to generate — select the existing page instead. No duplicates.
    if ( ! $ours ) {
        $detected = ( $lang && ! $is_default_lang )
            ? pcc_resolve_legal_page( $type, $lang )
            : pcc_detect_legal_page( $type );
        if ( $detected ) {
            if ( $lang && ! $is_default_lang ) {
                $legal['i18n'][ $type ][ $lang ] = $detected;
            } else {
                $legal[ $type ] = $detected;
            }
            update_option( 'pcc_legal_pages', $legal, false );
            wp_send_json_success( array(
                'action' => 'selected_existing',
                'id'     => $detected,
                'title'  => get_the_title( $detected ),
                'link'   => get_permalink( $detected ),
                'notice' => 'An existing page was found and selected instead — no duplicate was created.',
            ) );
        }
    }

    $company  = pcc_get_company();
    $tpl_lang = $lang ?: '';
    $content  = pcc_legal_template( $type, $company, $tpl_lang );

    if ( $ours ) {
        if ( 'trash' === get_post_status( $ours->ID ) ) {
            wp_untrash_post( $ours->ID );
        }
        wp_update_post( array(
            'ID'           => $ours->ID,
            'post_content' => $content,
            'post_status'  => 'publish',
        ) );
        $page_id = $ours->ID;
        $action  = 'updated';
    } else {
        $page_id = wp_insert_post( array(
            'post_type'    => 'page',
            'post_status'  => 'publish',
            'post_title'   => pcc_legal_page_title( $type, $tpl_lang ),
            'post_content' => $content,
        ) );
        if ( is_wp_error( $page_id ) || ! $page_id ) {
            wp_send_json_error( 'Could not create page' );
        }
        update_post_meta( $page_id, '_pcc_legal_type', $type );
        $action = 'created';
    }

    // Language bookkeeping (Polylang): set the page language and link it
    // to the default-language page as a translation.
    $effective_lang = $lang ?: pcc_site_default_language();
    if ( $effective_lang ) {
        update_post_meta( $page_id, '_pcc_legal_lang', $effective_lang );
        if ( function_exists( 'pll_set_post_language' ) ) {
            pll_set_post_language( $page_id, $effective_lang );
        }
        $default_id = (int) ( $legal[ $type ] ?? 0 );
        if ( $default_id && $default_id !== $page_id && function_exists( 'pll_save_post_translations' ) && function_exists( 'pll_get_post_translations' ) ) {
            $translations = pll_get_post_translations( $default_id );
            $translations[ $effective_lang ] = $page_id;
            pll_save_post_translations( $translations );
        }
    }

    if ( $lang && ! $is_default_lang ) {
        $legal['i18n'][ $type ][ $lang ] = $page_id;
    } else {
        $legal[ $type ] = $page_id;
    }
    update_option( 'pcc_legal_pages', $legal, false );

    if ( 'privacy' === $type && $is_default_lang && ! get_option( 'wp_page_for_privacy_policy' ) ) {
        update_option( 'wp_page_for_privacy_policy', $page_id );
    }

    wp_send_json_success( array(
        'action' => $action,
        'id'     => $page_id,
        'title'  => get_the_title( $page_id ),
        'link'   => get_permalink( $page_id ),
        'edit'   => get_edit_post_link( $page_id, 'raw' ),
    ) );
}

// ---- Admin page ----

function pcc_admin_legal_page() {
    $company = pcc_get_company();
    $legal   = pcc_get_legal_settings();
    $nonce   = wp_create_nonce( 'pcc_legal_nonce' );

    $fields = array(
        'company_name' => array( 'Brand / Company Name', 'e.g. Example SRL' ),
        'cui'          => array( 'CUI / VAT Number', 'e.g. RO00000000' ),
        'reg_com'      => array( 'Trade Registry No. (optional)', 'e.g. J00/000/0000' ),
        'address'      => array( 'Registered Address', 'Street, city, country' ),
        'email'        => array( 'Contact Email', 'example@example.com' ),
        'phone'        => array( 'Phone (optional)', '' ),
        'website'      => array( 'Website URL', 'https://example.com' ),
    );

    $types = array(
        'privacy' => 'Privacy Policy',
        'terms'   => 'Terms &amp; Conditions',
    );
    ?>
    <div class="wrap pcc-admin-wrap">
        <h1>Legal Pages</h1>
        <p>Use the legal pages your site already has, or generate them from your company data. The plugin <strong>never creates duplicates</strong>: existing pages are detected and selected automatically, and generation is only offered when a page is genuinely missing.</p>

        <div class="pcc-design-section" style="margin-bottom:20px;">
            <h3>Company Data</h3>
            <p style="margin-top:0;">Used only when generating pages. Fill these in before generating.</p>
            <table class="form-table" style="margin-top:0;">
                <?php foreach ( $fields as $key => $f ) : ?>
                <tr>
                    <th style="padding:8px 10px 8px 0;"><?php echo esc_html( $f[0] ); ?></th>
                    <td style="padding:8px 0;"><input type="text" class="regular-text" id="pcc-company-<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $company[ $key ] ); ?>" placeholder="<?php echo esc_attr( $f[1] ); ?>"></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <?php foreach ( $types as $type => $label ) :
            $selected = (int) $legal[ $type ];
            $detected = $selected ? 0 : pcc_detect_legal_page( $type );
            $current  = $selected ?: $detected;
            $is_ours  = $current && get_post_meta( $current, '_pcc_legal_type', true ) === $type;
        ?>
        <div class="pcc-design-section" style="margin-bottom:20px;">
            <h3><?php echo $label; // phpcs:ignore ?></h3>
            <?php if ( $current ) : ?>
                <p style="margin-top:0;">
                    <?php if ( $selected ) : ?>Selected page:<?php else : ?><strong>Existing page detected</strong> and pre-selected (save to confirm):<?php endif; ?>
                    <strong><?php echo esc_html( get_the_title( $current ) ); ?></strong>
                    &mdash; <a href="<?php echo esc_url( get_permalink( $current ) ); ?>" target="_blank" rel="noopener">view</a>
                    &middot; <a href="<?php echo esc_url( get_edit_post_link( $current, 'raw' ) ); ?>" target="_blank" rel="noopener">edit</a>
                    <?php echo $is_ours ? ' <em>(generated by this plugin)</em>' : ' <em>(your existing page &mdash; the plugin will not touch it)</em>'; ?>
                </p>
            <?php else : ?>
                <p style="margin-top:0;color:#b45309;"><strong>No page found on this site.</strong> You can generate one below from your company data.</p>
            <?php endif; ?>
            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                <label>Page:&nbsp;
                <?php wp_dropdown_pages( array(
                    'name'              => 'pcc-page-' . $type,
                    'id'                => 'pcc-page-' . $type,
                    'selected'          => $current,
                    'show_option_none'  => '— none —',
                    'option_none_value' => '0',
                ) ); ?>
                </label>
                <button type="button" class="button pcc-generate-legal" data-type="<?php echo esc_attr( $type ); ?>" data-lang="" <?php disabled( (bool) $current && ! $is_ours ); ?>>
                    <?php echo $is_ours ? 'Regenerate content' : 'Generate page'; ?>
                </button>
                <?php if ( $current && ! $is_ours ) : ?>
                    <span style="color:#666;">Generation is disabled because a page already exists &mdash; no duplicates.</span>
                <?php endif; ?>
            </div>

            <?php
            // Multilingual sites (Polylang): one page per language.
            $pcc_langs = pcc_site_languages();
            $pcc_def   = pcc_site_default_language();
            if ( count( $pcc_langs ) > 1 ) :
            ?>
            <h4 style="margin:16px 0 6px;">Languages</h4>
            <table class="widefat striped" style="max-width:720px;">
                <thead><tr><th>Language</th><th>Page</th><th style="width:180px;">Action</th></tr></thead>
                <tbody>
                <?php foreach ( $pcc_langs as $pcc_l ) :
                    $is_def  = ( $pcc_l === $pcc_def );
                    $rid     = $is_def ? $current : pcc_resolve_legal_page( $type, $pcc_l );
                    $r_ours  = $rid && get_post_meta( $rid, '_pcc_legal_type', true ) === $type;
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( strtoupper( $pcc_l ) ); ?></strong><?php echo $is_def ? ' <em>(default)</em>' : ''; ?></td>
                        <td>
                            <?php if ( $rid ) : ?>
                                <?php echo esc_html( get_the_title( $rid ) ); ?>
                                &mdash; <a href="<?php echo esc_url( get_permalink( $rid ) ); ?>" target="_blank" rel="noopener">view</a>
                                &middot; <a href="<?php echo esc_url( get_edit_post_link( $rid, 'raw' ) ); ?>" target="_blank" rel="noopener">edit</a>
                                <?php echo $r_ours ? ' <em>(generated)</em>' : ' <em>(existing)</em>'; ?>
                            <?php else : ?>
                                <span style="color:#b45309;">missing</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ( $is_def ) : ?>
                                <span style="color:#666;">managed above</span>
                            <?php else : ?>
                                <button type="button" class="button pcc-generate-legal" data-type="<?php echo esc_attr( $type ); ?>" data-lang="<?php echo esc_attr( $pcc_l ); ?>" <?php disabled( (bool) $rid && ! $r_ours ); ?>>
                                    <?php echo $r_ours ? 'Regenerate' : 'Generate (' . esc_html( strtoupper( $pcc_l ) ) . ')'; ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="color:#666;margin-top:6px;">Generated translations are linked automatically as Polylang translations of the default-language page. Templates exist in Romanian and English; other languages use the English template with a localized title.</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <div class="pcc-design-section" style="margin-bottom:20px;">
            <h3>Footer Links</h3>
            <label>
                <input type="checkbox" id="pcc-legal-footer-links" <?php checked( ! empty( $legal['footer_links'] ) ); ?>>
                Add footer links for pages <strong>generated by this plugin</strong> (next to the Cookie Settings link).
            </label>
            <p style="color:#666;margin-bottom:0;">Pages that already existed on the site never get extra links from the plugin &mdash; your theme already links them. Only the Cookie Settings link is added in that case.</p>
        </div>

        <p>
            <button type="button" class="button button-primary button-hero" id="pcc-save-legal">Save Legal Settings</button>
            <span id="pcc-legal-msg" style="margin-left:10px;"></span>
        </p>

        <script>
        (function(){
            var nonce = '<?php echo esc_js( $nonce ); ?>';
            var ajax  = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
            var msg   = document.getElementById('pcc-legal-msg');

            function collect() {
                var fd = new FormData();
                fd.append('nonce', nonce);
                <?php foreach ( array_keys( $fields ) as $key ) : ?>
                fd.append('company_<?php echo esc_js( $key ); ?>', document.getElementById('pcc-company-<?php echo esc_js( $key ); ?>').value);
                <?php endforeach; ?>
                fd.append('page_privacy', document.getElementById('pcc-page-privacy').value);
                fd.append('page_terms', document.getElementById('pcc-page-terms').value);
                fd.append('footer_links', document.getElementById('pcc-legal-footer-links').checked ? 1 : 0);
                return fd;
            }

            document.getElementById('pcc-save-legal').addEventListener('click', function(){
                var fd = collect();
                fd.append('action', 'pcc_save_legal');
                msg.textContent = 'Saving…';
                fetch(ajax, {method:'POST', body:fd}).then(function(r){return r.json();}).then(function(res){
                    msg.textContent = res.success ? 'Saved!' : 'Error saving';
                    setTimeout(function(){ msg.textContent=''; }, 2500);
                });
            });

            document.querySelectorAll('.pcc-generate-legal').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var save = collect();
                    save.append('action', 'pcc_save_legal');
                    btn.disabled = true;
                    msg.textContent = 'Generating…';
                    fetch(ajax, {method:'POST', body:save}).then(function(){
                        var fd = new FormData();
                        fd.append('action', 'pcc_generate_legal');
                        fd.append('nonce', nonce);
                        fd.append('type', btn.dataset.type);
                        fd.append('lang', btn.dataset.lang || '');
                        return fetch(ajax, {method:'POST', body:fd});
                    }).then(function(r){return r.json();}).then(function(res){
                        if (res.success) {
                            msg.textContent = res.data.notice || ('Done: ' + res.data.title);
                            setTimeout(function(){ window.location.reload(); }, 1200);
                        } else {
                            msg.textContent = 'Error: ' + (res.data || 'unknown');
                            btn.disabled = false;
                        }
                    });
                });
            });
        })();
        </script>
    </div>
    <?php
}

// =========================================================================
//  ADMIN: Menu, Dashboard & Scanner pages
// =========================================================================

add_action( 'admin_menu', 'pcc_admin_menu' );

function pcc_admin_menu() {
    add_menu_page(
        'Cookie Consent',
        'Cookie Consent',
        'manage_options',
        'pcc-cookie-stats',
        'pcc_admin_stats_page',
        'dashicons-chart-pie',
        81
    );

    add_submenu_page(
        'pcc-cookie-stats',
        'Consent Statistics',
        'Statistics',
        'manage_options',
        'pcc-cookie-stats',
        'pcc_admin_stats_page'
    );

    add_submenu_page(
        'pcc-cookie-stats',
        'Cookie Scanner',
        'Cookie Scanner',
        'manage_options',
        'pcc-cookie-scanner',
        'pcc_admin_scanner_page'
    );

    add_submenu_page(
        'pcc-cookie-stats',
        'Banner Design',
        'Banner Design',
        'manage_options',
        'pcc-card-design',
        'pcc_admin_design_page'
    );

    add_submenu_page(
        'pcc-cookie-stats',
        'Translations',
        'Translations',
        'manage_options',
        'pcc-translations',
        'pcc_admin_translations_page'
    );

    add_submenu_page(
        'pcc-cookie-stats',
        'Legal Pages',
        'Legal Pages',
        'manage_options',
        'pcc-legal-pages',
        'pcc_admin_legal_page'
    );
}

add_action( 'admin_enqueue_scripts', 'pcc_admin_assets' );

function pcc_admin_assets( $hook ) {
    $stats_hook   = 'toplevel_page_pcc-cookie-stats';
    $scanner_hook = 'cookie-consent_page_pcc-cookie-scanner';
    $design_hook  = 'cookie-consent_page_pcc-card-design';
    $trans_hook   = 'cookie-consent_page_pcc-translations';
    $legal_hook   = 'cookie-consent_page_pcc-legal-pages';

    if ( ! in_array( $hook, array( $stats_hook, $scanner_hook, $design_hook, $trans_hook, $legal_hook ), true ) ) {
        return;
    }

    wp_enqueue_style( 'pcc-admin-style', PCC_PLUGIN_URL . 'assets/css/admin.css', array(), PCC_VERSION );

    if ( $hook === $stats_hook ) {
        wp_enqueue_script( 'pcc-chart-js', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', array(), '4.4.7', true );
        wp_enqueue_script( 'pcc-admin-script', PCC_PLUGIN_URL . 'assets/js/admin.js', array( 'pcc-chart-js' ), PCC_VERSION, true );
        wp_localize_script( 'pcc-admin-script', 'pccStats', pcc_get_stats_data() );
    }

    if ( $hook === $scanner_hook ) {
        wp_enqueue_script( 'pcc-scanner-script', PCC_PLUGIN_URL . 'assets/js/scanner.js', array(), PCC_VERSION, true );
        $scan = get_option( 'pcc_scan_results', array() );
        wp_localize_script( 'pcc-scanner-script', 'pccScanner', array(
            'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'pcc_scanner_nonce' ),
            'scanData'   => $scan,
        ));
    }

    if ( $hook === $trans_hook ) {
        wp_enqueue_script( 'pcc-translations-script', PCC_PLUGIN_URL . 'assets/js/translations.js', array(), PCC_VERSION, true );
        wp_localize_script( 'pcc-translations-script', 'pccTrans', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pcc_translations_nonce' ),
            'current'  => pcc_get_translations(),
            'defaults' => pcc_translations_defaults(),
        ));
    }

    if ( $hook === $design_hook ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_style( 'pcc-card-style', PCC_PLUGIN_URL . 'assets/css/banner.css', array(), PCC_VERSION );
        wp_enqueue_script( 'pcc-design-script', PCC_PLUGIN_URL . 'assets/js/design.js', array( 'wp-color-picker' ), PCC_VERSION, true );
        wp_localize_script( 'pcc-design-script', 'pccDesign', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'pcc_design_nonce' ),
            'current'  => pcc_get_design(),
            'defaults' => pcc_design_defaults(),
        ));
    }
}

// ---- Stats data helper (unchanged) ----

function pcc_get_stats_data() {
    global $wpdb;
    $table = $wpdb->prefix . 'pcc_consent_log';

    $table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) );
    if ( ! $table_exists ) {
        return array(
            'total' => 0, 'acceptAll' => 0, 'rejectAll' => 0, 'savePrefs' => 0,
            'statsGranted' => 0, 'statsDenied' => 0,
            'mktGranted' => 0, 'mktDenied' => 0,
            'daily' => array(), 'uniqueVisitors' => 0,
        );
    }

    $period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : '30';
    $days   = absint( $period ) ?: 30;
    $since  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

    $total          = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE created_at >= %s", $since ) );
    $accept_all     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE action = 'accept_all' AND created_at >= %s", $since ) );
    $reject_all     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE action = 'reject_all' AND created_at >= %s", $since ) );
    $save_prefs     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE action = 'save_preferences' AND created_at >= %s", $since ) );
    $stats_granted  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE statistics = 1 AND created_at >= %s", $since ) );
    $mkt_granted    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE marketing = 1 AND created_at >= %s", $since ) );
    $unique_visitors = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT visitor_hash) FROM $table WHERE created_at >= %s", $since ) );

    $daily_rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(created_at) AS day,
                SUM(action = 'accept_all') AS accepted,
                SUM(action = 'reject_all') AS rejected,
                SUM(action = 'save_preferences') AS custom
         FROM $table WHERE created_at >= %s
         GROUP BY DATE(created_at) ORDER BY day ASC", $since
    ) );

    $daily = array();
    foreach ( $daily_rows as $row ) {
        $daily[] = array( 'day' => $row->day, 'accepted' => (int) $row->accepted, 'rejected' => (int) $row->rejected, 'custom' => (int) $row->custom );
    }

    return array(
        'total' => $total, 'acceptAll' => $accept_all, 'rejectAll' => $reject_all,
        'savePrefs' => $save_prefs, 'statsGranted' => $stats_granted,
        'statsDenied' => $total - $stats_granted, 'mktGranted' => $mkt_granted,
        'mktDenied' => $total - $mkt_granted, 'daily' => $daily,
        'uniqueVisitors' => $unique_visitors, 'days' => $days,
    );
}

// ---- Stats admin page ----

function pcc_admin_stats_page() {
    $period = isset( $_GET['period'] ) ? absint( $_GET['period'] ) : 30;
    ?>
    <div class="wrap pcc-admin-wrap">
        <h1>Cookie Consent Statistics</h1>

        <div class="pcc-period-selector">
            <span>Period:</span>
            <?php
            $periods = array( 7 => '7 Days', 30 => '30 Days', 90 => '90 Days', 365 => '1 Year' );
            foreach ( $periods as $val => $label ) {
                $active = $period === $val ? ' pcc-active' : '';
                printf( '<a href="%s" class="pcc-period-btn%s">%s</a>',
                    esc_url( admin_url( "admin.php?page=pcc-cookie-stats&period=$val" ) ),
                    esc_attr( $active ), esc_html( $label ) );
            } ?>
        </div>

        <div class="pcc-cards">
            <div class="pcc-card pcc-card-total"><div class="pcc-card-icon"><span class="dashicons dashicons-groups"></span></div><div class="pcc-card-body"><span class="pcc-card-value" id="pcc-total">-</span><span class="pcc-card-label">Total Interactions</span></div></div>
            <div class="pcc-card pcc-card-unique"><div class="pcc-card-icon"><span class="dashicons dashicons-admin-users"></span></div><div class="pcc-card-body"><span class="pcc-card-value" id="pcc-unique">-</span><span class="pcc-card-label">Unique Visitors</span></div></div>
            <div class="pcc-card pcc-card-accept"><div class="pcc-card-icon"><span class="dashicons dashicons-yes-alt"></span></div><div class="pcc-card-body"><span class="pcc-card-value" id="pcc-accepted">-</span><span class="pcc-card-label">Accept All</span></div></div>
            <div class="pcc-card pcc-card-reject"><div class="pcc-card-icon"><span class="dashicons dashicons-dismiss"></span></div><div class="pcc-card-body"><span class="pcc-card-value" id="pcc-rejected">-</span><span class="pcc-card-label">Reject All</span></div></div>
            <div class="pcc-card pcc-card-custom"><div class="pcc-card-icon"><span class="dashicons dashicons-admin-generic"></span></div><div class="pcc-card-body"><span class="pcc-card-value" id="pcc-custom">-</span><span class="pcc-card-label">Custom Preferences</span></div></div>
        </div>

        <div class="pcc-charts-grid">
            <div class="pcc-chart-card"><h3>Consent Actions Breakdown</h3><div class="pcc-chart-container"><canvas id="pcc-actions-chart"></canvas></div></div>
            <div class="pcc-chart-card"><h3>Statistics Cookies</h3><div class="pcc-chart-container"><canvas id="pcc-stats-chart"></canvas></div></div>
            <div class="pcc-chart-card"><h3>Marketing Cookies</h3><div class="pcc-chart-container"><canvas id="pcc-mkt-chart"></canvas></div></div>
            <div class="pcc-chart-card pcc-chart-wide"><h3>Daily Consent Trend</h3><div class="pcc-chart-container pcc-chart-wide-container"><canvas id="pcc-daily-chart"></canvas></div></div>
        </div>

        <div class="pcc-rate-section">
            <h3>Consent Rates</h3>
            <div class="pcc-rate-bars">
                <div class="pcc-rate-row"><span class="pcc-rate-label">Overall Accept Rate</span><div class="pcc-rate-bar"><div class="pcc-rate-fill pcc-rate-green" id="pcc-rate-accept"></div></div><span class="pcc-rate-pct" id="pcc-rate-accept-pct">-</span></div>
                <div class="pcc-rate-row"><span class="pcc-rate-label">Statistics Consent</span><div class="pcc-rate-bar"><div class="pcc-rate-fill pcc-rate-blue" id="pcc-rate-stats"></div></div><span class="pcc-rate-pct" id="pcc-rate-stats-pct">-</span></div>
                <div class="pcc-rate-row"><span class="pcc-rate-label">Marketing Consent</span><div class="pcc-rate-bar"><div class="pcc-rate-fill pcc-rate-orange" id="pcc-rate-mkt"></div></div><span class="pcc-rate-pct" id="pcc-rate-mkt-pct">-</span></div>
            </div>
        </div>
    </div>
    <?php
}

// ---- Scanner admin page ----

function pcc_admin_scanner_page() {
    ?>
    <div class="wrap pcc-admin-wrap">
        <h1>Cookie Scanner</h1>
        <p class="pcc-scanner-intro">Scan your website (up to 100 pages) to automatically discover cookies set by your server and detect known tracking scripts. Results are displayed in the cookie consent preferences panel on the frontend.</p>

        <div class="pcc-scanner-controls">
            <button id="pcc-start-scan" class="button button-primary button-hero">
                <span class="dashicons dashicons-search" style="margin-top:4px;margin-right:4px;"></span>
                Scan Website for Cookies
            </button>
            <div id="pcc-scan-progress" style="display:none;">
                <div class="pcc-progress-bar"><div class="pcc-progress-fill" id="pcc-progress-fill"></div></div>
                <span id="pcc-scan-status">Scanning...</span>
            </div>
        </div>

        <?php
        $scan = get_option( 'pcc_scan_results', array() );
        if ( ! empty( $scan['scanned_at'] ) ) :
        ?>
        <div class="pcc-scan-meta">
            <span>Last scan: <strong><?php echo esc_html( wp_date( 'M j, Y \a\t H:i', strtotime( $scan['scanned_at'] ) ) ); ?></strong></span>
            <span>Pages scanned: <strong><?php echo esc_html( $scan['pages_scanned'] ?? 0 ); ?></strong></span>
            <span>Cookies found: <strong><?php echo esc_html( count( $scan['cookies'] ?? array() ) ); ?></strong></span>
        </div>
        <?php endif; ?>

        <!-- Prebuilt cookie library -->
        <div id="pcc-prebuilt-section">
            <h2>Quick Add — Cookie Library</h2>
            <p>Click a platform to instantly add all its cookies to your list. Only cookies not already in your list will be added.</p>
            <div id="pcc-prebuilt-grid" class="pcc-prebuilt-grid"></div>
        </div>

        <!-- Cookie list table (editable) -->
        <div id="pcc-cookie-list-section">
            <h2>Detected Cookies</h2>
            <p>You can edit, add, or remove cookies. Changes are saved to the consent banner automatically.</p>
            <table class="wp-list-table widefat striped" id="pcc-admin-cookie-table">
                <thead>
                    <tr>
                        <th style="width:180px;">Cookie Name</th>
                        <th style="width:140px;">Provider</th>
                        <th style="width:120px;">Category</th>
                        <th style="width:100px;">Duration</th>
                        <th>Description</th>
                        <th style="width:60px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="pcc-cookie-tbody"></tbody>
            </table>
            <div class="pcc-ui-table-actions">
                <button id="pcc-add-cookie" class="button">+ Add Cookie</button>
                <button id="pcc-save-cookies" class="button button-primary">Save Cookie List</button>
            </div>
        </div>
    </div>
    <?php
}

// ---- Translations admin page ----

function pcc_admin_translations_page() {
    $trans = pcc_get_translations();
    $method = $trans['detection_method'];
    $default_lang = $trans['default_lang'];

    $string_fields = array(
        'tag'                => 'Top Badge Text',
        'heading'            => 'Banner Heading',
        'body'               => 'Banner Body Text',
        'btn_accept'         => 'Accept Button',
        'btn_reject'         => 'Reject Button',
        'btn_customize'      => 'Customize Button',
        'pref_heading'       => 'Preferences Heading',
        'pref_intro'         => 'Preferences Intro Text',
        'cat_necessary'      => 'Necessary Category Name',
        'cat_necessary_desc' => 'Necessary Description',
        'cat_analytics'      => 'Analytics Category Name',
        'cat_analytics_desc' => 'Analytics Description',
        'cat_marketing'      => 'Marketing Category Name',
        'cat_marketing_desc' => 'Marketing Description',
        'always_active'      => 'Always Active Badge',
        'btn_save'           => 'Save Button',
        'btn_cancel'         => 'Cancel Button',
        'footer_link'        => 'Consent Reopen Link Text',
    );

    $langs = array( 'en' => 'English', 'fr' => 'Fran&ccedil;ais', 'de' => 'Deutsch', 'it' => 'Italiano', 'ro' => 'Rom&acirc;n&#259;', 'es' => 'Espa&ntilde;ol' );
    ?>
    <div class="wrap pcc-admin-wrap">
        <h1>Banner Translations</h1>
        <p>Configure language detection and edit all banner texts in English, French, and German.</p>

        <!-- Detection Method -->
        <div class="pcc-trans-section">
            <h2>Language Detection</h2>
            <p class="pcc-trans-desc">Choose how the banner determines which language to display.</p>

            <div class="pcc-trans-methods">
                <label class="pcc-trans-method<?php echo $method === 'subfolder' ? ' pcc-method-active' : ''; ?>">
                    <input type="radio" name="pcc_detection" value="subfolder" <?php checked( $method, 'subfolder' ); ?>>
                    <div class="pcc-method-content">
                        <strong>URL Subfolder</strong>
                        <span>Detects language from the URL path: <code>/en/</code>, <code>/fr/</code>, <code>/de/</code></span>
                        <span class="pcc-method-example">e.g. example.com<strong>/fr/</strong>page &rarr; French</span>
                    </div>
                </label>
                <label class="pcc-trans-method<?php echo $method === 'html_lang' ? ' pcc-method-active' : ''; ?>">
                    <input type="radio" name="pcc_detection" value="html_lang" <?php checked( $method, 'html_lang' ); ?>>
                    <div class="pcc-method-content">
                        <strong>HTML lang Attribute</strong>
                        <span>Detects from <code>&lt;html lang="fr"&gt;</code> or <code>&lt;html lang="fr-FR"&gt;</code></span>
                        <span class="pcc-method-example">Works with WPML, Polylang, TranslatePress, etc.</span>
                    </div>
                </label>
            </div>

            <div class="pcc-trans-default">
                <label><strong>Default Language</strong> (fallback when no language is detected):</label>
                <select id="pcc-default-lang">
                    <?php foreach ( $langs as $code => $name ) : ?>
                        <option value="<?php echo esc_attr( $code ); ?>" <?php selected( $default_lang, $code ); ?>><?php echo $name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Language tabs -->
        <div class="pcc-trans-section">
            <h2>Texts</h2>
            <div class="pcc-lang-tabs">
                <?php $first = true; foreach ( $langs as $code => $name ) : ?>
                    <button type="button" class="pcc-lang-tab<?php echo $first ? ' pcc-lang-tab-active' : ''; ?>" data-lang="<?php echo esc_attr( $code ); ?>">
                        <?php echo $name; ?>
                    </button>
                <?php $first = false; endforeach; ?>
            </div>

            <?php $first = true; foreach ( $langs as $code => $name ) : ?>
            <div class="pcc-lang-panel" id="pcc-lang-<?php echo esc_attr( $code ); ?>" style="<?php echo $first ? '' : 'display:none;'; ?>">
                <table class="form-table pcc-trans-table">
                    <?php foreach ( $string_fields as $key => $label ) :
                        $val = $trans['strings'][ $code ][ $key ] ?? '';
                        $is_long = in_array( $key, array( 'body', 'pref_intro', 'cat_necessary_desc', 'cat_analytics_desc', 'cat_marketing_desc' ), true );
                    ?>
                    <tr>
                        <th><?php echo esc_html( $label ); ?></th>
                        <td>
                            <?php if ( $is_long ) : ?>
                                <textarea class="pcc-trans-input large-text" data-lang="<?php echo esc_attr( $code ); ?>" data-key="<?php echo esc_attr( $key ); ?>" rows="3"><?php echo esc_textarea( $val ); ?></textarea>
                            <?php else : ?>
                                <input type="text" class="pcc-trans-input regular-text" data-lang="<?php echo esc_attr( $code ); ?>" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php $first = false; endforeach; ?>
        </div>

        <div class="pcc-trans-actions">
            <button id="pcc-save-translations" class="button button-primary button-hero">Save Translations</button>
            <button id="pcc-reset-translations" class="button button-hero">Reset to Defaults</button>
        </div>
    </div>
    <?php
}

// ---- Banner Design admin page ----

function pcc_admin_design_page() {
    $d = pcc_get_design();
    $fields = array(
        'Banner Background & Text' => array(
            array( 'banner_bg',      'Background' ),
            array( 'banner_border',  'Border' ),
            array( 'overlay_bg',     'Overlay' ),
            array( 'heading_color',  'Heading "We respect..." Color' ),
            array( 'text_color',     'Body Text Color' ),
        ),
        'Preferences Panel Heading' => array(
            array( 'prefs_heading_color', '"Cookie Preferences" Color' ),
            array( 'close_btn_bg',        'Close Button Background' ),
            array( 'close_btn_color',     'Close Button Icon' ),
            array( 'close_btn_hover',     'Close Button Hover' ),
        ),
        'Top Badge'          => array(
            array( 'tag_bg',     'Background' ),
            array( 'tag_border', 'Border' ),
            array( 'tag_text',   'Text' ),
        ),
        'Accept Button'      => array(
            array( 'btn_accept_bg',    'Background' ),
            array( 'btn_accept_text',  'Text' ),
            array( 'btn_accept_hover', 'Hover Background' ),
        ),
        'Reject Button'      => array(
            array( 'btn_reject_bg',    'Background' ),
            array( 'btn_reject_text',  'Text' ),
            array( 'btn_reject_hover', 'Hover Background' ),
        ),
        'Customize Button'   => array(
            array( 'btn_prefs_bg',    'Background' ),
            array( 'btn_prefs_text',  'Text' ),
            array( 'btn_prefs_hover', 'Hover Background' ),
        ),
        'Save Button'        => array(
            array( 'btn_save_bg',    'Background' ),
            array( 'btn_save_text',  'Text' ),
            array( 'btn_save_hover', 'Hover Background' ),
        ),
        'Cancel Button'      => array(
            array( 'btn_cancel_bg',    'Background' ),
            array( 'btn_cancel_text',  'Text' ),
            array( 'btn_cancel_hover', 'Hover Background' ),
        ),
        'Category Cards'     => array(
            array( 'card_bg',     'Card Background' ),
            array( 'card_border', 'Card Border' ),
        ),
        'Toggle Switch'      => array(
            array( 'toggle_on',  'On State' ),
            array( 'toggle_off', 'Off State' ),
        ),
        'Badges & Tags'      => array(
            array( 'badge_bg',   'Background' ),
            array( 'badge_text', 'Text' ),
        ),
        'Cookie Table'       => array(
            array( 'table_code_bg',   'Code Background' ),
            array( 'table_code_text', 'Code Text' ),
        ),
        'Details Arrow'      => array(
            array( 'expand_btn_bg',    'Background' ),
            array( 'expand_btn_color', 'Arrow Color' ),
        ),
        'Floating Button'    => array(
            array( 'reopen_bg',   'Background' ),
            array( 'reopen_text', 'Icon' ),
        ),
    );
    ?>
    <?php
    $reopen_method = $d['reopen_method'] ?? 'icon';
    if ( ! in_array( $reopen_method, array( 'icon', 'footer_link', 'both' ), true ) ) {
        $reopen_method = 'icon';
    }
    ?>
    <div class="wrap pcc-admin-wrap">
        <h1>Banner Design</h1>
        <p>Customize every color of your cookie banner. Changes apply instantly on your site after saving.</p>

        <!-- Reopen consent method -->
        <div class="pcc-design-section" style="margin-bottom:20px;">
            <h3>Reopen Consent — how visitors change their choice later</h3>
            <p style="margin-top:0;">Choose how visitors can reopen the banner to update their consent (required by GDPR).</p>
            <div class="pcc-trans-methods" style="display:flex;gap:16px;flex-wrap:wrap;">
                <label class="pcc-trans-method<?php echo 'icon' === $reopen_method ? ' pcc-method-active' : ''; ?>">
                    <input type="radio" name="pcc_reopen_method" value="icon" <?php checked( $reopen_method, 'icon' ); ?>>
                    <div class="pcc-method-content">
                        <strong>Floating Icon</strong>
                        <span>A small round button fixed in the bottom-left corner, always visible after consent is given.</span>
                    </div>
                </label>
                <label class="pcc-trans-method<?php echo 'footer_link' === $reopen_method ? ' pcc-method-active' : ''; ?>">
                    <input type="radio" name="pcc_reopen_method" value="footer_link" <?php checked( $reopen_method, 'footer_link' ); ?>>
                    <div class="pcc-method-content">
                        <strong>Footer Link</strong>
                        <span>A translated text link (e.g. &ldquo;Cookie Settings&rdquo;) inserted automatically into your theme&rsquo;s footer links &mdash; the footer menu or copyright row &mdash; inheriting the theme&rsquo;s styling. Falls back to a discreet bar at the very bottom if the theme has no footer links area. Edit the text under <em>Translations</em>.</span>
                    </div>
                </label>
                <label class="pcc-trans-method<?php echo 'both' === $reopen_method ? ' pcc-method-active' : ''; ?>">
                    <input type="radio" name="pcc_reopen_method" value="both" <?php checked( $reopen_method, 'both' ); ?>>
                    <div class="pcc-method-content">
                        <strong>Both</strong>
                        <span>Show the floating icon and the footer link.</span>
                    </div>
                </label>
            </div>
            <p style="margin-bottom:0;color:#666;">Tip: you can also place the link yourself anywhere (footer menu, widget, privacy page) with the shortcode <code>[pcc_consent_link]</code> or by adding the CSS class <code>pcc-plink</code> to any link &mdash; those work with every option above. Remember to click <strong>Save Design</strong> below.</p>
        </div>

        <!-- Google Tag Manager blocking mode -->
        <?php
        $gtm_blocking = $d['gtm_blocking'] ?? 'advanced';
        if ( ! in_array( $gtm_blocking, array( 'advanced', 'basic' ), true ) ) {
            $gtm_blocking = 'advanced';
        }
        ?>
        <div class="pcc-design-section" style="margin-bottom:20px;">
            <h3>Google Tag Manager &mdash; blocking mode</h3>
            <p style="margin-top:0;">The plugin never has to block the GTM script for GDPR compliance: with Consent Mode v2, GTM loads but every Google tag stays cookieless until consent. Choose how strict you want to be.</p>
            <div class="pcc-trans-methods" style="display:flex;gap:16px;flex-wrap:wrap;">
                <label class="pcc-trans-method<?php echo 'advanced' === $gtm_blocking ? ' pcc-method-active' : ''; ?>">
                    <input type="radio" name="pcc_gtm_blocking" value="advanced" <?php checked( $gtm_blocking, 'advanced' ); ?>>
                    <div class="pcc-method-content">
                        <strong>Advanced Consent Mode (recommended, default)</strong>
                        <span>GTM loads immediately on every page. Consent Mode signals control the tags: before consent, Google tags send only cookieless pings; after consent, full tracking. Best for data quality (enables behavioral modeling in GA4).</span>
                    </div>
                </label>
                <label class="pcc-trans-method<?php echo 'basic' === $gtm_blocking ? ' pcc-method-active' : ''; ?>">
                    <input type="radio" name="pcc_gtm_blocking" value="basic" <?php checked( $gtm_blocking, 'basic' ); ?>>
                    <div class="pcc-method-content">
                        <strong>Basic Consent Mode (hard block)</strong>
                        <span>The GTM/gtag script itself is prevented from loading until the visitor grants Statistics or Marketing consent. No requests to googletagmanager.com before consent &mdash; but you lose all data from visitors who reject or ignore the banner.</span>
                    </div>
                </label>
            </div>
        </div>

        <!-- GitHub updates -->
        <?php
        $gh_token      = get_option( 'pcc_github_token', '' );
        $remote_ver    = pcc_get_remote_version();
        $update_status = $remote_ver
            ? ( version_compare( $remote_ver, PCC_VERSION, '>' )
                ? '<span style="color:#b45309;">Update available: v' . esc_html( $remote_ver ) . ' (installed: v' . esc_html( PCC_VERSION ) . ') &mdash; install it from the <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">Plugins page</a>.</span>'
                : '<span style="color:#15803d;">Up to date (v' . esc_html( PCC_VERSION ) . ').</span>' )
            : '<span style="color:#b91c1c;">GitHub not reachable &mdash; the repository is private. Paste a token below, or make the repo public.</span>';
        ?>
        <div class="pcc-design-section" style="margin-bottom:20px;">
            <h3>Plugin Updates from GitHub</h3>
            <p style="margin-top:0;">This plugin updates itself from <a href="https://github.com/<?php echo esc_attr( PCC_GITHUB_REPO ); ?>" target="_blank" rel="noopener">github.com/<?php echo esc_html( PCC_GITHUB_REPO ); ?></a> (main branch). When a new version is pushed, a normal WordPress update notice appears on the Plugins page &mdash; no manual zip uploads.</p>
            <p><strong>Status:</strong> <?php echo $update_status; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
            <p style="margin-bottom:6px;"><strong>GitHub access token</strong> (only needed while the repository is private; create one at GitHub &rarr; Settings &rarr; Developer settings &rarr; Personal access tokens, with read access to this repo):</p>
            <div style="display:flex;gap:8px;align-items:center;max-width:560px;">
                <input type="password" id="pcc-gh-token" class="regular-text" style="flex:1;" placeholder="<?php echo $gh_token ? 'Token saved — enter a new one to replace, save empty to remove' : 'ghp_… or github_pat_…'; ?>" autocomplete="off">
                <button type="button" class="button" id="pcc-save-gh-token">Save Token</button>
                <span id="pcc-gh-token-msg"></span>
            </div>

            <?php
            // ---- One-click version switch / rollback ----
            $pcc_tags     = pcc_get_github_tags();
            $pcc_pin      = get_option( 'pcc_pin_version', '' );
            $pcc_rb_base  = wp_nonce_url( admin_url( 'admin-post.php?action=pcc_rollback' ), 'pcc_rollback' );
            ?>
            <hr style="margin:16px 0;">
            <p style="margin-bottom:6px;"><strong>Version switch / rollback</strong> &mdash; install any released version with one click. Settings and statistics are kept (they live in the database).</p>
            <?php if ( $pcc_pin ) : ?>
                <p style="color:#b45309;"><strong>Updates paused:</strong> pinned to version <?php echo esc_html( $pcc_pin ); ?>.
                    <a class="button" href="<?php echo esc_url( $pcc_rb_base . '&version=latest' ); ?>">Resume updates (back to latest)</a>
                </p>
            <?php endif; ?>
            <?php if ( $pcc_tags ) : $pcc_changelog = pcc_get_changelog_map(); ?>
            <div style="display:flex;gap:8px;align-items:center;">
                <select id="pcc-rollback-version" style="max-width:560px;">
                    <?php foreach ( $pcc_tags as $pcc_tag ) :
                        $pcc_num   = ltrim( $pcc_tag, 'vV' );
                        $pcc_desc  = isset( $pcc_changelog[ $pcc_num ]['summary'] ) ? $pcc_changelog[ $pcc_num ]['summary'] : '';
                        if ( function_exists( 'mb_substr' ) && mb_strlen( $pcc_desc ) > 80 ) {
                            $pcc_desc = mb_substr( $pcc_desc, 0, 77 ) . '…';
                        }
                        $pcc_label = $pcc_tag
                            . ( $pcc_num === PCC_VERSION ? ' (installed)' : '' )
                            . ( $pcc_desc ? ' — ' . $pcc_desc : '' );
                    ?>
                        <option value="<?php echo esc_attr( $pcc_tag ); ?>"><?php echo esc_html( $pcc_label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <a class="button" href="#" id="pcc-rollback-go">Install selected version</a>
            </div>
            <script>
            document.getElementById('pcc-rollback-go').addEventListener('click', function(e){
                var v = document.getElementById('pcc-rollback-version').value;
                if (!confirm('Install version ' + v + '? Updates will be paused (pinned) until you resume them.')) { e.preventDefault(); return; }
                this.href = '<?php echo esc_url_raw( $pcc_rb_base ); ?>' + '&version=' + encodeURIComponent(v);
            });
            </script>
            <?php else : ?>
                <p style="color:#666;">Version list unavailable right now (GitHub unreachable).</p>
            <?php endif; ?>
            <script>
            (function(){
                var btn = document.getElementById('pcc-save-gh-token');
                if (!btn) return;
                btn.addEventListener('click', function(){
                    var fd = new FormData();
                    fd.append('action', 'pcc_save_gh_token');
                    fd.append('nonce', '<?php echo esc_js( wp_create_nonce( 'pcc_design_nonce' ) ); ?>');
                    fd.append('token', document.getElementById('pcc-gh-token').value);
                    btn.disabled = true;
                    fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {method:'POST', body:fd})
                        .then(function(r){ return r.json(); })
                        .then(function(res){
                            document.getElementById('pcc-gh-token-msg').textContent = res.success ? 'Saved — reloading…' : 'Error saving';
                            if (res.success) { setTimeout(function(){ window.location.reload(); }, 800); }
                            btn.disabled = false;
                        })
                        .catch(function(){ btn.disabled = false; });
                });
            })();
            </script>
        </div>

        <div class="pcc-design-layout">
            <!-- Color controls -->
            <div class="pcc-design-controls">
                <?php foreach ( $fields as $section => $items ) : ?>
                <div class="pcc-design-section">
                    <h3><?php echo esc_html( $section ); ?></h3>
                    <div class="pcc-design-fields">
                        <?php foreach ( $items as $item ) :
                            $key = $item[0];
                            $label = $item[1];
                            $val = $d[ $key ];
                        ?>
                        <div class="pcc-design-field">
                            <label><?php echo esc_html( $label ); ?></label>
                            <input type="text" class="pcc-color-picker" data-key="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>">
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="pcc-design-section">
                    <h3>Sizes</h3>
                    <div class="pcc-design-fields">
                        <div class="pcc-design-field pcc-design-field-wide">
                            <label>Corner Radius</label>
                            <input type="range" class="pcc-slider" data-key="banner_radius" min="0" max="30" value="<?php echo esc_attr( $d['banner_radius'] ); ?>">
                            <span class="pcc-slider-val" data-for="banner_radius"><?php echo esc_attr( $d['banner_radius'] ); ?>px</span>
                        </div>
                        <div class="pcc-design-field pcc-design-field-wide">
                            <label>Banner Heading</label>
                            <input type="range" class="pcc-slider" data-key="heading_font_size" min="14" max="36" value="<?php echo esc_attr( $d['heading_font_size'] ); ?>">
                            <span class="pcc-slider-val" data-for="heading_font_size"><?php echo esc_attr( $d['heading_font_size'] ); ?>px</span>
                        </div>
                        <div class="pcc-design-field pcc-design-field-wide">
                            <label>Body Text</label>
                            <input type="range" class="pcc-slider" data-key="text_font_size" min="11" max="20" value="<?php echo esc_attr( $d['text_font_size'] ); ?>">
                            <span class="pcc-slider-val" data-for="text_font_size"><?php echo esc_attr( $d['text_font_size'] ); ?>px</span>
                        </div>
                        <div class="pcc-design-field pcc-design-field-wide">
                            <label>Prefs Heading</label>
                            <input type="range" class="pcc-slider" data-key="prefs_heading_size" min="14" max="36" value="<?php echo esc_attr( $d['prefs_heading_size'] ); ?>">
                            <span class="pcc-slider-val" data-for="prefs_heading_size"><?php echo esc_attr( $d['prefs_heading_size'] ); ?>px</span>
                        </div>
                    </div>
                </div>

                <div class="pcc-design-actions">
                    <button id="pcc-save-design" class="button button-primary button-hero">Save Design</button>
                    <button id="pcc-reset-design" class="button button-hero">Reset to Defaults</button>
                </div>
            </div>

            <!-- Live preview -->
            <div class="pcc-design-preview">
                <h3>Live Preview</h3>
                <div id="pcc-preview-frame" class="pcc-preview-frame">
                    <div class="pcc-preview-banner" id="pcc-preview-banner">
                        <div class="pcc-card-tag" id="pv-tag">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Privacy &amp; Cookies
                        </div>
                        <h3 id="pv-heading" style="margin:0 0 10px;font-size:20px;">We respect your privacy</h3>
                        <p id="pv-text" style="font-size:14px;line-height:1.6;margin:0 0 20px;">We use cookies to improve your experience on our site, analyze traffic, and personalize content.</p>
                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button id="pv-accept" style="flex:1;padding:12px 20px;border:none;border-radius:10px;font-weight:700;font-size:13px;text-transform:uppercase;cursor:pointer;">Accept All</button>
                            <button id="pv-reject" style="flex:1;padding:12px 20px;border:none;border-radius:10px;font-weight:700;font-size:13px;text-transform:uppercase;cursor:pointer;">Reject All</button>
                        </div>
                        <button id="pv-prefs" style="width:100%;padding:12px 20px;border:none;border-radius:10px;font-weight:700;font-size:13px;text-transform:uppercase;cursor:pointer;margin-top:10px;">Customize</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Clean up on uninstall
register_uninstall_hook( __FILE__, 'pcc_uninstall' );

function pcc_uninstall() {
    global $wpdb;
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}pcc_consent_log" );
    delete_option( 'pcc_db_version' );
    delete_option( 'pcc_scan_results' );
    delete_option( 'pcc_design' );
    delete_option( 'pcc_translations' );
    delete_option( 'pcc_github_token' );
    delete_option( 'pcc_company' );
    delete_option( 'pcc_legal_pages' );
    delete_option( 'pcc_pin_version' );
    delete_site_transient( 'pcc_github_version' );
    delete_site_transient( 'pcc_github_tags' );
    delete_site_transient( 'pcc_github_readme' );
}
