<?php
/**
 * Tests for right-to-left image selection.
 *
 * Only the half-star images ship in RTL variants, and only for some sets, so
 * the fallbacks matter as much as the selection.
 *
 * @package WP-PostRatings
 */

/**
 * Image selection under an RTL locale.
 *
 * @covers Postratings_Template::ratings_images
 * @covers Postratings_Template::ratings_images_vote
 */
class Test_Postratings_Rtl extends WP_PostRatings_TestCase {

	/**
	 * Force the locale's text direction.
	 *
	 * The is_rtl() helper reads WP_Locale::is_rtl(), which compares this
	 * property, so setting it avoids loading a real RTL locale.
	 *
	 * @param bool $rtl Whether to report right-to-left.
	 *
	 * @return void
	 */
	private function set_rtl( $rtl ) {
		$GLOBALS['wp_locale']->text_direction = $rtl ? 'rtl' : 'ltr';
	}

	/**
	 * Restore the direction between tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		$this->set_rtl( false );

		parent::tear_down();
	}

	/**
	 * A normal set uses its RTL half image under an RTL locale.
	 *
	 * @return void
	 */
	public function test_a_normal_set_uses_the_rtl_half_image() {
		$this->set_rtl( true );

		// Rating 2.5 of 5 puts the half image at position 3.
		$html = Postratings_Template::ratings_images( 0, 5, 2.5, 'stars', 'Alt', 3 );

		$this->assertStringContainsString( 'rating_half-rtl.gif', $html );
	}

	/**
	 * The same set uses the plain half image otherwise.
	 *
	 * @return void
	 */
	public function test_a_normal_set_uses_the_plain_half_image_in_ltr() {
		$this->set_rtl( false );

		$html = Postratings_Template::ratings_images( 0, 5, 2.5, 'stars', 'Alt', 3 );

		$this->assertStringContainsString( 'rating_half.gif', $html );
		$this->assertStringNotContainsString( 'rating_half-rtl.gif', $html );
	}

	/**
	 * A custom set uses its per-step RTL half image.
	 *
	 * The original looked for "rating_3half-rtl" -- no underscore -- so this
	 * never matched and RTL sites using a custom set silently got the LTR half
	 * image. The numbers set ships rating_N_half-rtl.gif for every step, which
	 * is what makes this observable.
	 *
	 * @return void
	 */
	public function test_a_custom_set_uses_its_per_step_rtl_half_image() {
		$this->set_rtl( true );

		$html = Postratings_Template::ratings_images( 1, 5, 2.5, 'numbers', 'Alt', 3 );

		$this->assertStringContainsString( 'rating_3_half-rtl.gif', $html );
	}

	/**
	 * A set without an RTL half falls back rather than emitting a 404.
	 *
	 * @return void
	 */
	public function test_a_set_without_an_rtl_half_falls_back() {
		$this->set_rtl( true );

		$html = Postratings_Template::ratings_images( 1, 2, 0.5, 'thumbs', 'Alt', 1 );

		$this->assertStringNotContainsString( '-rtl', $html );
		$this->assertStringContainsString( 'rating_1_half.gif', $html );
	}

	/**
	 * The vote markup tells the script whether an RTL half exists.
	 *
	 * The script restores the strip on mouseout from these attributes, so a
	 * wrong flag means the wrong image after a hover.
	 *
	 * @return void
	 */
	public function test_the_vote_markup_flags_an_available_rtl_half() {
		$this->set_rtl( true );

		$html = Postratings_Template::ratings_images_vote( 1, 0, 5, 2.5, 'stars', 'Alt', 3, array() );

		$this->assertStringContainsString( 'data-half-rtl="1"', $html );
	}

	/**
	 * That flag is off when the set has no RTL half.
	 *
	 * @return void
	 */
	public function test_the_vote_markup_does_not_flag_a_missing_rtl_half() {
		$this->set_rtl( true );

		$html = Postratings_Template::ratings_images_vote( 1, 1, 2, 0.5, 'thumbs', 'Alt', 1, array() );

		$this->assertStringContainsString( 'data-half-rtl="0"', $html );
	}

	/**
	 * The flag is off under an LTR locale even when an RTL half exists.
	 *
	 * @return void
	 */
	public function test_the_flag_is_off_in_ltr() {
		$this->set_rtl( false );

		$html = Postratings_Template::ratings_images_vote( 1, 0, 5, 2.5, 'stars', 'Alt', 3, array() );

		$this->assertStringContainsString( 'data-half-rtl="0"', $html );
	}

	/**
	 * No shipped set has RTL start or end images, so those never appear.
	 *
	 * Documents the fallback rather than the feature: the branches exist, and
	 * this is what keeps them from silently emitting a missing file if a set
	 * ever gains one asymmetrically.
	 *
	 * @return void
	 */
	public function test_no_shipped_set_emits_an_rtl_start_or_end_image() {
		$this->set_rtl( true );

		foreach ( Postratings_Template::image_folders() as $folder ) {
			$html = Postratings_Template::ratings_images( 0, 5, 3, $folder, 'Alt', 0 );

			$this->assertStringNotContainsString( 'rating_start-rtl', $html );
			$this->assertStringNotContainsString( 'rating_end-rtl', $html );
		}
	}

	/**
	 * Every image the RTL strip references is actually on disk.
	 *
	 * A missing file is a 404 in the page, which no markup assertion catches.
	 *
	 * @return void
	 */
	public function test_every_referenced_rtl_image_exists() {
		$this->set_rtl( true );

		foreach ( array( 'stars', 'numbers', 'bars', 'squares' ) as $folder ) {
			$info   = Postratings_Template::folder_info( $folder );
			$custom = (int) $info['custom'];
			$max    = (int) $info['max'];

			$html = Postratings_Template::ratings_images( $custom, $max, $max / 2, $folder, 'Alt', (int) ceil( $max / 2 ) );

			preg_match_all( '#images/[^"]+#', $html, $matches );

			$this->assertNotEmpty( $matches[0], $folder . ' rendered no images' );

			foreach ( $matches[0] as $path ) {
				$this->assertFileExists( WP_POSTRATINGS_DIR . $path, $path . ' is referenced but missing' );
			}
		}
	}
}
