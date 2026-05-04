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
             ORDER BY option_value ASC LIMIT 20",
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
             ORDER BY LENGTH(option_value) DESC LIMIT 20",
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

        $total = $wpdb->get_row(
            "SELECT COUNT(*) AS cnt,
                    ROUND(SUM(LENGTH(option_value)) / 1024 / 1024, 2) AS size_mb
             FROM {$wpdb->options} WHERE autoload = 'yes'"
        );

        $top_options = $wpdb->get_results(
            "SELECT option_name,
                    ROUND(LENGTH(option_value) / 1024, 2) AS size_kb
             FROM {$wpdb->options}
             WHERE autoload = 'yes'
             ORDER BY LENGTH(option_value) DESC LIMIT 20"
        );

        $by_prefix = $wpdb->get_results(
            "SELECT SUBSTRING_INDEX(option_name, '_', 2) AS prefix,
                    COUNT(*) AS cnt,
                    ROUND(SUM(LENGTH(option_value)) / 1024, 2) AS size_kb
             FROM {$wpdb->options}
             GROUP BY prefix
             ORDER BY size_kb DESC LIMIT 20"
        );

        return [
            'count'       => (int) $total->cnt,
            'size'        => $total->size_mb ?: '0',
            'top_options' => $top_options,
            'by_prefix'   => $by_prefix,
            'mode'        => 'scan',
        ];
    }
}
