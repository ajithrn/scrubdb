<?php
/**
 * Core ScrubDB class — admin menu, AJAX dispatcher, asset loading.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScrubDB {

    private static $instance = null;

    /** Batch size for chunked deletes. */
    const BATCH = 1000;

    /** Task module registry: task_name => instance. */
    private $tasks = [];

    public static function init() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', [ $this, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_scrubdb', [ $this, 'ajax_handler' ] );

        // Plugin listing links.
        add_filter( 'plugin_action_links_' . SCRUBDB_BASENAME, [ $this, 'action_links' ] );
        add_filter( 'plugin_row_meta', [ $this, 'row_meta' ], 10, 2 );

        $this->register_tasks();
    }

    /**
     * Add "Settings" link on the Plugins listing page.
     */
    public function action_links( $links ) {
        $settings_link = '<a href="' . admin_url( 'tools.php?page=scrubdb' ) . '">' . __( 'Settings', 'scrubdb' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Add extra meta links on the Plugins listing page.
     */
    public function row_meta( $links, $file ) {
        if ( SCRUBDB_BASENAME !== $file ) {
            return $links;
        }

        $links[] = '<a href="' . admin_url( 'tools.php?page=scrubdb' ) . '">' . __( 'Documentation', 'scrubdb' ) . '</a>';

        return $links;
    }

    /**
     * Register all task modules.
     */
    private function register_tasks() {
        $modules = [
            'ScrubDB_Task_Orphaned_Data',
            'ScrubDB_Task_Content_Cleanup',
            'ScrubDB_Task_Options_Cleanup',
            'ScrubDB_Task_Database_Ops',
            'ScrubDB_Task_WooCommerce',
            'ScrubDB_Task_Debug',
        ];

        foreach ( $modules as $class ) {
            if ( class_exists( $class ) && method_exists( $class, 'get_tasks' ) ) {
                $instance = new $class();
                foreach ( $instance->get_tasks() as $task_name ) {
                    $this->tasks[ $task_name ] = $instance;
                }
            }
        }
    }

    public function add_menu() {
        add_management_page(
            __( 'ScrubDB', 'scrubdb' ),
            __( 'ScrubDB', 'scrubdb' ),
            'manage_options',
            'scrubdb',
            [ $this, 'render_page' ]
        );
    }

    public function enqueue_assets( $hook ) {
        if ( 'tools_page_scrubdb' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'scrubdb-admin',
            SCRUBDB_URL . 'admin/css/admin.css',
            [],
            SCRUBDB_VERSION
        );

        wp_enqueue_script(
            'scrubdb-admin',
            SCRUBDB_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            SCRUBDB_VERSION,
            true
        );

        wp_localize_script( 'scrubdb-admin', 'scrubdb', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'scrubdb_nonce' ),
        ] );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized access.', 'scrubdb' ) );
        }
        include SCRUBDB_PATH . 'admin/admin-page.php';
    }

    public function ajax_handler() {
        check_ajax_referer( 'scrubdb_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ] );
        }

        $task = sanitize_text_field( $_POST['task'] ?? '' );
        $mode = sanitize_text_field( $_POST['mode'] ?? 'scan' );

        if ( ! isset( $this->tasks[ $task ] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid task: ' . $task ] );
        }

        $instance = $this->tasks[ $task ];
        $method   = 'task_' . $task;

        if ( ! method_exists( $instance, $method ) ) {
            wp_send_json_error( [ 'message' => 'Task method not found.' ] );
        }

        $result         = $instance->$method( $mode );
        $result['task'] = $task;
        wp_send_json_success( $result );
    }

    /**
     * Batch delete rows using a LIMIT query to avoid timeouts.
     *
     * @param string $query SQL DELETE with LIMIT %d placeholder.
     * @return int Total rows deleted.
     */
    public static function batch_delete( $query ) {
        global $wpdb;
        $total = 0;
        do {
            $affected = (int) $wpdb->query( sprintf( $query, self::BATCH ) );
            $total   += $affected;
        } while ( $affected >= self::BATCH );
        return $total;
    }
}
