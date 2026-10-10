<?php
/**
 * Available TrueType fonts: bundled DejaVu fonts + any .ttf/.otf uploaded
 * into wp-content/uploads/bon-cadeau/fonts/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BC_Fonts {

	/**
	 * @return array id => [ label, path, url ]
	 */
	public static function all() {
		$fonts = array(
			'dejavu-sans'       => array( 'DejaVu Sans', 'DejaVuSans.ttf' ),
			'dejavu-sans-bold'  => array( 'DejaVu Sans Bold', 'DejaVuSans-Bold.ttf' ),
			'dejavu-serif'      => array( 'DejaVu Serif', 'DejaVuSerif.ttf' ),
			'dejavu-serif-bold' => array( 'DejaVu Serif Bold', 'DejaVuSerif-Bold.ttf' ),
		);
		$out   = array();
		foreach ( $fonts as $id => $f ) {
			$out[ $id ] = array(
				'label' => $f[0],
				'path'  => BC_DIR . 'fonts/' . $f[1],
				'url'   => BC_URL . 'fonts/' . $f[1],
			);
		}

		$dir     = bc_storage_dir( 'fonts' );
		$uploads = wp_upload_dir();
		$baseurl = trailingslashit( $uploads['baseurl'] ) . 'bon-cadeau/fonts/';
		foreach ( (array) glob( $dir . '/*' ) as $file ) {
			if ( ! $file || ! in_array( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ), array( 'ttf', 'otf' ), true ) ) {
				continue;
			}
			$name       = basename( $file );
			$id         = 'custom-' . sanitize_key( pathinfo( $name, PATHINFO_FILENAME ) );
			$out[ $id ] = array(
				'label' => pathinfo( $name, PATHINFO_FILENAME ),
				'path'  => $file,
				'url'   => $baseurl . rawurlencode( $name ),
			);
		}
		return $out;
	}

	public static function path( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ]['path'] : $all['dejavu-sans']['path'];
	}

	/**
	 * Handle a font upload from the admin form.
	 *
	 * @return true|WP_Error
	 */
	public static function handle_upload( array $file ) {
		if ( ! empty( $file['error'] ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'bc_font', __( "Échec de l'envoi de la police.", 'bon-cadeau' ) );
		}
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'ttf', 'otf' ), true ) ) {
			return new WP_Error( 'bc_font', __( 'Seuls les fichiers .ttf et .otf sont acceptés.', 'bon-cadeau' ) );
		}
		// Check the font signature (TrueType 0x00010000, "true" or OpenType "OTTO").
		$sig = (string) file_get_contents( $file['tmp_name'], false, null, 0, 4 );
		if ( ! in_array( $sig, array( "\x00\x01\x00\x00", 'true', 'OTTO' ), true ) ) {
			return new WP_Error( 'bc_font', __( "Ce fichier n'est pas une police valide.", 'bon-cadeau' ) );
		}
		$name = sanitize_file_name( pathinfo( $file['name'], PATHINFO_FILENAME ) ) . '.' . $ext;
		if ( ! move_uploaded_file( $file['tmp_name'], bc_storage_dir( 'fonts' ) . '/' . $name ) ) {
			return new WP_Error( 'bc_font', __( "Impossible d'enregistrer la police.", 'bon-cadeau' ) );
		}
		return true;
	}
}
