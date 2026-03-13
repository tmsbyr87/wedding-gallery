<?php
/**
 * Uninstall Wedding Gallery plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wg_settings' );
