# Converta Cookie Banner

GDPR/ePrivacy cookie consent banner for WordPress with **Google Consent Mode v2**, a built-in cookie scanner, consent statistics dashboard, full design customizer, and translations in 6 languages.

**Version:** 1.5.1 · **License:** GPL v2 or later · **Author:** [Converta](https://converta.ch)

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
  - Plus a shortcode `[pcc_consent_link]` (optional `text="…"` attribute) and support for adding the CSS class `pcc-consent-link` to any link or menu item — these work with every option above.
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
