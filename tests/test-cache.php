<?php
/**
 * WP_ServerInfo_Cache.
 *
 * The memcached and Redis extensions are not installed in the test container,
 * so the connection paths cannot be exercised here. What can be -- and what
 * users actually configure -- is the server resolution: the filters added in
 * 3.0.0 are the only way a site whose cache is not on localhost gets a
 * populated panel, and a filter that silently fails to apply looks exactly
 * like a cache server that is down.
 *
 * @package WP-ServerInfo
 */

/**
 * Covers cache server resolution and the filters that drive it.
 */
class WP_ServerInfo_Cache_Test extends WP_ServerInfo_TestCase {

	/**
	 * Call one of the private server-resolution methods.
	 *
	 * Reflection rather than a public accessor: these are an implementation
	 * detail of the two stats calls, and exposing them publicly purely to
	 * test them would widen the plugin's surface for no one's benefit.
	 *
	 * @param string $method Method name.
	 * @return array
	 */
	private function resolve( $method ) {
		$reflection = new ReflectionMethod( WP_ServerInfo_Cache::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invoke( null );
	}

	public function test_memcached_defaults_to_the_local_server() {
		$this->assertSame(
			array(
				'host' => 'localhost',
				'port' => 11211,
			),
			$this->resolve( 'memcached_server' )
		);
	}

	public function test_redis_defaults_to_the_local_server() {
		$this->assertSame(
			array(
				'host'    => '127.0.0.1',
				'port'    => 6379,
				'timeout' => 1.0,
			),
			$this->resolve( 'redis_server' )
		);
	}

	public function test_memcached_server_is_filterable() {
		add_filter(
			'wp_serverinfo_memcached_server',
			function () {
				return array(
					'host' => 'cache.internal',
					'port' => 11212,
				);
			}
		);

		$this->assertSame(
			array(
				'host' => 'cache.internal',
				'port' => 11212,
			),
			$this->resolve( 'memcached_server' )
		);
	}

	public function test_redis_server_is_filterable() {
		add_filter(
			'wp_serverinfo_redis_server',
			function () {
				return array(
					'host'    => 'redis.internal',
					'port'    => 6380,
					'timeout' => 2.5,
				);
			}
		);

		$this->assertSame(
			array(
				'host'    => 'redis.internal',
				'port'    => 6380,
				'timeout' => 2.5,
			),
			$this->resolve( 'redis_server' )
		);
	}

	/**
	 * A filter that returns a partial array must not leave the missing keys
	 * undefined -- indexing them would warn on PHP 8, inside a try/catch that
	 * would report it as an unreachable cache server.
	 */
	public function test_partial_filter_return_falls_back_to_defaults() {
		add_filter(
			'wp_serverinfo_redis_server',
			function () {
				return array( 'host' => 'redis.internal' );
			}
		);

		$this->assertSame(
			array(
				'host'    => 'redis.internal',
				'port'    => 6379,
				'timeout' => 1.0,
			),
			$this->resolve( 'redis_server' )
		);
	}

	public function test_extension_detection_matches_the_loaded_classes() {
		$this->assertSame(
			class_exists( 'Memcached' ) || class_exists( 'Memcache' ),
			WP_ServerInfo_Cache::has_memcached()
		);
		$this->assertSame( class_exists( 'Redis' ), WP_ServerInfo_Cache::has_redis() );
	}

	public function test_stats_return_false_when_the_extension_is_missing() {
		if ( WP_ServerInfo_Cache::has_redis() ) {
			$this->markTestSkipped( 'The Redis extension is installed in this environment.' );
		}

		$this->assertFalse( WP_ServerInfo_Cache::redis_stats() );
	}

	public function test_memcached_stats_return_false_when_the_extension_is_missing() {
		if ( WP_ServerInfo_Cache::has_memcached() ) {
			$this->markTestSkipped( 'A memcached extension is installed in this environment.' );
		}

		$this->assertFalse( WP_ServerInfo_Cache::memcached_stats() );
	}

	/**
	 * A filter that returns something that is not an array at all must not take
	 * the panel down with it. Indexing a string raises on PHP 8, inside a
	 * try/catch that would have reported it as an unreachable cache server.
	 *
	 * @dataProvider data_non_array_filter_returns
	 *
	 * @param mixed $returned What the filter hands back.
	 */
	public function test_a_filter_returning_a_non_array_falls_back_to_defaults( $returned ) {
		foreach ( array( 'wp_serverinfo_memcached_server', 'wp_serverinfo_redis_server' ) as $filter ) {
			add_filter(
				$filter,
				function () use ( $returned ) {
					return $returned;
				}
			);
		}

		$this->assertSame(
			array(
				'host' => 'localhost',
				'port' => 11211,
			),
			$this->resolve( 'memcached_server' )
		);

		$this->assertSame(
			array(
				'host'    => '127.0.0.1',
				'port'    => 6379,
				'timeout' => 1.0,
			),
			$this->resolve( 'redis_server' )
		);
	}

	public function data_non_array_filter_returns() {
		return array(
			'a hostname on its own' => array( 'cache.internal' ),
			'null'                  => array( null ),
			'false'                 => array( false ),
			'an integer'            => array( 6379 ),
		);
	}

	/**
	 * The values are cast, so a filter written with strings still produces the
	 * types connect() and addServer() expect.
	 */
	public function test_filtered_values_are_cast_to_their_declared_types() {
		add_filter(
			'wp_serverinfo_redis_server',
			function () {
				return array(
					'host'    => 12345,
					'port'    => '6380',
					'timeout' => '2',
				);
			}
		);

		$server = $this->resolve( 'redis_server' );

		$this->assertSame( '12345', $server['host'] );
		$this->assertSame( 6380, $server['port'] );
		$this->assertSame( 2.0, $server['timeout'] );
	}

	/**
	 * A unix socket is spelled as a host with port 0, and 0 must survive the
	 * fallback rather than being read as "missing" and replaced by 6379.
	 */
	public function test_a_zero_port_is_kept_for_a_unix_socket() {
		add_filter(
			'wp_serverinfo_redis_server',
			function ( $server ) {
				$server['host'] = 'unix:///var/run/redis.sock';
				$server['port'] = 0;

				return $server;
			}
		);

		$server = $this->resolve( 'redis_server' );

		$this->assertSame( 'unix:///var/run/redis.sock', $server['host'] );
		$this->assertSame( 0, $server['port'] );
	}

	/**
	 * Only the plugin's own filters decide where to look; nothing reads an option
	 * row, because WP-ServerInfo has no settings (section 2.1).
	 */
	public function test_the_cache_probes_read_no_option_row() {
		$source = wp_serverinfo_test_read( 'includes/class-wp-serverinfo-cache.php' );

		$this->assertStringNotContainsString( 'get_option', $source );
	}

	/**
	 * The two connection paths are the plugin's one legitimate coverage
	 * exclusion (section 7.3): neither extension is installed in any environment
	 * the suite runs in, so the bodies cannot be reached. Each start marker
	 * carries its reason on the same line, and there are no others anywhere.
	 *
	 * The annotation is assembled from a prefix rather than written out, because
	 * a literal one in this docblock would exclude the test itself.
	 */
	public function test_the_coverage_exclusions_carry_their_reasons() {
		// Split so that a literal annotation in this file does not exclude it.
		$marker = sprintf( '@%sIgnore', 'codeCoverage' );
		$starts = 0;
		$total  = 0;

		foreach ( wp_serverinfo_test_source_files() as $file ) {
			foreach ( explode( "\n", (string) file_get_contents( $file ) ) as $line ) {
				if ( false === strpos( $line, $marker ) ) {
					continue;
				}

				++$total;

				if ( false !== strpos( $line, $marker . 'End' ) ) {
					continue;
				}

				++$starts;

				$this->assertStringContainsString(
					'--',
					substr( $line, (int) strpos( $line, $marker ) ),
					"A coverage exclusion must state its reason: {$line}"
				);

				$this->assertStringContainsString(
					basename( $file ),
					'class-wp-serverinfo-cache.php',
					'Only the cache probes may be excluded.'
				);
			}
		}

		$this->assertSame( 2, $starts, 'Two excluded regions: memcached and Redis.' );
		$this->assertSame( 4, $total, 'Two starts and two ends, all balanced.' );
	}

	/**
	 * @dataProvider data_hit_ratios
	 *
	 * @param array     $info     Redis INFO array.
	 * @param int|float $expected Expected hit ratio.
	 */
	public function test_redis_hit_ratio( $info, $expected ) {
		$this->assertSame( $expected, WP_ServerInfo_Cache::redis_hit_ratio( $info ) );
	}

	public function data_hit_ratios() {
		return array(
			'no lookups yet'            => array( array(), 0 ),
			'all misses'                => array(
				array(
					'keyspace_hits'   => 0,
					'keyspace_misses' => 12,
				),
				0.0,
			),
			'string counters from INFO' => array(
				array(
					'keyspace_hits'   => '1',
					'keyspace_misses' => '3',
				),
				25.0,
			),
			'all hits'                  => array(
				array(
					'keyspace_hits'   => 10,
					'keyspace_misses' => 0,
				),
				100.0,
			),
			'three quarters'            => array(
				array(
					'keyspace_hits'   => 30,
					'keyspace_misses' => 10,
				),
				75.0,
			),
		);
	}
}
