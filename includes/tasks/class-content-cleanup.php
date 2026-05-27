<?php
/**
 * Task module: Content cleanup with item previews.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScrubDB_Task_Content_Cleanup {

    public function get_tasks() {
        return [
            'post_revisions',
            'auto_drafts',
            'trashed_posts',
            'spam_comments',
            'trashed_comments',
            'oembed_cache',
            'pingbacks',
            'duplicate_postmeta',
        ];
    }

    public function task_post_revisions( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

        $items = $wpdb->get_results(
            "SELECT p.ID, p.post_title, p.post_date, parent.post_title AS parent_title
             FROM {$wpdb->posts} p
             LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
             WHERE p.post_type = 'revision'
             ORDER BY p.post_date DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'ID',     'key' => 'ID',           'mono' => true ],
            [ 'label' => 'Title',  'key' => 'post_title' ],
            [ 'label' => 'Parent', 'key' => 'parent_title' ],
            [ 'label' => 'Date',   'key' => 'post_date' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_type = 'revision'" );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_auto_drafts( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );

        $items = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_date
             FROM {$wpdb->posts} WHERE post_status = 'auto-draft'
             ORDER BY post_date DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'ID',        'key' => 'ID',         'mono' => true ],
            [ 'label' => 'Title',     'key' => 'post_title' ],
            [ 'label' => 'Type',      'key' => 'post_type' ],
            [ 'label' => 'Date',      'key' => 'post_date' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_status = 'auto-draft'" );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_trashed_posts( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );

        $items = $wpdb->get_results(
            "SELECT ID, post_title, post_type, post_date
             FROM {$wpdb->posts} WHERE post_status = 'trash'
             ORDER BY post_date DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'ID',    'key' => 'ID',         'mono' => true ],
            [ 'label' => 'Title', 'key' => 'post_title' ],
            [ 'label' => 'Type',  'key' => 'post_type' ],
            [ 'label' => 'Date',  'key' => 'post_date' ],
        ];

        $details = $wpdb->get_results(
            "SELECT post_type, COUNT(*) AS cnt FROM {$wpdb->posts}
             WHERE post_status = 'trash' GROUP BY post_type ORDER BY cnt DESC LIMIT 10"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query( "DELETE pm FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.post_status = 'trash'" );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );
        }

        return compact( 'count', 'items', 'items_columns', 'details', 'deleted', 'mode' );
    }

    public function task_spam_comments( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );

        $items = $wpdb->get_results(
            "SELECT comment_ID, comment_author, LEFT(comment_content, 80) AS comment_content, comment_date
             FROM {$wpdb->comments} WHERE comment_approved = 'spam'
             ORDER BY comment_date DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'ID',      'key' => 'comment_ID',      'mono' => true ],
            [ 'label' => 'Author',  'key' => 'comment_author' ],
            [ 'label' => 'Content', 'key' => 'comment_content' ],
            [ 'label' => 'Date',    'key' => 'comment_date' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_approved = 'spam'" );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_trashed_comments( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

        $items = $wpdb->get_results(
            "SELECT comment_ID, comment_author, LEFT(comment_content, 80) AS comment_content, comment_date
             FROM {$wpdb->comments} WHERE comment_approved = 'trash'
             ORDER BY comment_date DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'ID',      'key' => 'comment_ID',      'mono' => true ],
            [ 'label' => 'Author',  'key' => 'comment_author' ],
            [ 'label' => 'Content', 'key' => 'comment_content' ],
            [ 'label' => 'Date',    'key' => 'comment_date' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_approved = 'trash'" );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_oembed_cache( $mode ) {
        global $wpdb;

        $like  = $wpdb->esc_like( '_oembed_' ) . '%';
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like
        ) );

        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT meta_id, post_id, meta_key, LEFT(meta_value, 80) AS meta_value
             FROM {$wpdb->postmeta} WHERE meta_key LIKE %s
             ORDER BY meta_id DESC LIMIT 100", $like
        ) );

        $items_columns = [
            [ 'label' => 'Meta ID', 'key' => 'meta_id', 'mono' => true ],
            [ 'label' => 'Post ID', 'key' => 'post_id', 'mono' => true ],
            [ 'label' => 'Meta Key', 'key' => 'meta_key', 'mono' => true ],
            [ 'label' => 'Value (truncated)', 'key' => 'meta_value' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = (int) $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like
            ) );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_pingbacks( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type IN ('pingback', 'trackback')"
        );

        $items = $wpdb->get_results(
            "SELECT comment_ID, comment_type, comment_author, comment_author_url, comment_date
             FROM {$wpdb->comments} WHERE comment_type IN ('pingback', 'trackback')
             ORDER BY comment_date DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'ID',   'key' => 'comment_ID',         'mono' => true ],
            [ 'label' => 'Type', 'key' => 'comment_type' ],
            [ 'label' => 'From', 'key' => 'comment_author' ],
            [ 'label' => 'URL',  'key' => 'comment_author_url', 'mono' => true ],
            [ 'label' => 'Date', 'key' => 'comment_date' ],
        ];

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query( "DELETE cm FROM {$wpdb->commentmeta} cm INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_type IN ('pingback', 'trackback')" );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_type IN ('pingback', 'trackback')" );
        }

        return compact( 'count', 'items', 'items_columns', 'deleted', 'mode' );
    }

    public function task_duplicate_postmeta( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_id NOT IN (
                SELECT * FROM (
                    SELECT MIN(meta_id) FROM {$wpdb->postmeta}
                    GROUP BY post_id, meta_key, meta_value
                ) AS keep_ids
            )"
        );

        $items = $wpdb->get_results(
            "SELECT pm.meta_id, pm.post_id, pm.meta_key, LEFT(pm.meta_value, 60) AS meta_value
             FROM {$wpdb->postmeta} pm
             WHERE pm.meta_id NOT IN (
                 SELECT * FROM (
                     SELECT MIN(meta_id) FROM {$wpdb->postmeta}
                     GROUP BY post_id, meta_key, meta_value
                 ) AS keep_ids
             )
             ORDER BY pm.meta_id DESC LIMIT 100"
        );

        $items_columns = [
            [ 'label' => 'Meta ID', 'key' => 'meta_id', 'mono' => true ],
            [ 'label' => 'Post ID', 'key' => 'post_id', 'mono' => true ],
            [ 'label' => 'Meta Key', 'key' => 'meta_key', 'mono' => true ],
            [ 'label' => 'Value (truncated)', 'key' => 'meta_value' ],
        ];

        $details = $wpdb->get_results(
            "SELECT meta_key,
                    COUNT(*) - COUNT(DISTINCT CONCAT(post_id, '|', meta_value)) AS dup_count
             FROM {$wpdb->postmeta}
             GROUP BY meta_key HAVING dup_count > 0
             ORDER BY dup_count DESC LIMIT 15"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = (int) $wpdb->query(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_id NOT IN (
                    SELECT * FROM (
                        SELECT MIN(meta_id) FROM {$wpdb->postmeta}
                        GROUP BY post_id, meta_key, meta_value
                    ) AS keep_ids
                )"
            );
        }

        return compact( 'count', 'items', 'items_columns', 'details', 'deleted', 'mode' );
    }
}
