<?php
/**
 * Uninstall Guest Upload Vault plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$guest_upload_vault_settings = get_option( 'guest_upload_vault_settings', null );
if ( null === $guest_upload_vault_settings ) {
	$guest_upload_vault_settings = get_option( 'guv_settings', array() );
}
$guest_upload_vault_cleanup  = is_array( $guest_upload_vault_settings ) && ! empty( $guest_upload_vault_settings['cleanup_on_uninstall'] );

if ( $guest_upload_vault_cleanup ) {
	$guest_upload_vault_dir = wp_upload_dir();
	if ( isset( $guest_upload_vault_dir['basedir'] ) ) {
		$guest_upload_vault_base_dir    = (string) $guest_upload_vault_dir['basedir'];
		$guest_upload_vault_target_dir  = trailingslashit( $guest_upload_vault_base_dir ) . 'guest-upload-vault';
		$guest_upload_vault_real_base   = realpath( $guest_upload_vault_base_dir );
		$guest_upload_vault_real_target = realpath( $guest_upload_vault_target_dir );

		if ( false !== $guest_upload_vault_real_base && false !== $guest_upload_vault_real_target && 0 === strpos( $guest_upload_vault_real_target, $guest_upload_vault_real_base ) && 'guest-upload-vault' === basename( $guest_upload_vault_real_target ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			global $wp_filesystem;

			if ( WP_Filesystem() && is_object( $wp_filesystem ) ) {
				$wp_filesystem->delete( $guest_upload_vault_real_target, true );
			}
		}
	}
}

delete_option( 'guest_upload_vault_settings' );
delete_option( 'guv_settings' );
