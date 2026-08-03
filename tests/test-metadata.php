<?php
/**
 * The release invariants, asserted from the source and from the stored rows.
 *
 * Everything §7.2 asks of all nineteen plugins now lives in
 * Plugin_Metadata_TestCase. What is left here is what only WP-ServerInfo can
 * say: the version it ships, its class prefix, the thirty global functions its
 * Upgrade Notice has to account for, the three filters it fires, the fact that
 * a read-only report stores nothing, and the widget id the uninstaller has to
 * spell out because it runs with the plugin inactive.
 *
 * @package WP-ServerInfo
 */

/**
 * WP-ServerInfo's half of the shared metadata contract.
 *
 * @coversNothing
 */
class WP_ServerInfo_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '3.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_ServerInfo';
	}

	/**
	 * What a site owner updating from the released 2.0.0 would notice.
	 *
	 * The screen moved from Dashboard to Tools, so the old URL is bookmarked
	 * and has to appear beside the new one. Thirty global functions became
	 * methods on four classes with no shims, and the names were generic enough
	 * that other plugins defined some of them too -- so a theme or snippet
	 * calling one fatals. The three filters are new rather than renamed, but
	 * they are the only way to reach a Memcached or Redis server that is not on
	 * localhost, which is the reason anybody would go looking.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'WordPress 6.8',
			'PHP 8.2',
			'index.php?page=wp-serverinfo',
			'tools.php?page=wp-serverinfo',
			'`format_filesize()`',
			'`format_php_size()`',
			'`get_mysql_version()`',
			'`get_serverload()`',
			'`get_gd_version()`',
			'`display_serverinfo()`',
			'`WP_ServerInfo_Format`',
			'`WP_ServerInfo_MySQL`',
			'`WP_ServerInfo_PHP`',
			'`WP_ServerInfo_Cache`',
			'`wp_serverinfo_capability`',
			'`wp_serverinfo_memcached_server`',
			'`wp_serverinfo_redis_server`',
			'`wp_serverinfo_version`',
		);
	}

	/**
	 * This plugin keeps no version marker row (§2.1).
	 *
	 * Five read-only tables describing the host, plus a dashboard widget. No
	 * settings, no schema and no migration, so there is nothing for a marker to
	 * mark.
	 *
	 * @return bool
	 */
	protected function has_version_row() {
		return false;
	}

	/**
	 * This plugin keeps no settings row either, and so has no sanitiser.
	 *
	 * @return bool
	 */
	protected function has_settings_row() {
		return false;
	}

	/**
	 * The one row uninstall will ever find.
	 *
	 * Written by hand: nothing in the plugin writes this any more. An early
	 * build of the unreleased 3.0.0 did, and uninstall.php is the only thing
	 * that will ever take it off a site that ran that build.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		update_option( 'wp_serverinfo_version', array( 'plugin' => '3.0.0' ) );
	}

	/**
	 * At most five tags: wordpress.org shows five and ignores the rest.
	 */
	public function test_the_readme_lists_at_most_five_tags() {
		preg_match( '/^Tags:\s*(.+?)\s*$/m', $this->readme(), $matches );

		$this->assertNotEmpty( $matches, 'The readme must carry a Tags line.' );
		$this->assertLessThanOrEqual( 5, count( explode( ',', $matches[1] ) ), 'wordpress.org reads at most five tags, so a sixth is silently dropped.' );
	}

	/**
	 * The licence statement has to agree with itself.
	 *
	 * The header says "GPLv2 or later" and composer.json says
	 * GPL-2.0-or-later, so the comment block below the header must be the "or
	 * later" variant too. Five plugins in this family shipped a v2-only block
	 * underneath a v2-or-later header, which is a self-contradicting licence.
	 */
	public function test_the_licence_block_is_the_or_later_variant() {
		$source = $this->plugin_file();

		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ), 'The header offers the later-version option.' );
		$this->assertStringContainsString( 'either version 2 of the License, or', $source, 'The licence comment offers it too.' );
		$this->assertStringContainsString( '(at your option) any later version.', $source, 'In full, so the two cannot disagree.' );
		$this->assertStringContainsString(
			'Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)',
			$source,
			'Two spaces after the year and around "email :".'
		);
	}

	/**
	 * Donations is mandated wording, and its position is mandated too.
	 *
	 * It is the last h3 of ## Description, so it cannot end up under Usage or
	 * the FAQ.
	 */
	public function test_donations_is_the_last_h3_of_the_description() {
		$readme      = $this->readme();
		$description = substr( $readme, (int) strpos( $readme, '## Description' ) );
		$description = substr( $description, 0, (int) strpos( $description, "\n## Usage" ) );

		preg_match_all( '/^### (.+?)\s*$/m', $description, $headings );

		$this->assertNotEmpty( $headings[1], '## Description must carry at least the Donations h3.' );
		$this->assertSame( 'Donations', end( $headings[1] ), 'Donations is the last heading, so it closes the description.' );

		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting these plugins,'
			. ' if you really love my plugins and could spare me a couple of bucks, I will really appreciate it.'
			. ' If not feel free to use it without any obligations.',
			$description,
			'And carries the collection wording, word for word.'
		);

		// A plain paragraph, never a bullet: five plugins carried a stray "* ".
		$this->assertStringNotContainsString( '* I spent most of my free time', $description, 'Without a stray bullet, which an earlier copy carried.' );
	}

	/**
	 * The raised floors need a BREAKING changelog line, not only a notice.
	 *
	 * The shared Upgrade Notice test covers the notice half. This is the other
	 * half of §14.1: the floors are themselves a breaking change, and the one
	 * most likely to bite, because a site on an older stack is simply never
	 * offered the update with nothing anywhere to say why.
	 */
	public function test_the_raised_floors_have_a_breaking_changelog_line() {
		$this->assertMatchesRegularExpression(
			'/^\* BREAKING: Requires WordPress 6\.8 and PHP 8\.2/m',
			$this->readme(),
			'The floors need a BREAKING: changelog line as well as the notice.'
		);
	}

	/**
	 * Every hook the plugin fires carries its prefix, and is named in the readme.
	 *
	 * Three, and only three: two that point a panel at a server which is not on
	 * localhost, and one that hands the screen to somebody other than an
	 * administrator. A filter nobody can find is a filter nobody can use, so
	 * the readme has to name each one.
	 */
	public function test_every_fired_hook_is_prefixed_and_documented() {
		preg_match_all(
			"/(?:apply_filters|do_action)(?:_ref_array)?\(\s*'([a-z0-9_]+)'/",
			wp_serverinfo_test_source_code(),
			$hooks
		);

		$fired = array_unique( $hooks[1] );
		sort( $fired );

		$this->assertSame(
			array(
				'wp_serverinfo_capability',
				'wp_serverinfo_memcached_server',
				'wp_serverinfo_redis_server',
			),
			$fired,
			'Every hook this plugin fires is prefixed and documented, and there are exactly these three.'
		);

		foreach ( $fired as $hook ) {
			$this->assertStringContainsString( '`' . $hook . '`', $this->readme(), "{$hook} is undocumented." );
		}
	}

	/**
	 * Each fired hook needs a docblock carrying an @since directly above it.
	 *
	 * Asserted by reading the lines above the call rather than by one regex
	 * over the whole call site, because the three are spelled differently --
	 * one is a return statement, two are assignments -- and a pattern loose
	 * enough to cover all three is loose enough to match a hook with nothing
	 * above it.
	 */
	public function test_every_fired_hook_has_a_since_tag() {
		$found = 0;

		foreach ( wp_serverinfo_test_source_files() as $file ) {
			$lines = explode( "\n", (string) file_get_contents( $file ) );

			foreach ( $lines as $number => $line ) {
				if ( ! preg_match( "/(?:apply_filters|do_action)\(\s*'?([a-z0-9_]*)/", $line, $matches ) ) {
					continue;
				}

				++$found;

				$preamble = implode( "\n", array_slice( $lines, max( 0, $number - 12 ), min( 12, $number ) ) );

				$this->assertMatchesRegularExpression(
					'/@since \d+\.\d+\.\d+/',
					$preamble,
					basename( $file ) . " line {$number}: a fired hook needs a docblock with an @since."
				);
				$this->assertStringContainsString(
					'*/',
					$preamble,
					basename( $file ) . " line {$number}: the docblock must close immediately above the call."
				);
			}
		}

		$this->assertSame( 3, $found, 'The plugin fires three hooks and no more.' );
	}

	/**
	 * Every translation call carries the plugin's own text domain.
	 */
	public function test_every_translation_call_uses_the_plugin_text_domain() {
		preg_match_all(
			'/(?:__|_n|_x|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\((.*?)\);/s',
			wp_serverinfo_test_source_code(),
			$calls
		);

		$this->assertNotEmpty( $calls[1], 'The plugin makes at least one translation call.' );

		foreach ( $calls[1] as $arguments ) {
			$this->assertStringContainsString(
				"'wp-serverinfo'",
				$arguments,
				"A translation call is missing the text domain: {$arguments}"
			);
		}
	}

	/**
	 * The old forums.lesterchan.net is gone, and the rest had drifted to http.
	 *
	 * Code spans are exempt: they document input rather than link anywhere.
	 */
	public function test_no_insecure_or_dead_links_remain() {
		$readme = (string) preg_replace( '/`[^`]*`/', '', $this->readme() );

		$this->assertSame( 0, preg_match( '#http://#', $readme ), 'Every readme link must use https.' );
		$this->assertSame( 0, preg_match( '#http://#', $this->plugin_file() ), 'The plugin file links over https only.' );
		$this->assertStringNotContainsString( 'forums.lesterchan.net', $readme, 'The retired support forum is not linked; it no longer exists.' );
	}

	/**
	 * No inline styling survives in the source.
	 *
	 * §4.4 forbids these outright and §5 forbids !important. Both were in the
	 * 2.0.0 screens, and both are gone; nothing renders here, so this is the
	 * source-level half of the assertion the screen tests make on the rendered
	 * markup.
	 */
	public function test_no_inline_styling_survives_in_the_source() {
		$code = wp_serverinfo_test_source_code();

		$this->assertStringNotContainsString( '!important', $code, 'No rule is forced anywhere in the source.' );
		$this->assertStringNotContainsString( '<style', $code, 'And no style block is printed.' );
		$this->assertDoesNotMatchRegularExpression( '/\s(style|valign|align)=/', $code, 'The source still carries inline styling where a stylesheet belongs.' );
		$this->assertDoesNotMatchRegularExpression( '/<(td|th|table|div)[^>]*\swidth=/', $code, 'The source still sets a width attribute where CSS belongs.' );
	}

	/**
	 * The widget id lives in two places and has to agree in both.
	 *
	 * The uninstaller runs with the plugin inactive, so it cannot read
	 * WP_SERVERINFO_WIDGET_ID and spells the string out instead. If the
	 * constant ever changes, the uninstaller silently stops finding the user
	 * meta it is there to remove, which nothing else would catch -- least of
	 * all the shared uninstall test, which only walks the options table.
	 */
	public function test_the_uninstaller_agrees_with_the_widget_id_constant() {
		$this->assertSame( 'dashboard_serverinfo', WP_SERVERINFO_WIDGET_ID, 'The widget id constant is the id core registers.' );
		$this->assertStringContainsString(
			"\$widget_id = '" . WP_SERVERINFO_WIDGET_ID . "';",
			wp_serverinfo_test_read( 'uninstall.php' ),
			'And uninstall.php uses that exact value, so a rename cannot orphan the cleanup.'
		);
	}

	/**
	 * The plugin writes no option row at all, ever.
	 *
	 * Stronger than the two shared opt-out assertions, which each name one row.
	 * WP-ServerInfo is a read-only report and a dashboard widget; it keeps no
	 * state between requests, so under §2.1 it stores nothing -- not a settings
	 * row, not the version markers, and not some third row a later change might
	 * invent.
	 */
	public function test_the_plugin_stores_nothing() {
		// admin_init is deliberately not fired: core's callbacks on it send
		// headers, which PHPUnit has already begun output past.
		do_action( 'plugins_loaded' );
		do_action( 'init' );

		$this->assertSame(
			array(),
			$this->stored_option_names(),
			'WP-ServerInfo wrote an option row; it is meant to store nothing at all.'
		);
	}

	/**
	 * None of the Settings API scaffolding is here either.
	 *
	 * The shared opt-out covers the row and register_setting(). These two are
	 * the other halves of a settings screen, and a plugin growing one of them
	 * is a plugin about to grow a row.
	 */
	public function test_no_settings_api_scaffolding_exists() {
		$code = wp_serverinfo_test_source_code();

		$this->assertStringNotContainsString( 'add_settings_field', $code, 'No Settings API field is registered; this plugin stores nothing.' );
		$this->assertStringNotContainsString( 'sanitize_callback', $code, 'And no sanitiser, for the same reason.' );
	}
}
