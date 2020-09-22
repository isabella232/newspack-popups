<?php
/**
 * Class API Test
 *
 * @package Newspack_Popups
 */

/**
 * API test case.
 */
class APITest extends WP_UnitTestCase {
	private static $settings             = []; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $maybe_show_campaign  = null; // phpcs:ignore Squiz.Commenting.VariableComment.Missing
	private static $report_campaign_data = null; // phpcs:ignore Squiz.Commenting.VariableComment.Missing

	public static function wpSetUpBeforeClass() { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		self::$maybe_show_campaign  = new Maybe_Show_Campaign();
		self::$report_campaign_data = new Report_Campaign_Data();
		self::$settings             = (object) [ // phpcs:ignore Squiz.Commenting.VariableComment.Missing
			'suppress_newsletter_campaigns' => true,
			'suppress_all_newsletter_campaigns_if_one_dismissed' => true,
		];
	}

	public static function create_test_popup( $options, $post_content = 'Faucibus placerat senectus metus molestie varius tincidunt.' ) { // phpcs:ignore Squiz.Commenting.FunctionComment.Missing
		$popup_id = self::factory()->post->create(
			[
				'post_type'    => Newspack_Popups::NEWSPACK_PLUGINS_CPT,
				'post_title'   => 'Platea fames',
				'post_content' => $post_content,
			]
		);
		Newspack_Popups_Model::set_popup_options( $popup_id, $options );
		$payload = (object) Newspack_Popups_Inserter::create_single_popup_access_payload(
			Newspack_Popups_Model::create_popup_object( get_post( $popup_id ) )
		);
		return [
			'id'      => $popup_id,
			'payload' => $payload,
		];
	}

	/**
	 * Suppression caused by "once" frequency.
	 */
	public function test_once_frequency() {
		$test_popup = self::create_test_popup( [ 'frequency' => 'once' ] );
		Newspack_Popups_Model::set_sitewide_popup( $test_popup['id'] );
		$client_id = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => $client_id,
				'popup_id' => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a single reported view.'
		);
	}

	/**
	 * Suppression caused by "daily" frequency.
	 */
	public function test_daily_frequency() {
		$test_popup = self::create_test_popup( [ 'frequency' => 'daily' ] );
		Newspack_Popups_Model::set_sitewide_popup( $test_popup['id'] );
		$client_id = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a view.
		self::$report_campaign_data->report_campaign(
			[
				'cid'      => $client_id,
				'popup_id' => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a single reported view.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_popup['payload'],
				self::$settings,
				'',
				strtotime( '+1 day 1 hour' )
			),
			'Assert visible after a day has passed.'
		);
	}

	/**
	 * Suppression caused by permanent dismissal.
	 */
	public function test_permanent_dismissal() {
		$test_popup = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			]
		);
		$client_id  = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);
		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert visible on a subsequent visit.'
		);

		// Dismiss permanently.
		self::$report_campaign_data->report_campaign(
			[
				'cid'              => $client_id,
				'popup_id'         => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
				'suppress_forever' => true,
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after a permanently dismissed.'
		);
	}

	/**
	 * Suppression by UTM source.
	 */
	public function test_utm_source_suppression() {
		$test_popup_a = self::create_test_popup(
			[
				'placement'       => 'inline',
				'frequency'       => 'always',
				'utm_suppression' => 'Our Newsletter',
			]
		);
		$client_id    = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_a['payload'], self::$settings ),
			'Assert visible without referer.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_popup_a['payload'],
				self::$settings,
				'http://example.com?utm_source=twitter'
			),
			'Assert shown when a referer is set, but not the one to be suppressed.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_popup_a['payload'],
				self::$settings,
				'http://example.com?utm_source=Our+Newsletter'
			),
			'Assert not shown when a referer is set, using plus sign as space.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_a['payload'], self::$settings ),
			'Assert not shown on a subsequent visit, without the UTM source in the URL.'
		);

		$test_popup_b = self::create_test_popup(
			[
				'placement'       => 'inline',
				'frequency'       => 'always',
				'utm_suppression' => 'Our Newsletter',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_popup_b['payload'],
				self::$settings,
				'http://example.com?utm_source=Our%20Newsletter'
			),
			'Assert not shown when a referer is set, using %20 as space.'
		);
	}

	/**
	 * Suppression by UTM medium.
	 */
	public function test_utm_medium_suppression() {
		$test_popup = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			]
		);
		$client_id  = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_popup['payload'],
				self::$settings,
				'http://example.com?utm_medium=email'
			),
			'Assert visible with email utm_medium, but no newsletter form in content.'
		);

		$test_newsletter_popup = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_newsletter_popup['payload'], self::$settings ),
			'Assert visible without referer.'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_newsletter_popup['payload'],
				self::$settings,
				'http://example.com?utm_medium=conduit'
			),
			'Assert visible with referer and non-email utm_medium.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_newsletter_popup['payload'],
				self::$settings,
				'http://example.com?utm_medium=email'
			),
			'Assert not shown with email utm_medium.'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_newsletter_popup['payload'],
				self::$settings
			),
			'Assert not shown on a subsequent visit, without the UTM medium in the URL.'
		);

		$modified_settings                                = clone self::$settings;
		$modified_settings->suppress_newsletter_campaigns = false;

		$test_newsletter_popup_a = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown(
				$client_id,
				$test_newsletter_popup_a['payload'],
				$modified_settings,
				'http://example.com?utm_medium=email'
			),
			'Assert shown with email utm_medium if the perinent setting is off.'
		);
	}

	/**
	 * Suppression of a *different* newsletter campaign.
	 * By default, if a visitor suppresses a newsletter campaign, they will not
	 * be shown other newsletter campaigns.
	 */
	public function test_different_newsletter_campaign_suppression() {
		$test_popup_a = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);
		$client_id    = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_a['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Dismiss permanently.
		self::$report_campaign_data->report_campaign(
			[
				'cid'                 => $client_id,
				'popup_id'            => Newspack_Popups_Model::canonize_popup_id( $test_popup_a['id'] ),
				'suppress_forever'    => true,
				'is_newsletter_popup' => true,
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_a['payload'], self::$settings ),
			'Assert not visible after permanent dismissal.'
		);

		$test_popup_b = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_b['payload'], self::$settings ),
			'Assert the other newsletter popup is not shown.'
		);

		$modified_settings = clone self::$settings;
		$modified_settings->suppress_all_newsletter_campaigns_if_one_dismissed = false;
		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_b['payload'], $modified_settings ),
			'Assert the other newsletter popup is shown if the pertinent setting is off.'
		);

		$test_popup_c = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			]
		);

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup_c['payload'], self::$settings ),
			'Assert a non-newsletter campaign is displayed.'
		);
	}

	/**
	 * Suppression caused by a newsletter subscription.
	 */
	public function test_newsletter_subscription() {
		$test_popup = self::create_test_popup(
			[
				'placement' => 'inline',
				'frequency' => 'always',
			],
			'<!-- wp:jetpack/mailchimp --><!-- wp:jetpack/button {"element":"button","uniqueId":"mailchimp-widget-id","text":"Join my email list"} /--><!-- /wp:jetpack/mailchimp -->'
		);
		$client_id  = 'amp-123';

		self::assertTrue(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert initially visible.'
		);

		// Report a subscription.
		self::$report_campaign_data->report_campaign(
			[
				'cid'                 => $client_id,
				'popup_id'            => Newspack_Popups_Model::canonize_popup_id( $test_popup['id'] ),
				'mailing_list_status' => 'subscribed',
				'email'               => 'foo@bar.com',
			]
		);

		self::assertFalse(
			self::$maybe_show_campaign->should_campaign_be_shown( $client_id, $test_popup['payload'], self::$settings ),
			'Assert not shown after subscribed.'
		);
	}
}