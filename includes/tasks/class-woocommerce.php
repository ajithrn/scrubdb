<?php
/**
 * Task module: WooCommerce-specific cleanup.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScrubDB_Task_WooCommerce {

    public function get_tasks() {
        return [
            'woo_sessions',
            'woo_transients',
        ];
    }

    public function task_woo_sessions( $mode ) {
        global $wpdb;

        $table = $wpdb->prefix . 'woocommerce_sessions';

        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table'" ) !== $table ) {
            return [
                'count'   => 0,
                'deleted' => 0,
                'mode'    => $mode,
                'note'    => 'WooCommerce sessions table not found.',
            ];
        }

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE session_expiry < UNIX_TIMESTAMP()" );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = (int) $wpdb->query( "DELETE FROM $table WHERE session_expiry < UNIX_TIMESTAMP()" );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_woo_transients( $mode ) {
        global $wpdb;

        $like1 = $wpdb->esc_like( '_transient_wc_' ) . '%';
        $like2 = $wpdb->esc_like( '_transient_timeout_wc_' ) . '%';

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->options}
             WHERE option_name LIKE %s OR option_name LIKE %s",
            $like1, $like2
        ) );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = (int) $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE %s OR option_name LIKE %s",
                $like1, $like2
            ) );
        }

        return compact( 'count', 'deleted', 'mode' );
    }
}
