<?php
/**
 * Task module: wp_options table — transients, autoload audit.
 * Returns sample items for preview in dry-run mode.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScrubDB_Task_Options_Cleanup {

    public function get_tasks() {
        return [
            'expired_transients',
            'all_transients',
            'autoload_audit',
            'options_debug',
            'toggle_autoload',
            'delete_option',
        ];
    }

    public function task_expired_transients( $mode ) {
        global $wpdb;

        $now   = time();
        $like  = $wpdb->esc_like( '_transient_timeout_' ) . '%';
        $slike = $wpdb->esc_like( '_site_transient_timeout_' ) . '%';

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
            $like, $now
        ) );

        // Sample expired transients.
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT option_name,
                    FROM_UNIXTIME(option_value) AS expired_at,
                    ROUND(LENGTH(option_value) / 1024, 2) AS size_kb
             FROM {$wpdb->options}
             WHERE option_name LIKE %s AND option_value < %d
             ORDER BY option_value ASC LIMIT 100",
            $like, $now
        ) );

        // Strip _transient_timeout_ prefix for readability.
        foreach ( $items as &$item ) {
            $item->option_name = str_replace( '_transient_timeout_', '', $item->option_name );
        }
        unset( $item );

        $items_columns = [
            [ 'label' => 'Transient Name', 'key' => 'option_name', 'mono' => true ],
            [ 'label' => 'Expired At',     'key' => 'expired_at' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query( $wpdb->prepare(
                "DELETE a, b FROM {$wpdb->options} a
                 INNER JOIN {$wpdb->options} b
                 ON b.option_name = REPLACE(a.option_name, '_transient_timeout_', '_transient_')
                 WHERE a.option_name LIKE %s AND a.option_value < %d",
                $like, $now
            ) );

            $wpdb->query( $wpdb->prepare(
                "DELETE a, b FROM {$wpdb->options} a
                 INNER JOIN {$wpdb->options} b
                 ON b.option_name = REPLACE(a.option_name, '_site_transient_timeout_', '_site_transient_')
                 WHERE a.option_name LIKE %s AND a.option_value < %d",
                $slike, $now
            ) );

            $deleted = $count;
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_all_transients( $mode ) {
        global $wpdb;

        $like  = $wpdb->esc_like( '_transient_' ) . '%';
        $slike = $wpdb->esc_like( '_site_transient_' ) . '%';

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like, $slike
        ) );

        $size = $wpdb->get_var( $wpdb->prepare(
            "SELECT ROUND(SUM(LENGTH(option_value)) / 1024 / 1024, 2)
             FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $like, $slike
        ) ) ?: '0';

        // Sample transients.
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT option_name,
                    ROUND(LENGTH(option_value) / 1024, 2) AS size_kb,
                    autoload
             FROM {$wpdb->options}
             WHERE option_name LIKE %s OR option_name LIKE %s
             ORDER BY LENGTH(option_value) DESC LIMIT 100",
            $like, $slike
        ) );

        $items_columns = [
            [ 'label' => 'Option Name', 'key' => 'option_name', 'mono' => true ],
            [ 'label' => 'Size',        'key' => 'size_kb',     'suffix' => 'KB' ],
            [ 'label' => 'Autoload',    'key' => 'autoload' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = (int) $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $like, $slike
            ) );
        }

        return compact( 'count', 'size', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_autoload_audit( $mode ) {
        global $wpdb;

        $al_values = self::autoload_values();

        $total = $wpdb->get_row(
            "SELECT COUNT(*) AS cnt,
                    ROUND(SUM(LENGTH(option_value)) / 1024 / 1024, 2) AS size_mb
             FROM {$wpdb->options} WHERE autoload IN ($al_values)"
        );

        $top_options = $wpdb->get_results(
            "SELECT option_name,
                    ROUND(LENGTH(option_value) / 1024, 2) AS size_kb
             FROM {$wpdb->options}
             WHERE autoload IN ($al_values)
             ORDER BY LENGTH(option_value) DESC LIMIT 100"
        );

        $by_prefix = $wpdb->get_results(
            "SELECT SUBSTRING_INDEX(option_name, '_', 2) AS prefix,
                    COUNT(*) AS cnt,
                    ROUND(SUM(LENGTH(option_value)) / 1024, 2) AS size_kb
             FROM {$wpdb->options}
             WHERE autoload IN ($al_values)
             GROUP BY prefix
             ORDER BY size_kb DESC LIMIT 100"
        );

        return [
            'count'       => (int) $total->cnt,
            'size'        => $total->size_mb ?: '0',
            'top_options' => $top_options,
            'by_prefix'   => $by_prefix,
            'mode'        => 'scan',
        ];
    }

    /**
     * Full wp_options table debug — shows everything eating space.
     */
    public function task_options_debug( $mode ) {
        global $wpdb;

        // ── Overall table stats ──
        $total = $wpdb->get_row(
            "SELECT COUNT(*) AS total_rows,
                    ROUND(SUM(LENGTH(option_name) + LENGTH(option_value)) / 1024 / 1024, 2) AS total_mb
             FROM {$wpdb->options}"
        );

        // ── Autoloaded vs not ──
        $al_values = self::autoload_values();

        $autoload_yes = $wpdb->get_row(
            "SELECT COUNT(*) AS cnt,
                    ROUND(SUM(LENGTH(option_value)) / 1024 / 1024, 2) AS size_mb
             FROM {$wpdb->options} WHERE autoload IN ($al_values)"
        );

        $autoload_no = $wpdb->get_row(
            "SELECT COUNT(*) AS cnt,
                    ROUND(SUM(LENGTH(option_value)) / 1024 / 1024, 2) AS size_mb
             FROM {$wpdb->options} WHERE autoload NOT IN ($al_values)"
        );

        // ── Transient vs non-transient ──
        $tlike  = $wpdb->esc_like( '_transient_' ) . '%';
        $stlike = $wpdb->esc_like( '_site_transient_' ) . '%';

        $transient_stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT COUNT(*) AS cnt,
                    ROUND(SUM(LENGTH(option_value)) / 1024 / 1024, 2) AS size_mb
             FROM {$wpdb->options}
             WHERE option_name LIKE %s OR option_name LIKE %s",
            $tlike, $stlike
        ) );

        // ── Top 50 largest options (any type) ──
        $top_options = $wpdb->get_results(
            "SELECT option_id, option_name, autoload,
                    ROUND(LENGTH(option_value) / 1024, 2) AS size_kb,
                    LEFT(option_value, 100) AS value_preview
             FROM {$wpdb->options}
             ORDER BY LENGTH(option_value) DESC LIMIT 50"
        );

        // Classify each option.
        foreach ( $top_options as &$opt ) {
            $name = $opt->option_name;

            if ( strpos( $name, '_transient_' ) === 0 || strpos( $name, '_site_transient_' ) === 0 ) {
                $opt->type = 'Transient';
            } elseif ( $this->is_core_option( $name ) ) {
                $opt->type = 'Core';
            } elseif ( strpos( $name, 'woocommerce' ) !== false || strpos( $name, '_wc_' ) !== false ) {
                $opt->type = 'WooCommerce';
            } else {
                $opt->type = 'Plugin/Theme';
            }

            // Truncate for display.
            $opt->value_preview = mb_strlen( $opt->value_preview ) >= 100
                ? $opt->value_preview . '…'
                : $opt->value_preview;
        }
        unset( $opt );

        // ── By plugin prefix (full table, not just autoloaded) ──
        $by_prefix = $wpdb->get_results(
            "SELECT SUBSTRING_INDEX(option_name, '_', 2) AS prefix,
                    COUNT(*) AS cnt,
                    ROUND(SUM(LENGTH(option_value)) / 1024, 2) AS size_kb,
                    SUM(CASE WHEN autoload IN ($al_values) THEN 1 ELSE 0 END) AS autoloaded
             FROM {$wpdb->options}
             GROUP BY prefix
             ORDER BY size_kb DESC LIMIT 30"
        );

        return [
            'total_rows'      => (int) $total->total_rows,
            'total_mb'        => $total->total_mb ?: '0',
            'autoload_yes'    => [
                'count'   => (int) $autoload_yes->cnt,
                'size_mb' => $autoload_yes->size_mb ?: '0',
            ],
            'autoload_no'     => [
                'count'   => (int) $autoload_no->cnt,
                'size_mb' => $autoload_no->size_mb ?: '0',
            ],
            'transients'      => [
                'count'   => (int) $transient_stats->cnt,
                'size_mb' => $transient_stats->size_mb ?: '0',
            ],
            'top_options'     => $top_options,
            'by_prefix'       => $by_prefix,
            'mode'            => 'scan',
        ];
    }

    /**
     * Toggle autoload on/off for a specific option.
     * Reads option_name from $_POST.
     */
    public function task_toggle_autoload( $mode ) {
        global $wpdb;

        $option_name = sanitize_text_field( $_POST['option_name'] ?? '' );
        if ( empty( $option_name ) ) {
            return [ 'error' => 'No option name provided.', 'mode' => $mode ];
        }

        // Block toggling critical core options.
        if ( $this->is_protected_option( $option_name ) ) {
            return [ 'error' => 'Cannot modify core option: ' . $option_name, 'mode' => $mode ];
        }

        $current = $wpdb->get_var( $wpdb->prepare(
            "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $option_name
        ) );

        if ( null === $current ) {
            return [ 'error' => 'Option not found.', 'mode' => $mode ];
        }

        // Determine correct on/off values for this WP version.
        $al        = self::autoload_on_off();
        $is_on     = in_array( $current, explode( ',', str_replace( "'", '', self::autoload_values() ) ), true );
        $new_value = $is_on ? $al['off'] : $al['on'];

        $wpdb->update(
            $wpdb->options,
            [ 'autoload' => $new_value ],
            [ 'option_name' => $option_name ]
        );

        return [
            'option_name' => $option_name,
            'old_value'   => $current,
            'new_value'   => $new_value,
            'message'     => 'Autoload changed from "' . $current . '" to "' . $new_value . '".',
            'mode'        => $mode,
        ];
    }

    /**
     * Delete a specific option by ID.
     * Reads option_id and option_name from $_POST.
     */
    public function task_delete_option( $mode ) {
        global $wpdb;

        $option_id   = absint( $_POST['option_id'] ?? 0 );
        $option_name = sanitize_text_field( $_POST['option_name'] ?? '' );

        if ( ! $option_id && empty( $option_name ) ) {
            return [ 'error' => 'No option specified.', 'mode' => $mode ];
        }

        // Block deleting critical core options.
        if ( ! empty( $option_name ) && $this->is_protected_option( $option_name ) ) {
            return [ 'error' => 'Cannot delete core option: ' . $option_name, 'mode' => $mode ];
        }

        if ( $option_id ) {
            // Verify the name matches the ID for safety.
            $row = $wpdb->get_row( $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_id = %d", $option_id
            ) );

            if ( ! $row ) {
                return [ 'error' => 'Option not found.', 'mode' => $mode ];
            }

            if ( $this->is_protected_option( $row->option_name ) ) {
                return [ 'error' => 'Cannot delete core option: ' . $row->option_name, 'mode' => $mode ];
            }

            $wpdb->delete( $wpdb->options, [ 'option_id' => $option_id ] );
            $deleted_name = $row->option_name;
        } else {
            $wpdb->delete( $wpdb->options, [ 'option_name' => $option_name ] );
            $deleted_name = $option_name;
        }

        return [
            'deleted'     => 1,
            'option_name' => $deleted_name,
            'message'     => 'Option "' . $deleted_name . '" has been deleted.',
            'mode'        => $mode,
        ];
    }

    /**
     * Canonical list of protected core options that should never be modified or deleted.
     * This is the single source of truth — the JS side mirrors this list.
     */
    private static $protected_options = [
        'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email',
        'users_can_register', 'start_of_week', 'date_format', 'time_format',
        'active_plugins', 'template', 'stylesheet', 'db_version',
        'initial_db_version', 'wp_user_roles', 'permalink_structure',
        'current_theme', 'WPLANG', 'blog_charset', 'gmt_offset',
        'timezone_string', 'default_role', 'cron', 'rewrite_rules',
    ];

    /**
     * SQL-safe string of autoload values that mean "yes, autoload this".
     * WordPress 6.6+ uses 'on'/'off'/'auto-on'/'auto-off'/'auto' instead of 'yes'/'no'.
     */
    private static function autoload_values() {
        return "'yes','on','auto-on','auto'";
    }

    /**
     * Determine the correct autoload "on" and "off" values for this WP version.
     */
    private static function autoload_on_off() {
        global $wpdb;
        // Check what values actually exist in the database.
        $has_yes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload = 'yes' LIMIT 1" );
        if ( $has_yes > 0 ) {
            return [ 'on' => 'yes', 'off' => 'no' ];
        }
        return [ 'on' => 'on', 'off' => 'off' ];
    }

    /**
     * Check if an option is a protected core option that should never be modified.
     */
    private function is_protected_option( $name ) {
        return in_array( $name, self::$protected_options, true );
    }

    /**
     * Check if an option is a known WP core option (for classification in X-Ray).
     * Broader than protected — includes options that are core but not necessarily dangerous to toggle.
     */
    private function is_core_option( $name ) {
        $core = array_merge( self::$protected_options, [
            'widget_block', 'sidebars_widgets', 'auto_core_update_notified',
            'wp_user_roles', 'default_comment_status', 'comment_moderation',
        ] );

        // Also match theme_mods_* pattern.
        if ( strpos( $name, 'theme_mods_' ) === 0 ) {
            return true;
        }

        return in_array( $name, $core, true );
    }
}
