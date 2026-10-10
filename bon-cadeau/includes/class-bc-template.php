<?php
/**
 * Voucher template: background image + text zones + settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BC_Template {

	const OPTION = 'bon_cadeau_template';

	public static function defaults() {
		return array(
			'image_id'        => 0,
			'code_prefix'     => 'BC-',
			'validity_months' => 12,
			'custom_fields'   => array(),
			'email_subject'   => __( 'Votre bon cadeau', 'bon-cadeau' ),
			'email_body'      => __( "Bonjour {beneficiaire},\n\nVous trouverez ci-joint votre bon cadeau d'une valeur de {valeur} €.\nCode : {code} — valable jusqu'au {date_expiration}.\n\nÀ très bientôt !", 'bon-cadeau' ),
			'zones'           => array(
				self::zone( array( 'text' => '{valeur} €', 'x' => 55, 'y' => 30, 'w' => 40, 'size' => 90, 'font' => 'dejavu-sans-bold', 'align' => 'center' ) ),
				self::zone( array( 'text' => 'Offert à {beneficiaire}', 'x' => 55, 'y' => 50, 'w' => 40, 'size' => 36, 'align' => 'center' ) ),
				self::zone( array( 'text' => 'De la part de {offert_par}', 'x' => 55, 'y' => 58, 'w' => 40, 'size' => 30, 'align' => 'center' ) ),
				self::zone( array( 'text' => "Code : {code}\nValable jusqu'au {date_expiration}", 'x' => 55, 'y' => 80, 'w' => 40, 'size' => 24, 'align' => 'center' ) ),
			),
		);
	}

	/**
	 * Normalised zone with defaults.
	 */
	public static function zone( array $z = array() ) {
		$z = wp_parse_args(
			$z,
			array(
				'id'        => 'z' . wp_generate_password( 6, false, false ),
				'text'      => '{valeur}',
				'x'         => 10,
				'y'         => 10,
				'w'         => 40,
				'size'      => 40,
				'font'      => 'dejavu-sans',
				'color'     => '#222222',
				'align'     => 'left',
				'uppercase' => false,
			)
		);
		return array(
			'id'        => preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $z['id'] ),
			'text'      => sanitize_textarea_field( (string) $z['text'] ),
			'x'         => self::clamp( (float) $z['x'], 0, 100 ),
			'y'         => self::clamp( (float) $z['y'], 0, 100 ),
			'w'         => self::clamp( (float) $z['w'], 1, 100 ),
			'size'      => self::clamp( (float) $z['size'], 4, 1000 ),
			'font'      => sanitize_key( $z['font'] ),
			'color'     => sanitize_hex_color( $z['color'] ) ? sanitize_hex_color( $z['color'] ) : '#222222',
			'align'     => in_array( $z['align'], array( 'left', 'center', 'right' ), true ) ? $z['align'] : 'left',
			'uppercase' => ! empty( $z['uppercase'] ),
		);
	}

	private static function clamp( $v, $min, $max ) {
		return round( max( $min, min( $max, $v ) ), 3 );
	}

	public static function get() {
		$t = get_option( self::OPTION );
		if ( ! is_array( $t ) ) {
			$t = self::defaults();
		}
		$t          = wp_parse_args( $t, self::defaults() );
		$t['zones'] = array_map( array( __CLASS__, 'zone' ), (array) $t['zones'] );
		return $t;
	}

	/**
	 * Sanitize and save raw input from the editor form.
	 */
	public static function save( array $input ) {
		$t = self::get();

		$t['image_id']        = absint( $input['image_id'] ?? 0 );
		$t['code_prefix']     = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $input['code_prefix'] ?? '' ) );
		$t['validity_months'] = max( 0, absint( $input['validity_months'] ?? 12 ) );
		$t['email_subject']   = sanitize_text_field( $input['email_subject'] ?? '' );
		$t['email_body']      = sanitize_textarea_field( $input['email_body'] ?? '' );

		// Custom fields: one label per line.
		$t['custom_fields'] = array();
		$builtin            = BC_Fields::builtin();
		$lines              = preg_split( '/\r\n|\r|\n/', (string) ( $input['custom_fields'] ?? '' ) );
		foreach ( $lines as $line ) {
			$label = sanitize_text_field( $line );
			if ( '' === $label ) {
				continue;
			}
			$key = str_replace( '-', '_', sanitize_title( remove_accents( $label ) ) );
			if ( '' === $key || isset( $builtin[ $key ] ) ) {
				continue;
			}
			$t['custom_fields'][ $key ] = array(
				'key'   => $key,
				'label' => $label,
			);
		}
		$t['custom_fields'] = array_values( $t['custom_fields'] );

		$zones = json_decode( (string) ( $input['zones'] ?? '[]' ), true );
		if ( is_array( $zones ) ) {
			$t['zones'] = array_values( array_map( array( __CLASS__, 'zone' ), array_filter( $zones, 'is_array' ) ) );
		}

		update_option( self::OPTION, $t, false );
		return $t;
	}

	/**
	 * Absolute path of the background image (full size), or ''.
	 */
	public static function image_path( array $t = null ) {
		$t = $t ? $t : self::get();
		if ( ! $t['image_id'] ) {
			return '';
		}
		$path = get_attached_file( $t['image_id'] );
		// WP >= 5.3 may scale big images; prefer the original upload.
		if ( function_exists( 'wp_get_original_image_path' ) ) {
			$original = wp_get_original_image_path( $t['image_id'] );
			if ( $original && file_exists( $original ) ) {
				$path = $original;
			}
		}
		return ( $path && file_exists( $path ) ) ? $path : '';
	}

	public static function image_url( array $t = null ) {
		$t = $t ? $t : self::get();
		if ( ! $t['image_id'] ) {
			return '';
		}
		if ( function_exists( 'wp_get_original_image_url' ) ) {
			$url = wp_get_original_image_url( $t['image_id'] );
			if ( $url ) {
				return $url;
			}
		}
		return (string) wp_get_attachment_url( $t['image_id'] );
	}
}
