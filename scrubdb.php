<?php
/**
 * Plugin Name: ScrubDB
 * Plugin URI: https://developer.developer.developer/scrubdb
 * Description: Comprehensive WordPress database optimizer — scan, clean, repair, and debug. Supports dry-run for all operations.
 * Version: 1.2.0
 * Author: Ajith R N
 * Author URI: https://developer.developer.developer
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Text Domain: scrubdb
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCRUBDB_VERSION', '1.2.0' );
define( 'SCRUBDB_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCRUBDB_URL', plugin_dir_url( __FILE__ ) );
define( 'SCRUBDB_BASENAME', plugin_basename( __FILE__ ) );

// Core dispatcher.
require_once SCRUBDB_PATH . 'includes/class-scrubdb.php';

// Task modules.
require_once SCRUBDB_PATH . 'includes/tasks/class-orphaned-data.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-content-cleanup.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-options-cleanup.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-database-ops.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-woocommerce.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-debug.php';

ScrubDB::init();
