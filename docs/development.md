# Development Guide

## Local Setup

1. Clone the repo into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/ajithrn/scrubdb.git
   ```

2. Activate the plugin in WordPress admin

3. Navigate to **Tools → ScrubDB** — that's it, no build step needed

## Project Structure

```
scrubdb/
├── scrubdb.php              # Entry point — constants, requires, init
├── includes/
│   ├── class-scrubdb.php    # Core dispatcher
│   ├── class-github-updater.php
│   └── tasks/               # One file per task category
├── admin/
│   ├── admin-page.php       # PHP template
│   ├── css/admin.css        # Styles (plain CSS, no preprocessor)
│   └── js/admin.js          # Client logic (vanilla jQuery)
└── docs/
```

No webpack, no npm, no build tools. Edit and reload.

## Coding Standards

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Tabs for indentation in PHP, spaces in CSS/JS
- Class names: `ScrubDB_Task_*` for task modules
- Method names: `task_{task_name}` matching the registered task string
- All SQL must use `$wpdb->prepare()` for dynamic values
- Sanitize all `$_POST` input with `sanitize_text_field()` or `absint()`

## Adding a New Task

### 1. Choose or create a task module

If your task fits an existing category (orphaned data, content, options, etc.), add it to that file. Otherwise create a new file in `includes/tasks/`.

### 2. Register the task name

Add it to the `get_tasks()` return array:

```php
public function get_tasks() {
    return [
        'existing_task',
        'your_new_task',  // Add here
    ];
}
```

### 3. Implement the task method

```php
public function task_your_new_task( $mode ) {
    global $wpdb;

    // Count items.
    $count = (int) $wpdb->get_var( "SELECT COUNT(*) ..." );

    // Get sample items for preview (limit 20).
    $items = $wpdb->get_results( "SELECT ... LIMIT 20" );

    // Define table columns for the frontend renderer.
    $items_columns = [
        [ 'label' => 'ID',   'key' => 'id',   'mono' => true ],
        [ 'label' => 'Name', 'key' => 'name' ],
        [ 'label' => 'Size', 'key' => 'size', 'suffix' => 'KB' ],
    ];

    // Perform cleanup if mode is 'clean'.
    $deleted = 0;
    if ( 'clean' === $mode && $count > 0 ) {
        $deleted = (int) $wpdb->query( "DELETE ..." );
        // Or use batch delete for large datasets:
        // $deleted = ScrubDB::batch_delete( "DELETE FROM ... LIMIT %d" );
    }

    return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
}
```

### 4. Add the UI card

In `admin/admin-page.php`, add a card in the appropriate tab section:

```php
<div class="scrubdb-card" id="card-your_new_task" data-group="content">
    <div class="scrubdb-card-header">
        <h3>Your New Task</h3>
    </div>
    <p>Description of what this finds and why it matters.</p>
    <div class="scrubdb-actions">
        <button type="button" class="button scrubdb-btn scrubdb-btn-scan"
            onclick="scrubdbRun('your_new_task', 'scan')">
            <span class="dashicons dashicons-search"></span> Dry Run
        </button>
        <button type="button" class="button scrubdb-btn scrubdb-btn-clean"
            onclick="scrubdbRun('your_new_task', 'clean')">
            <span class="dashicons dashicons-trash"></span> Clean
        </button>
    </div>
    <div class="scrubdb-result" id="result-your_new_task"></div>
</div>
```

### 5. If creating a new module file

Add the require in `scrubdb.php`:

```php
require_once SCRUBDB_PATH . 'includes/tasks/class-your-module.php';
```

And register it in `class-scrubdb.php` → `register_tasks()`:

```php
$modules = [
    // ...existing modules...
    'ScrubDB_Task_Your_Module',
];
```

## Custom Renderers

If your task returns non-standard data (like `database_info` or `options_debug`), you'll need a custom renderer in `admin/js/admin.js`:

1. Add a case in the `render()` router function
2. Write a `renderYourTask(d)` function that returns an HTML string
3. Use the helper functions: `badge()`, `stat()`, `fmtNum()`, `esc()`

## Return Value Reference

Standard keys the JS renderer understands:

| Key | Type | Description |
|-----|------|-------------|
| `count` | int | Total items found |
| `items` | array | Sample rows for table display |
| `items_columns` | array | Column definitions: `{label, key, mono?, suffix?}` |
| `deleted` | int | Items removed (clean mode) |
| `mode` | string | `'scan'` or `'clean'` |
| `size` | string | Optional size in MB |
| `note` | string | Optional footnote text |
| `details` | array | Legacy grouped summary (used by some tasks alongside items) |

## Testing

No automated test suite currently. Manual testing workflow:

1. Activate plugin on a dev site with sample data
2. Run each task in scan mode — verify counts match reality
3. Run clean mode on a task — verify items are actually removed
4. Check the count drops to 0 on re-scan
5. Test with WooCommerce active and inactive (tab visibility)
6. Test responsive layout at various breakpoints
