# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What it is

A read-only report about the host: server and WordPress general info, every PHP
directive, every MySQL variable and table status, and memcached / Redis
statistics. One tabbed screen under **Tools**, plus a core Dashboard widget with
the headline numbers. It writes nothing, configures nothing and has no settings
screen.

## Storage: none

No settings, no tables, not even a version marker row.

**But there is state, and it is not in `wp_options`.** WordPress records the
Dashboard widget's id in per-user meta the moment somebody closes, hides or
reorders it. That is why `WP_SERVERINFO_WIDGET_ID` (`dashboard_serverinfo`) is
a PHP constant and why `uninstall.php` is 100 lines rather than one: it strips
the id out of `closedpostboxes_dashboard`, `metaboxhidden_dashboard` and
`meta-box-order_dashboard` for every user. The last of those holds one
**comma-separated string per column**, so it needs splitting — deleting the key
outright would throw away the user's ordering of core's own widgets. 2.0.0 left
all of this behind forever.

The id is spelled out again inside `uninstall.php` because uninstall runs with
the plugin inactive and no constant loaded; `tests/test-metadata.php` asserts
the two copies still agree.

## Traps

* **The screen is `add_management_page()` under Tools, and that is a decision,
  not a default.** No settings form, so `add_options_page()` would file a report
  under Settings; no list tables, so a top-level menu claims a sidebar slot for
  five read-only tables. Before 3.0.0 it was a submenu of `index.php` — a report
  about the host inside the Dashboard menu, at a URL nobody could guess.
* **`WP_ServerInfo_MySQL` reads every row as an array (`ARRAY_A`), not an
  object.** `SHOW VARIABLES` and `SHOW TABLE STATUS` name their columns
  `Variable_name`, `Data_length` — MySQL's spellings, which WPCS would otherwise
  be asked to accept as property names.
* **`variable()` returns null for a missing variable rather than dereferencing
  the row.** Not hypothetical: MySQL 8.0 removed `query_cache_size` entirely.
* **`redis_stats()` catches `Throwable`, not `Exception`, and the comment says
  why.** phpredis throws `RedisException` for connection trouble, but
  `connect()` also raises `ValueError`/`TypeError` on PHP 8 for a malformed host
  or port — newly reachable now that the server is filterable. Those are Errors,
  they escaped the old catch list, and they took the whole Dashboard down.
* **`wp_serverinfo_memcached_server` and `wp_serverinfo_redis_server` are the
  only way to point those panels at a non-localhost server**, and both defend
  against a filter returning a non-array. The tabs for them appear only when the
  extension is loaded (`WP_ServerInfo_Admin::tabs()`), so a missing tab is a
  missing extension, not a bug.
* **`WP_ServerInfo_Dashboard` is a core dashboard widget** (`wp_add_dashboard_widget()`),
  not a `WP_Widget` subclass. There is nothing to convert.
* Every capability check — report and widget — goes through
  `WP_ServerInfo_Admin::capability( $context )` and the `wp_serverinfo_capability`
  filter. The default is `manage_options` because the page prints the document
  root, the server IP and every PHP directive.
* The `@codeCoverageIgnore` blocks around the memcached and Redis probes are
  deliberate: a test environment has neither extension nor a live server.
* All thirty pre-3.0.0 global functions are gone with no shims. Several were
  generic enough to collide — WP-DownloadManager defines its own
  `format_filesize()`, and whichever plugin loaded first won.

## Tests

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

`tests/` is one file per class plus `test-uninstall.php`, which is the
interesting one: it builds the three kinds of per-user dashboard meta and
asserts the widget id is removed from each *without* disturbing core's widgets.
`tests/e2e/serverinfo.spec.js` covers the tab strip and the widget's "View all"
link, which no PHPUnit test can see.

## Known discrepancies

* `wp-serverinfo.php:40` says "The last-run value is kept in the
  `wp_serverinfo_version` row." Nothing writes that row — commit `c407820`
  ("Store nothing at all") removed it. The README's 3.0.0 Upgrade Notice makes
  the same claim.
* `uninstall.php`'s docblock refers to `WP_ServerInfo_Options`, a class that
  does not exist.
* The README's Upgrade Notice opens "up from WordPress 4.0 and PHP 7.2", but the
  released 2.0.0 declared no `Requires PHP` at all.
