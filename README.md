# ScrubDB

A WordPress database diagnostic and cleanup tool. Inspect what's bloating your database, find orphaned data, debug problematic options, and clean up when you're ready.

## What It Does

ScrubDB is primarily a **diagnostic tool** — it helps you understand what's happening inside your WordPress database. Why is it slow? What's eating space? Which plugin left orphaned data behind? Once you've identified the problem, you can optionally clean it up.

### Key Features

- **Database X-Ray** — Full breakdown of all tables, sizes, overhead, and engine types
- **Orphaned Data Detection** — Find metadata entries pointing to posts, comments, terms, or users that no longer exist
- **Options Table Audit** — See what's autoloaded on every page request, identify bloated options, toggle autoload, or delete individual entries
- **Content Analysis** — Spot revisions, auto-drafts, trashed items, spam, duplicate meta, and oEmbed cache
- **WooCommerce Diagnostics** — Expired sessions and WC-specific transients (only shows when WooCommerce is active)
- **Maintenance Tools** — Optimize/repair tables, clean orphaned cron jobs, view debug.log
- **Dry Run First** — Every destructive action has a scan/preview mode so you see exactly what will be affected before committing

### Design Philosophy

1. **Diagnose first, clean second** — Every section shows you what exists before offering to remove it
2. **Non-destructive by default** — All scan operations are read-only; cleanup requires explicit confirmation
3. **No external dependencies** — Pure PHP, vanilla JS, no build step required
4. **Minimal footprint** — Assets only load on the plugin's own page

## Installation

1. Download the latest release zip from [GitHub Releases](https://github.com/ajithrn/scrubdb/releases)
2. Upload via **Plugins → Add New → Upload Plugin** in WordPress admin
3. Activate the plugin
4. Find it under **Tools → ScrubDB**

Auto-updates are supported — WordPress will notify you when a new release is available on GitHub.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- `manage_options` capability (admin only)

## Usage

1. Navigate to **Tools → ScrubDB**
2. Use the tabs to explore different areas of your database
3. Click **Dry Run** or **Analyze** to scan without making changes
4. Review the results — items found, sizes, sample data
5. If cleanup is needed, click **Clean** (you'll get a confirmation prompt)

## Documentation

Detailed docs are in the [`docs/`](docs/) folder:

- [Architecture](docs/architecture.md) — How the plugin is structured, data flow, module system
- [Development](docs/development.md) — Local setup, coding standards, adding new tasks
- [Deployment](docs/deployment.md) — Release process, auto-update system, versioning

## License

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
