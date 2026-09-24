# Converta Cookie Banner — Guide d'utilisation complet

🌍 **Langues :** [Română](GHID-UTILIZARE.md) · [English](USER-GUIDE.md) · **Français**

Ce guide couvre tout ce qu'il faut savoir pour installer, configurer et maintenir l'extension sur n'importe quel site WordPress : bannière de consentement RGPD avec Google Consent Mode v2, scanner de cookies, statistiques, pages légales (y compris multilingue avec Polylang) et mises à jour automatiques depuis GitHub.

---

## Sommaire

1. [Installation](#1-installation)
2. [Fonctionnement de la bannière](#2-fonctionnement-de-la-bannière)
3. [Intégration Google Tag Manager](#3-intégration-google-tag-manager)
4. [Banner Design — couleurs, tailles, réouverture du consentement](#4-banner-design)
5. [Traductions — les 6 langues de la bannière](#5-traductions)
6. [Cookie Scanner](#6-cookie-scanner)
7. [Statistiques de consentement](#7-statistiques-de-consentement)
8. [Legal Pages — Politique de Confidentialité et CGU](#8-legal-pages)
9. [Sites multilingues](#9-sites-multilingues)
10. [Mises à jour automatiques et rollback](#10-mises-à-jour-automatiques-et-rollback)
11. [Dépannage](#11-dépannage)

---

## 1. Installation

### Première installation sur un site

1. Téléchargez le ZIP de l'extension (depuis ce dépôt ou reçu directement).
2. Dans l'admin WordPress : **Extensions → Ajouter → Téléverser une extension** → choisissez le ZIP → **Installer** → **Activer**.
3. C'est tout — la bannière s'affiche immédiatement pour les visiteurs sans cookie de consentement. Toute la configuration se trouve dans le menu **Cookie Consent**.

> **Important :** après la première installation, plus jamais de ZIP manuel — l'extension se met à jour toute seule depuis GitHub (voir [section 10](#10-mises-à-jour-automatiques-et-rollback)).

### Si l'installation échoue avec « Impossible de créer le répertoire »

C'est un problème de permissions serveur (fréquent sur VPS/Docker), pas un bug de l'extension. L'administrateur du serveur doit exécuter :

```bash
sudo mkdir -p /chemin/du/site/wp-content/upgrade
sudo chown -R www-data:www-data /chemin/du/site/wp-content
```

### Ce qui est conservé, ce qui est perdu

| Action | Réglages & statistiques |
|---|---|
| Mise à jour (automatique ou « Remplacer l'actuelle par la version téléversée ») | ✅ conservés |
| Rollback vers une version antérieure | ✅ conservés |
| Désactivation temporaire | ✅ conservés |
| **Suppression (Delete)** | ❌ perdus définitivement (statistiques, réglages ; les pages légales générées restent) |

---

## 2. Fonctionnement de la bannière

### Le consentement avant Google Tag Manager — garanti

L'extension injecte le script de consentement comme **tout premier script du `<head>`**, via la mise en tampon de la page — quelle que soit la façon dont GTM est installé (codé en dur dans `header.php`, via une autre extension, via le thème). Ainsi :

- `gtag('consent', 'default', …)` — tout refusé (sauf `security_storage`) — s'exécute **avant** le conteneur GTM ;
- pour les visiteurs ayant déjà consenti, `gtag('consent', 'update', …)` s'exécute juste après, toujours avant GTM.

Vérifiez avec [Google Tag Assistant](https://tagassistant.google.com/) : l'événement **Consent Default** doit apparaître avant **Consent Initialization**. (Pour déboguer le conteneur GTM, lancez la session depuis GTM → bouton **Prévisualiser**, pas directement depuis Tag Assistant.)

### Résistant aux bloqueurs de publicité (Brave, uBlock Origin, AdGuard)

Les listes de filtres anti-bannières masquent habituellement les bannières de consentement. L'extension y survit légitimement : le CSS et le JS frontend sont **intégrés dans la page** (aucune URL à bloquer) et tous les id/classes portent des noms neutres, sans les mots « cookie/consent/banner ». Résultat : les visiteurs sous bloqueur peuvent quand même consentir.

### Propre pour le SEO

Les textes de la bannière **n'existent pas dans le HTML de la page** — ils sont chargés en AJAX côté navigateur, donc jamais en contenu dupliqué sur chaque URL. De plus, chaque conteneur porte `data-nosnippet` : Google n'utilisera jamais ces textes dans les extraits.

### Paramètres de démonstration

Utiles pour les démos client ou les captures d'écran, sur n'importe quelle page :

- `?pcc_demo=1` — force l'ouverture de la bannière (même après consentement)
- `?pcc_demo=prefs` — ouvre directement le panneau de préférences

---

## 3. Intégration Google Tag Manager

### Événements dataLayer pour les déclencheurs

| Événement | Déclenché quand |
|---|---|
| `cookie_necessary` | à chaque chargement de page |
| `cookie_analytics` | consentement Statistiques accordé |
| `cookie_marketing` | consentement Marketing accordé |
| `cookie_functional` | à chaque enregistrement du consentement (toujours accordé) |

Correspondance Consent Mode : **Marketing** → `ad_storage`, `ad_user_data`, `ad_personalization`, `personalization_storage` ; **Statistiques** → `analytics_storage` ; `security_storage` — toujours accordé.

### Advanced vs Basic (Banner Design → « Google Tag Manager — blocking mode »)

- **Advanced Consent Mode** *(par défaut, recommandé)* — GTM se charge immédiatement sur chaque page ; les signaux Consent Mode maintiennent les balises Google sans cookies jusqu'au consentement. Vous conservez la modélisation comportementale GA4. **L'extension ne bloque jamais le script GTM dans ce mode.**
- **Basic Consent Mode (blocage strict)** — optionnel : le script GTM/gtag **ne se charge pas du tout** tant que le visiteur n'accorde pas Statistiques ou Marketing. Coût : perte totale des données des visiteurs qui refusent ou ignorent la bannière.

---

## 4. Banner Design

**Cookie Consent → Banner Design** — aperçu en direct, application après **Save Design**.

### Couleurs et tailles

Chaque élément a son sélecteur de couleur (fond, boutons Accepter/Refuser/Personnaliser/Enregistrer/Annuler avec survol, cartes de catégories, interrupteurs, badges, overlay), plus le rayon des coins et les tailles de police.

### Reopen Consent — comment les visiteurs changent d'avis (exigence RGPD)

Trois options :

1. **Floating Icon** — bouton rond avec symbole de cookie, fixé en bas à gauche, visible après consentement.
2. **Footer Link** — lien texte traduit automatiquement (« Paramètres des cookies », « Cookie Settings »…), **inséré intelligemment dans le pied de page du thème** : d'abord le menu de pied de page, puis la ligne de copyright, puis — pour les pieds de page codés à la main — après le dernier lien du `<footer>`. À défaut, une barre discrète propre à l'extension. Texte modifiable sous **Translations**.
3. **Both** — icône et lien ensemble.

### Placement manuel du lien (optionnel, compatible avec toutes les options)

- Shortcode : `[pcc_consent_link]` ou `[pcc_consent_link text="Cookies"]` — dans toute page, widget ou pied de page.
- Classe CSS `pcc-plink` sur n'importe quel lien ou élément de menu (Apparence → Menus → activer « Classes CSS » dans Options de l'écran, puis ajouter la classe à un lien personnalisé d'URL `#`).
- Quand un lien manuel existe, le lien automatique **s'efface de lui-même** — aucun doublon.

---

## 5. Traductions

**Cookie Consent → Translations** — la bannière est livrée entièrement traduite en **roumain, anglais, allemand, français, italien, espagnol**.

### Détection de la langue

- **URL Subfolder** — d'après le préfixe d'URL : `/fr/`, `/en/`, `/de/`… (adapté à Polylang/WPML en sous-répertoires)
- **HTML lang** — d'après l'attribut `<html lang="…">` (compatible avec toute extension de traduction)
- **Langue par défaut** — repli quand rien n'est détecté

### Modification des textes

Chaque langue a son onglet ; toutes les chaînes sont modifiables : titre, corps, boutons, noms et descriptions des catégories, texte du lien de pied de page. **Reset to Defaults** restaure les traductions d'origine.

---

## 6. Cookie Scanner

**Cookie Consent → Cookie Scanner**

1. **Scan Website for Cookies** — parcourt jusqu'à 100 pages du site et détecte les cookies serveur et les scripts de suivi connus.
2. **Quick Add — Cookie Library** — ajoute en un clic les jeux standards des plateformes courantes (Google Analytics, Google Ads, Facebook, LinkedIn…).
3. **Liste modifiable** — nom, fournisseur, catégorie (necessary/statistics/marketing), durée, description ; ajout et suppression manuels.

La liste s'affiche publiquement dans le panneau de préférences de la bannière, par catégorie, avec le nombre de cookies.

---

## 7. Statistiques de consentement

**Cookie Consent → Statistics** — périodes de 7/30/90/365 jours :

- Interactions totales, visiteurs uniques, Tout accepter / Tout refuser / Personnalisé
- Graphiques : répartition des actions, Statistiques accordées, Marketing accordé, tendance quotidienne
- Taux de consentement (acceptation globale, statistiques, marketing)

**Confidentialité :** aucune adresse IP stockée ; les visiteurs sont comptés via un hachage SHA-256 salé. Les données vivent dans la table dédiée de l'extension et survivent aux mises à jour.

---

## 8. Legal Pages

**Cookie Consent → Legal Pages** — utilisez les pages légales existantes du site, ou générez-les à partir des données de l'entreprise. Règle d'or : **l'extension ne crée jamais de doublons.**

### Étape 1 — Données de l'entreprise

Renseignez : nom de marque/société, n° TVA, registre du commerce (optionnel), adresse, e-mail, téléphone (optionnel), URL du site. Utilisées uniquement à la génération. **Les champs vides sont simplement omis du texte** — aucun espace réservé « [TVA] » n'apparaît.

### Étape 2 — Détection automatique

Pour chaque document (Politique de Confidentialité, CGU), l'extension cherche des pages existantes :

1. le réglage natif WordPress de la page de confidentialité ;
2. les slugs usuels dans les 6 langues (`politique-de-confidentialite`, `privacy-policy`, `conditions-generales`, `terms-and-conditions`, `datenschutz`…) ;
3. des fragments de titre.

- **Page publiée trouvée** → sélectionnée automatiquement, marquée « votre page existante — l'extension n'y touchera pas », bouton Generate **désactivé** (« no duplicates »).
- **Brouillon trouvé** (ex. le brouillon « Politique de confidentialité » créé automatiquement par WordPress) → le bouton devient **« Complete & publish draft »** : il le remplit de contenu généré et le publie — sans doublon.
- **Rien trouvé** → **Generate page** crée la page.

### Contenu des textes générés

Modèles complets en **roumain et anglais** (autres langues : modèle EN avec titre localisé) :

- **Politique de Confidentialité :** identité du responsable, données collectées, finalités et bases légales (RGPD), section cookies avec lien vivant `[pcc_consent_link]` vers la bannière, destinataires et transferts, durées de conservation, droits des personnes, sécurité, mises à jour.
- **CGU :** identification, acceptation, propriété intellectuelle, usage, limitation de responsabilité, protection des données, droit applicable et litiges (plateforme européenne RLL/ODR incluse), modifications.

Chaque page se termine par un **avertissement** : modèle généré automatiquement, ne constituant pas un conseil juridique — relecture par un professionnel recommandée.

### Régénération sans doublons

Les pages générées portent un marqueur interne. **Regenerate content** met à jour la *même* page (ou la restaure depuis la corbeille) — jamais de seconde copie.

### Footer Links

La case « Add footer links » ajoute des liens de pied de page **uniquement pour les pages générées par l'extension**. Les pages préexistantes ne reçoivent jamais de liens supplémentaires (votre thème les référence déjà) — seul le lien Paramètres des cookies est ajouté. Aucun doublon possible.

### Synchronisation WordPress

À la génération de la Politique de Confidentialité, si le réglage natif WP de page de confidentialité est vide, l'extension le renseigne automatiquement.

---

## 9. Sites multilingues

### Polylang (prise en charge complète, intégrée)

Avec Polylang actif et plusieurs langues, chaque document reçoit un tableau **Languages** :

| Colonne | Signification |
|---|---|
| Language | chaque langue du site ; la langue par défaut est marquée « (default) » et gérée par le sélecteur principal |
| Page | la page trouvée pour cette langue (existante ou générée) avec liens voir/modifier, ou « missing » |
| Action | **Generate (FR/EN/…)** uniquement pour les langues manquantes ; **Regenerate** pour celles générées par l'extension |

Automatiquement, à la génération d'une traduction, l'extension :

- crée la page dans cette langue (titre localisé + modèle RO/EN) ;
- définit sa langue Polylang et **la lie comme traduction** de la page de langue par défaut — quel que soit l'ordre de génération ;
- fait pointer les liens de pied de page (si activés) vers **la version dans la langue du visiteur**.

La détection respecte aussi les traductions existantes : si la page FR existe déjà (créée par vous ou liée dans Polylang), elle est sélectionnée — aucune seconde page n'est générée.

> Astuce : si vous ne voyez pas les pages des autres langues dans l'admin, passez le filtre de langue Polylang (barre du haut) sur « Afficher toutes les langues ».

### WPML

L'extension affiche une notice dédiée : générez ici les pages de la langue par défaut, puis créez les traductions avec les outils propres de WPML. (La liaison automatique des traductions n'existe que pour Polylang.)

### TranslatePress / Weglot

Ces extensions traduisent la *même* page à la volée — **une seule page par document suffit** ; l'extension l'indique et ne demande pas de versions par langue.

### Site monolingue

Sans extension de traduction, tout est simple : un document par type, dans la langue du site ; le tableau des langues n'apparaît même pas.

---

## 10. Mises à jour automatiques et rollback

### Mises à jour

L'extension utilise le mécanisme natif WordPress (`Update URI`) relié à ce dépôt :

- à chaque nouvelle version publiée sur la branche `main`, la notification standard « Une nouvelle version est disponible… mettre à jour maintenant » apparaît sur la page **Extensions** — un clic suffit ;
- la vérification a lieu toutes les quelques heures (cache 6 h + cycle WordPress) ; **Tableau de bord → Mises à jour → « Vérifier à nouveau »** force une vérification immédiate ;
- vous pouvez activer les **« Mises à jour automatiques »** par site, pour une maintenance sans intervention ;
- **« Voir les détails »** ouvre la fenêtre complète Description / Installation / FAQ / Captures / **Journal des modifications** — l'historique de toutes les versions, lu en direct depuis GitHub.

### Rollback — revenir à n'importe quelle version

**Banner Design → Plugin Updates from GitHub → Version switch / rollback :**

1. choisissez la version dans la liste (chacune avec sa description) ;
2. **Install selected version** — installation automatique, réglages et statistiques conservés ;
3. les mises à jour sont **épinglées sur cette version** (pin), pour que le site ne repropose pas la version que vous venez de quitter ;
4. pour revenir à la dernière version : **Resume updates (back to latest)**.

### Jeton GitHub (optionnel)

Le champ jeton n'est requis que si le dépôt est privé. Sur un dépôt public tout fonctionne sans ; un jeton (GitHub → Settings → Developer settings → Personal access tokens, lecture seule) élève néanmoins la limite d'API de 60 à 5 000 requêtes/heure — utile si « GitHub not reachable » apparaît lors de vérifications répétées.

---

## 11. Dépannage

**La bannière n'apparaît pas**
Videz le cache de pages (extension de cache/CDN), puis testez en navigation privée. La bannière ne s'affiche pas pour qui a déjà consenti — utilisez `?pcc_demo=1` pour tester.

**La bannière n'apparaît pas sous Brave / uBlock**
Depuis la v1.7.0, l'extension est immunisée contre les listes de filtres standards. Si elle disparaît quand même, le visiteur utilise des listes personnalisées agressives ; confirmez boucliers désactivés et signalez-le.

**« GTM-XXXX is not enabled for debugging » dans Tag Assistant**
Ce n'est pas un blocage — lancez la session de débogage depuis GTM (tagmanager.google.com → votre conteneur → **Prévisualiser**), pas directement depuis tagassistant.google.com.

**Liens légaux « en double » dans le pied de page**
Vérifiez la source de la page (view-source) : si les liens existent dans le HTML brut, ils viennent du thème/menu, pas de l'extension. L'extension n'ajoute des liens légaux que pour les pages qu'elle a générées, et uniquement si la case est cochée.

**Une mise à jour existe mais n'apparaît pas**
Tableau de bord → Mises à jour → « Vérifier à nouveau ». Sinon, consultez le statut sous Banner Design → Plugin Updates ; « GitHub not reachable » signifie généralement la limite d'API — ajoutez un jeton ou réessayez dans l'heure.

**Versions antérieures à 1.9.0**
Elles précèdent l'auto-mise à jour — un dernier téléversement manuel de ZIP est nécessaire (« Remplacer l'actuelle par la version téléversée »), après quoi elles rejoignent le circuit automatique.

**Un réglage de Banner Design ne s'enregistre pas**
Rechargez avec Cmd/Ctrl+Shift+R (script d'admin en cache obsolète) puis réenregistrez.

---

*Guide d'utilisation de Converta Cookie Banner · [converta.ro](https://converta.ro) · mis à jour avec l'extension, dans ce dépôt.*
