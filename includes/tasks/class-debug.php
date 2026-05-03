<?php
/**
 * Task module: Debug & maintenance — cron cleanup, debug log.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScrubDB_Task_Debug {

    public function get_tasks() {
        return [
            'cron_cleanup',
            'debug_log',
        ];
    }

    public function task_cron_cleanup( $mode ) {
        $crons    = _get_cron_array();
        $orphaned = [];

        if ( is_array( $crons ) ) {
            foreach ( $crons as $timestamp => $hooks ) {
                foreach ( $hooks as $hook => $events ) {
                    if ( $this->is_core_cron( $hook ) ) {
                        continue;
                    }

                    if ( ! has_action( $hook ) ) {
                        $orphaned[] = [
                            'hook'     => $hook,
                            'next_run' => wp_date( 'Y-m-d H:i:s', $timestamp ),
                        ];
                    }
                }
            }
        }

        // Deduplicate by hook name.
        $seen   = [];
        $unique = [];
        foreach ( $orphaned as $item ) {
            if ( ! isset( $seen[ $item['hook'] ] ) ) {
                $seen[ $item['hook'] ] = true;
                $unique[] = $item;
            }
        }

        $deleted = 0;
        if ( 'clean' === $mode && ! empty( $unique ) ) {
            foreach ( $unique as $item ) {
                wp_unschedule_hook( $item['hook'] );
                $deleted++;
            }
        }

        return [
            'count'   => count( $unique ),
            'details' => array_slice( $unique, 0, 30 ),
            'deleted' => $deleted,
            'mode'    => $mode,
        ];
    }

    public function task_debug_log( $mode ) {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        $exists   = file_exists( $log_file );
        $size     = $exists ? round( filesize( $log_file ) / 1024 / 1024, 2 ) : 0;

        $tail = '';
        if ( $exists && $size > 0 ) {
            $lines = array_slice( file( $log_file ), -50 );
            $tail  = implode( '', $lines );
        }

        $cleared = 0;
        if ( 'clean' === $mode && $exists ) {
            file_put_contents( $log_file, '' );
            $cleared = 1;
        }

        return [
            'exists'            => $exists,
            'size_mb'           => $size,
            'tail'              => esc_html( $tail ),
            'cleared'           => $cleared,
            'debug_enabled'     => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'debug_log_enabled' => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            'mode'              => $mode,
        ];
    }

    /**
     * Check if a cron hook is a WP core hook (should not be removed).
     */
    private function is_core_cron( $hook ) {
        $core_hooks = [
            'wp_version_check',
            'wp_update_plugins',
            'wp_update_themes',
            'wp_scheduled_delete',
            'wp_scheduled_auto_draft_delete',
            'wp_privacy_delete_old_export_files',
            'wp_cron_delete_expired_personal_data_export_files',
            'delete_expired_transients',
            'wp_site_health_scheduled_check',
            'recovery_mode_clean_expired_keys',
            'wp_https_detection',
        ];

        return in_array( $hook, $core_hooks, true );
    }
}
