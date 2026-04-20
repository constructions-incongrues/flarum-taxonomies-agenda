# Release Guide

Guide complet pour publier une nouvelle version de l'extension sur GitHub et Packagist.

## Processus de Release

### 1. Préparer la Release

Avant de tagger une nouvelle version :

```bash
# Vérifier que tout est à jour
git status
git pull origin main

# Vérifier que les tests passent localement
composer test
cd js && npm test && npm run build && cd ..

# Vérifier la structure des fichiers
composer validate
```

### 2. Mettre à jour le CHANGELOG

Éditer `CHANGELOG.md` avec vos changements au format suivant :

```markdown
## [0.2.0] - 2026-04-20

### Added
- New feature description
- Another feature

### Fixed
- Bug fix description

### Changed
- Breaking change description

### Deprecated
- Deprecated feature

## [0.1.0] - 2026-04-19
...
```

**Formats de date supportés:** `YYYY-MM-DD` (ISO 8601)

### 3. Créer un Tag de Release

```bash
# Créer un tag sémantique (major.minor.patch)
git tag -a v0.2.0 -m "Release version 0.2.0"

# Pousser le tag vers GitHub
git push origin v0.2.0
```

**Convention de versioning:**
- `v0.1.0` - Version initiale (majeure=0)
- `v0.2.0` - Nouvelles fonctionnalités sans breaking change
- `v0.2.1` - Bug fixes et patchs
- `v1.0.0` - Release stable/production-ready

### 4. CI/CD Automatique

Dès que vous poussez un tag `v*.*.*`, le workflow `Release` s'exécute :

#### Phase 1️⃣ : Validation
- ✅ Valide `composer.json`
- ✅ Vérifie l'entrée CHANGELOG pour cette version
- ✅ Extrait le numéro de version

#### Phase 2️⃣ : Build des Artifacts
- 📦 Construit les bundles JavaScript (forum + admin)
- 💾 Commit les artifacts si changement détecté
- 🏷️ Met à jour le tag si les bundles ont changé

#### Phase 3️⃣ : Création de la Release GitHub
- 📝 Extrait les notes de release du CHANGELOG
- 🎯 Crée une release GitHub avec notes
- 🔗 Génère un lien de téléchargement

#### Phase 4️⃣ : Publication sur Packagist
- 🚀 Valide les métadonnées du package
- 📢 Notifie Packagist via webhook
- 🔔 Déclenche la découverte automatique

## Configuration Packagist

### Enregistrer le package (une seule fois)

1. Aller sur https://packagist.org
2. Cliquer "Submit Package"
3. Entrer l'URL du repo GitHub: `https://github.com/constructions-incongrues/musiques-incongrues.net`
4. Cliquer "Check"

### Activer la découverte automatique

**Option A: GitHub Webhook (recommandé)**

Packagist peut surveiller automatiquement les nouveaux tags GitHub :

1. Aller à https://packagist.org/packages/constructions-incongrues/taxonomies-agenda
2. Cliquer sur "Settings" (⚙️)
3. Sous "GitHub Service Hook", les paramètres devraient être pré-remplis
4. Vérifier que le webhook est activé dans les GitHub repository settings

**Option B: Webhook Manuel**

Si vous avez un secret webhook Packagist :

1. Aller à https://packagist.org/packages/constructions-incongrues/taxonomies-agenda
2. Copier l'URL du webhook
3. Dans les repository settings GitHub → Webhooks, ajouter :
   - Payload URL: `https://packagist.org/api/update-package?username=...&apiToken=...`
   - Content type: `application/x-www-form-urlencoded`
   - Events: "Push events"

### Ou utiliser les variables GitHub Actions

Pour automatiser la notification webhook (optionnel) :

1. Dans les repository settings GitHub → Secrets and variables → Variables
2. Ajouter `PACKAGIST_WEBHOOK_URL` avec votre URL webhook Packagist
3. Le workflow enverra automatiquement une notification

## Vérifier la Publication

### Après la Release

```bash
# 1. Vérifier sur GitHub
# https://github.com/constructions-incongrues/musiques-incongrues.net/releases

# 2. Vérifier sur Packagist (~5 min après)
# https://packagist.org/packages/constructions-incongrues/taxonomies-agenda

# 3. Installer depuis Packagist
composer require constructions-incongrues/taxonomies-agenda:^0.2.0
```

### Troubleshooting Packagist

| Problème | Solution |
|----------|----------|
| Package n'apparaît pas sur Packagist | Attendre 5-10 min pour auto-discovery, puis rafraîchir |
| Webhook ne déclenche pas | Vérifier les logs GitHub Actions et les webhook Packagist |
| Mauvaise version | Vérifier que le tag suit `v*.*.*` et que CHANGELOG a une entrée correspondante |
| Mauvais nom de package | Vérifier que `composer.json` a `"name": "constructions-incongrues/taxonomies-agenda"` |

## Files de Release

### CI Workflow (`.github/workflows/ci.yml`)

Exécuté sur chaque push et PR :

- ✅ Valide `composer.json`
- ✅ PHPUnit tests (PHP 8.1, 8.2, 8.3)
- ✅ Build JavaScript
- ✅ Upload coverage (codecov)
- 🔒 Concurrence: Annule les anciennes runs

**Badge CI:**
```markdown
![CI](https://github.com/constructions-incongrues/musiques-incongrues.net/actions/workflows/ci.yml/badge.svg)
```

### Release Workflow (`.github/workflows/release.yml`)

Exécuté sur tag `v*.*.*` :

- ✅ Valide release metadata
- 📦 Build artifacts
- 📝 Crée GitHub Release
- 🚀 Publie sur Packagist

**Fichiers importants pour le workflow:**
- `composer.json` - Package metadata
- `CHANGELOG.md` - Release notes
- `js/package.json` - JS build config
- `phpunit.xml` - Test configuration

## Exemples

### Release d'une nouvelle fonctionnalité

```bash
# 1. Ajouter la fonctionnalité dans une branche
git checkout -b feature/my-feature
# ... faire les changements ...
git commit -m "feat: add my feature"

# 2. Merger dans main
git checkout main
git pull origin main
git merge feature/my-feature
git push origin main

# 3. Mettre à jour CHANGELOG.md
# (ajouter "### Added" sous "## [0.2.0]")
git add CHANGELOG.md
git commit -m "docs: update changelog for 0.2.0"
git push origin main

# 4. Créer le tag
git tag -a v0.2.0 -m "Release version 0.2.0: add my feature"
git push origin v0.2.0

# Attendre que GitHub Actions complète...
# ✅ Tests passent
# ✅ GitHub Release créée
# ✅ Packagist mis à jour
```

### Release d'un bugfix

```bash
# 1. Bugfix et tests
git checkout -b fix/my-bug
# ... corriger le bug et ajouter un test ...
git push origin fix/my-bug

# 2. Merger et tagger la patch version
git checkout main
git pull
git merge fix/my-bug
git tag -a v0.1.1 -m "Release version 0.1.1: fix my bug"
git push origin main v0.1.1
```

### Release manuelle (sans tag Git)

Si vous préférez déclencher manuellement :

```bash
# Utiliser le workflow_dispatch
gh workflow run release.yml -f tag=v0.2.0
```

Ou via l'UI GitHub :
1. Aller à Actions → Release
2. Cliquer "Run workflow"
3. Entrer le tag (e.g., `v0.2.0`)
4. Cliquer "Run workflow"

## Notes

- Tous les numéros de version doivent être en **semantic versioning** (`major.minor.patch`)
- Les tags **doivent** commencer par `v` (e.g., `v0.2.0`, pas `0.2.0`)
- Le workflow **échoue gracieusement** si CHANGELOG n'a pas d'entrée (mais l'affiche en warning)
- Les artifacts JavaScript sont **inclus dans le commit** pour la distribution
- Packagist découvre automatiquement via GitHub tags (webhook par défaut)

## Support

Pour plus d'infos :
- Packagist Docs: https://packagist.org/about
- Semantic Versioning: https://semver.org
- GitHub Actions: https://docs.github.com/actions
