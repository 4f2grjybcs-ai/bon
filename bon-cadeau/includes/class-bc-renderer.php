<?php
/**
 * Draws the text zones onto the background image (GD + FreeType)
 * and wraps the result into a single-page PDF.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BC_Renderer {

	/** Line height, as a multiple of the font size (must match assets/admin.css). */
	const LINE_HEIGHT = 1.3;

	/**
	 * Render the voucher.
	 *
	 * @param string $background Absolute path to the background image.
	 * @param array  $zones      Template zones.
	 * @param array  $values     Field values (key => string).
	 * @return GdImage|resource|WP_Error
	 */
	public static function render( $background, array $zones, array $values ) {
		if ( ! function_exists( 'imagettftext' ) ) {
			return new WP_Error( 'bc_gd', __( "L'extension PHP GD avec FreeType est requise.", 'bon-cadeau' ) );
		}
		$img = self::load_image( $background );
		if ( ! $img ) {
			return new WP_Error( 'bc_image', __( 'Impossible de lire le visuel du bon cadeau.', 'bon-cadeau' ) );
		}
		$w = imagesx( $img );
		$h = imagesy( $img );

		imagealphablending( $img, true );
		imagesavealpha( $img, true );

		foreach ( $zones as $zone ) {
			$text = BC_Fields::replace( $zone['text'], $values );
			if ( $zone['uppercase'] ) {
				$text = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
			}
			if ( '' === trim( $text ) ) {
				continue;
			}
			self::draw_zone( $img, $w, $h, $zone, $text );
		}
		return $img;
	}

	private static function draw_zone( $img, $img_w, $img_h, array $zone, $text ) {
		$font    = BC_Fonts::path( $zone['font'] );
		$size_px = (float) $zone['size'];
		$size_pt = $size_px * 0.75; // GD renders at 96 dpi: 1pt = 4/3 px.
		$box_x   = $img_w * $zone['x'] / 100;
		$box_y   = $img_h * $zone['y'] / 100;
		$box_w   = $img_w * $zone['w'] / 100;
		$lh      = $size_px * self::LINE_HEIGHT;

		list( $r, $g, $b ) = sscanf( $zone['color'], '#%02x%02x%02x' );
		$color             = imagecolorallocate( $img, $r, $g, $b );

		// Font ascent / descent in px, used to vertically centre each line in its line box (like CSS).
		$asc_box = imagettfbbox( $size_pt, 0, $font, 'ÉÀHdlf|' );
		$des_box = imagettfbbox( $size_pt, 0, $font, 'gjpqy|' );
		$ascent  = -$asc_box[7];
		$descent = $des_box[1];

		$lines = self::wrap( $text, $font, $size_pt, $box_w );
		foreach ( $lines as $i => $line ) {
			$bbox   = imagettfbbox( $size_pt, 0, $font, $line );
			$line_w = $bbox[2] - $bbox[0];
			switch ( $zone['align'] ) {
				case 'center':
					$x = $box_x + ( $box_w - $line_w ) / 2;
					break;
				case 'right':
					$x = $box_x + $box_w - $line_w;
					break;
				default:
					$x = $box_x;
			}
			$x       -= $bbox[0];
			$baseline = $box_y + $i * $lh + ( $lh - ( $ascent + $descent ) ) / 2 + $ascent;
			imagettftext( $img, $size_pt, 0, (int) round( $x ), (int) round( $baseline ), $color, $font, $line );
		}
	}

	/**
	 * Word-wrap text to the zone width (explicit line breaks are kept).
	 */
	public static function wrap( $text, $font, $size_pt, $max_w ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $paragraph ) {
			$words = preg_split( '/ +/', trim( $paragraph ) );
			$line  = '';
			foreach ( $words as $word ) {
				$try  = '' === $line ? $word : $line . ' ' . $word;
				$bbox = imagettfbbox( $size_pt, 0, $font, $try );
				if ( '' !== $line && ( $bbox[2] - $bbox[0] ) > $max_w ) {
					$out[] = $line;
					$line  = $word;
				} else {
					$line = $try;
				}
			}
			$out[] = $line;
		}
		return $out;
	}

	private static function load_image( $path ) {
		$info = @getimagesize( $path );
		if ( ! $info ) {
			return false;
		}
		switch ( $info[2] ) {
			case IMAGETYPE_JPEG:
				$src = @imagecreatefromjpeg( $path );
				break;
			case IMAGETYPE_PNG:
				$src = @imagecreatefrompng( $path );
				break;
			case IMAGETYPE_GIF:
				$src = @imagecreatefromgif( $path );
				break;
			case IMAGETYPE_WEBP:
				$src = function_exists( 'imagecreatefromwebp' ) ? @imagecreatefromwebp( $path ) : false;
				break;
			default:
				$src = false;
		}
		if ( ! $src ) {
			return false;
		}
		if ( ! imageistruecolor( $src ) ) {
			imagepalettetotruecolor( $src );
		}
		return $src;
	}

	/**
	 * Save a rendered image as PNG.
	 */
	public static function save_png( $img, $path ) {
		return imagepng( $img, $path, 6 );
	}

	/**
	 * Build a single-page PDF embedding the image (JPEG, quality 92).
	 * The page keeps the image ratio, longest side = A4 long side (842pt).
	 *
	 * @param string $png_path Rendered voucher PNG.
	 * @return string|false PDF binary.
	 */
	public static function pdf_from_png( $png_path ) {
		$src = @imagecreatefrompng( $png_path );
		if ( ! $src ) {
			return false;
		}
		$w = imagesx( $src );
		$h = imagesy( $src );

		// Flatten transparency on white before JPEG encoding.
		$flat = imagecreatetruecolor( $w, $h );
		imagefill( $flat, 0, 0, imagecolorallocate( $flat, 255, 255, 255 ) );
		imagecopy( $flat, $src, 0, 0, 0, 0, $w, $h );

		ob_start();
		imagejpeg( $flat, null, 92 );
		$jpeg = ob_get_clean();

		$scale  = 842 / max( $w, $h );
		$page_w = round( $w * $scale, 2 );
		$page_h = round( $h * $scale, 2 );

		$content = sprintf( "q %.2F 0 0 %.2F 0 0 cm /Im1 Do Q", $page_w, $page_h );

		$objects   = array();
		$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
		$objects[] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
		$objects[] = sprintf( '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /XObject << /Im1 4 0 R >> >> /Contents 5 0 R >>', $page_w, $page_h );
		$objects[] = sprintf( "<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length %d >>\nstream\n%s\nendstream", $w, $h, strlen( $jpeg ), $jpeg );
		$objects[] = sprintf( "<< /Length %d >>\nstream\n%s\nendstream", strlen( $content ), $content );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $i => $obj ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $i + 1 ) . " 0 obj\n" . $obj . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
		foreach ( $offsets as $off ) {
			$pdf .= sprintf( "%010d 00000 n \n", $off );
		}
		$pdf .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
		return $pdf;
	}
}
