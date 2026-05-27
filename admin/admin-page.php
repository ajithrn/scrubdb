<?php
/**
 * Admin page template for ScrubDB.
 * Tab-based navigation with categorized sections.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if WooCommerce is active.
$woo_active = class_exists( 'WooCommerce' ) || in_array( 'woocommerce/woocommerce.php', (array) get_option( 'active_plugins', [] ), true );
?>
<div class="wrap scrubdb-wrap">

    <div class="scrubdb-header">
        <div class="scrubdb-header-inner">
            <h1><span class="dashicons dashicons-database"></span> ScrubDB</h1>
            <p>Diagnose database bloat, find orphaned data, and debug what's slowing things down. Use <strong>Dry Run</strong> to inspect before cleaning.</p>
        </div>
    </div>

    <!-- Tab Navigation -->
    <nav class="scrubdb-tabs" role="tablist">
        <button type="button" class="scrubdb-tab active" data-tab="overview" role="tab" aria-selected="true">
            <span class="dashicons dashicons-chart-bar"></span> Overview
        </button>
        <button type="button" class="scrubdb-tab" data-tab="orphaned" role="tab" aria-selected="false">
            <span class="dashicons dashicons-editor-unlink"></span> Orphaned Data
        </button>
        <button type="button" class="scrubdb-tab" data-tab="content" role="tab" aria-selected="false">
            <span class="dashicons dashicons-media-text"></span> Content Bloat
        </button>
        <button type="button" class="scrubdb-tab" data-tab="options" role="tab" aria-selected="false">
            <span class="dashicons dashicons-admin-settings"></span> Options Table
        </button>
        <?php if ( $woo_active ) : ?>
        <button type="button" class="scrubdb-tab" data-tab="woocommerce" role="tab" aria-selected="false">
            <span class="dashicons dashicons-cart"></span> WooCommerce
        </button>
        <?php endif; ?>
        <button type="button" class="scrubdb-tab" data-tab="maintenance" role="tab" aria-selected="false">
            <span class="dashicons dashicons-admin-tools"></span> Maintenance
        </button>
        <button type="button" class="scrubdb-tab" data-tab="about" role="tab" aria-selected="false">
            <span class="dashicons dashicons-info"></span> About
        </button>
    </nav>

    <!-- Tab: Overview -->
    <div class="scrubdb-tab-panel active" id="panel-overview" role="tabpanel">
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Database Overview</h2>
                <p class="scrubdb-section-desc">Full summary of all WordPress tables, sizes, and overhead.</p>
            </div>
            <div class="scrubdb-cards">
                <div class="scrubdb-card scrubdb-card-wide" id="card-database_info">
                    <div class="scrubdb-card-header">
                        <h3>Database Summary</h3>
                        <span class="scrubdb-card-tag scrubdb-tag-info">Read-Only</span>
                    </div>
                    <p>View all WordPress tables with their engine, row count, size, and overhead.</p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('database_info', 'scan')"><span class="dashicons dashicons-visibility"></span> Load Database Info</button>
                    </div>
                    <div class="scrubdb-result" id="result-database_info"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Orphaned Data -->
    <div class="scrubdb-tab-panel" id="panel-orphaned" role="tabpanel">
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Orphaned Data</h2>
                <p class="scrubdb-section-desc">Detect metadata rows pointing to deleted posts, comments, terms, or users. Common after bulk deletions or plugin removals.</p>
            </div>
            <div class="scrubdb-batch-actions">
                <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRunAll('orphaned')"><span class="dashicons dashicons-search"></span> Scan All</button>
            </div>
            <div class="scrubdb-cards">
                <?php
                $orphaned_tasks = [
                    'orphaned_postmeta'      => [ 'Orphaned Post Meta', 'Meta entries referencing posts that no longer exist.' ],
                    'orphaned_commentmeta'   => [ 'Orphaned Comment Meta', 'Meta entries for comments that have been deleted.' ],
                    'orphaned_termmeta'      => [ 'Orphaned Term Meta', 'Meta entries for taxonomy terms that no longer exist.' ],
                    'orphaned_usermeta'      => [ 'Orphaned User Meta', 'Meta entries for users that have been removed.' ],
                    'orphaned_relationships' => [ 'Orphaned Relationships', 'Term-to-post links where the post no longer exists.' ],
                ];
                foreach ( $orphaned_tasks as $task => $info ) :
                ?>
                <div class="scrubdb-card" id="card-<?php echo esc_attr( $task ); ?>" data-group="orphaned">
                    <div class="scrubdb-card-header">
                        <h3><?php echo esc_html( $info[0] ); ?></h3>
                    </div>
                    <p><?php echo esc_html( $info[1] ); ?></p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'scan')"><span class="dashicons dashicons-search"></span> Dry Run</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-clean" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'clean')"><span class="dashicons dashicons-trash"></span> Clean</button>
                    </div>
                    <div class="scrubdb-result" id="result-<?php echo esc_attr( $task ); ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tab: Content -->
    <div class="scrubdb-tab-panel" id="panel-content" role="tabpanel">
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Content Bloat</h2>
                <p class="scrubdb-section-desc">Identify revisions, drafts, trashed items, and duplicate data that accumulate over time and inflate your database.</p>
            </div>
            <div class="scrubdb-batch-actions">
                <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRunAll('content')"><span class="dashicons dashicons-search"></span> Scan All</button>
            </div>
            <div class="scrubdb-cards">
                <?php
                $content_tasks = [
                    'post_revisions'     => [ 'Post Revisions', 'Old revision copies saved every time you update a post or page.' ],
                    'auto_drafts'        => [ 'Auto-Drafts', 'Automatically saved draft posts created by WordPress.' ],
                    'trashed_posts'      => [ 'Trashed Posts', 'Posts, pages, and custom post types sitting in the trash.' ],
                    'spam_comments'      => [ 'Spam Comments', 'Comments flagged as spam by Akismet or manually.' ],
                    'trashed_comments'   => [ 'Trashed Comments', 'Comments moved to trash but not permanently deleted.' ],
                    'oembed_cache'       => [ 'oEmbed Cache', 'Cached embed data stored in post meta for YouTube, Twitter, etc.' ],
                    'pingbacks'          => [ 'Pingbacks & Trackbacks', 'Automated notifications from other sites linking to your content.' ],
                    'duplicate_postmeta' => [ 'Duplicate Post Meta', 'Exact duplicate meta entries — keeps one copy of each.' ],
                ];
                foreach ( $content_tasks as $task => $info ) :
                ?>
                <div class="scrubdb-card" id="card-<?php echo esc_attr( $task ); ?>" data-group="content">
                    <div class="scrubdb-card-header">
                        <h3><?php echo esc_html( $info[0] ); ?></h3>
                    </div>
                    <p><?php echo esc_html( $info[1] ); ?></p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'scan')"><span class="dashicons dashicons-search"></span> Dry Run</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-clean" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'clean')"><span class="dashicons dashicons-trash"></span> Clean</button>
                    </div>
                    <div class="scrubdb-result" id="result-<?php echo esc_attr( $task ); ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Tab: Options -->
    <div class="scrubdb-tab-panel" id="panel-options" role="tabpanel">
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Options Table Diagnostics</h2>
                <p class="scrubdb-section-desc">Inspect <code>wp_options</code> — the table that loads on every page request. Find what's bloating autoload, which plugins left transients behind, and manage individual options.</p>
            </div>
            <div class="scrubdb-cards">
                <!-- Transients — side by side -->
                <div class="scrubdb-card" id="card-expired_transients">
                    <div class="scrubdb-card-header">
                        <h3>Expired Transients</h3>
                    </div>
                    <p>Transient cache entries that have passed their expiration time.</p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('expired_transients', 'scan')"><span class="dashicons dashicons-search"></span> Dry Run</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-clean" onclick="scrubdbRun('expired_transients', 'clean')"><span class="dashicons dashicons-trash"></span> Clean</button>
                    </div>
                    <div class="scrubdb-result" id="result-expired_transients"></div>
                </div>

                <div class="scrubdb-card" id="card-all_transients">
                    <div class="scrubdb-card-header">
                        <h3>All Transients</h3>
                    </div>
                    <p>Remove ALL transient data — they will regenerate automatically as needed.</p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('all_transients', 'scan')"><span class="dashicons dashicons-search"></span> Dry Run</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-clean" onclick="scrubdbRun('all_transients', 'clean')"><span class="dashicons dashicons-trash"></span> Clean</button>
                    </div>
                    <div class="scrubdb-result" id="result-all_transients"></div>
                </div>

                <!-- Options X-Ray — full width -->
                <div class="scrubdb-card scrubdb-card-wide" id="card-options_debug">
                    <div class="scrubdb-card-header">
                        <h3>Options Table X-Ray</h3>
                        <span class="scrubdb-card-tag scrubdb-tag-info">Read-Only</span>
                    </div>
                    <p>Full analysis of <code>wp_options</code> — autoload health, biggest space consumers, plugin-by-plugin breakdown. Toggle autoload or delete individual options.</p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('options_debug', 'scan')"><span class="dashicons dashicons-search"></span> Analyze</button>
                    </div>
                    <div class="scrubdb-result" id="result-options_debug"></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ( $woo_active ) : ?>
    <!-- Tab: WooCommerce -->
    <div class="scrubdb-tab-panel" id="panel-woocommerce" role="tabpanel">
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>WooCommerce</h2>
                <p class="scrubdb-section-desc">Diagnose WooCommerce-specific bloat — expired customer sessions and WC transient cache.</p>
            </div>
            <div class="scrubdb-cards">
                <?php
                $woo_tasks = [
                    'woo_sessions'   => [ 'Expired Sessions', 'WooCommerce customer sessions that have passed their expiry.' ],
                    'woo_transients' => [ 'WC Transients', 'WooCommerce-specific transient cache entries.' ],
                ];
                foreach ( $woo_tasks as $task => $info ) :
                ?>
                <div class="scrubdb-card" id="card-<?php echo esc_attr( $task ); ?>">
                    <div class="scrubdb-card-header">
                        <h3><?php echo esc_html( $info[0] ); ?></h3>
                    </div>
                    <p><?php echo esc_html( $info[1] ); ?></p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'scan')"><span class="dashicons dashicons-search"></span> Dry Run</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-clean" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'clean')"><span class="dashicons dashicons-trash"></span> Clean</button>
                    </div>
                    <div class="scrubdb-result" id="result-<?php echo esc_attr( $task ); ?>"></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tab: Maintenance -->
    <div class="scrubdb-tab-panel" id="panel-maintenance" role="tabpanel">
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Table Operations</h2>
                <p class="scrubdb-section-desc">Reclaim overhead space and repair table corruption.</p>
            </div>
            <div class="scrubdb-cards">
                <div class="scrubdb-card" id="card-optimize_tables">
                    <div class="scrubdb-card-header">
                        <h3>Optimize Tables</h3>
                    </div>
                    <p>Reclaim wasted overhead space from InnoDB and MyISAM tables.</p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('optimize_tables', 'scan')"><span class="dashicons dashicons-search"></span> Dry Run</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-primary" onclick="scrubdbRun('optimize_tables', 'clean')"><span class="dashicons dashicons-performance"></span> Optimize</button>
                    </div>
                    <div class="scrubdb-result" id="result-optimize_tables"></div>
                </div>

                <div class="scrubdb-card" id="card-repair_tables">
                    <div class="scrubdb-card-header">
                        <h3>Repair Tables</h3>
                    </div>
                    <p>Run REPAIR TABLE on all WordPress tables to fix corruption.</p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('repair_tables', 'scan')"><span class="dashicons dashicons-search"></span> Dry Run</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-primary" onclick="scrubdbRun('repair_tables', 'clean')"><span class="dashicons dashicons-admin-generic"></span> Repair</button>
                    </div>
                    <div class="scrubdb-result" id="result-repair_tables"></div>
                </div>
            </div>
        </div>

        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Debug & Cron</h2>
                <p class="scrubdb-section-desc">Find orphaned scheduled events from deactivated plugins and inspect the debug log.</p>
            </div>
            <div class="scrubdb-cards">
                <div class="scrubdb-card" id="card-cron_cleanup">
                    <div class="scrubdb-card-header">
                        <h3>Orphaned Cron Jobs</h3>
                    </div>
                    <p>Scheduled events with no registered callback, typically from deactivated plugins.</p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('cron_cleanup', 'scan')"><span class="dashicons dashicons-search"></span> Dry Run</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-clean" onclick="scrubdbRun('cron_cleanup', 'clean')"><span class="dashicons dashicons-trash"></span> Clean</button>
                    </div>
                    <div class="scrubdb-result" id="result-cron_cleanup"></div>
                </div>

                <div class="scrubdb-card scrubdb-card-wide" id="card-debug_log">
                    <div class="scrubdb-card-header">
                        <h3>Debug Log</h3>
                    </div>
                    <p>View and clear <code>wp-content/debug.log</code>.</p>
                    <div class="scrubdb-actions">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('debug_log', 'scan')"><span class="dashicons dashicons-visibility"></span> View Log</button>
                        <button type="button" class="button scrubdb-btn scrubdb-btn-clean" onclick="scrubdbRun('debug_log', 'clean')"><span class="dashicons dashicons-dismiss"></span> Clear Log</button>
                    </div>
                    <div class="scrubdb-result" id="result-debug_log"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: About -->
    <div class="scrubdb-tab-panel" id="panel-about" role="tabpanel">

        <!-- Plugin Info Section -->
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Plugin Information</h2>
                <p class="scrubdb-section-desc">Current installation details and environment.</p>
            </div>
            <div class="scrubdb-cards">
                <div class="scrubdb-card">
                    <div class="scrubdb-card-header">
                        <h3>ScrubDB</h3>
                        <span class="scrubdb-card-tag scrubdb-tag-info">v<?php echo esc_html( SCRUBDB_VERSION ); ?></span>
                    </div>
                    <p>WordPress database diagnostic and cleanup tool.</p>
                    <table class="scrubdb-info-table">
                        <tr><td>Version</td><td><strong><?php echo esc_html( SCRUBDB_VERSION ); ?></strong></td></tr>
                        <tr><td>Author</td><td><a href="https://ajithrn.com" target="_blank" rel="noopener">Ajith R N</a></td></tr>
                        <tr><td>License</td><td>GPL v2 or later</td></tr>
                        <tr><td>Repository</td><td><a href="https://github.com/ajithrn/scrubdb" target="_blank" rel="noopener">github.com/ajithrn/scrubdb</a></td></tr>
                    </table>
                </div>

                <div class="scrubdb-card">
                    <div class="scrubdb-card-header">
                        <h3>Environment</h3>
                    </div>
                    <table class="scrubdb-info-table">
                        <tr><td>WordPress</td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
                        <tr><td>PHP</td><td><?php echo esc_html( PHP_VERSION ); ?></td></tr>
                        <tr><td>MySQL</td><td><?php global $wpdb; echo esc_html( $wpdb->db_version() ); ?></td></tr>
                        <tr><td>DB Prefix</td><td><code><?php echo esc_html( $wpdb->prefix ); ?></code></td></tr>
                        <tr><td>WP Debug</td><td><?php echo ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '<span style="color:#dc2626;">Enabled</span>' : '<span style="color:#166534;">Disabled</span>'; ?></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Update Section -->
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Updates</h2>
                <p class="scrubdb-section-desc">ScrubDB checks for updates from GitHub releases automatically every 12 hours.</p>
            </div>
            <div class="scrubdb-cards">
                <div class="scrubdb-card scrubdb-card-wide" id="card-update-status">
                    <div class="scrubdb-card-header">
                        <h3>Update Status</h3>
                    </div>
                    <?php
                    $update_cache = get_site_transient( 'scrubdb_github_update_' . md5( 'scrubdb' ) );
                    $has_cache    = $update_cache && isset( $update_cache->new_version );
                    $is_latest    = $has_cache && version_compare( SCRUBDB_VERSION, $update_cache->new_version, '>=' );
                    ?>
                    <div id="scrubdb-update-result">
                        <?php if ( $has_cache && ! $is_latest ) : ?>
                            <div class="scrubdb-update-status scrubdb-update-available">
                                <span class="dashicons dashicons-update"></span>
                                <div>
                                    <strong>Version <?php echo esc_html( $update_cache->new_version ); ?> is available</strong>
                                    <p style="margin:4px 0 0;font-size:12px;">
                                        You're running v<?php echo esc_html( SCRUBDB_VERSION ); ?>.
                                        <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Go to Plugins page to update</a>
                                    </p>
                                </div>
                            </div>
                        <?php elseif ( $has_cache && $is_latest ) : ?>
                            <div class="scrubdb-update-status scrubdb-update-ok">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <div>
                                    <strong>You're up to date</strong>
                                    <p style="margin:4px 0 0;font-size:12px;color:#64748b;">Running the latest version (v<?php echo esc_html( SCRUBDB_VERSION ); ?>).</p>
                                </div>
                            </div>
                        <?php else : ?>
                            <div class="scrubdb-update-status scrubdb-update-ok">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <div>
                                    <strong>Current version: v<?php echo esc_html( SCRUBDB_VERSION ); ?></strong>
                                    <p style="margin:4px 0 0;font-size:12px;color:#64748b;">No update check has run yet. Click below to check now.</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="scrubdb-actions" style="margin-top:14px;margin-bottom:0;">
                        <button type="button" class="button scrubdb-btn scrubdb-btn-scan" id="scrubdb-check-update-btn" onclick="scrubdbCheckUpdate()">
                            <span class="dashicons dashicons-update"></span> Check for Updates
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Help Section -->
        <div class="scrubdb-section">
            <div class="scrubdb-section-header">
                <h2>Getting Started</h2>
                <p class="scrubdb-section-desc">How to use ScrubDB effectively for database diagnostics.</p>
            </div>
            <div class="scrubdb-cards">
                <div class="scrubdb-card">
                    <div class="scrubdb-card-header">
                        <h3><span class="dashicons dashicons-lightbulb" style="color:#f59e0b;margin-right:6px;"></span> Diagnostic Workflow</h3>
                    </div>
                    <ol class="scrubdb-help-list">
                        <li>Start with <strong>Overview</strong> to see total database size and table breakdown</li>
                        <li>Check <strong>Options Table</strong> → Autoload Audit — autoload bloat is the #1 cause of slow page loads</li>
                        <li>Use <strong>Options Table</strong> → X-Ray to identify which plugins are consuming the most space</li>
                        <li>Scan <strong>Orphaned Data</strong> to find metadata left behind by deleted posts/users</li>
                        <li>Check <strong>Content Bloat</strong> for accumulated revisions, drafts, and spam</li>
                    </ol>
                </div>

                <div class="scrubdb-card">
                    <div class="scrubdb-card-header">
                        <h3><span class="dashicons dashicons-shield" style="color:#16a34a;margin-right:6px;"></span> Safety Notes</h3>
                    </div>
                    <ul class="scrubdb-help-list">
                        <li><strong>Always Dry Run first</strong> — every scan is read-only and shows exactly what will be affected</li>
                        <li><strong>Backup before cleaning</strong> — all cleanup operations are irreversible</li>
                        <li><strong>Protected options</strong> — core WP options (siteurl, active_plugins, etc.) cannot be modified or deleted</li>
                        <li><strong>Admin only</strong> — requires <code>manage_options</code> capability</li>
                    </ul>
                </div>

                <div class="scrubdb-card scrubdb-card-wide">
                    <div class="scrubdb-card-header">
                        <h3><span class="dashicons dashicons-admin-links" style="color:#3b82f6;margin-right:6px;"></span> Resources</h3>
                    </div>
                    <div class="scrubdb-links-row">
                        <a href="https://github.com/ajithrn/scrubdb" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-github"></span> GitHub Repository
                        </a>
                        <a href="https://github.com/ajithrn/scrubdb/issues" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-flag"></span> Report an Issue
                        </a>
                        <a href="https://github.com/ajithrn/scrubdb/releases" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-list-view"></span> Changelog
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
