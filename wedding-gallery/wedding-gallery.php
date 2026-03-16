<?php
/**
 * Plugin Name: Wedding Gallery
 * Plugin URI:  https://github.com/tmsbyr87/wedding-gallery
 * Description: Token-protected wedding guest photo/video uploads with encrypted-at-rest storage and admin media management.
 * Version:     1.0.0
 * Author:      Thomas Beyer
 * Author URI:  https://www.thomas-beyer.com
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wedding-gallery
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WG_PLUGIN_VERSION', '1.0.0' );
define( 'WG_PLUGIN_FILE', __FILE__ );
define( 'WG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WG_PLUGIN_DIR . 'includes/class-wg-plugin.php';

register_activation_hook( __FILE__, array( 'WG_Plugin', 'activate' ) );

WG_Plugin::instance();
