<?php
/**
 * Plugin Name: Guest Upload Vault
 * Plugin URI:  https://github.com/tmsbyr87/guest-upload-vault.git
 * Description: Securely collect guest photos and videos via protected link or QR code.
 * Version:     1.0.0
 * Author:      Thomas Beyer
 * Author URI:  https://thomas-beyer.com/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: guest-upload-vault
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GUEST_UPLOAD_VAULT_PLUGIN_VERSION', '1.0.0' );
define( 'GUEST_UPLOAD_VAULT_PLUGIN_FILE', __FILE__ );
define( 'GUEST_UPLOAD_VAULT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GUEST_UPLOAD_VAULT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GUEST_UPLOAD_VAULT_PLUGIN_DIR . 'includes/class-guest-upload-vault-plugin.php';

register_activation_hook( __FILE__, array( 'Guest_Upload_Vault_Plugin', 'activate' ) );

Guest_Upload_Vault_Plugin::instance();
