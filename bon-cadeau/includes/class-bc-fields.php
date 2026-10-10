<?php
/**
 * Field definitions: which data can be placed on the voucher.
 *
 * "auto"   fields are computed by the plugin (but can be overridden in the form).
 * "manual" fields are typed by you when generating a voucher.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BC_Fields {

	/**
	 * Built-in fields.
	 *
	 * @return array key => [ label, type, sample, input ]
	 */
	public static function builtin() {
		return array(
			'code'            => array(
				'label'  => __( 'Code unique', 'bon-cadeau' ),
				'type'   => 'auto',
				'sample' => 'BC-7KQ2-M9XA',
				'input'  => 'text',
			),
			'numero'          => array(
				'label'  => __( 'Numéro du bon', 'bon-cadeau' ),
				'type'   => 'auto',
				'sample' => '0042',
				'input'  => 'text',
			),
			'date_emission'   => array(
				'label'  => __( "Date d'émission", 'bon-cadeau' ),
				'type'   => 'auto',
				'sample' => date_i18n( get_option( 'date_format' ) ),
				'input'  => 'date',
			),
			'date_expiration' => array(
				'label'  => __( "Date d'expiration", 'bon-cadeau' ),
				'type'   => 'auto',
				'sample' => date_i18n( get_option( 'date_format' ), strtotime( '+12 months' ) ),
				'input'  => 'date',
			),
			'valeur'          => array(
				'label'  => __( 'Valeur', 'bon-cadeau' ),
				'type'   => 'manual',
				'sample' => '50',
				'input'  => 'text',
			),
			'beneficiaire'    => array(
				'label'  => __( 'Bénéficiaire', 'bon-cadeau' ),
				'type'   => 'manual',
				'sample' => 'Marie Dupont',
				'input'  => 'text',
			),
			'offert_par'      => array(
				'label'  => __( 'Offert par', 'bon-cadeau' ),
				'type'   => 'manual',
				'sample' => 'Paul',
				'input'  => 'text',
			),
			'prestation'      => array(
				'label'  => __( 'Prestation / produit', 'bon-cadeau' ),
				'type'   => 'manual',
				'sample' => 'Massage 1h',
				'input'  => 'text',
			),
			'message'         => array(
				'label'  => __( 'Message personnalisé', 'bon-cadeau' ),
				'type'   => 'manual',
				'sample' => 'Joyeux anniversaire ! Profite bien de ce moment.',
				'input'  => 'textarea',
			),
		);
	}

	/**
	 * All fields (built-in + custom fields defined in the template settings).
	 */
	public static function all() {
		$fields   = self::builtin();
		$template = BC_Template::get();
		foreach ( $template['custom_fields'] as $custom ) {
			$fields[ $custom['key'] ] = array(
				'label'  => $custom['label'],
				'type'   => 'manual',
				'sample' => $custom['label'],
				'input'  => 'text',
			);
		}
		return $fields;
	}

	/**
	 * Field keys referenced as {placeholders} in the given zones.
	 */
	public static function used_in_zones( array $zones ) {
		$used = array();
		foreach ( $zones as $zone ) {
			if ( preg_match_all( '/\{([a-z0-9_]+)\}/', $zone['text'], $m ) ) {
				foreach ( $m[1] as $key ) {
					$used[ $key ] = true;
				}
			}
		}
		return array_keys( $used );
	}

	/**
	 * Replace {placeholders} with values.
	 */
	public static function replace( $text, array $values ) {
		return preg_replace_callback(
			'/\{([a-z0-9_]+)\}/',
			function ( $m ) use ( $values ) {
				return isset( $values[ $m[1] ] ) ? (string) $values[ $m[1] ] : '';
			},
			$text
		);
	}

	/**
	 * Sample values used for the live preview in the editor.
	 */
	public static function samples() {
		$out = array();
		foreach ( self::all() as $key => $field ) {
			$out[ $key ] = $field['sample'];
		}
		return $out;
	}
}
