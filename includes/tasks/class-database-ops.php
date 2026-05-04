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
