<?php
/**
 * Tests for client IP resolution.
 *
 * The stored value keys de-duplication, so changing it for well-formed input
 * would orphan every row already recorded.
 *
 * @package WP-PostRatings
 */

/**
 * Address resolution, proxy header parsing and hashing.
 *
 * @covers WP_PostRatings_Rating::get_raw_ip
 * @covers WP_PostRatings_Rating::get_ip
 * @covers WP_PostRatings_Rating::parse_ip_header
 */
class WP_PostRatings_Ip_Test extends WP_PostRatings_TestCase {

	/**
	 * REMOTE_ADDR passes through unchanged.
	 *
	 * Pinned because the hash of it is what every existing row is keyed on.
	 *
	 * @return void
	 */
	public function test_remote_addr_is_unchanged() {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';

		$this->assertSame( '198.51.100.7', WP_PostRatings_Rating::get_raw_ip(), 'The remote address is used as it stands.' );
		$this->assertSame( wp_hash( '198.51.100.7' ), WP_PostRatings_Rating::get_ip(), 'And is hashed before it goes anywhere.' );
	}

	/**
	 * An IPv6 address survives too.
	 *
	 * @return void
	 */
	public function test_ipv6_remote_addr_is_unchanged() {
		$_SERVER['REMOTE_ADDR'] = '2001:db8::1';

		$this->assertSame( '2001:db8::1', WP_PostRatings_Rating::get_raw_ip(), 'An IPv6 remote address is used as it stands too.' );
	}

	/**
	 * Nothing but REMOTE_ADDR is consulted by default.
	 *
	 * @return void
	 */
	public function test_proxy_headers_are_ignored_by_default() {
		$_SERVER['REMOTE_ADDR']          = '198.51.100.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';

		$this->assertSame( '198.51.100.7', WP_PostRatings_Rating::get_raw_ip(), 'A proxy header is ignored until one is named.' );
	}

	/**
	 * A header the site named explicitly is honoured.
	 *
	 * @return void
	 */
	public function test_a_named_header_is_honoured() {
		$this->set_options( array( 'ip_header' => 'HTTP_X_FORWARDED_FOR' ) );

		$_SERVER['REMOTE_ADDR']          = '198.51.100.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9';

		$this->assertSame( '203.0.113.9', WP_PostRatings_Rating::get_raw_ip(), 'The named header is what is read.' );
	}

	/**
	 * Only the first address in the chain is used.
	 *
	 * X-Forwarded-For is "client, proxy1, proxy2" and the visitor controls the
	 * left of it. Hashing the whole string meant appending one more hop gave a
	 * different identity, so vote limiting was bypassable indefinitely on any
	 * site that had configured the header.
	 *
	 * @return void
	 */
	public function test_only_the_first_address_in_the_chain_is_used() {
		$this->set_options( array( 'ip_header' => 'HTTP_X_FORWARDED_FOR' ) );

		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9, 10.0.0.1, 10.0.0.2';
		$first                           = WP_PostRatings_Rating::get_ip();

		// The visitor appends another hop, which used to yield a new identity.
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.9, 10.0.0.1, 10.0.0.2, 10.0.0.3';
		$second                          = WP_PostRatings_Rating::get_ip();

		$this->assertSame( $first, $second, 'appending a hop changed the identity' );
		$this->assertSame( wp_hash( '203.0.113.9' ), $first, 'Only the first address in the chain is used.' );
	}

	/**
	 * Junk in the header falls back to REMOTE_ADDR rather than being hashed.
	 *
	 * @return void
	 */
	public function test_a_junk_header_falls_back_to_remote_addr() {
		$this->set_options( array( 'ip_header' => 'HTTP_X_FORWARDED_FOR' ) );

		$_SERVER['REMOTE_ADDR']          = '198.51.100.7';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-address, <script>alert(1)</script>';

		$this->assertSame( '198.51.100.7', WP_PostRatings_Rating::get_raw_ip(), 'A junk header falls back to the remote address rather than to nothing.' );
	}

	/**
	 * The first *valid* address wins even if junk precedes it.
	 *
	 * @return void
	 */
	public function test_the_first_valid_address_wins() {
		$this->assertSame( '203.0.113.9', WP_PostRatings_Rating::parse_ip_header( 'garbage, 203.0.113.9, 10.0.0.1' ), 'The first address that parses wins.' );
		$this->assertSame( '', WP_PostRatings_Rating::parse_ip_header( 'garbage, more garbage' ), 'And a header with none yields nothing.' );
	}

	/**
	 * An absent REMOTE_ADDR resolves to an empty string, not a warning.
	 *
	 * @return void
	 */
	public function test_a_missing_remote_addr_is_empty() {
		unset( $_SERVER['REMOTE_ADDR'] );

		$this->assertSame( '', WP_PostRatings_Rating::get_raw_ip(), 'With no remote address there is no address.' );
		$this->assertSame( '', WP_PostRatings_Rating::get_hostname(), 'And nothing to look up.' );
	}

	/**
	 * The hash is filterable.
	 *
	 * @return void
	 */
	public function test_the_address_is_filterable() {
		add_filter( 'wp_postratings_ipaddress', static fn() => 'filtered' );

		$this->assertSame( 'filtered', WP_PostRatings_Rating::get_ip(), 'The address is filterable.' );
	}

	/**
	 * The setting only accepts something shaped like a $_SERVER key.
	 *
	 * @return void
	 */
	public function test_the_header_setting_is_sanitized() {
		$clean = WP_PostRatings_Options::sanitize( array( 'ip_header' => 'http-x-forwarded-for' ) );
		$this->assertSame( '', $clean['ip_header'], 'a hyphenated header cannot match a $_SERVER key' );

		$clean = WP_PostRatings_Options::sanitize( array( 'ip_header' => 'http_cf_connecting_ip' ) );
		$this->assertSame( 'HTTP_CF_CONNECTING_IP', $clean['ip_header'], 'The header setting is sanitized into a superglobal key.' );
	}
}
