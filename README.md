# ScrubDB

A WordPress database diagnostic and cleanup tool. Inspect what's bloating your database, find orphaned data and tables, debug problematic options, identify which plugin owns what, and clean up when you're ready.

## What It Does

ScrubDB is primarily a **diagnostic tool** — it helps you understand what's happening inside your WordPress database. Why is it slow? What's eating space? Which plugin left orphaned data or tables behind? Once you've identified the problem, you can optionally clean it up.

### Key Features

- **Database Overview** — Full breakdown of all tables with size, overhead, owner identification, and active/inactive status
- **Table Owner Detection** — Identifies which plugin created each table using slug matching, a known plugin map (50+ plugins), and installed plugin scanning
- **Orphaned Tables** — Detect tables left behind by uninstalled plugins, with safe drop functionality (type-to-confirm)
- **Orphaned Data Detection** — Find metadata entries pointing to posts, comments, terms, or users that no longer exist
- **Options Table X-Ray** — Deep analysis of `wp_options` — autoload health, biggest consumers, plugin-by-plugin breakdown, toggle autoload or delete individual options
- **Content Bloat Analysis** — Spot revisions, auto-drafts, trashed items, spam, duplicate meta, and oEmbed cache
- **WooCommerce Diagnostics** — Expired sessions and WC-specific transients (tab only shows when WooCommerce is active)
- **Maintenance Tools** — Optimize/repair tables, clean orphaned cron jobs, view debug.log
- **Sortable & Paginated Tables** — All result tables support column sorting and client-side pagination (20 items/page)
- **Dry Run First** — Every destructive action has a scan/preview mode so you see exactly what will be affected

### Design Philosophy

1. **Diagnose first, clean second** — Every section shows you what exists before offering to remove it
2. **Non-destructive by default** — All scan operations are read-only; cleanup requires explicit confirmation
3. **No external dependencies** — Pure PHP, vanilla JS, no build step required
4. **Minimal footprint** — Assets only load on the plugin's own page
5. **WP 6.6+ compatible** — Handles both old (`yes`/`no`) and new (`on`/`off`) autoload column formats

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
2. Use the tabs to explore different areas:
   - **Overview** — Database size, table list with owner identification
   - **Orphaned Data** — Metadata pointing to deleted entities
   - **Content Bloat** — Revisions, drafts, trash, spam
   - **Options Table** — Transients, autoload analysis, X-Ray
   - **WooCommerce** — WC-specific sessions and transients
   - **Maintenance** — Optimize, repair, orphaned tables, cron, debug log
   - **About** — Plugin info, environment, update checker, help
3. Click **Dry Run** or **Analyze** to scan without making changes
4. Review the results — sort by any column, paginate through items
5. If cleanup is needed, click **Clean** (you'll get a confirmation prompt)

## Documentation

Detailed docs are in the [`docs/`](docs/) folder:

- [Architecture](docs/architecture.md) — Plugin structure, data flow, detection algorithms, table engine
- [Development](docs/development.md) — Local setup, coding standards, adding new tasks
- [Deployment](docs/deployment.md) — Release process, auto-update system, versioning

## License

GPL v2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
