<?php
/**
 * WP_ServerInfo_Format.
 *
 * Every case here is a bug that shipped in 2.0.0 and earlier. They were found
 * by rendering the dashboard widget under a range of ini values and reading
 * the result, so they are written as value assertions on the formatter that
 * produced those strings.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers size and count formatting.
 */
class WP_ServerInfo_Format_Test extends WP_ServerInfo_TestCase {

	public function test_bytes_below_a_kibibyte_render_as_bytes() {
		$this->assertSame( '512 bytes', WP_ServerInfo_Format::filesize( 512 ) );
	}

	public function test_zero_renders_as_bytes_rather_than_unknown() {
		$this->assertSame( '0 bytes', WP_ServerInfo_Format::filesize( 0 ) );
	}

	/**
	 * The boundary test used to be > rather than >=, so a value sitting
	 * exactly on a unit boundary fell through to the unit below and one
	 * gibibyte displayed as "1,024.0 MiB".
	 *
	 * @dataProvider data_unit_boundaries
	 *
	 * @param int    $bytes    Size in bytes.
	 * @param string $expected Expected rendering.
	 */
	public function test_exact_unit_boundary_uses_that_unit( $bytes, $expected ) {
		$this->assertSame( $expected, WP_ServerInfo_Format::filesize( $bytes ) );
	}

	public function data_unit_boundaries() {
		return array(
			'one kibibyte' => array( 1024, '1.0 KiB' ),
			'one mebibyte' => array( 1048576, '1.0 MiB' ),
			'one gibibyte' => array( 1073741824, '1.0 GiB' ),
			'one tebibyte' => array( 1099511627776, '1.0 TiB' ),
		);
	}

	public function test_negative_and_non_numeric_sizes_are_unknown() {
		$this->assertSame( 'unknown', WP_ServerInfo_Format::filesize( -1 ) );
		$this->assertSame( 'unknown', WP_ServerInfo_Format::filesize( 'not a size' ) );
		$this->assertSame( 'unknown', WP_ServerInfo_Format::filesize( null ) );
	}

	/**
	 * Shorthand suffixes in php.ini are case-insensitive. The hand-rolled parser
	 * only matched uppercase and handed anything else back untouched, so a
	 * host configured as "128m" displayed the literal string "128m".
	 *
	 * @dataProvider data_shorthand_sizes
	 *
	 * @param string $value    php.ini shorthand value.
	 * @param string $expected Expected rendering.
	 */
	public function test_shorthand_sizes_parse_in_either_case( $value, $expected ) {
		$this->assertSame( $expected, WP_ServerInfo_Format::php_size( $value ) );
	}

	public function data_shorthand_sizes() {
		return array(
			'uppercase K' => array( '512K', '512.0 KiB' ),
			'lowercase k' => array( '512k', '512.0 KiB' ),
			'uppercase M' => array( '128M', '128.0 MiB' ),
			'lowercase m' => array( '128m', '128.0 MiB' ),
			'uppercase G' => array( '1G', '1.0 GiB' ),
			'lowercase g' => array( '1g', '1.0 GiB' ),
			'plain bytes' => array( '1048576', '1.0 MiB' ),
		);
	}

	/**
	 * -1 is PHP's "no limit" sentinel for memory_limit, not a size. It used to
	 * fall through the formatter and render as "unknown".
	 */
	public function test_unlimited_memory_limit_is_labelled_not_unknown() {
		$this->assertSame( 'Unlimited', WP_ServerInfo_Format::php_size( '-1' ) );
		$this->assertSame( 'Unlimited', WP_ServerInfo_Format::php_size( -1 ) );
	}

	public function test_unparseable_php_size_is_returned_verbatim() {
		$this->assertSame( 'nonsense', WP_ServerInfo_Format::php_size( 'nonsense' ) );
	}

	public function test_surrounding_whitespace_is_ignored() {
		$this->assertSame( '128.0 MiB', WP_ServerInfo_Format::php_size( ' 128M ' ) );
	}

	/**
	 * A MySQL variable that does not exist now yields null. Passing that
	 * straight to number_format_i18n() deprecates on PHP 8.1+.
	 */
	public function test_number_falls_back_to_na_for_unavailable_values() {
		$this->assertSame( 'N/A', WP_ServerInfo_Format::number( null ) );
		$this->assertSame( 'N/A', WP_ServerInfo_Format::number( '' ) );
		$this->assertSame( 'N/A', WP_ServerInfo_Format::number( 'unknown' ) );
	}

	public function test_number_localizes_numeric_values() {
		$this->assertSame( '1,024', WP_ServerInfo_Format::number( 1024 ) );
		$this->assertSame( '151', WP_ServerInfo_Format::number( '151' ) );
	}

	/**
	 * Zero is a value, and false is not a number. Both used to be conflated by
	 * the truthiness checks the probes were written with.
	 */
	public function test_number_distinguishes_zero_from_unavailable() {
		$this->assertSame( '0', WP_ServerInfo_Format::number( 0 ) );
		$this->assertSame( '0', WP_ServerInfo_Format::number( '0' ) );
		$this->assertSame( 'N/A', WP_ServerInfo_Format::number( false ) );
		$this->assertSame( 'N/A', WP_ServerInfo_Format::number( array() ) );
	}

	/**
	 * Counts -- connections, items, commands -- go through number(), which shows
	 * no decimals. Sizes go through filesize(), which keeps one.
	 */
	public function test_number_shows_counts_without_decimals() {
		$this->assertSame( '2', WP_ServerInfo_Format::number( 1.5 ) );
		$this->assertSame( '1,024', WP_ServerInfo_Format::number( 1023.7 ) );
	}

	/**
	 * MySQL variables arrive as strings, so the size formatter has to take one.
	 */
	public function test_filesize_accepts_a_numeric_string() {
		$this->assertSame( '2.0 KiB', WP_ServerInfo_Format::filesize( '2048' ) );
		$this->assertSame( '1.0 MiB', WP_ServerInfo_Format::filesize( 1048576.0 ) );
	}

	/**
	 * Just under a boundary stays in the unit below it, which is the other half
	 * of the >= fix.
	 */
	public function test_a_byte_below_a_boundary_stays_in_the_smaller_unit() {
		$this->assertSame( '1,023 bytes', WP_ServerInfo_Format::filesize( 1023 ) );
		$this->assertSame( '1,024.0 KiB', WP_ServerInfo_Format::filesize( 1048575 ) );
	}

	/**
	 * An ini directive that is simply not set arrives as an empty string, and
	 * there is no size to show. It must not come back as "0 bytes", which would
	 * read as a configured limit of nothing.
	 */
	public function test_an_empty_php_size_is_returned_verbatim() {
		$this->assertSame( '', WP_ServerInfo_Format::php_size( '' ) );
		$this->assertSame( '', WP_ServerInfo_Format::php_size( null ) );
	}

	public function test_php_size_accepts_a_bare_zero() {
		$this->assertSame( '0 bytes', WP_ServerInfo_Format::php_size( 0 ) );
	}
}
