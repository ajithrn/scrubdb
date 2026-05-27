# Deployment & Releases

## How Releases Work

ScrubDB uses GitHub Actions for automated releases and a built-in updater class so WordPress sites receive updates automatically.

### Release Flow

```
Developer                    GitHub Actions                WordPress Sites
    │                            │                              │
    │  bump version in           │                              │
    │  scrubdb.php               │                              │
    │  commit + push to main     │                              │
    ├───────────────────────────►│                              │
    │                            │  release.yml triggers        │
    │                            │  ├─ extract version          │
    │                            │  ├─ check if tag exists      │
    │                            │  ├─ create zip               │
    │                            │  └─ publish GitHub Release   │
    │                            │      with scrubdb.zip        │
    │                            │                              │
    │                            │         (within 12 hours)    │
    │                            │                              │
    │                            │◄─────────────────────────────┤
    │                            │  GitHub API: latest release? │
    │                            ├─────────────────────────────►│
    │                            │  yes, v1.5.0 available       │
    │                            │                              │
    │                            │              WP admin shows   │
    │                            │              "Update Available"│
```

## Creating a Release

### Option 1: Manual Version Bump (Recommended)

1. Edit `scrubdb.php` — update both the header and the constant:
   ```php
   * Version: 1.5.0
   ```
   ```php
   define( 'SCRUBDB_VERSION', '1.5.0' );
   ```

2. Commit and push to `main`:
   ```bash
   git add scrubdb.php
   git commit -m "release: v1.5.0"
   git push origin main
   ```

3. GitHub Actions will automatically:
   - Detect the version from the PHP header
   - Create a `scrubdb.zip` (excluding `.git`, `.github`, `.gitignore`)
   - Publish a GitHub Release tagged `v1.5.0` with the zip attached
   - Generate release notes from commit messages

### Option 2: Auto-Bump (Tag Collision)

If you push a version that already has a tag (e.g., you forgot to bump), the workflow will:

1. Auto-increment the patch version (1.5.0 → 1.5.1)
2. Update `scrubdb.php` with the new version
3. Commit and push — which re-triggers the workflow to create the actual release

This prevents failed releases from stalling the pipeline.

## Versioning

Follow [Semantic Versioning](https://semver.org/):

- **Patch** (1.4.x) — Bug fixes, minor UI tweaks, documentation
- **Minor** (1.x.0) — New tasks, new features, UI improvements
- **Major** (x.0.0) — Breaking changes, major rewrites

The version must be consistent in two places:
- Plugin header: `* Version: X.Y.Z`
- PHP constant: `define( 'SCRUBDB_VERSION', 'X.Y.Z' );`

## Auto-Update System

### How It Works

`ScrubDB_GitHub_Updater` (in `includes/class-github-updater.php`) integrates with WordPress's native update system:

1. **Check hook** — Filters `pre_set_site_transient_update_plugins` to inject update data when a newer GitHub release exists
2. **Info hook** — Filters `plugins_api` to provide the "View Details" modal content (changelog, requirements, etc.)
3. **Caching** — Stores the GitHub API response as a site transient for 12 hours to avoid rate limiting
4. **Package resolution** — Looks for a `scrubdb.zip` asset on the release; falls back to GitHub's auto-generated zipball

### What Users See

- Standard "Update Available" notice in Plugins list
- "View Details" link shows version, changelog (from release notes), and requirements
- One-click update via the standard WordPress updater

### GitHub API Rate Limits

- Unauthenticated requests: 60/hour per IP
- With 12-hour caching, each site makes ~2 requests/day
- If the API is unreachable, the updater silently returns `false` (no error shown to users)

## Release Checklist

- [ ] Version bumped in `scrubdb.php` (header + constant)
- [ ] Changes tested locally (scan + clean on affected tasks)
- [ ] Committed with a descriptive message
- [ ] Pushed to `main`
- [ ] Verify the GitHub Release was created (check Actions tab)
- [ ] Verify the zip downloads and installs correctly

## Zip Contents

The release zip includes:

```
scrubdb/
├── scrubdb.php
├── README.md
├── includes/
│   ├── class-scrubdb.php
│   ├── class-github-updater.php
│   └── tasks/ (all task files)
├── admin/
│   ├── admin-page.php
│   ├── css/admin.css
│   └── js/admin.js
└── docs/ (architecture, development, deployment)
```

Excluded from zip: `.git/`, `.github/`, `.gitignore`, `node_modules/`

## Rollback

If a release has issues:

1. Delete the GitHub Release and tag
2. Fix the issue
3. Push a new version (patch bump)

WordPress sites that already updated will need to manually reinstall the previous version or wait for the fix release.
