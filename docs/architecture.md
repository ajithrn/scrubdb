# Architecture

## Overview

ScrubDB follows a dispatcher + task module pattern. The core class handles admin registration, AJAX routing, and asset loading. Individual task modules handle the actual database queries and diagnostic logic.

```
scrubdb/
├── scrubdb.php                      # Bootstrap: constants, requires, init
├── includes/
│   ├── class-scrubdb.php            # Core: menu, AJAX dispatcher, assets
│   ├── class-github-updater.php     # Auto-update from GitHub releases
│   └── tasks/
│       ├── class-orphaned-data.php  # Orphaned metadata detection/cleanup
│       ├── class-content-cleanup.php # Revisions, drafts, spam, duplicates
│       ├── class-options-cleanup.php # Transients, options X-Ray, option mgmt
│       ├── class-database-ops.php   # Optimize, repair, DB info, orphaned tables
│       ├── class-woocommerce.php    # WC sessions and transients
│       └── class-debug.php          # Cron cleanup, debug.log
├── admin/
│   ├── admin-page.php               # HTML template (tab-based UI)
│   ├── css/admin.css                # All styles
│   └── js/admin.js                  # AJAX, rendering, sort, pagination
├── .github/
│   └── workflows/release.yml        # Auto-release on version bump
└── docs/
```

## Request Lifecycle

```mermaid
sequenceDiagram
    participant Browser
    participant WordPress
    participant ScrubDB Core
    participant Task Module
    participant Database

    Browser->>WordPress: AJAX POST {task, mode, nonce}
    WordPress->>ScrubDB Core: wp_ajax_scrubdb hook
    ScrubDB Core->>ScrubDB Core: verify nonce + capability
    ScrubDB Core->>ScrubDB Core: lookup task in registry
    ScrubDB Core->>Task Module: call task_method($mode)
    Task Module->>Database: SQL queries (COUNT, SELECT, DELETE)
    Database-->>Task Module: result rows
    Task Module-->>ScrubDB Core: associative array
    ScrubDB Core-->>WordPress: wp_send_json_success()
    WordPress-->>Browser: JSON response
    Browser->>Browser: JS renders (badges, tables, pagination)
```

## Plugin Initialization

```mermaid
flowchart TD
    A[scrubdb.php loaded] --> B[Define constants]
    B --> C[Require class-scrubdb.php]
    C --> D[Require class-github-updater.php]
    D --> E[Require all task modules]
    E --> F[ScrubDB::init - singleton]
    F --> G[Register admin_menu hook]
    F --> H[Register admin_enqueue_scripts hook]
    F --> I[Register wp_ajax_scrubdb hook]
    F --> I2[Register wp_ajax_scrubdb_check_update hook]
    F --> J[register_tasks - instantiate modules]
    J --> K[Map task names → class instances]
    E --> L[new ScrubDB_GitHub_Updater]
    L --> M[Hook into update_plugins transient]
    L --> N[Hook into plugins_api filter]
```

## Task Registry & Dispatch

```mermaid
flowchart LR
    subgraph Registry
        A[ScrubDB_Task_Orphaned_Data] --> |"orphaned_postmeta<br>orphaned_commentmeta<br>orphaned_termmeta<br>orphaned_usermeta<br>orphaned_relationships"| R[tasks map]
        B[ScrubDB_Task_Content_Cleanup] --> |"post_revisions<br>auto_drafts<br>trashed_posts<br>spam_comments<br>trashed_comments<br>oembed_cache<br>pingbacks<br>duplicate_postmeta"| R
        C[ScrubDB_Task_Options_Cleanup] --> |"expired_transients<br>all_transients<br>options_debug<br>toggle_autoload<br>delete_option"| R
        D[ScrubDB_Task_Database_Ops] --> |"optimize_tables<br>repair_tables<br>database_info<br>orphaned_tables<br>drop_table"| R
        E[ScrubDB_Task_WooCommerce] --> |"woo_sessions<br>woo_transients"| R
        F[ScrubDB_Task_Debug] --> |"cron_cleanup<br>debug_log"| R
    end

    R --> |"ajax_handler looks up task"| G[Call task_method]
```

**Total: 26 registered tasks across 6 modules.**

## How Each Task Category Identifies Problems

### Orphaned Metadata Detection

Each orphaned data task uses a `LEFT JOIN` pattern to find metadata rows whose parent entity no longer exists:

```sql
SELECT COUNT(*)
FROM {meta_table} m
LEFT JOIN {parent_table} p ON m.{fk_column} = p.ID
WHERE p.ID IS NULL
```

| Task | Meta Table | Parent Table | FK Column | What It Means |
|------|-----------|--------------|-----------|---------------|
| `orphaned_postmeta` | `wp_postmeta` | `wp_posts` | `post_id` | Post was deleted but meta rows remain |
| `orphaned_commentmeta` | `wp_commentmeta` | `wp_comments` | `comment_id` | Comment deleted, meta left behind |
| `orphaned_termmeta` | `wp_termmeta` | `wp_terms` | `term_id` | Taxonomy term removed, meta orphaned |
| `orphaned_usermeta` | `wp_usermeta` | `wp_users` | `user_id` | User deleted, meta persists |
| `orphaned_relationships` | `wp_term_relationships` | `wp_posts` | `object_id` | Post gone, taxonomy links remain |

Cleanup uses `ScrubDB::batch_delete()` which runs `DELETE ... LIMIT 1000` in a loop to avoid timeouts on large datasets.

### Orphaned Tables Detection

Identifies database tables that don't belong to WordPress core or any currently active/installed plugin.

```mermaid
flowchart TD
    A[Get all tables with WP prefix] --> B{Is it a WP core table?}
    B -->|Yes| SKIP[Skip - it's core]
    B -->|No| C{Check known plugin map}
    C -->|Match found| D{Is that plugin active?}
    D -->|Yes| SKIP2[Skip - active plugin]
    D -->|No| E[Show as Inactive]
    C -->|No match| F{Check active plugin slug patterns}
    F -->|Match| SKIP2
    F -->|No match| G{Check ALL installed plugins}
    G -->|Match| H{Active or inactive?}
    H -->|Active| SKIP2
    H -->|Inactive| E
    G -->|No match| I[Show as Unknown]
```

**3-layer detection system:**

1. **Known plugin map** (checked first, most reliable) — Hardcoded mapping of 50+ popular plugins that use non-obvious table prefixes:
   - `wc_*` / `woocommerce_*` → WooCommerce
   - `actionscheduler_*` → Action Scheduler (shared library)
   - `yoast_*` → Yoast SEO
   - `e_*` → Elementor
   - `pmxe_*` / `pmxi_*` → WP All Export / Import
   - `gf_*` / `rg_*` → Gravity Forms
   - `wf*` → Wordfence
   - `icl_*` → WPML
   - `nf3_*` → Ninja Forms
   - `frm_*` → Formidable Forms
   - And 40+ more...

2. **Active plugin slug patterns** — For each active plugin, generates matching patterns:
   - Full slug normalized (`woocommerce`, `easy_digital_downloads`)
   - Abbreviation from first letters (`edd`, `wfr`)
   - First word if ≥4 chars (`gravity`, `easy`)

3. **All installed plugins** (active + inactive) with flexible matching:
   - Exact start match (`facetwp_index` starts with `facetwp`)
   - Strip `wp_`/`wordpress_` prefix and retry
   - Compact slug (remove underscores) for concatenated names (`wpzerospam`)
   - Slug as a segment anywhere in the table name

### Content Bloat Detection

| Task | Detection Query | Why It Accumulates |
|------|----------------|-------------------|
| `post_revisions` | `post_type = 'revision'` | WP saves a revision on every post update |
| `auto_drafts` | `post_status = 'auto-draft'` | Created every time you open "Add New Post" |
| `trashed_posts` | `post_status = 'trash'` | Trash isn't auto-emptied by default |
| `spam_comments` | `comment_approved = 'spam'` | Akismet flags but doesn't delete |
| `trashed_comments` | `comment_approved = 'trash'` | Same as trashed posts |
| `oembed_cache` | `meta_key LIKE '_oembed_%'` | Cached embed HTML in postmeta |
| `pingbacks` | `comment_type IN ('pingback','trackback')` | Automated cross-site notifications |
| `duplicate_postmeta` | `GROUP BY post_id, meta_key, meta_value` then find extras | Plugins sometimes double-write meta |

### Options Table Diagnostics

The options table (`wp_options`) is critical because **autoloaded options load on every single page request**.

**WP 6.6+ compatibility:** The autoload column changed from `'yes'`/`'no'` to `'on'`/`'off'`/`'auto-on'`/`'auto-off'`. ScrubDB uses `IN ('yes','on','auto-on','auto')` to handle both formats via the `autoload_values()` helper.

**Options X-Ray** provides full table analysis with type classification:

```mermaid
flowchart TD
    A[Read option_name] --> B{Starts with _transient_?}
    B -->|Yes| C[Type: Transient]
    B -->|No| D{In protected list OR starts with theme_mods_?}
    D -->|Yes| E[Type: Core]
    D -->|No| F{Contains 'woocommerce' or '_wc_'?}
    F -->|Yes| G[Type: WooCommerce]
    F -->|No| H[Type: Plugin/Theme]
```

**Inline actions:** Toggle autoload on/off, delete individual options (with protected core options list preventing dangerous modifications).

### Database Operations

- **Optimize** — Queries `information_schema.TABLES WHERE DATA_FREE > 0` to find tables with reclaimable overhead, then runs `OPTIMIZE TABLE`
- **Repair** — Runs `REPAIR TABLE` on all `wp_*` tables (useful after crashes or corruption)
- **Database info** — Reads `information_schema.TABLES` for engine, row count, data+index size, overhead, plus owner identification per table
- **Orphaned tables** — Detects and allows dropping tables from removed plugins (type-to-confirm safety)
- **Drop table** — Requires exact table name confirmation, blocks core tables

### WooCommerce Detection

- **Expired sessions** — `wp_woocommerce_sessions WHERE session_expiry < UNIX_TIMESTAMP()`
- **WC transients** — `option_name LIKE '_transient_wc_%' OR '_transient_timeout_wc_%'`

### Debug & Cron

- **Orphaned cron** — Iterates `_get_cron_array()`, checks each hook with `has_action($hook)`. If no callback is registered (plugin deactivated), it's orphaned
- **Debug log** — Reads `WP_CONTENT_DIR . '/debug.log'`, shows last 50 lines, reports file size

## Frontend Architecture

```mermaid
flowchart TD
    A[User clicks button] --> B[scrubdbRun task, mode]
    B --> C[AJAX POST to admin-ajax.php]
    C --> D{Success?}
    D -->|Yes| E[Store in resultData]
    E --> F[render task, data]
    F --> G{Custom renderer?}
    G -->|database_info| H[renderDbInfo → sortableTable]
    G -->|options_debug| I[renderOptionsDebug → renderXrayTable]
    G -->|orphaned_tables| I2[renderOrphanedTables]
    G -->|debug_log| J[renderDebugLog]
    G -->|Other| K[renderStandard → sortableTable]
    D -->|No| N[Show error badge]
```

### Sortable + Paginated Table Engine

All data tables use a shared engine (`sortableTable()` → `renderTableHTML()`):

```mermaid
flowchart LR
    A[sortableTable id, items, columns] --> B[Create tableState entry]
    B --> C[renderTableHTML stateId]
    C --> D[Slice items for current page]
    D --> E[Render header with sort indicators]
    E --> F[Render rows with optional format functions]
    F --> G[Render pagination controls]
    G --> H[Return HTML string]
```

**Features:**
- **State per table** — `tableState[stateId]` holds items, columns, page, sortKey, sortDir
- **Sort** — Click any column header. Numeric values sort numerically, strings alphabetically. Same column toggles asc/desc
- **Pagination** — 20 items per page, client-side. Prev/Next + page number buttons
- **Custom formatters** — Columns can define a `format(value, row)` function for custom rendering (e.g., status badges)
- **Custom renderer** — X-Ray table uses `renderXrayTable()` (flagged via `s.custom = true`) for inline action buttons
- **Re-render** — Sort or page change re-renders only that table's container

### Tab Navigation

- Tabs persist via URL hash (`#overview`, `#options`, etc.)
- WooCommerce tab conditionally rendered (PHP-side check for active WC)
- About tab includes manual "Check for Updates" button (AJAX to GitHub API)

## Auto-Update System

```mermaid
sequenceDiagram
    participant WP as WordPress Cron
    participant Updater as ScrubDB_GitHub_Updater
    participant Cache as Site Transient
    participant GH as GitHub API

    WP->>Updater: pre_set_site_transient_update_plugins
    Updater->>Cache: get_site_transient()
    alt Cache hit (< 12 hours old)
        Cache-->>Updater: cached release data
    else Cache miss
        Updater->>GH: GET /repos/ajithrn/scrubdb/releases/latest
        GH-->>Updater: {tag_name, assets, body}
        Updater->>Updater: Parse version, find scrubdb.zip asset
        Updater->>Cache: set_site_transient (12 hour TTL)
    end
    Updater->>Updater: Compare SCRUBDB_VERSION vs remote
    alt Remote is newer
        Updater-->>WP: Inject into $transient->response
    end
```

**Manual check:** The About tab has a "Check for Updates" button that clears the transient cache and fetches fresh data from GitHub via a separate AJAX endpoint (`wp_ajax_scrubdb_check_update`).

## Security Model

- All AJAX requests require `scrubdb_nonce` verification via `check_ajax_referer()`
- All operations require `manage_options` capability (administrator only)
- Protected options list prevents modification of critical WP core options (`siteurl`, `active_plugins`, `db_version`, etc.)
- Core tables list prevents dropping essential WordPress tables
- Drop table requires typing the exact table name to confirm
- `$_POST` input sanitized with `sanitize_text_field()` and `absint()`
- SQL queries use `$wpdb->prepare()` for all dynamic values
- Table names escaped with `esc_sql()` before use in queries
- Batch deletes use `LIMIT` to prevent long-running queries from timing out

## Asset Loading & Cache Busting

- CSS and JS are enqueued only on the `tools_page_scrubdb` hook (plugin's own page)
- Both use `SCRUBDB_VERSION` as the version parameter — every release busts browser caches
- No build step required — plain CSS and vanilla jQuery JS
- File sizes: ~700 lines CSS, ~650 lines JS — single file each (no splitting needed)
