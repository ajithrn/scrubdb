<?php
/**
 * Plugin Name: ScrubDB
 * Plugin URI: https://github.com/ajithrn/scrubdb
 * Description: WordPress database diagnostic and cleanup tool — inspect bloat, find orphaned data, debug problematic options, and clean up when ready.
 * Version: 1.4.0
 * Author: Ajith R N
 * Author URI: https://ajithrn.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Text Domain: scrubdb
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCRUBDB_VERSION', '1.4.0' );
define( 'SCRUBDB_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCRUBDB_URL', plugin_dir_url( __FILE__ ) );
define( 'SCRUBDB_BASENAME', plugin_basename( __FILE__ ) );

// Core dispatcher.
require_once SCRUBDB_PATH . 'includes/class-scrubdb.php';

// GitHub auto-updater.
require_once SCRUBDB_PATH . 'includes/class-github-updater.php';

// Task modules.
require_once SCRUBDB_PATH . 'includes/tasks/class-orphaned-data.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-content-cleanup.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-options-cleanup.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-database-ops.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-woocommerce.php';
require_once SCRUBDB_PATH . 'includes/tasks/class-debug.php';

// Initialize plugin.
ScrubDB::init();

// Initialize auto-updater.
new ScrubDB_GitHub_Updater( __FILE__, 'ajithrn/scrubdb' );
