<?php
/**
 * Generated vouchers storage (private custom post type "bc_voucher").
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BC_Vouchers {

	const POST_TYPE = 'bc_voucher';
	const COUNTER   = 'bon_cadeau_counter';

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => __( 'Bons cadeaux', 'bon-cadeau' ),
				'public'          => false,
				'show_ui'         => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
			)
		);
		add_action( 'before_delete_post', array( __CLASS__, 'delete_file' ) );
	}

	/**
	 * Create a voucher from form input.
	 *
	 * @param array $input Unslashed form values, key => value.
	 * @return int|WP_Error Post ID.
	 */
	public static function create( array $input ) {
		$template   = BC_Template::get();
		$background = BC_Template::image_path( $template );
		if ( ! $background ) {
			return new WP_Error( 'bc_no_image', __( "Aucun visuel n'est défini : ajoutez-le dans « Modèle du bon ».", 'bon-cadeau' ) );
		}

		$raw = array();
		foreach ( BC_Fields::all() as $key => $field ) {
			$value       = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$raw[ $key ] = 'textarea' === $field['input'] ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		}

		// --- Automatic fields (used when left empty) ---
		$raw['code'] = strtoupper( preg_replace( '/[^A-Za-z0-9_-]/', '', $raw['code'] ) );
		if ( '' === $raw['code'] ) {
			$raw['code'] = self::unique_code( $template['code_prefix'] );
		} elseif ( self::find_by_code( $raw['code'] ) ) {
			return new WP_Error( 'bc_code', __( 'Ce code existe déjà.', 'bon-cadeau' ) );
		}

		if ( '' === $raw['numero'] ) {
			$raw['numero'] = self::next_number();
		}

		$today = current_time( 'Y-m-d' );
		if ( ! self::is_ymd( $raw['date_emission'] ) ) {
			$raw['date_emission'] = $today;
		}
		if ( ! self::is_ymd( $raw['date_expiration'] ) ) {
			$raw['date_expiration'] = $template['validity_months']
				? gmdate( 'Y-m-d', strtotime( $raw['date_emission'] . ' +' . (int) $template['validity_months'] . ' months' ) )
				: '';
		}

		$values = self::display_values( $raw );
		$img    = BC_Renderer::render( $background, $template['zones'], $values );
		if ( is_wp_error( $img ) ) {
			return $img;
		}

		$file = sanitize_file_name( $raw['code'] ) . '-' . wp_generate_password( 12, false, false ) . '.png';
		if ( ! BC_Renderer::save_png( $img, bc_storage_dir( 'vouchers' ) . '/' . $file ) ) {
			return new WP_Error( 'bc_save', __( "Impossible d'enregistrer l'image du bon.", 'bon-cadeau' ) );
		}
		imagedestroy( $img );

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $raw['code'],
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		update_post_meta( $post_id, '_bc_code', $raw['code'] );
		update_post_meta( $post_id, '_bc_values', $raw );
		update_post_meta( $post_id, '_bc_status', 'valid' );
		update_post_meta( $post_id, '_bc_file', $file );

		return $post_id;
	}

	/**
	 * Values as printed on the voucher (dates formatted with the site date format).
	 */
	public static function display_values( array $raw ) {
		$values = $raw;
		foreach ( array( 'date_emission', 'date_expiration' ) as $key ) {
			if ( ! empty( $raw[ $key ] ) && self::is_ymd( $raw[ $key ] ) ) {
				$values[ $key ] = date_i18n( get_option( 'date_format' ), strtotime( $raw[ $key ] . ' 12:00:00' ) );
			}
		}
		return apply_filters( 'bon_cadeau_values', $values, $raw );
	}

	private static function is_ymd( $s ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s );
	}

	public static function unique_code( $prefix ) {
		$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No 0/O/1/I to avoid confusion.
		do {
			$code = '';
			for ( $i = 0; $i < 8; $i++ ) {
				$code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
				if ( 3 === $i ) {
					$code .= '-';
				}
			}
			$code = strtoupper( $prefix ) . $code;
		} while ( self::find_by_code( $code ) );
		return $code;
	}

	public static function next_number() {
		$n = (int) get_option( self::COUNTER, 0 ) + 1;
		update_option( self::COUNTER, $n, false );
		return str_pad( (string) $n, 4, '0', STR_PAD_LEFT );
	}

	public static function find_by_code( $code ) {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'meta_key'       => '_bc_code',
				'meta_value'     => $code,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	public static function file_path( $post_id ) {
		$file = get_post_meta( $post_id, '_bc_file', true );
		if ( ! $file ) {
			return '';
		}
		$path = bc_storage_dir( 'vouchers' ) . '/' . basename( $file );
		return file_exists( $path ) ? $path : '';
	}

	public static function set_status( $post_id, $status ) {
		$status = 'used' === $status ? 'used' : 'valid';
		update_post_meta( $post_id, '_bc_status', $status );
		if ( 'used' === $status ) {
			update_post_meta( $post_id, '_bc_used_at', current_time( 'mysql' ) );
		} else {
			delete_post_meta( $post_id, '_bc_used_at' );
		}
	}

	public static function delete_file( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$path = self::file_path( $post_id );
		if ( $path ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Email the voucher (PDF attached) to a recipient.
	 *
	 * @return bool
	 */
	public static function send_email( $post_id, $to ) {
		$to   = sanitize_email( $to );
		$path = self::file_path( $post_id );
		if ( ! is_email( $to ) || ! $path ) {
			return false;
		}
		$template = BC_Template::get();
		$values   = self::display_values( (array) get_post_meta( $post_id, '_bc_values', true ) );
		$pdf      = BC_Renderer::pdf_from_png( $path );
		if ( ! $pdf ) {
			return false;
		}
		$tmp = trailingslashit( get_temp_dir() ) . 'bon-cadeau-' . sanitize_file_name( $values['code'] ) . '.pdf';
		file_put_contents( $tmp, $pdf );

		$sent = wp_mail(
			$to,
			BC_Fields::replace( $template['email_subject'], $values ),
			BC_Fields::replace( $template['email_body'], $values ),
			array(),
			array( $tmp )
		);
		wp_delete_file( $tmp );
		if ( $sent ) {
			update_post_meta( $post_id, '_bc_email', $to );
		}
		return $sent;
	}
}
