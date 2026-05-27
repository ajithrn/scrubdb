<?php
/**
 * Task module: Orphaned metadata cleanup.
 * Returns sample items for preview in dry-run mode.
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

        // Sample items for preview.
        $items = $wpdb->get_results(
            "SELECT pm.meta_id, pm.post_id, pm.meta_key, LEFT(pm.meta_value, 60) AS meta_value
             $join ORDER BY pm.meta_id DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'Meta ID',    'key' => 'meta_id',    'mono' => true ],
            [ 'label' => 'Post ID',    'key' => 'post_id',    'mono' => true ],
            [ 'label' => 'Meta Key',   'key' => 'meta_key',   'mono' => true ],
            [ 'label' => 'Value (truncated)', 'key' => 'meta_value' ],
        ];

        // Summary by meta_key.
        $details = $wpdb->get_results(
            "SELECT meta_key, COUNT(*) AS cnt $join GROUP BY meta_key ORDER BY cnt DESC LIMIT 15"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts}) LIMIT %d"
            );
        }

        return compact( 'count', 'size', 'items', 'items_columns', 'details', 'deleted', 'mode' );
    }

    public function task_orphaned_commentmeta( $mode ) {
        global $wpdb;

        $join = "FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL";

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) $join" );

        $items = $wpdb->get_results(
            "SELECT cm.meta_id, cm.comment_id, cm.meta_key, LEFT(cm.meta_value, 60) AS meta_value
             $join ORDER BY cm.meta_id DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'Meta ID',    'key' => 'meta_id',    'mono' => true ],
            [ 'label' => 'Comment ID', 'key' => 'comment_id', 'mono' => true ],
            [ 'label' => 'Meta Key',   'key' => 'meta_key',   'mono' => true ],
            [ 'label' => 'Value (truncated)', 'key' => 'meta_value' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_ID FROM {$wpdb->comments}) LIMIT %d"
            );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_orphaned_termmeta( $mode ) {
        global $wpdb;

        $join = "FROM {$wpdb->termmeta} tm LEFT JOIN {$wpdb->terms} t ON tm.term_id = t.term_id WHERE t.term_id IS NULL";

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) $join" );

        $items = $wpdb->get_results(
            "SELECT tm.meta_id, tm.term_id, tm.meta_key, LEFT(tm.meta_value, 60) AS meta_value
             $join ORDER BY tm.meta_id DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'Meta ID', 'key' => 'meta_id', 'mono' => true ],
            [ 'label' => 'Term ID', 'key' => 'term_id', 'mono' => true ],
            [ 'label' => 'Meta Key', 'key' => 'meta_key', 'mono' => true ],
            [ 'label' => 'Value (truncated)', 'key' => 'meta_value' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->termmeta} WHERE term_id NOT IN (SELECT term_id FROM {$wpdb->terms}) LIMIT %d"
            );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_orphaned_usermeta( $mode ) {
        global $wpdb;

        $join = "FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID WHERE u.ID IS NULL";

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) $join" );

        $items = $wpdb->get_results(
            "SELECT um.umeta_id, um.user_id, um.meta_key, LEFT(um.meta_value, 60) AS meta_value
             $join ORDER BY um.umeta_id DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'Meta ID', 'key' => 'umeta_id', 'mono' => true ],
            [ 'label' => 'User ID', 'key' => 'user_id',  'mono' => true ],
            [ 'label' => 'Meta Key', 'key' => 'meta_key', 'mono' => true ],
            [ 'label' => 'Value (truncated)', 'key' => 'meta_value' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->usermeta} WHERE user_id NOT IN (SELECT ID FROM {$wpdb->users}) LIMIT %d"
            );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_orphaned_relationships( $mode ) {
        global $wpdb;

        $join = "FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID WHERE p.ID IS NULL";

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) $join" );

        $items = $wpdb->get_results(
            "SELECT tr.object_id, tr.term_taxonomy_id, tt.taxonomy
             $join
             LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             ORDER BY tr.object_id DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'Object ID',    'key' => 'object_id',        'mono' => true ],
            [ 'label' => 'Taxonomy ID',  'key' => 'term_taxonomy_id', 'mono' => true ],
            [ 'label' => 'Taxonomy',     'key' => 'taxonomy' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = ScrubDB::batch_delete(
                "DELETE FROM {$wpdb->term_relationships} WHERE object_id NOT IN (SELECT ID FROM {$wpdb->posts}) LIMIT %d"
            );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }
}
