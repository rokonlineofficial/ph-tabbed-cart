<?php
// Runs only when the plugin is deleted from the WordPress admin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'phtc_options' );
