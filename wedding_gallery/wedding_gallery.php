<?php
/**
 * Plugin Name: Wedding Gallery
 * Plugin URI:  https://example.com
 * Description: Collect wedding guest photos and videos through a protected frontend upload page.
 * Version:     0.1.0
 * Author:      Wedding Gallery Team
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wedding-gallery
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WG_PLUGIN_VERSION', '0.1.0' );
define( 'WG_PLUGIN_FILE', __FILE__ );
define( 'WG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WG_PLUGIN_DIR . 'includes/class-wg-plugin.php';

register_activation_hook( __FILE__, array( 'WG_Plugin', 'activate' ) );

WG_Plugin::instance();
