<?php
/**
 * Task module: Orphaned metadata cleanup.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScrubDB_Task_Orphaned_Data {

    public function get_tasks() {
        return [
            'orphaned_postmeta',
            'orphaned_commentmeta',
            'orphaned_termmeta',
            'orphaned_usermeta',
            'orphaned_relationships',
        ];
    }

    public function task_orphaned_postmeta( $mode ) {
        global $wpdb;

        $join = "FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL";

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) $join" );
        $size  = $wpdb->get_var( "SELECT ROUND(SUM(LENGTH(meta_key) + LENGTH(meta_value)) / 1024 / 1024, 2) $join" ) ?: '0';

        $details = $wpdb->get_results(
            "SELECT meta_key, COUNT(*) AS cnt $join GROUP BY meta_key ORDER BY cnt DESC LIMIT 15"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts}) LIMIT %d"
            );
        }

        return compact( 'count', 'size', 'details', 'deleted', 'mode' );
    }

    public function task_orphaned_commentmeta( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->commentmeta} cm
             LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
             WHERE c.comment_ID IS NULL"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->comments}) LIMIT %d"
            );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_orphaned_termmeta( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->termmeta} tm
             LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id
             WHERE t.term_id IS NULL"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->termmeta} WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->terms}) LIMIT %d"
            );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_orphaned_usermeta( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} um
             LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID
             WHERE u.ID IS NULL"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->usermeta} WHERE user_id NOT IN (SELECT ID FROM {$wpdb->users}) LIMIT %d"
            );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_orphaned_relationships( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
             LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID
             WHERE p.ID IS NULL"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->term_relationships} WHERE object_id NOT IN (SELECT ID FROM {$wpdb->posts}) LIMIT %d"
            );
        }

        return compact( 'count', 'deleted', 'mode' );
    }
}
