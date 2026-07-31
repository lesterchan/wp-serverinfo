<?php
/**
 * The release invariants, asserted from the source and from the stored rows.
 *
 * These are the house rules every plugin in this family shares, and every one of
 * them has been broken by an ordinary edit at some point: a header field that
 * drifted out of the canonical order, a new directory shipped without its
 * silence guard, a version bumped in one file of three, a readme header line
 * that lost the two trailing spaces holding it apart from the next.
 *
 * They are the things a restructuring quietly breaks and nothing notices until a
 * release fails its pre-flight months later, so catching them here is far
 * cheaper than catching them there.
 *
 * @package WP-ServerInfo
 */

/**
 * @coversNothing
 */
class WP_ServerInfo_Metadata_Test extends WP_ServerInfo_TestCase {

	const VERSION = '3.0.0';

	/**
	 * The main plugin file.
	 *
	 * @return string
	 */
	protected function plugin_file() {
		return wp_serverinfo_test_read( 'wp-serverinfo.php' );
	}

	/**
	 * The readme.
	 *
	 * @return string
	 */
	protected function readme() {
		return wp_serverinfo_test_read( 'README.md' );
	}

	/**
	 * Every directory in the repo that holds at least one PHP file.
	 *
	 * The filter prunes rather than discarding after the fact. A plain filter on
	 * a RecursiveIteratorIterator descends into vendor/ and node_modules/ before
	 * throwing the results away, which is slow enough to look like a hang.
	 *
	 * @return string[] Absolute paths, plugin root included.
	 */
	protected function php_directories() {
		$root  = dirname( __DIR__ );
		$found = array();

		$directories = new RecursiveCallbackFilterIterator(
			new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
			function ( $file ) {
				if ( ! $file->isDir() ) {
					return true;
				}

				// artifacts/ is a Playwright output directory: gitignored, never
				// deployed, and recreated on any failing run.
				return ! in_array( $file->getFilename(), array( 'vendor', 'node_modules', '.git', 'artifacts' ), true );
			}
		);

		foreach ( new RecursiveIteratorIterator( $directories ) as $file ) {
			if ( 'php' === strtolower( $file->getExtension() ) ) {
				$found[ dirname( $file->getPathname() ) ] = true;
			}
		}

		return array_keys( $found );
	}

	public function test_version_matches_everywhere() {
		$this->assertStringContainsString( ' * Version: ' . self::VERSION, $this->plugin_file() );
		$this->assertStringContainsString( "define( 'WP_SERVERINFO_VERSION', '" . self::VERSION . "' );", $this->plugin_file() );
		$this->assertStringContainsString( 'Stable tag: ' . self::VERSION, $this->readme() );
		$this->assertSame( self::VERSION, WP_SERVERINFO_VERSION );
	}

	public function test_the_changelog_has_a_section_for_this_version() {
		$this->assertStringContainsString( '### ' . self::VERSION . "\n", $this->readme() );
	}

	/**
	 * The order is neither alphabetical nor intuitive -- Requires at least and
	 * Requires PHP sit before Author -- so it is copied, never composed.
	 */
	public function test_the_plugin_header_fields_are_in_the_canonical_order() {
		$expected = array(
			'Plugin Name',
			'Plugin URI',
			'Description',
			'Version',
			'Requires at least',
			'Requires PHP',
			'Author',
			'Author URI',
			'License',
			'License URI',
			'Text Domain',
			'Domain Path',
		);

		preg_match( '#^<\?php\s*/\*\*(.+?)\*/#s', $this->plugin_file(), $matches );
		$this->assertNotEmpty( $matches, 'The plugin file must open with a docblock header.' );

		preg_match_all( '/^\s*\*\s*([A-Z][A-Za-z ]*?):\s/m', $matches[1], $fields );

		$this->assertSame( $expected, $fields[1] );
	}

	/**
	 * The readme order differs from the PHP one on purpose: Requires PHP comes
	 * after Stable tag here. They are not to be harmonised.
	 */
	public function test_the_readme_header_fields_are_in_the_canonical_order() {
		$expected = array(
			'Contributors',
			'Donate link',
			'Tags',
			'Requires at least',
			'Tested up to',
			'Stable tag',
			'Requires PHP',
			'License',
			'License URI',
		);

		$header = substr( $this->readme(), 0, (int) strpos( $this->readme(), "\n\n" ) );

		preg_match_all( '/^([A-Z][A-Za-z ]*?):\s/m', $header, $fields );

		$this->assertSame( $expected, $fields[1] );
	}

	public function test_requires_headers_match_readme() {
		$this->assertStringContainsString( ' * Requires at least: 6.8', $this->plugin_file() );
		$this->assertStringContainsString( ' * Requires PHP: 8.2', $this->plugin_file() );
		$this->assertStringContainsString( 'Requires at least: 6.8', $this->readme() );
		$this->assertStringContainsString( 'Requires PHP: 8.2', $this->readme() );
	}

	/**
	 * Header lines need two trailing spaces to render as separate lines.
	 *
	 * Markdown joins consecutive lines into one paragraph unless each is ended
	 * with a hard line break, so a missing pair renders as
	 * "License: GPLv2 or later License URI: https://..." on GitHub. It is
	 * invisible in the source and in a diff, which is exactly why it wants a
	 * test. The last line needs none, having nothing after it to run into.
	 */
	public function test_every_readme_header_line_keeps_its_line_break() {
		$header = substr( $this->readme(), 0, (int) strpos( $this->readme(), "\n\n" ) );
		$lines  = explode( "\n", $header );

		// The first line is the "# WP-ServerInfo" heading, not a header field.
		$fields = array_slice( $lines, 1 );
		$last   = array_pop( $fields );

		$this->assertCount( 8, $fields, 'Nine header fields, eight of them followed by another.' );

		foreach ( $fields as $line ) {
			$this->assertStringEndsWith(
				'  ',
				$line,
				"Needs two trailing spaces or it merges with the line below: {$line}"
			);
		}

		$this->assertStringStartsWith( 'License URI:', $last );
		$this->assertSame( rtrim( $last ), $last, 'The last header line takes no trailing spaces.' );
	}

	public function test_the_readme_lists_at_most_five_tags() {
		preg_match( '/^Tags:\s*(.+?)\s*$/m', $this->readme(), $matches );

		$this->assertNotEmpty( $matches, 'The readme must carry a Tags line.' );
		$this->assertLessThanOrEqual( 5, count( explode( ',', $matches[1] ) ) );
	}

	/**
	 * Bare versions: "### 3.0.0", never "### Version 3.0.0".
	 */
	public function test_every_changelog_heading_is_a_bare_version() {
		$this->assertSame( 0, preg_match( '/^### Version /m', $this->readme() ) );
	}

	public function test_canonical_lesterchan_urls() {
		$this->assertSame(
			'https://lesterchan.net/portfolio/programming/php/',
			$this->header_field( 'Plugin URI' )
		);
		$this->assertSame( 'https://lesterchan.net', $this->header_field( 'Author URI' ) );
		$this->assertSame( 'https://lesterchan.net/site/donation/', $this->readme_field( 'Donate link' ) );
		$this->assertSame(
			'https://www.gnu.org/licenses/gpl-2.0.html',
			$this->header_field( 'License URI' )
		);
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->readme_field( 'License URI' ) );
	}

	/**
	 * One name, in every plugin. A second contributor has to be added on
	 * wordpress.org as well, so a name here that is not on the listing silently
	 * does nothing.
	 */
	public function test_contributors_is_gamerz_only() {
		$this->assertSame( 'GamerZ', $this->readme_field( 'Contributors' ) );
	}

	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'wp-serverinfo', $this->header_field( 'Text Domain' ) );
		$this->assertSame( '/languages', $this->header_field( 'Domain Path' ) );
		$this->assertSame( 'wp-serverinfo', WP_SERVERINFO_SLUG );
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

		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ) );
		$this->assertStringContainsString( 'either version 2 of the License, or', $source );
		$this->assertStringContainsString( '(at your option) any later version.', $source );
		$this->assertStringContainsString(
			'Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)',
			$source,
			'Two spaces after the year and around "email :".'
		);
	}

	/**
	 * The second-level headings are a closed set in a fixed order.
	 *
	 * Third-level ones are not: Features, the usage subsections and every
	 * changelog version live below these.
	 */
	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## (.+?)\s*$/m', $this->readme(), $sections );

		$this->assertSame(
			array(
				'Description',
				'Usage',
				'Frequently Asked Questions',
				'Screenshots',
				'Changelog',
				'Upgrade Notice',
			),
			$sections[1]
		);
	}

	/**
	 * Donations is mandated wording, and its position is mandated too: the last
	 * h3 of ## Description, so it does not end up under Usage or the FAQ.
	 */
	public function test_donations_is_the_last_h3_of_the_description() {
		$readme      = $this->readme();
		$description = substr( $readme, (int) strpos( $readme, '## Description' ) );
		$description = substr( $description, 0, (int) strpos( $description, "\n## Usage" ) );

		preg_match_all( '/^### (.+?)\s*$/m', $description, $headings );

		$this->assertNotEmpty( $headings[1], '## Description must carry at least the Donations h3.' );
		$this->assertSame( 'Donations', end( $headings[1] ) );

		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting these plugins,'
			. ' if you really love my plugins and could spare me a couple of bucks, I will really appreciate it.'
			. ' If not feel free to use it without any obligations.',
			$description
		);

		// A plain paragraph, never a bullet: five plugins carried a stray "* ".
		$this->assertStringNotContainsString( '* I spent most of my free time', $description );
	}

	/**
	 * Five prefixes, and nothing else.
	 *
	 * The listing on wordpress.org renders the changelog verbatim, so a stray
	 * "Important:" or a lowercase "New:" is visible to every reader of it.
	 */
	public function test_changelog_prefixes_are_canonical() {
		$readme    = $this->readme();
		$changelog = substr( $readme, (int) strpos( $readme, '## Changelog' ) );
		$changelog = substr( $changelog, 0, (int) strpos( $changelog, "\n## Upgrade Notice" ) );

		preg_match_all( '/^\* (.+?):/m', $changelog, $bullets );

		$this->assertNotEmpty( $bullets[1], 'The changelog must carry bullets.' );

		foreach ( $bullets[1] as $prefix ) {
			$this->assertContains(
				$prefix . ':',
				array( 'BREAKING:', 'NEW:', 'CHANGED:', 'FIXED:', 'NOTE:' ),
				"'{$prefix}:' is not one of the five allowed changelog prefixes."
			);
		}
	}

	/**
	 * The raised floors are themselves a breaking change (section 14.1), and the
	 * one most likely to bite: a site on an older stack is simply never offered
	 * the update, with nothing anywhere to say why.
	 */
	public function test_the_upgrade_notice_covers_the_raised_floors() {
		$readme = $this->readme();
		$notice = substr( $readme, (int) strpos( $readme, '## Upgrade Notice' ) );

		$this->assertStringContainsString( '### ' . self::VERSION, $notice );
		$this->assertStringContainsString( 'WordPress 6.8', $notice );
		$this->assertStringContainsString( 'PHP 8.2', $notice );

		$this->assertMatchesRegularExpression(
			'/^\* BREAKING: Requires WordPress 6\.8 and PHP 8\.2/m',
			$readme,
			'The floors need a BREAKING: changelog line as well as the notice.'
		);
	}

	/**
	 * Both surfaces moved into one menu entry under Tools in 3.0.0, so the
	 * notice has to give the new URL -- the old one is bookmarked.
	 */
	public function test_the_upgrade_notice_records_the_screen_moving() {
		$notice = substr( $this->readme(), (int) strpos( $this->readme(), '## Upgrade Notice' ) );

		$this->assertStringContainsString( 'tools.php?page=wp-serverinfo', $notice );
		$this->assertStringContainsString( 'index.php?page=wp-serverinfo', $notice );
	}

	/**
	 * Every hook the plugin fires carries the plugin's prefix, and is named in
	 * the readme so it is findable without reading the source.
	 */
	public function test_every_fired_hook_is_prefixed_and_documented() {
		$code = wp_serverinfo_test_source_code();

		preg_match_all(
			"/(?:apply_filters|do_action)(?:_ref_array)?\(\s*'([a-z0-9_]+)'/",
			$code,
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
			$fired
		);

		foreach ( $fired as $hook ) {
			$this->assertStringContainsString( '`' . $hook . '`', $this->readme(), "{$hook} is undocumented." );
		}
	}

	/**
	 * Each one needs a docblock carrying an @since immediately above it, per WPCS.
	 *
	 * Asserted by reading the lines above the call rather than by one regex over
	 * the whole call site, because the three are spelled differently -- one is a
	 * return statement, two are assignments -- and a pattern loose enough to
	 * cover all three is loose enough to match a hook with nothing above it.
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
	 * The catalogue comes from translate.wordpress.org, and since WP 6.7 calling
	 * load_plugin_textdomain() this early trips _doing_it_wrong.
	 */
	public function test_the_plugin_does_not_load_its_own_textdomain() {
		$this->assertStringNotContainsString( 'load_plugin_textdomain', wp_serverinfo_test_source_code() );
	}

	/**
	 * Every translation call must carry the plugin's own text domain.
	 */
	public function test_every_translation_call_uses_the_plugin_text_domain() {
		$code = wp_serverinfo_test_source_code();

		preg_match_all( '/(?:__|_n|_x|esc_html__|esc_attr__|esc_html_e|esc_attr_e)\((.*?)\);/s', $code, $calls );

		foreach ( $calls[1] as $arguments ) {
			$this->assertStringContainsString(
				"'wp-serverinfo'",
				$arguments,
				"A translation call is missing the text domain: {$arguments}"
			);
		}
	}

	/**
	 * The old forums.lesterchan.net is gone, and the rest of these had drifted
	 * to http over twenty years. Code spans are exempt: they document input.
	 */
	public function test_no_insecure_or_dead_links_remain() {
		$readme = preg_replace( '/`[^`]*`/', '', $this->readme() );

		$this->assertSame( 0, preg_match( '#http://#', $readme ), 'Every readme link must use https.' );
		$this->assertSame( 0, preg_match( '#http://#', $this->plugin_file() ) );
		$this->assertStringNotContainsString( 'forums.lesterchan.net', $readme );
	}

	public function test_every_directory_has_an_index_php() {
		$directories = $this->php_directories();

		$this->assertNotEmpty( $directories, 'The walk found no PHP at all, which cannot be right.' );

		foreach ( $directories as $directory ) {
			$this->assertFileExists(
				$directory . '/index.php',
				"{$directory} ships PHP and so needs an index.php silence guard."
			);
		}
	}

	public function test_the_guards_use_the_docblock_form() {
		foreach ( $this->php_directories() as $directory ) {
			$guard = (string) file_get_contents( $directory . '/index.php' );

			// phpcbf cannot fix the one-line "// Silence is golden." form.
			$this->assertStringContainsString( '/**', $guard, "{$directory}/index.php must use the docblock form." );
			$this->assertStringContainsString( 'Silence is golden.', $guard );
		}
	}

	public function test_the_gpl_licence_is_shipped() {
		$licence = wp_serverinfo_test_read( 'LICENSE' );

		$this->assertStringContainsString( 'GNU GENERAL PUBLIC LICENSE', $licence );
		$this->assertStringContainsString( 'Version 2, June 1991', $licence );
	}

	/**
	 * The catalogue is built by translate.wordpress.org, and Travis has been
	 * dead for these repos for years.
	 */
	public function test_no_abandoned_build_or_translation_artefacts_ship() {
		$root = dirname( __DIR__ );

		$this->assertFileDoesNotExist( $root . '/.travis.yml' );
		$this->assertFileDoesNotExist( $root . '/.wp-env.override.json' );
		$this->assertDirectoryDoesNotExist( $root . '/languages' );
		$this->assertDirectoryDoesNotExist( $root . '/.idea' );

		foreach ( array( 'pot', 'po', 'mo' ) as $extension ) {
			$this->assertSame(
				array(),
				(array) glob( $root . '/*.' . $extension ),
				"No .{$extension} files: translate.wordpress.org builds the catalogue."
			);
		}
	}

	/**
	 * WP-ServerInfo ships no JavaScript at all, which is the strongest form of
	 * the house rule: nothing to enqueue, so nothing to depend on jQuery. There
	 * is therefore no registered handle whose deps could be checked, and the
	 * source assertion is the whole of it.
	 */
	public function test_no_jquery_is_enqueued() {
		$code = wp_serverinfo_test_source_code();

		$this->assertStringNotContainsStringIgnoringCase( 'jquery', $code );
		$this->assertStringNotContainsString(
			'wp_enqueue_script(',
			$code,
			'The plugin registers no scripts, so it can declare no dependencies.'
		);
		$this->assertSame( array(), (array) glob( dirname( __DIR__ ) . '/js/*.js' ) );
		$this->assertDirectoryDoesNotExist( dirname( __DIR__ ) . '/js' );
	}

	/**
	 * No plugin in this family ships a second, mirrored stylesheet: the admin
	 * screens use direction-neutral markup instead, so one sheet would serve
	 * both directions. This one ships no stylesheet at all -- the direction is a
	 * dir attribute, which is why there is no css/ directory to keep in step.
	 */
	public function test_no_rtl_stylesheet_is_registered() {
		$root = dirname( __DIR__ );

		$this->assertSame( array(), (array) glob( $root . '/*-rtl.css' ) );
		$this->assertSame( array(), (array) glob( $root . '/css/*-rtl.css' ) );
		$this->assertStringNotContainsString(
			'wp_style_add_data',
			wp_serverinfo_test_source_code(),
			"No plugin registers 'rtl' style data."
		);
	}

	/**
	 * Section 4.4 forbids these outright, and section 5 forbids !important. Both
	 * were in the 2.0.0 screens, and both are gone; nothing renders here, so
	 * this is the source-level half of the assertion the screen tests make on
	 * the rendered markup.
	 */
	public function test_no_inline_styling_survives_in_the_source() {
		$code = wp_serverinfo_test_source_code();

		$this->assertStringNotContainsString( '!important', $code );
		$this->assertStringNotContainsString( '<style', $code );
		$this->assertDoesNotMatchRegularExpression( '/\s(style|valign|align)=/', $code );
		$this->assertDoesNotMatchRegularExpression( '/<(td|th|table|div)[^>]*\swidth=/', $code );
	}

	/**
	 * The widget id lives in two places and has to agree in both.
	 *
	 * The uninstaller runs with the plugin inactive, so it cannot read
	 * WP_SERVERINFO_WIDGET_ID and spells the string out instead. If the constant
	 * ever changes, the uninstaller silently stops finding the user meta it is
	 * there to remove, which nothing else would catch.
	 */
	public function test_the_uninstaller_agrees_with_the_widget_id_constant() {
		$this->assertSame( 'dashboard_serverinfo', WP_SERVERINFO_WIDGET_ID );
		$this->assertStringContainsString(
			"\$widget_id = '" . WP_SERVERINFO_WIDGET_ID . "';",
			wp_serverinfo_test_read( 'uninstall.php' )
		);
	}

	/**
	 * The plugin writes no option row at all, ever.
	 *
	 * WP-ServerInfo is five read-only tables describing the host and a dashboard
	 * widget. It keeps no state between requests, so under STANDARDS.md 2.1 it
	 * stores nothing -- not a settings row, and not the version markers either.
	 * Those exist to tell a migration what it is upgrading from, and there is no
	 * migration and nothing to migrate.
	 */
	public function test_the_plugin_stores_nothing() {
		// admin_init is deliberately not fired: core's callbacks on it send
		// headers, which PHPUnit has already begun output past.
		do_action( 'plugins_loaded' );
		do_action( 'init' );

		global $wpdb;

		$rows = (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( 'wp_serverinfo_' ) . '%'
			)
		);

		$this->assertSame( array(), $rows, 'WP-ServerInfo wrote an option row; it is meant to store nothing at all.' );
	}

	/**
	 * There is still no settings row, and nothing that would build one.
	 */
	public function test_no_settings_row_and_no_settings_api() {
		$this->assertFalse(
			get_option( 'wp_serverinfo_options' ),
			'WP-ServerInfo is exempt from the settings row; reinstating one needs the sanitiser test back.'
		);

		$code = wp_serverinfo_test_source_code();

		$this->assertStringNotContainsString( 'register_setting', $code );
		$this->assertStringNotContainsString( 'add_settings_field', $code );
		$this->assertStringNotContainsString( 'sanitize_callback', $code );
	}

	/**
	 * Deleting the plugin leaves nothing behind.
	 *
	 * The assertion is deliberately a LIKE over wp_options rather than a
	 * delete_option() check: a row added later and forgotten in uninstall.php is
	 * exactly the failure this is here to catch. The multisite config runs the
	 * same test through uninstall.php's get_sites() branch.
	 */
	public function test_uninstall_removes_every_option_row() {
		// Written by hand: nothing in the plugin writes this any more. An early
		// build of the unreleased 3.0.0 did, and uninstall is the only thing that
		// will ever take it off a site that ran that build.
		update_option( 'wp_serverinfo_version', array( 'plugin' => '3.0.0' ) );

		$this->assertNotEmpty(
			$this->stored_option_names(),
			'There should be rows to remove before uninstall runs.'
		);

		$this->run_uninstall();

		wp_cache_flush();

		$this->assertSame(
			array(),
			$this->stored_option_names(),
			'uninstall.php must remove every wp_serverinfo_* row.'
		);
	}

	/**
	 * A field from the main plugin file's header docblock.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function header_field( $field ) {
		$data = get_file_data( dirname( __DIR__ ) . '/wp-serverinfo.php', array( $field => $field ) );

		return $data[ $field ];
	}

	/**
	 * A field from the readme's header block.
	 *
	 * @param string $field Field name.
	 * @return string
	 */
	protected function readme_field( $field ) {
		preg_match( '/^' . preg_quote( $field, '/' ) . ':\s*(.+?)\s*$/m', $this->readme(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}
}
