<?php
/**
 * Plugin Name:       Bon Cadeau Generator
 * Description:       Génère des bons cadeaux (PNG / PDF) à partir de votre propre visuel, avec des zones de texte remplies automatiquement (code, dates, numéro) ou manuellement (valeur, bénéficiaire, message…).
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Bon Cadeau
 * License:           GPL-2.0-or-later
 * Text Domain:       bon-cadeau
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BC_VERSION', '1.0.0' );
define( 'BC_FILE', __FILE__ );
define( 'BC_DIR', plugin_dir_path( __FILE__ ) );
define( 'BC_URL', plugin_dir_url( __FILE__ ) );

require_once BC_DIR . 'includes/class-bc-fields.php';
require_once BC_DIR . 'includes/class-bc-template.php';
require_once BC_DIR . 'includes/class-bc-fonts.php';
require_once BC_DIR . 'includes/class-bc-renderer.php';
require_once BC_DIR . 'includes/class-bc-vouchers.php';
require_once BC_DIR . 'includes/class-bc-admin.php';

/**
 * Capability required to manage vouchers (filterable).
 */
function bc_capability() {
	return apply_filters( 'bon_cadeau_capability', 'manage_options' );
}

/**
 * Private storage directory (uploads/bon-cadeau).
 */
function bc_storage_dir( $sub = '' ) {
	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'bon-cadeau' . ( $sub ? '/' . $sub : '' );
	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	// Block direct listing / access (Apache). Files are served through an authenticated handler.
	$root = trailingslashit( $uploads['basedir'] ) . 'bon-cadeau';
	if ( ! file_exists( $root . '/index.php' ) ) {
		@file_put_contents( $root . '/index.php', "<?php // Silence is golden.\n" );
	}
	if ( $sub && ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" );
	}
	if ( 'vouchers' === $sub && ! file_exists( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	return $dir;
}

register_activation_hook(
	__FILE__,
	function () {
		BC_Vouchers::register_post_type();
		bc_storage_dir( 'vouchers' );
		bc_storage_dir( 'fonts' );
		if ( false === get_option( BC_Template::OPTION ) ) {
			add_option( BC_Template::OPTION, BC_Template::defaults(), '', false );
		}
	}
);

add_action( 'init', array( 'BC_Vouchers', 'register_post_type' ) );

if ( is_admin() ) {
	BC_Admin::init();
}
