<?php
/**
 * Task module: Database operations — optimize, repair, info.
 * Uses smart units (KB/MB) for accurate display on all database sizes.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScrubDB_Task_Database_Ops {

    public function get_tasks() {
        return [
            'optimize_tables',
            'repair_tables',
            'database_info',
            'orphaned_tables',
            'drop_table',
        ];
    }

    public function task_optimize_tables( $mode ) {
        global $wpdb;

        $tables = $wpdb->get_results(
            "SELECT TABLE_NAME AS name,
                    ENGINE AS engine,
                    TABLE_ROWS AS rows_count,
                    ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
                    ROUND(DATA_FREE / 1024 / 1024, 2) AS overhead_mb
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE '{$wpdb->prefix}%'
               AND DATA_FREE > 0
             ORDER BY DATA_FREE DESC"
        );

        $optimized = 0;
        if ( 'clean' === $mode && ! empty( $tables ) ) {
            foreach ( $tables as $t ) {
                $wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $t->name ) . '`' );
                $optimized++;
            }
        }

        return [
            'count'     => count( $tables ),
            'details'   => $tables,
            'optimized' => $optimized,
            'mode'      => $mode,
        ];
    }

    public function task_repair_tables( $mode ) {
        global $wpdb;

        $tables = $wpdb->get_results(
            "SELECT TABLE_NAME AS name, ENGINE AS engine
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE '{$wpdb->prefix}%'"
        );

        $results = [];
        if ( 'clean' === $mode ) {
            foreach ( $tables as $t ) {
                $r = $wpdb->get_row( 'REPAIR TABLE `' . esc_sql( $t->name ) . '`' );
                $results[] = [
                    'table'  => $t->name,
                    'status' => $r->Msg_text ?? 'unknown',
                ];
            }
        }

        return [
            'count'   => count( $tables ),
            'results' => $results,
            'mode'    => $mode,
        ];
    }

    public function task_database_info( $mode ) {
        global $wpdb;

        // Get per-table data with smart unit sizing.
        $tables = $wpdb->get_results(
            "SELECT TABLE_NAME AS name,
                    ENGINE AS engine,
                    IFNULL(TABLE_ROWS, 0) AS rows_count,
                    IFNULL(DATA_LENGTH, 0) + IFNULL(INDEX_LENGTH, 0) AS size_bytes,
                    IFNULL(DATA_FREE, 0) AS overhead_bytes
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE '{$wpdb->prefix}%'
             ORDER BY (IFNULL(DATA_LENGTH, 0) + IFNULL(INDEX_LENGTH, 0)) DESC"
        );

        // Build plugin detection data for owner identification.
        $active_plugins  = (array) get_option( 'active_plugins', [] );
        $plugin_matchers = self::build_plugin_matchers( $active_plugins );
        $core_tables     = self::get_core_tables( $wpdb->prefix );

        // Format sizes with smart units.
        $total_size_bytes     = 0;
        $total_overhead_bytes = 0;

        foreach ( $tables as &$t ) {
            $t->rows_count     = (int) $t->rows_count;
            $size_b            = (float) $t->size_bytes;
            $overhead_b        = (float) $t->overhead_bytes;
            $total_size_bytes     += $size_b;
            $total_overhead_bytes += $overhead_b;

            $t->total_size    = self::format_bytes( $size_b );
            $t->overhead_size = self::format_bytes( $overhead_b );

            // Identify owner.
            if ( in_array( $t->name, $core_tables, true ) ) {
                $t->owner  = 'WordPress Core';
                $t->status = 'core';
            } else {
                $short_name = substr( $t->name, strlen( $wpdb->prefix ) );
                $owner_info = self::identify_table_owner( $short_name, $plugin_matchers, $active_plugins );
                $t->owner  = $owner_info['name'];
                $t->status = $owner_info['status'];
            }

            // Remove raw bytes from response.
            unset( $t->size_bytes, $t->overhead_bytes );
        }
        unset( $t );

        $server = $wpdb->get_var( 'SELECT VERSION()' );

        return [
            'tables'         => $tables,
            'total_size'     => self::format_bytes( $total_size_bytes ),
            'total_overhead' => self::format_bytes( $total_overhead_bytes ),
            'table_count'    => count( $tables ),
            'server'         => $server,
            'db_name'        => DB_NAME,
            'mode'           => 'scan',
        ];
    }

    /**
     * Detect orphaned tables — tables with the WP prefix that don't belong to
     * WordPress core or any currently active plugin.
     */
    public function task_orphaned_tables( $mode ) {
        global $wpdb;

        // All tables with our prefix.
        $all_tables = $wpdb->get_results(
            "SELECT TABLE_NAME AS name,
                    ENGINE AS engine,
                    IFNULL(TABLE_ROWS, 0) AS rows_count,
                    IFNULL(DATA_LENGTH, 0) + IFNULL(INDEX_LENGTH, 0) AS size_bytes
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE '{$wpdb->prefix}%'
             ORDER BY TABLE_NAME"
        );

        // WP core tables that should never be flagged.
        $core_tables = self::get_core_tables( $wpdb->prefix );

        // Build plugin detection data.
        $active_plugins = (array) get_option( 'active_plugins', [] );
        $plugin_matchers = self::build_plugin_matchers( $active_plugins );

        $orphaned = [];
        foreach ( $all_tables as $table ) {
            $name = $table->name;

            // Skip core tables.
            if ( in_array( $name, $core_tables, true ) ) {
                continue;
            }

            // Strip prefix for matching.
            $short_name = substr( $name, strlen( $wpdb->prefix ) );

            // Try to identify which plugin owns this table.
            $owner = self::identify_table_owner( $short_name, $plugin_matchers, $active_plugins );

            $table->size       = self::format_bytes( (float) $table->size_bytes );
            $table->rows_count = (int) $table->rows_count;
            $table->owner      = $owner['name'];
            $table->status     = $owner['status']; // 'active', 'inactive', 'unknown'
            unset( $table->size_bytes );

            // Only include tables that are NOT owned by an active plugin.
            if ( 'active' !== $owner['status'] ) {
                $orphaned[] = $table;
            }
        }

        $items_columns = [
            [ 'label' => 'Table Name',    'key' => 'name',       'mono' => true ],
            [ 'label' => 'Likely Owner',  'key' => 'owner' ],
            [ 'label' => 'Status',        'key' => 'status' ],
            [ 'label' => 'Engine',        'key' => 'engine' ],
            [ 'label' => 'Rows',          'key' => 'rows_count' ],
            [ 'label' => 'Size',          'key' => 'size' ],
        ];

        return [
            'count'         => count( $orphaned ),
            'items'         => $orphaned,
            'items_columns' => $items_columns,
            'mode'          => $mode,
            'note'          => count( $orphaned ) > 0
                ? 'These tables don\'t match any active plugin. "Unknown" means we couldn\'t identify the owner.'
                : '',
        ];
    }

    /**
     * Build matchers from active plugins for table identification.
     * Returns array of [ 'slug' => ..., 'patterns' => [...], 'name' => ... ]
     */
    private static function build_plugin_matchers( $active_plugins ) {
        $matchers = [];

        foreach ( $active_plugins as $plugin_file ) {
            $slug = dirname( $plugin_file );
            if ( '.' === $slug ) {
                $slug = basename( $plugin_file, '.php' );
            }

            $name = ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );

            // Generate table name patterns from the slug.
            $patterns = [];
            $normalized = str_replace( '-', '_', $slug );
            $patterns[] = $normalized; // e.g., "woocommerce"
            $patterns[] = str_replace( '_', '', $normalized ); // e.g., "woocommerce" (no change here)

            // Common abbreviation patterns.
            $parts = explode( '_', $normalized );
            if ( count( $parts ) > 1 ) {
                // First letters of each word: "wp_force_repair" → "wfr"
                $abbrev = '';
                foreach ( $parts as $p ) {
                    $abbrev .= $p[0] ?? '';
                }
                if ( strlen( $abbrev ) >= 2 ) {
                    $patterns[] = $abbrev;
                }

                // First word only if it's distinctive enough (>= 4 chars).
                if ( strlen( $parts[0] ) >= 4 ) {
                    $patterns[] = $parts[0];
                }
            }

            $patterns = array_unique( array_filter( $patterns ) );

            $matchers[] = [
                'slug'     => $slug,
                'patterns' => $patterns,
                'name'     => $name,
            ];
        }

        return $matchers;
    }

    /**
     * Try to identify which plugin created a table based on its name.
     * Returns [ 'name' => string, 'status' => 'active'|'inactive'|'uninstalled'|'unknown' ]
     */
    private static function identify_table_owner( $short_name, $plugin_matchers, $active_plugins ) {
        // Get all installed plugins once for reuse.
        static $all_plugins = null;
        if ( null === $all_plugins ) {
            $all_plugins = get_plugins();
        }

        // 1. Check against known plugin table prefixes first (most reliable).
        $known_map = self::get_known_table_map();
        foreach ( $known_map as $prefix => $info ) {
            if ( strpos( $short_name, $prefix ) === 0 ) {
                $status = self::get_plugin_status( (array) $info['slugs'], $active_plugins, $all_plugins );
                return [
                    'name'   => $info['name'],
                    'status' => $status,
                ];
            }
        }

        // 2. Check against active plugin slug patterns.
        foreach ( $plugin_matchers as $matcher ) {
            foreach ( $matcher['patterns'] as $pattern ) {
                // Match if table starts with pattern, or pattern appears as a segment.
                if ( strpos( $short_name, $pattern ) === 0 ||
                     strpos( $short_name, $pattern . '_' ) !== false ) {
                    return [ 'name' => $matcher['name'], 'status' => 'active' ];
                }
            }
        }

        // 3. Check ALL installed plugins (active + inactive) with flexible matching.
        foreach ( $all_plugins as $file => $data ) {
            $slug = dirname( $file );
            if ( '.' === $slug ) {
                $slug = basename( $file, '.php' );
            }

            $normalized = str_replace( '-', '_', $slug );
            $is_active  = in_array( $file, $active_plugins, true );

            // Exact start match.
            if ( strpos( $short_name, $normalized ) === 0 ) {
                return [
                    'name'   => $data['Name'] ?? $slug,
                    'status' => $is_active ? 'active' : 'inactive',
                ];
            }

            // Also try: remove common prefixes like "wp_" from the slug.
            $stripped = preg_replace( '/^(wp_|wordpress_)/', '', $normalized );
            if ( $stripped !== $normalized && strlen( $stripped ) >= 3 && strpos( $short_name, $stripped ) === 0 ) {
                return [
                    'name'   => $data['Name'] ?? $slug,
                    'status' => $is_active ? 'active' : 'inactive',
                ];
            }

            // Check if slug (without hyphens/underscores) appears at start of table name.
            $compact = str_replace( '_', '', $normalized );
            if ( strlen( $compact ) >= 4 && strpos( $short_name, $compact ) === 0 ) {
                return [
                    'name'   => $data['Name'] ?? $slug,
                    'status' => $is_active ? 'active' : 'inactive',
                ];
            }

            // Check if table name contains the slug as a segment.
            if ( strlen( $normalized ) >= 4 && strpos( $short_name, $normalized . '_' ) !== false ) {
                return [
                    'name'   => $data['Name'] ?? $slug,
                    'status' => $is_active ? 'active' : 'inactive',
                ];
            }
        }

        return [ 'name' => 'Unknown', 'status' => 'unknown' ];
    }

    /**
     * Determine plugin status: active, inactive (installed but deactivated), or uninstalled.
     *
     * @param array $slugs        Plugin folder slugs to check.
     * @param array $active_plugins Active plugins list from WP options.
     * @param array $all_plugins  All installed plugins from get_plugins().
     * @return string 'active', 'inactive', or 'uninstalled'
     */
    private static function get_plugin_status( $slugs, $active_plugins, $all_plugins ) {
        // Check if any of the slugs match an active plugin.
        foreach ( $active_plugins as $plugin_file ) {
            $plugin_slug = dirname( $plugin_file );
            if ( in_array( $plugin_slug, $slugs, true ) ) {
                return 'active';
            }
        }

        // Check if any of the slugs match an installed (but inactive) plugin.
        foreach ( $all_plugins as $plugin_file => $data ) {
            $plugin_slug = dirname( $plugin_file );
            if ( in_array( $plugin_slug, $slugs, true ) ) {
                return 'inactive';
            }
        }

        // Plugin is not on the filesystem at all.
        return 'uninstalled';
    }

    /**
     * Known mapping of table prefixes to plugins.
     * Loaded from data/known-tables.json for easy maintenance.
     */
    private static function get_known_table_map() {
        static $map = null;

        if ( null === $map ) {
            $file = SCRUBDB_PATH . 'data/known-tables.json';
            if ( file_exists( $file ) ) {
                $json = file_get_contents( $file );
                $data = json_decode( $json, true );
                if ( is_array( $data ) ) {
                    // Remove metadata keys (start with _).
                    $map = [];
                    foreach ( $data as $key => $value ) {
                        if ( strpos( $key, '_' ) === 0 && ! isset( $value['slugs'] ) ) {
                            continue;
                        }
                        $map[ $key ] = $value;
                    }
                } else {
                    $map = [];
                }
            } else {
                $map = [];
            }
        }

        return $map;
    }

    /**
     * Drop a specific table. Requires table_name confirmation via POST.
     */
    public function task_drop_table( $mode ) {
        global $wpdb;

        $table_name = sanitize_text_field( $_POST['table_name'] ?? '' );
        $confirm    = sanitize_text_field( $_POST['confirm_name'] ?? '' );

        if ( empty( $table_name ) ) {
            return [ 'error' => 'No table name provided.', 'mode' => $mode ];
        }

        // Must confirm by typing the table name.
        if ( $table_name !== $confirm ) {
            return [ 'error' => 'Confirmation does not match. Type the exact table name to confirm.', 'mode' => $mode ];
        }

        // Must have our prefix.
        if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
            return [ 'error' => 'Can only drop tables with the WordPress prefix.', 'mode' => $mode ];
        }

        // Block core tables.
        $core_tables = self::get_core_tables( $wpdb->prefix );
        if ( in_array( $table_name, $core_tables, true ) ) {
            return [ 'error' => 'Cannot drop WordPress core table: ' . $table_name, 'mode' => $mode ];
        }

        // Verify table exists.
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $table_name
        ) );

        if ( ! $exists ) {
            return [ 'error' => 'Table not found: ' . $table_name, 'mode' => $mode ];
        }

        // Drop it.
        $wpdb->query( 'DROP TABLE `' . esc_sql( $table_name ) . '`' );

        return [
            'deleted'    => 1,
            'table_name' => $table_name,
            'message'    => 'Table "' . $table_name . '" has been permanently dropped.',
            'mode'       => $mode,
        ];
    }

    /**
     * Get the list of WordPress core tables (should never be dropped).
     */
    private static function get_core_tables( $prefix ) {
        $core = [
            'commentmeta', 'comments', 'links', 'options',
            'postmeta', 'posts', 'term_relationships', 'term_taxonomy',
            'termmeta', 'terms', 'usermeta', 'users',
        ];

        // Multisite tables.
        $ms = [
            'blogs', 'blog_versions', 'registration_log', 'signups',
            'site', 'sitemeta', 'sitecategories',
        ];

        $tables = [];
        foreach ( $core as $t ) {
            $tables[] = $prefix . $t;
        }
        foreach ( $ms as $t ) {
            $tables[] = $prefix . $t;
        }

        return $tables;
    }

    /**
     * Format bytes into a human-readable string with appropriate unit.
     *
     * @param float $bytes Size in bytes.
     * @return string Formatted size (e.g. "1.5 MB", "320 KB", "0 B").
     */
    private static function format_bytes( $bytes ) {
        $bytes = max( 0, (float) $bytes );

        if ( $bytes === 0.0 ) {
            return '0 B';
        }

        if ( $bytes >= 1073741824 ) {
            return round( $bytes / 1073741824, 2 ) . ' GB';
        }

        if ( $bytes >= 1048576 ) {
            return round( $bytes / 1048576, 2 ) . ' MB';
        }

        if ( $bytes >= 1024 ) {
            return round( $bytes / 1024, 2 ) . ' KB';
        }

        return round( $bytes ) . ' B';
    }
}
