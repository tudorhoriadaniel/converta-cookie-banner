# Converta Cookie Banner

GDPR/ePrivacy cookie consent banner for WordPress with **Google Consent Mode v2**, a built-in cookie scanner, consent statistics dashboard, full design customizer, and translations in 6 languages.

**Version:** 2.4.1 · **License:** GPL v2 or later · **Author:** [Converta](https://converta.ro)

## Features

- **Google Consent Mode v2** — pushes `consent default` (all denied except `security_storage`) and, for returning visitors, `consent update` **before Google Tag Manager loads**, no matter how GTM is installed (theme `header.php`, another plugin, `wp_head`, hardcoded snippet). The script is injected as the very first script after `<head>` via output buffering, with an early `wp_head` fallback.
- **Consent categories** — Necessary (always on), Analytics/Statistics, Marketing — with per-category cookie tables in the preferences panel.
- **Cookie scanner** — crawls up to 100 pages of your site, detects cookies and known tracking scripts, plus a one-click "Quick Add" library for common platforms. Fully editable cookie list.
- **Statistics dashboard** — accept/reject/custom rates, unique visitors, daily trend charts (Chart.js), selectable period (7/30/90/365 days). Consent actions are logged with a salted SHA-256 visitor hash (no raw IPs stored).
- **Design customizer** — every color, corner radius, and font size, with live preview.
- **Translations** — English, French, German, Italian, Romanian, Spanish. Language detection via URL subfolder (`/fr/`, `/de/`…) or the `<html lang>` attribute (WPML, Polylang, TranslatePress compatible), with a configurable fallback.
- **Reopen consent** (GDPR requirement) — choose how visitors change their mind later:
  - **Floating icon** — small round button fixed bottom-left (default)
  - **Footer link** — a discreet translated text link ("Cookie Settings" / "Paramètres des cookies" / "Cookie-Einstellungen" / …) at the bottom of every page
  - **Both**
  - Plus a shortcode `[pcc_consent_link]` (optional `text="…"` attribute) and support for adding the CSS class `pcc-plink` to any link or menu item (the old `pcc-consent-link` class keeps working) — these work with every option above.
- **dataLayer events** — fires `cookie_necessary`, `cookie_analytics`, `cookie_marketing`, `cookie_functional` events for GTM triggers based on the granted categories, on every page load for returning visitors.

## Installation

1. Download this repository as a ZIP (or grab a release ZIP).
2. In WordPress admin go to **Plugins → Add New → Upload Plugin** and upload the ZIP.
3. Activate. The banner appears immediately for visitors without a consent cookie.
4. Configure under the **Cookie Consent** admin menu:
   - **Statistics** — consent analytics dashboard
   - **Cookie Scanner** — scan your site and manage the cookie list
   - **Banner Design** — colors, sizes, and the *Reopen Consent* method
   - **Translations** — language detection and all banner texts

### Updates — no more zip uploads

Once installed, the plugin **updates itself directly from this GitHub repository** (main branch) using WordPress's native update mechanism: when a newer version is pushed here, a standard "Update available" notice appears on the site's Plugins page. While the repository is private, paste a GitHub personal access token (read access to this repo) in **Banner Design → Plugin Updates from GitHub**; a public repository needs no token.

## Google Tag Manager setup

The plugin handles the consent side; in GTM you can use these dataLayer events as triggers for tags that must wait for consent:

| Event | Fired when |
|---|---|
| `cookie_necessary` | every page load |
| `cookie_analytics` | Statistics consent granted |
| `cookie_marketing` | Marketing consent granted |
| `cookie_functional` | every consent save (always granted) |

Consent Mode mapping: Marketing → `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage`; Statistics → `analytics_storage`; `security_storage` is always granted.

Verify with [Google Tag Assistant](https://tagassistant.google.com/): **Consent Default** must appear before GTM's **Consent Initialization** event.

## Data & privacy

- Consent choices are stored in a first-party cookie `procab_cookie_consent` (365 days, `SameSite=Lax; Secure`).
- Consent actions are logged to a custom table (`wp_pcc_consent_log`) with a salted hash — no raw IP addresses.
- Uninstalling the plugin drops the log table and deletes all plugin options.

## Changelog

### 2.4.1
- Works correctly in every setup when the legal pages don't exist yet: unpublished drafts (like the Privacy Policy draft WordPress auto-creates on install) are adopted — Generate completes and publishes them instead of refusing; Polylang translation linking works in any generation order; the default-language template follows Polylang's default language; clear guidance shown for WPML and on-the-fly translators (TranslatePress/Weglot)

### 2.4.0
- **Multilingual legal pages (Polylang):** each language gets its own Privacy/Terms page — a per-language table on the Legal Pages screen shows what exists and offers Generate only for missing languages; generated translations are linked automatically as Polylang translations, and the footer links point to the version in the visitor's language
- Generated texts no longer print empty placeholders: CUI, registry number, address, email and phone are included only when filled in; sentences adapt when a field is missing

### 2.3.1
- Legal Pages form: generic placeholders only (Example SRL, example@example.com — no real names), removed the separate Legal Entity Name field (Brand / Company Name is used everywhere), and the email field is no longer prefilled

### 2.3.0
- Full wordpress.org-style "View details" window: branded banner image, plugin icon (shown in update notices too), and complete Description / Installation / FAQ / Screenshots / Changelog tabs with real plugin information
- New demo parameter (?pcc_demo=1 or ?pcc_demo=prefs) to force the banner or preferences panel open — useful for client demos and screenshots

### 2.2.0
- Human-readable versions everywhere: the rollback dropdown now shows each version with a short description of what it changed, and "View details" on the Plugins page opens the full changelog (both read automatically from GitHub)

### 2.1.0
- **One-click version switch / rollback** (Banner Design → Plugin Updates from GitHub): pick any released version from a dropdown and install it directly — no zips, correct folder naming, settings and statistics preserved. After a rollback, updates are **pinned** to that version (the site won't be offered the newer release again) until you press "Resume updates".
- All releases are now tagged on GitHub (v1.5.0 → current), so every version stays permanently available.
- Fix: saving an empty GitHub token no longer clears the company data and legal-pages settings (regression from 2.0.0's uninstall cleanup).

### 2.0.1
- "Check again" on Dashboard → Updates now bypasses the plugin's 6-hour GitHub version cache, so freshly pushed releases appear immediately on a forced check

### 2.0.0
- New **Legal Pages** admin section (Cookie Consent → Legal Pages), designed to be **duplicate-proof**:
  - Detects Privacy Policy / Terms & Conditions pages the site already has (WordPress core privacy setting, then slug/title heuristics in all 6 supported languages) and selects them automatically
  - Generation from company data (name, CUI, address, email, …) is offered **only when no page exists** — while a page is detected, the Generate button is disabled, so duplicates are impossible
  - Pages generated by the plugin carry an internal marker; regenerating updates or restores the same page instead of creating another one
  - Generated templates in Romanian and English (other site languages fall back to English), including a live `[pcc_consent_link]` in the cookies section, WordPress core privacy-page setting sync, and a not-legal-advice disclaimer
  - **Footer links are added only for pages the plugin generated.** Pre-existing pages are assumed to be linked by the theme already, so the plugin adds only the Cookie Settings link — exactly avoiding duplicate footer links
  - Nothing is ever generated automatically (not on activation, not on update); pages are never deleted on uninstall

### 1.9.0
- **Self-updates from GitHub**: the plugin now uses WordPress's native `Update URI` mechanism to check this repository's main branch for new versions and offers them as normal one-click updates on the Plugins page — no manual zip uploads. New "Plugin Updates from GitHub" section on the Banner Design page shows the update status and accepts an optional GitHub token for private-repo access.

### 1.8.0
- New **Google Tag Manager blocking mode** option (Banner Design page):
  - **Advanced Consent Mode** (default) — GTM loads immediately on every page; Consent Mode signals keep Google tags cookieless until consent (best data quality, enables GA4 behavioral modeling). This is the plugin's existing behavior, now explicit.
  - **Basic Consent Mode (hard block)** — every script referencing `googletagmanager.com` is neutralized (`type="text/plain"`) server-side and only executed after the visitor grants Statistics or Marketing consent (immediately on later visits for visitors who already granted).

### 1.7.0
- **Ad-blocker hardening (Brave, uBlock Origin, AdGuard):** the banner was invisible for visitors with cookie-notice-blocking filters enabled, because (a) the plugin's asset URLs contain "cookie-banner" and were blocked by network filters, and (b) its element ids/classes (`pcc-cookie-banner`, `pcc-consent-link`, …) matched cosmetic hiding filters. Fixes:
  - Frontend CSS and JS are now **inlined into the page** — no external plugin asset URLs to block
  - All frontend ids/classes renamed to neutral names with no "cookie"/"consent"/"banner" words (`pcc-ui-card`, `pcc-ui-overlay`, `pcc-plink`, …)
  - AJAX action renamed `pcc_get_banner` → `pcc_load_ui` (old name kept as alias)
  - Neutral `aria-label`/`title` attributes
  - Manually placed links with the old `pcc-consent-link` class are automatically migrated to `pcc-plink` at runtime — existing menu items keep working

### 1.6.1
- Corrected plugin/author URLs to converta.ro

### 1.6.0
- **SEO: zero banner markup in the page HTML.** The banner's texts (headings, category descriptions, buttons) are no longer rendered server-side on every page — they were appearing as duplicate content in the source of every URL. The markup is now fetched via AJAX (`pcc_get_banner`) and injected client-side by banner.js. Language detection still works: the page's path and `<html lang>` are passed along with the request.
- All banner containers carry `data-nosnippet`, so search engines never use banner text in snippets.
- Consent Mode default/update in `<head>` and the dataLayer events are unchanged — GTM behavior is identical.

### 1.5.4
- Universal footer detection for custom/hand-coded footers: when no WordPress footer menu or copyright row is found, the consent link is inserted right after the last link inside the page's `<footer>` element, inheriting its styling — works with any theme or custom-built page

### 1.5.3
- If a consent link is placed manually (menu item with the `pcc-consent-link` class, shortcode, or widget), the automatic footer link steps aside — no duplicates

### 1.5.2
- Footer link is now **theme-aware**: it automatically inserts itself into the theme's own footer links area — the footer menu (last menu in the footer, e.g. next to "Contact · Blog") or, failing that, the copyright/site-info row — inheriting the theme's link styling. The standalone bottom bar remains only as a fallback for themes with no footer links area.

### 1.5.1
- Floating icon now shows a **cookie symbol** and is hardened against theme button styles (`!important` on size, padding, and `border-radius: 50%`), so it stays perfectly round in any theme
- Footer consent link now has a solid background bar (banner background color), so it is legible over theme background images and no longer exposes a strip of the body background below the footer

### 1.5.0
- New **Reopen Consent** option (Banner Design page): floating icon, translated footer link, or both
- New `[pcc_consent_link]` shortcode and `pcc-consent-link` CSS class for placing a consent link anywhere (footer menu, widget, privacy page)
- New translatable string "Consent Reopen Link Text" in all 6 languages

### 1.4.0
- Consent Mode default + update now injected as the **first script in `<head>`** via output buffering, guaranteeing it runs before GTM regardless of how the container snippet is installed
- JS guard against double injection; skips feeds, REST, AJAX, embeds, and non-HTML output

### 1.3.1
- Cookie scanner, statistics dashboard, design customizer, 6-language translations, Consent Mode v2 baseline
