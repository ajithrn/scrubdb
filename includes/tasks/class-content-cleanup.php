<?php
/**
 * Task module: Content cleanup — revisions, auto-drafts, trash, spam, oEmbed, pingbacks, duplicate meta.
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

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query(
                "DELETE pm FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_type = 'revision'"
            );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'" );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_auto_drafts( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query(
                "DELETE pm FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_status = 'auto-draft'"
            );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'" );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_trashed_posts( $mode ) {
        global $wpdb;

        $count   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'" );
        $details = $wpdb->get_results(
            "SELECT post_type, COUNT(*) AS cnt FROM {$wpdb->posts}
             WHERE post_status = 'trash' GROUP BY post_type ORDER BY cnt DESC LIMIT 10"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query(
                "DELETE pm FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                 WHERE p.post_status = 'trash'"
            );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'" );
        }

        return compact( 'count', 'details', 'deleted', 'mode' );
    }

    public function task_spam_comments( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query(
                "DELETE cm FROM {$wpdb->commentmeta} cm
                 INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
                 WHERE c.comment_approved = 'spam'"
            );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'" );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_trashed_comments( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query(
                "DELETE cm FROM {$wpdb->commentmeta} cm
                 INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
                 WHERE c.comment_approved = 'trash'"
            );
            $deleted = (int) $wpdb->query( "DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'" );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_oembed_cache( $mode ) {
        global $wpdb;

        $like  = $wpdb->esc_like( '_oembed_' ) . '%';
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like
        ) );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $deleted = (int) $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like
            ) );
        }

        return compact( 'count', 'deleted', 'mode' );
    }

    public function task_pingbacks( $mode ) {
        global $wpdb;

        $count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type IN ('pingback', 'trackback')"
        );

        $deleted = 0;
        if ( 'clean' === $mode && $count > 0 ) {
            $wpdb->query(
                "DELETE cm FROM {$wpdb->commentmeta} cm
                 INNER JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID
                 WHERE c.comment_type IN ('pingback', 'trackback')"
            );
            $deleted = (int) $wpdb->query(
                "DELETE FROM {$wpdb->comments} WHERE comment_type IN ('pingback', 'trackback')"
            );
        }

        return compact( 'count', 'deleted', 'mode' );
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

        return compact( 'count', 'details', 'deleted', 'mode' );
    }
}
