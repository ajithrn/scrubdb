<?php
/**
 * Admin page template for ScrubDB.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap scrubdb-wrap">

    <div class="scrubdb-header">
        <div class="scrubdb-header-inner">
            <h1><span class="dashicons dashicons-database"></span> ScrubDB</h1>
            <p>Scan, analyze, and clean your WordPress database. Always run <strong>Dry Run</strong> first.</p>
        </div>
    </div>

    <!-- Database Overview -->
    <div class="scrubdb-section">
        <div class="scrubdb-section-header">
            <span class="dashicons dashicons-chart-bar"></span>
            <h2>Database Overview</h2>
        </div>
        <div class="scrubdb-cards">
            <div class="scrubdb-card scrubdb-card-wide" id="card-database_info">
                <div class="scrubdb-card-header">
                    <h3>Database Summary</h3>
                    <span class="scrubdb-card-tag scrubdb-tag-info">Read-Only</span>
                </div>
                <p>Full overview of all WordPress tables, sizes, and overhead.</p>
                <div class="scrubdb-actions">
                    <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('database_info', 'scan')"><span class="dashicons dashicons-visibility"></span> Load Database Info</button>
                </div>
                <div class="scrubdb-result" id="result-database_info"></div>
            </div>
        </div>
    </div>

    <!-- Orphaned Data -->
    <div class="scrubdb-section">
        <div class="scrubdb-section-header">
            <span class="dashicons dashicons-editor-unlink"></span>
            <h2>Orphaned Data</h2>
        </div>
        <div class="scrubdb-cards">
            <?php
            $orphaned_tasks = [
                'orphaned_postmeta'      => [ 'Post Meta', 'Meta entries referencing posts that no longer exist in the database.' ],
                'orphaned_commentmeta'   => [ 'Comment Meta', 'Meta entries for comments that have been deleted.' ],
                'orphaned_termmeta'      => [ 'Term Meta', 'Meta entries for taxonomy terms that no longer exist.' ],
                'orphaned_usermeta'      => [ 'User Meta', 'Meta entries for users that have been removed.' ],
                'orphaned_relationships' => [ 'Term Relationships', 'Term-to-post links where the post no longer exists.' ],
            ];
            foreach ( $orphaned_tasks as $task => $info ) :
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

    <!-- Content Cleanup -->
    <div class="scrubdb-section">
        <div class="scrubdb-section-header">
            <span class="dashicons dashicons-media-text"></span>
            <h2>Content Cleanup</h2>
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

    <!-- Options Table -->
    <div class="scrubdb-section">
        <div class="scrubdb-section-header">
            <span class="dashicons dashicons-admin-settings"></span>
            <h2>Options Table</h2>
        </div>
        <div class="scrubdb-cards">
            <?php
            $options_tasks = [
                'expired_transients' => [ 'Expired Transients', 'Transient cache entries that have passed their expiration time.', false ],
                'all_transients'     => [ 'All Transients', 'Remove ALL transient data — they will regenerate automatically as needed.', false ],
                'autoload_audit'     => [ 'Autoload Audit', 'Analyze which options are autoloaded on every page load. Ideal size is under 1 MB.', true ],
            ];
            foreach ( $options_tasks as $task => $info ) :
                $scan_only = $info[2];
            ?>
            <div class="scrubdb-card" id="card-<?php echo esc_attr( $task ); ?>">
                <div class="scrubdb-card-header">
                    <h3><?php echo esc_html( $info[0] ); ?></h3>
                    <?php if ( $scan_only ) : ?>
                    <span class="scrubdb-card-tag scrubdb-tag-info">Read-Only</span>
                    <?php endif; ?>
                </div>
                <p><?php echo esc_html( $info[1] ); ?></p>
                <div class="scrubdb-actions">
                    <button type="button" class="button scrubdb-btn scrubdb-btn-scan" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'scan')"><span class="dashicons dashicons-search"></span> <?php echo $scan_only ? 'Analyze' : 'Dry Run'; ?></button>
                    <?php if ( ! $scan_only ) : ?>
                    <button type="button" class="button scrubdb-btn scrubdb-btn-clean" onclick="scrubdbRun('<?php echo esc_js( $task ); ?>', 'clean')"><span class="dashicons dashicons-trash"></span> Clean</button>
                    <?php endif; ?>
                </div>
                <div class="scrubdb-result" id="result-<?php echo esc_attr( $task ); ?>"></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- WooCommerce -->
    <div class="scrubdb-section">
        <div class="scrubdb-section-header">
            <span class="dashicons dashicons-cart"></span>
            <h2>WooCommerce</h2>
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

    <!-- Database Operations -->
    <div class="scrubdb-section">
        <div class="scrubdb-section-header">
            <span class="dashicons dashicons-admin-tools"></span>
            <h2>Database Operations</h2>
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

    <!-- Debug & Maintenance -->
    <div class="scrubdb-section">
        <div class="scrubdb-section-header">
            <span class="dashicons dashicons-warning"></span>
            <h2>Debug & Maintenance</h2>
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
