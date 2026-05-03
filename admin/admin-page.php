<?php
/**
 * Admin page template for ScrubDB.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap scrubdb-wrap">
    <h1>🧽 ScrubDB</h1>
    <p class="scrubdb-subtitle">Scan, analyze, and clean your WordPress database. Always run <strong>Dry Run</strong> first to preview what will be affected.</p>

    <!-- Database Overview -->
    <div class="scrubdb-section">
        <h2>📊 Database Overview</h2>
        <div class="scrubdb-overview" id="scrubdb-overview">
            <button type="button" class="button button-secondary" onclick="scrubdbRun('database_info', 'scan')">Load Database Info</button>
        </div>
    </div>

    <!-- Orphaned Data -->
    <div class="scrubdb-section">
        <h2>🔗 Orphaned Data</h2>
        <div class="scrubdb-cards">
            <?php
            $orphaned_tasks = [
                'orphaned_postmeta'      => [ 'Post Meta', 'Meta entries referencing deleted posts.' ],
                'orphaned_commentmeta'   => [ 'Comment Meta', 'Meta entries for deleted comments.' ],
                'orphaned_termmeta'      => [ 'Term Meta', 'Meta entries for deleted terms.' ],
                'orphaned_usermeta'      => [ 'User Meta', 'Meta entries for deleted users.' ],
                'orphaned_relationships' => [ 'Term Relationships', 'Term links to deleted posts.' ],
            ];
            foreach ( $orphaned_tasks as $task => $info ) :
            ?>
            <div class="scrubdb-card" id="card-<?php echo esc_attr( $task ); ?>">
                <h3><?php echo esc_html( $info[0] ); ?></h3>
                <p><?php echo esc_html( $info[1] ); ?></p>
                <div class="scrubdb-actions">
                    <button type="button" class="button button-secondary" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'scan')">🔍 Dry Run</button>
                    <button type="button" class="button button-primary scrubdb-btn-danger" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'clean')">🧹 Clean</button>
                </div>
                <div class="scrubdb-result" id="result-<?php echo esc_attr( $task ); ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Content Cleanup -->
    <div class="scrubdb-section">
        <h2>📝 Content Cleanup</h2>
        <div class="scrubdb-cards">
            <?php
            $content_tasks = [
                'post_revisions'     => [ 'Post Revisions', 'Old revision copies of posts and pages.' ],
                'auto_drafts'        => [ 'Auto-Drafts', 'Automatically saved draft posts.' ],
                'trashed_posts'      => [ 'Trashed Posts', 'Posts and pages in the trash.' ],
                'spam_comments'      => [ 'Spam Comments', 'Comments marked as spam.' ],
                'trashed_comments'   => [ 'Trashed Comments', 'Comments moved to trash.' ],
                'oembed_cache'       => [ 'oEmbed Cache', 'Cached embed data in postmeta.' ],
                'pingbacks'          => [ 'Pingbacks & Trackbacks', 'Pingback and trackback comments.' ],
                'duplicate_postmeta' => [ 'Duplicate Post Meta', 'Exact duplicate meta entries (keeps one copy).' ],
            ];
            foreach ( $content_tasks as $task => $info ) :
            ?>
            <div class="scrubdb-card" id="card-<?php echo esc_attr( $task ); ?>">
                <h3><?php echo esc_html( $info[0] ); ?></h3>
                <p><?php echo esc_html( $info[1] ); ?></p>
                <div class="scrubdb-actions">
                    <button type="button" class="button button-secondary" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'scan')">🔍 Dry Run</button>
                    <button type="button" class="button button-primary scrubdb-btn-danger" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'clean')">🧹 Clean</button>
                </div>
                <div class="scrubdb-result" id="result-<?php echo esc_attr( $task ); ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Options Table -->
    <div class="scrubdb-section">
        <h2>⚙️ Options Table</h2>
        <div class="scrubdb-cards">
            <?php
            $options_tasks = [
                'expired_transients' => [ 'Expired Transients', 'Transient cache entries past their expiry.', false ],
                'all_transients'     => [ 'All Transients', 'Remove ALL transient data (they regenerate automatically).', false ],
                'autoload_audit'     => [ 'Autoload Audit', 'Analyze autoloaded options for bloat (read-only).', true ],
            ];
            foreach ( $options_tasks as $task => $info ) :
                $scan_only = $info[2];
            ?>
            <div class="scrubdb-card" id="card-<?php echo esc_attr( $task ); ?>">
                <h3><?php echo esc_html( $info[0] ); ?></h3>
                <p><?php echo esc_html( $info[1] ); ?></p>
                <div class="scrubdb-actions">
                    <button type="button" class="button button-secondary" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'scan')">🔍 <?php echo $scan_only ? 'Analyze' : 'Dry Run'; ?></button>
                    <?php if ( ! $scan_only ) : ?>
                    <button type="button" class="button button-primary scrubdb-btn-danger" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'clean')">🧹 Clean</button>
                    <?php endif; ?>
                </div>
                <div class="scrubdb-result" id="result-<?php echo esc_attr( $task ); ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- WooCommerce -->
    <div class="scrubdb-section">
        <h2>🛒 WooCommerce</h2>
        <div class="scrubdb-cards">
            <?php
            $woo_tasks = [
                'woo_sessions'   => [ 'Expired Sessions', 'WooCommerce sessions past their expiry.' ],
                'woo_transients' => [ 'WC Transients', 'WooCommerce-specific transient cache.' ],
            ];
            foreach ( $woo_tasks as $task => $info ) :
            ?>
            <div class="scrubdb-card" id="card-<?php echo esc_attr( $task ); ?>">
                <h3><?php echo esc_html( $info[0] ); ?></h3>
                <p><?php echo esc_html( $info[1] ); ?></p>
                <div class="scrubdb-actions">
                    <button type="button" class="button button-secondary" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'scan')">🔍 Dry Run</button>
                    <button type="button" class="button button-primary scrubdb-btn-danger" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'clean')">🧹 Clean</button>
                </div>
                <div class="scrubdb-result" id="result-<?php echo esc_attr( $task ); ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Database Operations -->
    <div class="scrubdb-section">
        <h2>🛠️ Database Operations</h2>
        <div class="scrubdb-cards">
            <div class="scrubdb-card" id="card-optimize_tables">
                <h3>Optimize Tables</h3>
                <p>Reclaim overhead space from InnoDB / MyISAM tables.</p>
                <div class="scrubdb-actions">
                    <button type="button" class="button button-secondary" onclick="scrubdbRun('optimize_tables', 'scan')">🔍 Dry Run</button>
                    <button type="button" class="button button-primary" onclick="scrubdbRun('optimize_tables', 'clean')">⚡ Optimize</button>
                </div>
                <div class="scrubdb-result" id="result-optimize_tables"></div>
            </div>

            <div class="scrubdb-card" id="card-repair_tables">
                <h3>Repair Tables</h3>
                <p>Run REPAIR TABLE on all WordPress tables.</p>
                <div class="scrubdb-actions">
                    <button type="button" class="button button-secondary" onclick="scrubdbRun('repair_tables', 'scan')">🔍 Dry Run</button>
                    <button type="button" class="button button-primary" onclick="scrubdbRun('repair_tables', 'clean')">🔧 Repair</button>
                </div>
                <div class="scrubdb-result" id="result-repair_tables"></div>
            </div>
        </div>
    </div>

    <!-- Debug & Maintenance -->
    <div class="scrubdb-section">
        <h2>🐛 Debug & Maintenance</h2>
        <div class="scrubdb-cards">
            <div class="scrubdb-card" id="card-cron_cleanup">
                <h3>Orphaned Cron Jobs</h3>
                <p>Scheduled events with no registered callback (from deactivated plugins).</p>
                <div class="scrubdb-actions">
                    <button type="button" class="button button-secondary" onclick="scrubdbRun('cron_cleanup', 'scan')">🔍 Dry Run</button>
                    <button type="button" class="button button-primary scrubdb-btn-danger" onclick="scrubdbRun('cron_cleanup', 'clean')">🧹 Clean</button>
                </div>
                <div class="scrubdb-result" id="result-cron_cleanup"></div>
            </div>

            <div class="scrubdb-card scrubdb-card-wide" id="card-debug_log">
                <h3>Debug Log</h3>
                <p>View and clear <code>wp-content/debug.log</code>.</p>
                <div class="scrubdb-actions">
                    <button type="button" class="button button-secondary" onclick="scrubdbRun('debug_log', 'scan')">👁️ View Log</button>
                    <button type="button" class="button button-primary scrubdb-btn-danger" onclick="scrubdbRun('debug_log', 'clean')">🗑️ Clear Log</button>
                </div>
                <div class="scrubdb-result" id="result-debug_log"></div>
            </div>
        </div>
    </div>

</div>
