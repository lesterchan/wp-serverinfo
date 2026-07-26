<?php
/**
 * ServerInfo_Cache.
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
class Test_ServerInfo_Cache extends WP_UnitTestCase {

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
		$reflection = new ReflectionMethod( ServerInfo_Cache::class, $method );
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
			'serverinfo_memcached_server',
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
			'serverinfo_redis_server',
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
			'serverinfo_redis_server',
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
			ServerInfo_Cache::has_memcached()
		);
		$this->assertSame( class_exists( 'Redis' ), ServerInfo_Cache::has_redis() );
	}

	public function test_stats_return_false_when_the_extension_is_missing() {
		if ( ServerInfo_Cache::has_redis() ) {
			$this->markTestSkipped( 'The Redis extension is installed in this environment.' );
		}

		$this->assertFalse( ServerInfo_Cache::redis_stats() );
	}

	/**
	 * @dataProvider data_hit_ratios
	 *
	 * @param array     $info     Redis INFO array.
	 * @param int|float $expected Expected hit ratio.
	 */
	public function test_redis_hit_ratio( $info, $expected ) {
		$this->assertSame( $expected, ServerInfo_Cache::redis_hit_ratio( $info ) );
	}

	public function data_hit_ratios() {
		return array(
			'no lookups yet' => array( array(), 0 ),
			'all hits'       => array(
				array(
					'keyspace_hits'   => 10,
					'keyspace_misses' => 0,
				),
				100.0,
			),
			'three quarters' => array(
				array(
					'keyspace_hits'   => 30,
					'keyspace_misses' => 10,
				),
				75.0,
			),
		);
	}
}
