<?php
/**
 * PHP and host environment probes.
 *
 * @package WP-ServerInfo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Everything PHP can report about itself and the machine it runs on.
 *
 * Replaces the global get_php_*() / get_gd_version() / get_serverload()
 * functions from before 3.0.0.
 */
class ServerInfo_PHP {

	/**
	 * Read an ini directive, distinguishing "unset" from a falsy value.
	 *
	 * The old getters each used `if ( ini_get( $x ) )`, which treats the
	 * perfectly ordinary value "0" as absent. max_execution_time is 0 on any
	 * host that disables the script timeout, so those installs displayed
	 * "N/A" -- and, because the template appends a unit, the string "N/As".
	 *
	 * @param string $directive The ini directive name.
	 * @return string The configured value, or a localized "N/A" when truly unset.
	 */
	public static function ini_value( $directive ) {
		$value = ini_get( $directive );

		if ( false === $value || '' === $value ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		return $value;
	}

	/**
	 * Get the memory_limit ini value.
	 *
	 * @return string
	 */
	public static function memory_limit() {
		return self::ini_value( 'memory_limit' );
	}

	/**
	 * Get the upload_max_filesize ini value.
	 *
	 * @return string
	 */
	public static function upload_max() {
		return self::ini_value( 'upload_max_filesize' );
	}

	/**
	 * Get the post_max_size ini value.
	 *
	 * @return string
	 */
	public static function post_max() {
		return self::ini_value( 'post_max_size' );
	}

	/**
	 * Get the max_execution_time ini value.
	 *
	 * @return string
	 */
	public static function max_execution() {
		return self::ini_value( 'max_execution_time' );
	}

	/**
	 * Get the short_open_tag ini state as a localized On/Off string.
	 *
	 * @return string Localized "On" or "Off".
	 */
	public static function short_tag() {
		return ini_get( 'short_open_tag' ) ? __( 'On', 'wp-serverinfo' ) : __( 'Off', 'wp-serverinfo' );
	}

	/**
	 * Get the installed GD library version.
	 *
	 * There is no fallback here on purpose. gd_info() is defined whenever the
	 * GD extension is loaded, so if it is missing there is no GD and no
	 * version to report. The code before 3.0.0 answered that case by
	 * buffering phpinfo() and scraping the result -- which could not succeed
	 * for the same reason, and dumped the entire PHP configuration into an
	 * output buffer to do it.
	 *
	 * @return string GD version, or a localized "N/A".
	 */
	public static function gd_version() {
		if ( ! function_exists( 'gd_info' ) ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		$gd = gd_info();

		if ( empty( $gd['GD Version'] ) ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		return $gd['GD Version'];
	}

	/**
	 * Get the server's own IP address.
	 *
	 * IIS does not set SERVER_ADDR; it exposes the same value as LOCAL_ADDR.
	 * Both display surfaces need the same rule, so it lives in one place --
	 * the dashboard widget used to read SERVER_ADDR unconditionally and
	 * rendered a bare ":80" on IIS.
	 *
	 * @return string The server IP, or an empty string when unavailable.
	 */
	public static function server_address() {
		global $is_IIS;

		$key = $is_IIS ? 'LOCAL_ADDR' : 'SERVER_ADDR';

		return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ?? '' ) );
	}

	/**
	 * Read a $_SERVER key as plain text.
	 *
	 * @param string $key The $_SERVER key.
	 * @return string Sanitized value, or an empty string when unset.
	 */
	public static function server_value( $key ) {
		return sanitize_text_field( wp_unslash( $_SERVER[ $key ] ?? '' ) );
	}

	/**
	 * Get the current server load average (Unix-like hosts only).
	 *
	 * @return string 1-minute load average, or a localized "N/A".
	 */
	public static function server_load() {
		$server_load = '';

		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions -- probing the host for load average; @-suppression and low-level/shell calls are intentional and gated behind availability and disable_functions checks.
		if ( PHP_OS !== 'WINNT' && PHP_OS !== 'WIN32' ) {
			$disabled_functions = array_map( 'trim', explode( ',', ini_get( 'disable_functions' ) ) );

			/*
			 * Ordered cascade, cheapest and safest first. Each step runs only
			 * if everything before it came back empty.
			 *
			 * sys_getloadavg() is the native answer and needs neither a
			 * readable /proc nor a shell, so it goes first -- on most hosts
			 * the shell fallbacks below are now never reached at all. They
			 * stay because shared hosts do disable it, but they are last
			 * resort, not the main path.
			 *
			 * The code before 3.0.0 chained these with if/else on whether
			 * /proc/loadavg *existed*, so a host where the file was present
			 * but unreadable fell through to no fallback at all and simply
			 * reported "N/A".
			 *
			 * The system() branch it tried first is gone. system() writes the
			 * command's output straight to the response instead of returning
			 * it, so on any host where it was enabled the raw uptime line was
			 * being printed into the middle of the General table -- exec()
			 * captures the same string without emitting anything.
			 */
			if ( function_exists( 'sys_getloadavg' ) && ! in_array( 'sys_getloadavg', $disabled_functions, true ) ) {
				$load_avg = sys_getloadavg();
				if ( is_array( $load_avg ) && isset( $load_avg[0] ) ) {
					$server_load = number_format( (float) $load_avg[0], 2, '.', '' );
				}
			}

			if ( '' === $server_load && file_exists( '/proc/loadavg' ) ) {
				$fh = @fopen( '/proc/loadavg', 'r' );
				if ( $fh ) {
					$data = @fread( $fh, 6 );
					@fclose( $fh );
					if ( is_string( $data ) && '' !== $data ) {
						$load_avg    = explode( ' ', $data );
						$server_load = trim( $load_avg[0] );
					}
				}
			}

			if ( '' === $server_load && function_exists( 'exec' ) && ! in_array( 'exec', $disabled_functions, true ) ) {
				$data = @exec( 'uptime 2>&1', $output, $return_var );
				if ( 0 === $return_var && ! empty( $data ) ) {
					preg_match( '/load average[s]?:\s*([0-9\.]+)/', $data, $matches );
					if ( isset( $matches[1] ) ) {
						$server_load = $matches[1];
					}
				}
			}

			if ( '' === $server_load && function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', $disabled_functions, true ) ) {
				$data = @shell_exec( 'uptime 2>&1' );
				if ( ! empty( $data ) ) {
					preg_match( '/load average[s]?:\s*([0-9\.]+)/', $data, $matches );
					if ( isset( $matches[1] ) ) {
						$server_load = $matches[1];
					}
				}
			}
		}
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions

		if ( '' === $server_load ) {
			return __( 'N/A', 'wp-serverinfo' );
		}

		return $server_load;
	}

	/**
	 * Build the PHP summary table shown above the ini directives.
	 *
	 * @return array<string,string> Label => value.
	 */
	public static function summary() {
		return array(
			__( 'PHP Version', 'wp-serverinfo' )         => phpversion(),
			__( 'Zend Engine Version', 'wp-serverinfo' ) => zend_version(),
			__( 'Server API', 'wp-serverinfo' )          => php_sapi_name(),
			__( 'Loaded Configuration File', 'wp-serverinfo' ) => php_ini_loaded_file() ? php_ini_loaded_file() : __( 'N/A', 'wp-serverinfo' ),
			__( 'Loaded Extensions', 'wp-serverinfo' )   => implode( ', ', get_loaded_extensions() ),
		);
	}

	/**
	 * Get every ini directive with its local and master value, sorted by name.
	 *
	 * Built from ini_get_all() rather than by scraping phpinfo() output, so
	 * the panel cannot leak environment variables or request headers.
	 *
	 * @return array Directive => array of values, or an empty array.
	 */
	public static function ini_directives() {
		if ( ! function_exists( 'ini_get_all' ) ) {
			return array();
		}

		$ini = ini_get_all( null, true );

		if ( empty( $ini ) ) {
			return array();
		}

		ksort( $ini );

		return $ini;
	}
}
