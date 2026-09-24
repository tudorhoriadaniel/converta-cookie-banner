# Converta Cookie Banner — Ghid complet de utilizare

🌍 **Limbi:** **Română** · [English](USER-GUIDE.md) · [Français](GUIDE-UTILISATION.md)

Ghidul acoperă tot ce trebuie să știi ca să instalezi, configurezi și întreții pluginul pe orice site WordPress: banner de consimțământ GDPR cu Google Consent Mode v2, scanner de cookie-uri, statistici, pagini legale (inclusiv multilingv cu Polylang) și update-uri automate din GitHub.

---

## Cuprins

1. [Instalare](#1-instalare)
2. [Cum funcționează bannerul](#2-cum-funcționează-bannerul)
3. [Integrarea cu Google Tag Manager](#3-integrarea-cu-google-tag-manager)
4. [Banner Design — culori, dimensiuni, redeschiderea consimțământului](#4-banner-design)
5. [Traduceri — cele 6 limbi ale bannerului](#5-traduceri)
6. [Cookie Scanner](#6-cookie-scanner)
7. [Statistici de consimțământ](#7-statistici-de-consimțământ)
8. [Legal Pages — Politica de Confidențialitate și Termeni & Condiții](#8-legal-pages)
9. [Site-uri multilingve](#9-site-uri-multilingve)
10. [Update-uri automate și rollback](#10-update-uri-automate-și-rollback)
11. [Depanare — probleme frecvente](#11-depanare)

---

## 1. Instalare

### Prima instalare pe un site

1. Descarcă ZIP-ul pluginului (din acest repository sau primit direct).
2. În WordPress admin: **Module → Adaugă modul → Încarcă modul** → alege ZIP-ul → **Instalează** → **Activează**.
3. Gata — bannerul apare imediat pentru vizitatorii care nu au încă un cookie de consimțământ. Toată configurarea se face din meniul **Cookie Consent** din bara laterală.

> **Important:** după prima instalare nu mai încarci niciodată ZIP-uri manual — pluginul se actualizează singur din GitHub (vezi [secțiunea 10](#10-update-uri-automate-și-rollback)).

### Dacă instalarea eșuează cu „Nu am putut să creez directorul"

Este o problemă de permisiuni pe server (frecventă pe VPS/Docker), nu a pluginului. Cine administrează serverul trebuie să ruleze:

```bash
sudo mkdir -p /calea/catre/site/wp-content/upgrade
sudo chown -R www-data:www-data /calea/catre/site/wp-content
```

### Ce se păstrează și ce se pierde

| Acțiune | Setări & statistici |
|---|---|
| Update (automat sau manual cu „Înlocuiește versiunea curentă") | ✅ se păstrează |
| Rollback la o versiune anterioară | ✅ se păstrează |
| Dezactivare temporară | ✅ se păstrează |
| **Ștergere (Delete)** | ❌ se pierd definitiv (statistici, setări; paginile legale generate rămân) |

---

## 2. Cum funcționează bannerul

### Consimțământul înaintea lui Google Tag Manager — garantat

Pluginul injectează scriptul de consimțământ ca **primul script din `<head>`**, prin bufferizarea paginii — indiferent cum e instalat GTM (hardcodat în `header.php`, prin alt plugin, prin temă). Astfel:

- `gtag('consent', 'default', …)` — totul refuzat (except `security_storage`) — rulează **înaintea** containerului GTM;
- pentru vizitatorii care au consimțit deja, `gtag('consent', 'update', …)` rulează imediat după, tot înaintea GTM.

Poți verifica în [Google Tag Assistant](https://tagassistant.google.com/): evenimentul **Consent Default** trebuie să apară înaintea **Consent Initialization**. (Atenție: pentru a depana containerul GTM, pornește sesiunea din GTM → butonul **Preview**, nu direct din Tag Assistant.)

### Rezistent la ad-blockere (Brave, uBlock Origin, AdGuard)

Filtrele anti-cookie-notice blochează de obicei bannerele. Pluginul le ocolește legitim: CSS-ul și JS-ul de frontend sunt **inline în pagină** (nu există URL-uri de blocat), iar toate id-urile/clasele au nume neutre, fără cuvintele „cookie/consent/banner". Rezultat: vizitatorii cu ad-blocker pot totuși să-și dea consimțământul.

### Curat pentru SEO

Textele bannerului **nu există în HTML-ul paginii** — sunt încărcate prin AJAX doar în browser, deci nu apar ca conținut duplicat pe fiecare URL. Suplimentar, toate containerele poartă `data-nosnippet`, ca Google să nu folosească niciodată textul bannerului în snippet-uri.

### Parametri demo

Utili pentru prezentări la clienți sau capturi de ecran, pe orice pagină:

- `?pcc_demo=1` — deschide forțat bannerul (chiar dacă ai consimțit deja)
- `?pcc_demo=prefs` — deschide direct panoul de preferințe cu categorii

---

## 3. Integrarea cu Google Tag Manager

### Evenimente dataLayer pentru trigger-e

| Eveniment | Când se declanșează |
|---|---|
| `cookie_necessary` | la fiecare încărcare de pagină |
| `cookie_analytics` | consimțământ Statistici acordat |
| `cookie_marketing` | consimțământ Marketing acordat |
| `cookie_functional` | la fiecare salvare de consimțământ (mereu acordat) |

Maparea Consent Mode: **Marketing** → `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage`; **Statistici** → `analytics_storage`; `security_storage` — mereu acordat.

### Advanced vs. Basic (Banner Design → „Google Tag Manager — blocking mode")

- **Advanced Consent Mode** *(implicit, recomandat)* — GTM se încarcă imediat pe fiecare pagină; semnalele Consent Mode țin tagurile Google fără cookie-uri până la consimțământ. Păstrezi modelarea comportamentală GA4 și date de calitate. **Pluginul nu blochează niciodată scriptul GTM în acest mod.**
- **Basic Consent Mode (hard block)** — opțional, pentru cerințe stricte: scriptul GTM/gtag **nu se încarcă deloc** până când vizitatorul acordă Statistici sau Marketing. Costul: pierzi complet datele vizitatorilor care refuză sau ignoră bannerul.

---

## 4. Banner Design

**Cookie Consent → Banner Design** — totul se previzualizează live și se aplică după **Save Design**.

### Culori și dimensiuni

Fiecare element are propriul selector de culoare (fundal, butoane Accept/Reject/Customize/Save/Cancel cu hover, carduri categorii, toggle-uri, badge-uri, overlay), plus raza colțurilor și mărimile de font.

### Reopen Consent — cum își schimbă vizitatorii alegerea (cerință GDPR)

Trei variante:

1. **Floating Icon** — buton rotund cu simbol de fursec, fix stânga-jos, vizibil după consimțământ.
2. **Footer Link** — link text tradus automat („Setări cookie-uri", „Cookie Settings", „Cookie-Einstellungen"…), inserat **inteligent în footerul temei**: întâi caută meniul de footer, apoi rândul de copyright, apoi — pentru footere custom, scrise manual — se așază după ultimul link din `<footer>`. Doar dacă nu găsește nimic rămâne o bară discretă proprie. Textul se editează în **Translations**.
3. **Both** — ambele.

### Plasare manuală a linkului (opțional, merge cu orice variantă)

- Shortcode: `[pcc_consent_link]` sau `[pcc_consent_link text="Cookies"]` — în orice pagină, widget sau footer.
- Clasa CSS `pcc-plink` pe orice link sau element de meniu (Aspect → Meniuri → activează „Clase CSS" din Opțiuni ecran, apoi adaugă clasa pe un Custom Link cu URL `#`).
- Când există un link plasat manual, cel automat **se retrage singur** — fără dubluri.

---

## 5. Traduceri

**Cookie Consent → Translations** — bannerul vine tradus complet în **română, engleză, germană, franceză, italiană, spaniolă**.

### Detecția limbii

- **URL Subfolder** — după prefixul din URL: `/en/`, `/de/`, `/fr/`… (potrivit pentru Polylang/WPML cu subdirectoare)
- **HTML lang** — după atributul `<html lang="…">` al paginii (compatibil cu orice plugin de traduceri)
- **Limbă implicită** — fallback când nu se detectează nimic

### Editarea textelor

Fiecare limbă are tab propriu; toate textele sunt editabile: titlu, corp, butoane, denumirile și descrierile categoriilor, textul linkului de footer. **Reset to Defaults** readuce traducerile originale.

---

## 6. Cookie Scanner

**Cookie Consent → Cookie Scanner**

1. **Scan Website for Cookies** — parcurge până la 100 de pagini ale site-ului și detectează cookie-urile setate de server plus scripturile de tracking cunoscute.
2. **Quick Add — Cookie Library** — adaugi cu un click seturile standard pentru platforme uzuale (Google Analytics, Google Ads, Facebook, LinkedIn etc.).
3. **Lista editabilă** — nume, furnizor, categorie (necessary/statistics/marketing), durată, descriere; poți adăuga sau șterge manual.

Lista apare public în panoul de preferințe al bannerului, pe categorii, cu numărul de cookie-uri per categorie.

---

## 7. Statistici de consimțământ

**Cookie Consent → Statistics** — perioade de 7/30/90/365 zile:

- Total interacțiuni, vizitatori unici, Accept All / Reject All / Custom
- Grafice: distribuția acțiunilor, acordare Statistici, acordare Marketing, tendința zilnică
- Rate de consimțământ (accept general, statistici, marketing)

**Confidențialitate:** nu se stochează IP-uri; vizitatorii sunt numărați printr-un hash SHA-256 sărat. Datele stau în tabelul propriu din baza de date și supraviețuiesc update-urilor.

---

## 8. Legal Pages

**Cookie Consent → Legal Pages** — folosește paginile legale existente ale site-ului sau generează-le din datele companiei. Regula de aur: **pluginul nu creează niciodată duplicate.**

### Pasul 1 — Datele companiei

Completează: nume brand/companie, CUI, Nr. Reg. Com. (opțional), adresă, email, telefon (opțional), URL site. Se folosesc doar la generare. **Câmpurile necompletate sunt pur și simplu omise din text** — nu apar placeholder-e de tip „[CUI]".

### Pasul 2 — Detecție automată

Pentru fiecare document (Privacy Policy, Terms & Conditions), pluginul caută pagini existente:

1. setarea nativă WordPress pentru pagina de confidențialitate;
2. sluguri uzuale în toate cele 6 limbi (`politica-de-confidentialitate`, `privacy-policy`, `termeni-si-conditii`, `terms-and-conditions`, `datenschutz`, `agb`…);
3. fragmente de titlu.

- **Pagină publicată găsită** → e selectată automat, marcată „your existing page — the plugin will not touch it", iar butonul Generate e **dezactivat** („no duplicates").
- **Ciornă găsită** (ex. ciorna „Privacy Policy" pe care WordPress o creează singur la instalare) → butonul devine **„Complete & publish draft"**: o umple cu conținut generat și o publică — tot fără duplicat.
- **Nimic găsit** → **Generate page** creează pagina.

### Ce conține textul generat

Șabloane complete în **română și engleză** (alte limbi: șablon EN cu titlu localizat):

- **Politica de Confidențialitate:** operator, date colectate, scopuri și temeiuri (GDPR), secțiune de cookie-uri cu link viu `[pcc_consent_link]` către banner, destinatari și transferuri, durate de stocare, drepturile persoanei (inclusiv ANSPDCP pentru RO), securitate, actualizări.
- **Termeni & Condiții:** identificare, acceptare, proprietate intelectuală, utilizare, limitarea răspunderii, protecția datelor, lege aplicabilă și litigii (ANPC + platforma ODR pentru RO), modificări.

Fiecare pagină se încheie cu un **disclaimer**: este un șablon generat automat, nu consultanță juridică — recomandăm revizuirea de către un specialist.

### Regenerare fără duplicate

Paginile generate poartă un marcaj intern. **Regenerate content** actualizează *aceeași* pagină (sau o restaurează din coș dacă ai șters-o) — niciodată nu apare a doua.

### Footer Links

Bifa „Add footer links" adaugă linkuri în footer **doar pentru paginile generate de plugin**. Paginile care existau deja nu primesc linkuri suplimentare (tema le are deja) — se adaugă doar „Setări cookie-uri". Așa nu apar niciodată linkuri duble în footer.

### Sincronizare cu WordPress

La generarea Politicii de Confidențialitate, dacă setarea nativă WP pentru pagina de confidențialitate e goală, pluginul o completează automat (o folosesc și alte pluginuri, de ex. formularele).

---

## 9. Site-uri multilingve

### Polylang (suport complet, integrat)

Când Polylang e activ cu mai multe limbi, sub fiecare document apare tabelul **Languages**:

| Coloană | Semnificație |
|---|---|
| Language | fiecare limbă a site-ului; cea implicită e marcată „(default)" și se gestionează din selectorul principal |
| Page | pagina găsită pentru acea limbă (existentă sau generată) cu view/edit, sau „missing" |
| Action | **Generate (EN/DE/…)** doar pentru limbile lipsă; **Regenerate** pentru cele generate de plugin |

Ce face pluginul automat la generarea unei traduceri:

- creează pagina în limba respectivă (titlu localizat + șablon RO/EN);
- îi setează limba în Polylang și **o leagă ca traducere** a paginii din limba implicită — în orice ordine generezi limbile;
- linkurile din footer (dacă sunt active) duc la **versiunea în limba vizitatorului**.

Detecția respectă și traducerile existente: dacă pagina EN există deja (creată de tine sau legată în Polylang), e selectată — nu se generează alta.

> Sfat: dacă în listele de pagini din admin nu vezi paginile altei limbi, comută filtrul de limbă Polylang din bara de sus pe „Arată toate limbile".

### WPML

Pluginul afișează o notiță dedicată: generezi paginile în limba implicită din Legal Pages, apoi creezi traducerile prin instrumentele proprii WPML. (Legarea automată de traduceri e disponibilă doar pentru Polylang.)

### TranslatePress / Weglot

Aceste pluginuri traduc *aceeași* pagină din zbor — deci **o singură pagină per document e suficientă**; pluginul afișează notița corespunzătoare și nu îți cere versiuni per limbă.

### Site monolingv

Fără plugin de traduceri, totul e simplu: un document per tip, în limba site-ului; tabelul de limbi nici nu apare.

---

## 10. Update-uri automate și rollback

### Update-uri

Pluginul folosește mecanismul nativ WordPress (`Update URI`) conectat la acest repository:

- când se publică o versiune nouă pe ramura `main`, pe pagina **Module** apare notificarea standard „Este disponibilă o versiune nouă… actualizează acum" — un click și gata;
- verificarea se face la câteva ore (cache 6h + ciclul WordPress); **Panou → Actualizări → „Verifică din nou"** forțează verificarea imediat;
- poți activa **„Actualizări automate"** per site, din pagina Module, pentru zero intervenție;
- **„Vezi detalii"** deschide fereastra completă cu Descriere / Instalare / FAQ / Capturi / **Changelog** — istoricul tuturor versiunilor, citit live din GitHub.

### Rollback — revenirea la orice versiune

**Banner Design → Plugin Updates from GitHub → Version switch / rollback:**

1. alege versiunea din listă (fiecare apare cu descrierea ei, ex. „v2.0.1 — Check again now bypasses the cache…");
2. **Install selected version** — se instalează singură, cu setările și statisticile păstrate;
3. update-urile se **opresc pe acea versiune** (pin), ca site-ul să nu-ți ofere înapoi versiunea de la care ai fugit;
4. când vrei din nou ultima versiune: **Resume updates (back to latest)**.

### Token GitHub (opțional)

Câmpul de token e necesar doar dacă repository-ul e privat. Pe repo public totul merge fără token; totuși, un token (creat în GitHub → Settings → Developer settings → Personal access tokens, read-only) ridică limita API de la 60 la 5.000 cereri/oră — util dacă vezi vreodată „GitHub not reachable" la verificări repetate.

---

## 11. Depanare

**Bannerul nu apare pe site**
Golește cache-ul paginilor (plugin de cache/CDN). Verifică apoi în incognito. Bannerul nu apare pentru cine a consimțit deja — folosește `?pcc_demo=1` pentru test.

**Bannerul nu apare în Brave / cu uBlock**
De la v1.7.0 pluginul este imun la filtrele standard. Dacă totuși dispare, vizitatorul are liste de filtre agresive personalizate; verifică cu shields dezactivat pentru confirmare și raportează.

**„GTM-XXXX is not enabled for debugging" în Tag Assistant**
Nu e o blocare — pornește sesiunea de debug din GTM (tagmanager.google.com → containerul tău → **Preview**), nu direct din tagassistant.google.com.

**Linkuri „duplicate" către paginile legale în footer**
Verifică sursa paginii (view-source): dacă linkurile există în HTML-ul brut, vin din temă/meniu, nu din plugin. Pluginul adaugă linkuri legale doar pentru paginile generate de el și doar cu bifa activă.

**Nu văd update-ul deși știu că există**
Panou → Actualizări → „Verifică din nou". Dacă tot nu apare, verifică în Banner Design → Plugin Updates statusul; „GitHub not reachable" înseamnă de regulă limita API — adaugă un token sau reîncearcă peste o oră.

**Versiuni mai vechi de 1.9.0**
Nu au mecanismul de auto-update — necesită un ultim upload manual de ZIP („Înlocuiește versiunea curentă cu cea încărcată"), după care intră în circuitul automat.

**Pagina Banner Design nu salvează o setare**
Reîncarcă cu Cmd/Ctrl+Shift+R (script de admin vechi în cache) și salvează din nou.

---

*Ghid pentru Converta Cookie Banner · [converta.ro](https://converta.ro) · actualizat odată cu pluginul, în acest repository.*
