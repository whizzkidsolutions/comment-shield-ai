<?php
/**
 * Plugin Name: Comment Shield AI – Perspective Spam Guard
 * Description: Automate WordPress comment moderation with the Google Perspective API. Mark comments as approved or spam based on a toxicity score.
 * Version: 1.0.0
 * Author: WhizzkidSolutions
 * Text Domain: comment-shield-ai
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PCG_VERSION', '1.0.0' );
define( 'PCG_PLUGIN_FILE', __FILE__ );
define( 'PCG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PCG_TEXTDOMAIN', 'comment-shield-ai' );

require_once PCG_PLUGIN_DIR . 'includes/class-pcg-plugin.php';
require_once PCG_PLUGIN_DIR . 'includes/class-pcg-admin.php';
require_once PCG_PLUGIN_DIR . 'includes/class-pcg-cron.php';
require_once PCG_PLUGIN_DIR . 'includes/class-pcg-perspective-client.php';

/**
 * Load plugin text domain.
 */
function pcg_load_textdomain(): void
{
    load_plugin_textdomain(
        PCG_TEXTDOMAIN,
        false,
        dirname( PCG_PLUGIN_BASENAME ) . '/languages'
    );
}
add_action( 'plugins_loaded', 'pcg_load_textdomain' );

/**
 * Bootstrap plugin instance.
 */
function pcg_plugins_loaded(): void
{
    PCG_Plugin::instance();
}
add_action( 'plugins_loaded', 'pcg_plugins_loaded' );

register_activation_hook( __FILE__, array( 'PCG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PCG_Plugin', 'deactivate' ) );
