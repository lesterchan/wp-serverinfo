<?php
/**
 * Value formatting.
 *
 * @package WP-ServerInfo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns raw sizes and counts into localized display strings.
 *
 * Replaces the global format_filesize() and format_php_size() functions the
 * plugin defined before 3.0.0. Those names were generic enough to collide --
 * WP-DownloadManager defines a format_filesize() of its own, and because it
 * loads first alphabetically its copy won the function_exists() race, so
 * WP-ServerInfo could end up rendering its sizes with another plugin's text
 * domain.
 */
class ServerInfo_Format {

	/**
	 * Format a byte count into a localized TiB/GiB/MiB/KiB/bytes string.
	 *
	 * @param int|float|string|null $raw_size Size in bytes.
	 * @return string Human-readable, localized size.
	 */
	public static function filesize( $raw_size ) {
		if ( ! is_numeric( $raw_size ) || $raw_size < 0 ) {
			return __( 'unknown', 'wp-serverinfo' );
		}

		$raw_size = (float) $raw_size;

		/*
		 * The comparison is >= rather than >, so that a value sitting exactly
		 * on a unit boundary uses that unit. With >, 1073741824 failed the GiB
		 * test by a hair and fell through to the MiB branch, displaying one
		 * gibibyte as "1,024.0 MiB".
		 */
		if ( $raw_size / 1099511627776 >= 1 ) {
			return number_format_i18n( $raw_size / 1099511627776, 1 ) . ' ' . __( 'TiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1073741824 >= 1 ) {
			return number_format_i18n( $raw_size / 1073741824, 1 ) . ' ' . __( 'GiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1048576 >= 1 ) {
			return number_format_i18n( $raw_size / 1048576, 1 ) . ' ' . __( 'MiB', 'wp-serverinfo' );
		} elseif ( $raw_size / 1024 >= 1 ) {
			return number_format_i18n( $raw_size / 1024, 1 ) . ' ' . __( 'KiB', 'wp-serverinfo' );
		}

		return number_format_i18n( $raw_size, 0 ) . ' ' . __( 'bytes', 'wp-serverinfo' );
	}

	/**
	 * Convert a PHP shorthand size (e.g. "128M") into a localized size string.
	 *
	 * @param string|int|null $size PHP size value, shorthand or numeric.
	 * @return string Localized size, "Unlimited", or the original value when unparseable.
	 */
	public static function php_size( $size ) {
		$size = is_string( $size ) ? trim( $size ) : $size;

		// -1 is PHP's "no limit" sentinel for memory_limit; it is not a size.
		if ( '-1' === (string) $size ) {
			return __( 'Unlimited', 'wp-serverinfo' );
		}

		if ( ! is_numeric( $size ) ) {
			/*
			 * PHP's shorthand suffixes are case-insensitive, so "128m" is
			 * exactly as valid as "128M" in php.ini. The old hand-rolled
			 * parser only matched uppercase and handed anything else straight
			 * through, so a host configured in lowercase displayed the raw
			 * string. wp_convert_hr_to_bytes() lowercases before matching.
			 */
			$bytes = wp_convert_hr_to_bytes( (string) $size );

			if ( $bytes > 0 ) {
				return self::filesize( $bytes );
			}

			return (string) $size;
		}

		return self::filesize( $size );
	}

	/**
	 * Localize a count, falling back to "N/A" when the value is unavailable.
	 *
	 * Guards number_format_i18n() against the null a missing MySQL variable
	 * yields, which would otherwise raise a deprecation on PHP 8.1+ for
	 * passing null to a non-nullable parameter.
	 *
	 * @param mixed $value The value to format.
	 * @return string Localized number, or a localized "N/A".
	 */
	public static function number( $value ) {
		if ( ! is_numeric( $value ) ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		return number_format_i18n( $value );
	}
}
