<?php
/**
 * Object cache probes for memcached and Redis.
 *
 * @package WP-ServerInfo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Connects to the object caches and reads their statistics.
 *
 * Replaces the global serverinfo_has_memcached() / serverinfo_get_*_stats()
 * functions from before 3.0.0.
 */
class WP_ServerInfo_Cache {

	/**
	 * Determine whether a memcached PHP extension is available.
	 *
	 * @return bool True when the Memcached or Memcache extension is loaded.
	 */
	public static function has_memcached() {
		return class_exists( 'Memcached' ) || class_exists( 'Memcache' );
	}

	/**
	 * Determine whether the phpredis extension is available.
	 *
	 * @return bool True when the Redis class is loaded.
	 */
	public static function has_redis() {
		return class_exists( 'Redis' );
	}

	/**
	 * Get the memcached server to query.
	 *
	 * @return array{host:string,port:int}
	 */
	private static function memcached_server() {
		/**
		 * Filters the memcached server WP-ServerInfo reports on.
		 *
		 * The default is the local server on the default port. Sites whose
		 * memcached lives on another host see an empty panel without this.
		 *
		 * @since 3.0.0
		 *
		 * @param array $server {
		 *     @type string $host Hostname or IP. Default 'localhost'.
		 *     @type int    $port Port number. Default 11211.
		 * }
		 */
		$server = apply_filters(
			'wp_serverinfo_memcached_server',
			array(
				'host' => 'localhost',
				'port' => 11211,
			)
		);

		return array(
			'host' => (string) ( $server['host'] ?? 'localhost' ),
			'port' => (int) ( $server['port'] ?? 11211 ),
		);
	}

	/**
	 * Get the Redis server to query.
	 *
	 * @return array{host:string,port:int,timeout:float}
	 */
	private static function redis_server() {
		/**
		 * Filters the Redis server WP-ServerInfo reports on.
		 *
		 * The default is the local server on the default port, exactly as for
		 * memcached above.
		 *
		 * @since 3.0.0
		 *
		 * @param array $server {
		 *     @type string $host    Hostname, IP, or a unix:// socket path. Default '127.0.0.1'.
		 *     @type int    $port    Port number. Use 0 for a unix socket. Default 6379.
		 *     @type float  $timeout Connection timeout in seconds. Default 1.
		 * }
		 */
		$server = apply_filters(
			'wp_serverinfo_redis_server',
			array(
				'host'    => '127.0.0.1',
				'port'    => 6379,
				'timeout' => 1,
			)
		);

		return array(
			'host'    => (string) ( $server['host'] ?? '127.0.0.1' ),
			'port'    => (int) ( $server['port'] ?? 6379 ),
			'timeout' => (float) ( $server['timeout'] ?? 1 ),
		);
	}

	/**
	 * Fetch memcached server statistics, preferring Memcached over Memcache.
	 *
	 * @return array|false Flat stats array for the server, or false when unavailable.
	 */
	public static function memcached_stats() {
		$server = self::memcached_server();

		if ( class_exists( 'Memcached' ) ) {
			$memcached = new Memcached();
			$memcached->addServer( $server['host'], $server['port'] );
			$stats = $memcached->getStats();

			// Memcached::getStats() returns an array keyed by "host:port".
			$stats = is_array( $stats ) && ! empty( $stats ) ? reset( $stats ) : false;

			// A server that is down yields the key with a false value.
			return is_array( $stats ) && ! empty( $stats ) ? $stats : false;
		}

		if ( class_exists( 'Memcache' ) ) {
			$memcache = new Memcache();
			@$memcache->addServer( $server['host'], $server['port'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- addServer() can emit a connection warning when memcached is down; suppressed intentionally.
			$stats = $memcache->getStats();

			return is_array( $stats ) && ! empty( $stats ) ? $stats : false;
		}

		return false;
	}

	/**
	 * Fetch Redis server info via phpredis.
	 *
	 * @return array|false Associative INFO array, or false when unavailable.
	 */
	public static function redis_stats() {
		if ( ! self::has_redis() ) {
			return false;
		}

		$server = self::redis_server();

		try {
			$redis = new Redis();

			// Short timeout so an unreachable server does not stall the page.
			if ( ! $redis->connect( $server['host'], $server['port'], $server['timeout'] ) ) {
				return false;
			}

			$info = $redis->info();
			$redis->close();
		} catch ( Throwable $e ) {
			/*
			 * Throwable, not Exception: phpredis throws RedisException (an
			 * Exception) for connection trouble, but connect() also raises
			 * ValueError/TypeError on PHP 8 for a malformed host or port --
			 * newly reachable now that the server is filterable. Those are
			 * Errors, not Exceptions, so they escaped the old catch and took
			 * the whole dashboard down with them.
			 */
			return false;
		}

		return is_array( $info ) && ! empty( $info ) ? $info : false;
	}

	/**
	 * Compute the Redis keyspace hit ratio as a percentage.
	 *
	 * @param array $info Redis INFO array.
	 * @return float Hit ratio, 0 when there have been no lookups.
	 */
	public static function redis_hit_ratio( array $info ) {
		$hits    = isset( $info['keyspace_hits'] ) ? (int) $info['keyspace_hits'] : 0;
		$misses  = isset( $info['keyspace_misses'] ) ? (int) $info['keyspace_misses'] : 0;
		$lookups = $hits + $misses;

		return $lookups > 0 ? round( ( $hits / $lookups ) * 100, 2 ) : 0;
	}
}
