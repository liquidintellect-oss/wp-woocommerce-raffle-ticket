<?php

use PHPUnit\Framework\TestCase;
use WpWoocommerceRaffleTicket\Admin\PluginSettings;

class PluginSettingsTest extends TestCase {

	private PluginSettings $settings;

	public function setUp(): void {
		WP_Mock::setUp();
		$this->settings = new PluginSettings();
	}

	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	// ── getLabel() ────────────────────────────────────────────────────────────

	/** @test */
	public function get_label_returns_default_when_option_is_not_set(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => PluginSettings::DEFAULT_LABEL,
			)
		);

		$this->assertSame( 'Raffle Tickets', PluginSettings::getLabel() );
	}

	/** @test */
	public function get_label_returns_custom_value_when_option_is_set(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => 'Lottery Tickets',
			)
		);

		$this->assertSame( 'Lottery Tickets', PluginSettings::getLabel() );
	}

	/** @test */
	public function get_label_falls_back_to_default_when_option_is_empty_string(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => '',
			)
		);

		$this->assertSame( 'Raffle Tickets', PluginSettings::getLabel() );
	}

	/** @test */
	public function get_label_falls_back_to_default_when_option_is_whitespace(): void {
		WP_Mock::userFunction(
			'get_option',
			array(
				'args'   => array( PluginSettings::OPTION_KEY, PluginSettings::DEFAULT_LABEL ),
				'return' => '   ',
			)
		);

		$this->assertSame( 'Raffle Tickets', PluginSettings::getLabel() );
	}

	// ── register() ────────────────────────────────────────────────────────────

	/** @test */
	public function register_hooks_register_settings_to_admin_init(): void {
		WP_Mock::expectActionAdded( 'admin_init', array( $this->settings, 'registerSettings' ) );

		$this->settings->register();

		$this->addToAssertionCount( 1 );
	}

	// ── registerSettings() ────────────────────────────────────────────────────

	/** @test */
	public function register_settings_calls_register_setting_with_correct_args(): void {
		WP_Mock::userFunction(
			'register_setting',
			array(
				'times' => 1,
				'args'  => array( PluginSettings::OPTIONS_GROUP, PluginSettings::OPTION_KEY, \Mockery::any() ),
			)
		);

		$this->settings->registerSettings();

		$this->addToAssertionCount( 1 );
	}
}
