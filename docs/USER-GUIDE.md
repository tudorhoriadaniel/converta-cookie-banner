# Converta Cookie Banner — Complete User Guide

🌍 **Languages:** [Română](GHID-UTILIZARE.md) · **English** · [Français](GUIDE-UTILISATION.md)

This guide covers everything you need to install, configure and maintain the plugin on any WordPress site: GDPR consent banner with Google Consent Mode v2, cookie scanner, statistics, legal pages (including multilingual with Polylang) and automatic updates from GitHub.

---

## Contents

1. [Installation](#1-installation)
2. [How the banner works](#2-how-the-banner-works)
3. [Google Tag Manager integration](#3-google-tag-manager-integration)
4. [Banner Design — colors, sizes, reopening consent](#4-banner-design)
5. [Translations — the banner's 6 languages](#5-translations)
6. [Cookie Scanner](#6-cookie-scanner)
7. [Consent statistics](#7-consent-statistics)
8. [Legal Pages — Privacy Policy and Terms & Conditions](#8-legal-pages)
9. [Multilingual sites](#9-multilingual-sites)
10. [Automatic updates and rollback](#10-automatic-updates-and-rollback)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Installation

### First install on a site

1. Download the plugin ZIP (from this repository or received directly).
2. In WordPress admin: **Plugins → Add New → Upload Plugin** → choose the ZIP → **Install Now** → **Activate**.
3. Done — the banner appears immediately for visitors without a consent cookie. All configuration lives under the **Cookie Consent** menu in the sidebar.

> **Important:** after the first install you never upload ZIPs again — the plugin updates itself from GitHub (see [section 10](#10-automatic-updates-and-rollback)).

### If installation fails with "Could not create directory"

That's a server permission issue (common on VPS/Docker), not a plugin bug. Whoever manages the server should run:

```bash
sudo mkdir -p /path/to/site/wp-content/upgrade
sudo chown -R www-data:www-data /path/to/site/wp-content
```

### What is kept and what is lost

| Action | Settings & statistics |
|---|---|
| Update (automatic, or manual "Replace current with uploaded") | ✅ kept |
| Rollback to an earlier version | ✅ kept |
| Temporary deactivation | ✅ kept |
| **Delete** | ❌ permanently removed (statistics, settings; generated legal pages remain) |

---

## 2. How the banner works

### Consent before Google Tag Manager — guaranteed

The plugin injects the consent script as the **very first script in `<head>`** via page output buffering — no matter how GTM is installed (hardcoded in `header.php`, via another plugin, via the theme). As a result:

- `gtag('consent', 'default', …)` — everything denied (except `security_storage`) — runs **before** the GTM container;
- for returning visitors who already consented, `gtag('consent', 'update', …)` runs right after, still before GTM.

Verify with [Google Tag Assistant](https://tagassistant.google.com/): the **Consent Default** event must appear before **Consent Initialization**. (Note: to debug the GTM container, start the session from GTM → the **Preview** button, not directly from Tag Assistant.)

### Ad-blocker resistant (Brave, uBlock Origin, AdGuard)

Cookie-notice filter lists normally hide consent banners. The plugin legitimately survives them: frontend CSS and JS are **inlined into the page** (no asset URLs to block) and every id/class uses neutral naming without the words "cookie/consent/banner". Result: visitors running ad blockers can still give consent.

### SEO-clean

The banner texts **do not exist in the page HTML** — they load via AJAX in the browser only, so they never appear as duplicate content on every URL. Additionally every container carries `data-nosnippet`, so Google never uses banner text in snippets.

### Demo parameters

Useful for client demos or screenshots, on any page:

- `?pcc_demo=1` — force-opens the banner (even if you already consented)
- `?pcc_demo=prefs` — opens the preferences panel with categories directly

---

## 3. Google Tag Manager integration

### dataLayer events for triggers

| Event | Fires when |
|---|---|
| `cookie_necessary` | every page load |
| `cookie_analytics` | Statistics consent granted |
| `cookie_marketing` | Marketing consent granted |
| `cookie_functional` | every consent save (always granted) |

Consent Mode mapping: **Marketing** → `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage`; **Statistics** → `analytics_storage`; `security_storage` — always granted.

### Advanced vs. Basic (Banner Design → "Google Tag Manager — blocking mode")

- **Advanced Consent Mode** *(default, recommended)* — GTM loads immediately on every page; Consent Mode signals keep Google tags cookieless until consent. You keep GA4 behavioral modeling and better data quality. **The plugin never blocks the GTM script in this mode.**
- **Basic Consent Mode (hard block)** — optional, for strict requirements: the GTM/gtag script **does not load at all** until the visitor grants Statistics or Marketing. The cost: you lose all data from visitors who reject or ignore the banner.

---

## 4. Banner Design

**Cookie Consent → Banner Design** — everything previews live and applies after **Save Design**.

### Colors and sizes

Every element has its own color picker (background, Accept/Reject/Customize/Save/Cancel buttons with hover states, category cards, toggles, badges, overlay), plus corner radius and font sizes.

### Reopen Consent — how visitors change their choice (GDPR requirement)

Three options:

1. **Floating Icon** — a round cookie-symbol button fixed bottom-left, visible after consent.
2. **Footer Link** — an automatically translated text link ("Cookie Settings", "Setări cookie-uri", "Cookie-Einstellungen"…) **smart-inserted into the theme's footer**: it first looks for a footer menu, then the copyright row, then — for custom hand-coded footers — places itself after the last link inside `<footer>`. Only if nothing is found does it fall back to a discreet bar of its own. The text is editable under **Translations**.
3. **Both** — icon and link together.

### Manual link placement (optional, works with any option)

- Shortcode: `[pcc_consent_link]` or `[pcc_consent_link text="Cookies"]` — in any page, widget or footer.
- CSS class `pcc-plink` on any link or menu item (Appearance → Menus → enable "CSS Classes" in Screen Options, then add the class to a Custom Link with URL `#`).
- When a manually placed link exists, the automatic one **steps aside on its own** — no duplicates.

---

## 5. Translations

**Cookie Consent → Translations** — the banner ships fully translated into **Romanian, English, German, French, Italian, Spanish**.

### Language detection

- **URL Subfolder** — from the URL prefix: `/en/`, `/de/`, `/fr/`… (suits Polylang/WPML with subdirectories)
- **HTML lang** — from the page's `<html lang="…">` attribute (compatible with any translation plugin)
- **Default language** — fallback when nothing is detected

### Editing texts

Each language has its own tab; every string is editable: heading, body, buttons, category names and descriptions, the footer link text. **Reset to Defaults** restores the original translations.

---

## 6. Cookie Scanner

**Cookie Consent → Cookie Scanner**

1. **Scan Website for Cookies** — crawls up to 100 pages of the site and detects server-set cookies plus known tracking scripts.
2. **Quick Add — Cookie Library** — one-click adds the standard sets for common platforms (Google Analytics, Google Ads, Facebook, LinkedIn, etc.).
3. **Editable list** — name, provider, category (necessary/statistics/marketing), duration, description; add or remove entries manually.

The list is shown publicly in the banner's preferences panel, grouped by category with per-category cookie counts.

---

## 7. Consent statistics

**Cookie Consent → Statistics** — periods of 7/30/90/365 days:

- Total interactions, unique visitors, Accept All / Reject All / Custom
- Charts: action breakdown, Statistics granted, Marketing granted, daily trend
- Consent rates (overall accept, statistics, marketing)

**Privacy:** no IP addresses are stored; visitors are counted via a salted SHA-256 hash. Data lives in the plugin's own database table and survives updates.

---

## 8. Legal Pages

**Cookie Consent → Legal Pages** — use the legal pages your site already has, or generate them from company data. Golden rule: **the plugin never creates duplicates.**

### Step 1 — Company data

Fill in: brand/company name, VAT/tax ID, trade registry no. (optional), address, email, phone (optional), website URL. Used only for generation. **Empty fields are simply omitted from the text** — no "[VAT]"-style placeholders ever appear.

### Step 2 — Automatic detection

For each document (Privacy Policy, Terms & Conditions), the plugin looks for existing pages:

1. the native WordPress privacy-page setting;
2. common slugs across all 6 languages (`privacy-policy`, `politica-de-confidentialitate`, `terms-and-conditions`, `termeni-si-conditii`, `datenschutz`, `agb`…);
3. title fragments.

- **Published page found** → auto-selected, labeled "your existing page — the plugin will not touch it", and the Generate button is **disabled** ("no duplicates").
- **Draft found** (e.g. the "Privacy Policy" draft WordPress auto-creates on install) → the button becomes **"Complete & publish draft"**: it fills the draft with generated content and publishes it — again, no duplicate.
- **Nothing found** → **Generate page** creates it.

### What the generated text contains

Complete templates in **Romanian and English** (other languages: EN template with a localized title):

- **Privacy Policy:** controller identity, data collected, purposes and legal bases (GDPR), a cookies section with a live `[pcc_consent_link]` back to the banner, recipients and transfers, retention, data-subject rights, security, updates.
- **Terms & Conditions:** identification, acceptance, intellectual property, acceptable use, liability limitation, data protection, governing law and disputes (incl. the EU ODR platform), changes.

Every page ends with a **disclaimer**: it is an automatically generated template, not legal advice — review by a qualified professional is recommended.

### Regeneration without duplicates

Generated pages carry an internal marker. **Regenerate content** updates the *same* page (or restores it from trash if you deleted it) — a second copy never appears.

### Footer Links

The "Add footer links" checkbox adds footer links **only for pages generated by the plugin**. Pre-existing pages never get extra links (your theme already links them) — only the Cookie Settings link is added. This guarantees no duplicate footer links.

### WordPress sync

When generating the Privacy Policy, if the native WP privacy-page setting is empty, the plugin fills it automatically (other plugins, e.g. forms, use it).

---

## 9. Multilingual sites

### Polylang (full, built-in support)

With Polylang active and multiple languages, each document gets a **Languages** table:

| Column | Meaning |
|---|---|
| Language | every site language; the default one is marked "(default)" and managed by the main selector |
| Page | the page found for that language (existing or generated) with view/edit links, or "missing" |
| Action | **Generate (EN/DE/…)** only for missing languages; **Regenerate** for plugin-generated ones |

What the plugin does automatically when generating a translation:

- creates the page in that language (localized title + RO/EN template);
- sets its Polylang language and **links it as a translation** of the default-language page — in whatever order you generate languages;
- footer links (if enabled) point to **the version in the visitor's language**.

Detection respects existing translations too: if the EN page already exists (created by you or linked in Polylang), it is selected — no second one is generated.

> Tip: if you can't see other languages' pages in admin lists, switch the Polylang language filter in the top bar to "Show all languages".

### WPML

The plugin shows a dedicated notice: generate the default-language pages here, then create translations with WPML's own tools. (Automatic translation linking is available for Polylang only.)

### TranslatePress / Weglot

These plugins translate the *same* page on the fly — so **one page per document is enough**; the plugin shows the matching notice and does not ask for per-language versions.

### Single-language site

Without a translation plugin everything is simple: one document per type, in the site language; the language table doesn't even appear.

---

## 10. Automatic updates and rollback

### Updates

The plugin uses WordPress's native update mechanism (`Update URI`) connected to this repository:

- when a new version is published on the `main` branch, the standard "There is a new version… update now" notice appears on the **Plugins** page — one click and done;
- checks run every few hours (6h cache + the WordPress cycle); **Dashboard → Updates → "Check again"** forces an immediate check;
- you can enable per-site **"Auto-updates"** on the Plugins page for zero-touch maintenance;
- **"View details"** opens the full window with Description / Installation / FAQ / Screenshots / **Changelog** — the history of every version, read live from GitHub.

### Rollback — return to any version

**Banner Design → Plugin Updates from GitHub → Version switch / rollback:**

1. pick a version from the list (each shows its own description, e.g. "v2.0.1 — Check again now bypasses the cache…");
2. **Install selected version** — installs itself, settings and statistics preserved;
3. updates are **pinned to that version**, so the site won't offer you back the release you just left;
4. when you want the latest again: **Resume updates (back to latest)**.

### GitHub token (optional)

The token field is only required while the repository is private. On a public repo everything works without it; still, a token (GitHub → Settings → Developer settings → Personal access tokens, read-only) raises the API limit from 60 to 5,000 requests/hour — useful if you ever see "GitHub not reachable" during repeated checks.

---

## 11. Troubleshooting

**The banner doesn't appear on the site**
Clear the page cache (caching plugin/CDN), then test in incognito. The banner doesn't show for visitors who already consented — use `?pcc_demo=1` to test.

**The banner doesn't appear in Brave / with uBlock**
Since v1.7.0 the plugin is immune to the standard filter lists. If it still disappears, the visitor runs aggressive custom filter lists; confirm with shields disabled and report it.

**"GTM-XXXX is not enabled for debugging" in Tag Assistant**
Not a blocking issue — start the debug session from GTM (tagmanager.google.com → your container → **Preview**), not directly from tagassistant.google.com.

**"Duplicate" legal links in the footer**
Check the page source (view-source): if the links exist in the raw HTML, they come from the theme/menu, not from the plugin. The plugin only adds legal links for pages it generated, and only with the checkbox enabled.

**I know an update exists but don't see it**
Dashboard → Updates → "Check again". If it still doesn't appear, check the status under Banner Design → Plugin Updates; "GitHub not reachable" usually means the API rate limit — add a token or retry within the hour.

**Versions older than 1.9.0**
They predate the self-updater — they need one last manual ZIP upload ("Replace current with uploaded"), after which they join the automatic circuit.

**A Banner Design setting doesn't save**
Hard-reload with Cmd/Ctrl+Shift+R (stale cached admin script) and save again.

---

*User guide for Converta Cookie Banner · [converta.ro](https://converta.ro) · updated together with the plugin, in this repository.*
