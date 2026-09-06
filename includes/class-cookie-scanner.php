<?php
/**
 * Cookie Scanner - crawls up to 100 pages, collects Set-Cookie headers,
 * detects known tracking scripts, and maps them to cookie definitions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PCC_Cookie_Scanner {

    /**
     * Known script signatures → cookie definitions.
     * pattern: regex matched against page HTML
     * cookies: array of cookie definitions found when pattern matches
     */
    private static function get_known_scripts() {
        return array(
            // Google Analytics / GA4
            array(
                'pattern'  => '/gtag\/js\?id=G-|google-analytics\.com\/analytics\.js|googletagmanager\.com\/gtag|ga\(\s*[\'"]create/i',
                'cookies'  => array(
                    array( 'name' => '_ga',           'provider' => 'Google Analytics', 'category' => 'statistics', 'duration' => '2 years',  'description' => 'Distinguishes unique users by assigning a randomly generated number as a client identifier.' ),
                    array( 'name' => '_ga_*',         'provider' => 'Google Analytics', 'category' => 'statistics', 'duration' => '2 years',  'description' => 'Used to persist session state across page requests.' ),
                    array( 'name' => '_gid',          'provider' => 'Google Analytics', 'category' => 'statistics', 'duration' => '24 hours', 'description' => 'Distinguishes unique users for analytics within a 24-hour window.' ),
                    array( 'name' => '_gat',          'provider' => 'Google Analytics', 'category' => 'statistics', 'duration' => '1 minute', 'description' => 'Used to throttle request rate to Google Analytics.' ),
                ),
            ),
            // Google Tag Manager
            array(
                'pattern'  => '/googletagmanager\.com\/gtm\.js/i',
                'cookies'  => array(
                    array( 'name' => '_gcl_au',       'provider' => 'Google Tag Manager', 'category' => 'marketing', 'duration' => '90 days',  'description' => 'Used to store and track conversions from Google Ads.' ),
                ),
            ),
            // Google Ads
            array(
                'pattern'  => '/googleads\.g\.doubleclick\.net|googlesyndication\.com|adservice\.google\./i',
                'cookies'  => array(
                    array( 'name' => '_gcl_aw',       'provider' => 'Google Ads',      'category' => 'marketing', 'duration' => '90 days',  'description' => 'Stores Google Ads click information for conversion tracking.' ),
                    array( 'name' => '_gac_*',        'provider' => 'Google Ads',      'category' => 'marketing', 'duration' => '90 days',  'description' => 'Contains campaign-related information for Google Ads.' ),
                    array( 'name' => 'IDE',           'provider' => 'Google DoubleClick', 'category' => 'marketing', 'duration' => '1 year', 'description' => 'Used by Google DoubleClick to serve targeted advertisements.' ),
                    array( 'name' => 'test_cookie',   'provider' => 'Google DoubleClick', 'category' => 'marketing', 'duration' => '15 minutes', 'description' => 'Used to check if the browser supports cookies.' ),
                ),
            ),
            // Facebook Pixel
            array(
                'pattern'  => '/connect\.facebook\.net\/.*\/fbevents\.js|fbq\(\s*[\'"]init/i',
                'cookies'  => array(
                    array( 'name' => '_fbp',          'provider' => 'Facebook',        'category' => 'marketing', 'duration' => '3 months', 'description' => 'Used by Facebook to deliver advertisements and track ad performance.' ),
                    array( 'name' => '_fbc',          'provider' => 'Facebook',        'category' => 'marketing', 'duration' => '2 years',  'description' => 'Stores Facebook click identifier for conversion tracking.' ),
                    array( 'name' => 'fr',            'provider' => 'Facebook',        'category' => 'marketing', 'duration' => '3 months', 'description' => 'Used by Facebook for advertising and analytics purposes.' ),
                ),
            ),
            // Meta / Instagram
            array(
                'pattern'  => '/instagram\.com\/embed\.js|connect\.facebook\.net\/.*\/sdk\.js/i',
                'cookies'  => array(
                    array( 'name' => 'datr',          'provider' => 'Meta',            'category' => 'marketing', 'duration' => '2 years',  'description' => 'Identifies the browser connecting to Facebook/Meta services.' ),
                    array( 'name' => 'sb',            'provider' => 'Meta',            'category' => 'marketing', 'duration' => '2 years',  'description' => 'Used for browser identification and security by Meta.' ),
                ),
            ),
            // LinkedIn Insight Tag
            array(
                'pattern'  => '/snap\.licdn\.com\/li\.lms-analytics|linkedin\.com\/px/i',
                'cookies'  => array(
                    array( 'name' => 'li_sugr',       'provider' => 'LinkedIn',        'category' => 'marketing', 'duration' => '3 months', 'description' => 'Used for LinkedIn ad targeting and analytics.' ),
                    array( 'name' => 'bcookie',       'provider' => 'LinkedIn',        'category' => 'marketing', 'duration' => '1 year',   'description' => 'Browser ID cookie set by LinkedIn.' ),
                    array( 'name' => 'lidc',          'provider' => 'LinkedIn',        'category' => 'marketing', 'duration' => '24 hours', 'description' => 'Used for routing and data center selection by LinkedIn.' ),
                    array( 'name' => 'UserMatchHistory', 'provider' => 'LinkedIn',     'category' => 'marketing', 'duration' => '30 days',  'description' => 'LinkedIn ad targeting synchronisation cookie.' ),
                    array( 'name' => 'AnalyticsSyncHistory', 'provider' => 'LinkedIn', 'category' => 'marketing', 'duration' => '30 days',  'description' => 'Stores information about LinkedIn analytics sync.' ),
                ),
            ),
            // Twitter / X
            array(
                'pattern'  => '/static\.ads-twitter\.com|t\.co\/i\/adsct|analytics\.twitter\.com|twq\s*\(/i',
                'cookies'  => array(
                    array( 'name' => 'muc_ads',       'provider' => 'Twitter / X',     'category' => 'marketing', 'duration' => '2 years',  'description' => 'Used by Twitter for ad targeting and measurement.' ),
                    array( 'name' => 'personalization_id', 'provider' => 'Twitter / X', 'category' => 'marketing', 'duration' => '2 years', 'description' => 'Used for Twitter personalization and advertising.' ),
                ),
            ),
            // TikTok Pixel
            array(
                'pattern'  => '/analytics\.tiktok\.com|ttq\.load/i',
                'cookies'  => array(
                    array( 'name' => '_ttp',          'provider' => 'TikTok',          'category' => 'marketing', 'duration' => '13 months', 'description' => 'Used by TikTok to track visitors and measure ad performance.' ),
                    array( 'name' => 'tt_webid',      'provider' => 'TikTok',          'category' => 'marketing', 'duration' => '1 year',    'description' => 'TikTok tracking identifier for ad targeting.' ),
                ),
            ),
            // Hotjar
            array(
                'pattern'  => '/static\.hotjar\.com|hotjar\.com\/c\/hotjar/i',
                'cookies'  => array(
                    array( 'name' => '_hj*',          'provider' => 'Hotjar',          'category' => 'statistics', 'duration' => '1 year',   'description' => 'Used by Hotjar to track user behavior including heatmaps and recordings.' ),
                    array( 'name' => '_hjSessionUser_*', 'provider' => 'Hotjar',       'category' => 'statistics', 'duration' => '1 year',   'description' => 'Set when a user first visits a page, persists Hotjar User ID.' ),
                    array( 'name' => '_hjSession_*',  'provider' => 'Hotjar',          'category' => 'statistics', 'duration' => '30 minutes', 'description' => 'Holds current session data for Hotjar analytics.' ),
                ),
            ),
            // Microsoft Clarity
            array(
                'pattern'  => '/clarity\.ms\/tag|clarity\.ms\/s/i',
                'cookies'  => array(
                    array( 'name' => '_clck',         'provider' => 'Microsoft Clarity', 'category' => 'statistics', 'duration' => '1 year',  'description' => 'Persists the Clarity User ID and preferences.' ),
                    array( 'name' => '_clsk',         'provider' => 'Microsoft Clarity', 'category' => 'statistics', 'duration' => '1 day',   'description' => 'Connects multiple page views by a user into a single session.' ),
                    array( 'name' => 'CLID',          'provider' => 'Microsoft Clarity', 'category' => 'statistics', 'duration' => '1 year',  'description' => 'Identifies the first-time Clarity saw this user.' ),
                ),
            ),
            // Matomo / Piwik
            array(
                'pattern'  => '/matomo\.js|piwik\.js|matomo\.php|piwik\.php/i',
                'cookies'  => array(
                    array( 'name' => '_pk_id.*',      'provider' => 'Matomo',          'category' => 'statistics', 'duration' => '13 months', 'description' => 'Used to store visitor ID for Matomo analytics.' ),
                    array( 'name' => '_pk_ses.*',     'provider' => 'Matomo',          'category' => 'statistics', 'duration' => '30 minutes', 'description' => 'Short-lived cookie used to track page visits in Matomo.' ),
                ),
            ),
            // HubSpot
            array(
                'pattern'  => '/js\.hs-scripts\.com|js\.hs-analytics\.net|js\.hubspot\.com/i',
                'cookies'  => array(
                    array( 'name' => '__hstc',        'provider' => 'HubSpot',         'category' => 'marketing', 'duration' => '13 months', 'description' => 'Main HubSpot tracking cookie for visitor identification.' ),
                    array( 'name' => 'hubspotutk',    'provider' => 'HubSpot',         'category' => 'marketing', 'duration' => '13 months', 'description' => 'Keeps track of visitor identity for HubSpot forms.' ),
                    array( 'name' => '__hssc',        'provider' => 'HubSpot',         'category' => 'marketing', 'duration' => '30 minutes', 'description' => 'Keeps track of session data for HubSpot analytics.' ),
                    array( 'name' => '__hssrc',       'provider' => 'HubSpot',         'category' => 'marketing', 'duration' => 'Session',    'description' => 'Used to determine if the visitor has restarted their browser.' ),
                ),
            ),
            // YouTube embeds
            array(
                'pattern'  => '/youtube\.com\/embed|youtube-nocookie\.com\/embed|youtube\.com\/iframe_api/i',
                'cookies'  => array(
                    array( 'name' => 'YSC',           'provider' => 'YouTube (Google)', 'category' => 'marketing', 'duration' => 'Session',  'description' => 'Registers a unique ID to keep statistics of what YouTube videos the user has seen.' ),
                    array( 'name' => 'VISITOR_INFO1_LIVE', 'provider' => 'YouTube (Google)', 'category' => 'marketing', 'duration' => '6 months', 'description' => 'Estimates user bandwidth on pages with integrated YouTube videos.' ),
                ),
            ),
            // Google Maps
            array(
                'pattern'  => '/maps\.googleapis\.com\/maps\/api\/js|maps\.google\.com\/maps\?/i',
                'cookies'  => array(
                    array( 'name' => 'NID',           'provider' => 'Google Maps',     'category' => 'marketing', 'duration' => '6 months', 'description' => 'Stores preferences and information for Google Maps.' ),
                ),
            ),
            // Stripe
            array(
                'pattern'  => '/js\.stripe\.com/i',
                'cookies'  => array(
                    array( 'name' => '__stripe_mid',  'provider' => 'Stripe',          'category' => 'necessary', 'duration' => '1 year',   'description' => 'Set for fraud prevention during payment processing.' ),
                    array( 'name' => '__stripe_sid',  'provider' => 'Stripe',          'category' => 'necessary', 'duration' => '30 minutes', 'description' => 'Set for fraud prevention during payment sessions.' ),
                ),
            ),
            // WooCommerce
            array(
                'pattern'  => '/wp-content\/plugins\/woocommerce|wc-ajax=|wc-cart-fragments/i',
                'cookies'  => array(
                    array( 'name' => 'woocommerce_cart_hash', 'provider' => 'WooCommerce', 'category' => 'necessary', 'duration' => 'Session', 'description' => 'Stores a hash of the cart contents to detect changes.' ),
                    array( 'name' => 'woocommerce_items_in_cart', 'provider' => 'WooCommerce', 'category' => 'necessary', 'duration' => 'Session', 'description' => 'Indicates whether there are items in the cart.' ),
                    array( 'name' => 'wp_woocommerce_session_*', 'provider' => 'WooCommerce', 'category' => 'necessary', 'duration' => '2 days', 'description' => 'Contains a unique session identifier for the customer.' ),
                ),
            ),
            // Pinterest
            array(
                'pattern'  => '/pintrk|assets\.pinterest\.com|ct\.pinterest\.com/i',
                'cookies'  => array(
                    array( 'name' => '_pinterest_ct_ua', 'provider' => 'Pinterest',    'category' => 'marketing', 'duration' => '1 year',   'description' => 'Used by Pinterest for advertising and conversion tracking.' ),
                    array( 'name' => '_pin_unauth',   'provider' => 'Pinterest',       'category' => 'marketing', 'duration' => '1 year',   'description' => 'Pinterest tracking cookie for unauthenticated users.' ),
                ),
            ),
            // Intercom
            array(
                'pattern'  => '/widget\.intercom\.io|intercom\.com\/widget/i',
                'cookies'  => array(
                    array( 'name' => 'intercom-id-*', 'provider' => 'Intercom',        'category' => 'statistics', 'duration' => '9 months', 'description' => 'Allows visitors to see conversations they have had on Intercom.' ),
                    array( 'name' => 'intercom-session-*', 'provider' => 'Intercom',   'category' => 'statistics', 'duration' => '1 week',   'description' => 'Identifier for current Intercom session.' ),
                ),
            ),
            // Crisp Chat
            array(
                'pattern'  => '/client\.crisp\.chat/i',
                'cookies'  => array(
                    array( 'name' => 'crisp-client/*', 'provider' => 'Crisp',          'category' => 'necessary', 'duration' => '6 months', 'description' => 'Used by Crisp live chat to identify returning visitors.' ),
                ),
            ),
            // Cloudflare
            array(
                'pattern'  => '/cdnjs\.cloudflare\.com|cdn-cgi\/challenge-platform|cf-beacon\.min\.js/i',
                'cookies'  => array(
                    array( 'name' => '__cf_bm',       'provider' => 'Cloudflare',      'category' => 'necessary', 'duration' => '30 minutes', 'description' => 'Cloudflare bot management cookie to distinguish humans from bots.' ),
                    array( 'name' => 'cf_clearance',  'provider' => 'Cloudflare',      'category' => 'necessary', 'duration' => '30 minutes', 'description' => 'Set after a visitor completes a Cloudflare challenge.' ),
                ),
            ),
            // Google reCAPTCHA
            array(
                'pattern'  => '/google\.com\/recaptcha|gstatic\.com\/recaptcha/i',
                'cookies'  => array(
                    array( 'name' => '_GRECAPTCHA',   'provider' => 'Google reCAPTCHA', 'category' => 'necessary', 'duration' => '6 months', 'description' => 'Used by Google reCAPTCHA for spam and abuse protection.' ),
                ),
            ),
            // Snapchat Pixel
            array(
                'pattern'  => '/sc-static\.net\/scevent\.min\.js|tr\.snapchat\.com/i',
                'cookies'  => array(
                    array( 'name' => '_scid',         'provider' => 'Snapchat',        'category' => 'marketing', 'duration' => '13 months', 'description' => 'Used by Snapchat for advertising and conversion tracking.' ),
                    array( 'name' => '_scid_r',       'provider' => 'Snapchat',        'category' => 'marketing', 'duration' => '13 months', 'description' => 'Snapchat tracking cookie for retargeting purposes.' ),
                ),
            ),
            // Sourcebuster.js (sbjs) - traffic source tracking
            array(
                'pattern'  => '/sourcebuster|sbjs/i',
                'cookies'  => array(
                    array( 'name' => 'sbjs_current',     'provider' => 'Sourcebuster', 'category' => 'statistics', 'duration' => '6 months', 'description' => 'Stores current traffic source data for visitor analytics.' ),
                    array( 'name' => 'sbjs_current_add', 'provider' => 'Sourcebuster', 'category' => 'statistics', 'duration' => '6 months', 'description' => 'Stores additional current traffic source parameters.' ),
                    array( 'name' => 'sbjs_first',       'provider' => 'Sourcebuster', 'category' => 'statistics', 'duration' => '6 months', 'description' => 'Stores the first traffic source that brought the visitor.' ),
                    array( 'name' => 'sbjs_first_add',   'provider' => 'Sourcebuster', 'category' => 'statistics', 'duration' => '6 months', 'description' => 'Stores additional first traffic source parameters.' ),
                    array( 'name' => 'sbjs_migrations',  'provider' => 'Sourcebuster', 'category' => 'statistics', 'duration' => '6 months', 'description' => 'Tracks Sourcebuster cookie format migrations.' ),
                    array( 'name' => 'sbjs_session',     'provider' => 'Sourcebuster', 'category' => 'statistics', 'duration' => '30 minutes', 'description' => 'Tracks the current browsing session for analytics.' ),
                    array( 'name' => 'sbjs_udata',       'provider' => 'Sourcebuster', 'category' => 'statistics', 'duration' => '6 months', 'description' => 'Stores visitor identification data for analytics.' ),
                ),
            ),
        );
    }

    /**
     * WordPress core / standard cookies (always present).
     */
    private static function get_wordpress_cookies() {
        return array(
            array( 'name' => 'wordpress_logged_in_*', 'provider' => 'WordPress', 'category' => 'necessary', 'duration' => 'Session / 14 days', 'description' => 'Indicates when a user is logged in and who they are, used for the WordPress dashboard.' ),
            array( 'name' => 'wordpress_sec_*',       'provider' => 'WordPress', 'category' => 'necessary', 'duration' => 'Session / 14 days', 'description' => 'Used to store authentication details securely for admin area access.' ),
            array( 'name' => 'wordpress_test_cookie',  'provider' => 'WordPress', 'category' => 'necessary', 'duration' => 'Session',           'description' => 'Used to check if the browser accepts cookies.' ),
            array( 'name' => 'wp-settings-*',          'provider' => 'WordPress', 'category' => 'necessary', 'duration' => '1 year',            'description' => 'Used to customize the WordPress admin interface.' ),
            array( 'name' => 'wp-settings-time-*',     'provider' => 'WordPress', 'category' => 'necessary', 'duration' => '1 year',            'description' => 'Stores the time when wp-settings cookie was set.' ),
            array( 'name' => PCC_COOKIE_NAME,           'provider' => 'Converta Cookie Banner', 'category' => 'necessary', 'duration' => '1 year', 'description' => 'Stores your cookie consent preferences for this website.' ),
        );
    }

    /**
     * Run the scan: fetch up to 100 pages, collect cookies.
     *
     * @return array  {
     *     @type array  $cookies        Deduplicated cookie list.
     *     @type int    $pages_scanned  Number of pages fetched.
     *     @type array  $errors         Any fetch errors.
     *     @type string $scanned_at     ISO timestamp.
     * }
     */
    public static function scan( $max_pages = 100 ) {
        $urls   = self::collect_urls( $max_pages );
        $found  = array();
        $errors = array();
        $seen   = array(); // dedupe by cookie name

        // Always add WP core cookies
        foreach ( self::get_wordpress_cookies() as $cookie ) {
            $key = strtolower( $cookie['name'] );
            if ( ! isset( $seen[ $key ] ) ) {
                $seen[ $key ] = true;
                $found[]      = $cookie;
            }
        }

        $scripts_db = self::get_known_scripts();

        foreach ( $urls as $url ) {
            $result = self::fetch_page( $url );

            if ( is_wp_error( $result ) ) {
                $errors[] = array( 'url' => $url, 'error' => $result->get_error_message() );
                continue;
            }

            // 1. Collect Set-Cookie headers
            $headers = wp_remote_retrieve_headers( $result );
            $set_cookies = array();

            if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
                $all = $headers->getAll();
                if ( isset( $all['set-cookie'] ) ) {
                    $set_cookies = (array) $all['set-cookie'];
                }
            }

            foreach ( $set_cookies as $raw ) {
                $name = self::parse_cookie_name( $raw );
                if ( $name ) {
                    $key = strtolower( $name );
                    if ( ! isset( $seen[ $key ] ) ) {
                        $seen[ $key ] = true;
                        $found[] = array(
                            'name'        => $name,
                            'provider'    => self::guess_provider( $name ),
                            'category'    => self::guess_category( $name ),
                            'duration'    => self::parse_cookie_duration( $raw ),
                            'description' => 'Detected via HTTP Set-Cookie header.',
                        );
                    }
                }
            }

            // 2. Scan HTML for known script patterns
            $body = wp_remote_retrieve_body( $result );

            foreach ( $scripts_db as $script ) {
                if ( preg_match( $script['pattern'], $body ) ) {
                    foreach ( $script['cookies'] as $cookie ) {
                        $key = strtolower( $cookie['name'] );
                        if ( ! isset( $seen[ $key ] ) ) {
                            $seen[ $key ] = true;
                            $found[]      = $cookie;
                        }
                    }
                }
            }

            // 3. If GTM is present, fetch the GTM container JS and scan for tags inside it.
            // Supports: standard GTM, server-side GTM (sGTM) via Stape.io or custom proxy domains.
            $gtm_ids_scanned = isset( $gtm_ids_scanned ) ? $gtm_ids_scanned : array();

            // 3a. Standard GTM patterns — googletagmanager.com/gtm.js?id=GTM-XXX
            $gtm_patterns = array(
                '/googletagmanager\.com\/gtm\.js\?[^"\']*id=(GTM-[A-Z0-9]+)/i',
                '/googletagmanager\.com\/gtm\.js\?[^"\']*id=\\\x22(GTM-[A-Z0-9]+)/i',
                '/[\'"]GTM-([\w]+)[\'"]/i',
            );
            foreach ( $gtm_patterns as $gp ) {
                if ( preg_match_all( $gp, $body, $gtm_matches ) ) {
                    foreach ( $gtm_matches[1] as $raw_id ) {
                        $gtm_id = ( strpos( $raw_id, 'GTM-' ) === 0 ) ? $raw_id : 'GTM-' . $raw_id;
                        if ( isset( $gtm_ids_scanned[ $gtm_id ] ) ) continue;
                        $gtm_ids_scanned[ $gtm_id ] = true;

                        $gtm_cookies = self::scan_gtm_container( $gtm_id );
                        foreach ( $gtm_cookies as $cookie ) {
                            $key = strtolower( $cookie['name'] );
                            if ( ! isset( $seen[ $key ] ) ) {
                                $seen[ $key ] = true;
                                $found[]      = $cookie;
                            }
                        }
                    }
                }
            }

            // 3b. Server-side GTM (sGTM) — Stape.io, Addingwell, custom proxy domains.
            // sGTM encodes the GTM ID in base64 within the page source.
            // "aWQ9R1RN" is the base64 prefix for "id=GTM" — scan for any such string.
            // This catches ALL sGTM implementations regardless of proxy URL structure.
            if ( strpos( $body, 'gtm.start' ) !== false || strpos( $body, 'gtm.js' ) !== false ) {
                if ( preg_match_all( '/aWQ9R1R[A-Za-z0-9+\/=]{8,30}/', $body, $b64_matches ) ) {
                    foreach ( $b64_matches[0] as $b64 ) {
                        $decoded = base64_decode( $b64, true );
                        if ( $decoded && preg_match( '/id=(GTM-[A-Z0-9]+)/i', $decoded, $id_match ) ) {
                            $gtm_id = strtoupper( $id_match[1] );
                            if ( ! isset( $gtm_ids_scanned[ $gtm_id ] ) ) {
                                $gtm_ids_scanned[ $gtm_id ] = true;
                                $gtm_cookies = self::scan_gtm_container( $gtm_id );
                                foreach ( $gtm_cookies as $cookie ) {
                                    $key = strtolower( $cookie['name'] );
                                    if ( ! isset( $seen[ $key ] ) ) {
                                        $seen[ $key ] = true;
                                        $found[]      = $cookie;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        // Sort: necessary first, then statistics, then marketing
        usort( $found, function ( $a, $b ) {
            $order = array( 'necessary' => 0, 'statistics' => 1, 'marketing' => 2 );
            $oa = isset( $order[ $a['category'] ] ) ? $order[ $a['category'] ] : 3;
            $ob = isset( $order[ $b['category'] ] ) ? $order[ $b['category'] ] : 3;
            return $oa - $ob;
        });

        $result_data = array(
            'cookies'       => $found,
            'pages_scanned' => count( $urls ),
            'errors'        => $errors,
            'scanned_at'    => current_time( 'c' ),
        );

        update_option( 'pcc_scan_results', $result_data );

        return $result_data;
    }

    /**
     * Collect URLs from sitemap and internal links, up to $max.
     */
    private static function collect_urls( $max ) {
        $urls = array( home_url( '/' ) );
        $seen = array( trailingslashit( home_url( '/' ) ) => true );

        // Try sitemap
        $sitemap_urls = self::parse_sitemap( home_url( '/sitemap.xml' ) );
        if ( empty( $sitemap_urls ) ) {
            $sitemap_urls = self::parse_sitemap( home_url( '/sitemap_index.xml' ) );
        }
        if ( empty( $sitemap_urls ) ) {
            $sitemap_urls = self::parse_sitemap( home_url( '/wp-sitemap.xml' ) );
        }

        foreach ( $sitemap_urls as $u ) {
            $norm = trailingslashit( $u );
            if ( ! isset( $seen[ $norm ] ) && count( $urls ) < $max ) {
                $seen[ $norm ] = true;
                $urls[]        = $u;
            }
        }

        // If still under limit, grab published posts/pages
        if ( count( $urls ) < $max ) {
            $remaining = $max - count( $urls );
            $posts = get_posts( array(
                'post_type'   => array( 'post', 'page', 'product' ),
                'post_status' => 'publish',
                'numberposts' => $remaining,
                'fields'      => 'ids',
            ));
            foreach ( $posts as $pid ) {
                $permalink = get_permalink( $pid );
                $norm = trailingslashit( $permalink );
                if ( ! isset( $seen[ $norm ] ) && count( $urls ) < $max ) {
                    $seen[ $norm ] = true;
                    $urls[]        = $permalink;
                }
            }
        }

        return array_slice( $urls, 0, $max );
    }

    /**
     * Parse a sitemap XML and return URLs (handles sitemap index too).
     */
    private static function parse_sitemap( $sitemap_url ) {
        $urls   = array();
        $result = wp_remote_get( $sitemap_url, array( 'timeout' => 10, 'sslverify' => false ) );

        if ( is_wp_error( $result ) || wp_remote_retrieve_response_code( $result ) !== 200 ) {
            return $urls;
        }

        $body = wp_remote_retrieve_body( $result );

        // Suppress XML errors
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $body );
        libxml_clear_errors();

        if ( ! $xml ) {
            return $urls;
        }

        // Sitemap index → recurse into child sitemaps
        if ( isset( $xml->sitemap ) ) {
            foreach ( $xml->sitemap as $entry ) {
                if ( isset( $entry->loc ) ) {
                    $child_urls = self::parse_sitemap( (string) $entry->loc );
                    $urls       = array_merge( $urls, $child_urls );
                }
            }
        }

        // URL set
        if ( isset( $xml->url ) ) {
            foreach ( $xml->url as $entry ) {
                if ( isset( $entry->loc ) ) {
                    $urls[] = (string) $entry->loc;
                }
            }
        }

        return $urls;
    }

    /**
     * Fetch and parse a GTM container JS to detect tags deployed via GTM.
     *
     * GTM container JS is minified and uses escaped URLs like:
     *   "facebook.com\\/tr", "connect.facebook.net", "fbevents.js"
     *   Also uses string references like "Facebook" in tag names, and
     *   numeric pixel IDs (e.g. "1234567890" for FB pixel).
     *
     * We use VERY broad patterns to catch all variants.
     *
     * @param  string $gtm_id  e.g. 'GTM-XXXXXX'
     * @return array  Cookie definitions found inside the container.
     */
    private static function scan_gtm_container( $gtm_id ) {
        $found   = array();
        $gtm_url = 'https://www.googletagmanager.com/gtm.js?id=' . urlencode( $gtm_id );

        $result = wp_remote_get( $gtm_url, array(
            'timeout'    => 20,
            'sslverify'  => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ));

        if ( is_wp_error( $result ) || wp_remote_retrieve_response_code( $result ) !== 200 ) {
            return $found;
        }

        $js = wp_remote_retrieve_body( $result );

        // Unescape the GTM JS so patterns match properly.
        // GTM escapes: \/ → /,  \\u0026 → &,  \\x22 → ",  \\x27 → ',  \\n → newline
        $js_clean = str_replace(
            array( '\\/',  '\\u0026', '\\x22', '\\x27', '\\x3c', '\\x3e', '\\n', '\\r' ),
            array( '/',    '&',       '"',     "'",     '<',     '>',     "\n",  "\r" ),
            $js
        );

        // Broad patterns — GTM minifies strings but these fragments survive.
        // We match both URL fragments (for custom HTML tags) AND internal GTM
        // tag-type identifiers like __gaawc (GA4), __awct (Google Ads), etc.
        // GTM template tags use these internal names instead of full URLs.
        $gtm_service_patterns = array(
            // Facebook / Meta Pixel
            // URL patterns for custom HTML, plus GTM template type __fsl (Facebook)
            array(
                'pattern' => '/facebook\.com\/tr|fbevents\.js|connect\.facebook\.net|fbq\s*\(|Facebook\s*Pixel|fb_pixel|"__fsl"|"__ogt_fbpx"|facebook_pixel/i',
                'cookies' => array(
                    array( 'name' => '_fbp',  'provider' => 'Facebook (via GTM)', 'category' => 'marketing', 'duration' => '3 months', 'description' => 'Facebook Pixel tracking cookie deployed via Google Tag Manager.' ),
                    array( 'name' => '_fbc',  'provider' => 'Facebook (via GTM)', 'category' => 'marketing', 'duration' => '2 years',  'description' => 'Facebook click tracking cookie deployed via Google Tag Manager.' ),
                    array( 'name' => 'fr',    'provider' => 'Facebook (via GTM)', 'category' => 'marketing', 'duration' => '3 months', 'description' => 'Facebook advertising cookie deployed via Google Tag Manager.' ),
                ),
            ),
            // Google Ads — GTM template types: __awct (conversion), __gclidw (linker)
            array(
                'pattern' => '/AW-[0-9]{5,}|googleads\.g\.doubleclick|adservice\.google\.|google_conversion_id|ads\.google\.com|conversionLinker|"__awct"|"__gclidw"|"__aecid"|"__ccd_auto_redact"|"__ccd_conversion_marking"/i',
                'cookies' => array(
                    array( 'name' => '_gcl_aw',  'provider' => 'Google Ads (via GTM)',         'category' => 'marketing', 'duration' => '90 days', 'description' => 'Google Ads conversion tracking deployed via Google Tag Manager.' ),
                    array( 'name' => '_gcl_au',  'provider' => 'Google Ads (via GTM)',         'category' => 'marketing', 'duration' => '90 days', 'description' => 'Google Ads conversion linker deployed via Google Tag Manager.' ),
                    array( 'name' => 'IDE',      'provider' => 'Google DoubleClick (via GTM)', 'category' => 'marketing', 'duration' => '1 year',  'description' => 'DoubleClick ad targeting deployed via Google Tag Manager.' ),
                ),
            ),
            // LinkedIn — GTM template type: __bzi (Bing/LinkedIn insight)
            array(
                'pattern' => '/snap\.licdn\.com|LinkedIn\s*Insight|linkedin_pixel|_linkedin_data_|linkedin\.com\/px|"__bzi"/i',
                'cookies' => array(
                    array( 'name' => 'li_sugr',            'provider' => 'LinkedIn (via GTM)', 'category' => 'marketing', 'duration' => '3 months', 'description' => 'LinkedIn analytics deployed via Google Tag Manager.' ),
                    array( 'name' => 'bcookie',            'provider' => 'LinkedIn (via GTM)', 'category' => 'marketing', 'duration' => '1 year',   'description' => 'LinkedIn browser cookie deployed via Google Tag Manager.' ),
                    array( 'name' => 'UserMatchHistory',   'provider' => 'LinkedIn (via GTM)', 'category' => 'marketing', 'duration' => '30 days',  'description' => 'LinkedIn ad sync deployed via Google Tag Manager.' ),
                    array( 'name' => 'AnalyticsSyncHistory','provider' => 'LinkedIn (via GTM)', 'category' => 'marketing', 'duration' => '30 days', 'description' => 'LinkedIn analytics sync deployed via Google Tag Manager.' ),
                ),
            ),
            // Twitter / X — GTM template type: __twitter_website_tag
            array(
                'pattern' => '/static\.ads-twitter\.com|t\.co\/i\/adsct|analytics\.twitter\.com|twitter_pixel|twq\s*\(|Twitter\s*Pixel|"__twitter_website_tag"/i',
                'cookies' => array(
                    array( 'name' => 'muc_ads',            'provider' => 'Twitter/X (via GTM)', 'category' => 'marketing', 'duration' => '2 years', 'description' => 'Twitter ad tracking deployed via Google Tag Manager.' ),
                    array( 'name' => 'personalization_id', 'provider' => 'Twitter/X (via GTM)', 'category' => 'marketing', 'duration' => '2 years', 'description' => 'Twitter personalization deployed via Google Tag Manager.' ),
                ),
            ),
            // TikTok — GTM template type: __tiktok_pixel
            array(
                'pattern' => '/analytics\.tiktok\.com|ttq\.\s*load|TikTok\s*Pixel|"__tiktok_pixel"|tiktok\.com\/i18n/i',
                'cookies' => array(
                    array( 'name' => '_ttp', 'provider' => 'TikTok (via GTM)', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'TikTok tracking deployed via Google Tag Manager.' ),
                ),
            ),
            // Pinterest — GTM template type: __pinterest_tag
            array(
                'pattern' => '/ct\.pinterest\.com|pintrk\s*\(|Pinterest\s*Tag|"__pinterest_tag"/i',
                'cookies' => array(
                    array( 'name' => '_pinterest_ct_ua', 'provider' => 'Pinterest (via GTM)', 'category' => 'marketing', 'duration' => '1 year', 'description' => 'Pinterest conversion tracking deployed via Google Tag Manager.' ),
                ),
            ),
            // Snapchat
            array(
                'pattern' => '/sc-static\.net\/scevent|tr\.snapchat\.com|Snapchat\s*Pixel|"__snapchat_/i',
                'cookies' => array(
                    array( 'name' => '_scid', 'provider' => 'Snapchat (via GTM)', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'Snapchat tracking deployed via Google Tag Manager.' ),
                ),
            ),
            // Hotjar — GTM template type: __hjtc
            array(
                'pattern' => '/static\.hotjar\.com|hotjar\.com\/c\/hotjar|"__hjtc"/i',
                'cookies' => array(
                    array( 'name' => '_hjSessionUser_*', 'provider' => 'Hotjar (via GTM)', 'category' => 'statistics', 'duration' => '1 year',     'description' => 'Hotjar user tracking deployed via Google Tag Manager.' ),
                    array( 'name' => '_hjSession_*',     'provider' => 'Hotjar (via GTM)', 'category' => 'statistics', 'duration' => '30 minutes', 'description' => 'Hotjar session deployed via Google Tag Manager.' ),
                ),
            ),
            // Microsoft Clarity — GTM template type: __clarity
            array(
                'pattern' => '/clarity\.ms\/tag|clarity\.ms\/s\/|"__clarity"/i',
                'cookies' => array(
                    array( 'name' => '_clck', 'provider' => 'Microsoft Clarity (via GTM)', 'category' => 'statistics', 'duration' => '1 year', 'description' => 'Clarity analytics deployed via Google Tag Manager.' ),
                    array( 'name' => '_clsk', 'provider' => 'Microsoft Clarity (via GTM)', 'category' => 'statistics', 'duration' => '1 day',  'description' => 'Clarity session deployed via Google Tag Manager.' ),
                ),
            ),
            // HubSpot
            array(
                'pattern' => '/js\.hs-scripts\.com|js\.hs-analytics\.net|js\.hubspot\.com|"__hubspot"/i',
                'cookies' => array(
                    array( 'name' => '__hstc',     'provider' => 'HubSpot (via GTM)', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'HubSpot tracking deployed via Google Tag Manager.' ),
                    array( 'name' => 'hubspotutk', 'provider' => 'HubSpot (via GTM)', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'HubSpot visitor deployed via Google Tag Manager.' ),
                ),
            ),
            // Google Analytics (GA4 / UA) — GTM template types: __gaawc (GA4 config), __gaawe (GA4 event), __ua (UA)
            array(
                'pattern' => '/google-analytics\.com\/analytics|G-[A-Z0-9]{6,}|UA-\d+-\d+|gtag\/js\?id=|"__gaawc"|"__gaawe"|"__ua"|"__ogt_ga4"/i',
                'cookies' => array(
                    array( 'name' => '_ga',   'provider' => 'Google Analytics (via GTM)', 'category' => 'statistics', 'duration' => '2 years',  'description' => 'Google Analytics deployed via Google Tag Manager.' ),
                    array( 'name' => '_ga_*', 'provider' => 'Google Analytics (via GTM)', 'category' => 'statistics', 'duration' => '2 years',  'description' => 'GA4 session persistence deployed via Google Tag Manager.' ),
                    array( 'name' => '_gid',  'provider' => 'Google Analytics (via GTM)', 'category' => 'statistics', 'duration' => '24 hours', 'description' => 'GA daily user tracking deployed via Google Tag Manager.' ),
                ),
            ),
            // Floodlight / Campaign Manager — GTM template types: __flc, __fls
            array(
                'pattern' => '/floodlight|fls\.doubleclick|ad\.doubleclick|Floodlight|"__flc"|"__fls"/i',
                'cookies' => array(
                    array( 'name' => 'IDE', 'provider' => 'Campaign Manager (via GTM)', 'category' => 'marketing', 'duration' => '1 year', 'description' => 'Floodlight tracking deployed via Google Tag Manager.' ),
                ),
            ),
            // Criteo
            array(
                'pattern' => '/static\.criteo\.net|dis\.criteo\.com\/dis|criteo_q\s*=|"__criteo"/i',
                'cookies' => array(
                    array( 'name' => 'cto_bundle', 'provider' => 'Criteo (via GTM)', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'Criteo retargeting deployed via Google Tag Manager.' ),
                ),
            ),
            // Bing / Microsoft Ads — GTM template type: __baut
            array(
                'pattern' => '/bat\.bing\.com|"__baut"|UET\s*Tag|uetq/i',
                'cookies' => array(
                    array( 'name' => '_uetsid',  'provider' => 'Microsoft Ads (via GTM)', 'category' => 'marketing', 'duration' => '1 day',    'description' => 'Bing UET session tracking deployed via Google Tag Manager.' ),
                    array( 'name' => '_uetvid',  'provider' => 'Microsoft Ads (via GTM)', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'Bing UET visitor tracking deployed via Google Tag Manager.' ),
                    array( 'name' => 'MUID',     'provider' => 'Microsoft Ads (via GTM)', 'category' => 'marketing', 'duration' => '13 months', 'description' => 'Microsoft user identifier deployed via Google Tag Manager.' ),
                ),
            ),
            // Taboola
            array(
                'pattern' => '/cdn\.taboola\.com|trc\.taboola\.com|"__taboola"/i',
                'cookies' => array(
                    array( 'name' => 't_gid',  'provider' => 'Taboola (via GTM)', 'category' => 'marketing', 'duration' => '1 year', 'description' => 'Taboola tracking deployed via Google Tag Manager.' ),
                    array( 'name' => 't_pt_gid','provider' => 'Taboola (via GTM)', 'category' => 'marketing', 'duration' => '1 year', 'description' => 'Taboola tracking deployed via Google Tag Manager.' ),
                ),
            ),
            // Outbrain
            array(
                'pattern' => '/outbrain\.com\/outbrain|amplify\.outbrain|"__outbrain"/i',
                'cookies' => array(
                    array( 'name' => 'obuid', 'provider' => 'Outbrain (via GTM)', 'category' => 'marketing', 'duration' => '3 months', 'description' => 'Outbrain tracking deployed via Google Tag Manager.' ),
                ),
            ),
        );

        foreach ( $gtm_service_patterns as $service ) {
            if ( preg_match( $service['pattern'], $js_clean ) ) {
                foreach ( $service['cookies'] as $cookie ) {
                    $found[] = $cookie;
                }
            }
        }

        return $found;
    }

    /**
     * Fetch a page with wp_remote_get.
     */
    private static function fetch_page( $url ) {
        return wp_remote_get( $url, array(
            'timeout'    => 15,
            'sslverify'  => false,
            'user-agent' => 'ConvertaCookieScanner/1.0 (WordPress Plugin)',
            'headers'    => array( 'Accept' => 'text/html' ),
        ));
    }

    /**
     * Extract cookie name from a Set-Cookie header string.
     */
    private static function parse_cookie_name( $raw ) {
        $parts = explode( '=', $raw, 2 );
        $name  = trim( $parts[0] );
        return ( strlen( $name ) > 0 && strlen( $name ) < 100 ) ? $name : '';
    }

    /**
     * Try to extract expiry info from Set-Cookie header.
     */
    private static function parse_cookie_duration( $raw ) {
        // Check for max-age
        if ( preg_match( '/max-age\s*=\s*(\d+)/i', $raw, $m ) ) {
            $secs = (int) $m[1];
            if ( $secs <= 0 )        return 'Session';
            if ( $secs < 3600 )      return round( $secs / 60 ) . ' minutes';
            if ( $secs < 86400 )     return round( $secs / 3600 ) . ' hours';
            if ( $secs < 2592000 )   return round( $secs / 86400 ) . ' days';
            if ( $secs < 31536000 )  return round( $secs / 2592000 ) . ' months';
            return round( $secs / 31536000, 1 ) . ' years';
        }

        // Check for expires
        if ( preg_match( '/expires\s*=\s*([^;]+)/i', $raw, $m ) ) {
            $ts = strtotime( trim( $m[1] ) );
            if ( $ts ) {
                $diff = $ts - time();
                if ( $diff <= 0 )         return 'Session';
                if ( $diff < 86400 )      return round( $diff / 3600 ) . ' hours';
                if ( $diff < 2592000 )    return round( $diff / 86400 ) . ' days';
                if ( $diff < 31536000 )   return round( $diff / 2592000 ) . ' months';
                return round( $diff / 31536000, 1 ) . ' years';
            }
        }

        return 'Session';
    }

    /**
     * Guess provider from cookie name for auto-detected cookies.
     */
    private static function guess_provider( $name ) {
        $map = array(
            '_ga'       => 'Google Analytics',
            '_gid'      => 'Google Analytics',
            '_gat'      => 'Google Analytics',
            '_gcl'      => 'Google Ads',
            '_fbp'      => 'Facebook',
            '_fbc'      => 'Facebook',
            'PHPSESSID' => 'PHP',
            'wp-'       => 'WordPress',
            'wordpress' => 'WordPress',
        );
        foreach ( $map as $prefix => $provider ) {
            if ( stripos( $name, $prefix ) === 0 ) {
                return $provider;
            }
        }
        return parse_url( home_url(), PHP_URL_HOST );
    }

    /**
     * Guess category from cookie name for auto-detected cookies.
     */
    private static function guess_category( $name ) {
        $lower = strtolower( $name );
        // Known marketing prefixes
        if ( preg_match( '/^(_fbp|_fbc|_gcl|fr|IDE|_pin|_ttp|_scid|muc_ads)/', $lower ) ) {
            return 'marketing';
        }
        // Known statistics prefixes
        if ( preg_match( '/^(_ga|_gid|_gat|_hj|_pk|_cl)/', $lower ) ) {
            return 'statistics';
        }
        // Everything else → necessary as safe default
        return 'necessary';
    }
}
