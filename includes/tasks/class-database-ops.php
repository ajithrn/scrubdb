<?php
/**
 * Task module: Database operations — optimize, repair, info.
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

        $tables = $wpdb->get_results(
            "SELECT TABLE_NAME AS name,
                    ENGINE AS engine,
                    TABLE_ROWS AS rows_count,
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS total_mb,
                    ROUND(DATA_FREE / 1024 / 1024, 2) AS overhead_mb
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE '{$wpdb->prefix}%'
             ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC"
        );

        $totals = $wpdb->get_row(
            "SELECT ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS size_mb,
                    ROUND(SUM(DATA_FREE) / 1024 / 1024, 2) AS overhead_mb,
                    COUNT(*) AS table_count
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE '{$wpdb->prefix}%'"
        );

        $server = $wpdb->get_var( 'SELECT VERSION()' );

        return [
            'tables'         => $tables,
            'total_size'     => $totals->size_mb,
            'total_overhead' => $totals->overhead_mb,
            'table_count'    => $totals->table_count,
            'server'         => $server,
            'db_name'        => DB_NAME,
            'mode'           => 'scan',
        ];
    }
}
