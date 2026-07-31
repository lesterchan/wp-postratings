<?php
/**
 * The WP_POSTRATINGS_TRUST_PROXY opt-in.
 *
 * @package WP-PostRatings
 */

/**
 * Tests for the reverse-proxy opt-in constant.
 *
 * WP-PostRatings reads a visitor's address to decide whether they have already
 * rated a post, which makes this the same decision WP-Email, WP-Polls, WP-Ban
 * and WP-UserOnline all make, and it is now made the same way in all five. It
 * runs in its own process because the opt-in is a constant: defining it in the
 * shared run would silently change what every other IP test is asserting.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @coversNothing
 */
class WP_PostRatings_Trust_Proxy_Test extends WP_PostRatings_TestCase {

	/**
	 * Set up the fixtures for each test.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$_SERVER['REMOTE_ADDR'] = '198.51.100.200';
	}

	/**
	 * Clear the request state after each test.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset(
			$_SERVER['REMOTE_ADDR'],
			$_SERVER['HTTP_X_FORWARDED_FOR'],
			$_SERVER['HTTP_CF_CONNECTING_IP'],
			$_SERVER['HTTP_X_REAL_IP']
		);

		parent::tear_down();
	}

	/**
	 * Without the opt-in a proxy header is ignored.
	 *
	 * The default, and the one that matters: the header is whatever the client
	 * typed, so trusting it by default would let anyone rate a post as many
	 * times as they cared to vary it.
	 *
	 * @return void
	 */
	public function test_a_proxy_header_is_ignored_by_default() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';

		$this->assertSame( '198.51.100.200', WP_PostRatings_Rating::get_raw_ip(), 'a proxy header was trusted without any opt-in' );
	}

	/**
	 * The constant opts into the usual headers.
	 *
	 * @return void
	 */
	public function test_the_constant_opts_into_the_proxy_headers() {
		define( 'WP_POSTRATINGS_TRUST_PROXY', true );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';

		$this->assertSame( '203.0.113.7', WP_PostRatings_Rating::get_raw_ip(), 'the constant did not opt into the proxy headers' );
	}

	/**
	 * Cloudflare's header is consulted before the generic one.
	 *
	 * @return void
	 */
	public function test_the_cloudflare_header_wins() {
		define( 'WP_POSTRATINGS_TRUST_PROXY', true );

		$_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.8';
		$_SERVER['HTTP_X_FORWARDED_FOR']  = '203.0.113.9';

		$this->assertSame( '203.0.113.8', WP_PostRatings_Rating::get_raw_ip(), 'the Cloudflare header should be preferred' );
	}

	/**
	 * X-Forwarded-For is a chain, and the client is the left of it.
	 *
	 * @return void
	 */
	public function test_the_first_address_in_a_chain_is_used() {
		define( 'WP_POSTRATINGS_TRUST_PROXY', true );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10, 70.41.3.18, 150.172.238.178';

		$this->assertSame( '203.0.113.10', WP_PostRatings_Rating::get_raw_ip(), 'the chain was not reduced to its first address' );
	}

	/**
	 * A header holding nothing usable falls back to REMOTE_ADDR.
	 *
	 * @return void
	 */
	public function test_a_junk_header_falls_back() {
		define( 'WP_POSTRATINGS_TRUST_PROXY', true );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip, still-not-an-ip';

		$this->assertSame( '198.51.100.200', WP_PostRatings_Rating::get_raw_ip(), 'a junk header did not fall back to REMOTE_ADDR' );
	}

	/**
	 * Naming a header is the narrower answer, so it wins.
	 *
	 * @return void
	 */
	public function test_a_named_header_still_wins() {
		define( 'WP_POSTRATINGS_TRUST_PROXY', true );

		$this->set_option( 'ip_header', 'HTTP_X_REAL_IP' );

		$_SERVER['HTTP_X_REAL_IP']       = '203.0.113.20';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.21';

		$this->assertSame( '203.0.113.20', WP_PostRatings_Rating::get_raw_ip(), 'the named header should beat the guessed ones' );
	}

	/**
	 * The filter is the last word, so the constant can be refused per request.
	 *
	 * @return void
	 */
	public function test_the_filter_can_override_the_constant() {
		define( 'WP_POSTRATINGS_TRUST_PROXY', true );

		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';

		add_filter( 'wp_postratings_trust_proxy', '__return_false' );

		$this->assertSame( '198.51.100.200', WP_PostRatings_Rating::get_raw_ip(), 'the filter could not refuse the constant' );

		remove_filter( 'wp_postratings_trust_proxy', '__return_false' );
	}
}
