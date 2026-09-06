<?php
/**
 * Plugin Name: Converta Cookie Banner
 * Plugin URI: https://converta.ch
 * Description: GDPR/ePrivacy cookie consent banner with Google Consent Mode v2, cookie scanner, and admin stats dashboard.
 * Version: 1.5.2
 * Author: Converta
 * Author URI: https://converta.ch
 * License: GPL v2 or later
 * Text Domain: procab-cookie-consent
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PCC_VERSION', '1.5.2' );
define( 'PCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PCC_COOKIE_NAME', 'procab_cookie_consent' );
define( 'PCC_COOKIE_EXPIRY', 365 );

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
function pcc_detect_language() {
    $trans  = pcc_get_translations();
    $method = $trans['detection_method'];
    $default = $trans['default_lang'];

    if ( $method === 'subfolder' ) {
        $path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
        $segments = explode( '/', $path );
        $first = strtolower( $segments[0] ?? '' );
        if ( in_array( $first, array( 'en', 'fr', 'de', 'it', 'ro', 'es' ), true ) ) {
            return $first;
        }
    } elseif ( $method === 'html_lang' ) {
        $locale = get_bloginfo( 'language' );
        $short  = strtolower( substr( $locale, 0, 2 ) );
        if ( in_array( $short, array( 'en', 'fr', 'de', 'it', 'ro', 'es' ), true ) ) {
            return $short;
        }
    }

    return $default;
}

/**
 * Get the translated strings for the current page.
 */
function pcc_get_current_strings() {
    $trans = pcc_get_translations();
    $lang  = pcc_detect_language();
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
        return substr( $html, 0, $pos ) . "\n" . $script . substr( $html, $pos );
    }

    return $html;
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
    wp_enqueue_style( 'pcc-banner-style', PCC_PLUGIN_URL . 'assets/css/banner.css', array(), PCC_VERSION );
    wp_enqueue_script( 'pcc-banner-script', PCC_PLUGIN_URL . 'assets/js/banner.js', array(), PCC_VERSION, true );
    wp_localize_script( 'pcc-banner-script', 'pccConfig', array(
        'cookieName'   => PCC_COOKIE_NAME,
        'cookieExpiry' => PCC_COOKIE_EXPIRY,
        'cookieDomain' => parse_url( home_url(), PHP_URL_HOST ),
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'pcc_nonce' ),
    ));

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
    .pcc-banner{border-color:{$d['banner_border']} !important}
    .pcc-banner-header h3{color:{$d['heading_color']} !important;font-size:{$hfs}px !important}
    .pcc-banner-text p{color:{$d['text_color']} !important;font-size:{$tfs}px !important}
    .pcc-preferences-header h3{color:{$d['prefs_heading_color']} !important;font-size:{$phs}px !important}
    .pcc-preferences-intro{color:{$d['text_color']} !important;font-size:{$tfs}px !important}
    .pcc-category-desc{color:{$d['text_color']} !important}
    .pcc-banner-tag{background:{$d['tag_bg']} !important;border-color:{$d['tag_border']} !important;color:{$d['tag_text']} !important}
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
    .pcc-cookie-table code{background:{$d['table_code_bg']} !important;color:{$d['table_code_text']} !important}
    .pcc-reopen-btn{background:{$d['reopen_bg']} !important;color:{$d['reopen_text']} !important}
    .pcc-overlay{background:{$d['overlay_bg']} !important}
    .pcc-always-active,.pcc-cookie-count{background:{$d['badge_bg']} !important;color:{$d['badge_text']} !important}
    .pcc-pref-icon{background:{$d['btn_accept_bg']} !important}
    .pcc-close-btn{background:{$d['close_btn_bg']} !important;color:{$d['close_btn_color']} !important;border-color:rgba(255,255,255,0.1) !important}
    .pcc-close-btn:hover{color:{$d['close_btn_hover']} !important;border-color:{$d['close_btn_hover']} !important}
    .pcc-expand-btn{background:{$d['expand_btn_bg']} !important;color:{$d['expand_btn_color']} !important}";
    wp_add_inline_style( 'pcc-banner-style', $css );
}

add_action( 'wp_footer', 'pcc_render_banner' );

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
    <div class="pcc-cookie-table-wrap">
        <table class="pcc-cookie-table">
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
    return '<a href="#" class="pcc-consent-link" role="button">' . $text . '</a>';
}

function pcc_render_banner() {
    $cookie_list = pcc_get_cookie_list();
    $s = pcc_get_current_strings();
    $reopen_method = pcc_get_reopen_method();
    ?>
<div id="pcc-cookie-overlay" class="pcc-overlay" style="display:none;" role="dialog" aria-modal="true" aria-label="Cookie Consent">

    <!-- Main Banner -->
    <div id="pcc-cookie-banner" class="pcc-banner">
        <div class="pcc-banner-content">
            <div class="pcc-banner-tag">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <?php echo $s['tag']; ?>
            </div>
            <div class="pcc-banner-header">
                <h3><?php echo $s['heading']; ?></h3>
            </div>
            <div class="pcc-banner-text">
                <p><?php echo $s['body']; ?></p>
            </div>
            <div class="pcc-banner-actions">
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
            <div class="pcc-cookie-category">
                <div class="pcc-category-header">
                    <div class="pcc-category-info">
                        <strong><?php echo $s['cat_necessary']; ?></strong>
                        <span class="pcc-always-active"><?php echo $s['always_active']; ?></span>
                    </div>
                    <label class="pcc-toggle"><input type="checkbox" checked disabled><span class="pcc-toggle-slider pcc-always-on"></span></label>
                    <?php if ( ! empty( $cookie_list['necessary'] ) ) : ?>
                        <button type="button" class="pcc-expand-btn" data-target="pcc-necessary-cookies" aria-label="Show cookies">
                            &#9660;
                        </button>
                    <?php endif; ?>
                </div>
                <p class="pcc-category-desc"><?php echo $s['cat_necessary_desc']; ?></p>
                <?php if ( ! empty( $cookie_list['necessary'] ) ) : ?>
                    <div id="pcc-necessary-cookies" class="pcc-cookie-details" style="display:none;">
                        <?php pcc_render_cookie_table( $cookie_list['necessary'] ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Statistics -->
            <div class="pcc-cookie-category">
                <div class="pcc-category-header">
                    <div class="pcc-category-info">
                        <strong><?php echo $s['cat_analytics']; ?></strong>
                        <?php if ( ! empty( $cookie_list['statistics'] ) ) : ?>
                            <span class="pcc-cookie-count"><?php echo count( $cookie_list['statistics'] ); ?> cookies</span>
                        <?php endif; ?>
                    </div>
                    <label class="pcc-toggle"><input type="checkbox" id="pcc-statistics-toggle" data-category="statistics"><span class="pcc-toggle-slider"></span></label>
                    <?php if ( ! empty( $cookie_list['statistics'] ) ) : ?>
                        <button type="button" class="pcc-expand-btn" data-target="pcc-statistics-cookies" aria-label="Show cookies">
                            &#9660;
                        </button>
                    <?php endif; ?>
                </div>
                <p class="pcc-category-desc"><?php echo $s['cat_analytics_desc']; ?></p>
                <?php if ( ! empty( $cookie_list['statistics'] ) ) : ?>
                    <div id="pcc-statistics-cookies" class="pcc-cookie-details" style="display:none;">
                        <?php pcc_render_cookie_table( $cookie_list['statistics'] ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Marketing -->
            <div class="pcc-cookie-category">
                <div class="pcc-category-header">
                    <div class="pcc-category-info">
                        <strong><?php echo $s['cat_marketing']; ?></strong>
                        <?php if ( ! empty( $cookie_list['marketing'] ) ) : ?>
                            <span class="pcc-cookie-count"><?php echo count( $cookie_list['marketing'] ); ?> cookies</span>
                        <?php endif; ?>
                    </div>
                    <label class="pcc-toggle"><input type="checkbox" id="pcc-marketing-toggle" data-category="marketing"><span class="pcc-toggle-slider"></span></label>
                    <?php if ( ! empty( $cookie_list['marketing'] ) ) : ?>
                        <button type="button" class="pcc-expand-btn" data-target="pcc-marketing-cookies" aria-label="Show cookies">
                            &#9660;
                        </button>
                    <?php endif; ?>
                </div>
                <p class="pcc-category-desc"><?php echo $s['cat_marketing_desc']; ?></p>
                <?php if ( ! empty( $cookie_list['marketing'] ) ) : ?>
                    <div id="pcc-marketing-cookies" class="pcc-cookie-details" style="display:none;">
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
<button id="pcc-reopen-banner" class="pcc-reopen-btn" type="button" aria-label="Cookie Settings" style="display:none;" title="Cookie Settings">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg>
</button>
<?php endif; ?>

<?php if ( 'footer_link' === $reopen_method || 'both' === $reopen_method ) : ?>
<div class="pcc-footer-consent-bar">
    <a href="#" class="pcc-consent-link" role="button">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/></svg>
        <?php echo $s['footer_link'] ?? 'Cookie Settings'; ?>
    </a>
</div>
<?php endif; ?>
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
        'pcc-banner-design',
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
}

add_action( 'admin_enqueue_scripts', 'pcc_admin_assets' );

function pcc_admin_assets( $hook ) {
    $stats_hook   = 'toplevel_page_pcc-cookie-stats';
    $scanner_hook = 'cookie-consent_page_pcc-cookie-scanner';
    $design_hook  = 'cookie-consent_page_pcc-banner-design';
    $trans_hook   = 'cookie-consent_page_pcc-translations';

    if ( ! in_array( $hook, array( $stats_hook, $scanner_hook, $design_hook, $trans_hook ), true ) ) {
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
        wp_enqueue_style( 'pcc-banner-style', PCC_PLUGIN_URL . 'assets/css/banner.css', array(), PCC_VERSION );
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
            <div class="pcc-cookie-table-actions">
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
            <p style="margin-bottom:0;color:#666;">Tip: you can also place the link yourself anywhere (footer menu, widget, privacy page) with the shortcode <code>[pcc_consent_link]</code> or by adding the CSS class <code>pcc-consent-link</code> to any link &mdash; those work with every option above. Remember to click <strong>Save Design</strong> below.</p>
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
                        <div class="pcc-banner-tag" id="pv-tag">
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
}
